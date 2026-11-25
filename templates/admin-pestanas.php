<?php
/**
 * Admin View: Pestañas del Área de Profesores (name, order, groups).
 * Variables: $config, $grupos, $flash
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ap-admin-wrap ap-pestanas-admin">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Pestañas', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead"><?php esc_html_e('Las pestañas que ven los profesores en el Área: su nombre, el orden y qué grupos ven cada una. Basta con pertenecer a uno de los grupos marcados. Los administradores de la web ven siempre todas las pestañas activas.', 'area-profesores'); ?></p>
        </div>
    </div>

    <?php if ($flash) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($flash); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="ap-pestanas-form">
        <input type="hidden" name="action" value="ap_pestanas_guardar">
        <?php wp_nonce_field('ap_pestanas_guardar'); ?>

        <div class="ap-pestanas-lista">
            <?php foreach ($config as $k => $t) : ?>
                <div class="ap-settings-card ap-pestana<?php echo $t['activa'] ? '' : ' is-off'; ?>" data-key="<?php echo esc_attr($k); ?>">
                    <div class="ap-pestana-head">
                        <span class="ap-pestana-orden">
                            <button type="button" class="button button-small ap-pestana-subir" title="<?php esc_attr_e('Subir', 'area-profesores'); ?>" aria-label="<?php esc_attr_e('Subir', 'area-profesores'); ?>">↑</button>
                            <button type="button" class="button button-small ap-pestana-bajar" title="<?php esc_attr_e('Bajar', 'area-profesores'); ?>" aria-label="<?php esc_attr_e('Bajar', 'area-profesores'); ?>">↓</button>
                            <input type="hidden" name="pestanas[<?php echo esc_attr($k); ?>][orden]" value="<?php echo (int) $t['orden']; ?>" class="ap-pestana-orden-valor">
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="ap-pestana-icono" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo esc_attr($t['icono']); ?>" /></svg>
                        <label class="ap-pestana-nombre">
                            <span class="screen-reader-text"><?php esc_html_e('Nombre de la pestaña', 'area-profesores'); ?></span>
                            <input type="text" name="pestanas[<?php echo esc_attr($k); ?>][nombre]" value="<?php echo esc_attr($t['nombre']); ?>" placeholder="<?php echo esc_attr($t['nombre_defecto']); ?>" maxlength="40">
                        </label>
                        <?php if ($t['nombre'] !== $t['nombre_defecto']) : ?>
                            <span class="description"><?php printf(esc_html__('(original: %s)', 'area-profesores'), esc_html($t['nombre_defecto'])); ?></span>
                        <?php endif; ?>
                        <label class="ap-pestana-activa">
                            <input type="checkbox" name="pestanas[<?php echo esc_attr($k); ?>][activa]" value="1" <?php checked($t['activa']); ?>>
                            <?php esc_html_e('Activa', 'area-profesores'); ?>
                        </label>
                    </div>
                    <p class="description"><?php echo esc_html($t['descripcion']); ?></p>
                    <div class="ap-pestana-grupos">
                        <?php foreach ($grupos as $seccion => $lista) : ?>
                            <fieldset>
                                <legend><?php echo esc_html($seccion); ?></legend>
                                <?php foreach ($lista as $g => $label) : ?>
                                    <label class="<?php echo $g === 'todos' ? 'is-todos' : ''; ?>">
                                        <input type="checkbox" name="pestanas[<?php echo esc_attr($k); ?>][grupos][]" value="<?php echo esc_attr($g); ?>" <?php checked(in_array($g, $t['grupos'], true)); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="ap-form-actions">
            <button type="submit" class="button button-primary button-large"><?php esc_html_e('Guardar', 'area-profesores'); ?></button>
            <button type="submit" name="restablecer" value="1" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('¿Volver a los nombres, el orden y los grupos de siempre?', 'area-profesores')); ?>');"><?php esc_html_e('Restablecer todo', 'area-profesores'); ?></button>
        </p>
    </form>
</div>
<script>
(function () {
    var lista = document.querySelector('.ap-pestanas-lista');
    var numerar = function () {
        lista.querySelectorAll('.ap-pestana').forEach(function (el, i) {
            el.querySelector('.ap-pestana-orden-valor').value = (i + 1) * 10;
        });
    };
    lista.addEventListener('click', function (e) {
        var b = e.target.closest('.ap-pestana-subir, .ap-pestana-bajar');
        if (!b) { return; }
        var card = b.closest('.ap-pestana');
        if (b.classList.contains('ap-pestana-subir') && card.previousElementSibling) {
            lista.insertBefore(card, card.previousElementSibling);
        } else if (b.classList.contains('ap-pestana-bajar') && card.nextElementSibling) {
            lista.insertBefore(card.nextElementSibling, card);
        }
        numerar();
        b.focus();
    });
    lista.addEventListener('change', function (e) {
        if (e.target.matches('.ap-pestana-activa input')) {
            e.target.closest('.ap-pestana').classList.toggle('is-off', !e.target.checked);
        }
    });
    numerar();
})();
</script>
