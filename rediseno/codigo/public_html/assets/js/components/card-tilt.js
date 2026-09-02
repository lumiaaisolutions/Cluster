/**
 * card-tilt.js — Tilt 3D + lift para cards. Auto-init [data-tilt].
 * Requiere VanillaTilt cargado via CDN: window.VanillaTilt.
 */

export function initCardTilt(scope = document) {
  if (!window.VanillaTilt) return;
  const els = scope.querySelectorAll('[data-tilt]');
  if (!els.length) return;
  // Evitar double-init
  els.forEach(el => { if (el.vanillaTilt) el.vanillaTilt.destroy(); });
  window.VanillaTilt.init(els, {
    max: 6,
    speed: 600,
    perspective: 1500,
    scale: 1.02,
    glare: false,
    'max-glare': 0,
    reset: true,
    'reset-to-start': true,
    transition: true,
    easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
  });
}

/**
 * Magnetic — efecto que atrae el cursor a CTAs.
 * Uso: <button data-magnetic data-magnetic-radius="80">...</button>
 */
export function initMagnetic(scope = document) {
  scope.querySelectorAll('[data-magnetic]').forEach(el => {
    if (el._magneticAttached) return;
    el._magneticAttached = true;
    const radius = parseFloat(el.dataset.magneticRadius || '80');
    const strength = parseFloat(el.dataset.magneticStrength || '0.35');

    el.addEventListener('mousemove', e => {
      const r = el.getBoundingClientRect();
      const cx = r.left + r.width / 2;
      const cy = r.top + r.height / 2;
      const dx = e.clientX - cx;
      const dy = e.clientY - cy;
      const dist = Math.hypot(dx, dy);
      if (dist > radius) return;
      el.style.transform = `translate(${dx * strength}px, ${dy * strength}px)`;
    });
    el.addEventListener('mouseleave', () => {
      el.style.transform = '';
    });
  });
}
