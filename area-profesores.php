<?php
/**
 * Plugin Name: Área Profesores - Colegio
 * Plugin URI:  https://colegio.local/
 * Description: Gestión integral del Área de Profesores integrada en la web escolar. Control de acceso por profesor, asignación de tutorías, y registro diario de comensales para el comedor escolar mediante el shortcode [area_profesores].
 * Version:     1.0.0
 * Author:      Colegio
 * Text Domain: area-profesores
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('AREA_PROFESORES_VERSION', '1.0.0');
define('AREA_PROFESORES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AREA_PROFESORES_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require core classes
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-comedor.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-admin.php';
require_once AREA_PROFESORES_PLUGIN_DIR . 'includes/class-shortcode.php';

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
        Area_Profesores_Admin::get_instance();
        Area_Profesores_Shortcode::get_instance();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Activation hook - create database tables
     */
    public function activate() {
        Area_Profesores_Comedor::create_tables();
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

        wp_localize_script('area-profesores-frontend', 'AreaProfesoresData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('area_profesores_nonce'),
            'i18n'     => array(
                'submitting'     => __('Enviando registro...', 'area-profesores'),
                'success'        => __('¡Registro guardado correctamente!', 'area-profesores'),
                'error'          => __('Ha ocurrido un error al guardar el registro.', 'area-profesores'),
                'fill_required'  => __('Por favor, completa todos los campos requeridos (*).', 'area-profesores'),
                'confirm_delete' => __('¿Estás seguro de que deseas eliminar este registro?', 'area-profesores'),
            )
        ));
    }

    /**
     * Admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'area-familias') !== false || 'user-edit.php' === $hook || 'profile.php' === $hook) {
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
