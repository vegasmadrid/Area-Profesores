<?php
/**
 * Pestañas del Área de Profesores: name, order and which groups see each tab.
 *
 * Área Profesores › Pestañas. The defaults reproduce the previous behaviour.
 * WordPress administrators always see every active tab. What each tab shows
 * inside still depends on the person (e.g. "Mis alumnos": their own pupils).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Pestanas {

    const OPTION = 'ap_pestanas';
    const SLUG   = 'area-profesores-pestanas';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 30);
        add_action('admin_post_ap_pestanas_guardar', array($this, 'handle_save'));
    }

    /* ---------------------------------------------------------------------
     * Definiciones
     * ------------------------------------------------------------------- */

    /**
     * Tabs: key => { nombre, grupos (default), descripcion, icono (SVG path) }. Array order = default order.
     */
    public static function definiciones() {
        return array(
            'comedor' => array(
                'nombre'      => __('Comedor', 'area-profesores'),
                'grupos'      => array('todos'),
                'descripcion' => __('Los profesores de Primer Ciclo, Infantil y Primaria (y Dirección y Secretaría) ponen el comedor de su clase. El resto solo ve "Mi comida en el colegio".', 'area-profesores'),
                'icono'       => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            ),
            'administracion' => array(
                'nombre'      => __('Administración', 'area-profesores'),
                'grupos'      => array('direccion', 'secretaria', 'administracion'),
                'descripcion' => __('Comidas para cotejar con cocina y cobros a las familias. Contiene datos de todas las familias.', 'area-profesores'),
                'icono'       => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
            ),
            'enlaces' => array(
                'nombre'      => __('Enlaces Útiles', 'area-profesores'),
                'grupos'      => array('todos'),
                'descripcion' => __('Accesos directos (cada enlace puede limitarse a unas etapas en "Enlaces Útiles").', 'area-profesores'),
                'icono'       => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
            ),
            'alumnos' => array(
                'nombre'      => __('Mis alumnos', 'area-profesores'),
                'grupos'      => array('con_alumnos', 'direccion', 'secretaria'),
                'descripcion' => __('Cada profesor ve solo los alumnos de su tutoría y clases; Dirección y Secretaría, todos. Si alguien no tiene alumnos, no le aparece.', 'area-profesores'),
                'icono'       => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
            ),
            'reservas' => array(
                'nombre'      => __('Reservas', 'area-profesores'),
                'grupos'      => array('con_alumnos', 'direccion', 'secretaria', 'porteria'),
                'descripcion' => __('Servicios reservados. Los profesores ven los de sus alumnos; quien no tiene alumnos (Portería…), los de todos los cursos.', 'area-profesores'),
                'icono'       => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            ),
            'espacios' => array(
                'nombre'      => __('Espacios de aula', 'area-profesores'),
                'grupos'      => array('todos'),
                'descripcion' => __('Enlaces a los espacios de Google Chat de las familias de cada curso.', 'area-profesores'),
                'icono'       => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
            ),
            'comunicados' => array(
                'nombre'      => __('Comunicados', 'area-profesores'),
                'grupos'      => array('direccion', 'comunicacion'),
                'descripcion' => __('Publicar comunicados en los espacios de Google Chat de las familias.', 'area-profesores'),
                'icono'       => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
            ),
            'ayuda' => array(
                'nombre'      => __('Ayuda', 'area-profesores'),
                'grupos'      => array('todos'),
                'descripcion' => __('Manual del Área (cada uno ve las instrucciones de las pestañas que tiene).', 'area-profesores'),
                'icono'       => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            ),
        );
    }

    /**
     * Groups of people: key => label (grouped for the form).
     *
     * @return array section => [ key => label ]
     */
    public static function grupos() {
        $etapas = array();
        foreach (Area_Profesores_Cursos::get_etapas() as $k => $label) {
            $etapas['etapa:' . $k] = sprintf(__('Profesores de %s', 'area-profesores'), $label);
        }
        $cuentas = array();
        foreach (Area_Profesores_Cuentas::roles() as $k => $r) {
            $cuentas[$k] = $r['nombre'];
        }
        return array(
            __('Profesorado', 'area-profesores') => array_merge(array(
                'todos'        => __('Todo el profesorado', 'area-profesores'),
                'tutores'      => __('Tutores', 'area-profesores'),
                'especialistas'=> __('Especialistas', 'area-profesores'),
                'con_alumnos'  => __('Profesores con alumnos (tutoría o clases asignadas)', 'area-profesores'),
            ), $etapas),
            __('Cuentas especiales', 'area-profesores') => $cuentas,
        );
    }

    private static function grupos_planos() {
        $out = array();
        foreach (self::grupos() as $l) {
            $out += $l;
        }
        return $out;
    }

    /**
     * Saved settings merged with the defaults, in display order.
     *
     * @return array key => { nombre, nombre_defecto, grupos, activa, orden, descripcion, icono }
     */
    public static function config() {
        $o = get_option(self::OPTION, array());
        $o = is_array($o) ? $o : array();
        $out = array();
        $i = 0;
        foreach (self::definiciones() as $k => $d) {
            $s = isset($o[$k]) && is_array($o[$k]) ? $o[$k] : array();
            $out[$k] = array(
                'nombre'         => isset($s['nombre']) && trim($s['nombre']) !== '' ? $s['nombre'] : $d['nombre'],
                'nombre_defecto' => $d['nombre'],
                'grupos'         => isset($s['grupos']) && is_array($s['grupos']) ? $s['grupos'] : $d['grupos'],
                'activa'         => !isset($s['activa']) || !empty($s['activa']),
                'orden'          => isset($s['orden']) ? (int) $s['orden'] : ($i + 1) * 10,
                'descripcion'    => $d['descripcion'],
                'icono'          => $d['icono'],
            );
            $i++;
        }
        uasort($out, function ($a, $b) {
            return $a['orden'] <=> $b['orden'];
        });
        return $out;
    }

    public static function nombre($tab) {
        $c = self::config();
        return isset($c[$tab]) ? $c[$tab]['nombre'] : $tab;
    }

    /* ---------------------------------------------------------------------
     * Quién la ve
     * ------------------------------------------------------------------- */

    /** Does the user belong to a group? */
    public static function pertenece($user_id, $grupo) {
        if (!$user_id) {
            return false;
        }
        if ($grupo === 'todos') {
            return true;
        }
        if ($grupo === 'tutores') {
            return Area_Profesores_Admin::get_user_tutoria($user_id) !== '';
        }
        if ($grupo === 'especialistas') {
            return Area_Profesores_Cursos::get_codigo_asignacion($user_id) === 'esp';
        }
        if ($grupo === 'con_alumnos') {
            $v = Area_Profesores_Alumnos::cursos_visibles($user_id);
            return $v === true || !empty($v);
        }
        if (strpos($grupo, 'etapa:') === 0) {
            return in_array(substr($grupo, 6), (array) Area_Profesores_Cursos::get_etapas_usuario($user_id), true);
        }
        return Area_Profesores_Cuentas::tiene($user_id, $grupo);
    }

    /**
     * Can the user see the tab? (active, and the user is in one of its groups;
     * WordPress administrators see every active tab).
     */
    public static function ve($user_id, $tab) {
        static $cache = array();
        $k = $user_id . '|' . $tab;
        if (isset($cache[$k])) {
            return $cache[$k];
        }
        $c = self::config();
        $si = false;
        if ($user_id && isset($c[$tab]) && $c[$tab]['activa']) {
            if (user_can($user_id, 'manage_options')) {
                $si = true;
            } else {
                foreach ($c[$tab]['grupos'] as $g) {
                    if (self::pertenece($user_id, $g)) {
                        $si = true;
                        break;
                    }
                }
            }
        }
        return $cache[$k] = $si;
    }

    /* ---------------------------------------------------------------------
     * Backend
     * ------------------------------------------------------------------- */

    public function register_menu() {
        add_submenu_page('area-profesores', __('Pestañas', 'area-profesores'), __('Pestañas', 'area-profesores'), 'manage_options', self::SLUG, array($this, 'render'));
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $config = self::config();
        $grupos = self::grupos();
        $flash = get_transient('ap_pestanas_flash_' . get_current_user_id());
        if ($flash) {
            delete_transient('ap_pestanas_flash_' . get_current_user_id());
        }
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-pestanas.php';
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_pestanas_guardar');
        if (!empty($_POST['restablecer'])) {
            delete_option(self::OPTION);
            set_transient('ap_pestanas_flash_' . get_current_user_id(), __('Pestañas restablecidas: nombres, orden y grupos de siempre.', 'area-profesores'), 120);
            wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
            exit;
        }
        $validos = array_keys(self::grupos_planos());
        $p = isset($_POST['pestanas']) && is_array($_POST['pestanas']) ? wp_unslash($_POST['pestanas']) : array();
        $out = array();
        foreach (self::definiciones() as $k => $d) {
            $x = isset($p[$k]) && is_array($p[$k]) ? $p[$k] : array();
            $nombre = isset($x['nombre']) ? mb_substr(sanitize_text_field($x['nombre']), 0, 40) : '';
            $out[$k] = array(
                'nombre' => $nombre !== $d['nombre'] ? $nombre : '',
                'grupos' => array_values(array_intersect(isset($x['grupos']) ? array_map('sanitize_text_field', (array) $x['grupos']) : array(), $validos)),
                'activa' => !empty($x['activa']),
                'orden'  => isset($x['orden']) ? (int) $x['orden'] : 0,
            );
        }
        update_option(self::OPTION, $out, false);
        set_transient('ap_pestanas_flash_' . get_current_user_id(), __('Pestañas guardadas.', 'area-profesores'), 120);
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
        exit;
    }
}
