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
 *   "esp:<etapa>" para especialistas ("esp:primaria").
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

    /* ---------------------------------------------------------------------
     * Asignación de profesores (etapa + tutor/especialista + curso)
     * ------------------------------------------------------------------- */

    /**
     * Current assignment of a teacher.
     *
     * @return array { etapa, rol, curso }
     */
    public static function get_asignacion($user_id) {
        $curso = (string) get_user_meta($user_id, self::META_CURSO, true);
        $etapa = (string) get_user_meta($user_id, self::META_ETAPA, true);
        $rol = (string) get_user_meta($user_id, self::META_ROL, true);

        if ($curso !== '' && self::get_curso($curso)) {
            $c = self::get_curso($curso);
            return array('etapa' => $c['etapa'], 'rol' => 'tutor', 'curso' => $curso);
        }

        if (!array_key_exists($etapa, self::get_etapas())) {
            $etapa = '';
        }

        return array(
            'etapa' => $etapa,
            'rol'   => $etapa !== '' ? 'especialista' : '',
            'curso' => '',
        );
    }

    /**
     * Assignment as a single code: course key or "esp:<etapa>" ('' = sin asignar).
     */
    public static function get_codigo_asignacion($user_id) {
        $a = self::get_asignacion($user_id);
        if ($a['curso'] !== '') {
            return $a['curso'];
        }
        return $a['etapa'] !== '' ? 'esp:' . $a['etapa'] : '';
    }

    /**
     * Normalize a code; returns '' when invalid.
     */
    public static function sanitize_codigo($codigo) {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return '';
        }
        if (strpos($codigo, 'esp:') === 0) {
            $etapa = substr($codigo, 4);
            return array_key_exists($etapa, self::get_etapas()) ? $codigo : '';
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

        if (strpos($codigo, 'esp:') === 0) {
            update_user_meta($user_id, self::META_ETAPA, substr($codigo, 4));
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
     * Human label of a code: "Tutor/a de 3º EP A (Primaria)" / "Especialista de Primaria".
     */
    public static function get_codigo_label($codigo, $short = false) {
        $codigo = (string) $codigo;
        if ($codigo === '') {
            return __('Sin asignar', 'area-profesores');
        }
        if (strpos($codigo, 'esp:') === 0) {
            $etapa = self::get_etapa_label(substr($codigo, 4));
            if ($etapa === '') {
                return $codigo;
            }
            return $short ? sprintf(__('Especialista · %s', 'area-profesores'), $etapa) : sprintf(__('Especialista de %s', 'area-profesores'), $etapa);
        }
        $c = self::get_curso($codigo);
        if (!$c) {
            return $codigo;
        }
        return $short ? sprintf(__('Tutor/a %s', 'area-profesores'), $c['label']) : sprintf(__('Tutor/a de %1$s (%2$s)', 'area-profesores'), $c['label'], self::get_etapa_label($c['etapa']));
    }

    /**
     * <option>s with optgroups per etapa: "Especialista de X" + its courses.
     */
    public static function render_codigo_options($selected = '', $empty_label = null) {
        $empty_label = $empty_label === null ? __('— Sin asignar —', 'area-profesores') : $empty_label;
        $html = '<option value="">' . esc_html($empty_label) . '</option>';

        foreach (self::get_cursos_por_etapa() as $etapa => $cursos) {
            $html .= '<optgroup label="' . esc_attr(self::get_etapa_label($etapa)) . '">';
            $val = 'esp:' . $etapa;
            $html .= '<option value="' . esc_attr($val) . '"' . selected($selected, $val, false) . '>'
                . esc_html(sprintf(__('%s · Especialista', 'area-profesores'), self::get_etapa_label($etapa))) . '</option>';
            foreach ($cursos as $key => $c) {
                $html .= '<option value="' . esc_attr($key) . '"' . selected($selected, $key, false) . '>'
                    . esc_html(sprintf(__('%s · Tutor/a', 'area-profesores'), $c['label'])) . '</option>';
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
