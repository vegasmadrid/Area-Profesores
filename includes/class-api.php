<?php
/**
 * API para el Área Familias (otra instalación de WordPress).
 *
 * All routes need the header "X-Area-Key: <clave>" (generated in
 * "Área Profesores > Espacios de Chat › Conexión con el Área Familias").
 *
 * GET  /area-profesores/v1/espacios-aula
 *      Family Chat spaces (by etapa and course, previous promotions, other
 *      spaces) with the tutor of each course. Never the webhooks.
 * GET  /area-profesores/v1/familia?email=<correo>
 *      Only for the family that is logged in on the Área Familias: its
 *      children (course, tutor, class space) and its own details (parents,
 *      address, emergency phone).
 * POST /area-profesores/v1/familia/datos
 *      The family updates its emergency phone and addresses; the tutors of its
 *      children and Secretaría are notified by e-mail.
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
        register_rest_route(self::NS, '/familia', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_familia'),
            'permission_callback' => array($this, 'check_key'),
        ));
        register_rest_route(self::NS, '/familia/datos', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'post_familia_datos'),
            'permission_callback' => array($this, 'check_key'),
        ));
    }

    /* ---------------------------------------------------------------------
     * Familias
     * ------------------------------------------------------------------- */

    /**
     * Data of the families of an e-mail: children and own details.
     */
    public static function datos_familia($email) {
        $A = 'Area_Profesores_Alumnos';
        $familias = $A::familias_por_email($email);
        if (!$familias) {
            return array('encontrada' => false, 'familias' => array(), 'hijos' => array());
        }

        $ids = wp_list_pluck($familias, 'id');
        $hijos_por_familia = $A::alumnos_de_familias($ids, true);
        $tutores = Area_Profesores_Aulas::get_tutores_por_curso();

        // Class space of each course.
        $espacios = array();
        foreach (Area_Profesores_Chat::get_espacios() as $e) {
            if ($e['curso'] !== '' && $e['activo'] && $e['publico'] && $e['chat_url'] !== '' && !isset($espacios[$e['curso']])) {
                $espacios[$e['curso']] = array('titulo' => $e['titulo'], 'chat_url' => $e['chat_url']);
            }
        }

        $hijos = array();
        foreach ($hijos_por_familia as $lista) {
            foreach ($lista as $a) {
                if (isset($hijos[$a['id']])) {
                    continue;
                }
                $c = Area_Profesores_Cursos::get_curso($a['curso']);
                $hijos[$a['id']] = array(
                    'id'          => (int) $a['id'],
                    'nombre'      => $a['nombre'],
                    'curso'       => $a['curso'],
                    'curso_label' => $c ? $c['label'] : '',
                    'etapa'       => $c ? Area_Profesores_Cursos::get_etapa_label($c['etapa']) : '',
                    'tutor'       => ($c && !empty($tutores[$a['curso']])) ? Area_Profesores_Aulas::get_tutor_card($tutores[$a['curso']]) : null,
                    'espacio'     => isset($espacios[$a['curso']]) ? $espacios[$a['curso']] : null,
                );
            }
        }

        $out = array();
        foreach ($familias as $f) {
            $out[] = array(
                'id'        => (int) $f['id'],
                'nombre'    => $f['nombre'],
                'telefono'  => $f['telefono'],
                'contactos' => array_map(function ($c) {
                    return array('id' => (int) $c['id'], 'nombre' => $c['nombre'], 'direccion' => $c['direccion']);
                }, $A::get_contactos($f['id'])),
                'hijos'     => array_map('intval', wp_list_pluck(isset($hijos_por_familia[$f['id']]) ? $hijos_por_familia[$f['id']] : array(), 'id')),
            );
        }

        return array('encontrada' => true, 'familias' => $out, 'hijos' => array_values($hijos));
    }

    public function get_familia(WP_REST_Request $request) {
        $email = Area_Profesores_Alumnos::norm_email($request->get_param('email'));
        if ($email === '') {
            return new WP_Error('area_email', __('Falta el correo de la familia.', 'area-profesores'), array('status' => 400));
        }
        $r = rest_ensure_response(self::datos_familia($email));
        $r->header('Cache-Control', 'no-store');
        return $r;
    }

    /**
     * The family updates its emergency phone and/or addresses.
     * Body (JSON): email, familia_id, telefono, direcciones { contacto_id: dirección }.
     */
    public function post_familia_datos(WP_REST_Request $request) {
        $A = 'Area_Profesores_Alumnos';
        $email = $A::norm_email($request->get_param('email'));
        $fid = absint($request->get_param('familia_id'));

        // The e-mail must belong to that family.
        $propias = wp_list_pluck($A::familias_por_email($email), 'id');
        if (!$email || !$fid || !in_array((string) $fid, array_map('strval', $propias), true)) {
            return new WP_Error('area_familia', __('Esa familia no corresponde a esta cuenta.', 'area-profesores'), array('status' => 403));
        }

        // Max. 10 updates per hour and family.
        $limite = 'ap_familia_upd_' . $fid;
        $n = (int) get_transient($limite);
        if ($n >= 10) {
            return new WP_Error('area_limite', __('Demasiados cambios seguidos. Inténtalo más tarde.', 'area-profesores'), array('status' => 429));
        }
        set_transient($limite, $n + 1, HOUR_IN_SECONDS);

        $f = $A::get_familia($fid);
        $telefono = sanitize_text_field((string) $request->get_param('telefono'));
        $dirs = (array) $request->get_param('direcciones');

        $cambios = array();
        if (mb_strlen($telefono) > 100 || !preg_match('/\d{6,}/', str_replace(array(' ', '-', '.'), '', $telefono))) {
            return new WP_Error('area_telefono', __('Escribe un teléfono de emergencia válido.', 'area-profesores'), array('status' => 400));
        }
        if ($telefono !== $f['telefono']) {
            $cambios[] = array(__('Teléfono de emergencia', 'area-profesores'), $f['telefono'], $telefono);
        }

        $contactos = array();
        foreach ($f['contactos'] as $c) {
            $nueva = array_key_exists((string) $c['id'], $dirs) ? mb_substr(sanitize_text_field((string) $dirs[(string) $c['id']]), 0, 250) : $c['direccion'];
            if ($nueva !== $c['direccion']) {
                $cambios[] = array(sprintf(__('Dirección de %s', 'area-profesores'), $c['nombre']), $c['direccion'], $nueva);
            }
            $contactos[] = array('nombre' => $c['nombre'], 'direccion' => $nueva);
        }

        if ($cambios) {
            $A::guardar_familia(array('nombre' => $f['nombre'], 'emails' => $f['emails'], 'telefono' => $telefono, 'notas' => $f['notas'], 'contactos' => $contactos), $fid, 'familia');
            global $wpdb;
            $wpdb->update($A::t('familias'), array('editado_por_familia' => $A::now()), array('id' => $fid));
            self::avisar_cambios($fid, $email, $cambios);
        }

        return rest_ensure_response(array('ok' => true, 'cambios' => count($cambios), 'datos' => self::datos_familia($email)));
    }

    /**
     * E-mail to the tutors of the family's children and to Secretaría.
     */
    private static function avisar_cambios($fid, $email, $cambios) {
        $A = 'Area_Profesores_Alumnos';
        $f = $A::get_familia($fid);
        $hijos = $A::alumnos_de_familias(array($fid), true);
        $hijos = isset($hijos[$fid]) ? $hijos[$fid] : array();

        $para = $A::get_secretaria_emails();
        $lineas_hijos = array();
        foreach ($hijos as $h) {
            $c = Area_Profesores_Cursos::get_curso($h['curso']);
            $lineas_hijos[] = '- ' . $h['nombre'] . ($c ? ' (' . $c['label'] . ')' : '');
            $para = array_merge($para, $A::emails_tutores($h['curso']));
        }
        $para = array_values(array_unique(array_filter($para)));
        if (!$para) {
            $para = array(get_option('admin_email'));
        }

        $lineas = array();
        foreach ($cambios as $c) {
            $lineas[] = '- ' . $c[0] . ': ' . ($c[1] !== '' ? $c[1] : '—') . '  →  ' . ($c[2] !== '' ? $c[2] : '—');
        }

        $body = sprintf(__('La familia %1$s (%2$s) ha actualizado sus datos desde el Área Familias:', 'area-profesores'), $f['nombre'], $email) . "\n\n"
            . implode("\n", $lineas) . "\n\n"
            . __('Alumnos:', 'area-profesores') . "\n" . implode("\n", $lineas_hijos) . "\n\n"
            . __('Secretaría: recuerda actualizarlo también en Alexia.', 'area-profesores') . "\n";

        wp_mail($para, sprintf(__('[Área Profesores] Cambio de datos de la familia %s', 'area-profesores'), $f['nombre']), $body);
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
