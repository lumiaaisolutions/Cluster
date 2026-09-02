# 🏎️ Rediseño Landing Page — Fase A (2026-08-31)

> Referencia de estilo: landonorris.com (overlay de menú full-screen, doble columna de imágenes)
> Skills usadas: frontend-design (dirección visual), emil-design-eng (motion), ui-ux-pro-max (verificación)

## Qué se construyó

### 1. Menú overlay full-screen (reemplaza el nav horizontal)
- **Antes**: lista horizontal de 6 links en el header (`.nav-links`), sin alternativa en pantallas angostas — no había hamburguesa, los links simplemente desaparecían por debajo de 980px (bug pre-existente).
- **Ahora**: un solo botón "MENÚ" (círculo rojo + 3 líneas que hacen morph a X) abre un overlay full-screen:
  - **Columna izquierda** (solo desktop, ≥880px): dos columnas de fotogramas del sequence de rayos-X del auto (mismo asset ya usado en el hero — sin descargar imágenes externas sin licencia clara para un sitio en producción), 9 fotogramas únicos por columna, en blanco y negro por defecto, **revelan su color real al pasar el mouse** sobre cada imagen individualmente. Una columna sube, la otra baja (`@keyframes menuScrollUp/Down`, 85s/76s — deliberadamente lento).
  - **Columna derecha**: navegación en tipografía grande apilada (Inicio, Nosotros, Sectores, Beneficios, Eventos, Contacto — se corrigió el link "Casos" que apuntaba a un `#casos` inexistente), con stagger de entrada.
  - **Animación de apertura/cierre**: `clip-path: circle()` que se expande desde la posición del botón hamburguesa (600ms, `--e-in-out`), no un fade genérico. Cierre instantáneo del mismo origen. Respeta `prefers-reduced-motion` (fallback a opacity fade).
  - Accesibilidad: `role="dialog"`, `aria-modal`, trampa de foco (Tab/Shift+Tab), cierre con Escape, foco vuelve al botón que abrió el menú.

### 2. Header: fondo blanco sólido + logo con tamaño real
- **Problema real** (reportado por el usuario, confirmado en captura): el logo casi no se veía — el header tenía solo un tinte translúcido de 6-14% blanco (`rgba(255,255,255,.06-.14)`, heredado de la regla "liquid glass nav" en `responsive.css`), imperceptible contra el hero de rayos-X.
- **Iteración 1 (descartada)**: chip oscuro detrás del logo + logo monocromático blanco vía `filter`. El usuario aclaró que no quería tocar el logo, solo el fondo.
- **Fix final**: `.nav { background: rgba(255,255,255,0.96) !important }` — blanco sólido siempre (no solo al hacer scroll), con blur de respaldo. Logo con sus colores originales, solo **60px** (antes 44-56px según el archivo). Texto e íconos del header (`.lang-btn`, `.btn-ghost`, `.menu-btn`) recoloreados a oscuro para leerse sobre blanco.
- Cargado en `assets/css/menu-overlay.css`, el **último** `<link>` del `<head>` a propósito — gana la cascada sobre las ~4 definiciones dispersas de `.nav`/`.brand-logo` en `sections.css`, `legibility.css`, `responsive.css` (deuda pre-existente, no resuelta aquí — solo neutralizada).

### 3. CTA buttons más llamativos
- `.btn-red` pasó de color plano a `var(--grad-red)` (gradiente ya definido en tokens.css pero sin usar), con animación de posición de gradiente en hover, sombra más profunda (`0 22px 54px` en hover vs. `0 12px 32px` antes) y lift de 2px.

### 4. Más efectos de scroll / parallax
- Reutilizada la infraestructura de parallax ya existente en `scrollfx.js` (`parallaxNodes`), extendida a `.states-stack` (mapa) y `.cta-pillars` (CTA final) — no se construyó un sistema nuevo.

### 5. Contenido resumido
- Recortado el lead de "Quiénes somos" y sus 4 pilares, el lead de "Universo Clúster Intranet" y sus 6 tarjetas de beneficio — de párrafos de 20-30 palabras a frases de 6-12 palabras. Sincronizado en `i18n.js` (ES y EN) para que el toggle de idioma no restaure el texto largo original.

## Bugs encontrados y corregidos en el camino

- **Link "Casos" roto**: apuntaba a `#casos`, sección inexistente. Retirado del nuevo menú (los 6 destinos del overlay son todos anchors reales).
- **Caché del navegador**: los 4 scripts de la landing (`i18n.js`, `sequence.js`, `ui.js`, `scrollfx.js`) y el nuevo CSS nunca tuvieron query de versión — el botón de menú "no hacía nada" para el usuario porque su navegador servía el `ui.js` viejo (sin el listener nuevo) desde caché. Agregado `?v=20260831i` a los 5 archivos. Mismo patrón de bug ya documentado varias veces hoy en `DESIGN_SYSTEM.md` — **regla para el futuro**: todo asset estático de la landing debe llevar query de versión desde el día uno.

## Verificado

- Overlay abre/cierra (clic real y programático) tras el fix de caché.
- Header blanco + logo legible en desktop (1568px) y móvil (360-400px).
- Botones ghost/red/hamburguesa legibles sobre el fondo blanco.
- Responsive: `.menu-overlay-visual` (columnas de imágenes) se oculta por debajo de 880px, solo queda el listado de navegación — sin overflow horizontal.

## Pendiente / fuera de alcance de esta fase

- Las ~4 definiciones dispersas de `.nav`/`.brand-logo` en distintos CSS de la landing no se consolidaron — se neutralizaron cargando `menu-overlay.css` al final. Consolidarlas de raíz es trabajo de limpieza futuro, no bloqueante.
- Verificación de accesibilidad con lector de pantalla real no realizada (solo estructura ARIA correcta).
