<?php
/**
 * Conexión opcional con la API de Google Chat (OAuth 2.0).
 *
 * The incoming webhooks can only post messages. To read or change the
 * configuration of a space (its name, for example) the API is needed. An
 * administrator of the school's Google Workspace connects once from
 * "Área Profesores > Aulas"; with "acceso de administrador" (useAdminAccess)
 * the plugin can rename any space of the domain without being a member.
 *
 * Requires a Google Cloud project with the Google Chat API enabled, a Chat app
 * configured in that API and an OAuth client of type "Aplicación web".
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Google {

    const OPTION = 'ap_google_chat';
    const API    = 'https://chat.googleapis.com/v1/';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // OAuth callback: Google redirects to admin-post.php (without "action").
        add_action('admin_post', array($this, 'handle_oauth_callback'));
        add_action('admin_post_ap_google_save', array($this, 'handle_save'));
        add_action('admin_post_ap_google_connect', array($this, 'handle_connect'));
        add_action('admin_post_ap_google_disconnect', array($this, 'handle_disconnect'));
    }

    /* ---------------------------------------------------------------------
     * Ajustes
     * ------------------------------------------------------------------- */

    public static function get_settings() {
        $s = get_option(self::OPTION, array());
        return array_merge(array(
            'client_id'       => '',
            'client_secret'   => '',
            'admin_access'    => true,
            'refresh_token'   => '',
            'access_token'    => '',
            'expires_at'      => 0,
            'email'           => '',
            'plantilla'       => '{curso} (PROMOCIÓN {promocion})',
        ), is_array($s) ? $s : array());
    }

    private static function save_settings($s) {
        update_option(self::OPTION, $s, false);
    }

    public static function is_configured() {
        $s = self::get_settings();
        return $s['client_id'] !== '' && $s['client_secret'] !== '';
    }

    public static function is_connected() {
        $s = self::get_settings();
        return self::is_configured() && $s['refresh_token'] !== '';
    }

    public static function get_redirect_uri() {
        return admin_url('admin-post.php');
    }

    private static function get_scopes() {
        $s = self::get_settings();
        return array(
            'openid',
            'email',
            $s['admin_access'] ? 'https://www.googleapis.com/auth/chat.admin.spaces' : 'https://www.googleapis.com/auth/chat.spaces',
        );
    }

    private static function back($msg = '', $type = 'success') {
        if ($msg !== '') {
            set_transient('ap_aulas_flash_' . get_current_user_id(), array('type' => $type, 'text' => $msg), 120);
        }
        wp_safe_redirect(admin_url('admin.php?page=area-profesores-aulas#ap-google'));
        exit;
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_google_save');

        $s = self::get_settings();
        $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        $secret = isset($_POST['client_secret']) ? sanitize_text_field(wp_unslash($_POST['client_secret'])) : '';
        $admin_access = !empty($_POST['admin_access']);

        // Changing the client or the access mode invalidates the connection.
        if ($client_id !== $s['client_id'] || ($secret !== '' && $secret !== $s['client_secret']) || $admin_access !== (bool) $s['admin_access']) {
            $s['refresh_token'] = '';
            $s['access_token'] = '';
            $s['email'] = '';
        }

        $s['client_id'] = $client_id;
        if ($secret !== '') {
            $s['client_secret'] = $secret;
        }
        $s['admin_access'] = $admin_access;
        $plantilla = isset($_POST['plantilla']) ? sanitize_text_field(wp_unslash($_POST['plantilla'])) : '';
        $s['plantilla'] = $plantilla !== '' ? $plantilla : '{curso} (PROMOCIÓN {promocion})';

        self::save_settings($s);
        self::back(__('Ajustes de Google Chat guardados.', 'area-profesores'));
    }

    /* ---------------------------------------------------------------------
     * OAuth
     * ------------------------------------------------------------------- */

    public function handle_connect() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_google_connect');

        if (!self::is_configured()) {
            self::back(__('Primero guarda el ID de cliente y el secreto.', 'area-profesores'), 'error');
        }

        $s = self::get_settings();
        $state = wp_generate_password(24, false, false);
        set_transient('ap_google_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS);

        $url = add_query_arg(array(
            'client_id'     => rawurlencode($s['client_id']),
            'redirect_uri'  => rawurlencode(self::get_redirect_uri()),
            'response_type' => 'code',
            'scope'         => rawurlencode(implode(' ', self::get_scopes())),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'include_granted_scopes' => 'true',
            'state'         => rawurlencode($state),
        ), 'https://accounts.google.com/o/oauth2/v2/auth');

        wp_redirect($url);
        exit;
    }

    public function handle_oauth_callback() {
        if (!isset($_GET['state']) || (!isset($_GET['code']) && !isset($_GET['error']))) {
            return; // Not our callback.
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }

        $expected = get_transient('ap_google_state_' . get_current_user_id());
        delete_transient('ap_google_state_' . get_current_user_id());
        if (!$expected || !hash_equals($expected, (string) wp_unslash($_GET['state']))) {
            self::back(__('La conexión con Google ha caducado. Vuelve a intentarlo.', 'area-profesores'), 'error');
        }

        if (isset($_GET['error'])) {
            self::back(sprintf(__('Google no ha autorizado la conexión: %s', 'area-profesores'), sanitize_text_field(wp_unslash($_GET['error']))), 'error');
        }

        $s = self::get_settings();
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 20,
            'body'    => array(
                'code'          => sanitize_text_field(wp_unslash($_GET['code'])),
                'client_id'     => $s['client_id'],
                'client_secret' => $s['client_secret'],
                'redirect_uri'  => self::get_redirect_uri(),
                'grant_type'    => 'authorization_code',
            ),
        ));

        $data = is_wp_error($response) ? array() : json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            $err = is_wp_error($response) ? $response->get_error_message() : (isset($data['error_description']) ? $data['error_description'] : (isset($data['error']) ? $data['error'] : 'desconocido'));
            self::back(sprintf(__('No se pudo completar la conexión con Google: %s', 'area-profesores'), $err), 'error');
        }

        $s['access_token'] = $data['access_token'];
        $s['expires_at'] = time() + (isset($data['expires_in']) ? (int) $data['expires_in'] : 3500) - 60;
        if (!empty($data['refresh_token'])) {
            $s['refresh_token'] = $data['refresh_token'];
        }
        $s['email'] = self::email_from_id_token(isset($data['id_token']) ? $data['id_token'] : '');
        self::save_settings($s);

        if ($s['refresh_token'] === '') {
            self::back(__('Google no ha devuelto un permiso permanente. Desconecta y vuelve a conectar.', 'area-profesores'), 'error');
        }

        self::back(sprintf(__('Conectado con Google Chat como %s.', 'area-profesores'), $s['email'] !== '' ? $s['email'] : __('(cuenta de Google)', 'area-profesores')));
    }

    private static function email_from_id_token($jwt) {
        $parts = explode('.', (string) $jwt);
        if (count($parts) < 2) {
            return '';
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        return isset($payload['email']) ? sanitize_email($payload['email']) : '';
    }

    public function handle_disconnect() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_google_disconnect');

        $s = self::get_settings();
        if ($s['refresh_token'] !== '') {
            wp_remote_post('https://oauth2.googleapis.com/revoke', array(
                'timeout' => 10,
                'body'    => array('token' => $s['refresh_token']),
            ));
        }
        $s['refresh_token'] = '';
        $s['access_token'] = '';
        $s['email'] = '';
        self::save_settings($s);

        self::back(__('Desconectado de Google Chat.', 'area-profesores'));
    }

    /**
     * Valid access token (refreshing it when needed) or WP_Error.
     */
    private static function get_access_token() {
        $s = self::get_settings();
        if (!self::is_connected()) {
            return new WP_Error('google', __('No hay conexión con Google Chat.', 'area-profesores'));
        }
        if ($s['access_token'] !== '' && (int) $s['expires_at'] > time()) {
            return $s['access_token'];
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 20,
            'body'    => array(
                'client_id'     => $s['client_id'],
                'client_secret' => $s['client_secret'],
                'refresh_token' => $s['refresh_token'],
                'grant_type'    => 'refresh_token',
            ),
        ));
        $data = is_wp_error($response) ? array() : json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            $err = is_wp_error($response) ? $response->get_error_message() : (isset($data['error_description']) ? $data['error_description'] : __('permiso revocado o caducado; vuelve a conectar', 'area-profesores'));
            return new WP_Error('google', sprintf(__('No se pudo renovar el acceso a Google: %s', 'area-profesores'), $err));
        }

        $s['access_token'] = $data['access_token'];
        $s['expires_at'] = time() + (isset($data['expires_in']) ? (int) $data['expires_in'] : 3500) - 60;
        self::save_settings($s);
        return $s['access_token'];
    }

    /* ---------------------------------------------------------------------
     * API de Google Chat
     * ------------------------------------------------------------------- */

    /**
     * @return array|WP_Error decoded JSON
     */
    private static function request($method, $path, $query = array(), $body = null) {
        $token = self::get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $s = self::get_settings();
        if ($s['admin_access']) {
            $query['useAdminAccess'] = 'true';
        }

        $args = array(
            'method'  => $method,
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json; charset=UTF-8',
            ),
        );
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request(add_query_arg($query, self::API . $path), $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $msg = isset($data['error']['message']) ? $data['error']['message'] : wp_remote_retrieve_response_message($response);
            if ($code === 403) {
                $msg = __('Sin permiso sobre este espacio (con acceso de administrador, la cuenta conectada debe ser administradora de Google Workspace con privilegios de Chat).', 'area-profesores') . ' ' . $msg;
            } elseif ($code === 404) {
                $msg = __('El espacio no existe o la cuenta conectada no puede verlo.', 'area-profesores') . ' ' . $msg;
            }
            return new WP_Error('google', sprintf('HTTP %d: %s', $code, $msg));
        }

        return is_array($data) ? $data : array();
    }

    /**
     * @return array|WP_Error space resource (displayName, spaceDetails…)
     */
    public static function get_space($space_id) {
        return self::request('GET', 'spaces/' . rawurlencode($space_id));
    }

    /**
     * Change the name of a space.
     *
     * @return true|WP_Error
     */
    public static function rename_space($space_id, $name) {
        $name = mb_substr(trim($name), 0, 128);
        if ($name === '') {
            return new WP_Error('google', __('El nombre no puede estar vacío.', 'area-profesores'));
        }
        $r = self::request('PATCH', 'spaces/' . rawurlencode($space_id), array('updateMask' => 'displayName'), array('displayName' => $name));
        return is_wp_error($r) ? $r : true;
    }

    /**
     * Space name following the configured pattern.
     * Tokens: {curso}, {promocion} (año + grupo), {año}, {grupo}, {etapa}.
     */
    public static function format_nombre($curso_key, $promocion, $letra) {
        $s = self::get_settings();
        $c = Area_Profesores_Cursos::get_curso($curso_key);
        if (!$c) {
            return '';
        }
        $nombre = strtr($s['plantilla'], array(
            '{curso}'     => $c['label'],
            '{promocion}' => trim($promocion . ' ' . $letra),
            '{año}'       => (string) $promocion,
            '{grupo}'     => $letra,
            '{etapa}'     => Area_Profesores_Cursos::get_etapa_label($c['etapa']),
        ));
        return trim(preg_replace('/\s+/', ' ', $nombre));
    }
}
