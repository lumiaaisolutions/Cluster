/**
 * color-invert.js — Inversión de colores light→dark al scroll en hero (D1).
 * Solo activo en light mode. En dark mode no hace nada.
 */

const gsap = window.gsap;
const ScrollTrigger = window.ScrollTrigger;
if (gsap && ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

export function initColorInvert(triggerSelector) {
  if (!gsap || !ScrollTrigger) return;
  const trigger = document.querySelector(triggerSelector);
  if (!trigger) return;

  let st = null;

  function attach() {
    if (st) { st.kill(); st = null; }
    if (document.documentElement.getAttribute('data-theme') !== 'light') return;
    if (document.documentElement.dataset.colorInvert === '0') return;

    st = ScrollTrigger.create({
      trigger,
      start: 'top top',
      end: 'bottom top',
      scrub: 0.6,
      onUpdate: self => {
        const t = self.progress;
        // light #FFFFFF -> dark #0A0A0B
        const bg = lerpHex('#FFFFFF', '#0A0A0B', t);
        const tx = lerpHex('#1D1D1F', '#F2F2F2', t);
        document.documentElement.style.setProperty('--bg', bg);
        document.documentElement.style.setProperty('--text', tx);
      },
    });
  }

  attach();
  document.addEventListener('themechange', attach);
}

function lerpHex(a, b, t) {
  const ah = parseInt(a.slice(1), 16);
  const bh = parseInt(b.slice(1), 16);
  const ar = (ah >> 16) & 0xff, ag = (ah >> 8) & 0xff, ab = ah & 0xff;
  const br = (bh >> 16) & 0xff, bg = (bh >> 8) & 0xff, bb = bh & 0xff;
  const r = Math.round(ar + (br - ar) * t);
  const g = Math.round(ag + (bg - ag) * t);
  const bl = Math.round(ab + (bb - ab) * t);
  return '#' + ((1 << 24) + (r << 16) + (g << 8) + bl).toString(16).slice(1);
}
