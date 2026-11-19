<?php
/**
 * Alumnos y familias (datos del colegio).
 *
 * Tables:
 * - ap_alumnos            pupils (name, school e-mail, course, alta/baja)
 * - ap_familias           families (name, family e-mails shared by the parents)
 * - ap_familia_contactos  parents/guardians of each family (name, own emergency phone, address)
 * - ap_alumno_familia     pupil ↔ family (many to many: separated parents = 2 families)
 * - ap_alumnos_historial  who changed what and when
 *
 * The data comes from Alexia (Excel import) and can be edited in the backend.
 * Families can update their address and emergency phone from the Área Familias;
 * the tutor(s) and Secretaría are notified by e-mail.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Alumnos {

    const META_SECRETARIA = '_ap_is_secretaria';
    const META_CLASES     = '_ap_cursos_asignados';

    /* ---------------------------------------------------------------------
     * Tablas
     * ------------------------------------------------------------------- */

    public static function t($name) {
        global $wpdb;
        return $wpdb->prefix . 'ap_' . $name;
    }

    public static function create_tables() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE " . self::t('alumnos') . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre VARCHAR(200) NOT NULL DEFAULT '',
            email VARCHAR(190) DEFAULT NULL,
            curso VARCHAR(20) NOT NULL DEFAULT '',
            estado VARCHAR(10) NOT NULL DEFAULT 'alta',
            fecha_baja DATE DEFAULT NULL,
            notas TEXT DEFAULT NULL,
            creado DATETIME DEFAULT NULL,
            actualizado DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY curso (curso, estado)
        ) $c;");

        dbDelta("CREATE TABLE " . self::t('familias') . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre VARCHAR(200) NOT NULL DEFAULT '',
            emails TEXT DEFAULT NULL,
            telefono VARCHAR(255) NOT NULL DEFAULT '',
            notas TEXT DEFAULT NULL,
            editado_por_familia DATETIME DEFAULT NULL,
            creado DATETIME DEFAULT NULL,
            actualizado DATETIME DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $c;");

        dbDelta("CREATE TABLE " . self::t('familia_contactos') . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            familia_id BIGINT(20) UNSIGNED NOT NULL,
            nombre VARCHAR(200) NOT NULL DEFAULT '',
            telefono VARCHAR(100) NOT NULL DEFAULT '',
            direccion VARCHAR(255) NOT NULL DEFAULT '',
            orden INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY familia (familia_id)
        ) $c;");

        dbDelta("CREATE TABLE " . self::t('alumno_familia') . " (
            alumno_id BIGINT(20) UNSIGNED NOT NULL,
            familia_id BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (alumno_id, familia_id),
            KEY familia (familia_id)
        ) $c;");

        dbDelta("CREATE TABLE " . self::t('alumnos_historial') . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            fecha DATETIME NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            origen VARCHAR(20) NOT NULL DEFAULT '',
            objeto VARCHAR(20) NOT NULL DEFAULT '',
            objeto_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            accion VARCHAR(30) NOT NULL DEFAULT '',
            detalle TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY objeto (objeto, objeto_id),
            KEY fecha (fecha)
        ) $c;");
    }

    public static function now() {
        return current_time('mysql');
    }

    /* ---------------------------------------------------------------------
     * Utilidades
     * ------------------------------------------------------------------- */

    public static function norm_email($e) {
        $e = strtolower(trim((string) $e));
        return is_email($e) ? $e : '';
    }

    /**
     * "a@x, b@x" ⇄ array
     */
    public static function emails_array($s) {
        $out = array();
        foreach (preg_split('/[\s,;]+/', (string) $s) as $e) {
            $e = self::norm_email($e);
            if ($e !== '' && !in_array($e, $out, true)) {
                $out[] = $e;
            }
        }
        return $out;
    }

    public static function norm_nombre($s) {
        $s = remove_accents(mb_strtolower(trim((string) $s)));
        return preg_replace('/\s+/', ' ', $s);
    }

    public static function log($origen, $objeto, $objeto_id, $accion, $detalle = '') {
        global $wpdb;
        $wpdb->insert(self::t('alumnos_historial'), array(
            'fecha'     => self::now(),
            'user_id'   => get_current_user_id(),
            'origen'    => $origen,
            'objeto'    => $objeto,
            'objeto_id' => (int) $objeto_id,
            'accion'    => $accion,
            'detalle'   => is_array($detalle) ? wp_json_encode($detalle, JSON_UNESCAPED_UNICODE) : (string) $detalle,
        ));
    }

    /* ---------------------------------------------------------------------
     * Lectura
     * ------------------------------------------------------------------- */

    public static function get_alumno($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::t('alumnos') . ' WHERE id = %d', $id), ARRAY_A);
    }

    public static function get_familia($id) {
        global $wpdb;
        $f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::t('familias') . ' WHERE id = %d', $id), ARRAY_A);
        if ($f) {
            $f['contactos'] = self::get_contactos($id);
        }
        return $f;
    }

    public static function get_contactos($familia_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t('familia_contactos') . ' WHERE familia_id = %d ORDER BY orden, id', $familia_id), ARRAY_A);
    }

    /**
     * Pupils, with filters: curso (string|array), estado, busqueda.
     */
    public static function buscar_alumnos($args = array()) {
        global $wpdb;
        $where = array('1=1');
        $params = array();
        if (!empty($args['curso'])) {
            $cursos = (array) $args['curso'];
            $where[] = 'curso IN (' . implode(',', array_fill(0, count($cursos), '%s')) . ')';
            $params = array_merge($params, $cursos);
        }
        if (!empty($args['estado'])) {
            $where[] = 'estado = %s';
            $params[] = $args['estado'];
        }
        if (!empty($args['busqueda'])) {
            $like = '%' . $wpdb->esc_like($args['busqueda']) . '%';
            $where[] = '(nombre LIKE %s OR email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        $sql = 'SELECT * FROM ' . self::t('alumnos') . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY nombre';
        $rows = $wpdb->get_results($params ? $wpdb->prepare($sql, $params) : $sql, ARRAY_A);
        return self::ordenar_por_curso($rows);
    }

    /**
     * Course order of the school, then name.
     */
    public static function ordenar_por_curso($rows) {
        $orden = array();
        foreach (Area_Profesores_Cursos::get_cursos() as $k => $c) {
            $orden[$k] = $c['orden'];
        }
        usort($rows, function ($a, $b) use ($orden) {
            $oa = isset($orden[$a['curso']]) ? $orden[$a['curso']] : 999;
            $ob = isset($orden[$b['curso']]) ? $orden[$b['curso']] : 999;
            return $oa === $ob ? strnatcasecmp($a['nombre'], $b['nombre']) : $oa - $ob;
        });
        return $rows;
    }

    public static function buscar_familias($busqueda = '') {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::t('familias');
        if ($busqueda !== '') {
            $like = '%' . $wpdb->esc_like($busqueda) . '%';
            $sql .= $wpdb->prepare(' WHERE nombre LIKE %s OR emails LIKE %s OR telefono LIKE %s OR id IN (SELECT familia_id FROM ' . self::t('familia_contactos') . ' WHERE nombre LIKE %s)', $like, $like, $like, $like);
        }
        return $wpdb->get_results($sql . ' ORDER BY nombre', ARRAY_A);
    }

    /**
     * Families of each pupil: alumno_id => array of familias (with contactos).
     */
    public static function familias_de_alumnos($ids) {
        global $wpdb;
        $ids = array_filter(array_map('intval', (array) $ids));
        if (!$ids) {
            return array();
        }
        $rows = $wpdb->get_results('SELECT af.alumno_id, f.* FROM ' . self::t('alumno_familia') . ' af JOIN ' . self::t('familias') . ' f ON f.id = af.familia_id WHERE af.alumno_id IN (' . implode(',', $ids) . ') ORDER BY f.nombre', ARRAY_A);
        $fam_ids = array_unique(wp_list_pluck($rows, 'id'));
        $contactos = array();
        if ($fam_ids) {
            foreach ($wpdb->get_results('SELECT * FROM ' . self::t('familia_contactos') . ' WHERE familia_id IN (' . implode(',', array_map('intval', $fam_ids)) . ') ORDER BY orden, id', ARRAY_A) as $ct) {
                $contactos[$ct['familia_id']][] = $ct;
            }
        }
        $out = array();
        foreach ($rows as $r) {
            $r['contactos'] = isset($contactos[$r['id']]) ? $contactos[$r['id']] : array();
            $out[$r['alumno_id']][] = $r;
        }
        return $out;
    }

    /**
     * Pupils of each family: familia_id => array of alumnos.
     */
    public static function alumnos_de_familias($ids, $solo_alta = false) {
        global $wpdb;
        $ids = array_filter(array_map('intval', (array) $ids));
        if (!$ids) {
            return array();
        }
        $sql = 'SELECT af.familia_id, a.* FROM ' . self::t('alumno_familia') . ' af JOIN ' . self::t('alumnos') . ' a ON a.id = af.alumno_id WHERE af.familia_id IN (' . implode(',', $ids) . ')';
        if ($solo_alta) {
            $sql .= " AND a.estado = 'alta'";
        }
        $out = array();
        foreach (self::ordenar_por_curso($wpdb->get_results($sql, ARRAY_A)) as $r) {
            $out[$r['familia_id']][] = $r;
        }
        return $out;
    }

    /**
     * Families that use this e-mail (a family can have several e-mails).
     */
    public static function familias_por_email($email) {
        global $wpdb;
        $email = self::norm_email($email);
        if ($email === '') {
            return array();
        }
        $like = '%' . $wpdb->esc_like($email) . '%';
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t('familias') . ' WHERE emails LIKE %s', $like), ARRAY_A);
        return array_values(array_filter($rows, function ($f) use ($email) {
            return in_array($email, self::emails_array($f['emails']), true);
        }));
    }

    public static function contar() {
        global $wpdb;
        return array(
            'alumnos'  => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::t('alumnos') . " WHERE estado = 'alta'"),
            'bajas'    => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::t('alumnos') . " WHERE estado = 'baja'"),
            'familias' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::t('familias')),
        );
    }

    /* ---------------------------------------------------------------------
     * Escritura
     * ------------------------------------------------------------------- */

    public static function guardar_alumno($data, $id = 0, $origen = 'backend') {
        global $wpdb;
        $row = array(
            'nombre' => sanitize_text_field($data['nombre']),
            'email'  => self::norm_email(isset($data['email']) ? $data['email'] : '') ?: null,
            'curso'  => Area_Profesores_Cursos::get_curso(isset($data['curso']) ? $data['curso'] : '') ? $data['curso'] : '',
            'estado' => (isset($data['estado']) && $data['estado'] === 'baja') ? 'baja' : 'alta',
            'actualizado' => self::now(),
        );
        if (array_key_exists('notas', $data)) {
            $row['notas'] = sanitize_textarea_field($data['notas']);
        }

        if ($id) {
            $old = self::get_alumno($id);
            if ($row['estado'] === 'baja' && $old && $old['estado'] !== 'baja') {
                $row['fecha_baja'] = current_time('Y-m-d');
            } elseif ($row['estado'] === 'alta') {
                $row['fecha_baja'] = null;
            }
            $wpdb->update(self::t('alumnos'), $row, array('id' => $id));
            $cambios = self::diferencias($old, $row, array('nombre', 'email', 'curso', 'estado'));
            if ($cambios) {
                self::log($origen, 'alumno', $id, 'editar', $cambios);
            }
            return $id;
        }

        $row['creado'] = self::now();
        if ($row['estado'] === 'baja') {
            $row['fecha_baja'] = current_time('Y-m-d');
        }
        $wpdb->insert(self::t('alumnos'), $row);
        $id = (int) $wpdb->insert_id;
        self::log($origen, 'alumno', $id, 'alta', array('nombre' => $row['nombre'], 'curso' => $row['curso']));
        return $id;
    }

    /**
     * $data: nombre, emails (string|array), telefono, notas, contactos (array of nombre/direccion[/id])
     */
    public static function guardar_familia($data, $id = 0, $origen = 'backend') {
        global $wpdb;
        $row = array(
            'nombre'   => sanitize_text_field($data['nombre']),
            'emails'   => implode(', ', self::emails_array(is_array($data['emails']) ? implode(',', $data['emails']) : $data['emails'])),
            'telefono' => sanitize_text_field($data['telefono']),
            'actualizado' => self::now(),
        );
        if (array_key_exists('notas', $data)) {
            $row['notas'] = sanitize_textarea_field($data['notas']);
        }

        if ($id) {
            $old = self::get_familia($id);
            $wpdb->update(self::t('familias'), $row, array('id' => $id));
            $cambios = self::diferencias($old, $row, array('nombre', 'emails', 'telefono'));
        } else {
            $row['creado'] = self::now();
            $wpdb->insert(self::t('familias'), $row);
            $id = (int) $wpdb->insert_id;
            $old = null;
            $cambios = array();
        }

        if (isset($data['contactos'])) {
            $antes = $old ? $old['contactos'] : array();
            $wpdb->delete(self::t('familia_contactos'), array('familia_id' => $id));
            $orden = 0;
            $despues = array();
            foreach ((array) $data['contactos'] as $ct) {
                $nombre = sanitize_text_field(isset($ct['nombre']) ? $ct['nombre'] : '');
                $tel = sanitize_text_field(isset($ct['telefono']) ? $ct['telefono'] : '');
                $dir = sanitize_text_field(isset($ct['direccion']) ? $ct['direccion'] : '');
                if ($nombre === '' && $dir === '' && $tel === '') {
                    continue;
                }
                $wpdb->insert(self::t('familia_contactos'), array('familia_id' => $id, 'nombre' => $nombre, 'telefono' => $tel, 'direccion' => $dir, 'orden' => $orden++));
                $despues[] = self::contacto_txt(array('nombre' => $nombre, 'telefono' => $tel, 'direccion' => $dir));
            }
            $antes_txt = array_map(array(__CLASS__, 'contacto_txt'), $antes);
            if ($old && $antes_txt !== $despues) {
                $cambios['contactos'] = array(implode(' | ', $antes_txt), implode(' | ', $despues));
            }
        }

        if (!$old) {
            self::log($origen, 'familia', $id, 'alta', array('nombre' => $row['nombre']));
        } elseif ($cambios) {
            self::log($origen, 'familia', $id, 'editar', $cambios);
        }
        return $id;
    }

    /**
     * "Ana García · 600 000 000 · C/ Mayor, 1" (history and comparisons).
     */
    public static function contacto_txt($c) {
        return implode(' · ', array_filter(array(
            isset($c['nombre']) ? $c['nombre'] : '',
            isset($c['telefono']) ? $c['telefono'] : '',
            isset($c['direccion']) ? $c['direccion'] : '',
        ), 'strlen'));
    }

    /**
     * Parents of a family with a phone, for the lists: array of {nombre, telefono}.
     * Families imported before phones were per parent keep their old family phone.
     */
    public static function contactos_con_telefono($familia) {
        $out = array();
        foreach (isset($familia['contactos']) ? $familia['contactos'] : array() as $c) {
            $out[] = array('nombre' => $c['nombre'], 'telefono' => isset($c['telefono']) ? $c['telefono'] : '');
        }
        $alguno = array_filter(wp_list_pluck($out, 'telefono'), 'strlen');
        if (!$alguno && !empty($familia['telefono'])) {
            $out[] = array('nombre' => '', 'telefono' => $familia['telefono']);
        }
        return $out;
    }

    public static function vincular($alumno_id, $familia_id, $origen = 'backend') {
        global $wpdb;
        $t = self::t('alumno_familia');
        if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE alumno_id = %d AND familia_id = %d", $alumno_id, $familia_id))) {
            return false;
        }
        $wpdb->insert($t, array('alumno_id' => (int) $alumno_id, 'familia_id' => (int) $familia_id));
        self::log($origen, 'alumno', $alumno_id, 'vincular', array('familia' => (int) $familia_id));
        return true;
    }

    public static function desvincular($alumno_id, $familia_id, $origen = 'backend') {
        global $wpdb;
        $wpdb->delete(self::t('alumno_familia'), array('alumno_id' => (int) $alumno_id, 'familia_id' => (int) $familia_id));
        self::log($origen, 'alumno', $alumno_id, 'desvincular', array('familia' => (int) $familia_id));
    }

    public static function borrar_familia($id) {
        global $wpdb;
        $f = self::get_familia($id);
        $wpdb->delete(self::t('alumno_familia'), array('familia_id' => $id));
        $wpdb->delete(self::t('familia_contactos'), array('familia_id' => $id));
        $wpdb->delete(self::t('familias'), array('id' => $id));
        self::log('backend', 'familia', $id, 'borrar', array('nombre' => $f ? $f['nombre'] : ''));
    }

    public static function borrar_alumno($id) {
        global $wpdb;
        $a = self::get_alumno($id);
        $wpdb->delete(self::t('alumno_familia'), array('alumno_id' => $id));
        $wpdb->delete(self::t('alumnos'), array('id' => $id));
        self::log('backend', 'alumno', $id, 'borrar', array('nombre' => $a ? $a['nombre'] : ''));
    }

    private static function diferencias($old, $new, $campos) {
        $out = array();
        foreach ($campos as $c) {
            $a = $old && isset($old[$c]) ? (string) $old[$c] : '';
            $b = isset($new[$c]) ? (string) $new[$c] : '';
            if ($a !== $b) {
                $out[$c] = array($a, $b);
            }
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Profesores: Secretaría y clases asignadas
     * ------------------------------------------------------------------- */

    public static function es_secretaria($user_id) {
        return get_user_meta($user_id, self::META_SECRETARIA, true) === '1';
    }

    public static function get_secretaria_emails() {
        $users = get_users(array('meta_key' => self::META_SECRETARIA, 'meta_value' => '1', 'fields' => array('user_email')));
        return array_values(array_filter(wp_list_pluck($users, 'user_email')));
    }

    public static function get_clases_asignadas($user_id) {
        $c = get_user_meta($user_id, self::META_CLASES, true);
        return array_values(array_filter((array) $c, function ($k) {
            return (bool) Area_Profesores_Cursos::get_curso($k);
        }));
    }

    public static function set_clases_asignadas($user_id, $cursos) {
        $cursos = array_values(array_unique(array_filter((array) $cursos, function ($k) {
            return (bool) Area_Profesores_Cursos::get_curso($k);
        })));
        update_user_meta($user_id, self::META_CLASES, $cursos);
    }

    /**
     * Courses whose pupils a teacher can see: their tutoría + assigned classes;
     * Dirección, Secretaría and administrators see every course.
     *
     * @return array|true course keys, or true = all
     */
    public static function cursos_visibles($user_id) {
        if (user_can($user_id, 'manage_options') || Area_Profesores_Chat::has_direccion_flag($user_id) || self::es_secretaria($user_id)) {
            return true;
        }
        $cursos = self::get_clases_asignadas($user_id);
        $tutoria = (string) get_user_meta($user_id, Area_Profesores_Cursos::META_CURSO, true);
        if ($tutoria !== '' && Area_Profesores_Cursos::get_curso($tutoria)) {
            array_unshift($cursos, $tutoria);
        }
        return array_values(array_unique($cursos));
    }

    /**
     * Tutors of a course (e-mails), for the notices.
     */
    public static function emails_tutores($curso) {
        $users = get_users(array('meta_key' => Area_Profesores_Cursos::META_CURSO, 'meta_value' => $curso, 'fields' => array('user_email')));
        return array_values(array_filter(wp_list_pluck($users, 'user_email')));
    }

    /**
     * Data for the "Mis alumnos" tab: visible courses with their pupils and families.
     *
     * @return array { cursos: [curso_key => array(label, alumnos)], todos: bool, total }
     */
    public static function datos_mis_alumnos($user_id) {
        $visibles = self::cursos_visibles($user_id);
        $todos = $visibles === true;
        $keys = $todos ? array_keys(Area_Profesores_Cursos::get_cursos()) : $visibles;
        if (!$keys) {
            return array('cursos' => array(), 'todos' => $todos, 'total' => 0);
        }

        $alumnos = self::buscar_alumnos(array('curso' => $keys, 'estado' => 'alta'));
        $familias = self::familias_de_alumnos(wp_list_pluck($alumnos, 'id'));

        $por_curso = array();
        foreach ($keys as $k) {
            $por_curso[$k] = array('label' => Area_Profesores_Cursos::get_curso($k)['label'], 'alumnos' => array());
        }
        foreach ($alumnos as $a) {
            $a['familias'] = isset($familias[$a['id']]) ? $familias[$a['id']] : array();
            $por_curso[$a['curso']]['alumnos'][] = $a;
        }
        if ($todos) {
            // Dirección/Secretaría: only courses with pupils.
            $por_curso = array_filter($por_curso, function ($c) {
                return !empty($c['alumnos']);
            });
        }
        return array('cursos' => $por_curso, 'todos' => $todos, 'total' => count($alumnos));
    }
}
