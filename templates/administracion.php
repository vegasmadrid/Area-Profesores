<?php
/**
 * Teacher Portal: "Administración" tab (Administración, Secretaría, Dirección and administrators).
 * Everything needed to bill a month: summary per pupil, every service and reservation,
 * the extra meals of each pupil, the meals per day and the days teachers did not eat.
 *
 * Variables: $comensales_mes { mes, adm (Area_Profesores_Comensales::administracion()) }
 */

if (!defined('ABSPATH')) {
    exit;
}

$cm = $comensales_mes;
$cm_a = $cm['adm'];
$cm_d = $cm_a['comedor'];
$cm_p = $cm_a['profes'];
$cm_s = $cm_a['servicios'];
$cm_fechas = array_unique(array_merge(array_keys($cm_d['dias']), array_keys($cm_p['dias'])));
sort($cm_fechas);
$cm_t = strtotime($cm['mes'] . '-01 12:00:00');
$cm_url = function ($mes) {
    return add_query_arg('ap_mes', $mes, remove_query_arg('ap_mes')) . '#administracion';
};
$cm_prev = gmdate('Y-m', strtotime('-1 month', $cm_t));
$cm_next = gmdate('Y-m', strtotime('+1 month', $cm_t));
?>
<div class="ap-records-card ap-cm">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Administración', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Todo lo del mes para facturar: el resumen por alumno, los servicios y reservas (de las familias, de Portería y los comedores extra que apuntan los profesores), las comidas extra, los comensales de cada día y los días que los profesores no comen en el colegio.', 'area-profesores'); ?></p>
        </div>
        <div class="ap-ma-tools">
            <a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'ap_comedor_csv_mes', 'mes' => $cm['mes']), admin_url('admin-post.php')), 'ap_comedor_csv_mes')); ?>"><?php esc_html_e('Descargar (Excel)', 'area-profesores'); ?></a>
            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" onclick="window.print()"><?php esc_html_e('Imprimir', 'area-profesores'); ?></button>
        </div>
    </div>

    <div class="ap-res-nav">
        <a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url($cm_url($cm_prev)); ?>">&larr;</a>
        <strong class="ap-cm-mes"><?php echo esc_html(ucfirst(date_i18n('F \d\e Y', $cm_t))); ?></strong>
        <a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url($cm_url($cm_next)); ?>">&rarr;</a>
    </div>

    <div class="ap-cm-cifras">
        <div><b><?php echo (int) $cm_d['total']; ?></b><span><?php esc_html_e('comidas en el mes', 'area-profesores'); ?></span></div>
        <div><b><?php echo (int) $cm_d['total_extra']; ?></b><span><?php esc_html_e('comidas extra', 'area-profesores'); ?></span></div>
        <div><b><?php echo count($cm_d['extras']); ?></b><span><?php esc_html_e('alumnos con comidas extra', 'area-profesores'); ?></span></div>
        <div><b><?php echo count($cm_s['filas']); ?></b><span><?php esc_html_e('reservas de servicios', 'area-profesores'); ?></span></div>
        <div><b><?php echo (int) $cm_p['total']; ?></b><span><?php esc_html_e('comidas de profesores que no se hicieron', 'area-profesores'); ?></span></div>
    </div>

    <h3 class="ap-ma-titulo"><?php esc_html_e('Para facturar: resumen por alumno', 'area-profesores'); ?></h3>
    <p class="ap-records-subtitle"><?php esc_html_e('Días de cada servicio en el mes (solo días lectivos). El comedor cuenta las comidas extra reales (validadas en el comedor diario); las salidas anticipadas y los retrasos no se cobran y solo salen en el detalle.', 'area-profesores'); ?></p>
    <?php if (!$cm_a['alumnos']) : ?>
        <p class="ap-ma-vacio"><?php esc_html_e('Este mes no hay servicios que facturar.', 'area-profesores'); ?></p>
    <?php else : $cm_tot = array(); ?>
        <div class="ap-adm-scroll">
            <table class="ap-ma-tabla ap-adm-fact">
                <thead><tr>
                    <th><?php esc_html_e('Alumno', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Curso', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Familia', 'area-profesores'); ?></th>
                    <?php foreach ($cm_a['columnas'] as $k => $n) : ?><th class="ap-adm-num"><?php echo esc_html($n); ?></th><?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($cm_a['alumnos'] as $a) : ?>
                        <tr>
                            <td class="ap-ma-alumno"><?php echo esc_html($a['nombre']); ?></td>
                            <td><?php echo esc_html($a['curso']); ?></td>
                            <td><?php echo esc_html($a['familia']); ?></td>
                            <?php foreach ($cm_a['columnas'] as $k => $n) : $v = isset($a['cols'][$k]) ? $a['cols'][$k] : 0; $cm_tot[$k] = (isset($cm_tot[$k]) ? $cm_tot[$k] : 0) + $v; ?>
                                <td class="ap-adm-num"><?php echo $v ? '<strong>' . (int) $v . '</strong>' : '<span class="ap-adm-cero">—</span>'; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr>
                    <th colspan="3"><?php printf(esc_html(_n('Total (%d alumno)', 'Total (%d alumnos)', count($cm_a['alumnos']), 'area-profesores')), count($cm_a['alumnos'])); ?></th>
                    <?php foreach ($cm_a['columnas'] as $k => $n) : ?><th class="ap-adm-num"><?php echo (int) $cm_tot[$k]; ?></th><?php endforeach; ?>
                </tr></tfoot>
            </table>
        </div>
    <?php endif; ?>

    <h3 class="ap-ma-titulo"><?php esc_html_e('Servicios y reservas del mes', 'area-profesores'); ?></h3>
    <?php if (!$cm_s['servicios']) : ?>
        <p class="ap-ma-vacio"><?php esc_html_e('No hay reservas de servicios este mes.', 'area-profesores'); ?></p>
    <?php else : ?>
        <?php foreach ($cm_s['servicios'] as $k => $sv) : ?>
            <details class="ap-adm-serv">
                <summary><strong><?php echo esc_html($sv['nombre']); ?></strong>
                    <span><?php printf(esc_html(_n('%d alumno', '%d alumnos', count($sv['alumnos']), 'area-profesores')), count($sv['alumnos'])); ?> · <?php printf(esc_html(_n('%d día', '%d días', $sv['dias'], 'area-profesores')), (int) $sv['dias']); ?> · <?php printf(esc_html(_n('%d reserva', '%d reservas', $sv['reservas'], 'area-profesores')), (int) $sv['reservas']); ?></span>
                </summary>
                <div class="ap-adm-scroll">
                    <table class="ap-ma-tabla">
                        <thead><tr>
                            <th><?php esc_html_e('Alumno', 'area-profesores'); ?></th>
                            <th><?php esc_html_e('Curso', 'area-profesores'); ?></th>
                            <th><?php esc_html_e('Días', 'area-profesores'); ?></th>
                            <th><?php esc_html_e('Detalles', 'area-profesores'); ?></th>
                            <th><?php esc_html_e('Reservado por', 'area-profesores'); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($cm_s['filas'] as $r) : if ($r['clave'] !== $k) { continue; } ?>
                                <tr>
                                    <td class="ap-ma-alumno"><?php echo esc_html($r['alumno']); ?><br><small><?php echo esc_html($r['familia']); ?></small></td>
                                    <td><?php echo esc_html($r['curso_label']); ?></td>
                                    <td class="ap-cm-dias"><strong><?php echo count($r['dias_mes']); ?></strong>
                                        <?php foreach ($r['dias_mes'] as $f) : ?><span class="ap-cm-dia is-<?php echo esc_attr($r['origen']); ?>"><?php echo esc_html(date_i18n('D j', strtotime($f . ' 12:00:00'))); ?></span><?php endforeach; ?>
                                    </td>
                                    <td class="ap-res-detalles"><?php echo nl2br(esc_html(Area_Profesores_Reservas::detalles_texto($r))); ?></td>
                                    <td><small><?php echo esc_html(Area_Profesores_Reservas::origen_texto($r)); ?><br><?php echo esc_html(date_i18n('j/n H:i', strtotime((string) $r['creado']))); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3 class="ap-ma-titulo"><?php esc_html_e('Comedor: comidas extra por alumno', 'area-profesores'); ?></h3>
    <?php if (!$cm_d['extras']) : ?>
        <p class="ap-ma-vacio"><?php esc_html_e('No hay comidas extra este mes.', 'area-profesores'); ?></p>
    <?php else : ?>
        <table class="ap-ma-tabla">
            <thead><tr>
                <th><?php esc_html_e('Alumno', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Curso', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Comidas', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Días', 'area-profesores'); ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($cm_d['extras'] as $e) : ?>
                    <tr>
                        <td class="ap-ma-alumno"><?php echo esc_html($e['nombre']); ?></td>
                        <td><?php echo esc_html($e['curso']); ?></td>
                        <td><strong><?php echo count($e['dias']); ?></strong></td>
                        <td class="ap-cm-dias">
                            <?php foreach ($e['dias'] as $f => $tipo) : ?>
                                <span class="ap-cm-dia<?php echo $tipo === 'familia' ? ' is-familia' : ''; ?>" title="<?php echo esc_attr($tipo === 'familia' ? __('Reservada por la familia', 'area-profesores') : __('Marcada por el profesor', 'area-profesores')); ?>"><?php echo esc_html(date_i18n('D j', strtotime($f . ' 12:00:00'))); ?></span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="ap-records-subtitle"><span class="ap-cm-dia is-familia">👪</span> <?php esc_html_e('= reservada por la familia (Área Familias o Portería). El resto, apuntadas por el profesor en el comedor del día.', 'area-profesores'); ?></p>
    <?php endif; ?>

    <h3 class="ap-ma-titulo"><?php esc_html_e('Profesores que no comen en el colegio', 'area-profesores'); ?></h3>
    <?php if (!$cm_p['profes']) : ?>
        <p class="ap-ma-vacio"><?php esc_html_e('Ningún profesor ha marcado días sin comer en el colegio este mes.', 'area-profesores'); ?></p>
    <?php else : ?>
        <table class="ap-ma-tabla">
            <thead><tr>
                <th><?php esc_html_e('Profesor/a', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Días sin comer', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Días', 'area-profesores'); ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($cm_p['profes'] as $p) : ?>
                    <tr>
                        <td class="ap-ma-alumno"><?php echo esc_html($p['nombre']); ?></td>
                        <td><strong><?php echo count($p['dias']); ?></strong></td>
                        <td class="ap-cm-dias">
                            <?php foreach ($p['dias'] as $f) : ?><span class="ap-cm-dia is-profe"><?php echo esc_html(date_i18n('D j', strtotime($f . ' 12:00:00'))); ?></span><?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3 class="ap-ma-titulo"><?php esc_html_e('Comensales por día', 'area-profesores'); ?></h3>
    <?php if (!$cm_fechas) : ?>
        <p class="ap-ma-vacio"><?php esc_html_e('No hay ningún día con el comedor validado este mes.', 'area-profesores'); ?></p>
    <?php else : ?>
        <table class="ap-ma-tabla">
            <thead><tr>
                <th><?php esc_html_e('Día', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Comensales', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Extra', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Reservas de familias', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Clases validadas', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Profesores que no comen', 'area-profesores'); ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($cm_fechas as $f) : $x = isset($cm_d['dias'][$f]) ? $cm_d['dias'][$f] : array('total' => 0, 'extra' => 0, 'familia' => 0, 'clases' => 0); ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst(date_i18n('l j', strtotime($f . ' 12:00:00')))); ?></td>
                        <td><strong><?php echo (int) $x['total']; ?></strong></td>
                        <td><?php echo (int) $x['extra']; ?></td>
                        <td><?php echo (int) $x['familia']; ?></td>
                        <td><?php echo (int) $x['clases']; ?></td>
                        <td><?php echo isset($cm_p['dias'][$f]) ? (int) $cm_p['dias'][$f] : 0; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
