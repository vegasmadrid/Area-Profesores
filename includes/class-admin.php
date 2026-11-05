<?php
/**
 * Admin Management Class
 * Handles backend menus: "Área Familias" > "Profesores" and "Comedor Diario",
 * user role/meta assignment, and teacher tutoría configuration.
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

        // User profile fields integration
        add_action('show_user_profile', array($this, 'render_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'render_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));

        // AJAX handler for saving teacher status in admin table
        add_action('wp_ajax_ap_admin_save_teacher', array($this, 'ajax_save_teacher'));
    }

    /**
     * Check if a user is marked as teacher
     */
    public static function is_user_profesor($user_id) {
        if (!$user_id) {
            return false;
        }

        // Administrators can always be granted access or configured
        $is_teacher = get_user_meta($user_id, '_ap_is_profesor', true);
        if ($is_teacher === '1' || $is_teacher === 1 || $is_teacher === true) {
            return true;
        }

        // Also check if user has 'administrator' role (convenience for webmasters/testing)
        $user = get_userdata($user_id);
        if ($user && in_array('administrator', (array) $user->roles, true)) {
            // Check if explicitly disabled or allow admins by default
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
     * Register Admin Menus under "Área Familias"
     */
    public function register_admin_menus() {
        // Main Menu: Área Familias
        add_menu_page(
            __('Área Familias', 'area-profesores'),
            __('Área Familias', 'area-profesores'),
            'manage_options',
            'area-familias',
            array($this, 'render_main_admin_page'),
            'dashicons-groups',
            26
        );

        // Submenu: Profesores
        add_submenu_page(
            'area-familias',
            __('Profesores', 'area-profesores'),
            __('Profesores', 'area-profesores'),
            'manage_options',
            'area-familias-profesores',
            array($this, 'render_profesores_page')
        );

        // Submenu: Comedor Diario
        add_submenu_page(
            'area-familias',
            __('Comedor Diario', 'area-profesores'),
            __('Comedor Diario', 'area-profesores'),
            'manage_options',
            'area-familias-comedor',
            array($this, 'render_comedor_admin_page')
        );

        // Remove the duplicated top-level menu title from submenu list
        remove_submenu_page('area-familias', 'area-familias');
    }

    /**
     * Main page fallback redirecting to Profesores
     */
    public function render_main_admin_page() {
        $this->render_profesores_page();
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
