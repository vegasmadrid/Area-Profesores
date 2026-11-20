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
$ap_activo = isset($ap_ma['cursos'][$mi_curso]) ? $mi_curso : (string) array_key_first($ap_ma['cursos']);
?>
<div class="ap-records-card ap-ma">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Mis alumnos', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle">
                <?php echo $ap_ma['todos']
                    ? esc_html__('Puedes consultar los alumnos de todos los cursos.', 'area-profesores')
                    : esc_html__('Alumnos de tu tutoría y de las clases que tienes asignadas, con sus padres y el teléfono de emergencia.', 'area-profesores'); ?>
            </p>
        </div>
        <div class="ap-ma-tools">
            <input type="search" id="ap-ma-buscar" placeholder="<?php esc_attr_e('Buscar alumno…', 'area-profesores'); ?>" aria-label="<?php esc_attr_e('Buscar alumno', 'area-profesores'); ?>">
            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" id="ap-ma-imprimir"><?php esc_html_e('Imprimir', 'area-profesores'); ?></button>
        </div>
    </div>

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
                    </tr></thead>
                    <tbody>
                        <?php foreach ($c['alumnos'] as $i => $a) : ?>
                            <tr data-search="<?php echo esc_attr($a['nombre']); ?>">
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
