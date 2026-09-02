# 🚀 README de Despliegue — CLAUTMET Intranet (Rediseño v2.0)

> Stack 100% Hostinger Shared compatible. Sin Node en producción, sin Composer, sin servicios externos de pago.

---

## 📁 Estructura entregada

```
rediseno/codigo/
├── package.json                    ← Solo para correr Tailwind localmente (NO subir)
├── tailwind.config.js              ← Config Tailwind (NO subir)
├── src/main.css                    ← Source CSS (NO subir)
├── README_DESPLIEGUE.md            ← Este archivo
└── public_html/                    ← TODO lo de aquí se sube por FTP a public_html
    ├── index.html                  ← Landing
    ├── login.html
    ├── registro-empresa.html
    ├── validar.html                ← Validación QR de descuentos
    ├── manifest.json
    ├── service-worker.js
    ├── cron_recordatorios.php
    ├── .htaccess
    ├── api/
    │   ├── csrf-token.php
    │   ├── theme-config.php
    │   ├── catalogos.php
    │   ├── i18n.php
    │   ├── empresa-visibilidad.php
    │   ├── comite-sesiones.php
    │   ├── descuentos/
    │   │   ├── usar.php
    │   │   └── validar.php
    │   └── push/
    │       └── subscribe.php
    ├── app/dashboard.html          ← Vista interna
    ├── assets/
    │   ├── css/build.css           ← Generado con `npm run build:css`
    │   ├── js/
    │   │   ├── core/{api,auth,theme,i18n,toast,router,lenis-init}.js
    │   │   ├── animations/{hero-sequence,color-invert,scroll-reveal}.js
    │   │   └── components/{card-tilt,math-captcha,qr-display}.js
    │   ├── img/logo/{logo-light,logo-dark,logo-edomex}.png
    │   ├── fonts/inter/{InterVariable,InterVariable-Italic}.woff2 + inter.css
    │   └── lottie/hero-placeholder.json
    └── i18n/{es,en}.json
```

---

## ⚙️ Pasos de despliegue

### 1) Backup obligatorio

En **phpMyAdmin Hostinger** → Export → SQL → guardar dump de BD. **No omitir.**

### 2) Aplicar migraciones SQL

Abrir `rediseno/MIGRACIONES_NUEVAS.sql` y pegarlo entero en phpMyAdmin → SQL → Continuar. Las migraciones son **idempotentes** (`IF NOT EXISTS`), seguras de re-correr.

Verifica en pestaña Structure de cada tabla afectada que aparezcan las nuevas columnas/tablas. En Hostinger, errores con `INFORMATION_SCHEMA` son normales — ignorar.

### 3) Compilar Tailwind localmente

```bash
cd rediseno/codigo
npm install              # solo la primera vez (instala tailwindcss)
npm run build:css        # genera public_html/assets/css/build.css
```

Re-ejecuta `npm run build:css` cada vez que cambies clases de Tailwind. Para watch live mientras desarrollas: `npm run watch:css`.

### 4) Marcar el primer superadmin

En phpMyAdmin → tabla `usuarios_perfil` → editar tu propio registro:
```sql
UPDATE usuarios_perfil SET superadmin = 1, rol = 'admin' WHERE email = 'TU_EMAIL';
```

### 5) Subir por FTP

Cliente FTP (FileZilla / Cyberduck) → `public_html/` (Hostinger).

**Subir todo el contenido** de `rediseno/codigo/public_html/` a la raíz `public_html/` del servidor.

⚠️ **NO sobrescribas estos directorios existentes** del backend:
- `public_html/api/auth/` (login, register, logout, etc.)
- `public_html/api/admin/` (users, stats)
- `public_html/api/{eventos,boletines,comites,...}.php` (los 51 endpoints existentes)
- `public_html/config/` (database, session, env-loader)
- `public_html/middleware/` (cors, csrf, jwt, rate-limiter)
- `public_html/services/phpmailer/`
- `public_html/.env`
- `public_html/uploads/`

✅ **Sí subir/sobrescribir**:
- `index.html`, `login.html`, `validar.html`, `registro-empresa.html`
- `manifest.json`, `service-worker.js`, `cron_recordatorios.php`, `.htaccess`
- `api/csrf-token.php`, `api/theme-config.php`, `api/catalogos.php`, `api/i18n.php`, `api/empresa-visibilidad.php`, `api/comite-sesiones.php`, `api/descuentos/usar.php`, `api/descuentos/validar.php`, `api/push/subscribe.php` (archivos NUEVOS, no había antes)
- `app/dashboard.html`
- `assets/css/build.css`
- `assets/js/core/*`, `assets/js/animations/*`, `assets/js/components/*`
- `assets/img/logo/*`
- `assets/fonts/inter/*`
- `assets/lottie/hero-placeholder.json`
- `i18n/es.json`, `i18n/en.json`

### 6) Configurar el cron de recordatorios

Hostinger cPanel → **Cron Jobs** → Agregar:

```
Frecuencia: cada hora (0 * * * *)
Comando:    /usr/bin/php /home/u123456789/public_html/cron_recordatorios.php
```

(Reemplaza `u123456789` por tu username Hostinger real). Crear directorio `public_html/storage/` con permisos 755 si no existe (para el log).

### 7) Generar VAPID keys (opcional, push notifications)

Solo si vas a usar push browser. En tu máquina local:

```bash
npx --yes web-push generate-vapid-keys
```

Pegar resultado en phpMyAdmin → tabla `theme_config`:
```sql
UPDATE theme_config SET valor = 'TU_PUBLIC_KEY'  WHERE clave = 'vapid_public_key';
UPDATE theme_config SET valor = 'TU_PRIVATE_KEY' WHERE clave = 'vapid_private_key';
UPDATE theme_config SET valor = '1' WHERE clave = 'push_enabled';
```

### 8) Verificación

Visita https://intranet.clautmetropolitano.mx (o donde despliegues):

- [ ] Landing carga, hero se ve (con Lottie placeholder si no hay frames).
- [ ] Toggle dark/light funciona y persiste.
- [ ] Toggle ES/EN funciona y persiste.
- [ ] Sectores aparecen leídos del catálogo (no hardcoded).
- [ ] Login con usuario admin existente entra a `/app/dashboard.html`.
- [ ] Dashboard muestra KPIs reales y próximas sesiones.
- [ ] `/validar.html?c=invalido` muestra estado "INVÁLIDO".
- [ ] `/registro-empresa.html` envía solicitud y crea registro pendiente.
- [ ] Service worker se registra (DevTools → Application → Service Workers).
- [ ] Lighthouse Performance ≥ 90 en landing.

---

## 🎬 Generar frames del image sequence (cuando tengas video)

Ver `rediseno/GENERAR_FRAMES.md`. Resumen:

```bash
brew install ffmpeg
ffmpeg -i tu_video.mp4 -vf "fps=10,scale=1920:-2:flags=lanczos" \
  -c:v libwebp -quality 75 -compression_level 6 frames_hero/%04d.webp
```

Subir `frames_hero/` por FTP a `public_html/assets/img/sequences/hero/`. El sitio detecta automáticamente y reemplaza el Lottie placeholder.

---

## 🛡️ Notas de seguridad

- **Cookie de sesión** `CLAUT_SESSION` con `httpOnly + secure + SameSite=Lax` (lo manejan los archivos PHP existentes).
- **CSRF**: el frontend pide token en `/api/csrf-token.php` al cargar y lo inyecta en `<meta name="csrf-token">`. Todas las mutaciones lo envían en header `X-CSRF-TOKEN`.
- **Rate limiter**: ya configurado en middleware existente (5 logins/5min, 100 req/min API público).
- **CORS**: whitelist ya apunta a `https://intranet.clautmetropolitano.mx`. Si agregas otro origen, edita `middleware/cors.php`.
- **Captcha math**: en `/registro-empresa.html` para evitar spam público.
- **Permisos `cron_recordatorios.php`**: 644. No expuesto vía web (HTTP da 200 pero solo registra log si se invoca directamente — el cron lo ejecuta vía CLI sin riesgo).

---

## 🧠 Reglas heredadas que el frontend respeta automáticamente

- BUG-011: hostname `clautmetropolitano.mx` (sin tilde).
- BUG-012: `credentials: 'include'` en todos los fetches.
- BUG-014: FormData → POST (PUT no popula `$_POST`/`$_FILES`).
- BUG-015: NO `ApiValidator` con `$_FILES`.
- BUG-019: `isSubmitting` flag (en login.html + form de empresa).
- BUG-020: `?t=Date.now()` cache busting automático en GETs.
- Sin `alert()`/`confirm()`/`prompt()` — usar `toast` y `confirmDialog` de `/assets/js/core/toast.js`.

---

## 🆘 Rollback

Si algo se rompe en producción:

1. **Frontend**: subir backup previo de `public_html/` (lo tenías antes del despliegue).
2. **BD**: restaurar dump del paso 1. Las migraciones son **aditivas** — los datos viejos no se perdieron, solo se ignoran las columnas nuevas. En la práctica casi nunca necesitas rollback de BD.
3. **Cron**: desactivar desde cPanel → Cron Jobs si genera spam.

---

## 📞 Cosas que faltan capturar (no bloquean despliegue)

Una vez en producción, capturar desde panel admin/superadmin:

1. **Frames del image sequence** (Lottie cubre mientras tanto).
2. **VAPID keys** (push deshabilitado mientras tanto).
3. **Comités**: `link_whatsapp` y `link_google_form` por cada comité existente.
4. **Empresas**: marcar `es_socio = 1` para los socios CLAUTMET reales y `0` para empresas no-socias del directorio.
5. **Banners** del carrusel home desde `/admin/banners.html`.
6. **theme_config**: ajustar `site_tagline_es`, descripciones, colores si se requieren cambios.

Todo es editable desde panel sin tocar código.

---

> **Estado**: listo para desarrollo y pruebas. El sistema entrega una experiencia premium tipo Apple/Porsche con scroll storytelling, image sequence híbrido, light/dark mode, bilingüe, sobre la misma BD MySQL y APIs PHP existentes.
