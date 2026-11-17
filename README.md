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
   - Publica **el mismo comunicado (texto + imagen) en varios espacios de Google Chat a la vez**, firmado como *Colegio Casa de la Virgen*.
   - Solo pueden publicar los profesores marcados como **Dirección** (columna *Dirección* en *Área Profesores > Profesores* o en el perfil del usuario) y los administradores.
   - Pestaña **Comunicados** en el Área de Profesores: título, mensaje con formato de Chat (`*negrita*`, `_cursiva_`, `~tachado~`), imagen JPG/PNG/GIF (máx. 8 MB), selección de espacios por grupo (Infantil, Primaria…) y vista previa.
   - Tras publicar se muestra el resultado de cada espacio, con botón para **reintentar solo los que han fallado**, y un historial de los últimos comunicados.
   - Cada espacio se conecta con un **webhook entrante** de Google Chat. El texto va en el mensaje (aparece en la notificación del móvil) y la imagen debajo, en una tarjeta.

### Conectar los espacios de Google Chat

1. **Consola de administración de Google** (una sola vez): *Aplicaciones › Google Workspace › Google Chat › Ajustes de las aplicaciones de Chat* y activa **"Permitir que los usuarios añadan y usen webhooks entrantes"**.
2. En cada espacio de familias (desde el ordenador): nombre del espacio › **Aplicaciones e integraciones › Webhooks › Añadir webhooks**. Nombre: `Colegio Casa de la Virgen`; avatar: URL del logotipo.
3. Copia la URL del webhook y dala de alta en **Área Profesores > Espacios de Chat** (nombre, grupo y orden). Pulsa **Probar** para comprobar que llega el mensaje (la prueba incluye el logotipo para verificar que las imágenes se ven).
4. Marca como **Dirección** a quien pueda publicar en *Área Profesores > Profesores*.

> La URL del webhook es secreta: quien la tenga puede publicar en el espacio. Las imágenes se guardan en la Biblioteca de medios y Google las descarga desde `wp-content/uploads`, por lo que la web debe servirse por **HTTPS**.

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
