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
            $ok = 0;
            $fallidos = array();
            foreach ($c['resultados'] as $eid => $r) {
                if (!empty($r['ok'])) {
                    $ok++;
                } else {
                    $fallidos[$eid] = $r;
                }
            }
        ?>
            <article class="ap-com-item">
                <?php if ($c['thumb_url']) : ?>
                    <a href="<?php echo esc_url(wp_get_attachment_url($c['image_id'])); ?>" target="_blank" rel="noopener" class="ap-com-item-thumb">
                        <img src="<?php echo esc_url($c['thumb_url']); ?>" alt="" loading="lazy">
                    </a>
                <?php endif; ?>
                <div class="ap-com-item-body">
                    <div class="ap-com-item-meta">
                        <span><?php echo esc_html($c['fecha']); ?></span>
                        <?php if ($c['autor']) : ?>
                            <span>· <?php echo esc_html($c['autor']); ?></span>
                        <?php endif; ?>
                    </div>
                    <h4 class="ap-com-item-title"><?php echo esc_html($c['titulo']); ?></h4>
                    <?php if ($c['mensaje'] !== '') : ?>
                        <p class="ap-com-item-text"><?php echo esc_html(wp_trim_words($c['mensaje'], 30, '…')); ?></p>
                    <?php endif; ?>
                    <div class="ap-com-item-espacios">
                        <?php foreach ($c['resultados'] as $eid => $r) : ?>
                            <span class="ap-com-pill <?php echo !empty($r['ok']) ? 'is-ok' : 'is-error'; ?>" title="<?php echo esc_attr(!empty($r['ok']) ? __('Publicado', 'area-profesores') : $r['error']); ?>">
                                <?php echo !empty($r['ok']) ? '✓' : '✗'; ?> <?php echo esc_html($r['nombre']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($fallidos)) : ?>
                        <button type="button" class="ap-btn ap-btn-sm ap-btn-outline ap-com-retry-old"
                                data-comunicado="<?php echo esc_attr($c['id']); ?>"
                                data-espacios="<?php echo esc_attr(wp_json_encode(array_keys($fallidos))); ?>">
                            <?php printf(esc_html__('Reintentar %d fallido(s)', 'area-profesores'), count($fallidos)); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
