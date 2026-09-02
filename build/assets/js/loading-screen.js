/**
 * CLAUT LOADING SCREEN — loading-screen.js v3
 * Ripple effect: rings expand from center outward.
 * Dark pale theme · #C7252B · No scroll · No image border.
 */
(function () {
  'use strict';

  var LOGO_URL = '/assets/img/apple-icon.png';
  var MIN_SHOW = 900;
  var NAV_SHOW = 500;

  /* ============================================================
     CSS
     ============================================================ */
  var css = `
    /* Bloquear scroll mientras el loader está activo */
    body.claut-loading {
      overflow: hidden !important;
    }

    /* ── OVERLAY ── */
    #claut-loader {
      position: fixed;
      inset: 0;
      z-index: 2147483647;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #160c0e;
      opacity: 1;
      transition: opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1);
      pointer-events: all;
      overflow: hidden;
    }
    #claut-loader.claut-loader-hiding { opacity: 0; pointer-events: none; }
    #claut-loader.claut-loader-hidden { display: none !important; }

    /* Gradiente radial ambiental de fondo */
    #claut-loader::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse 70% 55% at 50% 50%, rgba(199,37,43,0.14) 0%, transparent 65%),
        radial-gradient(ellipse 40% 30% at 15% 95%,  rgba(199,37,43,0.07) 0%, transparent 60%),
        radial-gradient(ellipse 30% 30% at 88% 10%,  rgba(199,37,43,0.05) 0%, transparent 55%);
      pointer-events: none;
    }

    /* ── STAGE — centro de la pantalla ── */
    #claut-loader-stage {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 200px;
      height: 200px;
    }

    /* ── LOGO — sin contorno ni borde ── */
    #claut-loader-logo {
      width: 120px;
      height: 120px;
      object-fit: contain;
      object-position: center;
      position: relative;
      z-index: 10;
      border: none !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      outline: none !important;
      filter: drop-shadow(0 0 24px rgba(199,37,43,0.55));
      animation: claut-logo-breathe 2.8s ease-in-out infinite;
    }
    @media (max-width: 640px) {
      #claut-loader-stage { width: 160px; height: 160px; }
      #claut-loader-logo  { width: 92px;  height: 92px; }
    }
    @keyframes claut-logo-breathe {
      0%, 100% { filter: drop-shadow(0 0 16px rgba(199,37,43,0.40)); transform: scale(1);    }
      50%       { filter: drop-shadow(0 0 36px rgba(199,37,43,0.78)); transform: scale(1.05); }
    }

    /* ── RIPPLE RINGS — se expanden desde el centro ── */
    .claut-ripple {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 140px;
      height: 140px;
      border-radius: 50%;
      border: 2px solid rgba(199, 37, 43, 0.8);
      opacity: 0;
      pointer-events: none;
    }
    @media (max-width: 640px) {
      .claut-ripple { width: 110px; height: 110px; }
    }

    /* 4 anillos con delays escalonados para efecto continuo */
    .claut-ripple-1 { animation: claut-ripple 3s cubic-bezier(0.1, 0.8, 0.25, 1) 0s    infinite; }
    .claut-ripple-2 { animation: claut-ripple 3s cubic-bezier(0.1, 0.8, 0.25, 1) 0.75s infinite; border-color: rgba(199,37,43,0.55); }
    .claut-ripple-3 { animation: claut-ripple 3s cubic-bezier(0.1, 0.8, 0.25, 1) 1.5s  infinite; border-color: rgba(199,37,43,0.35); }
    .claut-ripple-4 { animation: claut-ripple 3s cubic-bezier(0.1, 0.8, 0.25, 1) 2.25s infinite; border-color: rgba(199,37,43,0.18); }

    @keyframes claut-ripple {
      0%   { width: 140px; height: 140px; opacity: 1;   }
      100% { width: 480px; height: 480px; opacity: 0;   }
    }

    /* ── TEXTO ── */
    #claut-loader-text {
      position: absolute;
      bottom: -60px;
      left: 50%;
      transform: translateX(-50%);
      white-space: nowrap;
      font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Inter', sans-serif;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.3);
      animation: claut-blink 1.8s ease-in-out infinite;
    }
    @keyframes claut-blink {
      0%, 100% { opacity: 0.3; }
      50%       { opacity: 0.65; }
    }
  `;

  /* ============================================================
     HTML
     ============================================================ */
  var html = `
    <div id="claut-loader" role="status" aria-label="Cargando">
      <div id="claut-loader-stage">

        <!-- Ripple rings (expand from center outward) -->
        <div class="claut-ripple claut-ripple-1"></div>
        <div class="claut-ripple claut-ripple-2"></div>
        <div class="claut-ripple claut-ripple-3"></div>
        <div class="claut-ripple claut-ripple-4"></div>

        <!-- Logo sin contorno -->
        <img id="claut-loader-logo" src="${LOGO_URL}" alt="Clúster" draggable="false">

        <!-- Texto -->
        <span id="claut-loader-text">Cargando</span>
      </div>
    </div>
  `;

  /* ============================================================
     INYECCIÓN CSS / HTML
     ============================================================ */
  function injectCSS() {
    if (document.getElementById('claut-loader-css')) return;
    var s = document.createElement('style');
    s.id = 'claut-loader-css';
    s.textContent = css;
    document.head.appendChild(s);
  }

  function injectHTML() {
    if (document.getElementById('claut-loader')) return;
    var div = document.createElement('div');
    div.innerHTML = html.trim();
    document.body.insertBefore(div.firstChild, document.body.firstChild);
  }

  /* ============================================================
     CONTROL DE SCROLL
     ============================================================ */
  function lockScroll() { document.body.classList.add('claut-loading'); }
  function unlockScroll() { document.body.classList.remove('claut-loading'); }

  /* ============================================================
     SHOW / HIDE
     ============================================================ */
  var _showTime = 0;
  var _hideTimer = null;

  function showLoader(text) {
    var loader = document.getElementById('claut-loader');
    if (!loader) return;
    var label = document.getElementById('claut-loader-text');
    if (label && text) label.textContent = text;
    loader.classList.remove('claut-loader-hiding', 'claut-loader-hidden');
    lockScroll();
    _showTime = Date.now();
    clearTimeout(_hideTimer);
  }

  function hideLoader() {
    var loader = document.getElementById('claut-loader');
    if (!loader) return;
    var elapsed = Date.now() - _showTime;
    var wait = Math.max(0, MIN_SHOW - elapsed);
    _hideTimer = setTimeout(function () {
      loader.classList.add('claut-loader-hiding');
      loader.addEventListener('transitionend', function onEnd() {
        loader.classList.add('claut-loader-hidden');
        loader.removeEventListener('transitionend', onEnd);
        unlockScroll();
      }, { once: true });
      setTimeout(unlockScroll, 800); // fallback
    }, wait);
  }

  /* ============================================================
     INTERCEPCIÓN DE NAVEGACIÓN
     ============================================================ */
  function isInternal(href) {
    if (!href) return false;
    if (href.startsWith('#') || href.startsWith('javascript') ||
      href.startsWith('mailto:') || href.startsWith('tel:')) return false;
    try {
      var url = new URL(href, window.location.href);
      return url.hostname === window.location.hostname ||
        !!href.match(/\.html(\?|#|$)/i) ||
        (!href.startsWith('http') && !href.startsWith('//'));
    } catch (e) { return false; }
  }

  function interceptLinks() {
    document.addEventListener('click', function (e) {
      var el = e.target.closest('a[href]');
      if (!el) return;
      var href = el.getAttribute('href');
      if (!isInternal(href)) return;
      if (el.target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey) return;
      showLoader('Cargando');
    }, true);
  }

  function interceptHistory() {
    var origPush = history.pushState;
    history.pushState = function () {
      showLoader('Cargando');
      var r = origPush.apply(this, arguments);
      setTimeout(hideLoader, NAV_SHOW);
      return r;
    };
    window.addEventListener('popstate', function () {
      showLoader('Cargando');
      setTimeout(hideLoader, NAV_SHOW + 200);
    });
  }

  /* ============================================================
     TEXTO ANIMADO
     ============================================================ */
  function animateText() {
    var dots = 0;
    setInterval(function () {
      var l = document.getElementById('claut-loader');
      var label = document.getElementById('claut-loader-text');
      if (!l || !label || l.classList.contains('claut-loader-hidden')) return;
      dots = (dots + 1) % 4;
      label.textContent = 'Cargando' + '.'.repeat(dots);
    }, 400);
  }

  /* ============================================================
     INIT
     ============================================================ */
  function init() {
    injectCSS();
    injectHTML();
    showLoader('Cargando');
    interceptLinks();
    interceptHistory();
    animateText();
    if (document.readyState === 'complete') {
      hideLoader();
    } else {
      window.addEventListener('load', hideLoader);
      setTimeout(hideLoader, 6000);
    }
  }

  // Si el <body> tiene data-skip-initial-loader, NO se muestra al cargar la página
  // (solo en transiciones via click). Útil para landing con animaciones propias.
  function shouldSkipInitial() {
    var body = document.body || document.querySelector('body');
    return body && body.hasAttribute('data-skip-initial-loader');
  }

  function initLite() {
    injectCSS();
    injectHTML();
    interceptLinks();
    interceptHistory();
    animateText();
    // El loader queda oculto. Solo se mostrará al hacer clic en un link interno.
    var loader = document.getElementById('claut-loader');
    if (loader) {
      loader.classList.add('claut-loader-hidden');
      unlockScroll();
    }
  }

  if (document.readyState === 'loading') {
    if (document.head) injectCSS();
    else document.addEventListener('DOMContentLoaded', injectCSS);
    document.addEventListener('DOMContentLoaded', function () {
      if (shouldSkipInitial()) {
        initLite();
        return;
      }
      injectHTML();
      showLoader('Cargando');
      interceptLinks();
      interceptHistory();
      animateText();
    });
    window.addEventListener('load', function () {
      if (!shouldSkipInitial()) hideLoader();
    });
    setTimeout(function () {
      if (!shouldSkipInitial()) hideLoader();
    }, 6000);
  } else {
    if (shouldSkipInitial()) {
      initLite();
    } else {
      init();
    }
  }

  window.ClautLoader = { show: showLoader, hide: hideLoader };
})();
