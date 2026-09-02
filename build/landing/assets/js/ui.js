/* ============================================================
   CLAUTMET · UI interactions
   ============================================================ */
(function () {
  // Year
  const y = document.getElementById('year');
  if (y) y.textContent = new Date().getFullYear();

  // Nav scrolled state
  const nav = document.querySelector('.nav');
  function onScrollNav() {
    if (window.scrollY > 20) nav.classList.add('scrolled');
    else nav.classList.remove('scrolled');
  }
  window.addEventListener('scroll', onScrollNav, { passive: true });
  onScrollNav();

  // Hero in
  setTimeout(() => {
    const hero = document.querySelector('.hero');
    if (hero) hero.classList.add('in');
  }, 80);

  // Reveal
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('in');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });
  document.querySelectorAll('[data-reveal], [data-reveal-mask]').forEach(el => io.observe(el));

  // Counters
  const counterIO = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (!e.isIntersecting) return;
      const el = e.target;
      const target = parseFloat(el.dataset.counter);
      const dur = 1800;
      const start = performance.now();
      function tick(now) {
        const t = Math.min(1, (now - start) / dur);
        const eased = 1 - Math.pow(1 - t, 3);
        const v = target * eased;
        const decimals = (target % 1 !== 0) ? 1 : 0;
        el.textContent = v.toFixed(decimals);
        if (t < 1) requestAnimationFrame(tick);
        else el.textContent = target.toString();
      }
      requestAnimationFrame(tick);
      counterIO.unobserve(el);
    });
  }, { threshold: 0.4 });
  document.querySelectorAll('[data-counter]').forEach(el => counterIO.observe(el));

  // Magnetic CTAs
  document.querySelectorAll('[data-magnetic]').forEach(el => {
    el.addEventListener('mousemove', (e) => {
      const r = el.getBoundingClientRect();
      const x = e.clientX - r.left - r.width / 2;
      const y = e.clientY - r.top - r.height / 2;
      el.style.transform = `translate(${x * 0.18}px, ${y * 0.22}px)`;
    });
    el.addEventListener('mouseleave', () => { el.style.transform = ''; });
  });

  // Sector card mouse glow
  document.querySelectorAll('.sector-card').forEach(el => {
    el.addEventListener('mousemove', (e) => {
      const r = el.getBoundingClientRect();
      el.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
      el.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
    });
  });

  // Tilt cards
  document.querySelectorAll('[data-tilt]').forEach(el => {
    el.style.transformStyle = 'preserve-3d';
    el.addEventListener('mousemove', (e) => {
      const r = el.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5;
      const y = (e.clientY - r.top) / r.height - 0.5;
      el.style.transform = `perspective(1200px) rotateX(${-y * 4}deg) rotateY(${x * 5}deg) translateY(-2px)`;
    });
    el.addEventListener('mouseleave', () => { el.style.transform = ''; });
  });

  // Sectores filter + search
  const search = document.getElementById('sector-search');
  const chips = document.querySelectorAll('.chip[data-filter]');
  function applyFilter() {
    const q = (search ? search.value : '').toLowerCase().trim();
    const active = document.querySelector('.chip.active');
    const f = active ? active.dataset.filter : 'all';
    document.querySelectorAll('.sector-card').forEach(c => {
      const name = c.querySelector('.name').textContent.toLowerCase();
      const cat = c.dataset.cat || 'all';
      const matchQ = !q || name.includes(q);
      const matchF = f === 'all' || cat === f;
      c.style.display = (matchQ && matchF) ? '' : 'none';
    });
  }
  chips.forEach(ch => ch.addEventListener('click', () => {
    chips.forEach(c => c.classList.remove('active'));
    ch.classList.add('active');
    applyFilter();
  }));
  if (search) search.addEventListener('input', applyFilter);

  // Mapa zonas — sync between zona list (right) and state-card stack (left)
  const zonas = document.querySelectorAll('.zona');
  const stateCards = document.querySelectorAll('.state-card');
  function setActiveZone(id) {
    zonas.forEach(o => o.classList.toggle('active', o.dataset.zona === id));
    stateCards.forEach(o => o.classList.toggle('active', o.dataset.zona === id));
  }
  zonas.forEach(z => {
    z.addEventListener('click', () => setActiveZone(z.dataset.zona));
  });
  stateCards.forEach(s => {
    s.addEventListener('click', () => setActiveZone(s.dataset.zona));
    s.addEventListener('mouseenter', () => setActiveZone(s.dataset.zona));
  });

  // FAQ — close others on open
  document.querySelectorAll('.faq details').forEach(d => {
    d.addEventListener('toggle', () => {
      if (d.open) {
        document.querySelectorAll('.faq details').forEach(o => { if (o !== d) o.open = false; });
      }
    });
  });

  // Form submit (PHP target: contacto.php)
  const form = document.getElementById('contact-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const success = document.getElementById('form-success');
      if (success) {
        success.classList.add('show');
        success.textContent = 'Gracias. Tu solicitud fue recibida — te contactaremos en menos de 24 horas.';
      }
      form.reset();
      // PHP integration: replace with fetch('/api/contacto.php', { method:'POST', body: new FormData(form) })
    });
  }

  // Theme toggle
  const themeBtn = document.getElementById('theme-toggle');
  function applyTheme(t) {
    document.documentElement.dataset.theme = t;
    try { localStorage.setItem('claut-theme', t); } catch(e){}
  }
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const cur = document.documentElement.dataset.theme || 'dark';
      applyTheme(cur === 'dark' ? 'light' : 'dark');
    });
  }
  try {
    const saved = localStorage.getItem('claut-theme');
    if (saved) applyTheme(saved);
    else applyTheme('dark');
  } catch(e){ applyTheme('dark'); }

  // Lang toggle (i18n)
  const langBtn = document.getElementById('lang-toggle');
  function applyLang(lang) {
    document.documentElement.lang = lang;
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const k = el.dataset.i18n;
      const v = (window.I18N && window.I18N[lang] && window.I18N[lang][k]);
      if (v != null) el.innerHTML = v;
    });
    document.querySelectorAll('[data-i18n-attr]').forEach(el => {
      const pair = el.dataset.i18nAttr.split(':');
      const attr = pair[0]; const k = pair[1];
      const v = (window.I18N && window.I18N[lang] && window.I18N[lang][k]);
      if (v != null) el.setAttribute(attr, v);
    });
    const cur = document.querySelector('[data-lang-current]');
    if (cur) cur.textContent = lang.toUpperCase();
    try { localStorage.setItem('claut-lang', lang); } catch(e){}
  }
  if (langBtn) {
    langBtn.addEventListener('click', () => {
      const cur = document.documentElement.lang || 'es';
      applyLang(cur === 'es' ? 'en' : 'es');
    });
  }
  try {
    const saved = localStorage.getItem('claut-lang') || 'es';
    applyLang(saved);
  } catch(e){ applyLang('es'); }

  // ============================================================
  // Menú overlay full-screen (hamburguesa)
  // ============================================================
  const menuToggle = document.getElementById('menu-toggle');
  const menuClose = document.getElementById('menu-close');
  const menuOverlay = document.getElementById('menu-overlay');
  const html = document.documentElement;
  let lastFocused = null;

  function openMenu() {
    lastFocused = document.activeElement;
    html.classList.add('menu-open');
    menuToggle.setAttribute('aria-expanded', 'true');
    menuClose.focus();
  }
  function closeMenu() {
    html.classList.remove('menu-open');
    menuToggle.setAttribute('aria-expanded', 'false');
    if (lastFocused) lastFocused.focus();
  }
  function toggleMenu() {
    html.classList.contains('menu-open') ? closeMenu() : openMenu();
  }

  if (menuToggle && menuOverlay) {
    menuToggle.addEventListener('click', toggleMenu);
    if (menuClose) menuClose.addEventListener('click', closeMenu);

    // Cerrar al elegir un destino
    menuOverlay.querySelectorAll('[data-menu-link]').forEach(a => {
      a.addEventListener('click', closeMenu);
    });

    // Escape para cerrar + trampa de foco básica (Tab)
    document.addEventListener('keydown', (e) => {
      if (!html.classList.contains('menu-open')) return;
      if (e.key === 'Escape') { closeMenu(); return; }
      if (e.key === 'Tab') {
        const focusables = menuOverlay.querySelectorAll('a[href], button:not([disabled])');
        if (!focusables.length) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault(); first.focus();
        }
      }
    });
  }

})();
