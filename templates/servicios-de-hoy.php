<?php
/**
 * Template for [comedorhoy]
 * Public view for kitchen staff and school team showing today's dining services.
 * Comfortable typography, responsive for mobile, date picker, and print-ready.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ap-portal-container ap-cocina-container">
    <!-- Header para Cocina -->
    <header class="ap-cocina-header">
        <div class="ap-cocina-header-top">
            <div class="ap-cocina-branding">
                <span class="ap-tag ap-tag-cocina">
                    <svg xmlns="http://www.w3.org/2000/svg" class="ap-icon-xs" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <?php esc_html_e('Servicio de Comedor Escolar', 'area-profesores'); ?>
                </span>
                <h1 class="ap-cocina-title"><?php esc_html_e('Control Diario de Cocina', 'area-profesores'); ?></h1>
                <p class="ap-cocina-subtitle">
                    <?php esc_html_e('Listado de comensales, dietas especiales e incidencias reportadas por el profesorado.', 'area-profesores'); ?>
                </p>
            </div>

            <!-- Botones de Acción (Imprimir y Actualizar) -->
            <div class="ap-cocina-actions no-print">
                <button type="button" class="ap-btn ap-btn-outline ap-btn-print" onclick="window.print()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="ap-btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span><?php esc_html_e('Imprimir para Cocina', 'area-profesores'); ?></span>
                </button>
                <a href="<?php echo esc_url(add_query_arg('fecha', $fecha, get_permalink())); ?>" class="ap-btn ap-btn-secondary" title="<?php esc_attr_e('Actualizar datos', 'area-profesores'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="ap-btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span><?php esc_html_e('Actualizar', 'area-profesores'); ?></span>
                </a>
            </div>
        </div>

        <!-- Barra de Selección de Fecha (Cómoda para móviles y táctil) -->
        <div class="ap-cocina-date-bar no-print">
            <form method="get" action="<?php echo esc_url(get_permalink()); ?>" class="ap-date-navigator-form">
                <div class="ap-date-nav-buttons">
                    <a href="<?php echo esc_url(add_query_arg('fecha', $prev_date, get_permalink())); ?>" 
                       class="ap-btn ap-btn-date-nav" 
                       title="<?php esc_attr_e('Día anterior', 'area-profesores'); ?>">
                        &larr; <?php esc_html_e('Día anterior', 'area-profesores'); ?>
                    </a>

                    <?php if ($fecha !== $today) : ?>
                        <a href="<?php echo esc_url(remove_query_arg('fecha', get_permalink())); ?>" 
                           class="ap-btn ap-btn-today-nav">
                            📅 <?php esc_html_e('Ir a Hoy', 'area-profesores'); ?>
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo esc_url(add_query_arg('fecha', $next_date, get_permalink())); ?>" 
                       class="ap-btn ap-btn-date-nav" 
                       title="<?php esc_attr_e('Día siguiente', 'area-profesores'); ?>">
                        <?php esc_html_e('Día siguiente', 'area-profesores'); ?> &rarr;
                    </a>
                </div>

                <div class="ap-date-picker-wrap">
                    <label for="ap-fecha-selector" class="ap-date-picker-label">
                        <?php esc_html_e('Cambiar fecha:', 'area-profesores'); ?>
                    </label>
                    <input type="date" 
                           id="ap-fecha-selector" 
                           name="fecha" 
                           value="<?php echo esc_attr($fecha); ?>" 
                           onchange="this.form.submit()" 
                           class="ap-date-input">
                    <button type="submit" class="ap-btn ap-btn-primary ap-btn-sm ap-btn-filter-date">
                        <?php esc_html_e('Ver', 'area-profesores'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Indicador de la Fecha Seleccionada -->
        <div class="ap-current-date-banner">
            <div class="ap-date-big-text">
                <span class="ap-date-day-badge">
                    <?php echo esc_html(ucfirst($fecha_formatted)); ?>
                </span>
                <?php if ($fecha === $today) : ?>
                    <span class="ap-badge-live"><?php esc_html_e('● SERVICIO DE HOY', 'area-profesores'); ?></span>
                <?php else : ?>
                    <span class="ap-badge-historical"><?php esc_html_e('Consulta Histórica', 'area-profesores'); ?></span>
                <?php endif; ?>
            </div>
            <div class="ap-date-time-stamp">
                <?php printf(esc_html__('Datos actualizados a las %s', 'area-profesores'), date_i18n('H:i')); ?>
            </div>
        </div>
    </header>

    <!-- Tarjetas de Resumen Numérico (Grandes y Cómodas de leer en cocina) -->
    <div class="ap-cocina-metrics-grid">
        <!-- 1. Total Comensales Estándar -->
        <div class="ap-cocina-metric-card ap-metric-total">
            <div class="ap-metric-icon-wrap ap-bg-blue">
                🍽️
            </div>
            <div class="ap-metric-data">
                <span class="ap-metric-big-number"><?php echo intval($summary->total_comensales); ?></span>
                <span class="ap-metric-title"><?php esc_html_e('Comensales Estándar', 'area-profesores'); ?></span>
                <span class="ap-metric-subtext"><?php esc_html_e('Sin incluir dietas especiales', 'area-profesores'); ?></span>
            </div>
        </div>

        <!-- 2. Clases con Dietas Especiales -->
        <div class="ap-cocina-metric-card ap-metric-dietas">
            <div class="ap-metric-icon-wrap ap-bg-orange">
                🥗
            </div>
            <div class="ap-metric-data">
                <span class="ap-metric-big-number ap-text-orange"><?php echo intval($summary->clases_con_dietas); ?></span>
                <span class="ap-metric-title"><?php esc_html_e('Clases con Dietas', 'area-profesores'); ?></span>
                <span class="ap-metric-subtext"><?php esc_html_e('Alergias / menús específicos', 'area-profesores'); ?></span>
            </div>
        </div>

        <!-- 3. Alérgicos Ausentes / Incidencias -->
        <div class="ap-cocina-metric-card ap-metric-alergicos">
            <div class="ap-metric-icon-wrap ap-bg-red">
                ⚠️
            </div>
            <div class="ap-metric-data">
                <span class="ap-metric-big-number ap-text-red"><?php echo intval($summary->clases_con_incidencias); ?></span>
                <span class="ap-metric-title"><?php esc_html_e('Alérgicos que Faltan', 'area-profesores'); ?></span>
                <span class="ap-metric-subtext"><?php esc_html_e('No preparar menú especial', 'area-profesores'); ?></span>
            </div>
        </div>

        <!-- 4. Clases Reportadas -->
        <div class="ap-cocina-metric-card ap-metric-clases">
            <div class="ap-metric-icon-wrap ap-bg-slate">
                📋
            </div>
            <div class="ap-metric-data">
                <span class="ap-metric-big-number"><?php echo intval($summary->total_clases); ?></span>
                <span class="ap-metric-title"><?php esc_html_e('Clases Notificadas', 'area-profesores'); ?></span>
                <span class="ap-metric-subtext"><?php esc_html_e('Profesores que han rellenado', 'area-profesores'); ?></span>
            </div>
        </div>
    </div>

    <?php if (empty($records)) : ?>
        <!-- Estado Vacío -->
        <div class="ap-cocina-empty-card">
            <div class="ap-empty-emoji">👨‍🍳</div>
            <h2 class="ap-empty-heading"><?php esc_html_e('No hay registros de comedor para esta fecha', 'area-profesores'); ?></h2>
            <p class="ap-empty-text">
                <?php if ($fecha === $today) : ?>
                    <?php esc_html_e('Los profesores aún no han enviado el parte de comedor de hoy. En cuanto los tutores rellenen el formulario, los datos aparecerán aquí automáticamente.', 'area-profesores'); ?>
                <?php else : ?>
                    <?php printf(esc_html__('No se registraron datos de comensales para el día %s.', 'area-profesores'), esc_html($fecha_formatted)); ?>
                <?php endif; ?>
            </p>
            <div class="ap-empty-actions no-print">
                <a href="<?php echo esc_url(remove_query_arg('fecha', get_permalink())); ?>" class="ap-btn ap-btn-primary">
                    <?php esc_html_e('Ver el día de hoy', 'area-profesores'); ?>
                </a>
            </div>
        </div>
    <?php else : ?>

        <!-- SECCIÓN 1: AVISOS CRÍTICOS PARA COCINA (Alérgicos que faltan) -->
        <?php if (!empty($alergicos_faltan_list)) : ?>
            <section class="ap-cocina-alert-section">
                <div class="ap-cocina-alert-header">
                    <div class="ap-alert-badge-icon">⚠️</div>
                    <div>
                        <h2 class="ap-alert-section-title">
                            <?php esc_html_e('ATENCIÓN COCINA: Alérgicos Ausentes / No preparar menús especiales', 'area-profesores'); ?>
                        </h2>
                        <p class="ap-alert-section-desc">
                            <?php esc_html_e('Los siguientes alumnos con alergia o dieta especial NO han asistido hoy:', 'area-profesores'); ?>
                        </p>
                    </div>
                </div>

                <div class="ap-alert-items-grid">
                    <?php foreach ($alergicos_faltan_list as $item) : ?>
                        <div class="ap-alert-item-card">
                            <span class="ap-alert-class-badge"><?php echo esc_html($item['clase']); ?> (<?php echo esc_html($item['etapa']); ?>)</span>
                            <div class="ap-alert-item-text">
                                <?php echo nl2br(esc_html($item['texto'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- SECCIÓN 2: RESUMEN DE DIETAS ESPECIALES PARA COCINA -->
        <?php if (!empty($dietas_list)) : ?>
            <section class="ap-cocina-dietas-section">
                <div class="ap-section-header">
                    <span class="ap-section-icon">🥗</span>
                    <div>
                        <h2 class="ap-section-title"><?php esc_html_e('Dietas Especiales a Preparar Hoy', 'area-profesores'); ?></h2>
                        <p class="ap-section-subtitle"><?php esc_html_e('Comprueba los nombres, cursos y necesidades alimentarias indicadas por los tutores:', 'area-profesores'); ?></p>
                    </div>
                </div>

                <div class="ap-dietas-cards-grid">
                    <?php foreach ($dietas_list as $dieta) : ?>
                        <div class="ap-dieta-card">
                            <div class="ap-dieta-card-header">
                                <span class="ap-dieta-class-badge"><?php echo esc_html($dieta['clase']); ?></span>
                                <span class="ap-dieta-stage-badge"><?php echo esc_html($dieta['etapa']); ?></span>
                            </div>
                            <div class="ap-dieta-content">
                                <?php echo nl2br(esc_html($dieta['texto'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- SECCIÓN 3: DESGLOSE DETALLADO CLASE POR CLASE -->
        <section class="ap-cocina-classes-section">
            <div class="ap-section-header">
                <span class="ap-section-icon">🏫</span>
                <div>
                    <h2 class="ap-section-title"><?php esc_html_e('Desglose Detallado por Clases', 'area-profesores'); ?></h2>
                    <p class="ap-section-subtitle"><?php esc_html_e('Detalle completo de comensales, dietas y datos reportados por cada tutor:', 'area-profesores'); ?></p>
                </div>
            </div>

            <!-- Vista en Tarjetas para Móviles y Escritorio -->
            <div class="ap-cocina-cards-container">
                <?php foreach ($records as $r) : ?>
                    <article class="ap-cocina-class-card">
                        <div class="ap-class-card-header">
                            <div class="ap-class-card-title-group">
                                <span class="ap-class-main-pill"><?php echo esc_html($r->clase); ?></span>
                                <span class="ap-stage-subpill"><?php echo esc_html($r->etapa); ?></span>
                            </div>

                            <div class="ap-class-comensales-box">
                                <span class="ap-comensales-number"><?php echo esc_html($r->num_alumnos); ?></span>
                                <span class="ap-comensales-lbl"><?php esc_html_e('alumnos estándar', 'area-profesores'); ?></span>
                            </div>
                        </div>

                        <div class="ap-class-card-body">
                            <!-- Dietas -->
                            <div class="ap-class-card-row">
                                <span class="ap-row-label">
                                    <strong>🥗 <?php esc_html_e('Dietas:', 'area-profesores'); ?></strong>
                                </span>
                                <div class="ap-row-value">
                                    <?php if (!empty($r->dietas)) : ?>
                                        <div class="ap-text-dieta-highlight">
                                            <?php echo nl2br(esc_html($r->dietas)); ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="ap-text-muted"><?php esc_html_e('Ninguna dieta especial indicada', 'area-profesores'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Alérgicos que faltan -->
                            <?php if (!empty($r->alergicos_faltan)) : ?>
                                <div class="ap-class-card-row ap-row-warning">
                                    <span class="ap-row-label">
                                        <strong>⚠️ <?php esc_html_e('Alérgicos ausentes / Obs:', 'area-profesores'); ?></strong>
                                    </span>
                                    <div class="ap-row-value ap-text-alergico-highlight">
                                        <?php echo nl2br(esc_html($r->alergicos_faltan)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <footer class="ap-class-card-footer">
                            <span class="ap-footer-teacher">
                                <svg xmlns="http://www.w3.org/2000/svg" class="ap-icon-xxs" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <?php echo esc_html($r->profesor_nombre ?: 'Profesor ID ' . $r->user_id); ?>
                            </span>
                            <span class="ap-footer-time">
                                <svg xmlns="http://www.w3.org/2000/svg" class="ap-icon-xxs" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <?php echo esc_html(date_i18n('H:i', strtotime($r->created_at))); ?>
                            </span>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Resumen Total Final al pie de página -->
            <div class="ap-cocina-grand-total">
                <div class="ap-grand-total-content">
                    <span class="ap-grand-total-label"><?php esc_html_e('TOTAL GENERAL DE COMENSALES ESTÁNDAR:', 'area-profesores'); ?></span>
                    <span class="ap-grand-total-number"><?php echo intval($summary->total_comensales); ?> <?php esc_html_e('alumnos', 'area-profesores'); ?></span>
                </div>
            </div>
        </section>

    <?php endif; ?>
</div>
