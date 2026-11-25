<?php
/**
 * Teacher Portal: list of the latest announcements sent to Google Chat.
 *
 * Variables: $historial (array from Area_Profesores_Chat::get_historial())
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<?php if (empty($historial)) : ?>
    <div class="ap-empty-state">
        <div class="ap-empty-icon">📭</div>
        <h3><?php esc_html_e('Aún no se ha publicado ningún comunicado', 'area-profesores'); ?></h3>
    </div>
<?php else : ?>
    <div class="ap-com-historial-list">
        <?php foreach ($historial as $c) :
            $fallidos = array();
            foreach ($c['resultados'] as $eid => $r) {
                if (empty($r['ok'])) {
                    $fallidos[] = $eid;
                }
            }
            $cancelado = ($c['estado'] === 'cancelado');
        ?>
            <article class="ap-com-item <?php echo $cancelado ? 'is-cancelado' : ''; ?>">
                <?php if ($c['thumb_url']) : ?>
                    <a href="<?php echo esc_url($c['image_url']); ?>" target="_blank" rel="noopener" class="ap-com-item-thumb">
                        <img src="<?php echo esc_url($c['thumb_url']); ?>" alt="" loading="lazy">
                    </a>
                <?php elseif (!empty($c['archivo_google'])) : ?>
                    <span class="ap-com-item-thumb ap-com-item-pdf" title="<?php echo esc_attr(sprintf(__('%s (guardado en Google Chat)', 'area-profesores'), $c['archivo_google']['nombre'])); ?>"><?php echo !empty($c['archivo_google']['pdf']) ? 'PDF' : '🖼️'; ?></span>
                <?php elseif (!empty($c['es_pdf'])) : ?>
                    <a href="<?php echo esc_url($c['image_url']); ?>" target="_blank" rel="noopener" class="ap-com-item-thumb ap-com-item-pdf" title="<?php echo esc_attr($c['archivo']); ?>">PDF</a>
                <?php endif; ?>
                <div class="ap-com-item-body">
                    <div class="ap-com-item-meta">
                        <?php if ($cancelado) : ?>
                            <span class="ap-com-pill is-error"><?php esc_html_e('Cancelado', 'area-profesores'); ?></span>
                            <span><?php printf(esc_html__('Estaba programado para %s', 'area-profesores'), esc_html($c['programado_texto'])); ?></span>
                        <?php else : ?>
                            <span><?php echo esc_html($c['fecha']); ?></span>
                            <?php if ($c['programado']) : ?>
                                <span>· <?php esc_html_e('programado', 'area-profesores'); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($c['autor']) : ?>
                            <span>· <?php echo esc_html($c['autor']); ?><?php if ($c['cuenta'] !== '') : ?> <?php printf(esc_html__('(como %s)', 'area-profesores'), esc_html($c['cuenta'])); ?><?php endif; ?></span>
                        <?php endif; ?>
                    </div>
                    <h4 class="ap-com-item-title"><?php echo esc_html($c['titulo_lista']); ?></h4>
                    <?php if ($c['mensaje'] !== '') : ?>
                        <p class="ap-com-item-text"><?php echo esc_html(wp_trim_words($c['mensaje'], 30, '…')); ?></p>
                    <?php endif; ?>
                    <div class="ap-com-item-espacios">
                        <?php if ($cancelado) : ?>
                            <?php foreach ($c['espacio_nombres'] as $nombre) : ?>
                                <span class="ap-com-pill"><?php echo esc_html($nombre); ?></span>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <?php foreach ($c['resultados'] as $eid => $r) : ?>
                                <span class="ap-com-pill <?php echo !empty($r['ok']) ? 'is-ok' : 'is-error'; ?>" title="<?php echo esc_attr(!empty($r['ok']) ? __('Publicado', 'area-profesores') : $r['error']); ?>">
                                    <?php echo !empty($r['ok']) ? '✓' : '✗'; ?> <?php echo esc_html($r['nombre']); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="ap-com-item-actions">
                        <?php if (!empty($fallidos) && !$cancelado) : ?>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline ap-com-retry-old"
                                    data-comunicado="<?php echo esc_attr($c['id']); ?>"
                                    data-espacios="<?php echo esc_attr(wp_json_encode($fallidos)); ?>">
                                <?php printf(esc_html__('Reintentar %d fallido(s)', 'area-profesores'), count($fallidos)); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="ap-btn ap-btn-sm ap-btn-outline ap-com-template"
                                data-form="<?php echo esc_attr(wp_json_encode(Area_Profesores_Chat::get_form_data($c))); ?>">
                            <?php esc_html_e('Usar como plantilla', 'area-profesores'); ?>
                        </button>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
