<?php
/**
 * Solicitudes de Acceso & Nextend Social Login Integration Class
 * Handles teacher access requests, approval workflows, email notifications,
 * and Nextend Social Login authorization checks for @casadelavirgen.es.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Solicitudes {

    private static $instance = null;
    private static $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'ap_solicitudes_acceso';

        // AJAX handlers for submitting request (public and logged-in)
        add_action('wp_ajax_nopriv_ap_submit_solicitud', array($this, 'ajax_submit_solicitud'));
        add_action('wp_ajax_ap_submit_solicitud', array($this, 'ajax_submit_solicitud'));

        // Admin actions for approving/rejecting requests
        add_action('wp_ajax_ap_admin_approve_solicitud', array($this, 'ajax_admin_approve_solicitud'));
        add_action('wp_ajax_ap_admin_reject_solicitud', array($this, 'ajax_admin_reject_solicitud'));
        add_action('wp_ajax_ap_admin_delete_solicitud', array($this, 'ajax_admin_delete_solicitud'));

        // Nextend Social Login hooks & integration.
        // (Antes se usaban "nsl_can_register" y "nsl_registration", que Nextend no
        // tiene: el filtro no se ejecutaba nunca y cualquiera con una cuenta de
        // Google podía darse de alta sin validación.)
        add_action('nsl_login', array($this, 'handle_nsl_login'), 10, 2);
        add_action('nsl_register_new_user', array($this, 'handle_nsl_registration'), 10, 2);
        add_filter('nsl_is_register_allowed', array($this, 'filter_nsl_is_register_allowed'), 10, 2);
        add_filter('nsl_disabled_register_error_message', array($this, 'filter_nsl_disabled_message'), 99);
        add_filter('nsl_disabled_register_redirect_url', array($this, 'filter_nsl_disabled_redirect'), 99);

        // Registro normal de WordPress (wp-login.php?action=register).
        add_filter('registration_errors', array($this, 'filter_registration_errors'), 99, 3);

        // Red de seguridad: cualquier alta que no haga un administrador y no esté
        // aprobada queda "pendiente de validación" y no puede entrar.
        add_action('user_register', array($this, 'handle_user_register'), 1);
        add_action('init', array($this, 'block_pending_users'), 1);
    }

    /** Motivo del último bloqueo de registro (para el mensaje en la página de login). */
    private $bloqueo = '';

    /**
     * ¿Hay que exigir la validación de la dirección para darse de alta?
     */
    public static function requires_approval() {
        return Area_Profesores_Admin::get_setting('only_approved_can_register', 'yes') === 'yes';
    }

    /**
     * ¿El correo es del dominio del colegio?
     */
    public static function is_allowed_domain($email) {
        $allowed_domain = strtolower(trim(str_replace('@', '', (string) Area_Profesores_Admin::get_setting('allowed_domain', 'casadelavirgen.es'))));
        if ($allowed_domain === '') {
            return true;
        }
        $parts = explode('@', (string) $email);
        return isset($parts[1]) && strtolower(trim($parts[1])) === $allowed_domain;
    }

    /**
     * ¿Tiene el correo una solicitud aprobada? (sin mirar si ya existe el usuario)
     */
    public static function has_approved_request($email) {
        $email = sanitize_email($email);
        if ($email === '') {
            return false;
        }
        global $wpdb;
        $table = self::get_table_name();
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email = %s AND estado = 'aprobada' LIMIT 1", $email));
    }

    /**
     * Crea (si no la hay) una solicitud pendiente para quien ha intentado entrar
     * sin estar aprobado, y avisa a la dirección. Devuelve el estado de su solicitud.
     */
    private function ensure_pending_solicitud($email, $nombre, $apellidos, $observaciones) {
        global $wpdb;
        $table = self::get_table_name();
        $email = sanitize_email($email);

        $estado = $wpdb->get_var($wpdb->prepare(
            "SELECT estado FROM $table WHERE email = %s ORDER BY (estado = 'pendiente') DESC, id DESC LIMIT 1",
            $email
        ));
        if ($estado === 'pendiente' || $estado === 'rechazada' || $estado === 'aprobada') {
            return $estado;
        }

        $nombre = $nombre !== '' ? $nombre : $email;
        $wpdb->insert(
            $table,
            array(
                'nombre'          => $nombre,
                'apellidos'       => $apellidos,
                'email'           => $email,
                'tutoria_deseada' => '',
                'observaciones'   => $observaciones,
                'estado'          => 'pendiente',
                'created_at'      => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        if ($wpdb->insert_id) {
            $this->send_admin_notification_email($wpdb->insert_id, $nombre, $apellidos, $email, '', $observaciones);
        }
        return 'pendiente';
    }

    /**
     * Filter: nsl_is_register_allowed
     * Nextend va a crear un usuario nuevo: solo se permite si la dirección ha
     * aprobado su solicitud. Si no, se guarda como solicitud pendiente.
     */
    public function filter_nsl_is_register_allowed($allowed, $provider = null) {
        if (!$allowed || !self::requires_approval()) {
            return $allowed;
        }

        $get = function ($key) use ($provider) {
            if (is_object($provider) && method_exists($provider, 'getAuthUserData')) {
                try {
                    return (string) $provider->getAuthUserData($key);
                } catch (Exception $e) {
                    return '';
                }
            }
            return '';
        };

        $email = sanitize_email($get('email'));
        if ($email === '') {
            $this->bloqueo = 'pendiente';
            return false;
        }
        if (!self::is_allowed_domain($email)) {
            $this->bloqueo = 'dominio';
            return false;
        }
        if (self::has_approved_request($email)) {
            return true;
        }

        $this->bloqueo = $this->ensure_pending_solicitud(
            $email,
            sanitize_text_field($get('first_name')),
            sanitize_text_field($get('last_name')),
            __('Solicitud creada automáticamente al intentar entrar con Google sin estar aprobado.', 'area-profesores')
        );
        if ($this->bloqueo === 'aprobada') {
            $this->bloqueo = '';
            return true;
        }
        return false;
    }

    /**
     * Sin aviso propio de Nextend: el mensaje lo muestra la página de login.
     */
    public function filter_nsl_disabled_message($message) {
        return $this->bloqueo !== '' ? false : $message;
    }

    public function filter_nsl_disabled_redirect($url) {
        if ($this->bloqueo === '') {
            return $url;
        }
        $login_page_id = Area_Profesores_Access::get_login_page_id();
        $base = $login_page_id ? get_permalink($login_page_id) : wp_login_url();
        return add_query_arg('ap_acceso', $this->bloqueo, $base);
    }

    /**
     * Filter: registration_errors (formulario de registro de WordPress).
     */
    public function filter_registration_errors($errors, $login, $email) {
        if (!self::requires_approval() || !is_wp_error($errors)) {
            return $errors;
        }
        if (!self::is_allowed_domain($email)) {
            $errors->add('ap_dominio', __('Solo se pueden registrar las cuentas del colegio.', 'area-profesores'));
        } elseif (!self::has_approved_request($email)) {
            $this->ensure_pending_solicitud($email, (string) $login, '', __('Solicitud creada automáticamente desde el formulario de registro de WordPress.', 'area-profesores'));
            $errors->add('ap_pendiente', __('Tu alta tiene que validarla la dirección del centro. Te avisaremos por correo cuando esté aprobada.', 'area-profesores'));
        }
        return $errors;
    }

    /**
     * Red de seguridad: un usuario creado por cualquier otra vía (otro plugin,
     * un formulario…) sin que lo cree un administrador y sin solicitud aprobada
     * queda marcado como pendiente y no puede usar la web hasta que se apruebe.
     */
    public function handle_user_register($user_id) {
        if (!self::requires_approval() || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        if (is_user_logged_in() && current_user_can('create_users')) {
            return; // Lo ha creado un administrador.
        }
        $user = get_userdata($user_id);
        if (!$user || self::has_approved_request($user->user_email)) {
            return;
        }
        update_user_meta($user_id, '_ap_pendiente_validacion', 1);
        $this->ensure_pending_solicitud(
            $user->user_email,
            (string) $user->first_name,
            (string) $user->last_name,
            __('Se creó el usuario sin validación previa: queda bloqueado hasta que se apruebe.', 'area-profesores')
        );
    }

    public static function is_user_pending($user_id) {
        return $user_id && get_user_meta($user_id, '_ap_pendiente_validacion', true);
    }

    /**
     * Saca de la web a los usuarios pendientes de validación.
     */
    public function block_pending_users() {
        if (!is_user_logged_in() || !self::is_user_pending(get_current_user_id())) {
            return;
        }
        if (current_user_can('create_users') || (defined('WP_CLI') && WP_CLI) || wp_doing_cron()) {
            return;
        }
        wp_logout();
        $login_page_id = Area_Profesores_Access::get_login_page_id();
        $base = $login_page_id ? get_permalink($login_page_id) : wp_login_url();
        if (wp_doing_ajax()) {
            wp_die(esc_html__('Tu cuenta está pendiente de validación.', 'area-profesores'), '', array('response' => 403));
        }
        wp_safe_redirect(add_query_arg('ap_acceso', 'pendiente', $base));
        exit;
    }

    /**
     * Get table name
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ap_solicitudes_acceso';
    }

    /**
     * Create table on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre VARCHAR(100) NOT NULL,
            apellidos VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            tutoria_deseada VARCHAR(50) DEFAULT '',
            observaciones TEXT DEFAULT NULL,
            estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email_idx (email),
            KEY estado_idx (estado)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Count pending requests (used for admin menu bubble badge)
     */
    public static function get_pending_count() {
        global $wpdb;
        $table = self::get_table_name();
        // Check if table exists first
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE estado = 'pendiente'");
        return intval($count);
    }

    /**
     * Check if an email is already approved
     */
    public static function is_email_approved($email) {
        $email = sanitize_email($email);
        if (empty($email)) {
            return false;
        }

        // 1. Check if user already exists and has teacher flag
        $user = get_user_by('email', $email);
        if ($user && Area_Profesores_Admin::is_user_profesor($user->ID)) {
            return true;
        }

        // 2. Check in approved requests
        global $wpdb;
        $table = self::get_table_name();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email = %s AND estado = 'aprobada'", $email));
        return !empty($exists);
    }

    /**
     * Submit access request from frontend form
     */
    public function ajax_submit_solicitud() {
        check_ajax_referer('area_profesores_nonce', 'nonce');

        $nombre = isset($_POST['nombre']) ? sanitize_text_field(wp_unslash($_POST['nombre'])) : '';
        $apellidos = isset($_POST['apellidos']) ? sanitize_text_field(wp_unslash($_POST['apellidos'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $tutoria = isset($_POST['tutoria_deseada']) ? Area_Profesores_Cursos::sanitize_codigo(sanitize_text_field(wp_unslash($_POST['tutoria_deseada']))) : '';
        $observaciones = isset($_POST['observaciones']) ? sanitize_textarea_field(wp_unslash($_POST['observaciones'])) : '';

        // Validation
        if (empty($nombre) || empty($apellidos) || empty($email)) {
            wp_send_json_error(array('message' => __('Por favor, completa tu nombre, apellidos y correo electrónico.', 'area-profesores')));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Por favor, introduce un correo electrónico válido.', 'area-profesores')));
        }

        // Domain validation: @casadelavirgen.es
        $allowed_domain = Area_Profesores_Admin::get_setting('allowed_domain', 'casadelavirgen.es');
        $allowed_domain = trim(str_replace('@', '', $allowed_domain));

        if (!empty($allowed_domain)) {
            $email_parts = explode('@', $email);
            $user_domain = isset($email_parts[1]) ? strtolower(trim($email_parts[1])) : '';
            if ($user_domain !== strtolower($allowed_domain)) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Debes indicar tu cuenta corporativa del centro (@%s). No se admiten correos personales.', 'area-profesores'),
                        esc_html($allowed_domain)
                    )
                ));
            }
        }

        global $wpdb;
        $table = self::get_table_name();

        // Check if there is already an existing user with this email who is a teacher
        $existing_user = get_user_by('email', $email);
        if ($existing_user && Area_Profesores_Admin::is_user_profesor($existing_user->ID)) {
            wp_send_json_error(array('message' => __('Esta cuenta ya está dada de alta y aprobada como profesor. Puedes acceder directamente pulsando el botón de acceso.', 'area-profesores')));
        }

        // Check if there is already a pending request for this email
        $existing_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND estado = 'pendiente'",
            $email
        ));

        if ($existing_request) {
            wp_send_json_success(array(
                'message' => __('Ya existe una solicitud pendiente de revisión con este correo. El equipo directivo la revisará a la mayor brevedad posible.', 'area-profesores'),
                'already_pending' => true
            ));
        }

        // Insert new request
        $inserted = $wpdb->insert(
            $table,
            array(
                'nombre'          => $nombre,
                'apellidos'       => $apellidos,
                'email'           => $email,
                'tutoria_deseada' => $tutoria,
                'observaciones'   => $observaciones,
                'estado'          => 'pendiente',
                'created_at'      => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            wp_send_json_error(array('message' => __('Error al guardar la solicitud en la base de datos.', 'area-profesores')));
        }

        $solicitud_id = $wpdb->insert_id;

        // Send Email notification to WordPress Administrator(s)
        $this->send_admin_notification_email($solicitud_id, $nombre, $apellidos, $email, $tutoria, $observaciones);

        wp_send_json_success(array(
            'message' => __('¡Solicitud de acceso enviada correctamente! Se ha notificado a la dirección del centro. En cuanto tu cuenta sea aprobada podrás acceder con tu cuenta de profesor.', 'area-profesores')
        ));
    }

    /**
     * Send email notification to site admin(s)
     */
    private function send_admin_notification_email($solicitud_id, $nombre, $apellidos, $email, $tutoria, $observaciones) {
        $admin_emails = Area_Profesores_Admin::get_setting('notification_emails', get_option('admin_email'));
        Area_Profesores_Correos::enviar('solicitud_nueva', array_map('trim', explode(',', $admin_emails)), array(
            'nombre'        => $nombre,
            'apellidos'     => $apellidos,
            'correo'        => $email,
            'tutoria'       => !empty($tutoria) ? Area_Profesores_Cursos::get_codigo_label($tutoria) : __('Sin indicar', 'area-profesores'),
            'observaciones' => !empty($observaciones) ? $observaciones : __('Ninguna', 'area-profesores'),
            'enlace'        => admin_url('admin.php?page=area-profesores-solicitudes'),
        ));
    }

    /**
     * AJAX handler to approve request in backend
     */
    public function ajax_admin_approve_solicitud() {
        check_ajax_referer('area_profesores_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }

        $solicitud_id = isset($_POST['solicitud_id']) ? absint($_POST['solicitud_id']) : 0;
        $tutoria = isset($_POST['tutoria']) ? Area_Profesores_Cursos::sanitize_codigo(sanitize_text_field(wp_unslash($_POST['tutoria']))) : '';

        global $wpdb;
        $table = self::get_table_name();
        $solicitud = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $solicitud_id));

        if (!$solicitud) {
            wp_send_json_error(array('message' => 'Solicitud no encontrada'));
        }

        // If tutoria was passed in request, use it, or fallback to stored tutoria_deseada
        if (empty($tutoria)) {
            $tutoria = $solicitud->tutoria_deseada;
        }

        // Update request status to 'aprobada'
        $wpdb->update(
            $table,
            array(
                'estado'          => 'aprobada',
                'tutoria_deseada' => $tutoria,
                'updated_at'      => current_time('mysql'),
            ),
            array('id' => $solicitud_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        // Check if user already exists in WordPress
        $user = get_user_by('email', $solicitud->email);
        if ($user) {
            delete_user_meta($user->ID, '_ap_pendiente_validacion');
            if ($tutoria !== '') {
                Area_Profesores_Cursos::set_asignacion($user->ID, $tutoria);
            }
        }

        // Send approval email to the teacher
        $this->send_teacher_approved_email($solicitud->nombre, $solicitud->email);

        wp_send_json_success(array(
            'message'       => __('Solicitud aprobada con éxito. El usuario ya tiene acceso al Área de Profesores.', 'area-profesores'),
            'pending_count' => self::get_pending_count(),
        ));
    }

    /**
     * AJAX handler to reject request in backend
     */
    public function ajax_admin_reject_solicitud() {
        check_ajax_referer('area_profesores_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }

        $solicitud_id = isset($_POST['solicitud_id']) ? absint($_POST['solicitud_id']) : 0;

        global $wpdb;
        $table = self::get_table_name();

        $wpdb->update(
            $table,
            array(
                'estado'     => 'rechazada',
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $solicitud_id),
            array('%s', '%s'),
            array('%d')
        );

        wp_send_json_success(array(
            'message'       => __('Solicitud rechazada.', 'area-profesores'),
            'pending_count' => self::get_pending_count(),
        ));
    }

    /**
     * AJAX handler to delete request in backend
     */
    public function ajax_admin_delete_solicitud() {
        check_ajax_referer('area_profesores_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }

        $solicitud_id = isset($_POST['solicitud_id']) ? absint($_POST['solicitud_id']) : 0;

        global $wpdb;
        $table = self::get_table_name();
        $wpdb->delete($table, array('id' => $solicitud_id), array('%d'));

        wp_send_json_success(array(
            'message'       => __('Solicitud eliminada.', 'area-profesores'),
            'pending_count' => self::get_pending_count(),
        ));
    }

    /**
     * Send email to teacher informing their account is approved
     */
    private function send_teacher_approved_email($nombre, $email) {
        $login_page_id = Area_Profesores_Admin::get_setting('login_page_id', 0);
        Area_Profesores_Correos::enviar('solicitud_aprobada', $email, array(
            'nombre' => $nombre,
            'enlace' => $login_page_id ? get_permalink($login_page_id) : home_url('/'),
        ));
    }

    /**
     * Hook into Nextend Social Login (nsl_registration)
     * When a user registers through NSL, check if they have an approved request and assign teacher role & tutoria
     */
    public function handle_nsl_registration($user_id, $provider) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $email = $user->user_email;
        global $wpdb;
        $table = self::get_table_name();

        $solicitud = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND estado = 'aprobada'",
            $email
        ));

        if ($solicitud) {
            if (!empty($solicitud->tutoria_deseada)) {
                Area_Profesores_Cursos::set_asignacion($user_id, $solicitud->tutoria_deseada);
            }
        }
    }

    /**
     * Hook into Nextend Social Login (nsl_login)
     * Ensure existing approved requests sync to the user account on login
     */
    public function handle_nsl_login($user_id, $provider) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $email = $user->user_email;
        global $wpdb;
        $table = self::get_table_name();

        $solicitud = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND estado = 'aprobada'",
            $email
        ));

        if ($solicitud) {
            // Only fill the assignment if the teacher has none yet (admins may have changed it).
            if (Area_Profesores_Cursos::get_codigo_asignacion($user_id) === '' && !empty($solicitud->tutoria_deseada)) {
                Area_Profesores_Cursos::set_asignacion($user_id, $solicitud->tutoria_deseada);
            }
        }
    }

    /**
     * Generate Nextend Social Login Google URL
     */
    public static function get_nextend_google_login_url($redirect_url = '') {
        if (empty($redirect_url)) {
            $area_page_id = Area_Profesores_Admin::get_setting('area_page_id', 0);
            $redirect_url = $area_page_id ? get_permalink($area_page_id) : home_url('/');
        }

        // Check if Nextend Social Login function/class exists
        if (class_exists('NextendSocialLogin') && method_exists('NextendSocialLogin', 'getProvider')) {
            $provider = NextendSocialLogin::getProvider('google');
            if ($provider && method_exists($provider, 'getLoginUrl')) {
                return $provider->getLoginUrl($redirect_url);
            }
        }

        // Standard Nextend Social Login query arg pattern
        return add_query_arg(array(
            'loginSocial' => 'google',
            'redirect'    => urlencode($redirect_url)
        ), site_url('wp-login.php'));
    }
}
