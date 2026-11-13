<?php
/**
 * Admin View: Comedor Diario
 * Screen under "Área Profesores > Comedor Diario"
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ap-admin-wrap">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores · Comedor', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Control Diario de Comedor Escolar', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead">
                <?php esc_html_e('Consulta en tiempo real los datos introducidos por cada profesor, el recuento de comensales y las dietas especiales notificadas para cocina.', 'area-profesores'); ?>
            </p>
        </div>

        <div class="ap-admin-header-actions">
            <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary">
                <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                <?php esc_html_e('Exportar a CSV / Excel', 'area-profesores'); ?>
            </a>
            <button type="button" class="button button-secondary" onclick="window.print()">
                <span class="dashicons dashicons-printer" style="margin-top: 4px;"></span>
                <?php esc_html_e('Imprimir Resumen', 'area-profesores'); ?>
            </button>
        </div>
    </div>

    <!-- Date selector bar -->
    <div class="ap-date-selector-bar">
        <form method="get" class="ap-date-form">
            <input type="hidden" name="page" value="area-profesores-comedor">
            <label for="fecha-input"><strong><?php esc_html_e('Seleccionar fecha:', 'area-profesores'); ?></strong></label>
            <input type="date" id="fecha-input" name="fecha" value="<?php echo esc_attr($fecha); ?>">
            <button type="submit" class="button button-primary"><?php esc_html_e('Filtrar', 'area-profesores'); ?></button>
            <?php if ($fecha !== current_time('Y-m-d')) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-comedor')); ?>" class="button"><?php esc_html_e('Ver Hoy', 'area-profesores'); ?></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Metrics Cards Summary -->
    <div class="ap-metrics-grid">
        <div class="ap-metric-card">
            <div class="ap-metric-number"><?php echo intval($summary->total_comensales); ?></div>
            <div class="ap-metric-label"><?php esc_html_e('Alumnos a comer (Estándar)', 'area-profesores'); ?></div>
        </div>
        <div class="ap-metric-card">
            <div class="ap-metric-number"><?php echo intval($summary->total_clases); ?></div>
            <div class="ap-metric-label"><?php esc_html_e('Clases reportadas', 'area-profesores'); ?></div>
        </div>
        <div class="ap-metric-card">
            <div class="ap-metric-number ap-text-orange"><?php echo intval($summary->clases_con_dietas); ?></div>
            <div class="ap-metric-label"><?php esc_html_e('Clases con dietas especiales', 'area-profesores'); ?></div>
        </div>
        <div class="ap-metric-card">
            <div class="ap-metric-number ap-text-red"><?php echo intval($summary->clases_con_incidencias); ?></div>
            <div class="ap-metric-label"><?php esc_html_e('Clases con alérgicos ausentes', 'area-profesores'); ?></div>
        </div>
    </div>

    <!-- Records Table -->
    <div class="ap-card-table-wrap">
        <table class="wp-list-table widefat fixed striped ap-table">
            <thead>
                <tr>
                    <th style="width: 90px;"><?php esc_html_e('Clase', 'area-profesores'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Etapa', 'area-profesores'); ?></th>
                    <th style="width: 140px; text-align: center;"><?php esc_html_e('Comensales (Estándar)', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Dietas Especiales', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Alérgicos ausentes / Obs.', 'area-profesores'); ?></th>
                    <th style="width: 160px;"><?php esc_html_e('Profesor que reporta', 'area-profesores'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Hora', 'area-profesores'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)) : ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 32px;">
                            <p style="margin: 0; font-size: 15px; color: #64748b;">
                                <?php esc_html_e('No hay registros de comedor para la fecha seleccionada.', 'area-profesores'); ?>
                            </p>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($records as $r) : ?>
                        <tr>
                            <td>
                                <strong class="ap-class-pill"><?php echo esc_html($r->clase); ?></strong>
                            </td>
                            <td><?php echo esc_html($r->etapa); ?></td>
                            <td style="text-align: center;">
                                <span class="ap-number-badge"><?php echo esc_html($r->num_alumnos); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($r->dietas)) : ?>
                                    <div class="ap-dietas-box">
                                        <?php echo nl2br(esc_html($r->dietas)); ?>
                                    </div>
                                <?php else : ?>
                                    <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r->alergicos_faltan)) : ?>
                                    <div class="ap-alert-box">
                                        <?php echo nl2br(esc_html($r->alergicos_faltan)); ?>
                                    </div>
                                <?php else : ?>
                                    <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($r->profesor_nombre ?: 'ID ' . $r->user_id); ?>
                            </td>
                            <td>
                                <?php echo esc_html(date_i18n('H:i', strtotime($r->created_at))); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
