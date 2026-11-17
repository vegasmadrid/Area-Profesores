<?php
/**
 * Teacher Portal: "Enlaces Útiles" tab.
 * Links are managed in "Área Profesores > Enlaces Útiles".
 *
 * @var array $enlaces { destacados, categorias, total }
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ap_render_enlace_card')) {
    function ap_render_enlace_card($link, $extra_class = '') {
        $target = $link['nueva_pestana'] ? ' target="_blank" rel="noopener noreferrer"' : '';
        $search = strtolower(remove_accents($link['titulo'] . ' ' . $link['descripcion'] . ' ' . $link['categoria']));
        ?>
        <a class="ap-enlace-card <?php echo esc_attr($extra_class); ?>" href="<?php echo esc_url($link['url']); ?>"<?php echo $target; // phpcs:ignore ?> data-search="<?php echo esc_attr($search); ?>">
            <span class="ap-enlace-icon" aria-hidden="true"><?php echo esc_html($link['icono'] !== '' ? $link['icono'] : '🔗'); ?></span>
            <span class="ap-enlace-body">
                <span class="ap-enlace-title"><?php echo esc_html($link['titulo']); ?></span>
                <?php if ($link['descripcion'] !== '') : ?>
                    <span class="ap-enlace-desc"><?php echo esc_html($link['descripcion']); ?></span>
                <?php endif; ?>
            </span>
            <?php if ($link['nueva_pestana']) : ?>
                <svg xmlns="http://www.w3.org/2000/svg" class="ap-enlace-ext" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
            <?php endif; ?>
        </a>
        <?php
    }
}
?>

<div class="ap-records-card ap-enlaces-card">
    <div class="ap-records-header ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Enlaces Útiles', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Accesos directos a plataformas, documentos y recursos del centro.', 'area-profesores'); ?></p>
        </div>
        <?php if ($enlaces['total'] > 6) : ?>
            <div class="ap-enlaces-search">
                <input type="search" id="ap-enlaces-search-input" placeholder="<?php esc_attr_e('Buscar enlace...', 'area-profesores'); ?>" aria-label="<?php esc_attr_e('Buscar enlace', 'area-profesores'); ?>">
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($enlaces['total'])) : ?>
        <div class="ap-empty-state">
            <div class="ap-empty-icon">🔗</div>
            <h3><?php esc_html_e('Todavía no hay enlaces disponibles', 'area-profesores'); ?></h3>
            <p><?php esc_html_e('La dirección del centro irá añadiendo aquí los enlaces de interés.', 'area-profesores'); ?></p>
        </div>
    <?php else : ?>

        <?php if (!empty($enlaces['destacados'])) : ?>
            <div class="ap-enlaces-group ap-enlaces-group-destacados">
                <h3 class="ap-enlaces-group-title">★ <?php esc_html_e('Accesos rápidos', 'area-profesores'); ?></h3>
                <div class="ap-enlaces-grid">
                    <?php foreach ($enlaces['destacados'] as $link) { ap_render_enlace_card($link, 'is-featured'); } ?>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($enlaces['categorias'] as $categoria => $links) : ?>
            <div class="ap-enlaces-group">
                <h3 class="ap-enlaces-group-title"><?php echo esc_html($categoria); ?></h3>
                <div class="ap-enlaces-grid">
                    <?php foreach ($links as $link) { ap_render_enlace_card($link); } ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="ap-empty-state ap-enlaces-no-results" style="display: none;">
            <p><?php esc_html_e('No se ha encontrado ningún enlace con esa búsqueda.', 'area-profesores'); ?></p>
        </div>
    <?php endif; ?>
</div>
