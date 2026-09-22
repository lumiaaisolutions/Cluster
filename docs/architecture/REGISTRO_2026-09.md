# Registro de usuarios — arquitectura (FEATURE-037, septiembre 2026)

**Estado: EN PRODUCCIÓN Y PROBADO** (registro real id 22 exitoso). Resumen en
`claude.md` (FEATURE-037).

## Flujo completo

1. **Formulario** (`pages/sign-up.html`): el usuario captura datos personales,
   perfil profesional, tipo de cuenta, empresa (si aplica) y ubicación.
2. **Envío** (`fetch` JSON a `api/auth/register.php`): valida, crea columnas
   faltantes con `ALTER TABLE` auto, verifica email único, hace el INSERT en
   `usuarios_perfil` con `estado_usuario='pendiente'`.
3. **Dos correos best-effort** tras el INSERT:
   - Verificación de cuenta al registrante (`sendAccountVerification`, token 24h
     en `email_tokens`).
   - Aviso a administradores con todos los datos (`sendNewRegistrationAlert`).
4. Respuesta: "PENDIENTE DE APROBACIÓN" — un admin lo aprueba en
   `gestionar_usuarios.php`.

## Campos del formulario

### Perfil profesional (nuevo, campo adicional)
Selector obligatorio, columna `perfil_profesional VARCHAR(30)`:
- `empresario_no_socio` → "Soy empresario (no socio)"
- `socio_cluster` → "Soy socio Clúster"
- `staff_cluster` → "Staff y personal Clúster"

Es **adicional** al "tipo de cuenta" (`rol`: empleado/empresa), que sigue
definiendo el flujo funcional (empresa requiere empresa_id, staff se
autoasigna a "Cluster Automotriz Metropolitano"). El perfil profesional es
para segmentar comunicación.

### Ubicación
- **País**: combo, 63 países, México preseleccionado. Si país ≠ México, los
  campos Estado/Municipio pasan a `<input>` de texto libre (vía
  `onPaisChange()` que alterna `disabled`).
- **Estado**: combo de los 32 estados MX (o texto libre fuera de MX).
- **Municipio**: combo poblado por el lookup de C.P., con opción "Otro" para
  captura manual.
- **Dirección**: texto libre (dirección postal de la empresa).
- **Código Postal**: 5 dígitos; al escribirlo, autollena Estado y Municipio.

#### Truco de FormData con campos duales
`estado` y `ciudad` tienen DOS elementos con el mismo `name` (un `<select>`
para México y un `<input>` de respaldo para otros países). Regla clave: los
controles `disabled` quedan **excluidos de FormData**, así que solo el activo
aporta valor. `onPaisChange()` mantiene exactamente uno enabled por par.

## `api/cp-lookup.php` — autollenado por código postal

`GET ?cp=NNNNN → { success, estado, municipio, fuente }`

Estrategia en cascada:
1. **Caché propia** (`cp_catalogo`, auto-creada): cada CP resuelto se guarda,
   el sistema construye su catálogo con el uso y deja de pegarle a servicios
   externos para CPs repetidos.
2. **Nominatim / OpenStreetMap** (sin token): estado + municipio. Su política
   pide máx 1 req/s — el volumen de un formulario + la caché lo respetan.
   Normaliza los `display_name` para extraer la alcaldía en CDMX (donde el
   `county` repite el estado).
3. **Zippopotam** (respaldo, solo estado).

COPOMEX se descartó: su token `pruebas` devuelve datos aleatorizados a
propósito. Si todo falla → `success:false` y el formulario cae a captura
manual (el registro nunca se bloquea por este servicio).

Normalización de nombres históricos: "Distrito Federal" → "Ciudad de México",
"Mexico"/"México" → "Estado de México".

## Correo a administradores — `EmailService::sendNewRegistrationAlert(array $datos)`

Envía a **dos** bandejas (`MAIL_ADMIN` = auxsistemas + `atencion@clautedomex.mx`)
un correo con todos los datos del registro renderizados en tabla HTML (sin la
contraseña). Basta con que una entrega tenga éxito. Best-effort: un fallo de
correo NO revierte el registro (que ya se completó).

## Migraciones automáticas

`register.php` crea con `ALTER TABLE ... ADD COLUMN IF` (patrón SHOW COLUMNS +
ALTER en try/catch) las columnas `estado_usuario` y `perfil_profesional` si no
existen — no requiere migración manual. Igual `cp-lookup.php` con `cp_catalogo`
(CREATE TABLE IF NOT EXISTS).

## Verificación de producción (2026-09-22)

- CP lookup: 54000→Tlalnepantla/EdoMex, 42000→Pachuca/Hidalgo; 2ª consulta
  responde `fuente:cache` (confirma escritura a BD en prod).
- Registro real de prueba: id 22, todos los campos, `success:true`,
  `estado_usuario:pendiente`. El INSERT de 22 columnas exitoso confirma que el
  ALTER de `perfil_profesional` corrió y todo guardó.
- Balance del INSERT: 22 columnas / 22 valores (21 placeholders + `activo`
  literal) / 21 binds.

## Pendiente del usuario

- Borrar el usuario de prueba **id 22** ("PRUEBA CLAUDE - BORRAR") desde
  Gestionar Usuarios (borrado de BD no lo puede hacer el asistente).
- Confirmar recepción de los 2 correos de admin de esa prueba.
- El nivel de acceso exacto que tendrá "empresario (no socio)" al ser aprobado
  quedó como campo de segmentación; si en el futuro debe tener permisos
  distintos a un socio, hay que mapearlo a un `rol` o flag de permisos.
