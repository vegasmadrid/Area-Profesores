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
                            <?php printf(esc_html__('Tutor/a: %s', 'area-profesores'), esc_html(isset($available_classes[$tutoria]) ? $available_classes[$tutoria] : $tutoria)); ?>
                        </span>
                    <?php else : ?>
                        <span class="ap-badge ap-badge-secondary">
                            <?php esc_html_e('Profesor/a Especialista (Sin tutoría asignada)', 'area-profesores'); ?>
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
    </main>
</div>
