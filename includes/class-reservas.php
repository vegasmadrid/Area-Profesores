<?php
/**
 * Reservas de servicios hechas por las familias desde el Área Familias.
 *
 * - The families site sends each reservation through the API (one row per
 *   child): service, dates (one day or a range with weekdays), time and the
 *   details the family filled in (desayuno, dieta, motivo…). The services and
 *   their rules (schedules, deadlines…) are configured in the Área Familias.
 * - Notifications (Correos automáticos): the tutor/a of the child and
 *   Secretaría, and a confirmation to the family. The same when it is cancelled.
 * - Teachers see the reservations of their pupils in the "Reservas" tab;
 *   Secretaría and Dirección see all of them, and the backend page
 *   "Área Profesores › Reservas de familias" lists them by day (printable) and
 *   exports them to CSV. The kitchen view ([comedorhoy]) shows the comedor and
 *   diet reservations of the day.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Reservas {

    const SLUG = 'area-profesores-reservas';

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
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE email = %s AND fecha_fin >= %s ORDER BY fecha_inicio ASC, id ASC', $email, $desde), ARRAY_A);
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
                    $out[(int) $a['id']] = array('alumno' => $a, 'familia' => isset($nombres[$fid]) ? $nombres[$fid] : '');
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

        $nueva = array('fecha_inicio' => $ini, 'fecha_fin' => $fin, 'dias' => $dias);

        // The same child cannot have the same service twice on the same day.
        foreach ($ids as $id) {
            $existentes = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . " WHERE estado = 'activa' AND alumno_id = %d AND servicio = %s AND fecha_inicio <= %s AND fecha_fin >= %s", $id, $servicio, $fin, $ini), ARRAY_A);
            foreach ((array) $existentes as $e) {
                $e = self::fila($e);
                if (self::se_solapan($e, $nueva)) {
                    return new WP_Error('area_repetida', sprintf(__('%1$s ya tiene una reserva de %2$s para esos días (%3$s). Si quieres cambiarla, anúlala primero.', 'area-profesores'), $hijos[$id]['alumno']['nombre'], $nombre, $e['fechas_texto']), array('status' => 409));
                }
            }
        }

        set_transient($limite, $n + 1, HOUR_IN_SECONDS);
        $grupo = wp_generate_uuid4();
        $creadas = array();
        foreach ($ids as $id) {
            $a = $hijos[$id]['alumno'];
            $wpdb->insert(self::t(), array(
                'grupo'           => $grupo,
                'alumno_id'       => $id,
                'alumno'          => $a['nombre'],
                'curso'           => $a['curso'],
                'familia'         => $hijos[$id]['familia'],
                'email'           => $email,
                'servicio'        => $servicio,
                'servicio_nombre' => $nombre,
                'fecha_inicio'    => $ini,
                'fecha_fin'       => $fin,
                'dias'            => implode(',', $dias),
                'hora'            => $hora,
                'detalles'        => wp_json_encode($detalles),
                'avisar'          => implode(', ', $avisar),
                'estado'          => 'activa',
                'creado'          => $A::now(),
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

        if ($creadas) {
            $panel = esc_url_raw((string) $request->get_param('panel_url'));
            Area_Profesores_Correos::enviar('reserva_familia', $email, array(
                'servicio' => $nombre,
                'alumnos'  => implode(', ', array_map(function ($r) {
                    return $r['alumno'] . ($r['curso_label'] !== '' ? ' (' . $r['curso_label'] . ')' : '');
                }, $creadas)),
                'fechas'   => $creadas[0]['fechas_texto'],
                'detalles' => self::detalles_texto($creadas[0]),
                'enlace'   => $panel,
            ));
        }

        return rest_ensure_response(array(
            'ok'       => true,
            'creadas'  => count($creadas),
            'reservas' => array_map(array(__CLASS__, 'para_familia'), self::de_cuenta($email)),
        ));
    }

    /**
     * The family cancels a reservation (its deadline is checked by the families site).
     * Body: email, id, panel_url.
     */
    public function api_anular(WP_REST_Request $request) {
        $email = Area_Profesores_Alumnos::norm_email($request->get_param('email'));
        $r = self::get(absint($request->get_param('id')));
        if (!$r || !$email || $r['email'] !== $email) {
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
        if ($conf['secretaria']) {
            $para = array_merge($para, $A::get_secretaria_emails());
        }
        if (!empty($conf['cocina']) && in_array($r['servicio'], array('comedor', 'dieta'), true) && class_exists('Area_Profesores_Cuentas')) {
            $para = array_merge($para, Area_Profesores_Cuentas::emails('cocina'));
        }
        if (!$para && !$conf['extra']) {
            $para = array(get_option('admin_email'));
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
