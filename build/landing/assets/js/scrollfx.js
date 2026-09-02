/* ============================================================
   CLAUTMET · Scroll-driven motion polish
   Adds: progress bar, smart reveals (direction-aware), parallax,
   word-by-word title reveals, marquee-on-scroll-velocity,
   and inertia-feel for cards.
   ============================================================ */
(function () {
  if (window.__claut_scrollfx_inited) return;
  window.__claut_scrollfx_inited = true;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Scroll progress bar ---------- */
  let progress = document.querySelector('.scroll-progress');
  if (!progress) {
    progress = document.createElement('div');
    progress.className = 'scroll-progress';
    document.body.appendChild(progress);
  }

  function setProgress() {
    const h = document.documentElement;
    const max = h.scrollHeight - h.clientHeight;
    const p = max > 0 ? (window.scrollY / max) * 100 : 0;
    progress.style.setProperty('--p', p.toFixed(2) + '%');
  }
  window.addEventListener('scroll', setProgress, { passive: true });
  window.addEventListener('resize', setProgress, { passive: true });
  setProgress();

  if (reduceMotion) return;

  /* ---------- Auto-tag elements that should animate on scroll ---------- */
  // Add data-reveal to anything that doesn't have one yet, in important content areas
  const autoTargets = [
    '.stat',
    '.benefit',
    '.sector-card',
    '.evento',
    '.post',
    '.faq details',
    '.cert',
    '.logo-cell',
    '.clautmet-cell',
    '.zona',
    '.state-card',
    '.section-head > div',
  ];
  autoTargets.forEach(sel => {
    document.querySelectorAll(sel).forEach((el, i) => {
      if (!el.hasAttribute('data-reveal') && !el.hasAttribute('data-reveal-fx')) {
        el.setAttribute('data-reveal-fx', '');
        el.style.setProperty('--rd', (Math.min(i, 8) * 0.05) + 's');
      }
    });
  });

  /* Direction-aware reveals: assign random/contextual fx based on position */
  document.querySelectorAll('[data-reveal-fx]').forEach((el, i) => {
    const r = el.getBoundingClientRect();
    const cx = r.left + r.width / 2;
    const w = window.innerWidth;
    let dir = 'up';
    if (el.matches('.evento')) dir = 'left';
    else if (el.matches('.post')) dir = 'up';
    else if (el.matches('.benefit, .sector-card, .stat, .cert, .logo-cell, .clautmet-cell, .zona, .state-card')) {
      // fan effect: cards on the left slide in from left, right from right
      dir = cx < w * 0.45 ? 'left' : (cx > w * 0.55 ? 'right' : 'up');
    } else if (el.matches('.faq details')) dir = 'left';
    el.setAttribute('data-fx-dir', dir);
  });

  /* ---------- IntersectionObserver: stagger groups ---------- */
  const fxIO = new IntersectionObserver((entries) => {
    // sort entries by Y to give a reliable top-down stagger
    entries.filter(e => e.isIntersecting).forEach(e => {
      e.target.classList.add('fx-in');
      fxIO.unobserve(e.target);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
  document.querySelectorAll('[data-reveal-fx]').forEach(el => fxIO.observe(el));

  /* ---------- Word-by-word reveal for big display titles ---------- */
  document.querySelectorAll('.section-head .display, .hero .display').forEach(h => {
    if (h.dataset.split === 'done') return;
    // Walk children: split text nodes only, preserve <br>, <em>, <span>, etc.
    const walk = (node) => {
      const kids = Array.from(node.childNodes);
      kids.forEach(child => {
        if (child.nodeType === Node.TEXT_NODE) {
          const text = child.textContent;
          if (!text.trim()) return;
          const frag = document.createDocumentFragment();
          const parts = text.split(/(\s+)/);
          parts.forEach(part => {
            if (/^\s+$/.test(part)) {
              frag.appendChild(document.createTextNode(part));
            } else if (part) {
              const w = document.createElement('span');
              w.className = 'fx-word';
              w.textContent = part;
              frag.appendChild(w);
            }
          });
          child.parentNode.replaceChild(frag, child);
        } else if (child.nodeType === Node.ELEMENT_NODE && child.tagName !== 'BR') {
          walk(child);
        }
      });
    };
    walk(h);
    h.dataset.split = 'done';
    // stagger
    h.querySelectorAll('.fx-word').forEach((w, i) => {
      w.style.setProperty('--wi', i);
    });
  });

  const wordIO = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('fx-words-in');
        wordIO.unobserve(e.target);
      }
    });
  }, { threshold: 0.25 });
  document.querySelectorAll('.section-head .display, .hero .display').forEach(h => wordIO.observe(h));

  /* ---------- Parallax on scroll: gentle Y translate for hero, kicker, etc ---------- */
  const parallaxNodes = [];
  document.querySelectorAll('.hero-side, .hero-stack, .footer-mega, .clautmet-lead, .states-stack, .cta-pillars').forEach(el => {
    const speed = el.matches('.hero-side, .clautmet-lead, .states-stack') ? -0.08 : 0.05;
    parallaxNodes.push({ el, speed });
  });

  let lastY = window.scrollY;
  let raf = null;
  function tickParallax() {
    raf = null;
    const y = window.scrollY;
    parallaxNodes.forEach(({ el, speed }) => {
      const r = el.getBoundingClientRect();
      const vh = window.innerHeight;
      // only when in viewport
      if (r.bottom < -200 || r.top > vh + 200) return;
      const center = r.top + r.height / 2;
      const offset = (center - vh / 2) * speed;
      el.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)`;
    });
    lastY = y;
  }
  function onParallaxScroll() {
    if (!raf) raf = requestAnimationFrame(tickParallax);
  }
  window.addEventListener('scroll', onParallaxScroll, { passive: true });
  tickParallax();

  /* ---------- Section tilt on scroll velocity ----------
     When user scrolls fast, sections compress slightly (squash & stretch) */
  const velocityNodes = document.querySelectorAll('.section, .hero, .cta-section');
  let lastSY = window.scrollY;
  let lastTS = performance.now();
  let velocityRaf = null;
  function tickVelocity() {
    velocityRaf = null;
    const now = performance.now();
    const dt = Math.max(8, now - lastTS);
    const dy = window.scrollY - lastSY;
    const v = Math.max(-1, Math.min(1, dy / dt / 2.4));
    lastSY = window.scrollY;
    lastTS = now;
    document.documentElement.style.setProperty('--scroll-v', v.toFixed(3));
    // decay
    if (Math.abs(v) > 0.01) requestAnimationFrame(() => {
      document.documentElement.style.setProperty('--scroll-v', '0');
    });
  }
  window.addEventListener('scroll', () => {
    if (!velocityRaf) velocityRaf = requestAnimationFrame(tickVelocity);
  }, { passive: true });

  /* ---------- 3D lift on cards as they cross viewport center ---------- */
  const liftCards = document.querySelectorAll('.benefit, .sector-card, .post, .evento, .state-card');
  let liftRaf = null;
  function tickLift() {
    liftRaf = null;
    const vh = window.innerHeight;
    const mid = vh / 2;
    liftCards.forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.bottom < 0 || r.top > vh) {
        el.style.removeProperty('--liftY');
        return;
      }
      const c = r.top + r.height / 2;
      // distance from middle, normalized
      const d = (c - mid) / vh; // -0.5..0.5
      const lift = -Math.abs(d) * 8 + 4; // slight rise as it nears center
      el.style.setProperty('--liftY', lift.toFixed(1) + 'px');
    });
  }
  window.addEventListener('scroll', () => {
    if (!liftRaf) liftRaf = requestAnimationFrame(tickLift);
  }, { passive: true });
  tickLift();

  /* ---------- Hero subtle parallax on mouse move (desktop) ---------- */
  const hero = document.querySelector('.hero');
  if (hero && window.matchMedia('(pointer: fine)').matches) {
    hero.addEventListener('mousemove', (e) => {
      const r = hero.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5;
      const y = (e.clientY - r.top) / r.height - 0.5;
      hero.style.setProperty('--mx-h', (x * 8).toFixed(1) + 'px');
      hero.style.setProperty('--my-h', (y * 8).toFixed(1) + 'px');
    });
    hero.addEventListener('mouseleave', () => {
      hero.style.setProperty('--mx-h', '0px');
      hero.style.setProperty('--my-h', '0px');
    });
  }
})();
