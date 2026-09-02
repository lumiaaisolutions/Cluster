/**
 * hero-sequence.js — Image sequence scrubbing estilo Apple/Porsche.
 * Pinta frames WebP sobre <canvas> mapeando scroll → frame index.
 *
 * Detección automática:
 *   - saveData / 2g / 3g → fallback a imagen estática (cover.webp)
 *   - frames faltantes / 404 → fallback Lottie
 *
 * Uso:
 *   <div class="hero-canvas-wrap" style="height:100vh">
 *     <canvas id="hero-canvas" class="w-full h-full"></canvas>
 *   </div>
 *   <script type="module">
 *     import { initHeroSequence } from '/assets/js/animations/hero-sequence.js';
 *     initHeroSequence({ canvasId: 'hero-canvas', basePath: '/assets/img/sequences/hero', frameCount: 60 });
 *   </script>
 */

const gsap = window.gsap;
const ScrollTrigger = window.ScrollTrigger;
if (gsap && ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

function isLowConnection() {
  const c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  if (!c) return false;
  if (c.saveData) return true;
  return ['slow-2g', '2g', '3g'].includes(c.effectiveType);
}

function showStaticFallback(wrap, basePath, lottiePath) {
  const cover = `${basePath}/cover.webp`;
  // Probamos cover.webp; si 404, usamos Lottie
  const img = new Image();
  img.onload = () => {
    wrap.innerHTML = `<img src="${cover}" alt="" class="w-full h-full object-cover" loading="eager">`;
  };
  img.onerror = () => {
    if (lottiePath && window.lottie) {
      wrap.innerHTML = '';
      window.lottie.loadAnimation({
        container: wrap,
        renderer: 'svg',
        loop: true,
        autoplay: true,
        path: lottiePath,
      });
    } else {
      wrap.innerHTML = `<div class="w-full h-full bg-gradient-to-br from-bg via-surface to-bg"></div>`;
    }
  };
  img.src = cover;
}

export async function initHeroSequence({
  canvasId = 'hero-canvas',
  basePath = '/assets/img/sequences/hero',
  frameCount = 60,
  ext = 'webp',
  pinDuration = '+=200%',
  fallbackLottie = '/assets/lottie/hero-placeholder.json',
} = {}) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const wrap = canvas.parentElement;

  if (isLowConnection() || !gsap || !ScrollTrigger) {
    showStaticFallback(wrap, basePath, fallbackLottie);
    return;
  }

  const ctx = canvas.getContext('2d', { alpha: false });
  const dpr = Math.min(window.devicePixelRatio || 1, 2);

  function resize() {
    const r = wrap.getBoundingClientRect();
    canvas.width = r.width * dpr;
    canvas.height = r.height * dpr;
    canvas.style.width = r.width + 'px';
    canvas.style.height = r.height + 'px';
  }
  resize();
  window.addEventListener('resize', () => { resize(); render(state.frame); });

  const images = new Array(frameCount);
  let firstFrameReady = false;
  let loadedCount = 0;
  let failedCount = 0;

  const promises = [];
  for (let i = 1; i <= frameCount; i++) {
    promises.push(new Promise(resolve => {
      const img = new Image();
      img.decoding = 'async';
      img.src = `${basePath}/${String(i).padStart(4, '0')}.${ext}`;
      img.onload = () => {
        loadedCount++;
        if (i === 1) {
          firstFrameReady = true;
          render(0);
        }
        resolve();
      };
      img.onerror = () => {
        failedCount++;
        resolve();
      };
      images[i - 1] = img;
    }));
  }

  // Si después de 3s ningún frame cargó, usar fallback
  setTimeout(() => {
    if (loadedCount === 0) {
      showStaticFallback(wrap, basePath, fallbackLottie);
    }
  }, 3000);

  await Promise.allSettled(promises);

  if (loadedCount < Math.max(10, frameCount * 0.3)) {
    showStaticFallback(wrap, basePath, fallbackLottie);
    return;
  }

  const state = { frame: 0 };

  function render(idx) {
    const i = Math.min(frameCount - 1, Math.max(0, Math.round(idx)));
    const img = images[i];
    if (!img || !img.complete || !img.naturalWidth) return;
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim() || '#FFFFFF';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    // cover
    const r = Math.max(canvas.width / img.naturalWidth, canvas.height / img.naturalHeight);
    const w = img.naturalWidth * r, h = img.naturalHeight * r;
    ctx.drawImage(img, (canvas.width - w) / 2, (canvas.height - h) / 2, w, h);
  }

  gsap.to(state, {
    frame: frameCount - 1,
    ease: 'none',
    scrollTrigger: {
      trigger: wrap,
      start: 'top top',
      end: pinDuration,
      scrub: 0.5,
      pin: true,
      pinSpacing: true,
      anticipatePin: 1,
    },
    onUpdate: () => render(state.frame),
  });

  if (firstFrameReady) render(0);
}
