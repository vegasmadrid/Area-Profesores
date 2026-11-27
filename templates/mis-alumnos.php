<?php
/**
 * Teacher Portal: "Mis alumnos" tab.
 * Pupils of the teacher's tutoría and assigned classes (all for Dirección/Secretaría),
 * with their parents and the emergency phone.
 *
 * Variables: $mis_alumnos (Area_Profesores_Alumnos::datos_mis_alumnos()), $mi_curso
 */

if (!defined('ABSPATH')) {
    exit;
}

$ap_ma = $mis_alumnos;
$ap_pedido = isset($_GET['ap_ma']) ? sanitize_text_field(wp_unslash($_GET['ap_ma'])) : '';
$ap_activo = isset($ap_ma['cursos'][$ap_pedido]) ? $ap_pedido : (isset($ap_ma['cursos'][$mi_curso]) ? $mi_curso : (string) array_key_first($ap_ma['cursos']));
$ap_ma_flash = get_transient('ap_ma_flash_' . get_current_user_id());
if ($ap_ma_flash) {
    delete_transient('ap_ma_flash_' . get_current_user_id());
}
?>
<div class="ap-records-card ap-ma">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Mis alumnos', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle">
                <?php echo $ap_ma['todos']
                    ? esc_html__('Puedes consultar los alumnos de todos los cursos.', 'area-profesores')
                    : esc_html__('Alumnos de tu tutoría y de las clases que tienes asignadas, con sus padres, el teléfono de emergencia y sus alergias.', 'area-profesores'); ?>
                <?php esc_html_e('Si algún dato no es correcto, pulsa "Editar": se avisa con el motivo (a Secretaría; las alergias alimentarias, a Cocina y a los tutores).', 'area-profesores'); ?>
            </p>
        </div>
        <div class="ap-ma-tools">
            <input type="search" id="ap-ma-buscar" placeholder="<?php esc_attr_e('Buscar alumno…', 'area-profesores'); ?>" aria-label="<?php esc_attr_e('Buscar alumno', 'area-profesores'); ?>">
            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" id="ap-ma-imprimir"><?php esc_html_e('Imprimir', 'area-profesores'); ?></button>
        </div>
    </div>

    <?php if ($ap_ma_flash) : ?>
        <div class="ap-cd-msg is-<?php echo esc_attr($ap_ma_flash['type']); ?>" role="status"><?php echo esc_html($ap_ma_flash['text']); ?></div>
    <?php endif; ?>

    <?php if (count($ap_ma['cursos']) > 1) : ?>
        <div class="ap-ma-cursos" role="tablist">
            <?php foreach ($ap_ma['cursos'] as $k => $c) : ?>
                <button type="button" class="ap-ma-curso<?php echo $k === $ap_activo ? ' is-active' : ''; ?>" data-curso="<?php echo esc_attr($k); ?>">
                    <?php echo esc_html($c['label']); ?> <span><?php echo count($c['alumnos']); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($ap_ma['cursos'] as $k => $c) : ?>
        <div class="ap-ma-lista" data-curso="<?php echo esc_attr($k); ?>" <?php echo $k === $ap_activo ? '' : 'hidden'; ?>>
            <h3 class="ap-ma-titulo"><?php echo esc_html($c['label']); ?> · <?php printf(esc_html(_n('%d alumno', '%d alumnos', count($c['alumnos']), 'area-profesores')), count($c['alumnos'])); ?></h3>
            <?php if (empty($c['alumnos'])) : ?>
                <p class="ap-ma-vacio"><?php esc_html_e('Todavía no hay alumnos en este curso.', 'area-profesores'); ?></p>
            <?php else : ?>
                <table class="ap-ma-tabla">
                    <thead><tr>
                        <th class="ap-ma-n">#</th>
                        <th><?php esc_html_e('Alumno', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Padres / tutores', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Teléfono de emergencia', 'area-profesores'); ?></th>
                        <th><?php esc_html_e('Alergias', 'area-profesores'); ?></th>
                        <th class="ap-ma-acc no-print"><span class="screen-reader-text"><?php esc_html_e('Editar', 'area-profesores'); ?></span></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($c['alumnos'] as $i => $a) : ?>
                            <tr data-search="<?php echo esc_attr($a['nombre']); ?>"<?php echo !empty($a['alergias_alimentarias']) ? ' class="has-alergia"' : ''; ?>>
                                <td class="ap-ma-n"><?php echo (int) $i + 1; ?></td>
                                <td class="ap-ma-alumno"><?php echo esc_html($a['nombre']); ?></td>
                                <?php
                                // One line per parent, each with their own emergency phone.
                                $ap_padres = array();
                                foreach ($a['familias'] as $fm) {
                                    foreach (Area_Profesores_Alumnos::contactos_con_telefono($fm) as $ct) {
                                        $ap_padres[] = $ct;
                                    }
                                }
                                ?>
                                <td class="ap-ma-padres">
                                    <?php foreach ($ap_padres as $ct) : ?>
                                        <div><?php echo esc_html($ct['nombre'] !== '' ? $ct['nombre'] : '—'); ?><?php if ($ct['telefono'] !== '') : ?><span class="ap-ma-tel-inline"> · <a href="tel:<?php echo esc_attr(preg_replace('/[^\d+]/', '', explode('/', $ct['telefono'])[0])); ?>"><?php echo esc_html($ct['telefono']); ?></a></span><?php endif; ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="ap-ma-tel">
                                    <?php foreach ($ap_padres as $ct) : ?>
                                        <div>
                                            <?php if ($ct['telefono'] === '') : ?>
                                                <span class="ap-ma-sin">—</span>
                                            <?php else : ?>
                                                <?php foreach (preg_split('/\s*\/\s*/', $ct['telefono']) as $k => $tel) : $num = preg_replace('/[^\d+]/', '', $tel); ?>
                                                    <?php echo $k ? ' / ' : ''; ?><?php if (strlen($num) >= 9) : ?><a href="tel:<?php echo esc_attr($num); ?>"><?php echo esc_html($tel); ?></a><?php else : echo esc_html($tel); endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="ap-ma-alergias">
                                    <?php if (!empty($a['alergias_alimentarias'])) : ?><div class="ap-ma-alergia is-alimentaria"><b>⚠️ <?php esc_html_e('Alimentaria:', 'area-profesores'); ?></b> <?php echo esc_html($a['alergias_alimentarias']); ?></div><?php endif; ?>
                                    <?php if (!empty($a['alergias_otras'])) : ?><div class="ap-ma-alergia"><b><?php esc_html_e('No alimentaria:', 'area-profesores'); ?></b> <?php echo esc_html($a['alergias_otras']); ?></div><?php endif; ?>
                                    <?php if (empty($a['alergias_alimentarias']) && empty($a['alergias_otras'])) : ?><span class="ap-ma-sin">—</span><?php endif; ?>
                                </td>
                                <td class="ap-ma-acc no-print">
                                    <button type="button" class="ap-btn ap-btn-sm ap-btn-outline ap-ma-editar" data-alumno="<?php echo esc_attr(wp_json_encode(Area_Profesores_Alumnos_Portal::datos_edicion($a))); ?>"><?php esc_html_e('Editar', 'area-profesores'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<dialog id="ap-ma-dialog" class="ap-ma-dialog" aria-labelledby="ap-ma-dialog-titulo">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-ma-dialog-form">
        <input type="hidden" name="action" value="ap_ma_editar">
        <input type="hidden" name="alumno_id" value="">
        <?php wp_nonce_field('ap_ma_editar'); ?>
        <div class="ap-ma-dialog-head">
            <h3 id="ap-ma-dialog-titulo"></h3>
            <button type="button" class="ap-ma-dialog-x" data-cerrar aria-label="<?php esc_attr_e('Cerrar', 'area-profesores'); ?>">×</button>
        </div>
        <div class="ap-ma-dialog-body">
            <fieldset>
                <legend><?php esc_html_e('Alergias', 'area-profesores'); ?></legend>
                <label><strong>⚠️ <?php esc_html_e('Alergias alimentarias', 'area-profesores'); ?></strong> <small><?php esc_html_e('(salen en el comedor y en cocina)', 'area-profesores'); ?></small>
                    <textarea name="alergias_alimentarias" rows="2" placeholder="<?php esc_attr_e('Ej.: Frutos secos y huevo', 'area-profesores'); ?>"></textarea></label>
                <label><strong><?php esc_html_e('Alergias no alimentarias', 'area-profesores'); ?></strong>
                    <textarea name="alergias_otras" rows="2" placeholder="<?php esc_attr_e('Ej.: Polen, látex', 'area-profesores'); ?>"></textarea></label>
            </fieldset>
            <div class="ap-ma-dialog-familias"></div>
            <label class="ap-ma-motivo"><strong><?php esc_html_e('Motivo del cambio', 'area-profesores'); ?> *</strong> <small><?php esc_html_e('(se envía con los datos nuevos: a Secretaría; las alergias alimentarias, a Cocina y a los tutores)', 'area-profesores'); ?></small>
                <textarea name="motivo" rows="2" required placeholder="<?php esc_attr_e('Ej.: La madre me lo ha comunicado hoy en la entrada.', 'area-profesores'); ?>"></textarea></label>
        </div>
        <div class="ap-ma-dialog-foot">
            <button type="button" class="ap-btn ap-btn-outline" data-cerrar><?php esc_html_e('Cancelar', 'area-profesores'); ?></button>
            <button type="submit" class="ap-btn ap-btn-primary"><?php esc_html_e('Guardar y avisar', 'area-profesores'); ?></button>
        </div>
    </form>
</dialog>
