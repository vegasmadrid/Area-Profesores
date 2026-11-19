<?php
/**
 * Template for Restricted Access
 * Displayed when an unauthorized user or guest visits the [area_profesores] page.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ap-portal-container ap-restricted-wrapper">
    <div class="ap-restricted-card">
        <div class="ap-restricted-icon-wrapper">
            <svg class="ap-restricted-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>

        <span class="ap-badge ap-badge-warning"><?php esc_html_e('Identificación Requerida', 'area-profesores'); ?></span>
        <h2 class="ap-restricted-title"><?php esc_html_e('Área de Profesores Restringida', 'area-profesores'); ?></h2>
        <p class="ap-restricted-desc">
            <?php esc_html_e('Para acceder a este espacio debes haber iniciado sesión previamente con tu cuenta de profesor del centro escolar.', 'area-profesores'); ?>
        </p>
        <div class="ap-restricted-actions">
            <a href="<?php echo esc_url($login_url); ?>" class="ap-btn ap-btn-primary">
                <svg class="ap-btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                <?php esc_html_e('Iniciar Sesión como Profesor', 'area-profesores'); ?>
            </a>
        </div>
        <div style="margin-top: 14px;">
            <a href="<?php echo esc_url($login_url); ?>" style="font-size: 13px; color: #1e40af; text-decoration: underline; font-weight: 600;">
                <?php esc_html_e('¿Eres nuevo en el colegio? Solicita tu alta de profesor aquí', 'area-profesores'); ?> &rarr;
            </a>
        </div>
    </div>
</div>
