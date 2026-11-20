<?php
/**
 * Admin View: Cuentas especiales
 * Variables: $roles, $todos, $flash
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ap-admin-wrap ap-cuentas-admin">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Cuentas especiales', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead"><?php esc_html_e('Elige qué cuentas son de Dirección, Secretaría, Cocina y Portería. Una misma cuenta puede estar en varios apartados y seguir siendo tutor/a o especialista.', 'area-profesores'); ?></p>
        </div>
    </div>

    <?php if ($flash) : ?>
        <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible"><p><?php echo esc_html($flash['text']); ?></p></div>
    <?php endif; ?>

    <div class="ap-cuentas-grid">
        <?php foreach ($roles as $rol => $r) : $usuarios = Area_Profesores_Cuentas::usuarios($rol); $ids = wp_list_pluck($usuarios, 'ID'); ?>
            <div class="ap-settings-card ap-cuentas-card" id="rol-<?php echo esc_attr($rol); ?>">
                <h2 class="ap-settings-section-title"><span class="dashicons <?php echo esc_attr($r['icono']); ?>"></span> <?php echo esc_html($r['nombre']); ?> <span class="ap-status-pill"><?php echo count($usuarios); ?></span></h2>
                <p class="description"><?php echo esc_html($r['desc']); ?></p>

                <?php if (!$usuarios) : ?>
                    <p class="ap-cuentas-vacio"><?php esc_html_e('Ninguna cuenta todavía.', 'area-profesores'); ?></p>
                <?php else : ?>
                    <ul class="ap-cuentas-lista">
                        <?php foreach ($usuarios as $u) : ?>
                            <li>
                                <?php echo get_avatar($u->ID, 28); ?>
                                <span><strong><?php echo esc_html($u->display_name); ?></strong><br><small><?php echo esc_html($u->user_email); ?></small></span>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="ap_cuentas">
                                    <input type="hidden" name="op" value="quitar">
                                    <input type="hidden" name="rol" value="<?php echo esc_attr($rol); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr($u->ID); ?>">
                                    <?php wp_nonce_field('ap_cuentas'); ?>
                                    <button type="submit" class="button-link button-link-delete" title="<?php esc_attr_e('Quitar', 'area-profesores'); ?>"><?php esc_html_e('Quitar', 'area-profesores'); ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-cuentas-anadir">
                    <input type="hidden" name="action" value="ap_cuentas">
                    <input type="hidden" name="op" value="anadir">
                    <input type="hidden" name="rol" value="<?php echo esc_attr($rol); ?>">
                    <?php wp_nonce_field('ap_cuentas'); ?>
                    <select name="user_id" required>
                        <option value=""><?php esc_html_e('— Elige una cuenta —', 'area-profesores'); ?></option>
                        <?php foreach ($todos as $t) : if (in_array($t->ID, $ids)) { continue; } ?>
                            <option value="<?php echo esc_attr($t->ID); ?>"><?php echo esc_html($t->display_name . ' (' . $t->user_email . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Añadir', 'area-profesores'); ?></button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
