# Área Profesores - Plugin para WordPress

Plugin a medida para colegios y centros educativos que integra el **Área de Profesores** directamente dentro de la web escolar (o el Área Familias), con control de acceso por usuario, asignación de tutorías y gestión diaria de comedor escolar.

---

## 🚀 Características Principales

1. **Shortcode Principal `[area_profesores]`**:
   - Genera una interfaz completa, moderna, limpia y elegante para los docentes.
   - Si el usuario no ha iniciado sesión, muestra un aviso amigable invitándole a identificarse.
   - Si el usuario ha iniciado sesión pero **no** está marcado como profesor, bloquea el acceso mostrando la pantalla de **Área Restringida**.
   - Si el usuario es un profesor autorizado, accede directamente a su panel docente.

2. **Gestión en el Backend (`Área Familias > Profesores`)**:
   - Menú propio en el panel de WordPress llamado **Área Familias** con la sección **Profesores**.
   - Listado de todos los usuarios registrados con buscador y filtros.
   - Interruptores rápidos (conmutadores AJAX) para marcar/desmarcar a cualquier usuario como **Profesor**.
   - Selector para asignar la **Tutoría** a cada profesor (1ºA, 1ºB, 2ºA, 2ºB, 3ºA, 3ºB, 4ºA, 4ºB, 5ºA, 5ºB, 6ºA, 6ºB o "Sin tutoría / Especialista").
   - Los cambios se guardan al instante vía AJAX y también con botón de guardado masivo.
   - También se integra en la pantalla estándar de edición de usuario de WordPress.

3. **Funcionalidad 1: "Apuntar Comedor"**:
   - Diseñado siguiendo exactamente los campos requeridos:
     - **Clase \***: 1A, 1B, 2A, 2B, 3A, 3B, 4A, 4B, 5A, 5B, 6A, 6B (si el profesor tiene asignada una tutoría, se preselecciona automáticamente).
     - **Etapa \***: Primer Ciclo, Infantil, Primaria.
     - **Alumnos que se quedan a comer (Sin incluir las dietas) \***: Selector numérico con botones `+` y `−`.
     - **Dietas**: Nombres y curso de alumnos con alergias, intolerancias o menús especiales.
     - **Aviso "MÁS DATOS"**: Indicación visual destacada para cocina.
     - **Alérgicos que faltan**: Registro de ausencias para no preparar menús especiales innecesariamente y observaciones extraordinarias.
   - Envío asíncrono con AJAX sin recargar la página.
   - Pestaña de **"Mis Registros de Hoy"** donde el docente puede revisar, editar o eliminar los envíos realizados durante el día.

4. **Shortcode de Cocina `[comedorhoy]` (Página Pública de Comedor)**:
   - Genera una página accesible para cualquier persona (incluso sin iniciar sesión), pensada para el personal de cocina, catering y monitores.
   - **Métricas inmediatas**: Total de comensales estándar, clases con dietas, alérgicos que faltan y clases reportadas.
   - **Alerta de Alérgicos Ausentes**: Bloque de advertencia destacado en rojo para que en cocina sepan qué menús especiales NO preparar hoy.
   - **Resumen de Dietas**: Bloque agrupado con todas las dietas notificadas por los tutores.
   - **Desglose Clase por Clase**: Tarjetas con números grandes y tipografía cómoda de leer en cocina.
   - **Navegación por Fechas**: Selector de fecha táctil y botones rápidos "Día anterior", "Hoy" y "Día siguiente".
   - **Botón Imprimir**: Hoja limpia adaptada a papel para colocar en el tablón de cocina.
   - **100% Adaptado a Móviles y Tablets**: Diseñado para consultarlo cómodamente desde el móvil o tablet en cocina.

5. **Panel de Cocina y Dirección en el Backend (`Área Familias > Comedor Diario`)**:
   - Resumen diario en tiempo real con estadísticas: Total comensales estándar, clases reportadas, clases con dietas y ausencias.
   - Selector de fecha para consultar días pasados.
   - Botón para **Exportar a CSV / Excel**.
   - Botón para **Imprimir Resumen** adaptado con vista de impresión limpia para el personal de cocina.

---

## 📦 Instalación

1. Copia o sube la carpeta `Area-Profesores` al directorio de plugins de WordPress:
   `wp-content/plugins/area-profesores`
2. Ve al panel de administración de WordPress > **Plugins** y activa **Área Profesores - Colegio**.
3. Al activarse, creará automáticamente la tabla necesaria en la base de datos (`wp_ap_comedor_registros`).

---

## ⚙️ Configuración Paso a Paso

1. **Configurar los profesores:**
   - Ve a **Área Familias > Profesores** en el menú lateral izquierdo de WordPress.
   - Localiza a los usuarios que pertenezcan al claustro.
   - Activa el interruptor en la columna **¿Es Profesor?**.
   - Selecciona el curso que tutoriza en **Tutoría Asignada** (o déjalo en *-- Sin tutoría --* si es especialista o de apoyo).
2. **Crear la página del Área Profesores:**
   - Ve a **Páginas > Añadir nueva**.
   - Ponle el título que desees (ej: *Área Profesores* o dentro de tu página de *Área Familias*).
   - Añade el shortcode:
     ```text
     [area_profesores]
     ```
   - Publica la página.
3. **Crear la página para Cocina:**
   - Ve a **Páginas > Añadir nueva**.
   - Ponle el título que desees (ej: *Servicio de Comedor Hoy* o *Cocina Diario*).
   - Añade el shortcode:
     ```text
     [comedorhoy]
     ```
   - Publica la página y comparte el enlace directamente con el personal de cocina (pueden acceder sin necesidad de usuario ni contraseña).

---

## 🧩 Arquitectura Modular

El plugin está estructurado para añadir fácilmente las próximas funcionalidades (ej: partes de incidencia, tutorías con familias, inventario):
- `area-profesores.php`: Archivo principal e inicialización.
- `includes/class-admin.php`: Menús de administración y metadatos de usuario.
- `includes/class-comedor.php`: Lógica de base de datos y endpoints para el comedor.
- `includes/class-shortcode.php`: Gestión del shortcode y control de acceso.
- `templates/`: Plantillas desacopladas de vista (formulario, panel, restringido y admin).
- `assets/`: Hojas de estilo y scripts limpios e interactivos.
