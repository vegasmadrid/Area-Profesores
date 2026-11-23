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

    <?php
    $ap_aj = Area_Profesores_Comensales::ajustes();
    $ap_est = Area_Profesores_Comensales::estados($fecha);
    $ap_cocina = Area_Profesores_Correos::emails_configurados('comedor_cocina');
    ?>
    <?php if (isset($_GET['ap_msg'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['ap_msg']))); ?></p></div>
    <?php endif; ?>

    <div class="ap-settings-card">
        <h2 class="ap-settings-section-title"><span class="dashicons dashicons-clock"></span> <?php esc_html_e('Comedor diario de Primer Ciclo, Infantil y Primaria', 'area-profesores'); ?></h2>
        <p class="description"><?php esc_html_e('Cada tutor/a ve la lista de su clase con los comensales fijos ya marcados, marca las excepciones y valida. Secundaria y Bachillerato no usan este sistema.', 'area-profesores'); ?></p>
        <?php if (!$ap_cocina) : ?>
            <div class="notice notice-warning inline"><p><?php esc_html_e('El resumen diario no se enviará a nadie: añade cuentas de Cocina en "Cuentas especiales" o elige otros destinatarios en "Avisos".', 'area-profesores'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-cuentas#rol-cocina')); ?>"><?php esc_html_e('Cuentas especiales', 'area-profesores'); ?></a> · <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-correos#correo-comedor_cocina')); ?>"><?php esc_html_e('Avisos', 'area-profesores'); ?></a></p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="ap_comedor_ajustes">
            <?php wp_nonce_field('ap_comedor_ajustes'); ?>
            <table class="form-table" role="presentation">
                <tr><th><?php esc_html_e('Recordatorio a los tutores', 'area-profesores'); ?></th>
                    <td><label><input type="checkbox" name="recordatorio" value="1" <?php checked($ap_aj['recordatorio'], 'yes'); ?>> <?php esc_html_e('A las', 'area-profesores'); ?></label>
                        <input type="time" name="hora_recordatorio" value="<?php echo esc_attr($ap_aj['hora_recordatorio']); ?>">
                        <?php esc_html_e('se avisa por correo a los tutores que no han validado su clase.', 'area-profesores'); ?></td></tr>
                <tr><th><?php esc_html_e('Resumen para cocina', 'area-profesores'); ?></th>
                    <td><label><input type="checkbox" name="resumen" value="1" <?php checked($ap_aj['resumen'], 'yes'); ?>> <?php esc_html_e('A las', 'area-profesores'); ?></label>
                        <input type="time" name="hora_cocina" value="<?php echo esc_attr($ap_aj['hora_cocina']); ?>">
                        <?php esc_html_e('se envía el total de comensales a', 'area-profesores'); ?>
                        (<?php echo $ap_cocina ? esc_html(implode(', ', $ap_cocina)) : esc_html__('nadie', 'area-profesores'); ?>).
                        <p class="description"><?php esc_html_e('Los cambios posteriores también se avisan. Los destinatarios y los textos se cambian en "Avisos".', 'area-profesores'); ?></p></td></tr>
                <tr><th><?php esc_html_e('Días sin clase', 'area-profesores'); ?></th>
                    <td><textarea name="dias_sin_clase" class="large-text code" rows="6" placeholder="08/12/2026 | Inmaculada&#10;23/12/2026 - 07/01/2027 | Navidad"><?php echo esc_textarea($ap_aj['dias_sin_clase']); ?></textarea>
                        <p class="description"><?php esc_html_e('Uno por línea: un día o un periodo; después de "|", un comentario. Esos días (y los fines de semana) no se pide el comedor ni se envían avisos. El Área Familias también los usa para no dejar reservar.', 'area-profesores'); ?></p></td></tr>
            </table>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e('Guardar', 'area-profesores'); ?></button>
                <button type="submit" name="probar" value="cocina" class="button"><?php esc_html_e('Guardar y enviar ahora el resumen de hoy a Cocina', 'area-profesores'); ?></button>
                <button type="submit" name="probar" value="recordatorio" class="button" onclick="return confirm('<?php echo esc_js(__('¿Enviar ahora el recordatorio a los tutores que no han validado hoy?', 'area-profesores')); ?>');"><?php esc_html_e('Enviar ahora los recordatorios', 'area-profesores'); ?></button>
            </p>
        </form>

        <?php if ($ap_est) : ?>
            <h3><?php printf(esc_html__('Clases el %s', 'area-profesores'), esc_html(date_i18n('j/n/Y', strtotime($fecha . ' 12:00:00')))); ?></h3>
            <div class="ap-cd-admin-estados">
                <?php foreach ($ap_est as $ap_e) : ?>
                    <span class="ap-status-pill <?php echo $ap_e['registro'] ? 'ap-status-success' : 'ap-status-danger'; ?>" title="<?php echo esc_attr($ap_e['registro'] ? sprintf(__('Validado por %s', 'area-profesores'), $ap_e['registro']['validado_nombre']) : __('Sin validar', 'area-profesores')); ?>">
                        <?php echo esc_html($ap_e['label'] . ': ' . ($ap_e['registro'] ? $ap_e['registro']['total'] : '—')); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
