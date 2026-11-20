<?php
/**
 * Cursos, etapas y promociones del colegio.
 *
 * - Etapas: Primer Ciclo, Infantil, Primaria y Secundaria (ESO + Bachillerato).
 * - Cursos con tutoría (1 año A … 2º Bachillerato).
 * - Promociones: cada curso corresponde a una promoción (año en que terminan
 *   2º de Bachillerato), que avanza sola cada septiembre. Los espacios de Google
 *   Chat de las familias van unidos a la promoción, así que cada año aparecen
 *   automáticamente con el curso nuevo.
 * - Asignación del profesor (un único código): curso para tutores ("EP3A") o
 *   "esp" para especialistas. Los especialistas no tienen una etapa fija: sus
 *   etapas son las de las clases que se les asignan (pueden ser de varias).
 *   El código antiguo "esp:<etapa>" se sigue aceptando y equivale a "esp".
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Cursos {

    const META_ETAPA = '_ap_etapa';
    const META_ROL   = '_ap_rol';      // tutor | especialista
    const META_CURSO = '_ap_tutoria';  // course key when tutor (legacy meta name)

    /** Month in which the school year starts (promotions move up). */
    const MES_INICIO_CURSO = 9;

    /**
     * Etapas, in display order.
     */
    public static function get_etapas() {
        return array(
            'primer_ciclo' => __('Primer Ciclo', 'area-profesores'),
            'infantil'     => __('Infantil', 'area-profesores'),
            'primaria'     => __('Primaria', 'area-profesores'),
            'secundaria'   => __('Secundaria', 'area-profesores'),
        );
    }

    public static function get_etapa_label($etapa) {
        $etapas = self::get_etapas();
        return isset($etapas[$etapa]) ? $etapas[$etapa] : '';
    }

    /**
     * Course levels: key prefix => (label pattern, etapa, years until the end of 2º Bachillerato, groups).
     */
    private static function get_niveles() {
        return array(
            'PC1'   => array('label' => '1 año',            'etapa' => 'primer_ciclo', 'offset' => 16, 'grupos' => array('A', 'B')),
            'PC2'   => array('label' => '2 años',           'etapa' => 'primer_ciclo', 'offset' => 15, 'grupos' => array('A', 'B')),
            'I3'    => array('label' => '3 años',           'etapa' => 'infantil',     'offset' => 14, 'grupos' => array('A', 'B')),
            'I4'    => array('label' => '4 años',           'etapa' => 'infantil',     'offset' => 13, 'grupos' => array('A', 'B')),
            'I5'    => array('label' => '5 años',           'etapa' => 'infantil',     'offset' => 12, 'grupos' => array('A', 'B')),
            'EP1'   => array('label' => '1º EP',            'etapa' => 'primaria',     'offset' => 11, 'grupos' => array('A', 'B')),
            'EP2'   => array('label' => '2º EP',            'etapa' => 'primaria',     'offset' => 10, 'grupos' => array('A', 'B')),
            'EP3'   => array('label' => '3º EP',            'etapa' => 'primaria',     'offset' => 9,  'grupos' => array('A', 'B')),
            'EP4'   => array('label' => '4º EP',            'etapa' => 'primaria',     'offset' => 8,  'grupos' => array('A', 'B')),
            'EP5'   => array('label' => '5º EP',            'etapa' => 'primaria',     'offset' => 7,  'grupos' => array('A', 'B')),
            'EP6'   => array('label' => '6º EP',            'etapa' => 'primaria',     'offset' => 6,  'grupos' => array('A', 'B')),
            'ESO1'  => array('label' => '1º ESO',           'etapa' => 'secundaria',   'offset' => 5,  'grupos' => array('A', 'B')),
            'ESO2'  => array('label' => '2º ESO',           'etapa' => 'secundaria',   'offset' => 4,  'grupos' => array('A', 'B')),
            'ESO3'  => array('label' => '3º ESO',           'etapa' => 'secundaria',   'offset' => 3,  'grupos' => array('A', 'B')),
            'ESO4'  => array('label' => '4º ESO',           'etapa' => 'secundaria',   'offset' => 2,  'grupos' => array('A', 'B')),
            'BACH1' => array('label' => '1º Bachillerato',  'etapa' => 'secundaria',   'offset' => 1,  'grupos' => array('')),
            'BACH2' => array('label' => '2º Bachillerato',  'etapa' => 'secundaria',   'offset' => 0,  'grupos' => array('')),
        );
    }

    /**
     * All courses with tutoría, in order.
     *
     * @return array key => array(key, nivel, label, etapa, offset, grupo, orden)
     */
    public static function get_cursos() {
        static $cursos = null;
        if ($cursos !== null) {
            return $cursos;
        }

        $cursos = array();
        $orden = 0;
        foreach (self::get_niveles() as $nivel => $n) {
            foreach ($n['grupos'] as $g) {
                $key = $nivel . $g;
                $cursos[$key] = array(
                    'key'    => $key,
                    'nivel'  => $nivel,
                    'label'  => trim($n['label'] . ' ' . $g),
                    'etapa'  => $n['etapa'],
                    'offset' => $n['offset'],
                    'grupo'  => $g,
                    'orden'  => $orden++,
                );
            }
        }
        return $cursos;
    }

    public static function get_curso($key) {
        $cursos = self::get_cursos();
        return isset($cursos[$key]) ? $cursos[$key] : null;
    }

    /**
     * Courses grouped by etapa: etapa => array(key => curso).
     */
    public static function get_cursos_por_etapa() {
        $out = array();
        foreach (array_keys(self::get_etapas()) as $e) {
            $out[$e] = array();
        }
        foreach (self::get_cursos() as $key => $c) {
            $out[$c['etapa']][$key] = $c;
        }
        return $out;
    }

    /**
     * Flat list key => label (for legacy callers).
     */
    public static function get_cursos_options() {
        $out = array();
        foreach (self::get_cursos() as $key => $c) {
            $out[$key] = $c['label'];
        }
        return $out;
    }

    /**
     * <option>s of every course grouped by etapa.
     */
    public static function get_cursos_options_html($selected = '') {
        $html = '';
        foreach (self::get_cursos_por_etapa() as $etapa => $cursos) {
            $html .= '<optgroup label="' . esc_attr(self::get_etapa_label($etapa)) . '">';
            foreach ($cursos as $key => $c) {
                $html .= '<option value="' . esc_attr($key) . '"' . selected($selected, $key, false) . '>' . esc_html($c['label']) . '</option>';
            }
            $html .= '</optgroup>';
        }
        return $html;
    }

    /* ---------------------------------------------------------------------
     * Promociones
     * ------------------------------------------------------------------- */

    /**
     * Calendar year in which the current school year ends (Nov 2026 → 2027).
     */
    public static function get_fin_curso_escolar($timestamp = null) {
        $timestamp = $timestamp ? $timestamp : current_time('timestamp');
        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
        return $month >= self::MES_INICIO_CURSO ? $year + 1 : $year;
    }

    /**
     * "2026-2027"
     */
    public static function get_curso_escolar_label() {
        $fin = self::get_fin_curso_escolar();
        return ($fin - 1) . '-' . $fin;
    }

    public static function get_promocion($curso_key) {
        $c = self::get_curso($curso_key);
        return $c ? self::get_fin_curso_escolar() + $c['offset'] : 0;
    }

    /**
     * Course that a promotion/group is studying this school year.
     * Group '' means a single group (shown without letter).
     *
     * @return array|null curso (with 'label' adjusted for single groups)
     */
    public static function get_curso_de_promocion($promocion, $grupo = '') {
        $offset = (int) $promocion - self::get_fin_curso_escolar();
        $candidatos = array();
        foreach (self::get_cursos() as $c) {
            if ($c['offset'] === $offset) {
                $candidatos[] = $c;
            }
        }
        if (empty($candidatos)) {
            return null; // Promoción ya egresada o todavía no ha empezado.
        }

        foreach ($candidatos as $c) {
            if ($c['grupo'] === $grupo) {
                return $c;
            }
        }

        // No exact group (e.g. a single-group promotion in a level that usually has A/B,
        // or groups A/B that reach Bachillerato): use the first course of the level and
        // show the level with the promotion's own letter ("2 años", "1º Bachillerato A").
        $c = $candidatos[0];
        $niveles = self::get_niveles();
        $c['label'] = trim($niveles[$c['nivel']]['label'] . ' ' . $grupo);
        return $c;
    }

    /**
     * "3º EP A · Promoción 2036"
     */
    public static function get_curso_label_con_promocion($curso_key) {
        $c = self::get_curso($curso_key);
        if (!$c) {
            return '';
        }
        return sprintf(__('%1$s · Promoción %2$d', 'area-profesores'), $c['label'], self::get_promocion($curso_key));
    }

    /**
     * Course the group moves to next school year ('' = leaves the school).
     * 1 año A → 2 años A … 4º ESO A → 1º Bachillerato, 2º Bachillerato → ''.
     */
    public static function get_siguiente_curso($key) {
        $c = self::get_curso($key);
        if (!$c) {
            return '';
        }
        $niveles = self::get_niveles();
        $orden = array_keys($niveles);
        $pos = array_search($c['nivel'], $orden, true);
        if ($pos === false || !isset($orden[$pos + 1])) {
            return '';
        }
        $next = $orden[$pos + 1];
        $grupos = $niveles[$next]['grupos'];
        if (in_array($c['grupo'], $grupos, true)) {
            return $next . $c['grupo'];
        }
        return in_array('', $grupos, true) ? $next : $next . $grupos[0];
    }

    /**
     * Course key from the "Nivel" of Alexia: EP-3ºA, INF-1A (1 año A), INF-4B,
     * E2ºB (2º ESO B), B-1ºA / B2ºA (Bachillerato). '' if unknown.
     */
    public static function curso_desde_alexia($nivel) {
        $n = strtoupper(str_replace(array(' ', 'º', 'ª', '°', '.'), '', (string) $nivel));
        if (preg_match('/^EP-?(\d)([AB])$/', $n, $m)) {
            $k = 'EP' . $m[1] . $m[2];
        } elseif (preg_match('/^INF-?(\d)([AB])$/', $n, $m)) {
            $k = ((int) $m[1] <= 2 ? 'PC' : 'I') . $m[1] . $m[2];
        } elseif (preg_match('/^(?:E|ESO)-?(\d)([AB])$/', $n, $m)) {
            $k = 'ESO' . $m[1] . $m[2];
        } elseif (preg_match('/^(?:B|BACH)-?(\d)[AB]?$/', $n, $m)) {
            $k = 'BACH' . $m[1];
        } else {
            return '';
        }
        return self::get_curso($k) ? $k : '';
    }

    /* ---------------------------------------------------------------------
     * Asignación de profesores (etapa + tutor/especialista + curso)
     * ------------------------------------------------------------------- */

    /**
     * Current assignment of a teacher.
     *
     * - etapa: the tutor's etapa ('' for specialists, who have no fixed etapa).
     * - etapas: every etapa the teacher works in (tutor: one; specialist: the
     *   etapas of the classes assigned to them).
     *
     * @return array { etapa, etapas, rol, curso }
     */
    public static function get_asignacion($user_id) {
        $curso = (string) get_user_meta($user_id, self::META_CURSO, true);
        $etapa = (string) get_user_meta($user_id, self::META_ETAPA, true);
        $rol = (string) get_user_meta($user_id, self::META_ROL, true);

        if ($curso !== '' && self::get_curso($curso)) {
            $c = self::get_curso($curso);
            $etapas = array($c['etapa']);
            foreach (self::etapas_de_clases($user_id) as $e) {
                if (!in_array($e, $etapas, true)) {
                    $etapas[] = $e;
                }
            }
            return array('etapa' => $c['etapa'], 'etapas' => $etapas, 'rol' => 'tutor', 'curso' => $curso);
        }

        // Specialist: new model (rol only) or legacy "specialist of an etapa".
        if ($rol === 'especialista' || array_key_exists($etapa, self::get_etapas())) {
            $etapas = self::etapas_de_clases($user_id);
            if (!$etapas && array_key_exists($etapa, self::get_etapas())) {
                $etapas = array($etapa); // legacy, until classes are assigned
            }
            return array('etapa' => '', 'etapas' => $etapas, 'rol' => 'especialista', 'curso' => '');
        }

        return array('etapa' => '', 'etapas' => array(), 'rol' => '', 'curso' => '');
    }

    /**
     * Etapas of the classes assigned to a teacher (in etapa order).
     */
    public static function etapas_de_clases($user_id) {
        if (!class_exists('Area_Profesores_Alumnos')) {
            return array();
        }
        $found = array();
        foreach (Area_Profesores_Alumnos::get_clases_asignadas($user_id) as $k) {
            $c = self::get_curso($k);
            if ($c) {
                $found[$c['etapa']] = true;
            }
        }
        return array_values(array_filter(array_keys(self::get_etapas()), function ($e) use ($found) {
            return isset($found[$e]);
        }));
    }

    /**
     * Etapas a teacher works in (see get_asignacion()).
     */
    public static function get_etapas_usuario($user_id) {
        return self::get_asignacion($user_id)['etapas'];
    }

    /**
     * Assignment as a single code: course key or "esp" ('' = sin asignar).
     */
    public static function get_codigo_asignacion($user_id) {
        $a = self::get_asignacion($user_id);
        if ($a['curso'] !== '') {
            return $a['curso'];
        }
        return $a['rol'] === 'especialista' ? 'esp' : '';
    }

    /**
     * Normalize a code; returns '' when invalid.
     */
    public static function sanitize_codigo($codigo) {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return '';
        }
        if ($codigo === 'esp' || strpos($codigo, 'esp:') === 0) {
            return 'esp'; // "esp:<etapa>" is the old code
        }
        return self::get_curso($codigo) ? $codigo : '';
    }

    /**
     * Save the assignment from a code.
     */
    public static function set_asignacion($user_id, $codigo) {
        $codigo = self::sanitize_codigo($codigo);

        if ($codigo === '') {
            update_user_meta($user_id, self::META_ETAPA, '');
            update_user_meta($user_id, self::META_ROL, '');
            update_user_meta($user_id, self::META_CURSO, '');
            return;
        }

        if ($codigo === 'esp') {
            update_user_meta($user_id, self::META_ETAPA, '');
            update_user_meta($user_id, self::META_ROL, 'especialista');
            update_user_meta($user_id, self::META_CURSO, '');
            return;
        }

        $c = self::get_curso($codigo);
        update_user_meta($user_id, self::META_ETAPA, $c['etapa']);
        update_user_meta($user_id, self::META_ROL, 'tutor');
        update_user_meta($user_id, self::META_CURSO, $codigo);
    }

    /**
     * Human label of a code: "Tutor/a de 3º EP A (Primaria)" / "Especialista".
     */
    public static function get_codigo_label($codigo, $short = false) {
        $codigo = (string) $codigo;
        if ($codigo === '') {
            return __('Sin asignar', 'area-profesores');
        }
        if ($codigo === 'esp' || strpos($codigo, 'esp:') === 0) {
            return __('Especialista', 'area-profesores');
        }
        $c = self::get_curso($codigo);
        if (!$c) {
            return $codigo;
        }
        return $short ? sprintf(__('Tutor/a %s', 'area-profesores'), $c['label']) : sprintf(__('Tutor/a de %1$s (%2$s)', 'area-profesores'), $c['label'], self::get_etapa_label($c['etapa']));
    }

    /**
     * <option>s: "Especialista" (any etapa) + one optgroup per etapa with its courses.
     */
    public static function render_codigo_options($selected = '', $empty_label = null) {
        $empty_label = $empty_label === null ? __('— Sin asignar —', 'area-profesores') : $empty_label;
        $selected = (string) $selected;
        if (strpos($selected, 'esp:') === 0) {
            $selected = 'esp';
        }
        $html = '<option value="">' . esc_html($empty_label) . '</option>';
        $html .= '<option value="esp"' . selected($selected, 'esp', false) . '>'
            . esc_html__('Especialista (clases de una o varias etapas)', 'area-profesores') . '</option>';

        foreach (self::get_cursos_por_etapa() as $etapa => $cursos) {
            $html .= '<optgroup label="' . esc_attr(sprintf(__('Tutor/a · %s', 'area-profesores'), self::get_etapa_label($etapa))) . '">';
            foreach ($cursos as $key => $c) {
                $html .= '<option value="' . esc_attr($key) . '"' . selected($selected, $key, false) . '>'
                    . esc_html(sprintf(__('Tutor/a de %s', 'area-profesores'), $c['label'])) . '</option>';
            }
            $html .= '</optgroup>';
        }

        return $html;
    }

    /* ---------------------------------------------------------------------
     * Migración desde el modelo anterior (tutorías 1A…6B de Primaria)
     * ------------------------------------------------------------------- */

    public static function maybe_migrate() {
        if (get_option('ap_cursos_version') === '2') {
            return;
        }

        $legacy = array();
        foreach (array('1', '2', '3', '4', '5', '6') as $n) {
            foreach (array('A', 'B') as $g) {
                $legacy[$n . $g] = 'EP' . $n . $g;
            }
        }

        $users = get_users(array(
            'meta_key'     => self::META_CURSO,
            'meta_compare' => 'EXISTS',
            'fields'       => 'ID',
        ));
        foreach ($users as $uid) {
            $old = (string) get_user_meta($uid, self::META_CURSO, true);
            if (isset($legacy[$old])) {
                self::set_asignacion($uid, $legacy[$old]);
            }
        }

        // Pending access requests that asked for an old tutoría.
        global $wpdb;
        if (class_exists('Area_Profesores_Solicitudes')) {
            $table = Area_Profesores_Solicitudes::get_table_name();
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
                foreach ($legacy as $old => $new) {
                    $wpdb->update($table, array('tutoria_deseada' => $new), array('tutoria_deseada' => $old));
                }
            }
        }

        update_option('ap_cursos_version', '2', false);
    }
}
