<?php
/**
 * Enlaces Útiles
 * Links managed from "Área Profesores > Enlaces Útiles" and shown to teachers
 * in the "Enlaces Útiles" tab of the teacher portal.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Enlaces {

    const OPTION = 'ap_enlaces';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'), 20);
        add_action('admin_post_ap_save_enlace', array($this, 'handle_save'));
        add_action('admin_post_ap_delete_enlace', array($this, 'handle_delete'));
        add_action('admin_post_ap_toggle_enlace', array($this, 'handle_toggle'));
    }

    /* ---------------------------------------------------------------------
     * Data
     * ------------------------------------------------------------------- */

    /**
     * Audience options for a link.
     */
    public static function get_audiences() {
        $aud = array(
            'todos'        => __('Todos los profesores', 'area-profesores'),
            'tutores'      => __('Solo tutores/as', 'area-profesores'),
            'especialistas'=> __('Solo especialistas (sin tutoría)', 'area-profesores'),
        );
        foreach (Area_Profesores_Cursos::get_etapas() as $key => $label) {
            $aud['etapa_' . $key] = sprintf(__('Solo profesores de %s', 'area-profesores'), $label);
        }
        return $aud;
    }

    /**
     * All stored links (normalized), ordered by "orden" then title.
     */
    public static function get_all() {
        $links = get_option(self::OPTION, array());
        if (!is_array($links)) {
            $links = array();
        }

        $out = array();
        foreach ($links as $id => $link) {
            $out[$id] = self::normalize(array_merge((array) $link, array('id' => $id)));
        }

        uasort($out, function ($a, $b) {
            if ($a['orden'] === $b['orden']) {
                return strcasecmp($a['titulo'], $b['titulo']);
            }
            return $a['orden'] - $b['orden'];
        });

        return $out;
    }

    public static function get($id) {
        $all = self::get_all();
        return isset($all[$id]) ? $all[$id] : null;
    }

    private static function normalize($link) {
        return array(
            'id'          => isset($link['id']) ? sanitize_key($link['id']) : '',
            'titulo'      => isset($link['titulo']) ? (string) $link['titulo'] : '',
            'url'         => isset($link['url']) ? (string) $link['url'] : '',
            'descripcion' => isset($link['descripcion']) ? (string) $link['descripcion'] : '',
            'categoria'   => isset($link['categoria']) ? (string) $link['categoria'] : '',
            'icono'       => isset($link['icono']) ? (string) $link['icono'] : '',
            'audiencia'   => isset($link['audiencia']) && array_key_exists($link['audiencia'], self::get_audiences()) ? $link['audiencia'] : 'todos',
            'destacado'   => !empty($link['destacado']),
            'nueva_pestana' => !isset($link['nueva_pestana']) || !empty($link['nueva_pestana']),
            'activo'      => !isset($link['activo']) || !empty($link['activo']),
            'orden'       => isset($link['orden']) ? (int) $link['orden'] : 0,
        );
    }

    private static function save_all($links) {
        $clean = array();
        foreach ($links as $id => $link) {
            $link = self::normalize($link);
            unset($link['id']);
            $clean[$id] = $link;
        }
        update_option(self::OPTION, $clean, false);
    }

    /**
     * Distinct categories in use (for the datalist in the admin form).
     */
    public static function get_categories() {
        $cats = array();
        foreach (self::get_all() as $link) {
            if ($link['categoria'] !== '') {
                $cats[$link['categoria']] = true;
            }
        }
        $cats = array_keys($cats);
        natcasesort($cats);
        return array_values($cats);
    }

    /**
     * Active links visible for a given teacher, grouped by category.
     * Featured links are returned separately.
     *
     * @return array { 'destacados' => array, 'categorias' => array( 'Nombre' => array(links) ), 'total' => int }
     */
    public static function get_for_user($user_id) {
        $has_tutoria = $user_id ? (Area_Profesores_Admin::get_user_tutoria($user_id) !== '') : false;
        $etapa = $user_id ? Area_Profesores_Cursos::get_asignacion($user_id)['etapa'] : '';
        $is_admin = $user_id ? user_can($user_id, 'manage_options') : false;

        $destacados = array();
        $categorias = array();
        $total = 0;

        foreach (self::get_all() as $link) {
            if (!$link['activo'] || $link['url'] === '' || $link['titulo'] === '') {
                continue;
            }
            if (!$is_admin) {
                if ($link['audiencia'] === 'tutores' && !$has_tutoria) {
                    continue;
                }
                if ($link['audiencia'] === 'especialistas' && $has_tutoria) {
                    continue;
                }
                if (strpos($link['audiencia'], 'etapa_') === 0 && $link['audiencia'] !== 'etapa_' . $etapa) {
                    continue;
                }
            }

            $total++;
            if ($link['destacado']) {
                $destacados[] = $link;
                continue;
            }
            $cat = $link['categoria'] !== '' ? $link['categoria'] : __('General', 'area-profesores');
            $categorias[$cat][] = $link;
        }

        uksort($categorias, 'strnatcasecmp');

        return array(
            'destacados' => $destacados,
            'categorias' => $categorias,
            'total'      => $total,
        );
    }

    /* ---------------------------------------------------------------------
     * Admin
     * ------------------------------------------------------------------- */

    public function register_admin_menu() {
        add_submenu_page(
            'area-profesores',
            __('Enlaces Útiles', 'area-profesores'),
            __('Enlaces Útiles', 'area-profesores'),
            'manage_options',
            'area-profesores-enlaces',
            array($this, 'render_admin_page')
        );
    }

    private function admin_url($args = array()) {
        return add_query_arg($args, admin_url('admin.php?page=area-profesores-enlaces'));
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $links = self::get_all();
        $categories = self::get_categories();
        $audiences = self::get_audiences();

        $editing = null;
        if (!empty($_GET['edit'])) {
            $editing = self::get(sanitize_key(wp_unslash($_GET['edit'])));
        }

        $message = isset($_GET['ap_msg']) ? sanitize_key(wp_unslash($_GET['ap_msg'])) : '';

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-enlaces.php';
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_save_enlace');

        $id = isset($_POST['enlace_id']) ? sanitize_key(wp_unslash($_POST['enlace_id'])) : '';
        $titulo = isset($_POST['titulo']) ? sanitize_text_field(wp_unslash($_POST['titulo'])) : '';
        $url = isset($_POST['url']) ? trim(wp_unslash($_POST['url'])) : '';

        // Allow URLs written without protocol ("www.ejemplo.com").
        if ($url !== '' && !preg_match('#^(https?|mailto|tel):#i', $url) && strpos($url, '/') !== 0) {
            $url = 'https://' . $url;
        }
        $url = esc_url_raw($url, array('http', 'https', 'mailto', 'tel'));

        if ($titulo === '' || $url === '') {
            wp_safe_redirect($this->admin_url(array('ap_msg' => 'missing', 'edit' => $id)));
            exit;
        }

        $links = get_option(self::OPTION, array());
        if (!is_array($links)) {
            $links = array();
        }

        $is_new = ($id === '' || !isset($links[$id]));
        if ($is_new) {
            $id = 'l' . strtolower(wp_generate_password(10, false, false));
        }

        $orden = isset($_POST['orden']) && $_POST['orden'] !== '' ? (int) $_POST['orden'] : ($is_new ? (count($links) + 1) * 10 : 0);

        $links[$id] = array(
            'titulo'        => $titulo,
            'url'           => $url,
            'descripcion'   => isset($_POST['descripcion']) ? sanitize_textarea_field(wp_unslash($_POST['descripcion'])) : '',
            'categoria'     => isset($_POST['categoria']) ? sanitize_text_field(wp_unslash($_POST['categoria'])) : '',
            'icono'         => isset($_POST['icono']) ? mb_substr(sanitize_text_field(wp_unslash($_POST['icono'])), 0, 4) : '',
            'audiencia'     => isset($_POST['audiencia']) ? sanitize_key(wp_unslash($_POST['audiencia'])) : 'todos',
            'destacado'     => !empty($_POST['destacado']),
            'nueva_pestana' => !empty($_POST['nueva_pestana']),
            'activo'        => !empty($_POST['activo']),
            'orden'         => $orden,
        );

        self::save_all($links);

        wp_safe_redirect($this->admin_url(array('ap_msg' => $is_new ? 'added' : 'updated')));
        exit;
    }

    public function handle_delete() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $id = isset($_GET['id']) ? sanitize_key(wp_unslash($_GET['id'])) : '';
        check_admin_referer('ap_delete_enlace_' . $id);

        $links = get_option(self::OPTION, array());
        if (is_array($links) && isset($links[$id])) {
            unset($links[$id]);
            self::save_all($links);
        }

        wp_safe_redirect($this->admin_url(array('ap_msg' => 'deleted')));
        exit;
    }

    public function handle_toggle() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $id = isset($_GET['id']) ? sanitize_key(wp_unslash($_GET['id'])) : '';
        check_admin_referer('ap_toggle_enlace_' . $id);

        $links = get_option(self::OPTION, array());
        if (is_array($links) && isset($links[$id])) {
            $current = self::normalize($links[$id]);
            $links[$id]['activo'] = !$current['activo'];
            self::save_all($links);
        }

        wp_safe_redirect($this->admin_url(array('ap_msg' => 'updated')));
        exit;
    }
}
