# Plan: Quitar `unsafe-inline` / `unsafe-eval` de la CSP

## Estado actual (2026-09-02)

`build/.htaccess` envía una Content-Security-Policy global desde FIX-026 (agosto 2026), pero `script-src` y `style-src` siguen incluyendo `'unsafe-inline'` y `'unsafe-eval'`. Esto anula buena parte de la protección de la CSP contra XSS: si un atacante logra inyectar HTML/JS en una página (por ejemplo vía un campo de formulario mal sanitizado), el navegador lo ejecutaría igual, porque la política permite explícitamente scripts y estilos inline.

Este documento reemplaza la nota de "aceptado como limitación" — no es un límite técnico permanente, es deuda técnica con una ruta de salida concreta. Lo que sigue es el plan para llegar a `script-src 'self' <nonces/hashes>` sin `unsafe-inline`/`unsafe-eval`.

## Por qué no se puede quitar en un solo cambio

Inventario real del código (`build/`, medido con `grep -r`):

| Dependencia | Alcance |
|---|---|
| Tailwind CDN (`cdn.tailwindcss.com`) | 22 páginas — su compilador JIT corre en el navegador y requiere `unsafe-eval` |
| Atributos `onclick=""` inline | 30 archivos, 289 ocurrencias — cada uno requiere `unsafe-inline` (los nonces de CSP no cubren atributos de evento, solo `<script>` y `<style>`) |
| Bloques `<script>` inline (sin `src`) | ~96 bloques repartidos en el sitio |

Quitar `unsafe-eval` sin resolver Tailwind CDN rompe el layout de 22 páginas al instante. Quitar `unsafe-inline` sin resolver los 289 `onclick=""` rompe toda interacción de esos 30 archivos (botones, modales, formularios). Ninguno de los dos es un cambio seguro de hacer "de pasada" — necesita su propia suite de pruebas manuales página por página.

## Fases propuestas

### Fase A — Reemplazar Tailwind CDN por CSS compilado (elimina la necesidad de `unsafe-eval`)
1. Instalar Tailwind CLI localmente, generar `tailwind.config.js` con el contenido real escaneado de `build/**/*.html`.
2. Compilar un único `dist/tailwind.css` de producción (purgado, sin JIT en navegador).
3. Reemplazar `<script src="https://cdn.tailwindcss.com">` por `<link rel="stylesheet" href="./dist/tailwind.css">` en las 22 páginas.
4. Verificar visualmente cada página (el purge de clases no usadas puede omitir clases generadas dinámicamente por JS — hay que revisar `classList.add(...)` en los `.js` del sitio y añadirlas al `safelist` del config).
5. Quitar `https://cdn.tailwindcss.com` de `script-src` en la CSP.

**Esto ya permite quitar `unsafe-eval`.** Es la fase de mayor impacto por menor esfuervo relativo — un solo build, no 289 ediciones manuales.

### Fase B — Migrar `onclick=""` a `addEventListener` (elimina la necesidad de `unsafe-inline` en scripts de evento)
1. Por archivo (empezar por los de mayor tráfico: `dashboard.html`, `demo_empresas.html`, `profile.html`), reemplazar `<button onclick="fn()">` por `<button data-action="fn">` + un listener delegado único por página (`document.addEventListener('click', e => { if (e.target.dataset.action) ... })`).
2. Alternativa más rápida por archivo pero menos limpia: mover cada `onclick=""` a un `addEventListener` explícito añadido en el `<script>` de esa misma página, referenciando el elemento por `id`.
3. Repetir para los 30 archivos. Es mecánico pero no automatizable a ciegas — cada `onclick` hay que verificar que el elemento tenga un `id` único antes de poder engancharlo por selector.

### Fase C — Mover `<script>` inline a nonces
1. Para los bloques de `<script>` inline que NO se puedan externalizar a un `.js` propio (código que depende de variables PHP renderizadas server-side), generar un nonce único por request en PHP y añadirlo tanto al header CSP (`script-src 'nonce-xxx'`) como al atributo `<script nonce="xxx">`.
2. Para los que sí sean JS puro sin variables PHP, simplemente moverlos a archivos `.js` externos versionados (mismo patrón `?v=` ya establecido en el proyecto).

### Fase D — Endurecer y verificar
1. Quitar `unsafe-inline`/`unsafe-eval` de la CSP.
2. Activar `Content-Security-Policy-Report-Only` una semana antes del cambio final, con un endpoint de reporte simple (`api/csp-report.php` que solo loguea a un archivo) para detectar violaciones sin bloquear nada, y revisar el log antes de aplicar la política real.
3. Deploy y verificación manual de las páginas más usadas (dashboard, profile, demo_empresas, gestionar_usuarios).

## Qué se hizo ya, sin esperar al plan completo

- `upgrade-insecure-requests` añadido a la CSP (2026-09-02) — fuerza HTTPS en cualquier subrecurso que por error apunte a `http://`, cambio de riesgo cero.
- `object-src 'none'`, `frame-ancestors 'self'`, `base-uri 'self'`, `form-action 'self'` ya estaban activos desde FIX-026.
- **Fase D-2 (2026-09-03)**: `Content-Security-Policy-Report-Only` desplegada en paralelo a la CSP real, con la política endurecida final (sin `unsafe-inline`/`unsafe-eval`) + `report-uri /api/csp-report.php`. No bloquea nada — la CSP que sí aplica sigue con `unsafe-inline`/`unsafe-eval` intactos. Cada violación que un navegador real encuentre (botón con `onclick`, script inline, uso de `eval` por el JIT de Tailwind) se registra en `build/logs/csp-violations.log` (protegido con `Require all denied`, no accesible por HTTP). Endpoint público sin autenticación por diseño — así es como el navegador debe poder reportar sin depender de sesión — pero no persiste PII, solo URL de documento, URI bloqueada y directiva violada.
- **Por qué no se ejecutaron las Fases A-C esta sesión**: se pidió explícitamente completarlas, pero requieren verificación visual página por página (22 páginas para el build de Tailwind, 30 archivos con 289 `onclick=""` para los event listeners) y esta sesión no tuvo acceso a una sesión de navegador ya autenticada como admin — sin eso, migrar 289 bindings de interacción a ciegas en un sistema en producción que gente usa a diario es exactamente el tipo de cambio "se ve bien en el código, rompe un botón en silencio" que este mismo documento advierte evitar. El modo Report-Only es el primer paso real y verificable que sí se pudo ejecutar sin ese riesgo — ahora hay datos reales de violación acumulándose para decidir el orden de la Fase B con evidencia en vez de conjetura.
- **Próximo paso concreto**: revisar `build/logs/csp-violations.log` después de una semana de tráfico real (vía FTP/SSH, o pedir a Hostinger acceso a logs) para confirmar qué directivas se violan más — eso prioriza qué archivos migrar primero en la Fase B en vez de adivinar por conteo de `onclick`.

## Estimación

Fase A: 1 sesión completa (build + verificación visual de 22 páginas).
Fase B: la más larga — probablemente 2-3 sesiones repartiendo los 30 archivos.
Fase C: 1 sesión (los bloques inline con PHP son pocos una vez hecho el barrido de FIX-023/FEATURE-024).
Fase D: medio día, mayormente esperar y revisar el log de Report-Only.

No se ejecuta ahora porque tocar 30+ archivos de interacción de usuario sin poder probar cada flujo manualmente en el navegador es exactamente el tipo de cambio que "se ve bien en el código" y rompe silenciosamente un botón en producción.

## ✅ FASE A EJECUTADA (2026-09-09) — Tailwind compilado + `unsafe-eval` ELIMINADO de producción

- **Build**: `tailwindcss@3.4.17` vía npx — `build/tailwind.config.js` (fusión de
  los 5 configs inline que había: la divergencia `'clúster-red': 'black'` de
  contacto.html era config muerta, la página no usa esa clase) +
  `build/src/tailwind.in.css` → `build/dist/tailwind.css` (91.8 KB minificado).
  Comando de rebuild documentado en el propio config.
- **Safelist obligatorio**: las clases con nombre unicode (`bg-clúster-red`,
  etc.) NO son confiables para el extractor de contenido de Tailwind — van por
  patrón en el safelist y salen escapadas (`.bg-cl\FAster-red`), lo cual
  matchea correctamente `class="bg-clúster-red"` en el HTML.
- **Reemplazo**: 21 archivos (los 20 del inventario + evento_detalle.php)
  pasaron de `<script src="https://cdn.tailwindcss.com">` a
  `<link href="./dist/tailwind.css?v=20260909a">` (../dist/ desde pages/ y
  admin/). Los 5 bloques `<script>tailwind.config = {...}</script>` inline se
  eliminaron — sin el CDN, el global `tailwind` no existe y lanzarían
  ReferenceError.
- **CSP**: `script-src` de `.htaccess` perdió `'unsafe-eval'` y
  `https://cdn.tailwindcss.com`. Verificado: cero `eval(` en el JS propio del
  sitio (grep completo) — el JIT del CDN era el único consumidor.
- **Verificación**: visual local en Chrome (demo_evento, boletines, sign-in,
  gestionar_usuarios — pixel-idéntico; utilidades medidas por computed style
  incluyendo valores arbitrarios `text-[10px]` y variantes); producción por
  curl (0 referencias al CDN, 91.8 KB servidos, header CSP sin eval) y carga
  real de sign-in.html en Chrome contra producción con CERO violaciones de
  CSP en consola.
- **Orden de deploy** (importante si se repite): assets y páginas PRIMERO,
  `.htaccess` AL FINAL — la CSP estricta solo llega cuando ya nada necesita eval.
- **Bonus**: las 21 páginas ya no cargan el compilador JIT (~350 KB de JS +
  compilación en runtime) — reemplazado por 91.8 KB de CSS cacheable.

### Estado restante
- **Fase B** (`onclick` → addEventListener, 289 en 30 archivos) y **Fase C**
  (scripts inline a externos/nonces): pendientes — son el requisito para
  quitar `'unsafe-inline'`, la única excepción que queda en script-src.
- **Fase D**: al terminar B y C — reactivar Report-Only BREVEMENTE (con la
  lección de FIX-038: nunca dejarlo activo con violaciones universales) y
  hacer el switch.
