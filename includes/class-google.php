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
 *
 * Publishing accounts: announcements can be published through the API as a user
 * of the school (e.g. colegio@): the image or PDF is uploaded to Google as an
 * attachment, as when posting by hand. An account connected as "compartida" can
 * be used by everyone who publishes (Dirección / Comunicación) without logging in
 * with it; a "personal" one only by the WordPress user who connected it.
 * With admin access the connected administrator also adds the shared account to
 * the spaces (chat.admin.memberships).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Google {

    const OPTION = 'ap_google_chat';
    const API    = 'https://chat.googleapis.com/v1/';
    const UPLOAD = 'https://chat.googleapis.com/upload/v1/';
    const OPT_CUENTAS = 'ap_google_cuentas';

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
        add_action('admin_post_ap_google_cuenta', array($this, 'handle_connect_cuenta'));
        add_action('admin_post_ap_google_cuenta_quitar', array($this, 'handle_quitar_cuenta'));
        add_action('admin_post_ap_google_miembros', array($this, 'handle_miembros'));
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
            'cuenta_espacios' => '', // shared account that is added to every space
            'scopes'          => '', // permissions granted to the administrator connection
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
            // To add the publishing account to the spaces.
            $s['admin_access'] ? 'https://www.googleapis.com/auth/chat.admin.memberships' : 'https://www.googleapis.com/auth/chat.memberships',
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

        self::ir_a_google(self::get_scopes(), array('modo' => 'admin'));
    }

    /**
     * Send the browser to Google's consent screen.
     */
    private static function ir_a_google($scopes, $datos, $login_hint = '') {
        $s = self::get_settings();
        $state = wp_generate_password(24, false, false);
        set_transient('ap_google_state_' . get_current_user_id(), array_merge($datos, array('state' => $state)), 15 * MINUTE_IN_SECONDS);

        $args = array(
            'client_id'     => rawurlencode($s['client_id']),
            'redirect_uri'  => rawurlencode(self::get_redirect_uri()),
            'response_type' => 'code',
            'scope'         => rawurlencode(implode(' ', $scopes)),
            'access_type'   => 'offline',
            'prompt'        => rawurlencode('consent select_account'),
            'include_granted_scopes' => 'true',
            'state'         => rawurlencode($state),
        );
        if ($login_hint !== '') {
            $args['login_hint'] = rawurlencode($login_hint);
        }
        wp_redirect(add_query_arg($args, 'https://accounts.google.com/o/oauth2/v2/auth'));
        exit;
    }

    public function handle_oauth_callback() {
        if (!isset($_GET['state']) || (!isset($_GET['code']) && !isset($_GET['error']))) {
            return; // Not our callback.
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }

        $guardado = get_transient('ap_google_state_' . get_current_user_id());
        delete_transient('ap_google_state_' . get_current_user_id());
        $expected = is_array($guardado) ? $guardado['state'] : $guardado;
        $modo = is_array($guardado) && isset($guardado['modo']) ? $guardado['modo'] : 'admin';
        if (!$expected || !hash_equals((string) $expected, (string) wp_unslash($_GET['state']))) {
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

        if ($modo === 'cuenta') {
            self::guardar_cuenta($data, !empty($guardado['compartida']));
        }

        $s['access_token'] = $data['access_token'];
        $s['expires_at'] = time() + (isset($data['expires_in']) ? (int) $data['expires_in'] : 3500) - 60;
        if (!empty($data['refresh_token'])) {
            $s['refresh_token'] = $data['refresh_token'];
        }
        $s['email'] = self::email_from_id_token(isset($data['id_token']) ? $data['id_token'] : '');
        $s['scopes'] = isset($data['scope']) ? (string) $data['scope'] : '';
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

    /* ---------------------------------------------------------------------
     * Cuentas para publicar comunicados (colegio@, personales…)
     * ------------------------------------------------------------------- */

    /** Has the administrator connection the permission to add members to the spaces? */
    public static function puede_anadir_miembros() {
        $s = self::get_settings();
        return self::is_connected() && strpos($s['scopes'], 'memberships') !== false;
    }

    /** Scopes of a publishing account: send messages with attachments. */
    private static function scopes_cuenta() {
        return array('openid', 'email', 'https://www.googleapis.com/auth/chat.messages.create');
    }

    /** @return array email => { email, refresh_token, access_token, expires_at, compartida, propietario, conectado } */
    public static function cuentas() {
        $c = get_option(self::OPT_CUENTAS, array());
        return is_array($c) ? $c : array();
    }

    private static function guardar_cuentas($c) {
        update_option(self::OPT_CUENTAS, $c, false);
    }

    /**
     * Accounts a user can publish with: the shared ones and their own personal ones.
     *
     * @return array email => label
     */
    public static function cuentas_para($user_id) {
        $out = array();
        foreach (self::cuentas() as $email => $c) {
            if ($c['refresh_token'] !== '' && (!empty($c['compartida']) || (int) $c['propietario'] === (int) $user_id)) {
                $out[$email] = $email . (!empty($c['compartida']) ? '' : ' ' . __('(solo tú)', 'area-profesores'));
            }
        }
        // The shared ones first.
        uksort($out, function ($a, $b) {
            $c = self::cuentas();
            return array(empty($c[$a]['compartida']), $a) <=> array(empty($c[$b]['compartida']), $b);
        });
        return $out;
    }

    public static function puede_usar_cuenta($user_id, $email) {
        return isset(self::cuentas_para($user_id)[$email]);
    }

    /** Shared account added to every space (the one chosen, or the first shared one). */
    public static function cuenta_espacios() {
        $s = self::get_settings();
        $c = self::cuentas();
        if ($s['cuenta_espacios'] !== '' && isset($c[$s['cuenta_espacios']])) {
            return $s['cuenta_espacios'];
        }
        foreach ($c as $email => $x) {
            if (!empty($x['compartida'])) {
                return $email;
            }
        }
        return '';
    }

    public function handle_connect_cuenta() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_google_cuenta');
        if (!self::is_configured()) {
            self::back(__('Primero guarda el ID de cliente y el secreto.', 'area-profesores'), 'error');
        }
        $hint = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        self::ir_a_google(self::scopes_cuenta(), array('modo' => 'cuenta', 'compartida' => !empty($_POST['compartida'])), $hint);
    }

    /** OAuth callback of a publishing account. */
    private static function guardar_cuenta($data, $compartida) {
        $email = strtolower(self::email_from_id_token(isset($data['id_token']) ? $data['id_token'] : ''));
        if ($email === '') {
            self::back(__('Google no ha indicado el correo de la cuenta. Vuelve a intentarlo.', 'area-profesores'), 'error');
        }
        $c = self::cuentas();
        $old = isset($c[$email]) ? $c[$email] : array();
        $refresh = !empty($data['refresh_token']) ? $data['refresh_token'] : (isset($old['refresh_token']) ? $old['refresh_token'] : '');
        if ($refresh === '') {
            self::back(__('Google no ha devuelto un permiso permanente. Quita la cuenta y vuelve a conectarla.', 'area-profesores'), 'error');
        }
        $c[$email] = array(
            'email'         => $email,
            'refresh_token' => $refresh,
            'access_token'  => $data['access_token'],
            'expires_at'    => time() + (isset($data['expires_in']) ? (int) $data['expires_in'] : 3500) - 60,
            'compartida'    => (bool) $compartida,
            'propietario'   => $compartida ? 0 : get_current_user_id(),
            'conectado'     => current_time('mysql'),
        );
        self::guardar_cuentas($c);
        self::back($compartida
            ? sprintf(__('%s conectada: Dirección y Comunicación ya pueden publicar los comunicados con esta cuenta. Pulsa "Añadir a todos los espacios" para que entre en ellos.', 'area-profesores'), $email)
            : sprintf(__('%s conectada: solo tú puedes publicar con esta cuenta.', 'area-profesores'), $email));
    }

    public function handle_quitar_cuenta() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_google_cuenta_quitar');
        $email = isset($_POST['email']) ? strtolower(sanitize_email(wp_unslash($_POST['email']))) : '';
        $c = self::cuentas();
        if (isset($c[$email])) {
            if (!empty($c[$email]['refresh_token'])) {
                wp_remote_post('https://oauth2.googleapis.com/revoke', array('timeout' => 10, 'body' => array('token' => $c[$email]['refresh_token'])));
            }
            unset($c[$email]);
            self::guardar_cuentas($c);
        }
        self::back(sprintf(__('Cuenta %s quitada.', 'area-profesores'), $email));
    }

    /**
     * Valid access token of a publishing account.
     *
     * @return string|WP_Error
     */
    private static function token_cuenta($email) {
        $c = self::cuentas();
        if (empty($c[$email]['refresh_token'])) {
            return new WP_Error('google', sprintf(__('La cuenta %s no está conectada.', 'area-profesores'), $email));
        }
        if (!empty($c[$email]['access_token']) && (int) $c[$email]['expires_at'] > time()) {
            return $c[$email]['access_token'];
        }
        $s = self::get_settings();
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 20,
            'body'    => array(
                'client_id'     => $s['client_id'],
                'client_secret' => $s['client_secret'],
                'refresh_token' => $c[$email]['refresh_token'],
                'grant_type'    => 'refresh_token',
            ),
        ));
        $data = is_wp_error($response) ? array() : json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            $err = is_wp_error($response) ? $response->get_error_message() : (isset($data['error_description']) ? $data['error_description'] : __('permiso revocado o caducado', 'area-profesores'));
            return new WP_Error('google', sprintf(__('No se pudo usar la cuenta %1$s: %2$s. Vuelve a conectarla en Área Profesores › Aulas.', 'area-profesores'), $email, $err));
        }
        $c[$email]['access_token'] = $data['access_token'];
        $c[$email]['expires_at'] = time() + (isset($data['expires_in']) ? (int) $data['expires_in'] : 3500) - 60;
        self::guardar_cuentas($c);
        return $data['access_token'];
    }

    /** Error text of a Chat API response. */
    private static function error_api($response, $contexto) {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $msg = isset($data['error']['message']) ? $data['error']['message'] : wp_remote_retrieve_response_message($response);
        if ($code === 403 || $code === 404) {
            $msg = sprintf(__('La cuenta %s no es miembro de este espacio o no puede publicar en él.', 'area-profesores'), $contexto) . ' (' . $msg . ')';
        } elseif ($code === 429) {
            $msg = __('Demasiados envíos seguidos. Inténtalo de nuevo en un minuto.', 'area-profesores');
        }
        return sprintf('HTTP %d: %s', $code, $msg);
    }

    /**
     * Publish a message in a space as a publishing account, with an optional file
     * (image or PDF) uploaded to Google as an attachment.
     *
     * @param array|null $archivo { path, nombre, mime }
     * @return array { ok, error, codigo }
     */
    public static function publicar($email, $space_id, $texto, $archivo = null) {
        $token = self::token_cuenta($email);
        if (is_wp_error($token)) {
            return array('ok' => false, 'error' => $token->get_error_message(), 'codigo' => 0);
        }
        $parent = 'spaces/' . rawurlencode($space_id);
        $body = array('text' => $texto);

        if ($archivo && is_readable($archivo['path'])) {
            $limite = '====' . wp_generate_password(24, false, false);
            $datos = "--$limite\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n" . wp_json_encode(array('filename' => $archivo['nombre'])) . "\r\n"
                . "--$limite\r\nContent-Type: " . $archivo['mime'] . "\r\n\r\n" . file_get_contents($archivo['path']) . "\r\n--$limite--";
            $r = wp_remote_post(self::UPLOAD . $parent . '/attachments:upload?uploadType=multipart', array(
                'timeout' => 60,
                'headers' => array('Authorization' => 'Bearer ' . $token, 'Content-Type' => 'multipart/related; boundary=' . $limite),
                'body'    => $datos,
            ));
            $code = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
            $up = is_wp_error($r) ? array() : json_decode(wp_remote_retrieve_body($r), true);
            if ($code < 200 || $code >= 300 || empty($up['attachmentDataRef'])) {
                return array('ok' => false, 'error' => __('No se pudo subir el archivo a Google:', 'area-profesores') . ' ' . self::error_api($r, $email), 'codigo' => $code);
            }
            $body['attachment'] = array(array('attachmentDataRef' => $up['attachmentDataRef']));
        }

        $r = wp_remote_post(self::API . $parent . '/messages', array(
            'timeout' => 30,
            'headers' => array('Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json; charset=UTF-8'),
            'body'    => wp_json_encode($body),
        ));
        $code = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
        if ($code >= 200 && $code < 300) {
            return array('ok' => true, 'error' => '', 'codigo' => $code);
        }
        return array('ok' => false, 'error' => self::error_api($r, $email), 'codigo' => $code);
    }

    /**
     * Add a person of the school to a space (with admin access the connected
     * administrator does not need to be a member). Already a member = ok.
     *
     * @return true|WP_Error
     */
    public static function anadir_miembro($space_id, $email) {
        if (!self::is_connected()) {
            return new WP_Error('google', __('Conecta primero la cuenta de administrador (arriba).', 'area-profesores'));
        }
        $r = self::request('POST', 'spaces/' . rawurlencode($space_id) . '/members', array(), array(
            'member' => array('name' => 'users/' . $email, 'type' => 'HUMAN'),
        ));
        if (is_wp_error($r) && strpos($r->get_error_message(), 'HTTP 409') !== 0) {
            return $r;
        }
        return true;
    }

    /**
     * Add the shared account to every active space with an ID.
     *
     * @return array { ok, errores: [titulo => mensaje] }
     */
    public static function anadir_a_espacios($email = '') {
        $email = $email !== '' ? $email : self::cuenta_espacios();
        $out = array('ok' => 0, 'errores' => array());
        if ($email === '') {
            $out['errores'][''] = __('No hay ninguna cuenta compartida conectada.', 'area-profesores');
            return $out;
        }
        foreach (Area_Profesores_Chat::get_espacios() as $e) {
            if ($e['space_id'] === '' || $e['archivada'] || !$e['activo']) {
                continue;
            }
            $r = self::anadir_miembro($e['space_id'], $email);
            if (is_wp_error($r)) {
                $out['errores'][$e['titulo']] = $r->get_error_message();
            } else {
                $out['ok']++;
            }
        }
        return $out;
    }

    public function handle_miembros() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_google_miembros');
        $s = self::get_settings();
        if (isset($_POST['cuenta_espacios'])) {
            $s['cuenta_espacios'] = strtolower(sanitize_email(wp_unslash($_POST['cuenta_espacios'])));
            self::save_settings($s);
        }
        $r = self::anadir_a_espacios();
        $txt = sprintf(__('%1$s está en %2$d espacios.', 'area-profesores'), self::cuenta_espacios(), $r['ok']);
        if ($r['errores']) {
            $l = array();
            foreach (array_slice($r['errores'], 0, 5, true) as $t => $m) {
                $l[] = ($t !== '' ? $t . ': ' : '') . $m;
            }
            $txt .= ' ' . sprintf(__('No se ha podido en %d:', 'area-profesores'), count($r['errores'])) . ' ' . implode(' · ', $l);
        }
        self::back($txt, $r['errores'] ? 'error' : 'success');
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
