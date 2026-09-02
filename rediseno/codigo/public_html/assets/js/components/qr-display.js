/**
 * qr-display.js — Generación de QR sin librerías externas pesadas.
 * Usa qrcode-generator CDN: window.qrcode.
 */

export function renderQR(targetEl, text, { size = 256, level = 'M', dark = '#0A0A0B', light = '#FFFFFF' } = {}) {
  if (!window.qrcode) {
    console.warn('[qr] window.qrcode no disponible. Cargar CDN qrcode-generator.');
    return;
  }
  const typeNumber = 0; // auto
  const qr = window.qrcode(typeNumber, level);
  qr.addData(text);
  qr.make();

  const cells = qr.getModuleCount();
  const cellSize = Math.floor(size / cells);
  const totalSize = cellSize * cells;

  const canvas = document.createElement('canvas');
  canvas.width = totalSize;
  canvas.height = totalSize;
  canvas.style.width = totalSize + 'px';
  canvas.style.height = totalSize + 'px';
  const ctx = canvas.getContext('2d');

  ctx.fillStyle = light;
  ctx.fillRect(0, 0, totalSize, totalSize);
  ctx.fillStyle = dark;

  for (let r = 0; r < cells; r++) {
    for (let c = 0; c < cells; c++) {
      if (qr.isDark(r, c)) {
        ctx.fillRect(c * cellSize, r * cellSize, cellSize, cellSize);
      }
    }
  }

  targetEl.innerHTML = '';
  targetEl.appendChild(canvas);
  return canvas;
}
