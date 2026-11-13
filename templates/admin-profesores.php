<?php
/**
 * Admin View: Gestión de Profesores
 * Screen under "Área Profesores > Profesores"
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ap-admin-wrap">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Gestión del Claustro de Profesores', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead">
                <?php esc_html_e('Marca qué usuarios registrados en la web tienen rol de profesor para permitirles el acceso al Área de Profesores y asígnales su curso de tutoría.', 'area-profesores'); ?>
            </p>
        </div>
        <div class="ap-admin-shortcode-box">
            <span class="ap-shortcode-label"><?php esc_html_e('Shortcode del Área Docente:', 'area-profesores'); ?></span>
            <code class="ap-shortcode-code">[area_profesores]</code>
        </div>
    </div>

    <!-- Filter and Search Bar -->
    <div class="ap-admin-toolbar">
        <form method="get" class="ap-filter-form">
            <input type="hidden" name="page" value="area-profesores">

            <div class="ap-filter-group">
                <select name="filter_profesor" onchange="this.form.submit()">
                    <option value=""><?php esc_html_e('Todos los usuarios', 'area-profesores'); ?></option>
                    <option value="yes" <?php selected($filter_profesor, 'yes'); ?>><?php esc_html_e('Solo Profesores activos', 'area-profesores'); ?></option>
                    <option value="no" <?php selected($filter_profesor, 'no'); ?>><?php esc_html_e('Usuarios no profesores', 'area-profesores'); ?></option>
                </select>

                <div class="ap-search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar por nombre, usuario o email...', 'area-profesores'); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Buscar', 'area-profesores'); ?></button>
                    <?php if (!empty($search) || !empty($filter_profesor)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores')); ?>" class="button"><?php esc_html_e('Limpiar', 'area-profesores'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="ap-toolbar-stats">
            <span><?php printf(esc_html__('Mostrando %d de %d usuarios', 'area-profesores'), count($users), $total_users); ?></span>
        </div>
    </div>

    <form method="post" id="ap-admin-profesores-form">
        <?php wp_nonce_field('ap_save_profesores_nonce'); ?>

        <div class="ap-card-table-wrap">
            <table class="wp-list-table widefat fixed striped table-view-list ap-table">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('Avatar', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Nombre y Usuario', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Correo electrónico', 'area-profesores'); ?></th>
                        <th style="width: 170px; text-align: center;"><?php esc_html_e('¿Es Profesor?', 'area-profesores'); ?></th>
                        <th style="width: 220px;"><?php esc_html_e('Tutoría Asignada', 'area-profesores'); ?></th>
                        <th style="width: 120px; text-align: right;"><?php esc_html_e('Acciones', 'area-profesores'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)) : ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px;">
                                <?php esc_html_e('No se encontraron usuarios que coincidan con la búsqueda.', 'area-profesores'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($users as $u) : 
                            $is_prof = Area_Profesores_Admin::is_user_profesor($u->ID);
                            $tutoria = Area_Profesores_Admin::get_user_tutoria($u->ID);
                        ?>
                            <tr class="ap-user-row <?php echo $is_prof ? 'is-teacher' : ''; ?>" data-user-id="<?php echo esc_attr($u->ID); ?>">
                                <input type="hidden" name="all_user_ids[]" value="<?php echo esc_attr($u->ID); ?>">
                                
                                <td>
                                    <?php echo get_avatar($u->ID, 36, '', esc_attr($u->display_name), array('class' => 'ap-table-avatar')); ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($u->display_name); ?></strong>
                                    <div class="ap-user-login-subtext">@<?php echo esc_html($u->user_login); ?></div>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($u->user_email); ?>"><?php echo esc_html($u->user_email); ?></a>
                                </td>
                                <td style="text-align: center;">
                                    <label class="ap-toggle-switch">
                                        <input type="checkbox" 
                                               class="ap-teacher-toggle" 
                                               name="is_profesor[<?php echo esc_attr($u->ID); ?>]" 
                                               value="1" 
                                               data-user-id="<?php echo esc_attr($u->ID); ?>" 
                                               <?php checked($is_prof, true); ?>>
                                        <span class="ap-toggle-slider"></span>
                                    </label>
                                    <span class="ap-ajax-status" id="status-<?php echo esc_attr($u->ID); ?>"></span>
                                </td>
                                <td>
                                    <select name="tutoria[<?php echo esc_attr($u->ID); ?>]" 
                                            class="ap-tutoria-select" 
                                            data-user-id="<?php echo esc_attr($u->ID); ?>">
                                        <option value=""><?php esc_html_e('-- Sin tutoría --', 'area-profesores'); ?></option>
                                        <?php foreach ($available_classes as $val => $lbl) : ?>
                                            <option value="<?php echo esc_attr($val); ?>" <?php selected($tutoria, $val); ?>>
                                                <?php echo esc_html($lbl); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?php echo esc_url(get_edit_user_link($u->ID)); ?>" class="button button-small" target="_blank">
                                        <?php esc_html_e('Perfil WP', 'area-profesores'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="ap-admin-footer">
            <button type="submit" name="ap_save_profesores" class="button button-primary button-large">
                <?php esc_html_e('Guardar todos los cambios', 'area-profesores'); ?>
            </button>
            <span class="description" style="margin-left: 12px;">
                <?php esc_html_e('Nota: Los cambios en los conmutadores y desplegables también se guardan automáticamente al modificarlos.', 'area-profesores'); ?>
            </span>
        </div>
    </form>

    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base'    => add_query_arg('paged', '%#%'),
                    'format'  => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'   => $total_pages,
                    'current' => $paged,
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
