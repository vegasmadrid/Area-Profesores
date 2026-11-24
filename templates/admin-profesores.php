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
                <?php esc_html_e('Todos los usuarios de la web son profesores. Indica si son tutores de un curso o especialistas. Las cuentas de Dirección, Secretaría, Cocina y Portería se eligen en "Cuentas especiales". A los especialistas se les asignan las clases en las que dan clase, de una o varias etapas (botón "Clases").', 'area-profesores'); ?>
            </p>
        </div>
        <?php $ap_area_page_id = absint(Area_Profesores_Admin::get_setting('area_page_id', 0)); ?>
        <div class="ap-admin-shortcode-box">
            <span class="ap-shortcode-label"><?php esc_html_e('Página del Área Docente:', 'area-profesores'); ?></span>
            <?php if ($ap_area_page_id && get_post_status($ap_area_page_id)) : ?>
                <a class="ap-shortcode-code" href="<?php echo esc_url(get_permalink($ap_area_page_id)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($ap_area_page_id)); ?></a>
            <?php else : ?>
                <a class="ap-shortcode-code" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-ajustes')); ?>"><?php esc_html_e('Sin configurar (ir a Ajustes)', 'area-profesores'); ?></a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter and Search Bar -->
    <div class="ap-admin-toolbar">
        <form method="get" class="ap-filter-form">
            <input type="hidden" name="page" value="area-profesores">

            <div class="ap-filter-group">
                <select name="filter_etapa" onchange="this.form.submit()">
                    <option value=""><?php esc_html_e('Todas las etapas', 'area-profesores'); ?></option>
                    <?php foreach (Area_Profesores_Cursos::get_etapas() as $ek => $el) : ?>
                        <option value="<?php echo esc_attr($ek); ?>" <?php selected($filter_etapa, $ek); ?>><?php echo esc_html($el); ?></option>
                    <?php endforeach; ?>
                    <option value="especialistas" <?php selected($filter_etapa, 'especialistas'); ?>><?php esc_html_e('Solo especialistas', 'area-profesores'); ?></option>
                    <option value="sin" <?php selected($filter_etapa, 'sin'); ?>><?php esc_html_e('Sin asignar', 'area-profesores'); ?></option>
                </select>

                <div class="ap-search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar por nombre, usuario o email...', 'area-profesores'); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Buscar', 'area-profesores'); ?></button>
                    <?php if (!empty($search) || !empty($filter_etapa)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores')); ?>" class="button"><?php esc_html_e('Limpiar', 'area-profesores'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="ap-toolbar-stats">
            <span><?php printf(esc_html__('Mostrando %d de %d usuarios', 'area-profesores'), count($users), $total_users); ?></span>
            <span><?php printf(esc_html__('Comen en el colegio: %d', 'area-profesores'), count(Area_Profesores_Comensales::profes_comensales())); ?></span>
        </div>
    </div>

    <form method="post" id="ap-admin-profesores-form">
        <?php wp_nonce_field('ap_save_profesores_nonce'); ?>

        <div class="ap-card-table-wrap">
            <table class="wp-list-table widefat striped table-view-list ap-table ap-prof-tabla">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('Avatar', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Nombre y Usuario', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Correo electrónico', 'area-profesores'); ?></th>
                        <th style="min-width: 230px;"><?php esc_html_e('Tutoría o especialista', 'area-profesores'); ?></th>
                        <th style="width: 110px; text-align: center;" title="<?php esc_attr_e('Desmarca a quien no come nunca en el colegio. Los demás cuentan como que comen todos los días, salvo los que ellos avisen.', 'area-profesores'); ?>"><?php esc_html_e('Come en el colegio', 'area-profesores'); ?></th>
                        <th style="width: 160px; text-align: right;"><?php esc_html_e('Acciones', 'area-profesores'); ?></th>
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
                            $codigo = Area_Profesores_Cursos::get_codigo_asignacion($u->ID);
                        ?>
                            <tr class="ap-user-row is-teacher" data-user-id="<?php echo esc_attr($u->ID); ?>">
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
                                <td>
                                    <select name="asignacion[<?php echo esc_attr($u->ID); ?>]"
                                            class="ap-tutoria-select"
                                            data-user-id="<?php echo esc_attr($u->ID); ?>">
                                        <?php echo Area_Profesores_Cursos::render_codigo_options($codigo); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    </select>
                                    <span class="ap-ajax-status" id="status-<?php echo esc_attr($u->ID); ?>"></span>
                                    <?php $ap_cl = Area_Profesores_Alumnos::get_clases_asignadas($u->ID); ?>
                                    <div class="ap-clases-resumen">
                                        <?php if ($ap_cl) : ?>
                                            <?php foreach ($ap_cl as $ap_k) : ?><span class="ap-clase-chip"><?php echo esc_html(Area_Profesores_Cursos::get_curso($ap_k)['label']); ?></span><?php endforeach; ?>
                                        <?php elseif ($codigo === 'esp') : ?>
                                            <span class="ap-clases-falta"><?php esc_html_e('Sin clases asignadas', 'area-profesores'); ?></span>
                                        <?php endif; ?>
                                        <button type="button" class="button button-small ap-clases-btn"
                                                data-user-id="<?php echo esc_attr($u->ID); ?>"
                                                data-nombre="<?php echo esc_attr($u->display_name); ?>"
                                                data-nonce="<?php echo esc_attr(wp_create_nonce('ap_clases_guardar_' . $u->ID)); ?>"
                                                data-clases="<?php echo esc_attr(wp_json_encode($ap_cl)); ?>"><?php esc_html_e('Clases', 'area-profesores'); ?></button>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <label class="ap-come-label">
                                        <input type="checkbox" class="ap-come-check" name="come[<?php echo esc_attr($u->ID); ?>]" value="1" data-user-id="<?php echo esc_attr($u->ID); ?>" <?php checked(Area_Profesores_Comensales::come_en_colegio($u->ID)); ?>>
                                        <span class="screen-reader-text"><?php printf(esc_html__('%s come en el colegio', 'area-profesores'), esc_html($u->display_name)); ?></span>
                                    </label>
                                    <span class="ap-ajax-status" id="status-come-<?php echo esc_attr($u->ID); ?>"></span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?php echo esc_url(get_edit_user_link($u->ID)); ?>" class="button button-small" target="_blank">
                                        <?php esc_html_e('Perfil WP', 'area-profesores'); ?>
                                    </a>
                                    <?php if (method_exists('Area_Profesores_Admin', 'can_delete_user') && Area_Profesores_Admin::can_delete_user($u->ID)) : ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_delete_profesor&user_id=' . $u->ID), 'ap_delete_profesor_' . $u->ID)); ?>"
                                           class="button button-small ap-btn-delete-user"
                                           onclick="return confirm('<?php echo esc_js(sprintf(__('¿Eliminar definitivamente a %s de la web? Perderá el acceso y no se puede deshacer. Sus comunicados pasarán a tu nombre.', 'area-profesores'), $u->display_name)); ?>');">
                                            <?php esc_html_e('Eliminar', 'area-profesores'); ?>
                                        </a>
                                    <?php endif; ?>
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

    <?php if (isset($_GET['ap_clases_ok'])) : ?>
        <script>document.addEventListener('DOMContentLoaded', function () { var w = document.querySelector('.ap-admin-wrap'); if (w) { var n = document.createElement('div'); n.className = 'notice notice-success is-dismissible'; n.innerHTML = '<p><?php echo esc_js(__('Clases guardadas.', 'area-profesores')); ?></p>'; w.insertBefore(n, w.children[1]); } });</script>
    <?php endif; ?>

    <dialog id="ap-clases-dialog" class="ap-clases-dialog">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="ap_clases_guardar">
            <input type="hidden" name="user_id" value="">
            <input type="hidden" name="_wpnonce" value="">
            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(remove_query_arg('ap_clases_ok', wp_unslash($_SERVER['REQUEST_URI']))); ?>">
            <h2><?php esc_html_e('Clases de', 'area-profesores'); ?> <span class="ap-clases-nombre"></span></h2>
            <p class="description"><?php esc_html_e('Marca las clases en las que da clase, de cualquier etapa: verá sus alumnos en "Mis alumnos" y los enlaces de esas etapas. El tutor/a ya ve su propia clase sin marcarla.', 'area-profesores'); ?></p>
            <?php foreach (Area_Profesores_Cursos::get_cursos_por_etapa() as $ap_etapa => $ap_cursos) : ?>
                <fieldset class="ap-clases-etapa">
                    <legend><?php echo esc_html(Area_Profesores_Cursos::get_etapa_label($ap_etapa)); ?>
                        <button type="button" class="button-link ap-clases-todas"><?php esc_html_e('todas', 'area-profesores'); ?></button></legend>
                    <?php foreach ($ap_cursos as $ap_key => $ap_c) : ?>
                        <label><input type="checkbox" name="ap_clases[]" value="<?php echo esc_attr($ap_key); ?>"> <?php echo esc_html($ap_c['label']); ?></label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
            <p class="ap-form-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Guardar clases', 'area-profesores'); ?></button>
                <button type="button" class="button ap-clases-cerrar"><?php esc_html_e('Cancelar', 'area-profesores'); ?></button>
            </p>
        </form>
    </dialog>
    <script>
    (function () {
        var dlg = document.getElementById('ap-clases-dialog');
        if (!dlg || !dlg.showModal) { return; }
        var form = dlg.querySelector('form');
        document.querySelectorAll('.ap-clases-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var clases = JSON.parse(btn.getAttribute('data-clases') || '[]');
                form.user_id.value = btn.getAttribute('data-user-id');
                form._wpnonce.value = btn.getAttribute('data-nonce');
                dlg.querySelector('.ap-clases-nombre').textContent = btn.getAttribute('data-nombre');
                form.querySelectorAll('input[name="ap_clases[]"]').forEach(function (c) { c.checked = clases.indexOf(c.value) !== -1; });
                dlg.showModal();
            });
        });
        dlg.querySelectorAll('.ap-clases-todas').forEach(function (b) {
            b.addEventListener('click', function () {
                var boxes = b.closest('fieldset').querySelectorAll('input[type=checkbox]');
                var all = Array.prototype.every.call(boxes, function (c) { return c.checked; });
                boxes.forEach(function (c) { c.checked = !all; });
            });
        });
        dlg.querySelector('.ap-clases-cerrar').addEventListener('click', function () { dlg.close(); });
    })();
    </script>

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
