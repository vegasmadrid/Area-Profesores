<?php
/**
 * Plugin Name: Área Profesores - Colegio
 * Plugin URI:  https://colegio.local/
 * Description: Gestión integral del Área de Profesores integrada en la web escolar. Control de acceso por profesor, asignación de tutorías, y registro diario de comensales para el comedor escolar mediante el shortcode [area_profesores].
 * Version:     1.20.0
 * Author:      Colegio
 * Text Domain: area-profesores
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('AREA_PROFESORES_VERSION', '1.20.0');
define('AREA_PROFESORES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AREA_PROFESORES_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require core classes
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-cursos.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-comedor.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-solicitudes.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-admin.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-access.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-enlaces.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-chat.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-google.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-aulas.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-api.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-hoja-calculo.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-alumnos.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-importador.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-alumnos-admin.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-alumnos-portal.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-correos.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-reservas.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-cuentas.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-comensales.php';

/**
 * Main Plugin Class
 */
class Area_Profesores_Plugin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize modules
        Area_Profesores_Comedor::get_instance();
        Area_Profesores_Solicitudes::get_instance();
        Area_Profesores_Admin::get_instance();
        Area_Profesores_Shortcode::get_instance();
        Area_Profesores_Access::get_instance();
        Area_Profesores_Enlaces::get_instance();
        Area_Profesores_Chat::get_instance();
        Area_Profesores_Google::get_instance();
        Area_Profesores_Aulas::get_instance();
        Area_Profesores_API::get_instance();
        Area_Profesores_Alumnos_Admin::get_instance();
        Area_Profesores_Alumnos_Portal::get_instance();
        Area_Profesores_Correos::get_instance();
        Area_Profesores_Reservas::get_instance();
        Area_Profesores_Cuentas::get_instance();
        Area_Profesores_Comensales::get_instance();

        // Create/upgrade the database tables after every plugin update (the activation
        // hook does not run when the plugin files are replaced without reactivating it).
        add_action('init', array($this, 'maybe_upgrade_db'), 1);

        // One-time data migrations (old 1A…6B tutorías → new courses).
        add_action('init', array('Area_Profesores_Cursos', 'maybe_migrate'), 5);

        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Activation hook - create database tables
     */
    public function activate() {
        Area_Profesores_Comedor::create_tables();
        Area_Profesores_Solicitudes::create_tables();
        Area_Profesores_Alumnos::create_tables();
        Area_Profesores_Reservas::create_tables();
        Area_Profesores_Comensales::create_tables();
    }

    /**
     * Run the table creation when the plugin version changes.
     */
    public function maybe_upgrade_db() {
        // The version plus the date of the files that define the tables: if a file is
        // uploaded after the main one (updates by FTP), the tables are updated again.
        $firma = AREA_PROFESORES_VERSION;
        foreach (array('class-comedor', 'class-solicitudes', 'class-alumnos', 'class-reservas', 'class-comensales') as $f) {
            $firma .= '|' . (int) @filemtime(AREA_PROFESORES_PLUGIN_DIR . 'includes/' . $f . '.php');
        }
        $firma = AREA_PROFESORES_VERSION . '-' . substr(md5($firma), 0, 8);
        if (get_option('area_profesores_db_version') === $firma) {
            return;
        }
        $this->activate();
        // Extras validated before they were registered as reservations (once per update, no e-mails).
        Area_Profesores_Comensales::registrar_extras_pendientes();

        // "¿Es profesor?" no longer exists (every user is a teacher): remove the stored flag.
        delete_metadata('user', 0, '_ap_is_profesor', '', true);

        // The public families page was removed (the families site has its own page):
        // delete its settings and stop treating that page as public.
        $settings = get_option('ap_settings', array());
        if (is_array($settings)) {
            if (!empty($settings['familias_page_id']) && !empty($settings['public_page_ids'])) {
                $settings['public_page_ids'] = array_values(array_diff(array_map('absint', (array) $settings['public_page_ids']), array((int) $settings['familias_page_id'])));
            }
            foreach (array_keys($settings) as $key) {
                if (strpos($key, 'familias_') === 0 || $key === 'logo_familias_url') {
                    unset($settings[$key]);
                }
            }
            update_option('ap_settings', $settings);
        }

        update_option('area_profesores_db_version', $firma, true);
    }

    /**
     * Deactivation hook
     */
    public function deactivate() {
        // Reserved for cleanup if needed
    }

    /**
     * Frontend assets
     */
    public function enqueue_frontend_assets() {
        // Enqueue styles and scripts only when shortcode is present or on relevant pages
        wp_register_style(
            'area-profesores-frontend',
            AREA_PROFESORES_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            AREA_PROFESORES_VERSION
        );

        wp_register_script(
            'area-profesores-frontend',
            AREA_PROFESORES_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            AREA_PROFESORES_VERSION,
            true
        );

        wp_register_script(
            'area-profesores-reservas-porteria',
            AREA_PROFESORES_PLUGIN_URL . 'assets/js/reservas-porteria.js',
            array(),
            AREA_PROFESORES_VERSION,
            true
        );

        wp_register_script(
            'area-profesores-comedor',
            AREA_PROFESORES_PLUGIN_URL . 'assets/js/comedor.js',
            array(),
            AREA_PROFESORES_VERSION,
            true
        );

        wp_localize_script('area-profesores-frontend', 'AreaProfesoresData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('area_profesores_nonce'),
            'i18n'     => array(
                'submitting'        => __('Enviando registro...', 'area-profesores'),
                'submitting_req'    => __('Enviando solicitud...', 'area-profesores'),
                'success'           => __('¡Registro guardado correctamente!', 'area-profesores'),
                'error'             => __('Ha ocurrido un error al guardar el registro.', 'area-profesores'),
                'fill_required'     => __('Por favor, completa todos los campos requeridos (*).', 'area-profesores'),
                'confirm_delete'    => __('¿Estás seguro de que deseas eliminar este registro?', 'area-profesores'),
                'confirm_approve'   => __('¿Aprobar esta solicitud y dar acceso al profesor?', 'area-profesores'),
                'confirm_reject'    => __('¿Rechazar esta solicitud de acceso?', 'area-profesores'),
                'com_publish'       => __('Publicar en %d espacio(s)', 'area-profesores'),
                'com_schedule_btn'  => __('Programar en %d espacio(s)', 'area-profesores'),
                'com_save_changes'  => __('Guardar cambios (%d espacio(s))', 'area-profesores'),
                'com_at'            => __('a las', 'area-profesores'),
                'com_will_publish'  => __('Se publicará el %s.', 'area-profesores'),
                'com_past'          => __('Elige un día y una hora futuros para programar el comunicado.', 'area-profesores'),
                'com_confirm_sched' => __('Se va a programar el comunicado en %1$d espacio(s). %2$s ¿Continuar?', 'area-profesores'),
                'com_sched_ok'      => __('Programado para el %s', 'area-profesores'),
                'com_sched_updated' => __('Cambios guardados. Se publicará el %s', 'area-profesores'),
                'com_sched_spaces'  => __('Se publicará en %d espacio(s):', 'area-profesores'),
                'com_editing'       => __('Estás editando un comunicado programado. Al guardar se sustituye el anterior.', 'area-profesores'),
                'com_confirm_cancel'=> __('¿Cancelar este comunicado programado? No se enviará.', 'area-profesores'),
                'com_none'          => __('Ningún espacio seleccionado', 'area-profesores'),
                'com_selected'      => __('%d espacio(s) seleccionado(s)', 'area-profesores'),
                'com_confirm'       => __('Se va a publicar el comunicado en %d espacio(s) de Google Chat. Las familias lo recibirán al momento y no se puede deshacer desde aquí. ¿Continuar?', 'area-profesores'),
                'com_sending'       => __('Publicando…', 'area-profesores'),
                'com_empty'         => __('Escribe un mensaje o adjunta una imagen.', 'area-profesores'),
                'com_bad_image'     => __('Formato no admitido. Usa una imagen JPG, PNG o GIF de menos de 8 MB.', 'area-profesores'),
                'com_all_ok'        => __('Publicado en los %d espacios', 'area-profesores'),
                'com_some_fail'     => __('Publicado en %1$d de %2$d espacios', 'area-profesores'),
                'com_ok'            => __('Publicado', 'area-profesores'),
                'com_placeholder'   => __('El texto aparecerá aquí…', 'area-profesores'),
                'com_net_error'     => __('Error de conexión. Revisa el historial antes de volver a publicar para no duplicar el comunicado.', 'area-profesores'),
            )
        ));
    }

    /**
     * Admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'area-profesores') !== false || strpos($hook, 'area-familias') !== false || 'user-edit.php' === $hook || 'profile.php' === $hook) {
            wp_enqueue_style(
                'area-profesores-admin',
                AREA_PROFESORES_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                AREA_PROFESORES_VERSION
            );
            wp_enqueue_script(
                'area-profesores-admin',
                AREA_PROFESORES_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                AREA_PROFESORES_VERSION,
                true
            );
            wp_localize_script('area-profesores-admin', 'AreaProfesoresAdminData', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('area_profesores_admin_nonce'),
            ));
        }
    }
}

// Bootstrap plugin
Area_Profesores_Plugin::get_instance();
