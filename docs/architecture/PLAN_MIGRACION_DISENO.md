# 🎨 Plan de implementación — Migrar `.claut-skin` a clases nativas

> No ejecutado en esta sesión más allá del alcance ya cubierto — requiere verificación visual con sesión activa, que no estaba disponible al escribir este plan.

## Por qué existe `.claut-skin` y por qué migrar

`.claut-skin` (sección 13 de `css/claut-ui.css`) es una capa de **traducción**: convierte clases Tailwind claras (`bg-white`, `text-gray-800`, etc.) a la paleta oscura mediante selectores como `.claut-skin .bg-white { background: var(--glass-bg) !important }`. Funciona, pero tiene dos costos reales:
1. **Especificidad frágil**: depende de `!important` para ganarle a Tailwind CDN, lo que ya causó una regresión real esta sesión (texto blanco sobre `background:white` inline en `descuentos.html`, porque el skin no alcanza estilos inline).
2. **No es la fuente de verdad**: una página nueva construida con Tailwind claro + `.claut-skin` puede verse bien hoy y mal mañana si Tailwind cambia cómo genera sus clases, porque depende de que la clase exacta (`bg-white`, no `bg-white/50`) siga apareciendo en el HTML.

Migrar a clases nativas (`.glass-panel`, `.claut-stat-card`, `.claut-table`, `.claut-badge`, etc., ya definidas en `claut-ui.css`) significa que el HTML declara directamente su intención visual, sin depender de una capa de traducción.

## Alcance: qué páginas usan `.claut-skin`

Confirmado por grep en la Fase 3/6 de `DESIGN_SYSTEM.md`: los 9 paneles `demo_*.html`, `visitante.html`, `profile.html` parcialmente, y `gestionar_usuarios.php`/`admin-panel.html`/`calendario.html` (aunque estos últimos casi no usan Tailwind claro, el skin ahí es mayormente inerte).

## Por qué no se ejecuta a ciegas en esta sesión

Cada migración de un componente (p. ej. cambiar `class="bg-white rounded-lg shadow p-4"` por `class="glass-panel"`) puede alterar sutilmente el padding, el radio de borde o el espaciado si las clases de Tailwind que acompañaban al `bg-white` (`p-4`, `rounded-lg`, `shadow`) no tienen equivalente exacto en la clase nativa. La única forma confiable de detectarlo es comparar visualmente antes/después en el navegador — que requiere sesión autenticada, no disponible en el momento de escribir este plan.

## Plan de ejecución (por sesión, con verificación obligatoria en cada paso)

### Paso 1 — Elegir una página piloto
`demo_boletines.html` es buena candidata: ya se corrigieron sus bugs de distribución en la Fase 3, es de tamaño moderado, y su patrón de tarjetas (`bg-white rounded-lg shadow p-4`) se repite en las otras 8 páginas demo — migrarla primero valida el mapeo de clases antes de replicarlo.

### Paso 2 — Mapeo de clases (tabla de conversión)
| Patrón Tailwind actual | Clase nativa destino |
|---|---|
| `bg-white rounded-lg shadow p-4` (tarjeta genérica) | `glass-panel` + padding propio si el nativo no coincide |
| `bg-white rounded-2xl shadow-xl` (tarjeta grande) | `glass-panel` con `border-radius` ajustado |
| Stat card (`bg-white rounded-lg shadow p-4` con número grande) | `claut-stat-card` (ya tiene `.stat-value`/`.stat-label`) |
| `<table>` con `bg-white`, headers `bg-gray-50` | `claut-table` |
| Badges de estado (`bg-green-100 text-green-800`, etc.) | `claut-badge claut-badge--success` (y variantes) |
| Modales (`bg-white rounded-lg shadow-lg`) | `claut-modal` + `claut-modal__header/body/footer` |
| Botones rojos sólidos | `porsche-btn` |
| Botones grises/neutros | `porsche-btn porsche-btn--ghost` |

### Paso 3 — Migrar UN componente a la vez, no la página completa
Ejemplo: migrar solo las tarjetas de estadísticas de `demo_boletines.html` (`Total Boletines`, `Publicados`, etc.) a `claut-stat-card`, desplegar, verificar visualmente, commit. Luego la tabla, luego los modales, luego los botones — cada uno es su propio ciclo desplegar→verificar.

### Paso 4 — Una vez validado el mapeo en la página piloto, replicar a las otras 8
Con el mapeo de clases ya probado, migrar las 8 páginas demo restantes es mecánico (buscar-reemplazar guiado, no exploratorio), pero **cada una igual necesita su propia verificación visual** — no asumir que el mapeo funciona igual en todas sin mirar.

### Paso 5 — Remover `.claut-skin` de `claut-ui.css`
Solo cuando las 11 páginas (9 demo + visitante + profile) ya no dependan de la capa de traducción. Antes de eso, dejarla activa no hace daño — es simplemente redundante en las páginas ya migradas.

## Estimado de esfuerzo total
- Página piloto (Paso 1-3): 2-3 horas con verificación cuidadosa.
- Replicar a 8 páginas restantes (Paso 4): 3-4 horas.
- Limpieza final (Paso 5): 30 minutos.
- **Total**: ~1 día de trabajo enfocado, no algo para comprimir al final de una sesión ya larga.

## Páginas pendientes de una segunda pasada de revisión visual (bloqueado por sesión expirada)
`visitante.html`, `admin-2fa.html`, `admin-auditoria.html`, `admin_usuarios.html` — no se pudieron revisar en esta sesión porque la sesión de administrador expiró y no debo escribir credenciales por política. Pendiente para la próxima sesión con login activo.
