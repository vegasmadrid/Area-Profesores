<?php
/**
 * Shortcode Management Class
 * Handles [area_profesores] and [comedorhoy] shortcodes.
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
        $login_url = wp_login_url(get_permalink());
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

        // Formatted date string in local language (e.g. "Jueves, 5 de Noviembre de 2026")
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
}
