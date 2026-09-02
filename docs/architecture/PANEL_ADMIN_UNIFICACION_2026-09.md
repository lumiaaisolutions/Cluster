# Unificación del Panel de Administración y Rediseño del Dashboard (Septiembre 2026)

## 1. Contexto

Sesión de trabajo enfocada en llevar el patrón de diseño validado en `admin/banner-admin-mejorado.php` (página piloto, aprobada por el usuario) a los 9 módulos administrativos restantes, corregir bugs de layout descubiertos en el proceso, auditar el código en busca de inconsistencias, y finalmente rediseñar el header/navegación de `dashboard.html` (la página de inicio de los socios).

## 2. Replicación del patrón unificado a 9 páginas

**Páginas migradas** (sidebar unificado `.claut-admin-sidebar` + tarjetas `.claut-stat-card` con variantes de color + formularios convertidos a `.claut-wizard` multi-paso):

- `demo_boletines.html`, `demo_documentos.html`, `demo_descuentos.html`, `demo_comite.html`, `calendario.html`, `demo_empresas.html`, `demo_evento.html`, `gestionar_usuarios.php`, `demo_visitante.html`.

Cada página se procesó con un agente dedicado (misma plantilla exacta del piloto), preservando el 100% de los `id`/`name` de campos existentes y la lógica JS de cada formulario. Verificado mediante diff de IDs contra `git HEAD`: cero campos de formulario perdidos en todo el proceso.

**Bugs preexistentes encontrados y corregidos de paso** (no introducidos por esta migración, detectados al reconstruir el markup):
- `demo_boletines.html`: `<div>` sin cerrar.
- `demo_evento.html`: 2 `</div>` huérfanos.
- `demo_comite.html`: `<details>` de "Centro de Comunicación" sin cerrar + `id="loadingMensajes"` faltante (rompía el spinner de carga).
- `gestionar_usuarios.php`: comillas dobles sin escapar en dos scripts PHP (`setup-database.php`, `verificar-admin.php`) que rompían la sintaxis tras un primer intento de edición — corregido a comillas simples.

## 3. Bug crítico de layout — caché de `claut-ui.css`

**Síntoma reportado por el usuario**: las tarjetas de estadísticas de todos los módulos (excepto el piloto) se veían sin color — números y iconos en blanco/gris en vez de rojo/verde/dorado/azul.

**Causa raíz**: 23 páginas seguían enlazando `css/claut-ui.css?v=20260831f` — la versión *anterior* a que se agregaran las clases `.claut-stat-card--red/green/gold/blue` en esta misma sesión (agregadas bajo `?v=20260901a` en adelante). El querystring de caché nunca se actualizó en esas 23 páginas, así que el navegador servía la hoja de estilos vieja indefinidamente.

**Fix**: bump masivo de `?v=20260831f` → `?v=20260901d` en las 23 páginas afectadas + la página piloto (para homogeneizar). Regla reforzada: **cualquier cambio a un archivo CSS/JS compartido exige revisar `grep -r "archivo.css?v="` en todo `build/` y actualizar el querystring en cada página que lo referencie**, no solo en la página que se está editando.

## 4. Submódulos (pestañas) — nuevo componente `.claut-tabs`

`demo_comite.html` mostraba 4 secciones (Comités / Solicitudes / Mensajería / Admisiones) como acordeones `<details>` apilados verticalmente — el usuario pidió que no aparecieran "todo junto" sino organizadas en apartados navegables.

**Solución**: nuevo componente reutilizable en `css/claut-ui.css` (`.claut-tabs` / `.claut-tab-btn` / `.claut-tab-pane`) + `assets/js/claut-tabs.js` (detección automática de grupo de pestañas, sin configuración adicional). Aplicado a `demo_comite.html`, reemplazando los 4 `<details>` por pestañas con el mismo rojo/negro del sistema. Disponible para aplicarse a cualquier otra página con el mismo problema de "todo apilado".

## 5. Bug de layout — `demo_empresas.html` desfasado

**Síntoma**: la barra de controles ("Crear Nueva Empresa", filtros) y la tabla completa de empresas aparecían tapadas/desfasadas por el riel del sidebar.

**Causa raíz**: un `</div>` cerraba el contenedor `.claut-admin-main` justo después de las tarjetas de stats y el panel de "Solicitudes de Cambio", dejando toda la sección de controles + tabla (⁓100 líneas) **sin el margen del sidebar**, renderizando a ancho completo desde `x:0`.

**Fix**: se envolvió la sección huérfana en un nuevo `<div class="claut-admin-main container mx-auto px-6">`, verificado con conteo de `<div>` contra `git HEAD` (mismo desbalance preexistente de +1, no se introdujo ninguno nuevo).

## 6. Auditoría de código (enlaces rotos, funciones incompletas, código deprecado)

- **Enlaces internos** (sidebar de las 11 páginas admin): 0 rotos.
- **Llamadas a API inexistentes**: `demo_boletines.html` llamaba a `./api/fix_boletines_table.php` y `./api/test_db.php` (ninguno existe en el servidor) desde un panel de diagnóstico que ya no existe en el HTML — ~150 líneas de JS 100% muertas (`testAPI()`, `fixTableStructure()`, `testDBConnection()`, `clearAPIResult()`), eliminadas.
- **Función duplicada**: `deleteBulletin()` estaba declarada dos veces en `demo_boletines.html` (JS solo ejecuta la segunda, la primera era código muerto sin refrescar `updateStatistics()` ni usar el sistema de notificaciones actual). Se eliminó la versión obsoleta.
- **Pendiente, fuera del alcance de esta sesión**: `toggleUserMenu`/`handleLogout` declaradas dos veces en `profile.html` y `boletines.html` (no se investigó ni corrigió — anotar para una próxima pasada).

## 7. Rediseño del header/navegación de `dashboard.html`

### 7.1 Nuevo componente: riel de íconos flotante

Reemplaza el menú horizontal (`.claut-header-nav`) **solo en escritorio** (≥1025px, mismo breakpoint que ya usaba el sistema para alternar con el menú curvo inferior `.claut-bottom-nav`, que sigue intacto para móvil/tablet ≤1024px — no se duplicó lógica de navegación móvil).

**Archivos nuevos**:
- `assets/css/layout/claut-icon-rail.css` — `.claut-icon-rail` / `.claut-icon-rail-item`, position fixed, 100% transparente (sin fondo propio), flota directamente sobre el contenido (decisión explícita del usuario: sin desplazar el contenido). Tooltip con el nombre de la sección al pasar el mouse.
- `assets/js/claut-icon-rail.js` — marca automáticamente el ítem activo según la URL actual (sin hardcodear `class="active"` por página).

**Bug encontrado y corregido durante la implementación**: una regla legacy muy amplia en el `<style>` inline de `dashboard.html` —
```css
nav, .navbar, header { z-index: 99997 !important; position: relative !important; }
```
— capturaba *cualquier* `<nav>` nuevo de la página (estaba pensada para un navbar específico, nunca se acotó con una clase). Rompía el `position: fixed` del riel nuevo. Se blindó `.claut-icon-rail` con `!important` propio (specificity de clase > selector de elemento, por lo que gana incluso entre dos reglas `!important`). **Regla para el futuro**: cualquier `<nav>` nuevo en `dashboard.html` debe usar una clase con `!important` en sus propiedades de posicionamiento por esta razón.

### 7.2 Fondo desfasado — causa raíz real

El fondo fijo detrás de todo el contenido (`.image-motion`, animado con GSAP ScrollTrigger en un efecto de "despliegue" 3D al hacer scroll) usaba `css/moveimage.png`: una imagen abstracta con tonos **rosa/naranja/café**, sin relación alguna con la paleta roja/negra de la marca — de ahí el "desfase de colores" reportado.

**Fix**: se reemplazó el `<img>` por un degradado CSS puro en la paleta correcta (`radial-gradient` rojo `#C7252B` sobre negro `--claut-bg-main`), conservando el mismo contenedor `.image-motion` para que la animación GSAP de scroll siga funcionando sin cambios de JS.

### 7.3 Logo más grande

`.claut-header-logo img` pasó de 32×32px a 44×44px (override scoped a `dashboard.html` únicamente, no afecta las otras 7 páginas que comparten `.claut-header`).

### 7.4 Distribución de contenido / límites de sección

Con el fondo corregido, las tarjetas `.glass-card` existentes (ej. sección "Agenda") vuelven a distinguirse claramente del fondo — el problema de "no tiene correcta distribución" era en gran parte un efecto del fondo desfasado ocultando los bordes/contraste de las tarjetas, no una falta de estructura.

## 8. Verificación

- Riel de íconos: confirmado `position:fixed` (se mantiene visible al hacer scroll), tooltips funcionando, ítem activo marcado correctamente por página.
- Fondo: confirmado visualmente sin tonos rosa/naranja en las 3 secciones principales (hero, banners, agenda).
- Calendario de vista previa (`dashboard-preview-calendar`, FullCalendar): renderiza correctamente sobre el nuevo fondo.
- Las 7 páginas que comparten `.claut-header`/`.claut-header-nav` (`profile.html`, `eventos.html`, `descuentos.html`, `comites.html`, `boletines.html`, `empresas-convenio.html`, `contacto.html`) quedaron **sin ningún cambio** — verificado que ninguna referencia los archivos nuevos.
- Nota de entorno: la automatización de navegador de esta sesión no pudo forzar un viewport móvil real para verificación visual (limitación de la herramienta, no del código) — el comportamiento móvil se apoya en el mismo breakpoint (`max-width:1024px`) y mecanismo (`.claut-bottom-nav`) que el sistema ya usaba antes de este cambio, sin modificaciones.

## 9. Pendiente para una próxima sesión

- Aplicar el mismo riel de íconos a las otras 7 páginas de socio si se desea consistencia total (hoy solo `dashboard.html` lo tiene, por alcance explícito del pedido).
- Investigar y resolver las funciones duplicadas `toggleUserMenu`/`handleLogout` en `profile.html` y `boletines.html`.
- Continuar la Fase F pendiente: migración completa de `.claut-skin` a clases nativas del sistema de diseño.
