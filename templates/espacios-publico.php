<?php
/**
 * Public page: list of the family Chat spaces ([espacios_familias]).
 *
 * Variables: $grupos, $titulo, $logo_url, $mostrar_tutor, $tutores
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ap-portal-container ap-directorio">
    <header class="ap-directorio-header">
        <?php if (!empty($logo_url)) : ?>
            <img class="ap-directorio-logo" src="<?php echo esc_url($logo_url); ?>" alt="">
        <?php endif; ?>
        <div>
            <h2 class="ap-directorio-title"><?php echo esc_html($titulo); ?></h2>
            <p class="ap-directorio-intro"><?php esc_html_e('Busca el curso de tu hijo o hija y pulsa "Abrir en Google Chat". Para entrar tienes que usar la cuenta de Google con la que el colegio te ha añadido al espacio.', 'area-profesores'); ?></p>
        </div>
    </header>

    <?php if (empty($grupos)) : ?>
        <div class="ap-empty-state"><p><?php esc_html_e('Todavía no hay espacios publicados.', 'area-profesores'); ?></p></div>
    <?php else : ?>
        <div class="ap-directorio-search">
            <input type="search" id="ap-directorio-buscar" placeholder="<?php esc_attr_e('Buscar curso (ej: 3º EP, 4 años, ESO…)', 'area-profesores'); ?>" aria-label="<?php esc_attr_e('Buscar curso', 'area-profesores'); ?>">
        </div>

        <nav class="ap-directorio-nav" aria-label="<?php esc_attr_e('Etapas', 'area-profesores'); ?>">
            <?php foreach ($grupos as $gkey => $g) : ?>
                <a href="#ap-dir-<?php echo esc_attr(sanitize_title($gkey)); ?>"><?php echo esc_html($g['label']); ?></a>
            <?php endforeach; ?>
        </nav>

        <?php foreach ($grupos as $gkey => $g) : ?>
            <section class="ap-directorio-grupo" id="ap-dir-<?php echo esc_attr(sanitize_title($gkey)); ?>">
                <h3 class="ap-directorio-grupo-title"><?php echo esc_html($g['label']); ?></h3>
                <div class="ap-directorio-grid">
                    <?php foreach ($g['espacios'] as $e) :
                        $tutor = ($mostrar_tutor && $e['curso'] !== '' && !empty($tutores[$e['curso']])) ? $tutores[$e['curso']][0]->display_name : '';
                        $buscar = $e['titulo'] . ' ' . $e['subtitulo'] . ' ' . $g['label'] . ' ' . $tutor;
                    ?>
                        <a class="ap-directorio-card" href="<?php echo esc_url($e['chat_url']); ?>" target="_blank" rel="noopener" data-search="<?php echo esc_attr($buscar); ?>">
                            <span class="ap-directorio-curso"><?php echo esc_html($e['titulo']); ?></span>
                            <?php if ($e['subtitulo'] !== '') : ?>
                                <span class="ap-directorio-promo"><?php echo esc_html($e['subtitulo']); ?></span>
                            <?php endif; ?>
                            <?php if ($tutor !== '') : ?>
                                <span class="ap-directorio-tutor"><?php printf(esc_html__('Tutor/a: %s', 'area-profesores'), esc_html($tutor)); ?></span>
                            <?php endif; ?>
                            <span class="ap-directorio-btn">💬 <?php esc_html_e('Abrir en Google Chat', 'area-profesores'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <p class="ap-directorio-empty" id="ap-directorio-vacio" hidden><?php esc_html_e('No hay ningún curso que coincida con la búsqueda.', 'area-profesores'); ?></p>

        <script>
        (function () {
            var input = document.getElementById('ap-directorio-buscar');
            if (!input) { return; }
            var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/º|ª/g, ''); };
            input.addEventListener('input', function () {
                var words = norm(input.value).split(/[^a-z0-9]+/).filter(Boolean);
                var total = 0;
                document.querySelectorAll('.ap-directorio-grupo').forEach(function (sec) {
                    var visible = 0;
                    sec.querySelectorAll('.ap-directorio-card').forEach(function (card) {
                        // Each word must be the start of a word of the card ("3 ep" → 3º EP).
                        var tokens = norm(card.getAttribute('data-search')).split(/[^a-z0-9]+/);
                        var ok = words.every(function (w) {
                            return tokens.some(function (t) { return t.indexOf(w) === 0; });
                        });
                        card.hidden = !ok;
                        if (ok) { visible++; }
                    });
                    sec.hidden = visible === 0;
                    total += visible;
                });
                document.getElementById('ap-directorio-vacio').hidden = total > 0;
            });
        })();
        </script>
    <?php endif; ?>
</div>
