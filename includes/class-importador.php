<?php
/**
 * Importación del "Listado de familias" de Alexia.
 *
 * The report has one block per family:
 *   Familia: <nombre>
 *   Padres/Tutores | Dirección | Teléfonos y correo electrónico
 *   <padre/madre>  | <dirección> | <correo o teléfono>   (one item per parent)
 *   Hijos          | Nivel     | Datos de contacto
 *   <hijo/a>       | EP-3ºA    | <correo del alumno>
 * with the page header repeated on every page. Columns are located by their
 * headings, so the import keeps working if Alexia moves them.
 *
 * Step 1 parses the file and shows a preview of the changes; step 2 applies
 * them. Families are matched by their e-mails. The "correo familiar" is usually
 * the school account of a pupil and siblings often share it, so a pupil is
 * identified by e-mail + name (with fallbacks if Alexia corrects a name).
 *
 * Secretaría sends one file per group (Infantil, Primaria, ESO, Bachillerato);
 * pupils of that group missing from the file are proposed as "baja".
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Importador {

    /* ---------------------------------------------------------------------
     * 1. Leer el informe
     * ------------------------------------------------------------------- */

    /**
     * @return array { familias: [...], avisos: [...] }
     */
    public static function parse_alexia($rows) {
        $find = function ($row, $label) {
            foreach ($row as $c => $v) {
                if ($v === $label) {
                    return $c;
                }
            }
            return -1;
        };

        $familias = array();
        $avisos = array();
        $f = null;
        $sec = null;
        $cols = array();

        foreach ($rows as $i => $row) {
            $c_fam = $find($row, 'Familia:');
            if ($c_fam >= 0) {
                if ($f) {
                    $familias[] = $f;
                }
                $nombre = '';
                for ($c = $c_fam + 1; $c < count($row); $c++) {
                    if ($row[$c] !== '') {
                        $nombre = $row[$c];
                        break;
                    }
                }
                $f = array('nombre' => $nombre, 'padres' => array(), 'emails' => array(), 'telefonos' => array(), 'hijos' => array(), 'fila' => $i + 1);
                $sec = null;
                continue;
            }
            if (!$f) {
                continue;
            }

            $c_padres = $find($row, 'Padres/Tutores');
            if ($c_padres >= 0) {
                $sec = 'p';
                $cols['p'] = array($c_padres, $find($row, 'Dirección'), $find($row, 'Teléfonos y correo electrónico'));
                continue;
            }
            $c_hijos = $find($row, 'Hijos');
            if ($c_hijos >= 0) {
                $sec = 'h';
                $cols['h'] = array($c_hijos, $find($row, 'Nivel'), $find($row, 'Datos de contacto'));
                continue;
            }
            if (!$sec || empty($cols[$sec])) {
                continue;
            }

            list($cn, $cx, $cc) = $cols[$sec];
            $get = function ($c) use ($row) {
                return ($c >= 0 && isset($row[$c])) ? $row[$c] : '';
            };
            $nombre = $get($cn);
            $extra = $get($cx);
            $contacto = $get($cc);

            if ($sec === 'p') {
                if ($nombre !== '') {
                    $f['padres'][] = array('nombre' => $nombre, 'telefono' => '', 'direccion' => $extra);
                } elseif ($extra !== '' && $f['padres']) {
                    $last = count($f['padres']) - 1;
                    $f['padres'][$last]['direccion'] = trim($f['padres'][$last]['direccion'] . ' ' . $extra);
                }
                foreach (preg_split('/\n/', $contacto) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if (strpos($line, '@') !== false) {
                        foreach (Area_Profesores_Alumnos::emails_array($line) as $e) {
                            if (!in_array($e, $f['emails'], true)) {
                                $f['emails'][] = $e;
                            }
                        }
                    } elseif (preg_match('/\d{6,}/', str_replace(' ', '', $line))) {
                        // The phone belongs to the parent of this row (or of the row above).
                        if ($f['padres']) {
                            $last = count($f['padres']) - 1;
                            $f['padres'][$last]['telefono'] = trim($f['padres'][$last]['telefono'] . ($f['padres'][$last]['telefono'] !== '' ? ' / ' : '') . $line);
                        } elseif (!in_array($line, $f['telefonos'], true)) {
                            $f['telefonos'][] = $line;
                        }
                    }
                }
            } else {
                if ($nombre !== '') {
                    $f['hijos'][] = array(
                        'nombre' => $nombre,
                        'nivel'  => $extra,
                        'curso'  => Area_Profesores_Cursos::curso_desde_alexia($extra),
                        'email'  => strpos($contacto, '@') !== false ? (Area_Profesores_Alumnos::emails_array($contacto)[0] ?? '') : '',
                    );
                } elseif ($f['hijos'] && strpos($contacto, '@') !== false) {
                    $last = count($f['hijos']) - 1;
                    if ($f['hijos'][$last]['email'] === '') {
                        $f['hijos'][$last]['email'] = Area_Profesores_Alumnos::emails_array($contacto)[0] ?? '';
                    }
                }
            }
        }
        if ($f) {
            $familias[] = $f;
        }

        // Only pupils with a course of the school are imported (the report also
        // lists older siblings who no longer study here).
        $niveles_desconocidos = array();
        foreach ($familias as &$fam) {
            if ($fam['nombre'] === '') {
                $fam['nombre'] = implode(' / ', wp_list_pluck($fam['padres'], 'nombre'));
            }
            foreach ($fam['hijos'] as $h) {
                if ($h['nivel'] !== '' && $h['curso'] === '') {
                    $niveles_desconocidos[$h['nivel']] = true;
                }
            }
        }
        unset($fam);
        if ($niveles_desconocidos) {
            $avisos[] = sprintf(__('Niveles que no se reconocen (esos alumnos no se importan): %s', 'area-profesores'), implode(', ', array_keys($niveles_desconocidos)));
        }

        return array('familias' => $familias, 'avisos' => $avisos);
    }

    /**
     * Import groups = the files Secretaría exports from Alexia.
     */
    public static function get_grupos() {
        return array(
            'infantil'     => __('Infantil (1 a 5 años)', 'area-profesores'),
            'primaria'     => __('Primaria', 'area-profesores'),
            'eso'          => __('ESO', 'area-profesores'),
            'bachillerato' => __('Bachillerato', 'area-profesores'),
        );
    }

    public static function grupo_de_curso($curso) {
        if (strpos($curso, 'PC') === 0 || strpos($curso, 'I') === 0) {
            return 'infantil';
        }
        if (strpos($curso, 'EP') === 0) {
            return 'primaria';
        }
        if (strpos($curso, 'ESO') === 0) {
            return 'eso';
        }
        return strpos($curso, 'BACH') === 0 ? 'bachillerato' : '';
    }

    /**
     * Groups of the pupils in the file, most frequent first (the first one is
     * the group of the file; the others are siblings).
     */
    public static function detectar_grupos($familias) {
        $n = array();
        $vistos = array();
        foreach ($familias as $f) {
            foreach ($f['hijos'] as $h) {
                $key = Area_Profesores_Alumnos::clave_nombre($h['nombre']) . '|' . $h['email'];
                if ($h['curso'] === '' || isset($vistos[$key])) {
                    continue;
                }
                $vistos[$key] = true;
                $g = self::grupo_de_curso($h['curso']);
                $n[$g] = isset($n[$g]) ? $n[$g] + 1 : 1;
            }
        }
        arsort($n);
        return $n;
    }

    /**
     * Etapas of the pupils in the file, most frequent first.
     */
    public static function detectar_etapas($familias) {
        $n = array();
        $vistos = array();
        foreach ($familias as $f) {
            foreach ($f['hijos'] as $h) {
                $key = $h['email'] !== '' ? $h['email'] : $h['nombre'];
                if ($h['curso'] === '' || isset($vistos[$key])) {
                    continue;
                }
                $vistos[$key] = true;
                $etapa = Area_Profesores_Cursos::get_curso($h['curso'])['etapa'];
                $n[$etapa] = isset($n[$etapa]) ? $n[$etapa] + 1 : 1;
            }
        }
        arsort($n);
        return $n;
    }

    /* ---------------------------------------------------------------------
     * 2. Comparar con la base de datos (y aplicar)
     * ------------------------------------------------------------------- */

    /**
     * Compare the file with the database. With $aplicar = true the changes are saved.
     *
     * @param array $familias  parsed families
     * @param array $grupos    import groups of the file (pupils of these groups
     *                         missing from the file are proposed as "baja")
     * @param array $bajas_ok  ids of pupils to set as "baja" (only when applying)
     */
    public static function plan($familias, $grupos, $aplicar = false, $bajas_ok = array()) {
        global $wpdb;
        $A = Area_Profesores_Alumnos::class;

        // Current data
        $alumnos = $wpdb->get_results('SELECT * FROM ' . $A::t('alumnos'), ARRAY_A);
        // Siblings often share the same e-mail: index pupils by e-mail + name.
        $por_email = array();
        $por_clave = array();
        foreach ($alumnos as $a) {
            if ($a['email']) {
                $por_email[strtolower($a['email'])][] = $a;
                $por_clave[$A::clave_nombre($a['nombre']) . '|' . strtolower($a['email'])] = $a;
            }
        }
        $fams = $wpdb->get_results('SELECT * FROM ' . $A::t('familias'), ARRAY_A);
        // A parent's e-mail can be in two families (separated parents, new partner…):
        // families are matched first by their exact set of e-mails, then by overlap.
        $firma = function ($emails) {
            $emails = (array) $emails;
            sort($emails);
            return implode(',', $emails);
        };
        $fam_por_firma = array();
        $fam_por_email = array();
        $fam_por_id = array();
        foreach ($fams as $fm) {
            $fam_por_id[(int) $fm['id']] = $fm;
            $em = $A::emails_array($fm['emails']);
            if ($em) {
                $fam_por_firma[$firma($em)] = $fm;
            }
            foreach ($em as $e) {
                $fam_por_email[$e][] = (int) $fm['id'];
            }
        }
        $links = array();
        $fam_de_alumno = array();
        foreach ($wpdb->get_results('SELECT * FROM ' . $A::t('alumno_familia'), ARRAY_A) as $l) {
            $links[$l['familia_id']][] = (int) $l['alumno_id'];
            $fam_de_alumno[(int) $l['alumno_id']][] = (int) $l['familia_id'];
        }
        $alumno_por_id = array();
        $por_nombre = array();
        foreach ($alumnos as $a) {
            $alumno_por_id[(int) $a['id']] = $a;
            $por_nombre[$A::clave_nombre($a['nombre'])][] = $a;
        }

        // How alike a family of the database is to a family of the file:
        // children with the same name weigh most; then the family name.
        $parecido = function ($fid, $pf) use ($links, $alumno_por_id, $fam_por_id, $A) {
            $nombres = array();
            foreach (isset($links[$fid]) ? $links[$fid] : array() as $aid) {
                if (isset($alumno_por_id[$aid])) {
                    $nombres[$A::clave_nombre($alumno_por_id[$aid]['nombre'])] = true;
                }
            }
            $score = 0;
            foreach ($pf['hijos'] as $h) {
                if ($h['curso'] !== '' && isset($nombres[$A::clave_nombre($h['nombre'])])) {
                    $score += 3;
                }
            }
            if (isset($fam_por_id[$fid]) && $A::norm_nombre($fam_por_id[$fid]['nombre']) === $A::norm_nombre($pf['nombre'])) {
                $score += 2;
            }
            return $score;
        };

        $res = array(
            'alumnos_nuevos'  => array(),
            'alumnos_cambios' => array(),
            'alumnos_iguales' => 0,
            'familias_nuevas' => array(),
            'familias_cambios'=> array(),
            'familias_iguales'=> 0,
            'conservados'     => array(),
            'vinculos'        => 0,
            'bajas'           => array(),
            'sin_email'       => 0,
        );
        $vistos = array();   // pupil ids present in the file
        $usadas = array();   // database families already matched (one per family of the file)
        $creados = array();  // email/name => new id (when applying)

        foreach ($familias as $pf) {
            // --- Family
            // 1) Same set of e-mails.
            // (A family listed twice in the file with the same e-mails is the same family.)
            $existente = ($pf['emails'] && isset($fam_por_firma[$firma($pf['emails'])])) ? $fam_por_firma[$firma($pf['emails'])] : null;
            if (!$existente) {
                // 2) Families sharing an e-mail or a child: the most alike one
                //    (at least one child or the family name in common).
                $candidatas = array();
                foreach ($pf['emails'] as $e) {
                    foreach (isset($fam_por_email[$e]) ? $fam_por_email[$e] : array() as $cid) {
                        $candidatas[$cid] = true;
                    }
                }
                foreach ($pf['hijos'] as $h) {
                    foreach (isset($por_nombre[$A::clave_nombre($h['nombre'])]) ? $por_nombre[$A::clave_nombre($h['nombre'])] : array() as $a) {
                        foreach (isset($fam_de_alumno[(int) $a['id']]) ? $fam_de_alumno[(int) $a['id']] : array() as $cid) {
                            $candidatas[$cid] = true;
                        }
                    }
                }
                $mejor = 0;
                foreach (array_keys($candidatas) as $cid) {
                    if (isset($usadas[$cid])) {
                        continue; // Already the family of another block (e.g. the other parent).
                    }
                    $sc = $parecido($cid, $pf);
                    // A family with other e-mails needs a child in common.
                    $otras = $A::emails_array($fam_por_id[$cid]['emails']);
                    $comparte_email = (bool) array_intersect($otras, $pf['emails']);
                    if (($sc >= 3 || ($sc >= 2 && ($comparte_email || !$otras))) && $sc > $mejor) {
                        $mejor = $sc;
                        $existente = $fam_por_id[$cid];
                    }
                }
            }

            $nueva = array(
                'nombre'    => $pf['nombre'],
                'emails'    => $pf['emails'],
                'telefono'  => implode(' / ', $pf['telefonos']),
                'contactos' => $pf['padres'],
            );

            if (!$existente) {
                $res['familias_nuevas'][] = $pf['nombre'];
                $fid = $aplicar ? $A::guardar_familia($nueva, 0, 'importacion') : 0;
                if ($fid) {
                    $usadas[$fid] = true;
                }
                if ($aplicar && $pf['emails']) {
                    // The same family twice in the file (exact same e-mails): reuse it.
                    $fam_por_firma[$firma($pf['emails'])] = array('id' => $fid, 'nombre' => $pf['nombre'], 'emails' => implode(', ', $pf['emails']), 'telefono' => $nueva['telefono'], 'editado_por_familia' => null);
                }
            } elseif (isset($usadas[(int) $existente['id']])) {
                // Second block of the same family in this file: only its children are added.
                $fid = (int) $existente['id'];
            } else {
                $fid = (int) $existente['id'];
                $usadas[$fid] = true;
                $cambios = array();
                $old_emails = $A::emails_array($existente['emails']);
                $emails = $pf['emails'] ? array_values(array_unique(array_merge($pf['emails'], array_diff($old_emails, $pf['emails'])))) : $old_emails;
                if ($emails !== $old_emails) {
                    $cambios[] = __('correos', 'area-profesores');
                }
                if ($pf['nombre'] !== '' && $pf['nombre'] !== $existente['nombre']) {
                    $cambios[] = __('nombre', 'area-profesores');
                }
                $datos = array('nombre' => $pf['nombre'] !== '' ? $pf['nombre'] : $existente['nombre'], 'emails' => $emails, 'telefono' => $existente['telefono']);

                $old_ct = $A::get_contactos($fid);
                $ct_old = array_map(array($A, 'contacto_txt'), $old_ct);
                $ct_new = array_map(array($A, 'contacto_txt'), $pf['padres']);

                // The family changed its phones/addresses on the web: they are already
                // in use. Until Alexia has the same data, the import does not undo them.
                if (!empty($existente['editado_por_familia']) && $ct_old !== $ct_new) {
                    $res['conservados'][] = $existente['nombre'];
                } else {
                    if (!empty($existente['editado_por_familia']) && $aplicar) {
                        // Alexia is up to date: back to normal.
                        $wpdb->update($A::t('familias'), array('editado_por_familia' => null), array('id' => $fid));
                    }
                    if ($nueva['telefono'] !== $existente['telefono']) {
                        $cambios[] = __('teléfono', 'area-profesores');
                    }
                    if ($ct_old !== $ct_new) {
                        $cambios[] = __('padres, teléfonos o direcciones', 'area-profesores');
                        $datos['contactos'] = $pf['padres'];
                    }
                    $datos['telefono'] = $nueva['telefono'];
                }

                if ($cambios) {
                    $res['familias_cambios'][] = $existente['nombre'] . ' (' . implode(', ', $cambios) . ')';
                    if ($aplicar) {
                        $A::guardar_familia($datos, $fid, 'importacion');
                    }
                } else {
                    $res['familias_iguales']++;
                }
            }

            // --- Pupils
            foreach ($pf['hijos'] as $h) {
                if ($h['curso'] === '') {
                    continue;
                }
                if ($h['email'] === '') {
                    $res['sin_email']++;
                }
                $nn = $A::clave_nombre($h['nombre']);
                $key = $h['email'] !== '' ? $nn . '|' . $h['email'] : 'n:' . $nn . ':' . $fid;
                $ex = null;
                if ($h['email'] !== '' && isset($por_clave[$key])) {
                    // 1) Same e-mail and name.
                    $ex = $por_clave[$key];
                }
                if (!$ex && $existente) {
                    // 2) Same name in the same family.
                    foreach (isset($links[$fid]) ? $links[$fid] : array() as $lid) {
                        if (isset($alumno_por_id[$lid]) && $A::clave_nombre($alumno_por_id[$lid]['nombre']) === $nn) {
                            $ex = $alumno_por_id[$lid];
                            break;
                        }
                    }
                }
                if (!$ex && isset($por_nombre[$nn])) {
                    // 3) The only pupil with this name and course (listed elsewhere with another e-mail).
                    $mismo = array_values(array_filter($por_nombre[$nn], function ($a) use ($h) {
                        return $a['curso'] === $h['curso'];
                    }));
                    if (count($mismo) === 1) {
                        $ex = $mismo[0];
                    }
                }

                $key_nc = 'nc:' . $nn . '|' . $h['curso'];
                if (!$ex && (isset($creados[$key]) || isset($creados[$key_nc]))) {
                    // Same pupil in another family of the file (separated parents).
                    $aid = isset($creados[$key]) ? $creados[$key] : $creados[$key_nc];
                } elseif (!$ex) {
                    $res['alumnos_nuevos'][] = $h['nombre'] . ' · ' . Area_Profesores_Cursos::get_curso($h['curso'])['label'];
                    $aid = $aplicar ? $A::guardar_alumno(array('nombre' => $h['nombre'], 'email' => $h['email'], 'curso' => $h['curso'], 'estado' => 'alta'), 0, 'importacion') : 'nuevo:' . $key;
                    $creados[$key] = $aid;
                    $creados[$key_nc] = $aid;
                } else {
                    $aid = (int) $ex['id'];
                    if (!isset($vistos[$aid])) {
                        $c = array();
                        if ($ex['curso'] !== $h['curso']) {
                            $c[] = sprintf(__('curso %1$s → %2$s', 'area-profesores'), $ex['curso'] ? Area_Profesores_Cursos::get_curso($ex['curso'])['label'] : '—', Area_Profesores_Cursos::get_curso($h['curso'])['label']);
                        }
                        if ($ex['estado'] === 'pendiente') {
                            $c[] = __('se incorpora (estaba pendiente: matrícula online)', 'area-profesores');
                        } elseif ($ex['estado'] !== 'alta') {
                            $c[] = __('vuelve a estar de alta', 'area-profesores');
                        }
                        if ($ex['nombre'] !== $h['nombre']) {
                            $c[] = __('nombre', 'area-profesores');
                        }
                        if ($h['email'] !== '' && strtolower((string) $ex['email']) !== $h['email']) {
                            $c[] = __('correo', 'area-profesores');
                        }
                        if ($c) {
                            $res['alumnos_cambios'][] = $h['nombre'] . ': ' . implode(', ', $c);
                            if ($aplicar) {
                                $A::guardar_alumno(array('nombre' => $h['nombre'], 'email' => $h['email'] !== '' ? $h['email'] : $ex['email'], 'curso' => $h['curso'], 'estado' => 'alta', 'notas' => $ex['notas']), $aid, 'importacion');
                            }
                        } else {
                            $res['alumnos_iguales']++;
                        }
                    }
                }
                $vistos[$aid] = true;

                // Link pupil and family
                $ya = $fid && is_int($aid) && in_array($aid, isset($links[$fid]) ? $links[$fid] : array(), true);
                if (!$ya) {
                    $res['vinculos']++;
                    if ($aplicar && $fid && is_int($aid)) {
                        $A::vincular($aid, $fid, 'importacion');
                        $links[$fid][] = $aid;
                    }
                }
            }
        }

        // --- Pupils of these etapas who are not in the file: proposed "bajas"
        $cursos_scope = array();
        foreach (array_keys(Area_Profesores_Cursos::get_cursos()) as $k) {
            if (in_array(self::grupo_de_curso($k), $grupos, true)) {
                $cursos_scope[] = $k;
            }
        }
        foreach ($alumnos as $a) {
            if ($a['estado'] === 'alta' && in_array($a['curso'], $cursos_scope, true) && !isset($vistos[(int) $a['id']])) {
                $res['bajas'][] = array('id' => (int) $a['id'], 'nombre' => $a['nombre'], 'curso' => Area_Profesores_Cursos::get_curso($a['curso'])['label']);
                if ($aplicar && in_array((int) $a['id'], $bajas_ok, true)) {
                    $A::guardar_alumno(array('nombre' => $a['nombre'], 'email' => $a['email'], 'curso' => $a['curso'], 'estado' => 'baja', 'notas' => $a['notas']), (int) $a['id'], 'importacion');
                }
            }
        }

        return $res;
    }
}
