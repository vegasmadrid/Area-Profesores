<?php
/**
 * Backend table of reservations.
 * Variables: $ap_lista (rows), $ap_con_servicio (bool, optional)
 */

if (!defined('ABSPATH')) {
    exit;
}
$ap_con_servicio = !empty($ap_con_servicio);
?>
<table class="widefat striped ap-reservas-tabla ap-sortable">
    <thead><tr>
        <?php if ($ap_con_servicio) : ?><th aria-sort="none"><button type="button" class="ap-sort-btn"><?php esc_html_e('Servicio', 'area-profesores'); ?><span class="ap-sort-ind" aria-hidden="true"></span></button></th><?php endif; ?>
        <th aria-sort="none"><button type="button" class="ap-sort-btn"><?php esc_html_e('Alumno', 'area-profesores'); ?><span class="ap-sort-ind" aria-hidden="true"></span></button></th>
        <th aria-sort="none"><button type="button" class="ap-sort-btn"><?php esc_html_e('Curso', 'area-profesores'); ?><span class="ap-sort-ind" aria-hidden="true"></span></button></th>
        <th aria-sort="none"><button type="button" class="ap-sort-btn"><?php esc_html_e('Fechas', 'area-profesores'); ?><span class="ap-sort-ind" aria-hidden="true"></span></button></th>
        <th><?php esc_html_e('Detalles', 'area-profesores'); ?></th>
        <th aria-sort="none"><button type="button" class="ap-sort-btn"><?php esc_html_e('Familia', 'area-profesores'); ?><span class="ap-sort-ind" aria-hidden="true"></span></button></th>
        <th class="no-print"></th>
    </tr></thead>
    <tbody>
        <?php foreach ($ap_lista as $r) : ?>
            <tr class="<?php echo $r['estado'] === 'anulada' ? 'is-anulada' : ''; ?>">
                <?php if ($ap_con_servicio) : ?><td><strong><?php echo esc_html($r['servicio_nombre']); ?></strong></td><?php endif; ?>
                <td><strong><?php echo esc_html($r['alumno']); ?></strong></td>
                <?php $ap_cc = Area_Profesores_Cursos::get_curso($r['curso']); ?>
                <td data-sort="<?php echo esc_attr(sprintf('%03d', $ap_cc ? $ap_cc['orden'] : 999)); ?>"><?php echo esc_html($r['curso_label']); ?></td>
                <td data-sort="<?php echo esc_attr($r['fecha_inicio'] . ' ' . $r['hora']); ?>"><?php echo esc_html($r['fechas_texto']); ?></td>
                <td class="ap-reservas-detalles"><?php echo nl2br(esc_html(Area_Profesores_Reservas::detalles_texto($r))); ?></td>
                <td><?php echo esc_html($r['familia']); ?><br><a href="mailto:<?php echo esc_attr($r['email']); ?>"><?php echo esc_html($r['email']); ?></a></td>
                <td class="no-print">
                    <?php if ($r['estado'] === 'activa') : ?>
                        <a class="button button-small button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_reserva_anular&id=' . $r['id']), 'ap_reserva_anular_' . $r['id'])); ?>"
                           onclick="return confirm('<?php echo esc_js(__('¿Anular esta reserva? Se avisará a la familia, al tutor/a y a Secretaría.', 'area-profesores')); ?>');"><?php esc_html_e('Anular', 'area-profesores'); ?></a>
                    <?php else : ?>
                        <span class="ap-status-pill ap-status-danger"><?php echo esc_html($r['anulado_por'] === 'colegio' ? __('Anulada por el colegio', 'area-profesores') : __('Anulada por la familia', 'area-profesores')); ?></span>
                    <?php endif; ?>
                    <div class="ap-reservas-creado"><?php printf(esc_html__('Reservada el %s', 'area-profesores'), esc_html(date_i18n('j/n/Y H:i', strtotime((string) $r['creado'])))); ?></div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
