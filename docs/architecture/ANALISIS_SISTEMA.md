# 🔍 Análisis Completo del Sistema — Claut Intranet

> Fecha de análisis: 2026-03-07 | Rol: Senior Web Developer
> Directorio analizado: `build/` (excluidos backups)

---

## 🚨 1. VULNERABILIDADES DEL SISTEMA

### CRÍTICAS

| # | Archivo | Vulnerabilidad | Estado |
|---|---------|----------------|--------|
| 1 | `build/.env` | **Credenciales expuestas**: Si se sube a Git. | **[RESUELTO]** (Protegido en .gitignore) |
| 2 | `config/database.php` | **Credenciales hardcodeadas** como fallback. | **[RESUELTO]** (Migrado a .env) |
| 3 | `api/auth/jwt_helper_fixed.php` | **JWT secret hardcodeado**. | **[RESUELTO]** (Manejado en .env) |
| 4 | `api/auth/login.php` | **Fallback JWT secret hardcodeado**. | **[RESUELTO]** (Manejado en .env) |
| 5 | `api/auth/login-compatible.php` | **`$_SESSION` completo expuesto**. | **[EN PROCESO]** |
| 6 | `api/auth/login.php` | **Stack trace expuesto** en errores API. | **[RESUELTO]** (JSON estructurado limpio) |
| 7 | `.htaccess` | **CORS abierto a `*`**. | **[RECOMENDADO]** (Continuar monitorizando) |
| 8 | `api/auth/login-compatible.php` | **CORS dinámico sin validación**. | **[RECOMENDADO]** |
| 9 | `build/config/database.php` | **Permisos `0777` en SQLite**. | **[RESUELTO]** (Removido fallback local) |
| 10 | `.htaccess` | **Protección incompleta de archivos PHP**. | **[RESUELTO]** (Refactorizado en config/central) |

### ALTAS

| # | Archivo | Vulnerabilidad |
|---|---------|----------------|
| 11 | `api/auth/login-compatible.php` (L143) | **Línea `login_time` duplicada** (L141 y L143): asignación duplicada que sugiere código de debugging no limpiado. |
| 12 | `middleware/security-headers.php` | **CSP con `unsafe-inline` y `unsafe-eval`** en `script-src`: elimina completamente la protección contra XSS que CSP debería dar. |
| 13 | `.htaccess` (L43-44) | **Páginas `demo_*` sin autenticación**. **[RESUELTO 2026-08]** — servidas vía `auth-gate.php` con validación de sesión PHP (ver CORRECCIONES_2026-08.md). |
| 14 | `config/database.php` (L147) | **Datos de prueba en producción (`insertSampleData`)**: inserta usuarios y contraseñas demo en la BD cuando no hay datos. |
| 15 | `api/auth/jwt_helper_fixed.php` (L21) | **`FILTER_SANITIZE_STRING` deprecado en PHP 8.1+**: causa errores silenciosos en servidores PHP 8+. |

### MEDIAS

| # | Archivo | Vulnerabilidad |
|---|---------|----------------|
| 16 | `build/uploads/` | **Directorio de uploads sin validación visible** de tipos de archivo a nivel de `.htaccess`. |
| 17 | `api/auth/login-compatible.php` | **No tiene rate limiting**: a diferencia de `login.php`, este endpoint no tiene protección contra fuerza bruta. |
| 18 | `middleware/security-headers.php` | **`X-XSS-Protection: 1; mode=block`** está deprecado en navegadores modernos (Chrome lo eliminó). Puede causar problemas. |
| 19 | `.htaccess` | **Sin `HSTS` a nivel de servidor**: el HSTS solo se añade vía PHP condicionalmente, no en `.htaccess`. |

---

## 🛠️ 2. MEJORAS QUE PUEDE TENER EL SISTEMA

### Arquitectura

- [ ] **Eliminar la duplicación de APIs de login**: hay 3 implementaciones (`login.php`, `login-compatible.php`, `login/login.php`). Debe existir **una sola**.
- [ ] **Eliminar el fallback a SQLite en producción**: si la BD remota falla, el sistema se conecta a SQLite con datos de prueba. Esto es peligroso. Mejor fallar explícitamente.
- [ ] **Centralizar manejo de JWT**: hay 3 implementaciones JWT (`jwt_helper.php`, `jwt_helper_fixed.php`, `middleware/jwt-validator.php`). Debe haber **una sola fuente de verdad**.
- [ ] **Separar Frontend de Backend**: los archivos `.html` gigantescos (`dashboard.html` 437KB, `admin-panel.html` 149KB) mezclan lógica, presentación y llamadas API. Requieren refactor o migración a framework.
- [ ] **Implementar un router centralizado**: actualmente no existe routing — todos los archivos son endpoints directos.

### Seguridad

- [ ] Añadir **whitelist de orígenes CORS** y eliminar el `*`.
- [ ] Eliminar **debug info** de respuestas de error en producción.
- [ ] Eliminar **`debug_session`** del JSON de respuesta.
- [ ] Rotar el **JWT secret** y nunca hardcodearlo en código.
- [x] Implementar **autenticación en páginas demo** — hecho vía `auth-gate.php` (2026-08).
- [ ] Agregar **Content-Security-Policy** sin `unsafe-inline`/`unsafe-eval`.
- [ ] Cambiar **permisos de directorio SQLite** de `0777` a `0750` o `0700`.

### Calidad de código

- [ ] Eliminar **comentarios de debugging** (`// TRACE LOG INTENSO`, `// REMOVED after debugging`).
- [ ] Eliminar **código comentado** que ya no se usa (`session_write_close`, `session_start`, etc.).
- [ ] Reemplazar `FILTER_SANITIZE_STRING` (deprecado PHP 8.1) por `htmlspecialchars()`.
- [ ] Unificar la función `responderJSON` / `jsonResponse` — existen con diferentes firmas en distintos archivos.
- [x] **Eliminar JS duplicados y experimentales** del directorio `js/`.
- [x] **Eliminar carpetas de backup y scripts obsoletos** en la raíz (Limpieza de Marzo 2026). **[RESUELTO]**

### Rendimiento

- [ ] Los archivos HTML están **sin minificar y son enormes** (dashboard.html = 437KB, admin-panel.html = 149KB). Requieren build pipeline.
- [ ] El `tailwind.config.js` (34KB) sugiere que Tailwind se usa pero los archivos HTML pueden tener clases en exceso.
- [ ] Habilitar el **cache de sesiones PHP** correctamente.
- [ ] Revisar el **timeout de BD** en `database.php` (30 segundos es muy alto, 5-10 es más razonable).

---

## ✅ 3. ARCHIVOS CORRECTOS Y EN BUEN ESTADO

| Archivo | Estado | Razón |
|---------|--------|-------|
| `middleware/rate-limiter.php` | ✅ Correcto | Implementación completa con ventanas de tiempo, configuración centralizada |
| `middleware/csrf-protection.php` | ✅ Correcto | CSRF implementado correctamente |
| `middleware/jwt-validator.php` | ✅ Correcto | La implementación más completa y moderna de JWT |
| `middleware/api-validator.php` | ✅ Correcto | Validación de inputs centralizada |
| `utils/input-validator.php` | ✅ Correcto | Sanitización de inputs |
| `utils/file-upload-validator.php` | ✅ Correcto | Validación de archivos subidos |
| `utils/security-logger.php` | ✅ Correcto | Logging de seguridad centralizado |
| `utils/token-blacklist.php` | ✅ Correcto | Blacklist de tokens al hacer logout |
| `config/session-config.php` | ✅ Correcto | Configuración de sesiones centralizada |
| `config/env-loader.php` | ✅ Correcto | Carga segura de variables de entorno |
| `api/auth/login-compatible.php` | ⚠️ Funcional con bugs | Funciona pero tiene los issues de debug listados arriba |
| `.env.example` | ✅ Correcto | Buen ejemplo de configuración sin valores reales |
| `build/.gitignore` | ✅ Correcto | Excluye correctamente `.env` y archivos sensibles |
| `SECURITY_README.md` | ✅ Correcto | Documentación de seguridad útil |

---

## ❌ 4. ARCHIVOS QUE YA NO SIRVEN / OBSOLETOS

### PHP — Duplicados y reemplazados

| Archivo | Razón |
|---------|-------|
| `api/auth/jwt_helper.php` | Reemplazado por `jwt_helper_fixed.php` y `middleware/jwt-validator.php`. La versión original tiene menos validaciones. |
| `api/auth/jwt_helper_fixed.php` | A su vez reemplazado por `middleware/jwt-validator.php` que es la implementación más completa. Hay 3 helpers JWT en total. |
| `api/auth/login.php` | Duplicado funcional de `login-compatible.php`. Apunta a `assets/conexion/config.php` que no existe en `build/`. |
| `api/auth/register_fixed.php` | Versión parcheada de `register.php`. Solo debe existir una versión. |
| `api/auth/register_with_approval.php` | Tercera variante de registro. Solo debe existir una versión. |
| `api/empresas-convenio.php` | Duplicado de `api/empresas_convenio.php` (solo cambia el guion por guion bajo). Mismo código. |
| `api/empresas-simple.php` | Versión simplificada de `api/empresas.php`. Sugiere iteraciones sin limpieza. |
| `api/boletines_simple.php` | Versión simplificada de `api/boletines.php`. La versión completa lo reemplaza. |
| `api/estadisticas_simple.php` | Versión simplificada de `api/estadisticas.php`. |
| `api/estadisticas-opciones.php` | Parece un endpoint extra creado en iteraciones. Requiere verificación. |
| `login/login.php` | Tercera implementación de login (en directorio `login/`). |
| `login/register.php` | Cuarta implementación de registro. |

### JavaScript — Duplicados y experimentales

| Archivo | Razón |
|---------|-------|
| `js/dashboard-simple.js` | Versión reducida de `js/dashboard-dinamico.js`. |
| `js/empresas-simple-viewer.js` | Versión reducida de `js/empresas-visualizacion.js`. |
| `js/empresas-evervault.js` | Nombre sugiere experimento visual nunca integrado definitivamente. |
| `js/empresas-analisis.js` | Parece un archivo de análisis/debug, no un módulo de producción. |
| `js/gestor-datos-reales.js` | Nombre sugiere archivo temporal para "datos reales" vs datos de prueba. |
| `js/grafico-seccion-correcta.js` | Nombre sugiere parche temporal ("correcta" implica había una versión incorrecta). |
| `js/bulletin-database.js` | Funcionalidad que debería estar en el backend, no en el frontend JS. |
| `dashboard-slider-fix.js` | Fix temporal en raíz del build — debería integrado o eliminado. |
| `announcements-fix.js` | Fix temporal en raíz del build — debería integrado o eliminado. |
| `frontend-integration.js` | Archivo de integración temporal en raíz del build. |
| `anuncios-mejorados.js` | Versión mejorada en raíz sin estructura — duplicado de algún módulo en `js/`. |

### HTML — Archivos demo sin propósito en producción

| Archivo | Razón |
|---------|-------|
| `demo_boletines.html` | Sin autenticación, datos de demostración. No debe estar en producción. |
| `demo_comite.html` | Ídem. |
| `demo_descuentos.html` | Ídem. |
| `demo_documentos.html` | Ídem. |
| `demo_empresas.html` | Ídem (aunque puede tener funciones admin). |
| `demo_estadisticasdinamicas.html` | Ídem. |
| `demo_evento.html` | Ídem. |
| `demo_gestion_grafico.html` | Ídem. |
| `demo_visitante.html` | Ídem. |

### Archivos varios en raíz de build/

| Archivo | Razón |
|---------|-------|
| `fix-content-scaling.css` (raíz del proyecto) | CSS de fix en raíz del proyecto, no en `build/css/`. Parche sin integrar. |
| `styles.css` (raíz del proyecto) | CSS vacío (59 bytes) en raíz del proyecto. |
| `config/database.sqlite` | Archivo SQLite en `config/` — debería estar en `data/` o ser ignorado por Git. |

---

## 🔒 5. CÓDIGO HARDCODEADO ENCONTRADO

| Archivo | Línea | Valor hardcodeado |
|---------|-------|-------------------|
| `build/.env` | L14 | `DB_PASS=CLAUT@admin_fernando!7` (contraseña real de producción) |
| `build/.env` | L13 | `DB_USER=u695712029_claut_fer` (usuario real de BD) |
| `build/.env` | L12 | `DB_NAME=u695712029_claut_intranet` (nombre real de BD) |
| `build/.env` | L19 | `JWT_SECRET=claut_jwt_secret_key_2024_muy_segura_cambiar_en_produccion` (el comentario dice "cambiar" pero no se cambió) |
| `config/database.php` | L65 | `'u695712029_claut_fer'` como default de `DB_USER` |
| `config/database.php` | L66 | `'CLAUT@admin_fernando!7'` como default de `DB_PASS` |
| `config/database.php` | L66 | `'u695712029_claut_intranet'` como default de `DB_NAME` |
| `api/auth/login.php` | L148 | `'CLAUT_SECRET_KEY_2024_SECURE'` como fallback de JWT secret |
| `api/auth/login-compatible.php` | L231 | `'CLAUT_SECRET_KEY_2024_SECURE'` como fallback de JWT secret |
| `api/auth/jwt_helper_fixed.php` | L130 | `'claut_jwt_secret_key_2024_muy_segura_cambiar_en_produccion'` hardcodeado en `getJWTSecret()` |
| `api/auth/jwt_helper.php` | L72 | `'claut_jwt_secret_key_2024_muy_segura_cambiar_en_produccion'` como fallback |
| `config/database.php` | L374-393 | Usuarios de prueba con emails y contraseñas hardcodeadas (`admin123`, `empresa123`, `empleado123`) |
| `config/database.php` | L71 | Array de hosts hardcodeados: `['$dbHost', 'localhost', 'clautmetropolitano.mx']` |

---

## ⚠️ 6. CÓDIGO DEPRECADO ENCONTRADO

| Archivo | Línea | Código deprecado | Reemplazo PHP 8.x |
|---------|-------|------------------|-------------------|
| `api/auth/jwt_helper_fixed.php` | L21 | `FILTER_SANITIZE_STRING` | Usar `htmlspecialchars()` o `strip_tags()` |
| `middleware/security-headers.php` | L17 | `X-XSS-Protection: 1; mode=block` | Header eliminado en Chrome/Edge, puede causar errores |
| `.htaccess` | L87-88 | `Order allow,deny` / `Deny from all` | Sintaxis antigua (Apache 2.2). Usar `Require all denied` (Apache 2.4) |
| `api/auth/jwt_helper.php` | Completo | Sistema JWT manual sin manejo de excepciones | Reemplazar por la clase `JwtValidator` de `middleware/jwt-validator.php` |
| `config/database.php` | L131 | `mkdir($dir, 0777, true)` | Permisos `0777` inseguros, usar `0750` |
| `api/auth/login-compatible.php` | L147-153 | Comentarios de trace/debug masivos | Eliminar código comentado |

---

## 📊 Resumen Ejecutivo

| Categoría | Cantidad |
|-----------|----------|
| Vulnerabilidades críticas | 10 |
| Vulnerabilidades altas | 5 |
| Vulnerabilidades medias | 4 |
| Archivos PHP obsoletos/duplicados | 12 |
| Archivos JS obsoletos/duplicados | 11 |
| Archivos HTML demo (sin auth) | 9 |
| Valores hardcodeados | 13 |
| APIs con código deprecado | 6 |

> [!CAUTION]
> El problema más urgente es `login-compatible.php` (L38) que **expone `$_SESSION` completo en cada respuesta JSON**. Cualquier usuario puede ver los datos de sesión de otros si intercepta la respuesta.

> [!WARNING]
> Las credenciales de BD y el JWT secret están en `config/database.php` como valores por defecto de PHP. Aunque `.env` esté en Git, el código ya tiene las credenciales reales embebidas.

---

## 📈 Conclusión del Refactor (Marzo 2026)

Se ha logrado una limpieza masiva de la deuda técnica:
- **Centralización**: Se pasó de un sistema con credenciales en cada archivo a una única fuente de verdad (`.env`).
- **Estandarización**: Se eliminó código "JS simple" y "CSS inline" masivo (3.8k líneas solo en boletines).
- **Consistencia**: Las vistas principales ahora comparten el mismo set de clases CSS y diseño premium. La última actualización consistió en homologar estrictamente `contacto.html` utilizando la plantilla de diseño de `dashboard.html` (`claut-header`, `porsche-navbar` y `claut-bottom-nav`), asegurando que todos los menús y acciones de usuario sean idénticos across-the-board.
- **Modernización de Agenda (Marzo 2026)**: Se transformó el calendario del dashboard principal en un formato de **Agenda Corporativa (listMonth)**. Se consolidó la administración de eventos en `admin-panel.html` eliminando redundancias y activando el controlador especializado `admin-eventos.js`. Además, se eliminaron las secciones legadas de "Gestionar Empresas" y "Nuestros Boletines" del dashboard para una interfaz más centrada y profesional.
