<?php
/**
 * API para el Área Familias (otra instalación de WordPress).
 *
 * GET {web}/?rest_route=/area-profesores/v1/espacios-aula
 * Header "X-Area-Key: <clave>" (or &key=<clave>).
 *
 * Returns the family Chat spaces (by etapa and course, previous promotions and
 * other spaces) with the tutor of each course. It never returns webhooks or any
 * private data. The key is generated in "Área Profesores > Espacios de Chat".
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_API {

    const NS          = 'area-profesores/v1';
    const KEY_OPTION  = 'ap_api_key';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        // The site blocks the REST API for visitors: let our route through (the key is checked below).
        add_filter('area_profesores_is_public_rest_request', array($this, 'allow_route'));
        add_action('admin_post_ap_api_key', array($this, 'handle_key'));
    }

    public static function get_key() {
        return (string) get_option(self::KEY_OPTION, '');
    }

    public static function get_endpoint() {
        return add_query_arg('rest_route', '/' . self::NS . '/espacios-aula', home_url('/'));
    }

    public function allow_route($allow) {
        $route = isset($_GET['rest_route']) ? (string) wp_unslash($_GET['rest_route']) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if (strpos($route, '/' . self::NS . '/') === 0 || strpos($uri, '/wp-json/' . self::NS . '/') !== false) {
            return true;
        }
        return $allow;
    }

    public function register_routes() {
        register_rest_route(self::NS, '/espacios-aula', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_espacios'),
            'permission_callback' => array($this, 'check_key'),
        ));
    }

    public function check_key(WP_REST_Request $request) {
        $key = self::get_key();
        $sent = (string) $request->get_header('X-Area-Key');
        if ($sent === '') {
            $sent = (string) $request->get_param('key');
        }
        if ($key === '' || $sent === '' || !hash_equals($key, $sent)) {
            return new WP_Error('area_key', __('Clave de conexión no válida.', 'area-profesores'), array('status' => 403));
        }
        return true;
    }

    public function get_espacios() {
        $tutores = Area_Profesores_Aulas::get_tutores_por_curso();

        $visibles = array_filter(Area_Profesores_Chat::get_espacios(), function ($e) {
            return $e['activo'] && $e['publico'] && $e['chat_url'] !== '';
        });

        $actuales = array();
        $anteriores = array();
        foreach ($visibles as $e) {
            $item = array(
                'titulo'    => $e['titulo'],
                'subtitulo' => $e['subtitulo'],
                'chat_url'  => $e['chat_url'],
                'curso'     => $e['curso'],
                'etapa'     => $e['etapa'],
                'tipo'      => $e['archivada'] ? 'anterior' : $e['tipo'],
                'tutor'     => ($e['curso'] !== '' && !empty($tutores[$e['curso']])) ? Area_Profesores_Aulas::get_tutor_card($tutores[$e['curso']]) : null,
            );
            if ($e['archivada']) {
                $anteriores[] = $item;
            } elseif ($e['tipo'] === 'otro' || $e['curso'] !== '') {
                $actuales[$e['grupo_key']]['key'] = $e['grupo_key'];
                $actuales[$e['grupo_key']]['label'] = $e['grupo_label'];
                $actuales[$e['grupo_key']]['es_etapa'] = $e['tipo'] === 'promocion';
                $actuales[$e['grupo_key']]['espacios'][] = $item;
            }
        }

        $response = rest_ensure_response(array(
            'version'       => 1,
            'curso_escolar' => Area_Profesores_Cursos::get_curso_escolar_label(),
            'actualizado'   => gmdate('c'),
            'grupos'        => array_values($actuales),
            'anteriores'    => $anteriores,
        ));
        $response->header('Cache-Control', 'no-store');
        return $response;
    }

    /**
     * Generate / regenerate the key (Espacios de Chat › Conexión con el Área Familias).
     */
    public function handle_key() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_api_key');

        if (!empty($_POST['borrar'])) {
            delete_option(self::KEY_OPTION);
            $msg = __('Conexión con el Área Familias desactivada.', 'area-profesores');
        } else {
            update_option(self::KEY_OPTION, wp_generate_password(40, false, false), false);
            $msg = __('Nueva clave generada. Cópiala en el Área Familias (la anterior deja de funcionar).', 'area-profesores');
        }
        set_transient('ap_chat_flash_' . get_current_user_id(), array('type' => 'success', 'text' => $msg), 120);
        wp_safe_redirect(admin_url('admin.php?page=area-profesores-chat#ap-api'));
        exit;
    }
}
