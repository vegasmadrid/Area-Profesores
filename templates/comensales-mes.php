<?php
/**
 * Teacher Portal: "Comensales" tab (Secretaría, Dirección and administrators).
 * Meals per day of a month and the extra meals of each pupil (to charge them).
 *
 * Variables: $comensales_mes { mes, datos }
 */

if (!defined('ABSPATH')) {
    exit;
}

$cm = $comensales_mes;
$cm_d = $cm['datos'];
$cm_t = strtotime($cm['mes'] . '-01 12:00:00');
$cm_url = function ($mes) {
    return add_query_arg('ap_mes', $mes, remove_query_arg('ap_mes')) . '#comensales';
};
$cm_prev = gmdate('Y-m', strtotime('-1 month', $cm_t));
$cm_next = gmdate('Y-m', strtotime('+1 month', $cm_t));
?>
<div class="ap-records-card ap-cm">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Comensales', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Comidas de cada día (Primer Ciclo, Infantil y Primaria) y comidas extra de cada alumno, para hacer los cobros. Son extra las de los alumnos que no son fijos ese día, y las que reservan las familias.', 'area-profesores'); ?></p>
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
    </div>

    <h3 class="ap-ma-titulo"><?php esc_html_e('Comidas extra por alumno', 'area-profesores'); ?></h3>
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
        <p class="ap-records-subtitle"><span class="ap-cm-dia is-familia">👪</span> <?php esc_html_e('= reservada por la familia desde el Área Familias.', 'area-profesores'); ?></p>
    <?php endif; ?>

    <h3 class="ap-ma-titulo"><?php esc_html_e('Comensales por día', 'area-profesores'); ?></h3>
    <?php if (!$cm_d['dias']) : ?>
        <p class="ap-ma-vacio"><?php esc_html_e('No hay ningún día con el comedor validado este mes.', 'area-profesores'); ?></p>
    <?php else : ?>
        <table class="ap-ma-tabla">
            <thead><tr>
                <th><?php esc_html_e('Día', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Comensales', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Extra', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Reservas de familias', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Clases validadas', 'area-profesores'); ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($cm_d['dias'] as $f => $x) : ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst(date_i18n('l j', strtotime($f . ' 12:00:00')))); ?></td>
                        <td><strong><?php echo (int) $x['total']; ?></strong></td>
                        <td><?php echo (int) $x['extra']; ?></td>
                        <td><?php echo (int) $x['familia']; ?></td>
                        <td><?php echo (int) $x['clases']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
