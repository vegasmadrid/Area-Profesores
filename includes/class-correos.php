<?php
/**
 * Avisos por correo (correos automáticos).
 *
 * Every automatic e-mail of the plugin is defined here and can be edited in
 * "Área Profesores > Avisos": on/off, subject, text with
 * {variables}, extra recipients and (for some e-mails) who receives it.
 * All of them share the same design (school logo and colours).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Correos {

    const OPTION = 'ap_correos';
    const SLUG   = 'area-profesores-correos';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 30);
        add_action('admin_post_ap_correos_guardar', array($this, 'handle_save'));
        add_action('admin_post_ap_correos_prueba', array($this, 'handle_prueba'));
    }

    /* ---------------------------------------------------------------------
     * Definición de los correos
     * ------------------------------------------------------------------- */

    public static function definiciones() {
        return array(
            'solicitud_nueva' => array(
                'titulo'  => __('Nueva solicitud de acceso de un profesor', 'area-profesores'),
                'cuando'  => __('Cuando alguien pide acceso desde la página de login.', 'area-profesores'),
                'para'    => __('Los correos de "Correos de notificación de solicitudes" (Ajustes).', 'area-profesores'),
                'cuentas' => array(),
                'grupo'   => __('Profesores', 'area-profesores'),
                'asunto'  => __('Nueva solicitud de acceso al Área de Profesores: {nombre} {apellidos}', 'area-profesores'),
                'cuerpo'  => __("Hay una nueva solicitud de acceso al Área de Profesores.\n\nNombre: {nombre} {apellidos}\nCorreo: {correo}\nTutoría o especialista: {tutoria}\nObservaciones: {observaciones}\n\nPuedes aprobarla o rechazarla aquí:\n{enlace}", 'area-profesores'),
                'boton'   => __('Revisar la solicitud', 'area-profesores'),
                'vars'    => array('nombre' => __('nombre', 'area-profesores'), 'apellidos' => __('apellidos', 'area-profesores'), 'correo' => __('correo del profesor', 'area-profesores'), 'tutoria' => __('etapa y tutoría pedidas', 'area-profesores'), 'observaciones' => __('observaciones', 'area-profesores'), 'enlace' => __('enlace a las solicitudes', 'area-profesores')),
                'ejemplo' => array('nombre' => 'Ana', 'apellidos' => 'García López', 'correo' => 'ana.garcia@casadelavirgen.es', 'tutoria' => 'Tutor/a de 3º EP A (Primaria)', 'observaciones' => 'Me incorporo este curso.', 'enlace' => admin_url('admin.php?page=area-profesores-solicitudes')),
            ),
            'solicitud_aprobada' => array(
                'titulo'  => __('Acceso de un profesor aprobado', 'area-profesores'),
                'cuando'  => __('Cuando se aprueba una solicitud de acceso.', 'area-profesores'),
                'para'    => __('El profesor que pidió el acceso.', 'area-profesores'),
                'cuentas' => array(),
                'grupo'   => __('Profesores', 'area-profesores'),
                'asunto'  => __('Tu acceso al Área de Profesores está activado', 'area-profesores'),
                'cuerpo'  => __("Hola, {nombre}:\n\nLa dirección del Colegio Casa de la Virgen ha aprobado tu acceso al Área de Profesores. Ya puedes entrar con tu cuenta del colegio:\n{enlace}\n\nUn saludo.", 'area-profesores'),
                'boton'   => __('Entrar en el Área de Profesores', 'area-profesores'),
                'vars'    => array('nombre' => __('nombre', 'area-profesores'), 'enlace' => __('enlace a la página de login', 'area-profesores')),
                'ejemplo' => array('nombre' => 'Ana', 'enlace' => home_url('/')),
            ),
            'familia_datos' => array(
                'titulo'  => __('Una familia cambia sus datos de contacto', 'area-profesores'),
                'cuando'  => __('Cuando una familia cambia un teléfono o una dirección desde el Área Familias.', 'area-profesores'),
                'para'    => __('Los que elijas abajo (si no hay nadie, el administrador de la web).', 'area-profesores'),
                'cuentas' => array('secretaria'),
                'grupo'   => __('Alumnos y familias', 'area-profesores'),
                'admin_si_vacio' => true,
                'asunto'  => __('Cambio de datos de la familia {familia}', 'area-profesores'),
                'cuerpo'  => __("La familia {familia} ({correo}) ha actualizado sus datos desde el Área Familias:\n\n{cambios}\n\nAlumnos:\n{alumnos}\n\nLos datos nuevos ya se ven en el Área de Profesores. Secretaría: recuerda actualizarlos también en Alexia.", 'area-profesores'),
                'boton'   => '',
                'vars'    => array('familia' => __('nombre de la familia', 'area-profesores'), 'correo' => __('cuenta de la familia', 'area-profesores'), 'cambios' => __('lista de cambios (antes → ahora)', 'area-profesores'), 'alumnos' => __('hijos y su curso', 'area-profesores')),
                'ejemplo' => array('familia' => 'García López', 'correo' => 'lucia.garcia@casadelavirgen.es', 'cambios' => "- Teléfono de emergencia de Ana López: 600 111 222 → 699 000 111", 'alumnos' => '- Lucía García López (3º EP A)'),
                'tutores' => true,
            ),
            'comedor_recordatorio' => array(
                'titulo'  => __('Recordatorio: falta poner el comedor', 'area-profesores'),
                'cuando'  => __('Cada día lectivo a la hora del recordatorio (10:30 por defecto), si una clase de Primer Ciclo, Infantil o Primaria no tiene el comedor validado. Se configura en "Comedor Diario".', 'area-profesores'),
                'para'    => __('El tutor/a de la clase (un correo por clase).', 'area-profesores'),
                'cuentas' => array(),
                'grupo'   => __('Comedor', 'area-profesores'),
                'asunto'  => __('Falta poner el comedor de {clase}', 'area-profesores'),
                'cuerpo'  => __("Hola, {nombre}:\n\nParece que se te ha olvidado poner el comedor de hoy de {clase}. Por favor, complétalo cuanto antes: a las {hora} se envía el resumen a cocina.\n\nSi los que se quedan son los de siempre, solo tienes que pulsar \"Validar\".\n\n{enlace}", 'area-profesores'),
                'boton'   => __('Poner el comedor', 'area-profesores'),
                'vars'    => array('nombre' => __('nombre del tutor/a', 'area-profesores'), 'clase' => __('clase', 'area-profesores'), 'hora' => __('hora del resumen a cocina', 'area-profesores'), 'enlace' => __('enlace al comedor de la clase', 'area-profesores')),
                'ejemplo' => array('nombre' => 'Ana García', 'clase' => '3º EP A', 'hora' => '12:00', 'enlace' => home_url('/')),
            ),
            'comedor_cocina' => array(
                'titulo'  => __('Resumen del comedor para cocina', 'area-profesores'),
                'cuando'  => __('Cada día lectivo a la hora del resumen (12:00 por defecto). Se configura en "Comedor Diario".', 'area-profesores'),
                'para'    => __('Las cuentas que elijas abajo (normalmente, Cocina).', 'area-profesores'),
                'cuentas' => array('cocina'),
                'grupo'   => __('Comedor', 'area-profesores'),
                'asunto'  => __('Comedor del {fecha}: {total} comensales', 'area-profesores'),
                'cuerpo'  => __("Comedor del {fecha}\n\nTotal de comensales (alumnos): {total}\n\n⚠️ ALERGIAS ALIMENTARIAS\n{alergias}\n\n👩‍🏫 PROFESORES\n{profesores}\n\n🍽️ POR COMEDORES\n{comedores}\n\nPor clases:\n{clases}\n\nClases sin validar todavía:\n{pendientes}\n\nDietas y observaciones:\n{dietas}\n\nLa página del comedor se actualiza sola con los cambios:\n{enlace}", 'area-profesores'),
                'boton'   => __('Ver el comedor de hoy', 'area-profesores'),
                'vars'    => array('fecha' => __('día', 'area-profesores'), 'total' => __('total de comensales', 'area-profesores'), 'clases' => __('comensales de cada clase', 'area-profesores'), 'pendientes' => __('clases sin validar', 'area-profesores'), 'dietas' => __('dietas y observaciones', 'area-profesores'), 'alergias' => __('alérgicos que no comen hoy (no preparar su menú) y alérgicos que comen', 'area-profesores'), 'profesores' => __('número y nombre de los profesores que hoy no comen en el colegio', 'area-profesores'), 'comedores' => __('comensales de cada comedor y de cada curso', 'area-profesores'), 'enlace' => __('página del comedor', 'area-profesores')),
                'ejemplo' => array('fecha' => 'martes 24 de noviembre', 'total' => '212', 'alergias' => "HOY NO COMEN (no preparar su menú especial):\n- 3º EP A · Pablo Ruiz: frutos secos\n\nComen hoy con alergia alimentaria:\n- 1º EP B · Marta Gil: huevo", 'clases' => "- 3 años A: 18 (1 extra)\n- 3º EP A: 21 (1 reservado por la familia)", 'pendientes' => '- 5º EP B', 'dietas' => '- 3º EP A · Lucía García: Dieta blanda (reserva de la familia)', 'profesores' => "2 profesores no comen hoy en el colegio:\n- Ana García\n- Luis Pérez", 'comedores' => "Comedor 1: 58 comensales\n   - 1 año: 12 (A 6 · B 6)\n   - 3 años: 30 (A 15 · B 15)\nComedor 2: 71 comensales\nComedor 3: 83 comensales", 'enlace' => home_url('/')),
            ),
            'comedor_cambio' => array(
                'titulo'  => __('Cambio en el comedor después del resumen', 'area-profesores'),
                'cuando'  => __('Cuando, después de enviar el resumen a cocina, se valida o cambia el comedor de una clase (o una familia reserva comedor para hoy).', 'area-profesores'),
                'para'    => __('Las cuentas que elijas abajo (normalmente, Cocina).', 'area-profesores'),
                'cuentas' => array('cocina'),
                'grupo'   => __('Comedor', 'area-profesores'),
                'asunto'  => __('Cambio en el comedor de {clase}: {ahora} comensales', 'area-profesores'),
                'cuerpo'  => __("Ha cambiado el comedor de hoy de {clase}: antes {antes}, ahora {ahora}.\n\nTotal de hoy: {total} comensales.\nCambio hecho por: {quien}\n\nAlergias alimentarias:\n{alergias}\n\n{enlace}", 'area-profesores'),
                'boton'   => __('Ver el comedor de hoy', 'area-profesores'),
                'vars'    => array('clase' => __('clase', 'area-profesores'), 'antes' => __('comensales antes', 'area-profesores'), 'ahora' => __('comensales ahora', 'area-profesores'), 'total' => __('total del día', 'area-profesores'), 'quien' => __('quién lo ha cambiado', 'area-profesores'), 'alergias' => __('alérgicos que ahora comen o dejan de comer', 'area-profesores'), 'enlace' => __('página del comedor', 'area-profesores')),
                'ejemplo' => array('clase' => '3º EP A', 'antes' => '20', 'ahora' => '19', 'total' => '212', 'quien' => 'Ana García', 'alergias' => '- Pablo Ruiz (alergia: frutos secos) NO come hoy: no preparar su menú especial.', 'enlace' => home_url('/')),
            ),
            'comedor_extra' => array(
                'titulo'  => __('Un profesor apunta alumnos extra al comedor', 'area-profesores'),
                'cuando'  => __('Cuando, al validar el comedor, se apunta a un alumno que ese día no es comensal fijo ni tiene reserva de la familia (o se le quita). Se registra como un servicio de comedor extra de ese día.', 'area-profesores'),
                'para'    => __('El profesor que lo ha apuntado (como confirmación) y las cuentas que elijas abajo (de serie, Administración).', 'area-profesores'),
                'cuentas' => array('administracion'),
                'grupo'   => __('Comedor', 'area-profesores'),
                'asunto'  => __('Comedor extra {fecha}: {clase}', 'area-profesores'),
                'cuerpo'  => __("{quien} ha apuntado al comedor del {fecha} ({clase}) como comensales extra a:\n{apuntados}\n\nQuitados del comedor extra:\n{quitados}\n\nSe ha registrado como un servicio de comedor extra de ese día (se ve en la pestaña Administración) y se ha enviado un correo a cada familia.\n\n{enlace}", 'area-profesores'),
                'boton'   => __('Ver el comedor', 'area-profesores'),
                'vars'    => array('quien' => __('profesor/a que lo apunta', 'area-profesores'), 'clase' => __('clase', 'area-profesores'), 'fecha' => __('día', 'area-profesores'), 'apuntados' => __('alumnos apuntados (y su familia)', 'area-profesores'), 'quitados' => __('alumnos quitados', 'area-profesores'), 'enlace' => __('comedor de la clase', 'area-profesores')),
                'ejemplo' => array('quien' => 'Ana García', 'clase' => '3º EP A', 'fecha' => 'martes 24 de noviembre', 'apuntados' => '- Pablo Ruiz (familia Ruiz Gil)', 'quitados' => 'Ninguno.', 'enlace' => home_url('/')),
            ),
            'comedor_extra_familia' => array(
                'titulo'  => __('Aviso a la familia: su hijo/a come hoy en el colegio', 'area-profesores'),
                'cuando'  => __('Cuando un profesor apunta al comedor del día a un alumno que no es comensal fijo ese día (lo habitual: la familia lo ha pedido de palabra o por correo).', 'area-profesores'),
                'para'    => __('Los correos de la familia del alumno.', 'area-profesores'),
                'cuentas' => array(),
                'grupo'   => __('Comedor', 'area-profesores'),
                'asunto'  => __('{alumno} come hoy en el colegio', 'area-profesores'),
                'cuerpo'  => __("Hola:\n\n{quien} ha apuntado a {alumno} ({clase}) al comedor del colegio el {fecha}. Queda registrado como un servicio de comedor extra de ese día.\n\nPrecio: {precio}\n\nSi no es correcto, responde a este correo o avisa al colegio cuanto antes.\n\nPuedes ver tus reservas en el Área de Familias.\n\n{enlace}", 'area-profesores'),
                'boton'   => __('Ver mis reservas', 'area-profesores'),
                'vars'    => array('alumno' => __('alumno', 'area-profesores'), 'clase' => __('clase', 'area-profesores'), 'fecha' => __('día', 'area-profesores'), 'quien' => __('profesor/a que lo apunta', 'area-profesores'), 'precio' => __('precio del comedor (Área Familias)', 'area-profesores'), 'enlace' => __('Área de Familias', 'area-profesores')),
                'ejemplo' => array('alumno' => 'Pablo Ruiz', 'clase' => '3º EP A', 'fecha' => 'martes 24 de noviembre', 'quien' => 'Ana García', 'precio' => '6 €', 'enlace' => home_url('/')),
            ),
            'comedor_profesor' => array(
                'titulo'  => __('Un profesor cambia su comida de hoy después del resumen', 'area-profesores'),
                'cuando'  => __('Cuando, después de enviar el resumen a cocina, un profesor marca que hoy no come en el colegio (o lo desmarca).', 'area-profesores'),
                'para'    => __('Las cuentas que elijas abajo (normalmente, Cocina).', 'area-profesores'),
                'cuentas' => array('cocina'),
                'grupo'   => __('Comedor', 'area-profesores'),
                'asunto'  => __('Comedor: {profesor} {cambio}', 'area-profesores'),
                'cuerpo'  => __("{profesor} {cambio}.\n\nProfesores que hoy no comen en el colegio: {numero}\n{lista}\n\n{enlace}", 'area-profesores'),
                'boton'   => __('Ver el comedor de hoy', 'area-profesores'),
                'vars'    => array('profesor' => __('profesor/a', 'area-profesores'), 'cambio' => __('"NO come hoy" o "SÍ come hoy"', 'area-profesores'), 'numero' => __('número de profesores que hoy no comen', 'area-profesores'), 'lista' => __('sus nombres', 'area-profesores'), 'enlace' => __('página del comedor', 'area-profesores')),
                'ejemplo' => array('profesor' => 'Ana García', 'cambio' => 'NO come hoy en el colegio', 'numero' => '3', 'lista' => "- Ana García\n- Luis Pérez\n- Marta Sanz", 'enlace' => home_url('/')),
            ),
            'profesor_datos' => array(
                'titulo'  => __('Un profesor cambia datos de un alumno o su familia', 'area-profesores'),
                'cuando'  => __('Cuando un profesor cambia, desde "Mis alumnos", las alergias de un alumno o el nombre o el teléfono de sus padres. Tiene que indicar el motivo.', 'area-profesores'),
                'para'    => __('Los que elijas abajo (si no hay nadie, el administrador de la web).', 'area-profesores'),
                'cuentas' => array('secretaria'),
                'grupo'   => __('Alumnos y familias', 'area-profesores'),
                'admin_si_vacio' => true,
                'asunto'  => __('Cambio de datos de {alumno} ({curso})', 'area-profesores'),
                'cuerpo'  => __("{profesor} ha cambiado datos de {alumno} ({curso}) desde \"Mis alumnos\":\n\n{cambios}\n\nMotivo: {motivo}\n\nLos datos nuevos ya se ven en el Área de Profesores. Secretaría: revísalos y actualízalos también en Alexia si hace falta.\n\n{enlace}", 'area-profesores'),
                'boton'   => __('Ver la ficha del alumno', 'area-profesores'),
                'vars'    => array('profesor' => __('profesor/a que hace el cambio', 'area-profesores'), 'alumno' => __('nombre del alumno', 'area-profesores'), 'curso' => __('curso', 'area-profesores'), 'cambios' => __('lista de cambios (antes → ahora)', 'area-profesores'), 'motivo' => __('motivo indicado por el profesor', 'area-profesores'), 'enlace' => __('enlace a la ficha del alumno', 'area-profesores')),
                'ejemplo' => array('profesor' => 'Ana García', 'alumno' => 'Lucía García López', 'curso' => '3º EP A', 'cambios' => "- Alergias alimentarias: (vacío) → Frutos secos\n- Teléfono de emergencia de Ana López: 600 111 222 → 699 000 111 (familia García López; también afecta a sus hermanos)", 'motivo' => 'La madre nos lo comunicó en la tutoría de hoy.', 'enlace' => admin_url('admin.php?page=area-profesores-alumnos')),
                'tutores' => false,
            ),
            'reserva_nueva' => array(
                'titulo'  => __('Una familia reserva un servicio', 'area-profesores'),
                'cuando'  => __('Cuando una familia reserva un servicio desde el Área Familias (uno por cada hijo).', 'area-profesores'),
                'para'    => __('Los correos de aviso del servicio que se pongan en el Área Familias y los que elijas abajo (si no hay nadie, el administrador de la web). Si respondéis, la respuesta le llega a la familia.', 'area-profesores'),
                'cuentas' => array('secretaria'),
                'grupo'   => __('Reservas de las familias', 'area-profesores'),
                'admin_si_vacio' => true,
                'asunto'  => __('Reserva de {servicio}: {alumno} ({curso})', 'area-profesores'),
                'cuerpo'  => __("La familia {familia} ({correo}) ha reservado un servicio desde el Área Familias:

Servicio: {servicio}
Alumno/a: {alumno} ({curso})
Fechas: {fechas}

{detalles}

Puedes ver las reservas de tus alumnos en la pestaña Reservas del Área de Profesores:
{enlace}", 'area-profesores'),
                'boton'   => __('Ver las reservas', 'area-profesores'),
                'vars'    => array('servicio' => __('servicio reservado', 'area-profesores'), 'alumno' => __('nombre del alumno', 'area-profesores'), 'curso' => __('curso', 'area-profesores'), 'familia' => __('nombre de la familia', 'area-profesores'), 'correo' => __('cuenta de la familia', 'area-profesores'), 'fechas' => __('día o días y hora', 'area-profesores'), 'detalles' => __('todo lo que ha indicado la familia (desayuno, dieta, motivo…)', 'area-profesores'), 'enlace' => __('enlace a la pestaña Reservas', 'area-profesores')),
                'ejemplo' => array('servicio' => 'Salida anticipada', 'alumno' => 'Lucía García López', 'curso' => '3º EP A', 'familia' => 'García López', 'correo' => 'lucia.garcia@casadelavirgen.es', 'fechas' => 'Martes 25 de noviembre de 2026 · a las 12:30', 'detalles' => "Motivo: Cita médica\nRecoge: Su abuela, Carmen López", 'enlace' => home_url('/')),
                'tutores' => true,
                'cocina' => true,
            ),
            'reserva_familia' => array(
                'titulo'  => __('Confirmación de la reserva a la familia', 'area-profesores'),
                'cuando'  => __('Cuando una familia reserva un servicio.', 'area-profesores'),
                'para'    => __('La cuenta de la familia que ha hecho la reserva.', 'area-profesores'),
                'cuentas' => array(),
                'grupo'   => __('Reservas de las familias', 'area-profesores'),
                'asunto'  => __('Reserva confirmada: {servicio}', 'area-profesores'),
                'cuerpo'  => __("Hola:

Hemos recibido tu reserva. Estos son sus datos:

Servicio: {servicio}
Alumno/a: {alumnos}
Fechas: {fechas}

{detalles}

Ya se lo hemos comunicado a su tutor/a y a secretaría. Puedes consultar o anular tus reservas en el Área Familias:
{enlace}

Un saludo,
Colegio Casa de la Virgen", 'area-profesores'),
                'boton'   => __('Ir al Área Familias', 'area-profesores'),
                'vars'    => array('servicio' => __('servicio reservado', 'area-profesores'), 'alumnos' => __('hijo o hijos y su curso', 'area-profesores'), 'fechas' => __('día o días y hora', 'area-profesores'), 'detalles' => __('lo que ha indicado la familia', 'area-profesores'), 'enlace' => __('enlace al Área Familias', 'area-profesores')),
                'ejemplo' => array('servicio' => 'Ampliación de horario', 'alumnos' => 'Lucía García López (3º EP A), Pablo García López (1º EP B)', 'fechas' => 'Del lunes 1 de diciembre de 2026 al viernes 19 de diciembre de 2026', 'detalles' => 'Desayuno: Sí', 'enlace' => home_url('/')),
            ),
            'reserva_anulada' => array(
                'titulo'  => __('Se anula una reserva', 'area-profesores'),
                'cuando'  => __('Cuando la familia anula una reserva desde el Área Familias, o se anula desde "Reservas de familias".', 'area-profesores'),
                'para'    => __('Los correos de aviso del servicio y los que elijas abajo (si no hay nadie, el administrador de la web).', 'area-profesores'),
                'cuentas' => array('secretaria'),
                'grupo'   => __('Reservas de las familias', 'area-profesores'),
                'admin_si_vacio' => true,
                'asunto'  => __('Reserva anulada: {servicio} — {alumno} ({curso})', 'area-profesores'),
                'cuerpo'  => __("Se ha anulado esta reserva (la ha anulado {quien}):

Servicio: {servicio}
Alumno/a: {alumno} ({curso})
Familia: {familia} ({correo})
Fechas: {fechas}

{detalles}", 'area-profesores'),
                'boton'   => '',
                'vars'    => array('servicio' => __('servicio', 'area-profesores'), 'alumno' => __('nombre del alumno', 'area-profesores'), 'curso' => __('curso', 'area-profesores'), 'familia' => __('nombre de la familia', 'area-profesores'), 'correo' => __('cuenta de la familia', 'area-profesores'), 'fechas' => __('día o días y hora', 'area-profesores'), 'detalles' => __('datos de la reserva', 'area-profesores'), 'quien' => __('la familia o el colegio', 'area-profesores')),
                'ejemplo' => array('servicio' => 'Comedor', 'alumno' => 'Lucía García López', 'curso' => '3º EP A', 'familia' => 'García López', 'correo' => 'lucia.garcia@casadelavirgen.es', 'fechas' => 'Jueves 27 de noviembre de 2026', 'detalles' => 'Dieta: No', 'quien' => 'la familia'),
                'tutores' => true,
                'cocina' => true,
            ),
            'reserva_anulada_familia' => array(
                'titulo'  => __('Aviso a la familia de una reserva anulada', 'area-profesores'),
                'cuando'  => __('Cuando la familia anula una reserva, o se anula desde "Reservas de familias".', 'area-profesores'),
                'para'    => __('La cuenta de la familia que hizo la reserva.', 'area-profesores'),
                'cuentas' => array(),
                'grupo'   => __('Reservas de las familias', 'area-profesores'),
                'asunto'  => __('Reserva anulada: {servicio}', 'area-profesores'),
                'cuerpo'  => __("Hola:

Se ha anulado esta reserva (la ha anulado {quien}):

Servicio: {servicio}
Alumno/a: {alumnos}
Fechas: {fechas}

Puedes hacer una reserva nueva en el Área Familias:
{enlace}

Un saludo,
Colegio Casa de la Virgen", 'area-profesores'),
                'boton'   => __('Ir al Área Familias', 'area-profesores'),
                'vars'    => array('servicio' => __('servicio', 'area-profesores'), 'alumnos' => __('alumno y curso', 'area-profesores'), 'fechas' => __('día o días y hora', 'area-profesores'), 'quien' => __('la propia familia o el colegio', 'area-profesores'), 'enlace' => __('enlace al Área Familias', 'area-profesores')),
                'ejemplo' => array('servicio' => 'Comedor', 'alumnos' => 'Lucía García López (3º EP A)', 'fechas' => 'Jueves 27 de noviembre de 2026', 'quien' => 'la propia familia', 'enlace' => home_url('/')),
            ),
            'programado_errores' => array(
                'titulo'  => __('Comunicado programado con errores', 'area-profesores'),
                'cuando'  => __('Cuando un comunicado programado no se ha podido publicar en todos los espacios de Chat.', 'area-profesores'),
                'para'    => __('Quien programó el comunicado.', 'area-profesores'),
                'cuentas' => array(),
                'grupo'   => __('Comunicados', 'area-profesores'),
                'asunto'  => __('Comunicado programado con errores: {titulo}', 'area-profesores'),
                'cuerpo'  => __("El comunicado programado \"{titulo}\" no se ha podido publicar en todos los espacios:\n\n{resultados}\n\nPuedes reintentar los espacios con error desde la pestaña Comunicados del Área de Profesores:\n{enlace}", 'area-profesores'),
                'boton'   => __('Ir a Comunicados', 'area-profesores'),
                'vars'    => array('titulo' => __('título del comunicado', 'area-profesores'), 'resultados' => __('resultado en cada espacio', 'area-profesores'), 'enlace' => __('enlace a Comunicados', 'area-profesores')),
                'ejemplo' => array('titulo' => 'Excursión al Planetario', 'resultados' => "✓ 3º EP A\n✗ 3º EP B — El webhook no existe o se ha borrado del espacio.", 'enlace' => home_url('/')),
            ),
        );
    }

    /**
     * Saved settings of an e-mail merged with its defaults.
     */
    public static function get($key) {
        $defs = self::definiciones();
        $d = $defs[$key];
        $all = get_option(self::OPTION, array());
        $s = (is_array($all) && isset($all[$key]) && is_array($all[$key])) ? $all[$key] : array();
        return array(
            'activo'     => isset($s['activo']) ? (bool) $s['activo'] : true,
            'asunto'     => isset($s['asunto']) && $s['asunto'] !== '' ? $s['asunto'] : $d['asunto'],
            'cuerpo'     => isset($s['cuerpo']) && $s['cuerpo'] !== '' ? $s['cuerpo'] : $d['cuerpo'],
            'extra'      => isset($s['extra']) ? $s['extra'] : '',
            'tutores'    => isset($s['tutores']) ? (bool) $s['tutores'] : !empty($d['tutores']),
            'cocina'     => isset($s['cocina']) ? (bool) $s['cocina'] : !empty($d['cocina']),
            'cuentas'    => self::cuentas_guardadas($d, $s),
        );
    }

    /**
     * Special accounts (Cuentas especiales) that receive an e-mail.
     * Settings saved before "Avisos" existed only had a Secretaría checkbox.
     */
    private static function cuentas_guardadas($d, $s) {
        $validas = array_keys(Area_Profesores_Cuentas::roles());
        if (isset($s['cuentas']) && is_array($s['cuentas'])) {
            return array_values(array_intersect($s['cuentas'], $validas));
        }
        $c = isset($d['cuentas']) ? $d['cuentas'] : array();
        if (isset($s['secretaria'])) {
            $c = array_diff($c, array('secretaria'));
            if ($s['secretaria']) {
                $c[] = 'secretaria';
            }
        }
        return array_values(array_intersect($c, $validas));
    }

    /**
     * E-mail addresses of the special accounts and extra addresses of an e-mail.
     */
    public static function emails_configurados($key) {
        $c = self::get($key);
        $to = Area_Profesores_Alumnos::emails_array($c['extra']);
        foreach ($c['cuentas'] as $rol) {
            $to = array_merge($to, Area_Profesores_Cuentas::emails($rol));
        }
        return array_values(array_unique($to));
    }

    /**
     * Who receives an e-mail, for the "Avisos" page.
     */
    public static function destinatarios_texto($key) {
        $defs = self::definiciones();
        $d = $defs[$key];
        $c = self::get($key);
        $roles = Area_Profesores_Cuentas::roles();
        $out = array();
        foreach ($c['cuentas'] as $r) {
            $out[] = $roles[$r]['nombre'];
        }
        if (isset($d['tutores']) && $c['tutores']) {
            $out[] = __('Tutores', 'area-profesores');
        }
        if (isset($d['cocina']) && $c['cocina'] && !in_array('cocina', $c['cuentas'], true)) {
            $out[] = __('Cocina (comedor y dietas)', 'area-profesores');
        }
        if ($c['extra'] !== '') {
            $out[] = $c['extra'];
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Envío
     * ------------------------------------------------------------------- */

    /**
     * Send an automatic e-mail.
     *
     * @param string       $key  e-mail key
     * @param string|array $to   recipients
     * @param array        $vars values of the {variables}
     * @return bool
     */
    public static function enviar($key, $to, $vars, $forzar = false, $reply_to = '') {
        $defs = self::definiciones();
        if (!isset($defs[$key])) {
            return false;
        }
        $c = self::get($key);
        if (!$c['activo'] && !$forzar) {
            return false;
        }

        // The special accounts chosen in "Avisos" and the extra addresses.
        $to = (array) $to;
        foreach ($c['cuentas'] as $rol) {
            $to = array_merge($to, Area_Profesores_Cuentas::emails($rol));
        }
        $to = array_merge($to, Area_Profesores_Alumnos::emails_array($c['extra']));
        $to = array_values(array_unique(array_filter(array_map('trim', $to), 'is_email')));
        if (!$to && !empty($defs[$key]['admin_si_vacio'])) {
            $to = array(get_option('admin_email'));
        }
        if (!$to) {
            return false;
        }

        $asunto = self::reemplazar($c['asunto'], $vars);
        $cuerpo = self::reemplazar($c['cuerpo'], $vars);
        if (!empty($vars['_anexo'])) {
            // Information added after the text was customised (e.g. food allergies).
            $cuerpo .= "\n\n" . $vars['_anexo'];
        }
        $html = self::html($asunto, $cuerpo, $defs[$key]['boton']);

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($reply_to !== '' && is_email($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        return wp_mail($to, wp_specialchars_decode($asunto, ENT_QUOTES), $html, $headers);
    }

    private static function reemplazar($texto, $vars) {
        $map = array('{web}' => get_bloginfo('name'));
        foreach ((array) $vars as $k => $v) {
            $map['{' . $k . '}'] = (string) $v;
        }
        return strtr($texto, $map);
    }

    /**
     * E-mail design: logo, coloured header and the text. Lines with only a link
     * become a button.
     */
    public static function html($asunto, $texto, $boton = '') {
        $logo = Area_Profesores_Admin::get_setting('logo_url', '');
        $lineas = preg_split('/\r\n|\r|\n/', $texto);
        $body = '';
        $parrafo = array();
        $cerrar = function () use (&$parrafo, &$body) {
            if ($parrafo) {
                $body .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1f2240;">' . implode('<br>', $parrafo) . '</p>';
                $parrafo = array();
            }
        };
        foreach ($lineas as $l) {
            $t = trim($l);
            if ($t === '') {
                $cerrar();
                continue;
            }
            if (preg_match('#^https?://\S+$#', $t)) {
                $cerrar();
                $body .= '<p style="margin:4px 0 20px;"><a href="' . esc_url($t) . '" style="display:inline-block;background:#313280;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:12px 24px;border-radius:999px;">' . esc_html($boton !== '' ? $boton : __('Abrir', 'area-profesores')) . '</a></p>';
                continue;
            }
            $parrafo[] = make_clickable(esc_html($l));
        }
        $cerrar();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($asunto) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f5f7fc;font-family:Montserrat,Segoe UI,Roboto,Arial,sans-serif;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fc;padding:24px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e3e6f2;">'
            . ($logo ? '<tr><td style="padding:18px 24px;background:#ffffff;"><img src="' . esc_url($logo) . '" alt="Colegio Casa de la Virgen" style="max-height:60px;width:auto;border:0;"></td></tr>' : '')
            . '<tr><td style="height:6px;background:#313280;font-size:0;line-height:0;">&nbsp;</td></tr>'
            . '<tr><td style="height:3px;background:#72a7f7;font-size:0;line-height:0;">&nbsp;</td></tr>'
            . '<tr><td style="height:3px;background:#dc8e2b;font-size:0;line-height:0;">&nbsp;</td></tr>'
            . '<tr><td style="padding:26px 28px 10px;"><h1 style="margin:0 0 18px;font-size:20px;line-height:1.3;color:#313280;">' . esc_html($asunto) . '</h1>' . $body . '</td></tr>'
            . '<tr><td style="padding:14px 28px 22px;border-top:1px solid #eef0f7;font-size:12px;color:#6b7091;">' . esc_html__('Correo automático del Colegio Casa de la Virgen.', 'area-profesores') . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /* ---------------------------------------------------------------------
     * Backend
     * ------------------------------------------------------------------- */

    public function register_menu() {
        add_submenu_page('area-profesores', __('Avisos por correo', 'area-profesores'), __('Avisos', 'area-profesores'), 'manage_options', self::SLUG, array($this, 'render'));
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }
        // Grouped (Profesores, Alumnos y familias, Comedor, Reservas…), keeping the order.
        $defs = array();
        foreach (self::definiciones() as $k => $d) {
            $defs[$d['grupo']][$k] = $d;
        }
        $defs = array_merge(...array_values($defs));
        $flash = get_transient('ap_correos_flash_' . get_current_user_id());
        if ($flash) {
            delete_transient('ap_correos_flash_' . get_current_user_id());
        }
        $roles = Area_Profesores_Cuentas::roles();
        $por_rol = array();
        foreach ($roles as $r => $x) {
            $por_rol[$r] = Area_Profesores_Cuentas::usuarios($r);
        }
        include AREA_PROFESORES_PLUGIN_DIR . 'templates/admin-correos.php';
    }

    private static function back($key, $text, $type = 'success') {
        set_transient('ap_correos_flash_' . get_current_user_id(), array('type' => $type, 'text' => $text), 120);
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '#correo-' . $key));
        exit;
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $key = isset($_POST['correo']) ? sanitize_key($_POST['correo']) : '';
        check_admin_referer('ap_correos_guardar_' . $key);
        $defs = self::definiciones();
        if (!isset($defs[$key])) {
            wp_die('Correo no válido');
        }

        $all = get_option(self::OPTION, array());
        $all = is_array($all) ? $all : array();
        if (!empty($_POST['restablecer'])) {
            unset($all[$key]);
            update_option(self::OPTION, $all, false);
            self::back($key, __('Texto original restablecido.', 'area-profesores'));
        }

        $all[$key] = array(
            'activo'     => !empty($_POST['activo']),
            'asunto'     => isset($_POST['asunto']) ? sanitize_text_field(wp_unslash($_POST['asunto'])) : '',
            'cuerpo'     => isset($_POST['cuerpo']) ? sanitize_textarea_field(wp_unslash($_POST['cuerpo'])) : '',
            'extra'      => isset($_POST['extra']) ? implode(', ', Area_Profesores_Alumnos::emails_array(wp_unslash($_POST['extra']))) : '',
            'tutores'    => !empty($_POST['tutores']),
            'cocina'     => !empty($_POST['cocina']),
            'cuentas'    => isset($_POST['cuentas']) ? array_values(array_intersect(array_map('sanitize_key', (array) wp_unslash($_POST['cuentas'])), array_keys(Area_Profesores_Cuentas::roles()))) : array(),
        );
        update_option(self::OPTION, $all, false);
        self::back($key, __('Aviso guardado.', 'area-profesores'));
    }

    /**
     * Send the e-mail with example data to the current admin.
     */
    public function handle_prueba() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'area-profesores'));
        }
        $key = isset($_GET['correo']) ? sanitize_key($_GET['correo']) : '';
        check_admin_referer('ap_correos_prueba_' . $key);
        $defs = self::definiciones();
        if (!isset($defs[$key])) {
            wp_die('Correo no válido');
        }
        $yo = wp_get_current_user()->user_email;
        // Only to me (not to the extra recipients) and even if it is turned off.
        $c = self::get($key);
        $asunto = '[' . __('PRUEBA', 'area-profesores') . '] ' . self::reemplazar($c['asunto'], $defs[$key]['ejemplo']);
        $ok = wp_mail($yo, $asunto, self::html($asunto, self::reemplazar($c['cuerpo'], $defs[$key]['ejemplo']), $defs[$key]['boton']), array('Content-Type: text/html; charset=UTF-8'));
        self::back($key, $ok ? sprintf(__('Correo de prueba enviado a %s.', 'area-profesores'), $yo) : __('WordPress no ha podido enviar el correo de prueba (revisa la configuración de correo del servidor).', 'area-profesores'), $ok ? 'success' : 'error');
    }
}
