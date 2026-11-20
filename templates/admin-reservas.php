<?php
/**
 * Admin View: Reservas de familias
 * Variables: $f (filters), $rows, $servicios, $flash
 */

if (!defined('ABSPATH')) {
    exit;
}

$ap_base = admin_url('admin.php?page=' . Area_Profesores_Reservas::SLUG);
$ap_q = array_filter(array('vista' => $f['vista'], 'fecha' => $f['vista'] === 'dia' ? $f['fecha'] : '', 'servicio' => $f['servicio'], 'curso' => $f['curso'], 'estado' => $f['estado'], 'q' => $f['q']));
$ap_dia = function ($delta) use ($f, $ap_base, $ap_q) {
    return add_query_arg(array_merge($ap_q, array('vista' => 'dia', 'fecha' => gmdate('Y-m-d', strtotime($f['fecha'] . ' 12:00:00 UTC') + $delta * DAY_IN_SECONDS))), $ap_base);
};
$ap_hoy = current_time('Y-m-d');

// Day view: group by service.
$ap_grupos = array();
foreach ($rows as $r) {
    $ap_grupos[$r['servicio_nombre']][] = $r;
}
?>
<div class="wrap ap-admin-wrap ap-reservas-admin">
    <div class="ap-admin-header no-print">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Reservas de familias', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead"><?php esc_html_e('Servicios que reservan las familias desde el Área Familias (ampliación, comedor, ludoteca, dieta, salida anticipada, retraso…). Los servicios, sus horarios y plazos se configuran en el Área Familias; los correos de aviso, en "Correos automáticos".', 'area-profesores'); ?></p>
        </div>
    </div>

    <?php if ($flash) : ?>
        <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible no-print"><p><?php echo esc_html($flash['text']); ?></p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper no-print">
        <a href="<?php echo esc_url(add_query_arg('vista', 'dia', $ap_base)); ?>" class="nav-tab <?php echo $f['vista'] === 'dia' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Por día', 'area-profesores'); ?></a>
        <a href="<?php echo esc_url(add_query_arg('vista', 'todas', $ap_base)); ?>" class="nav-tab <?php echo $f['vista'] === 'todas' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Todas las reservas', 'area-profesores'); ?></a>
    </nav>

    <form method="get" class="ap-reservas-filtros no-print">
        <input type="hidden" name="page" value="<?php echo esc_attr(Area_Profesores_Reservas::SLUG); ?>">
        <input type="hidden" name="vista" value="<?php echo esc_attr($f['vista']); ?>">
        <?php if ($f['vista'] === 'dia') : ?>
            <a class="button" href="<?php echo esc_url($ap_dia(-1)); ?>">&larr;</a>
            <input type="date" name="fecha" value="<?php echo esc_attr($f['fecha']); ?>" onchange="this.form.submit()">
            <a class="button" href="<?php echo esc_url($ap_dia(1)); ?>">&rarr;</a>
            <?php if ($f['fecha'] !== $ap_hoy) : ?><a class="button" href="<?php echo esc_url(add_query_arg(array('vista' => 'dia', 'fecha' => $ap_hoy), $ap_base)); ?>"><?php esc_html_e('Hoy', 'area-profesores'); ?></a><?php endif; ?>
        <?php else : ?>
            <select name="estado">
                <option value=""><?php esc_html_e('Activas y anuladas', 'area-profesores'); ?></option>
                <option value="activa" <?php selected($f['estado'], 'activa'); ?>><?php esc_html_e('Solo activas', 'area-profesores'); ?></option>
                <option value="anulada" <?php selected($f['estado'], 'anulada'); ?>><?php esc_html_e('Solo anuladas', 'area-profesores'); ?></option>
            </select>
        <?php endif; ?>
        <select name="servicio">
            <option value=""><?php esc_html_e('Todos los servicios', 'area-profesores'); ?></option>
            <?php foreach ($servicios as $k => $n) : ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($f['servicio'], $k); ?>><?php echo esc_html($n); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="curso">
            <option value=""><?php esc_html_e('Todos los cursos', 'area-profesores'); ?></option>
            <?php echo Area_Profesores_Cursos::get_cursos_options_html($f['curso']); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </select>
        <input type="search" name="q" value="<?php echo esc_attr($f['q']); ?>" placeholder="<?php esc_attr_e('Alumno o familia…', 'area-profesores'); ?>">
        <button type="submit" class="button"><?php esc_html_e('Filtrar', 'area-profesores'); ?></button>
        <span class="ap-reservas-acciones">
            <button type="button" class="button" onclick="window.print()"><?php esc_html_e('Imprimir', 'area-profesores'); ?></button>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge($ap_q, array('action' => 'ap_reservas_csv')), admin_url('admin-post.php')), 'ap_reservas_csv')); ?>"><?php esc_html_e('Descargar CSV', 'area-profesores'); ?></a>
        </span>
    </form>

    <?php if ($f['vista'] === 'dia') : ?>
        <h2 class="ap-reservas-dia"><?php echo esc_html(ucfirst(date_i18n('l j \d\e F \d\e Y', strtotime($f['fecha'] . ' 12:00:00')))); ?>
            <span><?php printf(esc_html(_n('%d reserva', '%d reservas', count($rows), 'area-profesores')), count($rows)); ?></span></h2>
        <?php if (!$rows) : ?>
            <div class="ap-settings-card"><p><?php esc_html_e('No hay reservas para este día.', 'area-profesores'); ?></p></div>
        <?php endif; ?>
        <?php foreach ($ap_grupos as $ap_serv => $ap_lista) : ?>
            <div class="ap-settings-card ap-reservas-grupo">
                <h3><?php echo esc_html($ap_serv); ?> <span class="ap-status-pill"><?php echo count($ap_lista); ?></span></h3>
                <?php include __DIR__ . '/partials/reservas-tabla.php'; ?>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <div class="ap-settings-card">
            <?php if (!$rows) : ?>
                <p><?php esc_html_e('No hay reservas con estos filtros.', 'area-profesores'); ?></p>
            <?php else : $ap_lista = $rows; $ap_con_servicio = true; ?>
                <?php include __DIR__ . '/partials/reservas-tabla.php'; ?>
                <?php if (count($rows) >= 300) : ?><p class="description"><?php esc_html_e('Se muestran las 300 más recientes. Usa los filtros o descarga el CSV.', 'area-profesores'); ?></p><?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
