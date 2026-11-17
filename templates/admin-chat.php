<?php
/**
 * Admin View: Espacios de Chat
 * Screen under "Área Profesores > Espacios de Chat"
 */

if (!defined('ABSPATH')) {
    exit;
}

$tipo_form = $editing ? $editing['tipo'] : $nuevo_tipo;
$form = $editing ? $editing : array(
    'id'        => '',
    'tipo'      => $tipo_form,
    'nombre'    => '',
    'grupo'     => '',
    'promocion' => '',
    'letra'     => 'A',
    'space_id'  => '',
    'webhook'   => '',
    'activo'    => true,
    'orden'     => '',
    'curso'     => '',
    'publico'   => true,
);
$fin_curso = Area_Profesores_Cursos::get_fin_curso_escolar();
?>

<div class="wrap ap-admin-wrap">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Espacios de Google Chat', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead">
                <?php printf(
                    esc_html__('Curso escolar %1$s · %2$d de %3$d espacios conectados. El curso de cada espacio se cambia en Aulas › "Pasar de curso".', 'area-profesores'),
                    esc_html(Area_Profesores_Cursos::get_curso_escolar_label()),
                    (int) $stats['conectados'],
                    (int) $stats['total']
                ); ?>
            </p>
        </div>
    </div>

    <?php if ($flash) : ?>
        <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible"><p><?php echo esc_html($flash['text']); ?></p></div>
    <?php endif; ?>

    <?php if (!$is_https) : ?>
        <div class="notice notice-warning"><p>
            <?php esc_html_e('La web no usa HTTPS. Google Chat solo muestra imágenes servidas por HTTPS, así que las imágenes de los comunicados podrían no verse.', 'area-profesores'); ?>
        </p></div>
    <?php endif; ?>

    <?php if ($tz !== 'Europe/Madrid') : ?>
        <div class="notice notice-warning"><p>
            <?php printf(
                esc_html__('La zona horaria de WordPress es "%s". Para que los comunicados programados salgan a la hora correcta, elige "Madrid" en Ajustes › Generales › Zona horaria.', 'area-profesores'),
                esc_html($tz)
            ); ?>
        </p></div>
    <?php endif; ?>

    <?php if ($cron_disabled) : ?>
        <div class="notice notice-warning"><p>
            <?php esc_html_e('WP-Cron está desactivado (DISABLE_WP_CRON). Asegúrate de que el servidor ejecuta wp-cron.php cada pocos minutos; si no, los comunicados programados solo saldrán cuando alguien visite la web.', 'area-profesores'); ?>
        </p></div>
    <?php endif; ?>

    <div class="ap-enlaces-layout">
        <div>
            <!-- Conectar varios webhooks a la vez -->
            <div class="ap-settings-card ap-enlaces-form-card">
                <h2 class="ap-settings-section-title">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e('Conectar webhooks', 'area-profesores'); ?>
                </h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                    <input type="hidden" name="action" value="ap_bulk_webhooks">
                    <?php wp_nonce_field('ap_bulk_webhooks'); ?>
                    <p class="description" style="margin-top:0;">
                        <?php esc_html_e('Pega aquí una o varias URLs de webhook (en cualquier orden). Cada una se asigna sola a su curso por el código del espacio.', 'area-profesores'); ?>
                    </p>
                    <textarea name="webhooks" class="widefat" rows="4" placeholder="https://chat.googleapis.com/v1/spaces/AAAA…/messages?key=…&token=…"></textarea>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Conectar', 'area-profesores'); ?></button></p>
                </form>
            </div>

            <!-- Formulario alta / edición -->
            <div class="ap-settings-card ap-chat-help" id="ap-espacio-form">
                <h2 class="ap-settings-section-title">
                    <span class="dashicons <?php echo $editing ? 'dashicons-edit' : 'dashicons-plus-alt'; ?>"></span>
                    <?php echo $editing ? esc_html(sprintf(__('Editar: %s', 'area-profesores'), $editing['titulo'])) : esc_html__('Añadir espacio', 'area-profesores'); ?>
                </h2>

                <?php if (!$editing) : ?>
                    <p class="ap-chat-tipo-switch">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-chat&tipo=promocion#ap-espacio-form')); ?>" class="button <?php echo $tipo_form === 'promocion' ? 'button-primary' : ''; ?>"><?php esc_html_e('Familias de una promoción', 'area-profesores'); ?></a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-chat&tipo=otro#ap-espacio-form')); ?>" class="button <?php echo $tipo_form === 'otro' ? 'button-primary' : ''; ?>"><?php esc_html_e('Otro espacio', 'area-profesores'); ?></a>
                    </p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                    <input type="hidden" name="action" value="ap_save_espacio">
                    <input type="hidden" name="espacio_id" value="<?php echo esc_attr($form['id']); ?>">
                    <input type="hidden" name="tipo" value="<?php echo esc_attr($tipo_form); ?>">
                    <?php wp_nonce_field('ap_save_espacio'); ?>

                    <?php if ($tipo_form === 'promocion') : ?>
                        <div class="ap-enlaces-row">
                            <p>
                                <label for="ap-espacio-promocion"><strong><?php esc_html_e('Promoción *', 'area-profesores'); ?></strong></label><br>
                                <input type="number" id="ap-espacio-promocion" name="promocion" class="widefat" min="2000" max="2100" required value="<?php echo esc_attr($form['promocion']); ?>" placeholder="<?php echo esc_attr($fin_curso + 16); ?>">
                            </p>
                            <p class="ap-enlaces-icon-field" style="flex-basis:110px;">
                                <label for="ap-espacio-letra"><strong><?php esc_html_e('Grupo', 'area-profesores'); ?></strong></label><br>
                                <select id="ap-espacio-letra" name="letra" class="widefat">
                                    <option value="A" <?php selected($form['letra'], 'A'); ?>>A</option>
                                    <option value="B" <?php selected($form['letra'], 'B'); ?>>B</option>
                                    <option value="" <?php selected($form['letra'], ''); ?>><?php esc_html_e('Único', 'area-profesores'); ?></option>
                                </select>
                            </p>
                        </div>
                        <p class="description" style="margin-top:-6px;">
                            <?php printf(
                                esc_html__('Año en que terminan 2º de Bachillerato. Este curso, 1 año = %1$d, 1º EP = %2$d, 2º Bachillerato = %3$d.', 'area-profesores'),
                                (int) ($fin_curso + 16), (int) ($fin_curso + 11), (int) $fin_curso
                            ); ?>
                        </p>
                        <p>
                            <label for="ap-espacio-curso"><strong><?php esc_html_e('Curso (aula) asignado', 'area-profesores'); ?></strong></label><br>
                            <select id="ap-espacio-curso" name="curso" class="widefat">
                                <option value=""><?php esc_html_e('— Sin curso —', 'area-profesores'); ?></option>
                                <?php foreach (Area_Profesores_Cursos::get_cursos_por_etapa() as $ap_et => $ap_cs) : ?>
                                    <optgroup label="<?php echo esc_attr(Area_Profesores_Cursos::get_etapa_label($ap_et)); ?>">
                                        <?php foreach ($ap_cs as $ap_k => $ap_c) : ?>
                                            <option value="<?php echo esc_attr($ap_k); ?>" <?php selected($form['curso'], $ap_k); ?>><?php echo esc_html($ap_c['label']); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    <?php else : ?>
                        <p>
                            <label for="ap-espacio-nombre"><strong><?php esc_html_e('Nombre *', 'area-profesores'); ?></strong></label><br>
                            <input type="text" id="ap-espacio-nombre" name="nombre" class="widefat" required value="<?php echo esc_attr($form['nombre']); ?>" placeholder="<?php esc_attr_e('Ej: Familias del Coro, AMPA…', 'area-profesores'); ?>">
                        </p>
                        <div class="ap-enlaces-row">
                            <p>
                                <label for="ap-espacio-grupo"><strong><?php esc_html_e('Categoría', 'area-profesores'); ?></strong></label><br>
                                <input type="text" id="ap-espacio-grupo" name="grupo" class="widefat" list="ap-espacio-grupos" value="<?php echo esc_attr($form['grupo']); ?>" placeholder="<?php esc_attr_e('Otros espacios', 'area-profesores'); ?>">
                                <datalist id="ap-espacio-grupos">
                                    <?php foreach ($grupos_otros as $g) : ?>
                                        <option value="<?php echo esc_attr($g); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </p>
                            <p class="ap-enlaces-icon-field">
                                <label for="ap-espacio-orden"><strong><?php esc_html_e('Orden', 'area-profesores'); ?></strong></label><br>
                                <input type="number" id="ap-espacio-orden" name="orden" class="widefat" value="<?php echo esc_attr($form['orden']); ?>" placeholder="10">
                            </p>
                        </div>
                    <?php endif; ?>

                    <p>
                        <label for="ap-espacio-space"><strong><?php esc_html_e('Enlace o código del espacio', 'area-profesores'); ?></strong></label><br>
                        <input type="text" id="ap-espacio-space" name="space_id" class="widefat" value="<?php echo esc_attr($form['space_id']); ?>" placeholder="https://chat.google.com/room/AAAA…">
                        <span class="description"><?php esc_html_e('Opcional. Sirve para abrir el espacio desde aquí y para comprobar que el webhook es del espacio correcto.', 'area-profesores'); ?></span>
                    </p>

                    <p>
                        <label for="ap-espacio-webhook"><strong><?php esc_html_e('URL del webhook', 'area-profesores'); ?></strong></label><br>
                        <input type="url" id="ap-espacio-webhook" name="webhook" class="widefat" value="" placeholder="https://chat.googleapis.com/v1/spaces/…/messages?key=…&token=…">
                        <span class="description">
                            <?php if ($editing && $editing['conectado']) : ?>
                                <?php esc_html_e('Ya tiene webhook. Déjalo vacío para mantenerlo (es una clave secreta y no se vuelve a mostrar).', 'area-profesores'); ?>
                                <br><label><input type="checkbox" name="quitar_webhook" value="1"> <?php esc_html_e('Quitar el webhook (desconectar)', 'area-profesores'); ?></label>
                            <?php else : ?>
                                <?php esc_html_e('Es una clave secreta: quien la tenga puede publicar en el espacio.', 'area-profesores'); ?>
                            <?php endif; ?>
                        </span>
                    </p>

                    <p>
                        <label><input type="checkbox" name="activo" value="1" <?php checked($form['activo']); ?>> <?php esc_html_e('Activo (aparece al publicar comunicados)', 'area-profesores'); ?></label><br>
                        <label><input type="checkbox" name="publico" value="1" <?php checked($form['publico']); ?>> <?php esc_html_e('Mostrar en la página pública de espacios para las familias', 'area-profesores'); ?></label>
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
                    <li><?php esc_html_e('Pulsa "Abrir en Chat" en la fila del curso (en el ordenador).', 'area-profesores'); ?></li>
                    <li><?php esc_html_e('Pulsa sobre el nombre del espacio › "Aplicaciones e integraciones" › "Webhooks" › "Añadir webhooks". Nombre: "Colegio Casa de la Virgen"; avatar: la URL del logotipo.', 'area-profesores'); ?></li>
                    <li><?php esc_html_e('Copia la URL del webhook y pégala en "Conectar webhooks" (puedes pegar varias a la vez).', 'area-profesores'); ?></li>
                    <li><?php esc_html_e('Pulsa "Probar" en ese curso.', 'area-profesores'); ?></li>
                </ol>
                <p class="description">
                    <?php esc_html_e('Cada septiembre: usa "Pasar de curso" en Aulas y añade aquí el espacio de la nueva promoción de 1 año.', 'area-profesores'); ?>
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
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores')); ?>"><?php esc_html_e('Marcar Dirección en Profesores', 'area-profesores'); ?></a>
                    <?php if ($area_page_id && get_post_status($area_page_id)) : ?>
                        <a class="button" href="<?php echo esc_url(get_permalink($area_page_id) . '#comunicados'); ?>" target="_blank" rel="noopener"><?php esc_html_e('Ir a publicar', 'area-profesores'); ?></a>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Programados -->
            <div class="ap-settings-card ap-chat-help">
                <h2 class="ap-settings-section-title">
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e('Comunicados programados', 'area-profesores'); ?>
                </h2>
                <?php if (empty($programados)) : ?>
                    <p><?php esc_html_e('No hay comunicados programados.', 'area-profesores'); ?></p>
                <?php else : ?>
                    <ul class="ap-chat-programados">
                        <?php foreach ($programados as $pr) : ?>
                            <li>
                                <strong><?php echo esc_html($pr['titulo_lista']); ?></strong><br>
                                <span><?php echo esc_html($pr['programado_texto']); ?> · <?php echo esc_html(sprintf(_n('%d espacio', '%d espacios', count($pr['espacios']), 'area-profesores'), count($pr['espacios']))); ?></span>
                                <?php if ($pr['retrasado']) : ?><br><span style="color:#b45309;"><?php esc_html_e('Pendiente: saldrá en la próxima visita a la web.', 'area-profesores'); ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="description">
                    <?php esc_html_e('WordPress envía los programados cuando hay visitas a la web. Para que salgan a la hora exacta, pide a tu proveedor de hosting un cron del servidor que llame a wp-cron.php cada 5 minutos.', 'area-profesores'); ?>
                </p>
            </div>
        </div>

        <!-- Listado -->
        <div class="ap-card-table-wrap ap-enlaces-list-card">
            <?php if (!empty($cursos_sin_espacio)) : ?>
                <div class="ap-chat-sin-espacio">
                    <strong><?php esc_html_e('Cursos sin espacio de familias:', 'area-profesores'); ?></strong>
                    <?php echo esc_html(implode(', ', $cursos_sin_espacio)); ?>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat ap-table ap-chat-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Espacio', 'area-profesores'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Estado', 'area-profesores'); ?></th>
                        <th style="width: 250px;"><?php esc_html_e('Acciones', 'area-profesores'); ?></th>
                    </tr>
                </thead>
                <?php if (empty($grupos_tabla)) : ?>
                    <tbody><tr><td colspan="3" style="text-align:center; padding:32px;"><?php esc_html_e('Todavía no hay espacios.', 'area-profesores'); ?></td></tr></tbody>
                <?php endif; ?>
                <?php foreach ($grupos_tabla as $grupo) : ?>
                    <tbody>
                        <tr class="ap-chat-group-row"><th colspan="3"><?php echo esc_html($grupo['label']); ?></th></tr>
                        <?php foreach ($grupo['espacios'] as $e) : ?>
                            <tr<?php echo ($e['activo'] && $e['grupo_key'] !== 'sin_curso') ? '' : ' style="opacity: .55;"'; ?>>
                                <td>
                                    <strong><?php echo esc_html($e['titulo']); ?></strong>
                                    <?php if ($e['subtitulo'] !== '') : ?>
                                        <span class="ap-chat-sub"><?php echo esc_html($e['subtitulo']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($e['nombre_chat'] !== '') : ?>
                                        <br><span class="ap-chat-sub" style="margin-left:0;">💬 <?php echo esc_html($e['nombre_chat']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($e['conectado']) : ?>
                                        <br><span class="ap-chat-mask"><?php echo esc_html(Area_Profesores_Chat::mask_webhook($e['webhook'])); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$e['activo']) : ?>
                                        <span class="ap-status-pill ap-status-danger"><?php esc_html_e('Inactivo', 'area-profesores'); ?></span>
                                    <?php elseif ($e['conectado']) : ?>
                                        <span class="ap-status-pill ap-status-success"><?php esc_html_e('Conectado', 'area-profesores'); ?></span>
                                    <?php else : ?>
                                        <span class="ap-status-pill ap-status-warning"><?php esc_html_e('Sin webhook', 'area-profesores'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($e['chat_url']) : ?>
                                        <a class="button button-small" href="<?php echo esc_url($e['chat_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Abrir en Chat', 'area-profesores'); ?></a>
                                    <?php endif; ?>
                                    <?php if ($e['conectado']) : ?>
                                        <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_test_espacio&id=' . $e['id']), 'ap_test_espacio_' . $e['id'])); ?>" onclick="return confirm('<?php echo esc_js(__('Se publicará un mensaje de prueba en el espacio y las familias lo verán. ¿Continuar?', 'area-profesores')); ?>');"><?php esc_html_e('Probar', 'area-profesores'); ?></a>
                                    <?php endif; ?>
                                    <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-chat&edit=' . $e['id'] . '#ap-espacio-form')); ?>"><?php esc_html_e('Editar', 'area-profesores'); ?></a>
                                    <a class="button button-small button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_delete_espacio&id=' . $e['id']), 'ap_delete_espacio_' . $e['id'])); ?>" onclick="return confirm('<?php echo esc_js(__('¿Eliminar este espacio de la lista? (En Google Chat no se borra nada)', 'area-profesores')); ?>');">
                                        <?php esc_html_e('Eliminar', 'area-profesores'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
