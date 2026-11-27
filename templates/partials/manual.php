<?php
/**
 * Manual of the Área de Profesores (used by the "Ayuda" tab and the printable manual).
 *
 * Variables:
 *   $mv  array  section key => bool (which sections to show)
 *   $maj array  dining-room settings (Area_Profesores_Comensales::ajustes())
 */

if (!defined('ABSPATH')) {
    exit;
}

$mv = array_merge(array(
    'entrar' => true, 'comedor' => true, 'mi_comida' => true, 'alumnos' => true, 'reservas' => true,
    'enlaces' => true, 'espacios' => true, 'comensales' => true, 'matriculas' => true, 'comunicados' => true, 'dudas' => true,
), isset($mv) ? (array) $mv : array());
$maj = isset($maj) ? $maj : Area_Profesores_Comensales::ajustes();
$m_rec = $maj['hora_recordatorio'];
$m_coc = $maj['hora_cocina'];
// Screenshot with its caption (assets/img/ayuda/).
$m_fig = function ($img, $cap) {
    $url = AREA_PROFESORES_PLUGIN_URL . 'assets/img/ayuda/' . $img . '.png?ver=' . AREA_PROFESORES_VERSION;
    echo '<figure class="ap-manual-fig"><a href="' . esc_url($url) . '" target="_blank" rel="noopener"><img src="' . esc_url($url) . '" alt="' . esc_attr($cap) . '" loading="lazy"></a><figcaption>' . esc_html($cap) . '</figcaption></figure>';
};
$m_secciones = array(
    'entrar'      => __('Entrar en el Área', 'area-profesores'),
    'comedor'     => __('Comedor de hoy (tutores)', 'area-profesores'),
    'mi_comida'   => __('Mi comida en el colegio', 'area-profesores'),
    'alumnos'     => __('Mis alumnos', 'area-profesores'),
    'reservas'    => __('Reservas de las familias', 'area-profesores'),
    'enlaces'     => __('Enlaces útiles', 'area-profesores'),
    'espacios'    => __('Espacios de aula', 'area-profesores'),
    'comensales'  => __('Administración (cocina y cobros del mes)', 'area-profesores'),
    'matriculas'  => __('Nuevas familias (solicitudes de matrícula)', 'area-profesores'),
    'comunicados' => __('Comunicados (Dirección y Comunicación)', 'area-profesores'),
    'dudas'       => __('Preguntas frecuentes', 'area-profesores'),
);
?>
<nav class="ap-manual-indice" aria-label="<?php esc_attr_e('Índice del manual', 'area-profesores'); ?>">
    <?php $m_i = 0; foreach ($m_secciones as $k => $t) : if (empty($mv[$k])) { continue; } $m_i++; ?>
        <a href="#ayuda-<?php echo esc_attr($k); ?>"><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($t); ?></a>
    <?php endforeach; ?>
</nav>

<?php $m_i = 0; ?>

<?php if ($mv['entrar']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-entrar">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['entrar']); ?></h3>
    <ul>
        <li><?php esc_html_e('Entra con tu cuenta de Google del colegio pulsando "Acceso Docente". No sirven las cuentas personales (Gmail, Hotmail…).', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Si es tu primer curso en el colegio, pulsa "Solicita aquí tu alta como profesor" y rellena tus datos. Dirección activa tu cuenta y te llega un correo cuando ya puedes entrar.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Arriba ves tu nombre, tu tutoría o tus clases y la fecha. Si Dirección ha dejado un aviso para el claustro, aparece debajo, en un recuadro.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Debajo están las pestañas. Cada profesor ve solo las que necesita: por ejemplo, "Comedor" solo aparece a los profesores de Primer Ciclo, Infantil y Primaria.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Para salir, pulsa "Salir" (arriba a la derecha). Hazlo siempre si usas un ordenador compartido.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('El Área funciona igual en el móvil: las pestañas se pueden deslizar a los lados.', 'area-profesores'); ?></li>
    </ul>
    <?php $m_fig('entrar', __('Arriba, tu nombre y tu clase; debajo, las pestañas que puedes usar.', 'area-profesores')); ?>
</section>
<?php endif; ?>

<?php if ($mv['comedor']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-comedor">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['comedor']); ?></h3>
    <p><?php esc_html_e('Cada día lectivo, el tutor (o quien esté con la clase) comprueba quién se queda a comer y lo valida. Así cocina sabe cuántos comensales hay, quién tiene alergias y qué dietas preparar.', 'area-profesores'); ?></p>
    <h4><?php esc_html_e('Validar el comedor de tu clase', 'area-profesores'); ?></h4>
    <ol>
        <li><?php esc_html_e('Abre la pestaña "Comedor". Sale tu clase con la lista de alumnos. Los que se quedan siempre ese día de la semana (comensales fijos) ya aparecen marcados.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Marca a quien se queda hoy sin ser fijo (aparece la etiqueta "Extra hoy") y desmarca a quien hoy no se queda ("Hoy no se queda"). Al validar, cada extra queda registrado como un servicio de comedor de ese día: se avisa a su familia y a Administración, y te llega un correo de confirmación.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Los alumnos con reserva de la familia (👪) ya salen marcados: la familia lo ha pedido desde el Área de Familias.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Para pedir una dieta, pulsa "🥗 Dieta" junto al alumno y rellena el formulario (tipo de dieta, instrucciones, motivo; un día o varios). Solo se puede los días que el alumno se queda a comer. Se avisa a la familia por correo y a cocina, y aparece en el Área de Familias.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Si hace falta, escribe otras observaciones para cocina.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Pulsa "Validar el comedor". Arriba aparece en verde quién lo ha validado, a qué hora y cuántos comensales hay.', 'area-profesores'); ?></li>
    </ol>
    <?php $m_fig('comedor-lista', __('El comedor de la clase: los fijos ya salen marcados (verde), un extra de hoy (naranja), un fijo que hoy no se queda (tachado), las alergias en rojo y una reserva de la familia con dieta.', 'area-profesores')); ?>
    <?php $m_fig('comedor-validar', __('Observaciones para cocina (debajo está el botón "Validar el comedor").', 'area-profesores')); ?>
    <div class="ap-manual-nota">
        <?php printf(esc_html__('A las %1$s se envía un recordatorio a los tutores que no lo hayan validado y a las %2$s se manda el resumen a cocina. Si cambias algo después de esa hora, vuelve a validar: cocina recibe un aviso con el cambio.', 'area-profesores'), esc_html($m_rec), esc_html($m_coc)); ?>
    </div>
    <h4><?php esc_html_e('Alergias alimentarias', 'area-profesores'); ?></h4>
    <ul>
        <li><?php esc_html_e('Los alumnos con alergia alimentaria tienen una etiqueta roja ⚠️ con su alergia (sale de su ficha).', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Si un comensal fijo con alergia no se queda, no tienes que hacer nada más: al validar, cocina recibe un aviso especial ("HOY NO COMEN") para no preparar su menú.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Si una alergia no es correcta, cámbiala desde "Mis alumnos" (ver más abajo).', 'area-profesores'); ?></li>
    </ul>
    <h4><?php esc_html_e('Comensales fijos', 'area-profesores'); ?></h4>
    <ul>
        <li><?php esc_html_e('Abajo, en "Comensales fijos de la clase", marca los días de la semana que se queda cada alumno y pulsa "Guardar comensales fijos". Solo hay que hacerlo a principio de curso o cuando cambie algo.', 'area-profesores'); ?></li>
        <li><strong><?php esc_html_e('Es muy importante tener los fijos marcados:', 'area-profesores'); ?></strong> <?php esc_html_e('todo alumno que se queda sin ser fijo cuenta como extra, y cada extra se notifica a Administración para cobrárselo a la familia. Si la clase no tiene fijos, el Área te avisa antes de validar.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Atajos: "Marcar todos (toda la semana)" marca a toda la clase de lunes a viernes; pulsa el nombre de un alumno para marcar su semana entera, o la letra de un día para marcar la columna.', 'area-profesores'); ?></li>
    </ul>
    <?php $m_fig('comedor-fijos', __('Comensales fijos: marca los días que se queda cada alumno y guarda.', 'area-profesores')); ?>
    <h4><?php esc_html_e('Poner el comedor de otra clase', 'area-profesores'); ?></h4>
    <p><?php esc_html_e('Si un compañero falta, elige su clase en "Poner el comedor de un compañero". En la lista, ✓ indica las clases ya validadas y ⏳ las pendientes.', 'area-profesores'); ?></p>
</section>
<?php endif; ?>

<?php if ($mv['mi_comida']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-mi_comida">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['mi_comida']); ?></h3>
    <p><?php esc_html_e('Se da por hecho que todos los profesores comen en el colegio todos los días (salvo los que Dirección haya marcado como que no comen nunca). Si algún día no vas a comer, márcalo para que cocina no prepare tu comida.', 'area-profesores'); ?></p>
    <ul>
        <li><?php esc_html_e('En la pestaña "Comedor", arriba, está el recuadro "Mi comida en el colegio". Los profesores de Secundaria y Bachillerato también tienen la pestaña "Comedor", solo con este recuadro.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Para hoy: pulsa "Hoy no como". Si cambias de idea, pulsa "Sí como hoy".', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Para otros días: abre "Marcar otros días que no como en el colegio", pulsa los días (se ponen en rojo con "No como") y pulsa "Guardar mis días". Puedes marcar días de las próximas semanas.', 'area-profesores'); ?></li>
        <li><?php printf(esc_html__('Cocina recibe cada día, en el resumen de las %s, cuántos profesores no comen y quiénes. Si cambias el día de hoy después de esa hora, cocina recibe un aviso.', 'area-profesores'), esc_html($m_coc)); ?></li>
        <li><?php esc_html_e('Administración y Secretaría ven cada mes los días que cada profesor no ha comido en el colegio.', 'area-profesores'); ?></li>
    </ul>
    <?php $m_fig('mi-comida', __('Mi comida en el colegio: el botón para el día de hoy.', 'area-profesores')); ?>
    <?php $m_fig('mi-comida-dias', __('Los días marcados en rojo son los que no comes en el colegio.', 'area-profesores')); ?>
</section>
<?php endif; ?>

<?php if ($mv['alumnos']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-alumnos">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['alumnos']); ?></h3>
    <p><?php esc_html_e('Lista de los alumnos de tu tutoría y de las clases que tienes asignadas, con sus padres o tutores, el teléfono de emergencia y sus alergias.', 'area-profesores'); ?></p>
    <ul>
        <li><?php esc_html_e('Si tienes varias clases, elige la clase en los botones de arriba. Usa "Buscar alumno" para encontrar a alguien rápido.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Las alergias alimentarias salen en rojo; las no alimentarias (polen, látex…), debajo.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('"Imprimir" saca la lista de la clase en papel (por ejemplo, para llevarla a una excursión).', 'area-profesores'); ?></li>
    </ul>
    <?php $m_fig('mis-alumnos', __('Mis alumnos: padres, teléfono de emergencia y alergias, con el botón "Editar".', 'area-profesores')); ?>
    <h4><?php esc_html_e('Corregir un dato', 'area-profesores'); ?></h4>
    <ol>
        <li><?php esc_html_e('Pulsa "Editar" en la fila del alumno.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Cambia sus alergias o el nombre y el teléfono de sus padres. También puedes añadir otro padre, madre o tutor.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Escribe el motivo del cambio (es obligatorio). Por ejemplo: "La madre me lo ha comunicado hoy en la entrada".', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Pulsa "Guardar y avisar". El cambio se ve al momento y se envía un correo con los datos anteriores, los nuevos y el motivo: los cambios de nombres y teléfonos le llegan a Secretaría; los de alergias alimentarias, a Cocina y a los tutores; y los de alergias no alimentarias, solo a los tutores (las alergias no le llegan a Secretaría). Además, si cambias una alergia, la familia recibe un correo confirmando cómo quedan registradas.', 'area-profesores'); ?></li>
    </ol>
    <?php $m_fig('mis-alumnos-editar', __('Al editar hay que escribir el motivo, que se envía con el aviso.', 'area-profesores')); ?>
    <div class="ap-manual-nota"><?php esc_html_e('Los datos de los padres son de la familia: si el alumno tiene hermanos en el colegio, el cambio también se ve en sus fichas.', 'area-profesores'); ?></div>
</section>
<?php endif; ?>

<?php if ($mv['reservas']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-reservas">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['reservas']); ?></h3>
    <p><?php esc_html_e('Servicios que las familias de tus alumnos han reservado desde el Área de Familias: ampliación de horario, comedor, ludoteca, dietas, salidas anticipadas y retrasos justificados.', 'area-profesores'); ?></p>
    <ul>
        <li><?php esc_html_e('Se abre con el día de hoy. Usa las flechas, el calendario o los botones de los próximos días (el número indica cuántas reservas hay ese día).', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Las reservas aparecen agrupadas por servicio, con el alumno, su clase y los detalles (hora de salida, quién lo recoge, tipo de dieta…).', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Las reservas de comedor y de dieta ya salen marcadas en la pestaña "Comedor"; no hace falta apuntarlas.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Portería ve las reservas de todos los cursos.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Si necesitas consultar las de otros alumnos, pulsa "Todo el centro" (arriba); "Mis alumnos" vuelve a tu lista.', 'area-profesores'); ?></li>
    </ul>
    <?php $m_fig('reservas', __('Reservas del día por servicio, con los próximos días arriba.', 'area-profesores')); ?>
    <h4><?php esc_html_e('Reservar un servicio para una familia (profesores y Portería)', 'area-profesores'); ?></h4>
    <p><?php esc_html_e('Cuando un padre o una madre pide un servicio de palabra, por teléfono o por correo, cualquier profesor (por ejemplo, si el tutor/a no está) o Portería lo reserva para cualquier alumno desde esta misma pestaña; su tutor/a recibe el aviso:', 'area-profesores'); ?></p>
    <ol>
        <li><?php esc_html_e('Pulsa "+ Nueva reserva para una familia" y escribe el nombre del alumno (o de la familia). Elige al alumno en la lista; si quieres, marca también a sus hermanos.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Elige el servicio, el día (o varios días con los días de la semana) y rellena lo que pida: hora, desayuno, dieta, quién le recoge, motivo…', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Una dieta solo se puede reservar los días que el alumno se queda a comer (comensal fijo o con el comedor reservado). Si no se queda, reserva el Comedor y marca en él que necesita dieta.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Escribe quién lo ha pedido (por ejemplo, "la madre, por teléfono") y pulsa "Hacer la reserva".', 'area-profesores'); ?></li>
    </ol>
    <?php $m_fig('reservas-buscar', __('Escribe parte del nombre y elige al alumno en la lista.', 'area-profesores')); ?>
    <?php $m_fig('reservas-nueva', __('Formulario de una nueva reserva (en el ejemplo, una salida anticipada).', 'area-profesores')); ?>
    <ul>
        <li><?php esc_html_e('No hay plazos: se puede reservar para hoy a cualquier hora. No se puede reservar dos veces el mismo servicio para el mismo alumno y día.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Se avisa al tutor/a (y a quien corresponda según "Avisos") y, si está marcado, la familia recibe la confirmación por correo. La reserva aparece en su Área de Familias con "Reservada por: Portería".', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Con el botón "Anular" de la lista, el profesor anula las reservas que ha hecho él y Portería cualquier reserva; se avisa a la familia y al tutor/a.', 'area-profesores'); ?></li>
    </ul>
</section>
<?php endif; ?>

<?php if ($mv['enlaces']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-enlaces">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['enlaces']); ?></h3>
    <p><?php esc_html_e('Accesos directos a las plataformas, documentos y recursos del colegio, agrupados por temas. Escribe en "Buscar enlace" para encontrar uno. Los enlaces los añade Dirección; si echas alguno en falta, díselo.', 'area-profesores'); ?></p>
</section>
<?php endif; ?>

<?php if ($mv['espacios']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-espacios">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['espacios']); ?></h3>
    <p><?php esc_html_e('Los espacios de Google Chat de las familias de cada curso, con su tutor. Pulsa "Abrir en Chat" para entrar (tienes que ser miembro del espacio). Puedes buscar por curso o por tutor; las promociones anteriores están al final.', 'area-profesores'); ?></p>
</section>
<?php endif; ?>

<?php if ($mv['comensales']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-comensales">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['comensales']); ?> <em class="ap-manual-solo"><?php esc_html_e('Solo Dirección, Secretaría y Administración', 'area-profesores'); ?></em></h3>
    <p><?php esc_html_e('La pestaña "Administración" reúne lo del mes en dos bloques. Elige el mes con las flechas.', 'area-profesores'); ?></p>
    <ol>
        <li><strong><?php esc_html_e('Comidas de los alumnos (para cotejar con cocina):', 'area-profesores'); ?></strong> <?php esc_html_e('las comidas validadas de cada día por comedor (Comedor 1, 2 y 3), cuántas fueron extra, cuántas clases validaron y los profesores que no comieron. Abajo, el total del mes para comparar con la factura de cocina. Si un día alguna clase no validó, sale en naranja.', 'area-profesores'); ?></li>
        <li><strong><?php esc_html_e('Cobros a las familias:', 'area-profesores'); ?></strong> <?php esc_html_e('una línea por familia con lo que hay que cobrarle (comidas extra, ampliación con o sin desayuno, ludoteca, dieta…). Pulsa la familia para ver cada alumno, cada servicio y sus días; con el ratón sobre un día ves cómo se pidió. Puedes buscar una familia o desplegarlas todas.', 'area-profesores'); ?></li>
    </ol>
    <ul>
        <li><?php esc_html_e('Las salidas anticipadas y los retrasos no se cobran: están al final, plegados.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('"Descargar (Excel)" baja el mes con los cobros (una línea por alumno y servicio, con las fechas) y las comidas por día; "Imprimir" lo saca en papel.', 'area-profesores'); ?></li>
    </ul>
    <?php $m_fig('administracion', __('Bloque 1: comidas de cada día por comedor, para cotejar con cocina.', 'area-profesores')); ?>
    <?php $m_fig('administracion-servicios', __('Bloque 2: lo que hay que cobrar a cada familia, con sus días.', 'area-profesores')); ?>
</section>
<?php endif; ?>

<?php if ($mv['matriculas']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-matriculas">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['matriculas']); ?> <em class="ap-manual-solo"><?php esc_html_e('Secretaría, Dirección y Administración', 'area-profesores'); ?></em></h3>
    <p><?php esc_html_e('Las familias nuevas rellenan la matrícula en la web del Área Familias (botón de la pantalla de acceso). Confirman su correo con un código y la solicitud llega aquí, con un aviso por correo. Los datos personales y bancarios se guardan cifrados.', 'area-profesores'); ?></p>
    <ol>
        <li><?php esc_html_e('En "Por hacer" están las solicitudes nuevas, en revisión y aprobadas. Pulsa "Abrir" para ver todos los datos, ordenados como en las hojas de papel.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Revisa los datos. Si hay que aclarar algo con la familia, pásala a "En revisión" y escríbeles (el correo de contacto está confirmado).', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Pásala a "Aprobada · pendiente de firma": la familia recibe un correo para venir a firmar y traer los originales.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Pulsa "Imprimir la ficha para firmar": salen la ficha de matrícula, la información del centro con las manifestaciones de los padres, la hoja de protección de datos, la reserva de plaza (solo Primer Ciclo) y la orden SEPA, ya rellenas con lo que contestó la familia. Antes, rellena el nº de matrícula, el NIA, la fecha de ingreso y el grupo para que salgan impresos.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Cuando firmen y comprobéis los originales, marca los documentos entregados y pásala a "Completada".', 'area-profesores'); ?></li>
    </ol>
    <ul>
        <li><?php esc_html_e('La cuenta de la familia se propone sola con la regla del colegio: primerapellido.segundoapellido.cvm del alumno (padres separados: un 1 antes de .cvm). Si otra familia ya la tiene, se añade la inicial del nombre del alumno. Puedes cambiarla.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Si sale el aviso "¿Hermanos de una familia del colegio?", comprueba si son hermanos: entonces se usa la cuenta que ya tienen.', 'area-profesores'); ?></li>
    </ul>
</section>
<?php endif; ?>

<?php if ($mv['comunicados']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-comunicados">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['comunicados']); ?> <em class="ap-manual-solo"><?php esc_html_e('Solo Dirección y Comunicación', 'area-profesores'); ?></em></h3>
    <p><?php esc_html_e('Publica un comunicado a las familias en sus espacios de Google Chat, firmado como el colegio, en varios espacios a la vez.', 'area-profesores'); ?></p>
    <ol>
        <li><?php esc_html_e('En "Publicar como" elige la cuenta con la que sale el comunicado (normalmente, la del colegio).', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Escribe un título (opcional, sale en negrita) y el texto. Para dar formato: *negrita*, _cursiva_, ~tachado~. Los enlaces se activan solos.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Si quieres, añade una imagen (JPG, PNG o GIF, hasta 8 MB) o un PDF (hasta 20 MB, por ejemplo el menú). Se adjuntan en el chat como cuando se publica a mano.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Marca los espacios: uno a uno, por etapa o "Todo el colegio". En la vista previa ves cómo quedará.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('Elige "Publicar ahora" o "Programar" (con fecha y hora, o el atajo "Mañana 8:00").', 'area-profesores'); ?></li>
    </ol>
    <?php $m_fig('comunicados', __('Comunicado a las familias con su vista previa.', 'area-profesores')); ?>
    <ul>
        <li><?php esc_html_e('Los programados se publican solos. Hasta entonces puedes editarlos, enviarlos ya o cancelarlos. Si alguno falla, se avisa por correo a quien lo programó.', 'area-profesores'); ?></li>
        <li><?php esc_html_e('En "Últimos comunicados" se ve en qué espacios se entregó cada uno; si alguno falló, pulsa "Reintentar los que han fallado".', 'area-profesores'); ?></li>
    </ul>
</section>
<?php endif; ?>

<?php if ($mv['dudas']) : $m_i++; ?>
<section class="ap-manual-sec" id="ayuda-dudas">
    <h3><span><?php echo (int) $m_i; ?></span> <?php echo esc_html($m_secciones['dudas']); ?></h3>
    <dl>
        <dt><?php esc_html_e('No veo alguna pestaña o no salen mis alumnos.', 'area-profesores'); ?></dt>
        <dd><?php esc_html_e('Las pestañas dependen de tu tutoría, de las clases que tienes asignadas y de tu cuenta. Pide a Dirección o al administrador que revise tus datos en el Área de Profesores.', 'area-profesores'); ?></dd>
        <dt><?php esc_html_e('Me he olvidado de validar el comedor.', 'area-profesores'); ?></dt>
        <dd><?php esc_html_e('Valídalo en cuanto puedas, aunque sea tarde: cocina recibe un aviso con los datos.', 'area-profesores'); ?></dd>
        <dt><?php esc_html_e('Un alumno sale como fijo pero ya no se queda nunca.', 'area-profesores'); ?></dt>
        <dd><?php esc_html_e('Quita sus días en "Comensales fijos de la clase" y guarda.', 'area-profesores'); ?></dd>
        <dt><?php esc_html_e('Un teléfono o una alergia no es correcto.', 'area-profesores'); ?></dt>
        <dd><?php esc_html_e('Corrígelo desde "Mis alumnos" › "Editar", indicando el motivo. Secretaría recibe el aviso (si son alergias: las alimentarias, Cocina y los tutores; las demás, los tutores; y la familia recibe la confirmación).', 'area-profesores'); ?></dd>
        <dt><?php printf(esc_html__('Hoy no como en el colegio y ya son más de las %s.', 'area-profesores'), esc_html($m_coc)); ?></dt>
        <dd><?php esc_html_e('Márcalo igualmente en "Mi comida en el colegio": cocina recibe un aviso al momento.', 'area-profesores'); ?></dd>
    </dl>
</section>
<?php endif; ?>
