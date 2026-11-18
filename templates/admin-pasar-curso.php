<?php
/**
 * Admin View: "Pasar de curso" / "Renombrar espacios" preview.
 *
 * Variables: $modo ('pasar'|'renombrar'), $filas, $cursos_vacios, $google, $google_connected
 */

if (!defined('ABSPATH')) {
    exit;
}

$ap_es_pasar = ($modo === 'pasar');
$ap_cursos_js = array();
foreach (Area_Profesores_Cursos::get_cursos() as $k => $c) {
    $ap_cursos_js[$k] = array('label' => $c['label'], 'etapa' => Area_Profesores_Cursos::get_etapa_label($c['etapa']));
}
?>

<div class="wrap ap-admin-wrap">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Aulas', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline">
                <?php echo $ap_es_pasar ? esc_html__('Pasar de curso', 'area-profesores') : esc_html__('Renombrar espacios en Google Chat', 'area-profesores'); ?>
            </h1>
            <p class="ap-admin-lead">
                <?php if ($ap_es_pasar) : ?>
                    <?php esc_html_e('Revisa el curso nuevo que se asignará a cada espacio. Puedes cambiar cualquiera antes de confirmar. No se aplica nada hasta que pulses "Confirmar".', 'area-profesores'); ?>
                <?php else : ?>
                    <?php esc_html_e('Revisa el nombre que tendrá cada espacio en Google Chat. Puedes editarlo antes de confirmar.', 'area-profesores'); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (empty($filas)) : ?>
        <div class="notice notice-warning"><p><?php esc_html_e('No hay espacios con curso asignado.', 'area-profesores'); ?></p></div>
        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-aulas')); ?>"><?php esc_html_e('Volver a Aulas', 'area-profesores'); ?></a></p>
    <?php else : ?>

    <?php if (!$google_connected) : ?>
        <div class="notice notice-info"><p>
            <?php esc_html_e('No hay conexión con la API de Google Chat, así que los nombres de los espacios en Google no se cambiarán (solo el curso aquí). Puedes conectar en Aulas › Conexión con Google Chat.', 'area-profesores'); ?>
        </p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="ap-pasar-form">
        <input type="hidden" name="action" value="ap_pasar_curso">
        <input type="hidden" name="modo" value="<?php echo esc_attr($modo); ?>">
        <?php wp_nonce_field('ap_pasar_curso'); ?>

        <div class="ap-card-table-wrap">
            <table class="wp-list-table widefat striped ap-table ap-pasar-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Espacio', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Curso actual', 'area-profesores'); ?></th>
                        <?php if ($ap_es_pasar) : ?>
                            <th style="width: 30px;"></th>
                            <th><?php esc_html_e('Curso nuevo', 'area-profesores'); ?></th>
                        <?php endif; ?>
                        <th><?php esc_html_e('Nombre en Google Chat', 'area-profesores'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $f) : $e = $f['espacio']; ?>
                        <tr data-promocion="<?php echo esc_attr($e['promocion']); ?>" data-letra="<?php echo esc_attr($e['letra']); ?>">
                            <td>
                                <strong><?php echo esc_html($e['subtitulo']); ?></strong>
                                <?php if ($e['chat_url']) : ?><br><a href="<?php echo esc_url($e['chat_url']); ?>" target="_blank" rel="noopener" style="font-size:12px;"><?php esc_html_e('Abrir en Chat', 'area-profesores'); ?></a><?php endif; ?>
                            </td>
                            <td><?php echo esc_html($e['titulo']); ?></td>
                            <?php if ($ap_es_pasar) : ?>
                                <td style="font-size:18px; color:#64748b;">→</td>
                                <td>
                                    <select name="nuevo_curso[<?php echo esc_attr($e['id']); ?>]" class="ap-pasar-curso">
                                        <option value="" <?php selected($f['nuevo_curso'], ''); ?>><?php esc_html_e('— Sale del colegio (pasa a Promociones anteriores) —', 'area-profesores'); ?></option>
                                        <?php foreach (Area_Profesores_Cursos::get_cursos_por_etapa() as $ap_et => $ap_cs) : ?>
                                            <optgroup label="<?php echo esc_attr(Area_Profesores_Cursos::get_etapa_label($ap_et)); ?>">
                                                <?php foreach ($ap_cs as $ap_k => $ap_c) : ?>
                                                    <option value="<?php echo esc_attr($ap_k); ?>" <?php selected($f['nuevo_curso'], $ap_k); ?>><?php echo esc_html($ap_c['label']); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php if ($e['nombre_chat'] !== '') : ?>
                                    <span class="ap-chat-sub" style="margin-left:0;"><?php printf(esc_html__('Ahora: %s', 'area-profesores'), esc_html($e['nombre_chat'])); ?></span><br>
                                <?php endif; ?>
                                <input type="text" name="nuevo_nombre[<?php echo esc_attr($e['id']); ?>]" class="widefat ap-pasar-nombre" maxlength="128" value="<?php echo esc_attr($f['nuevo_nombre']); ?>" <?php disabled(!$google_connected); ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($ap_es_pasar) : ?>
            <div class="ap-settings-card ap-chat-help" id="ap-pasar-avisos">
                <p id="ap-pasar-duplicados" class="ap-aula-warn" style="display:none;"></p>
                <?php if (!empty($cursos_vacios)) : ?>
                    <p><strong><?php esc_html_e('Cursos que se quedarán sin espacio de familias:', 'area-profesores'); ?></strong>
                        <span id="ap-pasar-vacios"><?php echo esc_html(implode(', ', $cursos_vacios)); ?></span></p>
                    <p class="description"><?php esc_html_e('Normalmente, 1 año A y B (la promoción nueva): añade sus espacios en Espacios de Chat después del cambio.', 'area-profesores'); ?></p>
                <?php endif; ?>
                <p class="description"><?php esc_html_e('Los tutores no se mueven: asigna las tutorías del nuevo curso en Aulas. Si te equivocas, podrás deshacer el cambio desde Aulas.', 'area-profesores'); ?></p>
            </div>
        <?php endif; ?>

        <p>
            <label>
                <input type="checkbox" name="renombrar" value="1" <?php checked($google_connected); ?> <?php disabled(!$google_connected); ?>>
                <?php esc_html_e('Cambiar también el nombre de los espacios en Google Chat', 'area-profesores'); ?>
            </label>
        </p>

        <p>
            <button type="submit" class="button button-primary button-large" onclick="return confirm('<?php echo esc_js($ap_es_pasar ? __('¿Aplicar el cambio de curso a todos los espacios de la lista?', 'area-profesores') : __('¿Cambiar el nombre de estos espacios en Google Chat? Lo verán las familias.', 'area-profesores')); ?>');">
                <?php echo $ap_es_pasar ? esc_html__('Confirmar cambio de curso', 'area-profesores') : esc_html__('Confirmar nombres', 'area-profesores'); ?>
            </button>
            <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=area-profesores-aulas')); ?>"><?php esc_html_e('Cancelar', 'area-profesores'); ?></a>
        </p>
    </form>

    <script>
    (function () {
        var cursos = <?php echo wp_json_encode($ap_cursos_js); ?>;
        var plantilla = <?php echo wp_json_encode($google['plantilla']); ?>;
        var allCursos = Object.keys(cursos);

        function nombre(key, promo, letra) {
            if (!cursos[key]) { return ''; }
            return plantilla
                .split('{curso}').join(cursos[key].label)
                .split('{promocion}').join((promo + ' ' + letra).trim())
                .split('{año}').join(promo)
                .split('{grupo}').join(letra)
                .split('{etapa}').join(cursos[key].etapa)
                .replace(/\s+/g, ' ').trim();
        }

        function revisar() {
            var usados = {}, dup = [];
            document.querySelectorAll('.ap-pasar-curso').forEach(function (sel) {
                if (!sel.value) { return; }
                if (usados[sel.value]) { dup.push(cursos[sel.value].label); }
                usados[sel.value] = true;
            });
            var d = document.getElementById('ap-pasar-duplicados');
            if (d) {
                d.style.display = dup.length ? '' : 'none';
                d.textContent = dup.length ? <?php echo wp_json_encode(__('Atención: varios espacios tendrán el mismo curso:', 'area-profesores')); ?> + ' ' + dup.join(', ') : '';
            }
            var v = document.getElementById('ap-pasar-vacios');
            if (v) {
                v.textContent = allCursos.filter(function (k) { return !usados[k]; }).map(function (k) { return cursos[k].label; }).join(', ') || '—';
            }
        }

        document.querySelectorAll('.ap-pasar-curso').forEach(function (sel) {
            sel.addEventListener('change', function () {
                var tr = sel.closest('tr');
                var input = tr.querySelector('.ap-pasar-nombre');
                if (input) { input.value = nombre(sel.value, tr.dataset.promocion, tr.dataset.letra); }
                revisar();
            });
        });
        revisar();
    })();
    </script>

    <?php endif; ?>
</div>
