<?php
/**
 * Página pública de espacios de familias: [espacios_familias]
 *
 * Lists every family Chat space, ordered by etapa and course, with a search box
 * and a button to open each space. Pages with this shortcode are always public,
 * even when "Restringir toda la web" is on.
 *
 * Attributes:
 * - titulo="…"           Heading (default "Espacios de familias en Google Chat").
 * - etapa="primaria"     Only one etapa (primer_ciclo, infantil, primaria, secundaria).
 * - tutor="si"           Also show the tutor/a of each course.
 * - otros="no"           Hide the "otros espacios" (AMPA, coro…).
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
        add_shortcode(self::SHORTCODE, array($this, 'render'));
        add_filter('area_profesores_is_public_request', array($this, 'make_public'));
    }

    /**
     * Pages containing the shortcode are public.
     */
    public function make_public($public) {
        if ($public || !is_singular()) {
            return $public;
        }
        $post = get_queried_object();
        return ($post instanceof WP_Post && has_shortcode($post->post_content, self::SHORTCODE)) ? true : $public;
    }

    public function render($atts = array()) {
        $atts = shortcode_atts(array(
            'titulo' => __('Espacios de familias en Google Chat', 'area-profesores'),
            'etapa'  => '',
            'tutor'  => 'no',
            'otros'  => 'si',
        ), $atts, self::SHORTCODE);

        wp_enqueue_style('area-profesores-frontend');

        $mostrar_tutor = in_array(strtolower($atts['tutor']), array('si', 'sí', 'yes', '1', 'true'), true);
        $mostrar_otros = !in_array(strtolower($atts['otros']), array('no', '0', 'false'), true);
        $etapa_filtro = sanitize_key($atts['etapa']);

        $tutores = $mostrar_tutor ? Area_Profesores_Aulas::get_tutores_por_curso() : array();

        $espacios = array_filter(Area_Profesores_Chat::get_espacios(), function ($e) use ($etapa_filtro, $mostrar_otros) {
            if (!$e['activo'] || !$e['publico'] || $e['chat_url'] === '') {
                return false;
            }
            if ($e['tipo'] === 'promocion') {
                return $e['curso'] !== '' && ($etapa_filtro === '' || $e['etapa'] === $etapa_filtro);
            }
            return $mostrar_otros && $etapa_filtro === '';
        });
        $grupos = Area_Profesores_Chat::agrupar($espacios);
        $titulo = $atts['titulo'];
        $logo_url = Area_Profesores_Admin::get_setting('logo_url', '');

        ob_start();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/espacios-publico.php';
        return ob_get_clean();
    }
}
