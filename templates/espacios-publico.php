<?php
/**
 * Public page: list of the family Chat spaces ([espacios_familias]).
 * Self-contained styles with the look of the school's website.
 *
 * Variables: $grupos, $anteriores, $titulo, $intro (HTML), $mostrar_ayuda, $logo_url,
 *            $tribanda_url, $mostrar_tutor, $mostrar_foto, $tutores
 */

if (!defined('ABSPATH')) {
    exit;
}

$apdf_icono_chat = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 3h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H8l-4.6 3.7A.9.9 0 0 1 2 21V5a2 2 0 0 1 2-2Zm3 6.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm5 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm5 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z"/></svg>';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
.apdf {
    --apdf-navy: #313280;
    --apdf-navy-dark: #24245f;
    --apdf-sky: #72a7f7;
    --apdf-orange: #dc8e2b;
    --apdf-ink: #1f2240;
    --apdf-muted: #6b7091;
    --apdf-line: #e3e6f2;
    --apdf-bg: #f5f7fc;
    font-family: "Montserrat", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--apdf-ink);
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 0 40px;
    box-sizing: border-box;
    line-height: 1.5;
    text-align: left;
}
.apdf *, .apdf *::before, .apdf *::after { box-sizing: border-box; }
/* Header and tribanda across the full width of the window, like the school's website */
html:has(.apdf), body:has(.apdf) { overflow-x: clip; }
.apdf .apdf-bleed {
    position: relative;
    width: 100vw;
    max-width: 100vw;
    left: 50%;
    margin-left: -50vw;
}
.apdf .apdf-header {
    background: #fff;
    box-shadow: 0 1px 0 rgba(49, 50, 128, .06);
}
.apdf .apdf-hero {
    display: flex;
    align-items: center;
    gap: 20px;
    max-width: 1100px;
    margin: 0 auto;
    padding: 18px 20px 16px;
}
.apdf .apdf-logo {
    display: block;
    width: auto;
    height: auto;
    max-width: 150px;
    max-height: 88px;
    object-fit: contain;
    flex-shrink: 0;
    margin: 0;
    border: 0;
    box-shadow: none;
}
.apdf .apdf-hero-text { min-width: 0; }
.apdf .apdf-kicker {
    margin: 0 0 2px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--apdf-orange);
}
.apdf .apdf-title {
    margin: 0;
    padding: 0;
    font-family: inherit;
    font-size: clamp(26px, 4.4vw, 42px);
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: .01em;
    text-transform: uppercase;
    color: var(--apdf-sky);
    border: 0;
}
.apdf .apdf-tribanda {
    display: block;
    line-height: 0;
    margin-bottom: 26px;
    overflow: hidden;
}
.apdf .apdf-tribanda img {
    display: block;
    width: 100%;
    max-width: none;
    height: auto;
    transform: rotate(180deg);
    margin: 0;
    border: 0;
}
.apdf .apdf-intro {
    margin: 0 0 18px;
    font-size: 16px;
    color: var(--apdf-muted);
    max-width: 760px;
}
.apdf .apdf-intro strong { color: var(--apdf-navy); }
.apdf .apdf-search {
    position: relative;
    margin: 0 0 14px;
}
.apdf .apdf-search svg {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--apdf-navy);
    pointer-events: none;
}
.apdf .apdf-search input[type="search"] {
    width: 100%;
    margin: 0;
    height: 54px;
    padding: 0 18px 0 48px;
    font-family: inherit;
    font-size: 16px;
    font-weight: 500;
    color: var(--apdf-ink);
    background: #fff;
    border: 2px solid var(--apdf-line);
    border-radius: 999px;
    box-shadow: 0 2px 10px rgba(49, 50, 128, .06);
    outline: none;
    -webkit-appearance: none;
    appearance: none;
}
.apdf .apdf-search input[type="search"]:focus {
    border-color: var(--apdf-sky);
    box-shadow: 0 0 0 4px rgba(114, 167, 247, .25);
}
.apdf .apdf-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 8px;
    padding: 0;
}
.apdf .apdf-nav a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 999px;
    border: 2px solid var(--apdf-navy);
    background: #fff;
    color: var(--apdf-navy);
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    text-decoration: none;
    line-height: 1.2;
    transition: background .15s, color .15s;
}
.apdf .apdf-nav a.apdf-nav-ant { border-color: #b9bdd6; color: var(--apdf-muted); }
.apdf .apdf-nav a:hover,
.apdf .apdf-nav a:focus {
    background: var(--apdf-navy);
    color: #fff;
}
.apdf .apdf-nav a span {
    font-size: 11px;
    background: var(--apdf-orange);
    color: #fff;
    border-radius: 999px;
    padding: 1px 7px;
}
.apdf .apdf-grupo {
    margin: 30px 0 0;
    scroll-margin-top: 90px;
}
.apdf .apdf-grupo[hidden], .apdf .apdf-card[hidden], .apdf .apdf-vacio[hidden] { display: none !important; }
.apdf .apdf-grupo-title {
    margin: 0 0 16px;
    padding: 0 0 10px;
    font-family: inherit;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: .03em;
    text-transform: uppercase;
    color: var(--apdf-navy);
    border: 0;
    position: relative;
}
.apdf .apdf-grupo-title::after {
    content: "";
    position: absolute;
    left: 0;
    bottom: 0;
    width: 56px;
    height: 4px;
    border-radius: 4px;
    background: var(--apdf-orange);
}
.apdf .apdf-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 14px;
}
.apdf a.apdf-card {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 16px 16px 14px;
    background: #fff;
    border: 1px solid var(--apdf-line);
    border-top: 4px solid var(--apdf-navy);
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(49, 50, 128, .06);
    color: var(--apdf-ink);
    text-decoration: none;
    transition: transform .15s, box-shadow .15s, border-color .15s;
}
.apdf a.apdf-card:hover,
.apdf a.apdf-card:focus {
    transform: translateY(-2px);
    border-top-color: var(--apdf-orange);
    box-shadow: 0 10px 22px rgba(49, 50, 128, .14);
    color: var(--apdf-ink);
}
.apdf .apdf-curso {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.15;
    color: var(--apdf-navy);
}
.apdf .apdf-promo {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--apdf-muted);
}
.apdf .apdf-tutor {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--apdf-muted);
    margin-top: 8px;
    line-height: 1.3;
}
.apdf .apdf-tutor b { display: block; color: var(--apdf-ink); font-weight: 600; }
.apdf .apdf-foto {
    position: relative;
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    background: var(--apdf-sky);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 0 2px #fff, 0 0 0 3px var(--apdf-line);
}
.apdf .apdf-foto img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    margin: 0;
    border: 0;
    border-radius: 50%;
}
.apdf .apdf-grupo-anteriores .apdf-grupo-title { color: var(--apdf-muted); }
.apdf .apdf-grupo-anteriores .apdf-grupo-title::after { background: var(--apdf-sky); }
.apdf .apdf-grupo-desc { margin: -6px 0 14px; font-size: 14px; color: var(--apdf-muted); }
.apdf .apdf-grupo-anteriores a.apdf-card { border-top-color: #b9bdd6; background: #fbfbfe; }
.apdf .apdf-grupo-anteriores .apdf-curso { color: #4b4f7a; font-size: 19px; }
.apdf .apdf-grupo-anteriores .apdf-btn { background: #fff; color: var(--apdf-navy); box-shadow: inset 0 0 0 2px var(--apdf-navy); }
.apdf .apdf-grupo-anteriores a.apdf-card:hover .apdf-btn { background: var(--apdf-navy); color: #fff; }
.apdf .apdf-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 12px;
    padding: 9px 12px;
    border-radius: 999px;
    background: var(--apdf-navy);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
    transition: background .15s;
}
.apdf a.apdf-card:hover .apdf-btn,
.apdf a.apdf-card:focus .apdf-btn { background: var(--apdf-orange); }
.apdf .apdf-vacio {
    text-align: center;
    color: var(--apdf-muted);
    padding: 30px 0 10px;
    font-weight: 600;
}
.apdf .apdf-ayuda {
    margin: 36px 0 0;
    padding: 18px 20px;
    background: var(--apdf-bg);
    border-left: 4px solid var(--apdf-sky);
    border-radius: 10px;
    font-size: 14px;
    color: var(--apdf-muted);
}
.apdf .apdf-ayuda strong { color: var(--apdf-navy); }
@media (max-width: 600px) {
    .apdf .apdf-hero { gap: 14px; padding: 10px 16px 12px; }
    .apdf .apdf-logo { max-width: 92px; max-height: 64px; }
    .apdf .apdf-kicker { font-size: 11px; }
    .apdf .apdf-tribanda { margin-bottom: 18px; }
    .apdf .apdf-intro { font-size: 15px; }
    .apdf .apdf-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .apdf a.apdf-card { padding: 12px 12px 12px; border-radius: 12px; }
    .apdf .apdf-curso { font-size: 18px; }
    .apdf .apdf-btn { font-size: 12px; padding: 8px 6px; white-space: normal; text-align: center; }
    .apdf .apdf-title { font-size: 26px; }
    .apdf .apdf-promo { font-size: 10.5px; letter-spacing: .02em; }
    .apdf .apdf-kicker { letter-spacing: .08em; }
    .apdf .apdf-btn svg { display: none; }
    .apdf .apdf-grupo-title { font-size: 18px; }
}
</style>

<div class="apdf">
    <div class="apdf-bleed apdf-header">
        <header class="apdf-hero">
            <?php if (!empty($logo_url)) : ?>
                <img class="apdf-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php esc_attr_e('Colegio Casa de la Virgen', 'area-profesores'); ?>">
            <?php endif; ?>
            <div class="apdf-hero-text">
                <p class="apdf-kicker"><?php esc_html_e('Colegio Casa de la Virgen · Google Chat', 'area-profesores'); ?></p>
                <h2 class="apdf-title"><?php echo esc_html($titulo); ?></h2>
            </div>
        </header>
    </div>

    <?php if (!empty($tribanda_url)) : ?>
        <div class="apdf-bleed apdf-tribanda" aria-hidden="true"><img src="<?php echo esc_url($tribanda_url); ?>" alt=""></div>
    <?php else : ?>
        <div style="height:22px"></div>
    <?php endif; ?>

    <?php if (empty($grupos) && empty($anteriores)) : ?>
        <p class="apdf-vacio"><?php esc_html_e('Todavía no hay espacios publicados.', 'area-profesores'); ?></p>
    <?php else : ?>
        <?php if ($intro !== '') : ?>
            <p class="apdf-intro"><?php echo wp_kses(nl2br($intro), array('strong' => array(), 'br' => array())); ?></p>
        <?php endif; ?>

        <div class="apdf-search">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.4-1.41 1.4-5.4-5.39A8 8 0 1 1 10 2Zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12Z"/></svg>
            <input type="search" id="apdf-buscar" placeholder="<?php esc_attr_e('Busca tu curso: 3º EP, 4 años, 2º ESO…', 'area-profesores'); ?>" aria-label="<?php esc_attr_e('Buscar curso', 'area-profesores'); ?>" autocomplete="off">
        </div>

        <nav class="apdf-nav" aria-label="<?php esc_attr_e('Etapas', 'area-profesores'); ?>">
            <?php foreach ($grupos as $gkey => $g) : ?>
                <a href="#apdf-<?php echo esc_attr(sanitize_title($gkey)); ?>"><?php echo esc_html($g['label']); ?> <span><?php echo (int) count($g['espacios']); ?></span></a>
            <?php endforeach; ?>
            <?php if (!empty($anteriores)) : ?>
                <a class="apdf-nav-ant" href="#apdf-anteriores"><?php esc_html_e('Anteriores', 'area-profesores'); ?></a>
            <?php endif; ?>
        </nav>

        <?php foreach ($grupos as $gkey => $g) : ?>
            <section class="apdf-grupo" id="apdf-<?php echo esc_attr(sanitize_title($gkey)); ?>">
                <h3 class="apdf-grupo-title"><?php echo esc_html($g['label']); ?></h3>
                <div class="apdf-grid">
                    <?php foreach ($g['espacios'] as $e) :
                        $tutor = ($mostrar_tutor && $e['curso'] !== '' && !empty($tutores[$e['curso']])) ? Area_Profesores_Directorio::get_tutor_card($tutores[$e['curso']]) : null;
                        $buscar = $e['titulo'] . ' ' . $e['subtitulo'] . ' ' . $g['label'] . ' ' . ($tutor ? $tutor['nombre'] : '');
                    ?>
                        <a class="apdf-card" href="<?php echo esc_url($e['chat_url']); ?>" target="_blank" rel="noopener" data-search="<?php echo esc_attr($buscar); ?>">
                            <span class="apdf-curso"><?php echo esc_html($e['titulo']); ?></span>
                            <?php if ($e['subtitulo'] !== '') : ?>
                                <span class="apdf-promo"><?php echo esc_html($e['subtitulo']); ?></span>
                            <?php endif; ?>
                            <?php if ($tutor) : ?>
                                <span class="apdf-tutor">
                                    <?php if ($mostrar_foto) : ?>
                                        <span class="apdf-foto" aria-hidden="true"><?php echo esc_html($tutor['iniciales']); ?><?php if ($tutor['foto']) : ?><img src="<?php echo esc_url($tutor['foto']); ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></span>
                                    <?php endif; ?>
                                    <span><?php esc_html_e('Tutor/a', 'area-profesores'); ?><b><?php echo esc_html($tutor['nombre']); ?></b></span>
                                </span>
                            <?php endif; ?>
                            <span class="apdf-btn"><?php echo $apdf_icono_chat; // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e('Abrir en Google Chat', 'area-profesores'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if (!empty($anteriores)) : ?>
            <section class="apdf-grupo apdf-grupo-anteriores" id="apdf-anteriores">
                <h3 class="apdf-grupo-title"><?php esc_html_e('Promociones anteriores', 'area-profesores'); ?></h3>
                <p class="apdf-grupo-desc"><?php esc_html_e('Espacios de las promociones que ya han terminado en el colegio. Se mantienen como recuerdo.', 'area-profesores'); ?></p>
                <div class="apdf-grid">
                    <?php foreach ($anteriores as $e) : ?>
                        <a class="apdf-card" href="<?php echo esc_url($e['chat_url']); ?>" target="_blank" rel="noopener" data-search="<?php echo esc_attr($e['titulo'] . ' ' . $e['subtitulo'] . ' anteriores'); ?>">
                            <span class="apdf-curso"><?php echo esc_html($e['titulo']); ?></span>
                            <?php if ($e['subtitulo'] !== '') : ?>
                                <span class="apdf-promo"><?php echo esc_html($e['subtitulo']); ?></span>
                            <?php endif; ?>
                            <span class="apdf-btn"><?php echo $apdf_icono_chat; // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e('Abrir en Google Chat', 'area-profesores'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <p class="apdf-vacio" id="apdf-vacio" hidden><?php esc_html_e('No hay ningún curso que coincida con la búsqueda.', 'area-profesores'); ?></p>

        <?php if ($mostrar_ayuda) : ?>
            <div class="apdf-ayuda">
                <?php echo wp_kses(__('<strong>¿No puedes entrar en el espacio?</strong> Comprueba que usas la cuenta de Google que diste al colegio. Si sigue sin funcionar, habla con el tutor o la tutora de tu hijo o hija.', 'area-profesores'), array('strong' => array())); ?>
            </div>
        <?php endif; ?>

        <script>
        (function () {
            var input = document.getElementById('apdf-buscar');
            if (!input) { return; }
            var norm = function (s) {
                return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[ºª]/g, '');
            };
            input.addEventListener('input', function () {
                var words = norm(input.value).split(/[^a-z0-9]+/).filter(Boolean);
                var total = 0;
                document.querySelectorAll('.apdf-grupo').forEach(function (sec) {
                    var visible = 0;
                    sec.querySelectorAll('.apdf-card').forEach(function (card) {
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
                document.getElementById('apdf-vacio').hidden = total > 0;
            });
        })();
        </script>
    <?php endif; ?>
</div>
