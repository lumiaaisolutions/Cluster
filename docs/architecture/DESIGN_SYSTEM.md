# 🎨 CLAUT UI — Sistema de Diseño Unificado

> Creado: 2026-08-31 | Fuente de verdad: `build/css/claut-ui.css`
> Referencia visual canónica: `calendario.html` (Porsche dark + glassmorphism + Inter + rojo #C7252B)

## Principio

Un solo archivo CSS con **tokens** (variables) y **clases reutilizables** que todas las vistas comparten. Las páginas NO deben duplicar estos estilos inline: enlazan `./css/claut-ui.css` y usan las clases. El login (`pages/sign-in.html`, `sign-up.html`) y la landing (`/landing/`) quedan **fuera** del sistema por decisión de producto.

## Cómo usarlo en una página

```html
<link rel="stylesheet" href="./css/claut-ui.css">  <!-- ANTES del <style> local -->
...
<body class="claut-dark">
```

El link va **antes** del `<style>` propio de la página: así los ajustes locales pueden sobreescribir al sistema cuando sea necesario (y se detectan fácil como deuda a migrar).

## Tokens principales

| Token | Valor | Uso |
|-------|-------|-----|
| `--porsche-black` | `#09090b` | Fondo base de TODO el sistema (nunca `#1a1a1a`, `#0f0f0f` ni `#1D1D1F`) |
| `--porsche-accent` | `#C7252B` | Rojo Clúster (nunca `#c9302c`) |
| `--glass-bg` / `--glass-border` | `rgba(255,255,255,.03)` / `.08` | Superficies glassmorphism |
| `--text-primary/secondary/muted` | `#f8fafc` / `#94a3b8` / `#64748b` | Jerarquía tipográfica |
| `--state-success/warning/danger/info/gold` | verde/ámbar/rojo/azul/oro | Estados + acento oro para convenios/premium |
| `--radius-sm/md/lg` | 8px / 12px / 1.5rem | Radios unificados |
| `--sidebar-width` / `--header-height` | 280px / 80px | Layout |
| `--ease-premium`, `--t-fast`, `--t-base` | cubic-bezier(0.4,0,0.2,1) | Motion |

## Clases de componente

- **Superficies**: `.glass-panel`, `.glass-panel--hover`, `.claut-stat-card` (con `.stat-value`/`.stat-label`)
- **Tipografía**: `.claut-eyebrow` (micro-etiqueta uppercase — rasgo firma), `.claut-title`, `.claut-text-secondary/muted`
- **Botones**: `.porsche-btn` (+ `--ghost`, `--danger`, `--sm`)
- **Formularios**: `.claut-input`, `.claut-select`, `.claut-textarea`, `.claut-label`
- **Tablas**: `.claut-table` (header uppercase, hover en filas)
- **Badges**: `.claut-badge` + `--success/--warning/--danger/--info/--accent/--neutral`
- **Navegación**: `.side-nav`, `.main-content`, `.header-console`, `.porsche-nav-item` (+ `.active`)
- **Modales**: `.claut-modal-backdrop`, `.claut-modal` (+ `__header`, `__body`, `__footer`)
- **Otros**: `.claut-empty` (estados vacíos), `.claut-toast` (+ `--success/--error`)
- **Accesibilidad**: focus-visible rojo consistente, `prefers-reduced-motion` respetado, scrollbar unificado

## Reglas

1. **Nunca** hardcodear `#1a1a1a`, `#0f0f0f`, `#1D1D1F`, `#c9302c` — usar tokens. Esta ronda unificó todas las instancias existentes.
2. Página nueva = link a `claut-ui.css` + `body.claut-dark` + clases del sistema. Cero CSS duplicado.
3. Si una página necesita un ajuste, va en su `<style>` local DESPUÉS del link, comentado con el porqué — y se considera deuda a absorber por el sistema.
4. Login y landing no se tocan.

## Capa de conversión `.claut-skin` (sección 13 del CSS)

Los paneles CRUD (`demo_*.html`) estaban construidos con utilidades Tailwind claras (`bg-white`, `bg-gray-100`, `text-gray-*`) — cambiar tokens no bastaba. La sección 13 de `claut-ui.css` es una **capa de conversión** que re-mapea esas utilidades al lenguaje oscuro sin reescribir el markup:

- Se activa con `<body class="claut-dark claut-skin">`.
- Header de banda roja → consola oscura con blur y línea de acento rojo degradada.
- `bg-white` → glass panel; textos y bordes grises → jerarquía del sistema; botones de estado sólidos → versión translúcida premium; inputs → dark glass con focus ring rojo.
- Micro-interacciones (skill emil-design-eng): `scale(0.97)` en `:active` de botones, transiciones específicas 160ms ease-out (nunca `all`), entrada `fade-up` 400ms con `cubic-bezier(0.23,1,0.32,1)` respetando `prefers-reduced-motion`.
- Se eliminaron los **emojis de los `<h1>-<h3>` y `<title>`** de los módulos (el lenguaje de calendario es tipografía limpia).
- Fix de distribución: los demos arrastraban `.container { margin-left: 0 !important }` de su CSS de sidebar → regla de re-centrado con mayor especificidad.
- `demo_boletines.html`: eliminado el doble encabezado ("Lista de Boletines" + "Boletines de la Base de Datos") y la lista ahora **auto-carga** al abrir (antes exigía clic en "Actualizar").
- **Verificado visualmente** (local + Chrome): boletines, empresas y comités renderizando coherentes con calendario.html.

## Estado del rollout (2026-08-31)

| Página | Link CSS | Base unificada | Conversión completa a clases |
|--------|:--:|:--:|:--:|
| `calendario.html` (referencia) | ✅ | ✅ | ✅ (origen del sistema) |
| `boletines/comites/descuentos/contacto.html` | ✅ | ✅ token `clúster-dark` → `#09090b` | ⏳ |
| `demo_*` (×9) | ✅ | ✅ tokens locales → canon | ⏳ |
| `gestionar_usuarios.php`, `admin-panel.html` | ✅ | ✅ | ⏳ |
| `admin-2fa.html`, `admin-auditoria.html`, `mensajes.html`, `empresas-convenio.html` | ✅ | ✅ (ya eran dark) | ⏳ |
| `evento_detalle.php`, `eventos.html` | ✅ | ✅ vía tokens compartidos | ✅ (ya usaba `--claut-*`) |
| `dashboard.html` | ✅ | ✅ familia carbón `#1f232a` → `#09090b` + bordes visibles | ✅ |
| `profile.html`, `visitante.html` | ✅ | ✅ `claut-dark claut-skin` activado | ✅ |
| `pages/sign-in`, `sign-up`, `/landing/` | — | — | Excluidos (decisión de producto) |

**⏳ Conversión completa** = reemplazar el CSS inline duplicado de cada página por las clases del sistema, y convertir las páginas claras (profile, eventos, visitante, dashboard) al tema oscuro. Debe hacerse **página por página con revisión visual** — no en batch — porque cada una tiene layout Tailwind propio. Orden sugerido: dashboard → profile → eventos → visitante.

## Fase 3 del rollout (2026-08-31, tarde)

- **Hallazgo clave**: `assets/css/layout/header-navbar.css` es la fuente compartida de los tokens `--claut-*` (la usan 9+ páginas: dashboard, profile, eventos, contacto, comites, boletines, empresas-convenio, descuentos). Los fondos eran familia carbón (`#1f232a/#252a32/#2d333b/#363d47`) y los **bordes eran slate-oscuro `rgba(15,23,42,…)` — invisibles sobre fondo oscuro**.
- **Unificación**: fondos → `#09090b/#101013/#16161a/#1e1e24`; bordes/dividers → blanco-alfa (`rgba(255,255,255,.08/.14)`). Aplicado en el CSS compartido y en la copia inline de `dashboard.html`.
- `profile.html` y `visitante.html`: activado `claut-dark claut-skin` (el body de profile era blanco con cards oscuras — ahora base negra unificada). Skin extendido con utilidades Argon/slate (`text-slate-*`, `shadow-soft-*`).
- **Responsive (sección 14 del CSS)**: `overflow-x: hidden` en el body del skin, scroll táctil en tablas, botones min-height 44px en móvil (guía táctil), tipografía de header y paddings compactados en <768px, modales full-width en móvil. Viewport meta verificado presente en las 13 páginas principales.
- **Cache-busting**: `?v=20260831c` en `claut-ui.css` y `header-navbar.css` en las 24 páginas.

## Fase 4 del rollout (2026-08-31, verificación en navegador con sesión real)

Verificación visual módulo por módulo en Chrome (sesión autenticada como admin), corrigiendo en vivo lo encontrado:

- **Hallazgo raíz**: la mayoría de "vistas de usuario" (comites, boletines, descuentos, contacto, empresas-convenio, gestionar_usuarios, admin-panel, calendario) nunca tuvieron `claut-dark claut-skin` activado — solo su header/nav ya era oscuro (vía tokens `--claut-*`), pero el contenido (tarjetas, modales) seguía en Tailwind claro. Activado en las 9 páginas restantes.
- **Regresión de contraste corregida** (`descuentos.html`): `.descuento-card` y `.descuentos-info-card` tenían `background: white` **inline** (no clase Tailwind, el skin no las tocaba) mientras el skin sí oscureció el texto a blanco → texto blanco sobre fondo blanco. Corregido a superficies glass oscuras.
- **Logo tile del directorio de socios** (`empresas-convenio.html`): usaba `bg-gradient-to-br from-blue-50 to-indigo-100` (claro, pensado para logos con fondo blanco) — desentonaba con el resto de la tarjeta oscura. Homologado a glass oscuro vía regla `.claut-skin .from-blue-50.to-indigo-100`.
- **`mensajes.html`**: módulo standalone sin ninguna clase Tailwind (CSS propio compacto) — completamente blanco, el skin no podía tocarlo. Reescrito su `<style>` inline al lenguaje oscuro del sistema.
- **Animación de entrada del nav rota** (`eventos.html`, `comites.html`): GSAP usaba `rotationY: -180` (flip 3D) para la entrada de `.claut-header-nav .claut-nav-item` — el fotograma intermedio de la rotación hace ver el texto/iconos espejados. Reemplazado por un fade+translateY de 300ms con `power2.out` (guía emil-design-eng: nav visto en cada navegación no debe llevar animación vistosa; nunca usar `ease-in`; UI animations <300ms). Icono del bottom-nav: `scale:0→1` reemplazado por `scale:0.9→1` (nunca animar desde `scale(0)`).
- **Distribución rota en dashboard.html**: el carrusel de banners (`.glass-card.shadow-2xl`, `h-[700px]`) con un solo banner activo dejaba un vacío enorme antes de "Agenda" — la tarjeta centrada de 300-600px de alto flotaba dentro de un contenedor de 700-750px. Fix con `:has()`: cuando solo hay una tarjeta (`.expanding-card:only-child`), la altura se ajusta al contenido.
- **Botón de usuario duplicado y código muerto** (`profile.html`): existían DOS botones de avatar/dropdown superpuestos en el header — uno funcional (`userMenuBtn`/`userDropdown`, con logout) y otro sin ningún `onclick` ni listener (`userMenuToggle`/`userMenuContent`, dead code). El muerto se eliminaba visualmente sobre el vivo en anchos angostos. Eliminado el bloque muerto.
- **Overlap responsive en profile.html**: la barra "Cuenta / Perfil — Bienvenido, Fernando" y el bloque de acciones (nombre/rol, Admin Panel, menú de usuario) no hacían wrap por debajo de 768px, colisionando. Fix con `flex-wrap: wrap` acotado a `nav[navbar-main]` bajo `max-width: 768px`. Verificado en 564px y 592px — limpio.
- **Verificado sin regresión**: `calendario.html` (referencia original, sin cambios visuales), `dashboard.html`, `gestionar_usuarios.php`, `admin-panel.html`, `boletines.html`, `comites.html`, `contacto.html` — todos coherentes en desktop y en los anchos angostos alcanzables por la herramienta de automatización (~564-592px; no se pudo forzar un viewport de teléfono real <560px por límite del propio navegador/OS).
- **Pendiente conocido, no corregido (fuera de alcance de esta ronda)**: en `dashboard.html` existen dos botones circulares flotantes (buscar/notificaciones) que ya se veían visualmente desconectados del header antes de esta ronda — no es una regresión de este trabajo, queda como ítem de pulido futuro.

## Fase 5 del rollout (2026-08-31, noche — corrección de bugs reportados por el usuario)

### Header: búsqueda y notificaciones flotantes reintegradas
- `js/global-search.js` y `js/notification-bell.js` inyectaban un botón circular con `position:fixed` (esquina superior derecha, colores de tema claro) totalmente desconectado del `<header>` — se veían como círculos oscuros casi invisibles.
- Fix: `dashboard.html` ahora tiene `<div id="global-search-mount">` y `<div id="notification-bell-mount">` dentro de `.claut-header-actions`; ambos scripts detectan el mount point y, si existe, inyectan el botón ahí con la clase `.claut-header-btn` (mismo estilo que mensajes/perfil/menú). Si no hay mount point (otras páginas), caen al fallback flotante, ahora también con colores oscuros.
- Paneles/modales de ambos widgets (dropdown de notificaciones, modal de búsqueda) recoloreados a dark glass (`#101013`, bordes `rgba(255,255,255,.14)`) para consistencia total.

### descuentos.html: marquesina infinita desactivada (causaba el "no centrado")
- Causa real: `js/descuentos-frontend.js` tenía una clase `DiscountStreamController` — marquesina auto-scroll infinita (clona tarjetas ×3, anima `transform:translateX` sin parar) con ancho de tarjeta **hardcodeado a 350px**, en conflicto directo con la regla responsive ya existente (`clamp(260px,80vw,350px)`). Resultado: tarjetas duplicadas, scroll en posición aleatoria, roto en móvil.
- Decisión de producto: se desactivó la marquesina (código de descuento en movimiento continuo es un anti-patrón de legibilidad — el usuario necesita leer/copiar el código, no perseguirlo). Se dejó como fila estática: centrada cuando cabe, scroll horizontal nativo cuando no.

### comites.html: banner con texto cortado
- Las imágenes de comité son gráficos con texto ya incrustado (p. ej. "OBSERVATORIO LABORAL"), renderizados con `object-cover` en un contenedor de altura fija (`h-56`) — cualquier imagen con proporción distinta a la del contenedor se recortaba, cortando el texto.
- Fix universal (no depende de las dimensiones de cada imagen subida): `object-contain` + fondo glass de relleno (`linear-gradient(135deg,#16161a,#1e1e24)`), y se quitó el overlay rojo diagonal que además tapaba parte de la imagen.

### Bug propio: colisión de nombre de clase `.main-content`
- **Causa de "el contenido aparece debajo, hay que hacer scroll"** en `demo_documentos.html`, `demo_descuentos.html`, `demo_comite.html`, `demo_empresas.html` (y potencialmente cualquier página que cargue `claut-ui.css`): la Fase 6 del rollout agregó `.main-content { min-height:100vh; display:flex }` en `claut-ui.css` como componente genérico para acompañar `.side-nav`. Pero `.main-content` ya era una clase utilitaria usada en ~15 páginas legacy (sin relación con sidebars) para "sin margen izquierdo, ancho completo". Como las páginas legacy solo sobreescribían `margin-left`/`width`, mi `min-height:100vh` se colaba sin oposición sobre el `<header>` o el primer contenedor con esa clase, empujando todo el contenido real fuera de la pantalla.
- Fix: renombrada a `.claut-content-area` (namespaced, sin colisión). `.side-nav` nunca se usó en ningún HTML — sin impacto.
- **Lección**: antes de introducir una clase genérica al sistema de diseño, verificar que el nombre no esté ya en uso en el codebase legacy (`grep -rl 'class="...*NOMBRE'`).

### admin/banner-admin-mejorado.php: nunca tuvo el sistema de diseño
- No enlazaba `claut-ui.css` en absoluto (huérfano del rollout, `body class="bg-gray-100"` sin activar). Corregido: link + `claut-dark claut-skin` + tokens porsche unificados (`#1a1a1a`→`#09090b`, `#c9302c`→`#C7252B`).

### demo_empresas.html: sidebar abierto por defecto en desktop
- Única página de la familia `demo_*` con `@media (min-width:1280px) { .porsche-sidebar { transform: translateX(0) } }` — el sidebar se abría solo en pantallas anchas, inconsistente con el resto (que siempre arrancan cerrados). Unificado: cerrado por defecto en todos los anchos, se abre solo con el botón hamburguesa (mismo comportamiento en todo el sitio). JS del toggle simplificado para no bifurcar mobile/desktop.

## Próximos pasos del rollout

1. Convertir `dashboard.html` al tema oscuro completo (es la vista más usada).
2. Absorber los `<style>` duplicados de las demo_* (sidebar Porsche repetido ×9) en `claut-ui.css`.
3. `profile.html` y `eventos.html` (vista de usuario) al tema oscuro.
4. Auditoría visual final con screenshots por página.
