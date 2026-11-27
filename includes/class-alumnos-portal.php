<?php
/**
 * Teacher Portal: changes to a pupil's data from "Mis alumnos".
 *
 * A teacher who can see a pupil can change their allergies and the name and
 * emergency phone of their parents. A reason is required and an e-mail is sent
 * with the changes and the reason. There are two e-mails (recipients chosen in "Avisos"):
 * - Food allergies: "profesor_alergias" (Cocina and the tutors by default, not Secretaría).
 * - Other allergies: "profesor_alergias_otras" (only the tutors by default).
 * - Parents' names and phones: "profesor_datos" (Secretaría by default).
 * When an allergy changes, the family receives a confirmation ("alergias_familia").
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Alumnos_Portal {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_post_ap_ma_editar', array($this, 'handle_editar'));
    }

    /**
     * Can this user see (and so edit) the pupil?
     */
    public static function puede_editar($user_id, $alumno) {
        if (!$user_id || !$alumno) {
            return false;
        }
        $visibles = Area_Profesores_Alumnos::cursos_visibles($user_id);
        return $visibles === true || in_array($alumno['curso'], (array) $visibles, true);
    }

    /**
     * Data of a pupil for the edit dialog (JSON in the row).
     */
    public static function datos_edicion($alumno) {
        $fams = array();
        foreach ($alumno['familias'] as $f) {
            $cts = array();
            foreach ($f['contactos'] as $c) {
                $cts[] = array('id' => (int) $c['id'], 'nombre' => $c['nombre'], 'telefono' => (string) $c['telefono']);
            }
            $fams[] = array('id' => (int) $f['id'], 'nombre' => $f['nombre'], 'contactos' => $cts);
        }
        return array(
            'id'       => (int) $alumno['id'],
            'nombre'   => $alumno['nombre'],
            'alim'     => (string) $alumno['alergias_alimentarias'],
            'otras'    => (string) $alumno['alergias_otras'],
            'familias' => $fams,
        );
    }

    private static function volver($type, $text, $curso = '') {
        set_transient('ap_ma_flash_' . get_current_user_id(), array('type' => $type, 'text' => $text), 120);
        $back = wp_get_referer() ? wp_get_referer() : home_url('/');
        $back = remove_query_arg('ap_ma', strtok($back, '#'));
        if ($curso !== '') {
            $back = add_query_arg('ap_ma', $curso, $back);
        }
        wp_safe_redirect($back . '#alumnos');
        exit;
    }

    private static function txt($v) {
        $v = trim((string) $v);
        return $v === '' ? __('(vacío)', 'area-profesores') : $v;
    }

    private static function txt_familia($v) {
        $v = trim((string) $v);
        return $v === '' ? __('ninguna', 'area-profesores') : $v;
    }

    public function handle_editar() {
        global $wpdb;
        $uid = get_current_user_id();
        $id = isset($_POST['alumno_id']) ? absint($_POST['alumno_id']) : 0;
        check_admin_referer('ap_ma_editar');

        $A = 'Area_Profesores_Alumnos';
        $alumno = $A::get_alumno($id);
        if (!self::puede_editar($uid, $alumno)) {
            wp_die(esc_html__('No puedes cambiar los datos de este alumno.', 'area-profesores'));
        }
        $curso = $alumno['curso'];
        $motivo = isset($_POST['motivo']) ? trim(sanitize_textarea_field(wp_unslash($_POST['motivo']))) : '';
        if ($motivo === '') {
            self::volver('error', __('Escribe el motivo del cambio: se envía por correo junto con los datos nuevos.', 'area-profesores'), $curso);
        }

        $cambios = array();       // lines for the e-mail to Secretaría (parents)
        $cambios_alim = array();  // food allergies: Cocina and the tutors
        $cambios_otras = array(); // other allergies: the tutors
        $cambios_fam = array();   // allergies, for the family's confirmation
        $log_alumno = array();

        // 1. Allergies of the pupil.
        $nuevo = array();
        foreach (array('alergias_alimentarias' => __('Alergias alimentarias', 'area-profesores'), 'alergias_otras' => __('Alergias no alimentarias', 'area-profesores')) as $k => $label) {
            if (!isset($_POST[$k])) {
                continue;
            }
            $v = trim(sanitize_textarea_field(wp_unslash($_POST[$k])));
            $old = trim((string) $alumno[$k]);
            if ($v !== $old) {
                $nuevo[$k] = $v;
                $log_alumno[$k] = array($old, $v);
                $linea = '- ' . $label . ': ' . self::txt($old) . ' → ' . self::txt($v);
                if ($k === 'alergias_alimentarias') {
                    $cambios_alim[] = $linea;
                } else {
                    $cambios_otras[] = $linea;
                }
                $cambios_fam[] = '- ' . $label . ': ' . self::txt_familia($old) . ' → ' . self::txt_familia($v);
            }
        }

        // 2. Parents of the pupil's families (only those families).
        $familias = $A::familias_de_alumnos(array($id));
        $familias = isset($familias[$id]) ? $familias[$id] : array();
        $hermanos = $A::alumnos_de_familias(wp_list_pluck($familias, 'id'), true);
        $post_ct = isset($_POST['ct']) && is_array($_POST['ct']) ? wp_unslash($_POST['ct']) : array();
        $post_nuevo = isset($_POST['nuevo']) && is_array($_POST['nuevo']) ? wp_unslash($_POST['nuevo']) : array();
        $fam_updates = array();  // familia_id => [ [contact_id|0, nombre, telefono, log] ]

        foreach ($familias as $f) {
            $nota = '';
            $otros = array();
            foreach (isset($hermanos[$f['id']]) ? $hermanos[$f['id']] : array() as $h) {
                if ((int) $h['id'] !== $id) {
                    $otros[] = $h['nombre'];
                }
            }
            $nota = $otros
                ? ' ' . sprintf(__('(familia %1$s; también afecta a: %2$s)', 'area-profesores'), $f['nombre'], implode(', ', $otros))
                : ' ' . sprintf(__('(familia %s)', 'area-profesores'), $f['nombre']);

            foreach ($f['contactos'] as $c) {
                if (!isset($post_ct[$c['id']]) || !is_array($post_ct[$c['id']])) {
                    continue;
                }
                $n = sanitize_text_field(isset($post_ct[$c['id']]['nombre']) ? $post_ct[$c['id']]['nombre'] : $c['nombre']);
                $t = sanitize_text_field(isset($post_ct[$c['id']]['telefono']) ? $post_ct[$c['id']]['telefono'] : $c['telefono']);
                $quien = $c['nombre'] !== '' ? $c['nombre'] : __('padre/madre', 'area-profesores');
                if ($n === '' && $c['nombre'] !== '') {
                    $n = $c['nombre']; // a name cannot be emptied from here
                }
                if ($n !== $c['nombre']) {
                    $cambios[] = '- ' . sprintf(__('Nombre de %1$s: %2$s → %3$s', 'area-profesores'), $quien, self::txt($c['nombre']), self::txt($n)) . $nota;
                }
                if ($t !== (string) $c['telefono']) {
                    $cambios[] = '- ' . sprintf(__('Teléfono de emergencia de %1$s: %2$s → %3$s', 'area-profesores'), $n !== '' ? $n : $quien, self::txt($c['telefono']), self::txt($t)) . $nota;
                }
                if ($n !== $c['nombre'] || $t !== (string) $c['telefono']) {
                    $fam_updates[$f['id']][] = array((int) $c['id'], $n, $t, array($A::contacto_txt($c), $A::contacto_txt(array('nombre' => $n, 'telefono' => $t, 'direccion' => $c['direccion']))));
                }
            }

            // A new parent / guardian.
            if (isset($post_nuevo[$f['id']]) && is_array($post_nuevo[$f['id']])) {
                $n = sanitize_text_field(isset($post_nuevo[$f['id']]['nombre']) ? $post_nuevo[$f['id']]['nombre'] : '');
                $t = sanitize_text_field(isset($post_nuevo[$f['id']]['telefono']) ? $post_nuevo[$f['id']]['telefono'] : '');
                if ($n !== '' || $t !== '') {
                    $cambios[] = '- ' . sprintf(__('Nuevo padre/madre o tutor: %s', 'area-profesores'), $A::contacto_txt(array('nombre' => $n, 'telefono' => $t))) . $nota;
                    $fam_updates[$f['id']][] = array(0, $n, $t, array('', $A::contacto_txt(array('nombre' => $n, 'telefono' => $t))));
                }
            }
        }

        if (!$cambios && !$cambios_alim && !$cambios_otras) {
            self::volver('info', __('No has cambiado ningún dato.', 'area-profesores'), $curso);
        }

        // Save.
        if ($nuevo) {
            $nuevo['actualizado'] = $A::now();
            $wpdb->update($A::t('alumnos'), $nuevo, array('id' => $id));
            $log_alumno['motivo'] = $motivo;
            $A::log('profesor', 'alumno', $id, 'editar', $log_alumno);
        }
        foreach ($fam_updates as $fid => $lista) {
            $log = array();
            foreach ($lista as $u) {
                list($cid, $n, $t, $antes_despues) = $u;
                if ($cid) {
                    $wpdb->update($A::t('familia_contactos'), array('nombre' => $n, 'telefono' => $t), array('id' => $cid, 'familia_id' => $fid));
                } else {
                    $orden = (int) $wpdb->get_var($wpdb->prepare('SELECT COALESCE(MAX(orden), -1) + 1 FROM ' . $A::t('familia_contactos') . ' WHERE familia_id = %d', $fid));
                    $wpdb->insert($A::t('familia_contactos'), array('familia_id' => $fid, 'nombre' => $n, 'telefono' => $t, 'direccion' => '', 'orden' => $orden));
                }
                $log[] = $antes_despues;
            }
            $wpdb->update($A::t('familias'), array('actualizado' => $A::now()), array('id' => $fid));
            $A::log('profesor', 'familia', $fid, 'editar', array('contactos' => array(
                implode(' | ', array_filter(wp_list_pluck($log, 0), 'strlen')),
                implode(' | ', wp_list_pluck($log, 1)),
            ), 'alumno' => $alumno['nombre'], 'motivo' => $motivo));
        }

        // Notices: food allergies to Cocina and the tutors; the rest to Secretaría
        // (recipients chosen in "Avisos").
        $u = wp_get_current_user();
        $c = Area_Profesores_Cursos::get_curso($curso);
        $vars = array(
            'profesor' => $u->display_name,
            'alumno'   => $alumno['nombre'],
            'curso'    => $c ? $c['label'] : $curso,
            'motivo'   => $motivo,
            'enlace'   => admin_url('admin.php?page=' . Area_Profesores_Alumnos_Admin::SLUG . '&tab=alumnos&editar=' . $id),
        );
        $enviado = false;
        foreach (array('profesor_alergias' => $cambios_alim, 'profesor_alergias_otras' => $cambios_otras, 'profesor_datos' => $cambios) as $key => $lineas) {
            if (!$lineas) {
                continue;
            }
            $conf = Area_Profesores_Correos::get($key);
            $para = $conf['tutores'] ? $A::emails_tutores($curso) : array(); // + the accounts chosen in "Avisos"
            if (Area_Profesores_Correos::enviar($key, $para, $vars + array('cambios' => implode("\n", $lineas)), false, $u->user_email)) {
                $enviado = true;
            }
        }

        // Confirmation to the family (each family of the pupil separately).
        if ($cambios_fam) {
            $ahora = array_merge($alumno, $nuevo);
            $fam_vars = array(
                'alumno'   => $alumno['nombre'],
                'curso'    => $vars['curso'],
                'cambios'  => implode("\n", $cambios_fam),
                'alergias' => '- ' . __('Alimentarias', 'area-profesores') . ': ' . self::txt_familia($ahora['alergias_alimentarias'])
                    . "\n- " . __('No alimentarias', 'area-profesores') . ': ' . self::txt_familia($ahora['alergias_otras']),
                'profesor' => $u->display_name,
            );
            foreach ($familias as $f) {
                $emails = $A::emails_array($f['emails']);
                if ($emails) {
                    Area_Profesores_Correos::enviar('alergias_familia', $emails, $fam_vars, false, $u->user_email);
                }
            }
        }

        self::volver('success', $enviado
            ? sprintf(__('Datos de %s guardados. Se ha enviado el aviso por correo.', 'area-profesores'), $alumno['nombre'])
            : sprintf(__('Datos de %s guardados.', 'area-profesores'), $alumno['nombre']), $curso);
    }
}
