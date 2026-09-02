#!/bin/bash
# ============================================================================
# OPTIMIZAR frames de la landing — JPG → WebP
# ============================================================================
# Reduce ~3.9 MB de frames JPG en `landing/assets/sequence/` a ~1 MB WebP.
# Cambio neto en transferencia por visita a la landing: ~3 MB menos.
#
# REQUISITO: tener instalado cwebp (Google WebP encoder)
#   macOS:   brew install webp
#   Ubuntu:  sudo apt install webp
#
# USO:
#   1. cd al directorio raíz del proyecto
#   2. bash build/setup/optimize_sequence.sh
#   3. El script convierte y deja los .webp junto a los .jpg originales
#   4. Edita landing/assets/js/sequence.js → cambia FRAME_EXT de '.jpg' a '.webp'
#   5. Sube SOLO los .webp nuevos + el sequence.js modificado a producción
#   6. Después de verificar que carga bien, puedes borrar los .jpg de producción
# ============================================================================

set -e

DIR="$(cd "$(dirname "$0")/.." && pwd)/landing/assets/sequence"
QUALITY=82  # Calidad WebP — 82 es óptimo (visualmente idéntico a JPG q=90)

if ! command -v cwebp &> /dev/null; then
  echo "❌ ERROR: cwebp no encontrado. Instala con: brew install webp"
  exit 1
fi

if [ ! -d "$DIR" ]; then
  echo "❌ ERROR: directorio no encontrado: $DIR"
  exit 1
fi

cd "$DIR"

TOTAL=$(ls -1 *.jpg 2>/dev/null | wc -l | xargs)
if [ "$TOTAL" -eq 0 ]; then
  echo "❌ No hay archivos .jpg en $DIR"
  exit 1
fi

echo "🔄 Convirtiendo $TOTAL frames JPG → WebP (calidad $QUALITY)..."
echo "📁 $DIR"
echo ""

COUNT=0
SIZE_BEFORE=0
SIZE_AFTER=0

for jpg in *.jpg; do
  webp="${jpg%.jpg}.webp"
  if [ -f "$webp" ]; then
    echo "  ⏭  $jpg ya convertido — skipping"
  else
    cwebp -q "$QUALITY" -quiet "$jpg" -o "$webp"
    echo "  ✅ $jpg → $webp"
  fi
  SIZE_BEFORE=$((SIZE_BEFORE + $(stat -f%z "$jpg" 2>/dev/null || stat -c%s "$jpg")))
  SIZE_AFTER=$((SIZE_AFTER + $(stat -f%z "$webp" 2>/dev/null || stat -c%s "$webp")))
  COUNT=$((COUNT + 1))
done

echo ""
echo "═══════════════════════════════════════════════"
echo "✅ TERMINADO: $COUNT frames convertidos"
echo "   Antes (JPG):  $(echo "scale=2; $SIZE_BEFORE / 1024 / 1024" | bc) MB"
echo "   Después (WebP): $(echo "scale=2; $SIZE_AFTER / 1024 / 1024" | bc) MB"
echo "   Ahorro: $(echo "scale=1; ($SIZE_BEFORE - $SIZE_AFTER) * 100 / $SIZE_BEFORE" | bc)%"
echo "═══════════════════════════════════════════════"
echo ""
echo "📋 SIGUIENTE PASO:"
echo "   1. Edita: build/landing/assets/js/sequence.js"
echo "      Cambia: const FRAME_EXT = '.jpg';"
echo "      Por:    const FRAME_EXT = '.webp';"
echo "   2. Sube via FTP los archivos *.webp a public_html/landing/assets/sequence/"
echo "   3. Sube el sequence.js modificado"
echo "   4. Hard refresh en el navegador"
