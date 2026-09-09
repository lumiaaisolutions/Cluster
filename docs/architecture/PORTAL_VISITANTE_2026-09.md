# Portal Visitante (`visitante.html`) — arquitectura y rediseño v3 "cine + aurora glass"

**Estado: EN PRODUCCIÓN** (septiembre 2026). Bitácora resumida en `claude.md`
(FEATURE-032 original y FEATURE-036 r1–r6). Este documento es la referencia
técnica completa de la página y su API.

## Qué es

Página pública de conversión para el "modo visitante": una sesión PHP sin
usuario (creada por `api/visitante.php?action=entrar` desde el botón del
login) que permite explorar un teaser de la intranet — módulos bloqueados,
eventos abiertos al público, boletines publicados — con CTAs de registro,
correo y WhatsApp. Métricas de conversión sin PII.

## Principios de diseño (aprobados por el usuario a lo largo de r1–r6)

1. **Impacto en los primeros 3 segundos**: hero cinemático a pantalla
   completa (oscuro, radiografía del auto, título gigante con entrada
   coreografiada sin necesidad de scroll).
2. **"Aurora glass" claro** en el resto: fondo `#eef0f6` con 4 blobs pastel
   difuminados en parallax, tarjetas de vidrio translúcido.
3. **Sin límites de diseño**: nada de contenedores con borde visible — ni el
   header, ni las tarjetas, ni la banda de contacto. La definición la dan
   vidrio (translucidez + blur) y sombra, nunca un trazo.
4. **Sin saturación**: el color aparece como splash/glow difuminado de baja
   opacidad, nunca como relleno sólido. El rojo pleno queda para acentos
   pequeños (subrayados, glifos).
5. **Autocontenida**: no carga el stack admin ni Tailwind — un solo archivo
   con CSS/JS inline propios (los assets externos son Inter, Font Awesome y
   los frames de la secuencia de la landing).

## Anatomía de la página

### 1. Header flotante (`.hdr`)
Fijo, **sin contenedor** (sin fondo/borde/sombra). Sobre el cine, la
legibilidad la da la viñeta superior del escenario; sobre el contenido claro
(clase `.on-light`, toggled por scroll), un `::before` con degradado del
color de fondo que se desvanece. `pointer-events:none` en el contenedor y
`auto` en los hijos. El logo flota sobre una **pastilla horizontal 96×56 con
degradado blanco→transparente** (el gesto de marca del sidebar admin, en
horizontal) — `object-fit:contain` centrado por padding, sin markup extra.

### 2. Cine (`.cine` — scrollytelling, la firma de la página)
Región de 300vh con escenario `position:sticky` de 100svh:
- **Canvas** con la secuencia de rayos-X de la landing (1 de cada 4 frames,
  42 en total, `./landing/assets/sequence/ezgif-frame-NNN.jpg`), dibujado
  con `devicePixelRatio` (cap 2).
- **Scrub con inercia**: el frame objetivo sale del avance de scroll sobre
  la región; el frame pintado lo persigue con lerp (.16) en rAF. Saltos
  grandes (>0.5) hacen snap directo; `visibilitychange` reanuda el loop
  (Chrome pausa rAF con la pestaña oculta — 0 ticks medidos con la ventana
  ocluida; sin el listener, el lerp quedaba congelado a media transición).
- **3 escenas** con ventanas de opacidad (fade in/hold/out) + translateY
  direccional: S1 título+CTAs (0–.34, con entrada coreografiada al cargar
  vía animation-delay 80–540ms), S2 chips de valor (.36–.68), S3 CTA de
  registro + chips de región con los emblemas reales de la landing
  (.70–1). `pointer-events` solo en la escena viva (clase `.live`).
- Viñeta + grano SVG inline para el look cinematográfico; barra de progreso
  roja al pie; hint "Desplázate para ensamblar" que se desvanece al primer
  scroll.

### 3. Contenido claro (`.light-wrap`)
El degradado de handoff cine→claro termina en **transparente** al 18% para
que los blobs aurora fijos se vean a través (un fondo opaco los tapaba —
bug propio corregido en r3).
- **Marquee "Te recibe gente real"**: 4 fotos reales del equipo directivo
  (assets de contacto.html) a 84px, en b/n que gana color al hover, loop
  infinito CSS 32s (track duplicado por JS) con pausa al hover y máscara de
  degradado en los bordes.
- **Bento de módulos**: 5 tarjetas + CTA. Íconos como **glifo puro de color**
  (sin tile de fondo ni borde, 22px). Candado que hace crossfade a candado
  abierto verde al hover ("se desbloquea con cuenta"). Spotlight radial
  rojo que sigue al cursor (variables `--mx/--my`).
- **Tarjeta CTA** (`.cta-red`): lenguaje oscuro-con-glows desaturados
  (rojo .14 / violeta .08), NO rojo sólido.
- **Eventos / boletines**: cargados por fetch de `api/visitante.php`
  (`action=eventos` → `visible_visitantes=1` futuros; `action=boletines` →
  publicados). Entrada escalonada propia (`--ci` por índice — llegan después
  del IntersectionObserver de reveals). Estados vacíos como invitación
  (ícono flotante + CTA de registro), no como disculpa.
- **Palabras editoriales de fondo** (`.wm`: CLÚSTER/SOCIOS/EVENTOS/NOTICIAS):
  outline gigante (`-webkit-text-stroke`) con parallax propio por factor
  (`data-wmf`) relativo al centro del viewport.
- **Encabezados de sección**: título + subrayado degradado rojo→violeta que
  se dibuja al entrar al viewport (sin kickers pill — eliminados en r6 por
  pedido expreso).

### 4. Banda de contacto (gran final) + FORMULARIO
Vidrio oscuro translúcido `rgba(19,20,27,.58)` + `blur(26px)` — las auroras
se transparentan a través; sin borde. Contiene el **formulario de contacto**
(ver API abajo) + fila de botones alternos (correo / WhatsApp / registro).

### 5. Footer
Logo del Clúster a **96px flotando sobre un splash difuminado**
(rojo/violeta/azul, `blur(26px)`, sin contenedor — se funde con el fondo;
hover lo intensifica), línea de © y **badge "Desarrollado por LUMIA"**
(pastilla glass sin borde, gradiente violeta→azul en el nombre).

## Sistema de interacción compartido

- **Un solo listener delegado** de `pointermove` (solo `pointer: fine`) fija
  `--mx/--my` en `.bcard`, `.pcard` y `.btn` — alimenta tanto el spotlight
  de tarjetas como el **splash de color de los botones**.
- **Botones v3**: vidrio translúcido + borde a media opacidad + splash
  radial del color semántico que florece desde el cursor + sheen sutil +
  flecha que se desliza + `scale(.955)` al presionar + `:focus-visible`.
  Variantes de contraste por contexto: `.btn-red` sobre oscuro (texto
  blanco), sobre claro (`.light-wrap`/`.hdr.on-light` — texto rojo profundo)
  y de vuelta clara-sobre-oscuro dentro de las bandas oscuras anidadas.
- **Reveals** `.rv` (fade+rise+blur) por IntersectionObserver con stagger
  `--rvd`.
- **`prefers-reduced-motion`**: apaga parallax (auroras y watermarks),
  reveals, marquee (pasa a scroll nativo), tilt, entrada coreografiada,
  animaciones de hint/íconos y el cine colapsa a una pantalla estática con
  solo la escena 1 (S2/S3 ocultas — su contenido se repite en el bento y el
  CTA). El scrub queda sin lerp (es contenido, no decoración).

## API: `api/contacto-visitante.php` (formulario de contacto)

- **POST** `nombre, correo, mensaje, empresa_web(honeypot)` → JSON.
- Envía por `EmailService::sendNotification()` (mismo SMTP/plantilla del
  sitio) a **`atencion@clautedomex.mx`**. **No persiste nada en BD** — el
  mensaje viaja solo por correo (mismo criterio de privacidad que
  `visitante_metricas`).
- **Anti-abuso sin CAPTCHA**: honeypot (si el campo invisible trae contenido
  → éxito silencioso sin enviar, para no dar señal al bot), rate limit por
  sesión (3 envíos/10 min en `$_SESSION['contacto_envios']`), y requiere la
  sesión de visitante/usuario (SessionConfig::init — cookie CLAUT_SESSION,
  regla BUG-035; nunca `session_start()` a secas).
- Frontend: submit con spinner y botón deshabilitado, estado ok/error en
  `role=status aria-live`, métrica `contacto_form` por sendBeacon al éxito.
- **Verificado end-to-end en producción**: entrar→check→POST → correo real
  enviado; honeypot → éxito silencioso; correo inválido → 400 con mensaje.

## Métricas de conversión

`data-metrica` en cada CTA → sendBeacon a `api/visitante.php?action=metrica`
(`click_registro`, `click_correo`, `click_whatsapp`, `click_evento`,
`click_boletin`, `contacto_form`). Solo tipo+fecha — sin IP, sin UA, sin PII.
El admin las ve en `demo_visitante.html` (franja 7 días, `action=stats`).

## Guard de sesión

`action=check` al cargar; si falla, un único reintento vía `action=entrar`
(flag `visitante_retry` en sessionStorage para no ciclar) antes de expulsar
al login. Si la API no responde, la vista informativa NO se bloquea.

## Metodología de verificación (sin BD local)

Servidor PHP local + copia "deauth" (guard recortado con perl — con BD
inexistente el guard entra en redirect-loop); datos de muestra inyectados
por consola para ver tarjetas; medición de estado real del DOM
(`getBoundingClientRect`, `classList`) ANTES de diagnosticar por captura —
con la ventana de Chrome ocluida las capturas traen tiles obsoletos del
compositor Y rAF se pausa por completo (dos falsos bugs cazados así).
Producción siempre confirmada por `curl` con UA de navegador (el WAF de
Hostinger responde 403 al fingerprint de curl pelón).

## Pendientes conocidos

- **Número real de WhatsApp**: los 3 botones usan el placeholder
  `wa.me/5255000000` heredado de la landing.
- **Eventos reales**: la sección de eventos muestra el estado vacío hasta
  que un admin marque al menos un evento con "Mostrar en el portal de
  visitantes" (`visible_visitantes=1`, checkbox en demo_evento).
- Las tarjetas de evento con **portada de imagen real** no se han visto en
  producción (no hay eventos visibles aún) — el CSS las cubre, pero merece
  un vistazo cuando exista la primera.
