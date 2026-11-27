<?php
/**
 * Printable enrolment sheets, filled in with what the family wrote on the web:
 * - Ficha de matrícula (one per pupil), signed by both parents or legal guardians.
 * - Reserva de plaza (only Primer Ciclo: 1 and 2 years).
 * - Hoja de datos económicos, norma SEPA (one per pupil), or "pago por ventanilla".
 *
 * Variables: $m (request with 'datos' and 'oficina'), $logo_url
 */

if (!defined('ABSPATH')) {
    exit;
}

$M = 'Area_Profesores_Matriculas';
$d = $m['datos'];
$of = $m['oficina'];
$e = function ($v) {
    echo esc_html(($v === '' || $v === null) ? '' : $v);
};
$tutor = function ($rol) use ($d) {
    foreach ($d['tutores'] as $t) {
        if ($t['rol'] === $rol) {
            return $t;
        }
    }
    return null;
};
// Padre / madre (or the first and second guardian when they are not padre/madre).
$t1 = $tutor('padre');
$t2 = $tutor('madre');
$resto = array_values(array_filter($d['tutores'], function ($t) {
    return !in_array($t['rol'], array('padre', 'madre'), true);
}));
if (!$t1 && $resto) {
    $t1 = array_shift($resto);
}
if (!$t2 && $resto) {
    $t2 = array_shift($resto);
}
$vacio = array('nombre' => '', 'apellidos' => '', 'fecha_nacimiento' => '', 'localidad_nacimiento' => '', 'nacionalidad' => '', 'documento_tipo' => '', 'documento' => '', 'estudios' => '', 'profesion' => '', 'movil' => '', 'email' => '', 'rol' => '');
$t1 = $t1 ? $t1 : $vacio;
$t2 = $t2 ? $t2 : $vacio;
$dom = $d['domicilio'];
$cuentas = isset($of['cuentas']) ? implode(' · ', (array) $of['cuentas']) : '';
$sepa = $d['pago']['modo'] === 'domiciliacion';
$iban = $sepa ? $d['pago']['iban'] : '';
$ce = explode('-', str_replace('/', '-', (string) $d['curso_escolar']));
$cabecera = function () use ($logo_url) {
    ?>
    <div class="hd">
        <?php if ($logo_url) : ?><img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="Colegio Casa de la Virgen"><?php endif; ?>
        <div class="hd-dir">COLEGIO CASA DE LA VIRGEN<br>C/ Virgen del Val, 1 · 28027 MADRID<br>Tel. 914041786/88 · 608662980</div>
    </div>
    <?php
};
$campo = function ($label, $valor, $clase = '') {
    echo '<div class="f ' . esc_attr($clase) . '"><span class="l">' . esc_html($label) . '</span><span class="v">' . esc_html((string) $valor) . '</span></div>';
};
$datos_tutor = function ($titulo, $t) use ($campo, $M) {
    ?>
    <div class="box">
        <h3><?php echo esc_html($titulo); ?></h3>
        <?php
        $campo('APELLIDOS:', $t['apellidos']);
        $campo('NOMBRE:', $t['nombre']);
        $campo('FECHA DE NACIMIENTO:', $M::f($t['fecha_nacimiento']));
        $campo('LOCALIDAD DE NACIMIENTO:', $t['localidad_nacimiento']);
        $campo('NACIONALIDAD:', $t['nacionalidad']);
        $campo('NIF, NIE O Nº DE PASAPORTE:', $t['documento']);
        $campo('ESTUDIOS REALIZADOS:', $t['estudios']);
        $campo('PROFESIÓN ACTUAL:', $t['profesion']);
        $campo('TELÉFONO MÓVIL:', $t['movil']);
        $campo('CORREO PERSONAL:', $t['email']);
        ?>
    </div>
    <?php
};
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html($m['referencia'] . ' · Matrícula ' . $m['familia']); ?></title>
<style>
    @page { size: A4; margin: 12mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e5e7eb; color: #1f2240; font-family: "Segoe UI", Roboto, Arial, sans-serif; font-size: 10.5pt; }
    .barra { position: sticky; top: 0; display: flex; gap: 12px; align-items: center; justify-content: center; padding: 10px; background: #313280; color: #fff; font-size: 14px; z-index: 2; }
    .barra button { padding: 8px 18px; border: 0; border-radius: 999px; background: #dc8e2b; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
    .hoja { width: 210mm; min-height: 297mm; margin: 14px auto; padding: 12mm; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.15); page-break-after: always; break-after: page; }
    .hoja:last-child { page-break-after: auto; break-after: auto; }
    .hd { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 8px; }
    .logo { max-height: 60px; max-width: 170px; }
    .hd-dir { font-size: 8.5pt; color: #475569; text-align: right; line-height: 1.35; }
    h1 { font-size: 12.5pt; margin: 4px 0 8px; color: #dc8e2b; text-align: center; letter-spacing: .02em; }
    h2 { font-size: 10pt; margin: 0 0 6px; color: #313280; }
    h3 { font-size: 10pt; margin: 0 0 6px; color: #313280; text-align: center; }
    .box { border: 1.5px solid #dc8e2b; border-radius: 10px; padding: 7px 10px; margin: 0 0 8px; }
    .cols { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .cols .box { margin: 0; }
    .row { display: flex; flex-wrap: wrap; gap: 0 14px; }
    .row .f { flex: 1 1 0; min-width: 0; }
    .f { display: flex; align-items: baseline; gap: 6px; padding: 2.5px 0; border-bottom: 1px solid #cbd5e1; min-height: 19px; }
    .f .l { font-weight: 700; font-size: 8pt; color: #313280; white-space: nowrap; }
    .f .v { flex: 1; font-size: 10pt; overflow-wrap: anywhere; }
    .nota { font-size: 8.5pt; color: #475569; margin: 4px 0 0; }
    .firmas { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 10px; }
    .firma { border: 1px dashed #94a3b8; border-radius: 8px; height: 30mm; padding: 6px 8px; font-size: 8pt; font-weight: 700; color: #313280; }
    .firma small { display: block; font-weight: 400; color: #475569; }
    .tabla { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    .tabla td, .tabla th { border-bottom: 1px solid #cbd5e1; padding: 3px 4px; text-align: left; }
    .tabla th { font-size: 8pt; color: #313280; }
    .iban { display: flex; flex-wrap: nowrap; gap: 1mm; margin: 6px 0; }
    .iban span { display: inline-flex; flex: 0 0 6mm; width: 6mm; height: 8mm; align-items: center; justify-content: center; border: 1.5px solid #313280; border-radius: 3px; font-weight: 700; font-size: 10.5pt; background: #fdf6ec; }
    .iban span.sep { margin-left: 1.5mm; }
    .imp { font-size: 9pt; text-align: justify; line-height: 1.4; }
    .check { display: inline-block; width: 4.5mm; height: 4.5mm; border: 1.5px solid #313280; vertical-align: middle; margin-right: 4px; text-align: center; line-height: 4mm; font-weight: 700; }
    .pie { margin-top: 8px; font-size: 7.5pt; color: #64748b; }
    .legal { font-size: 9pt; line-height: 1.38; text-align: justify; }
    .legal h4 { font-size: 9.5pt; color: #313280; margin: 7px 0 2px; }
    .legal p { margin: 0 0 4px; }
    .legal ul { margin: 0 0 4px; padding-left: 10px; list-style: none; }
    .legal li { margin: 0 0 2px; }
    .legal.peq { font-size: 8.2pt; line-height: 1.3; }
    .manif { display: flex; gap: 8px; align-items: flex-start; margin: 0 0 6px; }
    .manif .n { font-weight: 700; color: #313280; min-width: 22px; }
    .manif .t { flex: 1; }
    .resp { display: inline-block; margin-top: 3px; padding: 1px 8px; border: 1.5px solid #313280; border-radius: 4px; font-weight: 700; font-size: 8.5pt; }
    .resp.no { border-color: #b91c1c; color: #b91c1c; }
    .cons { margin: 4px 0 6px; font-weight: 700; }
    .cons span { margin-right: 22px; }
    .firmas3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 8px; }
    .lineas { border-bottom: 1px solid #94a3b8; min-height: 16px; margin: 2px 0 6px; font-size: 9.5pt; }
    @media print {
        body { background: #fff; }
        .barra { display: none; }
        .hoja { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; }
        body { font-size: 10pt; }
        .hd { margin-bottom: 4px; }
        .logo { max-height: 48px; }
        h1 { margin: 2px 0 5px; }
        .box { padding: 5px 9px; margin-bottom: 6px; }
        .f { min-height: 17px; padding: 1.5px 0; }
        .f .v { font-size: 9.5pt; }
        .firma { height: 22mm; }
        .legal.peq { font-size: 7.9pt; line-height: 1.26; }
    }
</style>
</head>
<body>
<div class="barra">
    <span><?php echo esc_html($m['referencia'] . ' · ' . $m['familia']); ?></span>
    <button type="button" onclick="window.print()">Imprimir</button>
</div>

<?php foreach ($d['alumnos'] as $i => $a) :
    $x = isset($of['alumnos'][$i]) ? $of['alumnos'][$i] : array();
    $curso_txt = Area_Profesores_Cursos::get_nivel_label($a['nivel']) . (!empty($x['grupo']) ? ' ' . $x['grupo'] : '');
    ?>
    <!-- FICHA DE MATRÍCULA -->
    <section class="hoja">
        <?php $cabecera(); ?>
        <h1>FICHA DE MATRÍCULA</h1>
        <div class="box">
            <p class="nota" style="margin:0 0 4px;"><strong>CONCERTADO:</strong> INFANTIL 3-5 AÑOS / PRIMARIA / SECUNDARIA OBLIGATORIA · <strong>PRIVADO:</strong> INFANTIL 1-2 AÑOS / BACHILLERATO</p>
            <div class="row">
                <?php $campo('CURSO ESCOLAR:', $d['curso_escolar']); ?>
                <?php $campo('Nº DE MATRÍCULA:', isset($x['numero_matricula']) ? $x['numero_matricula'] : ''); ?>
            </div>
            <div class="row">
                <?php $campo('FECHA DE INGRESO:', isset($x['fecha_ingreso']) ? $M::f($x['fecha_ingreso']) : ''); ?>
                <?php $campo('CURSO:', $curso_txt); ?>
                <?php $campo('NIA:', isset($x['nia']) ? $x['nia'] : ''); ?>
            </div>
            <p class="nota"><strong>Nota importante:</strong> El colegio Casa de la Virgen es un centro de inspiración cristiana y católica. Se requiere, por tanto, por parte de los padres y alumnos, la aceptación voluntaria de su Ideario y del Reglamento de Régimen Interior.</p>
        </div>

        <div class="box">
            <h3>DATOS PERSONALES DEL ALUMNO</h3>
            <?php $campo('APELLIDOS:', trim($a['apellido1'] . ' ' . $a['apellido2'])); ?>
            <?php $campo('NOMBRE:', $a['nombre']); ?>
            <div class="row">
                <?php $campo('NACIMIENTO · FECHA:', $M::f($a['fecha_nacimiento'])); ?>
                <?php $campo('SEXO:', $a['sexo'] === 'H' ? 'HOMBRE' : ($a['sexo'] === 'M' ? 'MUJER' : '')); ?>
            </div>
            <div class="row">
                <?php $campo('LOCALIDAD:', $a['localidad_nacimiento']); ?>
                <?php $campo('PROVINCIA:', $a['provincia_nacimiento']); ?>
                <?php $campo('NACIONALIDAD:', $a['nacionalidad']); ?>
            </div>
            <?php $campo('CENTRO EDUCATIVO DE PROCEDENCIA:', $a['centro_procedencia']); ?>
        </div>

        <div class="cols">
            <?php $datos_tutor($t1['rol'] === 'padre' || $t1['rol'] === '' ? 'DATOS DEL PADRE' : 'DATOS DEL TUTOR/A LEGAL', $t1); ?>
            <?php $datos_tutor($t2['rol'] === 'madre' || $t2['rol'] === '' ? 'DATOS DE LA MADRE' : 'DATOS DEL TUTOR/A LEGAL', $t2); ?>
        </div>

        <div class="box" style="margin-top:8px;">
            <h3>HERMANOS EN EL CENTRO</h3>
            <?php if ($d['hermanos']) : ?>
                <table class="tabla"><tr><th>CURSO</th><th>APELLIDOS Y NOMBRE</th></tr>
                    <?php foreach ($d['hermanos'] as $h) : ?><tr><td><?php $e($h['curso']); ?></td><td><?php $e($h['nombre']); ?></td></tr><?php endforeach; ?>
                </table>
            <?php else : ?>
                <?php $campo('CURSO / APELLIDOS Y NOMBRE:', count($d['alumnos']) > 1 ? 'Se matriculan a la vez: ' . implode(', ', array_map(array($M, 'nombre_alumno'), $d['alumnos'])) : 'Ninguno'); ?>
            <?php endif; ?>
        </div>

        <div class="box">
            <h3>RESIDENCIA HABITUAL</h3>
            <div class="row">
                <?php $campo('TITULAR DE LA CORRESPONDENCIA:', $dom['titular']); ?>
                <?php $campo('TFNO.:', $dom['telefono']); ?>
            </div>
            <div class="row">
                <?php $campo('CALLE:', $dom['calle']); ?>
                <?php $campo('Nº:', $dom['numero']); ?>
                <?php $campo('PISO:', $dom['piso']); ?>
                <?php $campo('PUERTA:', $dom['puerta']); ?>
            </div>
            <div class="row">
                <?php $campo('C.P.:', $dom['cp']); ?>
                <?php $campo('LOCALIDAD:', $dom['localidad']); ?>
                <?php $campo('PROVINCIA:', $dom['provincia']); ?>
            </div>
            <?php $campo('NÚMERO TOTAL DE PERSONAS QUE CONVIVEN EN LA CASA:', $d['convivientes'] ? $d['convivientes'] : ''); ?>
            <?php $campo('CORREO ELECTRÓNICO INSTITUCIONAL:', $cuentas); ?>
            <?php $campo('CORREO DE CONTACTO:', $d['email_contacto']); ?>
        </div>

        <div class="box">
            <h3>DATOS BANCARIOS</h3>
            <?php if ($sepa) : ?>
                <?php $campo('TITULAR:', $d['pago']['pagador']['nombre']); ?>
                <?php $campo('IBAN:', $M::iban_formato($iban)); ?>
            <?php else : ?>
                <p style="margin:2px 0;"><span class="check">✓</span> Pago por ventanilla</p>
            <?php endif; ?>
        </div>

        <h2 style="text-align:center;text-decoration:underline;margin-top:6px;">FIRMAS DE LOS DOS PADRES O TUTORES LEGALES</h2>
        <div class="firmas">
            <div class="firma"><?php echo esc_html(trim($t1['nombre'] . ' ' . $t1['apellidos'])); ?><small><?php echo esc_html($t1['documento']); ?></small></div>
            <div class="firma"><?php echo esc_html(trim($t2['nombre'] . ' ' . $t2['apellidos'])); ?><small><?php echo esc_html($t2['documento']); ?></small></div>
        </div>
        <p class="pie">Datos enviados por la familia desde el Área de Familias el <?php echo esc_html(date_i18n('j/m/Y H:i', strtotime($m['creado']))); ?> (ref. <?php echo esc_html($m['referencia']); ?>). Ideario y RRI aceptados en la web el <?php echo esc_html($d['aceptaciones']['ideario']); ?>.</p>
    </section>

    <?php if (!empty($d['textos']['info_centro']) || !empty($d['textos']['manifestaciones'])) :
        $mresp = isset($d['aceptaciones']['manifestaciones']) ? $d['aceptaciones']['manifestaciones'] : array(); ?>
    <!-- INFORMACIÓN DEL CENTRO A LA FAMILIA (2 páginas) -->
    <section class="hoja">
        <?php $cabecera(); ?>
        <h1>INFORMACIÓN DEL CENTRO A LA FAMILIA</h1>
        <div class="box legal"><?php echo $M::texto_html($d['textos']['info_centro']); // phpcs:ignore -- escaped inside ?></div>
    </section>
    <section class="hoja">
        <?php $cabecera(); ?>
        <div class="box legal">
            <h2>MANIFESTACIONES DE LOS PADRES/TUTORES:</h2>
            <p><strong>Alumno/a:</strong> <?php $e($M::nombre_alumno($a) . ' · ' . $curso_txt . ' · ' . $d['curso_escolar']); ?></p>
            <?php foreach ($d['textos']['manifestaciones'] as $mk => $mf) : $r = isset($mresp[$mk]) ? $mresp[$mk] : ''; ?>
                <div class="manif">
                    <span class="n"><?php $e($mf['n']); ?>.</span>
                    <div class="t">
                        <?php echo $M::texto_html($mf['texto']); // phpcs:ignore ?>
                        <?php if ($mf['tipo'] === 'eleccion' && $r !== '') : ?>
                            <span class="resp <?php echo $r === 'si' ? '' : 'no'; ?>"><?php echo $r === 'si' ? '✓ ' : '✗ '; ?><?php $e($r === 'si' ? $mf['si'] : $mf['no']); ?></span>
                        <?php elseif ($mf['tipo'] === 'texto') : ?>
                            <div class="lineas"><?php echo nl2br(esc_html($r)); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <p style="margin-top:10px;">En Madrid a ______ de ____________________ de 20____</p>
            <div class="firmas3">
                <div class="firma">Firma:<small>Padre/Tutor: <?php $e(trim($t1['nombre'] . ' ' . $t1['apellidos'])); ?></small></div>
                <div class="firma">Firma:<small>Madre/tutora: <?php $e(trim($t2['nombre'] . ' ' . $t2['apellidos'])); ?></small></div>
                <div class="firma">Firma:<small>Representante del Centro</small></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($d['textos']['proteccion'])) :
        $rgpd = isset($d['aceptaciones']['rgpd']) ? $d['aceptaciones']['rgpd'] : array();
        $cons = isset($d['textos']['consentimientos']) ? $d['textos']['consentimientos'] : array(); ?>
    <!-- PROTECCIÓN DE DATOS (Fundación) -->
    <section class="hoja">
        <div class="hd">
            <?php if ($logo_url) : ?><img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="Colegio Casa de la Virgen"><?php endif; ?>
            <div class="hd-dir" style="font-size:11pt;font-weight:700;color:#1f2240;">FUNDACIÓN CASA DE LA VIRGEN</div>
        </div>
        <div class="legal peq">
            <?php
            echo $M::texto_html($d['textos']['proteccion'], function ($marca) use ($cons, $rgpd) { // phpcs:ignore
                if (!isset($cons[$marca])) {
                    return '';
                }
                $r = isset($rgpd[$cons[$marca]['campo']]) ? $rgpd[$cons[$marca]['campo']] : '';
                return '<p class="cons"><span><span class="check">' . ($r === 'consiento' ? '✓' : '') . '</span> Consiento</span><span><span class="check">' . ($r === 'no' ? '✓' : '') . '</span> No consiento</span></p>';
            });
            ?>
        </div>
        <div class="box" style="margin-top:8px;">
            <?php $campo('ALUMNO/A:', $M::nombre_alumno($a) . ' · ' . $curso_txt); ?>
            <div class="firmas">
                <div class="firma">NOMBRE Y APELLIDOS: <?php $e(trim($t1['nombre'] . ' ' . $t1['apellidos'])); ?><small>FECHA: ____________ · FIRMA:</small></div>
                <div class="firma">NOMBRE Y APELLIDOS: <?php $e(trim($t2['nombre'] . ' ' . $t2['apellidos'])); ?><small>FECHA: ____________ · FIRMA:</small></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (in_array($a['nivel'], array('PC1', 'PC2'), true)) : ?>
    <!-- RESERVA DE PLAZA (PRIMER CICLO) -->
    <section class="hoja">
        <?php $cabecera(); ?>
        <h1>RESERVA DE PLAZA · 1<sup>er</sup> CICLO DE EDUCACIÓN INFANTIL</h1>
        <div class="box">
            <h3>DATOS DEL ALUMNO</h3>
            <?php $campo('NOMBRE:', $a['nombre']); ?>
            <div class="row">
                <?php $campo('PRIMER APELLIDO:', $a['apellido1']); ?>
                <?php $campo('SEGUNDO APELLIDO:', $a['apellido2']); ?>
            </div>
            <div class="row">
                <?php $campo('FECHA DE NACIMIENTO:', $M::f($a['fecha_nacimiento'])); ?>
                <?php $campo('PROVINCIA:', $a['provincia_nacimiento']); ?>
                <?php $campo('LOCALIDAD:', $a['localidad_nacimiento']); ?>
            </div>
            <?php $campo('CURSO:', $curso_txt . ' · ' . $d['curso_escolar']); ?>
        </div>
        <div class="box">
            <h3>DATOS DE LOS PADRES O TUTORES</h3>
            <?php $campo('NOMBRE DE LA MADRE:', $t2['nombre']); ?>
            <?php $campo('APELLIDOS:', $t2['apellidos']); ?>
            <?php $campo('NOMBRE DEL PADRE:', $t1['nombre']); ?>
            <?php $campo('APELLIDOS:', $t1['apellidos']); ?>
            <?php $campo('TELÉFONO DE CONTACTO:', implode(' · ', array_filter(array($t1['movil'], $t2['movil'])))); ?>
            <div class="firmas">
                <div class="firma">FIRMA DE LA MADRE/TUTORA</div>
                <div class="firma">FIRMA DEL PADRE/TUTOR</div>
            </div>
        </div>
        <div class="box">
            <h3 style="text-align:left;">Documentación a presentar</h3>
            <p><span class="check"><?php echo !empty($of['docs']['libro_familia']) ? '✓' : ''; ?></span> Libro de familia / partida de nacimiento.</p>
            <p><span class="check"><?php echo !empty($of['docs']['dni']) ? '✓' : ''; ?></span> DNI/NIE/Pasaporte de los padres o tutores.</p>
            <p><span class="check"><?php echo !empty($of['docs']['pago']) ? '✓' : ''; ?></span> Importe de la matrícula.</p>
        </div>
    </section>
    <?php endif; ?>

    <!-- HOJA DE DATOS ECONÓMICOS NORMA SEPA -->
    <section class="hoja">
        <?php $cabecera(); ?>
        <h1>HOJA DE DATOS ECONÓMICOS NORMA SEPA</h1>
        <p class="imp"><strong>Importante:</strong> Se deberá cumplimentar una hoja por cada alumno. Mediante la firma de esta orden de domiciliación, el pagador autoriza al Colegio Casa de la Virgen a enviar instrucciones a la entidad del pagador para adeudar su cuenta y a la entidad para efectuar los adeudos en su cuenta siguiendo las instrucciones del Colegio Casa de la Virgen. Puede obtener información adicional en su entidad financiera.</p>
        <div class="box">
            <h2>Datos del Alumno/a</h2>
            <div class="row">
                <?php $campo('Nombre:', $a['nombre']); ?>
                <?php $campo('Primer apellido:', $a['apellido1']); ?>
                <?php $campo('Segundo apellido:', $a['apellido2']); ?>
            </div>
            <?php $campo('Curso escolar:', $curso_txt . ' · ' . $d['curso_escolar']); ?>
        </div>
        <div class="box">
            <?php if ($sepa) : $pg = $d['pago']['pagador']; ?>
                <div class="row">
                    <?php $campo('Nombre del pagador:', $pg['nombre']); ?>
                    <?php $campo('DNI/NIE:', $pg['documento']); ?>
                    <?php $campo('Teléfono:', $pg['telefono']); ?>
                </div>
                <div class="row">
                    <?php $campo('Dirección:', $pg['direccion']); ?>
                    <?php $campo('Código Postal:', $pg['cp']); ?>
                </div>
                <div class="row">
                    <?php $campo('Localidad:', $pg['localidad']); ?>
                    <?php $campo('Provincia:', $pg['provincia']); ?>
                </div>
                <p style="margin:8px 0 0;font-weight:700;color:#313280;font-size:9pt;">Cuenta corriente (IBAN), imprescindible cumplimentar los 24 dígitos según normativa SEPA</p>
                <div class="iban">
                    <?php foreach (str_split($iban) as $k => $ch) : ?><span class="<?php echo ($k > 0 && $k % 4 === 0) ? 'sep' : ''; ?>"><?php echo esc_html($ch); ?></span><?php endforeach; ?>
                </div>
                <p><span class="check"></span> Pago por ventanilla</p>
            <?php else : ?>
                <p style="font-size:12pt;"><span class="check">✓</span> <strong>Pago por ventanilla</strong></p>
            <?php endif; ?>
            <div class="firmas">
                <div class="firma">Firmado pagador:</div>
                <div class="firma">Fecha: Madrid, ______ de ____________________ de 20____</div>
            </div>
            <?php $campo('Fecha del procesamiento:', ''); ?>
        </div>
    </section>
<?php endforeach; ?>
</body>
</html>
