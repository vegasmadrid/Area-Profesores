<?php
/**
 * Admin View: Solicitudes de Acceso
 * Screen under "Área Profesores > Solicitudes de Acceso"
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ap-admin-wrap">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Solicitudes de Acceso de Profesores', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead">
                <?php esc_html_e('Revisa y aprueba las peticiones de los nuevos docentes. Al aprobar una solicitud, el profesor tendrá acceso inmediato al Área de Profesores cuando acceda con su cuenta @casadelavirgen.es.', 'area-profesores'); ?>
            </p>
        </div>
        <div class="ap-admin-shortcode-box">
            <span class="ap-shortcode-label"><?php esc_html_e('Shortcode de la página de Login:', 'area-profesores'); ?></span>
            <code class="ap-shortcode-code">[loginprofesores]</code>
        </div>
    </div>

    <!-- Status Tabs Nav -->
    <ul class="subsubsub ap-admin-tabs">
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-solicitudes')); ?>" class="<?php echo empty($estado_filter) ? 'current' : ''; ?>">
                <?php esc_html_e('Todas', 'area-profesores'); ?> <span class="count">(<?php echo intval($count_total); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(add_query_arg('estado', 'pendiente', admin_url('admin.php?page=area-profesores-solicitudes'))); ?>" class="<?php echo ($estado_filter === 'pendiente') ? 'current' : ''; ?>">
                <?php esc_html_e('Pendientes', 'area-profesores'); ?> <span class="count" style="color: #d97706; font-weight: 700;">(<?php echo intval($count_pendiente); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(add_query_arg('estado', 'aprobada', admin_url('admin.php?page=area-profesores-solicitudes'))); ?>" class="<?php echo ($estado_filter === 'aprobada') ? 'current' : ''; ?>">
                <?php esc_html_e('Aprobadas', 'area-profesores'); ?> <span class="count" style="color: #059669;">(<?php echo intval($count_aprobada); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(add_query_arg('estado', 'rechazada', admin_url('admin.php?page=area-profesores-solicitudes'))); ?>" class="<?php echo ($estado_filter === 'rechazada') ? 'current' : ''; ?>">
                <?php esc_html_e('Rechazadas', 'area-profesores'); ?> <span class="count">(<?php echo intval($count_rechazada); ?>)</span>
            </a>
        </li>
    </ul>

    <!-- Filter and Search Toolbar -->
    <div class="ap-admin-toolbar" style="margin-top: 10px;">
        <form method="get" class="ap-filter-form">
            <input type="hidden" name="page" value="area-profesores-solicitudes">
            <?php if (!empty($estado_filter)) : ?>
                <input type="hidden" name="estado" value="<?php echo esc_attr($estado_filter); ?>">
            <?php endif; ?>

            <div class="ap-search-box">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar por nombre, apellidos o email...', 'area-profesores'); ?>">
                <button type="submit" class="button"><?php esc_html_e('Buscar', 'area-profesores'); ?></button>
                <?php if (!empty($search)) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-solicitudes' . (!empty($estado_filter) ? '&estado=' . $estado_filter : ''))); ?>" class="button"><?php esc_html_e('Limpiar', 'area-profesores'); ?></a>
                <?php endif; ?>
            </div>
        </form>

        <div class="ap-toolbar-stats">
            <span><?php printf(esc_html__('Mostrando %d solicitudes', 'area-profesores'), count($solicitudes)); ?></span>
        </div>
    </div>

    <!-- Table of Requests -->
    <div class="ap-card-table-wrap">
        <table class="wp-list-table widefat fixed striped table-view-list ap-table">
            <thead>
                <tr>
                    <th style="width: 220px;"><?php esc_html_e('Profesor / Solicitante', 'area-profesores'); ?></th>
                    <th style="width: 230px;"><?php esc_html_e('Email Institucional', 'area-profesores'); ?></th>
                    <th style="width: 170px;"><?php esc_html_e('Tutoría Asignada', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Observaciones / Especialidad', 'area-profesores'); ?></th>
                    <th style="width: 110px;"><?php esc_html_e('Fecha', 'area-profesores'); ?></th>
                    <th style="width: 110px; text-align: center;"><?php esc_html_e('Estado', 'area-profesores'); ?></th>
                    <th style="width: 180px; text-align: right;"><?php esc_html_e('Acciones', 'area-profesores'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($solicitudes)) : ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 36px;">
                            <p style="font-size: 15px; color: #64748b; margin: 0;">
                                <?php esc_html_e('No hay solicitudes de acceso en este listado.', 'area-profesores'); ?>
                            </p>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($solicitudes as $sol) : ?>
                        <tr class="ap-solicitud-row ap-row-estado-<?php echo esc_attr($sol->estado); ?>" data-solicitud-id="<?php echo esc_attr($sol->id); ?>">
                            <td>
                                <strong style="font-size: 14px; color: #0f172a;"><?php echo esc_html($sol->nombre . ' ' . $sol->apellidos); ?></strong>
                            </td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($sol->email); ?>" style="font-weight: 600;"><?php echo esc_html($sol->email); ?></a>
                            </td>
                            <td>
                                <?php if ($sol->estado === 'pendiente') : ?>
                                    <select class="ap-solicitud-tutoria-select" data-id="<?php echo esc_attr($sol->id); ?>" style="width: 100%;">
                                        <option value=""><?php esc_html_e('-- Sin tutoría --', 'area-profesores'); ?></option>
                                        <?php foreach ($available_classes as $val => $lbl) : ?>
                                            <option value="<?php echo esc_attr($val); ?>" <?php selected($sol->tutoria_deseada, $val); ?>>
                                                <?php echo esc_html($lbl); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else : ?>
                                    <span class="ap-badge-tutoria-plain">
                                        <?php echo esc_html(!empty($sol->tutoria_deseada) && isset($available_classes[$sol->tutoria_deseada]) ? $available_classes[$sol->tutoria_deseada] : ($sol->tutoria_deseada ?: '—')); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($sol->observaciones)) : ?>
                                    <span class="ap-obs-text"><?php echo esc_html($sol->observaciones); ?></span>
                                <?php else : ?>
                                    <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html(date_i18n('d/m/Y', strtotime($sol->created_at))); ?><br>
                                <span style="font-size: 11px; color: #94a3b8;"><?php echo esc_html(date_i18n('H:i', strtotime($sol->created_at))); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($sol->estado === 'pendiente') : ?>
                                    <span class="ap-status-pill ap-status-warning"><?php esc_html_e('Pendiente', 'area-profesores'); ?></span>
                                <?php elseif ($sol->estado === 'aprobada') : ?>
                                    <span class="ap-status-pill ap-status-success"><?php esc_html_e('Aprobada', 'area-profesores'); ?></span>
                                <?php else : ?>
                                    <span class="ap-status-pill ap-status-danger"><?php esc_html_e('Rechazada', 'area-profesores'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($sol->estado === 'pendiente') : ?>
                                    <button type="button" class="button button-primary ap-btn-approve-solicitud" data-id="<?php echo esc_attr($sol->id); ?>">
                                        <?php esc_html_e('Aprobar', 'area-profesores'); ?>
                                    </button>
                                    <button type="button" class="button ap-btn-reject-solicitud" data-id="<?php echo esc_attr($sol->id); ?>">
                                        <?php esc_html_e('Rechazar', 'area-profesores'); ?>
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="button button-link-delete ap-btn-delete-solicitud" data-id="<?php echo esc_attr($sol->id); ?>" style="color: #b91c1c;">
                                        <?php esc_html_e('Eliminar', 'area-profesores'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
