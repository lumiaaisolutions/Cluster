# 🌐 Landing Page Pública — Arquitectura

> Documento técnico que describe cómo la página de aterrizaje pública convive con el sistema autenticado de Clúster Intranet.

---

## 🎯 Objetivo

Que cualquier visitante que entre a `https://intranet.clautmetropolitano.mx` vea la **landing pública** (marketing) en lugar del formulario de login. Los socios ya autenticados siguen viendo el dashboard como antes.

---

## 🗂️ Estructura de archivos

```
public_html/
├── index.php                 ← Router raíz (decide qué servir)
├── .htaccess                 ← Reglas Apache (permite /landing/)
├── landing/                  ← Landing pública aislada
│   ├── index.html            ← HTML principal
│   ├── assets/
│   │   ├── css/              ← 9 hojas de estilo
│   │   ├── js/               ← i18n, sequence, ui, scrollfx
│   │   ├── img/              ← logos, banderas, sectores
│   │   └── sequence/         ← 168 frames JPG (~3.9 MB) para scroll animado
│   └── uploads/              ← imágenes de eventos / screenshots
├── pages/
│   ├── sign-in.html          ← Login del sistema (intacto)
│   └── sign-up.html          ← Registro del sistema (intacto)
└── dashboard.html            ← Dashboard interno (intacto)
```

---

## 🔁 Flujo de routing (`index.php`)

| Condición | Acción |
|---|---|
| Visitante NO autenticado entra a `/` | Sirve `landing/index.html` |
| Visitante autenticado entra a `/` | Sirve `dashboard.html` |
| `/index.php?dashboard=1` o `?home=1` | Fuerza `dashboard.html` |
| `/index.php?landing=1` | Fuerza landing (preview / debug) |

La autenticación se determina por (en orden):
1. `?authenticated=true` en query string
2. Cookie `claut_authenticated=true`
3. Variable de sesión PHP `$_SESSION['usuario_id']`

---

## 🔗 Mapeo de URLs en la landing

Todos los botones de la landing apuntan al sistema existente con **rutas absolutas**:

| Botón landing | Destino real |
|---|---|
| "Iniciar sesión" (nav, hero, footer) | `/pages/sign-in.html` |
| "Iniciar registro" (CTA principal) | `/pages/sign-up.html` |
| "Registro" (footer) | `/pages/sign-up.html` |
| "Intranet" (footer) | `/index.php?dashboard=1` |
| Anchors internos (`#about`, `#contacto`...) | Misma página (no salen del documento) |
| Aviso de privacidad / Términos / Cookies | `#` *(placeholder — pendiente fase 2)* |

---

## 📐 Decisiones de diseño

### ¿Por qué `/landing/` como subcarpeta y no `/`?

Para **evitar colisiones** con carpetas ya existentes en producción:
- `/assets/`, `/css/`, `/js/`, `/uploads/` ya pertenecen al dashboard.
- Si la landing reusara esas rutas, sus archivos se mezclarían con los del sistema.

La landing queda aislada como módulo, fácil de actualizar o reemplazar sin riesgo.

### ¿Por qué URLs absolutas (`/landing/assets/...`) en lugar de relativas?

Tres opciones se evaluaron:

| Opción | Pros | Contras |
|---|---|---|
| Relativas (`assets/...`) + `<base href>` inyectado | Cero cambios al HTML | `<base>` rompe anchors internos (`#about` → `/landing/#about` recarga página) |
| Redirect 302 a `/landing/` | Estándar HTTP | URL cambia visiblemente; no es lo pedido |
| **Absolutas (`/landing/assets/...`)** ✅ | Funciona desde `/` y `/landing/`. Anchors intactos. Sin redirects. | Cambio invasivo al HTML (resuelto con `perl -i`) |

Elegida la opción 3 por robustez.

### ¿Por qué `include` y no `header('Location:')`?

`include` mantiene la URL visible como `/` (sin cambio a `/landing/`), preservando la experiencia que pidió el usuario. Como las URLs internas son absolutas, no hay problema de resolución.

---

## 🛠️ Cambios aplicados

### Archivos modificados
1. **`build/index.php`** — Nueva lógica de routing (servir landing por defecto, dashboard si hay sesión).
2. **`build/.htaccess`** — Añadida regla `RewriteCond %{REQUEST_URI} ^/landing/` para acceso directo a assets.

### Archivos nuevos
3. **`build/landing/index.html`** — Landing con URLs absolutas (`/landing/assets/...`, `/pages/sign-in.html`).
4. **`build/landing/assets/`** — CSS, JS, IMG, sequence (heredados de Cluster Portal 2).
5. **`build/landing/uploads/`** — Imágenes adicionales.

### Cambios internos a la landing
- 20 paths relativos (`assets/`, `uploads/`) → absolutos (`/landing/assets/`, `/landing/uploads/`).
- `assets/sequence/ezgif-frame-` → `/landing/assets/sequence/ezgif-frame-` en `sequence.js`.
- Botones de login y registro reapuntados al sistema (`/pages/sign-in.html`, `/pages/sign-up.html`).
- Links legales (`aviso-privacidad.php`, `terminos.php`, `cookies.php`) → `#` con `onclick="event.preventDefault()"` (placeholders, fase 2).

---

## 🔒 Garantías de no-regresión

- ✅ `pages/sign-in.html`, `pages/sign-up.html`, `dashboard.html`: **no tocados**.
- ✅ Todas las APIs (`/api/*`): **no tocadas**.
- ✅ Sesiones, cookies, JWT, autenticación: **lógica intacta**.
- ✅ `admin/`, `gestionar_usuarios.php`, perfiles: **sin cambios**.
- ✅ El usuario autenticado entra a `/` y va directo al dashboard, igual que antes.

---

## 🚨 Plan de rollback (30 segundos)

Si la landing causa problemas en producción:

1. Restaurar `index.php` a la versión anterior:
   ```php
   // Reemplazar línea: include __DIR__ . '/landing/index.html';
   // Por:              header('Location: ./pages/sign-in.html', true, 302);
   ```
2. (Opcional) Revertir la regla añadida en `.htaccess` (`RewriteCond ^/landing/`).
3. La carpeta `/landing/` puede quedarse en el servidor sin afectar nada.

---

## 📋 Pendientes (fase 2)

- [ ] Crear páginas legales reales: `aviso-privacidad.html`, `terminos.html`, `cookies.html`.
- [ ] Decidir si el botón "Iniciar sesión" del header del sistema debe enlazar a "Volver a la landing" en sign-in.html.
- [ ] Considerar minificar / optimizar la carpeta `sequence/` (3.9 MB → posible WebP a ~1 MB).
- [ ] Analítica en la landing (Google Analytics / Plausible / etc).

---

## 🧭 URLs de prueba post-deploy

| URL | Resultado esperado |
|---|---|
| `https://intranet.clautmetropolitano.mx/` | Landing pública |
| `https://intranet.clautmetropolitano.mx/?landing=1` | Landing forzada |
| `https://intranet.clautmetropolitano.mx/?dashboard=1` | Dashboard (si hay sesión) |
| `https://intranet.clautmetropolitano.mx/pages/sign-in.html` | Login del sistema |
| `https://intranet.clautmetropolitano.mx/pages/sign-up.html` | Registro del sistema |
| `https://intranet.clautmetropolitano.mx/landing/` | Landing directa (debug) |
| `https://intranet.clautmetropolitano.mx/landing/assets/css/tokens.css` | Archivo asset directo (200 OK) |
