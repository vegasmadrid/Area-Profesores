<?php
/**
 * Admin View: Ajustes del Área de Profesores
 * Screen under "Área Profesores > Ajustes"
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ap-admin-wrap">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Configuración del Área de Profesores', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead">
                <?php esc_html_e('Personaliza la identidad corporativa, el dominio institucional, las alertas por correo electrónico y el comportamiento del acceso con Nextend Social Login.', 'area-profesores'); ?>
            </p>
        </div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('ap_save_ajustes_nonce'); ?>

        <div class="ap-settings-card">
            <h2 class="ap-settings-section-title">
                <span class="dashicons dashicons-id-alt"></span>
                <?php esc_html_e('Identidad Institucional y Acceso Corporativo', 'area-profesores'); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Configuración del dominio escolar y los elementos visuales que verán los docentes en la pantalla de acceso.', 'area-profesores'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="allowed_domain"><?php esc_html_e('Dominio Corporativo Permitido', 'area-profesores'); ?></label></th>
                    <td>
                        <div class="ap-input-prefix-wrap">
                            <span class="ap-input-prefix">@</span>
                            <input type="text" name="allowed_domain" id="allowed_domain" value="<?php echo esc_attr($settings['allowed_domain']); ?>" class="regular-text" style="padding-left: 28px;">
                        </div>
                        <p class="description">
                            <?php esc_html_e('Solo los correos que terminen en este dominio podrán solicitar acceso e identificarse como profesores.', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="login_button_text"><?php esc_html_e('Texto del Botón de Acceso', 'area-profesores'); ?></label></th>
                    <td>
                        <input type="text" name="login_button_text" id="login_button_text" value="<?php echo esc_attr($settings['login_button_text']); ?>" class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Texto que se muestra en el botón de acceso (sin logotipo de Google para evitar confusiones).', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="tribanda_url"><?php esc_html_e('URL de la Tribanda Corporativa', 'area-profesores'); ?></label></th>
                    <td>
                        <input type="url" name="tribanda_url" id="tribanda_url" value="<?php echo esc_url($settings['tribanda_url']); ?>" class="large-text">
                        <p class="description">
                            <?php esc_html_e('Imagen de la tribanda superior de Casa de la Virgen.', 'area-profesores'); ?>
                        </p>
                        <?php if (!empty($settings['tribanda_url'])) : ?>
                            <div style="margin-top: 8px;">
                                <img src="<?php echo esc_url($settings['tribanda_url']); ?>" alt="Tribanda preview" style="max-height: 20px; max-width: 100%; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="logo_url"><?php esc_html_e('URL del Logotipo Institucional', 'area-profesores'); ?></label></th>
                    <td>
                        <input type="url" name="logo_url" id="logo_url" value="<?php echo esc_url($settings['logo_url']); ?>" class="large-text">
                        <p class="description">
                            <?php esc_html_e('Logotipo oficial del colegio adaptado a la página de profesores.', 'area-profesores'); ?>
                        </p>
                        <?php if (!empty($settings['logo_url'])) : ?>
                            <div style="margin-top: 8px;">
                                <img src="<?php echo esc_url($settings['logo_url']); ?>" alt="Logo preview" style="max-height: 48px; border: 1px solid #ddd; padding: 4px; border-radius: 4px; background: #fff;">
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="ap-settings-card" style="margin-top: 24px;">
            <h2 class="ap-settings-section-title">
                <span class="dashicons dashicons-shield"></span>
                <?php esc_html_e('Seguridad y Registro Restringido', 'area-profesores'); ?>
            </h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Control de Altas', 'area-profesores'); ?></th>
                    <td>
                        <label for="only_approved_can_register">
                            <input type="checkbox" name="only_approved_can_register" id="only_approved_can_register" value="yes" <?php checked($settings['only_approved_can_register'], 'yes'); ?>>
                            <strong><?php esc_html_e('Permitir acceso y registro en la web únicamente a usuarios aprobados por la dirección.', 'area-profesores'); ?></strong>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Si está activado, nadie podrá registrarse automáticamente mediante Google a menos que su solicitud haya sido aprobada previamente en el panel.', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Restringir toda la web', 'area-profesores'); ?></th>
                    <td>
                        <label for="restrict_site">
                            <input type="checkbox" name="restrict_site" id="restrict_site" value="yes" <?php checked($settings['restrict_site'], 'yes'); ?>>
                            <strong><?php esc_html_e('Solo los usuarios registrados que hayan iniciado sesión pueden ver las páginas de la web.', 'area-profesores'); ?></strong>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Los visitantes sin sesión serán redirigidos a la Página de Login configurada más abajo (o a wp-login.php si no hay ninguna seleccionada). La página de login siempre es pública.', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Páginas públicas (excepciones)', 'area-profesores'); ?></th>
                    <td>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 8px 12px; border-radius: 4px; background: #fff; max-width: 520px;">
                            <?php foreach ($pages as $p) : ?>
                                <label style="display: block; margin-bottom: 4px;">
                                    <input type="checkbox" name="public_page_ids[]" value="<?php echo esc_attr($p->ID); ?>" <?php checked(in_array((int) $p->ID, array_map('intval', $settings['public_page_ids']), true)); ?>>
                                    <?php echo esc_html($p->post_title); ?> (ID: <?php echo esc_html($p->ID); ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Marca las páginas que deben seguir siendo accesibles sin iniciar sesión (por ejemplo, la página de cocina).', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="notification_emails"><?php esc_html_e('Correos de Notificación de Solicitudes', 'area-profesores'); ?></label></th>
                    <td>
                        <input type="text" name="notification_emails" id="notification_emails" value="<?php echo esc_attr($settings['notification_emails']); ?>" class="large-text">
                        <p class="description">
                            <?php esc_html_e('Dirección o direcciones de correo que recibirán un aviso instantáneo cada vez que un docente solicite acceso (separar con comas si hay varias).', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="ap-settings-card" style="margin-top: 24px;">
            <h2 class="ap-settings-section-title">
                <span class="dashicons dashicons-admin-links"></span>
                <?php esc_html_e('Páginas y Redirecciones del Sistema', 'area-profesores'); ?>
            </h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="login_page_id"><?php esc_html_e('Página de Login', 'area-profesores'); ?></label></th>
                    <td>
                        <select name="login_page_id" id="login_page_id">
                            <option value="0"><?php esc_html_e('-- Selecciona la página de login --', 'area-profesores'); ?></option>
                            <?php foreach ($pages as $p) : ?>
                                <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($settings['login_page_id'], $p->ID); ?>>
                                    <?php echo esc_html($p->post_title); ?> (ID: <?php echo esc_html($p->ID); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('En esta página se mostrará automáticamente la pantalla de acceso (no hace falta ningún shortcode). Los usuarios sin sesión serán redirigidos aquí.', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="area_page_id"><?php esc_html_e('Página del Área de Profesores', 'area-profesores'); ?></label></th>
                    <td>
                        <select name="area_page_id" id="area_page_id">
                            <option value="0"><?php esc_html_e('-- Selecciona la página del Área de Profesores --', 'area-profesores'); ?></option>
                            <?php foreach ($pages as $p) : ?>
                                <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($settings['area_page_id'], $p->ID); ?>>
                                    <?php echo esc_html($p->post_title); ?> (ID: <?php echo esc_html($p->ID); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('En esta página se mostrará automáticamente el panel del profesor (no hace falta ningún shortcode). Es la página a la que se redirige al profesor tras iniciar sesión.', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="comedor_page_id"><?php esc_html_e('Página de Comedor / Cocina', 'area-profesores'); ?></label></th>
                    <td>
                        <select name="comedor_page_id" id="comedor_page_id">
                            <option value="0"><?php esc_html_e('-- Selecciona la página de cocina (opcional) --', 'area-profesores'); ?></option>
                            <?php foreach ($pages as $p) : ?>
                                <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($settings['comedor_page_id'], $p->ID); ?>>
                                    <?php echo esc_html($p->post_title); ?> (ID: <?php echo esc_html($p->ID); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('En esta página se mostrará automáticamente el resumen diario de comensales para cocina. Si debe verse sin iniciar sesión, márcala también en "Páginas públicas".', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <td colspan="2" style="padding-left:0;">
                        <p class="description">
                            <?php esc_html_e('Nota: el contenido que tengan estas páginas en el editor se sustituye por la pantalla correspondiente del plugin. Los shortcodes antiguos ([loginprofesores], [area_profesores], [comedorhoy]) siguen funcionando por compatibilidad, pero ya no son necesarios.', 'area-profesores'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 24px;">
            <button type="submit" name="ap_save_ajustes" class="button button-primary button-large">
                <?php esc_html_e('Guardar Configuración', 'area-profesores'); ?>
            </button>
        </div>
    </form>
</div>
