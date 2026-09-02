# 📋 Backlog post-rediseño — Fases B-E (2026-08-31)

> Continuación del backlog identificado en el gap-analysis de la sesión. Documentado por fases para no perder el hilo, tal como se pidió. Rotar la contraseña FTP queda a cargo del usuario.

## Fase B — CSP: hallazgo real y qué se hizo

**Hallazgo antes de tocar nada**: la CSP débil (`unsafe-inline`/`unsafe-eval`) en `middleware/security-headers.php` **solo la usa un archivo** (`api/upload-image.php`). El `.htaccess` no enviaba ninguna CSP. Es decir, el problema real no era "la CSP es débil" — era que **el 99% del sitio no tenía CSP en absoluto**.

**Qué se hizo**: se agregó una CSP global vía `.htaccess` (`Header always set Content-Security-Policy`), aplicada a todo el sitio por primera vez. Se mantuvieron `unsafe-inline`/`unsafe-eval` en `script-src`/`style-src` porque casi todos los HTML del sitio usan `<script>` inline y atributos `onclick=""` extensivamente (el propio `dashboard.html` tiene miles de líneas de JS inline) — quitarlos requiere externalizar ese código en decenas de archivos grandes sin suite de pruebas que detecte regresiones, un riesgo real de romper producción a ciegas. Se endureció lo que sí era seguro sin tocar código: `img-src` ya no acepta cualquier origen `https:` (antes `img-src 'self' data: https: blob:`, ahora restringido a los CDNs realmente usados), y se agregó `object-src 'none'`.

**Verificado**: CSP header confirmado en respuesta HTTP (`curl -I`), sin errores de consola ni recursos rotos en dashboard/sign-in tras el cambio.

**Pendiente real (no resuelto, correctamente fuera de alcance de un parche)**: eliminar `unsafe-inline`/`unsafe-eval` requiere:
1. Mover todo el JS inline de los HTML grandes (dashboard.html, admin-panel.html, etc.) a archivos `.js` externos.
2. Reemplazar atributos `onclick=""` por `addEventListener` en JS externo.
3. Para el CSS inline (`style=""` y `<style>` embebido en cada página), usar nonces por request o mover todo a archivos externos.
4. Verificar cada página manualmente tras el cambio (sin CI, es la única forma disponible).

Es un proyecto de varios días, no una tarea de esta sesión. Recomendación: abordarlo módulo por módulo (empezar por las páginas más pequeñas) en una sesión dedicada, no en un solo intento sobre todo el sitio.

## Fase C — Duplicados y código muerto (hecho, con hallazgo importante)

**Verifiqué cada candidato antes de borrar nada** — y el resultado contradijo varias suposiciones de la ronda anterior:

| Archivo | Suposición previa | Realidad verificada | Acción |
|---|---|---|---|
| `js/dashboard-auth.js` | Sin consumidores | **Confirmado**: 0 páginas lo cargan | 🗑️ Eliminado |
| `components/menu-components.html` | Sin consumidores | **Confirmado**: 0 referencias | 🗑️ Eliminado |
| `pages/users.php` | Sin consumidores | **Confirmado**: 0 referencias reales | 🗑️ Eliminado |
| `api/registros_eventos.php` | Sin consumidores, candidato a eliminar | **FALSO** — `js/eventos.js` (cargado por `eventos.html`) sí lo consume | ❌ **NO se eliminó** |
| `api/empresas-simple.php` vs `api/empresas.php` | Uno es duplicado del otro | Ambos tienen consumidores reales y distintos (empresas-simple: admin-empresas.js, empresas-visualizacion.js, dashboard.html, demo_empresas.html — 5 sitios; empresas.php: gestionar_usuarios.php, demo_descuentos.html — 2 sitios) | ❌ **NO se eliminó ninguno** |
| `boletines_simple.php`/`boletines.php`, `estadisticas_simple.php`/`estadisticas.php`, `empresas-convenio.php`/`empresas_convenio.php` | Duplicados seguros de borrar | Mismo patrón: ambos lados de cada par tienen consumidores activos | ❌ **NO se eliminó ninguno** |

**Por qué no se tocaron los duplicados de API**: consolidarlos exige primero migrar TODOS los consumidores a un solo archivo canónico y solo entonces borrar el otro — es un refactor con pasos ordenados, no un borrado directo. Intentarlo a ciegas hoy habría roto páginas en producción sin forma de detectarlo (sin suite de pruebas). Queda documentado como trabajo futuro, no como "resuelto".

**Verificado tras el borrado**: dashboard.html/profile.html/eventos.html responden 200; los 3 archivos borrados devuelven 404 correctamente.

## Fase D — Infraestructura de calidad

### CI básico — creado, pendiente de tu confirmación para subir
El repo sí tiene remoto en GitHub (`LUMIA-AI-SOLUTIONS/Cluster_Intranet`). Creé `.github/workflows/smoke-tests.yml` con dos jobs:
1. **Lint de PHP**: corre `php -l` sobre todos los `.php` de `build/` en cada push/PR a `main` — atrapa errores de sintaxis antes de que lleguen a producción (el tipo de error que hoy solo se detecta subiendo por FTP y probando a mano).
2. **Chequeo de enlaces rotos**: reutiliza la misma lógica de detección que usé manualmente hoy para encontrar los 62 links rotos — corre como advertencia informativa (no bloquea el build, para evitar falsos positivos ya conocidos en archivos JS).

**No lo subí a GitHub** — crear/modificar pipelines de CI y hacer push son acciones que prefiero confirmar contigo antes de ejecutar, ya que son visibles para cualquiera con acceso al repo. El archivo está listo en `.github/workflows/smoke-tests.yml`; dime si lo confirmo con un commit y push.

### Respaldo automatizado de BD — no se pudo configurar, requiere tu acceso
Esta sesión solo tiene credenciales FTP, no acceso al panel de Hostinger (hPanel) donde se configuran cron jobs. No puedo configurar un respaldo automatizado sin eso. **Procedimiento recomendado** para que lo hagas tú (o me des acceso a hPanel):
1. En hPanel → Bases de datos → Copias de seguridad, Hostinger ya ofrece snapshots automáticos diarios en la mayoría de planes — verifica si ya están activos.
2. Como respaldo adicional, en hPanel → Avanzado → Cron Jobs, crear una tarea diaria: `mysqldump -u USUARIO -pCONTRASEÑA NOMBRE_BD | gzip > /home/USUARIO/backups/bd_$(date +\%Y\%m\%d).sql.gz`, con un segundo cron que borre respaldos de más de 30 días.
3. Alternativa sin cron: activar el respaldo automático de Hostinger a Google Drive/Dropbox si el plan lo incluye.

## Fase E — Páginas legales (hecho) + sistema de diseño (parcial, honesto)

### Páginas legales — hecho y desplegado
- `landing/terminos.html` y `landing/cookies.html`, con el mismo lenguaje visual oscuro del resto de la landing (`legal.css` nuevo, comparte tokens).
- El footer de la landing ya no apunta al PDF de aviso de privacidad para "Términos" y "Cookies" — apunta a estas páginas propias. El link de "Aviso de privacidad" sigue al PDF (correcto, es el documento real).
- **Límite honesto**: el contenido es un texto estándar razonable para una asociación civil, no fue redactado ni revisado por un abogado. Está marcado explícitamente como referencia general, no asesoría legal, con nota "para el tratamiento de datos personales, consulta el Aviso de Privacidad" (el documento con validez real). Si el Clúster necesita términos con validez legal específica, deben ser redactados o revisados por su asesor legal — yo no debo presentar texto legal inventado como si fuera definitivo.

### Sistema de diseño — no se re-verificó exhaustivamente en esta ronda
Dado el volumen de trabajo de hoy (seguridad, rediseño de landing, CSP, limpieza de código), no alcancé a hacer una segunda pasada de verificación visual completa de cada página del sitio en esta fase — ya se hizo una ronda extensa en `DESIGN_SYSTEM.md` (Fases 1-5) cubriendo las páginas principales y las reportadas por el usuario. Migrar el markup de `.claut-skin` (capa de traducción) a clases nativas del sistema sigue siendo trabajo pendiente de una sesión dedicada — no es algo que deba apresurarse al final de una sesión larga sin margen para verificar cada cambio visualmente.
