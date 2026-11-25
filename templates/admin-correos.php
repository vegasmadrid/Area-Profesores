<?php
/**
 * Admin View: Avisos (every automatic e-mail of the web, who receives it and its text)
 * Variables: $defs, $flash, $roles, $por_rol
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ap-admin-wrap ap-correos-admin">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Avisos', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead"><?php esc_html_e('Todos los avisos que la web envía por correo. En cada uno eliges a qué cuentas especiales llega (Dirección, Secretaría, Administración…), si se activa o no, y su asunto y texto. Las cuentas de cada apartado se eligen en "Cuentas especiales".', 'area-profesores'); ?></p>
        </div>
    </div>

    <?php if ($flash) : ?>
        <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible"><p><?php echo esc_html($flash['text']); ?></p></div>
    <?php endif; ?>

    <div class="ap-card-table-wrap ap-avisos-resumen">
        <table class="wp-list-table widefat striped ap-table">
            <thead><tr>
                <th><?php esc_html_e('Aviso', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Se envía siempre a', 'area-profesores'); ?></th>
                <th><?php esc_html_e('Cuentas especiales y otros destinatarios', 'area-profesores'); ?></th>
                <th style="width:90px;"></th>
            </tr></thead>
            <tbody>
                <?php $ap_grupo = null; foreach ($defs as $key => $d) : $c = Area_Profesores_Correos::get($key); ?>
                    <?php if ($d['grupo'] !== $ap_grupo) : $ap_grupo = $d['grupo']; ?>
                        <tr class="ap-avisos-grupo"><th colspan="4"><?php echo esc_html($ap_grupo); ?></th></tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong><?php echo esc_html($d['titulo']); ?></strong>
                            <?php if (!$c['activo']) : ?> <span class="ap-status-pill ap-status-danger"><?php esc_html_e('Desactivado', 'area-profesores'); ?></span><?php endif; ?>
                            <br><span class="ap-mini"><?php echo esc_html($d['cuando']); ?></span></td>
                        <td class="ap-mini"><?php echo esc_html($d['para']); ?></td>
                        <td>
                            <?php $ap_dest = Area_Profesores_Correos::destinatarios_texto($key); ?>
                            <?php if ($ap_dest) : foreach ($ap_dest as $x) : ?><span class="ap-aviso-chip"><?php echo esc_html($x); ?></span> <?php endforeach; else : ?><span class="ap-mini">—</span><?php endif; ?>
                        </td>
                        <td><a class="button button-small" href="#correo-<?php echo esc_attr($key); ?>"><?php esc_html_e('Configurar', 'area-profesores'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php foreach ($defs as $key => $d) : $c = Area_Profesores_Correos::get($key); ?>
        <div class="ap-settings-card ap-correo-card" id="correo-<?php echo esc_attr($key); ?>">
            <h2 class="ap-settings-section-title"><span class="dashicons dashicons-email-alt"></span> <?php echo esc_html($d['titulo']); ?> <span class="ap-status-pill"><?php echo esc_html($d['grupo']); ?></span></h2>
            <p class="ap-correo-info"><strong><?php esc_html_e('Cuándo:', 'area-profesores'); ?></strong> <?php echo esc_html($d['cuando']); ?><br><strong><?php esc_html_e('Se envía siempre a:', 'area-profesores'); ?></strong> <?php echo esc_html($d['para']); ?><br><strong><?php esc_html_e('Remitente:', 'area-profesores'); ?></strong> <?php echo esc_html(Area_Profesores_Correos::remitente($key)); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ap_correos_guardar">
                <input type="hidden" name="correo" value="<?php echo esc_attr($key); ?>">
                <?php wp_nonce_field('ap_correos_guardar_' . $key); ?>

                <p><label><input type="checkbox" name="activo" value="1" <?php checked($c['activo']); ?>> <strong><?php esc_html_e('Enviar este aviso', 'area-profesores'); ?></strong></label></p>

                <fieldset class="ap-aviso-cuentas">
                    <legend><strong><?php esc_html_e('Enviar también a estas cuentas especiales', 'area-profesores'); ?></strong></legend>
                    <?php foreach ($roles as $r => $x) : $ap_us = $por_rol[$r]; ?>
                        <label title="<?php echo esc_attr($ap_us ? implode(', ', wp_list_pluck($ap_us, 'user_email')) : __('Ninguna cuenta todavía', 'area-profesores')); ?>">
                            <input type="checkbox" name="cuentas[]" value="<?php echo esc_attr($r); ?>" <?php checked(in_array($r, $c['cuentas'], true)); ?>>
                            <span class="dashicons <?php echo esc_attr($x['icono']); ?>"></span> <?php echo esc_html($x['nombre']); ?>
                            <span class="ap-mini">(<?php echo $ap_us ? esc_html(implode(', ', wp_list_pluck($ap_us, 'display_name'))) : esc_html__('sin cuentas', 'area-profesores'); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (isset($d['tutores']) || isset($d['cocina'])) : ?>
                        <span class="ap-aviso-sep"><?php esc_html_e('Según el caso:', 'area-profesores'); ?></span>
                        <?php if (isset($d['tutores'])) : ?>
                            <label><input type="checkbox" name="tutores" value="1" <?php checked($c['tutores']); ?>> <span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e('El tutor/a del alumno (o de sus hijos)', 'area-profesores'); ?></label>
                        <?php endif; ?>
                        <?php if (isset($d['cocina'])) : ?>
                            <label><input type="checkbox" name="cocina" value="1" <?php checked($c['cocina']); ?>> <span class="dashicons dashicons-carrot"></span> <?php esc_html_e('Cocina, solo si es una reserva de comedor o dieta', 'area-profesores'); ?></label>
                        <?php endif; ?>
                    <?php endif; ?>
                </fieldset>

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
                <p><label><strong><?php esc_html_e('Otros correos', 'area-profesores'); ?></strong> <span class="description"><?php esc_html_e('(que no sean cuentas especiales; separados por comas; opcional)', 'area-profesores'); ?></span><br>
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
