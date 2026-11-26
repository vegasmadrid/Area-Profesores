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

        // Delete a user from the Profesores list
        add_action('admin_post_ap_delete_profesor', array($this, 'handle_delete_profesor'));
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
            'comedor_page_id'           => 0,
            'start_page_id' => 0,
            'start_custom_url' => '',
            'respect_redirect_to' => 'yes',
            'hide_admin_bar' => 'yes',
            'block_wp_admin' => 'yes',
            'aviso_profesores' => '',
            'restrict_site'             => 'yes',
            'public_page_ids'           => array(),
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
     * Every registered user is a teacher: only teachers get an account (the
     * access requests and the corporate Google login control who can register).
     */
    public static function is_user_profesor($user_id) {
        return $user_id && get_userdata($user_id) !== false && !Area_Profesores_Solicitudes::is_user_pending($user_id);
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
            $asignaciones = isset($_POST['asignacion']) && is_array($_POST['asignacion']) ? wp_unslash($_POST['asignacion']) : array();
            $all_user_ids = isset($_POST['all_user_ids']) && is_array($_POST['all_user_ids']) ? array_map('absint', $_POST['all_user_ids']) : array();

            $come = isset($_POST['come']) && is_array($_POST['come']) ? array_map('absint', array_keys($_POST['come'])) : array();
            foreach ($all_user_ids as $uid) {
                if (isset($asignaciones[$uid])) {
                    Area_Profesores_Cursos::set_asignacion($uid, sanitize_text_field($asignaciones[$uid]));
                }
                update_user_meta($uid, Area_Profesores_Comensales::META_NO_COME, in_array($uid, $come, true) ? '0' : '1');
            }

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Configuración de profesores actualizada correctamente.', 'area-profesores') . '</p></div>';
        }

        if (isset($_GET['ap_deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('Usuario "%s" eliminado.', 'area-profesores'), sanitize_text_field(wp_unslash($_GET['ap_deleted'])))) . '</p></div>';
        } elseif (isset($_GET['ap_delete_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('No se puede eliminar ese usuario desde aquí (no puedes eliminarte a ti mismo ni a un administrador).', 'area-profesores') . '</p></div>';
        }

        // Search and pagination parameters
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $filter_etapa = isset($_GET['filter_etapa']) ? sanitize_key($_GET['filter_etapa']) : '';
        if ($filter_etapa !== '' && $filter_etapa !== 'sin' && $filter_etapa !== 'especialistas' && !array_key_exists($filter_etapa, Area_Profesores_Cursos::get_etapas())) {
            $filter_etapa = '';
        }
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

        if ($filter_etapa !== '') {
            // Specialists work in several etapas (those of their classes), so the
            // filter is resolved here: tutors of the etapa + specialists with
            // classes in it.
            $ids = array();
            foreach (get_users(array('fields' => 'ID')) as $uid) {
                $a = Area_Profesores_Cursos::get_asignacion((int) $uid);
                if ($filter_etapa === 'sin' ? $a['rol'] === ''
                    : ($filter_etapa === 'especialistas' ? $a['rol'] === 'especialista' : in_array($filter_etapa, $a['etapas'], true))) {
                    $ids[] = (int) $uid;
                }
            }
            $args['include'] = $ids ? $ids : array(0);
        }

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total_users = (int) $user_query->get_total_users();
        // Some plugins/hosts break the "found rows" count: count the matching users directly.
        if ($total_users < count($users) || ($total_users === 0 && !empty($users))) {
            $count_args = $args;
            unset($count_args['number'], $count_args['offset']);
            $count_args['fields'] = 'ID';
            $count_args['count_total'] = false;
            $total_users = count(get_users($count_args));
        }
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
                'comedor_page_id'           => isset($_POST['comedor_page_id']) ? absint($_POST['comedor_page_id']) : 0,
                'start_page_id' => isset($_POST['start_page_id']) ? absint($_POST['start_page_id']) : 0,
                'start_custom_url' => isset($_POST['start_custom_url']) ? esc_url_raw(wp_unslash($_POST['start_custom_url'])) : '',
                'respect_redirect_to' => isset($_POST['respect_redirect_to']) ? 'yes' : 'no',
                'hide_admin_bar' => isset($_POST['hide_admin_bar']) ? 'yes' : 'no',
                'block_wp_admin' => isset($_POST['block_wp_admin']) ? 'yes' : 'no',
                'aviso_profesores' => isset($_POST['aviso_profesores']) ? wp_kses_post(wp_unslash($_POST['aviso_profesores'])) : '',
                'restrict_site'             => isset($_POST['restrict_site']) ? 'yes' : 'no',
                'public_page_ids'           => isset($_POST['public_page_ids']) ? array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['public_page_ids'])))) : array(),
            );

            // Keep the settings saved from other screens.
            $existing = get_option('ap_settings', array());
            update_option('ap_settings', array_merge(is_array($existing) ? $existing : array(), $settings));
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
            'comedor_page_id'           => self::get_setting('comedor_page_id', 0),
            'start_page_id' => self::get_setting('start_page_id', 0),
            'start_custom_url' => self::get_setting('start_custom_url', ''),
            'respect_redirect_to' => self::get_setting('respect_redirect_to', 'yes'),
            'hide_admin_bar' => self::get_setting('hide_admin_bar', 'yes'),
            'block_wp_admin' => self::get_setting('block_wp_admin', 'yes'),
            'aviso_profesores' => self::get_setting('aviso_profesores', ''),
            'restrict_site'             => self::get_setting('restrict_site', 'yes'),
            'public_page_ids'           => (array) self::get_setting('public_page_ids', array()),
        );

        $pages = get_pages();

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-ajustes.php';
    }

    /**
     * AJAX handler to quickly save a teacher's etapa / tutoría
     */
    public function ajax_save_teacher() {
        check_ajax_referer('area_profesores_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Usuario no válido'));
        }

        if (isset($_POST['asignacion'])) {
            Area_Profesores_Cursos::set_asignacion($user_id, sanitize_text_field(wp_unslash($_POST['asignacion'])));
        }
        if (isset($_POST['come'])) {
            update_user_meta($user_id, Area_Profesores_Comensales::META_NO_COME, $_POST['come'] === '1' ? '0' : '1');
        }

        wp_send_json_success(array(
            'message' => __('Guardado', 'area-profesores'),
            'label'   => Area_Profesores_Cursos::get_codigo_label(Area_Profesores_Cursos::get_codigo_asignacion($user_id)),
        ));
    }

    /**
     * Whether the current admin may delete this user from the Profesores list.
     */
    public static function can_delete_user($user_id) {
        if (!current_user_can('delete_users') || (int) $user_id === get_current_user_id()) {
            return false;
        }
        $user = get_userdata($user_id);
        // Administrators are deleted from the WordPress Users screen, on purpose.
        return $user && !in_array('administrator', (array) $user->roles, true) && !is_super_admin($user_id);
    }

    /**
     * Delete a user (its announcements are reassigned to the admin who deletes it).
     */
    public function handle_delete_profesor() {
        $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
        check_admin_referer('ap_delete_profesor_' . $user_id);

        $back = wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=area-profesores');
        $back = remove_query_arg(array('ap_deleted', 'ap_delete_error'), $back);

        if (!self::can_delete_user($user_id)) {
            wp_safe_redirect(add_query_arg('ap_delete_error', 1, $back));
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $user = get_userdata($user_id);
        $name = $user ? $user->display_name : '';
        wp_delete_user($user_id, get_current_user_id());

        wp_safe_redirect(add_query_arg('ap_deleted', rawurlencode($name), $back));
        exit;
    }

    /**
     * User profile integration fields
     */
    public function render_user_profile_fields($user) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $codigo = Area_Profesores_Cursos::get_codigo_asignacion($user->ID);
        ?>
        <h2><?php esc_html_e('Configuración Área de Profesores', 'area-profesores'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="ap_asignacion"><?php esc_html_e('Tutoría o especialista', 'area-profesores'); ?></label></th>
                <td>
                    <select name="ap_asignacion" id="ap_asignacion">
                        <?php echo Area_Profesores_Cursos::render_codigo_options($codigo); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </select>
                    <p class="description"><?php esc_html_e('Si es tutor/a, su curso (en "Apuntar Comedor" se preselecciona). Los especialistas no tienen una etapa fija: sus etapas son las de las "Clases asignadas" (más abajo).', 'area-profesores'); ?></p>
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

        if (isset($_POST['ap_asignacion'])) {
            Area_Profesores_Cursos::set_asignacion($user_id, sanitize_text_field(wp_unslash($_POST['ap_asignacion'])));
        }
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
