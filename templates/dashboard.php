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

    <?php
    // Tabs in the order and with the names of Área Profesores › Pestañas.
    $ap_P = 'Area_Profesores_Pestanas';
    $ap_tabs = array(
        'comedor'        => array('ve' => $comedor !== null, 'tpl' => 'comedor-diario.php',
            'contador' => ($comedor !== null && $comedor['lectivo'] && $comedor['mi_curso'] !== '' && empty($comedor['estados'][$comedor['mi_curso']]['registro'])) ? '<span class="ap-tab-counter" title="' . esc_attr__('Pendiente de validar', 'area-profesores') . '">!</span>' : ''),
        'administracion' => array('ve' => $comensales_mes !== null, 'tpl' => 'administracion.php', 'contador' => ''),
        'enlaces'        => array('ve' => $ap_P::ve($user_id, 'enlaces'), 'tpl' => 'enlaces-utiles.php',
            'contador' => !empty($enlaces['total']) ? '<span class="ap-tab-counter">' . (int) $enlaces['total'] . '</span>' : ''),
        'alumnos'        => array('ve' => !empty($mis_alumnos['cursos']), 'tpl' => 'mis-alumnos.php',
            'contador' => '<span class="ap-tab-counter">' . (int) $mis_alumnos['total'] . '</span>'),
        'reservas'       => array('ve' => $reservas !== null, 'tpl' => 'reservas-profesor.php',
            'contador' => ($reservas !== null && !empty($reservas['proximos'][$reservas['hoy']])) ? '<span class="ap-tab-counter">' . (int) $reservas['proximos'][$reservas['hoy']] . '</span>' : ''),
        'espacios'       => array('ve' => $ap_P::ve($user_id, 'espacios'), 'tpl' => 'espacios-aula.php',
            'contador' => !empty($espacios_aula['total']) ? '<span class="ap-tab-counter">' . (int) $espacios_aula['total'] . '</span>' : ''),
        'comunicados'    => array('ve' => $is_direccion, 'tpl' => 'comunicados.php', 'contador' => ''),
        'ayuda'          => array('ve' => $ap_P::ve($user_id, 'ayuda'), 'tpl' => 'ayuda.php', 'contador' => ''),
    );
    $ap_orden = array();
    foreach ($pestanas as $ap_tab_k => $ap_tab_t) {
        if (isset($ap_tabs[$ap_tab_k]) && $ap_tabs[$ap_tab_k]['ve']) {
            $ap_orden[$ap_tab_k] = $ap_tab_t;
        }
    }
    // Opens first: the class's dining room (tutors) or else the first tab.
    $ap_primera = ($comedor !== null && empty($comedor['solo_mi_comida'])) ? 'comedor' : '';
    if ($ap_primera === '') {
        foreach ($ap_orden as $ap_tab_k => $ap_tab_t) {
            if ($ap_tab_k !== 'comedor') {
                $ap_primera = $ap_tab_k;
                break;
            }
        }
    }
    if ($ap_primera === '' && $ap_orden) {
        $ap_primera = key($ap_orden);
    }
    ?>

    <!-- Navigation / Tabs Menu -->
    <nav class="ap-nav-tabs">
        <?php foreach ($ap_orden as $ap_tab_k => $ap_tab_t) : ?>
            <button type="button" class="ap-tab-btn<?php echo $ap_tab_k === $ap_primera ? ' is-active' : ''; ?>" data-target="tab-<?php echo esc_attr($ap_tab_k); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="ap-tab-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo esc_attr($ap_tab_t['icono']); ?>" />
                </svg>
                <span><?php echo esc_html($ap_tab_t['nombre']); ?></span>
                <?php echo $ap_tabs[$ap_tab_k]['contador']; // phpcs:ignore WordPress.Security.EscapeOutput -- built above with escaping ?>
            </button>
        <?php endforeach; ?>
    </nav>

    <!-- Content Sections -->
    <main class="ap-portal-body">
        <?php if (!$ap_orden) : ?>
            <p class="ap-ma-vacio"><?php esc_html_e('Todavía no tienes ninguna pestaña asignada. Habla con Dirección.', 'area-profesores'); ?></p>
        <?php endif; ?>
        <?php foreach ($ap_orden as $ap_tab_k => $ap_tab_t) : ?>
            <section id="tab-<?php echo esc_attr($ap_tab_k); ?>" class="ap-tab-content<?php echo $ap_tab_k === $ap_primera ? ' is-active' : ''; ?>">
                <?php include AREA_PROFESORES_PLUGIN_DIR . 'templates/' . $ap_tabs[$ap_tab_k]['tpl']; ?>
            </section>
        <?php endforeach; ?>
    </main>
</div>
