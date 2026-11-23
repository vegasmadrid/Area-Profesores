<?php
/**
 * Reservas de servicios hechas por las familias desde el Área Familias.
 *
 * - The families site sends each reservation through the API (one row per
 *   child): service, dates (one day or a range with weekdays), time and the
 *   details the family filled in (desayuno, dieta, motivo…). The services and
 *   their rules (schedules, deadlines…) are configured in the Área Familias.
 * - Notifications (Avisos): the tutor/a of the child and
 *   Secretaría, and a confirmation to the family. The same when it is cancelled.
 * - Teachers see the reservations of their pupils in the "Reservas" tab;
 *   Secretaría and Dirección see all of them, and the backend page
 *   "Área Profesores › Reservas de familias" lists them by day (printable) and
 *   exports them to CSV. The kitchen view ([comedorhoy]) shows the comedor and
 *   diet reservations of the day.
 * - Portería can book any service for any family from its "Reservas" tab
 *   (parents often ask in person or by phone). The families site sends its
 *   service settings (POST /servicios) so both forms follow the same rules.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Reservas {

    const SLUG = 'area-profesores-reservas';
    const OPT_SERVICIOS = 'ap_reservas_servicios';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_menu', array($this, 'register_menu'), 25);
        add_action('admin_post_ap_reserva_anular', array($this, 'handle_anular_admin'));
        add_action('admin_post_ap_reservas_csv', array($this, 'handle_csv'));
        add_action('admin_post_ap_reserva_porteria', array($this, 'handle_porteria'));
        add_action('admin_post_ap_reserva_anular_portal', array($this, 'handle_anular_portal'));
    }

    public static function t() {
        global $wpdb;
        return $wpdb->prefix . 'ap_reservas';
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE " . self::t() . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            grupo VARCHAR(40) NOT NULL DEFAULT '',
            alumno_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            familia_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            alumno VARCHAR(200) NOT NULL DEFAULT '',
            curso VARCHAR(20) NOT NULL DEFAULT '',
            familia VARCHAR(200) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            servicio VARCHAR(40) NOT NULL DEFAULT '',
            servicio_nombre VARCHAR(120) NOT NULL DEFAULT '',
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            dias VARCHAR(20) NOT NULL DEFAULT '',
            hora VARCHAR(5) NOT NULL DEFAULT '',
            detalles LONGTEXT DEFAULT NULL,
            avisar TEXT DEFAULT NULL,
            estado VARCHAR(10) NOT NULL DEFAULT 'activa',
            creado DATETIME DEFAULT NULL,
            anulado DATETIME DEFAULT NULL,
            anulado_por VARCHAR(20) NOT NULL DEFAULT '',
            creado_por BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY fechas (estado, fecha_inicio, fecha_fin),
            KEY alumno (alumno_id),
            KEY email (email)
        ) " . $wpdb->get_charset_collate() . ";");
    }

    /* ---------------------------------------------------------------------
     * Datos
     * ------------------------------------------------------------------- */

    /**
     * Normalise a row from the database.
     */
    private static function fila($r) {
        $r = (array) $r;
        $r['id'] = (int) $r['id'];
        $r['alumno_id'] = (int) $r['alumno_id'];
        $r['familia_id'] = isset($r['familia_id']) ? (int) $r['familia_id'] : 0;
        $r['creado_por'] = isset($r['creado_por']) ? (int) $r['creado_por'] : 0;
        $r['dias'] = $r['dias'] === '' ? array() : array_map('intval', explode(',', $r['dias']));
        $d = json_decode((string) $r['detalles'], true);
        $r['detalles'] = is_array($d) ? $d : array();
        $r['avisar'] = $r['avisar'] ? Area_Profesores_Alumnos::emails_array($r['avisar']) : array();
        $c = Area_Profesores_Cursos::get_curso($r['curso']);
        $r['curso_label'] = $c ? $c['label'] : $r['curso'];
        $r['fechas_texto'] = self::fechas_texto($r);
        return $r;
    }

    public static function get($id) {
        global $wpdb;
        $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE id = %d', $id), ARRAY_A);
        return $r ? self::fila($r) : null;
    }

    /**
     * Whether a reservation applies on a date (Y-m-d).
     */
    public static function aplica($r, $fecha) {
        if ($fecha < $r['fecha_inicio'] || $fecha > $r['fecha_fin']) {
            return false;
        }
        return !$r['dias'] || in_array((int) gmdate('N', strtotime($fecha . ' 12:00:00 UTC')), $r['dias'], true);
    }

    /**
     * Active reservations that apply on a date.
     *
     * @param string     $fecha  Y-m-d
     * @param array|true $cursos course keys or true = all
     */
    public static function del_dia($fecha, $cursos = true, $servicio = '') {
        global $wpdb;
        $sql = $wpdb->prepare('SELECT * FROM ' . self::t() . " WHERE estado = 'activa' AND fecha_inicio <= %s AND fecha_fin >= %s", $fecha, $fecha);
        if ($servicio !== '') {
            $sql .= $wpdb->prepare(' AND servicio = %s', $servicio);
        }
        $out = array();
        foreach ((array) $wpdb->get_results($sql, ARRAY_A) as $r) {
            $r = self::fila($r);
            if ($cursos !== true && !in_array($r['curso'], (array) $cursos, true)) {
                continue;
            }
            if (self::aplica($r, $fecha)) {
                $out[] = $r;
            }
        }
        return self::ordenar($out);
    }

    /**
     * Sort by service, course and pupil.
     */
    private static function ordenar($rows) {
        $orden = array_flip(array_keys(Area_Profesores_Cursos::get_cursos()));
        usort($rows, function ($a, $b) use ($orden) {
            $ka = array($a['servicio_nombre'], isset($orden[$a['curso']]) ? $orden[$a['curso']] : 999, $a['hora'], $a['alumno']);
            $kb = array($b['servicio_nombre'], isset($orden[$b['curso']]) ? $orden[$b['curso']] : 999, $b['hora'], $b['alumno']);
            return $ka <=> $kb;
        });
        return $rows;
    }

    /**
     * Reservations made by a family account (recent and future ones).
     */
    public static function de_cuenta($email) {
        global $wpdb;
        $desde = gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' -60 days'));
        // Also the ones the school (Portería) made for the family.
        $fams = array_map('intval', wp_list_pluck(Area_Profesores_Alumnos::familias_por_email($email), 'id'));
        $o = $fams ? ' OR (creado_por > 0 AND familia_id IN (' . implode(',', $fams) . '))' : '';
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE (email = %s' . $o . ') AND fecha_fin >= %s ORDER BY fecha_inicio ASC, id ASC', $email, $desde), ARRAY_A);
        return array_map(array(__CLASS__, 'fila'), (array) $rows);
    }

    /**
     * Search for the backend list.
     */
    public static function buscar($args) {
        global $wpdb;
        $where = array('1=1');
        if (!empty($args['estado'])) {
            $where[] = $wpdb->prepare('estado = %s', $args['estado']);
        }
        if (!empty($args['servicio'])) {
            $where[] = $wpdb->prepare('servicio = %s', $args['servicio']);
        }
        if (!empty($args['curso'])) {
            $where[] = $wpdb->prepare('curso = %s', $args['curso']);
        }
        if (!empty($args['q'])) {
            $like = '%' . $wpdb->esc_like($args['q']) . '%';
            $where[] = $wpdb->prepare('(alumno LIKE %s OR familia LIKE %s OR email LIKE %s)', $like, $like, $like);
        }
        if (!empty($args['desde'])) {
            $where[] = $wpdb->prepare('fecha_fin >= %s', $args['desde']);
        }
        $limit = isset($args['limit']) ? absint($args['limit']) : 300;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::t() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY creado DESC, id DESC' . ($limit ? ' LIMIT ' . $limit : ''), ARRAY_A);
        return array_map(array(__CLASS__, 'fila'), (array) $rows);
    }

    /**
     * Services that appear in the reservations (key => name), for the filters.
     */
    public static function servicios_usados() {
        global $wpdb;
        $out = array();
        foreach ((array) $wpdb->get_results('SELECT servicio, MAX(servicio_nombre) AS nombre FROM ' . self::t() . ' GROUP BY servicio', ARRAY_A) as $r) {
            $out[$r['servicio']] = $r['nombre'];
        }
        asort($out);
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Textos
     * ------------------------------------------------------------------- */

    private static function fecha_larga($ymd) {
        return date_i18n('l j \d\e F \d\e Y', strtotime($ymd . ' 12:00:00'));
    }

    public static function dias_semana() {
        return array(1 => __('lunes', 'area-profesores'), 2 => __('martes', 'area-profesores'), 3 => __('miércoles', 'area-profesores'), 4 => __('jueves', 'area-profesores'), 5 => __('viernes', 'area-profesores'), 6 => __('sábado', 'area-profesores'), 7 => __('domingo', 'area-profesores'));
    }

    /**
     * "Martes 25 de noviembre de 2026" / "Del lunes 1 … al viernes 19 … (lunes y miércoles)".
     */
    public static function fechas_texto($r) {
        $hora = $r['hora'] !== '' ? ' · ' . sprintf(__('a las %s', 'area-profesores'), $r['hora']) : '';
        if ($r['fecha_inicio'] === $r['fecha_fin']) {
            return ucfirst(self::fecha_larga($r['fecha_inicio'])) . $hora;
        }
        $txt = sprintf(__('Del %1$s al %2$s', 'area-profesores'), self::fecha_larga($r['fecha_inicio']), self::fecha_larga($r['fecha_fin']));
        $dias = (array) $r['dias'];
        if ($dias && count($dias) < 5) {
            $n = self::dias_semana();
            $txt .= ' (' . sprintf(__('solo %s', 'area-profesores'), implode(', ', array_map(function ($d) use ($n) {
                return isset($n[$d]) ? $n[$d] : $d;
            }, $dias))) . ')';
        }
        return $txt . $hora;
    }

    /**
     * Details as text lines ("Desayuno: Sí").
     */
    public static function detalles_texto($r) {
        $l = array();
        foreach ((array) $r['detalles'] as $d) {
            if (isset($d['label'], $d['valor']) && trim((string) $d['valor']) !== '') {
                $l[] = $d['label'] . ': ' . $d['valor'];
            }
        }
        return implode("\n", $l);
    }

    /* ---------------------------------------------------------------------
     * API (Área Familias)
     * ------------------------------------------------------------------- */

    public function register_routes() {
        $api = Area_Profesores_API::get_instance();
        register_rest_route(Area_Profesores_API::NS, '/familia/reservas', array(
            array('methods' => 'GET', 'callback' => array($this, 'api_listar'), 'permission_callback' => array($api, 'check_key')),
            array('methods' => 'POST', 'callback' => array($this, 'api_crear'), 'permission_callback' => array($api, 'check_key')),
        ));
        register_rest_route(Area_Profesores_API::NS, '/servicios', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'api_servicios'),
            'permission_callback' => array($api, 'check_key'),
        ));
        register_rest_route(Area_Profesores_API::NS, '/familia/reservas/anular', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'api_anular'),
            'permission_callback' => array($api, 'check_key'),
        ));
    }

    /**
     * For the families site: the fields it needs.
     */
    private static function para_familia($r) {
        return array(
            'id'              => $r['id'],
            'grupo'           => $r['grupo'],
            'alumno_id'       => $r['alumno_id'],
            'alumno'          => $r['alumno'],
            'curso_label'     => $r['curso_label'],
            'servicio'        => $r['servicio'],
            'servicio_nombre' => $r['servicio_nombre'],
            'fecha_inicio'    => $r['fecha_inicio'],
            'fecha_fin'       => $r['fecha_fin'],
            'dias'            => $r['dias'],
            'hora'            => $r['hora'],
            'detalles'        => $r['detalles'],
            'fechas_texto'    => $r['fechas_texto'],
            'estado'          => $r['estado'],
            'creado'          => $r['creado'],
            'anulado_por'     => $r['anulado_por'],
        );
    }

    public function api_listar(WP_REST_Request $request) {
        $email = Area_Profesores_Alumnos::norm_email($request->get_param('email'));
        if ($email === '') {
            return new WP_Error('area_email', __('Falta el correo de la familia.', 'area-profesores'), array('status' => 400));
        }
        $r = rest_ensure_response(array('reservas' => array_map(array(__CLASS__, 'para_familia'), self::de_cuenta($email))));
        $r->header('Cache-Control', 'no-store');
        return $r;
    }

    /**
     * Pupils of the families of an e-mail: id => array(alumno, familia).
     */
    private static function hijos_de_cuenta($email) {
        $A = 'Area_Profesores_Alumnos';
        $familias = $A::familias_por_email($email);
        $out = array();
        if (!$familias) {
            return $out;
        }
        $nombres = wp_list_pluck($familias, 'nombre', 'id');
        foreach ($A::alumnos_de_familias(wp_list_pluck($familias, 'id'), true) as $fid => $lista) {
            foreach ($lista as $a) {
                if (!isset($out[(int) $a['id']])) {
                    $out[(int) $a['id']] = array('alumno' => $a, 'familia' => isset($nombres[$fid]) ? $nombres[$fid] : '', 'familia_id' => (int) $fid);
                }
            }
        }
        return $out;
    }

    private static function fecha_valida($f) {
        $f = (string) $f;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Whether two reservations share at least one day.
     */
    private static function se_solapan($a, $b) {
        $ini = max($a['fecha_inicio'], $b['fecha_inicio']);
        $fin = min($a['fecha_fin'], $b['fecha_fin']);
        for ($t = strtotime($ini . ' 12:00:00 UTC'), $end = strtotime($fin . ' 12:00:00 UTC'), $i = 0; $t <= $end && $i < 400; $t += DAY_IN_SECONDS, $i++) {
            $d = gmdate('Y-m-d', $t);
            if (self::aplica($a, $d) && self::aplica($b, $d)) {
                return true;
            }
        }
        return false;
    }

    /**
     * New reservation (one row per child).
     *
     * Body (JSON): email, alumnos [ids], servicio, servicio_nombre, fecha_inicio,
     * fecha_fin, dias [1-7], hora, detalles [{label, valor}], avisar [e-mails],
     * panel_url, permitir_repetida.
     */
    public function api_crear(WP_REST_Request $request) {
        global $wpdb;
        $A = 'Area_Profesores_Alumnos';
        $email = $A::norm_email($request->get_param('email'));
        $hijos = self::hijos_de_cuenta($email);
        $ids = array_values(array_unique(array_map('absint', (array) $request->get_param('alumnos'))));
        if (!$email || !$ids) {
            return new WP_Error('area_reserva', __('Elige al menos un alumno.', 'area-profesores'), array('status' => 400));
        }
        foreach ($ids as $id) {
            if (!isset($hijos[$id])) {
                return new WP_Error('area_familia', __('Ese alumno no corresponde a esta cuenta.', 'area-profesores'), array('status' => 403));
            }
        }

        $servicio = sanitize_key((string) $request->get_param('servicio'));
        $nombre = mb_substr(sanitize_text_field((string) $request->get_param('servicio_nombre')), 0, 120);
        $ini = (string) $request->get_param('fecha_inicio');
        $fin = (string) $request->get_param('fecha_fin');
        $fin = $fin === '' ? $ini : $fin;
        $hora = (string) $request->get_param('hora');
        if ($servicio === '' || $nombre === '' || !self::fecha_valida($ini) || !self::fecha_valida($fin) || $fin < $ini
            || (strtotime($fin) - strtotime($ini)) > 400 * DAY_IN_SECONDS || ($hora !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora))) {
            return new WP_Error('area_reserva', __('Los datos de la reserva no son válidos.', 'area-profesores'), array('status' => 400));
        }
        if ($fin < current_time('Y-m-d')) {
            return new WP_Error('area_reserva', __('Esa fecha ya ha pasado.', 'area-profesores'), array('status' => 400));
        }
        $dias = array_values(array_unique(array_filter(array_map('intval', (array) $request->get_param('dias')), function ($d) {
            return $d >= 1 && $d <= 7;
        })));
        sort($dias);
        $detalles = array();
        foreach (array_slice((array) $request->get_param('detalles'), 0, 20) as $d) {
            if (is_array($d) && isset($d['label'], $d['valor'])) {
                $detalles[] = array(
                    'label' => mb_substr(sanitize_text_field((string) $d['label']), 0, 100),
                    'valor' => mb_substr(sanitize_textarea_field((string) $d['valor']), 0, 1000),
                );
            }
        }
        $avisar = array_slice($A::emails_array(implode(',', array_map('strval', (array) $request->get_param('avisar')))), 0, 5);

        // Max. 30 reservations per hour and account.
        $limite = 'ap_reservas_n_' . md5($email);
        $n = (int) get_transient($limite);
        if ($n >= 30) {
            return new WP_Error('area_limite', __('Demasiadas reservas seguidas. Inténtalo más tarde.', 'area-profesores'), array('status' => 429));
        }

        $alumnos = array();
        foreach ($ids as $id) {
            $alumnos[$id] = $hijos[$id];
        }
        $creadas = self::crear(array(
            'email'           => $email,
            'servicio'        => $servicio,
            'servicio_nombre' => $nombre,
            'fecha_inicio'    => $ini,
            'fecha_fin'       => $fin,
            'dias'            => $dias,
            'hora'            => $hora,
            'detalles'        => $detalles,
            'avisar'          => $avisar,
        ), $alumnos, array($email), esc_url_raw((string) $request->get_param('panel_url')));
        if (is_wp_error($creadas)) {
            return $creadas;
        }
        set_transient($limite, $n + 1, HOUR_IN_SECONDS);

        return rest_ensure_response(array(
            'ok'       => true,
            'creadas'  => count($creadas),
            'reservas' => array_map(array(__CLASS__, 'para_familia'), self::de_cuenta($email)),
        ));
    }

    /**
     * Save a reservation (one row per child), tell the school and confirm it to the family.
     *
     * @param array  $d       email, servicio, servicio_nombre, fecha_inicio, fecha_fin, dias, hora, detalles, avisar
     * @param array  $alumnos alumno_id => { alumno, familia, familia_id }
     * @param array  $para    e-mails of the family for the confirmation
     * @param string $panel   families panel URL (button of the confirmation)
     * @param int    $por     user who made it at school (0 = the family)
     * @return array|WP_Error created reservations
     */
    public static function crear($d, $alumnos, $para, $panel = '', $por = 0) {
        global $wpdb;
        $nueva = array('fecha_inicio' => $d['fecha_inicio'], 'fecha_fin' => $d['fecha_fin'], 'dias' => $d['dias']);

        // The same child cannot have the same service twice on the same day.
        foreach ($alumnos as $id => $x) {
            $existentes = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . " WHERE estado = 'activa' AND alumno_id = %d AND servicio = %s AND fecha_inicio <= %s AND fecha_fin >= %s", $id, $d['servicio'], $d['fecha_fin'], $d['fecha_inicio']), ARRAY_A);
            foreach ((array) $existentes as $e) {
                $e = self::fila($e);
                if (self::se_solapan($e, $nueva)) {
                    return new WP_Error('area_repetida', sprintf(__('%1$s ya tiene una reserva de %2$s para esos días (%3$s). Si quieres cambiarla, anúlala primero.', 'area-profesores'), $x['alumno']['nombre'], $d['servicio_nombre'], $e['fechas_texto']), array('status' => 409));
                }
            }
        }

        $grupo = wp_generate_uuid4();
        $creadas = array();
        foreach ($alumnos as $id => $x) {
            $a = $x['alumno'];
            $wpdb->insert(self::t(), array(
                'grupo'           => $grupo,
                'alumno_id'       => $id,
                'familia_id'      => isset($x['familia_id']) ? (int) $x['familia_id'] : 0,
                'alumno'          => $a['nombre'],
                'curso'           => $a['curso'],
                'familia'         => $x['familia'],
                'email'           => $d['email'],
                'servicio'        => $d['servicio'],
                'servicio_nombre' => $d['servicio_nombre'],
                'fecha_inicio'    => $d['fecha_inicio'],
                'fecha_fin'       => $d['fecha_fin'],
                'dias'            => implode(',', $d['dias']),
                'hora'            => $d['hora'],
                'detalles'        => wp_json_encode($d['detalles']),
                'avisar'          => implode(', ', $d['avisar']),
                'estado'          => 'activa',
                'creado'          => Area_Profesores_Alumnos::now(),
                'creado_por'      => (int) $por,
            ));
            $r = self::get((int) $wpdb->insert_id);
            if ($r) {
                $creadas[] = $r;
                self::avisar_colegio('reserva_nueva', $r);
                if (class_exists('Area_Profesores_Comensales')) {
                    Area_Profesores_Comensales::on_reserva($r);
                }
            }
        }

        if ($creadas && $para) {
            Area_Profesores_Correos::enviar('reserva_familia', $para, array(
                'servicio' => $d['servicio_nombre'],
                'alumnos'  => implode(', ', array_map(function ($r) {
                    return $r['alumno'] . ($r['curso_label'] !== '' ? ' (' . $r['curso_label'] . ')' : '');
                }, $creadas)),
                'fechas'   => $creadas[0]['fechas_texto'],
                'detalles' => self::detalles_texto($creadas[0]),
                'enlace'   => $panel,
            ));
        }
        return $creadas;
    }

    /**
     * The family cancels a reservation (its deadline is checked by the families site).
     * Body: email, id, panel_url.
     */
    public function api_anular(WP_REST_Request $request) {
        $email = Area_Profesores_Alumnos::norm_email($request->get_param('email'));
        $r = self::get(absint($request->get_param('id')));
        $suya = $r && $email && ($r['email'] === $email || ($r['creado_por'] && in_array($r['familia_id'], array_map('intval', wp_list_pluck(Area_Profesores_Alumnos::familias_por_email($email), 'id')), true)));
        if (!$suya) {
            return new WP_Error('area_familia', __('Esa reserva no corresponde a esta cuenta.', 'area-profesores'), array('status' => 403));
        }
        if ($r['estado'] !== 'activa') {
            return new WP_Error('area_reserva', __('Esa reserva ya estaba anulada.', 'area-profesores'), array('status' => 400));
        }
        self::anular($r, 'familia', esc_url_raw((string) $request->get_param('panel_url')));
        return rest_ensure_response(array(
            'ok'       => true,
            'reservas' => array_map(array(__CLASS__, 'para_familia'), self::de_cuenta($email)),
        ));
    }

    /**
     * Cancel and notify the school and the family.
     */
    public static function anular($r, $por, $panel_url = '') {
        global $wpdb;
        $wpdb->update(self::t(), array('estado' => 'anulada', 'anulado' => Area_Profesores_Alumnos::now(), 'anulado_por' => $por), array('id' => $r['id']));
        $r['estado'] = 'anulada';
        $r['anulado_por'] = $por;
        self::avisar_colegio('reserva_anulada', $r);
        if (class_exists('Area_Profesores_Comensales')) {
            Area_Profesores_Comensales::on_reserva($r);
        }
        Area_Profesores_Correos::enviar('reserva_anulada_familia', $r['email'], array(
            'servicio' => $r['servicio_nombre'],
            'alumnos'  => $r['alumno'] . ($r['curso_label'] !== '' ? ' (' . $r['curso_label'] . ')' : ''),
            'fechas'   => $r['fechas_texto'],
            'quien'    => $por === 'familia' ? __('la propia familia', 'area-profesores') : __('el colegio', 'area-profesores'),
            'enlace'   => $panel_url,
        ));
    }

    /**
     * E-mail to the tutor/a of the child, Secretaría and the extra addresses of the service.
     */
    private static function avisar_colegio($key, $r) {
        $A = 'Area_Profesores_Alumnos';
        $conf = Area_Profesores_Correos::get($key);
        $para = $r['avisar'];
        if ($conf['tutores']) {
            $para = array_merge($para, $A::emails_tutores($r['curso']));
        }
        if (!empty($conf['cocina']) && in_array($r['servicio'], array('comedor', 'dieta'), true) && class_exists('Area_Profesores_Cuentas')) {
            $para = array_merge($para, Area_Profesores_Cuentas::emails('cocina'));
        }
        Area_Profesores_Correos::enviar($key, $para, array(
            'servicio' => $r['servicio_nombre'],
            'alumno'   => $r['alumno'],
            'curso'    => $r['curso_label'],
            'familia'  => $r['familia'],
            'correo'   => $r['email'],
            'fechas'   => $r['fechas_texto'],
            'detalles' => self::detalles_texto($r),
            'quien'    => $r['anulado_por'] === 'colegio' ? __('el colegio', 'area-profesores') : __('la familia', 'area-profesores'),
            'enlace'   => self::url_portal(),
        ), false, $r['email']);
    }

    /**
     * "Reservas" tab of the teachers' area.
     */
    public static function url_portal() {
        $id = absint(Area_Profesores_Admin::get_setting('area_page_id', 0));
        return ($id && get_post_status($id) === 'publish') ? get_permalink($id) . '#reservas' : home_url('/');
    }

    /* ---------------------------------------------------------------------
     * Servicios (configurados en el Área Familias) y reservas de Portería
     * ------------------------------------------------------------------- */

    /**
     * Services as the Área Familias defines them by default (used until it sends its settings).
     */
    private static function servicios_defecto() {
        $base = array('activo' => 'yes', 'hora_inicio' => '', 'hora_fin' => '', 'precio' => '', 'varios_dias' => 'yes', 'etapas' => array(), 'avisar' => '', 'desayuno' => 'no', 'dieta' => 'no', 'recoge' => 'no', 'tipos_dieta' => '');
        return array(
            'ampliacion' => array_merge($base, array('nombre' => __('Ampliación de horario', 'area-profesores'), 'icono' => '☀️', 'hora_inicio' => '07:30', 'hora_fin' => '09:45', 'desayuno' => 'yes')),
            'comedor'    => array_merge($base, array('nombre' => __('Comedor', 'area-profesores'), 'icono' => '🍽️', 'dieta' => 'yes')),
            'ludoteca'   => array_merge($base, array('nombre' => __('Ludoteca', 'area-profesores'), 'icono' => '🧩', 'hora_inicio' => '16:30', 'hora_fin' => '18:00')),
            'dieta'      => array_merge($base, array('nombre' => __('Dieta', 'area-profesores'), 'icono' => '🥗', 'tipos_dieta' => "Dieta blanda\nSin lactosa\nSin gluten\nOtra (explícala en las instrucciones)")),
            'salida'     => array_merge($base, array('nombre' => __('Salida anticipada', 'area-profesores'), 'icono' => '🚪', 'hora_inicio' => '09:00', 'hora_fin' => '17:00', 'varios_dias' => 'no', 'recoge' => 'yes')),
            'retraso'    => array_merge($base, array('nombre' => __('Retraso justificado', 'area-profesores'), 'icono' => '⏰', 'hora_inicio' => '09:00', 'hora_fin' => '14:00', 'varios_dias' => 'no')),
        );
    }

    /**
     * Active services (key => settings): the ones the Área Familias sent, or the defaults.
     */
    public static function servicios() {
        $o = get_option(self::OPT_SERVICIOS, array());
        $guardados = is_array($o) && !empty($o['servicios']) && is_array($o['servicios']) ? $o['servicios'] : null;
        $out = array();
        foreach (self::servicios_defecto() as $k => $d) {
            $s = $guardados !== null ? (isset($guardados[$k]) && is_array($guardados[$k]) ? array_merge($d, array_intersect_key($guardados[$k], $d)) : null) : $d;
            if ($s && $s['activo'] === 'yes') {
                $s['key'] = $k;
                $out[$k] = $s;
            }
        }
        return $out;
    }

    /** Days without school configured in the Área Familias (Y-m-d => true). */
    private static function festivos_familias() {
        $o = get_option(self::OPT_SERVICIOS, array());
        return is_array($o) && !empty($o['festivos']) && is_array($o['festivos']) ? array_fill_keys($o['festivos'], true) : array();
    }

    public static function es_lectivo($f) {
        return Area_Profesores_Comensales::es_lectivo($f) && !isset(self::festivos_familias()[$f]);
    }

    /**
     * POST /servicios: settings of the services and days without school, sent by the Área Familias.
     */
    public function api_servicios(WP_REST_Request $request) {
        $txt = function ($v, $max = 200) {
            return mb_substr(sanitize_text_field((string) $v), 0, $max);
        };
        $hora = function ($v) {
            $v = substr((string) $v, 0, 5);
            return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : '';
        };
        $yn = function ($v) {
            return $v === 'yes' ? 'yes' : 'no';
        };
        $out = array();
        $def = self::servicios_defecto();
        foreach ((array) $request->get_param('servicios') as $k => $x) {
            $k = sanitize_key($k);
            if (!isset($def[$k]) || !is_array($x)) {
                continue;
            }
            $out[$k] = array(
                'activo'      => $yn(isset($x['activo']) ? $x['activo'] : ''),
                'nombre'      => isset($x['nombre']) && trim((string) $x['nombre']) !== '' ? $txt($x['nombre'], 120) : $def[$k]['nombre'],
                'icono'       => isset($x['icono']) ? $txt($x['icono'], 10) : $def[$k]['icono'],
                'hora_inicio' => $hora(isset($x['hora_inicio']) ? $x['hora_inicio'] : ''),
                'hora_fin'    => $hora(isset($x['hora_fin']) ? $x['hora_fin'] : ''),
                'precio'      => isset($x['precio']) ? $txt($x['precio'], 120) : '',
                'varios_dias' => $yn(isset($x['varios_dias']) ? $x['varios_dias'] : ''),
                'etapas'      => array_values(array_map('sanitize_key', isset($x['etapas']) ? (array) $x['etapas'] : array())),
                'avisar'      => implode(', ', Area_Profesores_Alumnos::emails_array(isset($x['avisar']) ? (string) $x['avisar'] : '')),
                'desayuno'    => $yn(isset($x['desayuno']) ? $x['desayuno'] : ''),
                'dieta'       => $yn(isset($x['dieta']) ? $x['dieta'] : ''),
                'recoge'      => $yn(isset($x['recoge']) ? $x['recoge'] : ''),
                'tipos_dieta' => isset($x['tipos_dieta']) ? mb_substr(sanitize_textarea_field((string) $x['tipos_dieta']), 0, 1000) : '',
            );
        }
        $festivos = array_values(array_filter(array_map('strval', (array) $request->get_param('festivos')), array(__CLASS__, 'fecha_valida')));
        update_option(self::OPT_SERVICIOS, array('servicios' => $out, 'festivos' => array_slice($festivos, 0, 400), 'recibido' => current_time('mysql')), false);
        return rest_ensure_response(array('ok' => true, 'servicios' => count($out)));
    }

    /** Portería (and administrators) can book any service for any family. */
    public static function puede_reservar($user_id) {
        return $user_id && (user_can($user_id, 'manage_options') || Area_Profesores_Cuentas::tiene($user_id, 'porteria'));
    }

    /**
     * Families with their children (for the Portería form).
     *
     * @return array[] { id, nombre, emails, hijos: [ {id, nombre, curso, etapa} ] }
     */
    public static function familias_para_reservar() {
        global $wpdb;
        $A = 'Area_Profesores_Alumnos';
        $fams = $wpdb->get_results('SELECT id, nombre, emails FROM ' . $A::t('familias') . ' ORDER BY nombre', ARRAY_A);
        $hijos = $A::alumnos_de_familias(wp_list_pluck($fams, 'id'), true);
        $out = array();
        foreach ((array) $fams as $f) {
            if (empty($hijos[$f['id']])) {
                continue;
            }
            $l = array();
            foreach ($hijos[$f['id']] as $a) {
                $c = Area_Profesores_Cursos::get_curso($a['curso']);
                $l[] = array('id' => (int) $a['id'], 'nombre' => $a['nombre'], 'curso' => $c ? $c['label'] : $a['curso'], 'etapa' => $c ? $c['etapa'] : '');
            }
            $out[] = array('id' => (int) $f['id'], 'nombre' => $f['nombre'], 'emails' => $A::emails_array($f['emails']), 'hijos' => $l);
        }
        return $out;
    }

    private static function volver_portal($type, $text, $fecha = '', $old = null) {
        set_transient('ap_res_flash_' . get_current_user_id(), array('type' => $type, 'text' => $text, 'old' => $old), 300);
        $back = wp_get_referer() ? wp_get_referer() : self::url_portal();
        $back = remove_query_arg('ap_res', strtok($back, '#'));
        if ($fecha !== '') {
            $back = add_query_arg('ap_res', $fecha, $back);
        }
        wp_safe_redirect($back . '#reservas');
        exit;
    }

    /**
     * Validate the Portería form (the same questions as the families' form, without its deadlines).
     *
     * @return array|WP_Error { datos, alumnos, familia }
     */
    public static function validar_porteria($p) {
        $A = 'Area_Profesores_Alumnos';
        global $wpdb;
        $serv = self::servicios();
        $k = isset($p['servicio']) ? sanitize_key($p['servicio']) : '';
        if (!isset($serv[$k])) {
            return new WP_Error('ap', __('Elige el servicio.', 'area-profesores'));
        }
        $s = $serv[$k];

        $fid = isset($p['familia_id']) ? absint($p['familia_id']) : 0;
        $fam = $fid ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $A::t('familias') . ' WHERE id = %d', $fid), ARRAY_A) : null;
        if (!$fam) {
            return new WP_Error('ap', __('Busca al alumno y elige su familia.', 'area-profesores'));
        }
        $hijos = $A::alumnos_de_familias(array($fid), true);
        $por_id = array();
        foreach (isset($hijos[$fid]) ? $hijos[$fid] : array() as $a) {
            $por_id[(int) $a['id']] = $a;
        }
        $ids = array_values(array_unique(array_map('absint', isset($p['alumnos']) ? (array) $p['alumnos'] : array())));
        if (!$ids) {
            return new WP_Error('ap', __('Marca para qué alumno es la reserva.', 'area-profesores'));
        }
        $alumnos = array();
        foreach ($ids as $id) {
            if (!isset($por_id[$id])) {
                return new WP_Error('ap', __('Ese alumno no es de esa familia.', 'area-profesores'));
            }
            $c = Area_Profesores_Cursos::get_curso($por_id[$id]['curso']);
            if ($s['etapas'] && (!$c || !in_array($c['etapa'], (array) $s['etapas'], true))) {
                return new WP_Error('ap', sprintf(__('El servicio %1$s no está disponible para %2$s.', 'area-profesores'), $s['nombre'], $por_id[$id]['nombre']));
            }
            $alumnos[$id] = array('alumno' => $por_id[$id], 'familia' => $fam['nombre'], 'familia_id' => $fid);
        }

        $txt = function ($name, $max = 500) use ($p) {
            return isset($p[$name]) ? mb_substr(trim(sanitize_textarea_field($p[$name])), 0, $max) : '';
        };

        $hora = '';
        if ($k === 'salida' || $k === 'retraso') {
            $hora = isset($p['hora']) ? substr(sanitize_text_field($p['hora']), 0, 5) : '';
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
                return new WP_Error('ap', $k === 'salida' ? __('Indica a qué hora va a salir.', 'area-profesores') : __('Indica a qué hora va a llegar.', 'area-profesores'));
            }
        }

        $hoy = current_time('Y-m-d');
        $sumar = function ($f, $n) {
            return gmdate('Y-m-d', strtotime($f . ' 12:00:00 UTC') + $n * DAY_IN_SECONDS);
        };
        if ($s['varios_dias'] === 'yes' && isset($p['modo']) && $p['modo'] === 'varios') {
            $desde = isset($p['desde']) ? sanitize_text_field($p['desde']) : '';
            $hasta = isset($p['hasta']) ? sanitize_text_field($p['hasta']) : '';
            if (!self::fecha_valida($desde) || !self::fecha_valida($hasta) || $hasta < $desde) {
                return new WP_Error('ap', __('Indica bien el primer y el último día.', 'area-profesores'));
            }
            if ($hasta > $sumar($desde, 366)) {
                return new WP_Error('ap', __('Como mucho se puede reservar un curso entero de una vez.', 'area-profesores'));
            }
            $dias = array_values(array_unique(array_filter(array_map('intval', isset($p['dias']) ? (array) $p['dias'] : array()), function ($d) {
                return $d >= 1 && $d <= 5;
            })));
            sort($dias);
            if (!$dias) {
                return new WP_Error('ap', __('Marca al menos un día de la semana.', 'area-profesores'));
            }
            $lista = array();
            for ($f = max($desde, $hoy), $i = 0; $f <= $hasta && $i < 400; $f = $sumar($f, 1), $i++) {
                if (self::es_lectivo($f) && in_array((int) gmdate('N', strtotime($f . ' 12:00:00 UTC')), $dias, true)) {
                    $lista[] = $f;
                }
            }
            if (!$lista) {
                return new WP_Error('ap', __('En esas fechas no hay ningún día lectivo con los días de la semana marcados.', 'area-profesores'));
            }
            $ini = $lista[0];
            $fin = end($lista);
            if (count($dias) === 5) {
                $dias = array();
            }
        } else {
            $ini = isset($p['fecha']) ? sanitize_text_field($p['fecha']) : '';
            if (!self::fecha_valida($ini)) {
                return new WP_Error('ap', __('Indica el día.', 'area-profesores'));
            }
            if (!self::es_lectivo($ini)) {
                return new WP_Error('ap', __('Ese día no hay clase. Elige otro día.', 'area-profesores'));
            }
            if ($ini < $hoy) {
                return new WP_Error('ap', __('Esa fecha ya ha pasado.', 'area-profesores'));
            }
            $fin = $ini;
            $dias = array();
        }

        // Details: the same labels as the families' form (the dining room reads them).
        $det = array();
        $add = function ($label, $valor) use (&$det) {
            if (trim((string) $valor) !== '') {
                $det[] = array('label' => $label, 'valor' => $valor);
            }
        };
        if ($s['hora_inicio'] !== '' && $s['hora_fin'] !== '' && !in_array($k, array('salida', 'retraso'), true)) {
            $add(__('Horario', 'area-profesores'), sprintf(__('De %1$s a %2$s', 'area-profesores'), preg_replace('/^0(\d)/', '$1', $s['hora_inicio']), preg_replace('/^0(\d)/', '$1', $s['hora_fin'])));
        }
        $pide_dieta = false;
        if ($k === 'ampliacion' && $s['desayuno'] === 'yes') {
            if (!isset($p['desayuno']) || !in_array($p['desayuno'], array('si', 'no'), true)) {
                return new WP_Error('ap', __('Indica si quiere desayuno.', 'area-profesores'));
            }
            $add(__('Desayuno', 'area-profesores'), $p['desayuno'] === 'si' ? __('Sí', 'area-profesores') : __('No', 'area-profesores'));
        } elseif ($k === 'comedor' && $s['dieta'] === 'yes') {
            $dieta = isset($p['dieta']) && $p['dieta'] === 'si';
            $add(__('Dieta', 'area-profesores'), $dieta ? __('Sí', 'area-profesores') : __('No', 'area-profesores'));
            $pide_dieta = $dieta;
        } elseif ($k === 'dieta') {
            $pide_dieta = true;
        } elseif ($k === 'salida') {
            $add(__('Hora de salida', 'area-profesores'), $hora);
            if ($s['recoge'] === 'yes') {
                $add(__('Quién le recoge', 'area-profesores'), $txt('recoge', 150));
            }
        } elseif ($k === 'retraso') {
            $add(__('Hora de llegada', 'area-profesores'), $hora);
        }
        if ($pide_dieta) {
            $tipo = $txt('tipo_dieta', 150);
            $instr = $txt('instrucciones', 1000);
            if ($tipo === '' && $instr === '') {
                return new WP_Error('ap', __('Indica qué dieta necesita (el tipo o las instrucciones).', 'area-profesores'));
            }
            $add(__('Tipo de dieta', 'area-profesores'), $tipo);
            $add(__('Instrucciones especiales', 'area-profesores'), $instr);
        }
        if ($pide_dieta || in_array($k, array('salida', 'retraso'), true)) {
            $add(__('Motivo', 'area-profesores'), $txt('motivo', 500));
        }
        $add(__('Observaciones', 'area-profesores'), $txt('observaciones', 1000));
        if ($s['precio'] !== '') {
            $add(__('Precio', 'area-profesores'), $s['precio']);
        }
        $u = wp_get_current_user();
        $pedido = $txt('pedido', 200);
        $add(__('Reservada por', 'area-profesores'), sprintf(__('Portería (%s)', 'area-profesores'), $u->display_name) . ($pedido !== '' ? ' — ' . sprintf(__('lo ha pedido: %s', 'area-profesores'), $pedido) : ''));

        $emails = $A::emails_array($fam['emails']);
        return array(
            'familia' => $fam,
            'emails'  => $emails,
            'alumnos' => $alumnos,
            'datos'   => array(
                'email'           => $emails ? $emails[0] : '',
                'servicio'        => $k,
                'servicio_nombre' => $s['nombre'],
                'fecha_inicio'    => $ini,
                'fecha_fin'       => $fin,
                'dias'            => $dias,
                'hora'            => $hora,
                'detalles'        => $det,
                'avisar'          => array_slice($A::emails_array($s['avisar']), 0, 5),
            ),
        );
    }

    public function handle_porteria() {
        $uid = get_current_user_id();
        check_admin_referer('ap_reserva_porteria');
        if (!self::puede_reservar($uid)) {
            wp_die(esc_html__('Solo Portería puede hacer reservas para las familias.', 'area-profesores'));
        }
        $post = wp_unslash($_POST);
        unset($post['_wpnonce'], $post['_wp_http_referer'], $post['action']);
        $v = self::validar_porteria($post);
        if (is_wp_error($v)) {
            self::volver_portal('error', $v->get_error_message(), '', $post);
        }
        $creadas = self::crear($v['datos'], $v['alumnos'], !empty($post['confirmar']) ? $v['emails'] : array(), '', $uid);
        if (is_wp_error($creadas)) {
            self::volver_portal('error', $creadas->get_error_message(), '', $post);
        }
        $nombres = implode(', ', wp_list_pluck($creadas, 'alumno'));
        self::volver_portal('success', sprintf(__('Reserva hecha: %1$s para %2$s (%3$s). Se ha avisado al tutor/a%4$s.', 'area-profesores'),
            $v['datos']['servicio_nombre'], $nombres, $creadas ? $creadas[0]['fechas_texto'] : '',
            (!empty($post['confirmar']) && $v['emails']) ? __(' y se ha enviado la confirmación a la familia', 'area-profesores') : ''), $v['datos']['fecha_inicio']);
    }

    public function handle_anular_portal() {
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        check_admin_referer('ap_reserva_anular_portal_' . $id);
        if (!self::puede_reservar(get_current_user_id())) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $r = self::get($id);
        if (!$r || $r['estado'] !== 'activa') {
            self::volver_portal('error', __('Esa reserva ya estaba anulada.', 'area-profesores'));
        }
        self::anular($r, 'colegio');
        self::volver_portal('success', sprintf(__('Reserva anulada: %1$s de %2$s. Se ha avisado a la familia y al tutor/a.', 'area-profesores'), $r['servicio_nombre'], $r['alumno']));
    }

    /**
     * Data for the Portería form in the "Reservas" tab (null if the user cannot book).
     */
    public static function datos_porteria($user_id) {
        if (!self::puede_reservar($user_id)) {
            return null;
        }
        $flash = get_transient('ap_res_flash_' . $user_id);
        if ($flash) {
            delete_transient('ap_res_flash_' . $user_id);
        }
        return array(
            'servicios' => self::servicios(),
            'familias'  => self::familias_para_reservar(),
            'flash'     => $flash,
            'hoy'       => current_time('Y-m-d'),
        );
    }

    /* ---------------------------------------------------------------------
     * Backend: Área Profesores › Reservas de familias
     * ------------------------------------------------------------------- */

    public function register_menu() {
        add_submenu_page('area-profesores', __('Reservas de familias', 'area-profesores'), __('Reservas de familias', 'area-profesores'), 'manage_options', self::SLUG, array($this, 'render_admin'));
    }

    private static function filtros() {
        $g = function ($k) {
            return isset($_GET[$k]) ? sanitize_text_field(wp_unslash($_GET[$k])) : '';
        };
        $f = array(
            'vista'    => $g('vista') === 'todas' ? 'todas' : 'dia',
            'fecha'    => self::fecha_valida($g('fecha')) ? $g('fecha') : current_time('Y-m-d'),
            'servicio' => sanitize_key($g('servicio')),
            'curso'    => Area_Profesores_Cursos::get_curso($g('curso')) ? $g('curso') : '',
            'estado'   => in_array($g('estado'), array('activa', 'anulada'), true) ? $g('estado') : '',
            'q'        => $g('q'),
        );
        return $f;
    }

    private static function filas_filtradas($f) {
        if ($f['vista'] === 'dia') {
            $rows = self::del_dia($f['fecha'], $f['curso'] !== '' ? array($f['curso']) : true, $f['servicio']);
            if ($f['q'] !== '') {
                $q = mb_strtolower($f['q']);
                $rows = array_values(array_filter($rows, function ($r) use ($q) {
                    return mb_strpos(mb_strtolower($r['alumno'] . ' ' . $r['familia'] . ' ' . $r['email']), $q) !== false;
                }));
            }
            return $rows;
        }
        return self::buscar(array('servicio' => $f['servicio'], 'curso' => $f['curso'], 'estado' => $f['estado'], 'q' => $f['q']));
    }

    public function render_admin() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $f = self::filtros();
        $rows = self::filas_filtradas($f);
        $servicios = self::servicios_usados();
        $flash = get_transient('ap_reservas_flash_' . get_current_user_id());
        if ($flash) {
            delete_transient('ap_reservas_flash_' . get_current_user_id());
        }
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-reservas.php';
    }

    public function handle_anular_admin() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('ap_reserva_anular_' . $id);
        $r = self::get($id);
        if ($r && $r['estado'] === 'activa') {
            self::anular($r, 'colegio');
            $msg = sprintf(__('Reserva de %1$s (%2$s) anulada. Se ha avisado a la familia, al tutor/a y a Secretaría.', 'area-profesores'), $r['servicio_nombre'], $r['alumno']);
        } else {
            $msg = __('Esa reserva ya estaba anulada.', 'area-profesores');
        }
        set_transient('ap_reservas_flash_' . get_current_user_id(), array('type' => 'success', 'text' => $msg), 120);
        $back = wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=' . self::SLUG);
        wp_safe_redirect($back);
        exit;
    }

    public function handle_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        check_admin_referer('ap_reservas_csv');
        $f = self::filtros();
        $rows = self::filas_filtradas($f);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reservas-' . ($f['vista'] === 'dia' ? $f['fecha'] : current_time('Y-m-d')) . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('Servicio', 'Alumno', 'Curso', 'Fechas', 'Hora', 'Detalles', 'Familia', 'Cuenta', 'Estado', 'Reservado'), ';');
        foreach ($rows as $r) {
            fputcsv($out, array($r['servicio_nombre'], $r['alumno'], $r['curso_label'], $r['fechas_texto'], $r['hora'], str_replace("\n", ' · ', self::detalles_texto($r)), $r['familia'], $r['email'], $r['estado'], $r['creado']), ';');
        }
        fclose($out);
        exit;
    }
}
