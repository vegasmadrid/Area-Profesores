<?php
/**
 * Printable credentials sheet for a new family: the account of the school, the
 * temporary password (only accounts created here, until they are delivered) and
 * how to enter the Área de Familias and Google Chat, with a QR code.
 *
 * Variables: $m (request), $cuentas (Area_Profesores_Matriculas::estado_cuentas()),
 *            $claves (email => temporary password or ''), $logo_url, $familias_url
 */

if (!defined('ABSPATH')) {
    exit;
}

$M = 'Area_Profesores_Matriculas';
$d = $m['datos'];
$alumnos = implode(', ', array_map(array($M, 'nombre_alumno'), $d['alumnos']));
$tutores = $d['tutores'];
$separar = count($cuentas) > 1 && !empty($d['separados']);
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html($m['referencia'] . ' · Credenciales ' . $m['familia']); ?></title>
<style>
    @page { size: A4; margin: 14mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e5e7eb; color: #1f2240; font-family: "Segoe UI", Roboto, Arial, sans-serif; font-size: 11pt; }
    .barra { position: sticky; top: 0; display: flex; gap: 12px; align-items: center; justify-content: center; padding: 10px; background: #313280; color: #fff; font-size: 14px; z-index: 2; }
    .barra button { padding: 8px 18px; border: 0; border-radius: 999px; background: #dc8e2b; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
    .hoja { width: 210mm; min-height: 297mm; margin: 14px auto; padding: 14mm; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.15); page-break-after: always; break-after: page; }
    .hoja:last-child { page-break-after: auto; break-after: auto; }
    .hd { display: flex; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 3px solid #dc8e2b; padding-bottom: 8px; margin-bottom: 14px; }
    .logo { max-height: 64px; max-width: 180px; }
    .hd-dir { font-size: 8.5pt; color: #475569; text-align: right; line-height: 1.35; }
    h1 { font-size: 20pt; color: #313280; margin: 0 0 4px; }
    .sub { color: #475569; margin: 0 0 16px; }
    .cred { border: 2px solid #313280; border-radius: 14px; padding: 14px 18px; margin: 0 0 16px; display: flex; gap: 18px; align-items: center; }
    .cred .datos { flex: 1; min-width: 0; }
    .cred .l { font-size: 9pt; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .06em; margin: 8px 0 2px; }
    .cred .v { font-family: "Consolas", "Courier New", monospace; font-size: 15pt; font-weight: 700; color: #1f2240; overflow-wrap: anywhere; }
    .cred .v.clave { letter-spacing: .04em; background: #fdf6ec; display: inline-block; padding: 2px 8px; border-radius: 6px; }
    .cred .nota { font-size: 9.5pt; color: #475569; margin: 8px 0 0; }
    .qr { width: 34mm; text-align: center; font-size: 8pt; color: #475569; }
    .qr canvas, .qr img { width: 30mm !important; height: 30mm !important; }
    h2 { font-size: 13pt; color: #313280; margin: 16px 0 6px; }
    ol { margin: 0 0 8px; padding-left: 20px; line-height: 1.5; }
    ol li { margin: 0 0 6px; }
    .aviso { background: #eef3fe; border-radius: 10px; padding: 10px 14px; font-size: 10pt; margin-top: 12px; }
    .pie { margin-top: 18px; font-size: 8.5pt; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 6px; }
    @media print {
        body { background: #fff; }
        .barra { display: none; }
        .hoja { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; }
    }
</style>
</head>
<body>
<div class="barra">
    <span><?php echo esc_html($m['referencia'] . ' · ' . $m['familia']); ?></span>
    <button type="button" onclick="window.print()">Imprimir</button>
</div>

<?php $i = 0; foreach ($cuentas as $email => $c) : $clave = isset($claves[$email]) ? $claves[$email] : ''; $t = $separar && isset($tutores[$i]) ? $tutores[$i] : null; $i++; ?>
<section class="hoja">
    <div class="hd">
        <?php if ($logo_url) : ?><img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="Colegio Casa de la Virgen"><?php endif; ?>
        <div class="hd-dir">COLEGIO CASA DE LA VIRGEN<br>C/ Virgen del Val, 1 · 28027 MADRID<br>Tel. 914041786/88 · 608662980</div>
    </div>

    <h1>¡Bienvenidos al Colegio Casa de la Virgen!</h1>
    <p class="sub">
        <?php echo esc_html(sprintf('Familia %s · %s', $m['familia'], $alumnos)); ?>
        <?php if ($t) : ?><br><?php echo esc_html(sprintf('Cuenta de %s %s', $t['nombre'], $t['apellidos'])); ?><?php endif; ?>
    </p>

    <div class="cred">
        <div class="datos">
            <div class="l">Vuestra cuenta del colegio</div>
            <div class="v"><?php echo esc_html($email); ?></div>
            <?php if ($c['estado'] === 'creada' && $clave !== '') : ?>
                <div class="l">Contraseña temporal</div>
                <div class="v clave"><?php echo esc_html($clave); ?></div>
                <p class="nota">La primera vez que entréis, Google os pedirá que la cambiéis por una vuestra. Guardad esta hoja en un lugar seguro hasta entonces.</p>
            <?php elseif ($c['estado'] === 'creada') : ?>
                <p class="nota"><strong>La contraseña ya se entregó.</strong> Si la habéis perdido, pedid en Secretaría una nueva.</p>
            <?php else : ?>
                <p class="nota">Es la cuenta que ya usáis para vuestros otros hijos en el colegio: entrad con vuestra contraseña de siempre.</p>
            <?php endif; ?>
        </div>
        <?php if ($familias_url !== '') : ?>
            <div class="qr"><div class="qr-code" data-url="<?php echo esc_attr($familias_url); ?>"></div>Área de Familias</div>
        <?php endif; ?>
    </div>

    <h2>Cómo entrar en el Área de Familias</h2>
    <ol>
        <li>Abrid <strong><?php echo esc_html($familias_url !== '' ? preg_replace('#^https?://#', '', untrailingslashit($familias_url)) : 'la web del Área de Familias'); ?></strong> (o escanead el código QR con el móvil).</li>
        <li>Pulsad <strong>«Accede con tu cuenta del colegio»</strong> y escribid la cuenta y la contraseña de esta hoja.</li>
        <?php if ($c['estado'] === 'creada') : ?><li>Google os pedirá que pongáis <strong>una contraseña nueva</strong> (al menos 8 caracteres). Apuntadla bien: es la que usaréis a partir de ahora.</li><?php endif; ?>
        <li>Allí veréis a vuestros hijos, su tutor/a y su espacio de aula, podréis actualizar vuestros datos de contacto y reservar servicios (comedor, ampliación de horario…).</li>
    </ol>

    <h2>Google Chat: los avisos de la clase</h2>
    <ol>
        <li>Instalad en el móvil la aplicación <strong>Google Chat</strong> (App Store o Google Play) y entrad con la misma cuenta.</li>
        <li>Cuando vuestro hijo/a empiece en el colegio os añadiremos al <strong>espacio de su clase</strong>: allí el tutor/a y el colegio publican los avisos y comunicados.</li>
        <li>Activad las notificaciones de la aplicación para no perderos nada.</li>
    </ol>

    <div class="aviso">Esta cuenta es personal de la familia: no la compartáis fuera de casa. Si tenéis cualquier problema para entrar, escribid o llamad a Secretaría.</div>
    <p class="pie">Ref. <?php echo esc_html($m['referencia']); ?> · Hoja entregada el ____ / ____ / ________ · Recibí (firma): ______________________________</p>
</section>
<?php endforeach; ?>

<?php if ($familias_url !== '') : ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll('.qr-code').forEach(function (el) {
    if (window.QRCode) {
        new QRCode(el, { text: el.getAttribute('data-url'), width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M });
    }
});
</script>
<?php endif; ?>
</body>
</html>
