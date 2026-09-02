# ⚠️ Fricciones Detectadas en el Cuestionario

> Cosas que marcaste y que conviene matizar para no romper el sistema actual o no degradar la experiencia. Mi propuesta junto a cada punto. Si no respondes, aplico la propuesta.

---

## 🟡 A3 — Auto light/dark + inversión scroll en hero

**Lo que pediste**: ambos a la vez.

**Fricción**: si el usuario activa **dark mode global**, el efecto de "blanco→negro al scroll" pierde sentido (ya está en negro). En light mode el efecto luce premium tipo Apple, en dark luce confuso.

**Propuesta**:
- En **light mode**: hero blanco que invierte a negro al scroll (Porsche/Apple feel).
- En **dark mode**: hero arranca en negro profundo, scroll degrada a gris carbón con acentos rojos. Sin inversión completa, solo profundidad.
- Toggle global persistido en `localStorage` + `prefers-color-scheme` por default.

✅ Aplicar esta lógica en `theme.js` y `color-invert.js`.

---

## 🟡 A5 — Tono corporativo formal

**Lo que pediste**: corporativo formal.

**Fricción**: el sistema tiene un componente comunitario (mensajes entre socios, comités, etc). El tono 100% formal puede sentirse rígido en buzón/comités.

**Propuesta**: tono **formal en headers/landing/legal** + **profesional cálido en interacciones internas** (toasts, mensajes de éxito, vacíos). No mezcla forzada — solo respeta el contexto de cada componente.

Ejemplo:
- Landing: "Conectando empresas del Estado de México."
- Toast tras guardar: "Cambios aplicados." (no "¡Listo!" ni "Operación completada satisfactoriamente").

✅ Aplicar en JSON de i18n (`es.json` con dos tonos por contexto).

---

## 🟡 B3 — Igual estética landing y dashboard

**Lo que pediste**: misma estética.

**Fricción**: un dashboard real necesita **mayor densidad de información** (tablas, listas largas, filtros). Si lo intentamos hacer "lleno de aire" como un landing, se siente vacío y obliga a scrollear de más.

**Propuesta**: **mismo lenguaje visual** (paleta, tipografía, animaciones, microinteracciones, página de transiciones) pero **densidad ajustada**:
- Landing/Hero/About: aire generoso, hero gigante, scroll animations protagonistas.
- Dashboard interno: misma paleta y tipografía, microinteracciones idénticas, pero layouts compactos con datos reales. Tablas sin "aire decorativo" excesivo.

Resultado: parecen del mismo producto, no de la misma página. ✅ Aplicar.

---

## 🟢 C1 — Bottom nav móvil + sidebar desktop ✅ OK
Sin fricción. Mejor que el header sticky para apps con muchos módulos. Aplicar.

---

## 🟡 C3 — Bilingüe ES/EN

**Lo que pediste**: bilingüe ES/EN con toggle.

**Fricción**: el contenido dinámico (eventos, boletines, comités, descuentos) **lo crean los admins en español** desde el panel actual. Si solo traduces la UI estática, el usuario verá mezcla (botones EN + cuerpo ES).

**Propuesta** (dos opciones, elige):
- **Opción A — Solo UI traducida** (rápido): botones, menús, labels, toasts, mensajes de error, plantillas de email. El contenido dinámico queda en el idioma que lo capturó el admin. ✦ recomiendo arrancar aquí.
- **Opción B — Contenido bilingüe completo** (más caro): cada `eventos`, `boletines`, `comites`, `descuentos`, `documentos` necesita columnas `_es` y `_en` (o tabla `traducciones (entity, entity_id, lang, field, value)`). Admin captura ambos idiomas. Migración SQL necesaria.

✅ Por defecto aplico **Opción A**. Si quieres B avísame y agrego migración.

---

## 🔴 D1 — Image sequence en cada módulo principal

**Lo que pediste**: una secuencia hero por módulo.

**Fricción crítica**:
- Cada secuencia HD optimizada en WebP pesa **~3-8MB** (60-120 frames).
- En móvil 4G lento se ven cargas de 5-15 segundos.
- Multiplicado por 8-10 módulos = 30-80MB de assets solo para sequences.
- Hostinger Shared limita ancho de banda, puedes saturarlo en horas pico.

**Propuesta balanceada**:
- **Hero principal del landing**: 1 sequence HD completa (la estrella del show, vale el peso).
- **Heroes de módulo**: SVG morphing animado con GSAP (50-200KB cada uno). Mismo feel cinematográfico, 100x más liviano.
- **Detección de conexión**: en `2g`/`3g`/`save-data` → fallback a imagen estática + animación CSS. Patrón nativo `navigator.connection`.

✅ Aplicar este enfoque híbrido. Si quieres todas con sequence pesado, dime y lo hago.

---

## 🟡 D4 — Lift + Tilt 3D combinados

**Lo que pediste**: ambos efectos en cards.

**Fricción**: lift+sombra ya da sensación de elevación. Sumarle tilt 3D fuerte (15°+) se siente "ruidoso" y compite con el contenido de la card.

**Propuesta**: **tilt sutil (max 6° en X y Y) + lift discreto (translateY -4px) + sombra leve**. Combinados con curvas easing líquidas, se siente premium sin ser molesto.

✅ Aplicar en `card-tilt.js` con `max: 6, scale: 1.02, speed: 600, perspective: 1500`.

---

## 🟢 E2 — Columna `superadmin TINYINT(1)` en `usuarios_perfil` ✅ OK simple
Más simple que la tabla `usuario_permisos` flexible que recomendaba ✦.
- Pro: 1 sola migración, comparación trivial `if ($_SESSION['superadmin'] === 1)`.
- Contra: futuros roles requieren más migraciones.
Aceptable para el alcance actual. ✅ Aplicar.

---

## 🟡 F3 — Toggle granular por campo (tabla nueva)

**Lo que pediste**: cada campo "para directorio" tiene su toggle.

**Fricción**: añade complejidad UI considerable. El socio verá ~13 toggles individuales en su perfil. Probabilidad alta de que termine activando todos por flojera, lo cual deja la casilla maestra más conveniente.

**Propuesta**:
- Implementar tabla `empresa_visibilidad_campo (empresa_id, campo, visible TINYINT)`.
- En UI, presentar como **"Mostrar todo en directorio" (toggle maestro)** + un acordeón "Personalizar visibilidad de campos" para los que quieran granularidad.
- Default: maestro=ON, todos los campos visibles. Solo quien quiera privacidad fina abre el acordeón.

✅ Aplicar este patrón. Te da control real sin abrumar.

---

## 🟡 F4 — Form público de auto-registro de empresas

**Lo que pediste**: admin captura + form público de auto-registro.

**Fricción**: form público sin auth abierto a internet = spam y registros basura. Riesgo de moderación constante para admin.

**Propuesta**:
- Form público con **CAPTCHA** (Cloudflare Turnstile gratis, sin API key compleja, o math captcha local).
- Estado inicial `pendiente`. Solo admin puede aprobar.
- Email de confirmación al solicitante con token de 1 sola lectura.
- Rate limit ya existe (3/h por IP).

✅ Aplicar con Turnstile o math captcha simple.

---

## 🟡 G2 — Código único + QR

**Lo que pediste**: código único por usuario + QR + tracking.

**Fricción**: necesitas un endpoint nuevo que **valide** el QR del lado del comerciante (la empresa que ofrece el descuento). Sin esto, el QR es decorativo.

**Propuesta**:
- Generar `codigo_unico` UNIQUE en `descuentos_usos` cuando el usuario hace click.
- QR codifica `https://intranet.clautmetropolitano.mx/validar?c=<codigo>`.
- Página `validar.html` (sin login) que llama `GET /api/descuentos/validar.php?c=<codigo>` → marca `usado=1`. Comerciante escanea con cualquier lector y ve "VÁLIDO" o "YA USADO".

✅ Aplicar con endpoint nuevo `validar.php` y vista pública `validar.html`.

---

## 🟢 H1 — Beneficio Clúster en eventos ✅ OK
Migración simple, factible. Aplicar.

---

## 🟡 H3 — Recordatorios email + push browser

**Lo que pediste**: ambos.

**Fricción**: 
- **Email** se programa con **cron job de Hostinger** (lo soporta gratis): `0 8 * * * php /home/.../cron_recordatorios.php`.
- **Push browser** requiere Service Worker + permiso del usuario + endpoint VAPID + almacenar `subscription` en BD. Funciona, pero requiere HTTPS (ya tienes) y que el usuario acepte permisos. Si rechaza, no llega nada.

**Propuesta**:
- ✅ Email recordatorios via cron Hostinger (24h y 1h antes).
- ✅ Push opcional con onboarding que pregunte permiso (no popup intrusivo). Si acepta, se guarda en `push_subscriptions (user_id, endpoint, p256dh, auth)`.
- Fallback: notificación in-app vía `notificaciones` cuando el usuario abre la app.

✅ Aplicar los 3 niveles.

---

## 🟢 I1 + I2 — Comités con Google Forms, WhatsApp, sesiones ✅ OK
Tabla nueva `comite_sesiones`. Aplicar.

---

## ✅ Resumen de migraciones SQL nuevas necesarias

Todas se generan en `MIGRACIONES_NUEVAS.sql` (idempotentes):

1. `usuarios_perfil` → ADD COLUMN `superadmin TINYINT(1) DEFAULT 0`
2. `empresas_convenio` → ADD COLUMN `es_socio TINYINT(1) DEFAULT 1`
3. `empresas_convenio` → ADD COLUMN `tiene_convenio_activo TINYINT(1) DEFAULT 0`
4. CREATE TABLE `empresa_visibilidad_campo`
5. CREATE TABLE `comite_sesiones`
6. `comites` → ADD COLUMN `link_whatsapp VARCHAR(500)`, `link_google_form VARCHAR(500)`
7. `eventos` → ADD COLUMN `beneficio_cluster TINYINT(1) DEFAULT 0`, `beneficio_descripcion TEXT`, `beneficio_contacto VARCHAR(255)`
8. `descuentos_usos` → ADD COLUMN `codigo_unico VARCHAR(64) UNIQUE`, `usado TINYINT(1) DEFAULT 0`, `fecha_validacion DATETIME`
9. CREATE TABLE `push_subscriptions`
10. CREATE TABLE `theme_config` (key/value para superadmin)
11. CREATE TABLE `traducciones` (solo si eliges Opción B en C3) — opcional

Todas se corren UNA VEZ en phpMyAdmin después del rediseño. Cero downtime.

---

## 🟢 Lo que dejé sin tocar (ya está bien)

- ✅ Stack confirmado (J1) — adaptado a Hostinger en `STACK_HOSTINGER.md`.
- ✅ Despliegue Hostinger (J2 ajustado, no Vercel).
- ✅ Sin Storybook, sin tests por ahora (J3).
- ✅ Panel `/admin/configuracion` (K2).
- ✅ Conexión remota a BD (L1).
- ✅ Usar dump_db.php (L2).
- ✅ Dejar que decida lo de Sección N.
