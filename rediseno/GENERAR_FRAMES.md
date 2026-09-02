# 🎬 Cómo generar los frames del Image Sequence Scrubbing

> El hero del landing usa **image sequence scrubbing** estilo Porsche/Apple: una secuencia de imágenes que cambia con el scroll, pintada sobre `<canvas>`. Necesitas 60 frames WebP optimizados.
>
> Si no tienes los frames todavía, el sistema **funciona con un fallback Lottie genérico**. Puedes generar los frames después y solo subirlos por FTP — no requiere recompilar nada.

---

## 1. Origen del video

Ten un clip MP4 corto (5-10 segundos) que represente la marca. Sugerencias:
- **Loop del logo Clúster** girando, rearmándose o apareciendo letra por letra.
- **Time-lapse aéreo** del Estado de México / zona industrial.
- **Empresas trabajando**: B-roll de fábricas, oficinas, reuniones (con permisos).
- **Animación 3D** del logo flotando con luces.

Resolución mínima: **1920×1080**. Ideal: 2560×1440. Más alto = pesa demasiado.

---

## 2. Instalar ffmpeg (una sola vez en tu Mac)

```bash
brew install ffmpeg
```

Verifica:
```bash
ffmpeg -version
```

---

## 3. Generar 60 frames WebP optimizados

Reemplaza `tu_video.mp4` por la ruta de tu video y duración (ej. 6 segundos):

```bash
cd ~/Desktop/Claut_BD/rediseno
mkdir -p frames_hero

# 60 frames = 10 fps × 6 segundos. Ajusta fps según duración del video.
# Para 6 seg → fps=10 da exactamente 60 frames.
# Para 10 seg → fps=6 da 60 frames.

ffmpeg -i tu_video.mp4 \
  -vf "fps=10,scale=1920:-2:flags=lanczos" \
  -c:v libwebp \
  -quality 75 \
  -compression_level 6 \
  -pred mixed \
  frames_hero/%04d.webp
```

Esto produce: `0001.webp`, `0002.webp`, ..., `0060.webp` en `frames_hero/`.

### Variantes según necesidad

**Mejor calidad (~6MB total)**:
```bash
ffmpeg -i tu_video.mp4 -vf "fps=10,scale=2560:-2" -c:v libwebp -quality 85 frames_hero/%04d.webp
```

**Mínimo peso (~2MB total) para móvil/3g**:
```bash
ffmpeg -i tu_video.mp4 -vf "fps=10,scale=1280:-2" -c:v libwebp -quality 65 frames_hero/%04d.webp
```

---

## 4. Verificar tamaño total

```bash
du -sh frames_hero/
```

**Objetivo**: entre **2MB y 4MB** total. Si pasa de 5MB, baja `quality` o `scale`.

```bash
# Cuenta frames generados
ls frames_hero/*.webp | wc -l   # debe dar 60
```

---

## 5. Subir a Hostinger

Por FTP, copia `frames_hero/` completo a:
```
public_html/assets/img/sequences/hero/
```

Estructura final en el servidor:
```
public_html/assets/img/sequences/hero/
├── 0001.webp
├── 0002.webp
├── ...
└── 0060.webp
```

---

## 6. Imagen "cover" para fallback

Generar UNA imagen estática (frame medio del video) para conexiones lentas o cuando JS está deshabilitado:

```bash
ffmpeg -i tu_video.mp4 -ss 00:00:03 -frames:v 1 \
  -vf "scale=1920:-2" -c:v libwebp -quality 80 \
  frames_hero/cover.webp
```

Subir a `public_html/assets/img/sequences/hero/cover.webp`.

---

## 7. Si NO tienes video todavía

Opciones para arrancar **sin frames reales**:

### Opción A — Lottie genérico (lo que el código incluye por default)
- Descarga una animación gratuita en [lottiefiles.com](https://lottiefiles.com/) (busca "abstract loop" o "geometric morph").
- Súbela como `public_html/assets/lottie/hero-placeholder.json`.
- El hero usa Lottie hasta que reemplaces los frames.

### Opción B — Generar de un PNG con Photoshop / After Effects
- Si tienes el logo SVG, anímalo en After Effects con un loop de 6s, exporta MP4 y aplica el comando ffmpeg de arriba.

### Opción C — Generar online sin software
- [Runway ML](https://runwayml.com/) (free tier) genera video corto desde texto.
- [Pika Labs](https://pika.art/) similar.
- Descarga el MP4 → ffmpeg → frames.

### Opción D — Stock video gratis
- [Pexels Videos](https://www.pexels.com/videos/) — busca "industrial", "city aerial", "abstract", "blue gradient".
- Descarga 1080p MP4 → ffmpeg → frames.

---

## 8. Probar localmente

Antes de subir, prueba que los frames se vean correctamente:

```bash
# En la carpeta frames_hero/
open 0001.webp 0030.webp 0060.webp
```

Si la transición frame-a-frame se ve fluida (el video original es continuo), el scrubbing funcionará.

---

## 9. Cómo el código consume los frames

El JS `assets/js/animations/hero-sequence.js` hace esto automáticamente:

```js
initHeroSequence(
  'hero-canvas',                       // id del <canvas>
  '/assets/img/sequences/hero',        // ruta base
  60,                                  // cantidad de frames
  'webp'                               // extensión
);
```

Detecta automáticamente:
- `navigator.connection.saveData` → fallback estático.
- `effectiveType === '3g'` → fallback estático.
- Falta de algún frame → fallback Lottie.

---

## 10. Configuración runtime desde panel superadmin

El comportamiento es configurable en `theme_config`:

| Clave | Valor | Efecto |
|-------|-------|--------|
| `hero_sequence_enabled` | `1` | Activa/desactiva el sequence |
| `hero_sequence_frames` | `60` | Cantidad de frames a cargar |
| `hero_sequence_path` | `/assets/img/sequences/hero` | Ruta base |
| `hero_sequence_ext` | `webp` | Extensión |
| `hero_fallback_lottie` | `/assets/lottie/hero-placeholder.json` | Fallback |

Cambias el valor desde `/admin/configuracion.html` y aplica sin tocar código.

---

## 11. Optimización avanzada (opcional)

### AVIF en lugar de WebP (~30% menos peso)
```bash
ffmpeg -i tu_video.mp4 -vf "fps=10,scale=1920:-2" -c:v libavif -crf 28 frames_hero/%04d.avif
```
Cambia `hero_sequence_ext` a `avif`. Compatibilidad: 90%+ de navegadores modernos.

### Sprite sheet (un solo archivo grande)
```bash
ffmpeg -i tu_video.mp4 -vf "fps=10,scale=480:-2,tile=10x6" -frames:v 1 sprite.webp
```
Genera 1 imagen 4800×2880 con todos los frames. JS la "cropea" según frame index. Reduce HTTP requests de 60 a 1. Requiere variante del JS — pídele al LLM que la genere si quieres esta optimización.

---

## 12. Checklist final

- [ ] ffmpeg instalado.
- [ ] Video fuente listo (5-10s, ≥1080p).
- [ ] Comando ejecutado, 60 frames generados.
- [ ] Tamaño total entre 2-4MB.
- [ ] `cover.webp` generado.
- [ ] Carpeta subida a `public_html/assets/img/sequences/hero/` por FTP.
- [ ] Probado en `https://intranet.clautmetropolitano.mx/` — el hero hace scrubbing fluido al scroll.
- [ ] Probado con throttling 3G en DevTools — fallback estático aparece correctamente.

---

> 📌 **Resumen**: el rediseño se entrega **sin frames**, funcionando con Lottie placeholder. Cuando tengas tu video, corre el comando ffmpeg, sube los WebP por FTP y el hero se ve premium automáticamente. Cero recompilación, cero deploy, cero downtime.
