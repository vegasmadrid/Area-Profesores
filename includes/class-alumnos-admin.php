<?php
/**
 * Backend: "Área Profesores > Alumnos" (alumnos, familias, importar, historial)
 * and the teacher settings related to pupils (Secretaría, clases asignadas).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Alumnos_Admin {

    const SLUG = 'area-profesores-alumnos';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 11);
        foreach (array('ap_alumno_guardar', 'ap_alumno_borrar', 'ap_familia_guardar', 'ap_familia_borrar', 'ap_importar_subir', 'ap_importar_aplicar', 'ap_clases_guardar') as $a) {
            add_action('admin_post_' . $a, array($this, 'handle_' . substr($a, 3)));
        }
        add_action('wp_ajax_ap_admin_save_secretaria', array($this, 'ajax_save_secretaria'));

        // Clases asignadas in the user profile.
        add_action('show_user_profile', array($this, 'render_profile_clases'), 20);
        add_action('edit_user_profile', array($this, 'render_profile_clases'), 20);
        add_action('personal_options_update', array($this, 'save_profile_clases'));
        add_action('edit_user_profile_update', array($this, 'save_profile_clases'));
    }

    public function register_menu() {
        add_submenu_page('area-profesores', __('Alumnos y familias', 'area-profesores'), __('Alumnos y familias', 'area-profesores'), 'manage_options', self::SLUG, array($this, 'render'));
    }

    private static function url($args = array()) {
        return add_query_arg($args, admin_url('admin.php?page=' . self::SLUG));
    }

    private static function check($nonce) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer($nonce);
    }

    private static function flash($text, $type = 'success') {
        set_transient('ap_alumnos_flash_' . get_current_user_id(), array('type' => $type, 'text' => $text), 120);
    }

    /* ---------------------------------------------------------------------
     * Pantalla
     * ------------------------------------------------------------------- */

    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'alumnos';
        $flash = get_transient('ap_alumnos_flash_' . get_current_user_id());
        if ($flash) {
            delete_transient('ap_alumnos_flash_' . get_current_user_id());
        }
        $totales = Area_Profesores_Alumnos::contar();
        $cursos_por_etapa = Area_Profesores_Cursos::get_cursos_por_etapa();

        $data = array();
        if ($tab === 'alumnos') {
            $f_curso = isset($_GET['curso']) ? sanitize_text_field(wp_unslash($_GET['curso'])) : '';
            $f_estado = isset($_GET['estado']) ? sanitize_key($_GET['estado']) : 'alta';
            $f_q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
            $cursos = $f_curso;
            if (strpos($f_curso, 'etapa:') === 0) {
                $cursos = array_keys($cursos_por_etapa[substr($f_curso, 6)] ?? array());
            }
            $data['alumnos'] = Area_Profesores_Alumnos::buscar_alumnos(array('curso' => $cursos, 'estado' => $f_estado === 'todos' ? '' : $f_estado, 'busqueda' => $f_q));
            $data['familias_de'] = Area_Profesores_Alumnos::familias_de_alumnos(wp_list_pluck($data['alumnos'], 'id'));
            $data['filtros'] = compact('f_curso', 'f_estado', 'f_q');
            if (isset($_GET['editar'])) {
                $id = absint($_GET['editar']);
                $data['editar'] = $id ? Area_Profesores_Alumnos::get_alumno($id) : array('id' => 0, 'nombre' => '', 'email' => '', 'curso' => '', 'estado' => 'alta', 'fecha_alta' => '', 'notas' => '', 'alergias_alimentarias' => '', 'alergias_otras' => '');
                $data['editar_familias'] = $id ? (Area_Profesores_Alumnos::familias_de_alumnos(array($id))[$id] ?? array()) : array();
                $data['todas_familias'] = Area_Profesores_Alumnos::buscar_familias();
            }
        } elseif ($tab === 'familias') {
            $f_q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
            $data['familias'] = Area_Profesores_Alumnos::buscar_familias($f_q);
            foreach ($data['familias'] as &$ap_f) {
                $ap_f['contactos'] = Area_Profesores_Alumnos::get_contactos($ap_f['id']);
            }
            unset($ap_f);
            $data['hijos_de'] = Area_Profesores_Alumnos::alumnos_de_familias(wp_list_pluck($data['familias'], 'id'));
            $data['f_q'] = $f_q;
            if (isset($_GET['editar'])) {
                $id = absint($_GET['editar']);
                $data['editar'] = $id ? Area_Profesores_Alumnos::get_familia($id) : array('id' => 0, 'nombre' => '', 'emails' => '', 'telefono' => '', 'notas' => '', 'contactos' => array(), 'editado_por_familia' => null);
                $data['editar_hijos'] = $id ? (Area_Profesores_Alumnos::alumnos_de_familias(array($id))[$id] ?? array()) : array();
            }
        } elseif ($tab === 'importar') {
            $data['previa'] = get_transient('ap_importar_' . get_current_user_id());
        } elseif ($tab === 'historial') {
            global $wpdb;
            $data['historial'] = $wpdb->get_results('SELECT * FROM ' . Area_Profesores_Alumnos::t('alumnos_historial') . ' ORDER BY id DESC LIMIT 300', ARRAY_A);
        }

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-alumnos.php';
    }

    /* ---------------------------------------------------------------------
     * Alumnos
     * ------------------------------------------------------------------- */

    public function handle_alumno_guardar() {
        self::check('ap_alumno_guardar');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $nombre = isset($_POST['nombre']) ? trim(wp_unslash($_POST['nombre'])) : '';
        if ($nombre === '') {
            self::flash(__('El nombre del alumno es obligatorio.', 'area-profesores'), 'error');
            wp_safe_redirect(self::url(array('tab' => 'alumnos', 'editar' => $id)));
            exit;
        }
        $id = Area_Profesores_Alumnos::guardar_alumno(array(
            'nombre' => $nombre,
            'email'  => isset($_POST['email']) ? wp_unslash($_POST['email']) : '',
            'curso'  => isset($_POST['curso']) ? sanitize_text_field(wp_unslash($_POST['curso'])) : '',
            'estado' => isset($_POST['estado']) ? sanitize_key($_POST['estado']) : 'alta',
            'fecha_alta' => isset($_POST['fecha_alta']) ? sanitize_text_field(wp_unslash($_POST['fecha_alta'])) : '',
            'notas'  => isset($_POST['notas']) ? wp_unslash($_POST['notas']) : '',
            'alergias_alimentarias' => isset($_POST['alergias_alimentarias']) ? wp_unslash($_POST['alergias_alimentarias']) : '',
            'alergias_otras'        => isset($_POST['alergias_otras']) ? wp_unslash($_POST['alergias_otras']) : '',
        ), $id);

        foreach ((array) (isset($_POST['quitar_familia']) ? $_POST['quitar_familia'] : array()) as $fid) {
            Area_Profesores_Alumnos::desvincular($id, absint($fid));
        }
        $nueva = isset($_POST['anadir_familia']) ? absint($_POST['anadir_familia']) : 0;
        if ($nueva) {
            Area_Profesores_Alumnos::vincular($id, $nueva);
        }

        self::flash(__('Alumno guardado.', 'area-profesores'));
        wp_safe_redirect(self::url(array('tab' => 'alumnos', 'editar' => $id)));
        exit;
    }

    public function handle_alumno_borrar() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        self::check('ap_alumno_borrar_' . $id);
        Area_Profesores_Alumnos::borrar_alumno($id);
        self::flash(__('Alumno eliminado.', 'area-profesores'));
        wp_safe_redirect(self::url(array('tab' => 'alumnos')));
        exit;
    }

    /* ---------------------------------------------------------------------
     * Familias
     * ------------------------------------------------------------------- */

    public function handle_familia_guardar() {
        self::check('ap_familia_guardar');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $contactos = array();
        $nombres = isset($_POST['ct_nombre']) ? (array) wp_unslash($_POST['ct_nombre']) : array();
        $tels = isset($_POST['ct_telefono']) ? (array) wp_unslash($_POST['ct_telefono']) : array();
        $dirs = isset($_POST['ct_direccion']) ? (array) wp_unslash($_POST['ct_direccion']) : array();
        foreach ($nombres as $i => $n) {
            $contactos[] = array('nombre' => $n, 'telefono' => isset($tels[$i]) ? $tels[$i] : '', 'direccion' => isset($dirs[$i]) ? $dirs[$i] : '');
        }
        $id = Area_Profesores_Alumnos::guardar_familia(array(
            'nombre'    => isset($_POST['nombre']) ? wp_unslash($_POST['nombre']) : '',
            'emails'    => isset($_POST['emails']) ? wp_unslash($_POST['emails']) : '',
            'telefono'  => isset($_POST['telefono']) ? wp_unslash($_POST['telefono']) : '',
            'notas'     => isset($_POST['notas']) ? wp_unslash($_POST['notas']) : '',
            'contactos' => $contactos,
        ), $id);

        self::flash(__('Familia guardada.', 'area-profesores'));
        wp_safe_redirect(self::url(array('tab' => 'familias', 'editar' => $id)));
        exit;
    }

    public function handle_familia_borrar() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        self::check('ap_familia_borrar_' . $id);
        Area_Profesores_Alumnos::borrar_familia($id);
        self::flash(__('Familia eliminada (sus alumnos se mantienen).', 'area-profesores'));
        wp_safe_redirect(self::url(array('tab' => 'familias')));
        exit;
    }

    /* ---------------------------------------------------------------------
     * Importar
     * ------------------------------------------------------------------- */

    public function handle_importar_subir() {
        self::check('ap_importar_subir');
        $back = self::url(array('tab' => 'importar'));

        if (empty($_FILES['fichero']['tmp_name']) || !empty($_FILES['fichero']['error'])) {
            self::flash(__('Elige el Excel que quieres importar.', 'area-profesores'), 'error');
            wp_safe_redirect($back);
            exit;
        }
        $rows = Area_Profesores_Hoja_Calculo::leer($_FILES['fichero']['tmp_name'], sanitize_file_name($_FILES['fichero']['name']));
        if (is_wp_error($rows)) {
            self::flash($rows->get_error_message(), 'error');
            wp_safe_redirect($back);
            exit;
        }
        $parsed = Area_Profesores_Importador::parse_alexia($rows);
        if (empty($parsed['familias'])) {
            self::flash(__('No se han encontrado familias. El fichero tiene que ser el "Listado de familias" de Alexia (con los apartados "Familia:", "Padres/Tutores" e "Hijos").', 'area-profesores'), 'error');
            wp_safe_redirect($back);
            exit;
        }

        $detectados = Area_Profesores_Importador::detectar_grupos($parsed['familias']);
        $grupo = isset($_POST['grupo']) ? sanitize_key($_POST['grupo']) : 'auto';
        if (!array_key_exists($grupo, Area_Profesores_Importador::get_grupos())) {
            $grupo = (string) array_key_first($detectados);
        }

        set_transient('ap_importar_' . get_current_user_id(), array(
            'fichero'    => sanitize_file_name($_FILES['fichero']['name']),
            'familias'   => $parsed['familias'],
            'avisos'     => $parsed['avisos'],
            'grupo'      => $grupo,
            'detectados' => $detectados,
            'plan'       => Area_Profesores_Importador::plan($parsed['familias'], array($grupo)),
        ), HOUR_IN_SECONDS);

        wp_safe_redirect($back . '#previa');
        exit;
    }

    public function handle_importar_aplicar() {
        self::check('ap_importar_aplicar');
        $key = 'ap_importar_' . get_current_user_id();
        $p = get_transient($key);
        if (!$p) {
            self::flash(__('La vista previa ha caducado. Vuelve a subir el fichero.', 'area-profesores'), 'error');
            wp_safe_redirect(self::url(array('tab' => 'importar')));
            exit;
        }
        if (!empty($_POST['cancelar'])) {
            delete_transient($key);
            wp_safe_redirect(self::url(array('tab' => 'importar')));
            exit;
        }

        $bajas = array_map('absint', (array) (isset($_POST['bajas']) ? $_POST['bajas'] : array()));
        $r = Area_Profesores_Importador::plan($p['familias'], array($p['grupo']), true, $bajas);
        delete_transient($key);
        Area_Profesores_Alumnos::log('importacion', 'importacion', 0, 'importar', array(
            'fichero' => $p['fichero'], 'grupo' => $p['grupo'],
            'alumnos_nuevos' => count($r['alumnos_nuevos']), 'alumnos_cambios' => count($r['alumnos_cambios']),
            'familias_nuevas' => count($r['familias_nuevas']), 'familias_cambios' => count($r['familias_cambios']), 'bajas' => count($bajas),
        ));

        self::flash(sprintf(
            __('Importación aplicada: %1$d alumnos nuevos, %2$d alumnos actualizados, %3$d familias nuevas, %4$d familias actualizadas, %5$d bajas.', 'area-profesores'),
            count($r['alumnos_nuevos']), count($r['alumnos_cambios']), count($r['familias_nuevas']), count($r['familias_cambios']), count($bajas)
        ));
        wp_safe_redirect(self::url(array('tab' => 'importar')));
        exit;
    }

    /* ---------------------------------------------------------------------
     * Profesores: Secretaría y clases asignadas
     * ------------------------------------------------------------------- */

    public function ajax_save_secretaria() {
        check_ajax_referer('area_profesores_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Usuario no válido'));
        }
        update_user_meta($user_id, Area_Profesores_Alumnos::META_SECRETARIA, !empty($_POST['is_secretaria']) ? '1' : '0');
        wp_send_json_success(array('message' => __('Guardado', 'area-profesores')));
    }

    public function render_profile_clases($user) {
        if (!current_user_can('manage_options')) {
            return;
        }
        $asignadas = Area_Profesores_Alumnos::get_clases_asignadas($user->ID);
        ?>
        <h2 id="ap-clases"><?php esc_html_e('Clases asignadas (Área de Profesores)', 'area-profesores'); ?></h2>
        <p class="description"><?php esc_html_e('Para especialistas: cursos cuyos alumnos podrá consultar en "Mis alumnos". El tutor/a ya ve su propia clase sin marcarla.', 'area-profesores'); ?></p>
        <?php wp_nonce_field('ap_profile_clases', 'ap_profile_clases_nonce'); ?>
        <table class="form-table" role="presentation">
            <?php foreach (Area_Profesores_Cursos::get_cursos_por_etapa() as $etapa => $cursos) : ?>
                <tr>
                    <th><?php echo esc_html(Area_Profesores_Cursos::get_etapa_label($etapa)); ?></th>
                    <td>
                        <?php foreach ($cursos as $key => $c) : ?>
                            <label style="display:inline-block; min-width: 130px; margin: 0 8px 6px 0;"><input type="checkbox" name="ap_clases[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $asignadas, true)); ?>> <?php echo esc_html($c['label']); ?></label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    public function save_profile_clases($user_id) {
        if (!current_user_can('manage_options') || !isset($_POST['ap_profile_clases_nonce']) || !wp_verify_nonce(sanitize_key($_POST['ap_profile_clases_nonce']), 'ap_profile_clases')) {
            return;
        }
        Area_Profesores_Alumnos::set_clases_asignadas($user_id, isset($_POST['ap_clases']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['ap_clases'])) : array());
    }

    /**
     * Same from the "Profesores" table (dialog with the courses).
     */
    public function handle_clases_guardar() {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        self::check('ap_clases_guardar_' . $user_id);
        Area_Profesores_Alumnos::set_clases_asignadas($user_id, isset($_POST['ap_clases']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['ap_clases'])) : array());
        $back = wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=area-profesores');
        wp_safe_redirect(add_query_arg('ap_clases_ok', 1, $back));
        exit;
    }
}
