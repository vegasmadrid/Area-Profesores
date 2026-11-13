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

        // Nextend Social Login hooks & integration
        add_action('nsl_login', array($this, 'handle_nsl_login'), 10, 2);
        add_action('nsl_registration', array($this, 'handle_nsl_registration'), 10, 2);
        add_filter('nsl_can_register', array($this, 'filter_nsl_can_register'), 10, 3);
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
        $tutoria = isset($_POST['tutoria_deseada']) ? sanitize_text_field(wp_unslash($_POST['tutoria_deseada'])) : '';
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
        $emails = array_map('trim', explode(',', $admin_emails));

        $admin_approval_url = admin_url('admin.php?page=area-profesores-solicitudes');
        $site_name = get_bloginfo('name');

        $subject = sprintf('[%s] Nueva solicitud de acceso de profesor: %s %s', $site_name, $nombre, $apellidos);

        $tutoria_label = !empty($tutoria) ? $tutoria : __('Sin tutoría / Especialista', 'area-profesores');
        $obs_text = !empty($observaciones) ? esc_html($observaciones) : __('Ninguna observación aportada.', 'area-profesores');

        $logo_url = Area_Profesores_Admin::get_setting('logo_url', 'https://profesores.casadelavirgen.es/wp-content/uploads/2019/06/cropped-Logo_Color_MARGENES-2.png');
        $tribanda_url = Area_Profesores_Admin::get_setting('tribanda_url', 'https://profesores.casadelavirgen.es/wp-content/uploads/2021/04/Tribanda-corporativa-SUP-e1611846970284-1536x130-1-1024x87.png');

        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
                .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
                .email-tribanda { width: 100%; height: 6px; display: block; }
                .email-header { padding: 24px 28px; text-align: center; border-bottom: 1px solid #f1f5f9; background: #ffffff; }
                .email-logo { max-height: 56px; margin-bottom: 10px; }
                .email-title { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; }
                .email-body { padding: 28px; font-size: 15px; line-height: 1.6; }
                .data-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 20px 0; }
                .data-row { margin-bottom: 10px; }
                .data-row:last-child { margin-bottom: 0; }
                .data-label { font-weight: 700; color: #475569; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 2px; }
                .data-value { font-size: 15px; color: #0f172a; font-weight: 600; }
                .btn-approve { display: inline-block; background: #1e40af; color: #ffffff !important; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; text-align: center; margin-top: 10px; }
                .email-footer { background: #f1f5f9; padding: 16px 28px; font-size: 12px; color: #64748b; text-align: center; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <img src="' . esc_url($tribanda_url) . '" alt="" class="email-tribanda" style="width: 100%; height: auto; max-height: 8px; object-fit: cover;">
                <div class="email-header">
                    <img src="' . esc_url($logo_url) . '" alt="Casa de la Virgen" class="email-logo">
                    <h2 class="email-title">Nueva Solicitud de Acceso</h2>
                </div>
                <div class="email-body">
                    <p>Hola,</p>
                    <p>Un nuevo docente ha solicitado acceso al <strong>Área de Profesores</strong> desde la web escolar:</p>
                    
                    <div class="data-box">
                        <div class="data-row">
                            <span class="data-label">Profesor / Solicitante:</span>
                            <span class="data-value">' . esc_html($nombre . ' ' . $apellidos) . '</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Cuenta de Correo (@casadelavirgen.es):</span>
                            <span class="data-value"><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Tutoría Deseada:</span>
                            <span class="data-value">' . esc_html($tutoria_label) . '</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Observaciones / Departamento:</span>
                            <span class="data-value">' . nl2br(esc_html($obs_text)) . '</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Fecha de Solicitud:</span>
                            <span class="data-value">' . esc_html(current_time('d/m/Y H:i')) . '</span>
                        </div>
                    </div>

                    <p style="text-align: center; margin-top: 24px;">
                        <a href="' . esc_url($admin_approval_url) . '" class="btn-approve">
                            Revisar y Aprobar en el Panel de Profesores
                        </a>
                    </p>
                </div>
                <div class="email-footer">
                    Este es un aviso automático generado por el Área de Profesores del Colegio Casa de la Virgen.
                </div>
            </div>
        </body>
        </html>
        ';

        $headers = array('Content-Type: text/html; charset=UTF-8');

        foreach ($emails as $recipient) {
            if (is_email($recipient)) {
                wp_mail($recipient, $subject, $message, $headers);
            }
        }
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
        $tutoria = isset($_POST['tutoria']) ? sanitize_text_field(wp_unslash($_POST['tutoria'])) : '';

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
            update_user_meta($user->ID, '_ap_is_profesor', '1');
            update_user_meta($user->ID, '_ap_tutoria', $tutoria);
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
        $login_url = $login_page_id ? get_permalink($login_page_id) : home_url('/');

        $logo_url = Area_Profesores_Admin::get_setting('logo_url', 'https://profesores.casadelavirgen.es/wp-content/uploads/2019/06/cropped-Logo_Color_MARGENES-2.png');
        $tribanda_url = Area_Profesores_Admin::get_setting('tribanda_url', 'https://profesores.casadelavirgen.es/wp-content/uploads/2021/04/Tribanda-corporativa-SUP-e1611846970284-1536x130-1-1024x87.png');

        $subject = 'Tu acceso al Área de Profesores ha sido activado - Colegio Casa de la Virgen';
        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
                .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
                .email-tribanda { width: 100%; height: auto; max-height: 8px; display: block; }
                .email-header { padding: 24px 28px; text-align: center; border-bottom: 1px solid #f1f5f9; }
                .email-logo { max-height: 56px; margin-bottom: 10px; }
                .email-body { padding: 28px; font-size: 15px; line-height: 1.6; }
                .btn-login { display: inline-block; background: #1e40af; color: #ffffff !important; padding: 13px 28px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; margin-top: 14px; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <img src="' . esc_url($tribanda_url) . '" alt="" class="email-tribanda">
                <div class="email-header">
                    <img src="' . esc_url($logo_url) . '" alt="Casa de la Virgen" class="email-logo">
                    <h2 style="margin: 0; color: #0f172a; font-size: 20px;">¡Acceso Aprobado!</h2>
                </div>
                <div class="email-body">
                    <p>Hola <strong>' . esc_html($nombre) . '</strong>,</p>
                    <p>Tu solicitud de acceso al <strong>Área de Profesores</strong> del Colegio Casa de la Virgen ha sido revisada y aprobada por la dirección.</p>
                    <p>Ya puedes acceder directamente con tu cuenta de profesor pulsando en el botón a continuación:</p>
                    <p style="text-align: center; margin: 24px 0;">
                        <a href="' . esc_url($login_url) . '" class="btn-login">Acceder al Área de Profesores</a>
                    </p>
                    <p style="font-size: 13px; color: #64748b;">Recuerda identificarte con tu cuenta corporativa <strong>' . esc_html($email) . '</strong>.</p>
                </div>
            </div>
        </body>
        </html>';

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($email, $subject, $message, $headers);
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
            update_user_meta($user_id, '_ap_is_profesor', '1');
            if (!empty($solicitud->tutoria_deseada)) {
                update_user_meta($user_id, '_ap_tutoria', $solicitud->tutoria_deseada);
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
            update_user_meta($user_id, '_ap_is_profesor', '1');
            $tutoria_actual = get_user_meta($user_id, '_ap_tutoria', true);
            if (empty($tutoria_actual) && !empty($solicitud->tutoria_deseada)) {
                update_user_meta($user_id, '_ap_tutoria', $solicitud->tutoria_deseada);
            }
        }
    }

    /**
     * Filter: nsl_can_register
     * Check if registration via NSL is restricted to approved requests or domain
     */
    public function filter_nsl_can_register($can_register, $provider, $userData) {
        // If admin setting "only_approved_can_register" is true:
        $only_approved = Area_Profesores_Admin::get_setting('only_approved_can_register', 'yes');
        if ($only_approved !== 'yes') {
            return $can_register;
        }

        $email = '';
        if (is_array($userData) && isset($userData['email'])) {
            $email = $userData['email'];
        } elseif (is_object($userData) && isset($userData->email)) {
            $email = $userData->email;
        }

        if (empty($email)) {
            return false;
        }

        // Domain check (@casadelavirgen.es)
        $allowed_domain = Area_Profesores_Admin::get_setting('allowed_domain', 'casadelavirgen.es');
        $allowed_domain = trim(str_replace('@', '', $allowed_domain));
        if (!empty($allowed_domain)) {
            $parts = explode('@', $email);
            $domain = isset($parts[1]) ? strtolower(trim($parts[1])) : '';
            if ($domain !== strtolower($allowed_domain)) {
                return false;
            }
        }

        // Check if pre-approved in DB
        return self::is_email_approved($email);
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
