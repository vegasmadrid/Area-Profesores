<?php
/**
 * Teacher Portal: "Reservas" tab.
 * Services reserved by the families for the pupils the teacher can see
 * (tutoría + assigned classes; every course for Dirección and Secretaría).
 *
 * Portería also books services for the families ("Nueva reserva") and cancels them.
 *
 * Variables: $reservas { fecha, hoy, lista, proximos, todos, porteria }
 */

if (!defined('ABSPATH')) {
    exit;
}

$ap_r = $reservas;
$ap_pt = isset($ap_r['porteria']) ? $ap_r['porteria'] : null;
$ap_url = function ($fecha) {
    return add_query_arg('ap_res', $fecha, remove_query_arg('ap_res')) . '#reservas';
};
$ap_mover = function ($fecha, $delta) {
    $t = strtotime($fecha . ' 12:00:00 UTC');
    do {
        $t += $delta * DAY_IN_SECONDS;
    } while ((int) gmdate('N', $t) > 5);
    return gmdate('Y-m-d', $t);
};
$ap_grupos = array();
foreach ($ap_r['lista'] as $r) {
    $ap_grupos[$r['servicio_nombre']][] = $r;
}
?>
<div class="ap-records-card ap-res">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Reservas de las familias', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle">
                <?php echo $ap_r['todos']
                    ? esc_html__('Servicios reservados para los alumnos de todos los cursos: ampliación, comedor, ludoteca, dietas, salidas anticipadas y retrasos.', 'area-profesores')
                    : esc_html__('Servicios reservados para tus alumnos: ampliación, comedor, ludoteca, dietas, salidas anticipadas y retrasos.', 'area-profesores'); ?>
            </p>
            <?php if (!empty($ap_r['elegir'])) : ?>
                <div class="ap-res-alcance no-print" role="group" aria-label="<?php esc_attr_e('Qué reservas ver', 'area-profesores'); ?>">
                    <a href="<?php echo esc_url(remove_query_arg('ap_rv') . '#reservas'); ?>" class="<?php echo empty($ap_r['todo']) ? 'is-active' : ''; ?>"<?php echo empty($ap_r['todo']) ? ' aria-current="true"' : ''; ?>><?php esc_html_e('Mis alumnos', 'area-profesores'); ?></a>
                    <a href="<?php echo esc_url(add_query_arg('ap_rv', 'todos') . '#reservas'); ?>" class="<?php echo !empty($ap_r['todo']) ? 'is-active' : ''; ?>"<?php echo !empty($ap_r['todo']) ? ' aria-current="true"' : ''; ?>><?php esc_html_e('Todo el centro', 'area-profesores'); ?></a>
                </div>
            <?php endif; ?>
        </div>
        <div class="ap-ma-tools">
            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" onclick="window.print()"><?php esc_html_e('Imprimir', 'area-profesores'); ?></button>
        </div>
    </div>

    <?php if ($ap_pt) : ?>
        <?php if ($ap_pt['flash']) : ?>
            <div class="ap-cd-msg is-<?php echo esc_attr($ap_pt['flash']['type']); ?>" role="status"><?php echo esc_html($ap_pt['flash']['text']); ?></div>
        <?php endif; ?>
        <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/partials/reserva-porteria.php'; ?>
    <?php endif; ?>

    <div class="ap-res-nav">
        <a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url($ap_url($ap_mover($ap_r['fecha'], -1))); ?>">&larr;</a>
        <form method="get" class="ap-res-fecha">
            <?php foreach ($_GET as $ap_k => $ap_v) : if ($ap_k === 'ap_res' || is_array($ap_v)) { continue; } ?>
                <input type="hidden" name="<?php echo esc_attr($ap_k); ?>" value="<?php echo esc_attr(wp_unslash($ap_v)); ?>">
            <?php endforeach; ?>
            <input type="date" name="ap_res" value="<?php echo esc_attr($ap_r['fecha']); ?>" onchange="this.form.action = location.pathname + '#reservas'; this.form.submit();" aria-label="<?php esc_attr_e('Elegir día', 'area-profesores'); ?>">
        </form>
        <a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url($ap_url($ap_mover($ap_r['fecha'], 1))); ?>">&rarr;</a>
        <div class="ap-res-proximos">
            <?php foreach ($ap_r['proximos'] as $d => $n) : ?>
                <a href="<?php echo esc_url($ap_url($d)); ?>" class="ap-res-dia<?php echo $d === $ap_r['fecha'] ? ' is-active' : ''; ?><?php echo $n ? ' has-res' : ''; ?>">
                    <b><?php echo esc_html($d === $ap_r['hoy'] ? __('Hoy', 'area-profesores') : ucfirst(date_i18n('D j', strtotime($d . ' 12:00:00')))); ?></b>
                    <span><?php echo (int) $n; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <h3 class="ap-ma-titulo"><?php echo esc_html(ucfirst(date_i18n('l j \d\e F', strtotime($ap_r['fecha'] . ' 12:00:00')))); ?> · <?php printf(esc_html(_n('%d reserva', '%d reservas', count($ap_r['lista']), 'area-profesores')), count($ap_r['lista'])); ?></h3>

    <?php if (!$ap_r['lista']) : ?>
        <p class="ap-ma-vacio"><?php esc_html_e('No hay reservas para este día.', 'area-profesores'); ?></p>
    <?php endif; ?>

    <?php foreach ($ap_grupos as $ap_serv => $ap_lista) : ?>
        <div class="ap-res-grupo">
            <h4><?php echo esc_html($ap_serv); ?> <span><?php echo count($ap_lista); ?></span></h4>
            <table class="ap-ma-tabla ap-sortable">
                <thead><tr>
                    <th aria-sort="none"><button type="button" class="ap-sort-btn"><?php esc_html_e('Alumno', 'area-profesores'); ?><span class="ap-sort-ind" aria-hidden="true"></span></button></th>
                    <th aria-sort="none"><button type="button" class="ap-sort-btn"><?php esc_html_e('Curso', 'area-profesores'); ?><span class="ap-sort-ind" aria-hidden="true"></span></button></th>
                    <th aria-sort="none"><button type="button" class="ap-sort-btn"><?php esc_html_e('Hora', 'area-profesores'); ?><span class="ap-sort-ind" aria-hidden="true"></span></button></th>
                    <th><?php esc_html_e('Detalles', 'area-profesores'); ?></th>
                    <?php if ($ap_pt) : ?><th class="ap-ma-acc no-print"></th><?php endif; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($ap_lista as $r) : ?>
                        <tr>
                            <td class="ap-ma-alumno"><?php echo esc_html($r['alumno']); ?></td>
                            <?php $ap_cc = Area_Profesores_Cursos::get_curso($r['curso']); ?>
                            <td data-sort="<?php echo esc_attr(sprintf('%03d', $ap_cc ? $ap_cc['orden'] : 999)); ?>"><?php echo esc_html($r['curso_label']); ?></td>
                            <td data-sort="<?php echo esc_attr($r['hora']); ?>"><?php echo esc_html($r['hora'] !== '' ? $r['hora'] : '—'); ?></td>
                            <td class="ap-res-detalles">
                                <?php echo nl2br(esc_html(Area_Profesores_Reservas::detalles_texto($r))); ?>
                                <?php if ($r['fecha_inicio'] !== $r['fecha_fin']) : ?><small><?php echo esc_html($r['fechas_texto']); ?></small><?php endif; ?>
                            </td>
                            <?php if ($ap_pt) : ?>
                                <td class="ap-ma-acc no-print">
                                    <?php if (Area_Profesores_Reservas::puede_anular($ap_pt['uid'], $r)) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(sprintf(__('¿Anular la reserva de %1$s de %2$s (%3$s)? Se avisará a la familia y al tutor/a.', 'area-profesores'), $r['servicio_nombre'], $r['alumno'], $r['fechas_texto'])); ?>');">
                                        <input type="hidden" name="action" value="ap_reserva_anular_portal">
                                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                        <?php wp_nonce_field('ap_reserva_anular_portal_' . $r['id']); ?>
                                        <button type="submit" class="ap-btn ap-btn-sm ap-btn-outline ap-res-anular"><?php esc_html_e('Anular', 'area-profesores'); ?></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>
