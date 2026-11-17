<?php
/**
 * Shortcode Management Class
 * Handles [area_profesores], [comedorhoy], and [loginprofesores] shortcodes.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Shortcode {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Teacher portal shortcode (restricted to authorized teachers)
        add_shortcode('area_profesores', array($this, 'render_shortcode'));

        // Kitchen dining room service shortcodes (publicly accessible)
        add_shortcode('comedorhoy', array($this, 'render_comedor_hoy'));
        add_shortcode('comedor_hoy', array($this, 'render_comedor_hoy'));
        add_shortcode('serviciosdehoy', array($this, 'render_comedor_hoy'));

        // Corporate Login & Access Request shortcode (publicly accessible)
        add_shortcode('loginprofesores', array($this, 'render_login_profesores'));
        add_shortcode('login_profesores', array($this, 'render_login_profesores'));

        // Automatic injection: the pages selected in "Área Profesores > Ajustes"
        // render the corresponding screen without needing any shortcode.
        // Late priority so wpautop/page builders do not alter the generated HTML.
        add_filter('the_content', array($this, 'inject_page_content'), 999);
    }

    /**
     * Map of configured page ID => render callback.
     */
    private function get_injected_pages() {
        $map = array();

        $pages = array(
            'login_page_id'   => 'render_login_profesores',
            'area_page_id'    => 'render_shortcode',
            'comedor_page_id' => 'render_comedor_hoy',
        );

        foreach ($pages as $setting => $callback) {
            $page_id = absint(Area_Profesores_Admin::get_setting($setting, 0));
            if ($page_id && !isset($map[$page_id])) {
                $map[$page_id] = $callback;
            }
        }

        return $map;
    }

    /**
     * Replace the content of the configured pages with the plugin screens.
     */
    public function inject_page_content($content) {
        if (is_admin() || is_feed() || !is_singular() || doing_filter('get_the_excerpt')) {
            return $content;
        }

        $post_id = get_the_ID();
        if (!$post_id || (int) $post_id !== (int) get_queried_object_id()) {
            return $content;
        }

        $map = $this->get_injected_pages();
        if (!isset($map[$post_id])) {
            return $content;
        }

        // Render only once per request (some themes/builders call the_content several times).
        static $rendered = array();
        if (isset($rendered[$post_id])) {
            return $rendered[$post_id];
        }

        $callback = $map[$post_id];
        $rendered[$post_id] = $this->$callback();

        return $rendered[$post_id];
    }

    /**
     * Render the [area_profesores] shortcode (Restricted to logged-in teachers)
     */
    public function render_shortcode($atts = array()) {
        // Enqueue frontend CSS and JS
        wp_enqueue_style('area-profesores-frontend');
        wp_enqueue_script('area-profesores-frontend');

        // Check 1: User is logged in?
        if (!is_user_logged_in()) {
            return $this->render_restricted_view('not_logged_in');
        }

        $user_id = get_current_user_id();

        // Check 2: User is marked as a teacher?
        if (!Area_Profesores_Admin::is_user_profesor($user_id)) {
            return $this->render_restricted_view('not_a_teacher');
        }

        // User is an authorized teacher!
        return $this->render_teacher_portal($user_id);
    }

    /**
     * Render Restricted Access View
     */
    private function render_restricted_view($reason) {
        ob_start();
        $login_url = Area_Profesores_Access::get_login_url(get_permalink());

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/restricted.php';
        return ob_get_clean();
    }

    /**
     * Render Teacher Portal Dashboard
     */
    private function render_teacher_portal($user_id) {
        $user = get_userdata($user_id);
        $tutoria = Area_Profesores_Admin::get_user_tutoria($user_id);
        $available_classes = Area_Profesores_Comedor::get_available_classes();
        $available_stages = Area_Profesores_Comedor::get_available_stages();
        $today = current_time('Y-m-d');
        $today_formatted = date_i18n(get_option('date_format'), strtotime($today));

        // Get teacher's existing submissions for today
        $today_records = Area_Profesores_Comedor::get_records_by_user_and_date($user_id, $today);

        // Useful links, notice for teachers and logout URL
        $enlaces = Area_Profesores_Enlaces::get_for_user($user_id);
        $aviso = trim((string) Area_Profesores_Admin::get_setting('aviso_profesores', ''));
        $logout_url = Area_Profesores_Access::get_logout_url();

        // Comunicados a familias (Google Chat), only for Dirección
        $is_direccion = Area_Profesores_Chat::is_user_direccion($user_id);
        $chat_grupos = $is_direccion ? Area_Profesores_Chat::get_espacios_agrupados() : array();
        $logo_url = Area_Profesores_Admin::get_setting('logo_url', '');

        ob_start();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/dashboard.php';
        return ob_get_clean();
    }

    /**
     * Render [comedorhoy] shortcode (Public view for kitchen & school)
     */
    public function render_comedor_hoy($atts = array()) {
        wp_enqueue_style('area-profesores-frontend');
        wp_enqueue_script('area-profesores-frontend');

        $today = current_time('Y-m-d');
        $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? sanitize_text_field(wp_unslash($_GET['fecha'])) : $today;

        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = $today;
        }

        // Previous and next date calculation
        $prev_date = date('Y-m-d', strtotime($fecha . ' -1 day'));
        $next_date = date('Y-m-d', strtotime($fecha . ' +1 day'));

        // Formatted date string in local language
        $fecha_timestamp = strtotime($fecha);
        $fecha_formatted = date_i18n(get_option('date_format'), $fecha_timestamp);
        $day_of_week = date_i18n('l', $fecha_timestamp);
        $fecha_formatted = $day_of_week . ', ' . $fecha_formatted;

        // Retrieve data
        $records = Area_Profesores_Comedor::get_records_by_date($fecha);
        $summary = Area_Profesores_Comedor::get_summary_by_date($fecha);

        // Extract diet and allergy notice lists for instant kitchen overview
        $dietas_list = array();
        $alergicos_faltan_list = array();

        if (!empty($records)) {
            foreach ($records as $r) {
                if (!empty(trim($r->dietas))) {
                    $dietas_list[] = array(
                        'clase' => $r->clase,
                        'etapa' => $r->etapa,
                        'texto' => $r->dietas,
                    );
                }
                if (!empty(trim($r->alergicos_faltan))) {
                    $alergicos_faltan_list[] = array(
                        'clase' => $r->clase,
                        'etapa' => $r->etapa,
                        'texto' => $r->alergicos_faltan,
                    );
                }
            }
        }

        ob_start();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/servicios-de-hoy.php';
        return ob_get_clean();
    }

    /**
     * Render [loginprofesores] shortcode (Corporate login & access request)
     */
    public function render_login_profesores($atts = array()) {
        wp_enqueue_style('area-profesores-frontend');
        wp_enqueue_script('area-profesores-frontend');

        $is_logged_in = is_user_logged_in();
        $user_id = $is_logged_in ? get_current_user_id() : 0;
        $is_profesor = $is_logged_in ? Area_Profesores_Admin::is_user_profesor($user_id) : false;

        // Start page after login (configurable in Ajustes)
        $area_url = Area_Profesores_Access::get_start_url();

        $tribanda_url = Area_Profesores_Admin::get_setting('tribanda_url', 'https://profesores.casadelavirgen.es/wp-content/uploads/2021/04/Tribanda-corporativa-SUP-e1611846970284-1536x130-1-1024x87.png');
        $logo_url = Area_Profesores_Admin::get_setting('logo_url', 'https://profesores.casadelavirgen.es/wp-content/uploads/2019/06/cropped-Logo_Color_MARGENES-2.png');
        $login_button_text = Area_Profesores_Admin::get_setting('login_button_text', 'Accede usando tu cuenta de profesor');
        $allowed_domain = Area_Profesores_Admin::get_setting('allowed_domain', 'casadelavirgen.es');
        $available_classes = Area_Profesores_Comedor::get_available_classes();

        // Target URL after Nextend Social Login
        // Target after login: the configured start page, or the page the visitor
        // originally requested (only same-site URLs, prevents open redirects).
        $requested = !empty($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        $redirect_target = Area_Profesores_Access::resolve_login_redirect($requested);
        $google_login_url = Area_Profesores_Solicitudes::get_nextend_google_login_url($redirect_target);

        ob_start();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/login-profesores.php';
        return ob_get_clean();
    }
}
