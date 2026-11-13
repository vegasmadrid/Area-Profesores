<?php
/**
 * Admin Management Class
 * Handles backend menus under "Área Profesores":
 * - Profesores (Gestión de usuarios y tutorías)
 * - Solicitudes de Acceso (Aprobación y altas pendientes con badge)
 * - Comedor Diario (Control de cocina y exportación)
 * - Ajustes (Configuración institucional, login y Nextend Social Login)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menus'));

        // Backward compatibility redirects for older menu slugs
        add_action('admin_init', array($this, 'handle_legacy_admin_redirects'));

        // User profile fields integration
        add_action('show_user_profile', array($this, 'render_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'render_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));

        // AJAX handler for saving teacher status in admin table
        add_action('wp_ajax_ap_admin_save_teacher', array($this, 'ajax_save_teacher'));
    }

    /**
     * Get a setting with fallback default
     */
    public static function get_setting($key, $default = '') {
        $defaults = array(
            'allowed_domain'            => 'casadelavirgen.es',
            'tribanda_url'              => 'https://profesores.casadelavirgen.es/wp-content/uploads/2021/04/Tribanda-corporativa-SUP-e1611846970284-1536x130-1-1024x87.png',
            'logo_url'                  => 'https://profesores.casadelavirgen.es/wp-content/uploads/2019/06/cropped-Logo_Color_MARGENES-2.png',
            'login_button_text'         => 'Accede usando tu cuenta de profesor',
            'only_approved_can_register'=> 'yes',
            'notification_emails'       => get_option('admin_email'),
            'login_page_id'             => 0,
            'area_page_id'              => 0,
        );

        $settings = get_option('ap_settings', array());

        if (isset($settings[$key])) {
            return $settings[$key];
        }

        return isset($defaults[$key]) ? $defaults[$key] : $default;
    }

    /**
     * Update a single setting
     */
    public static function update_setting($key, $value) {
        $settings = get_option('ap_settings', array());
        $settings[$key] = $value;
        update_option('ap_settings', $settings);
    }

    /**
     * Check if a user is marked as teacher
     */
    public static function is_user_profesor($user_id) {
        if (!$user_id) {
            return false;
        }

        $is_teacher = get_user_meta($user_id, '_ap_is_profesor', true);
        if ($is_teacher === '1' || $is_teacher === 1 || $is_teacher === true) {
            return true;
        }

        // Also check if user has 'administrator' role
        $user = get_userdata($user_id);
        if ($user && in_array('administrator', (array) $user->roles, true)) {
            return true;
        }

        return false;
    }

    /**
     * Get user's assigned tutoría
     */
    public static function get_user_tutoria($user_id) {
        if (!$user_id) {
            return '';
        }
        return get_user_meta($user_id, '_ap_tutoria', true);
    }

    /**
     * Redirect legacy admin menu slugs (area-familias -> area-profesores)
     */
    public function handle_legacy_admin_redirects() {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ($page === 'area-familias' || $page === 'area-familias-profesores') {
            wp_safe_redirect(admin_url('admin.php?page=area-profesores'));
            exit;
        } elseif ($page === 'area-familias-comedor') {
            wp_safe_redirect(admin_url('admin.php?page=area-profesores-comedor'));
            exit;
        }
    }

    /**
     * Register Admin Menus under "Área Profesores"
     */
    public function register_admin_menus() {
        $pending_count = Area_Profesores_Solicitudes::get_pending_count();
        $badge = '';
        if ($pending_count > 0) {
            $badge = sprintf(' <span class="update-plugins count-%d"><span class="plugin-count">%d</span></span>', $pending_count, $pending_count);
        }

        // Main Menu: Área Profesores
        add_menu_page(
            __('Área Profesores', 'area-profesores'),
            __('Área Profesores', 'area-profesores') . $badge,
            'manage_options',
            'area-profesores',
            array($this, 'render_profesores_page'),
            'dashicons-welcome-learn-status',
            26
        );

        // Submenu: Profesores (claustro actual)
        add_submenu_page(
            'area-profesores',
            __('Profesores', 'area-profesores'),
            __('Profesores', 'area-profesores'),
            'manage_options',
            'area-profesores',
            array($this, 'render_profesores_page')
        );

        // Submenu: Solicitudes de Acceso (con contador)
        add_submenu_page(
            'area-profesores',
            __('Solicitudes de Acceso', 'area-profesores'),
            __('Solicitudes de Acceso', 'area-profesores') . $badge,
            'manage_options',
            'area-profesores-solicitudes',
            array($this, 'render_solicitudes_page')
        );

        // Submenu: Comedor Diario
        add_submenu_page(
            'area-profesores',
            __('Comedor Diario', 'area-profesores'),
            __('Comedor Diario', 'area-profesores'),
            'manage_options',
            'area-profesores-comedor',
            array($this, 'render_comedor_admin_page')
        );

        // Submenu: Ajustes
        add_submenu_page(
            'area-profesores',
            __('Ajustes', 'area-profesores'),
            __('Ajustes', 'area-profesores'),
            'manage_options',
            'area-profesores-ajustes',
            array($this, 'render_ajustes_page')
        );
    }

    /**
     * Render "Profesores" backend management screen
     */
    public function render_profesores_page() {
        // Handle bulk form submission if needed
        if (isset($_POST['ap_save_profesores']) && check_admin_referer('ap_save_profesores_nonce')) {
            $profesores_status = isset($_POST['is_profesor']) && is_array($_POST['is_profesor']) ? $_POST['is_profesor'] : array();
            $tutorias = isset($_POST['tutoria']) && is_array($_POST['tutoria']) ? $_POST['tutoria'] : array();
            $all_user_ids = isset($_POST['all_user_ids']) && is_array($_POST['all_user_ids']) ? array_map('absint', $_POST['all_user_ids']) : array();

            foreach ($all_user_ids as $uid) {
                $is_prof = isset($profesores_status[$uid]) ? '1' : '0';
                $tutoria = isset($tutorias[$uid]) ? sanitize_text_field(wp_unslash($tutorias[$uid])) : '';

                update_user_meta($uid, '_ap_is_profesor', $is_prof);
                update_user_meta($uid, '_ap_tutoria', $tutoria);
            }

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Configuración de profesores actualizada correctamente.', 'area-profesores') . '</p></div>';
        }

        // Search and pagination parameters
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $filter_profesor = isset($_GET['filter_profesor']) ? sanitize_text_field($_GET['filter_profesor']) : '';
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;

        $args = array(
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
            'search' => !empty($search) ? '*' . $search . '*' : '',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'orderby' => 'display_name',
            'order'   => 'ASC',
        );

        if ($filter_profesor === 'yes') {
            $args['meta_query'] = array(
                array(
                    'key'     => '_ap_is_profesor',
                    'value'   => '1',
                    'compare' => '=',
                )
            );
        } elseif ($filter_profesor === 'no') {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_ap_is_profesor',
                    'value'   => '1',
                    'compare' => '!=',
                ),
                array(
                    'key'     => '_ap_is_profesor',
                    'compare' => 'NOT EXISTS',
                )
            );
        }

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total_users = $user_query->get_total_users();
        $total_pages = ceil($total_users / $per_page);

        $available_classes = Area_Profesores_Comedor::get_available_classes();

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-profesores.php';
    }

    /**
     * Render "Solicitudes de Acceso" screen
     */
    public function render_solicitudes_page() {
        global $wpdb;
        $table = Area_Profesores_Solicitudes::get_table_name();

        $estado_filter = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $where = array('1=1');
        $params = array();

        if (!empty($estado_filter) && in_array($estado_filter, array('pendiente', 'aprobada', 'rechazada'), true)) {
            $where[] = 'estado = %s';
            $params[] = $estado_filter;
        }

        if (!empty($search)) {
            $where[] = '(nombre LIKE %s OR apellidos LIKE %s OR email LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);
        if (!empty($params)) {
            $sql = $wpdb->prepare("SELECT * FROM $table WHERE $where_sql ORDER BY id DESC", $params);
        } else {
            $sql = "SELECT * FROM $table ORDER BY id DESC";
        }

        $solicitudes = $wpdb->get_results($sql);
        $available_classes = Area_Profesores_Comedor::get_available_classes();

        // Counts for tabs
        $count_pendiente = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE estado = 'pendiente'");
        $count_aprobada = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE estado = 'aprobada'");
        $count_rechazada = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE estado = 'rechazada'");
        $count_total = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-solicitudes.php';
    }

    /**
     * Render "Ajustes" configuration screen
     */
    public function render_ajustes_page() {
        if (isset($_POST['ap_save_ajustes']) && check_admin_referer('ap_save_ajustes_nonce')) {
            $settings = array(
                'allowed_domain'            => sanitize_text_field(wp_unslash($_POST['allowed_domain'])),
                'tribanda_url'              => esc_url_raw(wp_unslash($_POST['tribanda_url'])),
                'logo_url'                  => esc_url_raw(wp_unslash($_POST['logo_url'])),
                'login_button_text'         => sanitize_text_field(wp_unslash($_POST['login_button_text'])),
                'only_approved_can_register'=> isset($_POST['only_approved_can_register']) ? 'yes' : 'no',
                'notification_emails'       => sanitize_text_field(wp_unslash($_POST['notification_emails'])),
                'login_page_id'             => absint($_POST['login_page_id']),
                'area_page_id'              => absint($_POST['area_page_id']),
            );

            update_option('ap_settings', $settings);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Ajustes guardados correctamente.', 'area-profesores') . '</p></div>';
        }

        $settings = array(
            'allowed_domain'            => self::get_setting('allowed_domain', 'casadelavirgen.es'),
            'tribanda_url'              => self::get_setting('tribanda_url', 'https://profesores.casadelavirgen.es/wp-content/uploads/2021/04/Tribanda-corporativa-SUP-e1611846970284-1536x130-1-1024x87.png'),
            'logo_url'                  => self::get_setting('logo_url', 'https://profesores.casadelavirgen.es/wp-content/uploads/2019/06/cropped-Logo_Color_MARGENES-2.png'),
            'login_button_text'         => self::get_setting('login_button_text', 'Accede usando tu cuenta de profesor'),
            'only_approved_can_register'=> self::get_setting('only_approved_can_register', 'yes'),
            'notification_emails'       => self::get_setting('notification_emails', get_option('admin_email')),
            'login_page_id'             => self::get_setting('login_page_id', 0),
            'area_page_id'              => self::get_setting('area_page_id', 0),
        );

        $pages = get_pages();

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-ajustes.php';
    }

    /**
     * AJAX handler to quickly save individual teacher status and tutoria
     */
    public function ajax_save_teacher() {
        check_ajax_referer('area_profesores_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $is_profesor = !empty($_POST['is_profesor']) ? '1' : '0';
        $tutoria = isset($_POST['tutoria']) ? sanitize_text_field(wp_unslash($_POST['tutoria'])) : '';

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Usuario no válido'));
        }

        update_user_meta($user_id, '_ap_is_profesor', $is_profesor);
        update_user_meta($user_id, '_ap_tutoria', $tutoria);

        wp_send_json_success(array('message' => __('Guardado', 'area-profesores')));
    }

    /**
     * User profile integration fields
     */
    public function render_user_profile_fields($user) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $is_profesor = get_user_meta($user->ID, '_ap_is_profesor', true);
        $tutoria = get_user_meta($user->ID, '_ap_tutoria', true);
        $available_classes = Area_Profesores_Comedor::get_available_classes();
        ?>
        <h2><?php esc_html_e('Configuración Área de Profesores', 'area-profesores'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="ap_is_profesor"><?php esc_html_e('¿Es Profesor?', 'area-profesores'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="ap_is_profesor" id="ap_is_profesor" value="1" <?php checked($is_profesor, '1'); ?>>
                        <?php esc_html_e('Marcar este usuario como profesor (tendrá acceso al Área de Profesores)', 'area-profesores'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="ap_tutoria"><?php esc_html_e('Tutoría asignada', 'area-profesores'); ?></label></th>
                <td>
                    <select name="ap_tutoria" id="ap_tutoria">
                        <option value=""><?php esc_html_e('-- Sin tutoría (Especialista / No tutor) --', 'area-profesores'); ?></option>
                        <?php foreach ($available_classes as $key => $name) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($tutoria, $key); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Asigna el curso del cual este profesor es tutor. Al rellenar formularios se seleccionará automáticamente.', 'area-profesores'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user profile fields
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $is_profesor = isset($_POST['ap_is_profesor']) ? '1' : '0';
        $tutoria = isset($_POST['ap_tutoria']) ? sanitize_text_field(wp_unslash($_POST['ap_tutoria'])) : '';

        update_user_meta($user_id, '_ap_is_profesor', $is_profesor);
        update_user_meta($user_id, '_ap_tutoria', $tutoria);
    }

    /**
     * Render Comedor backend administration screen
     */
    public function render_comedor_admin_page() {
        $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? sanitize_text_field($_GET['fecha']) : current_time('Y-m-d');
        $records = Area_Profesores_Comedor::get_records_by_date($fecha);
        $summary = Area_Profesores_Comedor::get_summary_by_date($fecha);
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=ap_export_comedor_csv&fecha=' . urlencode($fecha)), 'ap_export_csv_nonce');

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-comedor.php';
    }
}
