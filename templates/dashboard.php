<?php
/**
 * Teacher Portal Dashboard Shell
 * Loaded on the configured Área de Profesores page when an authorized teacher visits.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ap-portal-container">
    <!-- Top Welcome Header / Profile Card -->
    <header class="ap-portal-header">
        <div class="ap-header-left">
            <div class="ap-teacher-avatar">
                <?php echo get_avatar($user_id, 56, '', esc_attr($user->display_name), array('class' => 'ap-avatar-img')); ?>
            </div>
            <div class="ap-teacher-info">
                <span class="ap-welcome-tag"><?php esc_html_e('Área Docente', 'area-profesores'); ?></span>
                <h1 class="ap-teacher-name"><?php printf(esc_html__('Hola, %s', 'area-profesores'), esc_html($user->display_name)); ?></h1>
                <div class="ap-teacher-meta">
                    <?php if (!empty($tutoria)) : ?>
                        <span class="ap-badge ap-badge-tutoria">
                            <svg xmlns="http://www.w3.org/2000/svg" class="ap-icon-xs" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <?php echo esc_html(Area_Profesores_Cursos::get_codigo_label($tutoria)); ?>
                        </span>
                    <?php else : ?>
                        <?php $ap_codigo = Area_Profesores_Cursos::get_codigo_asignacion($user_id); ?>
                        <span class="ap-badge ap-badge-secondary">
                            <?php
                            $ap_etapas = array_map(array('Area_Profesores_Cursos', 'get_etapa_label'), Area_Profesores_Cursos::get_etapas_usuario($user_id));
                            echo $ap_codigo !== ''
                                ? esc_html(Area_Profesores_Cursos::get_codigo_label($ap_codigo) . ($ap_etapas ? ' · ' . implode(', ', $ap_etapas) : ''))
                                : esc_html__('Sin asignar', 'area-profesores'); ?>
                        </span>
                    <?php endif; ?>

                    <span class="ap-date-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" class="ap-icon-xs" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <?php echo esc_html($today_formatted); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="ap-header-right">
            <a href="<?php echo esc_url($logout_url); ?>" class="ap-btn ap-btn-subtle" title="<?php esc_attr_e('Cerrar sesión', 'area-profesores'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="ap-btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span><?php esc_html_e('Salir', 'area-profesores'); ?></span>
            </a>
        </div>
    </header>

    <?php if (!empty($aviso)) : ?>
        <!-- Aviso para el claustro (configurable en Ajustes) -->
        <div class="ap-portal-aviso" role="status">
            <span class="ap-portal-aviso-icon" aria-hidden="true">📢</span>
            <div class="ap-portal-aviso-text"><?php echo wp_kses_post(wpautop($aviso)); ?></div>
        </div>
    <?php endif; ?>

    <!-- Navigation / Tabs Menu -->
    <nav class="ap-nav-tabs">
        <?php $ap_primera = $comedor !== null ? 'tab-comedor' : 'tab-enlaces'; ?>
        <?php if ($comedor !== null) : ?>
            <button type="button" class="ap-tab-btn is-active" data-target="tab-comedor">
                <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span><?php esc_html_e('Comedor', 'area-profesores'); ?></span>
                <?php if ($comedor['lectivo'] && $comedor['mi_curso'] !== '' && empty($comedor['estados'][$comedor['mi_curso']]['registro'])) : ?>
                    <span class="ap-tab-counter" title="<?php esc_attr_e('Pendiente de validar', 'area-profesores'); ?>">!</span>
                <?php endif; ?>
            </button>
        <?php endif; ?>

        <?php if ($comensales_mes !== null) : ?>
            <button type="button" class="ap-tab-btn" data-target="tab-administracion">
                <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span><?php esc_html_e('Administración', 'area-profesores'); ?></span>
            </button>
        <?php endif; ?>

        <button type="button" class="ap-tab-btn<?php echo $ap_primera === 'tab-enlaces' ? ' is-active' : ''; ?>" data-target="tab-enlaces">
            <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            <span><?php esc_html_e('Enlaces Útiles', 'area-profesores'); ?></span>
            <?php if (!empty($enlaces['total'])) : ?>
                <span class="ap-tab-counter"><?php echo (int) $enlaces['total']; ?></span>
            <?php endif; ?>
        </button>

        <?php if (!empty($mis_alumnos['cursos'])) : ?>
            <button type="button" class="ap-tab-btn" data-target="tab-alumnos">
                <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span><?php esc_html_e('Mis alumnos', 'area-profesores'); ?></span>
                <span class="ap-tab-counter"><?php echo (int) $mis_alumnos['total']; ?></span>
            </button>
        <?php endif; ?>

        <?php if ($reservas !== null) : ?>
            <button type="button" class="ap-tab-btn" data-target="tab-reservas">
                <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span><?php esc_html_e('Reservas', 'area-profesores'); ?></span>
                <?php if (!empty($reservas['proximos'][$reservas['hoy']])) : ?>
                    <span class="ap-tab-counter"><?php echo (int) $reservas['proximos'][$reservas['hoy']]; ?></span>
                <?php endif; ?>
            </button>
        <?php endif; ?>

        <button type="button" class="ap-tab-btn" data-target="tab-espacios">
            <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <span><?php esc_html_e('Espacios de aula', 'area-profesores'); ?></span>
            <?php if (!empty($espacios_aula['total'])) : ?>
                <span class="ap-tab-counter"><?php echo (int) $espacios_aula['total']; ?></span>
            <?php endif; ?>
        </button>

        <?php if ($is_direccion) : ?>
            <button type="button" class="ap-tab-btn" data-target="tab-comunicados">
                <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                </svg>
                <span><?php esc_html_e('Comunicados', 'area-profesores'); ?></span>
            </button>
        <?php endif; ?>

        <button type="button" class="ap-tab-btn" data-target="tab-ayuda">
            <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?php esc_html_e('Ayuda', 'area-profesores'); ?></span>
        </button>
    </nav>

    <!-- Content Sections -->
    <main class="ap-portal-body">
        <?php if ($comedor !== null) : ?>
            <!-- Pestaña: Comedor de hoy (Primer Ciclo, Infantil y Primaria) -->
            <section id="tab-comedor" class="ap-tab-content is-active">
                <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/comedor-diario.php'; ?>
            </section>
        <?php endif; ?>

        <?php if ($comensales_mes !== null) : ?>
            <!-- Pestaña: Administración (todo lo del mes para facturar) -->
            <section id="tab-administracion" class="ap-tab-content">
                <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/administracion.php'; ?>
            </section>
        <?php endif; ?>

        <!-- Pestaña 3: Enlaces Útiles -->
        <section id="tab-enlaces" class="ap-tab-content<?php echo $ap_primera === 'tab-enlaces' ? ' is-active' : ''; ?>">
            <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/enlaces-utiles.php'; ?>
        </section>

        <?php if (!empty($mis_alumnos['cursos'])) : ?>
            <!-- Pestaña: Mis alumnos -->
            <section id="tab-alumnos" class="ap-tab-content">
                <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/mis-alumnos.php'; ?>
            </section>
        <?php endif; ?>

        <?php if ($reservas !== null) : ?>
            <!-- Pestaña: Reservas de las familias -->
            <section id="tab-reservas" class="ap-tab-content">
                <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/reservas-profesor.php'; ?>
            </section>
        <?php endif; ?>

        <!-- Pestaña: Espacios de aula (espacios de Google Chat de las familias) -->
        <section id="tab-espacios" class="ap-tab-content">
            <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/espacios-aula.php'; ?>
        </section>

        <?php if ($is_direccion) : ?>
            <!-- Pestaña 4: Comunicados a familias (Google Chat, Dirección y Comunicación) -->
            <section id="tab-comunicados" class="ap-tab-content">
                <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/comunicados.php'; ?>
            </section>
        <?php endif; ?>

        <!-- Pestaña: Ayuda (manual del Área de Profesores) -->
        <section id="tab-ayuda" class="ap-tab-content">
            <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/ayuda.php'; ?>
        </section>
    </main>
</div>
