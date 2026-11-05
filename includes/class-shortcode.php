<?php
/**
 * Shortcode Management Class
 * Handles [area_profesores] shortcode rendering, access control, and routing.
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
        add_shortcode('area_profesores', array($this, 'render_shortcode'));
    }

    /**
     * Render the [area_profesores] shortcode
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
}
