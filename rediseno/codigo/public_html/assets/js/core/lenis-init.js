/**
 * lenis-init.js — Smooth scroll global + sync con GSAP ScrollTrigger.
 * Lenis se carga via CDN <script>; aquí asumimos window.Lenis disponible.
 */

let _lenis = null;

export function initLenis({ disableOn = '.no-smooth' } = {}) {
  if (typeof window === 'undefined' || !window.Lenis) return null;
  if (_lenis) return _lenis;

  _lenis = new window.Lenis({
    duration: 1.1,
    easing: t => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
    smoothWheel: true,
    smoothTouch: false,
    touchMultiplier: 1.5,
  });

  function raf(time) {
    _lenis.raf(time);
    requestAnimationFrame(raf);
  }
  requestAnimationFrame(raf);

  // Sync con GSAP ScrollTrigger
  if (window.ScrollTrigger) {
    _lenis.on('scroll', window.ScrollTrigger.update);
    window.gsap?.ticker.add((time) => _lenis.raf(time * 1000));
    window.gsap?.ticker.lagSmoothing(0);
  }

  // Permitir desactivar en zonas con scroll nativo (modales con overflow)
  document.addEventListener('mouseover', e => {
    const target = e.target.closest(disableOn);
    if (target) _lenis.stop();
    else _lenis.start();
  });

  return _lenis;
}

export function getLenis() { return _lenis; }
