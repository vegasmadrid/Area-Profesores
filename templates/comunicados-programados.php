<?php
/**
 * Teacher Portal: scheduled announcements.
 *
 * Variables: $programados (array from Area_Profesores_Chat::get_programados())
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<?php if (empty($programados)) : ?>
    <div class="ap-empty-state ap-empty-state-sm">
        <p><?php esc_html_e('No hay comunicados programados.', 'area-profesores'); ?></p>
    </div>
<?php else : ?>
    <div class="ap-com-historial-list">
        <?php foreach ($programados as $c) : ?>
            <article class="ap-com-item is-programado">
                <?php if ($c['thumb_url']) : ?>
                    <a href="<?php echo esc_url($c['image_url']); ?>" target="_blank" rel="noopener" class="ap-com-item-thumb">
                        <img src="<?php echo esc_url($c['thumb_url']); ?>" alt="" loading="lazy">
                    </a>
                <?php elseif (!empty($c['es_pdf'])) : ?>
                    <a href="<?php echo esc_url($c['image_url']); ?>" target="_blank" rel="noopener" class="ap-com-item-thumb ap-com-item-pdf" title="<?php echo esc_attr($c['archivo']); ?>">PDF</a>
                <?php endif; ?>
                <div class="ap-com-item-body">
                    <div class="ap-com-item-when">
                        🕗 <?php echo esc_html($c['programado_texto']); ?>
                        <?php if ($c['estado'] === 'enviando') : ?>
                            <span class="ap-com-pill is-ok"><?php esc_html_e('Enviando…', 'area-profesores'); ?></span>
                        <?php elseif ($c['retrasado']) : ?>
                            <span class="ap-com-pill is-error"><?php esc_html_e('Pendiente de envío', 'area-profesores'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ap-com-item-meta">
                        <span><?php printf(esc_html__('Programado por %s', 'area-profesores'), esc_html($c['autor'])); ?></span>
                    </div>
                    <h4 class="ap-com-item-title"><?php echo esc_html($c['titulo_lista']); ?></h4>
                    <?php if ($c['mensaje'] !== '') : ?>
                        <p class="ap-com-item-text"><?php echo esc_html(wp_trim_words($c['mensaje'], 30, '…')); ?></p>
                    <?php endif; ?>
                    <div class="ap-com-item-espacios">
                        <?php foreach ($c['espacio_nombres'] as $nombre) : ?>
                            <span class="ap-com-pill"><?php echo esc_html($nombre); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($c['estado'] === 'programado') : ?>
                        <div class="ap-com-item-actions">
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline ap-com-edit"
                                    data-comunicado="<?php echo esc_attr($c['id']); ?>"
                                    data-form="<?php echo esc_attr(wp_json_encode(Area_Profesores_Chat::get_form_data($c))); ?>">
                                <?php esc_html_e('Editar', 'area-profesores'); ?>
                            </button>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline ap-com-send-now" data-comunicado="<?php echo esc_attr($c['id']); ?>" data-total="<?php echo esc_attr(count($c['espacios'])); ?>">
                                <?php esc_html_e('Enviar ya', 'area-profesores'); ?>
                            </button>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-subtle-danger ap-com-cancel" data-comunicado="<?php echo esc_attr($c['id']); ?>">
                                <?php esc_html_e('Cancelar', 'area-profesores'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
