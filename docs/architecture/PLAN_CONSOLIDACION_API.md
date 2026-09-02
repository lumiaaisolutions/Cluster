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

### `empresas-convenio.php` vs `empresas_convenio.php`
- El nombre sugiere que son copias casi idénticas (mismo código, un guion vs guion bajo) — de los 4 pares, este es el más probable candidato a ser un duplicado literal (no una API "simple" vs "completa"). Verificar con `diff` directo antes de asumir cuál conservar.
- **Esfuerzo estimado**: 30 minutos si son idénticos, 1 hora si divergieron.

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
