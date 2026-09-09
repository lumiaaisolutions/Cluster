# CLAUT Admin Elite — reskin 2026 del panel de administración

**Fecha**: 2026-09-08 · **Estado**: desplegado en producción

## Qué es

Capa de reskin visual para los 12 módulos del panel de administración, basada en la
propuesta interactiva aprobada por el usuario (artifact v3/v4: bento grid colorido,
rail de navegación vertical a toda altura, saludo personal, superficies sin bordes
ni blur, botones píldora, acento lima secundario).

**Principio de diseño de la implementación**: la capa reestiliza las clases
compartidas QUE YA EXISTEN (`.claut-admin-sidebar`, `.claut-stat-card`,
`header.claut-admin-main`, `.glass-card`) — **cero cambios de markup en
formularios, cero cambios en el JS de CRUD**. Cada página solo recibió 2 líneas.

## Archivos

| Archivo | Rol |
|---|---|
| `css/claut-admin-elite.css` | Todo el reskin (rail, bento, header, píldoras, superficies) |
| `js/claut-admin-elite.js` | Saludo "Hola, {nombre}" + barras decorativas de la stat-card destacada. 100% aditivo y null-safe |

### Páginas que lo cargan (12)

`admin-panel.html`, `calendario.html`, `demo_boletines.html`, `demo_comite.html`,
`demo_descuentos.html`, `demo_documentos.html`, `demo_empresas.html`,
`demo_evento.html`, `demo_gestion_grafico.html`, `demo_visitante.html`,
`gestionar_usuarios.php`, `admin/banner-admin-mejorado.php` (con rutas `../`).

Patrón por página (versionado obligatorio, regla BUG-026):

```html
<link rel="stylesheet" href="./css/claut-admin-elite.css?v=20260908a">  <!-- antes de </head> -->
<script src="./js/claut-admin-elite.js?v=20260908a"></script>            <!-- antes de </body> -->
```

## Qué transforma (y qué NO toca)

1. **Rail** — `.claut-admin-sidebar` pasa de sidebar 260px a rail flotante de 74px
   tipo píldora con los botones distribuidos en toda la altura (`space-evenly` +
   `display:contents` en los grupos). Las etiquetas `<span>` existentes se vuelven
   tooltips al hover. En móvil (≤1024px) el mismo `.show` del JS existente abre el
   drawer expandido con etiquetas visibles. El estado `.collapsed` queda
   neutralizado (el rail ya es angosto siempre); el botón hamburger sigue
   funcionando en móvil sin cambios de JS.
2. **Bento stats** — las variantes `.claut-stat-card--red/green/blue/gold/purple`
   pasan a gradientes vivos; `--green` usa el acento lima con texto oscuro. Cuando
   la fila tiene exactamente 4 tarjetas (patrón dominante), la primera se destaca
   con `grid-column:span 2` sobre una grilla de 5 (`:has()` + `:nth-last-child(4)`,
   solo ≥900px — en móvil manda el `grid-cols-1` de Tailwind de siempre).
   `claut-admin-elite.js` inyecta barras decorativas en la primera tarjeta.
3. **Header + saludo** — `header.claut-admin-main` queda transparente; el JS
   inyecta después del header el bloque "Hola, {nombre}" (nombre desde
   `localStorage.userData/currentUser/userInfo`, fallback "Administrador") con el
   `<h1>` original como subtítulo. El `<h1>` solo se oculta si el saludo se insertó
   (clase `body.elite-greeted`) — en páginas sin ese header (admin-panel usa
   `.top-navbar`) no pasa nada.
4. **Botones** — `button/a` con `.rounded-lg/.rounded-xl` → radio píldora +
   feedback de presión `scale(0.96)`.
5. **Superficies** — `.glass-card` y los contenedores con el borde inline estándar
   del proyecto pierden borde y blur (`backdrop-filter:none`), quedando como
   rellenos translúcidos sobre el fondo con glow que la propia capa agrega vía
   `body::before` (gradientes radiales, sin blur).

**No toca**: formularios (IDs/names/validaciones), wizard (`claut-wizard.css` sigue
íntegro), llamadas a APIs, `claut-admin-sidebar.js`, flujos de aprobación, tablas
generadas por JS.

## Verificación realizada

- `node --check` sobre `claut-admin-elite.js` — limpio.
- `php -l` sobre `gestionar_usuarios.php` y `admin/banner-admin-mejorado.php` — limpio.
- Visual en Chrome local (`php -S`) con copias temporales sin el guard de auth
  (borradas después): `demo_boletines.html` (rail + saludo con nombre real +
  bento 2+1+1+1 + píldoras + sin bordes, todo correcto) y `admin-panel.html`
  (rail + tarjetas de módulos correctos; el saludo NO se inyecta ahí por diseño).
- **Falso positivo documentado**: en la copia local de `admin-panel.html` el
  `.top-navbar` quedaba invisible (opacity 0). Causa: el `gsap.from()` de entrada
  quedaba congelado en el estado inicial — artefacto de haber quitado los scripts
  de auth en la copia, NO de la capa elite (que no toca opacity ni GSAP; verificado
  con grep y con inspección de hojas de estilo en vivo). En producción con sesión
  real ese código lleva semanas funcionando.
- Producción tras deploy: `claut-admin-elite.css`/`.js` sirven HTTP 200 y
  `admin-panel.html` referencia ambos.

## Módulo Banners en la propuesta

El artifact de propuesta (v4) incluye el módulo de Banners del login con sus campos
reales verificados en `admin/banner-admin-mejorado.php`: título (req.),
descripción (opc.), imagen local o URL (`tipo_imagen` radio), posición (number),
activo (checkbox), `fecha_inicio`/`fecha_fin` (datetime-local). La página de
banners recibió la misma capa elite que el resto.

## Reversión

Quitar las 2 líneas (`link` + `script`) de una página la regresa exactamente a su
estado anterior — la capa no modifica nada más de esa página.

---

# Ronda 2 (2026-09-08, tarde) — correcciones con sesión real del usuario · `?v=20260908b`

El usuario revisó los 12 módulos en producción y pidió una ronda grande de
correcciones. Todo desplegado y verificado. Detalle por fase:

## A. Capa elite v2 (`css/claut-admin-elite.css`)
- **Logo del rail**: fondo blanco (el logo del Clúster no debe llevar fondo rojo),
  56px (antes 46px con gradiente rojo).
- **Centrado global**: TODOS los módulos ahora centran su contenido en el espacio
  restante junto al rail (antes quedaban pegados a la izquierda, ej. demo_evento
  vs gestionar_usuarios). Método: `body:has(.claut-admin-sidebar){padding-left}` +
  contenedores con `max-width:1360px; margin-inline:auto !important`.
  **Bug propio encontrado y corregido en el camino**: el primer intento puso el
  `padding-left` en `.claut-admin-main`, pero las utilidades `px-*` de Tailwind
  CDN se inyectan en runtime DESPUÉS de cualquier hoja estática y ganan el empate
  de especificidad — el contenido se encimó al rail (detectado en verificación
  visual local antes de desplegar). Regla: offsets de layout que convivan con
  Tailwind CDN van en `body` o con especificidad (0,2,0)+.
- **Componentes nuevos compartidos** (sección 6): `.elite-grid`, `.elite-card`
  (+cover/body/meta/foot/logo), `.elite-card-cover--contain` (pósters sin
  recortar), `.elite-btn--view/--edit/--del` (lenguaje único de acciones:
  Ver azul / Editar ámbar / Eliminar rojo), `.elite-chip(--link)`,
  `.elite-filter-chips`. Los usan todos los renders de módulos.

## B. Documentos = diseño de Boletines
`demo_documentos.html`: la tabla se reemplazó por la lista de tarjetas idéntica
en estructura a `createBulletinCard` de boletines (título+desc+badge, fecha/
categoría/autor, pie con "Ver archivo" + Ver/Editar/Eliminar píldora). Badges
con valores reales: publico→info, privado→neutral, restringido→danger. El botón
Ver reutiliza el anchor de apertura de archivo existente (la página nunca tuvo
visor propio — no se inventó uno).

## C. Descuentos sin scroll horizontal + botón Ver
`demo_descuentos.html`: tabla de 9 columnas (causa del scroll) → tarjetas
apiladas con chips (empresa, %/monto, código, vigencia, usos). Se eliminó el
`min-width:800px` del media query. **Hallazgo**: `handleSearch`/`handleFilter`
eran stubs vacíos — el buscador y filtros de esta página NUNCA funcionaron;
se implementó `applyFilters()` real (busca por data-search, filtra estado y
empresa). **Botón Ver**: no existía función de detalle → nuevo `viewDescuento(id)`
+ modal de solo lectura que lee del caché del listado (sin fetch extra).

## D. Comités (3 sub-fixes en `demo_comite.html` + `js/admin-comites.js`)
- **Portadas sin recortar**: `createComiteCard` ahora usa `elite-card-cover--contain`
  — las imágenes de comités son pósters con texto ("CÓMITE COMERCIO...") que
  `bg-cover` decapitaba; con contain se ven completas sobre fondo oscuro. Tarjetas
  migradas a `elite-card` con badge de estado real (activo/inactivo/suspendido) y
  botón **Ver** nuevo (`verComite()`: overlay de solo lectura con imagen, objetivo,
  periodicidad, miembros; botón para saltar a Editar).
- **Centro de Comunicación sin textos técnicos**: el campo `contenido` de los
  mensajes puede venir como JSON serializado (`{"archivo":"uploads/...","descripcion":"ab"}`)
  y se mostraba CRUDO. Ahora se parsea: descripción como texto + chip clickeable
  de adjunto (nombre real del archivo) o de link. Badges "NORMAL"/"NO_LEIDO" →
  "Normal"/"Sin leer".
- **Panel de Admisiones**: la columna "Enviado por" mostraba IP en texto visible —
  ahora la IP va en tooltip (title) y el texto se limpia del prefijo redundante.

## E. Socios/Empresas en tarjetas
`demo_empresas.html` + `js/admin-empresas.js`: tabla → `elite-grid` de tarjetas
con logo real en `elite-card-logo` (fondo blanco 52px, mismo fallback onerror),
estrella dorada si destacada, badges de estado, chips de contacto, y
Ver/Editar/Eliminar conectados a `mostrarDetallesEmpresa`/`editarEmpresa`/
`eliminarEmpresa` existentes. Filtros intactos (filtran por datos, no por DOM).

## F. Eventos en tarjetas + adiós Unsplash
`demo_evento.html` + `js/demo-eventos.js`: tabla → tarjetas con la imagen REAL
del evento como portada grande; si no hay imagen → degradado CSS con ícono de
calendario (la foto de Unsplash hardcodeada quedó en CERO ocurrencias en el
flujo de listado). Badges de estado con etiquetas reales (Publicado/En
Preparación/Histórico/Cancelado), aforo actual/máximo, y acciones Registros/
Editar/Eliminar con las funciones existentes. **Pendiente reportado sin tocar**:
`api/eventos.php` (líneas ~89/136) aún redirige a Unsplash en el caso borde
"imagen registrada en BD pero archivo perdido en disco" — flujo backend distinto.

## G. Usuarios en tarjetas + filtros por rol
`gestionar_usuarios.php`: tabla PHP (que además tenía un desajuste real de 6
columnas declaradas vs 9 celdas renderizadas) → `elite-grid` de tarjetas con
avatar de iniciales coloreado por rol (admin rojo/empresa azul/empleado slate),
badge de estado, chips de empresa/cargo/teléfono/fecha, y acciones: Editar,
Accesos (restricciones), Activar/Pausar y Eliminar (mismos forms POST con
confirm). **Filtros nuevos por tipo de usuario**: chips Todos/Administradores/
Empresas/Empleados (`data-rol`) combinables con el buscador (`data-search`).
`filterUsers()` reescrita para tarjetas.

## H. Calendario como la propuesta
`calendario.html`: leyenda de categorías con los 4 colores reales (Asamblea
rojo/Networking azul/Capacitación verde/Comunicado ámbar) + celdas del mes
redondeadas tipo bento (fondo `rgba(0,0,0,.18)`, hover, día de hoy tintado en
rojo) — solo CSS sobre FullCalendar, cero cambios de JS.

## Verificación de la ronda
- `node --check`: 4 JS tocados + inline scripts de 5 HTML — todo limpio.
- `php -l`: gestionar_usuarios.php y banner-admin-mejorado.php — limpio.
- Visual local (Chrome + php -S, copia temporal sin auth): regresión de centrado
  detectada y corregida ANTES de desplegar; estado final verificado (rail, logo
  blanco, bento, contenido centrado).
- Producción: CSS v2 servido completo (verificado desde navegador real).
  **Nota**: el WAF de Hostinger empezó a responder 403 a `curl` tras muchas
  peticiones seguidas — es rate-limiting del fingerprint de curl, NO un problema
  del sitio (el navegador real recibe 200). Verificar producción desde el
  navegador cuando pase.
- 17 archivos desplegados vía FTP (`?v=20260908b` en las 12 páginas).

---

# Ronda 3 (2026-09-08, tarde-2) — centrado real, header unificado, móvil, branding del rail · `?v=20260908c`

Tres reportes más del usuario con capturas, resueltos en una sola pasada sobre la capa:

## 1. Centrado que "no funcionaba" (banner-admin pegado a la izquierda)
**Causa raíz encontrada**: el usuario tenía `.collapsed` persistida en localStorage
(de colapsar el sidebar viejo). Mi regla `body:has(.claut-admin-sidebar.collapsed)
.claut-admin-main { margin-left:0 !important }` tiene especificidad **(0,3,1)** —
`:has()` cuenta la especificidad de su argumento — y le ganaba a la regla de
centrado de los `.container` (0,2,0), forzando margin-left:0. **Fix**: eliminar esa
regla duplicada (el `.claut-admin-main{margin-left:0!important}` simple ya cubre el
caso, porque `!important` le gana al `margin-left:76px` sin important de
admin-sidebar.css sin importar especificidad). Verificado en local simulando
`.collapsed`: gaps izquierdo/derecho de 229px/229px exactos.
**Regla**: cuidado con `:has()` en overrides — su especificidad heredada puede
pisar reglas hermanas sin que el orden del archivo lo salve.

## 2. Móvil roto (drawer en 74px con etiquetas asomando por el borde)
Misma causa (.collapsed persistida): en móvil las reglas de escritorio
`.claut-admin-sidebar.collapsed{width:74px}` y `.collapsed:hover` (especificidad
mayor que las del media query) dejaban el drawer angosto y las etiquetas-tooltip
sobresalían de la pantalla. **Fix doble**: (a) CSS — todos los selectores del
bloque móvil ahora incluyen las variantes `.collapsed` y `.collapsed:hover`;
(b) JS — `claut-admin-elite.js` remueve `.collapsed` al entrar a viewport ≤1024px
(es un concepto solo de escritorio) y escucha el matchMedia por si se rota/resize.

## 3. Hamburguesa fuera de escritorio
`#claut-header-menu-btn{display:none!important}` en ≥1025px — el rail siempre está
visible en PC y el botón era un no-op confuso. En móvil sigue abriendo el drawer.

## 4. Header unificado en todos los módulos
`injectGreeting()` generalizada: ancla en `header.claut-admin-main` **o** en el
`<header>/<nav>` que contenga la hamburguesa — con eso demo_evento (header propio
"Gestión de Eventos") y admin-panel (top-navbar) reciben el mismo saludo
"Hola, {nombre}" + subtítulo del módulo que el resto. El `<h1>` original se oculta
vía clase `.elite-hidden-title` (solo si el saludo se insertó). **Excepción
deliberada**: calendario.html se excluye del saludo — su layout es 100vh sin
scroll y un bloque extra descuadraría el alto del calendario; ahí la unificación
se limita a ocultar la hamburguesa en desktop.

## 5. Branding del rail (referencia visual del usuario)
`.claut-admin-sidebar-brand`: degradado blanco→negro que cubre TODA la sección
superior del rail (blanco 0% → grises → transparente hacia el negro del rail),
con el radio superior de la píldora (37px). Logo a 64px **sin fondo, sin círculo,
sin sombra** — la imagen flota sobre el degradado, como el diseño de referencia.
En el drawer móvil el degradado va full-bleed con el radio 0 26px.

Deploy: CSS + JS + 12 páginas con `?v=20260908c`. Verificación visual local del
rail/saludo/centrado antes de subir; sintaxis `node --check` limpia.

---

# Ronda 4 (2026-09-08, tarde-3) — lote de 8 reportes con capturas · `?v=20260908d`

1. **admin-panel descentrado (hacia la derecha)**: su `<style>` inline post-sidebar
   forzaba `margin-left:260px !important` (parche pre-elite) que se SUMABA al
   padding del rail. → `margin-left:0 !important` siempre.
2. **Calendario "desfasado" (~420px de ancho)**: la regla de centrado
   `margin-inline:auto` sobre un flex-item SIN width lo encoge a su contenido
   (shrink-to-fit) → se agregó `width:100%` a la regla. **Regla**: centrar
   flex-items con margin auto exige width explícito.
3. **Franja oscura izquierda en móvil (todas las páginas)**: el
   `body{padding-left:102px}` del rail no se reseteaba en ≤1024px → agregado
   `padding-left:0` móvil.
4. **Vista Previa**: banner-admin (botón Ver por banner → abre la imagen),
   demo_boletines (barra → `boletines.html` + botón Ver por boletín con overlay
   de lectura), demo_documentos (barra → `boletines.html`), demo_evento
   (reemplaza el chip "Conectado" → `eventos.html`).
5. **demo_evento franja negra**: su `.glass-header` era `position:fixed` con
   fondo oscuro; con el saludo elite debajo tapaba contenido → estático y
   transparente vía elite.css (scoped a páginas con rail). El chip "Conectado"
   eliminado a petición (span#userInfo se conserva oculto: el JS de auth le
   escribe).
6. **Panel de Admisiones (demo_comite)**: tabla de 9 → 5 columnas (Usuario con
   empresa+email, Comité con cargo, Estado, Fecha, Acciones) — se acabó el scroll
   horizontal. Acciones nuevas por fila: **Ver** (overlay con TODO el detalle,
   incl. remitente/procesado), **Editar** (cambiar decisión → reutiliza
   `cambiarEstadoRegistro` aprobar/rechazar), **Eliminar** (nuevo case
   `eliminar_registro` en `api/comites.php`, DELETE preparado, mismo patrón que
   aprobar/rechazar). Pendientes conservan Aprobar/Rechazar directos.
7. **Restricciones de acceso (gestionar_usuarios)**: los subtítulos técnicos
   `eventos.html`, `boletines.html`… del selector de páginas eliminados — quedan
   solo los nombres legibles.
8. Deploy: elite.css + api/comites.php + 12 páginas (`?v=20260908d`).

---

# Ronda 5 (2026-09-08, cierre) — móvil fino, unificación de eventos, FEATURE-032 Visitante, FEATURE-033 headers · `?v=20260908e`

## Fixes visuales/móvil (todos con captura del usuario)
- **Fondo de demo_comite**: tenía `background:#1f232a` INLINE en el `<body>` (gris
  azulado fuera de sistema) → `#09090b` estándar; el glow lo pone elite.
- **Calendario**: header-console separado del borde (margen + radio + superficie
  píldora); en móvil el cluster de controles envuelve y la fecha (que se cortaba)
  baja a su propia línea centrada.
- **Móvil — botones amontonados** (banners/boletines/documentos): regla genérica
  ≤640px — los pies `flex.justify-between` envuelven y las acciones bajan a fila
  completa alineada a la derecha.
- **Móvil — wizard cortado** (modal Editar Usuario): `.claut-wizard` excedía el
  viewport → `max-width:calc(100vw - 20px)` + pie con wrap.
- **Móvil — navbar de admin-panel tapando el saludo**: `nav.top-navbar +
  .elite-greet { margin-top:78px }`.
- **demo_evento unificado**: `.glass-header` fijo→estático/transparente (franja
  negra fuera); chip "Conectado" eliminado → botón **Vista Previa** (eventos.html);
  en móvil sus botones se compactan a íconos junto a la hamburguesa.

## FEATURE-032 — Experiencia Visitante (aprobada punto por punto, EN PRODUCCIÓN)
- **Entrada**: botón "Entrar como visitante" en sign-in.html →
  `api/visitante.php?action=entrar` crea sesión PHP de visitante (SessionConfig,
  `$_SESSION['visitante']=true`, sin usuario), registra la visita y redirige a
  `visitante.html`.
- **`api/visitante.php`** (nuevo): `entrar`/`check`/`eventos`/`boletines`/
  `metrica`/`stats`. Crea su infraestructura al primer uso: tabla
  `visitante_metricas` (solo tipo+fecha, **cero PII** — ni IP ni user agent) y
  columna `eventos.visible_visitantes` (ALTER tolerante a duplicado).
- **`visitante.html`** (reescrito, autocontenido): hero híbrido con la secuencia
  de rayos-X real de la landing en canvas **con 1 de cada 4 frames (42 de 168,
  ~4× más ligero)** scrubbed por scroll; header píldora "Modo visitante" con
  Salir y CTA; bento de módulos con candado; **probadita real**: eventos con
  `visible_visitantes=1` futuros (portada con su imagen real o degradado) y
  boletines `publicado` (resumen 220 chars + Leer si hay adjunto); CTAs a
  sign-up + `mailto:atencion@clautedomex.mx` + WhatsApp (número de la landing —
  **confirmar el real con el Clúster**). Todos los CTAs reportan métrica
  (sendBeacon). Guard por `action=check`.
- **Flag en eventos end-to-end**: checkbox "Mostrar en el portal de visitantes"
  en demo_evento.html; demo-eventos.js lo envía y lo puebla al editar;
  api/eventos.php lo persiste en INSERT y UPDATE.
- **demo_visitante.html** (Control de Acceso) por fin sirve: franja "Actividad
  de visitantes · 7 días" con visitas / clics registro / WhatsApp / correo /
  interés en eventos vía `action=stats` (solo admin).

## FEATURE-033 — Headers de socio sin botones muertos
En los 8 paneles de socio el menú funcional es el dropdown `cph-` de
claut-page-header.js. El ícono suelto `#headerUserMenuBtn` (no hacía nada) y la
hamburguesa `#mobileMenuBtn` (obsoleta con el riel/tab-bar) se **ocultan por CSS**
en `header-navbar.css` (+ inline en dashboard.html, que no linkea ese archivo).
NO se eliminó el markup a propósito: header-navbar.js les cuelga listeners por id
y removerlos rompería el JS con null-refs.

## Verificación y deploy
`php -l` limpio (visitante, eventos, gestionar, banner); `node --check` limpio en
todos los tocados (dashboard.html reporta el artefacto conocido de extracción de
template-literals — HEAD falla idéntico, diff propio = 2 líneas de CSS);
27 archivos desplegados; `?v=20260908e` en las 12 páginas admin y
`header-navbar.css?v=20260908e` en las 7 de socio.

# Ronda 6 (2026-09-09) — responsive móvil real + subcategorías del sidebar · `?v=20260909a`

Reportes del usuario con capturas de iPhone (Safari, producción).

## BUG-045 — Sidebar móvil amontonado sobre "Cerrar sesión" (causa raíz doble)
1. `.claut-admin-sidebar { overflow: visible }` (regla del rail de escritorio,
   pensada para que los tooltips no se recorten) no tenía media query — en el
   drawer móvil los ~13 ítems desbordaban el alto disponible SIN scroll,
   encimándose sobre el botón de logout ("Usuarios" literalmente encima de
   "Cerrar sesión"). Fix: `overflow-y:auto` en ≤1024px.
2. `justify-content: space-evenly` en `.claut-admin-nav` (layout del rail)
   forzaba a repartir los ítems en toda la altura aunque no cupieran. Fix:
   `flex-start` en móvil.

## FEATURE-035 — Subcategorías desplegables del sidebar (móvil y PC)
El HTML de las 12 páginas YA agrupa en `.claut-admin-nav-group` con
`.claut-admin-nav-label` ("General"/"Ecosistema de módulos"/"Sistema") — la
capa elite solo lo aplanaba. `initNavGroups()` en `claut-admin-elite.js`
vuelve colapsable cualquier grupo con >3 ítems (solo "Ecosistema", 9-10):
- la etiqueta se convierte en botón con chevron (texto envuelto en `<span>`
  propio para ocultarlo SOLO en el rail de escritorio — bug propio cazado:
  la v1 desbordaba el texto sobre el botón redondo);
- estado persistido en localStorage por nombre de grupo; expandido por
  defecto solo si contiene la página activa;
- móvil: encabezado de sección clickeable; escritorio: botón redondo más
  del rail con tooltip del nombre del grupo.
Cero cambios de markup en las 12 páginas.

## BUG-046 — Tarjeta de banner no responsive (admin/banner-admin-mejorado.php)
Miniatura 128×96 fija + contenido lado a lado sin punto de quiebre → en
angosto el título/badge quedaban aplastados. Fix: apilado en ≤640px (imagen
arriba a todo el ancho 160px, contenido abajo, fila título+badge con wrap).

## BUG-047 — Eventos: botones Vista Previa/Crear flotando antes de las stats
El `.glass-header` de demo_evento.html apila sus botones en móvil ANTES del
saludo/stats — hueco raro con 2 botones sueltos. Fix: se ocultan en el header
en ≤768px y una copia visible-solo-móvil (mismo href / mismo onclick) se
inserta después del grid de stats (`.elite-mobile-header-actions`).

## BUG-048 — Widget "Agenda" del dashboard con controles fuera de pantalla
No era calendario.html: es el preview del dashboard (visible para admins).
La fila título+prev/hoy/sig+mes+badge no tenía flex-wrap → el botón
"siguiente" y el badge quedaban cortados fuera del viewport. Fix: wrap en la
fila y su grupo, badge a fila propia centrada en ≤640px.

## BUG-044 (páginas de socio, misma sesión) — navbar pegado al header en móvil
comites/boletines/descuentos/dashboard llevaban `padding-top: 56px !important`
hardcodeado en su media query móvil — valor de cuando el header medía menos,
nunca actualizado al crecer a 80px (FEATURE-027). contacto.html (la única
sin hardcode, con `calc(var(--header-height)+1rem)`) era la referencia que
sí se veía bien. Homologadas las 4 al calc; descuentos además tenía padding
redundante en el propio nav que causaba salto de línea de los botones; el
dashboard tenía una línea de CSS inválida (`--header-height: 56px;` suelta
dentro de un @media, fuera de todo selector — ignorada en silencio).
Diagnóstico por MEDICIÓN en vivo (getBoundingClientRect), no por lectura
de CSS.
