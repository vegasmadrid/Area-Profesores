<?php
/**
 * Comunicados a Familias (Google Chat)
 *
 * Lets teachers marked as "Dirección" publish the same announcement (text + optional
 * image) in several Google Chat spaces at once, now or at a scheduled date/time,
 * using an incoming webhook per space. Messages are signed with the webhook name
 * configured in each space (e.g. "Colegio Casa de la Virgen").
 *
 * Each family space belongs to a promoción (cohort) + group and has an assigned
 * course (aula). The course only changes when an administrator runs
 * "Pasar de curso" in "Área Profesores > Aulas".
 *
 * - Backend: "Área Profesores > Espacios de Chat".
 * - Frontend: pestaña "Comunicados" del Área de Profesores, visible solo para Dirección.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Chat {

    const OPTION      = 'ap_chat_espacios';
    const POST_TYPE   = 'ap_comunicado';
    const META_DIR    = '_ap_is_direccion';
    const CRON_HOOK   = 'ap_chat_enviar_programado';
    const MAX_TEXT    = 4000;       // Google Chat admite 4096 caracteres por mensaje.
    const MAX_IMAGE   = 8388608;    // 8 MB
    const MAX_DIAS_PROGRAMACION = 180;
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
        add_action('init', array($this, 'maybe_migrate'), 20);
        add_action('init', array($this, 'rescue_overdue'), 30);
        add_action(self::CRON_HOOK, array($this, 'cron_enviar_programado'));

        // Backend
        add_action('admin_menu', array($this, 'register_admin_menu'), 15);
        add_action('admin_post_ap_save_espacio', array($this, 'handle_save'));
        add_action('admin_post_ap_delete_espacio', array($this, 'handle_delete'));
        add_action('admin_post_ap_test_espacio', array($this, 'handle_test'));
        add_action('admin_post_ap_bulk_webhooks', array($this, 'handle_bulk_webhooks'));
        add_action('wp_ajax_ap_admin_save_direccion', array($this, 'ajax_admin_save_direccion'));

        // Frontend (Dirección)
        add_action('wp_ajax_ap_chat_publicar', array($this, 'ajax_publicar'));
        add_action('wp_ajax_ap_chat_cancelar', array($this, 'ajax_cancelar'));
        add_action('wp_ajax_ap_chat_enviar_ahora', array($this, 'ajax_enviar_ahora'));
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

    /**
     * Spaces of the families of each promoción (from the school's list).
     * promoción, grupo ('' = grupo único), ID del espacio de Google Chat.
     */
    private static function get_promociones_iniciales() {
        return array(
            array(2043, 'A', 'AAQA5y9T4do'),
            array(2043, 'B', 'AAQAOjBzDsI'),
            array(2042, '',  'AAQA5gaLZMw'),
            array(2041, 'A', 'AAAAsnUVkYw'),
            array(2041, 'B', 'AAQAq-R4GCE'),
            array(2040, 'A', 'AAAAQyDxtxw'),
            array(2040, 'B', 'AAQAw8H0tXI'),
            array(2039, 'A', 'AAAAnHXaV-c'),
            array(2039, 'B', 'AAAAfdmLcF4'),
            array(2038, 'A', 'AAAAozErdSo'),
            array(2038, 'B', 'AAAAUREh0So'),
            array(2037, 'A', 'AAAAjJlMtJQ'),
            array(2037, 'B', 'AAAA4ss70YM'),
            array(2036, 'A', 'AAAAmU4ziAc'),
            array(2036, 'B', 'AAAAmrXxg98'),
            array(2035, 'A', 'AAAADpdJQa8'),
            array(2035, 'B', 'AAAARcFl1Gw'),
            array(2034, 'A', 'AAAAiwO67k0'),
            array(2034, 'B', 'AAAAlBb3bCw'),
            array(2033, 'A', 'AAAAmnlGOC0'),
            array(2033, 'B', 'AAAAGPhrs9M'),
            array(2032, 'A', 'AAAAzf4Ad3s'),
            array(2032, 'B', 'AAAAW3ck6Ac'),
            array(2031, 'A', 'AAAAyINkiIQ'),
            array(2031, 'B', 'AAAABmvtQ2Q'),
            array(2030, 'A', 'AAAA9gQj_mg'),
            array(2030, 'B', 'AAAAE7z_viI'),
            array(2029, 'A', 'AAAATKQ5bQ8'),
            array(2029, 'B', 'AAAAKgauMUA'),
            array(2028, '',  'AAAAk7qTJIs'),
            array(2027, '',  'AAAABpDJ9c4'),
        );
    }

    private static function normalize($e) {
        $tipo = isset($e['tipo']) && $e['tipo'] === 'promocion' ? 'promocion' : 'otro';
        $letra = isset($e['letra']) ? strtoupper((string) $e['letra']) : '';
        return array(
            'id'        => isset($e['id']) ? sanitize_key($e['id']) : '',
            'tipo'      => $tipo,
            'nombre'    => isset($e['nombre']) ? (string) $e['nombre'] : '',
            'grupo'     => isset($e['grupo']) ? (string) $e['grupo'] : '',
            'promocion' => isset($e['promocion']) ? (int) $e['promocion'] : 0,
            'letra'     => in_array($letra, array('A', 'B', 'C', 'D'), true) ? $letra : '',
            'space_id'  => isset($e['space_id']) ? preg_replace('/[^A-Za-z0-9_-]/', '', (string) $e['space_id']) : '',
            'webhook'   => isset($e['webhook']) ? (string) $e['webhook'] : '',
            'activo'    => !isset($e['activo']) || !empty($e['activo']),
            'orden'     => isset($e['orden']) ? (int) $e['orden'] : 0,
            'curso'     => isset($e['curso']) && Area_Profesores_Cursos::get_curso($e['curso']) ? $e['curso'] : '',
            'publico'   => !isset($e['publico']) || !empty($e['publico']),
            'nombre_chat' => isset($e['nombre_chat']) ? (string) $e['nombre_chat'] : '',
        );
    }

    /**
     * Add the computed fields (current course, etapa, display name, status).
     */
    private static function enrich($e) {
        $e['etapa'] = '';
        $promo_label = $e['promocion'] ? trim(sprintf(__('Promoción %d', 'area-profesores'), $e['promocion']) . ' ' . $e['letra']) : '';

        if ($e['tipo'] === 'promocion') {
            $c = $e['curso'] !== '' ? Area_Profesores_Cursos::get_curso($e['curso']) : null;
            if ($c) {
                $e['etapa'] = $c['etapa'];
                $e['titulo'] = $c['label'];
                $e['subtitulo'] = $promo_label;
                $e['grupo_key'] = $c['etapa'];
                $e['grupo_label'] = Area_Profesores_Cursos::get_etapa_label($c['etapa']);
                $etapas = array_keys(Area_Profesores_Cursos::get_etapas());
                $e['sort'] = array(array_search($c['etapa'], $etapas, true), $c['orden'], $e['letra']);
            } else {
                $e['titulo'] = $promo_label !== '' ? $promo_label : __('Espacio sin curso', 'area-profesores');
                $e['subtitulo'] = __('Sin curso asignado', 'area-profesores');
                $e['grupo_key'] = 'sin_curso';
                $e['grupo_label'] = __('Espacios sin curso asignado', 'area-profesores');
                $e['sort'] = array(98, -$e['promocion'], $e['letra']);
            }
        } else {
            $e['titulo'] = $e['nombre'];
            $e['subtitulo'] = '';
            $e['grupo_key'] = 'otro:' . sanitize_title($e['grupo']);
            $e['grupo_label'] = $e['grupo'] !== '' ? $e['grupo'] : __('Otros espacios', 'area-profesores');
            $e['sort'] = array(50, $e['orden'], $e['nombre']);
        }
        $e['egresada'] = false;

        $e['conectado'] = $e['webhook'] !== '';
        $e['publicable'] = $e['activo'] && $e['conectado'] && ($e['tipo'] === 'otro' || $e['curso'] !== '');
        $e['chat_url'] = $e['space_id'] !== '' ? 'https://chat.google.com/room/' . rawurlencode($e['space_id']) : '';

        return $e;
    }

    /**
     * All spaces, ordered by etapa / course / name.
     */
    public static function get_espacios($only_publicables = false) {
        $raw = get_option(self::OPTION, array());
        if (!is_array($raw)) {
            $raw = array();
        }

        $out = array();
        foreach ($raw as $id => $e) {
            $e = self::enrich(self::normalize(array_merge((array) $e, array('id' => $id))));
            if ($only_publicables && !$e['publicable']) {
                continue;
            }
            $out[$id] = $e;
        }

        uasort($out, function ($a, $b) {
            foreach (array(0, 1, 2) as $i) {
                if ($a['sort'][$i] != $b['sort'][$i]) {
                    return is_string($a['sort'][$i]) ? strnatcasecmp($a['sort'][$i], $b['sort'][$i]) : ($a['sort'][$i] < $b['sort'][$i] ? -1 : 1);
                }
            }
            return 0;
        });

        return $out;
    }

    public static function get_espacio($id) {
        $all = self::get_espacios();
        return isset($all[$id]) ? $all[$id] : null;
    }

    private static function get_raw_espacios() {
        $raw = get_option(self::OPTION, array());
        return is_array($raw) ? $raw : array();
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
     * Spaces grouped for display: grupo_key => array(label, espacios).
     */
    public static function agrupar($espacios) {
        $grupos = array();
        foreach ($espacios as $e) {
            if (!isset($grupos[$e['grupo_key']])) {
                $grupos[$e['grupo_key']] = array('label' => $e['grupo_label'], 'espacios' => array());
            }
            $grupos[$e['grupo_key']]['espacios'][] = $e;
        }
        return $grupos;
    }

    /**
     * Publishable spaces grouped by etapa (for the publishing form).
     */
    public static function get_espacios_agrupados() {
        return self::agrupar(self::get_espacios(true));
    }

    public static function get_grupos_otros() {
        $grupos = array();
        foreach (self::get_espacios() as $e) {
            if ($e['tipo'] === 'otro' && $e['grupo'] !== '') {
                $grupos[$e['grupo']] = true;
            }
        }
        $grupos = array_keys($grupos);
        natcasesort($grupos);
        return array_values($grupos);
    }

    /**
     * Courses that have no family space this school year.
     */
    public static function get_cursos_sin_espacio() {
        $con = array();
        foreach (self::get_espacios() as $e) {
            if ($e['curso'] !== '') {
                $con[$e['curso']] = true;
            }
        }
        $sin = array();
        foreach (Area_Profesores_Cursos::get_cursos() as $key => $c) {
            if (!isset($con[$key])) {
                $sin[$key] = $c['label'];
            }
        }
        return $sin;
    }

    public static function is_valid_webhook($url) {
        return (bool) preg_match(self::WEBHOOK_RE, $url);
    }

    /**
     * Space ID contained in a webhook URL ('' if none).
     */
    public static function get_space_id_from_webhook($url) {
        return preg_match('#/spaces/([A-Za-z0-9_-]+)/messages#', (string) $url, $m) ? $m[1] : '';
    }

    /**
     * Extract a space ID from a webhook, a Chat link or a bare ID.
     */
    public static function parse_space_id($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        foreach (array('#/spaces/([A-Za-z0-9_-]+)#', '#/room/([A-Za-z0-9_-]+)#', '#/space/([A-Za-z0-9_-]+)#', '#/chat/([A-Za-z0-9_-]{8,})#') as $re) {
            if (preg_match($re, $text, $m)) {
                return $m[1];
            }
        }
        return preg_match('#^[A-Za-z0-9_-]{6,}$#', $text) ? $text : '';
    }

    /**
     * Webhook URL with the secret parts hidden (for listings).
     */
    public static function mask_webhook($url) {
        $id = self::get_space_id_from_webhook($url);
        if ($id !== '') {
            return 'spaces/' . $id . ' · ' . __('clave oculta', 'area-profesores');
        }
        return $url === '' ? '—' : __('URL no válida', 'area-profesores');
    }

    /**
     * One-time data migration: create the promoción spaces of the school and move
     * webhooks of spaces created by hand to their promoción.
     */
    public function maybe_migrate() {
        $version = (string) get_option('ap_chat_version');
        if ($version === '3') {
            return;
        }
        if ($version !== '2') {
            $this->migrate_v2();
        }

        // v3: the course of each space is stored (it no longer moves up automatically).
        $raw = self::get_raw_espacios();
        foreach ($raw as $id => $e) {
            $n = self::normalize($e);
            if ($n['tipo'] === 'promocion' && $n['curso'] === '' && $n['promocion']) {
                $c = Area_Profesores_Cursos::get_curso_de_promocion($n['promocion'], $n['letra']);
                $raw[$id]['curso'] = $c ? $c['key'] : '';
            }
        }
        self::save_espacios($raw);
        update_option('ap_chat_version', '3', false);
    }

    private function migrate_v2() {
        $raw = self::get_raw_espacios();

        // Index existing spaces by space ID.
        $by_space = array();
        foreach ($raw as $id => $e) {
            $e = self::normalize($e);
            $sid = $e['space_id'] !== '' ? $e['space_id'] : self::get_space_id_from_webhook($e['webhook']);
            if ($sid !== '') {
                $by_space[$sid] = $id;
            }
        }

        foreach (self::get_promociones_iniciales() as $p) {
            list($promo, $letra, $sid) = $p;
            $new = array(
                'tipo'      => 'promocion',
                'promocion' => $promo,
                'letra'     => $letra,
                'space_id'  => $sid,
                'webhook'   => '',
                'activo'    => true,
            );

            if (isset($by_space[$sid])) {
                // A space with this ID already exists (created by hand): keep its webhook.
                $old_id = $by_space[$sid];
                $old = self::normalize($raw[$old_id]);
                $new['webhook'] = $old['webhook'];
                $new['activo'] = $old['activo'];
                unset($raw[$old_id]);
            }

            $raw['p' . $promo . strtolower($letra !== '' ? $letra : 'u')] = $new;
        }

        self::save_espacios($raw);
        update_option('ap_chat_version', '2', false);
    }

    /**
     * Update some fields of a space (used by Aulas and "Pasar de curso").
     */
    public static function update_espacio($id, $fields) {
        $raw = self::get_raw_espacios();
        if (!isset($raw[$id])) {
            return false;
        }
        $raw[$id] = array_merge((array) $raw[$id], $fields);
        self::save_espacios($raw);
        return true;
    }

    /**
     * Replace the whole list (used to undo "Pasar de curso").
     */
    public static function restore_raw_espacios($raw) {
        if (is_array($raw)) {
            self::save_espacios($raw);
        }
    }

    public static function export_raw_espacios() {
        return self::get_raw_espacios();
    }

    /**
     * Spaces assigned to a course.
     */
    public static function get_espacios_de_curso($curso) {
        return array_filter(self::get_espacios(), function ($e) use ($curso) {
            return $e['curso'] === $curso;
        });
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

    /**
     * Send a stored announcement to some spaces and record the results.
     *
     * @return array list of { id, nombre, ok, error }
     */
    private static function enviar_comunicado($post_id, $espacio_ids) {
        $post = get_post($post_id);
        $titulo = (string) get_post_meta($post_id, '_ap_titulo', true);
        $image_id = (int) get_post_meta($post_id, '_ap_image_id', true);
        $payload = self::build_payload($titulo, $post->post_content, $image_id ? self::get_image_url($image_id) : '');

        $espacios = self::get_espacios();
        $resultados = get_post_meta($post_id, '_ap_resultados', true);
        if (!is_array($resultados)) {
            $resultados = array();
        }

        $out = array();
        foreach ($espacio_ids as $eid) {
            if (!isset($espacios[$eid])) {
                continue;
            }
            $e = $espacios[$eid];
            if (!$e['publicable']) {
                $r = array('ok' => false, 'error' => __('El espacio está desactivado o ya no tiene webhook.', 'area-profesores'));
            } else {
                $r = self::send_to_webhook($e['webhook'], $payload);
            }
            $resultados[$eid] = array(
                'nombre' => $e['titulo'],
                'ok'     => $r['ok'],
                'error'  => $r['error'],
                'fecha'  => current_time('mysql'),
            );
            $out[] = array('id' => $eid, 'nombre' => $e['titulo'], 'ok' => $r['ok'], 'error' => $r['error']);
        }

        update_post_meta($post_id, '_ap_resultados', $resultados);
        update_post_meta($post_id, '_ap_estado', 'enviado');
        if (!get_post_meta($post_id, '_ap_enviado_gmt', true)) {
            update_post_meta($post_id, '_ap_enviado_gmt', time());
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     * Programación
     * ------------------------------------------------------------------- */

    /**
     * Atomic lock so a scheduled announcement is never sent twice.
     */
    private static function lock($post_id) {
        $key = 'ap_chat_lock_' . (int) $post_id;
        if (add_option($key, time(), '', 'no')) {
            return true;
        }
        // Stale lock (process died): take it over after 10 minutes.
        if ((int) get_option($key) < time() - 600) {
            update_option($key, time(), false);
            return true;
        }
        return false;
    }

    private static function unlock($post_id) {
        delete_option('ap_chat_lock_' . (int) $post_id);
    }

    /**
     * Send a scheduled announcement (from cron, the rescue check or "Enviar ahora").
     *
     * @return array|false results, or false if it was not pending
     */
    public static function procesar_programado($post_id) {
        if (get_post_type($post_id) !== self::POST_TYPE || get_post_meta($post_id, '_ap_estado', true) !== 'programado') {
            return false;
        }
        if (!self::lock($post_id)) {
            return false;
        }

        // Re-check after taking the lock.
        if (get_post_meta($post_id, '_ap_estado', true) !== 'programado') {
            self::unlock($post_id);
            return false;
        }

        update_post_meta($post_id, '_ap_estado', 'enviando');
        wp_clear_scheduled_hook(self::CRON_HOOK, array((int) $post_id));

        $espacio_ids = (array) get_post_meta($post_id, '_ap_espacios', true);
        $out = self::enviar_comunicado($post_id, $espacio_ids);
        self::unlock($post_id);

        $fallidos = array_filter($out, function ($r) {
            return !$r['ok'];
        });
        if (!empty($fallidos)) {
            self::notificar_fallos($post_id, $out);
        }

        return $out;
    }

    public function cron_enviar_programado($post_id) {
        self::procesar_programado((int) $post_id);
    }

    /**
     * Safety net when WP-Cron did not run (few visits, cron disabled…):
     * at most every 2 minutes, send scheduled announcements that are overdue.
     */
    public function rescue_overdue() {
        if (get_transient('ap_chat_rescue_check')) {
            return;
        }
        set_transient('ap_chat_rescue_check', 1, 2 * MINUTE_IN_SECONDS);

        $ids = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'private',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'meta_query'     => array(
                array('key' => '_ap_estado', 'value' => 'programado'),
                array('key' => '_ap_programado_gmt', 'value' => time() - 120, 'compare' => '<=', 'type' => 'NUMERIC'),
            ),
        ));

        foreach ($ids as $id) {
            self::procesar_programado($id);
        }
    }

    /**
     * E-mail the author when a scheduled announcement could not be delivered everywhere.
     */
    private static function notificar_fallos($post_id, $out) {
        $post = get_post($post_id);
        $author = get_userdata($post->post_author);
        if (!$author || !$author->user_email) {
            return;
        }

        $lines = array();
        foreach ($out as $r) {
            $lines[] = ($r['ok'] ? '✓ ' : '✗ ') . $r['nombre'] . ($r['ok'] ? '' : ' — ' . $r['error']);
        }

        $area_page_id = absint(Area_Profesores_Admin::get_setting('area_page_id', 0));
        $link = $area_page_id ? get_permalink($area_page_id) . '#comunicados' : home_url('/');

        $body = sprintf(__('El comunicado programado "%s" no se ha podido publicar en todos los espacios:', 'area-profesores'), $post->post_title)
            . "\n\n" . implode("\n", $lines) . "\n\n"
            . __('Puedes reintentar los espacios con error desde la pestaña Comunicados del Área de Profesores:', 'area-profesores')
            . "\n" . $link . "\n";

        wp_mail(
            $author->user_email,
            __('[Área Profesores] Comunicado programado con errores', 'area-profesores'),
            $body
        );
    }

    /**
     * Parse "Y-m-d" + "H:i" in the site's time zone. Returns a UTC timestamp or WP_Error.
     */
    private static function parse_fecha_programada($fecha, $hora) {
        $dt = DateTime::createFromFormat('Y-m-d H:i', trim($fecha) . ' ' . trim($hora), wp_timezone());
        if (!$dt) {
            return new WP_Error('fecha', __('Indica una fecha y una hora válidas para programar el comunicado.', 'area-profesores'));
        }
        $ts = $dt->getTimestamp();
        if ($ts < time() + 60) {
            return new WP_Error('fecha', __('La fecha programada ya ha pasado. Elige un momento futuro o publica ahora.', 'area-profesores'));
        }
        if ($ts > time() + self::MAX_DIAS_PROGRAMACION * DAY_IN_SECONDS) {
            return new WP_Error('fecha', sprintf(__('Solo se puede programar con %d días de antelación como máximo.', 'area-profesores'), self::MAX_DIAS_PROGRAMACION));
        }
        return $ts;
    }

    /**
     * "martes, 18 de noviembre de 2026 a las 08:00"
     */
    public static function format_fecha($ts) {
        return wp_date(__('l, j \d\e F \d\e Y \a \l\a\s H:i', 'area-profesores'), $ts);
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
     * Sent / cancelled announcements (newest first).
     */
    public static function get_historial($limit = 15) {
        $posts = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'private',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                'relation' => 'OR',
                array('key' => '_ap_estado', 'value' => array('programado', 'enviando'), 'compare' => 'NOT IN'),
                array('key' => '_ap_estado', 'compare' => 'NOT EXISTS'),
            ),
        ));

        return array_map(array(__CLASS__, 'format_comunicado'), $posts);
    }

    /**
     * Scheduled announcements (soonest first).
     */
    public static function get_programados() {
        $posts = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'private',
            'posts_per_page' => 50,
            'meta_key'       => '_ap_programado_gmt',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => array(
                array('key' => '_ap_estado', 'value' => array('programado', 'enviando'), 'compare' => 'IN'),
            ),
        ));

        return array_map(array(__CLASS__, 'format_comunicado'), $posts);
    }

    public static function format_comunicado($p) {
        $resultados = get_post_meta($p->ID, '_ap_resultados', true);
        $image_id = (int) get_post_meta($p->ID, '_ap_image_id', true);
        $author = get_userdata($p->post_author);
        $estado = (string) get_post_meta($p->ID, '_ap_estado', true);
        $programado = (int) get_post_meta($p->ID, '_ap_programado_gmt', true);
        $enviado = (int) get_post_meta($p->ID, '_ap_enviado_gmt', true);
        $titulo = metadata_exists('post', $p->ID, '_ap_titulo') ? (string) get_post_meta($p->ID, '_ap_titulo', true) : $p->post_title;

        $espacios = self::get_espacios();
        $espacio_ids = array_values(array_filter((array) get_post_meta($p->ID, '_ap_espacios', true)));
        $espacio_nombres = array();
        foreach ($espacio_ids as $eid) {
            $espacio_nombres[] = isset($espacios[$eid]) ? $espacios[$eid]['titulo'] : $eid;
        }
        if (empty($espacio_ids) && is_array($resultados)) {
            $espacio_ids = array_keys($resultados);
        }

        return array(
            'id'          => $p->ID,
            'titulo'      => $titulo,
            'titulo_lista'=> $p->post_title,
            'mensaje'     => $p->post_content,
            'estado'      => $estado !== '' ? $estado : 'enviado',
            'fecha'       => $enviado ? wp_date(get_option('date_format') . ' · H:i', $enviado) : date_i18n(get_option('date_format') . ' · H:i', strtotime($p->post_date)),
            'programado'  => $programado,
            'programado_texto' => $programado ? self::format_fecha($programado) : '',
            'retrasado'   => $programado && $estado === 'programado' && $programado < time() - 300,
            'autor'       => $author ? $author->display_name : '',
            'image_id'    => $image_id,
            'image_url'   => $image_id ? (string) wp_get_attachment_url($image_id) : '',
            'thumb_url'   => $image_id ? (string) wp_get_attachment_image_url($image_id, 'thumbnail') : '',
            'espacios'    => $espacio_ids,
            'espacio_nombres' => $espacio_nombres,
            'resultados'  => is_array($resultados) ? $resultados : array(),
        );
    }

    /**
     * Data used by the "Editar" / "Usar como plantilla" buttons.
     */
    public static function get_form_data($c) {
        $data = array(
            'titulo'    => $c['titulo'],
            'mensaje'   => $c['mensaje'],
            'espacios'  => $c['espacios'],
            'image_id'  => $c['image_id'],
            'image_url' => $c['image_url'],
        );
        if ($c['programado']) {
            $data['fecha'] = wp_date('Y-m-d', $c['programado']);
            $data['hora'] = wp_date('H:i', $c['programado']);
        }
        return $data;
    }

    /**
     * Public URL of the image sent to Google (large size to keep it light).
     */
    private static function get_image_url($attachment_id) {
        $src = wp_get_attachment_image_src($attachment_id, 'large');
        $url = $src ? $src[0] : wp_get_attachment_url($attachment_id);
        return $url ? set_url_scheme($url, 'https') : '';
    }

    public static function render_historial_html() {
        $historial = self::get_historial(15);
        ob_start();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/comunicados-historial.php';
        return ob_get_clean();
    }

    public static function render_programados_html() {
        $programados = self::get_programados();
        ob_start();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/comunicados-programados.php';
        return ob_get_clean();
    }

    /* ---------------------------------------------------------------------
     * AJAX: publicar / programar / cancelar
     * ------------------------------------------------------------------- */

    private static function check_ajax_direccion() {
        check_ajax_referer('area_profesores_nonce', 'nonce');
        if (!self::is_user_direccion(get_current_user_id())) {
            wp_send_json_error(array('message' => __('Solo Dirección puede publicar comunicados.', 'area-profesores')), 403);
        }
    }

    private static function get_comunicado_post($id) {
        $post = $id ? get_post($id) : null;
        return ($post && $post->post_type === self::POST_TYPE) ? $post : null;
    }

    private static function respuesta($extra) {
        return array_merge(array(
            'historial_html'  => self::render_historial_html(),
            'programados_html'=> self::render_programados_html(),
        ), $extra);
    }

    public function ajax_publicar() {
        self::check_ajax_direccion();
        $user_id = get_current_user_id();

        $espacio_ids = isset($_POST['espacios']) && is_array($_POST['espacios']) ? array_map('sanitize_key', wp_unslash($_POST['espacios'])) : array();
        $espacios = self::get_espacios(true);
        $espacio_ids = array_values(array_filter(array_unique($espacio_ids), function ($id) use ($espacios) {
            return isset($espacios[$id]);
        }));

        if (empty($espacio_ids)) {
            wp_send_json_error(array('message' => __('Selecciona al menos un espacio.', 'area-profesores')));
        }

        // Retry of an announcement already sent (same text and image).
        $comunicado_id = isset($_POST['comunicado_id']) ? absint($_POST['comunicado_id']) : 0;
        if ($comunicado_id) {
            $post = self::get_comunicado_post($comunicado_id);
            if (!$post || get_post_meta($comunicado_id, '_ap_estado', true) === 'programado') {
                wp_send_json_error(array('message' => __('Comunicado no encontrado.', 'area-profesores')));
            }
            $out = self::enviar_comunicado($comunicado_id, $espacio_ids);
            wp_send_json_success(self::respuesta(self::resumen($comunicado_id, $out)));
        }

        $titulo = isset($_POST['titulo']) ? sanitize_text_field(wp_unslash($_POST['titulo'])) : '';
        $mensaje = isset($_POST['mensaje']) ? sanitize_textarea_field(wp_unslash($_POST['mensaje'])) : '';
        $mensaje = str_replace(array("\r\n", "\r"), "\n", $mensaje);
        $has_file = !empty($_FILES['imagen']) && !empty($_FILES['imagen']['name']);
        $reuse_image = isset($_POST['reuse_image_id']) ? absint($_POST['reuse_image_id']) : 0;
        if ($reuse_image && (get_post_type($reuse_image) !== 'attachment' || !wp_attachment_is_image($reuse_image))) {
            $reuse_image = 0;
        }

        if ($mensaje === '' && !$has_file && !$reuse_image) {
            wp_send_json_error(array('message' => __('Escribe un mensaje o adjunta una imagen.', 'area-profesores')));
        }
        if (mb_strlen($titulo . "\n\n" . $mensaje) > self::MAX_TEXT) {
            wp_send_json_error(array('message' => sprintf(__('El mensaje es demasiado largo (máximo %d caracteres).', 'area-profesores'), self::MAX_TEXT)));
        }

        // Scheduling
        $programar = isset($_POST['modo']) && $_POST['modo'] === 'programar';
        $ts = 0;
        if ($programar) {
            $ts = self::parse_fecha_programada(
                isset($_POST['fecha']) ? sanitize_text_field(wp_unslash($_POST['fecha'])) : '',
                isset($_POST['hora']) ? sanitize_text_field(wp_unslash($_POST['hora'])) : ''
            );
            if (is_wp_error($ts)) {
                wp_send_json_error(array('message' => $ts->get_error_message()));
            }
        }

        // Editing a scheduled announcement: it must still be pending.
        $replace_id = isset($_POST['replace_id']) ? absint($_POST['replace_id']) : 0;
        if ($replace_id) {
            $old = self::get_comunicado_post($replace_id);
            if (!$old || get_post_meta($replace_id, '_ap_estado', true) !== 'programado') {
                wp_send_json_error(array('message' => __('Ese comunicado ya se ha enviado o se ha cancelado, así que no se puede modificar.', 'area-profesores')));
            }
        }

        $image_id = $reuse_image;
        if ($has_file) {
            $image_id = $this->handle_image_upload();
            if (is_wp_error($image_id)) {
                wp_send_json_error(array('message' => $image_id->get_error_message()));
            }
        }

        $comunicado_id = wp_insert_post(array(
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'private',
            'post_title'   => $titulo !== '' ? $titulo : wp_trim_words($mensaje !== '' ? $mensaje : __('Imagen', 'area-profesores'), 8, '…'),
            'post_content' => $mensaje,
            'post_author'  => $user_id,
        ), true);

        if (is_wp_error($comunicado_id)) {
            wp_send_json_error(array('message' => __('No se pudo guardar el comunicado.', 'area-profesores')));
        }

        update_post_meta($comunicado_id, '_ap_titulo', $titulo);
        update_post_meta($comunicado_id, '_ap_espacios', $espacio_ids);
        if ($image_id) {
            update_post_meta($comunicado_id, '_ap_image_id', $image_id);
            if ($has_file) {
                wp_update_post(array('ID' => $image_id, 'post_parent' => $comunicado_id));
            }
        }

        if ($replace_id) {
            wp_clear_scheduled_hook(self::CRON_HOOK, array($replace_id));
            wp_delete_post($replace_id, true);
        }

        if ($programar) {
            update_post_meta($comunicado_id, '_ap_estado', 'programado');
            update_post_meta($comunicado_id, '_ap_programado_gmt', $ts);
            wp_schedule_single_event($ts, self::CRON_HOOK, array((int) $comunicado_id));

            wp_send_json_success(self::respuesta(array(
                'programado'       => true,
                'comunicado_id'    => $comunicado_id,
                'programado_texto' => self::format_fecha($ts),
                'total'            => count($espacio_ids),
                'espacios'         => array_map(function ($id) use ($espacios) {
                    return $espacios[$id]['titulo'];
                }, $espacio_ids),
                'editado'          => (bool) $replace_id,
            )));
        }

        $out = self::enviar_comunicado($comunicado_id, $espacio_ids);
        wp_send_json_success(self::respuesta(self::resumen($comunicado_id, $out)));
    }

    private static function resumen($comunicado_id, $out) {
        $ok = count(array_filter($out, function ($r) {
            return $r['ok'];
        }));
        return array(
            'comunicado_id' => $comunicado_id,
            'resultados'    => $out,
            'ok'            => $ok,
            'total'         => count($out),
        );
    }

    public function ajax_cancelar() {
        self::check_ajax_direccion();
        $id = isset($_POST['comunicado_id']) ? absint($_POST['comunicado_id']) : 0;

        if (!self::get_comunicado_post($id) || get_post_meta($id, '_ap_estado', true) !== 'programado' || !self::lock($id)) {
            wp_send_json_error(array('message' => __('Ese comunicado ya se ha enviado o cancelado.', 'area-profesores')));
        }

        update_post_meta($id, '_ap_estado', 'cancelado');
        update_post_meta($id, '_ap_cancelado_por', get_current_user_id());
        wp_clear_scheduled_hook(self::CRON_HOOK, array($id));
        self::unlock($id);

        wp_send_json_success(self::respuesta(array('message' => __('Comunicado programado cancelado. No se enviará.', 'area-profesores'))));
    }

    public function ajax_enviar_ahora() {
        self::check_ajax_direccion();
        $id = isset($_POST['comunicado_id']) ? absint($_POST['comunicado_id']) : 0;

        $out = self::get_comunicado_post($id) ? self::procesar_programado($id) : false;
        if ($out === false) {
            wp_send_json_error(array('message' => __('Ese comunicado ya se ha enviado o cancelado.', 'area-profesores')));
        }

        wp_send_json_success(self::respuesta(self::resumen($id, $out)));
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

    private static function flash($text, $type = 'success') {
        set_transient('ap_chat_flash_' . get_current_user_id(), array('type' => $type, 'text' => $text), 120);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $espacios = self::get_espacios();
        $grupos_tabla = self::agrupar($espacios);
        $grupos_otros = self::get_grupos_otros();
        $cursos_sin_espacio = self::get_cursos_sin_espacio();

        $editing = null;
        if (!empty($_GET['edit'])) {
            $editing = self::get_espacio(sanitize_key(wp_unslash($_GET['edit'])));
        }
        $nuevo_tipo = isset($_GET['tipo']) && $_GET['tipo'] === 'otro' ? 'otro' : 'promocion';

        $flash = get_transient('ap_chat_flash_' . get_current_user_id());
        if ($flash) {
            delete_transient('ap_chat_flash_' . get_current_user_id());
        }

        $direccion_users = get_users(array(
            'meta_key'   => self::META_DIR,
            'meta_value' => '1',
            'orderby'    => 'display_name',
        ));

        $stats = array('total' => 0, 'conectados' => 0);
        foreach ($espacios as $e) {
            if ($e['tipo'] === 'otro' || $e['curso'] !== '') {
                $stats['total']++;
                if ($e['conectado']) {
                    $stats['conectados']++;
                }
            }
        }

        $programados = self::get_programados();
        $area_page_id = absint(Area_Profesores_Admin::get_setting('area_page_id', 0));

        // Google must download the images over HTTPS from the uploads folder.
        $uploads = wp_upload_dir();
        $is_https = strpos(home_url(), 'https://') === 0 || strpos($uploads['baseurl'], 'https://') === 0;
        $tz = wp_timezone_string();
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;

        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-chat.php';
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_save_espacio');

        $raw = self::get_raw_espacios();
        $id = isset($_POST['espacio_id']) ? sanitize_key(wp_unslash($_POST['espacio_id'])) : '';
        $is_new = ($id === '' || !isset($raw[$id]));
        $old = $is_new ? self::normalize(array()) : self::normalize($raw[$id]);

        $tipo = isset($_POST['tipo']) && $_POST['tipo'] === 'otro' ? 'otro' : 'promocion';
        $webhook = isset($_POST['webhook']) ? trim(wp_unslash($_POST['webhook'])) : '';
        $space_id = isset($_POST['space_id']) ? self::parse_space_id(wp_unslash($_POST['space_id'])) : '';
        $back = array('edit' => $is_new ? '' : $id, 'tipo' => $tipo);

        if ($webhook === '' && !$is_new) {
            $webhook = $old['webhook']; // Keep the stored webhook.
        } elseif (!empty($_POST['quitar_webhook'])) {
            $webhook = '';
        }

        if ($webhook !== '' && !self::is_valid_webhook($webhook)) {
            self::flash(__('La URL no parece un webhook de Google Chat. Debe empezar por https://chat.googleapis.com/v1/spaces/…', 'area-profesores'), 'error');
            wp_safe_redirect($this->admin_url($back));
            exit;
        }

        $webhook_space = self::get_space_id_from_webhook($webhook);
        if ($space_id === '') {
            $space_id = $webhook_space;
        } elseif ($webhook_space !== '' && $webhook_space !== $space_id) {
            self::flash(__('Ese webhook pertenece a otro espacio de Chat distinto al indicado. Revisa que lo hayas copiado del espacio correcto.', 'area-profesores'), 'error');
            wp_safe_redirect($this->admin_url($back));
            exit;
        }

        $data = array(
            'tipo'     => $tipo,
            'space_id' => $space_id,
            'webhook'  => $webhook !== '' ? esc_url_raw($webhook, array('https')) : '',
            'activo'   => !empty($_POST['activo']),
            'publico'  => !empty($_POST['publico']),
            'nombre_chat' => $old['nombre_chat'],
        );

        if ($tipo === 'promocion') {
            $data['promocion'] = isset($_POST['promocion']) ? absint($_POST['promocion']) : 0;
            $data['letra'] = isset($_POST['letra']) ? sanitize_text_field(wp_unslash($_POST['letra'])) : '';
            $data['curso'] = isset($_POST['curso']) ? sanitize_text_field(wp_unslash($_POST['curso'])) : '';
            if ($data['promocion'] < 2000 || $data['promocion'] > 2100) {
                self::flash(__('Indica el año de la promoción (por ejemplo, 2036).', 'area-profesores'), 'error');
                wp_safe_redirect($this->admin_url($back));
                exit;
            }
        } else {
            $data['nombre'] = isset($_POST['nombre']) ? sanitize_text_field(wp_unslash($_POST['nombre'])) : '';
            $data['grupo'] = isset($_POST['grupo']) ? sanitize_text_field(wp_unslash($_POST['grupo'])) : '';
            $data['orden'] = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;
            if ($data['nombre'] === '') {
                self::flash(__('El nombre del espacio es obligatorio.', 'area-profesores'), 'error');
                wp_safe_redirect($this->admin_url($back));
                exit;
            }
        }

        // The same Chat space cannot be registered twice.
        if ($space_id !== '') {
            foreach ($raw as $rid => $r) {
                $r = self::normalize($r);
                if ($rid !== $id && $r['space_id'] === $space_id) {
                    self::flash(__('Ese espacio de Chat ya está dado de alta en otra fila.', 'area-profesores'), 'error');
                    wp_safe_redirect($this->admin_url($back));
                    exit;
                }
            }
        }

        if ($is_new) {
            $id = 'e' . strtolower(wp_generate_password(10, false, false));
        }
        $raw[$id] = $data;
        self::save_espacios($raw);

        self::flash($is_new ? __('Espacio añadido. Usa "Probar" para comprobar que llega el mensaje.', 'area-profesores') : __('Espacio actualizado correctamente.', 'area-profesores'));
        wp_safe_redirect($this->admin_url());
        exit;
    }

    /**
     * Paste several webhooks at once: each one is assigned to its space by the space ID.
     */
    public function handle_bulk_webhooks() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_bulk_webhooks');

        $text = isset($_POST['webhooks']) ? wp_unslash($_POST['webhooks']) : '';
        preg_match_all('#https://chat\.googleapis\.com/v1/spaces/[^\s"\'<>]+#', $text, $m);
        $urls = array_unique($m[0]);

        $raw = self::get_raw_espacios();
        $by_space = array();
        foreach ($raw as $rid => $r) {
            $r = self::normalize($r);
            if ($r['space_id'] !== '') {
                $by_space[$r['space_id']] = $rid;
            }
        }

        $ok = array();
        $sin = array();
        foreach ($urls as $url) {
            $url = html_entity_decode($url);
            $sid = self::get_space_id_from_webhook($url);
            if (!self::is_valid_webhook($url) || $sid === '') {
                $sin[] = $url;
                continue;
            }
            if (!isset($by_space[$sid])) {
                $sin[] = 'spaces/' . $sid;
                continue;
            }
            $rid = $by_space[$sid];
            $raw[$rid]['webhook'] = esc_url_raw($url, array('https'));
            $raw[$rid]['activo'] = true;
            $ok[] = $rid;
        }
        self::save_espacios($raw);

        $espacios = self::get_espacios();
        $nombres = array_map(function ($rid) use ($espacios) {
            return isset($espacios[$rid]) ? $espacios[$rid]['titulo'] : $rid;
        }, $ok);

        if (empty($urls)) {
            self::flash(__('No se ha encontrado ninguna URL de webhook en el texto pegado.', 'area-profesores'), 'error');
        } else {
            $msg = sprintf(_n('%d webhook conectado', '%d webhooks conectados', count($ok), 'area-profesores'), count($ok));
            if ($nombres) {
                $msg .= ': ' . implode(', ', $nombres) . '.';
            }
            if ($sin) {
                $msg .= ' ' . sprintf(__('Sin espacio que coincida (añádelos a mano): %s', 'area-profesores'), implode(', ', $sin));
            }
            self::flash($msg, $sin ? 'warning' : 'success');
        }

        wp_safe_redirect($this->admin_url());
        exit;
    }

    public function handle_delete() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $id = isset($_GET['id']) ? sanitize_key(wp_unslash($_GET['id'])) : '';
        check_admin_referer('ap_delete_espacio_' . $id);

        $raw = self::get_raw_espacios();
        if (isset($raw[$id])) {
            unset($raw[$id]);
            self::save_espacios($raw);
        }

        self::flash(__('Espacio eliminado (en Google Chat no se borra nada).', 'area-profesores'));
        wp_safe_redirect($this->admin_url());
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
        if (!$e || !$e['conectado']) {
            self::flash(__('Ese espacio todavía no tiene webhook.', 'area-profesores'), 'error');
            wp_safe_redirect($this->admin_url());
            exit;
        }

        $logo = Area_Profesores_Admin::get_setting('logo_url', '');
        $payload = self::build_payload(
            __('Mensaje de prueba', 'area-profesores'),
            sprintf(__('Este es un mensaje de prueba enviado desde el Área de Profesores al espacio "%s". Ya se pueden publicar comunicados aquí.', 'area-profesores'), $e['titulo']),
            // The school logo also checks that Google can show images from this site.
            $logo ? set_url_scheme($logo, 'https') : ''
        );

        $r = self::send_to_webhook($e['webhook'], $payload);
        if ($r['ok']) {
            self::flash(sprintf(__('Mensaje de prueba enviado a "%s". Compruébalo en Google Chat.', 'area-profesores'), $e['titulo']));
        } else {
            self::flash(sprintf(__('No se pudo enviar el mensaje de prueba a "%1$s": %2$s', 'area-profesores'), $e['titulo'], $r['error']), 'error');
        }

        wp_safe_redirect($this->admin_url());
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
