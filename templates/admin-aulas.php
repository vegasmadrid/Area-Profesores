<?php
/**
 * Admin View: Aulas
 * Screen under "Área Profesores > Aulas"
 */

if (!defined('ABSPATH')) {
    exit;
}

$ap_etapas = Area_Profesores_Cursos::get_etapas();
?>

<div class="wrap ap-admin-wrap">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Aulas', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead">
                <?php esc_html_e('Cada aula con su tutor/a y su espacio de familias en Google Chat. Cambia lo que necesites y pulsa "Guardar aulas".', 'area-profesores'); ?>
            </p>
        </div>
        <div class="ap-aulas-actions">
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-aulas&view=pasar')); ?>"><?php esc_html_e('Pasar de curso…', 'area-profesores'); ?></a>
        </div>
    </div>

    <?php if ($flash) : ?>
        <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible"><p><?php echo esc_html($flash['text']); ?></p></div>
    <?php endif; ?>

    <?php if (is_array($backup) && !empty($backup['fecha'])) : ?>
        <div class="notice notice-info">
            <p>
                <?php printf(
                    esc_html__('Último "Pasar de curso": %s.', 'area-profesores'),
                    esc_html(wp_date(get_option('date_format') . ' H:i', $backup['fecha']))
                ); ?>
                <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_deshacer_curso'), 'ap_deshacer_curso')); ?>" onclick="return confirm('<?php echo esc_js(__('¿Devolver cada espacio al curso que tenía antes del último cambio?', 'area-profesores')); ?>');"><?php esc_html_e('Deshacer', 'area-profesores'); ?></a>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="ap_save_aulas">
        <?php wp_nonce_field('ap_save_aulas'); ?>

        <div class="ap-card-table-wrap">
            <table class="wp-list-table widefat ap-table ap-chat-table ap-aulas-table">
                <thead>
                    <tr>
                        <th style="width: 140px;"><?php esc_html_e('Aula', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Tutor/a', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Espacio de familias', 'area-profesores'); ?></th>
                        <th style="width: 260px;"><?php esc_html_e('Estado', 'area-profesores'); ?></th>
                    </tr>
                </thead>
                <?php foreach ($aulas as $etapa => $filas) : ?>
                    <tbody>
                        <tr class="ap-chat-group-row"><th colspan="4"><?php echo esc_html($ap_etapas[$etapa]); ?></th></tr>
                        <?php foreach ($filas as $key => $a) :
                            $tutor_id = !empty($a['tutores']) ? $a['tutores'][0]->ID : 0;
                            $esp = !empty($a['espacios']) ? $a['espacios'][0] : null;
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($a['curso']['label']); ?></strong></td>
                                <td>
                                    <select name="tutor[<?php echo esc_attr($key); ?>]" class="widefat">
                                        <option value="0"><?php esc_html_e('— Sin tutor/a —', 'area-profesores'); ?></option>
                                        <?php foreach ($profesores as $p) : ?>
                                            <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($tutor_id, $p->ID); ?>><?php echo esc_html($p->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (count($a['tutores']) > 1) : ?>
                                        <span class="ap-aula-warn"><?php printf(esc_html__('También tutores: %s', 'area-profesores'), esc_html(implode(', ', wp_list_pluck(array_slice($a['tutores'], 1), 'display_name')))); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select name="espacio[<?php echo esc_attr($key); ?>]" class="widefat">
                                        <option value=""><?php esc_html_e('— Sin espacio —', 'area-profesores'); ?></option>
                                        <?php foreach ($espacios_promocion as $e) : ?>
                                            <option value="<?php echo esc_attr($e['id']); ?>" <?php selected($esp ? $esp['id'] : '', $e['id']); ?>>
                                                <?php echo esc_html($e['subtitulo'] !== '' && $e['curso'] !== '' ? $e['subtitulo'] . ' · ' . __('ahora', 'area-profesores') . ' ' . $e['titulo'] : $e['titulo'] . ($e['curso'] === '' ? ' · ' . __('sin curso', 'area-profesores') : '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (count($a['espacios']) > 1) : ?>
                                        <span class="ap-aula-warn"><?php esc_html_e('Hay más de un espacio con este curso; al guardar solo quedará el elegido.', 'area-profesores'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($esp) : ?>
                                        <?php if ($esp['conectado']) : ?>
                                            <span class="ap-status-pill ap-status-success"><?php esc_html_e('Webhook', 'area-profesores'); ?></span>
                                        <?php else : ?>
                                            <span class="ap-status-pill ap-status-warning"><?php esc_html_e('Sin webhook', 'area-profesores'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($esp['chat_url']) : ?>
                                            <a href="<?php echo esc_url($esp['chat_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Abrir', 'area-profesores'); ?></a>
                                        <?php endif; ?>
                                        <?php if ($esp['nombre_chat'] !== '') : ?>
                                            <br><span class="ap-chat-sub" style="margin-left:0;">💬 <?php echo esc_html($esp['nombre_chat']); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="ap-status-pill ap-status-danger"><?php esc_html_e('Sin espacio', 'area-profesores'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="ap-admin-footer">
            <button type="submit" class="button button-primary button-large"><?php esc_html_e('Guardar aulas', 'area-profesores'); ?></button>
            <span class="description" style="margin-left: 12px;">
                <?php esc_html_e('Si quitas a un tutor/a de su aula y no lo pones en otra, queda como especialista de esa etapa.', 'area-profesores'); ?>
            </span>
        </div>
    </form>

    <script>
    // A teacher / space chosen in one row is cleared from any other row.
    (function () {
        ['tutor', 'espacio'].forEach(function (name) {
            var selects = document.querySelectorAll('select[name^="' + name + '["]');
            selects.forEach(function (sel) {
                sel.addEventListener('change', function () {
                    if (!sel.value || sel.value === '0') { return; }
                    selects.forEach(function (other) {
                        if (other !== sel && other.value === sel.value) {
                            other.value = name === 'tutor' ? '0' : '';
                            other.closest('tr').classList.add('ap-aula-changed');
                        }
                    });
                    sel.closest('tr').classList.add('ap-aula-changed');
                });
            });
        });
    })();
    </script>

    <?php if (!empty($sin_curso)) : ?>
        <div class="ap-settings-card ap-chat-help">
            <h2 class="ap-settings-section-title"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('Espacios sin curso asignado', 'area-profesores'); ?></h2>
            <p><?php echo esc_html(implode(', ', wp_list_pluck($sin_curso, 'titulo'))); ?></p>
            <p class="description"><?php esc_html_e('Por ejemplo, la promoción que ha terminado 2º de Bachillerato. No aparecen al publicar comunicados ni en la página pública; puedes eliminarlos en Espacios de Chat.', 'area-profesores'); ?></p>
        </div>
    <?php endif; ?>

    <div class="ap-aulas-cards">
        <!-- Página pública -->
        <div class="ap-settings-card ap-chat-help">
            <h2 class="ap-settings-section-title"><span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e('Página pública de espacios', 'area-profesores'); ?></h2>
            <p><?php echo wp_kses(__('Crea una página con el shortcode <code>[espacios_familias]</code> y comparte su enlace con las familias. Es pública aunque la web esté restringida y muestra todos los espacios ordenados por etapa y curso, con un buscador.', 'area-profesores'), array('code' => array())); ?></p>
            <p class="description"><?php echo wp_kses(__('Opciones: <code>[espacios_familias tutor="si"]</code> muestra también el nombre del tutor/a; <code>etapa="primaria"</code> muestra solo una etapa; <code>titulo="…"</code> cambia el título.', 'area-profesores'), array('code' => array())); ?></p>
            <?php if (!empty($public_pages)) : ?>
                <ul>
                    <?php foreach ($public_pages as $pid) : ?>
                        <li><a href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($pid)); ?></a> — <code><?php echo esc_html(get_permalink($pid)); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><em><?php esc_html_e('Todavía no hay ninguna página con este shortcode.', 'area-profesores'); ?></em></p>
            <?php endif; ?>
        </div>

        <!-- Google Chat API -->
        <div class="ap-settings-card ap-chat-help" id="ap-google">
            <h2 class="ap-settings-section-title"><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e('Conexión con Google Chat (opcional)', 'area-profesores'); ?></h2>
            <p><?php esc_html_e('Los webhooks solo publican mensajes. Para leer y cambiar el nombre de los espacios desde aquí hace falta conectar con la API de Google Chat una sola vez.', 'area-profesores'); ?></p>

            <?php if ($google_connected) : ?>
                <p><span class="ap-status-pill ap-status-success"><?php esc_html_e('Conectado', 'area-profesores'); ?></span>
                    <?php echo esc_html($google['email']); ?>
                    <?php echo $google['admin_access'] ? esc_html__('(acceso de administrador)', 'area-profesores') : ''; ?>
                </p>
                <p>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_leer_nombres'), 'ap_leer_nombres')); ?>"><?php esc_html_e('Leer nombres desde Google Chat', 'area-profesores'); ?></a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-aulas&view=renombrar')); ?>"><?php esc_html_e('Renombrar espacios en Google Chat…', 'area-profesores'); ?></a>
                </p>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                <input type="hidden" name="action" value="ap_google_save">
                <?php wp_nonce_field('ap_google_save'); ?>
                <p>
                    <label><strong><?php esc_html_e('Plantilla del nombre del espacio', 'area-profesores'); ?></strong></label><br>
                    <input type="text" name="plantilla" class="widefat" value="<?php echo esc_attr($google['plantilla']); ?>">
                    <span class="description"><?php echo wp_kses(__('Ejemplo: <code>{curso} (PROMOCIÓN {promocion})</code> → "3º EP A (PROMOCIÓN 2036 A)". También: <code>{año}</code>, <code>{grupo}</code>, <code>{etapa}</code>.', 'area-profesores'), array('code' => array())); ?></span>
                </p>
                <details <?php echo Area_Profesores_Google::is_configured() ? '' : 'open'; ?>>
                    <summary><strong><?php esc_html_e('Credenciales de Google Cloud', 'area-profesores'); ?></strong></summary>
                    <ol class="ap-google-steps">
                        <li><?php echo wp_kses(__('En <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>, con la cuenta del colegio, crea un proyecto.', 'area-profesores'), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))); ?></li>
                        <li><?php esc_html_e('Activa la "Google Chat API" y, en su pestaña "Configuración", rellena el nombre de la app (es obligatorio aunque no se use como bot).', 'area-profesores'); ?></li>
                        <li><?php esc_html_e('En "Pantalla de consentimiento de OAuth" elige tipo "Interno".', 'area-profesores'); ?></li>
                        <li><?php esc_html_e('En "Credenciales" crea un "ID de cliente de OAuth" de tipo "Aplicación web" con este URI de redirección autorizado:', 'area-profesores'); ?><br><code><?php echo esc_html(Area_Profesores_Google::get_redirect_uri()); ?></code></li>
                        <li><?php esc_html_e('Copia aquí el ID de cliente y el secreto, guarda y pulsa "Conectar con Google" con una cuenta de administrador de Google Workspace.', 'area-profesores'); ?></li>
                    </ol>
                    <p>
                        <label><strong><?php esc_html_e('ID de cliente', 'area-profesores'); ?></strong></label><br>
                        <input type="text" name="client_id" class="widefat" value="<?php echo esc_attr($google['client_id']); ?>">
                    </p>
                    <p>
                        <label><strong><?php esc_html_e('Secreto de cliente', 'area-profesores'); ?></strong></label><br>
                        <input type="password" name="client_secret" class="widefat" value="" placeholder="<?php echo $google['client_secret'] !== '' ? esc_attr__('(guardado; déjalo vacío para mantenerlo)', 'area-profesores') : ''; ?>">
                    </p>
                    <p>
                        <label><input type="checkbox" name="admin_access" value="1" <?php checked($google['admin_access']); ?>> <?php esc_html_e('Usar acceso de administrador (permite cambiar cualquier espacio del colegio sin ser miembro)', 'area-profesores'); ?></label>
                    </p>
                </details>
                <p><button type="submit" class="button"><?php esc_html_e('Guardar', 'area-profesores'); ?></button></p>
            </form>

            <?php if (Area_Profesores_Google::is_configured()) : ?>
                <p>
                    <?php if ($google_connected) : ?>
                        <a class="button button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_google_disconnect'), 'ap_google_disconnect')); ?>"><?php esc_html_e('Desconectar', 'area-profesores'); ?></a>
                    <?php else : ?>
                        <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_google_connect'), 'ap_google_connect')); ?>"><?php esc_html_e('Conectar con Google', 'area-profesores'); ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
