# Rediseño 2026 — Sistema de paneles "dph" (Septiembre 2026)

Rediseño integral de la experiencia de socio aprobado por el usuario sobre
`dashboard-preview.html` y extendido a las 7 páginas restantes.
**Estado: DESPLEGADO EN PRODUCCIÓN.** Las 8 páginas del rediseño
(dashboard, descuentos, boletines, comites, contacto, eventos,
empresas-convenio, profile) + los 3 archivos compartidos
(`css/claut-panel.css`, `js/claut-panel.js`, `assets/js/claut-page-header.js`
v2) están subidos y verificados vía `curl` contra
`intranet.clautmetropolitano.mx`. Antes del deploy también se corrigieron
3 bugs reportados aparte (Fases 6-8, ver abajo): `pages/sign-in.html`,
`admin-panel.html` y `calendario.html`.

## Cómo revisar las propuestas

```bash
cd build && php -S localhost:8090
```

| Página | URL de preview |
|---|---|
| Dashboard | http://localhost:8090/dashboard-preview.html |
| Descuentos | http://localhost:8090/descuentos-preview.html |
| Boletines | http://localhost:8090/boletines-preview.html |
| Comités | http://localhost:8090/comites-preview.html |
| Contacto | http://localhost:8090/contacto-preview.html |
| Eventos | http://localhost:8090/eventos-preview.html |
| Socios | http://localhost:8090/empresas-convenio-preview.html |
| Perfil | http://localhost:8090/profile-preview.html |

Los previews tienen `auth-session.js`/`session-security.js` comentados **solo
en esas copias** para poder revisarlos sin sesión; sembrar sesión mock en la
consola: `localStorage.setItem('userData', JSON.stringify({nombre:'Fernando',
rol:'admin', email:'x@y.mx'}))`.

En localhost la BD de Hostinger no es alcanzable: los grids muestran sus
estados vacíos/fallback ("Sin Banners", datos de ejemplo del chart). En
producción cargan datos reales — no se tocó ninguna API.

## Archivos nuevos compartidos (fuente única de verdad)

### `css/claut-panel.css` (prefijo `dph-`)
Todos los componentes del rediseño: hero con secuencia scrubbed
(`.dph-hero`, variante compacta `.dph-hero--page`), veladura de legibilidad,
tipografía del saludo (eyebrow + título con gradiente blanco→rojo), logo
flotante sin bordes sobre glow difuminado, botones sociales, gauges con
anillo SVG, tarjetas/`dph-grid`, tiles de acceso rápido con flecha fantasma,
chips, gráfico SVG, encabezados de sección, **ensamblado al scroll**
(`.dph-reveal`/`.dph-in`, blur 6px + translateY + stagger), entrada del hero,
`prefers-reduced-motion` completo y responsive (1180/1024/768/640/560/380px).
También la **portada de perfil** (`.dph-profile-*`: banner gradiente + logo
watermark, avatar 92px sobrepuesto con status dot, tabs pill con activa en
rojo).

El prefijo `dph-` es deliberado: no colisiona con Tailwind ni con clases
legacy (`.stat-card`, `.glass-card`) de los HTML grandes.

### `js/claut-panel.js`
Componente estilo `claut-page-header.js`: un `<script>` con `data-attributes`
inyecta el hero completo y corre los motores.

```html
<link rel="stylesheet" href="./css/claut-panel.css?v=20260903a">
<script src="./js/claut-panel.js?v=20260903a"
        data-eyebrow="Beneficios exclusivos" data-title="Descuentos"
        data-tagline="Convenios para los socios del <strong>Clúster</strong>"
        data-reveal=".discount-container, .descuentos-info-card"></script>
```

- `data-sequence="off"` desactiva la secuencia; `data-compact="0"` usa el
  hero alto del dashboard.
- **Secuencia**: reutiliza los 168 frames de la landing
  (`/landing/assets/sequence/`), carga 1 de cada 2 (~2 MB) con prioridad
  progresiva; el navegador los cachea entre páginas (mismas URLs). Scrub por
  scroll con `requestAnimationFrame` + listener `{capture:true, passive:true}`
  (funciona aunque el scroll ocurra en un contenedor interno). Resize con
  debounce, dpr cap 2.
- **Reveals**: `IntersectionObserver` sobre los selectores de `data-reveal`
  (threshold 0.12, rootMargin -8%), stagger 90ms por lote, `unobserve` tras
  entrar. **Solo bloques estáticos** — el observer corre una vez al inicio;
  no apuntar a contenido generado por JS después.
- Todo desactivado con `prefers-reduced-motion`.

### `assets/js/claut-page-header.js` **v2** (navbar homologado)
**Causa del bug reportado**: la v1 estilizaba el dropdown con utilidades
Tailwind (`w-52`, `py-2.5`, `rounded-xl`) que no existen en páginas sin
Tailwind CDN — en `dashboard.html` (CSS compilado de Argon) el dropdown se
veía como texto plano sin caja.

**Solución v2**: el componente inyecta su **propio `<style>`** (id
`cph-styles`, clases prefijo `cph-`) con TODO el diseño: navbar sticky con
borde hairline degradado rojo, punto de estado + breadcrumb, welcome con
gradiente, pill de usuario con avatar, dropdown glass (cabecera nombre/rol,
ítems con íconos, logout rojo), botón admin circular 44px, login pill,
responsive 640px. Al inyectarse en el `<body>` gana los empates de
`!important` contra reglas legacy por orden de aparición; la especificidad
`.porsche-navbar[navbar-main]` (0,2,0) le gana a las reglas legacy de
elemento `nav {...!important}` de dashboard.

Mantiene los mismos IDs de la v1 (compatibilidad con el JS de cada página) y
la misma lógica de sesión multi-fuente.

**Al integrar a producción hay que quitar** los bloques end-of-body de
`dashboard.html` que re-estilizaban `.porsche-navbar`/`.porsche-welcome`
(gradiente granate y "navbar refinado") — ya se quitaron en
`dashboard-preview.html`; dejarlos ganaría el empate por orden y rompería la
homologación.

## Fases ejecutadas

- **Fase 1** — Bloques redundantes eliminados de los previews: hero legacy
  "Boletines Informativos" y header "Centro de Documentación" (boletines;
  el contador `#totalDocumentos` vivía ahí → su asignación ahora lleva
  null-check), CTA "¿Listo para Transformar tu Negocio?" (eventos, su botón
  no tenía acción).
- **Fase 2** — Navbar homologado (v2 arriba). Verificado idéntico en
  dashboard-preview y empresas-convenio-preview, dropdown incluido.
- **Fase 3** — `profile-preview.html`: además de la portada nueva, se
  **saneó** el HTML: el navbar legacy propio (líneas 335–460, que incluía un
  bloque duplicado huérfano con segundo `#userInfo`/dropdown fuera del
  `<nav>` y markup corrupto en 518–523) se reemplazó por el componente
  compartido; la tarjeta de perfil vieja (blobs morados) se reemplazó por
  `.dph-profile-cover` **conservando los IDs que puebla el JS existente**
  (`profileName`, `profilePosition`, `profileDepartment`, `profileTabs`,
  botones `.tab-btn[data-tab]`). Switch de tabs verificado funcionando.
- **Fase 5** — Este documento.
- **Fase 4 (hecho)** — reconstruido `dashboard.html` a partir del
  `dashboard-preview.html` ya verificado (única divergencia real: la línea
  de `auth-session.js`, reactivada). `profile.html` recibió la misma
  cirugía que su preview (navbar compartido + portada). Las 6 páginas
  restantes recibieron el mismo diff que sus previews (link+script del
  hero + eliminación de bloques redundantes), verificado con `diff` contra
  cada `*-preview.html` — la única diferencia esperada eran las líneas de
  auth (que en las reales SÍ deben quedar activas). **BUG-026 encontrado
  en el camino**: solo `profile.html` traía `?v=` en su
  `<script src="claut-page-header.js">`; las otras 7 páginas lo cargaban
  sin versión — corregido con `?v=20260903b` en las 8. Deploy vía FTP
  verificado con `curl` + `grep` contra producción (contenido real, no
  caché) para los 14 archivos (8 páginas + 3 compartidos + los 3 de las
  Fases 6-8).

## Fases 6-8 — Bugs reportados aparte, corregidos antes del deploy

- **Fase 6 — `pages/sign-in.html`**: `body{overflow:hidden}` + `height:100vh`
  fijo cortaba el badge "Desarrollado por LUMIA" en formularios altos o
  viewports bajos — cambiado a `min-height` + `overflow-y:auto` en
  `.left-panel`. Único breakpoint existente era 1024px (oculta el
  carrusel); se agregaron 640px/380px para el padding de la tarjeta.
  `.carousel-overlay` se desvanecía a "transparent"/blanco por la derecha,
  dejando el texto (`color:#1e293b`, oscuro) ilegible sobre fotos claras —
  reescrito como scrim oscuro anclado abajo-izquierda + texto forzado a
  blanco con `text-shadow` de 3 capas, legible sobre cualquier imagen.
- **Fase 7 — `admin-panel.html`**: quitado el badge "Vista General" (el
  `<p id="sectionIndicator">` se conserva con `sr-only` porque
  `showSection()` lo sigue actualizando al navegar entre secciones); título
  "Panel de Administración" agrandado con regla roja lateral, mismo
  lenguaje visual que el resto del rediseño. Mounts de búsqueda/
  notificaciones con `display:flex;align-items:center` explícito.
  Breakpoints 640px/420px agregados.
- **Fase 8 — `calendario.html`**: el fix previo (header-console con wrap +
  scroll horizontal) resolvía el header pero NO la tabla de FullCalendar —
  sus 7 columnas de día se aplastaban al ancho del teléfono
  ("LUNMARMIÉ..." ilegible) y en pantallas muy angostas el overflow
  escapaba de `.glass-panel` forzando scroll horizontal de toda la
  página. Fix: `.glass-panel{overflow-x:auto}` + `.fc,.fc-view-harness
  {min-width:640px}` en `max-width:900px` — la tabla mantiene un ancho
  legible y scrollea contenida, sin arrastrar el resto de la página.
- **Nota de verificación**: el servidor PHP local (`php -S localhost:8090`)
  se cayó 3 veces durante esta ronda y la herramienta de resize de
  ventana del navegador no funcionó en ningún intento — Fase 6 y 7 se
  verificaron visualmente en desktop con éxito; Fase 8 y el responsive
  detallado de Fase 7 se verificaron solo estáticamente (sintaxis, balance
  de tags, selectores confirmados contra el DOM real, mismo patrón de
  breakpoints ya probado exitosamente en 7 páginas esta sesión) — no hubo
  captura de pantalla en viewport móvil real para estas dos.

## Fase 6 (cont.) — carrusel de sign-in.html seguía roto con banners reales

El scrim de la Fase 6 se diseñó pensando solo en el banner de respaldo
genérico (`mostrarBannerDefault()`, foto de stock sin texto propio). Con
sesión real se vio que los banners que sube el admin (ej. el anuncio "BAM
México") son imágenes de marketing YA diseñadas con su propio texto — el
scrim las oscurecía sin necesidad, tapando contenido que ya era legible.
Fix: `.carousel-overlay` bajó de un oscurecimiento agresivo (0.96 en el
punto más oscuro) a un tinte sutil (0.4 máximo); `renderBanners()` ahora
solo inyecta el bloque `.carousel-content` (h2/p) si `banner.titulo` viene
con contenido, evitando una caja de texto vacía sobre banners que no la
necesitan. También se agregaron breakpoints por **altura**
(`max-height:760px/640px`) — el formulario completo (logo+título+email+
password+botón+visitante+registro+badge LUMIA) no cabía sin scroll en
laptops de pantalla baja; se compactó el espaciado vertical para que quepa
sin scroll en la mayoría de las pantallas reales.

## Fase 9 — errores de consola con sesión real (Septiembre 2026)

Con una sesión de admin real se pudo ver la consola completa por primera
vez, revelando 3 problemas independientes:

- **Botón de notificaciones no mostraba nada — causa raíz real**:
  `api/notificaciones.php` llamaba a `session_start()` a secas dentro de
  `getCurrentUserEmail()`/`getCurrentUserRole()`. El login real
  (`login-compatible.php`, `api/auth/session.php`) usa
  `config/session-config.php` → `SessionConfig::init()`, que fija un
  **nombre de cookie personalizado** (`CLAUT_SESSION`) en vez del
  `PHPSESSID` por defecto de PHP. Un `session_start()` sin ese config abre
  una sesión completamente distinta (busca la cookie `PHPSESSID`, que no
  existe), así que `$_SESSION` siempre estaba vacío ahí — 401 "Usuario no
  autenticado" sin importar que el usuario sí tuviera sesión iniciada.
  Fix: `notificaciones.php` ahora incluye `session-config.php` y llama
  `SessionConfig::init()` igual que el resto del sistema de auth.
  **Se encontraron otros 4 archivos con el mismo patrón roto**
  (`api/get-avatar.php`, `api/restricciones.php`,
  `api/mensajes_usuario.php`, `api/admin/manage_users.php`) — no se
  tocaron por estar fuera del reporte del usuario, pero es altamente
  probable que tengan el mismo bug de sesión "fantasma".
- **`TypeError: null is not an object` en `totalUsers`/`totalCompanies`/
  `totalEvents`/`totalBulletins`**: una tarjeta de resumen de estadísticas
  se eliminó del HTML de `admin-panel.html` en algún momento, pero quedaron
  10 referencias JS huérfanas apuntando a esos IDs sin `getElementById`
  null-check. Se envolvieron las 10 con guardas (mismo patrón ya usado en
  el propio archivo para `totalEventsElement`, línea ~3076).
- **CSP bloqueaba fuentes e imágenes legítimas**: `font-src` no incluía
  `data:` (Font Awesome vía `kit.fontawesome.com` a veces inyecta una
  fuente embebida en base64) e `img-src` no incluía
  `https://images.unsplash.com` (imagen de respaldo hardcodeada en
  `api/eventos.php` para eventos sin foto propia) — el segundo caso además
  generaba "Redirect was not allowed" porque el endpoint interno
  `/api/eventos.php?action=imagen` redirige a esa URL externa. Ambos
  orígenes se agregaron al CSP existente en `.htaccess` (adición, no se
  quitó ninguna protección existente).

## Fase 10 — Sesión "fantasma" en 4 archivos más (mismo patrón de BUG-035)

Al terminar la Fase 9, se auditaron TODOS los archivos con `session_start()`
sin `session-config.php`:

- `api/restricciones.php` y `api/admin/manage_users.php` — **en uso real**
  (`gestionar_usuarios.php`/`js/session-security.js` y
  `admin_usuarios.html` respectivamente). El segundo tenía además un bug
  más profundo: comparaba `$_SESSION['user_id']`/`['rol']`, claves que el
  login real nunca escribe — bloqueaba aprobar/rechazar usuarios para
  admins reales incluso arreglando el nombre de cookie. Corregido para
  usar `user_email`/`user_rol` con la normalización `['admin','administrador','root']`.
- `api/get-avatar.php` y `api/mensajes_usuario.php` — cero referencias en
  todo el sitio (código muerto), corregidos por consistencia.
  `mensajes_usuario.php` tenía además su etiqueta de apertura corrupta
  (`&lt;?php` literal) — nunca se ejecutaba como PHP.

## Fase 11 — Migración parcial de `demo_estadisticasdinamicas.html`

Colores claros corregidos (modales, selectores, query builder) y
desplegados. Al auditar contra la lista completa de clases que remapea
`.claut-skin` se encontró que el archivo depende de **~90 clases Tailwind
claras** en el HTML estático (comparable a `demo_empresas.html`, 105) —
mucho más grande de lo que el registro anterior indicaba. Migración
parcial hecha: sidebar (`.claut-nav-icon-badge`, 11 íconos) y botón
"Dashboard" del header (`.claut-header-ghost-btn`, replicando la intención
original del propio `claut-skin` de convertirlo en "ghost glass" en vez de
blanco sólido). **`claut-skin` sigue activo en el `<body>`** — quitarlo
ahora rompería las ~80 clases restantes sin migrar (stat cards, botones de
acción, panel de edición rápida). Pendiente continuar.

## BUG-036 y FIX-037 — ver `claude.md` para el detalle completo

Documentados en la bitácora principal del proyecto: el panel "Centro de
Mensajes" de `dashboard.html` tenía un bloque de depuración olvidado que
forzaba un panel de 400px blanco vía estilos inline sobre el CSS premium
ya diseñado; y una ronda de bugs de sesión real en `admin-panel.html`
(notificaciones recortadas por `overflow:hidden`, script duplicado,
referencias JS huérfanas), `sign-in.html` (segunda vuelta de ajuste) y
`sign-up.html` (campo "Otros" editable, verificación de guardado en BD).
