# 🧠 Análisis Integral — Clúster Intranet (v2.3)

> **Propósito de este documento**: Servir como fuente única de verdad para rediseñar el frontend en **Next.js + Tailwind + Framer Motion + GSAP** preservando 100% la base de datos MySQL y la API PHP existentes.
>
> **Fecha de auditoría**: 2026-05-04
> **Stack actual**: Vanilla JS + Tailwind CDN + PHP 8.x + MySQL/MariaDB (Hostinger)
> **Stack objetivo**: Next.js 14 (App Router) + TypeScript + Tailwind + Framer Motion + GSAP ScrollTrigger + SVGator embeds

---

## 📑 Índice

1. [Visión General y Objetivo del Rediseño](#1-visión-general-y-objetivo-del-rediseño)
2. [Mapa de Módulos](#2-mapa-de-módulos)
3. [Esquema de Base de Datos (Inalterable)](#3-esquema-de-base-de-datos-inalterable)
4. [Inventario de APIs](#4-inventario-de-apis)
5. [Capa de Autenticación, Sesión y Seguridad](#5-capa-de-autenticación-sesión-y-seguridad)
6. [Roles del Sistema](#6-roles-del-sistema)
7. [Flujos de Trabajo por Botón / Acción](#7-flujos-de-trabajo-por-botón--acción)
8. [Reglas de Visibilidad del Directorio](#8-reglas-de-visibilidad-del-directorio)
9. [Notificaciones por Correo](#9-notificaciones-por-correo)
10. [Bugs históricos y reglas heredadas (BUG-006 a BUG-021)](#10-bugs-históricos-y-reglas-heredadas)
11. [Recomendaciones de mejora del flujo](#11-recomendaciones-de-mejora-del-flujo)

---

## 1. Visión General y Objetivo del Rediseño

**Plataforma**: Intranet del Clúster Metropolitano (asociación empresarial). Gestión de socios, eventos, comités, boletines, documentos, descuentos y un directorio empresarial filtrable.

### Pilares del rediseño (no negociables)
- **No tocar**: BD MySQL, endpoints PHP, estructura de sesiones (`$_SESSION`), middleware (CORS, CSRF, rate-limiter, JWT, security-headers).
- **Reemplazar**: TODA la capa visual (HTML+CSS+JS) por Next.js + componentes tipados.
- **Preservar**: cookies (`CLAUT_SESSION`), uso obligatorio de `credentials: 'include'`, headers CSRF.
- **Estética nueva**: estilo **Porsche / Apple** — light mode con grandes hero, scroll-driven animations, image sequence scrubbing, transiciones SPA, microinteracciones líquidas. NO minimalista plano: moderno, claro, premium.
- **Sin datos hardcodeados**: TODO configurable desde panel admin (textos, banners, secciones, colores corporativos opcionales).
- **Sin alertas nativas** (`alert()`, `confirm()`, `prompt()`): toda comunicación dentro del frame con toasts, modales custom y banners contextuales.

---

## 2. Mapa de Módulos

| Módulo | Vista actual | Vista objetivo (Next.js route) | API principal | Tabla(s) BD | Roles con acceso |
|--------|--------------|-------------------------------|---------------|-------------|------------------|
| **Login / Registro** | `index.php`, `auth-redirect.js` | `/login`, `/registro` | `/api/auth/login-compatible.php`, `/api/auth/register.php` | `usuarios_perfil`, `email_tokens` | Público |
| **Dashboard** | `dashboard.html` | `/dashboard` | `/api/admin/stats.php`, `/api/admin/recent-activity.php`, `/api/banners.php` | varias agregadas | Todos (vista por rol) |
| **Directorio empresas** | `empresas-convenio.html` | `/directorio` | `/api/empresas-simple.php` (lectura pública), `/api/empresas-convenio.php` (admin) | `empresas_convenio` | Todos los autenticados |
| **Admin empresas** | `demo_empresas.html` | `/admin/empresas` | `/api/empresas-convenio.php`, `/api/solicitudes_empresa.php` | `empresas_convenio`, `solicitudes_empresa` | admin, superadmin |
| **Mi empresa** (perfil socio) | `profile.html` (tab "Mi Empresa") | `/perfil/empresa` | `/api/perfil_empresa.php` | `empresas_convenio` (filtrado por `admin_usuario_id`) | empresa_socio |
| **Eventos** | `eventos.html`, `demo_evento.html` | `/eventos`, `/admin/eventos` | `/api/eventos.php`, `/api/eventos-all.php`, `/api/registros_eventos.php` | `eventos`, `registros_eventos` | Lectura: todos · Edición: admin |
| **Calendario** | `calendario.html` | `/calendario` | `/api/eventos.php` | `eventos` | Todos |
| **Comités** | `comites.html`, `demo_comite.html` | `/comites`, `/admin/comites` | `/api/comites.php` | `comites`, `comite_registros`, `mensajes_comites` | Lectura: todos · Edición: admin |
| **Boletines** | `boletines.html`, `demo_boletines.html` | `/boletines`, `/admin/boletines` | `/api/boletines.php`, `/api/boletines_simple.php`, `/api/boletines_archivos.php` | `boletines` | Lectura: todos · Edición: admin |
| **Documentos** | `demo_documentos.html` | `/documentos`, `/admin/documentos` | `/api/documentos.php` | `documentos` | Visibilidad por campo `visibilidad` |
| **Descuentos / Convenios** | `descuentos.html`, `demo_descuentos.html` | `/descuentos`, `/admin/descuentos` | `/api/descuentos.php` | `descuentos`, `descuentos_usos`, `empresas_convenio` | Lectura: todos · Edición: admin |
| **Estadísticas** | `demo_estadisticasdinamicas.html` | `/admin/estadisticas` | `/api/estadisticas.php`, `/api/estadisticas-config.php`, `/api/estadisticas-opciones.php` | `estadisticas_*` | admin, superadmin |
| **Gestión de usuarios** | `gestionar_usuarios.php`, `admin_usuarios.html` | `/admin/usuarios` | `/api/admin/users.php`, `/api/admin/manage_users.php`, `/api_notificaciones_perfil.php` | `usuarios_perfil`, `notificaciones_cambios_perfil` | admin, superadmin |
| **Visitantes** | `visitante.html`, `demo_visitante.html` | `/visitante` | `/api/visitante-config.php` | configuración pública | Público |
| **Mi Perfil** | `profile.html` | `/perfil` | `/api/profile.php`, `/api/auth/me.php`, `/api/get-avatar.php`, `/api/upload-image.php` | `usuarios_perfil` | Todos los autenticados |
| **Mensajes / Buzón** | `contacto.html` | `/buzon` | `/api/usuarios_mensajes.php`, `/api/mensajes_usuario.php` | `usuarios_mensajes`, `usuarios_mensajes_individuales` | Todos |
| **Notificaciones** | (componente global) | (componente global) | `/api/notificaciones.php`, `/api/notificaciones_eventos.php` | `notificaciones` | Todos |
| **Email config** | (en admin-panel) | `/admin/configuracion/email` | `/api/email-config.php` | `email_notification_config` | superadmin |
| **Banners (carrusel)** | (en dashboard) | `/admin/banners` | `/api/banners.php` | `banner_carrusel`, `banners` | superadmin |

---

## 3. Esquema de Base de Datos (Inalterable)

> 🔒 **NO se modifican estructura ni nombres**. Solo lectura/escritura vía API existente.
> Total: **21 tablas** (19 principales + 2 asociativas).

### 3.1 Usuarios y autenticación

#### `usuarios_perfil` (tabla activa principal)
```
id INT PK · empresa_id INT · nombre VARCHAR(100) · apellidos VARCHAR(100)
email VARCHAR(255) UNIQUE · password VARCHAR(255) · telefono VARCHAR(20)
fecha_nacimiento DATE · nombre_empresa VARCHAR(255)
rol ENUM('admin','empresa','empleado') DEFAULT 'empleado'
biografia TEXT · direccion VARCHAR(255) · ciudad VARCHAR(100)
estado VARCHAR(100) · codigo_postal VARCHAR(10) · pais VARCHAR(100)
telefono_emergencia VARCHAR(20) · contacto_emergencia VARCHAR(255)
cargo VARCHAR(100) · departamento VARCHAR(100)
estado_usuario ENUM('activo','pendiente','rechazado','lista_espera') DEFAULT 'pendiente'
email_verificado TINYINT(1) DEFAULT 0
created_at | fecha_ingreso TIMESTAMP · fecha_actualizacion TIMESTAMP
```

#### `usuarios` (legado — migrar lectura, no escribir)
Igual que arriba pero con `apellido` en singular y `rol ENUM('admin','empleado','moderador')`.

#### `email_tokens`
```
id PK · user_email · token VARCHAR(128) UNIQUE
tipo ENUM('password_reset','account_verify')
expires_at DATETIME · usado TINYINT · ip_origen · created_at
```

### 3.2 Empresas y directorio

#### `empresas_convenio` (corazón del directorio)
```
id PK · nombre / nombre_empresa · descripcion / beneficios TEXT
logo_url · logo_archivo · sitio_web
descuento · categoria · email · telefono · direccion
estado ENUM('activo','inactivo','pausado') · activo TINYINT · destacado TINYINT
fecha_convenio | fecha_inicio_convenio · fecha_vencimiento | fecha_fin_convenio
contacto_email · contacto_movil · contacto_cargo · creado_por

-- Migración 2026-04-12 (directorio):
entidad_federativa VARCHAR(100) · municipio VARCHAR(120)
certificaciones TEXT (coma-separadas) · exporta TINYINT(1)
redes_fb · redes_x · redes_linkedin · redes_instagram VARCHAR(255)
autoriza_directorio TINYINT(1) -- ★ casilla del usuario socio

-- Trazabilidad:
usuario_registro_nombre · usuario_registro_apellido
departamento · cargo

-- Convenio Clúster:
convenio_descripcion TEXT · vigencia_inicio DATE · vigencia_fin DATE
contacto_movil · contacto_cargo

fecha_creacion · fecha_actualizacion TIMESTAMP
admin_usuario_id INT -- vínculo a usuario empresa_socio dueño del registro
```

#### `solicitudes_empresa`
```
id PK · usuario_id FK→usuarios_perfil · empresa_id FK→empresas_convenio (nullable)
estado ENUM('pendiente','aprobado','rechazado')
nombre_empresa · descripcion · categoria
contacto_email · contacto_telefono
admin_id FK→usuarios_perfil · fecha_solicitud · fecha_respuesta · notas_admin
```

### 3.3 Eventos

#### `eventos`
```
id PK · titulo · descripcion TEXT
fecha_inicio DATETIME · fecha_fin DATETIME · ubicacion
tipo · imagen (URL o base64)
estado ENUM('programado','en_curso','finalizado','cancelado')
cupo_maximo / capacidad_maxima · registrados
fecha_creacion · fecha_actualizacion
```

#### `registros_eventos`
```
id PK · evento_id FK · user_id (nullable)
nombre · apellido · email · telefono · empresa · cargo
notas_especiales · codigo_qr UNIQUE
estado_registro ENUM('pendiente','aprobado') · fecha_registro
```

### 3.4 Comités

#### `comites`
```
id PK · nombre · descripcion · objetivo · imagen
periodicidad · miembros_activos · organizacion
coordinador_id FK→usuarios_perfil
estado ENUM('activo','inactivo')
tipo_registro · link_registro -- ★ link Google Forms
-- Recomendación nueva: link_whatsapp VARCHAR(500)
```

#### `comite_registros`
```
id PK · comite_id FK · nombre_empresa · nombre_usuario
email_contacto · telefono_contacto · cargo · departamento · comentarios
estado_registro ENUM('pendiente','aprobado','rechazado')
usuario_loggeado_id · usuario_loggeado_nombre · usuario_loggeado_email · usuario_loggeado_empresa
session_info JSON · ip_address · fecha_registro
```

#### `mensajes_comites`
```
id PK · comite_id FK · usuario_id · asunto · contenido
estado ENUM('enviado','leido','archivado')
fecha_envio · fecha_lectura
```

### 3.5 Contenido (boletines, documentos, descuentos)

#### `boletines`
```
id PK · titulo · contenido / descripcion TEXT
estado ENUM('borrador','publicado','archivado')
archivo_url / archivo_adjunto · fecha_publicacion
descargas · visualizaciones · autor_id
fecha_creacion · fecha_actualizacion
```

#### `documentos`
```
id PK · titulo · descripcion
archivo_nombre · archivo_ruta · tipo_archivo · tamaño_archivo
categoria DEFAULT 'general'
subido_por · fecha_subida
visibilidad ENUM('publico','privado','restringido')
descargas
```

#### `descuentos`
```
id PK · titulo · descripcion
empresa_oferente_id FK→empresas_convenio
codigo_descuento · porcentaje_descuento DECIMAL(5,2) · monto_descuento DECIMAL(10,2)
fecha_inicio · fecha_fin · usos_maximos · usos_actuales
estado ENUM('activo','inactivo','expirado')
accion_tipo ENUM('link','telefono','email','whatsapp','mapa','ninguno')
accion_valor · accion_etiqueta · fecha_creacion
```

#### `descuentos_usos`
```
id PK · descuento_id FK · usuario_id · fecha_uso
```

### 3.6 Comunicación

#### `notificaciones`
```
id PK · titulo · contenido
tipo ENUM('boletin','evento','documento','comite','general','sistema')
origen_id · activo · leido
dirigido_a ENUM('todos','admin','destinatario')
destinatario_email · destinatario_rol ENUM('admin','empleado','usuario','miembro_comite')
fecha_creacion
```

#### `usuarios_mensajes` + `usuarios_mensajes_individuales`
Buzón de mensajería interna. Master + tracking por destinatario.

#### `email_notification_config`
```
id PK · evento_tipo VARCHAR(50) UNIQUE
  -- valores: nuevo_evento, nuevo_descuento, nueva_empresa, mensaje_buzon
nombre · descripcion · activo TINYINT
dirigido_a ENUM('admin','todos','destinatario')
icono · updated_at
```

### 3.7 UI / Personalización

#### `banner_carrusel`
```
id PK · titulo · descripcion · imagen_url
posicion / orden · activo · fecha_inicio · fecha_fin
creado_por · fecha_creacion · fecha_actualizacion
```

---

## 4. Inventario de APIs

> Todas en `/api/`. Todas devuelven JSON `{success: bool, data?: any, message?: string}`.
> Todas requieren `credentials: 'include'` salvo lectura pública del directorio.

### 4.1 Auth (`/api/auth/`)

| Endpoint | Método | Acción |
|----------|--------|--------|
| `login-compatible.php` | POST `?action=login` | Login (email + password) |
| `login-compatible.php` | GET `?action=check` | Verifica sesión activa |
| `login-compatible.php` | POST `?action=logout` | Logout alternativo |
| `register.php` | POST | Crea usuario `pendiente` + token email |
| `verify.php`, `verify-account.php` | POST | Activa cuenta con token |
| `forgot-password.php` | POST | Envía link reset |
| `reset-password.php` | POST | Cambia password con token |
| `logout.php` | POST | Destruye sesión + revoca JWT |
| `validate-session.php` | GET | Datos del usuario actual |
| `session.php`, `me.php` | GET | Idem (legacy) |
| `profile.php` | GET / PUT | Perfil del usuario |
| `notify-approval.php` | POST | Admin aprueba/rechaza usuario pendiente |

### 4.2 Admin (`/api/admin/`)

| Endpoint | Método | Acción |
|----------|--------|--------|
| `users.php` | GET/POST/PUT/DELETE | CRUD usuarios |
| `manage_users.php` | POST/DELETE | Aprobar/rechazar |
| `stats.php` | GET | Métricas globales |
| `recent-activity.php` | GET | Auditoría actividades |

### 4.3 Datos core

| Endpoint | Métodos | Notas |
|----------|---------|-------|
| `eventos.php` | GET/POST/PUT/DELETE | Usa `fastcgi_finish_request()` para email asíncrono (BUG-019) |
| `eventos-all.php` | GET | Listado público |
| `boletines.php` / `boletines_simple.php` | GET/POST/DELETE | **POST hace create+update** (BUG-014: PUT con FormData no funciona) |
| `boletines_archivos.php` | GET/POST | Archivo adjunto |
| `comites.php` | GET/POST/PUT/DELETE | CRUD + registro Google Forms + mensajes |
| `descuentos.php` | GET/POST/PUT/DELETE | Tracking en `descuentos_usos` |
| `documentos.php` | GET/POST/DELETE | **No usar ApiValidator** con archivos (BUG-015) |
| `empresas-convenio.php` | GET/POST/PUT/DELETE | Admin completo |
| `empresas-simple.php` | GET | Lectura pública filtrada por `autoriza_directorio` |
| `empresas_convenio.php` | helpers | (notar guion bajo, distinto archivo) |
| `perfil_empresa.php` | GET/PUT | Edición desde "Mi Empresa" del socio |
| `solicitudes_empresa.php` | GET/POST/PUT | Solicitudes de cambio |
| `notificaciones.php` | GET/POST | Notificaciones in-app |
| `notificaciones_eventos.php` | GET | Próximos eventos |
| `usuarios_mensajes.php`, `mensajes_usuario.php` | GET/POST | Buzón |
| `estadisticas*.php` | GET/PUT | Datos para gráficos dinámicos |
| `email-config.php` | GET/PUT/POST(test) | Toggle de notificaciones por correo |
| `banners.php` | GET/POST/DELETE | Carrusel home |
| `restricciones.php` | GET | Permisos finos |
| `get-avatar.php` | GET | Imagen avatar |
| `upload-image.php` | POST | Carga genérica |
| `visitante-config.php` | GET | Config landing público |
| `check_me.php` | GET | Health check sesión |

### 4.4 Otros (en raíz `/build/`)

- `api_notificaciones_perfil.php` — Listar/aprobar/rechazar solicitudes de cambio (BUG-013).
- `register_evento.php`, `get_eventos.php`, `evento_detalle.php` — Endpoints legados.

---

## 5. Capa de Autenticación, Sesión y Seguridad

### 5.1 Login flow
1. `POST /api/auth/login-compatible.php?action=login` con `{email, password}`.
2. PHP valida `password_verify()` y `estado_usuario='activo'` (admins exentos).
3. Crea `$_SESSION` y opcionalmente JWT (15 min access + 7 días refresh).
4. Cookie de sesión: `CLAUT_SESSION` · `httpOnly` · `secure` · `SameSite=Lax`.
5. Frontend llama `GET ?action=check` para hidratar `userData`.

### 5.2 Datos en sesión
```
$_SESSION = {
  user_email, user_nombre, user_nombre_empresa,
  user_rol, login_time, last_activity,
  created_at, last_regeneration
}
```

### 5.3 Timeouts
- `SESSION_LIFETIME`: 1 hora
- `INACTIVITY_TIMEOUT`: 30 min
- Regeneración cada 30 min (anti session fixation)

### 5.4 Middleware (`/build/middleware/`)
- **CORS** (`cors.php`): whitelist `https://intranet.clautmetropolitano.mx` + `Access-Control-Allow-Credentials: true`.
- **CSRF** (`csrf-protection.php`): token en sesión + header `X-CSRF-TOKEN` para POST/PUT/DELETE/PATCH.
- **Rate limiter** (`rate-limiter.php`): archivos JSON en `/storage/rate-limit/`.
  - Login: 5/5min · Register: 3/h · Reset: 3/h · API público: 100/min · Privado: 300/min.
- **JWT** (`jwt-validator.php`): HS256, blacklist en logout, `hasPermission()` opcional.
- **Headers** (`security-headers.php`): X-Frame-Options DENY, CSP, HSTS, Permissions-Policy.
- **API validator** (`api-validator.php`): NO usar con `$_FILES` (BUG-015).

### 5.5 Reglas críticas para Next.js
1. `fetch(url, { credentials: 'include' })` SIEMPRE.
2. Header `X-CSRF-TOKEN` en mutaciones (obtener vía endpoint custom o meta).
3. NO usar `localStorage` para datos sensibles (rol, email).
4. Hostname en detección de entorno: `clautmetropolitano.mx` (sin tilde, BUG-011).
5. CORS: en dev, exponer Next.js como `http://localhost:3000` y agregar a whitelist temporal del CORS PHP, o usar proxy de Next (`rewrites`).

---

## 6. Roles del Sistema

### 6.1 Roles actuales (BD)
- `admin` (también aceptado: `Administrador`, `root` — normalizar a `lower()`).
- `empresa` — empresa socio con un usuario administrador asociado.
- `empleado` — usuario base.

### 6.2 Roles objetivo (rediseño)
| Rol nuevo | Mapping a BD | Permisos |
|-----------|--------------|----------|
| **superadmin** | `usuarios_perfil.rol = 'admin'` + flag `superadmin=1` (nueva columna sugerida o flag en `restricciones`) | Todo. Configura email, roles, banners, plantillas, BD. |
| **admin** | `rol = 'admin'` | CRUD completo de eventos, comités, boletines, documentos, descuentos, empresas, usuarios. NO toca configuración de sistema. |
| **empleado_cluster** | `rol = 'empleado'` con departamento ≠ NULL | Lectura todo. Edición asignada por área (modula via `restricciones`). |
| **empresa_socio** | `rol = 'empresa'` + `empresa_id` ligado | Edita SOLO su propio registro de empresa (`admin_usuario_id`). Decide qué mostrar en directorio. |

> 📌 **Implementación recomendada**: añadir `permissions JSON` en `usuarios_perfil` o crear tabla `usuario_permisos (user_id, permission_key, granted)`. Sin tocar la BD existente, se puede usar `restricciones.php` ya disponible.

---

## 7. Flujos de Trabajo por Botón / Acción

### 7.1 Empresas / Directorio (CRÍTICO para el rediseño)

**Pantalla "Admin Empresas" (`demo_empresas.html`)**

| Botón | Llama a | Efecto BD |
|-------|---------|-----------|
| "Nueva empresa" | `POST /api/empresas-convenio.php` | INSERT en `empresas_convenio` |
| "Editar" | `PUT /api/empresas-convenio.php` con id | UPDATE |
| "Eliminar" | `DELETE /api/empresas-convenio.php?id=` | DELETE (o soft delete `activo=0`) |
| "Ver solicitudes" | `GET /api/solicitudes_empresa.php` (con `credentials: 'include'`, BUG-012) | SELECT `solicitudes_empresa` |
| "Aprobar solicitud" | `PUT /api/solicitudes_empresa.php` | UPDATE estado='aprobado' + aplica cambios a `empresas_convenio` |
| "Rechazar" | `PUT /api/solicitudes_empresa.php` | UPDATE estado='rechazado' + notas |
| "Asignar usuario" | `PUT /api/empresas-convenio.php` | actualiza `admin_usuario_id` |

**Pantalla "Mi Empresa" (perfil del socio)**
- El socio (`rol='empresa'`) edita SOLO su empresa filtrada por `admin_usuario_id = session.user_id`.
- Cada cambio en campos sensibles → crea entrada en `solicitudes_empresa` con `estado='pendiente'`.
- Admin recibe notificación → aprueba/rechaza desde panel.
- Casilla "Autorizo aparecer en directorio" → bit `autoriza_directorio` (sólo si =1, sale en `/directorio`).

### 7.2 Eventos
| Botón | Endpoint | Notas |
|-------|----------|-------|
| "Crear evento" | POST `/api/eventos.php` | `isSubmitting` flag (BUG-019), envío email asíncrono |
| "Editar" | PUT `/api/eventos.php` | |
| "Eliminar" | DELETE | manipulación DOM directa (BUG-020) |
| "Registrarme" | POST `/api/registros_eventos.php` | genera `codigo_qr` UNIQUE |
| "Aprobar registro" | PUT `/api/registros_eventos.php` | admin |
| "Cancelar evento" | PUT `/api/eventos.php` | `estado='cancelado'` |
| "Ver detalle / lista de registros" | GET con `?id=` | |
| Cache busting | `?t=Date.now()` | (BUG-020) |

### 7.3 Comités
| Botón | Endpoint | Notas |
|-------|----------|-------|
| "Crear comité" | POST `/api/comites.php` | `window.nuevoComite()` (BUG-017) |
| "Editar" | PUT con id | |
| "Registrarme" | POST `comite_registros` | redirige a Google Forms si `tipo_registro='link'` |
| "Unirme a WhatsApp" | abre `link_whatsapp` (sugerido nuevo campo) | |
| "Mensajes del comité" | GET/POST `mensajes_comites` | |

### 7.4 Boletines
- Crear/editar SIEMPRE con `method: 'POST'` y FormData (BUG-014). El `case 'POST'` distingue create vs update por `id`.
- Adjuntos: `$_FILES` solo en POST.
- Eliminar: DELETE acepta id por query string o JSON body, hace `unlink()`.
- Stat counter: `if (el) el.textContent = …` (null check, BUG-014).

### 7.5 Documentos
- POST con FormData (sin ApiValidator, BUG-015).
- Validar `empty($titulo)` directamente.
- Errores formatear con campo `message` (no `error`).

### 7.6 Descuentos
- `<select empresas>` debe preservar `nombre` Y `nombre_empresa` (BUG-016).
- Botón Guardar `type="button"` con `onclick`, NO `submit` event (BUG-016).

### 7.7 Login / Logout / Sesión
- Login: POST credenciales → cookie + JWT.
- Verificar sesión al montar app: `GET ?action=check`.
- Auto-logout por inactividad 30min (timer en cliente + verificación servidor).

---

## 8. Reglas de Visibilidad del Directorio

**Casilla `autoriza_directorio`** define la visibilidad pública. Cuando = 1:

### Campos visibles en `/directorio` (público a socios autenticados)
- Nombre de la empresa ✓
- Sector / categoria ✓
- Estado (`entidad_federativa`) ✓
- Municipio ✓
- Descripción de productos y servicios ✓
- Certificaciones (lista) ✓
- Exporta (Sí/No) ✓
- Redes sociales (FB, X, LinkedIn, Instagram) ✓
- Logo (archivo o URL) ✓
- Persona de contacto (nombre + cargo + teléfono + email) ✓
- Sitio web ✓

### Campos NO públicos (privados al admin y al socio)
- Email general · Teléfono general · Dirección física exacta
- Departamento del usuario
- Datos de login (password, etc.)

### Convenios Clúster (sub-sección opcional)
Si la empresa decide ofrecer descuentos:
- Descripción del descuento
- % de descuento
- Vigencia (inicio – fin)
- Términos y condiciones
- Contacto convenio (móvil + correo)

→ Se muestra en `/descuentos` automáticamente cuando `vigencia_inicio <= hoy <= vigencia_fin`.

### Filtros del directorio (UI nueva)
- Por sector / categoría
- Por estado / municipio
- Solo exportadores
- Con descuento Clúster activo
- Con certificaciones (multi-select)
- Búsqueda full-text (nombre + descripción)

---

## 9. Notificaciones por Correo

Configurables vía `/api/email-config.php` (tabla `email_notification_config`).

| Evento | Destinatario | Trigger |
|--------|--------------|---------|
| `nuevo_evento` | todos | POST eventos.php |
| `nuevo_descuento` | todos | POST descuentos.php |
| `nueva_empresa` | admin | POST solicitudes_empresa.php |
| `mensaje_buzon` | destinatario | POST usuarios_mensajes.php |
| Recuperación contraseña | usuario | forgot-password.php |
| Verificación cuenta | usuario | register.php |
| Aprobación/rechazo | usuario | notify-approval.php |

> Email actualmente: PHPMailer en `/build/services/phpmailer/` (sin Composer).
> SMTP via `.env`: `MAIL_HOST`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM`, `MAIL_PORT`, `MAIL_ENCRYPTION`.
> **Pendiente**: confirmar correo del dominio para SMTP en producción (mencionado en historial).

---

## 10. Bugs históricos y reglas heredadas

> Todas estas reglas DEBEN respetarse en el rediseño Next.js.

| ID | Regla |
|----|-------|
| BUG-006 | Variables de respuesta de fetch deben coincidir (`resultData` vs `result`). |
| BUG-007 | Lógica toggle explícita para sidebar móvil. |
| BUG-008 | Validar contención flexbox: nunca dejar `</div>` huérfanos. |
| BUG-009 | `.tab-pane{display:none}` y `.active{display:block}` explícitos. |
| BUG-010 | NUNCA cargar scripts Argon Dashboard. Solo `claut-core.min.js`. |
| BUG-011 | Hostname: `clautmetropolitano.mx` (sin tilde). |
| BUG-012 | Fetches autenticados → `credentials: 'include'`. |
| BUG-013 | Endpoint debe existir en producción al desplegar feature. |
| BUG-014 | `FormData` requiere POST en PHP (PUT no popula `$_POST`/`$_FILES`). |
| BUG-015 | NO usar `ApiValidator` en endpoints con `$_FILES`. Errores en campo `message`. |
| BUG-016 | Un solo punto de entrada por acción (botón `type="button"` o submit handler, no ambos). |
| BUG-017 | Todo CRUD necesita botón "Crear" visible en barra de controles. |
| BUG-018 | URLs opcionales no deben romper modal de detalle. |
| BUG-019 | Procesos pesados (email) tras `fastcgi_finish_request()`. Frontend bloquea botón con `isSubmitting`. |
| BUG-020 | Cache busting con `?t=Date.now()` en GETs frecuentes. Manipulación DOM directa para feedback. |
| BUG-021 | Cualquier calendario custom necesita controles `prev`/`next`/`today` y callback `datesSet`. |
| Dashboard responsive | NO usar `100vw`, usar `width: 100%`. Eliminar `max-width` estáticos en >1441px. Layouts fluidos al 95% en pantallas anchas. |

---

## 11. Recomendaciones de mejora del flujo

### 11.1 Mantener (funciona bien)
- Modelo de **solicitudes de cambio** (`solicitudes_empresa` + `notificaciones_cambios_perfil`) — flujo socio→admin→aprobación. Pulido por bugs anteriores.
- Casilla `autoriza_directorio` como kill-switch privacy.
- `email_notification_config` como matriz toggleable. Excelente para superadmin.
- `fastcgi_finish_request()` para responder antes del envío de correo.
- Migraciones idempotentes (`IF NOT EXISTS`).

### 11.2 Mejorar
1. **Unificar `usuarios` vs `usuarios_perfil`** — hoy hay dos tablas. Usar SOLO `usuarios_perfil`. Marcar `usuarios` como legado readonly.
2. **Tabla de permisos finos** — `usuario_permisos (user_id, modulo, can_view, can_edit, can_delete)`. Reemplaza el muchos-`if` actual.
3. **Auditoría centralizada** — `audit_log (user_id, action, table, record_id, before, after, ip, ts)`. Hoy está disperso.
4. **Webhook/cola para emails** — actualmente síncrono+`fastcgi_finish_request`. A futuro, cola Redis o tabla `email_queue`.
5. **Soft delete uniforme** — añadir `deleted_at` a empresas, eventos, comités, boletines, documentos. Hoy mezcla `activo=0` con DELETE físico.
6. **Tags / categorías como tabla** — hoy son ENUM o strings libres. Crear `tags` + `entity_tags` para reuso entre eventos/boletines/documentos.
7. **Sesiones server-side en Next.js** — usar `getServerSession()` o middleware Next.js que llame a `?action=check` y cachee 60s.
8. **Internacionalización (i18n)** — todos los strings parametrizados (TODO configurable, no hardcoded).
9. **Tema corporativo desde admin** — color primario, logo, tipografía configurables (variables CSS hidratadas en `<html style="--brand: …">`). Hoy `#C7252B` está hardcoded en CSS.
10. **API REST consistente** — algunas rutas son `kebab-case`, otras `snake_case`. Estandarizar (no urgente, pero útil con tipado TS).
11. **Endpoint público `GET /api/csrf-token`** — el frontend Next.js necesita pedir el token explícitamente al montar.
12. **Imagen sequence frames** — generar set de PNG/WebP secuenciales optimizados (servir desde `/build/uploads/sequences/<scene>/<n>.webp`) para scroll scrubbing tipo Apple/Porsche.

### 11.3 Nuevos campos sugeridos (sin migración disruptiva)
| Tabla | Campo | Propósito |
|-------|-------|-----------|
| `comites` | `link_whatsapp VARCHAR(500)` | Grupo WhatsApp del comité (mencionado en historial) |
| `comites` | `link_google_form VARCHAR(500)` | Forms de registro (alternativo a `link_registro` actual) |
| `usuarios_perfil` | `permissions JSON` | Permisos por módulo |
| `empresas_convenio` | `terminos_convenio TEXT` | Términos y condiciones del descuento |
| `eventos` | `beneficio_cluster TINYINT, beneficio_descripcion TEXT, beneficio_contacto VARCHAR(255)` | Solicitado en historial |
| `eventos` | `lat DECIMAL(10,7), lng DECIMAL(10,7)` | Para Google Maps embed |
| (nueva) `theme_config` | `key, value, updated_by, updated_at` | Personalización superadmin |

---

## 12. Resumen ejecutivo

- **51 endpoints API** distribuidos en 14 grupos funcionales.
- **21 tablas** MySQL + 3 migraciones aplicadas (email_tokens, email_notification_config, empresas_convenio_directorio).
- **18 vistas HTML** principales a migrar a Next.js routes.
- **30 archivos JS** en `/build/js/` a reemplazar con componentes React/TS.
- **6 middlewares PHP** que SIGUEN intactos (CORS, CSRF, rate, JWT, headers, validator).
- **4 roles** objetivo: `superadmin`, `admin`, `empleado_cluster`, `empresa_socio`.
- **0 alertas nativas** en el rediseño. **0 datos hardcoded**. Todo configurable.

---

> Próximo documento: `CUESTIONARIO_DISENO.md` → preguntas para definir el rediseño visual.
> Último documento: `PROMPT_FINAL.md` → el prompt para Claude/Cursor que recreará el sistema con el nuevo diseño.
