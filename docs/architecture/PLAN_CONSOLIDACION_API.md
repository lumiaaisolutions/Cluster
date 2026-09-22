# 📐 Plan de implementación — Consolidación de APIs duplicadas

> No ejecutado — es un plan para tu revisión, dado el riesgo de tocar APIs con consumidores reales en producción sin red de seguridad (sin tests, sin staging).

## Por qué no se hizo directo

Verifiqué antes de proponer cualquier borrado: `empresas-simple.php`/`empresas.php`, `boletines_simple.php`/`boletines.php`, `estadisticas_simple.php`/`estadisticas.php` y `empresas-convenio.php`/`empresas_convenio.php` tienen **consumidores reales en ambos lados**. Borrar cualquiera de los dos sin migrar antes a sus llamadores rompería una página en producción de forma inmediata.

## Principio del plan: migrar antes de borrar, nunca al revés

Para cada par, el orden correcto es siempre:
1. Elegir cuál de los dos es el canónico (el más completo/reciente, generalmente el que NO tiene sufijo `-simple`).
2. Auditar que el canónico soporte **todos** los parámetros/acciones que usan los consumidores del otro archivo.
3. Migrar cada consumidor uno por uno, verificando en el navegador tras cada cambio.
4. Solo cuando el archivo "viejo" tenga **cero** referencias, eliminarlo.
5. Nunca combinar pasos 3 y 4 en el mismo despliegue — cada migración de consumidor es su propio commit/deploy verificable.

## Caso por caso

### `empresas-simple.php` (canónico) vs `empresas.php`
- **Consumidores de `empresas-simple.php`** (5): `admin-empresas.js`, `empresas-visualizacion.js`, `empresas-convenio-mejorado.js`, `demo_empresas.html`, `dashboard.html`.
- **Consumidores de `empresas.php`** (2 reales + 1 código muerto): `gestionar_usuarios.php`, `demo_descuentos.html` (endpoints de fallback con múltiples URLs candidatas — revisar si de verdad depende de esta o de `empresas-simple.php`), y `js/dashboard-auth.js` que **ya fue eliminado por estar muerto** (Fase C) — su referencia ya no cuenta.
- **Pasos**:
  1. Comparar el JSON de respuesta de `empresas.php?destacadas=true` (usado por gestionar_usuarios.php) contra lo que devuelve `empresas-simple.php` — confirmar si `empresas-simple.php` ya soporta el parámetro `destacadas`.
  2. Si no lo soporta, agregarlo a `empresas-simple.php` (aditivo, sin riesgo para los consumidores existentes).
  3. Cambiar el fetch de `gestionar_usuarios.php` a `empresas-simple.php?destacadas=true`, verificar en el panel de usuarios.
  4. Repetir para `demo_descuentos.html`.
  5. Cuando `empresas.php` tenga 0 referencias reales, eliminarlo.
- **Esfuerzo estimado**: 1-2 horas con verificación manual.

### `boletines.php` (canónico, más completo por su nombre) vs `boletines_simple.php`
- Mismo patrón: identificar consumidores exactos de cada uno (pendiente de auditar con el mismo detalle que arriba — no se hizo en esta sesión), comparar payloads, migrar, verificar, borrar.
- **Esfuerzo estimado**: 1-2 horas.

### `estadisticas.php` vs `estadisticas_simple.php`
- Mismo patrón. Nota: `dashboard.html` ya fue corregido en una ronda anterior para usar `estadisticas_simple.php` (bug de guion vs guion bajo, ver `CORRECCIONES_2026-08.md`) — confirmar si ese es ahora el canónico de facto antes de decidir cuál eliminar.
- **Esfuerzo estimado**: 1 hora.

### ✅ `empresas-convenio.php` vs `empresas_convenio.php` — CONSOLIDADO (2026-09-22)
- **Hallazgo del audit**: NO eran duplicados literales — divergieron. El de guion bajo (`empresas_convenio.php`) es el canónico (6 consumidores: demo_descuentos, admin-panel, empresas-destacadas-index.js, empresas-destacadas-widget.js, empresas-convenio.js, sign-up.html) y devuelve `nombre_empresa`. El de guion (`empresas-convenio.php`) tenía UN solo consumidor: un fallback dentro de un `catch` en `gestionar_usuarios.php` (`cargarEmpresasSelect`) que leía `nombre`/`razon_social`.
- **Migración ejecutada**: el fallback ahora apunta al canónico `empresas_convenio.php` con lectura robusta (`nombre_empresa || nombre || razon_social || '(sin nombre)'`, cubre cualquier contrato). Con eso el archivo de guion quedó con CERO consumidores → borrado del repo y producción (404 confirmado; canónico sigue 200).
- **Nota de riesgo residual**: el fallback vive en un `catch` (solo se dispara si el fetch primario a `empresas.php` falla), difícil de gatillar en producción — pero la migración es estrictamente MÁS robusta que antes (lectura de campo multi-contrato + API canónica probada por 6 consumidores diarios), así que el cambio no puede empeorar el comportamiento.

### Conteos de consumidores frescos (2026-09-22) — pares restantes, TODOS con lado vivo
- `estadisticas.php` (1) vs `estadisticas_simple.php` (2)
- `boletines.php` (3) vs `boletines_simple.php` (1)
- `empresas.php` (5) vs `empresas-simple.php` (5) ← el más grande, 10 consumidores
Ninguno tiene un lado muerto — cada uno exige la migración consumidor-por-consumidor con verificación en navegador. NO intentarlos en bloque al cierre de sesión.

---

## ✅ Ejecución 2026-09-22 (sesión de cierre) — 3 de 4 pares consolidados

### ✅ `estadisticas.php` — ELIMINADO (canónico: `estadisticas_simple.php`)
- **Audit real**: los 2 "consumidores" de `estadisticas.php` (`dashboard.html` → `loadAppleStyleStats` línea 12943 y `loadClautStyleStats` línea 13171) son **código muerto**: `loadAppleStyleStats()` nunca se invoca (solo se define), y `loadClautStyleStats()` solo lo llama esa función muerta. El `#estadisticasContainer` real lo llenan otras funciones vivas que ya usan `estadisticas_simple.php`. Sin includes server-side.
- **Acción**: `estadisticas.php` tenía CERO consumidores vivos → borrado del repo (`git rm`) y de producción (FTP `DELE`). Verificado: `estadisticas.php` → 404, `estadisticas_simple.php?action=general` → 200.
- **Nota**: las 2 funciones muertas siguen en `dashboard.html` (nunca se ejecutan; tocar el monolito de 500 KB por código muerto inerte no vale el riesgo). Si algún día se "reviven", apuntan a un 404 — documentado aquí.

### ✅ `boletines.php` — ELIMINADO (canónico: `boletines_simple.php`)
- **Corrección al plan original**: el plan asumía "`boletines.php` canónico por su nombre". **Falso**: `boletines_simple.php` es el SUPERSET (CRUD completo: GET/POST-crear/POST-actualizar/DELETE con limpieza de archivos — el que resolvió BUG-014). `boletines.php` es un subconjunto (solo GET + POST-crear).
- **Los 3 consumidores de `boletines.php` son todos de solo-lectura (GET)**: `admin-panel.html` (lee `.success && .data`), `boletines-manager.js` (fallback de `apiBase`), `boletines-config.js` (default de config — **archivo que ninguna página carga**, `window.BoletinesConfig` nunca se define, así que el manager siempre usa su fallback).
- **Formas de respuesta verificadas idénticas para GET**: ambos emiten `{success:true, data:[...]}` en lista y single. La única diferencia es cosmética (`total` en top-level en `_simple` vs enterrado en `message` en `boletines.php` por un `ApiResponse::success($x, ['total'=>N])` que pasa el array como `$message` — nadie lo lee).
- **Acción**: migrados los 3 consumidores a `boletines_simple.php`, bumpeado `boletines-manager.js?v=2.1` → `?v=20260922a` en `boletines.html` (BUG-026). Con 0 referencias restantes, `boletines.php` borrado del repo y producción. Verificado: `boletines.php` → 404, `boletines_simple.php` → 200 devolviendo `{success:true, data:[...]}`, `boletines.html` sirve el nuevo `?v=`.

### ✅ `empresas.php` vs `empresas-simple.php` — CONSOLIDADO Y VERIFICADO (2026-09-22, con sesión admin real)
- **Ejecutado**: se migraron los 2 consumidores vivos a `empresas-simple.php?action=listar` con lectura robusta de forma (maneja `data:[]` plano Y `data:{empresas:[]}` anidado); se borraron `empresas.php` + los 3 scripts CRUD muertos (`crear/editar/eliminar-empresa.js`). Canónico: `empresas-simple.php` (privacidad por rol correcta).
  - `gestionar_usuarios.php`: fetch → `empresas-simple.php?action=listar`, reader robusto (`Array.isArray(result.data) ? result.data : result.data?.empresas`), campo `nombre || nombre_empresa`, catch→`empresas_convenio.php` conservado.
  - `demo_descuentos.html`: las 3 entradas muertas de `empresas.php` del array de endpoints → reemplazadas por `empresas-simple.php?action=listar` al frente; agregada rama de parser `else if (data.data?.empresas) empresasArray = data.data.empresas`.
- **Verificación end-to-end en producción con sesión admin real** (navegador, no curl): `check_me.php` → `is_admin:true`; `empresas-simple.php?action=listar` → 12 empresas; dropdown de Gestionar Usuarios → 13 opciones pobladas (ABB México, Abitat, Aeromexico Delta...); selector de Descuentos → 13 opciones, con logs de consola confirmando *"Endpoint exitoso: empresas-simple.php"* (primer endpoint, no fallback) y *"Usando data.data.empresas"* (la rama nueva se ejercitó). Post-borrado de `empresas.php`, ambos dropdowns siguen poblando 12 empresas.
- **Fuga de privacidad cerrada**: `empresas.php` (sin filtro de rol) ya no existe; el directorio pasa exclusivamente por `empresas-simple.php`, que filtra `autoriza_directorio` para no-admins.
- **Resultado**: los 4 pares duplicados consolidados. `empresas.php` → 404, `empresas-simple.php` → 200.

<details><summary>Audit original que reveló que NO era drop-in (histórico)</summary>

El conteo "5 vs 5, listo para ejecutar" **no capturó** los bloqueos reales. Audit profundo de esta sesión:
- **3 de los 5 consumidores de `empresas.php` son código muerto**: `js/crear-empresa.js`, `js/editar-empresa.js`, `js/eliminar-empresa.js` — NINGUNA página los carga (grep confirmó cero `<script src>`). Son el write-path CRUD, muerto.
- **Consumidores VIVOS de `empresas.php` = solo 2, ambos GET de solo-lectura**: `gestionar_usuarios.php:1380` (dropdown "asignar empresa a usuario") y `demo_descuentos.html:611-616` (selector de empresa para descuentos).
- **BLOQUEO 1 — formas de respuesta incompatibles**: `empresas.php` devuelve `{success, data:[array]}` (array plano); `empresas-simple.php?action=listar` devuelve `{success, data:{empresas:[array], total}}` (array **anidado** en `data.empresas`). El parser de `demo_descuentos.html` maneja `data.data`/`data`/`data.empresas`/`data.results` pero **NO** `data.data.empresas` → migración naïve dejaría el selector vacío. `gestionar_usuarios.php` lee `result.data.forEach` → también rompería con la forma anidada.
- **BLOQUEO 2 — verificación solo posible con sesión admin**: ambos consumidores son páginas de admin. `empresas-simple.php` aplica filtro de privacidad por rol (`$esAdmin` vía `SessionConfig::init()`): admin ve todo, no-admin ve solo `activo=1 AND (autoriza_directorio=1 OR NULL)`. Confirmar que los 2 dropdowns se pueblan correctamente exige una sesión admin autenticada en el navegador — esta sesión no puede loguearse (prohibido escribir contraseña).
- **HALLAZGO ADICIONAL (fuga de privacidad, reportar aparte)**: `empresas.php` NO aplica ningún filtro de privacidad — un `curl` **sin sesión** a `https://intranet.clautmetropolitano.mx/api/empresas.php` devuelve la lista completa de empresas activas con detalles (ABB México, etc.). `empresas-simple.php` fue escrito precisamente para corregir esto. Consolidar hacia `empresas-simple.php` **eliminaría la fuga** — razón extra para hacerlo, pero con verificación.
- **Receta segura para una sesión con navegador admin** (no ejecutar a ciegas):
  1. En `gestionar_usuarios.php`: cambiar el fetch a `empresas-simple.php?action=listar` y el reader a `const arr = Array.isArray(result.data) ? result.data : (result.data?.empresas || []); arr.forEach(...)` (robusto a ambas formas). Campo `empresa.nombre` ya lo devuelve `_simple`. Conservar el catch→`empresas_convenio.php`.
  2. En `demo_descuentos.html`: reemplazar las 3 entradas muertas de `empresas.php` en el array de endpoints por `empresas-simple.php?action=listar`, y agregar al parser una rama `else if (data.data?.empresas && Array.isArray(data.data.empresas)) empresasArray = data.data.empresas;`.
  3. **Verificar en navegador con sesión admin**: abrir `gestionar_usuarios.php` (dropdown "empresa" poblado) y `demo_descuentos.html` (selector de empresa poblado). Solo si ambos se pueblan → borrar `empresas.php` + los 3 scripts CRUD muertos (`crear/editar/eliminar-empresa.js`).
- **Esfuerzo**: 30-45 min, casi todo en verificación con sesión admin real.

*(Esta receta se ejecutó exactamente así el 2026-09-22 — ver el encabezado ✅ arriba.)*
</details>

## `api/registros_eventos.php` — desajuste de esquema, no duplicado

- **Situación**: tiene un consumidor real (`js/eventos.js`, cargado por `eventos.html`), pero su `INSERT`/`UPDATE` asume columnas (`estado_registro`, `user_id`, `apellido`, `notas_especiales`, `codigo_qr`) que no existen en la tabla real de producción (que usa `estado`, `usuario_id`, `comentarios`, `asistio`). Su `GET` funciona porque usa `SELECT r.*`.
- **Plan**:
  1. Confirmar con `SHOW COLUMNS FROM registros_eventos` en producción (ya lo hice una vez, documentado en `CORRECCIONES_2026-08.md`) que el esquema no cambió.
  2. Reescribir el `INSERT`/`UPDATE` de `registros_eventos.php` para usar los nombres de columna reales.
  3. Probar el flujo completo de registro a un evento desde `eventos.html` en producción (crear un registro de prueba, confirmar que aparece en la tabla, borrar el registro de prueba).
- **Esfuerzo estimado**: 30-45 minutos, la mayor parte en la verificación end-to-end.

## Orden sugerido de ejecución

1. `registros_eventos.php` primero (el más acotado, un solo archivo, bug claro).
2. `empresas-convenio.php`/`empresas_convenio.php` (probable duplicado literal, rápido de resolver).
3. `empresas-simple.php`/`empresas.php`.
4. `boletines.php`/`boletines_simple.php`.
5. `estadisticas.php`/`estadisticas_simple.php`.

Cada punto es una sesión de trabajo separada con su propio verify-antes-de-borrar — no se debe intentar los 5 casos en una sola sentada sin poder probar cada uno en el navegador entre paso y paso.
