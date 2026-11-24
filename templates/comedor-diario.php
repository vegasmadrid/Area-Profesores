<?php
/**
 * Teacher Portal: "Comedor" tab (Primer Ciclo, Infantil y Primaria).
 * Variables: $comedor (Area_Profesores_Comensales::datos_portal())
 */

if (!defined('ABSPATH')) {
    exit;
}

$cd = $comedor;
if (!empty($cd['solo_mi_comida'])) : // teachers of other stages: only "Mi comida" ?>
    <div class="ap-records-card ap-cd" id="ap-comedor-diario">
        <div class="ap-enlaces-header"><div>
            <h2 class="ap-records-title"><?php esc_html_e('Comedor', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Tu clase no usa el comedor diario, pero aquí puedes marcar los días que no comes en el colegio.', 'area-profesores'); ?></p>
        </div></div>
        <?php if ($cd['flash']) : ?><div class="ap-cd-msg is-<?php echo esc_attr($cd['flash']['type']); ?>" role="status"><?php echo esc_html($cd['flash']['text']); ?></div><?php endif; ?>
        <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/partials/mi-comida.php'; ?>
    </div>
    <?php return;
endif;
$cd_curso = $cd['curso'] !== '' ? Area_Profesores_Cursos::get_curso($cd['curso']) : null;
$cd_reg = $cd['registro'];
$cd_dias = array(1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V');
$cd_validadas = count(array_filter($cd['estados'], function ($e) {
    return (bool) $e['registro'];
}));
$cd_base = remove_query_arg(array('ap_cc', 'ap_res'));
?>
<div class="ap-records-card ap-cd" id="ap-comedor-diario">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Comedor de hoy', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php echo esc_html(ucfirst(date_i18n('l j \d\e F', strtotime($cd['fecha'] . ' 12:00:00')))); ?> ·
                <?php printf(esc_html__('%1$d de %2$d clases validadas', 'area-profesores'), (int) $cd_validadas, count($cd['estados'])); ?></p>
        </div>
    </div>

    <?php if ($cd['flash']) : ?>
        <div class="ap-cd-msg is-<?php echo esc_attr($cd['flash']['type']); ?>" role="status"><?php echo esc_html($cd['flash']['text']); ?></div>
    <?php endif; ?>

    <?php if (!empty($cd['profe'])) { include AREA_PROFESORES_PLUGIN_DIR . 'templates/partials/mi-comida.php'; } ?>

    <?php if (!$cd['lectivo']) : ?>
        <div class="ap-cd-aviso"><?php echo esc_html($cd['motivo'] !== '' ? sprintf(__('Hoy no hay clase (%s): no hace falta poner el comedor.', 'area-profesores'), $cd['motivo']) : __('Hoy no hay clase: no hace falta poner el comedor.', 'area-profesores')); ?></div>
    <?php else : ?>

        <div class="ap-cd-clases">
            <?php if ($cd['mi_curso'] !== '') : $e = isset($cd['estados'][$cd['mi_curso']]) ? $cd['estados'][$cd['mi_curso']] : null; ?>
                <a class="ap-cd-clase<?php echo $cd['curso'] === $cd['mi_curso'] ? ' is-active' : ''; ?><?php echo $e && $e['registro'] ? ' is-ok' : ''; ?>" href="<?php echo esc_url(add_query_arg('ap_cc', $cd['mi_curso'], $cd_base) . '#comedor'); ?>">
                    <small><?php esc_html_e('Mi clase', 'area-profesores'); ?></small>
                    <b><?php echo esc_html(Area_Profesores_Cursos::get_curso($cd['mi_curso'])['label']); ?></b>
                    <span><?php echo $e && $e['registro'] ? '✓ ' . esc_html__('Validado', 'area-profesores') : esc_html__('Pendiente', 'area-profesores'); ?></span>
                </a>
            <?php endif; ?>
            <form method="get" class="ap-cd-otra" action="<?php echo esc_url(strtok($cd_base, '?')); ?>">
                <?php foreach ($_GET as $k => $v) : if (in_array($k, array('ap_cc', 'ap_res'), true) || is_array($v)) { continue; } ?>
                    <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr(wp_unslash($v)); ?>">
                <?php endforeach; ?>
                <label for="ap-cd-otra"><?php esc_html_e('Poner el comedor de un compañero:', 'area-profesores'); ?></label>
                <select id="ap-cd-otra" name="ap_cc" class="ap-cd-select">
                    <option value=""><?php esc_html_e('— Elige la clase —', 'area-profesores'); ?></option>
                    <?php $cd_g = ''; foreach ($cd['estados'] as $k => $e) : ?>
                        <?php if ($e['etapa'] !== $cd_g) : ?><?php if ($cd_g !== '') : ?></optgroup><?php endif; $cd_g = $e['etapa']; ?><optgroup label="<?php echo esc_attr($cd_g); ?>"><?php endif; ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected($cd['curso'], $k); ?>><?php echo esc_html(($e['registro'] ? '✓ ' : '⏳ ') . $e['label'] . ($e['tutores'] ? ' · ' . implode(', ', wp_list_pluck($e['tutores'], 'display_name')) : '')); ?></option>
                    <?php endforeach; ?>
                    <?php if ($cd_g !== '') : ?></optgroup><?php endif; ?>
                </select>
                <noscript><button type="submit" class="ap-btn ap-btn-sm ap-btn-outline"><?php esc_html_e('Ir', 'area-profesores'); ?></button></noscript>
            </form>
        </div>

        <?php if (!$cd_curso) : ?>
            <p class="ap-ma-vacio"><?php esc_html_e('Elige una clase para poner su comedor.', 'area-profesores'); ?></p>
        <?php else : $cd_tutores = isset($cd['estados'][$cd['curso']]) ? $cd['estados'][$cd['curso']]['tutores'] : array(); ?>

            <div class="ap-cd-estado <?php echo $cd_reg ? 'is-ok' : 'is-pendiente'; ?>">
                <?php if ($cd_reg) : ?>
                    <?php printf(esc_html__('✓ Validado por %1$s a las %2$s: %3$d comensales. Si hay algún cambio, márcalo y vuelve a validar.', 'area-profesores'), esc_html($cd_reg['validado_nombre']), esc_html(date_i18n('H:i', strtotime($cd_reg['validado']))), (int) $cd_reg['total']); ?>
                <?php else : ?>
                    <?php printf(esc_html__('Pendiente de validar. Comprueba quién se queda hoy y pulsa "Validar". A las %1$s se recuerda a quien no lo haya hecho y a las %2$s se envía el resumen a cocina.', 'area-profesores'), esc_html($cd['ajustes']['hora_recordatorio']), esc_html($cd['ajustes']['hora_cocina'])); ?>
                <?php endif; ?>
                <?php if ($cd['curso'] !== $cd['mi_curso'] && $cd_tutores) : ?>
                    <br><small><?php printf(esc_html__('Estás poniendo el comedor de %1$s (tutor/a: %2$s).', 'area-profesores'), esc_html($cd_curso['label']), esc_html(implode(', ', wp_list_pluck($cd_tutores, 'display_name')))); ?></small>
                <?php endif; ?>
            </div>

            <?php if (!$cd['lista']) : ?>
                <p class="ap-ma-vacio"><?php esc_html_e('Todavía no hay alumnos en esta clase (se cargan en "Alumnos y familias").', 'area-profesores'); ?></p>
            <?php else : $cd_sin_fijos = !array_filter(wp_list_pluck($cd['lista'], 'dias')); ?>
                <?php if ($cd_sin_fijos) : ?>
                    <div class="ap-cd-sinfijos" role="alert">
                        <strong><?php esc_html_e('Atención: esta clase no tiene comensales fijos marcados.', 'area-profesores'); ?></strong>
                        <?php esc_html_e('Es importante marcarlos: todos los alumnos que apuntes al comedor sin ser fijos cuentan como EXTRA, y cada extra se notifica a Administración para cobrar el servicio a la familia (que también recibe un correo).', 'area-profesores'); ?>
                        <a href="#ap-cd-fijos" class="ap-btn ap-btn-sm ap-btn-primary" onclick="document.getElementById('ap-cd-fijos').open = true;"><?php esc_html_e('Marcar los comensales fijos', 'area-profesores'); ?></a>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-cd-form" id="ap-cd-form" data-sin-fijos="<?php echo $cd_sin_fijos ? '1' : '0'; ?>">
                    <input type="hidden" name="action" value="ap_comedor_validar">
                    <input type="hidden" name="curso" value="<?php echo esc_attr($cd['curso']); ?>">
                    <?php wp_nonce_field('ap_comedor_validar_' . $cd['curso']); ?>

                    <div class="ap-cd-tools">
                        <h3 class="ap-ma-titulo"><?php echo esc_html($cd_curso['label']); ?> · <?php printf(esc_html(_n('%d alumno', '%d alumnos', count($cd['lista']), 'area-profesores')), count($cd['lista'])); ?></h3>
                        <span>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-cd="fijos"><?php esc_html_e('Solo los fijos', 'area-profesores'); ?></button>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-cd="ninguno"><?php esc_html_e('Ninguno', 'area-profesores'); ?></button>
                        </span>
                    </div>

                    <ul class="ap-cd-lista">
                        <?php foreach ($cd['lista'] as $a) : ?>
                            <li class="ap-cd-alumno<?php echo $a['alergia'] !== '' ? ' has-alergia' : ''; ?>" data-fijo="<?php echo $a['fijo'] ? '1' : '0'; ?>" data-familia="<?php echo $a['familia'] ? '1' : '0'; ?>">
                                <label>
                                    <input type="checkbox" name="come[]" value="<?php echo esc_attr($a['id']); ?>" <?php checked($a['come']); ?>>
                                    <span class="ap-cd-check" aria-hidden="true"></span>
                                    <span class="ap-cd-nombre"><?php echo esc_html($a['nombre']); ?></span>
                                </label>
                                <span class="ap-cd-tags">
                                    <?php if ($a['dias']) : ?><span class="ap-cd-tag is-dias" title="<?php esc_attr_e('Días que se queda siempre', 'area-profesores'); ?>"><?php echo esc_html(implode(' ', array_map(function ($d) use ($cd_dias) { return $cd_dias[$d]; }, $a['dias']))); ?></span><?php endif; ?>
                                    <?php if ($a['familia']) : ?><span class="ap-cd-tag is-familia" title="<?php echo esc_attr($a['familia']['fechas_texto']); ?>">👪 <?php esc_html_e('Reserva de la familia', 'area-profesores'); ?></span><?php endif; ?>
                                    <?php if ($a['alergia'] !== '') : ?><span class="ap-cd-tag is-alergia" title="<?php esc_attr_e('Alergia alimentaria (ficha del alumno)', 'area-profesores'); ?>">⚠️ <?php echo esc_html($a['alergia']); ?></span><?php endif; ?>
                                    <?php if ($a['dieta'] !== '') : ?><span class="ap-cd-tag is-dieta">🥗 <?php echo esc_html($a['dieta']); ?></span><?php endif; ?>
                                    <span class="ap-cd-tag is-extra"><?php esc_html_e('Extra hoy', 'area-profesores'); ?></span>
                                    <span class="ap-cd-tag is-no"><?php echo $a['alergia'] !== '' ? esc_html__('Hoy no se queda · se avisa a cocina para que no prepare su menú', 'area-profesores') : esc_html__('Hoy no se queda', 'area-profesores'); ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="ap-cd-resumen" aria-live="polite"></p>

                    <div class="ap-cd-campos">
                        <label><strong><?php esc_html_e('Dietas', 'area-profesores'); ?></strong> <small><?php esc_html_e('(opcional; las que piden las familias ya aparecen solas)', 'area-profesores'); ?></small>
                            <textarea name="dietas" rows="2" placeholder="<?php esc_attr_e('Ej.: Pablo — dieta blanda', 'area-profesores'); ?>"><?php echo esc_textarea($cd_reg ? $cd_reg['dietas'] : $cd['dietas_prev']); ?></textarea></label>
                        <?php if (!$cd_reg && $cd['dietas_prev'] !== '') : ?><small class="ap-cd-nota"><?php esc_html_e('Hemos copiado las dietas del último día: bórralas si ya no hacen falta.', 'area-profesores'); ?></small><?php endif; ?>
                        <label><strong><?php esc_html_e('Otras observaciones para cocina', 'area-profesores'); ?></strong> <small><?php esc_html_e('(los alérgicos que faltan se avisan solos)', 'area-profesores'); ?></small>
                            <textarea name="observaciones" rows="2"><?php echo esc_textarea($cd_reg ? $cd_reg['observaciones'] : ''); ?></textarea></label>
                    </div>

                    <button type="submit" class="ap-btn ap-btn-primary ap-cd-validar"><?php esc_html_e('Validar el comedor', 'area-profesores'); ?></button>
                </form>

                <details class="ap-cd-fijos" id="ap-cd-fijos"<?php echo array_filter(wp_list_pluck($cd['lista'], 'dias')) ? '' : ' open'; ?>>
                    <summary><?php esc_html_e('Comensales fijos de la clase (los que se quedan siempre)', 'area-profesores'); ?></summary>
                    <p class="ap-records-subtitle"><?php esc_html_e('Marca los días que se queda cada alumno. Esos días aparecerá marcado solo; tú solo tendrás que marcar las excepciones y validar. Pulsa el nombre para marcar toda su semana o la letra del día para marcar toda la columna.', 'area-profesores'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ap_comedor_fijos">
                        <input type="hidden" name="curso" value="<?php echo esc_attr($cd['curso']); ?>">
                        <?php wp_nonce_field('ap_comedor_fijos_' . $cd['curso']); ?>
                        <div class="ap-cd-fijos-tools">
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-cd-fijos="todos"><?php esc_html_e('Marcar todos (toda la semana)', 'area-profesores'); ?></button>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-cd-fijos="ninguno"><?php esc_html_e('Desmarcar todos', 'area-profesores'); ?></button>
                        </div>
                        <table class="ap-ma-tabla ap-cd-fijos-tabla">
                            <thead><tr>
                                <th><?php esc_html_e('Alumno', 'area-profesores'); ?></th>
                                <?php foreach ($cd_dias as $n => $d) : ?><th><button type="button" class="ap-cd-col" data-dia="<?php echo (int) $n; ?>" title="<?php esc_attr_e('Marcar o desmarcar la columna', 'area-profesores'); ?>"><?php echo esc_html($d); ?></button></th><?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($cd['lista'] as $a) : ?>
                                    <tr>
                                        <td><button type="button" class="ap-cd-fila" title="<?php esc_attr_e('Toda la semana', 'area-profesores'); ?>"><?php echo esc_html($a['nombre']); ?></button><?php if ($a['alergia'] !== '') : ?> <span class="ap-cd-tag is-alergia" title="<?php esc_attr_e('Alergia alimentaria', 'area-profesores'); ?>">⚠️</span><?php endif; ?></td>
                                        <?php foreach ($cd_dias as $n => $d) : ?>
                                            <td><input type="checkbox" name="fijos[<?php echo esc_attr($a['id']); ?>][]" value="<?php echo (int) $n; ?>" data-dia="<?php echo (int) $n; ?>" <?php checked(in_array($n, $a['dias'], true)); ?> aria-label="<?php echo esc_attr($a['nombre'] . ' ' . $d); ?>"></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" class="ap-btn ap-btn-sm ap-btn-outline"><?php esc_html_e('Guardar comensales fijos', 'area-profesores'); ?></button>
                    </form>
                </details>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
