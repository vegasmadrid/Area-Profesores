<?php
/**
 * "Mi comida en el colegio": days a teacher does not eat at school.
 * Variables: $cd (Area_Profesores_Comensales::datos_portal()) with $cd['profe']
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
    <?php $cp = $cd['profe']; ?>
    <!-- Mi comida: días que el profesor NO come en el colegio -->
    <section class="ap-cp" id="ap-mi-comida">
        <div class="ap-cp-head">
            <div>
                <h3 class="ap-ma-titulo"><?php esc_html_e('Mi comida en el colegio', 'area-profesores'); ?></h3>
                <p class="ap-records-subtitle"><?php esc_html_e('Si algún día no vas a comer en el colegio, márcalo aquí. Cocina recibe en su resumen cuántos profesores no comen y quiénes.', 'area-profesores'); ?></p>
            </div>
            <?php if ($cp['hoy_lectivo']) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-cp-hoy <?php echo $cp['hoy_no_come'] ? 'is-no' : 'is-si'; ?>">
                    <input type="hidden" name="action" value="ap_comedor_profe">
                    <?php wp_nonce_field('ap_comedor_profe'); ?>
                    <span class="ap-cp-hoy-estado"><?php echo $cp['hoy_no_come'] ? esc_html__('Hoy NO comes en el colegio', 'area-profesores') : esc_html__('Hoy comes en el colegio', 'area-profesores'); ?></span>
                    <?php if ($cp['hoy_no_come']) : ?>
                        <button type="submit" name="hoy" value="si" class="ap-btn ap-btn-sm ap-btn-outline"><?php esc_html_e('Sí como hoy', 'area-profesores'); ?></button>
                    <?php else : ?>
                        <button type="submit" name="hoy" value="no" class="ap-btn ap-btn-sm ap-btn-outline"><?php esc_html_e('Hoy no como', 'area-profesores'); ?></button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
        <details class="ap-cp-dias">
            <summary><?php esc_html_e('Marcar otros días que no como en el colegio', 'area-profesores'); ?>
                <?php $cp_n = 0; foreach ($cp['semanas'] as $w) { foreach ($w as $d) { $cp_n += ($d['no_come'] && !$d['pasado']) ? 1 : 0; } } ?>
                <?php if ($cp_n) : ?><span class="ap-cp-count"><?php printf(esc_html(_n('%d día marcado', '%d días marcados', $cp_n, 'area-profesores')), (int) $cp_n); ?></span><?php endif; ?>
            </summary>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ap_comedor_profe">
                <?php wp_nonce_field('ap_comedor_profe'); ?>
                <p class="ap-records-subtitle"><?php esc_html_e('Pulsa los días en los que NO comes en el colegio (se ponen en rojo) y guarda. Los días sin marcar se entiende que comes.', 'area-profesores'); ?></p>
                <div class="ap-cp-grid" role="group" aria-label="<?php esc_attr_e('Días que no como en el colegio', 'area-profesores'); ?>">
                    <?php foreach (array('L', 'M', 'X', 'J', 'V') as $cp_l) : ?><span class="ap-cp-dow"><?php echo esc_html($cp_l); ?></span><?php endforeach; ?>
                    <?php foreach ($cp['semanas'] as $w) : foreach ($w as $d) : $cp_t = strtotime($d['fecha'] . ' 12:00:00'); $cp_off = $d['pasado'] || !$d['lectivo']; ?>
                        <label class="ap-cp-dia<?php echo $d['hoy'] ? ' is-hoy' : ''; ?><?php echo $cp_off ? ' is-off' : ''; ?><?php echo !$d['lectivo'] ? ' is-festivo' : ''; ?>" title="<?php echo esc_attr(ucfirst(date_i18n('l j \d\e F', $cp_t)) . (!$d['lectivo'] ? ' · ' . ($d['motivo'] !== '' ? $d['motivo'] : __('sin clase', 'area-profesores')) : '')); ?>">
                            <?php if (!$cp_off) : ?>
                                <input type="hidden" name="vistos[]" value="<?php echo esc_attr($d['fecha']); ?>">
                                <input type="checkbox" name="no_come[]" value="<?php echo esc_attr($d['fecha']); ?>" <?php checked($d['no_come']); ?>>
                            <?php endif; ?>
                            <span class="ap-cp-num"><?php echo esc_html(date_i18n('j', $cp_t)); ?><small><?php echo esc_html(date_i18n('M', $cp_t)); ?></small></span>
                            <span class="ap-cp-txt"><?php echo !$d['lectivo'] ? esc_html__('Sin clase', 'area-profesores') : ($d['no_come'] ? esc_html__('No como', 'area-profesores') : ($d['pasado'] ? '' : esc_html__('Como', 'area-profesores'))); ?></span>
                        </label>
                    <?php endforeach; endforeach; ?>
                </div>
                <?php if ($cp['enviado']) : ?><p class="ap-cd-nota"><?php esc_html_e('El resumen de hoy ya se ha enviado a cocina: si cambias el día de hoy, se le avisa con un correo.', 'area-profesores'); ?></p><?php endif; ?>
                <button type="submit" class="ap-btn ap-btn-sm ap-btn-primary"><?php esc_html_e('Guardar mis días', 'area-profesores'); ?></button>
            </form>
        </details>
    </section>
