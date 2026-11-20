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
        <button type="button" class="ap-tab-btn is-active" data-target="tab-comedor">
            <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span><?php esc_html_e('Apuntar Comedor', 'area-profesores'); ?></span>
        </button>

        <button type="button" class="ap-tab-btn" data-target="tab-mis-registros">
            <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <span><?php esc_html_e('Mis Registros de Hoy', 'area-profesores'); ?></span>
            <span class="ap-tab-counter" id="ap-records-count"><?php echo count($today_records); ?></span>
        </button>

        <button type="button" class="ap-tab-btn" data-target="tab-enlaces">
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
    </nav>

    <!-- Content Sections -->
    <main class="ap-portal-body">
        <!-- Pestaña 1: Formulario Comedor -->
        <section id="tab-comedor" class="ap-tab-content is-active">
            <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/form-comedor.php'; ?>
        </section>

        <!-- Pestaña 2: Mis Registros de Hoy -->
        <section id="tab-mis-registros" class="ap-tab-content">
            <div class="ap-records-card">
                <div class="ap-records-header">
                    <div>
                        <h2 class="ap-records-title"><?php esc_html_e('Registros Realizados Hoy', 'area-profesores'); ?></h2>
                        <p class="ap-records-subtitle"><?php esc_html_e('Aquí puedes revisar, editar o corregir la información enviada a cocina.', 'area-profesores'); ?></p>
                    </div>
                </div>

                <div id="ap-my-records-container">
                    <?php if (empty($today_records)) : ?>
                        <div class="ap-empty-state">
                            <div class="ap-empty-icon">🍽️</div>
                            <h3><?php esc_html_e('Aún no has enviado registros para hoy', 'area-profesores'); ?></h3>
                            <p><?php esc_html_e('Rellena el formulario de "Apuntar Comedor" para notificar los comensales y dietas.', 'area-profesores'); ?></p>
                            <button type="button" class="ap-btn ap-btn-primary ap-btn-sm ap-switch-to-form">
                                <?php esc_html_e('Ir al formulario', 'area-profesores'); ?>
                            </button>
                        </div>
                    <?php else : ?>
                        <div class="ap-records-list">
                            <?php foreach ($today_records as $rec) : ?>
                                <article class="ap-record-item" data-record-id="<?php echo esc_attr($rec->id); ?>">
                                    <div class="ap-record-item-main">
                                        <div class="ap-record-item-badge">
                                            <span class="ap-badge-class"><?php echo esc_html($rec->clase); ?></span>
                                            <span class="ap-badge-stage"><?php echo esc_html($rec->etapa); ?></span>
                                        </div>
                                        <div class="ap-record-item-stats">
                                            <span class="ap-stat-highlight">
                                                <strong><?php echo esc_html($rec->num_alumnos); ?></strong> <?php esc_html_e('alumnos estándar', 'area-profesores'); ?>
                                            </span>
                                            <?php if (!empty($rec->dietas)) : ?>
                                                <div class="ap-record-detail-row">
                                                    <strong><?php esc_html_e('Dietas:', 'area-profesores'); ?></strong>
                                                    <span><?php echo esc_html($rec->dietas); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($rec->alergicos_faltan)) : ?>
                                                <div class="ap-record-detail-row ap-text-warning">
                                                    <strong><?php esc_html_e('Faltan / Obs:', 'area-profesores'); ?></strong>
                                                    <span><?php echo esc_html($rec->alergicos_faltan); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ap-record-item-actions">
                                        <button type="button" 
                                                class="ap-btn ap-btn-sm ap-btn-outline ap-edit-record"
                                                data-id="<?php echo esc_attr($rec->id); ?>"
                                                data-clase="<?php echo esc_attr($rec->clase); ?>"
                                                data-etapa="<?php echo esc_attr($rec->etapa); ?>"
                                                data-alumnos="<?php echo esc_attr($rec->num_alumnos); ?>"
                                                data-dietas="<?php echo esc_attr($rec->dietas); ?>"
                                                data-alergicos="<?php echo esc_attr($rec->alergicos_faltan); ?>">
                                            <?php esc_html_e('Editar', 'area-profesores'); ?>
                                        </button>
                                        <button type="button" 
                                                class="ap-btn ap-btn-sm ap-btn-subtle-danger ap-delete-record"
                                                data-id="<?php echo esc_attr($rec->id); ?>">
                                            <?php esc_html_e('Eliminar', 'area-profesores'); ?>
                                        </button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Pestaña 3: Enlaces Útiles -->
        <section id="tab-enlaces" class="ap-tab-content">
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
            <!-- Pestaña 4: Comunicados a familias (Google Chat, solo Dirección) -->
            <section id="tab-comunicados" class="ap-tab-content">
                <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/comunicados.php'; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
