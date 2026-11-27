<?php
/**
 * Teacher Portal: "Nuevas familias" tab (Secretaría, Dirección and Administración).
 * Enrolment requests sent by new families from the Área Familias.
 *
 * Variables: $matriculas (Area_Profesores_Matriculas::datos_portal())
 */

if (!defined('ABSPATH')) {
    exit;
}

$M = 'Area_Profesores_Matriculas';
$ap_m = $matriculas;
$ap_estados = $M::estados();
$ap_filtros = array_merge(
    array('pendientes' => __('Por hacer', 'area-profesores')),
    wp_list_pluck($ap_estados, 'label'),
    array('todas' => __('Todas', 'area-profesores'))
);
$ap_cnt = $ap_m['contadores'];
$ap_cnt['pendientes'] = $ap_cnt['nueva'] + $ap_cnt['revision'] + $ap_cnt['aprobada'];
$ap_cnt['todas'] = array_sum($ap_m['contadores']);
$ap_v = function ($v) {
    return ($v === '' || $v === null) ? '—' : $v;
};
$ap_doc_tipos = array('DNI' => 'DNI', 'NIE' => 'NIE', 'PAS' => __('Pasaporte', 'area-profesores'));
$ap_roles = array('padre' => __('Padre', 'area-profesores'), 'madre' => __('Madre', 'area-profesores'), 'tutor' => __('Tutor legal', 'area-profesores'), 'tutora' => __('Tutora legal', 'area-profesores'));
?>
<div class="ap-records-card ap-nf">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Nuevas familias', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Solicitudes de matrícula que las familias nuevas envían desde el Área Familias. Revisa los datos, imprime la ficha para que la firmen en Secretaría y marca los documentos que traen.', 'area-profesores'); ?></p>
        </div>
    </div>

    <?php if ($ap_m['flash']) : ?>
        <div class="ap-cd-msg is-<?php echo esc_attr($ap_m['flash']['type'] === 'error' ? 'error' : 'success'); ?>" role="status"><?php echo esc_html($ap_m['flash']['text']); ?></div>
    <?php endif; ?>

<?php if (!$ap_m['detalle']) : ?>

    <div class="ap-ma-cursos ap-nf-filtros" role="tablist">
        <?php foreach ($ap_filtros as $k => $label) : ?>
            <a class="ap-ma-curso<?php echo $ap_m['estado'] === $k ? ' is-active' : ''; ?>" href="<?php echo esc_url($M::url_lista(array('ap_mat_estado' => $k))); ?>">
                <?php echo esc_html($label); ?> <span><?php echo (int) (isset($ap_cnt[$k]) ? $ap_cnt[$k] : 0); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$ap_m['lista']) : ?>
        <p class="ap-ma-vacio"><?php esc_html_e('No hay solicitudes en esta lista.', 'area-profesores'); ?></p>
    <?php else : ?>
        <table class="ap-ma-tabla ap-nf-tabla">
            <thead><tr>
                <th><?php esc_html_e('Referencia', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Recibida', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Familia y alumnos', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Curso', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Estado', 'area-profesores'); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($ap_m['lista'] as $r) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($r['referencia']); ?></strong></td>
                        <td><?php echo esc_html(date_i18n('j M Y, H:i', strtotime($r['creado']))); ?></td>
                        <td>
                            <strong><?php echo esc_html($r['familia']); ?></strong><br>
                            <small><?php echo esc_html($r['alumnos_txt']); ?></small><br>
                            <small class="ap-nf-gris"><?php echo esc_html($r['email_contacto'] . ($r['telefono'] !== '' ? ' · ' . $r['telefono'] : '')); ?></small>
                        </td>
                        <td>
                            <?php echo esc_html(implode(', ', array_map(array('Area_Profesores_Cursos', 'get_nivel_label'), array_filter(explode(',', $r['niveles']))))); ?>
                            <?php if ($r['curso_escolar'] !== '') : ?><br><small class="ap-nf-gris"><?php echo esc_html($r['curso_escolar']); ?></small><?php endif; ?>
                        </td>
                        <td><span class="ap-nf-estado is-<?php echo esc_attr($r['estado']); ?>"><?php echo esc_html($M::estado_label($r['estado'])); ?></span></td>
                        <td><a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url($M::url_detalle($r['id'])); ?>"><?php esc_html_e('Abrir', 'area-profesores'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php else :
    $m = $ap_m['detalle'];
    $d = $m['datos'];
    $of = $m['oficina'];
    $ap_nonce = wp_nonce_field('ap_matricula_' . $m['id'], '_wpnonce', true, false);
    ?>
    <p class="no-print"><a href="<?php echo esc_url($M::url_lista()); ?>">&larr; <?php esc_html_e('Volver a la lista', 'area-profesores'); ?></a></p>

    <div class="ap-nf-cabecera">
        <div>
            <h3 class="ap-nf-titulo"><?php echo esc_html($m['referencia'] . ' · ' . sprintf(__('Familia %s', 'area-profesores'), $m['familia'])); ?></h3>
            <p class="ap-nf-gris">
                <?php printf(esc_html__('Recibida el %1$s · Curso escolar %2$s', 'area-profesores'), esc_html(date_i18n('j \d\e F \d\e Y, H:i', strtotime($m['creado']))), esc_html($ap_v($m['curso_escolar']))); ?>
            </p>
            <span class="ap-nf-estado is-<?php echo esc_attr($m['estado']); ?>"><?php echo esc_html($M::estado_label($m['estado'])); ?></span>
        </div>
        <?php if ($d) : ?>
            <a class="ap-btn ap-btn-primary" href="<?php echo esc_url($M::url_imprimir($m['id'])); ?>" target="_blank" rel="noopener">🖨️ <?php esc_html_e('Imprimir la ficha para firmar', 'area-profesores'); ?></a>
        <?php endif; ?>
    </div>

    <?php if (!$d) : ?>
        <div class="ap-cd-msg is-error" role="alert"><?php esc_html_e('No se pueden leer los datos de esta solicitud: la clave de cifrado de la web ha cambiado (AP_MATRICULAS_KEY o las claves de wp-config.php). Pide a la familia que la vuelva a enviar.', 'area-profesores'); ?></div>
    <?php else : ?>

    <!-- Estado -->
    <form class="ap-nf-box ap-nf-estado-form no-print" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php echo $ap_nonce; // phpcs:ignore ?>
        <input type="hidden" name="action" value="ap_matricula">
        <input type="hidden" name="op" value="estado">
        <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
        <label><strong><?php esc_html_e('Cambiar el estado', 'area-profesores'); ?></strong>
            <select name="estado" id="ap-nf-nuevo-estado">
                <?php foreach ($ap_estados as $k => $e) : ?>
                    <option value="<?php echo esc_attr($k); ?>" <?php selected($m['estado'], $k); ?> title="<?php echo esc_attr($e['desc']); ?>"><?php echo esc_html($e['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <input type="text" name="motivo" maxlength="300" placeholder="<?php esc_attr_e('Nota (opcional): por qué cambia', 'area-profesores'); ?>">
        <label class="ap-nf-check"><input type="checkbox" name="avisar" value="1" checked> <?php esc_html_e('Si pasa a «Aprobada», enviar a la familia el correo para venir a firmar', 'area-profesores'); ?></label>
        <?php if (!$M::alta_hecha($m)) : ?>
            <label class="ap-nf-check"><input type="checkbox" name="dar_alta" value="1" checked> <?php esc_html_e('Si pasa a «Completada», dar de alta a la familia y a los alumnos en Alumnos y familias', 'area-profesores'); ?></label>
        <?php endif; ?>
        <button type="submit" class="ap-btn ap-btn-sm ap-btn-primary"><?php esc_html_e('Guardar estado', 'area-profesores'); ?></button>
    </form>

    <?php if ($ap_m['coinciden'] && !$M::alta_hecha($m)) : ?>
        <div class="ap-cd-msg is-warning" role="note">
            <strong><?php esc_html_e('¿Hermanos de una familia del colegio?', 'area-profesores'); ?></strong>
            <?php foreach ($ap_m['coinciden'] as $email => $c) : ?>
                <br><?php printf(esc_html__('La cuenta %1$s ya es de la %2$s (hijos: %3$s).', 'area-profesores'), '<code>' . esc_html($email) . '</code>', esc_html($c['familia']), esc_html($c['hijos'] ? implode(', ', $c['hijos']) : '—')); ?>
            <?php endforeach; ?>
            <br><?php esc_html_e('Si es la misma familia, usad su cuenta (escríbela abajo en «Cuenta de la familia»). Si es otra familia con los mismos apellidos, se propone otra cuenta.', 'area-profesores'); ?>
        </div>
    <?php endif; ?>

    <!-- Alta en Alumnos y familias -->
    <?php $ap_alta = $M::alta_hecha($m) ? $m['oficina']['alta'] : null; ?>
    <section class="ap-nf-box ap-nf-alta<?php echo $ap_alta ? ' is-hecha' : ''; ?>">
        <h4><?php esc_html_e('Alta en Alumnos y familias', 'area-profesores'); ?></h4>
        <?php if ($ap_alta) : ?>
            <p>✅ <?php printf(esc_html__('Dada de alta el %1$s por %2$s.', 'area-profesores'), esc_html(date_i18n('j/m/Y H:i', strtotime($ap_alta['fecha']))), esc_html($ap_alta['quien'])); ?></p>
            <ul class="ap-nf-lista">
                <?php foreach ($ap_alta['resumen'] as $l) : ?><li><?php echo esc_html($l); ?></li><?php endforeach; ?>
            </ul>
            <?php if (!empty($ap_alta['existentes'])) : ?>
                <p class="ap-nf-ayuda"><?php esc_html_e('Los alumnos se han vinculado a la familia que ya estaba en el colegio con esa cuenta (sus datos no se han cambiado).', 'area-profesores'); ?></p>
            <?php endif; ?>
            <p class="ap-nf-ayuda"><?php esc_html_e('Los alumnos que todavía no han empezado están como «Pendientes de incorporación»: no salen en las listas de clase, comedor ni reservas, y su familia los ve en el Área Familias con la fecha en que empiezan. Ese día pasan solos a «De alta».', 'area-profesores'); ?></p>
            <?php if (current_user_can('manage_options')) : ?>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=' . Area_Profesores_Alumnos_Admin::SLUG . '&tab=alumnos&estado=todos&q=' . rawurlencode($m['familia']))); ?>"><?php esc_html_e('Ver en Alumnos y familias', 'area-profesores'); ?></a></p>
            <?php endif; ?>
        <?php else : $ap_faltan = $M::faltan_para_alta($m); ?>
            <p><?php esc_html_e('Crea la familia (con su cuenta y los teléfonos y la dirección de los padres) y los alumnos en su curso, con sus alergias. Si la cuenta ya es de una familia del colegio (hermanos), los alumnos se vinculan a ella.', 'area-profesores'); ?></p>
            <ul class="ap-nf-lista">
                <?php foreach ($d['alumnos'] as $i => $a) : $f = $M::fecha_ingreso($m, $i); ?>
                    <li><?php echo esc_html($M::nombre_alumno($a) . ' · ' . Area_Profesores_Cursos::get_nivel_label($a['nivel']) . (!empty($of['alumnos'][$i]['grupo']) ? ' ' . $of['alumnos'][$i]['grupo'] : '') . ' · ' . ($f > current_time('Y-m-d') ? sprintf(__('pendiente de incorporación hasta el %s', 'area-profesores'), $M::f($f)) : __('de alta desde hoy', 'area-profesores'))); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($ap_faltan) : ?>
                <div class="ap-cd-msg is-info"><?php echo esc_html(__('Falta:', 'area-profesores') . ' ' . implode(' ', $ap_faltan)); ?></div>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="no-print" onsubmit="return confirm('<?php echo esc_js(__('¿Dar de alta ahora a esta familia y sus alumnos en Alumnos y familias?', 'area-profesores')); ?>');">
                    <?php echo $ap_nonce; // phpcs:ignore ?>
                    <input type="hidden" name="action" value="ap_matricula">
                    <input type="hidden" name="op" value="alta">
                    <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                    <button type="submit" class="ap-btn ap-btn-sm ap-btn-outline"><?php esc_html_e('Dar de alta ahora', 'area-profesores'); ?></button>
                    <span class="ap-nf-ayuda"><?php esc_html_e('Se hace solo al pasar la solicitud a «Completada».', 'area-profesores'); ?></span>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- Datos de Secretaría -->
    <form class="ap-nf-box no-print" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php echo $ap_nonce; // phpcs:ignore ?>
        <input type="hidden" name="action" value="ap_matricula">
        <input type="hidden" name="op" value="oficina">
        <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
        <h4><?php esc_html_e('Para rellenar en Secretaría', 'area-profesores'); ?></h4>

        <div class="ap-nf-grid">
            <?php foreach ($d['alumnos'] as $i => $a) : $x = isset($of['alumnos'][$i]) ? $of['alumnos'][$i] : array(); $niv = Area_Profesores_Cursos::get_niveles_publicos(); $con_grupo = !in_array($a['nivel'], array('BACH1', 'BACH2'), true); ?>
                <fieldset>
                    <legend><?php echo esc_html($M::nombre_alumno($a) . ' · ' . Area_Profesores_Cursos::get_nivel_label($a['nivel'])); ?></legend>
                    <label><?php esc_html_e('Nº de matrícula', 'area-profesores'); ?> <input type="text" name="al[<?php echo (int) $i; ?>][numero_matricula]" value="<?php echo esc_attr(isset($x['numero_matricula']) ? $x['numero_matricula'] : ''); ?>"></label>
                    <label><?php esc_html_e('NIA', 'area-profesores'); ?> <input type="text" name="al[<?php echo (int) $i; ?>][nia]" value="<?php echo esc_attr(isset($x['nia']) ? $x['nia'] : ''); ?>"></label>
                    <label><?php esc_html_e('Fecha de ingreso', 'area-profesores'); ?> <input type="date" name="al[<?php echo (int) $i; ?>][fecha_ingreso]" value="<?php echo esc_attr(isset($x['fecha_ingreso']) ? $x['fecha_ingreso'] : ''); ?>"><small class="ap-nf-gris"><?php printf(esc_html__('Si la dejas vacía: %s', 'area-profesores'), esc_html($M::f($M::fecha_inicio_curso($m['curso_escolar'])))); ?></small></label>
                    <?php if ($con_grupo) : ?>
                        <label><?php esc_html_e('Grupo', 'area-profesores'); ?>
                            <select name="al[<?php echo (int) $i; ?>][grupo]">
                                <option value=""><?php esc_html_e('Sin decidir', 'area-profesores'); ?></option>
                                <?php foreach (array('A', 'B') as $g) : ?><option value="<?php echo esc_attr($g); ?>" <?php selected(isset($x['grupo']) ? $x['grupo'] : '', $g); ?>><?php echo esc_html($g); ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>

            <fieldset>
                <legend><?php esc_html_e('Documentación entregada en papel', 'area-profesores'); ?></legend>
                <?php foreach ($M::documentos() as $k => $label) : ?>
                    <label class="ap-nf-check"><input type="checkbox" name="docs[<?php echo esc_attr($k); ?>]" value="1" <?php checked(!empty($of['docs'][$k])); ?>> <?php echo esc_html($label); ?></label>
                <?php endforeach; ?>
            </fieldset>

            <fieldset>
                <legend><?php esc_html_e('Cuenta de la familia en Google', 'area-profesores'); ?></legend>
                <?php $ap_cuentas = isset($of['cuentas']) ? (array) $of['cuentas'] : array(); ?>
                <?php for ($i = 0; $i < max(count($ap_cuentas), !empty($d['separados']) ? 2 : 1); $i++) : ?>
                    <label><?php echo $i === 0 ? esc_html__('Cuenta', 'area-profesores') : esc_html__('Segunda cuenta (padres separados)', 'area-profesores'); ?>
                        <input type="text" name="cuentas[]" value="<?php echo esc_attr(isset($ap_cuentas[$i]) ? $ap_cuentas[$i] : ''); ?>" spellcheck="false" autocomplete="off">
                    </label>
                <?php endfor; ?>
                <p class="ap-nf-ayuda"><?php printf(esc_html__('Regla del colegio: primerapellido.segundoapellido.%1$s@%2$s del alumno; padres separados: un 1 antes de .%1$s. Si otra familia ya la usa, se añade la inicial del nombre del alumno.', 'area-profesores'), esc_html($M::SUFIJO), esc_html($M::dominio())); ?></p>
                <button type="submit" name="op" value="proponer" class="ap-btn ap-btn-sm ap-btn-outline"><?php esc_html_e('Volver a proponer la cuenta', 'area-profesores'); ?></button>
            </fieldset>
        </div>

        <label class="ap-nf-notas"><?php esc_html_e('Notas internas (no las ve la familia)', 'area-profesores'); ?>
            <textarea name="notas" rows="3"><?php echo esc_textarea(isset($of['notas']) ? $of['notas'] : ''); ?></textarea>
        </label>
        <button type="submit" class="ap-btn ap-btn-primary"><?php esc_html_e('Guardar', 'area-profesores'); ?></button>
    </form>

    <!-- Datos de la familia -->
    <?php foreach ($d['alumnos'] as $i => $a) : ?>
        <section class="ap-nf-box">
            <h4><?php printf(esc_html__('Alumno/a %d', 'area-profesores'), $i + 1); ?> · <?php echo esc_html($M::nombre_alumno($a)); ?></h4>
            <dl class="ap-nf-dl">
                <dt><?php esc_html_e('Curso que solicita', 'area-profesores'); ?></dt><dd><?php echo esc_html(Area_Profesores_Cursos::get_nivel_label($a['nivel'])); ?></dd>
                <dt><?php esc_html_e('Apellidos', 'area-profesores'); ?></dt><dd><?php echo esc_html(trim($a['apellido1'] . ' ' . $a['apellido2'])); ?></dd>
                <dt><?php esc_html_e('Nombre', 'area-profesores'); ?></dt><dd><?php echo esc_html($a['nombre']); ?></dd>
                <dt><?php esc_html_e('Sexo', 'area-profesores'); ?></dt><dd><?php echo esc_html($a['sexo'] === 'H' ? __('Hombre', 'area-profesores') : ($a['sexo'] === 'M' ? __('Mujer', 'area-profesores') : '—')); ?></dd>
                <dt><?php esc_html_e('Nacimiento', 'area-profesores'); ?></dt><dd><?php echo esc_html($M::f($a['fecha_nacimiento']) . ' · ' . trim($a['localidad_nacimiento'] . ' (' . $a['provincia_nacimiento'] . ')', ' ()')); ?></dd>
                <dt><?php esc_html_e('Nacionalidad', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($a['nacionalidad'])); ?></dd>
                <dt><?php esc_html_e('Centro de procedencia', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($a['centro_procedencia'])); ?></dd>
                <dt><?php esc_html_e('Alergias alimentarias', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($a['alergias_alimentarias'])); ?></dd>
                <dt><?php esc_html_e('Otras alergias', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($a['alergias_otras'])); ?></dd>
            </dl>
        </section>
    <?php endforeach; ?>

    <?php foreach ($d['tutores'] as $t) : ?>
        <section class="ap-nf-box">
            <h4><?php echo esc_html((isset($ap_roles[$t['rol']]) ? $ap_roles[$t['rol']] : $t['rol']) . ' · ' . $t['nombre'] . ' ' . $t['apellidos']); ?></h4>
            <dl class="ap-nf-dl">
                <dt><?php esc_html_e('Fecha de nacimiento', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($M::f($t['fecha_nacimiento']))); ?></dd>
                <dt><?php esc_html_e('Localidad de nacimiento', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($t['localidad_nacimiento'])); ?></dd>
                <dt><?php esc_html_e('Nacionalidad', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($t['nacionalidad'])); ?></dd>
                <dt><?php echo esc_html(isset($ap_doc_tipos[$t['documento_tipo']]) ? $ap_doc_tipos[$t['documento_tipo']] : $t['documento_tipo']); ?></dt><dd><?php echo esc_html($ap_v($t['documento'])); ?></dd>
                <dt><?php esc_html_e('Estudios realizados', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($t['estudios'])); ?></dd>
                <dt><?php esc_html_e('Profesión actual', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($t['profesion'])); ?></dd>
                <dt><?php esc_html_e('Teléfono móvil', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($t['movil'])); ?></dd>
                <dt><?php esc_html_e('Correo personal', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($t['email'])); ?></dd>
            </dl>
        </section>
    <?php endforeach; ?>

    <section class="ap-nf-box">
        <h4><?php esc_html_e('Residencia habitual y familia', 'area-profesores'); ?></h4>
        <?php $dom = $d['domicilio']; ?>
        <dl class="ap-nf-dl">
            <dt><?php esc_html_e('Titular de la correspondencia', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($dom['titular'])); ?></dd>
            <dt><?php esc_html_e('Dirección', 'area-profesores'); ?></dt><dd><?php echo esc_html(trim($dom['calle'] . ' ' . $dom['numero'] . ($dom['piso'] !== '' ? ', ' . $dom['piso'] : '') . ($dom['puerta'] !== '' ? ' ' . $dom['puerta'] : '') . ' · ' . $dom['cp'] . ' ' . $dom['localidad'] . ($dom['provincia'] !== '' ? ' (' . $dom['provincia'] . ')' : ''))); ?></dd>
            <dt><?php esc_html_e('Teléfono fijo', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($dom['telefono'])); ?></dd>
            <dt><?php esc_html_e('Personas que conviven en la casa', 'area-profesores'); ?></dt><dd><?php echo esc_html($d['convivientes'] ? $d['convivientes'] : '—'); ?></dd>
            <dt><?php esc_html_e('Padres separados (dos cuentas)', 'area-profesores'); ?></dt><dd><?php echo !empty($d['separados']) ? esc_html__('Sí', 'area-profesores') : esc_html__('No', 'area-profesores'); ?></dd>
            <dt><?php esc_html_e('Hermanos en el centro', 'area-profesores'); ?></dt>
            <dd><?php echo $d['hermanos'] ? esc_html(implode(' · ', array_map(function ($h) { return $h['nombre'] . ($h['curso'] !== '' ? ' (' . $h['curso'] . ')' : ''); }, $d['hermanos']))) : '—'; ?></dd>
            <dt><?php esc_html_e('Correo de contacto', 'area-profesores'); ?></dt><dd><a href="mailto:<?php echo esc_attr($d['email_contacto']); ?>"><?php echo esc_html($d['email_contacto']); ?></a> <small class="ap-nf-gris"><?php esc_html_e('(confirmado con un código)', 'area-profesores'); ?></small></dd>
            <?php if ($d['observaciones'] !== '') : ?>
                <dt><?php esc_html_e('Observaciones de la familia', 'area-profesores'); ?></dt><dd><?php echo nl2br(esc_html($d['observaciones'])); ?></dd>
            <?php endif; ?>
        </dl>
    </section>

    <section class="ap-nf-box">
        <h4><?php esc_html_e('Datos económicos (SEPA)', 'area-profesores'); ?></h4>
        <?php if ($d['pago']['modo'] === 'ventanilla') : ?>
            <p><?php esc_html_e('Pago por ventanilla (sin domiciliación).', 'area-profesores'); ?></p>
        <?php else : $pg = $d['pago']['pagador']; ?>
            <dl class="ap-nf-dl">
                <dt><?php esc_html_e('Pagador', 'area-profesores'); ?></dt><dd><?php echo esc_html($pg['nombre'] . ' · ' . $pg['documento']); ?></dd>
                <dt><?php esc_html_e('Teléfono', 'area-profesores'); ?></dt><dd><?php echo esc_html($ap_v($pg['telefono'])); ?></dd>
                <dt><?php esc_html_e('Dirección', 'area-profesores'); ?></dt><dd><?php echo esc_html(trim($pg['direccion'] . ' · ' . $pg['cp'] . ' ' . $pg['localidad'] . ' (' . $pg['provincia'] . ')', ' ·()')); ?></dd>
                <dt>IBAN</dt><dd><code class="ap-nf-iban"><?php echo esc_html($M::iban_formato($d['pago']['iban'])); ?></code></dd>
            </dl>
        <?php endif; ?>
    </section>

    <section class="ap-nf-box">
        <h4><?php esc_html_e('Manifestaciones y autorizaciones', 'area-profesores'); ?></h4>
        <?php
        $ap_ac = $d['aceptaciones'];
        $ap_mt = isset($d['textos']['manifestaciones']) ? $d['textos']['manifestaciones'] : array();
        $ap_mr = isset($ap_ac['manifestaciones']) ? $ap_ac['manifestaciones'] : array();
        $ap_si = function ($ok, $txt) {
            return '<span class="ap-nf-resp ' . ($ok ? 'is-si' : 'is-no') . '">' . esc_html($txt) . '</span>';
        };
        ?>
        <?php if ($M::imagenes_contradictorias($d)) : ?>
            <div class="ap-cd-msg is-warning" role="note"><?php esc_html_e('Ojo: la familia ha contestado distinto sobre las imágenes en la manifestación 4º y en el consentimiento de protección de datos. Aclaradlo con ellos al firmar.', 'area-profesores'); ?></div>
        <?php endif; ?>
        <?php if ($ap_mt) : ?>
            <dl class="ap-nf-dl">
                <?php foreach ($ap_mt as $mk => $mf) : $r = isset($ap_mr[$mk]) ? $ap_mr[$mk] : ''; ?>
                    <dt><?php echo esc_html($mf['n'] . ' ' . wp_trim_words(strtok($mf['texto'], "\n"), 9, '…')); ?></dt>
                    <dd>
                        <?php
                        if ($mf['tipo'] === 'acepta') {
                            echo $ap_si($r === 'si', $r === 'si' ? __('Aceptada', 'area-profesores') : __('Sin aceptar', 'area-profesores')); // phpcs:ignore
                        } elseif ($mf['tipo'] === 'eleccion') {
                            echo $r === '' ? '—' : $ap_si($r === 'si', $r === 'si' ? $mf['si'] : $mf['no']); // phpcs:ignore
                        } else {
                            echo $r !== '' ? nl2br(esc_html($r)) : '—';
                        }
                        ?>
                    </dd>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
        <dl class="ap-nf-dl" style="margin-top:10px;">
            <?php foreach (isset($d['textos']['consentimientos']) ? $d['textos']['consentimientos'] : array() as $c) : $r = isset($ap_ac['rgpd'][$c['campo']]) ? $ap_ac['rgpd'][$c['campo']] : ''; ?>
                <dt><?php echo esc_html(sprintf(__('Protección de datos: %s', 'area-profesores'), $c['label'])); ?></dt>
                <dd><?php echo $r === '' ? '—' : $ap_si($r === 'consiento', $r === 'consiento' ? __('Consiento', 'area-profesores') : __('No consiento', 'area-profesores')); // phpcs:ignore ?></dd>
            <?php endforeach; ?>
            <dt><?php esc_html_e('Información leída y aceptada en la web', 'area-profesores'); ?></dt>
            <dd><?php echo esc_html($ap_ac['privacidad']); ?></dd>
        </dl>
    </section>
    <?php endif; ?>

    <details class="ap-nf-box">
        <summary><strong><?php esc_html_e('Historial', 'area-profesores'); ?></strong></summary>
        <ul class="ap-nf-lista">
            <?php foreach (array_reverse($m['historial']) as $h) : ?>
                <li><?php echo esc_html(date_i18n('j M Y, H:i', strtotime($h['fecha'])) . ' · ' . $h['quien'] . ' · ' . $h['accion']); ?></li>
            <?php endforeach; ?>
        </ul>
    </details>

    <?php if ($ap_m['borrar']) : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="no-print" onsubmit="return confirm('<?php echo esc_js(__('¿Eliminar esta solicitud y todos sus datos? No se puede deshacer.', 'area-profesores')); ?>');">
            <?php echo $ap_nonce; // phpcs:ignore ?>
            <input type="hidden" name="action" value="ap_matricula">
            <input type="hidden" name="op" value="borrar">
            <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
            <button type="submit" class="ap-btn ap-btn-sm ap-btn-subtle"><?php esc_html_e('Eliminar la solicitud (solo administradores)', 'area-profesores'); ?></button>
        </form>
    <?php endif; ?>

<?php endif; ?>
</div>
