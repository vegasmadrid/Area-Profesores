<?php
/**
 * Printable manual of the Área de Profesores (every section), to hand out or save as PDF.
 */

if (!defined('ABSPATH')) {
    exit;
}

$mv = array();
$maj = Area_Profesores_Comensales::ajustes();
$logo = Area_Profesores_Admin::get_setting('logo_url', '');
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php esc_html_e('Manual del Área de Profesores', 'area-profesores'); ?></title>
<link rel="stylesheet" href="<?php echo esc_url(AREA_PROFESORES_PLUGIN_URL . 'assets/css/frontend.css?ver=' . AREA_PROFESORES_VERSION); ?>">
<style>
    body { margin: 0; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #0f172a; }
    .ap-manual-hoja { max-width: 820px; margin: 24px auto; background: #fff; padding: 36px 44px; border-radius: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
    .ap-manual-portada { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 3px solid #0f172a; padding-bottom: 14px; margin-bottom: 18px; }
    .ap-manual-portada h1 { margin: 0; font-size: 28px; }
    .ap-manual-portada p { margin: 4px 0 0; color: #475569; }
    .ap-manual-portada img { max-height: 64px; width: auto; }
    .ap-manual-barra { max-width: 820px; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; padding: 0 16px; }
    @media (max-width: 640px) { .ap-manual-hoja { margin: 12px; padding: 20px 16px; } }
    @media print {
        body { background: #fff; }
        body * { visibility: visible !important; }
        header.ap-manual-portada, nav.ap-manual-indice { display: flex !important; }
        .ap-manual-barra { display: none; }
        .ap-manual-hoja { margin: 0; padding: 0; box-shadow: none; max-width: none; }
        .ap-manual-indice a { color: #0f172a; }
    }
</style>
</head>
<body>
    <div class="ap-manual-barra">
        <button type="button" class="ap-btn ap-btn-primary" onclick="window.print()"><?php esc_html_e('Imprimir o guardar en PDF', 'area-profesores'); ?></button>
    </div>
    <main class="ap-manual-hoja ap-manual">
        <header class="ap-manual-portada">
            <div>
                <h1><?php esc_html_e('Manual del Área de Profesores', 'area-profesores'); ?></h1>
                <p><?php echo esc_html(get_bloginfo('name')); ?> · <?php echo esc_html(date_i18n('F \d\e Y')); ?></p>
            </div>
            <?php if ($logo) : ?><img src="<?php echo esc_url($logo); ?>" alt=""><?php endif; ?>
        </header>
        <p><?php printf(esc_html__('El Área de Profesores está en %s. Cada profesor ve solo las pestañas que necesita; las secciones marcadas como "Solo…" son para las cuentas indicadas.', 'area-profesores'), '<strong>' . esc_html(preg_replace('#^https?://#', '', untrailingslashit(Area_Profesores_Comensales::url_portal() ? strtok(Area_Profesores_Comensales::url_portal(), '#') : home_url('/')))) . '</strong>'); ?></p>
        <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/partials/manual.php'; ?>
    </main>
</body>
</html>
