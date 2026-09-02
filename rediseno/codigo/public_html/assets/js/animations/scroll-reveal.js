/**
 * scroll-reveal.js — Reveals al scroll: stagger fade-up + parallax + drawSVG manual.
 * Auto-detecta atributos:
 *   data-reveal              → fade-up básico
 *   data-reveal="stagger"    → stagger de hijos
 *   data-reveal="parallax"   → translateY parallax sutil
 *   data-reveal="draw"       → dibuja path SVG con stroke-dasharray
 *   data-reveal-delay="0.2"  → delay extra
 */

const gsap = window.gsap;
const ScrollTrigger = window.ScrollTrigger;
if (gsap && ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

const LIQUID = 'cubic-bezier(0.22, 1, 0.36, 1)';

export function initScrollReveal(scope = document) {
  if (!gsap || !ScrollTrigger) return fallbackIO(scope);

  scope.querySelectorAll('[data-reveal]').forEach(el => {
    const mode = el.dataset.reveal || 'fade';
    const delay = parseFloat(el.dataset.revealDelay || '0');

    if (mode === 'stagger') {
      const children = el.children;
      gsap.from(children, {
        y: 24,
        opacity: 0,
        duration: 0.9,
        ease: 'power3.out',
        stagger: 0.08,
        delay,
        scrollTrigger: { trigger: el, start: 'top 80%', once: true },
      });
    } else if (mode === 'parallax') {
      gsap.to(el, {
        y: -40,
        ease: 'none',
        scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: 0.6 },
      });
    } else if (mode === 'draw') {
      el.querySelectorAll('path, line, polyline, circle, rect').forEach(p => {
        const len = p.getTotalLength?.() || 800;
        p.style.strokeDasharray = len;
        p.style.strokeDashoffset = len;
        gsap.to(p, {
          strokeDashoffset: 0,
          duration: 1.6,
          ease: 'power2.out',
          delay,
          scrollTrigger: { trigger: el, start: 'top 75%', once: true },
        });
      });
    } else {
      gsap.from(el, {
        y: 32,
        opacity: 0,
        duration: 1,
        ease: 'power3.out',
        delay,
        scrollTrigger: { trigger: el, start: 'top 85%', once: true },
      });
    }
  });
}

// Fallback sin GSAP usando IntersectionObserver
function fallbackIO(scope) {
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.opacity = '1';
        e.target.style.transform = 'none';
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.15 });

  scope.querySelectorAll('[data-reveal]').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = `opacity 800ms ${LIQUID}, transform 800ms ${LIQUID}`;
    io.observe(el);
  });
}
