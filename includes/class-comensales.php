<?php
/**
 * Comedor diario por alumnos (Primer Ciclo, Infantil y Primaria).
 *
 * - Each class has its "comensales fijos": the pupils who always stay, with
 *   the weekdays they stay. Every day they appear marked; the teacher marks the
 *   exceptions (a pupil who stays one day = "extra", a regular who does not
 *   stay) and VALIDATES the class. Pupils whose family reserved the comedor in
 *   the Área Familias appear marked too, labelled "Reserva de la familia".
 * - Any teacher of these etapas can fill in a colleague's class.
 * - At the reminder time (10:30) the tutors of the classes not validated get an
 *   e-mail; at the kitchen time (12:00) the "Cocina" accounts get the summary.
 *   Changes made later are sent to the kitchen too. The kitchen page
 *   ([comedorhoy]) shows the data live.
 * - Secretaría sees the "Comensales" tab: meals per day and the extra meals of
 *   each pupil, by month, to charge them.
 * - Secundaria and Bachillerato are not part of this system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Comensales {

    const OPTION   = 'ap_comedor_ajustes';
    const ENVIADOS = 'ap_comedor_enviados';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_post_ap_comedor_validar', array($this, 'handle_validar'));
        add_action('admin_post_ap_comedor_fijos', array($this, 'handle_fijos'));
        add_action('admin_post_ap_comedor_ajustes', array($this, 'handle_ajustes'));
        add_action('admin_post_ap_comedor_csv_mes', array($this, 'handle_csv_mes'));
        add_action('wp_ajax_ap_comedor_estado', array($this, 'ajax_estado'));
        add_action('wp_ajax_nopriv_ap_comedor_estado', array($this, 'ajax_estado'));
        // Same 5-minute event as the scheduled announcements.
        add_action('ap_chat_cron_tick', array($this, 'tick'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * GET /area-profesores/v1/calendario: days without school, for the Área Familias.
     */
    public function register_routes() {
        register_rest_route(Area_Profesores_API::NS, '/calendario', array(
            'methods'             => 'GET',
            'callback'            => function () {
                return rest_ensure_response(array('dias_sin_clase' => array_keys(self::dias_sin_clase())));
            },
            'permission_callback' => array(Area_Profesores_API::get_instance(), 'check_key'),
        ));
    }

    /* ---------------------------------------------------------------------
     * Tablas y ajustes
     * ------------------------------------------------------------------- */

    public static function t($name = 'dia') {
        global $wpdb;
        return $wpdb->prefix . ($name === 'fijos' ? 'ap_comedor_fijos' : 'ap_comedor_dia');
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE " . self::t('dia') . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            fecha DATE NOT NULL,
            curso VARCHAR(20) NOT NULL DEFAULT '',
            total INT(11) NOT NULL DEFAULT 0,
            alumnos LONGTEXT DEFAULT NULL,
            ausentes LONGTEXT DEFAULT NULL,
            dietas TEXT DEFAULT NULL,
            observaciones TEXT DEFAULT NULL,
            validado_por BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            validado DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY fecha_curso (fecha, curso)
        ) $c;");
        dbDelta("CREATE TABLE " . self::t('fijos') . " (
            alumno_id BIGINT(20) UNSIGNED NOT NULL,
            dias VARCHAR(20) NOT NULL DEFAULT '',
            actualizado DATETIME DEFAULT NULL,
            PRIMARY KEY  (alumno_id)
        ) $c;");
    }

    public static function ajustes() {
        $o = get_option(self::OPTION, array());
        return array_merge(array(
            'hora_recordatorio' => '10:30',
            'hora_cocina'       => '12:00',
            'recordatorio'      => 'yes',
            'resumen'           => 'yes',
            'dias_sin_clase'    => '',
        ), is_array($o) ? $o : array());
    }

    /** Etapas with the daily dining-room system. */
    public static function etapas() {
        return array('primer_ciclo', 'infantil', 'primaria');
    }

    /** Courses of those etapas (key => course). */
    public static function cursos() {
        return array_filter(Area_Profesores_Cursos::get_cursos(), function ($c) {
            return in_array($c['etapa'], self::etapas(), true);
        });
    }

    public static function es_curso($k) {
        return isset(self::cursos()[$k]);
    }

    /**
     * Whether a teacher uses the dining room: tutors and teachers of those
     * etapas, Dirección, Secretaría and administrators.
     */
    public static function puede($user_id) {
        if (!$user_id) {
            return false;
        }
        if (user_can($user_id, 'manage_options') || Area_Profesores_Chat::has_direccion_flag($user_id) || Area_Profesores_Alumnos::es_secretaria($user_id)) {
            return true;
        }
        return (bool) array_intersect(Area_Profesores_Cursos::get_etapas_usuario($user_id), self::etapas());
    }

    public static function puede_ver_mes($user_id) {
        return $user_id && (user_can($user_id, 'manage_options') || Area_Profesores_Chat::has_direccion_flag($user_id) || Area_Profesores_Alumnos::es_secretaria($user_id));
    }

    /* ---------------------------------------------------------------------
     * Días lectivos
     * ------------------------------------------------------------------- */

    private static function parse_fecha($t) {
        $t = trim($t);
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $t, $m)) {
            $t = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) ? $t : '';
    }

    /**
     * Days without school ("08/12/2026", "23/12/2026 - 07/01/2027", optional "| comment").
     *
     * @return array Y-m-d => comment
     */
    public static function dias_sin_clase() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = array();
        foreach (preg_split('/\r\n|\r|\n/', (string) self::ajustes()['dias_sin_clase']) as $line) {
            $parts = explode('|', $line, 2);
            $txt = trim($parts[0]);
            if ($txt === '') {
                continue;
            }
            $p = preg_split('/\s+(?:-|–|al|a)\s+/u', $txt);
            $ini = self::parse_fecha($p[0]);
            $fin = isset($p[1]) ? self::parse_fecha($p[1]) : $ini;
            if ($ini === '' || $fin === '' || $fin < $ini) {
                continue;
            }
            for ($t = strtotime($ini . ' 12:00:00 UTC'), $e = strtotime($fin . ' 12:00:00 UTC'), $i = 0; $t <= $e && $i < 400; $t += DAY_IN_SECONDS, $i++) {
                $cache[gmdate('Y-m-d', $t)] = isset($parts[1]) ? trim($parts[1]) : '';
            }
        }
        return $cache;
    }

    public static function dow($fecha) {
        return (int) gmdate('N', strtotime($fecha . ' 12:00:00 UTC'));
    }

    public static function es_lectivo($fecha) {
        return self::dow($fecha) <= 5 && !isset(self::dias_sin_clase()[$fecha]);
    }

    /* ---------------------------------------------------------------------
     * Comensales fijos
     * ------------------------------------------------------------------- */

    /**
     * Weekdays each pupil stays (alumno_id => [1..5]).
     */
    public static function fijos($ids) {
        global $wpdb;
        $ids = array_filter(array_map('intval', (array) $ids));
        if (!$ids) {
            return array();
        }
        $rows = $wpdb->get_results('SELECT alumno_id, dias FROM ' . self::t('fijos') . ' WHERE alumno_id IN (' . implode(',', $ids) . ')', ARRAY_A);
        $out = array();
        foreach ((array) $rows as $r) {
            $out[(int) $r['alumno_id']] = $r['dias'] === '' ? array() : array_map('intval', explode(',', $r['dias']));
        }
        return $out;
    }

    public static function set_fijos($alumno_id, $dias) {
        global $wpdb;
        $dias = array_values(array_unique(array_filter(array_map('intval', (array) $dias), function ($d) {
            return $d >= 1 && $d <= 5;
        })));
        sort($dias);
        $wpdb->replace(self::t('fijos'), array('alumno_id' => (int) $alumno_id, 'dias' => implode(',', $dias), 'actualizado' => current_time('mysql')));
    }

    /* ---------------------------------------------------------------------
     * Registro del día
     * ------------------------------------------------------------------- */

    private static function fila($r) {
        if (!$r) {
            return null;
        }
        $r = (array) $r;
        foreach (array('alumnos', 'ausentes') as $k) {
            $d = json_decode((string) $r[$k], true);
            $r[$k] = is_array($d) ? $d : array();
        }
        $r['total'] = (int) $r['total'];
        $r['validado_por'] = (int) $r['validado_por'];
        $u = $r['validado_por'] ? get_userdata($r['validado_por']) : null;
        $r['validado_nombre'] = $u ? $u->display_name : '';
        return $r;
    }

    public static function get_dia($fecha, $curso) {
        global $wpdb;
        return self::fila($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE fecha = %s AND curso = %s', $fecha, $curso), ARRAY_A));
    }

    /**
     * Records of a date (curso => record).
     */
    public static function registros($fecha) {
        global $wpdb;
        $out = array();
        foreach ((array) $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE fecha = %s', $fecha), ARRAY_A) as $r) {
            $out[$r['curso']] = self::fila($r);
        }
        return $out;
    }

    public static function alumnos_curso($curso) {
        return Area_Profesores_Alumnos::buscar_alumnos(array('curso' => $curso, 'estado' => 'alta'));
    }

    /**
     * Families' reservations of a date for a course: comedor (alumno_id => r)
     * and diet details (alumno_id => text).
     */
    private static function reservas_familia($fecha, $curso) {
        $comedor = array();
        $dietas = array();
        if (!class_exists('Area_Profesores_Reservas')) {
            return array($comedor, $dietas);
        }
        foreach (Area_Profesores_Reservas::del_dia($fecha, array($curso)) as $r) {
            if ($r['servicio'] !== 'comedor' && $r['servicio'] !== 'dieta') {
                continue;
            }
            if ($r['servicio'] === 'comedor') {
                $comedor[$r['alumno_id']] = $r;
            }
            $l = array();
            $con_dieta = $r['servicio'] === 'dieta';
            foreach ($r['detalles'] as $d) {
                if ($d['label'] === 'Dieta' && stripos($d['valor'], 's') === 0) {
                    $con_dieta = true;
                }
                if (in_array($d['label'], array('Tipo de dieta', 'Instrucciones especiales'), true)) {
                    $l[] = $d['valor'];
                }
            }
            if ($con_dieta && $l) {
                $dietas[$r['alumno_id']] = implode(' — ', $l);
            }
        }
        return array($comedor, $dietas);
    }

    /**
     * Pupils of a class for a date, with whether they eat and why.
     *
     * tipo: fijo | extra | familia | no (regular who does not stay) | '' (does not eat)
     */
    public static function lista($fecha, $curso) {
        $alumnos = self::alumnos_curso($curso);
        $fijos = self::fijos(wp_list_pluck($alumnos, 'id'));
        list($familia, $dietas) = self::reservas_familia($fecha, $curso);
        $reg = self::get_dia($fecha, $curso);
        $guardados = array();
        if ($reg) {
            foreach ($reg['alumnos'] as $a) {
                $guardados[(int) $a['id']] = $a['tipo'];
            }
        }
        $dow = self::dow($fecha);
        $out = array();
        foreach ($alumnos as $a) {
            $id = (int) $a['id'];
            $dias = isset($fijos[$id]) ? $fijos[$id] : array();
            $fijo = in_array($dow, $dias, true);
            $fam = isset($familia[$id]) ? $familia[$id] : null;
            if ($reg) {
                // A family reservation made after the validation also counts.
                $come = isset($guardados[$id]) || ($fam && strtotime((string) $fam['creado']) > strtotime((string) $reg['validado']));
            } else {
                $come = $fijo || (bool) $fam;
            }
            $out[] = array(
                'id'      => $id,
                'nombre'  => $a['nombre'],
                'dias'    => $dias,
                'fijo'    => $fijo,
                'familia' => $fam,
                'dieta'   => isset($dietas[$id]) ? $dietas[$id] : '',
                'alergia' => trim((string) (isset($a['alergias_alimentarias']) ? $a['alergias_alimentarias'] : '')),
                'come'    => $come,
                'tipo'    => self::tipo($come, $fijo, (bool) $fam),
            );
        }
        return $out;
    }

    public static function tipo($come, $fijo, $familia) {
        if ($come) {
            return $familia && !$fijo ? 'familia' : ($fijo ? 'fijo' : 'extra');
        }
        return $fijo ? 'no' : '';
    }

    /**
     * Save (validate) the dining room of a class.
     */
    public static function guardar($fecha, $curso, $ids, $dietas, $obs, $user_id) {
        global $wpdb;
        $ids = array_map('intval', (array) $ids);
        $antes = self::get_dia($fecha, $curso);
        $lista = self::lista($fecha, $curso);
        $comen = array();
        $ausentes = array();
        foreach ($lista as $a) {
            $come = in_array($a['id'], $ids, true);
            if ($come) {
                $comen[] = array('id' => $a['id'], 'nombre' => $a['nombre'], 'tipo' => self::tipo(true, $a['fijo'], (bool) $a['familia']), 'dieta' => $a['dieta'], 'alergia' => $a['alergia']);
            } elseif ($a['fijo']) {
                $ausentes[] = array('id' => $a['id'], 'nombre' => $a['nombre'], 'alergia' => $a['alergia']);
            }
        }
        $data = array(
            'fecha'         => $fecha,
            'curso'         => $curso,
            'total'         => count($comen),
            'alumnos'       => wp_json_encode($comen),
            'ausentes'      => wp_json_encode($ausentes),
            'dietas'        => $dietas,
            'observaciones' => $obs,
            'validado_por'  => (int) $user_id,
            'validado'      => current_time('mysql'),
        );
        if ($antes) {
            $wpdb->update(self::t(), $data, array('id' => $antes['id']));
        } else {
            $wpdb->insert(self::t(), $data);
        }
        self::sincronizar_registro_antiguo($fecha, $curso, $data, $comen);

        // After the kitchen summary, the kitchen is told about the change
        // (number of diners, or a pupil with a food allergy who now eats / does not eat).
        $env = get_option(self::ENVIADOS, array());
        $cambio_alergias = self::cambios_alergias($antes, $comen, $ausentes);
        if (is_array($env) && isset($env['fecha'], $env['cocina']) && $env['fecha'] === $fecha && $env['cocina'] && (!$antes || $antes['total'] !== count($comen) || $cambio_alergias)) {
            $c = Area_Profesores_Cursos::get_curso($curso);
            $u = get_userdata($user_id);
            $alergias_txt = $cambio_alergias ? implode("\n", $cambio_alergias) : __('Sin cambios.', 'area-profesores');
            Area_Profesores_Correos::enviar('comedor_cambio', Area_Profesores_Cuentas::emails('cocina'), self::con_alergias('comedor_cambio', array(
                'clase'    => $c ? $c['label'] : $curso,
                'antes'    => $antes ? $antes['total'] : __('sin validar', 'area-profesores'),
                'ahora'    => count($comen),
                'total'    => self::total_dia($fecha),
                'quien'    => $u ? $u->display_name : '',
                'enlace'   => self::url_cocina(),
                'alergias' => $alergias_txt,
            ), $cambio_alergias ? __('Alergias alimentarias:', 'area-profesores') . "\n" . $alergias_txt : ''));
        }
        return count($comen);
    }

    /**
     * Pupils with a food allergy whose situation changed between two validations.
     *
     * @return string[] lines for the kitchen
     */
    private static function cambios_alergias($antes, $comen, $ausentes) {
        $ahora_come = array();
        $ahora_falta = array();
        foreach ($comen as $a) {
            if (!empty($a['alergia'])) {
                $ahora_come[$a['id']] = $a;
            }
        }
        foreach ($ausentes as $a) {
            if (!empty($a['alergia'])) {
                $ahora_falta[$a['id']] = $a;
            }
        }
        $antes_come = array();
        if ($antes) {
            foreach ($antes['alumnos'] as $a) {
                $antes_come[(int) $a['id']] = true;
            }
        }
        $out = array();
        foreach ($ahora_falta as $id => $a) {
            if (!$antes || isset($antes_come[$id])) {
                $out[] = '- ' . sprintf(__('%1$s (alergia: %2$s) NO come hoy: no preparar su menú especial.', 'area-profesores'), $a['nombre'], $a['alergia']);
            }
        }
        if ($antes) {
            foreach ($ahora_come as $id => $a) {
                if (!isset($antes_come[$id])) {
                    $out[] = '- ' . sprintf(__('%1$s (alergia: %2$s) SÍ come hoy: preparar su menú especial.', 'area-profesores'), $a['nombre'], $a['alergia']);
                }
            }
        }
        return $out;
    }

    /**
     * E-mails whose text was customised before {alergias} existed still get the allergies.
     */
    private static function con_alergias($key, $vars, $bloque) {
        $c = Area_Profesores_Correos::get($key);
        if ($bloque !== '' && strpos($c['cuerpo'], '{alergias}') === false) {
            $vars['_anexo'] = $bloque;
        }
        return $vars;
    }

    /**
     * Food allergies of a date: regular diners with an allergy who do not eat
     * (the special menu must not be cooked) and diners with an allergy who eat.
     *
     * @return array { no_comen: [ {clase, nombre, alergia} ], comen: [ ... ] }
     */
    public static function alergias_dia($fecha) {
        $out = array('no_comen' => array(), 'comen' => array());
        $cursos = self::cursos();
        $ids = array();
        $regs = self::registros($fecha);
        foreach ($regs as $r) {
            foreach (array_merge($r['alumnos'], $r['ausentes']) as $a) {
                $ids[] = (int) $a['id'];
            }
        }
        // Current allergies (the record keeps the ones written on the day as a fallback).
        $actual = array();
        if ($ids) {
            global $wpdb;
            foreach ((array) $wpdb->get_results('SELECT id, alergias_alimentarias FROM ' . Area_Profesores_Alumnos::t('alumnos') . ' WHERE id IN (' . implode(',', array_unique($ids)) . ')', ARRAY_A) as $row) {
                $actual[(int) $row['id']] = trim((string) $row['alergias_alimentarias']);
            }
        }
        foreach ($regs as $k => $r) {
            if (!isset($cursos[$k])) {
                continue;
            }
            foreach (array('comen' => $r['alumnos'], 'no_comen' => $r['ausentes']) as $grupo => $lista) {
                foreach ($lista as $a) {
                    $al = isset($actual[(int) $a['id']]) ? $actual[(int) $a['id']] : (isset($a['alergia']) ? (string) $a['alergia'] : '');
                    if ($al !== '') {
                        $out[$grupo][] = array('clase' => $cursos[$k]['label'], 'nombre' => $a['nombre'], 'alergia' => $al);
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Keep the previous table (kitchen page, "Comedor Diario" and its CSV) in step.
     */
    private static function sincronizar_registro_antiguo($fecha, $curso, $data, $comen) {
        global $wpdb;
        $t = Area_Profesores_Comedor::get_table_name();
        $c = Area_Profesores_Cursos::get_curso($curso);
        $dietas = trim((string) $data['dietas']);
        foreach ($comen as $a) {
            if ($a['dieta'] !== '' && strpos($dietas, $a['nombre']) === false) {
                $dietas .= ($dietas !== '' ? "\n" : '') . $a['nombre'] . ': ' . $a['dieta'] . ' ' . __('(reserva de la familia)', 'area-profesores');
            }
        }
        $row = array(
            'user_id'          => $data['validado_por'],
            'fecha'            => $fecha,
            'clase'            => $curso,
            'etapa'            => $c ? Area_Profesores_Cursos::get_etapa_label($c['etapa']) : '',
            'num_alumnos'      => $data['total'],
            'dietas'           => $dietas,
            'alergicos_faltan' => $data['observaciones'],
            'updated_at'       => current_time('mysql'),
        );
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE fecha = %s AND clase = %s", $fecha, $curso));
        if ($id) {
            $wpdb->update($t, $row, array('id' => $id));
        } else {
            $row['created_at'] = current_time('mysql');
            $wpdb->insert($t, $row);
        }
    }

    /**
     * A family books (or cancels) the comedor for today after the class was validated.
     */
    public static function on_reserva($r) {
        $hoy = current_time('Y-m-d');
        if ($r['servicio'] !== 'comedor' || !self::es_curso($r['curso']) || !Area_Profesores_Reservas::aplica($r, $hoy)) {
            return;
        }
        $reg = self::get_dia($hoy, $r['curso']);
        if (!$reg) {
            return; // not validated yet: it will appear marked
        }
        $ids = array_map('intval', wp_list_pluck($reg['alumnos'], 'id'));
        if ($r['estado'] === 'activa') {
            $ids[] = (int) $r['alumno_id'];
        } else {
            $ids = array_diff($ids, array((int) $r['alumno_id']));
        }
        self::guardar($hoy, $r['curso'], array_unique($ids), (string) $reg['dietas'], (string) $reg['observaciones'], $reg['validado_por']);
    }

    public static function total_dia($fecha) {
        $t = 0;
        foreach (self::registros($fecha) as $k => $r) {
            if (self::es_curso($k)) {
                $t += $r['total'];
            }
        }
        return $t;
    }

    /**
     * Status of every class of a date: validated or pending (only classes with pupils).
     */
    public static function estados($fecha) {
        $regs = self::registros($fecha);
        $tutores = Area_Profesores_Aulas::get_tutores_por_curso();
        $con_alumnos = array();
        global $wpdb;
        foreach ((array) $wpdb->get_results("SELECT curso, COUNT(*) AS n FROM " . Area_Profesores_Alumnos::t('alumnos') . " WHERE estado = 'alta' GROUP BY curso", ARRAY_A) as $r) {
            $con_alumnos[$r['curso']] = (int) $r['n'];
        }
        $out = array();
        foreach (self::cursos() as $k => $c) {
            if (empty($con_alumnos[$k]) && !isset($regs[$k])) {
                continue;
            }
            $out[$k] = array(
                'label'    => $c['label'],
                'etapa'    => Area_Profesores_Cursos::get_etapa_label($c['etapa']),
                'alumnos'  => isset($con_alumnos[$k]) ? $con_alumnos[$k] : 0,
                'registro' => isset($regs[$k]) ? $regs[$k] : null,
                'tutores'  => isset($tutores[$k]) ? $tutores[$k] : array(),
            );
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Portal del profesor
     * ------------------------------------------------------------------- */

    public static function url_portal($curso = '') {
        $id = absint(Area_Profesores_Admin::get_setting('area_page_id', 0));
        $url = ($id && get_post_status($id) === 'publish') ? get_permalink($id) : home_url('/');
        return ($curso !== '' ? add_query_arg('ap_cc', $curso, $url) : $url) . '#comedor';
    }

    public static function url_cocina() {
        $id = absint(Area_Profesores_Admin::get_setting('comedor_page_id', 0));
        return ($id && get_post_status($id) === 'publish') ? get_permalink($id) : self::url_portal();
    }

    /**
     * Data for the "Comedor" tab.
     */
    public static function datos_portal($user_id) {
        if (!self::puede($user_id)) {
            return null;
        }
        $hoy = current_time('Y-m-d');
        $tutoria = (string) get_user_meta($user_id, Area_Profesores_Cursos::META_CURSO, true);
        $mi = self::es_curso($tutoria) ? $tutoria : '';
        $pedido = isset($_GET['ap_cc']) ? sanitize_text_field(wp_unslash($_GET['ap_cc'])) : '';
        $curso = self::es_curso($pedido) ? $pedido : $mi;
        $estados = self::estados($hoy);
        $reg = $curso !== '' ? self::get_dia($hoy, $curso) : null;

        // Diets usually last several days: offer the last ones written for the class.
        $dietas_prev = '';
        if ($curso !== '' && !$reg) {
            global $wpdb;
            $dietas_prev = (string) $wpdb->get_var($wpdb->prepare('SELECT dietas FROM ' . self::t() . ' WHERE curso = %s AND fecha < %s AND fecha >= %s ORDER BY fecha DESC LIMIT 1', $curso, $hoy, gmdate('Y-m-d', strtotime($hoy . ' -14 days'))));
        }

        $flash = get_transient('ap_comedor_flash_' . $user_id);
        if ($flash) {
            delete_transient('ap_comedor_flash_' . $user_id);
        }
        return array(
            'fecha'       => $hoy,
            'lectivo'     => self::es_lectivo($hoy),
            'motivo'      => isset(self::dias_sin_clase()[$hoy]) ? self::dias_sin_clase()[$hoy] : '',
            'mi_curso'    => $mi,
            'curso'       => $curso,
            'lista'       => $curso !== '' ? self::lista($hoy, $curso) : array(),
            'registro'    => $reg,
            'estados'     => $estados,
            'dietas_prev' => $dietas_prev,
            'ajustes'     => self::ajustes(),
            'flash'       => $flash,
        );
    }

    private static function volver($curso, $type, $text) {
        set_transient('ap_comedor_flash_' . get_current_user_id(), array('type' => $type, 'text' => $text), 120);
        $back = wp_get_referer() ? wp_get_referer() : self::url_portal();
        wp_safe_redirect(add_query_arg('ap_cc', $curso, strtok($back, '#')) . '#comedor');
        exit;
    }

    public function handle_validar() {
        $uid = get_current_user_id();
        $curso = isset($_POST['curso']) ? sanitize_text_field(wp_unslash($_POST['curso'])) : '';
        check_admin_referer('ap_comedor_validar_' . $curso);
        if (!self::puede($uid) || !self::es_curso($curso)) {
            wp_die(esc_html__('No puedes poner el comedor de esta clase.', 'area-profesores'));
        }
        $hoy = current_time('Y-m-d');
        $ids = isset($_POST['come']) ? array_map('absint', (array) $_POST['come']) : array();
        $dietas = isset($_POST['dietas']) ? sanitize_textarea_field(wp_unslash($_POST['dietas'])) : '';
        $obs = isset($_POST['observaciones']) ? sanitize_textarea_field(wp_unslash($_POST['observaciones'])) : '';
        $n = self::guardar($hoy, $curso, $ids, $dietas, $obs, $uid);
        $c = Area_Profesores_Cursos::get_curso($curso);
        self::volver($curso, 'success', sprintf(_n('Comedor de %1$s validado: %2$d comensal. ¡Gracias!', 'Comedor de %1$s validado: %2$d comensales. ¡Gracias!', $n, 'area-profesores'), $c['label'], $n));
    }

    public function handle_fijos() {
        $uid = get_current_user_id();
        $curso = isset($_POST['curso']) ? sanitize_text_field(wp_unslash($_POST['curso'])) : '';
        check_admin_referer('ap_comedor_fijos_' . $curso);
        if (!self::puede($uid) || !self::es_curso($curso)) {
            wp_die(esc_html__('No puedes cambiar los comensales de esta clase.', 'area-profesores'));
        }
        $post = isset($_POST['fijos']) && is_array($_POST['fijos']) ? wp_unslash($_POST['fijos']) : array();
        $n = 0;
        foreach (self::alumnos_curso($curso) as $a) {
            $dias = isset($post[$a['id']]) ? (array) $post[$a['id']] : array();
            self::set_fijos($a['id'], $dias);
            $n += $dias ? 1 : 0;
        }
        $c = Area_Profesores_Cursos::get_curso($curso);
        self::volver($curso, 'success', sprintf(__('Comensales fijos de %1$s guardados (%2$d alumnos). Recuerda validar el comedor de hoy.', 'area-profesores'), $c['label'], $n));
    }

    /* ---------------------------------------------------------------------
     * Avisos automáticos (cron)
     * ------------------------------------------------------------------- */

    public function tick() {
        $hoy = current_time('Y-m-d');
        if (!self::es_lectivo($hoy)) {
            return;
        }
        $aj = self::ajustes();
        $ahora = current_time('H:i');
        $env = get_option(self::ENVIADOS, array());
        if (!is_array($env) || !isset($env['fecha']) || $env['fecha'] !== $hoy) {
            $env = array('fecha' => $hoy, 'recordatorio' => false, 'cocina' => false);
        }

        if (!$env['recordatorio'] && $aj['recordatorio'] === 'yes' && $ahora >= $aj['hora_recordatorio'] && $ahora < $aj['hora_cocina']) {
            $env['recordatorio'] = true;
            update_option(self::ENVIADOS, $env, false);
            self::enviar_recordatorios($hoy);
        } elseif (!$env['recordatorio'] && $ahora >= $aj['hora_cocina']) {
            $env['recordatorio'] = true; // too late for the reminder
        }

        if (!$env['cocina'] && $aj['resumen'] === 'yes' && $ahora >= $aj['hora_cocina']) {
            $env['cocina'] = true;
            update_option(self::ENVIADOS, $env, false);
            self::enviar_resumen_cocina($hoy);
        }
        update_option(self::ENVIADOS, $env, false);
    }

    /**
     * E-mail to the tutors of the classes that are not validated yet.
     *
     * @return int e-mails sent
     */
    public static function enviar_recordatorios($fecha) {
        $aj = self::ajustes();
        $n = 0;
        foreach (self::estados($fecha) as $k => $e) {
            if ($e['registro'] || !$e['alumnos']) {
                continue;
            }
            foreach ($e['tutores'] as $u) {
                if (Area_Profesores_Correos::enviar('comedor_recordatorio', $u->user_email, array(
                    'nombre' => $u->display_name,
                    'clase'  => $e['label'],
                    'hora'   => $aj['hora_cocina'],
                    'enlace' => self::url_portal($k),
                ))) {
                    $n++;
                }
            }
        }
        return $n;
    }

    /**
     * Text summary of a day (for the kitchen).
     */
    public static function resumen($fecha) {
        $clases = array();
        $pendientes = array();
        $dietas = array();
        $total = 0;
        foreach (self::estados($fecha) as $k => $e) {
            $r = $e['registro'];
            if (!$r) {
                $pendientes[] = '- ' . $e['label'];
                continue;
            }
            $total += $r['total'];
            $n = array('extra' => 0, 'familia' => 0);
            foreach ($r['alumnos'] as $a) {
                if (isset($n[$a['tipo']])) {
                    $n[$a['tipo']]++;
                }
                if (!empty($a['dieta'])) {
                    $dietas[] = '- ' . $e['label'] . ' · ' . $a['nombre'] . ': ' . $a['dieta'] . ' ' . __('(reserva de la familia)', 'area-profesores');
                }
            }
            $extra = array();
            if ($n['extra']) {
                $extra[] = sprintf(_n('%d extra', '%d extra', $n['extra'], 'area-profesores'), $n['extra']);
            }
            if ($n['familia']) {
                $extra[] = sprintf(_n('%d reservado por la familia', '%d reservados por la familia', $n['familia'], 'area-profesores'), $n['familia']);
            }
            $clases[] = '- ' . $e['label'] . ': ' . $r['total'] . ($extra ? ' (' . implode(', ', $extra) . ')' : '');
            if (trim((string) $r['dietas']) !== '') {
                $dietas[] = '- ' . $e['label'] . ': ' . str_replace("\n", ' · ', trim($r['dietas']));
            }
            if (trim((string) $r['observaciones']) !== '') {
                $dietas[] = '- ' . $e['label'] . ' (' . __('observaciones', 'area-profesores') . '): ' . str_replace("\n", ' · ', trim($r['observaciones']));
            }
        }
        return array('total' => $total, 'clases' => $clases, 'pendientes' => $pendientes, 'dietas' => $dietas, 'alergias' => self::alergias_texto($fecha));
    }

    /**
     * Food allergies of a date as text (kitchen e-mail). '' when there are none.
     */
    public static function alergias_texto($fecha) {
        $al = self::alergias_dia($fecha);
        $l = array();
        if ($al['no_comen']) {
            $l[] = __('HOY NO COMEN (no preparar su menú especial):', 'area-profesores');
            foreach ($al['no_comen'] as $a) {
                $l[] = '- ' . $a['clase'] . ' · ' . $a['nombre'] . ': ' . $a['alergia'];
            }
        }
        if ($al['comen']) {
            if ($l) {
                $l[] = '';
            }
            $l[] = __('Comen hoy con alergia alimentaria:', 'area-profesores');
            foreach ($al['comen'] as $a) {
                $l[] = '- ' . $a['clase'] . ' · ' . $a['nombre'] . ': ' . $a['alergia'];
            }
        }
        return implode("\n", $l);
    }

    public static function enviar_resumen_cocina($fecha) {
        $r = self::resumen($fecha);
        $alergias = $r['alergias'] !== '' ? $r['alergias'] : __('Ningún comensal validado tiene alergias alimentarias.', 'area-profesores');
        return Area_Profesores_Correos::enviar('comedor_cocina', Area_Profesores_Cuentas::emails('cocina'), self::con_alergias('comedor_cocina', array(
            'alergias'   => $alergias,
            'fecha'      => date_i18n('l j \d\e F', strtotime($fecha . ' 12:00:00')),
            'total'      => $r['total'],
            'clases'     => $r['clases'] ? implode("\n", $r['clases']) : __('Ninguna clase validada.', 'area-profesores'),
            'pendientes' => $r['pendientes'] ? implode("\n", $r['pendientes']) : __('Ninguna: todas las clases están validadas.', 'area-profesores'),
            'dietas'     => $r['dietas'] ? implode("\n", $r['dietas']) : __('Ninguna.', 'area-profesores'),
            'enlace'     => self::url_cocina(),
        ), $r['alergias'] !== '' ? '⚠️ ' . __('ALERGIAS ALIMENTARIAS', 'area-profesores') . "\n" . $r['alergias'] : ''));
    }

    /**
     * Live status for the kitchen page (polled every minute).
     */
    public function ajax_estado() {
        $fecha = isset($_GET['fecha']) ? sanitize_text_field(wp_unslash($_GET['fecha'])) : current_time('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = current_time('Y-m-d');
        }
        global $wpdb;
        $firma = wp_json_encode($wpdb->get_results($wpdb->prepare('SELECT curso, total, validado FROM ' . self::t() . ' WHERE fecha = %s ORDER BY curso', $fecha), ARRAY_N));
        $firma .= '|' . wp_json_encode($wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Area_Profesores_Reservas::t() . ' WHERE fecha_inicio <= %s AND fecha_fin >= %s AND estado = %s ORDER BY id', $fecha, $fecha, 'activa')));
        nocache_headers();
        wp_send_json_success(array('firma' => md5($firma)));
    }

    /* ---------------------------------------------------------------------
     * Secretaría: comensales del mes
     * ------------------------------------------------------------------- */

    /**
     * @param string $mes Y-m
     * @return array { dias: [fecha => {total, extra, familia, clases}], extras: [alumno_id => {nombre, curso, dias:[fecha => tipo]}], total, total_extra }
     */
    public static function mes($mes) {
        global $wpdb;
        $ini = $mes . '-01';
        $fin = gmdate('Y-m-t', strtotime($ini . ' 12:00:00 UTC'));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE fecha >= %s AND fecha <= %s ORDER BY fecha ASC', $ini, $fin), ARRAY_A);
        $dias = array();
        $extras = array();
        $total = 0;
        $total_extra = 0;
        foreach ((array) $rows as $r) {
            $r = self::fila($r);
            if (!self::es_curso($r['curso'])) {
                continue;
            }
            $f = $r['fecha'];
            if (!isset($dias[$f])) {
                $dias[$f] = array('total' => 0, 'extra' => 0, 'familia' => 0, 'clases' => 0);
            }
            $dias[$f]['total'] += $r['total'];
            $dias[$f]['clases']++;
            $total += $r['total'];
            foreach ($r['alumnos'] as $a) {
                if ($a['tipo'] !== 'extra' && $a['tipo'] !== 'familia') {
                    continue;
                }
                $dias[$f][$a['tipo']]++;
                $total_extra++;
                $id = (int) $a['id'];
                if (!isset($extras[$id])) {
                    $c = Area_Profesores_Cursos::get_curso($r['curso']);
                    $extras[$id] = array('nombre' => $a['nombre'], 'curso' => $c ? $c['label'] : $r['curso'], 'orden' => $c ? $c['orden'] : 999, 'dias' => array());
                }
                $extras[$id]['dias'][$f] = $a['tipo'];
            }
        }
        uasort($extras, function ($a, $b) {
            return array($a['orden'], $a['nombre']) <=> array($b['orden'], $b['nombre']);
        });
        return array('dias' => $dias, 'extras' => $extras, 'total' => $total, 'total_extra' => $total_extra);
    }

    public function handle_csv_mes() {
        if (!self::puede_ver_mes(get_current_user_id())) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_comedor_csv_mes');
        $mes = isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes']) ? sanitize_text_field(wp_unslash($_GET['mes'])) : current_time('Y-m');
        $d = self::mes($mes);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="comidas-extra-' . $mes . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('Alumno', 'Curso', 'Comidas extra', 'De ellas, reservadas por la familia', 'Días'), ';');
        foreach ($d['extras'] as $e) {
            fputcsv($out, array($e['nombre'], $e['curso'], count($e['dias']), count(array_filter($e['dias'], function ($t) {
                return $t === 'familia';
            })), implode(', ', array_map(function ($f) {
                return gmdate('j/n', strtotime($f . ' 12:00:00 UTC'));
            }, array_keys($e['dias'])))), ';');
        }
        fputcsv($out, array(), ';');
        fputcsv($out, array('Día', 'Comensales', 'Extra', 'Reservas de familias', 'Clases validadas'), ';');
        foreach ($d['dias'] as $f => $x) {
            fputcsv($out, array($f, $x['total'], $x['extra'], $x['familia'], $x['clases']), ';');
        }
        fclose($out);
        exit;
    }

    /* ---------------------------------------------------------------------
     * Ajustes (Área Profesores › Comedor Diario)
     * ------------------------------------------------------------------- */

    private static function hora($v, $def) {
        $v = substr(sanitize_text_field((string) $v), 0, 5);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : $def;
    }

    public function handle_ajustes() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_comedor_ajustes');
        $p = wp_unslash($_POST);
        update_option(self::OPTION, array(
            'hora_recordatorio' => self::hora(isset($p['hora_recordatorio']) ? $p['hora_recordatorio'] : '', '10:30'),
            'hora_cocina'       => self::hora(isset($p['hora_cocina']) ? $p['hora_cocina'] : '', '12:00'),
            'recordatorio'      => !empty($p['recordatorio']) ? 'yes' : 'no',
            'resumen'           => !empty($p['resumen']) ? 'yes' : 'no',
            'dias_sin_clase'    => isset($p['dias_sin_clase']) ? sanitize_textarea_field($p['dias_sin_clase']) : '',
        ), false);
        $msg = __('Ajustes del comedor guardados.', 'area-profesores');
        if (!empty($p['probar']) && in_array($p['probar'], array('recordatorio', 'cocina'), true)) {
            $hoy = current_time('Y-m-d');
            $msg = $p['probar'] === 'cocina'
                ? (self::enviar_resumen_cocina($hoy) ? __('Resumen de hoy enviado a Cocina.', 'area-profesores') : __('No se ha enviado: no hay cuentas de Cocina en "Cuentas especiales" o el correo está desactivado.', 'area-profesores'))
                : sprintf(__('Recordatorios enviados: %d.', 'area-profesores'), self::enviar_recordatorios($hoy));
        }
        wp_safe_redirect(add_query_arg('ap_msg', rawurlencode($msg), admin_url('admin.php?page=area-profesores-comedor')));
        exit;
    }
}
