<?php
/**
 * Template for [loginprofesores]
 * Corporate Login Screen for Colegio Casa de la Virgen.
 * Integrates Nextend Social Login (NO Google logo), Tribanda, Logo and Access Request workflow.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ap-portal-container ap-login-wrapper">
    <div class="ap-login-card">
        <!-- Tribanda Corporativa Superior -->
        <?php if (!empty($tribanda_url)) : ?>
            <div class="ap-login-tribanda-wrap">
                <img src="<?php echo esc_url($tribanda_url); ?>" alt="" class="ap-login-tribanda-img">
            </div>
        <?php endif; ?>

        <div class="ap-login-content">
            <!-- Logotipo Oficial Casa de la Virgen -->
            <?php if (!empty($logo_url)) : ?>
                <div class="ap-login-logo-wrap">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php esc_attr_e('Colegio Casa de la Virgen', 'area-profesores'); ?>" class="ap-login-logo-img">
                </div>
            <?php endif; ?>

            <header class="ap-login-header">
                <span class="ap-tag ap-tag-institucional"><?php esc_html_e('Portal Docente', 'area-profesores'); ?></span>
                <h1 class="ap-login-title"><?php esc_html_e('Área de Profesores', 'area-profesores'); ?></h1>
                <p class="ap-login-subtitle">
                    <?php esc_html_e('Acceso exclusivo para el claustro de profesores del Colegio Casa de la Virgen.', 'area-profesores'); ?>
                </p>
            </header>

            <?php if ($is_logged_in) : ?>
                <!-- Estado: Usuario ya conectado -->
                <div class="ap-login-already-box">
                    <?php if ($is_profesor) : ?>
                        <div class="ap-alert ap-alert-success" style="display: block;">
                            <p style="margin: 0 0 10px 0; font-size: 15px;">
                                <?php printf(esc_html__('Has iniciado sesión correctamente como profesor (%s).', 'area-profesores'), '<strong>' . esc_html(wp_get_current_user()->display_name) . '</strong>'); ?>
                            </p>
                            <a href="<?php echo esc_url($area_url); ?>" class="ap-btn ap-btn-primary ap-btn-lg" style="width: 100%; justify-content: center;">
                                <?php esc_html_e('Entrar al Área de Profesores', 'area-profesores'); ?> &rarr;
                            </a>
                        </div>
                    <?php else : ?>
                        <div class="ap-alert ap-alert-error" style="display: block; text-align: left;">
                            <h4 style="margin: 0 0 6px 0; font-size: 15px; color: #991b1b;"><?php esc_html_e('Cuenta pendiente de aprobación', 'area-profesores'); ?></h4>
                            <p style="margin: 0 0 12px 0; font-size: 13.5px; line-height: 1.5;">
                                <?php printf(esc_html__('Has iniciado sesión con %s, pero tu usuario todavía no ha sido activado como profesor por la dirección del centro.', 'area-profesores'), '<strong>' . esc_html(wp_get_current_user()->user_email) . '</strong>'); ?>
                            </p>
                            <p style="margin: 0; font-size: 13px; color: #7f1d1d;">
                                <?php esc_html_e('Si ya has enviado tu solicitud, te avisaremos por correo en cuanto esté aprobada.', 'area-profesores'); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 18px; text-align: center;">
                        <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="ap-link-subtle">
                            <?php esc_html_e('Cerrar sesión / Cambiar de cuenta', 'area-profesores'); ?>
                        </a>
                    </div>
                </div>

            <?php else : ?>
                <!-- Estado: No conectado -> Selector de Pestañas (Acceso / Solicitud) -->
                <nav class="ap-login-tabs-nav">
                    <button type="button" class="ap-login-tab-btn is-active" data-login-target="tab-entrar">
                        <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        <span><?php esc_html_e('Acceso Docente', 'area-profesores'); ?></span>
                    </button>
                    <button type="button" class="ap-login-tab-btn" data-login-target="tab-solicitar">
                        <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span><?php esc_html_e('Solicitar Acceso', 'area-profesores'); ?></span>
                    </button>
                </nav>

                <!-- PESTAÑA 1: ACCESO DOCENTE (Botón Corporativo sin logo de Google) -->
                <div id="tab-entrar" class="ap-login-tab-panel is-active">
                    <div class="ap-corporate-login-section">
                        <a href="<?php echo esc_url($google_login_url); ?>" class="ap-btn ap-btn-corporate-auth">
                            <svg xmlns="http://www.w3.org/2000/svg" class="ap-corporate-btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            <span class="ap-corporate-btn-text"><?php echo esc_html($login_button_text); ?></span>
                        </a>

                        <div class="ap-login-domain-hint">
                            <span class="ap-domain-pill">@<?php echo esc_html($allowed_domain); ?></span>
                            <p class="ap-domain-text">
                                <?php printf(esc_html__('Debes seleccionar tu cuenta de profesor terminada en @%s cuando el sistema te lo solicite.', 'area-profesores'), esc_html($allowed_domain)); ?>
                            </p>
                        </div>

                        <div class="ap-login-helper-switch">
                            <span><?php esc_html_e('¿Es tu primer curso en el colegio?', 'area-profesores'); ?></span>
                            <button type="button" class="ap-link-button ap-switch-to-request">
                                <?php esc_html_e('Solicita aquí tu alta como profesor', 'area-profesores'); ?> &rarr;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 2: FORMULARIO DE SOLICITUD DE ACCESO -->
                <div id="tab-solicitar" class="ap-login-tab-panel">
                    <div class="ap-request-header">
                        <h2 class="ap-request-title"><?php esc_html_e('Solicitud de Alta para Profesores', 'area-profesores'); ?></h2>
                        <p class="ap-request-desc">
                            <?php printf(esc_html__('Rellena tus datos para que la dirección del centro valide tu cuenta @%s y te asigne tus permisos de profesor.', 'area-profesores'), esc_html($allowed_domain)); ?>
                        </p>
                    </div>

                    <div id="ap-solicitud-alert" class="ap-alert" style="display: none;"></div>

                    <form id="ap-solicitud-form" class="ap-solicitud-form" method="post">
                        <!-- Nombre -->
                        <div class="ap-form-field-group">
                            <label for="ap-req-nombre" class="ap-field-label">
                                <?php esc_html_e('Nombre', 'area-profesores'); ?> <span class="ap-asterisk">*</span>
                            </label>
                            <input type="text" id="ap-req-nombre" name="nombre" class="ap-field-input" placeholder="Tu nombre" required>
                        </div>

                        <!-- Apellidos -->
                        <div class="ap-form-field-group">
                            <label for="ap-req-apellidos" class="ap-field-label">
                                <?php esc_html_e('Apellidos', 'area-profesores'); ?> <span class="ap-asterisk">*</span>
                            </label>
                            <input type="text" id="ap-req-apellidos" name="apellidos" class="ap-field-input" placeholder="Tus apellidos" required>
                        </div>

                        <!-- Correo Electrónico Institucional -->
                        <div class="ap-form-field-group">
                            <label for="ap-req-email" class="ap-field-label">
                                <?php esc_html_e('Correo de Profesor (@casadelavirgen.es)', 'area-profesores'); ?> <span class="ap-asterisk">*</span>
                            </label>
                            <input type="email" 
                                   id="ap-req-email" 
                                   name="email" 
                                   class="ap-field-input" 
                                   placeholder="<?php printf(esc_attr__('ejemplo@%s', 'area-profesores'), esc_attr($allowed_domain)); ?>" 
                                   required>
                            <span class="ap-field-hint">
                                <?php printf(esc_html__('Introduce tu cuenta institucional (@%s). No se admiten correos personales (@gmail, @hotmail).', 'area-profesores'), esc_html($allowed_domain)); ?>
                            </span>
                        </div>

                        <!-- Tutoría Asignada -->
                        <div class="ap-form-field-group">
                            <label for="ap-req-tutoria" class="ap-field-label">
                                <?php esc_html_e('Tutoría o Curso asignado este año', 'area-profesores'); ?>
                            </label>
                            <select id="ap-req-tutoria" name="tutoria_deseada" class="ap-field-select">
                                <option value=""><?php esc_html_e('-- Sin tutoría (Especialista / Apoyo / No tutor) --', 'area-profesores'); ?></option>
                                <?php foreach ($available_classes as $val => $lbl) : ?>
                                    <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Observaciones / Departamento -->
                        <div class="ap-form-field-group">
                            <label for="ap-req-observaciones" class="ap-field-label">
                                <?php esc_html_e('Observaciones o Departamento (opcional)', 'area-profesores'); ?>
                            </label>
                            <textarea id="ap-req-observaciones" 
                                      name="observaciones" 
                                      class="ap-field-textarea" 
                                      rows="2" 
                                      placeholder="<?php esc_attr_e('Indica si impartes alguna especialidad (ej: Inglés, Música, E.F., PT, etc.)...', 'area-profesores'); ?>"></textarea>
                        </div>

                        <div class="ap-form-submit-row">
                            <button type="submit" id="ap-submit-solicitud-btn" class="ap-btn ap-btn-primary ap-btn-lg" style="width: 100%; justify-content: center;">
                                <span class="ap-btn-text">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="ap-btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <?php esc_html_e('Enviar Solicitud a Dirección', 'area-profesores'); ?>
                                </span>
                                <span class="ap-btn-loading" style="display: none;">
                                    <span class="ap-spinner"></span>
                                    <?php esc_html_e('Enviando solicitud...', 'area-profesores'); ?>
                                </span>
                            </button>
                        </div>

                        <div class="ap-login-helper-switch" style="margin-top: 14px;">
                            <span><?php esc_html_e('¿Ya tienes tu cuenta activada?', 'area-profesores'); ?></span>
                            <button type="button" class="ap-link-button ap-switch-to-login">
                                &larr; <?php esc_html_e('Volver a la pantalla de acceso', 'area-profesores'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
