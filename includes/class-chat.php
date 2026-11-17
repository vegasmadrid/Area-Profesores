<?php
/**
 * Comunicados a Familias (Google Chat)
 *
 * Lets teachers marked as "Dirección" publish the same announcement (text + optional
 * image) in several Google Chat spaces at once, using an incoming webhook per space.
 * Messages are signed with the webhook name configured in each space
 * (e.g. "Colegio Casa de la Virgen").
 *
 * - Backend: "Área Profesores > Espacios de Chat" (alta de espacios y webhooks, prueba de envío).
 * - Frontend: pestaña "Comunicados" del Área de Profesores, visible solo para Dirección.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Chat {

    const OPTION      = 'ap_chat_espacios';
    const POST_TYPE   = 'ap_comunicado';
    const META_DIR    = '_ap_is_direccion';
    const MAX_TEXT    = 4000;       // Google Chat admite 4096 caracteres por mensaje.
    const MAX_IMAGE   = 8388608;    // 8 MB
    const WEBHOOK_RE  = '#^https://chat\.googleapis\.com/v1/spaces/[A-Za-z0-9_-]+/messages\?#';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));

        // Backend
        add_action('admin_menu', array($this, 'register_admin_menu'), 15);
        add_action('admin_post_ap_save_espacio', array($this, 'handle_save'));
        add_action('admin_post_ap_delete_espacio', array($this, 'handle_delete'));
        add_action('admin_post_ap_test_espacio', array($this, 'handle_test'));
        add_action('wp_ajax_ap_admin_save_direccion', array($this, 'ajax_admin_save_direccion'));

        // Frontend (Dirección)
        add_action('wp_ajax_ap_chat_publicar', array($this, 'ajax_publicar'));
    }

    /* ---------------------------------------------------------------------
     * Permisos
     * ------------------------------------------------------------------- */

    /**
     * Is the user allowed to publish announcements? (Dirección or administrator)
     */
    public static function is_user_direccion($user_id) {
        if (!$user_id) {
            return false;
        }
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        return get_user_meta($user_id, self::META_DIR, true) === '1'
            && Area_Profesores_Admin::is_user_profesor($user_id);
    }

    /**
     * Only the "Dirección" flag (without the administrator fallback), for the admin UI.
     */
    public static function has_direccion_flag($user_id) {
        return get_user_meta($user_id, self::META_DIR, true) === '1';
    }

    /* ---------------------------------------------------------------------
     * Espacios
     * ------------------------------------------------------------------- */

    private static function normalize($e) {
        return array(
            'id'      => isset($e['id']) ? sanitize_key($e['id']) : '',
            'nombre'  => isset($e['nombre']) ? (string) $e['nombre'] : '',
            'grupo'   => isset($e['grupo']) ? (string) $e['grupo'] : '',
            'webhook' => isset($e['webhook']) ? (string) $e['webhook'] : '',
            'activo'  => !isset($e['activo']) || !empty($e['activo']),
            'orden'   => isset($e['orden']) ? (int) $e['orden'] : 0,
        );
    }

    /**
     * All spaces ordered by "orden" then name.
     */
    public static function get_espacios($only_active = false) {
        $raw = get_option(self::OPTION, array());
        if (!is_array($raw)) {
            $raw = array();
        }

        $out = array();
        foreach ($raw as $id => $e) {
            $e = self::normalize(array_merge((array) $e, array('id' => $id)));
            if ($only_active && (!$e['activo'] || $e['webhook'] === '')) {
                continue;
            }
            $out[$id] = $e;
        }

        uasort($out, function ($a, $b) {
            if ($a['orden'] === $b['orden']) {
                return strnatcasecmp($a['nombre'], $b['nombre']);
            }
            return $a['orden'] - $b['orden'];
        });

        return $out;
    }

    public static function get_espacio($id) {
        $all = self::get_espacios();
        return isset($all[$id]) ? $all[$id] : null;
    }

    private static function save_espacios($espacios) {
        $clean = array();
        foreach ($espacios as $id => $e) {
            $e = self::normalize($e);
            unset($e['id']);
            $clean[$id] = $e;
        }
        update_option(self::OPTION, $clean, false);
    }

    /**
     * Active spaces grouped by "grupo" (for the publishing form).
     */
    public static function get_espacios_agrupados() {
        $grupos = array();
        foreach (self::get_espacios(true) as $e) {
            $g = $e['grupo'] !== '' ? $e['grupo'] : __('Otros espacios', 'area-profesores');
            $grupos[$g][] = $e;
        }
        return $grupos;
    }

    public static function get_grupos() {
        $grupos = array();
        foreach (self::get_espacios() as $e) {
            if ($e['grupo'] !== '') {
                $grupos[$e['grupo']] = true;
            }
        }
        $grupos = array_keys($grupos);
        natcasesort($grupos);
        return array_values($grupos);
    }

    public static function is_valid_webhook($url) {
        return (bool) preg_match(self::WEBHOOK_RE, $url);
    }

    /**
     * Webhook URL with the secret parts hidden (for listings).
     */
    public static function mask_webhook($url) {
        if (preg_match('#/spaces/([A-Za-z0-9_-]+)/#', $url, $m)) {
            return 'spaces/' . $m[1] . ' · ' . __('clave oculta', 'area-profesores');
        }
        return $url === '' ? '—' : __('URL no válida', 'area-profesores');
    }

    /* ---------------------------------------------------------------------
     * Envío a Google Chat
     * ------------------------------------------------------------------- */

    /**
     * Build the Google Chat message payload.
     * The text goes in "text" (so it is shown in the families' push notification);
     * the image goes in a card below it.
     */
    public static function build_payload($titulo, $mensaje, $image_url = '') {
        $text = '';
        if ($titulo !== '') {
            $text .= '*' . $titulo . "*\n\n";
        }
        $text .= $mensaje;

        $payload = array('text' => trim($text));

        if ($image_url !== '') {
            $payload['cardsV2'] = array(
                array(
                    'cardId' => 'imagen',
                    'card'   => array(
                        'sections' => array(
                            array(
                                'widgets' => array(
                                    array(
                                        'image' => array(
                                            'imageUrl' => $image_url,
                                            'altText'  => $titulo !== '' ? $titulo : __('Imagen del comunicado', 'area-profesores'),
                                            'onClick'  => array(
                                                'openLink' => array('url' => $image_url),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            );
        }

        return $payload;
    }

    /**
     * POST a payload to a webhook.
     *
     * @return array { ok: bool, error: string }
     */
    public static function send_to_webhook($webhook, $payload) {
        if (!self::is_valid_webhook($webhook)) {
            return array('ok' => false, 'error' => __('La URL del webhook no es válida.', 'area-profesores'));
        }

        $response = wp_remote_post($webhook, array(
            'timeout' => 20,
            'headers' => array('Content-Type' => 'application/json; charset=UTF-8'),
            'body'    => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            return array('ok' => false, 'error' => $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return array('ok' => true, 'error' => '');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $msg = isset($body['error']['message']) ? $body['error']['message'] : wp_remote_retrieve_response_message($response);

        // Friendlier messages for the usual problems.
        if ($code === 404) {
            $msg = __('El webhook no existe o se ha borrado del espacio.', 'area-profesores') . ' (' . $msg . ')';
        } elseif ($code === 403) {
            $msg = __('Google no permite publicar en este espacio (revisa que los webhooks estén permitidos en la Consola de administración y en el espacio).', 'area-profesores') . ' (' . $msg . ')';
        } elseif ($code === 429) {
            $msg = __('Demasiados envíos seguidos. Inténtalo de nuevo en un minuto.', 'area-profesores');
        }

        return array('ok' => false, 'error' => sprintf('HTTP %d: %s', $code, $msg));
    }

    /* ---------------------------------------------------------------------
     * Historial (tipo de contenido privado)
     * ------------------------------------------------------------------- */

    public function register_post_type() {
        register_post_type(self::POST_TYPE, array(
            'labels'          => array(
                'name'          => __('Comunicados', 'area-profesores'),
                'singular_name' => __('Comunicado', 'area-profesores'),
            ),
            'public'          => false,
            'show_ui'         => false,
            'show_in_rest'    => false,
            'rewrite'         => false,
            'query_var'       => false,
            'supports'        => array('title', 'editor', 'author'),
            'capability_type' => 'post',
        ));
    }

    /**
     * Latest announcements with their delivery results.
     */
    public static function get_historial($limit = 15) {
        $posts = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'private',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $out = array();
        foreach ($posts as $p) {
            $out[] = self::format_comunicado($p);
        }
        return $out;
    }

    private static function format_comunicado($p) {
        $resultados = get_post_meta($p->ID, '_ap_resultados', true);
        $image_id = (int) get_post_meta($p->ID, '_ap_image_id', true);
        $author = get_userdata($p->post_author);

        return array(
            'id'         => $p->ID,
            'titulo'     => $p->post_title,
            'mensaje'    => $p->post_content,
            'fecha'      => date_i18n(get_option('date_format') . ' · H:i', strtotime($p->post_date)),
            'autor'      => $author ? $author->display_name : '',
            'image_id'   => $image_id,
            'image_url'  => $image_id ? self::get_image_url($image_id) : '',
            'thumb_url'  => $image_id ? (string) wp_get_attachment_image_url($image_id, 'thumbnail') : '',
            'resultados' => is_array($resultados) ? $resultados : array(),
        );
    }

    /**
     * Public URL of the image sent to Google (large size to keep it light).
     */
    private static function get_image_url($attachment_id) {
        $src = wp_get_attachment_image_src($attachment_id, 'large');
        $url = $src ? $src[0] : wp_get_attachment_url($attachment_id);
        return $url ? set_url_scheme($url, 'https') : '';
    }

    /* ---------------------------------------------------------------------
     * AJAX: publicar
     * ------------------------------------------------------------------- */

    public function ajax_publicar() {
        check_ajax_referer('area_profesores_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!self::is_user_direccion($user_id)) {
            wp_send_json_error(array('message' => __('Solo Dirección puede publicar comunicados.', 'area-profesores')), 403);
        }

        $espacio_ids = isset($_POST['espacios']) && is_array($_POST['espacios']) ? array_map('sanitize_key', wp_unslash($_POST['espacios'])) : array();
        $espacios = self::get_espacios(true);
        $espacio_ids = array_values(array_filter(array_unique($espacio_ids), function ($id) use ($espacios) {
            return isset($espacios[$id]);
        }));

        if (empty($espacio_ids)) {
            wp_send_json_error(array('message' => __('Selecciona al menos un espacio.', 'area-profesores')));
        }

        $comunicado_id = isset($_POST['comunicado_id']) ? absint($_POST['comunicado_id']) : 0;

        if ($comunicado_id) {
            // Reintento de un comunicado ya guardado (mismo texto e imagen).
            $post = get_post($comunicado_id);
            if (!$post || $post->post_type !== self::POST_TYPE) {
                wp_send_json_error(array('message' => __('Comunicado no encontrado.', 'area-profesores')));
            }
            $titulo = $post->post_title;
            $mensaje = $post->post_content;
            $image_id = (int) get_post_meta($comunicado_id, '_ap_image_id', true);
        } else {
            $titulo = isset($_POST['titulo']) ? sanitize_text_field(wp_unslash($_POST['titulo'])) : '';
            $mensaje = isset($_POST['mensaje']) ? sanitize_textarea_field(wp_unslash($_POST['mensaje'])) : '';
            $mensaje = str_replace(array("\r\n", "\r"), "\n", $mensaje);
            $image_id = 0;

            $has_file = !empty($_FILES['imagen']) && !empty($_FILES['imagen']['name']);

            if ($mensaje === '' && !$has_file) {
                wp_send_json_error(array('message' => __('Escribe un mensaje o adjunta una imagen.', 'area-profesores')));
            }
            if (mb_strlen($titulo . "\n\n" . $mensaje) > self::MAX_TEXT) {
                wp_send_json_error(array('message' => sprintf(__('El mensaje es demasiado largo (máximo %d caracteres).', 'area-profesores'), self::MAX_TEXT)));
            }

            if ($has_file) {
                $image_id = $this->handle_image_upload();
                if (is_wp_error($image_id)) {
                    wp_send_json_error(array('message' => $image_id->get_error_message()));
                }
            }

            $comunicado_id = wp_insert_post(array(
                'post_type'    => self::POST_TYPE,
                'post_status'  => 'private',
                'post_title'   => $titulo !== '' ? $titulo : wp_trim_words($mensaje, 8, '…'),
                'post_content' => $mensaje,
                'post_author'  => $user_id,
            ), true);

            if (is_wp_error($comunicado_id)) {
                wp_send_json_error(array('message' => __('No se pudo guardar el comunicado.', 'area-profesores')));
            }

            // Keep the real title (may be empty) separately from the display title.
            update_post_meta($comunicado_id, '_ap_titulo', $titulo);
            if ($image_id) {
                update_post_meta($comunicado_id, '_ap_image_id', $image_id);
                wp_update_post(array('ID' => $image_id, 'post_parent' => $comunicado_id));
            }
        }

        if (metadata_exists('post', $comunicado_id, '_ap_titulo')) {
            $titulo = (string) get_post_meta($comunicado_id, '_ap_titulo', true);
        }

        $image_url = $image_id ? self::get_image_url($image_id) : '';
        $payload = self::build_payload($titulo, $mensaje, $image_url);

        $resultados = get_post_meta($comunicado_id, '_ap_resultados', true);
        if (!is_array($resultados)) {
            $resultados = array();
        }

        $out = array();
        $ok_count = 0;
        foreach ($espacio_ids as $eid) {
            $e = $espacios[$eid];
            $r = self::send_to_webhook($e['webhook'], $payload);
            $resultados[$eid] = array(
                'nombre' => $e['nombre'],
                'ok'     => $r['ok'],
                'error'  => $r['error'],
                'fecha'  => current_time('mysql'),
            );
            $out[] = array('id' => $eid, 'nombre' => $e['nombre'], 'ok' => $r['ok'], 'error' => $r['error']);
            if ($r['ok']) {
                $ok_count++;
            }
        }

        update_post_meta($comunicado_id, '_ap_resultados', $resultados);

        wp_send_json_success(array(
            'comunicado_id' => $comunicado_id,
            'resultados'    => $out,
            'ok'            => $ok_count,
            'total'         => count($out),
            'historial_html'=> self::render_historial_html(),
        ));
    }

    /**
     * Validate and store the uploaded image in the media library.
     *
     * @return int|WP_Error attachment ID
     */
    private function handle_image_upload() {
        $file = $_FILES['imagen'];

        if (!empty($file['error'])) {
            return new WP_Error('upload', __('No se pudo subir la imagen (puede que sea demasiado grande).', 'area-profesores'));
        }
        if ($file['size'] > self::MAX_IMAGE) {
            return new WP_Error('upload', __('La imagen es demasiado grande (máximo 8 MB).', 'area-profesores'));
        }

        $allowed = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
        );
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed);
        if (empty($check['type']) || !in_array($check['type'], $allowed, true)) {
            return new WP_Error('upload', __('Formato no admitido. Usa una imagen JPG, PNG o GIF.', 'area-profesores'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('imagen', 0, array(), array(
            'test_form' => false,
            'mimes'     => $allowed,
        ));

        if (is_wp_error($attachment_id)) {
            return new WP_Error('upload', __('No se pudo guardar la imagen: ', 'area-profesores') . $attachment_id->get_error_message());
        }

        return (int) $attachment_id;
    }

    /**
     * History list HTML (also returned after publishing to refresh the tab).
     */
    public static function render_historial_html() {
        $historial = self::get_historial(15);
        ob_start();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/comunicados-historial.php';
        return ob_get_clean();
    }

    /* ---------------------------------------------------------------------
     * Backend
     * ------------------------------------------------------------------- */

    public function register_admin_menu() {
        add_submenu_page(
            'area-profesores',
            __('Espacios de Chat', 'area-profesores'),
            __('Espacios de Chat', 'area-profesores'),
            'manage_options',
            'area-profesores-chat',
            array($this, 'render_admin_page')
        );
    }

    private function admin_url($args = array()) {
        return add_query_arg($args, admin_url('admin.php?page=area-profesores-chat'));
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $espacios = self::get_espacios();
        $grupos = self::get_grupos();

        $editing = null;
        if (!empty($_GET['edit'])) {
            $editing = self::get_espacio(sanitize_key(wp_unslash($_GET['edit'])));
        }

        $message = isset($_GET['ap_msg']) ? sanitize_key(wp_unslash($_GET['ap_msg'])) : '';
        $test_error = get_transient('ap_chat_test_error_' . get_current_user_id());
        if ($test_error) {
            delete_transient('ap_chat_test_error_' . get_current_user_id());
        }

        $direccion_users = get_users(array(
            'meta_key'   => self::META_DIR,
            'meta_value' => '1',
            'orderby'    => 'display_name',
        ));

        $area_page_id = absint(Area_Profesores_Admin::get_setting('area_page_id', 0));
        // Google must download the images over HTTPS from the uploads folder.
        $uploads = wp_upload_dir();
        $is_https = strpos(home_url(), 'https://') === 0 || strpos($uploads['baseurl'], 'https://') === 0;

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-chat.php';
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_save_espacio');

        $id = isset($_POST['espacio_id']) ? sanitize_key(wp_unslash($_POST['espacio_id'])) : '';
        $nombre = isset($_POST['nombre']) ? sanitize_text_field(wp_unslash($_POST['nombre'])) : '';
        $webhook = isset($_POST['webhook']) ? trim(wp_unslash($_POST['webhook'])) : '';

        $raw = get_option(self::OPTION, array());
        if (!is_array($raw)) {
            $raw = array();
        }
        $is_new = ($id === '' || !isset($raw[$id]));

        // When editing, an empty webhook field keeps the stored one.
        if ($webhook === '' && !$is_new) {
            $webhook = isset($raw[$id]['webhook']) ? $raw[$id]['webhook'] : '';
        }

        if ($nombre === '' || $webhook === '') {
            wp_safe_redirect($this->admin_url(array('ap_msg' => 'missing', 'edit' => $id)));
            exit;
        }
        if (!self::is_valid_webhook($webhook)) {
            wp_safe_redirect($this->admin_url(array('ap_msg' => 'invalid', 'edit' => $id)));
            exit;
        }

        if ($is_new) {
            $id = 'e' . strtolower(wp_generate_password(10, false, false));
        }

        $orden = isset($_POST['orden']) && $_POST['orden'] !== '' ? (int) $_POST['orden'] : ($is_new ? (count($raw) + 1) * 10 : 0);

        $raw[$id] = array(
            'nombre'  => $nombre,
            'grupo'   => isset($_POST['grupo']) ? sanitize_text_field(wp_unslash($_POST['grupo'])) : '',
            'webhook' => esc_url_raw($webhook, array('https')),
            'activo'  => !empty($_POST['activo']),
            'orden'   => $orden,
        );

        self::save_espacios($raw);

        wp_safe_redirect($this->admin_url(array('ap_msg' => $is_new ? 'added' : 'updated')));
        exit;
    }

    public function handle_delete() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $id = isset($_GET['id']) ? sanitize_key(wp_unslash($_GET['id'])) : '';
        check_admin_referer('ap_delete_espacio_' . $id);

        $raw = get_option(self::OPTION, array());
        if (is_array($raw) && isset($raw[$id])) {
            unset($raw[$id]);
            self::save_espacios($raw);
        }

        wp_safe_redirect($this->admin_url(array('ap_msg' => 'deleted')));
        exit;
    }

    /**
     * Send a test message to one space.
     */
    public function handle_test() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $id = isset($_GET['id']) ? sanitize_key(wp_unslash($_GET['id'])) : '';
        check_admin_referer('ap_test_espacio_' . $id);

        $e = self::get_espacio($id);
        if (!$e) {
            wp_safe_redirect($this->admin_url(array('ap_msg' => 'test_error')));
            exit;
        }

        $logo = Area_Profesores_Admin::get_setting('logo_url', '');
        $payload = self::build_payload(
            __('Mensaje de prueba', 'area-profesores'),
            sprintf(__('Este es un mensaje de prueba enviado desde el Área de Profesores al espacio "%s". Ya se pueden publicar comunicados aquí.', 'area-profesores'), $e['nombre']),
            // The school logo also checks that Google can show images from this site.
            $logo ? set_url_scheme($logo, 'https') : ''
        );

        $r = self::send_to_webhook($e['webhook'], $payload);
        if (!$r['ok']) {
            set_transient('ap_chat_test_error_' . get_current_user_id(), $e['nombre'] . ': ' . $r['error'], 120);
        }

        wp_safe_redirect($this->admin_url(array('ap_msg' => $r['ok'] ? 'test_ok' : 'test_error')));
        exit;
    }

    /**
     * Toggle "Dirección" from the Profesores table.
     */
    public function ajax_admin_save_direccion() {
        check_ajax_referer('area_profesores_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Usuario no válido'));
        }

        update_user_meta($user_id, self::META_DIR, !empty($_POST['is_direccion']) ? '1' : '0');

        wp_send_json_success(array('message' => __('Guardado', 'area-profesores')));
    }
}
