/* ============================================================
   CLAUTMET · Scroll sequence engine
   ============================================================ */
(function () {
  const FRAME_COUNT = 168;
  const FRAME_PATH = '/landing/assets/sequence/ezgif-frame-';
  const FRAME_EXT = '.jpg';
  const PAD = 3;

  const canvas = document.getElementById('sequence-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d', { alpha: false });

  const frames = new Array(FRAME_COUNT);
  let loaded = 0;
  let currentFrame = 0;
  let renderedFrame = -1;

  const dpr = Math.min(window.devicePixelRatio || 1, 2);
  function resize() {
    const w = window.innerWidth;
    const h = window.innerHeight;
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    renderedFrame = -1;
    drawFrame(currentFrame);
  }

  function drawFrame(i) {
    const idx = Math.max(0, Math.min(FRAME_COUNT - 1, Math.round(i)));
    const img = frames[idx];
    if (!img || !img.complete || !img.naturalWidth) {
      for (let j = idx; j >= 0; j--) {
        if (frames[j] && frames[j].complete && frames[j].naturalWidth) {
          paint(frames[j]); renderedFrame = j; return;
        }
      }
      return;
    }
    if (idx === renderedFrame) return;
    paint(img);
    renderedFrame = idx;
  }

  function paint(img) {
    const cw = canvas.clientWidth;
    const ch = canvas.clientHeight;
    const bg = getComputedStyle(document.documentElement).getPropertyValue('--bg-0').trim() || '#07080A';
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, cw, ch);
    const ir = img.width / img.height;
    const cr = cw / ch;
    const zoom = 1.05;
    let dw, dh, dx, dy;
    if (cr > ir) { dw = cw * zoom; dh = dw / ir; }
    else { dh = ch * zoom; dw = dh * ir; }
    dx = (cw - dw) / 2;
    dy = (ch - dh) / 2;
    ctx.drawImage(img, dx, dy, dw, dh);
  }

  function preload() {
    for (let i = 0; i < FRAME_COUNT; i++) {
      const img = new Image();
      img.decoding = 'async';
      img.src = FRAME_PATH + String(i + 1).padStart(PAD, '0') + FRAME_EXT;
      img.onload = () => {
        loaded++;
        if (i === 0) drawFrame(0);
      };
      frames[i] = img;
    }
  }

  let ticking = false;
  function onScroll() {
    if (!ticking) { requestAnimationFrame(updateFrame); ticking = true; }
  }
  function updateFrame() {
    ticking = false;
    const stage = document.querySelector('.stage');
    if (!stage) return;
    const r = stage.getBoundingClientRect();
    const stageH = stage.offsetHeight;
    const vh = window.innerHeight;
    const scrolled = -r.top;
    const total = stageH - vh;
    let p = total > 0 ? scrolled / total : 0;
    p = Math.max(0, Math.min(1, p));
    currentFrame = p * (FRAME_COUNT - 1);
    drawFrame(currentFrame);

    // Fade canvas as we scroll past the stage
    const stageEnd = r.bottom;
    let alpha = 1;
    if (stageEnd < vh) {
      alpha = Math.max(0, stageEnd / vh);
    }
    canvas.style.opacity = String(alpha);
  }

  preload();
  resize();
  updateFrame();
  window.addEventListener('resize', resize, { passive: true });
  window.addEventListener('scroll', onScroll, { passive: true });
})();
