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

5. **Shortcode de Cocina `[comedorhoy]`**:
   - Página abierta (sin necesidad de login) pensada para el personal de cocina, catering e inspección.
   - Tipografía grande y cómoda, adaptada a teléfonos móviles y tablets.
   - Alerta destacada de **Alérgicos ausentes** para no preparar menús especiales innecesariamente.
   - Resumen agrupado de dietas especiales y desglose completo clase por clase.
   - Navegación rápida por fechas y botón **"Imprimir para Cocina"**.

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

2. **Crear la Página de Login:**
   - Ve a **Páginas > Añadir nueva** (ej: *Acceso Profesores*).
   - Pega el shortcode:
     ```text
     [loginprofesores]
     ```
   - Publica la página y selecciónala en **Área Profesores > Ajustes** como tu página de login.

3. **Crear la Página del Área Docente:**
   - Ve a **Páginas > Añadir nueva** (ej: *Área Profesores*).
   - Pega el shortcode:
     ```text
     [area_profesores]
     ```
   - Publica la página.

4. **Crear la Página de Cocina:**
   - Ve a **Páginas > Añadir nueva** (ej: *Comedor Diario*).
   - Pega el shortcode:
     ```text
     [comedorhoy]
     ```
   - Publica la página y comparte el enlace con el equipo de cocina.
