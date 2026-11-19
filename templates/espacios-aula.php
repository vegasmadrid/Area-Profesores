<?php
/**
 * Teacher Portal: "Espacios de aula" tab.
 * Google Chat spaces of the families of every course, to open the one needed.
 *
 * Variables: $espacios_aula (Area_Profesores_Aulas::get_espacios_para_profesores())
 */

if (!defined('ABSPATH')) {
    exit;
}

$ap_ea = $espacios_aula;
$ap_ea_card = function ($e, $tutores, $destacado = false) {
    $tutor = ($e['curso'] !== '' && !empty($tutores[$e['curso']])) ? $tutores[$e['curso']][0]->display_name : '';
    $buscar = $e['titulo'] . ' ' . $e['subtitulo'] . ' ' . $e['grupo_label'] . ' ' . $tutor;
    ?>
    <a class="ap-ea-card<?php echo $destacado ? ' is-mine' : ''; ?>" href="<?php echo esc_url($e['chat_url']); ?>" target="_blank" rel="noopener" data-search="<?php echo esc_attr($buscar); ?>">
        <span class="ap-ea-curso"><?php echo esc_html($e['titulo']); ?></span>
        <?php if ($e['subtitulo'] !== '') : ?>
            <span class="ap-ea-promo"><?php echo esc_html($e['subtitulo']); ?></span>
        <?php endif; ?>
        <?php if ($tutor !== '') : ?>
            <span class="ap-ea-tutor"><?php printf(esc_html__('Tutor/a: %s', 'area-profesores'), esc_html($tutor)); ?></span>
        <?php endif; ?>
        <span class="ap-ea-btn"><?php esc_html_e('Abrir en Chat', 'area-profesores'); ?> ↗</span>
    </a>
    <?php
};
?>

<div class="ap-records-card ap-ea">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Espacios de aula', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Espacios de Google Chat de las familias de cada curso. Pulsa en uno para abrirlo (tienes que ser miembro del espacio).', 'area-profesores'); ?></p>
        </div>
        <?php if (!empty($ap_ea['total'])) : ?>
            <div class="ap-enlaces-search">
                <input type="search" id="ap-ea-buscar" placeholder="<?php esc_attr_e('Buscar curso o tutor/a…', 'area-profesores'); ?>" aria-label="<?php esc_attr_e('Buscar espacio de aula', 'area-profesores'); ?>">
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($ap_ea['total']) && empty($ap_ea['anteriores'])) : ?>
        <div class="ap-empty-state">
            <div class="ap-empty-icon">💬</div>
            <h3><?php esc_html_e('Todavía no hay espacios de aula', 'area-profesores'); ?></h3>
        </div>
    <?php else : ?>

        <?php if ($ap_ea['mi_espacio']) : ?>
            <div class="ap-ea-grupo ap-ea-mine-wrap">
                <h3 class="ap-enlaces-group-title"><?php esc_html_e('Tu clase', 'area-profesores'); ?></h3>
                <div class="ap-ea-grid">
                    <?php $ap_ea_card($ap_ea['mi_espacio'], $ap_ea['tutores'], true); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($ap_ea['grupos'] as $g) : ?>
            <div class="ap-ea-grupo">
                <h3 class="ap-enlaces-group-title"><?php echo esc_html($g['label']); ?></h3>
                <div class="ap-ea-grid">
                    <?php foreach ($g['espacios'] as $e) { $ap_ea_card($e, $ap_ea['tutores']); } ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($ap_ea['anteriores'])) : ?>
            <details class="ap-ea-grupo ap-ea-anteriores">
                <summary class="ap-enlaces-group-title"><?php printf(esc_html__('Promociones anteriores (%d)', 'area-profesores'), count($ap_ea['anteriores'])); ?></summary>
                <div class="ap-ea-grid">
                    <?php foreach ($ap_ea['anteriores'] as $e) { $ap_ea_card($e, array()); } ?>
                </div>
            </details>
        <?php endif; ?>

        <p class="ap-ea-vacio" id="ap-ea-vacio" hidden><?php esc_html_e('No hay ningún espacio que coincida con la búsqueda.', 'area-profesores'); ?></p>
    <?php endif; ?>
</div>
