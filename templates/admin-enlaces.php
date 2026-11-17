<?php
/**
 * Admin View: Enlaces Útiles
 * Screen under "Área Profesores > Enlaces Útiles"
 */

if (!defined('ABSPATH')) {
    exit;
}

$form = $editing ? $editing : array(
    'id'            => '',
    'titulo'        => '',
    'url'           => '',
    'descripcion'   => '',
    'categoria'     => '',
    'icono'         => '',
    'audiencia'     => 'todos',
    'destacado'     => false,
    'nueva_pestana' => true,
    'activo'        => true,
    'orden'         => '',
);

$messages = array(
    'added'   => array('success', __('Enlace añadido correctamente.', 'area-profesores')),
    'updated' => array('success', __('Enlace actualizado correctamente.', 'area-profesores')),
    'deleted' => array('success', __('Enlace eliminado.', 'area-profesores')),
    'missing' => array('error', __('El título y la URL son obligatorios.', 'area-profesores')),
);

$suggested_icons = array('🔗', '📚', '📅', '📝', '📊', '🎓', '🏫', '📧', '💻', '🗂️', '📢', '🍽️', '🧾', '🎥', '☁️', '⚙️');
?>

<div class="wrap ap-admin-wrap">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Enlaces Útiles', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead">
                <?php esc_html_e('Gestiona los enlaces que verán los profesores en la pestaña "Enlaces Útiles" de su área: plataformas educativas, calendarios, documentos del centro, formularios, etc.', 'area-profesores'); ?>
            </p>
        </div>
    </div>

    <?php if ($message && isset($messages[$message])) : ?>
        <div class="notice notice-<?php echo esc_attr($messages[$message][0]); ?> is-dismissible"><p><?php echo esc_html($messages[$message][1]); ?></p></div>
    <?php endif; ?>

    <div class="ap-enlaces-layout">
        <!-- Formulario alta / edición -->
        <div class="ap-settings-card ap-enlaces-form-card">
            <h2 class="ap-settings-section-title">
                <span class="dashicons <?php echo $editing ? 'dashicons-edit' : 'dashicons-plus-alt'; ?>"></span>
                <?php echo $editing ? esc_html__('Editar enlace', 'area-profesores') : esc_html__('Añadir nuevo enlace', 'area-profesores'); ?>
            </h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ap_save_enlace">
                <input type="hidden" name="enlace_id" value="<?php echo esc_attr($form['id']); ?>">
                <?php wp_nonce_field('ap_save_enlace'); ?>

                <p>
                    <label for="ap-enlace-titulo"><strong><?php esc_html_e('Título *', 'area-profesores'); ?></strong></label><br>
                    <input type="text" id="ap-enlace-titulo" name="titulo" class="widefat" required value="<?php echo esc_attr($form['titulo']); ?>" placeholder="<?php esc_attr_e('Ej: Calendario escolar', 'area-profesores'); ?>">
                </p>

                <p>
                    <label for="ap-enlace-url"><strong><?php esc_html_e('URL *', 'area-profesores'); ?></strong></label><br>
                    <input type="text" id="ap-enlace-url" name="url" class="widefat" required value="<?php echo esc_attr($form['url']); ?>" placeholder="https://">
                    <span class="description"><?php esc_html_e('También admite enlaces mailto: y tel:', 'area-profesores'); ?></span>
                </p>

                <p>
                    <label for="ap-enlace-descripcion"><strong><?php esc_html_e('Descripción', 'area-profesores'); ?></strong></label><br>
                    <textarea id="ap-enlace-descripcion" name="descripcion" class="widefat" rows="2" placeholder="<?php esc_attr_e('Texto breve que aparece bajo el título (opcional)', 'area-profesores'); ?>"><?php echo esc_textarea($form['descripcion']); ?></textarea>
                </p>

                <div class="ap-enlaces-row">
                    <p>
                        <label for="ap-enlace-categoria"><strong><?php esc_html_e('Categoría', 'area-profesores'); ?></strong></label><br>
                        <input type="text" id="ap-enlace-categoria" name="categoria" class="widefat" list="ap-enlace-categorias" value="<?php echo esc_attr($form['categoria']); ?>" placeholder="<?php esc_attr_e('Ej: Plataformas', 'area-profesores'); ?>">
                        <datalist id="ap-enlace-categorias">
                            <?php foreach ($categories as $cat) : ?>
                                <option value="<?php echo esc_attr($cat); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </p>
                    <p class="ap-enlaces-icon-field">
                        <label for="ap-enlace-icono"><strong><?php esc_html_e('Icono', 'area-profesores'); ?></strong></label><br>
                        <input type="text" id="ap-enlace-icono" name="icono" class="widefat" maxlength="4" value="<?php echo esc_attr($form['icono']); ?>" placeholder="🔗">
                    </p>
                </div>

                <div class="ap-enlaces-icon-picker" aria-label="<?php esc_attr_e('Iconos sugeridos', 'area-profesores'); ?>">
                    <?php foreach ($suggested_icons as $ic) : ?>
                        <button type="button" class="button ap-icon-pick" data-icon="<?php echo esc_attr($ic); ?>"><?php echo esc_html($ic); ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="ap-enlaces-row">
                    <p>
                        <label for="ap-enlace-audiencia"><strong><?php esc_html_e('Visible para', 'area-profesores'); ?></strong></label><br>
                        <select id="ap-enlace-audiencia" name="audiencia" class="widefat">
                            <?php foreach ($audiences as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($form['audiencia'], $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p class="ap-enlaces-icon-field">
                        <label for="ap-enlace-orden"><strong><?php esc_html_e('Orden', 'area-profesores'); ?></strong></label><br>
                        <input type="number" id="ap-enlace-orden" name="orden" class="widefat" value="<?php echo esc_attr($form['orden']); ?>" placeholder="10">
                    </p>
                </div>

                <p>
                    <label><input type="checkbox" name="destacado" value="1" <?php checked($form['destacado']); ?>> <?php esc_html_e('Destacado (aparece arriba, en "Accesos rápidos")', 'area-profesores'); ?></label><br>
                    <label><input type="checkbox" name="nueva_pestana" value="1" <?php checked($form['nueva_pestana']); ?>> <?php esc_html_e('Abrir en una pestaña nueva', 'area-profesores'); ?></label><br>
                    <label><input type="checkbox" name="activo" value="1" <?php checked($form['activo']); ?>> <?php esc_html_e('Visible para los profesores', 'area-profesores'); ?></label>
                </p>

                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo $editing ? esc_html__('Guardar cambios', 'area-profesores') : esc_html__('Añadir enlace', 'area-profesores'); ?>
                    </button>
                    <?php if ($editing) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-enlaces')); ?>" class="button"><?php esc_html_e('Cancelar', 'area-profesores'); ?></a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <!-- Listado -->
        <div class="ap-card-table-wrap ap-enlaces-list-card">
            <table class="wp-list-table widefat striped ap-table">
                <thead>
                    <tr>
                        <th style="width: 44px;"></th>
                        <th><?php esc_html_e('Enlace', 'area-profesores'); ?></th>
                        <th style="width: 18%;"><?php esc_html_e('Categoría', 'area-profesores'); ?></th>
                        <th style="width: 16%;"><?php esc_html_e('Visible para', 'area-profesores'); ?></th>
                        <th style="width: 60px;"><?php esc_html_e('Orden', 'area-profesores'); ?></th>
                        <th style="width: 90px;"><?php esc_html_e('Estado', 'area-profesores'); ?></th>
                        <th style="width: 170px;"><?php esc_html_e('Acciones', 'area-profesores'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($links)) : ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 32px;">
                                <?php esc_html_e('Todavía no hay enlaces. Añade el primero con el formulario.', 'area-profesores'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($links as $link) : ?>
                            <tr<?php echo $link['activo'] ? '' : ' style="opacity: .55;"'; ?>>
                                <td style="font-size: 20px; text-align: center;"><?php echo esc_html($link['icono'] !== '' ? $link['icono'] : '🔗'); ?></td>
                                <td>
                                    <strong><?php echo esc_html($link['titulo']); ?></strong>
                                    <?php if ($link['destacado']) : ?>
                                        <span class="ap-status-pill ap-status-warning">★ <?php esc_html_e('Destacado', 'area-profesores'); ?></span>
                                    <?php endif; ?>
                                    <br>
                                    <a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener" style="font-size: 12px; word-break: break-all;"><?php echo esc_html($link['url']); ?></a>
                                    <?php if ($link['descripcion'] !== '') : ?>
                                        <div style="color: #64748b; font-size: 12px; margin-top: 2px;"><?php echo esc_html($link['descripcion']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($link['categoria'] !== '' ? $link['categoria'] : __('General', 'area-profesores')); ?></td>
                                <td><?php echo esc_html($audiences[$link['audiencia']]); ?></td>
                                <td><?php echo esc_html($link['orden']); ?></td>
                                <td>
                                    <?php if ($link['activo']) : ?>
                                        <span class="ap-status-pill ap-status-success"><?php esc_html_e('Visible', 'area-profesores'); ?></span>
                                    <?php else : ?>
                                        <span class="ap-status-pill ap-status-danger"><?php esc_html_e('Oculto', 'area-profesores'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-enlaces&edit=' . $link['id'])); ?>"><?php esc_html_e('Editar', 'area-profesores'); ?></a>
                                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_toggle_enlace&id=' . $link['id']), 'ap_toggle_enlace_' . $link['id'])); ?>">
                                        <?php echo $link['activo'] ? esc_html__('Ocultar', 'area-profesores') : esc_html__('Mostrar', 'area-profesores'); ?>
                                    </a>
                                    <a class="button button-small button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_delete_enlace&id=' . $link['id']), 'ap_delete_enlace_' . $link['id'])); ?>" onclick="return confirm('<?php echo esc_js(__('¿Eliminar este enlace?', 'area-profesores')); ?>');">
                                        <?php esc_html_e('Eliminar', 'area-profesores'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    (function () {
        var input = document.getElementById('ap-enlace-icono');
        document.querySelectorAll('.ap-icon-pick').forEach(function (btn) {
            btn.addEventListener('click', function () {
                input.value = btn.getAttribute('data-icon');
            });
        });
    })();
</script>
