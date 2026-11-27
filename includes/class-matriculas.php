<?php
/**
 * Nuevas familias: solicitudes de matrícula que las familias rellenan en la web
 * del Área Familias (sin cuenta) y que revisan Secretaría, Dirección y
 * Administración en la pestaña "Nuevas familias" del Área de Profesores.
 *
 * - The Área Familias sends each request to POST /area-profesores/v1/matriculas
 *   (header X-Area-Key), after the family has confirmed its e-mail with a code.
 * - Everything the family writes (ID numbers, IBAN…) is stored encrypted
 *   (sodium). Only a short summary (surnames, pupils, levels, contact) is kept in
 *   plain text for the list. The key is AP_MATRICULAS_KEY (wp-config.php) or,
 *   if it is not defined, one derived from the WordPress salts.
 * - States: nueva → revision → aprobada (waiting to come and sign) → completada,
 *   or rechazada (also "lista de espera").
 * - "Imprimir" prints the same sheets as on paper, filled in (enrolment form,
 *   place reservation for Primer Ciclo and SEPA mandate), for the family to sign.
 * - The family account is proposed with the school's rule:
 *   firstsurname.secondsurname.cvm@ (separated parents: a "1" before ".cvm").
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Matriculas {

    const TABLA  = 'matriculas';
    const SUFIJO = 'cvm';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_post_ap_matricula', array($this, 'handle_accion'));
        add_action('admin_post_ap_matricula_imprimir', array($this, 'render_imprimir'));
        // Pupils "pendientes de incorporación" whose date has arrived go to "alta" (once a day).
        add_action('init', array('Area_Profesores_Alumnos', 'activar_pendientes'), 20);
        add_action('ap_alumnos_activados', array(__CLASS__, 'al_activar'));
        add_action('admin_post_ap_matricula_credenciales', array($this, 'render_credenciales'));
    }

    public static function t() {
        global $wpdb;
        return $wpdb->prefix . 'ap_' . self::TABLA;
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE " . self::t() . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            estado VARCHAR(20) NOT NULL DEFAULT 'nueva',
            curso_escolar VARCHAR(20) NOT NULL DEFAULT '',
            familia VARCHAR(200) NOT NULL DEFAULT '',
            alumnos_txt TEXT DEFAULT NULL,
            niveles VARCHAR(100) NOT NULL DEFAULT '',
            email_contacto VARCHAR(190) NOT NULL DEFAULT '',
            telefono VARCHAR(50) NOT NULL DEFAULT '',
            datos LONGTEXT DEFAULT NULL,
            oficina LONGTEXT DEFAULT NULL,
            historial LONGTEXT DEFAULT NULL,
            creado DATETIME DEFAULT NULL,
            actualizado DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY estado (estado),
            KEY creado (creado)
        ) $c;");
    }

    /* ---------------------------------------------------------------------
     * Estados
     * ------------------------------------------------------------------- */

    public static function estados() {
        return array(
            'nueva'      => array('label' => __('Nueva', 'area-profesores'), 'desc' => __('Recibida y sin revisar.', 'area-profesores')),
            'revision'   => array('label' => __('En revisión', 'area-profesores'), 'desc' => __('Alguien la está revisando o falta aclarar algo con la familia.', 'area-profesores')),
            'aprobada'   => array('label' => __('Aprobada · pendiente de firma', 'area-profesores'), 'desc' => __('La familia tiene que venir a firmar la ficha impresa y traer los documentos.', 'area-profesores')),
            'completada' => array('label' => __('Completada', 'area-profesores'), 'desc' => __('Firmada, documentos comprobados y matrícula pagada.', 'area-profesores')),
            'rechazada'  => array('label' => __('Rechazada / lista de espera', 'area-profesores'), 'desc' => __('No sigue adelante por ahora.', 'area-profesores')),
        );
    }

    public static function estado_label($e) {
        $s = self::estados();
        return isset($s[$e]) ? $s[$e]['label'] : $e;
    }

    /** Requests that still need someone (new + under review). */
    public static function contar_pendientes() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::t() . " WHERE estado IN ('nueva','revision')");
    }

    public static function puede_ver($user_id) {
        return $user_id && Area_Profesores_Pestanas::ve($user_id, 'matriculas');
    }

    /* ---------------------------------------------------------------------
     * Cifrado
     * ------------------------------------------------------------------- */

    private static function clave() {
        $base = defined('AP_MATRICULAS_KEY') && AP_MATRICULAS_KEY !== '' ? (string) AP_MATRICULAS_KEY : wp_salt('secure_auth');
        return hash_hmac('sha256', 'ap_matriculas_v1', $base, true);
    }

    public static function cifrar($valor) {
        $json = wp_json_encode($valor);
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($json, $nonce, self::clave()));
        }
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($json, 'aes-256-gcm', self::clave(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'o1:' . base64_encode($iv . $tag . $ct);
    }

    /**
     * @return array|null null if it cannot be read (e.g. the key changed).
     */
    public static function descifrar($texto) {
        $texto = (string) $texto;
        $raw = base64_decode(substr($texto, 3), true);
        if ($raw === false) {
            return null;
        }
        $json = false;
        if (strpos($texto, 's1:') === 0 && function_exists('sodium_crypto_secretbox_open')) {
            $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            if (strlen($raw) > $n) {
                $json = sodium_crypto_secretbox_open(substr($raw, $n), substr($raw, 0, $n), self::clave());
            }
        } elseif (strpos($texto, 'o1:') === 0 && strlen($raw) > 28) {
            $json = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::clave(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        }
        if ($json === false || $json === null) {
            return null;
        }
        $d = json_decode($json, true);
        return is_array($d) ? $d : null;
    }

    /* ---------------------------------------------------------------------
     * Lectura
     * ------------------------------------------------------------------- */

    private static function preparar($row) {
        if (!$row) {
            return null;
        }
        $row = (array) $row;
        $row['id'] = (int) $row['id'];
        $row['datos'] = $row['datos'] ? self::descifrar($row['datos']) : null;
        $of = json_decode((string) $row['oficina'], true);
        $row['oficina'] = is_array($of) ? $of : array();
        $h = json_decode((string) $row['historial'], true);
        $row['historial'] = is_array($h) ? $h : array();
        $row['referencia'] = self::referencia($row['id']);
        return $row;
    }

    public static function referencia($id) {
        return 'NF-' . str_pad((string) (int) $id, 4, '0', STR_PAD_LEFT);
    }

    public static function get($id) {
        global $wpdb;
        return self::preparar($wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::t() . " WHERE id = %d", $id)));
    }

    /**
     * List without decrypting (summary columns only).
     */
    public static function listar($estado = '') {
        global $wpdb;
        $sql = "SELECT id, estado, curso_escolar, familia, alumnos_txt, niveles, email_contacto, telefono, creado, actualizado FROM " . self::t();
        if ($estado !== '' && isset(self::estados()[$estado])) {
            $sql .= $wpdb->prepare(' WHERE estado = %s', $estado);
        }
        $rows = $wpdb->get_results($sql . ' ORDER BY creado DESC, id DESC LIMIT 500', ARRAY_A);
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['referencia'] = self::referencia($r['id']);
        }
        return $rows;
    }

    public static function contar_por_estado() {
        global $wpdb;
        $out = array_fill_keys(array_keys(self::estados()), 0);
        foreach ($wpdb->get_results("SELECT estado, COUNT(*) n FROM " . self::t() . " GROUP BY estado") as $r) {
            $out[$r->estado] = (int) $r->n;
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * API (Área Familias)
     * ------------------------------------------------------------------- */

    public function register_routes() {
        register_rest_route(Area_Profesores_API::NS, '/matriculas', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'api_crear'),
            'permission_callback' => array(Area_Profesores_API::get_instance(), 'check_key'),
        ));
    }

    private static function txt($a, $k, $max = 200) {
        return isset($a[$k]) && is_scalar($a[$k]) ? mb_substr(sanitize_text_field((string) $a[$k]), 0, $max) : '';
    }

    private static function fecha($a, $k) {
        $f = self::txt($a, $k, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : '';
    }

    /**
     * Clean the request sent by the Área Familias (it has validated it already;
     * here only the shape is ensured).
     *
     * @return array|WP_Error
     */
    public static function sanitizar($p) {
        $p = is_array($p) ? $p : array();
        $niveles = Area_Profesores_Cursos::get_niveles_publicos();

        $alumnos = array();
        foreach (array_slice(isset($p['alumnos']) && is_array($p['alumnos']) ? $p['alumnos'] : array(), 0, 6) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $al = array(
                'nombre'               => self::txt($a, 'nombre', 80),
                'apellido1'            => self::txt($a, 'apellido1', 80),
                'apellido2'            => self::txt($a, 'apellido2', 80),
                'sexo'                 => in_array(self::txt($a, 'sexo', 1), array('H', 'M'), true) ? self::txt($a, 'sexo', 1) : '',
                'fecha_nacimiento'     => self::fecha($a, 'fecha_nacimiento'),
                'localidad_nacimiento' => self::txt($a, 'localidad_nacimiento', 100),
                'provincia_nacimiento' => self::txt($a, 'provincia_nacimiento', 100),
                'nacionalidad'         => self::txt($a, 'nacionalidad', 80),
                'centro_procedencia'   => self::txt($a, 'centro_procedencia', 150),
                'nivel'                => isset($niveles[self::txt($a, 'nivel', 10)]) ? self::txt($a, 'nivel', 10) : '',
                'alergias_alimentarias'=> self::txt($a, 'alergias_alimentarias', 300),
                'alergias_otras'       => self::txt($a, 'alergias_otras', 300),
            );
            if ($al['nombre'] === '' || $al['apellido1'] === '' || $al['nivel'] === '' || $al['fecha_nacimiento'] === '') {
                return new WP_Error('ap_matricula', __('Faltan datos de algún alumno (nombre, primer apellido, fecha de nacimiento o curso).', 'area-profesores'), array('status' => 400));
            }
            $alumnos[] = $al;
        }
        if (!$alumnos) {
            return new WP_Error('ap_matricula', __('La solicitud no tiene ningún alumno.', 'area-profesores'), array('status' => 400));
        }

        $tutores = array();
        foreach (array_slice(isset($p['tutores']) && is_array($p['tutores']) ? $p['tutores'] : array(), 0, 2) as $t) {
            if (!is_array($t)) {
                continue;
            }
            $tu = array(
                'rol'                  => in_array(self::txt($t, 'rol', 10), array('padre', 'madre', 'tutor', 'tutora'), true) ? self::txt($t, 'rol', 10) : 'tutor',
                'nombre'               => self::txt($t, 'nombre', 80),
                'apellidos'            => self::txt($t, 'apellidos', 150),
                'fecha_nacimiento'     => self::fecha($t, 'fecha_nacimiento'),
                'localidad_nacimiento' => self::txt($t, 'localidad_nacimiento', 100),
                'nacionalidad'         => self::txt($t, 'nacionalidad', 80),
                'documento_tipo'       => in_array(self::txt($t, 'documento_tipo', 3), array('DNI', 'NIE', 'PAS'), true) ? self::txt($t, 'documento_tipo', 3) : 'DNI',
                'documento'            => strtoupper(self::txt($t, 'documento', 30)),
                'estudios'             => self::txt($t, 'estudios', 150),
                'profesion'            => self::txt($t, 'profesion', 150),
                'movil'                => self::txt($t, 'movil', 30),
                'email'                => Area_Profesores_Alumnos::norm_email(self::txt($t, 'email', 190)),
            );
            if ($tu['nombre'] === '' || $tu['apellidos'] === '') {
                return new WP_Error('ap_matricula', __('Faltan el nombre o los apellidos de un padre, madre o tutor.', 'area-profesores'), array('status' => 400));
            }
            $tutores[] = $tu;
        }
        if (!$tutores) {
            return new WP_Error('ap_matricula', __('La solicitud no tiene ningún padre, madre o tutor.', 'area-profesores'), array('status' => 400));
        }

        $dom = isset($p['domicilio']) && is_array($p['domicilio']) ? $p['domicilio'] : array();
        $domicilio = array();
        foreach (array('titular' => 150, 'calle' => 200, 'numero' => 20, 'piso' => 20, 'puerta' => 20, 'cp' => 10, 'localidad' => 100, 'provincia' => 100, 'telefono' => 30) as $k => $max) {
            $domicilio[$k] = self::txt($dom, $k, $max);
        }

        $hermanos = array();
        foreach (array_slice(isset($p['hermanos']) && is_array($p['hermanos']) ? $p['hermanos'] : array(), 0, 8) as $h) {
            if (is_array($h) && self::txt($h, 'nombre', 150) !== '') {
                $hermanos[] = array('nombre' => self::txt($h, 'nombre', 150), 'curso' => self::txt($h, 'curso', 60));
            }
        }

        $pg = isset($p['pago']) && is_array($p['pago']) ? $p['pago'] : array();
        $modo = self::txt($pg, 'modo', 20) === 'ventanilla' ? 'ventanilla' : 'domiciliacion';
        $pag = isset($pg['pagador']) && is_array($pg['pagador']) ? $pg['pagador'] : array();
        $pago = array('modo' => $modo, 'pagador' => array(), 'iban' => '');
        if ($modo === 'domiciliacion') {
            foreach (array('nombre' => 150, 'documento' => 30, 'telefono' => 30, 'direccion' => 200, 'cp' => 10, 'localidad' => 100, 'provincia' => 100) as $k => $max) {
                $pago['pagador'][$k] = self::txt($pag, $k, $max);
            }
            $pago['pagador']['documento'] = strtoupper($pago['pagador']['documento']);
            $iban = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', self::txt($pg, 'iban', 40)));
            if (!self::iban_valido($iban)) {
                return new WP_Error('ap_matricula', __('El IBAN no es válido.', 'area-profesores'), array('status' => 400));
            }
            $pago['iban'] = $iban;
        }

        $email = Area_Profesores_Alumnos::norm_email(self::txt($p, 'email_contacto', 190));
        if (!$email) {
            return new WP_Error('ap_matricula', __('Falta el correo de contacto.', 'area-profesores'), array('status' => 400));
        }
        $acept = isset($p['aceptaciones']) && is_array($p['aceptaciones']) ? $p['aceptaciones'] : array();
        if (empty($acept['ideario']) || empty($acept['privacidad'])) {
            return new WP_Error('ap_matricula', __('Faltan las aceptaciones obligatorias.', 'area-profesores'), array('status' => 400));
        }
        $origen = isset($p['origen']) && is_array($p['origen']) ? $p['origen'] : array();

        return array(
            'curso_escolar' => self::txt($p, 'curso_escolar', 20),
            'alumnos'       => $alumnos,
            'tutores'       => $tutores,
            'domicilio'     => $domicilio,
            'convivientes'  => min(30, absint(isset($p['convivientes']) ? $p['convivientes'] : 0)),
            'hermanos'      => $hermanos,
            'separados'     => !empty($p['separados']),
            'pago'          => $pago,
            'email_contacto'=> $email,
            'observaciones' => isset($p['observaciones']) && is_scalar($p['observaciones']) ? mb_substr(sanitize_textarea_field((string) $p['observaciones']), 0, 2000) : '',
            'aceptaciones'  => array(
                'ideario'         => self::txt($acept, 'ideario', 30),
                'privacidad'      => self::txt($acept, 'privacidad', 30),
                'imagen'          => self::txt($acept, 'imagen', 10),
                'manifestaciones' => self::sanitizar_respuestas(isset($acept['manifestaciones']) ? $acept['manifestaciones'] : array(), 2000),
                'rgpd'            => self::sanitizar_respuestas(isset($acept['rgpd']) ? $acept['rgpd'] : array(), 10),
            ),
            'textos'        => self::sanitizar_textos(isset($p['textos']) ? $p['textos'] : array()),
            'origen'        => array(
                'ip'         => self::txt($origen, 'ip', 64),
                'navegador'  => self::txt($origen, 'navegador', 255),
                'verificado' => self::txt($origen, 'verificado', 30),
            ),
        );
    }

    private static function sanitizar_respuestas($a, $max) {
        $out = array();
        foreach (is_array($a) ? $a : array() as $k => $v) {
            if (is_scalar($v) && count($out) < 20) {
                $out[sanitize_key($k)] = mb_substr(sanitize_textarea_field((string) $v), 0, $max);
            }
        }
        return $out;
    }

    /**
     * Texts the family accepted (information of the school, manifestations and data
     * protection), kept with the request to print exactly what they read.
     */
    private static function sanitizar_textos($t) {
        $t = is_array($t) ? $t : array();
        $largo = function ($k) use ($t) {
            return isset($t[$k]) && is_scalar($t[$k]) ? mb_substr(sanitize_textarea_field((string) $t[$k]), 0, 30000) : '';
        };
        $manif = array();
        foreach (isset($t['manifestaciones']) && is_array($t['manifestaciones']) ? array_slice($t['manifestaciones'], 0, 12, true) : array() as $k => $m) {
            if (!is_array($m)) {
                continue;
            }
            $x = array();
            foreach (array('n' => 10, 'tipo' => 10, 'texto' => 5000, 'si' => 200, 'no' => 200) as $c => $max) {
                $x[$c] = isset($m[$c]) && is_scalar($m[$c]) ? mb_substr(sanitize_textarea_field((string) $m[$c]), 0, $max) : '';
            }
            $manif[sanitize_key($k)] = $x;
        }
        $cons = array();
        foreach (isset($t['consentimientos']) && is_array($t['consentimientos']) ? $t['consentimientos'] : array() as $k => $c) {
            if (is_array($c) && preg_match('/^[A-Z_]+$/', (string) $k)) {
                $cons[$k] = array(
                    'campo' => sanitize_key(isset($c['campo']) ? $c['campo'] : ''),
                    'label' => isset($c['label']) && is_scalar($c['label']) ? mb_substr(sanitize_text_field($c['label']), 0, 200) : '',
                );
            }
        }
        return array('info_centro' => $largo('info_centro'), 'manifestaciones' => $manif, 'proteccion' => $largo('proteccion'), 'consentimientos' => $cons);
    }

    /**
     * Text with the simple format of the Área Familias → HTML ("## " heading, "- " list,
     * **bold**, *italic*; a line [MARKER] is replaced by $marca($marker)).
     */
    public static function texto_html($texto, $marca = null) {
        $out = '';
        $lista = false;
        $inline = function ($s) {
            $s = esc_html($s);
            $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
            $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/u', '<em>$1</em>', $s);
            return $s;
        };
        foreach (preg_split('/\r\n|\r|\n/', (string) $texto) as $l) {
            $l = trim($l);
            $es_sub = strpos($l, '-- ') === 0;
            $es_item = $es_sub || strpos($l, '- ') === 0;
            if ($lista && !$es_item) {
                $out .= '</ul>';
                $lista = false;
            }
            if ($l === '') {
                continue;
            }
            if (preg_match('/^\[([A-Z_]+)\]$/', $l, $m)) {
                $out .= $marca ? call_user_func($marca, $m[1]) : '';
            } elseif (strpos($l, '## ') === 0) {
                $out .= '<h4>' . $inline(substr($l, 3)) . '</h4>';
            } elseif ($es_item) {
                if (!$lista) {
                    $out .= '<ul>';
                    $lista = true;
                }
                $out .= $es_sub ? '<li class="sub">– ' . $inline(substr($l, 3)) . '</li>' : '<li>' . $inline(substr($l, 2)) . '</li>';
            } else {
                $out .= '<p>' . $inline($l) . '</p>';
            }
        }
        return $out . ($lista ? '</ul>' : '');
    }

    /**
     * The image answers do not match (4º of the manifestations and the data-protection consent)?
     */
    public static function imagenes_contradictorias($d) {
        $m4 = isset($d['aceptaciones']['manifestaciones']['m4']) ? $d['aceptaciones']['manifestaciones']['m4'] : '';
        $rg = isset($d['aceptaciones']['rgpd']['rgpd_imagenes']) ? $d['aceptaciones']['rgpd']['rgpd_imagenes'] : '';
        return $m4 !== '' && $rg !== '' && (($m4 === 'si') !== ($rg === 'consiento'));
    }

    public static function iban_valido($iban) {
        $iban = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $iban));
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban) || (substr($iban, 0, 2) === 'ES' && strlen($iban) !== 24)) {
            return false;
        }
        $r = substr($iban, 4) . substr($iban, 0, 4);
        $num = '';
        foreach (str_split($r) as $ch) {
            $num .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $mod = 0;
        foreach (str_split($num, 7) as $part) {
            $mod = (int) (($mod . $part) % 97);
        }
        return $mod === 1;
    }

    public static function nombre_alumno($a) {
        return trim($a['nombre'] . ' ' . $a['apellido1'] . ' ' . $a['apellido2']);
    }

    public static function alumnos_txt($datos, $con_curso = true) {
        $l = array();
        foreach ($datos['alumnos'] as $a) {
            $l[] = '- ' . self::nombre_alumno($a) . ($con_curso ? ' (' . Area_Profesores_Cursos::get_nivel_label($a['nivel']) . ')' : '');
        }
        return implode("\n", $l);
    }

    public function api_crear(WP_REST_Request $request) {
        global $wpdb;
        $datos = self::sanitizar($request->get_json_params());
        if (is_wp_error($datos)) {
            return $datos;
        }
        $url = esc_url_raw((string) (isset($request->get_json_params()['familias_url']) ? $request->get_json_params()['familias_url'] : ''));
        if ($url !== '' && get_option('ap_familias_login_url') !== $url) {
            update_option('ap_familias_login_url', $url, false);
        }
        $primero = $datos['alumnos'][0];
        $niveles = array_values(array_unique(wp_list_pluck($datos['alumnos'], 'nivel')));
        $tel = '';
        foreach ($datos['tutores'] as $t) {
            if ($t['movil'] !== '') {
                $tel = $t['movil'];
                break;
            }
        }
        $ahora = Area_Profesores_Alumnos::now();
        $ok = $wpdb->insert(self::t(), array(
            'estado'         => 'nueva',
            'curso_escolar'  => $datos['curso_escolar'],
            'familia'        => trim($primero['apellido1'] . ' ' . $primero['apellido2']),
            'alumnos_txt'    => implode(', ', array_map(array(__CLASS__, 'nombre_alumno'), $datos['alumnos'])),
            'niveles'        => implode(',', $niveles),
            'email_contacto' => $datos['email_contacto'],
            'telefono'       => $tel,
            'datos'          => self::cifrar($datos),
            'oficina'        => wp_json_encode(array('cuentas' => self::cuentas_propuestas($datos))),
            'historial'      => wp_json_encode(array(array('fecha' => $ahora, 'user' => 0, 'quien' => __('La familia (web)', 'area-profesores'), 'accion' => __('Solicitud enviada desde el Área Familias', 'area-profesores')))),
            'creado'         => $ahora,
            'actualizado'    => $ahora,
        ));
        if (!$ok) {
            return new WP_Error('ap_matricula', __('No se ha podido guardar la solicitud. Inténtalo de nuevo más tarde.', 'area-profesores'), array('status' => 500));
        }
        $id = (int) $wpdb->insert_id;
        $ref = self::referencia($id);

        $vars = array(
            'referencia'    => $ref,
            'familia'       => trim($primero['apellido1'] . ' ' . $primero['apellido2']),
            'alumnos'       => self::alumnos_txt($datos),
            'curso_escolar' => $datos['curso_escolar'],
            'contacto'      => $datos['email_contacto'] . ($tel !== '' ? ' · ' . $tel : ''),
            'enlace'        => self::url_detalle($id),
        );
        Area_Profesores_Correos::enviar('matricula_nueva', array(), $vars);
        Area_Profesores_Correos::enviar('matricula_recibida', $datos['email_contacto'], $vars, false, self::reply_to());

        return rest_ensure_response(array('ok' => true, 'referencia' => $ref));
    }

    private static function reply_to() {
        $s = Area_Profesores_Cuentas::emails('secretaria');
        return $s ? $s[0] : '';
    }

    /* ---------------------------------------------------------------------
     * Cuenta de la familia
     * ------------------------------------------------------------------- */

    public static function dominio() {
        return strtolower(trim(str_replace('@', '', (string) Area_Profesores_Admin::get_setting('allowed_domain', 'casadelavirgen.es'))));
    }

    /** "De la Fuente" → "delafuente", "Núñez-Peña" → "nunezpena". */
    public static function slug($s) {
        $s = remove_accents(mb_strtolower(trim((string) $s)));
        return preg_replace('/[^a-z0-9]/', '', $s);
    }

    /**
     * Base of the account: firstsurname.secondsurname of the pupil
     * (pupils with only one surname: surname.firstname).
     */
    public static function base_cuenta($alumno) {
        $a1 = self::slug($alumno['apellido1']);
        $a2 = self::slug($alumno['apellido2']);
        if ($a2 === '') {
            $n = preg_split('/\s+/', trim((string) $alumno['nombre']));
            $a2 = self::slug($n[0]);
        }
        return $a1 . '.' . $a2;
    }

    /**
     * Accounts already used: those of the families of the school and those
     * proposed in other requests still in progress.
     *
     * @return array email => description of who uses it
     */
    public static function cuentas_usadas($excluir_id = 0) {
        global $wpdb;
        $A = 'Area_Profesores_Alumnos';
        $out = array();
        foreach ($wpdb->get_results("SELECT id, nombre, emails FROM " . $A::t('familias')) as $f) {
            foreach ($A::emails_array($f->emails) as $e) {
                $out[$e] = array('tipo' => 'familia', 'id' => (int) $f->id, 'texto' => sprintf(__('Familia %s del colegio', 'area-profesores'), $f->nombre));
            }
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, oficina FROM " . self::t() . " WHERE estado <> 'rechazada' AND id <> %d", (int) $excluir_id));
        foreach ($rows as $r) {
            $of = json_decode((string) $r->oficina, true);
            foreach (isset($of['cuentas']) ? (array) $of['cuentas'] : array() as $c) {
                $c = strtolower((string) $c);
                if ($c !== '' && !isset($out[$c])) {
                    $out[$c] = array('tipo' => 'solicitud', 'id' => (int) $r->id, 'texto' => sprintf(__('Solicitud %s', 'area-profesores'), self::referencia($r->id)));
                }
            }
        }
        return $out;
    }

    /**
     * Proposed account(s), following the school's rule:
     *   garcia.lopez.cvm@                 (normal)
     *   garcia.lopez1.cvm@                (second account of separated parents)
     * If another family already uses it, the initial(s) of the eldest pupil's first
     * name are added (garcia.lopez.l.cvm@, garcia.lopez.lu.cvm@…) and, as a last
     * resort, a number (garcia.lopez.l2.cvm@).
     *
     * @return string[] one account, or two for separated parents
     */
    public static function cuentas_propuestas($datos, $excluir_id = 0) {
        // Brothers or sisters at the school: the account(s) of their family.
        $hermanos = self::analizar_hermanos($datos);
        $ya = array();
        foreach ($hermanos['familias'] as $f) {
            if ($f['emails']) {
                $ya[] = $f['emails'][0];
            }
        }
        if ($ya) {
            sort($ya);
            return array_values(array_unique($ya));
        }
        $usadas = self::cuentas_usadas($excluir_id);
        $alumnos = $datos['alumnos'];
        // Eldest pupil (the earliest date of birth).
        usort($alumnos, function ($a, $b) {
            return strcmp($a['fecha_nacimiento'], $b['fecha_nacimiento']);
        });
        $al = $alumnos[0];
        $base = self::base_cuenta($al);
        $dom = '@' . self::dominio();
        $sep = !empty($datos['separados']);
        $libre = function ($b) use ($usadas, $sep, $dom) {
            if (isset($usadas[$b . '.' . self::SUFIJO . $dom])) {
                return false;
            }
            return !$sep || !isset($usadas[$b . '1.' . self::SUFIJO . $dom]);
        };

        $elegida = '';
        if ($libre($base)) {
            $elegida = $base;
        } else {
            $nombre = self::slug(preg_split('/\s+/', trim((string) $al['nombre']))[0]);
            for ($i = 1; $i <= strlen($nombre); $i++) {
                if ($libre($base . '.' . substr($nombre, 0, $i))) {
                    $elegida = $base . '.' . substr($nombre, 0, $i);
                    break;
                }
            }
            for ($n = 2; $elegida === '' && $n < 100; $n++) {
                if ($libre($base . '.' . substr($nombre, 0, 1) . $n)) {
                    $elegida = $base . '.' . substr($nombre, 0, 1) . $n;
                }
            }
        }
        $out = array($elegida . '.' . self::SUFIJO . $dom);
        if ($sep) {
            $out[] = $elegida . '1.' . self::SUFIJO . $dom;
        }
        return $out;
    }

    /** Words of a name, without accents and in any order. */
    private static function palabras($s) {
        $k = Area_Profesores_Alumnos::clave_nombre($s);
        return $k === '' ? array() : array_values(array_unique(explode(' ', $k)));
    }

    /**
     * Same person? At least two words in common and all the words of the shorter
     * name are in the other one ("Pablo García" = "Pablo García López";
     * "Ana López" = "Ana María López Sanz").
     */
    public static function mismo_nombre($a, $b) {
        $wa = self::palabras($a);
        $wb = self::palabras($b);
        if (count($wa) < 2 || count($wb) < 2) {
            return false;
        }
        $comunes = count(array_intersect($wa, $wb));
        return $comunes >= 2 && $comunes === min(count($wa), count($wb));
    }

    /**
     * Is this a family that already has children at the school? It is, when
     * - a brother or sister written in "Hermanos en el centro" is a pupil of the
     *   school (only one pupil with that name, sharing a surname with the new
     *   pupils), or
     * - the family that already uses the account of these surnames has a father,
     *   mother or guardian with the same name as one of the request.
     * Then the existing account is used and no new one is created. If the account
     * exists but nothing matches, it is another family with the same surnames.
     *
     * @return array {
     *   familias: id => { id, nombre, emails[], hijos[], motivos[] }  same family (sure)
     *   dudas:    string[]   for Secretaría to check
     *   otras:    email => text  account of another family with the same surnames
     * }
     */
    public static function analizar_hermanos($datos) {
        global $wpdb;
        $A = 'Area_Profesores_Alumnos';
        $out = array('familias' => array(), 'dudas' => array(), 'otras' => array());
        $apellidos = array();
        foreach ($datos['alumnos'] as $a) {
            foreach (array($a['apellido1'], $a['apellido2']) as $ap) {
                foreach (self::palabras($ap) as $w) {
                    $apellidos[$w] = true;
                }
            }
        }
        $tutores = array_map(function ($t) {
            return trim($t['nombre'] . ' ' . $t['apellidos']);
        }, $datos['tutores']);

        $hijos_txt = function ($fid) use ($A) {
            $h = $A::alumnos_de_familias(array($fid));
            return array_map(function ($x) {
                $c = Area_Profesores_Cursos::get_curso($x['curso']);
                return $x['nombre'] . ($c ? ' (' . $c['label'] . ')' : '');
            }, isset($h[$fid]) ? array_values(array_filter($h[$fid], function ($x) {
                return $x['estado'] !== 'baja';
            })) : array());
        };
        $marcar = function ($fid, $motivo) use (&$out, $A, $hijos_txt) {
            if (!isset($out['familias'][$fid])) {
                $f = $A::get_familia($fid);
                if (!$f) {
                    return;
                }
                $out['familias'][$fid] = array('id' => (int) $fid, 'nombre' => $f['nombre'], 'emails' => $A::emails_array($f['emails']), 'hijos' => $hijos_txt($fid), 'motivos' => array());
            }
            if (!in_array($motivo, $out['familias'][$fid]['motivos'], true)) {
                $out['familias'][$fid]['motivos'][] = $motivo;
            }
        };

        // 1) Brothers and sisters written by the family.
        if (!empty($datos['hermanos'])) {
            $todos = $wpdb->get_results('SELECT id, nombre, curso FROM ' . $A::t('alumnos') . " WHERE estado <> 'baja'", ARRAY_A);
            foreach ($datos['hermanos'] as $h) {
                $cand = array_values(array_filter($todos, function ($x) use ($h, $apellidos) {
                    return self::mismo_nombre($x['nombre'], $h['nombre']) && array_intersect_key(array_flip(self::palabras($x['nombre'])), $apellidos);
                }));
                if (count($cand) === 1) {
                    $fams = $A::familias_de_alumnos(array((int) $cand[0]['id']));
                    $fams = isset($fams[$cand[0]['id']]) ? $fams[$cand[0]['id']] : array();
                    foreach ($fams as $f) {
                        $marcar((int) $f['id'], sprintf(__('su hermano/a %s está en el colegio', 'area-profesores'), $cand[0]['nombre']));
                    }
                    if (!$fams) {
                        $out['dudas'][] = sprintf(__('El hermano/a «%s» está en el colegio pero no tiene familia asignada en Alumnos y familias.', 'area-profesores'), $cand[0]['nombre']);
                    }
                } elseif (count($cand) > 1) {
                    $out['dudas'][] = sprintf(__('El hermano/a «%1$s» coincide con varios alumnos (%2$s): comprobad cuál es.', 'area-profesores'), $h['nombre'], implode(', ', wp_list_pluck($cand, 'nombre')));
                } else {
                    $out['dudas'][] = sprintf(__('La familia dice que «%s» estudia en el colegio, pero no lo encontramos en Alumnos y familias.', 'area-profesores'), $h['nombre']);
                }
            }
        }

        // 2) The family that already uses the account of these surnames.
        $usadas = self::cuentas_usadas();
        $dom = '@' . self::dominio();
        foreach ($datos['alumnos'] as $a) {
            $base = self::base_cuenta($a);
            foreach (array($base . '.' . self::SUFIJO . $dom, $base . '1.' . self::SUFIJO . $dom) as $e) {
                if (!isset($usadas[$e]) || $usadas[$e]['tipo'] !== 'familia') {
                    continue;
                }
                $fid = $usadas[$e]['id'];
                $padres = false;
                foreach ($A::get_contactos($fid) as $c) {
                    foreach ($tutores as $t) {
                        if (self::mismo_nombre($c['nombre'], $t)) {
                            $marcar($fid, sprintf(__('coincide %s', 'area-profesores'), $c['nombre']));
                            $padres = true;
                        }
                    }
                }
                if (!$padres && !isset($out['familias'][$fid])) {
                    $out['otras'][$e] = $usadas[$e]['texto'];
                }
            }
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Acciones de Secretaría, Dirección y Administración
     * ------------------------------------------------------------------- */

    public static function url_lista($args = array()) {
        $id = absint(Area_Profesores_Admin::get_setting('area_page_id', 0));
        $base = ($id && get_post_status($id) === 'publish') ? get_permalink($id) : home_url('/');
        return add_query_arg($args, $base) . '#matriculas';
    }

    public static function url_detalle($id) {
        return self::url_lista(array('ap_mat' => (int) $id));
    }

    public static function url_imprimir($id) {
        return wp_nonce_url(admin_url('admin-post.php?action=ap_matricula_imprimir&id=' . (int) $id), 'ap_matricula_imprimir_' . (int) $id);
    }

    private static function volver($id, $type, $text) {
        set_transient('ap_mat_flash_' . get_current_user_id(), array('type' => $type, 'text' => $text), 120);
        wp_safe_redirect($id ? self::url_detalle($id) : self::url_lista());
        exit;
    }

    public static function flash() {
        $k = 'ap_mat_flash_' . get_current_user_id();
        $f = get_transient($k);
        if ($f) {
            delete_transient($k);
        }
        return $f;
    }

    private static function anotar(&$row, $accion) {
        $u = wp_get_current_user();
        $row['historial'][] = array('fecha' => Area_Profesores_Alumnos::now(), 'user' => (int) $u->ID, 'quien' => $u->display_name, 'accion' => $accion);
    }

    private static function guardar_fila($row) {
        global $wpdb;
        $wpdb->update(self::t(), array(
            'estado'      => $row['estado'],
            'oficina'     => wp_json_encode($row['oficina']),
            'historial'   => wp_json_encode($row['historial']),
            'actualizado' => Area_Profesores_Alumnos::now(),
        ), array('id' => $row['id']));
    }

    public function handle_accion() {
        $uid = get_current_user_id();
        if (!self::puede_ver($uid)) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        check_admin_referer('ap_matricula_' . $id);
        $row = self::get($id);
        if (!$row) {
            self::volver(0, 'error', __('Esa solicitud ya no existe.', 'area-profesores'));
        }
        $op = isset($_POST['op']) ? sanitize_key($_POST['op']) : '';
        $p = wp_unslash($_POST);

        if ($op === 'estado') {
            $nuevo = isset($p['estado']) ? sanitize_key($p['estado']) : '';
            if (!isset(self::estados()[$nuevo]) || $nuevo === $row['estado']) {
                self::volver($id, 'error', __('Elige un estado distinto.', 'area-profesores'));
            }
            $crear_ahora = $nuevo === 'aprobada' && !empty($p['crear_cuenta']) && Area_Profesores_Google::familias_conectada() && self::cuentas_sin_crear($row);
            $alta_ahora = $nuevo === 'completada' && !empty($p['dar_alta']) && !self::alta_hecha($row);
            if ($alta_ahora && ($faltan = self::faltan_para_alta($row))) {
                self::volver($id, 'error', __('No se ha cambiado el estado. Para completarla y darla de alta en Alumnos y familias:', 'area-profesores') . ' ' . implode(' ', $faltan));
            }
            $antes = $row['estado'];
            $row['estado'] = $nuevo;
            $motivo = isset($p['motivo']) ? mb_substr(sanitize_text_field($p['motivo']), 0, 300) : '';
            self::anotar($row, sprintf(__('Estado: %1$s → %2$s', 'area-profesores'), self::estado_label($antes), self::estado_label($nuevo)) . ($motivo !== '' ? ' · ' . $motivo : ''));
            $aviso = '';
            if ($nuevo === 'aprobada' && !empty($p['avisar']) && $row['datos']) {
                $d = $row['datos'];
                $ok = Area_Profesores_Correos::enviar('matricula_aprobada', $row['email_contacto'], array(
                    'referencia' => $row['referencia'],
                    'familia'    => $row['familia'],
                    'alumnos'    => self::alumnos_txt($d),
                    'curso_escolar' => $row['curso_escolar'],
                ), false, self::reply_to());
                $aviso = $ok ? ' ' . __('Se ha enviado el correo a la familia.', 'area-profesores') : ' ' . __('No se ha podido enviar el correo a la familia (revisa "Avisos").', 'area-profesores');
                self::anotar($row, $ok ? __('Correo a la familia: venir a firmar y traer los documentos', 'area-profesores') : __('No se pudo enviar el correo a la familia', 'area-profesores'));
            }
            self::guardar_fila($row);
            if ($crear_ahora) {
                $dudas = $row['datos'] ? self::analizar_hermanos($row['datos'])['dudas'] : array();
                if ($dudas || !empty($row['oficina']['google_conflicto'])) {
                    $aviso .= ' ' . __('No se ha creado la cuenta en Google porque hay algo que revisar (hermanos o cuenta): revísalo y pulsa «Crear la cuenta en Google».', 'area-profesores');
                } else {
                    $aviso .= ' ' . self::texto_crear(self::crear_cuentas($id));
                }
            }
            if ($nuevo === 'completada') {
                self::borrar_claves($id);
            }
            if ($alta_ahora) {
                $r = self::dar_de_alta($id);
                $aviso .= is_wp_error($r) ? ' ' . $r->get_error_message() : ' ' . __('Familia y alumnos dados de alta en Alumnos y familias.', 'area-profesores');
            }
            self::volver($id, 'success', sprintf(__('Estado cambiado a «%s».', 'area-profesores'), self::estado_label($nuevo)) . $aviso);
        }

        if ($op === 'oficina') {
            $of = $row['oficina'];
            $n = $row['datos'] ? count($row['datos']['alumnos']) : 0;
            $al = isset($p['al']) && is_array($p['al']) ? $p['al'] : array();
            $of['alumnos'] = array();
            for ($i = 0; $i < $n; $i++) {
                $x = isset($al[$i]) && is_array($al[$i]) ? $al[$i] : array();
                $grupo = isset($x['grupo']) ? strtoupper(sanitize_text_field($x['grupo'])) : '';
                $f = isset($x['fecha_ingreso']) ? sanitize_text_field($x['fecha_ingreso']) : '';
                $of['alumnos'][$i] = array(
                    'numero_matricula' => isset($x['numero_matricula']) ? mb_substr(sanitize_text_field($x['numero_matricula']), 0, 30) : '',
                    'nia'              => isset($x['nia']) ? mb_substr(sanitize_text_field($x['nia']), 0, 30) : '',
                    'fecha_ingreso'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : '',
                    'grupo'            => in_array($grupo, array('A', 'B'), true) ? $grupo : '',
                );
            }
            $of['docs'] = array();
            foreach (array_keys(self::documentos()) as $k) {
                $of['docs'][$k] = !empty($p['docs'][$k]);
            }
            $cuentas = array();
            foreach ((array) (isset($p['cuentas']) ? $p['cuentas'] : array()) as $c) {
                $c = strtolower(trim(sanitize_text_field($c)));
                if ($c === '') {
                    continue;
                }
                if (strpos($c, '@') === false) {
                    $c .= '@' . self::dominio();
                }
                if (!is_email($c)) {
                    self::volver($id, 'error', sprintf(__('La cuenta «%s» no es válida.', 'area-profesores'), $c));
                }
                $cuentas[] = $c;
            }
            if (self::alguna_creada($row)) {
                $cuentas = (array) $row['oficina']['cuentas']; // already in Google: they do not change here
            }
            $usadas = self::cuentas_usadas($id);
            foreach ($cuentas as $c) {
                if (isset($usadas[$c]) && $usadas[$c]['tipo'] === 'solicitud') {
                    self::volver($id, 'error', sprintf(__('La cuenta %1$s ya está propuesta en la %2$s.', 'area-profesores'), $c, $usadas[$c]['texto']));
                }
            }
            $of['cuentas'] = array_values(array_unique($cuentas));
            $of['notas'] = isset($p['notas']) ? mb_substr(sanitize_textarea_field($p['notas']), 0, 3000) : '';
            $row['oficina'] = $of;
            self::anotar($row, __('Datos de Secretaría guardados', 'area-profesores'));
            self::guardar_fila($row);
            self::volver($id, 'success', __('Datos guardados.', 'area-profesores'));
        }

        if ($op === 'proponer') {
            if ($row['datos']) {
                $row['oficina']['cuentas'] = self::cuentas_propuestas($row['datos'], $id);
                self::anotar($row, __('Cuenta propuesta de nuevo', 'area-profesores'));
                self::guardar_fila($row);
            }
            self::volver($id, 'success', __('Se ha vuelto a calcular la cuenta propuesta.', 'area-profesores'));
        }

        if ($op === 'google_crear') {
            $r = self::crear_cuentas($id);
            self::volver($id, $r['creadas'] && !$r['errores'] ? 'success' : 'error', self::texto_crear($r));
        }

        if ($op === 'google_alternativa' || $op === 'google_usar') {
            $conf = isset($row['oficina']['google_conflicto']) ? $row['oficina']['google_conflicto'] : null;
            if (!$conf) {
                self::volver($id, 'error', __('No hay nada pendiente con esa cuenta.', 'area-profesores'));
            }
            unset($row['oficina']['google_conflicto']);
            if ($op === 'google_usar') {
                // Secretaría confirms the existing Google account is this family's.
                $row['oficina']['google'][$conf['cuenta']] = array('estado' => 'existente', 'fecha' => Area_Profesores_Alumnos::now(), 'quien' => wp_get_current_user()->display_name);
                self::anotar($row, sprintf(__('Se usa la cuenta que ya existía en Google: %s', 'area-profesores'), $conf['cuenta']));
                self::guardar_fila($row);
                self::volver($id, 'success', sprintf(__('Se usará la cuenta %s, que ya existía (no se ha cambiado su contraseña).', 'area-profesores'), $conf['cuenta']));
            }
            if ($conf['propuesta'] === '') {
                self::volver($id, 'error', __('No se ha encontrado otra cuenta libre: escríbela a mano.', 'area-profesores'));
            }
            $row['oficina']['cuentas'] = array_values(array_map(function ($c) use ($conf) {
                return $c === $conf['cuenta'] ? $conf['propuesta'] : $c;
            }, (array) $row['oficina']['cuentas']));
            self::anotar($row, sprintf(__('Cuenta cambiada: %1$s → %2$s', 'area-profesores'), $conf['cuenta'], $conf['propuesta']));
            self::guardar_fila($row);
            $r = self::crear_cuentas($id);
            self::volver($id, $r['creadas'] && !$r['errores'] ? 'success' : 'error', self::texto_crear($r));
        }

        if ($op === 'google_clave') {
            $email = isset($p['email']) ? strtolower(sanitize_email($p['email'])) : '';
            $est = self::estado_cuentas($row);
            if (!isset($est[$email]) || $est[$email]['estado'] !== 'creada') {
                self::volver($id, 'error', __('Solo se puede cambiar la contraseña de las cuentas creadas desde aquí.', 'area-profesores'));
            }
            $clave = Area_Profesores_Google::generar_password();
            $r = Area_Profesores_Google::cambiar_password($email, $clave);
            if (is_wp_error($r)) {
                self::volver($id, 'error', $r->get_error_message());
            }
            $row['oficina']['google'][$email]['clave'] = self::cifrar(array('c' => $clave));
            unset($row['oficina']['google'][$email]['entregada']);
            self::anotar($row, sprintf(__('Contraseña nueva para %s (la anterior ya no vale)', 'area-profesores'), $email));
            self::guardar_fila($row);
            self::volver($id, 'success', sprintf(__('Contraseña nueva para %s. Imprime otra vez la hoja de credenciales.', 'area-profesores'), $email));
        }

        if ($op === 'google_entregadas') {
            self::borrar_claves($id);
            self::volver($id, 'success', __('Credenciales marcadas como entregadas: la contraseña temporal se ha borrado de la web.', 'area-profesores'));
        }

        if ($op === 'alta') {
            $r = self::dar_de_alta($id);
            if (is_wp_error($r)) {
                self::volver($id, 'error', $r->get_error_message());
            }
            self::volver($id, 'success', __('Familia y alumnos dados de alta en Alumnos y familias.', 'area-profesores') . ' ' . implode('; ', $r['resumen']));
        }

        if ($op === 'borrar' && current_user_can('manage_options')) {
            global $wpdb;
            $wpdb->delete(self::t(), array('id' => $id));
            self::volver(0, 'success', sprintf(__('Solicitud %s eliminada.', 'area-profesores'), $row['referencia']));
        }

        self::volver($id, 'error', __('Acción no válida.', 'area-profesores'));
    }

    /** Paper documents checked by Secretaría. */
    public static function documentos() {
        return array(
            'libro_familia' => __('Libro de familia / partida de nacimiento (original comprobado)', 'area-profesores'),
            'dni'           => __('DNI / NIE / pasaporte de los padres o tutores (originales comprobados)', 'area-profesores'),
            'ficha'         => __('Ficha de matrícula firmada por los dos padres o tutores', 'area-profesores'),
            'sepa'          => __('Orden SEPA firmada (o pago por ventanilla)', 'area-profesores'),
            'pago'          => __('Matrícula pagada', 'area-profesores'),
        );
    }

    /* ---------------------------------------------------------------------
     * Cuentas de Google de las familias (fase 3)
     * ------------------------------------------------------------------- */

    /**
     * State of each account of the request:
     *   familia   = already the account of a family of the school (siblings): nothing to create
     *   creada    = created by this website (temporary password kept until it is delivered)
     *   existente = it already existed in Google and Secretaría confirmed it is this family's
     *   pendiente = not created yet
     *
     * @return array email => { estado, fecha, quien, clave (bool), entregada }
     */
    public static function estado_cuentas($m) {
        $usadas = self::cuentas_usadas((int) $m['id']);
        $g = isset($m['oficina']['google']) ? (array) $m['oficina']['google'] : array();
        $out = array();
        foreach (isset($m['oficina']['cuentas']) ? (array) $m['oficina']['cuentas'] : array() as $email) {
            if (isset($g[$email]['estado'])) {
                $x = $g[$email];
                $out[$email] = array('estado' => $x['estado'], 'fecha' => isset($x['fecha']) ? $x['fecha'] : '', 'quien' => isset($x['quien']) ? $x['quien'] : '', 'clave' => !empty($x['clave']), 'entregada' => isset($x['entregada']) ? $x['entregada'] : '');
            } elseif (isset($usadas[$email]) && $usadas[$email]['tipo'] === 'familia') {
                $out[$email] = array('estado' => 'familia', 'fecha' => '', 'quien' => '', 'clave' => false, 'entregada' => '');
            } else {
                $out[$email] = array('estado' => 'pendiente', 'fecha' => '', 'quien' => '', 'clave' => false, 'entregada' => '');
            }
        }
        return $out;
    }

    public static function cuentas_sin_crear($m) {
        return array_keys(array_filter(self::estado_cuentas($m), function ($c) {
            return $c['estado'] === 'pendiente';
        }));
    }

    public static function alguna_creada($m) {
        foreach (self::estado_cuentas($m) as $c) {
            if (in_array($c['estado'], array('creada', 'existente'), true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Next free account after $email exists in Google: the initials of the eldest
     * pupil's name are added (keeping the "1" of separated parents), checking the
     * school's families, the other requests and Google.
     */
    public static function siguiente_libre($m, $email) {
        $dom = '@' . self::dominio();
        $local = substr($email, 0, strpos($email, '@'));
        $local = preg_replace('/\.' . preg_quote(self::SUFIJO, '/') . '$/', '', $local);
        $sep = substr($local, -1) === '1' ? '1' : '';
        $alumnos = $m['datos']['alumnos'];
        usort($alumnos, function ($a, $b) {
            return strcmp($a['fecha_nacimiento'], $b['fecha_nacimiento']);
        });
        $base = self::base_cuenta($alumnos[0]);
        $nombre = self::slug(preg_split('/\s+/', trim((string) $alumnos[0]['nombre']))[0]);
        $usadas = self::cuentas_usadas((int) $m['id']);
        $cands = array();
        for ($i = 1; $i <= strlen($nombre); $i++) {
            $cands[] = $base . '.' . substr($nombre, 0, $i);
        }
        for ($n = 2; $n < 30; $n++) {
            $cands[] = $base . '.' . substr($nombre, 0, 1) . $n;
        }
        foreach ($cands as $c) {
            $e = $c . $sep . '.' . self::SUFIJO . $dom;
            if ($e === $email || isset($usadas[$e])) {
                continue;
            }
            // Free, or it cannot be checked (then creating it will tell).
            if (Area_Profesores_Google::usuario_existe($e) !== true) {
                return $e;
            }
        }
        return '';
    }

    /**
     * Create in Google the accounts of the request that are not created yet.
     * If one already exists in Google (and it is not a family of the school), it
     * is NOT touched: the next free account is proposed and Secretaría decides.
     *
     * @return array { creadas: [], conflicto: string|'', errores: [] }
     */
    public static function crear_cuentas($id) {
        $m = self::get($id);
        $out = array('creadas' => array(), 'conflicto' => '', 'errores' => array());
        if (!$m || !$m['datos']) {
            $out['errores'][] = __('No se pueden leer los datos de la solicitud.', 'area-profesores');
            return $out;
        }
        if (!Area_Profesores_Google::familias_conectada()) {
            $out['errores'][] = __('No está conectada la cuenta que crea las cuentas de las familias (Área Profesores › Aulas).', 'area-profesores');
            return $out;
        }
        foreach (self::cuentas_sin_crear($m) as $email) {
            $existe = Area_Profesores_Google::usuario_existe($email);
            if (is_wp_error($existe) && $existe->get_error_code() === 'google' && strpos($existe->get_error_message(), 'HTTP 403') !== 0) {
                $out['errores'][] = $existe->get_error_message();
                continue;
            }
            $clave = Area_Profesores_Google::generar_password();
            $r = $existe === true ? new WP_Error('existe', '') : Area_Profesores_Google::crear_usuario($email, __('Familia', 'area-profesores'), $m['familia'], $clave);
            if (is_wp_error($r) && $r->get_error_code() === 'existe') {
                $alt = self::siguiente_libre($m, $email);
                $m['oficina']['google_conflicto'] = array('cuenta' => $email, 'propuesta' => $alt);
                self::anotar($m, sprintf(__('La cuenta %1$s ya existe en Google y no es de ninguna familia de la web: no se ha tocado. Propuesta: %2$s', 'area-profesores'), $email, $alt !== '' ? $alt : '—'));
                $out['conflicto'] = $email;
                break;
            }
            if (is_wp_error($r)) {
                $out['errores'][] = $r->get_error_message();
                continue;
            }
            $m['oficina']['google'][$email] = array(
                'estado' => 'creada',
                'fecha'  => Area_Profesores_Alumnos::now(),
                'quien'  => wp_get_current_user()->display_name,
                'clave'  => self::cifrar(array('c' => $clave)),
            );
            self::anotar($m, sprintf(__('Cuenta de Google creada: %s', 'area-profesores'), $email));
            $out['creadas'][] = $email;
        }
        self::guardar_fila($m);
        return $out;
    }

    public static function texto_crear($r) {
        $t = array();
        if ($r['creadas']) {
            $t[] = sprintf(__('Cuenta creada en Google: %s. Ya puedes imprimir la hoja de credenciales.', 'area-profesores'), implode(', ', $r['creadas']));
        }
        if ($r['conflicto'] !== '') {
            $t[] = sprintf(__('La cuenta %s ya existe en Google: no se ha creado nada. Revisa el aviso de la solicitud.', 'area-profesores'), $r['conflicto']);
        }
        foreach ($r['errores'] as $e) {
            $t[] = $e;
        }
        return implode(' ', $t);
    }

    /** Temporary password of a created account (until it is delivered), or ''. */
    public static function clave_de($m, $email) {
        $c = isset($m['oficina']['google'][$email]['clave']) ? $m['oficina']['google'][$email]['clave'] : '';
        if ($c === '') {
            return '';
        }
        $v = self::descifrar($c);
        return isset($v['c']) && is_string($v['c']) ? $v['c'] : '';
    }

    /** Address of the families' website (for the credentials sheet). */
    public static function url_familias() {
        $g = Area_Profesores_Google::familias();
        if (!empty($g['familias_url'])) {
            return $g['familias_url'];
        }
        $u = (string) get_option('ap_familias_login_url', '');
        if ($u === '') {
            $u = (string) strtok(Area_Profesores_Reservas::panel_familias(), '#');
        }
        return $u;
    }

    /**
     * Add the families of these pupils to the Chat space of their class (only
     * pupils "de alta": the ones who have not started wait for their date).
     *
     * @return string[] result lines
     */
    public static function anadir_a_espacios($alumno_ids) {
        $A = 'Area_Profesores_Alumnos';
        $lineas = array();
        if (!$alumno_ids || !Area_Profesores_Google::puede_anadir_miembros()) {
            return $lineas;
        }
        $espacios = array();
        foreach (Area_Profesores_Chat::get_espacios() as $e) {
            if ($e['curso'] !== '' && $e['space_id'] !== '' && $e['activo'] && !$e['archivada'] && !isset($espacios[$e['curso']])) {
                $espacios[$e['curso']] = $e;
            }
        }
        $familias = $A::familias_de_alumnos($alumno_ids);
        foreach ($alumno_ids as $aid) {
            $al = $A::get_alumno($aid);
            if (!$al || $al['estado'] !== 'alta' || !isset($espacios[$al['curso']])) {
                continue;
            }
            $esp = $espacios[$al['curso']];
            foreach (isset($familias[$aid]) ? $familias[$aid] : array() as $f) {
                foreach ($A::emails_array($f['emails']) as $email) {
                    $r = Area_Profesores_Google::anadir_familia_a_espacio($esp['space_id'], $email);
                    $txt = is_wp_error($r)
                        ? sprintf(__('No se ha podido añadir %1$s al espacio %2$s: %3$s', 'area-profesores'), $email, $esp['titulo'], $r->get_error_message())
                        : sprintf(__('%1$s añadida al espacio de Google Chat %2$s', 'area-profesores'), $email, $esp['titulo']);
                    $A::log('matricula', 'alumno', $aid, 'chat', $txt);
                    $lineas[] = $txt;
                }
            }
        }
        return $lineas;
    }

    /** Pupils who start today: their families enter the class space. */
    public static function al_activar($ids) {
        self::anadir_a_espacios(array_map('intval', (array) $ids));
    }

    /* ---------------------------------------------------------------------
     * Alta en «Alumnos y familias» (fase 2)
     * ------------------------------------------------------------------- */

    /** First day by default: 1 September of the school year of the request ("2027/2028" → 2027-09-01). */
    public static function fecha_inicio_curso($curso_escolar) {
        if (preg_match('/^(\d{4})/', (string) $curso_escolar, $m)) {
            $f = $m[1] . '-09-01';
            return $f > current_time('Y-m-d') ? $f : current_time('Y-m-d');
        }
        return current_time('Y-m-d');
    }

    /** Incorporation date of a pupil of the request (Secretaría's date or the default). */
    public static function fecha_ingreso($m, $i) {
        $f = isset($m['oficina']['alumnos'][$i]['fecha_ingreso']) ? $m['oficina']['alumnos'][$i]['fecha_ingreso'] : '';
        return $f !== '' ? $f : self::fecha_inicio_curso($m['curso_escolar']);
    }

    public static function alta_hecha($m) {
        return !empty($m['oficina']['alta']['alumnos']);
    }

    /**
     * What is missing before registering the family (empty = ready).
     *
     * @return string[]
     */
    public static function faltan_para_alta($m) {
        $faltan = array();
        if (!$m['datos']) {
            return array(__('No se pueden leer los datos de la solicitud.', 'area-profesores'));
        }
        $cuentas = isset($m['oficina']['cuentas']) ? array_filter((array) $m['oficina']['cuentas']) : array();
        if (!$cuentas) {
            $faltan[] = __('Escribe la cuenta de la familia en «Cuenta de la familia en Google».', 'area-profesores');
        } elseif (Area_Profesores_Google::familias_conectada() && self::cuentas_sin_crear($m)) {
            $faltan[] = sprintf(__('Crea primero la cuenta en Google (%s).', 'area-profesores'), implode(', ', self::cuentas_sin_crear($m)));
        }
        foreach ($m['datos']['alumnos'] as $i => $a) {
            $grupo = isset($m['oficina']['alumnos'][$i]['grupo']) ? $m['oficina']['alumnos'][$i]['grupo'] : '';
            if (Area_Profesores_Cursos::curso_de_nivel($a['nivel'], $grupo) === '') {
                $faltan[] = sprintf(__('Elige el grupo (A o B) de %s.', 'area-profesores'), self::nombre_alumno($a));
            }
        }
        return $faltan;
    }

    /** "Calle de Alcalá 200, 3º B · 28028 Madrid (Madrid)" */
    private static function direccion_txt($dom) {
        $l1 = trim($dom['calle'] . ' ' . $dom['numero'] . ($dom['piso'] !== '' || $dom['puerta'] !== '' ? ', ' . trim($dom['piso'] . ' ' . $dom['puerta']) : ''));
        $l2 = trim($dom['cp'] . ' ' . $dom['localidad'] . ($dom['provincia'] !== '' && $dom['provincia'] !== $dom['localidad'] ? ' (' . $dom['provincia'] . ')' : ''));
        return trim($l1 . ($l2 !== '' ? ' · ' . $l2 : ''), ' ·');
    }

    /**
     * Registers the family and its pupils in "Alumnos y familias":
     * - one family per account (separated parents: two families, each with its
     *   parent); if the account already belongs to a family of the school
     *   (siblings), the pupils are linked to it and its details are not changed;
     * - the pupils, in their course (level + group), "pendiente de incorporación"
     *   until their incorporation date (then "alta" by themselves), with their
     *   allergies and, if the family does not authorise them, a note about images.
     *
     * @return array|WP_Error summary
     */
    public static function dar_de_alta($id) {
        $m = self::get($id);
        if (!$m) {
            return new WP_Error('ap_matricula', __('Esa solicitud ya no existe.', 'area-profesores'));
        }
        if (self::alta_hecha($m)) {
            return new WP_Error('ap_matricula', __('Esta familia ya está dada de alta.', 'area-profesores'));
        }
        $faltan = self::faltan_para_alta($m);
        if ($faltan) {
            return new WP_Error('ap_matricula', __('Antes de dar de alta:', 'area-profesores') . ' ' . implode(' ', $faltan));
        }
        $A = 'Area_Profesores_Alumnos';
        $d = $m['datos'];
        $hoy = current_time('Y-m-d');
        $dir = self::direccion_txt($d['domicilio']);
        $contacto = function ($t) use ($dir) {
            return array('nombre' => trim($t['nombre'] . ' ' . $t['apellidos']), 'telefono' => $t['movil'], 'direccion' => $dir);
        };
        $cuentas = array_values(array_filter((array) $m['oficina']['cuentas']));
        $nombre_familia = $m['familia'];

        // Families: separated parents → one per account, each with its parent.
        // Every account is a family (the pupils go in all of them). Separated
        // parents: each new family with its own parent.
        $separar = !empty($d['separados']) && count($cuentas) >= 2 && count($d['tutores']) >= 2;
        $grupos = array();
        foreach ($cuentas as $i => $cuenta) {
            $grupos[] = array($cuenta, ($separar && isset($d['tutores'][$i])) ? array($contacto($d['tutores'][$i])) : array_map($contacto, $d['tutores']));
        }
        $familias = array();
        $existentes = array();
        foreach ($grupos as $g) {
            $ya = $A::familias_por_email($g[0]);
            if ($ya) {
                $familias[] = (int) $ya[0]['id'];
                $existentes[] = (int) $ya[0]['id'];
                continue;
            }
            $fid = $A::guardar_familia(array('nombre' => $nombre_familia, 'emails' => $g[0], 'telefono' => '', 'contactos' => $g[1]), 0, 'matricula');
            if (!$fid) {
                return new WP_Error('ap_matricula', __('No se ha podido crear la familia.', 'area-profesores'));
            }
            $familias[] = (int) $fid;
        }

        // Pupils
        $sin_imagen = (isset($d['aceptaciones']['manifestaciones']['m4']) && $d['aceptaciones']['manifestaciones']['m4'] === 'no')
            || (isset($d['aceptaciones']['rgpd']['rgpd_imagenes']) && $d['aceptaciones']['rgpd']['rgpd_imagenes'] === 'no');
        $alumnos = array();
        $resumen = array();
        foreach ($d['alumnos'] as $i => $a) {
            $grupo = isset($m['oficina']['alumnos'][$i]['grupo']) ? $m['oficina']['alumnos'][$i]['grupo'] : '';
            $curso = Area_Profesores_Cursos::curso_de_nivel($a['nivel'], $grupo);
            $fecha = self::fecha_ingreso($m, $i);
            $notas = array(sprintf(__('Alta desde la matrícula online %s.', 'area-profesores'), $m['referencia']));
            if ($sin_imagen) {
                $notas[] = __('La familia NO autoriza el uso de su imagen (ver la solicitud de matrícula).', 'area-profesores');
            }
            $aid = $A::guardar_alumno(array(
                'nombre'                => self::nombre_alumno($a),
                'email'                 => '',
                'curso'                 => $curso,
                'estado'                => $fecha > $hoy ? 'pendiente' : 'alta',
                'fecha_alta'            => $fecha,
                'notas'                 => implode("\n", $notas),
                'alergias_alimentarias' => $a['alergias_alimentarias'],
                'alergias_otras'        => $a['alergias_otras'],
            ), 0, 'matricula');
            foreach ($familias as $fid) {
                $A::vincular($aid, $fid, 'matricula');
            }
            $alumnos[] = (int) $aid;
            $c = Area_Profesores_Cursos::get_curso($curso);
            $resumen[] = self::nombre_alumno($a) . ' → ' . ($c ? $c['label'] : $curso) . ($fecha > $hoy ? ' · ' . sprintf(__('se incorpora el %s', 'area-profesores'), self::f($fecha)) : '');
        }

        $m['oficina']['alta'] = array(
            'fecha'      => Area_Profesores_Alumnos::now(),
            'quien'      => wp_get_current_user()->display_name,
            'familias'   => $familias,
            'existentes' => $existentes,
            'alumnos'    => $alumnos,
            'resumen'    => $resumen,
        );
        self::anotar($m, __('Dada de alta en Alumnos y familias', 'area-profesores') . ': ' . implode('; ', $resumen) . ($existentes ? ' · ' . __('vinculados a una familia que ya estaba en el colegio', 'area-profesores') : ''));
        // Families of pupils who start now enter the Chat space of their class.
        foreach (self::anadir_a_espacios($alumnos) as $l) {
            self::anotar($m, $l);
        }
        self::guardar_fila($m);
        return $m['oficina']['alta'];
    }

    /* ---------------------------------------------------------------------
     * Pestaña e impresión
     * ------------------------------------------------------------------- */

    /**
     * Data for the "Nuevas familias" tab.
     */
    public static function datos_portal($user_id) {
        if (!self::puede_ver($user_id)) {
            return null;
        }
        $estado = isset($_GET['ap_mat_estado']) ? sanitize_key(wp_unslash($_GET['ap_mat_estado'])) : 'pendientes';
        $id = isset($_GET['ap_mat']) ? absint($_GET['ap_mat']) : 0;
        $detalle = $id ? self::get($id) : null;
        $lista = array();
        if (!$detalle) {
            if ($estado === 'pendientes') {
                $lista = array_values(array_filter(self::listar(), function ($r) {
                    return in_array($r['estado'], array('nueva', 'revision', 'aprobada'), true);
                }));
            } else {
                $lista = self::listar($estado === 'todas' ? '' : $estado);
            }
        }
        return array(
            'estado'     => $estado,
            'lista'      => $lista,
            'contadores' => self::contar_por_estado(),
            'pendientes' => self::contar_pendientes(),
            'detalle'    => $detalle,
            'hermanos'   => ($detalle && $detalle['datos']) ? self::analizar_hermanos($detalle['datos']) : null,
            'flash'      => self::flash(),
            'borrar'     => current_user_can('manage_options'),
        );
    }

    public function render_imprimir() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!self::puede_ver(get_current_user_id()) || !wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'ap_matricula_imprimir_' . $id)) {
            wp_die(esc_html__('No tienes permisos para ver esta solicitud.', 'area-profesores'));
        }
        $m = self::get($id);
        if (!$m || !$m['datos']) {
            wp_die(esc_html__('No se puede leer esta solicitud.', 'area-profesores'));
        }
        $logo_url = Area_Profesores_Admin::get_setting('logo_url', '');
        nocache_headers();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/matricula-imprimir.php';
        exit;
    }

    /** The temporary passwords are deleted once the credentials are delivered. */
    public static function borrar_claves($id) {
        $m = self::get($id);
        if (!$m || empty($m['oficina']['google'])) {
            return;
        }
        $hay = false;
        foreach ($m['oficina']['google'] as $email => $g) {
            if (!empty($g['clave'])) {
                $m['oficina']['google'][$email]['clave'] = '';
                $m['oficina']['google'][$email]['entregada'] = Area_Profesores_Alumnos::now();
                $hay = true;
            }
        }
        if ($hay) {
            self::anotar($m, __('Credenciales entregadas: contraseña temporal borrada de la web', 'area-profesores'));
            self::guardar_fila($m);
        }
    }

    public function render_credenciales() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!self::puede_ver(get_current_user_id()) || !wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'ap_matricula_credenciales_' . $id)) {
            wp_die(esc_html__('No tienes permisos para ver esta solicitud.', 'area-profesores'));
        }
        $m = self::get($id);
        if (!$m || !$m['datos']) {
            wp_die(esc_html__('No se puede leer esta solicitud.', 'area-profesores'));
        }
        $cuentas = self::estado_cuentas($m);
        $claves = array();
        foreach ($cuentas as $email => $c) {
            $claves[$email] = $c['estado'] === 'creada' ? self::clave_de($m, $email) : '';
        }
        $logo_url = Area_Profesores_Admin::get_setting('logo_url', '');
        $familias_url = self::url_familias();
        nocache_headers();
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/credenciales-imprimir.php';
        exit;
    }

    public static function url_credenciales($id) {
        return wp_nonce_url(admin_url('admin-post.php?action=ap_matricula_credenciales&id=' . (int) $id), 'ap_matricula_credenciales_' . (int) $id);
    }

    /** "1234 5678 …" groups of four. */
    public static function iban_formato($iban) {
        return trim(chunk_split((string) $iban, 4, ' '));
    }

    /** Date Y-m-d → d/m/Y. */
    public static function f($fecha) {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $fecha, $m) ? $m[3] . '/' . $m[2] . '/' . $m[1] : (string) $fecha;
    }
}
