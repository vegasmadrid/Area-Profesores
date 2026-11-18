<?php
/**
 * Página de espacios para las familias.
 *
 * The page is chosen in "Área Profesores > Espacios de Chat" and its content is
 * injected automatically (like the login and teacher pages). It lists every
 * family Chat space by etapa and course, with a search box, and the previous
 * promotions at the bottom. It is public only if it is marked in
 * "Ajustes > Páginas públicas".
 *
 * The shortcode [espacios_familias] still works (same options as attributes).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Directorio {

    const SHORTCODE = 'espacios_familias';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_action('admin_post_ap_save_familias', array($this, 'handle_save'));
    }

    /* ---------------------------------------------------------------------
     * Opciones
     * ------------------------------------------------------------------- */

    public static function get_defaults() {
        return array(
            'familias_page_id'    => 0,
            'familias_titulo'     => __('Espacios de familias', 'area-profesores'),
            'familias_intro'      => __('Busca el curso de tu hijo o hija y pulsa **Abrir en Google Chat**. Entra con la cuenta de Google con la que el colegio te ha añadido al espacio.', 'area-profesores'),
            'familias_tribanda'   => 'yes',
            'familias_tutor'      => 'no',
            'familias_foto'       => 'no',
            'familias_anteriores' => 'yes',
            'familias_otros'      => 'yes',
            'familias_ayuda'      => 'yes',
        );
    }

    public static function get_opciones() {
        $out = array();
        foreach (self::get_defaults() as $key => $default) {
            $out[$key] = Area_Profesores_Admin::get_setting($key, $default);
        }
        return $out;
    }

    public static function get_page_id() {
        $id = absint(Area_Profesores_Admin::get_setting('familias_page_id', 0));
        return ($id && get_post_status($id)) ? $id : 0;
    }

    public static function is_page_public() {
        $id = self::get_page_id();
        return $id && in_array($id, Area_Profesores_Access::get_public_page_ids(), true);
    }

    /**
     * Save the options from "Espacios de Chat" (and the public flag, which is the
     * same list as "Ajustes > Páginas públicas").
     */
    public function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_save_familias');

        $page_id = isset($_POST['familias_page_id']) ? absint($_POST['familias_page_id']) : 0;
        $yes = function ($key) {
            return !empty($_POST[$key]) ? 'yes' : 'no';
        };

        Area_Profesores_Admin::update_setting('familias_page_id', $page_id);
        Area_Profesores_Admin::update_setting('familias_titulo', isset($_POST['familias_titulo']) ? sanitize_text_field(wp_unslash($_POST['familias_titulo'])) : '');
        Area_Profesores_Admin::update_setting('familias_intro', isset($_POST['familias_intro']) ? sanitize_textarea_field(wp_unslash($_POST['familias_intro'])) : '');
        foreach (array('familias_tribanda', 'familias_tutor', 'familias_foto', 'familias_anteriores', 'familias_otros', 'familias_ayuda') as $key) {
            Area_Profesores_Admin::update_setting($key, $yes($key));
        }

        // Public page: same list as "Ajustes > Páginas públicas".
        $public = array_map('absint', (array) Area_Profesores_Admin::get_setting('public_page_ids', array()));
        $public = array_values(array_diff($public, array($page_id)));
        if ($page_id && !empty($_POST['familias_publica'])) {
            $public[] = $page_id;
        }
        Area_Profesores_Admin::update_setting('public_page_ids', $public);

        set_transient('ap_chat_flash_' . get_current_user_id(), array('type' => 'success', 'text' => __('Página para las familias guardada.', 'area-profesores')), 120);
        wp_safe_redirect(admin_url('admin.php?page=area-profesores-chat#ap-familias'));
        exit;
    }

    /* ---------------------------------------------------------------------
     * Render
     * ------------------------------------------------------------------- */

    /**
     * Content injected in the selected page.
     */
    public static function render_pagina() {
        return self::render(self::get_opciones());
    }

    /**
     * [espacios_familias] — attributes override the saved options:
     * titulo, etapa, tutor, foto, tribanda, anteriores, otros, logo.
     */
    public function render_shortcode($atts = array()) {
        $o = self::get_opciones();
        $atts = (array) $atts;
        $map = array('titulo' => 'familias_titulo', 'tutor' => 'familias_tutor', 'foto' => 'familias_foto', 'tribanda' => 'familias_tribanda', 'anteriores' => 'familias_anteriores', 'otros' => 'familias_otros');
        foreach ($map as $attr => $key) {
            if (isset($atts[$attr])) {
                $o[$key] = $attr === 'titulo' ? $atts[$attr] : (in_array(strtolower($atts[$attr]), array('si', 'sí', 'yes', '1', 'true'), true) ? 'yes' : 'no');
            }
        }
        $o['etapa'] = isset($atts['etapa']) ? sanitize_key($atts['etapa']) : '';
        $o['logo'] = isset($atts['logo']) ? esc_url_raw($atts['logo']) : '';
        return self::render($o);
    }

    private static function render($o) {
        $etapa_filtro = isset($o['etapa']) ? $o['etapa'] : '';
        $mostrar_tutor = $o['familias_tutor'] === 'yes';
        $mostrar_foto = $mostrar_tutor && $o['familias_foto'] === 'yes';
        $mostrar_otros = $o['familias_otros'] === 'yes';

        $todos = array_filter(Area_Profesores_Chat::get_espacios(), function ($e) {
            return $e['activo'] && $e['publico'] && $e['chat_url'] !== '';
        });

        $actuales = array_filter($todos, function ($e) use ($etapa_filtro, $mostrar_otros) {
            if ($e['archivada']) {
                return false;
            }
            if ($e['tipo'] === 'promocion') {
                return $e['curso'] !== '' && ($etapa_filtro === '' || $e['etapa'] === $etapa_filtro);
            }
            return $mostrar_otros && $etapa_filtro === '';
        });
        $anteriores = ($o['familias_anteriores'] === 'yes' && $etapa_filtro === '') ? array_values(array_filter($todos, function ($e) {
            return $e['archivada'];
        })) : array();

        $grupos = Area_Profesores_Chat::agrupar($actuales);
        $tutores = $mostrar_tutor ? Area_Profesores_Aulas::get_tutores_por_curso() : array();

        $titulo = $o['familias_titulo'] !== '' ? $o['familias_titulo'] : __('Espacios de familias', 'area-profesores');
        // Intro: plain text with **negrita**.
        $intro = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', esc_html($o['familias_intro']));
        $mostrar_ayuda = $o['familias_ayuda'] === 'yes';
        $logo_url = !empty($o['logo']) ? $o['logo'] : Area_Profesores_Admin::get_setting('logo_familias_url', '');
        $tribanda_url = $o['familias_tribanda'] === 'yes' ? Area_Profesores_Admin::get_setting('tribanda_url', '') : '';

        ob_start();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/espacios-publico.php';
        return ob_get_clean();
    }

    /**
     * Data of the tutor of a course for the cards: name, photo URL and initials.
     */
    public static function get_tutor_card($users) {
        if (empty($users)) {
            return null;
        }
        $u = $users[0];
        $partes = preg_split('/\s+/', trim($u->display_name));
        $iniciales = mb_strtoupper(mb_substr($partes[0], 0, 1) . (isset($partes[1]) ? mb_substr($partes[1], 0, 1) : ''));
        return array(
            'nombre'    => $u->display_name,
            // default=404: without a real photo the image fails and the initials are shown.
            'foto'      => get_avatar_url($u->ID, array('size' => 96, 'default' => '404')),
            'iniciales' => $iniciales,
        );
    }
}
