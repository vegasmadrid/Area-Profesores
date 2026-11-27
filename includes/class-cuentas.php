<?php
/**
 * Cuentas especiales: Dirección, Secretaría, Cocina, Portería, Administración y Comunicación.
 * Which e-mails each one receives is chosen in "Área Profesores › Avisos".
 *
 * They are few accounts, so instead of a column in the Profesores list they are
 * chosen in "Área Profesores › Cuentas especiales":
 * - Dirección: publishes announcements to the families (Google Chat) and sees
 *   every pupil.
 * - Secretaría: receives the families' notices, sees every pupil and the
 *   "Administración" tab (everything to bill a month).
 * - Cocina: receives the daily summary of the dining room.
 * - Portería: sees the families' reservations of every course (early
 *   departures, late arrivals…). Ready for future notices.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Cuentas {

    const SLUG = 'area-profesores-cuentas';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 12);
        add_action('admin_post_ap_cuentas', array($this, 'handle'));
    }

    /**
     * Roles: key => meta, name and description.
     */
    public static function roles() {
        return array(
            'direccion' => array(
                'meta'   => Area_Profesores_Chat::META_DIR,
                'nombre' => __('Dirección', 'area-profesores'),
                'icono'  => 'dashicons-megaphone',
                'desc'   => __('Publican comunicados a las familias en los espacios de Google Chat, ven a todos los alumnos y las reservas de todos los cursos.', 'area-profesores'),
            ),
            'secretaria' => array(
                'meta'   => Area_Profesores_Alumnos::META_SECRETARIA,
                'nombre' => __('Secretaría', 'area-profesores'),
                'icono'  => 'dashicons-id-alt',
                'desc'   => __('Ven a todos los alumnos y la pestaña "Administración" con todo lo del mes para facturar. Reciben los avisos que elijas en "Avisos" (de serie, los cambios de datos y las reservas de las familias).', 'area-profesores'),
            ),
            'cocina' => array(
                'meta'   => '_ap_is_cocina',
                'nombre' => __('Cocina', 'area-profesores'),
                'icono'  => 'dashicons-carrot',
                'desc'   => __('Reciben los avisos que elijas en "Avisos" (de serie, el resumen diario del comedor, los cambios que se hagan después y los cambios de alergias alimentarias que hagan los profesores).', 'area-profesores'),
            ),
            'porteria' => array(
                'meta'   => '_ap_is_porteria',
                'nombre' => __('Portería', 'area-profesores'),
                'icono'  => 'dashicons-admin-network',
                'desc'   => __('Ven la pestaña "Reservas" de todos los cursos (salidas anticipadas, retrasos justificados…), pueden reservar cualquier servicio para cualquier familia (cuando lo piden en persona o por teléfono) y anular reservas. Reciben los avisos que elijas en "Avisos".', 'area-profesores'),
            ),
            'administracion' => array(
                'meta'   => '_ap_is_administracion',
                'nombre' => __('Administración', 'area-profesores'),
                'icono'  => 'dashicons-calculator',
                'desc'   => __('Ven la pestaña "Administración": por meses, todos los servicios y reservas de cada alumno para facturar, las comidas extra y los días que los profesores no comen en el colegio. Reciben los avisos que elijas en "Avisos" (de serie, los comedores extra que apuntan los profesores).', 'area-profesores'),
            ),
            'comunicacion' => array(
                'meta'   => '_ap_is_comunicacion',
                'nombre' => __('Comunicación', 'area-profesores'),
                'icono'  => 'dashicons-share',
                'desc'   => __('Publican comunicados a las familias en los espacios de Google Chat (pestaña "Comunicados", igual que Dirección) y reciben los avisos que elijas en "Avisos".', 'area-profesores'),
            ),
        );
    }

    public static function tiene($user_id, $rol) {
        $r = self::roles();
        return isset($r[$rol]) && get_user_meta($user_id, $r[$rol]['meta'], true) === '1';
    }

    /**
     * Users with a role.
     *
     * @return WP_User[]
     */
    public static function usuarios($rol) {
        $r = self::roles();
        if (!isset($r[$rol])) {
            return array();
        }
        return get_users(array('meta_key' => $r[$rol]['meta'], 'meta_value' => '1', 'orderby' => 'display_name'));
    }

    public static function emails($rol) {
        return array_values(array_filter(wp_list_pluck(self::usuarios($rol), 'user_email')));
    }

    /* ---------------------------------------------------------------------
     * Backend
     * ------------------------------------------------------------------- */

    public function register_menu() {
        add_submenu_page('area-profesores', __('Cuentas especiales', 'area-profesores'), __('Cuentas especiales', 'area-profesores'), 'manage_options', self::SLUG, array($this, 'render'));
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $roles = self::roles();
        $todos = get_users(array('orderby' => 'display_name', 'fields' => array('ID', 'display_name', 'user_email')));
        $flash = get_transient('ap_cuentas_flash_' . get_current_user_id());
        if ($flash) {
            delete_transient('ap_cuentas_flash_' . get_current_user_id());
        }
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-cuentas.php';
    }

    public function handle() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_cuentas');
        $roles = self::roles();
        $rol = isset($_POST['rol']) ? sanitize_key($_POST['rol']) : '';
        $op = isset($_POST['op']) ? sanitize_key($_POST['op']) : '';
        $uid = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $user = $uid ? get_userdata($uid) : null;
        if (!isset($roles[$rol]) || !$user) {
            $msg = array('type' => 'error', 'text' => __('Elige una cuenta.', 'area-profesores'));
        } elseif ($op === 'quitar') {
            update_user_meta($uid, $roles[$rol]['meta'], '0');
            $msg = array('type' => 'success', 'text' => sprintf(__('%1$s ya no es de %2$s.', 'area-profesores'), $user->display_name, $roles[$rol]['nombre']));
        } else {
            update_user_meta($uid, $roles[$rol]['meta'], '1');
            $msg = array('type' => 'success', 'text' => sprintf(__('%1$s añadido a %2$s.', 'area-profesores'), $user->display_name, $roles[$rol]['nombre']));
        }
        set_transient('ap_cuentas_flash_' . get_current_user_id(), $msg, 120);
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '#rol-' . $rol));
        exit;
    }
}
