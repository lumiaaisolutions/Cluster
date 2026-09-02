# 🏗️ Stack Definitivo — 100% Compatible con Hostinger Shared

> **Restricción dura**: el resultado debe subirse vía FTP a `public_html` sin Node.js, sin servicios externos de pago, sin claves API de terceros, sin build pipeline en servidor. Todo se construye local y se suben archivos planos.

---

## 1. Decisión arquitectónica

### ❌ Descartado: Next.js
- Hostinger Shared no soporta Node.js. Requeriría VPS (~$8-20/mes) o desplegar en Vercel y conectar cross-origin con tu API PHP (conflicto CORS, latencia extra, otra factura).
- `next export` genera estático pero rompe Server Components, middleware, image optimization y CSRF cookies cross-origin.

### ✅ Adoptado: **Stack web nativo (HTML+CSS+JS) sobre PHP existente**
Mismo stack que tu sistema actual, pero con librerías modernas vía CDN público. Subes archivos vía FTP exactamente como hoy.

### Precedente real
Este enfoque es como Apple hizo el famoso scroll scrubbing del MacBook Pro: HTML+JS+canvas, no necesita framework. Pure web platform.

---

## 2. Stack final — todas las librerías vía CDN gratuito

### 2.1 Animaciones y scroll
```html
<!-- GSAP 3 + ScrollTrigger (gratis) -->
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>

<!-- Lenis smooth scroll -->
<script src="https://cdn.jsdelivr.net/gh/studio-freight/lenis@1.0.42/bundled/lenis.min.js"></script>

<!-- Motion One (sustituto Framer Motion) -->
<script src="https://cdn.jsdelivr.net/npm/motion@10.16.4/dist/motion.min.js"></script>

<!-- VanillaTilt para tilt 3D -->
<script src="https://cdn.jsdelivr.net/npm/vanilla-tilt@1.8.1/dist/vanilla-tilt.min.js"></script>

<!-- Lottie player -->
<script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>
```

### 2.2 Reactividad y UI
```html
<!-- Alpine.js: reactividad declarativa sin build -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

<!-- Notyf: toasts sin alert nativo -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

<!-- Swup: page transitions SPA-like -->
<script src="https://cdn.jsdelivr.net/npm/swup@4.6.0/dist/Swup.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@swup/fade-theme@2/dist/index.min.js"></script>

<!-- QR code generator -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
```

### 2.3 Tailwind CSS — build local (NO CDN play en producción)
```bash
# Una sola vez, en local:
npm i -D tailwindcss
npx tailwindcss -i ./src/input.css -o ./public/assets/css/build.css --minify
# Subes build.css por FTP. El usuario nunca corre npm.
```
> Razón: el CDN `play.tailwindcss.com` es solo para prototipo, lento en producción (~500KB sin minify, sin tree-shake).

### 2.4 Iconos
- **Lucide SVG inline** (`https://lucide.dev`) — copia/pega los SVG que necesites en componentes. No carga librería entera.

### 2.5 Backend (intacto)
- PHP 8.x ✅ (Hostinger soporta 8.0/8.1/8.2)
- MySQL/MariaDB ✅
- PHPMailer standalone ✅ (ya en `/build/services/phpmailer/`)
- Sin Composer

---

## 3. Estructura de carpetas propuesta para `public_html` (post-rediseño)

```
public_html/
├── index.html                     ← Landing público (NUEVO, reemplaza index.php)
├── login.html                     ← Login
├── registro.html                  ← Registro
├── recuperar.html                 ← Reset password
│
├── app/                           ← Vistas autenticadas (SPA-like con Swup)
│   ├── dashboard.html
│   ├── directorio.html
│   ├── empresa-detalle.html
│   ├── eventos.html
│   ├── evento-detalle.html
│   ├── calendario.html
│   ├── comites.html
│   ├── comite-detalle.html
│   ├── boletines.html
│   ├── documentos.html
│   ├── descuentos.html
│   ├── perfil.html
│   ├── perfil-empresa.html
│   ├── buzon.html
│   └── notificaciones.html
│
├── admin/                         ← Vistas admin/superadmin
│   ├── empresas.html
│   ├── eventos.html
│   ├── comites.html
│   ├── boletines.html
│   ├── documentos.html
│   ├── descuentos.html
│   ├── usuarios.html
│   ├── solicitudes.html
│   ├── estadisticas.html
│   ├── banners.html
│   └── configuracion.html
│
├── api/                           ← INTACTO — todo lo de hoy
│   └── (51 endpoints PHP existentes)
│
├── assets/
│   ├── css/
│   │   └── build.css              ← Tailwind compilado
│   ├── js/
│   │   ├── core/
│   │   │   ├── api.js             ← fetch wrapper con credentials:include + CSRF
│   │   │   ├── auth.js            ← getUser(), requireRole()
│   │   │   ├── i18n.js            ← bilingüe ES/EN
│   │   │   ├── theme.js           ← light/dark + theme_config dinámico
│   │   │   ├── toast.js           ← wrapper Notyf
│   │   │   ├── router.js          ← Swup config + transiciones
│   │   │   └── lenis-init.js
│   │   ├── modules/
│   │   │   ├── directorio.js
│   │   │   ├── eventos.js
│   │   │   ├── comites.js
│   │   │   ├── boletines.js
│   │   │   ├── descuentos.js
│   │   │   ├── documentos.js
│   │   │   ├── perfil-empresa.js
│   │   │   ├── solicitudes.js
│   │   │   └── notificaciones.js
│   │   ├── animations/
│   │   │   ├── hero-sequence.js   ← image sequence scrubbing canvas
│   │   │   ├── scroll-reveal.js   ← stagger + magnetic + drawSVG
│   │   │   ├── color-invert.js    ← inversión light→dark al scroll en hero
│   │   │   └── page-transitions.js
│   │   └── components/
│   │       ├── nav-desktop.js
│   │       ├── nav-mobile-bottom.js
│   │       ├── card-tilt.js       ← VanillaTilt config
│   │       ├── modal.js
│   │       ├── data-table.js
│   │       └── filter-bar.js
│   ├── img/
│   │   ├── logo/
│   │   ├── sequences/             ← frames del image scrubbing
│   │   │   └── hero/0001.webp ... 0120.webp
│   │   └── placeholders/
│   ├── fonts/
│   │   └── inter/                 ← self-host de Inter (subset variable)
│   └── lottie/
│       └── hero-placeholder.json
│
├── i18n/
│   ├── es.json
│   └── en.json
│
├── service-worker.js              ← push notifications
├── manifest.json                  ← PWA básico
└── .htaccess                      ← rewrite + headers (NUEVO)
```

---

## 4. Cómo se siente "SPA" sin React/Next

**Swup** + **Lenis** + **GSAP** dan la sensación SPA real:
1. Click en link → Swup intercepta navegación.
2. Anima salida de la vista actual (fade + scale).
3. Hace fetch HTML del destino en background.
4. Reemplaza el `<main>` y anima entrada.
5. Re-ejecuta scripts del módulo nuevo.
6. URL cambia con History API.

Resultado: el usuario nunca ve un white flash de recarga. Se siente como Next.js sin serlo.

---

## 5. Patrones críticos para que funcione en Hostinger

### 5.1 fetch wrapper unificado
```js
// /assets/js/core/api.js
export async function api(path, opts = {}) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  return fetch(`/api/${path}`, {
    credentials: 'include',                    // BUG-012
    headers: {
      'Content-Type': 'application/json',
      ...(csrf && { 'X-CSRF-TOKEN': csrf }),
      ...opts.headers
    },
    ...opts
  }).then(r => r.json());
}
```

### 5.2 Detección de entorno
```js
// SIEMPRE usar 'clautmetropolitano.mx' (sin tilde) — BUG-011
const isProd = location.hostname.includes('clautmetropolitano');
const API_BASE = isProd ? '' : 'http://localhost/Claut_BD/build';
```

### 5.3 Sin alertas nativas
```js
// /assets/js/core/toast.js
import { Notyf } from 'notyf';
export const toast = new Notyf({
  duration: 4000,
  position: { x: 'right', y: 'top' },
  types: [
    { type: 'success', background: '#16a34a' },
    { type: 'error',   background: '#C7252B' }
  ]
});
// Uso: toast.success('Guardado'). Nunca alert().
```

### 5.4 Image sequence scrubbing (la receta de Apple)
```js
// /assets/js/animations/hero-sequence.js
// Carga 120 frames WebP, los pinta sobre <canvas>, ScrollTrigger mapea
// scrollProgress -> frameIndex. GPU compositor dibuja sin reflow.
```

### 5.5 Tailwind compile script (un solo paso local)
```json
// package.json
{
  "scripts": {
    "build:css": "tailwindcss -i ./src/main.css -o ./public_html/assets/css/build.css --minify",
    "watch:css": "tailwindcss -i ./src/main.css -o ./public_html/assets/css/build.css --watch"
  }
}
```
> Para subir a producción: `npm run build:css` → subes `public_html/` por FTP.

---

## 6. Lo que necesitas instalar EN TU MÁQUINA (no en Hostinger)
1. **Node.js 18+** local (solo para correr `tailwindcss` cuando edites estilos).
2. **Cliente FTP** (FileZilla / Cyberduck) — ya lo usas.
3. **Editor** (VS Code, Cursor) — ya lo usas.

Nada se instala en el servidor. El servidor solo corre PHP + MySQL exactamente como hoy.

---

## 7. Verificación de soporte Hostinger

| Recurso | Plan Hostinger Premium/Business Shared | Estado |
|---------|----------------------------------------|--------|
| PHP 8.x | ✅ | OK |
| MySQL | ✅ | OK |
| HTTPS / SSL | ✅ Let's Encrypt incluido | OK |
| `.htaccess` (rewrites) | ✅ Apache habilitado | OK |
| Service Worker (PWA) | ✅ requiere HTTPS — listo | OK |
| Push Notifications API | ✅ (browser-side, no server push externo) | OK |
| Subdominios | ✅ | OK |
| Cron jobs (recordatorios eventos) | ✅ wp-cron equivalente | OK |
| Almacenamiento archivos `/uploads/` | ✅ (ya lo usas) | OK |
| Tamaño máximo upload | ~256MB (configurable en `.htaccess`) | OK |
| Node.js | ❌ no en Shared | **NO USAR** |
| Composer | ❌ no en Shared | **NO USAR** (PHPMailer ya standalone) |

Conclusión: el stack propuesto está 100% dentro de los límites de tu plan actual.
