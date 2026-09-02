# 🎨 Cuestionario de Diseño — Pre-Prompt Final

> Necesito tus respuestas para afinar el `PROMPT_FINAL.md`. Responde solo lo que aplique, lo demás lo asumo con valores por default sensatos (marcados con ✦).

---

## SECCIÓN A — Identidad visual y mood

**A1.** ¿Qué referencia es la dominante?
- [ ] Apple (microinteracciones discretas, scroll suave, mucho aire, tipografía SF Pro / Inter)
- [ ] Porsche (image sequence scrubbing protagonista, hero cinemático, paneles full-bleed)
- [ ] Stripe / Linear (color suave + gradients sutiles + animaciones tipográficas)
- [x] Mezcla (Porsche en hero, Apple en interior) ✦ default sugerido

**A2.** ¿Conservas el rojo Clúster `#C7252B` como acento?
- [x] Sí, como acento mínimo (CTA, badges) ✦ recomendado
- [] Sí, protagonista en headers
- [ ] No, gris carbón / negro únicamente
- [ ] Otro hex (especifica): ____

**A3.** ¿Modo de color?
- [ ] Light mode estricto blanco puro `#FFFFFF` ✦
- [ ] Light mode con tinte cálido (off-white `#FAFAFA`)
- [x] Auto (light + dark) — el sistema toggle desde settings de usuario
- [x] Inversión a oscuro **al hacer scroll** en hero (efecto Porsche/Apple)

**A4.** Tipografía principal:
- [x] Inter ✦ (ya en stack actual)
- [ ] Söhne / sans premium de pago
- [ ] Geist (Vercel)
- [ ] SF Pro (vía CDN)
- [ ] Open Sans (legado actual)

**A5.** ¿Tono general del copy?
- [x] Corporativo formal
- [ ] Profesional cálido ✦
- [ ] Conversacional / cercano

---

## SECCIÓN B — Hero y landing

**B1.** El landing público (`/`) verá:
- [x] Image sequence scrubbing con video de socios/eventos (estilo Porsche) ✦
- [ ] SVG hero animado con SVGator (logo morphing + draw on scroll)
- [ ] Vídeo en loop silenciado fullscreen
- [ ] Hero estático con tipografía gigante + gradiente sutil

**B2.** ¿Tienes assets para image sequence (frames PNG/WebP)?
- [ ] Sí, los voy a generar
- [x] No, usa placeholder con frames de un Lottie genérico
- [ ] Convertir el logo PHOTO-2025-08-11-12-16-03 + ClautEdoMex a una secuencia de morph
- [ ] Asume placeholder y genera código compatible para que yo agregue frames después ✦

**B3.** ¿Quieres que el landing público sea distinto al dashboard interno?
- [ ] Sí, landing es marketing/ventas, dashboard es app ✦
- [x] No, igual estética en ambos
- [ ] Solo dashboard, sin landing público (el `/` redirige a login)

---

## SECCIÓN C — Navegación y layout

**C1.** Estructura de navegación dashboard:
- [ ] Header horizontal sticky (estado actual de profile.html) ✦
- [ ] Sidebar fijo izquierdo
- [ ] Sidebar colapsable + header
- [x] Bottom nav móvil + sidebar desktop

**C2.** ¿Cómo entra/sale entre vistas?
- [x] Page transitions con Framer Motion (fade + slide pequeño) ✦
- [ ] Cortinas verticales que revelan la nueva vista
- [ ] Cross-fade con shared layout (Apple-like)
- [ ] Sin transición (instant navigation)

**C3.** ¿Idioma del menú?
- [ ] Español ✦
- [x] Bilingüe ES/EN con toggle

---

## SECCIÓN D — Microinteracciones y scroll

**D1.** ¿Image sequence scrubbing aparece en…?
- [ ] Solo landing público
- [ ] Landing + dashboard "About / Quiénes somos" ✦
- [x] En cada módulo principal (1 secuencia hero por módulo)

**D2.** Inversión de colores al scroll (hero blanco → fondo negro al avanzar):
- [x] Sí, en landing ✦
- [ ] Sí, en cada hero de módulo
- [ ] No, mantener light mode siempre

**D3.** Para revelado de tarjetas/listas en scroll:
- [ ] Stagger fade-up suave ✦
- [x] Magnetic / parallax leve
- [ ] Mask reveal (las cards entran detrás de un curtain)
- [x] Drawing SVG (líneas que se dibujan antes del contenido)

**D4.** Hover en cards de empresa/evento:
- [x] Lift + sombra (Apple) ✦
- [x] Tilt 3D (mouse parallax)
- [ ] Border draw + zoom imagen sutil
- [ ] Solo cambia cursor, sin movimiento

---

## SECCIÓN E — Roles y permisos

**E1.** Confirmar roles definitivos:
- [x] **superadmin** — todo + configuración del sistema
- [x] **admin** — CRUD operativo, no toca config
- [x] **empleado_cluster** — lectura y edición acotada por departamento
- [x] **empresa_socio** — solo edita su empresa + envía solicitudes
- [ ] ¿Algún rol adicional? (especifica): ____

**E2.** ¿El superadmin se distingue del admin por…?
- [x] Una columna `superadmin TINYINT(1)` nueva en `usuarios_perfil`
- [ ] Un email o user_id "raíz" hardcoded en `.env` (`SUPERADMIN_USER_ID`)
- [ ] Una tabla `usuario_permisos` flexible (recomendado para escalar) ✦
- [ ] Por ahora superadmin = admin con flag manual

**E3.** ¿Qué puede ver un `empresa_socio` además de su perfil?
- [x] Directorio + Eventos + Comités + Descuentos + Boletines (lectura) ✦
- [ ] Solo directorio + sus propias solicitudes
- [ ] Todo lo de empleado_cluster excepto admin/usuarios

---

## SECCIÓN F — Directorio (campos)

**F1.** Confirmas los campos PARA DIRECTORIO listados en tu mensaje:
- [x] Nombre, Sector, Estado, Municipio, Descripción, Certificaciones, Exporta, Redes (FB, X, LinkedIn, IG), Logo (archivo o URL), Persona contacto + cargo + tel + email, Sitio web
- [ ] Faltó agregar: ____

**F2.** Datos NO públicos (solo admin + el propio socio):
- [x] Email general, teléfono general, dirección física, departamento del usuario
- [ ] Agregar: ____

**F3.** ¿Visibilidad granular por campo o casilla única `autoriza_directorio`?
- [ ] Solo casilla única (estado actual) ✦ simple
- [x] Cada campo "para directorio" tiene su propio toggle (más control) — requiere nueva tabla `empresa_visibilidad_campo`

**F4.** ¿Las empresas del directorio que NO son socios cluster:
- [x] Las captura el admin manualmente y las marca con `es_socio=0` ✦
- [x] Hay un formulario público para que se auto-registren (con aprobación)
- [ ] Solo socios pueden estar en el directorio

---

## SECCIÓN G — Convenios / Descuentos

**G1.** ¿La empresa decide aparecer en `/descuentos` con una casilla aparte de `autoriza_directorio`?
- [x] Sí, casilla `tiene_convenio_activo` independiente ✦
- [ ] No, basta con que llene `convenio_descripcion` + `vigencia_*`

**G2.** ¿El descuento se valida con código o solo es informativo?
- [ ] Solo informativo (estado actual) ✦
- [x] Genera `codigo_descuento` único por usuario al hacer click "Quiero usar este descuento" + tracking en `descuentos_usos`
- [x] QR en pantalla + tracking

---

## SECCIÓN H — Eventos

**H1.** ¿Cada evento debe poder marcar "tiene beneficio Clúster"?
- [x] Sí (mencionado en historial) — agregar campos `beneficio_cluster TINYINT, beneficio_descripcion TEXT, beneficio_contacto VARCHAR(255)`
- [ ] No

**H2.** ¿Mapas?
- [ ] Embed Google Maps con lat/lng ✦
- [x] Solo dirección texto + link a Maps
- [ ] Mapbox custom styled

**H3.** ¿Recordatorios automáticos?
- [ ] 24h antes por email ✦
- [ ] 1h antes por push browser
- [x] Ambos

---

## SECCIÓN I — Comités

**I1.** Datos a capturar por comité (mencionados en historial):
- [x] Liga Google Forms de registro (campo `link_registro`/`link_google_form`)
- [x] Link/teléfono del grupo de WhatsApp (campo nuevo `link_whatsapp`)
- [x] Sesiones próximas (¿tabla nueva `comite_sesiones`?)
- [ ] Otros: ____

**I2.** ¿Las sesiones de comité tienen su propia tabla o reusan `eventos` con `tipo='comite'`?
- [x] Tabla nueva `comite_sesiones (id, comite_id, fecha, hora, lugar, agenda, link_meet)` ✦
- [ ] Reusar `eventos` con `comite_id` opcional

---

## SECCIÓN J — Estética técnica del rediseño

**J1.** Stack confirmado:
- [x] Next.js 14 App Router + TypeScript
- [x] Tailwind CSS
- [x] Framer Motion (microinteracciones + page transitions)
- [x] GSAP + ScrollTrigger (scroll scrubbing, drawSVG)
- [x] Lenis (smooth scroll suave) ✦ recomiendo agregar
- [x] react-three/fiber (opcional, solo si quieres 3D en hero)
- [x] cmdk (command palette ⌘K para superadmin)
- [x] sonner (toasts en lugar de alert nativo)
- [x] ¿Agregar Storybook?
- [x] ¿Agregar Vitest + Playwright para tests?

**J2.** Despliegue:
- [ ] Vercel (Next.js standalone) + API PHP en Hostinger (cross-origin) ✦
- [ ] Build estático export → mismo Hostinger
- [ ] Solo `next export` para que sirva desde `public_html` igual que hoy

**J3.** ¿Quieres que el prompt incluya generación de Storybook con cada componente?
- [x] Sí (más código, mejor para mantener)
- [ ] No, solo código de páginas y componentes ✦

---

## SECCIÓN K — Personalización superadmin (TODO configurable)

**K1.** Confirmas que TODO es configurable. Eso incluye crear `theme_config` y endpoints CRUD:
- Logo (archivo)
- Color primario / secundario / acento
- Tipografía (de un set permitido)
- Textos del landing (hero, secciones, footer)
- Banners del carrusel
- Lista de sectores / categorías / municipios disponibles
- Lista de tipos de evento / comité
- Plantillas de email (subject + html)
- Toggle por evento de notificación

**¿Algo NO debe ser configurable y debe quedar fijo en código?** ____

**K2.** ¿Quieres un panel `/admin/configuracion` con tabs para todo lo anterior?
- [x] Sí ✦
- [ ] No, configuración dispersa por módulo

---

## SECCIÓN L — Datos de prueba y seed

**L1.** ¿El nuevo frontend debe poder correr en local con la BD remota o necesitas seed local?
- [x] Conexión remota a Hostinger (con .env) ✦
- [ ] Seed local con docker-compose (mysql + phpmyadmin) y dump opcional
- [ ] Mock con MSW para desarrollo sin backend

**L2.** ¿Tienes un dump SQL reciente para seed?
- [ ] Sí (path: ____)
- [ ] No, lo genero después
- [x] Existe `dump_db.php` ✦ — usar ese

---

## SECCIÓN M — Pendientes operativos del historial

Confirma cuáles bloquean el lanzamiento:
- [ ] Correo del dominio para SMTP (notificaciones automáticas)
- [ ] Links de invitación de grupos WhatsApp por comité
- [ ] Links de Google Forms por comité (¿existen o se crean?)
- [ ] Definición de pago de uso de IA (asistente del sistema)
- [ ] ¿Algo más?

---

## SECCIÓN N — Riesgos / decisiones que prefieres que tome yo

Marca lo que prefieres que decida con criterio (sin tu input):
- [x] Naming TypeScript de tipos (Empresa vs Company, Evento vs Event)
- [x] Estructura de carpetas Next.js
- [x] Set exacto de componentes Tailwind base
- [x] Curvas de easing de Framer (cubic-bezier exacta)
- [x] Cuántos breakpoints responsive
- [x] Qué iconos usar (Lucide vs Heroicons vs custom SVG)

---

## 📥 Cómo respondes

Puedes responder corto, ej:

> A1: mezcla. A2: rojo acento. A3: con inversión al scroll. A4: Inter. B1: image sequence. B2: placeholder. C1: header sticky. C2: shared layout. D1: landing+about. D2: sí en landing. E2: tabla permisos. F3: solo casilla. G1: independiente. H1: sí. I2: tabla nueva. J1: agrega Lenis y sonner, sin Storybook ni tests. J2: Vercel + Hostinger. K2: sí. L1: remoto. M: SMTP pendiente, otros desconocidos. N: decide tú todo.

Con eso compilo `PROMPT_FINAL.md` listo para pegar en Claude/Cursor y arrancar.
