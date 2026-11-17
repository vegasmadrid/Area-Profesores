<?php
/**
 * Admin View: Espacios de Chat
 * Screen under "Área Profesores > Espacios de Chat"
 */

if (!defined('ABSPATH')) {
    exit;
}

$form = $editing ? $editing : array(
    'id'      => '',
    'nombre'  => '',
    'grupo'   => '',
    'webhook' => '',
    'activo'  => true,
    'orden'   => '',
);

$messages = array(
    'added'      => array('success', __('Espacio añadido. Usa "Probar" para comprobar que llega el mensaje.', 'area-profesores')),
    'updated'    => array('success', __('Espacio actualizado correctamente.', 'area-profesores')),
    'deleted'    => array('success', __('Espacio eliminado.', 'area-profesores')),
    'missing'    => array('error', __('El nombre y la URL del webhook son obligatorios.', 'area-profesores')),
    'invalid'    => array('error', __('La URL no parece un webhook de Google Chat. Debe empezar por https://chat.googleapis.com/v1/spaces/…', 'area-profesores')),
    'test_ok'    => array('success', __('Mensaje de prueba enviado. Compruébalo en el espacio de Google Chat.', 'area-profesores')),
    'test_error' => array('error', __('No se pudo enviar el mensaje de prueba.', 'area-profesores')),
);
?>

<div class="wrap ap-admin-wrap">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Espacios de Google Chat', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead">
                <?php esc_html_e('Da de alta los espacios de Chat de las familias. Los profesores marcados como Dirección podrán publicar el mismo comunicado (texto e imagen) en varios espacios a la vez desde la pestaña "Comunicados" de su Área.', 'area-profesores'); ?>
            </p>
        </div>
    </div>

    <?php if ($message && isset($messages[$message])) : ?>
        <div class="notice notice-<?php echo esc_attr($messages[$message][0]); ?> is-dismissible">
            <p><?php echo esc_html($messages[$message][1]); ?></p>
            <?php if ($message === 'test_error' && $test_error) : ?>
                <p><code><?php echo esc_html($test_error); ?></code></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$is_https) : ?>
        <div class="notice notice-warning"><p>
            <?php esc_html_e('La web no usa HTTPS. Google Chat solo muestra imágenes servidas por HTTPS, así que las imágenes de los comunicados podrían no verse.', 'area-profesores'); ?>
        </p></div>
    <?php endif; ?>

    <div class="ap-enlaces-layout">
        <!-- Formulario alta / edición -->
        <div>
            <div class="ap-settings-card ap-enlaces-form-card">
                <h2 class="ap-settings-section-title">
                    <span class="dashicons <?php echo $editing ? 'dashicons-edit' : 'dashicons-plus-alt'; ?>"></span>
                    <?php echo $editing ? esc_html__('Editar espacio', 'area-profesores') : esc_html__('Añadir espacio', 'area-profesores'); ?>
                </h2>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                    <input type="hidden" name="action" value="ap_save_espacio">
                    <input type="hidden" name="espacio_id" value="<?php echo esc_attr($form['id']); ?>">
                    <?php wp_nonce_field('ap_save_espacio'); ?>

                    <p>
                        <label for="ap-espacio-nombre"><strong><?php esc_html_e('Nombre *', 'area-profesores'); ?></strong></label><br>
                        <input type="text" id="ap-espacio-nombre" name="nombre" class="widefat" required value="<?php echo esc_attr($form['nombre']); ?>" placeholder="<?php esc_attr_e('Ej: Familias 3º B', 'area-profesores'); ?>">
                    </p>

                    <div class="ap-enlaces-row">
                        <p>
                            <label for="ap-espacio-grupo"><strong><?php esc_html_e('Grupo / Etapa', 'area-profesores'); ?></strong></label><br>
                            <input type="text" id="ap-espacio-grupo" name="grupo" class="widefat" list="ap-espacio-grupos" value="<?php echo esc_attr($form['grupo']); ?>" placeholder="<?php esc_attr_e('Ej: Primaria', 'area-profesores'); ?>">
                            <datalist id="ap-espacio-grupos">
                                <?php foreach (array_unique(array_merge($grupos, array('Infantil', 'Primaria', 'ESO'))) as $g) : ?>
                                    <option value="<?php echo esc_attr($g); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </p>
                        <p class="ap-enlaces-icon-field">
                            <label for="ap-espacio-orden"><strong><?php esc_html_e('Orden', 'area-profesores'); ?></strong></label><br>
                            <input type="number" id="ap-espacio-orden" name="orden" class="widefat" value="<?php echo esc_attr($form['orden']); ?>" placeholder="10">
                        </p>
                    </div>

                    <p>
                        <label for="ap-espacio-webhook"><strong><?php echo $editing ? esc_html__('URL del webhook', 'area-profesores') : esc_html__('URL del webhook *', 'area-profesores'); ?></strong></label><br>
                        <input type="url" id="ap-espacio-webhook" name="webhook" class="widefat" <?php echo $editing ? '' : 'required'; ?> value="" placeholder="https://chat.googleapis.com/v1/spaces/…/messages?key=…&token=…">
                        <span class="description">
                            <?php if ($editing) : ?>
                                <?php esc_html_e('Déjalo vacío para mantener el webhook actual. Es una clave secreta: no se vuelve a mostrar completa.', 'area-profesores'); ?>
                            <?php else : ?>
                                <?php esc_html_e('Es una clave secreta: quien la tenga puede publicar en el espacio.', 'area-profesores'); ?>
                            <?php endif; ?>
                        </span>
                    </p>

                    <p>
                        <label><input type="checkbox" name="activo" value="1" <?php checked($form['activo']); ?>> <?php esc_html_e('Activo (aparece al publicar comunicados)', 'area-profesores'); ?></label>
                    </p>

                    <p>
                        <button type="submit" class="button button-primary">
                            <?php echo $editing ? esc_html__('Guardar cambios', 'area-profesores') : esc_html__('Añadir espacio', 'area-profesores'); ?>
                        </button>
                        <?php if ($editing) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-chat')); ?>" class="button"><?php esc_html_e('Cancelar', 'area-profesores'); ?></a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <!-- Instrucciones -->
            <div class="ap-settings-card ap-chat-help">
                <h2 class="ap-settings-section-title">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php esc_html_e('Cómo conectar un espacio', 'area-profesores'); ?>
                </h2>
                <ol>
                    <li>
                        <strong><?php esc_html_e('Una sola vez, en la Consola de administración de Google:', 'area-profesores'); ?></strong>
                        <?php esc_html_e('Aplicaciones › Google Workspace › Google Chat › Ajustes de las aplicaciones de Chat › activa "Permitir que los usuarios añadan y usen webhooks entrantes". Puede tardar un rato en aplicarse.', 'area-profesores'); ?>
                    </li>
                    <li><?php esc_html_e('Abre el espacio en Google Chat (en el ordenador), pulsa sobre su nombre › "Aplicaciones e integraciones" › "Webhooks" › "Añadir webhooks".', 'area-profesores'); ?></li>
                    <li><?php esc_html_e('Nombre: "Colegio Casa de la Virgen". URL del avatar: la del logotipo del colegio (opcional). Así es como aparecerá firmado el comunicado.', 'area-profesores'); ?></li>
                    <li><?php esc_html_e('Copia la URL del webhook que te da Google, pégala aquí y pulsa "Probar".', 'area-profesores'); ?></li>
                </ol>
                <p class="description">
                    <?php esc_html_e('Para añadir el webhook hay que ser miembro (o gestor) del espacio. Si el espacio es de solo anuncios o tiene familias con cuentas externas, haz primero una prueba con un espacio.', 'area-profesores'); ?>
                </p>
            </div>

            <!-- Quién puede publicar -->
            <div class="ap-settings-card ap-chat-help">
                <h2 class="ap-settings-section-title">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e('Quién puede publicar', 'area-profesores'); ?>
                </h2>
                <?php if (empty($direccion_users)) : ?>
                    <p><?php esc_html_e('Todavía no hay profesores marcados como Dirección (los administradores siempre pueden publicar).', 'area-profesores'); ?></p>
                <?php else : ?>
                    <ul class="ap-chat-direccion-list">
                        <?php foreach ($direccion_users as $du) : ?>
                            <li><?php echo get_avatar($du->ID, 20); ?> <?php echo esc_html($du->display_name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores&filter_profesor=yes')); ?>"><?php esc_html_e('Marcar Dirección en Profesores', 'area-profesores'); ?></a>
                    <?php if ($area_page_id && get_post_status($area_page_id)) : ?>
                        <a class="button" href="<?php echo esc_url(get_permalink($area_page_id) . '#comunicados'); ?>" target="_blank" rel="noopener"><?php esc_html_e('Ir a publicar', 'area-profesores'); ?></a>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Listado -->
        <div class="ap-card-table-wrap ap-enlaces-list-card">
            <table class="wp-list-table widefat striped ap-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Espacio', 'area-profesores'); ?></th>
                        <th style="width: 16%;"><?php esc_html_e('Grupo', 'area-profesores'); ?></th>
                        <th style="width: 60px;"><?php esc_html_e('Orden', 'area-profesores'); ?></th>
                        <th style="width: 90px;"><?php esc_html_e('Estado', 'area-profesores'); ?></th>
                        <th style="width: 200px;"><?php esc_html_e('Acciones', 'area-profesores'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($espacios)) : ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 32px;">
                                <?php esc_html_e('Todavía no hay espacios. Añade el primero con el formulario.', 'area-profesores'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($espacios as $e) : ?>
                            <tr<?php echo $e['activo'] ? '' : ' style="opacity: .55;"'; ?>>
                                <td>
                                    <strong><?php echo esc_html($e['nombre']); ?></strong><br>
                                    <span style="font-size: 12px; color: #64748b; word-break: break-all;"><?php echo esc_html(Area_Profesores_Chat::mask_webhook($e['webhook'])); ?></span>
                                </td>
                                <td><?php echo esc_html($e['grupo'] !== '' ? $e['grupo'] : '—'); ?></td>
                                <td><?php echo esc_html($e['orden']); ?></td>
                                <td>
                                    <?php if ($e['activo']) : ?>
                                        <span class="ap-status-pill ap-status-success"><?php esc_html_e('Activo', 'area-profesores'); ?></span>
                                    <?php else : ?>
                                        <span class="ap-status-pill ap-status-danger"><?php esc_html_e('Inactivo', 'area-profesores'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_test_espacio&id=' . $e['id']), 'ap_test_espacio_' . $e['id'])); ?>" onclick="return confirm('<?php echo esc_js(__('Se publicará un mensaje de prueba en el espacio y las familias lo verán. ¿Continuar?', 'area-profesores')); ?>');"><?php esc_html_e('Probar', 'area-profesores'); ?></a>
                                    <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-chat&edit=' . $e['id'])); ?>"><?php esc_html_e('Editar', 'area-profesores'); ?></a>
                                    <a class="button button-small button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_delete_espacio&id=' . $e['id']), 'ap_delete_espacio_' . $e['id'])); ?>" onclick="return confirm('<?php echo esc_js(__('¿Eliminar este espacio? (No se borra nada en Google Chat)', 'area-profesores')); ?>');">
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
