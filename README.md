# Área Profesores - Colegio Casa de la Virgen

Plugin a medida para WordPress desarrollado para el **Colegio Casa de la Virgen**. Integra el Área Docente corporativa, autenticación con Nextend Social Login (sin logo de Google), gestión de solicitudes de acceso con pre-aprobación, control de tutorías y registro diario de comedor escolar.

---

## 🚀 Características Principales

1. **Shortcode de Acceso y Solicitud `[loginprofesores]`**:
   - Tarjeta institucional con la **Tribanda corporativa superior** y el **Logotipo oficial** de Casa de la Virgen.
   - **Botón institucional**: *"Accede usando tu cuenta de profesor"* (conectado al motor de autenticación Google de Nextend Social Login, sin logotipo de Google para evitar confusiones).
   - **Solicitud de acceso**: Formulario integrado para que los nuevos profesores soliciten su alta indicando su nombre, apellidos, cuenta corporativa `@casadelavirgen.es`, tutoría deseada y observaciones.
   - Envío asíncrono vía AJAX con confirmación inmediata.

2. **Gestión en el Backend (`Área Profesores`)**:
   - Menú renovado en WordPress: **Área Profesores**.
   - **Profesores**: Listado del claustro con interruptores rápidos para marcar profesores y asignar tutorías (1ºA a 6ºB o especialista).
   - **Solicitudes de Acceso**: Bandeja de entrada con **contador de pendientes** (*badge* en el menú). Permite **Aprobar** con un clic (vinculando la cuenta y enviando un email de bienvenida al docente) o **Rechazar**.
   - **Comedor Diario**: Resumen en tiempo real de comensales, dietas y alérgicos ausentes, con exportación a CSV e impresión.
   - **Ajustes**: Configuración del dominio corporativo (`casadelavirgen.es`), URLs de tribanda y logo, correos de notificación para dirección y selección de páginas.

3. **Notificaciones Automáticas por Email**:
   - Cada vez que un profesor solicita acceso, la dirección del centro recibe un correo formateado con todos los datos y un botón directo para revisar la solicitud en el panel de WordPress.
   - Al aprobar la solicitud, el profesor recibe un correo confirmándole que ya puede entrar con su cuenta.

4. **Shortcode del Área Docente `[area_profesores]`**:
   - Panel restringido accesible únicamente para usuarios aprobados como profesores.
   - Formulario de **"Apuntar Comedor"** con preselección automática de la tutoría del docente, recuento de alumnos, dietas especiales y aviso de alérgicos ausentes.
   - Pestaña **"Mis Registros de Hoy"** para editar o consultar envíos realizados durante la jornada.

5. **Restricción de acceso a toda la web**:
   - Con la opción *Restringir toda la web* (activada por defecto en **Área Profesores > Ajustes**), solo los usuarios registrados con sesión iniciada pueden ver cualquier página del sitio.
   - Los visitantes sin sesión son redirigidos a la **Página de Login** configurada (con `redirect_to` para volver a la página que querían ver). Si no hay página configurada se usa `wp-login.php`.
   - También se bloquean la API REST y los feeds RSS para visitantes anónimos.
   - Se pueden marcar **páginas públicas** como excepción (la página de login siempre es pública).

6. **Enlaces Útiles** (*Área Profesores > Enlaces Útiles*):
   - Alta, edición, ocultación y borrado de enlaces desde el backend: título, URL, descripción, categoría, icono (emoji), orden, destacado, abrir en pestaña nueva y visibilidad (todos / solo tutores / solo especialistas).
   - Los profesores los ven en la pestaña **Enlaces Útiles** de su área, agrupados por categoría, con los destacados arriba como *Accesos rápidos* y un buscador cuando hay muchos.
   - Se puede abrir directamente la pestaña con `#enlaces` al final de la URL del Área de Profesores.

7. **Inicio tras el login y experiencia del profesor** (*Ajustes*):
   - Página de inicio tras iniciar sesión (o URL personalizada), con opción de volver a la página que el profesor intentaba abrir.
   - Al cerrar sesión se vuelve a la página de login.
   - Aviso destacado configurable en la parte superior del Área de Profesores.
   - Ocultar la barra de WordPress y bloquear `/wp-admin` a los profesores (no afecta a administradores ni editores).

8. **Shortcode de Cocina `[comedorhoy]`**:
   - Pensada para el personal de cocina, catering e inspección. Si la restricción global está activa, márcala como **página pública** en *Ajustes* para que se pueda ver sin login.
   - Tipografía grande y cómoda, adaptada a teléfonos móviles y tablets.
   - Alerta destacada de **Alérgicos ausentes** para no preparar menús especiales innecesariamente.
   - Resumen agrupado de dietas especiales y desglose completo clase por clase.
   - Navegación rápida por fechas y botón **"Imprimir para Cocina"**.

9. **Comunicados a familias en Google Chat** (*Área Profesores > Espacios de Chat*):
   - Publica **el mismo comunicado (texto + imagen) en varios espacios de Google Chat a la vez**, firmado como *Colegio Casa de la Virgen*, **ahora o programado** para un día y hora.
   - Solo pueden publicar los profesores marcados como **Dirección** (columna *Dirección* en *Área Profesores > Profesores* o en el perfil del usuario) y los administradores.
   - Pestaña **Comunicados** en el Área de Profesores: título, mensaje con formato de Chat (`*negrita*`, `_cursiva_`, `~tachado~`), imagen JPG/PNG/GIF (máx. 8 MB), espacios agrupados por etapa (con "Todo el colegio" y casilla por etapa), vista previa y programación (atajos *Mañana 8:00*, *Lunes 8:00*, *Hoy 17:00*).
   - **Programados**: se pueden editar, enviar ya o cancelar. Si al enviarse falla algún espacio, el autor recibe un email.
   - **Historial** con el resultado por espacio, **reintento de los fallidos** y **Usar como plantilla**.
   - Cada espacio de familias tiene su **promoción**, su grupo y el **curso asignado**. El curso **no cambia solo**: se cambia con **Aulas › Pasar de curso**.
   - El plugin comprueba que cada webhook es del espacio correcto y avisa de los cursos sin espacio, de la zona horaria y de HTTPS.

10. **Etapas y tutorías del profesorado**:
   - **Todos los usuarios de la web son profesores** (ya no hay casilla "¿Es profesor?"): el acceso lo controlan las solicitudes y el login con la cuenta del colegio.
   - Cada profesor es **tutor/a de un curso** (su etapa es la del curso) o **especialista**. Los especialistas **no tienen una etapa fija**: con el botón *Clases* de *Profesores* se les asignan clases de una o varias etapas (Primer Ciclo, Infantil, Primaria, Secundaria), y esas son sus etapas (para "Mis alumnos", los enlaces por etapa y el filtro de etapa). Se asigna con un único desplegable en *Profesores*, en el perfil, en las *Solicitudes de acceso* y en el formulario de solicitud del login.
   - Cursos: 1 año A/B, 2 años A/B, 3-5 años A/B, 1º-6º EP A/B, 1º-4º ESO A/B, 1º y 2º Bachillerato.
   - Filtro por etapa en *Profesores*; *Apuntar Comedor* muestra los cursos de la etapa del profesor (con su curso preseleccionado y la etapa rellenada sola); los *Enlaces Útiles* pueden limitarse a una etapa.
   - Las tutorías antiguas (1A…6B) se convierten automáticamente a 1º-6º EP A/B de Primaria.
   - En *Profesores* se puede **eliminar** a un usuario (no a administradores ni a uno mismo); sus comunicados pasan a quien lo elimina.

11. **Aulas** (*Área Profesores > Aulas*):
   - Tabla editable con cada aula, su **tutor/a** y su **espacio de familias** (estado del webhook, enlace y nombre en Google Chat).
   - **Pasar de curso…**: muestra antes una lista con el curso actual y el curso nuevo de cada espacio (editable), avisa de cursos repetidos o sin espacio y, si hay conexión con Google, el **nuevo nombre del espacio en Google Chat**. Se puede **deshacer**.
   - **Conexión con Google Chat (opcional)**: con la API de Google Chat y una cuenta de administrador de Workspace se pueden **leer y cambiar los nombres de los espacios** (plantilla configurable, p. ej. `{curso} (PROMOCIÓN {promocion})`).

12. **Espacios de aula** (pestaña del Área de Profesores): todos los espacios de Google Chat de las familias, por etapa y curso, con el tutor/a, un buscador y la clase del propio tutor/a destacada arriba ("Tu clase"). Al final, las promociones anteriores (plegadas). La página para las familias está ahora en la web de familias (plugin **Área Familias**).

15. **Alumnos y familias** (*Área Profesores > Alumnos y familias*):
   - Base de datos de **alumnos** (curso, alta/baja), **familias** (correos familiares, normalmente una cuenta compartida por los padres) con sus **padres/tutores**, cada uno con **su teléfono de emergencia** y su dirección. Un alumno puede estar en dos familias (padres separados, con una segunda cuenta).
   - **Importación del "Listado de familias" de Alexia** tal cual (.xls, también .xlsx/.csv), una etapa cada vez (Infantil, Primaria, ESO, Bachillerato), con **vista previa** de altas, cambios y posibles bajas antes de aplicar. Reimportar el mismo fichero no duplica nada. Lector de Excel propio, sin librerías externas.
   - Edición en cualquier momento (alumnos, familias, vincular a otra familia, bajas) e **historial** de cambios (backend, importación o la propia familia).
   - Si la familia cambia un teléfono o una dirección desde el Área Familias, **se actualiza al momento** aquí y en "Mis alumnos", y se avisa por correo al tutor/a y a Secretaría (para cambiarlo en Alexia). Mientras Alexia tenga los datos antiguos, la importación no los deshace; cuando coinciden, todo vuelve a la normalidad solo.
16. **Mis alumnos** (pestaña del Área de Profesores): el tutor/a ve su clase; los especialistas, las clases que se les asignan (botón *Clases* en *Profesores*), de cualquier etapa; Dirección y **Secretaría** (nueva columna en *Profesores*), todos los cursos. Cada padre/madre en su línea con su propio teléfono de emergencia (sin direcciones), buscador e impresión.
17. **API para el Área Familias**: hijos y datos de la familia que ha iniciado sesión, y actualización del teléfono de emergencia y la dirección de cada padre/madre, con aviso por correo al tutor/a y a Secretaría.
18. **Avisos** (*Área Profesores > Avisos*, antes "Correos automáticos"): una tabla con **todos los avisos que la web envía por correo**, agrupados (Profesores, Alumnos y familias, Comedor, Reservas, Comunicados), a quién se envían siempre y a qué **cuentas especiales** van. En cada aviso se marcan las cuentas especiales que lo reciben (Dirección, Secretaría, Cocina, Portería, Administración, Comunicación), los tutores cuando corresponde, otros correos, si se envía o no, y su asunto y texto con variables como `{nombre}` o `{cambios}`; también se puede enviar una prueba. Si un aviso no tiene a nadie, llega al administrador de la web.
19. **Reservas de las familias** (servicios que las familias reservan en el Área Familias: ampliación de horario con o sin desayuno, comedor con o sin dieta, ludoteca, dieta, salida anticipada y retraso justificado):
   - Se guardan aquí (una por alumno) y se avisa por correo al tutor/a, a Secretaría, a los correos de aviso del servicio y a la familia (confirmación). Si el tutor/a responde al correo, le llega a la familia.
   - *Área Profesores > Reservas de familias*: vista por día (agrupada por servicio, para imprimir) y listado completo con filtros, descarga en CSV y botón para anular (avisa a la familia).
   - Pestaña **Reservas** del Área Docente: las reservas de los alumnos de cada profesor (todos los cursos para Dirección y Secretaría), por día, con los próximos días de un vistazo.
   - El listado de cocina (`[comedorhoy]`) muestra el comedor, las dietas y los desayunos reservados por las familias para ese día.
   - Los servicios, horarios, plazos y preguntas se configuran en el Área Familias (*Área Familias > Reservas de servicios*).
20. **Cuentas especiales** (*Área Profesores > Cuentas especiales*): se eligen las cuentas de **Dirección** (comunicados y todos los alumnos), **Secretaría** (todos los alumnos y pestaña *Comensales*), **Cocina**, **Portería** (pestaña *Reservas* de todos los cursos), **Administración** (pestaña *Comensales*) y **Comunicación** (pestaña *Comunicados*, igual que Dirección). Qué correos recibe cada una se elige en *Avisos*. Ya no se marcan en la lista de *Profesores*.
21. **Comedor diario por alumnos** (solo Primer Ciclo, Infantil y Primaria; Secundaria y Bachillerato no lo ven):
   - Pestaña **Comedor**: el tutor/a ve la lista de su clase con los **comensales fijos** ya marcados (cada alumno con los días de la semana que se queda), marca las excepciones (un alumno que se queda un día = **extra**; un fijo que hoy no se queda) y **valida**. Si no hay cambios, basta con validar.
   - Los alumnos cuya familia ha reservado comedor en el Área Familias aparecen marcados con "Reserva de la familia" (y su dieta, si la piden) y se suman; si la reserva llega después de validar, se suma sola.
   - **Poner el comedor de un compañero**: cualquier profesor de estas etapas puede poner o validar el comedor de otra clase (queda registrado quién lo valida).
   - A las **10:30** se avisa por correo a los tutores que no han validado; a las **12:00** se envía a **Cocina** el resumen (total, por clases, clases sin validar, dietas y observaciones), y después cada cambio. Horas, avisos y **días sin clase** en *Comedor Diario* (los días sin clase también los usa el Área Familias).
   - La página de cocina (`[comedorhoy]`) se actualiza sola en cuanto un profesor valida o una familia reserva, y muestra las clases pendientes.
   - Pestaña **Administración** (antes *Comensales*; Secretaría, Administración y Dirección): por meses, comidas de cada día y **comidas extra de cada alumno** (las marcadas por el profesor y las reservadas por la familia) con sus días, para hacer los cobros; descarga para Excel.
   - En *Comensales fijos* hay botones para **marcar o desmarcar a todos** (toda la semana), además de marcar por alumno o por día.
22. **Alergias** (ficha de cada alumno en *Alumnos y familias*): **alergias alimentarias** y **no alimentarias** por separado.
   - Las alimentarias aparecen en la lista del comedor de la clase (⚠️) y en cocina: en la página `[comedorhoy]` y en el correo de las 12:00 salen los comensales con alergia alimentaria y, destacado en rojo, los **comensales habituales con alergia alimentaria que hoy NO comen** (para no preparar su menú especial). Si cambia después del resumen, Cocina recibe el aviso.
   - En **Mis alumnos** los profesores ven las dos alergias y pueden **editar** las alergias y el nombre y el teléfono de emergencia de los padres (o añadir otro). Es obligatorio escribir el **motivo**, que se envía a **Secretaría** en el correo "Un profesor cambia datos de un alumno o su familia" (en *Avisos* se elige quién más lo recibe). Todo queda en el *Historial*.
23. **Comidas del profesorado** (pestaña *Comedor*, recuadro *Mi comida en el colegio*): se da por hecho que todos los profesores comen en el colegio; cada uno marca los días que **no** come (hoy con un botón, o cualquier día de las próximas 5 semanas). El resumen diario a cocina incluye **cuántos profesores no comen y quiénes** (variable `{profesores}`), la página de cocina lo muestra y, si alguien cambia el día de hoy después del resumen, cocina recibe el aviso *Un profesor cambia su comida de hoy después del resumen*. En la pestaña *Comensales* (Dirección, Secretaría y **Administración**) se ven los días de cada profesor por mes, también en el Excel.
24. **Ayuda**: pestaña con el manual de las pestañas que ve cada profesor y botón *Manual completo (imprimir o PDF)*, que abre el manual entero listo para imprimir o guardar en PDF y repartir al claustro.
25. **Reservas desde Portería** (pestaña *Reservas*, cuentas de *Portería*): botón *Nueva reserva para una familia* para reservar cualquier servicio a cualquier alumno cuando los padres lo piden en persona o por teléfono (buscador de alumnos, hermanos, servicio, uno o varios días y las mismas preguntas que en el Área Familias, sin plazos). Queda anotado *Reservada por: Portería (nombre) — lo ha pedido: …*, se avisa igual que una reserva de la familia, la familia puede recibir la confirmación y la ve en su Área Familias. Portería también puede **anular** reservas. El Área Familias envía al Área de Profesores la configuración de sus servicios (al guardarla y cada hora si cambia).
26. **Comensales por comedor** (página de cocina `[comedorhoy]` y correo del resumen, variable `{comedores}`): total de cada comedor y de cada curso, con el detalle de cada grupo (A/B) y las clases que faltan por validar. **Comedor 1**: Primer Ciclo y 3 años · **Comedor 2**: 4 años, 5 años y 1º EP · **Comedor 3**: 2º a 6º EP (se cambia en `Area_Profesores_Comensales::comedores()`). Secundaria y Bachillerato no entran.
27. **Comedor extra registrado como servicio**: cuando al validar el comedor el tutor/a apunta a un alumno que ese día no es fijo ni tiene reserva de la familia, se registra como una **reserva de comedor de ese día** (origen *profesor*), que la familia ve en su Área Familias. Avisos: *Un profesor apunta alumnos extra al comedor* (al profesor que lo apunta como confirmación y, de serie, a **Administración**) y *Aviso a la familia: su hijo/a come hoy en el colegio*. Si se desmarca, la reserva se anula.
28. **Pestaña Administración** (sustituye a *Comensales*; Administración, Secretaría y Dirección), por meses y en dos bloques: **1. Comidas de los alumnos para cotejar con cocina** (cada día por Comedor 1, 2 y 3, extras, clases validadas y profesores que no comen, con totales) y **2. Cobros a las familias** (una línea por familia con lo que hay que cobrarle; al desplegarla, cada alumno, servicio y días, y cómo se pidió). El comedor se cobra por las comidas extra reales; salidas y retrasos no se cobran. *Descargar (Excel)* trae los cobros (una línea por alumno y servicio) y las comidas por día.
29. **Reservas desde los tutores**: el mismo formulario de Portería (*Nueva reserva para una familia*, pestaña *Reservas*) lo tienen los profesores para los alumnos de su tutoría y clases asignadas (Dirección y Secretaría, todos), para cuando los padres lo piden de palabra o por correo. Queda anotado *Reservada por: Profesor/a (nombre)*; cada profesor puede anular las reservas que ha hecho él.
30. **Remitente de los correos**: los avisos a las familias llegan como *Área de Familias* y el resto como *Área de Profesores* (en lugar de *WordPress*). Se ve en cada aviso de *Avisos*.
31. **Capturas en la Ayuda**: el manual (pestaña *Ayuda* y versión para imprimir) lleva capturas de pantalla con datos ficticios (`assets/img/ayuda/`).
32. **Profesores que comen** (*Área Profesores › Profesores*, casilla *Come en el colegio*, marcada por defecto): se cuenta que todos los profesores comen todos los días salvo los días que ellos avisan en *Mi comida* (ahora disponible para todo el profesorado, también Secundaria y Bachillerato). Los desmarcados no comen nunca y no cuentan. Cocina ve cada día *Hoy comen N profesores* (página y correo) y Administración tiene la columna *Profesores que comen* para cotejar con cocina.
33. **PDF en los comunicados** (por ejemplo, el menú): además de una imagen se puede adjuntar un PDF de hasta 20 MB. Como los webhooks de Google Chat no permiten adjuntar archivos, el PDF se guarda en la web y en el espacio aparece una tarjeta con la primera página (si el servidor tiene Imagick y Ghostscript) y el botón *Abrir el PDF*.
34. **Publicar con la API de Google Chat** (*Área Profesores › Aulas › Cuentas para publicar los comunicados*): se conecta una cuenta del colegio **compartida** (p. ej. colegio@, la usan Dirección y Comunicación sin conocer su contraseña) y cuentas **personales** (solo las ve quien las conecta). En *Comunicados* se elige *Publicar como*; el mensaje sale enviado por esa cuenta y la imagen o el PDF van **adjuntos y guardados en Google**; después se borran de la web. La cuenta compartida se añade sola a los espacios nuevos (y con el botón *Añadir a todos los espacios*), usando la conexión de administrador con el permiso `chat.admin.memberships`. Sin cuenta conectada se sigue usando el webhook de cada espacio, que también es el último recurso si la API falla en un espacio.

13. **Envío automático (cron)**: el plugin revisa cada 5 minutos si hay comunicados programados. En *Espacios de Chat* se ve si el cron funciona y el comando para crearlo en cPanel:
   `wget -q -O /dev/null "https://TU-WEB/wp-cron.php?doing_wp_cron" >/dev/null 2>&1`

---

## 📦 Instalación

1. Sube o clona la carpeta `Area-Profesores` en el directorio de plugins:
   `wp-content/plugins/area-profesores`
2. En el panel de WordPress > **Plugins**, activa **Área Profesores - Colegio**.
3. El plugin creará automáticamente las tablas necesarias en la base de datos:
   - `wp_ap_comedor_registros`
   - `wp_ap_solicitudes_acceso`

---

## ⚙️ Configuración Inicial Paso a Paso

1. **Ajustes Generales:**
   - Ve a **Área Profesores > Ajustes**.
   - Verifica que el dominio corporativo sea `casadelavirgen.es`.
   - Comprueba las URLs de la tribanda y el logotipo.
   - Añade el correo o correos donde la dirección desea recibir las alertas de nuevas solicitudes de docentes.

2. **Crear las páginas (sin shortcodes):**
   - Crea y publica tres páginas vacías, por ejemplo *Acceso Profesores*, *Área Profesores* y *Comedor Diario*.
   - En **Área Profesores > Ajustes > Páginas y Redirecciones del Sistema** selecciona cada una como *Página de Login*, *Página del Área de Profesores* y *Página de Comedor / Cocina*.
   - El plugin inyecta automáticamente la pantalla correspondiente en cada página; no hace falta pegar ningún shortcode (el contenido del editor se sustituye).
   - Si la página de cocina debe verse sin iniciar sesión, márcala en *Páginas públicas*.
   - Los shortcodes `[loginprofesores]`, `[area_profesores]` y `[comedorhoy]` siguen funcionando por compatibilidad.
