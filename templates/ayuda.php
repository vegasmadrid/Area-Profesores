<?php
/**
 * Teacher Portal: "Ayuda" tab (manual of the sections this teacher can see).
 * Variables: $ayuda { mv, completo_url }
 */

if (!defined('ABSPATH')) {
    exit;
}

$mv = $ayuda['mv'];
$maj = Area_Profesores_Comensales::ajustes();
?>
<div class="ap-records-card ap-manual">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Ayuda', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Cómo se usa cada pestaña del Área de Profesores. Aquí solo sale lo que tú puedes ver.', 'area-profesores'); ?></p>
        </div>
        <div class="ap-ma-tools">
            <a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url($ayuda['completo_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Manual completo (imprimir o PDF)', 'area-profesores'); ?></a>
        </div>
    </div>
    <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/partials/manual.php'; ?>
</div>
