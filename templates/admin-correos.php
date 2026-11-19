<?php
/**
 * Admin View: Correos automáticos
 * Variables: $defs, $flash, $secretaria
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ap-admin-wrap ap-correos-admin">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Correos automáticos', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead"><?php esc_html_e('Todos los correos que se envían solos. Puedes activarlos o desactivarlos, cambiar el asunto y el texto, y añadir más destinatarios. Las palabras entre llaves, como {nombre}, se sustituyen por los datos de cada caso; una línea que solo contenga {enlace} se convierte en un botón.', 'area-profesores'); ?></p>
        </div>
    </div>

    <?php if ($flash) : ?>
        <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible"><p><?php echo esc_html($flash['text']); ?></p></div>
    <?php endif; ?>

    <nav class="ap-correos-indice">
        <?php foreach ($defs as $key => $d) : $c = Area_Profesores_Correos::get($key); ?>
            <a href="#correo-<?php echo esc_attr($key); ?>"><?php echo esc_html($d['titulo']); ?><?php if (!$c['activo']) : ?> <span class="ap-status-pill ap-status-danger"><?php esc_html_e('Desactivado', 'area-profesores'); ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>

    <?php foreach ($defs as $key => $d) : $c = Area_Profesores_Correos::get($key); ?>
        <div class="ap-settings-card ap-correo-card" id="correo-<?php echo esc_attr($key); ?>">
            <h2 class="ap-settings-section-title"><span class="dashicons dashicons-email-alt"></span> <?php echo esc_html($d['titulo']); ?></h2>
            <p class="ap-correo-info"><strong><?php esc_html_e('Cuándo:', 'area-profesores'); ?></strong> <?php echo esc_html($d['cuando']); ?><br><strong><?php esc_html_e('A quién:', 'area-profesores'); ?></strong> <?php echo esc_html($d['para']); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ap_correos_guardar">
                <input type="hidden" name="correo" value="<?php echo esc_attr($key); ?>">
                <?php wp_nonce_field('ap_correos_guardar_' . $key); ?>

                <p><label><input type="checkbox" name="activo" value="1" <?php checked($c['activo']); ?>> <strong><?php esc_html_e('Enviar este correo', 'area-profesores'); ?></strong></label></p>

                <?php if (isset($d['tutores'])) : ?>
                    <p class="ap-correo-para">
                        <label><input type="checkbox" name="tutores" value="1" <?php checked($c['tutores']); ?>> <?php esc_html_e('A los tutores de sus hijos', 'area-profesores'); ?></label>
                        <label><input type="checkbox" name="secretaria" value="1" <?php checked($c['secretaria']); ?>> <?php esc_html_e('A Secretaría', 'area-profesores'); ?>
                            <span class="description">(<?php echo $secretaria ? esc_html(implode(', ', $secretaria)) : esc_html__('nadie marcado como Secretaría en Profesores', 'area-profesores'); ?>)</span></label>
                    </p>
                <?php endif; ?>

                <p><label><strong><?php esc_html_e('Asunto', 'area-profesores'); ?></strong><br>
                    <input type="text" name="asunto" class="widefat ap-correo-asunto" value="<?php echo esc_attr($c['asunto']); ?>"></label></p>
                <p><label><strong><?php esc_html_e('Mensaje', 'area-profesores'); ?></strong><br>
                    <textarea name="cuerpo" class="widefat ap-correo-cuerpo" rows="9"><?php echo esc_textarea($c['cuerpo']); ?></textarea></label></p>
                <p class="ap-correo-vars">
                    <span><?php esc_html_e('Variables (pulsa para insertarla):', 'area-profesores'); ?></span>
                    <?php foreach ($d['vars'] as $v => $desc) : ?>
                        <button type="button" class="button button-small ap-correo-var" data-var="{<?php echo esc_attr($v); ?>}" title="<?php echo esc_attr($desc); ?>">{<?php echo esc_html($v); ?>}</button>
                    <?php endforeach; ?>
                </p>
                <p><label><strong><?php esc_html_e('Enviar también a', 'area-profesores'); ?></strong> <span class="description"><?php esc_html_e('(correos separados por comas; opcional)', 'area-profesores'); ?></span><br>
                    <input type="text" name="extra" class="widefat" value="<?php echo esc_attr($c['extra']); ?>"></label></p>

                <p class="ap-form-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Guardar', 'area-profesores'); ?></button>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_correos_prueba&correo=' . $key), 'ap_correos_prueba_' . $key)); ?>"><?php printf(esc_html__('Enviarme una prueba (%s)', 'area-profesores'), esc_html(wp_get_current_user()->user_email)); ?></a>
                    <button type="submit" name="restablecer" value="1" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('¿Volver al asunto y el texto originales?', 'area-profesores')); ?>');"><?php esc_html_e('Restablecer texto original', 'area-profesores'); ?></button>
                </p>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<script>
(function () {
    var last = null;
    document.querySelectorAll('.ap-correo-asunto, .ap-correo-cuerpo').forEach(function (el) {
        el.addEventListener('focus', function () { last = el; });
    });
    document.querySelectorAll('.ap-correo-var').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = btn.closest('form');
            var el = (last && form.contains(last)) ? last : form.querySelector('.ap-correo-cuerpo');
            var v = btn.getAttribute('data-var');
            var s = el.selectionStart || el.value.length, e = el.selectionEnd || el.value.length;
            el.value = el.value.slice(0, s) + v + el.value.slice(e);
            el.focus();
            el.selectionStart = el.selectionEnd = s + v.length;
        });
    });
})();
</script>
