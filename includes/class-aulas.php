<?php
/**
 * Aulas
 *
 * "Área Profesores > Aulas": one row per course with its tutor/a and its family
 * Chat space, editable in one screen. Also:
 * - "Pasar de curso": moves every family space to the next course after showing
 *   a preview (and optionally renames the spaces in Google Chat). Can be undone.
 * - Reading and renaming the spaces in Google Chat (optional API connection).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Aulas {

    const BACKUP_OPTION = 'ap_cambio_curso_backup';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'), 12);
        add_action('admin_post_ap_save_aulas', array($this, 'handle_save'));
        add_action('admin_post_ap_pasar_curso', array($this, 'handle_pasar_curso'));
        add_action('admin_post_ap_deshacer_curso', array($this, 'handle_deshacer'));
        add_action('admin_post_ap_leer_nombres', array($this, 'handle_leer_nombres'));
    }

    public function register_admin_menu() {
        add_submenu_page(
            'area-profesores',
            __('Aulas', 'area-profesores'),
            __('Aulas', 'area-profesores'),
            'manage_options',
            'area-profesores-aulas',
            array($this, 'render_admin_page')
        );
    }

    private static function url($args = array()) {
        return add_query_arg($args, admin_url('admin.php?page=area-profesores-aulas'));
    }

    private static function flash($text, $type = 'success') {
        set_transient('ap_aulas_flash_' . get_current_user_id(), array('type' => $type, 'text' => $text), 120);
    }

    private static function check() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
    }

    /* ---------------------------------------------------------------------
     * Datos
     * ------------------------------------------------------------------- */

    /**
     * Teachers (users marked as profesor), by name.
     */
    public static function get_profesores() {
        return get_users(array(
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ));
    }

    /**
     * course key => array of WP_User tutors.
     */
    public static function get_tutores_por_curso() {
        $out = array();
        $users = get_users(array(
            'meta_key'     => Area_Profesores_Cursos::META_CURSO,
            'meta_value'   => '',
            'meta_compare' => '!=',
            'orderby'      => 'display_name',
        ));
        foreach ($users as $u) {
            $curso = (string) get_user_meta($u->ID, Area_Profesores_Cursos::META_CURSO, true);
            if (Area_Profesores_Cursos::get_curso($curso)) {
                $out[$curso][] = $u;
            }
        }
        return $out;
    }

    /**
     * Rows of the Aulas table, grouped by etapa.
     */
    public static function get_aulas() {
        $tutores = self::get_tutores_por_curso();
        $espacios = Area_Profesores_Chat::get_espacios();
        $por_curso = array();
        foreach ($espacios as $e) {
            if ($e['curso'] !== '') {
                $por_curso[$e['curso']][] = $e;
            }
        }

        $out = array();
        foreach (Area_Profesores_Cursos::get_cursos_por_etapa() as $etapa => $cursos) {
            foreach ($cursos as $key => $c) {
                $out[$etapa][$key] = array(
                    'curso'    => $c,
                    'tutores'  => isset($tutores[$key]) ? $tutores[$key] : array(),
                    'espacios' => isset($por_curso[$key]) ? $por_curso[$key] : array(),
                );
            }
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Pantalla
     * ------------------------------------------------------------------- */

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $flash = get_transient('ap_aulas_flash_' . get_current_user_id());
        if ($flash) {
            delete_transient('ap_aulas_flash_' . get_current_user_id());
        }

        $google = Area_Profesores_Google::get_settings();
        $google_connected = Area_Profesores_Google::is_connected();
        $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';

        if ($view === 'pasar' || $view === 'renombrar') {
            $modo = $view;
            $filas = $this->get_filas_cambio($modo);
            $cursos_vacios = $this->get_cursos_vacios_tras_cambio($filas);
            include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-pasar-curso.php';
            return;
        }

        $aulas = self::get_aulas();
        $profesores = self::get_profesores();
        $espacios_promocion = array_filter(Area_Profesores_Chat::get_espacios(), function ($e) {
            return $e['tipo'] === 'promocion' && !$e['archivada'];
        });
        $sin_curso = array_filter($espacios_promocion, function ($e) {
            return $e['curso'] === '';
        });
        $backup = get_option(self::BACKUP_OPTION);

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-aulas.php';
    }

    /**
     * Rows for the "Pasar de curso" / "Renombrar" preview.
     */
    private function get_filas_cambio($modo) {
        $filas = array();
        foreach (Area_Profesores_Chat::get_espacios() as $e) {
            if ($e['tipo'] !== 'promocion' || $e['archivada']) {
                continue;
            }
            if ($modo === 'renombrar' && $e['curso'] === '') {
                continue;
            }
            if ($modo === 'pasar' && $e['curso'] === '') {
                continue; // Nothing to move.
            }
            $nuevo = $modo === 'pasar' ? Area_Profesores_Cursos::get_siguiente_curso($e['curso']) : $e['curso'];
            $filas[] = array(
                'espacio'      => $e,
                'nuevo_curso'  => $nuevo,
                'nuevo_nombre' => $nuevo !== '' ? Area_Profesores_Google::format_nombre($nuevo, $e['promocion'], $e['letra']) : '',
            );
        }
        return $filas;
    }

    /**
     * Courses that will have no family space after the change (e.g. 1 año).
     */
    private function get_cursos_vacios_tras_cambio($filas) {
        $con = array();
        foreach ($filas as $f) {
            if ($f['nuevo_curso'] !== '') {
                $con[$f['nuevo_curso']] = true;
            }
        }
        $vacios = array();
        foreach (Area_Profesores_Cursos::get_cursos() as $key => $c) {
            if (!isset($con[$key])) {
                $vacios[] = $c['label'];
            }
        }
        return $vacios;
    }

    /* ---------------------------------------------------------------------
     * Guardar la tabla de aulas
     * ------------------------------------------------------------------- */

    public function handle_save() {
        self::check();
        check_admin_referer('ap_save_aulas');

        $tutor_sel = isset($_POST['tutor']) && is_array($_POST['tutor']) ? array_map('absint', wp_unslash($_POST['tutor'])) : array();
        $espacio_sel = isset($_POST['espacio']) && is_array($_POST['espacio']) ? array_map('sanitize_key', wp_unslash($_POST['espacio'])) : array();
        $cursos = Area_Profesores_Cursos::get_cursos();
        $errores = array();

        // --- Tutores: one course per teacher.
        $usado = array();
        foreach ($tutor_sel as $curso => $uid) {
            if (!isset($cursos[$curso]) || !$uid) {
                continue;
            }
            if (isset($usado[$uid])) {
                $u = get_userdata($uid);
                $errores[] = sprintf(__('%1$s estaba elegido como tutor/a de %2$s y de %3$s; solo se ha guardado %2$s.', 'area-profesores'), $u ? $u->display_name : $uid, $cursos[$usado[$uid]]['label'], $cursos[$curso]['label']);
                unset($tutor_sel[$curso]);
                continue;
            }
            $usado[$uid] = $curso;
        }

        $actuales = self::get_tutores_por_curso();
        foreach ($cursos as $curso => $c) {
            if (!array_key_exists($curso, $tutor_sel)) {
                continue;
            }
            // Previous tutors no longer chosen for any course become specialists of their etapa.
            foreach (isset($actuales[$curso]) ? $actuales[$curso] : array() as $u) {
                if (!isset($usado[$u->ID])) {
                    Area_Profesores_Cursos::set_asignacion($u->ID, 'esp:' . $c['etapa']);
                }
            }
        }
        foreach ($usado as $uid => $curso) {
            Area_Profesores_Cursos::set_asignacion($uid, $curso);
        }

        // --- Espacios: one course per space.
        $espacios = Area_Profesores_Chat::get_espacios();
        $nuevo_curso_de = array();
        foreach ($espacio_sel as $curso => $eid) {
            if (!isset($cursos[$curso]) || $eid === '' || !isset($espacios[$eid])) {
                continue;
            }
            if (isset($nuevo_curso_de[$eid])) {
                $errores[] = sprintf(__('El espacio "%1$s" estaba elegido para %2$s y para %3$s; solo se ha guardado %2$s.', 'area-profesores'), $espacios[$eid]['subtitulo'] ?: $espacios[$eid]['titulo'], $cursos[$nuevo_curso_de[$eid]]['label'], $cursos[$curso]['label']);
                continue;
            }
            $nuevo_curso_de[$eid] = $curso;
        }

        foreach ($espacios as $eid => $e) {
            if ($e['tipo'] !== 'promocion') {
                continue;
            }
            if (isset($nuevo_curso_de[$eid])) {
                $nuevo = $nuevo_curso_de[$eid];
            } elseif ($e['curso'] !== '' && array_key_exists($e['curso'], $espacio_sel)) {
                $nuevo = ''; // Its course now has another space (or none).
            } else {
                continue;
            }
            if ($nuevo !== $e['curso']) {
                Area_Profesores_Chat::update_espacio($eid, array('curso' => $nuevo));
            }
        }

        self::flash(
            empty($errores) ? __('Aulas guardadas.', 'area-profesores') : __('Aulas guardadas con avisos: ', 'area-profesores') . implode(' ', $errores),
            empty($errores) ? 'success' : 'warning'
        );
        wp_safe_redirect(self::url());
        exit;
    }

    /* ---------------------------------------------------------------------
     * Pasar de curso / renombrar
     * ------------------------------------------------------------------- */

    public function handle_pasar_curso() {
        self::check();
        check_admin_referer('ap_pasar_curso');

        $modo = isset($_POST['modo']) && $_POST['modo'] === 'renombrar' ? 'renombrar' : 'pasar';
        $nuevos = isset($_POST['nuevo_curso']) && is_array($_POST['nuevo_curso']) ? wp_unslash($_POST['nuevo_curso']) : array();
        $nombres = isset($_POST['nuevo_nombre']) && is_array($_POST['nuevo_nombre']) ? wp_unslash($_POST['nuevo_nombre']) : array();
        $renombrar = !empty($_POST['renombrar']) && Area_Profesores_Google::is_connected();
        $espacios = Area_Profesores_Chat::get_espacios();
        $archivadas = array();

        if ($modo === 'pasar') {
            // Snapshot to allow undoing the change.
            update_option(self::BACKUP_OPTION, array(
                'fecha'    => time(),
                'usuario'  => get_current_user_id(),
                'espacios' => Area_Profesores_Chat::export_raw_espacios(),
            ), false);

            foreach ($nuevos as $eid => $curso) {
                $eid = sanitize_key($eid);
                $curso = sanitize_text_field($curso);
                if (!isset($espacios[$eid]) || $espacios[$eid]['tipo'] !== 'promocion') {
                    continue;
                }
                $nuevo = Area_Profesores_Cursos::get_curso($curso) ? $curso : '';
                $cambios = array('curso' => $nuevo);
                if ($nuevo === '') {
                    // The promotion leaves the school: keep it in "Promociones anteriores",
                    // remembering its last course ("2º Bachillerato (Promoción 2027)").
                    $cambios['archivada'] = true;
                    $cambios['nombre'] = $espacios[$eid]['titulo']; // Last course, e.g. "2º Bachillerato".
                    $archivadas[] = $espacios[$eid]['subtitulo'];
                }
                Area_Profesores_Chat::update_espacio($eid, $cambios);
            }
        }

        $ok = 0;
        $errores = array();
        if ($renombrar) {
            foreach ($nombres as $eid => $nombre) {
                $eid = sanitize_key($eid);
                $nombre = sanitize_text_field($nombre);
                if (!isset($espacios[$eid]) || $nombre === '' || $espacios[$eid]['space_id'] === '' || $nombre === $espacios[$eid]['nombre_chat']) {
                    continue;
                }
                $r = Area_Profesores_Google::rename_space($espacios[$eid]['space_id'], $nombre);
                if (is_wp_error($r)) {
                    $errores[] = $espacios[$eid]['subtitulo'] . ': ' . $r->get_error_message();
                } else {
                    $ok++;
                    Area_Profesores_Chat::update_espacio($eid, array('nombre_chat' => $nombre));
                }
            }
        }

        $msg = $modo === 'pasar' ? __('Cambio de curso aplicado.', 'area-profesores') : '';
        if ($archivadas) {
            $msg .= ' ' . sprintf(__('Pasan a "Promociones anteriores": %s.', 'area-profesores'), implode(', ', $archivadas));
        }
        if ($renombrar) {
            $msg .= ' ' . sprintf(_n('%d espacio renombrado en Google Chat.', '%d espacios renombrados en Google Chat.', $ok, 'area-profesores'), $ok);
        }
        if ($errores) {
            $msg .= ' ' . __('Errores:', 'area-profesores') . ' ' . implode(' · ', $errores);
        }
        self::flash(trim($msg) !== '' ? trim($msg) : __('No había nada que cambiar.', 'area-profesores'), $errores ? 'warning' : 'success');
        wp_safe_redirect(self::url());
        exit;
    }

    public function handle_deshacer() {
        self::check();
        check_admin_referer('ap_deshacer_curso');

        $backup = get_option(self::BACKUP_OPTION);
        if (!is_array($backup) || empty($backup['espacios'])) {
            self::flash(__('No hay ningún cambio de curso que deshacer.', 'area-profesores'), 'error');
        } else {
            // Keep the webhooks/names changed since then; only restore the courses.
            $actual = Area_Profesores_Chat::export_raw_espacios();
            foreach ($actual as $eid => $e) {
                if (isset($backup['espacios'][$eid])) {
                    foreach (array('curso' => '', 'archivada' => false, 'nombre' => '') as $campo => $defecto) {
                        $actual[$eid][$campo] = isset($backup['espacios'][$eid][$campo]) ? $backup['espacios'][$eid][$campo] : $defecto;
                    }
                }
            }
            Area_Profesores_Chat::restore_raw_espacios($actual);
            delete_option(self::BACKUP_OPTION);
            self::flash(__('Se ha deshecho el último cambio de curso. Los nombres en Google Chat no se cambian solos: usa "Renombrar espacios en Google Chat" si hace falta.', 'area-profesores'));
        }
        wp_safe_redirect(self::url());
        exit;
    }

    /**
     * Read the current names of the spaces from Google Chat.
     */
    public function handle_leer_nombres() {
        self::check();
        check_admin_referer('ap_leer_nombres');

        if (!Area_Profesores_Google::is_connected()) {
            self::flash(__('Conecta primero con Google Chat.', 'area-profesores'), 'error');
            wp_safe_redirect(self::url());
            exit;
        }

        $ok = 0;
        $errores = array();
        foreach (Area_Profesores_Chat::get_espacios() as $eid => $e) {
            if ($e['space_id'] === '') {
                continue;
            }
            $r = Area_Profesores_Google::get_space($e['space_id']);
            if (is_wp_error($r)) {
                $errores[] = $e['titulo'] . ': ' . $r->get_error_message();
                if (count($errores) >= 3 && $ok === 0) {
                    break; // Probably a permissions problem: do not try all of them.
                }
                continue;
            }
            Area_Profesores_Chat::update_espacio($eid, array('nombre_chat' => isset($r['displayName']) ? sanitize_text_field($r['displayName']) : ''));
            $ok++;
        }

        $msg = sprintf(_n('Leído el nombre de %d espacio.', 'Leídos los nombres de %d espacios.', $ok, 'area-profesores'), $ok);
        if ($errores) {
            $msg .= ' ' . __('Errores:', 'area-profesores') . ' ' . implode(' · ', $errores);
        }
        self::flash($msg, $errores ? 'warning' : 'success');
        wp_safe_redirect(self::url());
        exit;
    }

    /**
     * Data of the tutor of a course for the cards: name, photo URL and initials.
     */
    public static function get_tutor_card($users) {
        if (empty($users)) {
            return null;
        }
        $u = $users[0];
        $partes = preg_split('/\s+/', trim($u->display_name));
        $iniciales = mb_strtoupper(mb_substr($partes[0], 0, 1) . (isset($partes[1]) ? mb_substr($partes[1], 0, 1) : ''));
        return array(
            'nombre'    => $u->display_name,
            // default=404: without a real photo the image fails and the initials are shown.
            'foto'      => get_avatar_url($u->ID, array('size' => 96, 'default' => '404')),
            'iniciales' => $iniciales,
        );
    }

    /**
     * Family Chat spaces for the "Espacios de aula" tab of the teachers' home screen.
     *
     * @return array { grupos, anteriores, tutores, mi_espacio, total }
     */
    public static function get_espacios_para_profesores($user_id) {
        $todos = array_filter(Area_Profesores_Chat::get_espacios(), function ($e) {
            return $e['activo'] && $e['chat_url'] !== '';
        });
        $actuales = array_filter($todos, function ($e) {
            return !$e['archivada'] && ($e['tipo'] === 'otro' || $e['curso'] !== '');
        });
        $anteriores = array_values(array_filter($todos, function ($e) {
            return $e['archivada'];
        }));

        // The space of the teacher's own class (tutors), shown first.
        $mi_curso = (string) get_user_meta($user_id, Area_Profesores_Cursos::META_CURSO, true);
        $mi_espacio = null;
        if ($mi_curso !== '') {
            foreach ($actuales as $e) {
                if ($e['curso'] === $mi_curso) {
                    $mi_espacio = $e;
                    break;
                }
            }
        }

        return array(
            'grupos'     => Area_Profesores_Chat::agrupar($actuales),
            'anteriores' => $anteriores,
            'tutores'    => self::get_tutores_por_curso(),
            'mi_espacio' => $mi_espacio,
            'total'      => count($actuales),
        );
    }
}
