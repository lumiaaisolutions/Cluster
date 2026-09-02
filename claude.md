# 🧠 Memoria del Proyecto: Clúster Intranet (v2.3)

## 🎯 Visión General
Plataforma integral para la gestión de socios y empresas del Clúster Metropolitano. El objetivo es proporcionar una interfaz premium (Glassmorphism/Apple-inspired) que permita a los administradores gestionar el directorio y a los socios actualizar su propia información mediante un sistema de solicitudes de revisión.

## 🛠️ Comandos y Flujo de Trabajo
- **Despliegue**: Los cambios se realizan en la carpeta `/build/` para pruebas antes de ser sincronizados al servidor de producción vía FTP/SFTP por el usuario.
- **Entorno Local**: `/Users/fernandotorres/Desktop/Claut_BD/`
- **URLs Críticas**:
  - `demo_empresas.html`: Panel administrativo de empresas.
  - `profile.html`: Perfil del socio y gestión de "Mi Empresa".
  - `api/empresas-simple.php`: Backend principal de empresas.
  - `api/perfil_empresa.php`: Gestión de datos desde el perfil.

## 🎨 Guía de Estilo (Aesthetics)
- **Core**: Vanilla JS + Tailwind CSS.
- **Diseño**: Glassmorphism (transparencias, desenfoques en fondo).
- **Colores**:
  - Rojo Clúster: `#C7252B` (Primario)
  - Fondo: Oscuro (`#1D1D1F`) con gradientes.
  - Acentos: Oro/Amarillo para estados destacados.
- **Tipografía**: Inter / Open Sans.

## 🔒 Seguridad y Acceso
- **Roles**: `admin`, `Administrador`, `root` tienen acceso al panel `demo_empresas.html`.
- **Sesiones**: Gestionadas mediante `session-config.php` y validadas en cada API.
- **Asignación**: Las empresas se vinculan a los usuarios mediante `admin_usuario_id`.

## 📝 Bitácora de Errores (Error Log)

### BUG-006: Fallo de Carga en Perfil de Empresa
- **Síntoma**: El perfil mostraba "No se pudo cargar la información" a pesar de que la API devolvía éxito.
- **Causa**: Error de referencia en `profile.html`. Se intentaba acceder a `result` en lugar de `resultData` dentro del callback de la promesa del fetch.
- **Solución**: Corregida la referencia a la variable de respuesta de la API.

### BUG-007: Menú Lateral (Sidebar) Persistent
- **Síntoma**: El sidebar no se cerraba correctamente en dispositivos móviles o tras interactuar con él.
- **Causa**: Falta de lógica de *toggle* para las clases CSS de Tailwind.
- **Solución**: Refactorización del script de navegación para manejar estados `hidden` y `expanded`.

### BUG-008: Ruptura de Layout Flexbox (El formulario "salta" de la tarjeta)
- **Síntoma**: Partes del formulario en `demo_empresas.html` (como el botón Guardar o la Información de Registro) se salían del área con scroll e interrumpían el header modal.
- **Causa**: Etiquetas `</div>` extras/huérfanos procedentes de ediciones mal cerradas rompían la contención vertical del div maestro flex `flex-1 overflow-y-auto`.
- **Solución**: Depurar el árbol DOM del HTML usando la indentación para identificar cierres prematuros y borrarlos.

### BUG-009: Pestañas Empalmadas en Profile (Superposición)
- **Síntoma**: Al acceder a Mis Datos / Mi Empresa la información se apilaba verticalmente en `profile.html` por debajo del header personal.
- **Causa**: TailwindCSS y Argon no tenían definida la clase oculta/activa nativamente. Todos los `.tab-pane` se renderizaban como bloque por defecto.
- **Solución**: Insertar una regla de transición en línea `<style>` forzando `.tab-pane { display: none; opacity: 0; }` y habilitando `.active`.

### BUG-010: Scripts Argon Legacy que no existen en producción (404)
- **Síntoma**: Consola muestra múltiples errores 404 al cargar `profile.html`: `popper.min.js`, `bootstrap.min.js`, `dragula.min.js`, `jkanban.js`, `smooth-scrollbar.min.js`, `argon-dashboard-tailwind.js`.
- **Causa**: `profile.html` heredó los tags `<script>` del template Argon original, pero esos archivos no están en el servidor de producción de Clúster Intranet (solo existe `claut-core.min.js`).
- **Solución**: Eliminar **todos** los `<script src="./assets/js/core/...">` y `<script src="./assets/js/plugins/...">` del archivo. Solo deben quedar: `auth-session.js`, `session-security.js`, `header-navbar.js`, `loading-screen.js` y `claut-core.min.js`.
- **Regla**: NUNCA incluir scripts de Argon Dashboard en páginas nuevas o rediseñadas. El sistema de producción usa `dist/claut-core.min.js`.

### BUG-011: URL del Dominio con tilde (clúster vs claut)
- **Síntoma**: La solicitud `PUT` a `perfil_empresa.php` fallaba con `Load failed` (sin conectar al servidor).
- **Causa**: La función `determineApiUrl()` en `profile.html` usaba el hostname `'intranet.clústermetropolitano.mx'` (con ú - carácter UTF-8) en lugar del hostname real `'intranet.clautmetropolitano.mx'`. JavaScript nunca activaba la URL absoluta de producción, y la relativa `./api/perfil_empresa.php` resolvía a la raíz del servidor que devolvía error de red.
- **Solución**: Reescribir la condición usando el dominio correcto: `hostname.includes('clautmetropolitano')` para detección más robusta.
- **Regla**: En cualquier script de detección de entorno, usar `clautmetropolitano.mx` (sin tilde, sin acento). Jamás el hostname con unicode `clúster`.

### BUG-012: Panel de Solicitudes no aparecía en demo_empresas.html
- **Síntoma**: El panel "Solicitudes de Cambio" permanecía oculto (`class="hidden"`) aunque había solicitudes pendientes en la BD.
- **Causa**: El `fetch()` en `admin-empresas.js → cargarSolicitudes()` no incluía `credentials: 'include'`. La API `solicitudes_empresa.php` requiere sesión válida y devolvía HTTP 401 sin la cookie, haciendo que `data.success` nunca fuera `true`.
- **Solución**: Agregar `credentials: 'include'` y `headers: {'Content-Type':'application/json'}` al fetch de solicitudes.
- **Regla**: TODOS los fetches que llamen a APIs PHP autenticadas deben incluir `credentials: 'include'`. Sin esto, la cookie de sesión no se envía y el servidor rechaza la petición.

### BUG-013: 404 en Notificaciones de Perfil (gestionar_usuarios.php)
- **Síntoma**: El panel de administración de usuarios intentaba cargar solicitudes de cambio pero fallaba con Error 404 al buscar `api_notificaciones_perfil.php`.
- **Causa**: El archivo API invocado por `loadProfileNotifications()` no existía en el servidor ni en el repositorio local.
- **Solución**: Creación del endpoint `/build/api_notificaciones_perfil.php` con soporte para acciones `listar`, `aprobar` (mapeo dinámico de campos a `usuarios_perfil`) y `rechazar`.
- **Regla**: Al implementar notificaciones de cambio en el frontend, asegurar que el endpoint backend correspondiente esté deployado en el mismo nivel de directorio o ruta relativa correcta.

### BUG-014: "ID inválido" al editar boletines + archivo no se guardaba
- **Síntoma**: Al hacer clic en "Actualizar Boletín" dentro del modal de edición, el servidor respondía `{ success: false, message: "ID inválido" }`. El archivo adjunto tampoco se guardaba al crear ni al editar.
- **Causa raíz (API)**: El `case 'PUT'` en `boletines_simple.php` usaba `parse_str(file_get_contents("php://input"), $_PUT)` para leer el body. `parse_str` **solo parsea `application/x-www-form-urlencoded`**, pero el frontend enviaba `FormData` (`multipart/form-data`). Por eso `$_PUT['id']` siempre llegaba vacío → `intval('') = 0` → "ID inválido". Además, `$_FILES` **solo se popula en requests POST**, nunca en PUT → los archivos tampoco se procesaban.
- **Causa raíz (Frontend)**: `updateStatistics()` referenciaba `document.getElementById('stats-info')` que no existe en el DOM → `TypeError: null is not an object`.
- **Solución**:
  1. Se eliminó el `case 'PUT'` del backend.
  2. El `case 'POST'` ahora maneja **create y update** según `$_POST['id'] > 0`. Esto permite usar `$_FILES` correctamente y conservar el archivo existente si no se sube uno nuevo.
  3. El `case 'DELETE'` se corrigió para aceptar el ID tanto por query string como por JSON body, y ahora también elimina el archivo físico con `unlink()`.
  4. En `demo_boletines.html`, `method: 'PUT'` → `method: 'POST'`. El campo `id` ya se incluía en FormData.
  5. Se agregó null-check: `const el = document.getElementById('stats-info'); if (el) el.textContent = ...`.
- **Archivos modificados**: `build/api/boletines_simple.php`, `build/demo_boletines.html`
- **Documentación completa**: `docs/architecture/BOLETINES_API.md`
- **Regla**: Para operaciones con `FormData` (multipart) en PHP, **siempre usar POST**. Los métodos PUT/PATCH no populan `$_POST` ni `$_FILES`; solo funcionan con `application/x-www-form-urlencoded` via `parse_str`.

### BUG-015: HTTP 400 "Error al crear el documento" en demo_documentos.html
- **Síntoma**: Al hacer clic en "Guardar" en el modal de creación, el servidor respondía HTTP 400 y el frontend mostraba siempre "Error al crear el documento" sin detalle.
- **Causa raíz 1 (API)**: El `case 'POST'` usaba `ApiValidator::validateAndSanitize()` con la regla `'titulo' => 'required|string|min:3|max:255'`. Títulos de menos de 3 caracteres retornaban HTTP 400.
- **Causa raíz 2 (formato de respuesta)**: `ApiValidator::errorResponse()` devuelve `{ error: "..." }` pero el frontend busca `result.message`. Siempre mostraba el fallback genérico.
- **Causa raíz 3 (Frontend)**: `handleSaveClick` lanzaba `console.error('EditingId está perdido!')` incluso durante creación, cuando `editingId = null` es el estado correcto. Falso positivo que confundía el diagnóstico.
- **Solución**:
  1. Se eliminó `ApiValidator` del `case 'POST'`. Validación directa con `empty($titulo)` y whitelist de visibilidades. Errores usan el campo `message`.
  2. Errores de upload de `$_FILES` muestran mensajes legibles según el código PHP de error.
  3. `handleSaveClick` solo intenta recuperar `editingId` si el modal está en modo "Editar" (detectado por el título). Sin `console.error` en creación.
- **Archivos modificados**: `build/api/documentos.php`, `build/demo_documentos.html`
- **Documentación completa**: `docs/architecture/DOCUMENTOS_API.md`
- **Regla**: NUNCA usar `ApiValidator` en endpoints que manejan archivos (`$_FILES`). Siempre usar validación directa PHP y asegurar que los errores usen el campo `message` para compatibilidad con el frontend.

### BUG-016: Selector de empresas vacío + registros duplicados en demo_descuentos.html
- **Bug A — Selector vacío**: El `<select>` mostraba el total (`11 disponibles`) pero sin opciones. Causa: el map en `loadEmpresas()` guardaba solo `nombre_empresa`, pero la BD usa el campo `nombre`. El `forEach` buscaba `empresa.nombre` (undefined post-map) → condición `if (id && nombre)` fallaba para todas las empresas → 0 opciones. Fix: preservar ambos campos en el map (`nombre` y `nombre_empresa`) para que el `forEach` siempre encuentre el valor.
- **Bug B — Doble guardado**: Cada clic generaba 2 registros. Causa: doble disparo de `saveDescuento()` — el botón era `type="submit"` y además `setupEventListeners()` añadía un listener `submit` al form. Al hacer clic, el form disparaba el submit event que lo llamaba dos veces. Fix: cambiar botón a `type="button" onclick="saveDescuento(event)"` y eliminar el `addEventListener('submit', saveDescuento)`.
- **Archivos modificados**: `build/demo_descuentos.html`
- **Documentación completa**: `docs/architecture/DESCUENTOS_API.md`
- **Regla**: Nunca registrar `submit` event en un form que ya tiene botón con `onclick`. Un único punto de entrada por acción.

### BUG-017: Botón "Crear Comité" faltante en demo_comite.html
- **Síntoma**: No había forma de abrir el formulario en modo creación. El acordeón `#acordeonFormulario` existía pero no había ningún botón que lo abriera ni que llamara a `limpiarFormulario()`.
- **Causa raíz**: La barra de controles solo tenía el botón "Actualizar". `editComite(id)` sí abría el acordeón, pero no había punto de entrada equivalente para creación.
- **Solución**: Se agregó botón "Crear Comité" (rojo corporativo) en la barra de controles, y la función global `window.nuevoComite()` que limpia el form, resetea `comiteEditando = null`, actualiza el título del acordeón, lo abre y hace scroll suave al formulario.
- **Archivos modificados**: `build/demo_comite.html`
- **Documentación completa**: `docs/architecture/COMITES_API.md`
- **Regla**: Todo módulo CRUD debe tener un botón de creación visible en la barra de controles. No asumir que el usuario encontrará el formulario en un acordeón colapsado.

### FEATURE-022.1: Ajustes post-deploy de la landing
- **Hero title cortado**: `font-size: clamp(56px, 10.5vw, 168px)` desbordaba el grid `7fr` y cortaba "automotriz". Reducido a `clamp(44px, 7.8vw, 128px)` + `overflow-wrap: anywhere`. Breakpoints añadidos a 1200px / 880px / 480px.
- **Responsive móvil**: añadidas reglas en `responsive.css` para esconder `nav-links` en móvil, apilar CTAs del hero, esconder `hero-bottom-bar` en <480px.
- **Banner sign-up.html invisible**: el `background` shorthand reseteaba el gradiente y la imagen lo sobrescribía sin fallback. Fix: separar `background-color` (sólido) + `background-image` (gradiente + url), de modo que aunque la PNG falle se vea el fondo oscuro.
- **Loading screen**: logo cambiado de `apple-icon.png` (76px pixelado) a `/assets/img/logo-ct.png` (logo oficial). Stage 160→200px, logo 80→120px, ripples 100→140px.
- **Botón "Volver al sitio"**: añadido en `sign-in.html` (pill glassmorphism) y `sign-up.html` (pill ghost) — link a `/`.
- **Footer landing**: crédito "Desarrollado por LUMIA AI Solutions" con link a https://lumiaaisolutions.com.
- **Bug menor**: `loading-screen.js` se cargaba dos veces en `sign-up.html` — eliminada la duplicación.
- **Archivos modificados**: `build/landing/index.html`, `build/landing/assets/css/sections.css`, `build/landing/assets/css/responsive.css`, `build/pages/sign-in.html`, `build/pages/sign-up.html`, `build/assets/js/loading-screen.js`.

### FEATURE-022: Landing Page Pública como puerta de entrada al dominio
- **Síntoma / Solicitud**: Al entrar a `https://intranet.clautmetropolitano.mx` aparecía directamente el formulario de login. Se requería que apareciera una landing pública (marketing) y que los botones de "Iniciar sesión" y "Crear cuenta" llevaran al sistema existente.
- **Implementación**:
  1. Se copió la landing (`Cluster Portal 2/`) a `build/landing/` como módulo aislado.
  2. Se reescribieron **20 paths relativos** (`assets/`, `uploads/`) a absolutos (`/landing/assets/`, `/landing/uploads/`) en el HTML, y la constante `FRAME_PATH` en `assets/js/sequence.js`. Razón: usar `<base href>` rompía los anchors internos (`#about` → recarga); usar redirect cambiaba la URL visible.
  3. Se modificó `build/index.php` para servir `landing/index.html` por defecto, y `dashboard.html` solo cuando hay sesión activa. Se conservan los parámetros `?dashboard=1`, `?home=1`, y se añade `?landing=1` para preview.
  4. Se añadió una regla en `.htaccess`: `RewriteCond %{REQUEST_URI} ^/landing/` → acceso libre a assets.
  5. Botones de la landing reapuntados: `login.php` → `/pages/sign-in.html`; `registro.php` → `/pages/sign-up.html`; `intranet.php` → `/index.php?dashboard=1`.
  6. Links legales (`aviso-privacidad.php`, `terminos.php`, `cookies.php`) marcados como `#` con `onclick="event.preventDefault()"` — pendientes fase 2.
- **Archivos modificados**: `build/index.php`, `build/.htaccess`
- **Archivos nuevos**: `build/landing/` (carpeta completa: ~14 MB incluyendo `sequence/`)
- **Documentación completa**: `docs/architecture/LANDING_PAGE.md`
- **Garantía de no-regresión**: No se modificó NINGÚN archivo del sistema autenticado (`pages/`, `dashboard.html`, `api/`, `admin/`, perfiles). El usuario con sesión activa sigue viendo el dashboard al entrar a `/`.
- **Regla**: Cualquier landing pública futura debe vivir en una subcarpeta aislada (`/landing/`, `/marketing/`, etc.) y usar paths absolutos. Nunca compartir rutas raíz con el sistema autenticado.

### FIX-023: Ronda de flujos rotos e inconsistencias (Agosto 2026)
- **Alcance**: Auditoría automatizada de 62 referencias rotas (href/src/fetch/redirects) en `build/` → corregidas o clasificadas. Documentación completa: `docs/architecture/CORRECCIONES_2026-08.md`.
- **Seguridad**:
  1. `demo_*.html` ahora protegidas del lado servidor vía **`auth-gate.php`** (nuevo) + regla rewrite en `.htaccess`. Antes eran públicas (solo protección JS cliente).
  2. `api/check_me.php` exponía `$_SESSION` completo + `session_id` → recortado a `{authenticated, detected_role, is_admin}`.
- **Flujos críticos**:
  3. `api/registros_eventos.php` requería `config.php` inexistente → fatal 500 en toda la API de registros. Fix: `../config/database.php`.
  4. Creado `check_registro.php` (lo consumen `eventos.html` y `evento_detalle.php`; no existía → 404).
  5. Redirect de sesión expirada `/sign-in.html` → `/pages/sign-in.html` en `jwt-manager.js` y `token-refresh-worker.js`.
  6. `dashboard.html`: fetch `estadisticas-simple.php` → `estadisticas_simple.php` (guion vs guion bajo).
- **Links/botones**: favicon `../assets/img/` → `./assets/img/` en 10 páginas; sidebar `demo_usuarios.html` → `gestionar_usuarios.php` (×6); botones sin backend eliminados (`test_boletines_completo.php`, `reset-estadisticas.php`, `debug-estadisticas.php`); 5 scripts 404 removidos de `demo_gestion_grafico.html`; `admin-panel.html` `../demo_empresas.html` → `./`; fallbacks de imagen a `placeholder.svg`.
- **Código muerto identificado (no eliminado aún)**: `js/dashboard-auth.js`, `components/menu-components.html`, `pages/users.php`, utilidades debug en `admin/`.
- **Regla**: Toda página administrativa estática nueva debe servirse a través de `auth-gate.php` o convertirse a PHP con validación de sesión. Nunca exponer `$_SESSION` en respuestas JSON.

### FEATURE-024: Sistema de Diseño Unificado CLAUT UI (Agosto 2026)
- **Qué**: Nuevo `build/css/claut-ui.css` — fuente de verdad visual del sistema (tokens + clases reutilizables), basado en el lenguaje de `calendario.html` (Porsche dark `#09090b` + glassmorphism + Inter + rojo `#C7252B`).
- **Rollout fase 1 (hecho)**: link al CSS en 24 páginas (todas excepto login y landing); unificación de tokens divergentes — `clúster-dark #1D1D1F` → `#09090b` (boletines, comites, descuentos, contacto), `--porsche-black #1a1a1a` → `#09090b` y `--porsche-accent #c9302c` → `#C7252B` (demos, eventos, gestionar_usuarios, evento_detalle, dashboard), `--porsche-dark #0f0f0f` → `#09090b` (demo_empresas).
- **Pendiente (fase 2)**: conversión completa página por página a clases del sistema; pasar las páginas claras (dashboard, profile, eventos, visitante) al tema oscuro con revisión visual individual.
- **Rollout fase 2 (hecho)**: capa de conversión `.claut-skin` (sección 13 de claut-ui.css) que re-mapea las utilidades Tailwind claras de los 9 paneles demo al tema oscuro sin reescribir markup (header rojo → consola oscura, bg-white → glass, inputs dark, botones translúcidos, press feedback scale 0.97). Emojis eliminados de headings/títulos. Fix de centrado de `.container`. Boletines: doble heading eliminado + auto-carga de la lista. Verificado visualmente en Chrome y desplegado.
- **Rollout fase 3 (hecho)**: unificados los tokens compartidos de `assets/css/layout/header-navbar.css` (fuente de `--claut-*` para 9+ páginas): fondos carbón → familia `#09090b` y bordes slate-oscuro invisibles → blanco-alfa. `profile.html`/`visitante.html` con `claut-dark claut-skin`. Sección 14 responsive en claut-ui.css (touch 44px, overflow contenido, tipografía móvil). Cache-busting `?v=20260831c`. Todo desplegado.
- **Documentación completa**: `docs/architecture/DESIGN_SYSTEM.md`.
- **Regla**: Página nueva = `<link claut-ui.css>` + `body.claut-dark` + clases del sistema. Prohibido hardcodear `#1a1a1a`, `#0f0f0f`, `#1D1D1F`, `#c9302c`. Los tokens `--claut-*` compartidos viven en `assets/css/layout/header-navbar.css` — cambiarlos ahí propaga a todo el sistema.
- **Rollout fase 5 (hecho, bugs reportados por usuario)**: búsqueda/notificaciones del header reintegradas en `.claut-header-actions` (antes flotaban con `position:fixed` y colores de tema claro, casi invisibles); `descuentos.html` — desactivada una marquesina infinita en JS (`DiscountStreamController`, ancho hardcodeado 350px) que chocaba con el ancho responsive real y causaba descentrado/duplicados; `comites.html` — banner con texto cortado corregido (`object-cover`→`object-contain` + fondo de relleno); **bug propio corregido**: `.main-content` en `claut-ui.css` colisionaba de nombre con una clase utilitaria ya usada en ~15 páginas legacy, causando huecos negros enormes en varios `demo_*` — renombrada a `.claut-content-area`; `demo_empresas.html` — sidebar ya no se abre solo en desktop, unificado cerrado-por-defecto como el resto de `demo_*`; `admin/banner-admin-mejorado.php` — nunca había tenido el sistema de diseño enlazado, corregido.
- **Rollout fase 4 (hecho, verificación en Chrome con sesión real)**: activado `claut-skin` en las 9 páginas que faltaban (comites, boletines, descuentos, contacto, empresas-convenio, gestionar_usuarios, admin-panel, calendario); corregida regresión de contraste en `descuentos.html` (texto blanco sobre `background:white` inline); `mensajes.html` reescrito a oscuro (no usaba Tailwind, el skin no lo alcanzaba); logo tile de socios homologado; animación GSAP `rotationY:-180` del nav (hacía ver el texto espejado en cada navegación) reemplazada por fade+translateY 300ms en `eventos.html`/`comites.html`; vacío enorme en banner de `dashboard.html` corregido con `:has()`; botón de usuario duplicado (código muerto) eliminado de `profile.html`; overlap responsive en `profile.html` corregido con flex-wrap bajo 768px. Detalle completo en `docs/architecture/DESIGN_SYSTEM.md`.

### FEATURE-025: Rediseño Landing Page — menú overlay + header (Agosto 2026)
- **Menú overlay full-screen** (referencia landonorris.com): reemplaza el nav horizontal (que en móvil no tenía alternativa — bug pre-existente sin hamburguesa). Doble columna de fotogramas del sequence de rayos-X (b/n, revelan color al hover, scroll infinito lento) + navegación en tipografía grande. Apertura/cierre con `clip-path: circle()` desde el botón. Corregido link "Casos" que apuntaba a un anchor inexistente.
- **Header con fondo blanco sólido** (antes solo un tinte de 6-14%, el logo casi no se veía) + logo con sus colores originales a 60px (antes 44-56px según archivo). Texto/íconos del header recoloreados a oscuro para leerse sobre blanco.
- CTA `.btn-red` con gradiente animado y sombra más profunda. Parallax extendido a mapa y CTA final (reutilizando infraestructura ya existente en `scrollfx.js`). Contenido resumido en "Quiénes somos" y "Universo Clúster Intranet".
- **Bug de caché recurrente**: los scripts/CSS de la landing nunca tenían versión — el botón de menú no funcionaba hasta agregar `?v=`. Documentado como regla: assets de landing deben versionarse desde el día uno.
- **Documentación completa**: `docs/architecture/LANDING_REDESIGN_2026-08.md`.

### FIX-026: Backlog de seguridad/deuda técnica — Fases B-E (Agosto 2026)
- **CSP (Fase B)**: hallazgo clave — la CSP débil en `middleware/security-headers.php` solo la usaba `api/upload-image.php`; el resto del sitio no enviaba CSP en absoluto. Se agregó CSP global vía `.htaccess`, manteniendo `unsafe-inline`/`unsafe-eval` (removerlos requiere externalizar JS/CSS inline de HTML gigantes — refactor de días, no un parche) pero endureciendo `img-src`/`object-src`. Verificado sin romper nada (consola limpia, header confirmado con `curl -I`).
- **Duplicados (Fase C)**: verificación antes de borrar reveló que la mayoría de los "duplicados" identificados en `ANALISIS_SISTEMA.md` (`empresas-simple.php`/`empresas.php`, `boletines_simple.php`/`boletines.php`, etc.) tienen consumidores reales en AMBOS lados — no son seguros de eliminar sin consolidar primero. Solo se confirmaron y eliminaron 3 archivos genuinamente muertos: `js/dashboard-auth.js`, `components/menu-components.html`, `pages/users.php`. `api/registros_eventos.php` (antes marcado como candidato) resultó SÍ tener un consumidor real (`js/eventos.js`) — no se tocó.
- **CI (Fase D)**: creado `.github/workflows/smoke-tests.yml` (lint PHP + chequeo de enlaces rotos) — **no subido a GitHub**, pendiente de tu confirmación por ser una acción visible en el repo compartido.
- **Respaldo de BD (Fase D)**: no se pudo configurar — requiere acceso al panel de Hostinger (hPanel) que esta sesión no tiene. Procedimiento documentado en `BACKLOG_2026-08.md`.
- **Páginas legales (Fase E)**: `landing/terminos.html` y `landing/cookies.html` creadas y enlazadas desde el footer (antes ambas apuntaban al PDF de aviso de privacidad). Contenido estándar razonable, marcado explícitamente como no revisado por abogado.
- **Documentación completa de las 4 fases**: `docs/architecture/BACKLOG_2026-08.md`.

### FIX-027: Cierre de sesión — CSP, CI, planes de consolidación (Agosto/Sept 2026)
- **CSP**: verificado que `unsafe-eval` no se puede quitar hoy sin romper — 20 páginas cargan Tailwind CDN (`cdn.tailwindcss.com`), cuyo compilador JIT en navegador depende de eval. `unsafe-inline` tampoco es removible sin externalizar el `onclick=""` extensivo del codebase (no hay una alternativa parcial real: nonces no cubren atributos de evento inline).
- **CI**: `.github/workflows/smoke-tests.yml` commiteado localmente (`fc11862`). **El push falló**: el remoto `origin` (`LUMIA-AI-SOLUTIONS/Cluster_Intranet`) no existe ni es accesible con la cuenta de GitHub autenticada (`lumiaaisolutions`) — verificado con `gh repo view` y `gh repo list`, ninguno de sus 10 repos coincide y no pertenece a ninguna organización. Requiere que el usuario indique la ubicación/cuenta correcta del remoto.
- **Planes de implementación documentados** (no ejecutados — requieren verificación visual/funcional paso a paso que esta sesión no pudo completar por sesión de admin expirada): `docs/architecture/PLAN_CONSOLIDACION_API.md` (duplicados de API + fix de esquema de `registros_eventos.php`) y `docs/architecture/PLAN_MIGRACION_DISENO.md` (migrar `.claut-skin` a clases nativas).
- **Pendiente de próxima sesión**: revisión visual de `visitante.html`, `admin-2fa.html`, `admin-auditoria.html`, `admin_usuarios.html` (sesión expiró, no se debe escribir credenciales).
- **91 archivos siguen sin commitear** en git — todo el trabajo del día vive en producción (FTP) pero no en el repositorio. Recomendado commitear por tema antes de la próxima sesión.

## 🚀 Próximos Pasos (En curso)
- [x] Sidebar → Header horizontal homologado en `profile.html`.
- [x] Eliminar scripts Argon legacy que causan 404.
- [x] Corregir URL del dominio en `determineApiUrl()`.
- [x] Panel de solicitudes en `demo_empresas.html` — agregar `credentials:include` al fetch.
- [x] **New**: Resolución 404 API Notificaciones de Perfil en `gestionar_usuarios.php`.
- [x] **New**: Rediseño Premium (Glassmorphism + Dark Mode) del módulo `gestionar_usuarios.php`.
- [x] **New**: Fix BUG-014 — Edición de boletines (PUT→POST, archivos adjuntos, null-check stats-info).
- [x] **New**: Fix BUG-015 — Creación de documentos (ApiValidator HTTP 400, formato error, falso editingId).
- [x] **New**: Fix BUG-016 — Descuentos: selector de empresas vacío (campo `nombre` vs `nombre_empresa`) + registros duplicados (doble submit).
- [x] **New**: Fix BUG-017 — Comités: botón "Crear Comité" faltante; se agregó botón + función `window.nuevoComite()`.
- [x] **New**: Fix BUG-018 — Eventos: URL de registro opcional, modal de detalle de notificaciones habilitado, y vista de detalles de registro en lista reactivada.
- [x] **New**: FEATURE-022 — Landing pública como puerta de entrada al dominio. `index.php` ahora sirve `/landing/` por defecto; dashboard solo con sesión activa.
- [x] **Deploy FTP 2026-08-31 — COMPLETADO Y VERIFICADO EN PRODUCCIÓN**: toda la lista pendiente de abajo + correcciones FIX-023 + sistema de diseño FEATURE-024 (css/claut-ui.css, auth-gate.php, check_registro.php, 24 páginas, landing completa). Verificación HTTP: landing en `/`, demos con 302 al login, check_me.php recortado, check_registro.php funcional. Detalle en `docs/architecture/CORRECCIONES_2026-08.md`.
- [x] ~~**Subir via FTP** (pendiente)~~ (hecho 2026-08-31):
  - `build/api/documentos.php` → `public_html/api/documentos.php`
  - `build/demo_documentos.html` → `public_html/demo_documentos.html`
  - `build/demo_descuentos.html` → `public_html/demo_descuentos.html`
  - `build/demo_comite.html` → `public_html/demo_comite.html`
  - `build/demo_evento.html` → `public_html/demo_evento.html`
  - `build/js/demo-eventos.js` → `public_html/js/demo-eventos.js`
  - **FEATURE-022 (Landing)**:
    - `build/index.php` → `public_html/index.php` *(EDITADO)*
    - `build/.htaccess` → `public_html/.htaccess` *(EDITADO)*
    - `build/landing/` → `public_html/landing/` *(NUEVO — carpeta completa, ~14 MB)*
- [ ] Validar flujo completo: Empresa edita → solicitud llega → Admin aprueba en `demo_empresas.html`.
- [ ] Finalizar ajustes de responsividad extrema en tablas de `gestionar_usuarios.php`.




### Responsividad de Dashboard (`dashboard.html`)
* **Problema:** En pantallas grandes (mayores a 1440px), el contenido de la intranet se mostraba agrupado a la izquierda o con márgenes excesivos, dejando gran parte del ancho de la pantalla desaprovechado.
* **Solución:**
  * Se eliminaron los anchos máximos estáticos (`max-width: 1400px`) y márgenes fijos (`margin-left: 140px`) en pantallas `>1441px` para permitir un diseño verdaderamente fluido.
  * Se actualizó la clase `.max-w-7xl` para que en resoluciones ultra-anchas ocupe el `95%` del ancho total de la pantalla en lugar de quedar estancado en `80rem` (1280px).
  * Se corrigió el uso de `100vw` en `html, body` reemplazándolo por `width: 100%` para evitar conflictos de scrollbar horizontal.
  * Se deshabilitó un script inyectado al final del documento que sobrescribía de forma agresiva los márgenes (`margin: 0`), rompiendo el comportamiento `mx-auto` de centrado en contenedores internos.


### BUG-019: Retraso Crítico en Creación de Eventos
- **Síntoma**: Al crear un evento, la notificación de éxito tardaba varios segundos en aparecer, provocando que el usuario hiciera clic múltiples veces y creara duplicados.
- **Causa**: El API enviaba un correo electrónico síncrono vía SMTP antes de responder al navegador. Además, el frontend no tenía un bloqueo de peticiones concurrentes.
- **Solución**:
  1. Se implementó `fastcgi_finish_request()` y técnicas de flush en `api/eventos.php` para cerrar la conexión con el navegador **antes** de procesar el envío de correos.
  2. Se añadió una bandera `isSubmitting` en `demo-eventos.js` y `admin-calendar-master.js` para bloquear el botón de guardado.
- **Regla**: Todo proceso pesado (envío de correos, procesamiento de imágenes pesadas) debe ocurrir después de enviar la respuesta JSON al cliente.

### BUG-020: Sincronización Visual en Calendario (Cache y Estado)
- **Síntoma**: Al eliminar o crear registros en el calendario, los cambios no se reflejaban automáticamente; el usuario debía pulsar F5.
- **Causa**: El navegador cacheaba las respuestas de la API de eventos. Además, `refetchEvents()` no siempre forzaba un redibujado inmediato de los elementos eliminados.
- **Solución**: 
  1. Se agregó un timestamp dinámico (`&t=Date.now()`) a las URLs de consulta de eventos.
  2. Se implementó manipulación directa del DOM del calendario (`eventObj.remove()`, `addEvent`) para feedback instantáneo de milisegundos.
- **Archivos**: `build/js/admin-calendar-master.js`, `build/dashboard.html`.

### BUG-021: Navegación Bloqueada en Calendarios
- **Síntoma**: Los calendarios de la consola master y del dashboard estaban "congelados" en el mes actual sin botones para ver meses futuros.
- **Causa**: El diseño premium ocultaba el header nativo de FullCalendar y no se habían vinculado botones externos a los métodos `.prev()` y `.next()`.
- **Solución**: Creación de controles personalizados (Prev, Hoy, Next) y sincronización del título dinámico mediante el callback `datesSet`.
- **Archivos**: `build/calendario.html`, `build/dashboard.html`.

### FEATURE-026: Unificación de Panel Admin (9 módulos) + Rediseño de Dashboard (Septiembre 2026)
- **Qué**: Replicado el patrón validado en el piloto `admin/banner-admin-mejorado.php` (sidebar unificado + tarjetas de color + formularios wizard) a los 9 módulos restantes vía agentes paralelos, preservando el 100% de los campos de formulario existentes.
- **BUG-022 — Caché de `claut-ui.css` (crítico)**: 23 páginas seguían apuntando a `?v=20260831f` (versión anterior a las clases de color de tarjetas agregadas esta sesión) — los stat-cards se veían sin color en todos los módulos excepto el piloto. **Regla**: al modificar un CSS/JS compartido, `grep -r "archivo?v="` en TODO `build/` y actualizar el querystring en cada página que lo referencie, no solo en la que se edita.
- **BUG-023**: `demo_empresas.html` — un `</div>` cerraba `.claut-admin-main` antes de tiempo, dejando la barra de controles y la tabla completa sin el margen del sidebar (se veían tapadas). Corregido envolviendo la sección huérfana.
- **Nuevo componente**: `.claut-tabs`/`.claut-tab-pane` en `claut-ui.css` + `assets/js/claut-tabs.js` — reemplaza el patrón `<details>` acordeón cuando una página tiene varias secciones que no deben verse "todo apiladas" (aplicado en `demo_comite.html`).
- **Auditoría de código muerto**: `demo_boletines.html` tenía ~150 líneas de JS huérfano (panel de diagnóstico eliminado del HTML tiempo atrás, pero las funciones JS y sus llamadas a 2 APIs inexistentes seguían ahí) + una función `deleteBulletin()` duplicada (solo la segunda se ejecutaba). Ambos eliminados.
- **Rediseño de `dashboard.html`**: nuevo componente reutilizable `assets/css/layout/claut-icon-rail.css` + `assets/js/claut-icon-rail.js` — riel de íconos flotante y 100% transparente que reemplaza el menú horizontal SOLO en escritorio (≥1025px, mismo breakpoint que ya usaba el sistema; el menú curvo inferior `.claut-bottom-nav` sigue manejando móvil sin cambios). Logo agrandado (32px→44px, solo en dashboard.html). Fondo corregido: `css/moveimage.png` (imagen rosa/naranja sin relación con la marca) reemplazado por un `radial-gradient` rojo/negro puro en CSS, manteniendo el mismo contenedor `.image-motion` para no romper la animación GSAP ScrollTrigger existente.
- **BUG-024**: una regla legacy `nav, .navbar, header { position: relative !important; z-index: 99997 !important }` en el `<style>` inline de `dashboard.html` capturaba cualquier `<nav>` nuevo de la página, rompiendo el `position:fixed` del riel. **Regla**: cualquier `<nav>` nuevo en `dashboard.html` debe declarar sus propiedades de posicionamiento con `!important` propio por esta razón (la especificidad de clase gana sobre el selector de elemento aun entre dos reglas `!important`).
- **Documentación completa**: `docs/architecture/PANEL_ADMIN_UNIFICACION_2026-09.md`.

### BUG-025: Estilo de depuración olvidado causaba "bordes negros" y líneas visibles en `dashboard.html`
- **Síntoma**: el usuario reportó bordes/líneas negras a los lados del contenido y sobre el título "Bienvenido a Clúster Intranet", como si el diseño estuviera "cortado".
- **Causa**: `.main-content-wrapper { background: rgba(15, 22, 35, 0.45); /* Slate-azul para ver el efecto de scroll */ }` — un tinte azulado de depuración (el propio comentario lo confirma) que cubría solo el wrapper de contenido, no el ancho completo de la página, creando una discontinuidad de color visible en los bordes contra el fondo rojo/negro real.
- **Solución**: eliminado el `background`/`backdrop-filter` de depuración por completo.
- **Regla**: revisar comentarios tipo "para ver el efecto" / "debug" / "test" en CSS inline antes de asumir que un desajuste visual es un problema de layout — a veces es literalmente una ayuda visual de desarrollo que nunca se quitó.

### BUG-026 (crítico): JS del wizard nunca llegaba a producción por caché sin versión
- **Síntoma**: en `calendario.html`, el botón "Nuevo Post-it" no abría ningún modal — `openQuickEventModal()` existía pero no hacía nada.
- **Causa**: `js/admin-calendar-master.js` se cargaba sin `?v=` en absoluto. El navegador servía una copia cacheada de ANTES de que se agregara la lógica `.open` del wizard (visto/cacheado en sesiones de prueba previas), aunque el archivo correcto ya estaba en el servidor.
- **Solución**: agregado `?v=20260901a` a `admin-calendar-master.js`. Se auditaron y corrigieron por el mismo motivo (sin versión o con versión estática vieja `?v=1.2`) los scripts de `demo_empresas.html` (`admin-empresas.js`), `demo_evento.html` (`demo-eventos.js`) y `demo_comite.html` (`admin-comites.js`).
- **Regla reforzada**: TODO `<script src="...">` de un archivo JS propio del proyecto debe llevar `?v=` — sin excepción, incluso si "no se ha tocado en esta sesión". Un JS sin versión es una bomba de tiempo: funciona en desarrollo (caché limpia) y falla silenciosamente en producción para cualquier usuario que ya haya visitado la página antes del cambio.

### BUG-027 (crítico): Doble menú móvil en `dashboard.html` — círculo rojo flotando a media pantalla
- **Síntoma**: en vista móvil, además de la barra de pestañas inferior correcta, aparecía un círculo rojo "Inicio" flotando solo, a la mitad de la pantalla, con huecos enormes arriba y abajo.
- **Causa raíz 1**: `.claut-icon-rail` (escritorio) define `top` y `bottom` con `!important` para poder ganarle a reglas legacy de otras páginas. Su propio media query móvil (`max-width:1024px`) redefinía `top:auto; bottom:0.9rem;` **sin** `!important` — en CSS, una declaración `!important` siempre gana sobre una normal sin importar el orden en el archivo. Resultado: en móvil el riel seguía estirado de `top:header` a `bottom:0` (toda la pantalla), y al ser flex-row centrado verticalmente, los íconos terminaban flotando a la mitad en vez de pegados abajo.
- **Causa raíz 2**: el menú curvo legacy `.claut-bottom-nav` (que se suponía oculto por `claut-icon-rail.css` al adoptar el riel/tab-bar como único menú) tenía una regla `@media (max-width:1024px) { .claut-bottom-nav { display:flex !important; } }` más arriba en el `<style>` inline de `dashboard.html` — al tener la misma especificidad `!important`, gana la regla que aparece más abajo en el documento, así que había que volver a ocultarlo al final del archivo para que esa fuera la última palabra.
- **Solución**: `top`/`bottom` del media query móvil de `.claut-icon-rail` ahora llevan `!important`; se agregó `.claut-bottom-nav { display: none !important; }` en el bloque de overrides al final de `dashboard.html` (después de todo lo demás, para ganar el empate por orden de aparición). Aprovechado para pulir la tab-bar móvil: `env(safe-area-inset-bottom)` para no quedar tapada por la barra gestual de iOS, íconos 44px (antes 42px) para cumplir el mínimo táctil de 44×44, y un breakpoint extra en ≤380px para pantallas muy angostas.
- **Regla reforzada**: cuando una regla de escritorio usa `!important` en una propiedad, el override correspondiente en un media query móvil DEBE usar `!important` también — de lo contrario el empate lo gana la regla no-móvil sin importar cuál esté más abajo en el archivo. Antes de dar por "oculto" un componente legacy con `display:none`, buscar TODAS las reglas `!important` que apunten a esa misma clase en el archivo (pueden reactivarlo más abajo).

### FEATURE-027: Menú único (riel/tab-bar) + header con logo difuminado replicado a 7 páginas de socio
- **Qué**: los mismos cambios validados en `dashboard.html` (riel `.claut-icon-rail` vertical en escritorio / tab-bar en móvil, logo 58px circular con degradado difuminado en el header, sin doble menú) se replicaron a `descuentos.html`, `boletines.html`, `comites.html`, `contacto.html`, `eventos.html`, `empresas-convenio.html` y `profile.html`.
- **Reutilización real**: a diferencia de `dashboard.html` (monolito de 14 700+ líneas con estilos de header duplicados inline), estas 7 páginas ya comparten `assets/css/layout/header-navbar.css` como única fuente de `.claut-header`/`.claut-header-logo`/`--header-height`. El fix de logo grande + degradado + `--header-height:80px` se hizo UNA sola vez ahí y se propaga a las 7 automáticamente — no se duplicó CSS por página.
- **Único cambio real por página**: agregar `<link claut-icon-rail.css>` (después del `<style>` propio de cada página, para ganar el empate de `!important` contra `.claut-bottom-nav{display:flex!important}` del media query móvil legacy — mismo truco que BUG-027), insertar el `<nav class="claut-icon-rail">` con los 7 destinos justo después de `</header>`, cargar `claut-icon-rail.js` al final del body, y bumpear `header-navbar.css?v=20260831d` → `?v=20260901a`.
- **BUG-028 encontrado en el rollout**: las 7 páginas ya tenían su propia regla `main.claut-main { padding-left: clamp(0.75rem, 3vw, 3rem) !important; }` (compuesta elemento+clase, más específica que un simple `.claut-main` sin `!important` en el CSS compartido). Sin corregirlo, el riel de escritorio se superpondría al contenido. Solución: un `<style>` por página, colocado después del `<link>` del riel, con el MISMO selector `main.claut-main` y `!important` — así gana el empate por orden de aparición sin tener que subir la especificidad del archivo compartido.
- **Verificación**: probado en navegador real (desktop 1440px y viewport móvil 500px) en `descuentos.html`, `comites.html`, `profile.html` (las 3 que NO tienen `.main-content-wrapper` anidado, el caso de mayor riesgo) y `eventos.html` — riel activo correcto, sin doble menú, logo difuminado sin líneas, contenido no tapado.

### BUG-029: Glow del hero acotado a la sección — filo visible arriba/abajo de "Bienvenido a Clúster Intranet"
- **Síntoma**: en `dashboard.html`, la sección "Bienvenido a Clúster Intranet" seguía mostrando lo que parecía una caja/línea que dividía esa parte de la página del resto, incluso después de quitar el borde de `.porsche-navbar` y el `<style>` de depuración de BUG-025.
- **Causa**: un `<div style="position:absolute; inset:0; background: radial-gradient(circle at 50% 50%, rgba(199,37,43,0.1) 0%, transparent 50%);">` ("Efectos de fondo premium") vivía DENTRO de `<section class="claut-hero">`, acotado estrictamente al alto de esa sección (confirmado inspeccionando el elemento con DevTools: 1658×679px, exactamente el tamaño del `<section>`). Donde el degradado llegaba a "transparent" en los bordes superior/inferior de la sección, se veía un filo recto contra el fondo continuo del resto de la página.
- **Solución**: se eliminó ese div. El glow rojo/negro de fondo ya lo cubre `.unified-gradient-background` (fija, `top/left/right/bottom:0` sobre TODO `.content-gradient-container`, no solo el hero), así que el efecto visual se conserva pero sin costura en los bordes de sección.
- **Regla**: cualquier efecto de fondo (glow, gradiente, textura) debe vivir en un contenedor que abarque el layout completo, nunca en un `<section>` individual con `position:absolute; inset:0` — si el fondo circundante no es idéntico fuera de esa sección, el corte del `inset:0` se ve como una línea o caja.
- **Segunda vuelta — causa real restante**: tras quitar el div de arriba, el usuario seguía viendo la línea arriba del texto y un "círculo cortado". La causa era otra: `.claut-hero { overflow: hidden !important; }` recortaba `.claut-hero::after`, un "orbe" decorativo de 600×600px posicionado deliberadamente `top: -200px` (para que sobresaliera del borde superior de la sección y se viera flotando/difuminado). El `overflow:hidden` cortaba en línea recta justo el tercio superior del círculo exactamente en el borde de la sección — eso era literalmente la línea Y el círculo cortado que se reportaban. Se quitó `overflow: hidden` de `.claut-hero` (ya no había contenido real que necesitara recortarse, solo el pseudo-elemento decorativo) y el orbe ahora se difumina libremente sin costura.
- **Regla reforzada**: si un elemento decorativo se posiciona deliberadamente fuera del `0-100%` de su contenedor (`top`/`left` negativos, o mayor que el tamaño del padre) para lograr un efecto de "sangrado"/difuminado, revisar que NINGÚN ancestro tenga `overflow: hidden` — si lo tiene, ese sangrado se corta en línea recta justo en el borde, convirtiendo el efecto "flotante" pretendido en la clase de línea/caja dura que se reporta como bug.

### FIX-030: Limpieza de funciones duplicadas + rediseño del botón Admin Panel
- **`toggleUserMenu`/`handleLogout` duplicados**: `boletines.html` y `profile.html` tenían la función declarada DOS veces en distintos bloques `<script>` — por hoisting de `function`, solo la última declaración sobrevive en tiempo de ejecución, dejando la primera como código muerto. En `boletines.html` esto era más grave que redundancia: la segunda declaración de `handleLogout()` limpiaba claves de `localStorage` distintas (`userToken`/`isLoggedIn` en vez de `userData`/`currentUser`) y redirigía a `dashboard.html` en vez de `index.php` — inconsistente con el resto del sitio (`descuentos.html`, `comites.html`), por lo que cerrar sesión desde Boletines dejaba sesión "colgada" y mandaba al usuario de vuelta a una página que requiere estar autenticado. Se conservó en ambos archivos la versión con guardas null-safe (`if (dropdown) {...}`) y, en `boletines.html`, el `handleLogout` consistente con el resto del sitio.
- **Botón Admin Panel con mal diseño**: investigando el `#adminPanelButton` en `dashboard.html` se encontró que el `<span>Admin Panel</span>` usa la utilidad Tailwind `lg:inline`, pero `dashboard.html` NO carga Tailwind — usa el CSS ya compilado `assets/css/argon-dashboard-tailwind.css`, que nunca generó esa clase. Resultado: el texto no aparece en NINGÚN ancho de pantalla, y el botón lleva años siendo solo-ícono sin que nadie le hubiera dado un estilo propio para ese caso (quedaba un rectángulo de ~27px, `border-radius:10px`, ícono descentrado). Se rediseñó como círculo de 44px (mínimo táctil), mismo lenguaje visual que `.claut-icon-rail-item.active`.
- **Regla**: cuando una clase Tailwind (`hidden`, `lg:inline`, etc.) aparece en un archivo que NO carga Tailwind (ni CDN ni build con ese contenido escaneado), verificar si existe realmente en el CSS compilado antes de asumir que el comportamiento responsive funciona — puede llevar mucho tiempo silenciosamente rota.

### BUG-031: Tarjeta "Bienvenido" a 160px del header y descentrada (doble margen legacy)
- **Síntoma**: en escritorio, la tarjeta "INTRANET / Inicio... Bienvenido, Fernando" quedaba muy separada del header, y el contenido se veía descentrado hacia la derecha.
- **Causa**: un bloque `@media (min-width:1025px)` con el comentario "FORZAR POSICIÓN FIJA DEL NAVBAR — DESHABILITAR ANIMACIONES" (línea ~8479, de antes del rediseño del riel) le ponía a `.porsche-navbar[navbar-main]` `margin-top: 80px !important`, que se SUMABA al `padding-top: 80px` que ya tiene `main.claut-main` para librar el header fijo — 160px de hueco en vez de 80px. La misma regla agregaba `margin-left/right: 120px !important`, que se sumaba al `padding-left: 134px !important` que `main.claut-main` ya reserva para el riel (FEATURE-026) — dejando 254px a la izquierda contra solo 120px a la derecha.
- **Solución**: al final de `dashboard.html` (para ganar el empate de `!important` por orden de aparición) se sobrescribió ese bloque con `margin-top: 24px` y `margin-left/right: 0`, dejando que el único hueco superior sea el `padding-top` de `.claut-main` más un respiro corto, y que el ancho de la tarjeta llene el espacio disponible junto al riel de forma simétrica.
- **Regla**: al heredar reglas "FORZAR POSICIÓN..." de versiones anteriores del diseño (pre-riel), revisar si siguen sumándose a paddings/márgenes que un rediseño posterior ya resolvió en el contenedor padre — quedan como offsets duplicados invisibles hasta que se miden con DevTools.

### Método de despliegue FTP — `lftp -u` bloqueado, usar `curl --netrc`
- El clasificador de seguridad del entorno empezó a bloquear `lftp -u 'user','pass'` por exponer la contraseña en texto plano en el comando (inconsistente: funcionó muchas veces antes de empezar a bloquearse en la misma sesión).
- `lftp` 4.9.3 (la versión instalada) no soporta `~/.netrc` automáticamente (falla con "530 Login incorrect" aunque el archivo esté bien formado).
- **Solución que funciona**: `curl --netrc -sS -T archivo "ftp://82.29.80.146:21/archivo"` — `curl` sí soporta `~/.netrc` de forma nativa y confiable. El archivo `~/.netrc` (permisos 600, fuera del repo) se creó con autorización explícita del usuario.
