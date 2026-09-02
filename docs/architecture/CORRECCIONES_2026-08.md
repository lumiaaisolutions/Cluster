# 🔧 Correcciones de Flujos Rotos e Inconsistencias — Agosto 2026

> Fecha: 2026-08-31 | Auditoría automatizada de referencias (href/src/fetch/redirects) sobre `build/`
> Resultado inicial: **62 referencias rotas reales** detectadas → corregidas o clasificadas.
> Script de auditoría: escaneo de `href/src/action`, `fetch()` y `window.location` en HTML/JS/PHP, resolviendo rutas relativas contra la página que incluye cada script.

---

## FASE 1 — Seguridad

### 1.1 Páginas `demo_*` ahora protegidas del lado servidor (CRÍTICO)
- **Problema**: `.htaccess` tenía la regla "PÁGINAS DEMO PÚBLICAS" (`^/demo_` → acceso libre). Las `demo_*.html` son paneles administrativos reales (empresas, boletines, usuarios) y solo se protegían con `auth-session.js` del lado cliente — bypasseable con `curl` o viendo el código fuente. Además, quitar la excepción no bastaba: el `.htaccess` no tiene default-deny, así que se necesitaba un gate real.
- **Solución**: Nuevo archivo **`build/auth-gate.php`**: valida la sesión PHP (`SessionConfig::init()` + `$_SESSION['user_email']`/`user_rol`) y sirve el HTML con `readfile()` solo si hay sesión activa; si no, redirige a `/pages/sign-in.html?redirect=...`. El `.htaccess` reescribe `demo_*.html` → `auth-gate.php?page=...` con whitelist estricta (`^demo_[a-z_]+\.html$` + `is_file`).
- **Archivos**: `build/auth-gate.php` (nuevo), `build/.htaccess` (regla reemplazada).
- **Regla**: NO restaurar el acceso directo sin autenticación a `demo_*`. Cualquier página administrativa estática nueva debe servirse a través del gate o convertirse a PHP con validación de sesión.

### 1.2 Fuga de `$_SESSION` completo en `api/check_me.php` (CRÍTICO)
- **Problema**: El endpoint devolvía `all_session` (la sesión completa), `session_id` y `raw_session_keys` en el JSON — la misma clase de vulnerabilidad que se corrigió en `login-compatible.php` (ver ANALISIS_SISTEMA.md #5). Sin consumidores en el frontend (era un endpoint de debug olvidado).
- **Solución**: Reescrito para devolver solo `authenticated`, `detected_role`, `is_admin`. Nunca exponer `$_SESSION` ni `session_id()`.
- **Archivos**: `build/api/check_me.php`.

---

## FASE 2 — Flujos rotos críticos

### 2.1 API de registros de eventos con error fatal 500 (CRÍTICO)
- **Problema**: `api/registros_eventos.php` hacía `require_once 'config.php'` — **archivo que no existe** en `build/api/`. Toda la API (listar/crear/editar/eliminar registros de eventos) moría con fatal error 500.
- **Solución**: `require_once __DIR__ . '/../config/database.php';` (mismo patrón que `api/eventos.php`).
- **Archivos**: `build/api/registros_eventos.php`.

### 2.1.b Desajuste de esquema en `api/registros_eventos.php` (documentado, no corregido)
- **Hallazgo post-deploy**: la tabla real de producción `registros_eventos` usa las columnas `estado`, `usuario_id`, `comentarios`, `asistio` — pero el API asume `estado_registro`, `user_id`, `apellido`, `notas_especiales`, `codigo_qr`. Su `GET` funciona (usa `r.*`), pero su `POST/INSERT` fallaría contra el esquema real.
- **Decisión**: no se reescribió porque **ningún frontend lo consume** (el flujo real de registro usa `register_evento.php`). Es un API muerto con esquema imaginario. **Candidato a eliminación** o a reescritura si algún día se necesita.

### 2.2 `check_registro.php` no existía (flujo de registro a eventos)
- **Problema**: `eventos.html` y `evento_detalle.php` hacen `fetch('./check_registro.php?evento_id=N&email=...')` para avisar si el usuario ya está registrado a un evento. El archivo no existía → 404 y la verificación fallaba silenciosamente (permitiendo dobles intentos de registro hasta chocar con el backend).
- **Solución**: Creado **`build/check_registro.php`** con el contrato exacto que espera el frontend: `{ status: 'registered'|'not_registered', data: { estado_registro, fecha_registro } }`, consultando `registros_eventos` por `evento_id + email`. Se consulta la columna real `estado` de producción y se mapea a la clave `estado_registro` que espera el frontend. **Verificado en producción** (`not_registered` y validación de parámetros responden correctamente).
- **Archivos**: `build/check_registro.php` (nuevo).

### 2.3 Redirect de sesión expirada apuntaba a página inexistente (CRÍTICO)
- **Problema**: `jwt-manager.js` y `token-refresh-worker.js` redirigían a **`/sign-in.html`** cuando fallaba la renovación del token — esa ruta no existe (la real es `/pages/sign-in.html`). El usuario con sesión expirada caía en el 404 en lugar del login.
- **Solución**: `window.location.href = '/pages/sign-in.html?session_expired=1'` en ambos archivos.
- **Archivos**: `build/assets/js/jwt-manager.js`, `build/assets/js/token-refresh-worker.js`.

### 2.4 Estadísticas del dashboard: endpoint con guion en vez de guion bajo
- **Problema**: `dashboard.html` hacía `fetch('./api/estadisticas-simple.php')` pero el archivo real es `estadisticas_simple.php` → 404 y la sección de estadísticas caía siempre al manejo de error.
- **Solución**: Corregido a `./api/estadisticas_simple.php`.
- **Archivos**: `build/dashboard.html`.
- **Regla**: misma clase de bug que BUG-016 (`nombre` vs `nombre_empresa`) — verificar siempre el nombre exacto del archivo/campo, guion vs guion bajo.

---

## FASE 3 — Links rotos y botones sin backend

| # | Archivo | Problema | Fix |
|---|---------|----------|-----|
| 3.1 | `demo_*.html` (×9) + `gestionar_usuarios.php` | Favicon `../assets/img/apple-icon.png` — ruta sube fuera de la raíz → 404 | `./assets/img/apple-icon.png` |
| 3.2 | `demo_*.html` (×6) | Sidebar "Usuarios" → `demo_usuarios.html` (no existe) | → `gestionar_usuarios.php` |
| 3.3 | `demo_boletines.html` | Botón "Tests" → `test_boletines_completo.php` (no existe) | Botón eliminado |
| 3.4 | `demo_gestion_grafico.html` | 5 scripts 404 activos (`diagnostico-simple.js`, `gestor-datos-reales.js`, `emergencia.js`, `solucionador.js`, `test-tres-puntos.js`) | Tags eliminados; quedan solo los 4 scripts existentes |
| 3.5 | `demo_estadisticasdinamicas.html` | Botones "Resetear Datos" y "Corregir SQL" llamaban a `api/reset-estadisticas.php` y `api/debug-estadisticas.php` (no existen) | Botones eliminados |
| 3.6 | `demo_estadisticasdinamicas.html` | Flujo de recuperación de error: `resolverErrorDuplicado()` y botón "Resetear Sistema" llamaban a `reset-estadisticas.php` inexistente | `resolverErrorDuplicado()` recarga desde `estadisticas-config.php` (API real); botón "Resetear Sistema" eliminado, queda "Reintentar" |
| 3.7 | `admin-panel.html` | Botón → `../demo_empresas.html` (sube fuera de la raíz) | `./demo_empresas.html` |
| 3.8 | `assets/js/eventos.js` | Imagen fallback `evento-default.jpg` no existe | → `placeholder.svg` (sí existe) |
| 3.9 | `admin/banner-admin.js` | Fallback `../assets/img/placeholder.jpg` no existe | → `placeholder.svg` |
| 3.10 | `admin/banner-admin-mejorado.php` | Link `gestionar_usuarios.php` relativo a `/admin/` → 404 | `../gestionar_usuarios.php` |
| 3.11 | `setup/init_database.php` | Link final a `../index.html` (no existe) | `../index.php` |

---

## Clasificados como NO-ACCIÓN (código muerto / falsos positivos)

Documentados para no volver a diagnosticarlos:

- **`dashboard.html`** — 8 scripts "faltantes" (`debug-graficos.js`, `dashboard-simple.js`, `announcements-fix.js`, etc.) ya estaban **comentados** con `<!-- -->`; no producen 404.
- **`js/dashboard-auth.js`** — no está referenciado por ninguna página (código muerto). Su fetch a `api/anuncios.php` no se ejecuta nunca. **Candidato a eliminación.**
- **`components/menu-components.html`** — componente no referenciado por ninguna página. Su link a `documentacion.html` no afecta. **Candidato a eliminación.**
- **`pages/users.php`** — template Argon legacy sin referencias (los flujos reales usan `api/admin/users.php`, que sí existe). **Candidato a eliminación.**
- **`landing/assets/js/ui.js`** — el "formulario de contacto" no existe en el HTML de la landing (la sección Contacto usa `mailto:`); el handler es código latente con guard `if (form)`. La referencia a `/api/contacto.php` está en un comentario.
- **`register_evento.php`** — referencia a `check_new_registrations.php` dentro de un bloque de comentario.
- **`services/phpmailer/PHPMailer.php`** — referencia interna de la librería (documentación).
- **`admin/acceso-admin.html`, `admin/admin-dashboard-directo.php`, `admin/init-banners.php`, `admin/verificar-admin.php`** — utilidades de debug legacy con links a páginas de diagnóstico que ya no existen (`debug-login.html`, `diagnostico-rutas.php`, `diagnostico-banners.php`). **Candidatos a eliminación en una limpieza futura** — no forman parte de ningún flujo activo.

---

## Hallazgos positivos (deuda ya saldada, actualiza ANALISIS_SISTEMA.md)

- La **triplicación de login/JWT/registro ya fue limpiada**: en `api/auth/` ya no existen `login.php`, `jwt_helper.php`, `jwt_helper_fixed.php`, `register_fixed.php`, `register_with_approval.php` ni el directorio `login/`. Queda una sola implementación (`login-compatible.php` + middleware).
- El **CORS ya tiene whitelist** (`middleware/cors.php` + origen fijo en `.htaccess`), sin `*` en producción.
- La exposición de `$_SESSION` en `login-compatible.php` está resuelta.

## Pendientes conocidos (fuera del alcance de esta ronda)

- CSP sin `unsafe-inline`/`unsafe-eval` (requiere refactor de HTML gigantes).
- Tests automatizados + CI, pipeline de build (minificación), respaldos automatizados de BD.
- Eliminar físicamente los archivos marcados como "candidato a eliminación" (hacerlo en una ronda dedicada, verificando producción).
- Páginas legales de la landing (términos y cookies propios).

---

## Archivos desplegados (2026-08-31)

Correcciones de esta ronda + pendientes del roadmap del CLAUDE.md (documentos, descuentos, comités, eventos, landing). Ver Fase 5 en CLAUDE.md.
