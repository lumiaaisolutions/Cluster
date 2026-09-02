# 🎯 PROMPT FINAL — Rediseño Clúster Intranet (v2.0 — Decisiones Aplicadas)

> **Cómo usar este archivo**: Pégalo COMPLETO en Claude/Cursor/ChatGPT junto con los archivos `SISTEMA_ANALISIS.md`, `STACK_HOSTINGER.md`, `MIGRACIONES_NUEVAS.sql` y la carpeta `Inter-4/`. Es autosuficiente.
>
> **Versión**: 2.0 — Todas las propuestas de `FRICCIONES.md` están **APLICADAS como decisiones firmes**. El sistema sigue siendo 100% configurable desde el panel superadmin (los valores quedan editables en runtime via `theme_config`, `catalogos`, `i18n`).

---

## 🧑‍💻 Rol del LLM

Actúa como un **Senior UI/UX Engineer + Creative Developer + PHP/MySQL Backend Engineer** con 10+ años entregando productos premium (Apple, Porsche, Stripe, Linear). Stack: HTML5 semántico, CSS3 (Tailwind compilado local), JavaScript ES2022 vanilla, GSAP 3 + ScrollTrigger, Motion One, Lenis, Alpine.js, Swup, PHP 8.x, MySQL/MariaDB. Hosting de destino: **Hostinger Shared** (sin Node.js runtime, sin Composer, sin servicios externos de pago, sin claves API de terceros).

Tu trabajo es **rediseñar el frontend del sistema "Clúster Intranet"** preservando 100% la base de datos y las APIs PHP existentes, con estética **Porsche-Apple híbrida**: hero cinemático con image sequence scrubbing, microinteracciones líquidas, scroll storytelling vectorial, page transitions SPA-like, light mode con inversión a oscuro al scroll en hero, dark mode opcional toggable.

---

## ⚠️ Por qué NO Next.js / NO React (precedente real)

El usuario reportó este error de un build Vercel reciente en otro proyecto suyo:

```
npm error ERESOLVE could not resolve
npm error While resolving: @sentry/nextjs@8.55.1
npm error Found: next@16.2.4
npm error peer next@"^13.2.0 || ^14.0 || ^15.0.0-rc.0" from @sentry/nextjs@8.55.1
npm error Conflicting peer dependency
```

Esto confirma la decisión: dependency hell de npm/Next.js es incompatible con un sistema que debe ser entregable copy-paste-FTP por usuarios no-técnicos. Vamos con **HTML+CSS+JS+PHP nativo** y librerías por CDN público con versión pinneada — sin npm install en producción, sin builds que rompan por peerDependencies, sin Vercel.

---

## 📜 Reglas inviolables (no negociables)

1. **NO Next.js, NO React, NO Node en runtime, NO Vercel, NO Composer.** Todo HTML+CSS+JS+PHP nativo subible por FTP a `public_html`.
2. **NO alertas nativas** (`alert()`, `confirm()`, `prompt()`). Toda comunicación con **Notyf** o modales custom.
3. **NO datos hardcoded**. Todo configurable desde tablas `theme_config`, `catalogos`, `email_notification_config` y los JSON `i18n/{es,en}.json` (que también pueden editarse desde panel superadmin).
4. **Preservar 100% la BD existente** (21 tablas). Solo aplicar las **migraciones aditivas** del archivo `MIGRACIONES_NUEVAS.sql` (todas idempotentes con `IF NOT EXISTS`).
5. **Preservar 100% los 51 endpoints `/api/*.php`** y middleware (CORS, CSRF, JWT, rate-limiter, security-headers).
6. **Preservar la configuración SMTP actual** (`MAIL_HOST`, `MAIL_USER`, `MAIL_PASS` ya en `.env`). NO tocar `EmailService.php` ni el `.env`. El nuevo cron de recordatorios usa el mismo `EmailService` ya cargado.
7. **Hostname canónico**: `clautmetropolitano.mx` (sin tilde, BUG-011).
8. **Fetch SIEMPRE** con `credentials: 'include'` (BUG-012) y header `X-CSRF-TOKEN`.
9. **FormData → POST** en PHP (BUG-014). Nunca PUT con multipart.
10. **No `ApiValidator`** en endpoints con `$_FILES` (BUG-015).
11. **isSubmitting flag** en todo botón que dispare endpoint pesado (BUG-019). `fastcgi_finish_request()` para emails async.
12. **Cache busting** con `?t=Date.now()` en GETs frecuentes (BUG-020).

---

## ✅ Decisiones de diseño APLICADAS (fricciones cerradas)

Estas son decisiones firmes que el LLM debe implementar tal cual. El sistema queda configurable en runtime, pero el comportamiento **por defecto** es el siguiente:

### D1 — Light/Dark + Inversión scroll
- **Light mode (default)**: hero blanco `#FFFFFF` que invierte a `#0A0A0B` interpolando con scroll. Apple/Porsche feel.
- **Dark mode**: hero arranca en `#0A0A0B`, scroll degrada a gris carbón `#1D1D1F` con acentos rojos. Sin inversión completa, solo profundidad.
- Toggle persistido en `localStorage.theme` con fallback a `prefers-color-scheme`.
- Configurable en `theme_config.color_invert_on_scroll` (bool).

### D2 — Tono de copy
- **Headers/landing/legal**: corporativo formal.
- **Toasts/empty states/microcopy interno**: profesional cálido.
- Implementar como dos archivos i18n con namespaces: `i18n/es.json` con `formal.*` y `casual.*` keys.

### D3 — Densidad landing vs dashboard
- **Mismo lenguaje visual** (paleta, tipografía, animaciones, transiciones).
- **Densidad ajustada por contexto**: landing aire generoso (max-w-7xl + padding alto), dashboard compacto (max-w-full + padding 16px).
- Mismo CSS, distinta clase `data-density="hero|app"` en `<main>` que controla spacing.

### D4 — Bilingüe (Opción A: solo UI)
- Solo se traducen labels/botones/toasts/menús/emails. Contenido dinámico (eventos, boletines, etc) queda en idioma del admin que lo creó.
- JSON keys en `i18n/es.json` y `i18n/en.json`. Editables desde panel superadmin (CRUD sobre archivos JSON via endpoint `/api/i18n.php`).
- Toggle en footer + header. Persiste en `localStorage.lang`.

### D5 — Image sequence híbrido
- **Hero del landing**: image sequence WebP de 60 frames a 1080p (~2-4MB total). Configurable en `theme_config.hero_sequence_enabled`.
- **Heroes de submódulo**: SVG morphing animado con GSAP (50-200KB cada uno, animados con `MorphSVGPlugin` reemplazado por interpolación manual de `d` paths).
- **Detección automática de conexión** (`navigator.connection.effectiveType`): si `2g`, `3g`, o `saveData=true` → fallback a imagen estática + animación CSS sutil.
- **Lottie genérico** como placeholder mientras el usuario sube su sequence final.

### D6 — Microinteracciones cards
- **VanillaTilt**: `max: 6, scale: 1.02, perspective: 1500, speed: 600, glare: false`.
- **Lift** sutil: `translateY(-4px)` con sombra `0 12px 24px -8px rgba(0,0,0,0.12)`.
- **Magnetic** solo en CTA primarios (no en cards de listado para evitar saturación).

### D7 — Visibilidad granular del directorio
- Casilla maestra `autoriza_directorio` (estado actual) **+** acordeón "Personalizar visibilidad de campos" que escribe en `empresa_visibilidad_campo`.
- Default al activar maestra: todos los campos visibles. El acordeón existe para quien quiera privacidad fina.
- 13 campos toggleables: `nombre, sector, entidad_federativa, municipio, descripcion, certificaciones, exporta, redes_fb, redes_x, redes_linkedin, redes_instagram, logo, contacto_persona, sitio_web`.

### D8 — Auto-registro empresas
- Form público en `/registro-empresa.html` con **math captcha** local (suma 2 números aleatorios) + rate-limiter ya existente (3/h por IP).
- Estado inicial: `pendiente`, `es_socio=0`. Solo admin aprueba.
- Email confirmación al solicitante con token de 1 lectura (tabla `email_tokens` ya existe).

### D9 — Validación de descuentos con QR
- Click "Quiero usar este descuento" → `POST /api/descuentos/usar.php` → genera `codigo_unico` UUID + INSERT en `descuentos_usos` con `usado=0`.
- Vista usuario muestra QR (vía `qrcode-generator` CDN) con URL `https://intranet.clautmetropolitano.mx/validar.html?c=<codigo_unico>`.
- Página `validar.html` (sin login) llama `GET /api/descuentos/validar.php?c=<codigo>` → marca `usado=1`, `fecha_validacion=NOW()`. Comerciante escanea con cualquier lector QR del celular.
- Estados: VÁLIDO ✓ verde · YA USADO ⚠ amarillo · INVÁLIDO ✗ rojo.

### D10 — Recordatorios eventos (3 capas)
- **Email**: cron Hostinger `0 */1 * * * php /home/.../cron_recordatorios.php` busca eventos a 24h y 1h. Marca `recordatorio_enviado=1` por capa. Usa SMTP existente.
- **Push browser**: opcional. Onboarding después de 3 visitas, no popup intrusivo. VAPID keys en `theme_config.vapid_public_key` y `vapid_private_key` (generadas localmente UNA vez con `web-push generate-vapid-keys` y pegadas).
- **In-app**: notificación en `/api/notificaciones.php` con `tipo='evento'` + badge en bell icon.

### D11 — Comités completos
- Tabla nueva `comite_sesiones` (en `MIGRACIONES_NUEVAS.sql`).
- Campos `link_whatsapp` y `link_google_form` agregados a `comites`.
- En la vista detalle de comité: botón "Unirme al WhatsApp" (link directo `wa.me/...` o `chat.whatsapp.com/...`), botón "Registrarme via Google Forms" (abre link en nueva tab), timeline de sesiones próximas con countdown.

### D12 — Roles y superadmin
- Columna `superadmin TINYINT(1)` en `usuarios_perfil` (en migraciones).
- Validación: `if ($_SESSION['superadmin'] === 1)` en endpoints de configuración.
- Las APIs admin existentes siguen validando `rol === 'admin'`. Superadmin = admin con flag adicional.

---

## 🎨 Lenguaje visual

### Paleta (en CSS variables, leídas de `theme_config`)
```css
:root {
  --bg: #FFFFFF;            /* theme_config.brand_color_light */
  --text: #1D1D1F;
  --brand: #C7252B;          /* theme_config.brand_color_primary */
  --surface: #FAFAFA;
  --border: #E5E5E7;
  --muted: #6E6E73;
}
[data-theme="dark"] {
  --bg: #0A0A0B;             /* theme_config.brand_color_dark */
  --text: #F2F2F2;
  --surface: #1D1D1F;
  --border: #2C2C2E;
  --muted: #98989D;
}
```

### Tipografía: **Inter Variable self-hosted**
Usar el folder `Inter-4/web/` ya provisto por el usuario. Copiar a `public_html/assets/fonts/inter/` y en `src/main.css`:
```css
@font-face {
  font-family: InterVariable;
  font-style: normal;
  font-weight: 100 900;
  font-display: swap;
  src: url("/assets/fonts/inter/InterVariable.woff2") format("woff2");
}
@font-face {
  font-family: InterVariable;
  font-style: italic;
  font-weight: 100 900;
  font-display: swap;
  src: url("/assets/fonts/inter/InterVariable-Italic.woff2") format("woff2");
}
:root { font-family: "InterVariable", -apple-system, BlinkMacSystemFont, sans-serif; font-optical-sizing: auto; }
```

Tamaños:
- Hero h1: `clamp(2.5rem, 6vw, 5rem)` peso 600 letter-spacing `-0.025em`.
- Section h2: `clamp(2rem, 4vw, 3rem)` peso 600 letter-spacing `-0.02em`.
- Body: 16-17px peso 400 line-height 1.6.

### Curvas de animación
- Liquid ease: `cubic-bezier(0.22, 1, 0.36, 1)`.
- Snappy ease: `cubic-bezier(0.16, 1, 0.3, 1)` (entrada de page transitions).
- Page transitions: 600ms.
- Hover cards: 400ms.
- Scroll scrub: linear (lo dicta el scroll del usuario).

---

## 🗂️ Entregables (estructura de carpetas final)

```
public_html/
├── index.html                      ← Landing público
├── login.html · registro.html · registro-empresa.html · recuperar.html · validar.html
│
├── app/                            ← Vistas autenticadas (Swup transitions)
│   ├── dashboard.html · directorio.html · empresa-detalle.html
│   ├── eventos.html · evento-detalle.html · calendario.html
│   ├── comites.html · comite-detalle.html
│   ├── boletines.html · documentos.html · descuentos.html
│   ├── perfil.html · perfil-empresa.html
│   ├── buzon.html · notificaciones.html
│
├── admin/                          ← admin + superadmin
│   ├── empresas.html · solicitudes.html · eventos.html · comites.html
│   ├── boletines.html · documentos.html · descuentos.html
│   ├── usuarios.html · estadisticas.html · banners.html
│   └── configuracion.html          ← Solo superadmin: theme_config, catalogos, i18n, email_config, vapid keys
│
├── api/                            ← INTACTO + 8 endpoints nuevos
│   ├── (51 endpoints existentes)
│   ├── csrf-token.php · theme-config.php · catalogos.php · i18n.php
│   ├── empresa-visibilidad.php · comite-sesiones.php
│   ├── descuentos/usar.php · descuentos/validar.php
│   └── push/{subscribe,unsubscribe,send}.php
│
├── assets/
│   ├── css/build.css               ← Tailwind compilado local
│   ├── js/
│   │   ├── core/{api,auth,i18n,theme,toast,router,lenis-init}.js
│   │   ├── modules/{directorio,eventos,comites,boletines,descuentos,documentos,perfil-empresa,solicitudes,notificaciones}.js
│   │   ├── animations/{hero-sequence,scroll-reveal,color-invert,page-transitions,svg-morph}.js
│   │   └── components/{nav-desktop,nav-mobile-bottom,card-tilt,modal,data-table,filter-bar,qr-display,math-captcha}.js
│   ├── img/
│   │   ├── logo/{logo-light.svg,logo-dark.svg}
│   │   ├── sequences/hero/         ← frames del image scrubbing (60 WebP)
│   │   └── placeholders/
│   ├── fonts/inter/                ← copiado desde rediseno/Inter-4/web/
│   └── lottie/hero-placeholder.json ← fallback hero mientras no hay frames
│
├── i18n/
│   ├── es.json
│   └── en.json
│
├── cron_recordatorios.php          ← ejecutado cada hora por cron Hostinger
├── service-worker.js               ← push notifications + cache estática
├── manifest.json                   ← PWA básico
└── .htaccess                       ← rewrites + headers
```

---

## 🔧 Archivos clave a generar (código completo, no pseudocódigo)

### `assets/js/core/api.js`
```js
// Wrapper unificado de fetch
const META_CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const isProd = location.hostname.includes('clautmetropolitano');
export const API_BASE = isProd ? '/api' : 'http://localhost/Claut_BD/build/api';

export async function api(path, opts = {}) {
  const isFormData = opts.body instanceof FormData;
  const headers = {
    'X-CSRF-TOKEN': META_CSRF(),
    ...(!isFormData && { 'Content-Type': 'application/json' }),
    ...opts.headers,
  };
  const res = await fetch(`${API_BASE}/${path}`, {
    credentials: 'include',
    headers,
    ...opts,
  });
  if (res.status === 401) { location.href = '/login.html'; return; }
  if (res.status === 429) { import('./toast.js').then(m => m.toast.error('Demasiadas solicitudes. Intenta en unos segundos.')); }
  return res.json();
}
```

### `assets/js/core/auth.js`
```js
import { api } from './api.js';
let _user = null;

export async function getUser() {
  if (_user) return _user;
  const r = await api('auth/login-compatible.php?action=check');
  _user = r?.success ? r.data : null;
  return _user;
}

export async function requireRole(allowed = []) {
  const u = await getUser();
  if (!u) { location.href = '/login.html'; return false; }
  const role = (u.user_rol || '').toLowerCase();
  const isSuper = u.superadmin === 1 || u.superadmin === '1';
  if (allowed.includes('superadmin') && isSuper) return true;
  if (allowed.includes(role)) return true;
  location.href = '/app/dashboard.html';
  return false;
}

export async function logout() {
  await api('auth/logout.php', { method: 'POST' });
  _user = null;
  location.href = '/login.html';
}
```

### `assets/js/core/theme.js`
```js
import { api } from './api.js';

export async function applyTheme() {
  // Lee theme_config y aplica como CSS vars
  const r = await api('theme-config.php');
  if (r?.success) {
    const root = document.documentElement;
    Object.entries(r.data).forEach(([k, v]) => {
      if (k.startsWith('brand_color_')) root.style.setProperty(`--${k}`, v);
    });
  }
  // Light/Dark
  const saved = localStorage.getItem('theme');
  const prefer = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', saved || prefer);
}

export function toggleTheme() {
  const cur = document.documentElement.getAttribute('data-theme');
  const next = cur === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
}
```

### `assets/js/core/i18n.js`
```js
let _dict = {};
export async function loadLang(lang) {
  const saved = lang || localStorage.getItem('lang') || 'es';
  const res = await fetch(`/i18n/${saved}.json?t=${Date.now()}`);
  _dict = await res.json();
  localStorage.setItem('lang', saved);
  document.documentElement.lang = saved;
  applyTranslations();
}

function getKey(path) {
  return path.split('.').reduce((o, k) => o?.[k], _dict);
}

export function t(key) { return getKey(key) || key; }

function applyTranslations() {
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const v = getKey(el.dataset.i18n);
    if (v) el.textContent = v;
  });
  document.querySelectorAll('[data-i18n-attr]').forEach(el => {
    const [attr, key] = el.dataset.i18nAttr.split(':');
    const v = getKey(key);
    if (v) el.setAttribute(attr, v);
  });
}

export function switchLang(lang) {
  loadLang(lang);
}
```

### `assets/js/core/toast.js`
```js
import 'https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js';
const notyf = new Notyf({
  duration: 4000,
  position: { x: 'right', y: 'top' },
  types: [
    { type: 'success', background: '#16a34a', icon: false },
    { type: 'error',   background: '#C7252B', icon: false },
    { type: 'info',    background: '#1D1D1F', icon: false },
  ],
});
export const toast = {
  success: (msg) => notyf.success(msg),
  error:   (msg) => notyf.error(msg),
  info:    (msg) => notyf.open({ type: 'info', message: msg }),
};
```

### `assets/js/core/router.js`
```js
import Swup from 'https://cdn.jsdelivr.net/npm/swup@4.6.0/dist/Swup.min.js';
import SwupFadeTheme from 'https://cdn.jsdelivr.net/npm/@swup/fade-theme@2/dist/index.min.js';

export const swup = new Swup({
  containers: ['#main'],
  plugins: [new SwupFadeTheme({ duration: 300 })],
  cache: true,
});

swup.hooks.on('content:replace', () => {
  // Re-init módulos por vista
  const route = location.pathname;
  if (route.includes('/directorio'))    import('/assets/js/modules/directorio.js');
  if (route.includes('/eventos'))       import('/assets/js/modules/eventos.js');
  // ... etc
});
```

### `assets/js/animations/hero-sequence.js` (image sequence scrubbing)
```js
// Renderiza secuencia WebP en canvas con ScrollTrigger
import { gsap } from 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js';
import { ScrollTrigger } from 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js';
gsap.registerPlugin(ScrollTrigger);

export async function initHeroSequence(canvasId, basePath, frameCount = 60, ext = 'webp') {
  // Detectar conexión lenta -> fallback
  const conn = navigator.connection || {};
  if (conn.saveData || ['slow-2g','2g','3g'].includes(conn.effectiveType)) {
    document.getElementById(canvasId).parentElement.innerHTML =
      '<img src="/assets/img/sequences/hero/cover.webp" alt="" style="width:100%;height:100%;object-fit:cover;">';
    return;
  }

  const canvas = document.getElementById(canvasId);
  const ctx = canvas.getContext('2d');
  const dpr = window.devicePixelRatio || 1;
  function resize() {
    canvas.width = canvas.offsetWidth * dpr;
    canvas.height = canvas.offsetHeight * dpr;
  }
  resize();
  window.addEventListener('resize', resize);

  const images = [];
  let loaded = 0;
  for (let i = 1; i <= frameCount; i++) {
    const img = new Image();
    img.src = `${basePath}/${String(i).padStart(4, '0')}.${ext}`;
    img.onload = () => { if (++loaded === frameCount) render(0); };
    images.push(img);
  }

  const state = { frame: 0 };

  function render(idx) {
    const i = Math.min(frameCount - 1, Math.max(0, Math.round(idx)));
    const img = images[i];
    if (!img || !img.complete) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    // cover
    const r = Math.max(canvas.width / img.width, canvas.height / img.height);
    const w = img.width * r, h = img.height * r;
    ctx.drawImage(img, (canvas.width - w) / 2, (canvas.height - h) / 2, w, h);
  }

  gsap.to(state, {
    frame: frameCount - 1,
    ease: 'none',
    scrollTrigger: {
      trigger: canvas.parentElement,
      start: 'top top',
      end: '+=200%',
      scrub: 0.5,
      pin: true,
    },
    onUpdate: () => render(state.frame),
  });
}
```

### `assets/js/animations/color-invert.js`
```js
import { gsap } from 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js';
import { ScrollTrigger } from 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js';
gsap.registerPlugin(ScrollTrigger);

export function initColorInvert(triggerSelector) {
  if (document.documentElement.getAttribute('data-theme') === 'dark') return; // solo light
  gsap.fromTo(document.documentElement,
    { '--bg': '#FFFFFF', '--text': '#1D1D1F' },
    {
      '--bg': '#0A0A0B',
      '--text': '#F2F2F2',
      ease: 'none',
      scrollTrigger: {
        trigger: triggerSelector,
        start: 'top top',
        end: 'bottom top',
        scrub: 0.5,
      }
    }
  );
}
```

### `cron_recordatorios.php`
```php
<?php
// Ejecutado por cron Hostinger: 0 * * * * php /home/USER/public_html/cron_recordatorios.php
require __DIR__ . '/api/config/database.php';
require __DIR__ . '/services/EmailService.php';

$pdo = (new Database())->getConnection();
$ahora = new DateTime();

// Eventos a 24h y 1h
$rangos = [
  ['horas' => 24, 'col' => 'recordatorio_24h_enviado'],
  ['horas' => 1,  'col' => 'recordatorio_1h_enviado'],
];

foreach ($rangos as $r) {
  $desde = (clone $ahora)->modify("+{$r['horas']} hours");
  $hasta = (clone $desde)->modify('+1 hour');
  $stmt = $pdo->prepare("
    SELECT e.*, GROUP_CONCAT(re.email) as emails
    FROM eventos e
    JOIN registros_eventos re ON re.evento_id = e.id
    WHERE e.fecha_inicio BETWEEN :d AND :h
      AND e.estado = 'programado'
      AND (e.{$r['col']} IS NULL OR e.{$r['col']} = 0)
    GROUP BY e.id
  ");
  $stmt->execute([':d' => $desde->format('Y-m-d H:i:s'), ':h' => $hasta->format('Y-m-d H:i:s')]);
  $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($eventos as $ev) {
    $emails = explode(',', $ev['emails']);
    foreach ($emails as $em) {
      EmailService::send($em, "Recordatorio: {$ev['titulo']}", render_template($ev, $r['horas']));
    }
    $pdo->prepare("UPDATE eventos SET {$r['col']} = 1 WHERE id = ?")
        ->execute([$ev['id']]);
  }
}

function render_template($ev, $horas) {
  $titulo = htmlspecialchars($ev['titulo']);
  $fecha = htmlspecialchars($ev['fecha_inicio']);
  $ubic  = htmlspecialchars($ev['ubicacion']);
  return "<h2>Recordatorio</h2><p>El evento <strong>{$titulo}</strong> comienza en {$horas}h.</p>"
       . "<p><strong>Cuándo:</strong> {$fecha}<br><strong>Dónde:</strong> {$ubic}</p>";
}
```

### Endpoint `/api/csrf-token.php`
```php
<?php
require __DIR__ . '/../config/session-config.php';
header('Content-Type: application/json');
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
echo json_encode(['token' => $_SESSION['csrf_token']]);
```

### Endpoint `/api/theme-config.php`
```php
<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/session-config.php';
require __DIR__ . '/../middleware/csrf-protection.php';

header('Content-Type: application/json');
$pdo = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $rows = $pdo->query("SELECT clave, valor FROM theme_config")->fetchAll(PDO::FETCH_KEY_PAIR);
  echo json_encode(['success' => true, 'data' => $rows]);
  exit;
}

// PUT: solo superadmin
$superadmin = (int)($_SESSION['superadmin'] ?? 0);
if ($superadmin !== 1) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'No autorizado']);
  exit;
}

if ($method === 'PUT') {
  CSRFProtection::validate();
  $body = json_decode(file_get_contents('php://input'), true);
  $stmt = $pdo->prepare("UPDATE theme_config SET valor = :v, updated_by = :u WHERE clave = :k");
  foreach ($body as $k => $v) {
    $stmt->execute([':k' => $k, ':v' => $v, ':u' => $_SESSION['user_id'] ?? null]);
  }
  echo json_encode(['success' => true]);
}
```

### Endpoint `/api/descuentos/validar.php`
```php
<?php
require __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

$codigo = $_GET['c'] ?? '';
if (!$codigo || !preg_match('/^[a-f0-9-]{8,64}$/i', $codigo)) {
  echo json_encode(['estado' => 'invalido', 'message' => 'Código inválido']); exit;
}

$pdo = (new Database())->getConnection();
$stmt = $pdo->prepare("
  SELECT du.*, d.titulo, d.porcentaje_descuento, e.nombre as empresa
  FROM descuentos_usos du
  JOIN descuentos d ON d.id = du.descuento_id
  LEFT JOIN empresas_convenio e ON e.id = d.empresa_oferente_id
  WHERE du.codigo_unico = :c
");
$stmt->execute([':c' => $codigo]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) { echo json_encode(['estado' => 'invalido']); exit; }
if ((int)$row['usado'] === 1) {
  echo json_encode([
    'estado' => 'usado',
    'fecha_validacion' => $row['fecha_validacion'],
    'descuento' => $row['titulo'],
  ]); exit;
}

$pdo->prepare("UPDATE descuentos_usos SET usado = 1, fecha_validacion = NOW() WHERE codigo_unico = :c")
    ->execute([':c' => $codigo]);

echo json_encode([
  'estado' => 'valido',
  'descuento' => $row['titulo'],
  'porcentaje' => $row['porcentaje_descuento'],
  'empresa' => $row['empresa'],
]);
```

(Resto de endpoints — `catalogos.php`, `i18n.php`, `empresa-visibilidad.php`, `comite-sesiones.php`, `descuentos/usar.php`, `push/*.php` — siguen mismo patrón. El LLM debe generarlos completos.)

---

## 📱 Navegación

- **Desktop ≥1024px**: Sidebar fijo izquierdo 240px → colapsable a 72px (solo iconos). Hover expande overlay.
- **Mobile <1024px**: Bottom nav fijo 64px con 5 íconos: Inicio · Directorio · Eventos · Comités · Más. Hamburger top para resto.
- **Tablet 768-1024px**: Sidebar overlay deslizable.
- Page transitions con Swup, agrupadas por sección.

---

## 🎬 Scroll storytelling

### Landing `/`
1. **Hero (100vh, pin scroll 200%)**: image sequence scrubbing 60 frames + h1 con drawSVG underline + CTA magnetic.
2. **Quiénes somos (200vh)**: SVG morphing del logo + 3 párrafos stagger reveal.
3. **Métricas en vivo (100vh)**: contadores animados leyendo `/api/admin/stats.php`.
4. **Empresas destacadas**: carrusel horizontal con drag + tilt + lift.
5. **Próximos eventos**: timeline vertical drawSVG conectando puntos.
6. **CTA final**: "Únete al Clúster" magnetic.
7. **Footer**: links, redes, idioma toggle, dark mode toggle.

### Dashboard `/app/dashboard.html`
- Header personal con avatar + saludo + badge rol.
- 4 KPI cards con números reales + microspark SVG.
- Feed `/api/notificaciones.php`.
- Próximas sesiones de comités donde es miembro.
- Banner carrusel `banner_carrusel`.

---

## ✅ Checklist de aceptación

- [ ] Las 21 tablas BD intactas + 13 migraciones nuevas aplicadas sin error.
- [ ] Login/logout/reset funcionan con cookie + CSRF.
- [ ] Los 4 roles entran a sus vistas correctas (server-side gatekeep + UI).
- [ ] Directorio: socio puede toggle individual de campos visibles + casilla maestra.
- [ ] Filtros municipio/sector/exporta/certificaciones funcionan.
- [ ] Empresa socio edita su perfil → genera `solicitudes_empresa pendiente` → admin aprueba → cambios se aplican.
- [ ] Eventos con `beneficio_cluster` muestran contacto del beneficio en detalle.
- [ ] Comités muestran link Forms + WhatsApp + sesiones próximas con countdown.
- [ ] Descuentos: click "Quiero usar" → genera código único + QR + `validar.html` valida correctamente (3 estados).
- [ ] Boletines/documentos: upload archivo POST FormData, descarga incrementa contador.
- [ ] Notificaciones in-app + emails configurables + push opcional.
- [ ] Cron de recordatorios envía 24h y 1h antes (verificar `recordatorio_*_enviado`).
- [ ] Bilingüe ES/EN funciona, persiste en localStorage.
- [ ] Light/Dark toggle persiste + respeta `prefers-color-scheme`.
- [ ] Hero image sequence fluido a 60fps en desktop. Fallback en 3g.
- [ ] Page transitions sin white flash.
- [ ] Sin alertas nativas en toda la app.
- [ ] Sin colores ni textos hardcoded — todo en `theme_config`/`catalogos`/`i18n`.
- [ ] Lighthouse: Performance ≥90, Accessibility ≥95, Best Practices ≥95, SEO ≥90 en landing.
- [ ] Service Worker registra y push test funciona en Chrome/Firefox.

---

## 🚦 Orden de implementación

1. **Migraciones SQL** — ejecutar `MIGRACIONES_NUEVAS.sql` en phpMyAdmin (5 min).
2. **Endpoints PHP nuevos** — los 8 listados arriba.
3. **Core JS + Tailwind build** — wrappers, theme, i18n, router, toast.
4. **Layout principal** — sidebar/bottom-nav + Swup.
5. **Landing público** — hero sequence + scrollytelling completo.
6. **Auth pages** — login/registro/recuperar/validar.
7. **Módulo Directorio** (prioridad #1).
8. **Módulo Eventos** (prioridad #2).
9. **Módulo Comités** (prioridad #3).
10. Resto: Boletines, Documentos, Descuentos, Buzón, Notificaciones.
11. Panel superadmin: theme_config, catalogos, i18n editor, banners, email_config.
12. PWA — service worker + manifest + push.
13. i18n EN — completar `en.json`.
14. QA — Lighthouse + checklist manual.

---

## 📦 Archivos de contexto que el LLM debe leer ANTES de generar código

1. `SISTEMA_ANALISIS.md` — toda la BD, APIs, flujos.
2. `STACK_HOSTINGER.md` — restricciones técnicas y stack exacto.
3. `MIGRACIONES_NUEVAS.sql` — las únicas migraciones a aplicar.
4. `Inter-4/web/` — fuentes ya descargadas.
5. `claude.md` raíz y `CLAUDE.md` interno — bugs históricos.

---

## 🎁 Tono y patrón del copy

- **Landing**: declarativo, premium, ≤12 palabras hero. Ej: "Conectamos empresas. Catalizamos resultados."
- **Dashboard**: minimal verbal, datos concretos, microcopy útil.
- **Errors**: humanos, accionables. "No pudimos conectar. Reintenta en un momento."
- **Empty states**: invitan acción.
- **EN formal**: "Sign in" no "Log in".

---

## ⚙️ Constraints adicionales

- Hero sequence: ≤4MB total (60 frames × ~50-70KB WebP q75).
- Bundle JS core: ≤80KB minified+gzipped.
- Tailwind CSS final: ≤30KB.
- LCP ≤2.5s en 4G simulado · TBT ≤200ms · CLS ≤0.1.
- Inter Variable preload en `<head>`: `<link rel="preload" href="/assets/fonts/inter/InterVariable.woff2" as="font" type="font/woff2" crossorigin>`.

---

## 🚀 Output esperado del LLM

1. Estructura de carpetas (`mkdir -p` lista).
2. Contenido COMPLETO de cada archivo en `core/`, `animations/`, `components/`.
3. **3 vistas HTML completas** mínimo: `index.html` (landing), `app/directorio.html`, `admin/empresas.html`.
4. Endpoints PHP nuevos con código funcional (no pseudocódigo).
5. `tailwind.config.js` y `src/main.css` listos para `npm run build:css`.
6. `service-worker.js` con registro de push.
7. `cron_recordatorios.php` completo (arriba mostrado).
8. `i18n/es.json` y `i18n/en.json` con namespaces `formal` y `casual`.
9. `README_DESPLIEGUE.md` con pasos exactos para subir por FTP a Hostinger.

Si por extensión no cabe en un turno, prioriza:
1. Migraciones + endpoints PHP nuevos.
2. Core JS (api, auth, theme, i18n, router, toast).
3. Layout + landing con hero sequence.
4. Módulo Directorio completo.

Y deja el resto en placeholders documentados para iterar.

---

## 🧠 Recordatorio final

Estás reemplazando UI. **No estás migrando lógica de negocio.** El backend PHP es la fuente de verdad. Cualquier decisión nueva (tabla, columna, endpoint) debe estar justificada contra "Hostinger Shared, sin Node, sin pago extra, sin npm install en producción".

El usuario final del sistema (admins, socios, empleados del Clúster) verá un producto **moderno, premium, líquido, claro y rápido**, con lenguaje visual de Apple/Porsche, pero corriendo sobre la misma infraestructura PHP/MySQL que ya paga.

Eso es el éxito.
