/**
 * CLAUT ADMIN ELITE — comportamiento del reskin 2026 (va en par con
 * css/claut-admin-elite.css). 100% aditivo y null-safe: si una página no
 * tiene los elementos esperados, simplemente no hace nada. No toca ningún
 * flujo de CRUD ni listener existente.
 *
 *  1. Saludo unificado "Hola, {nombre}" después del header del módulo.
 *     Ancla: header.claut-admin-main, o el <header>/<nav> que contiene la
 *     hamburguesa (#claut-header-menu-btn) en páginas con header propio
 *     (demo_evento, admin-panel...). El <h1> original se oculta solo si el
 *     saludo se insertó (clase .elite-hidden-title). calendario.html se
 *     excluye: su layout es 100vh sin scroll y un bloque extra rompería el
 *     alto del calendario.
 *  2. Barras decorativas en la primera stat-card (bento).
 *  3. En móvil remueve la clase .collapsed del sidebar (concepto solo de
 *     escritorio persistido en localStorage) — con ella presente el drawer
 *     quedaba en 74px con las etiquetas asomando por el borde.
 */
(function () {
  'use strict';

  function firstName() {
    var sources = ['userData', 'currentUser', 'userInfo'];
    for (var i = 0; i < sources.length; i++) {
      try {
        var raw = localStorage.getItem(sources[i]) || sessionStorage.getItem(sources[i]);
        if (!raw) continue;
        var obj = JSON.parse(raw);
        var name = obj.nombre || obj.name || obj.nombre_completo || obj.usuario || '';
        if (typeof name === 'string' && name.trim()) return name.trim().split(/\s+/)[0];
      } catch (e) { /* fuente corrupta: probar la siguiente */ }
    }
    return 'Administrador';
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function findHeaderAnchor() {
    var header = document.querySelector('header.claut-admin-main');
    if (header) return header;
    var hb = document.getElementById('claut-header-menu-btn');
    if (hb) return hb.closest('header') || hb.closest('nav');
    return null;
  }

  function injectGreeting() {
    if (document.querySelector('.elite-greet')) return;
    if (document.getElementById('admin-master-calendar')) return; // calendario: layout 100vh propio
    var header = findHeaderAnchor();
    if (!header) return;

    var h1 = header.querySelector('h1');
    var titleEl = h1 || header.querySelector('.top-navbar-title');
    var moduleTitle = titleEl ? titleEl.textContent.trim() : '';

    if (h1) {
      h1.classList.add('elite-hidden-title');
      var sib = h1.nextElementSibling;
      if (sib && sib.tagName === 'P') sib.classList.add('elite-hidden-title');
    }

    var months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    var now = new Date();
    var greet = document.createElement('div');
    greet.className = 'elite-greet claut-admin-main container mx-auto';
    greet.innerHTML =
      '<div class="elite-greet-txt">' +
        '<h2>Hola, <em>' + esc(firstName()) + '</em></h2>' +
        (moduleTitle ? '<p>' + esc(moduleTitle) + '</p>' : '') +
      '</div>' +
      '<span class="elite-greet-date"><i class="far fa-calendar"></i>' +
        months[now.getMonth()] + ' ' + now.getFullYear() + '</span>';
    header.insertAdjacentElement('afterend', greet);
    document.body.classList.add('elite-greeted');
  }

  function injectBars() {
    var featured = document.querySelector('.claut-stat-card');
    if (!featured || featured.querySelector('.elite-bars')) return;
    var heights = [38, 55, 42, 70, 48, 90];
    var bars = document.createElement('div');
    bars.className = 'elite-bars';
    bars.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < heights.length; i++) {
      var bar = document.createElement('i');
      bar.style.height = heights[i] + '%';
      bar.style.animationDelay = (200 + i * 60) + 'ms';
      if (i === heights.length - 1) bar.className = 'hl';
      bars.appendChild(bar);
    }
    featured.appendChild(bars);
  }

  function stripCollapsedOnMobile() {
    var sidebar = document.getElementById('claut-admin-sidebar');
    if (!sidebar) return;
    var mq = window.matchMedia('(max-width: 1024px)');
    var apply = function () { if (mq.matches) sidebar.classList.remove('collapsed'); };
    apply();
    if (mq.addEventListener) mq.addEventListener('change', apply);
    else if (mq.addListener) mq.addListener(apply);
  }

  /**
   * Subcategorías desplegables del sidebar (FEATURE-035). El HTML de las 12
   * páginas ya agrupa los items en .claut-admin-nav-group con una
   * .claut-admin-nav-label ("General" / "Ecosistema de módulos" / "Sistema")
   * — el skin elite solo la ocultaba para el rail de iconos. Cualquier grupo
   * con más de 3 items se vuelve colapsable (la etiqueta se convierte en
   * botón): resuelve de paso el amontonamiento del drawer móvil (menos
   * items visibles por defecto) y cumple el pedido de categorías
   * desplegables tanto en móvil como en escritorio, sin tocar el HTML de
   * ninguna página.
   */
  function initNavGroups() {
    var groups = document.querySelectorAll('.claut-admin-nav-group');
    if (!groups.length) return;
    var STORE_PREFIX = 'claut-nav-group-';

    groups.forEach(function (group, idx) {
      var items = group.querySelectorAll(':scope > .claut-admin-nav-item');
      var label = group.querySelector(':scope > .claut-admin-nav-label');
      if (!label || items.length <= 3) return; // grupos chicos: siempre visibles, sin toggle

      var groupName = label.textContent.trim() || ('grupo-' + idx);
      var hasActive = !!group.querySelector('.claut-admin-nav-item.active');
      var storeKey = STORE_PREFIX + groupName;
      var stored = null;
      try { stored = localStorage.getItem(storeKey); } catch (e) { /* localStorage no disponible */ }
      var collapsed = stored !== null ? stored === '1' : !hasActive;

      label.classList.add('claut-admin-nav-toggle');
      label.setAttribute('data-group-name', groupName);
      label.setAttribute('role', 'button');
      label.setAttribute('tabindex', '0');
      // El texto va envuelto en su propio <span> para poder ocultarlo SOLO
      // en el riel de escritorio (ahí el botón es redondo, solo el chevron
      // cabe) sin tocar el texto visible del drawer móvil.
      if (!label.querySelector('.claut-admin-nav-toggle-text')) {
        var textSpan = document.createElement('span');
        textSpan.className = 'claut-admin-nav-toggle-text';
        textSpan.textContent = groupName;
        label.textContent = '';
        label.appendChild(textSpan);
      }
      if (!label.querySelector('.claut-admin-nav-toggle-chevron')) {
        var chevron = document.createElement('i');
        chevron.className = 'fas fa-chevron-down claut-admin-nav-toggle-chevron';
        chevron.setAttribute('aria-hidden', 'true');
        label.appendChild(chevron);
      }

      function apply() {
        group.classList.toggle('is-collapsed', collapsed);
        label.setAttribute('aria-expanded', String(!collapsed));
      }
      apply();

      function toggle() {
        collapsed = !collapsed;
        try { localStorage.setItem(storeKey, collapsed ? '1' : '0'); } catch (e) { /* no-op */ }
        apply();
      }
      label.addEventListener('click', toggle);
      label.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
      });
    });
  }

  function init() {
    injectGreeting();
    injectBars();
    stripCollapsedOnMobile();
    initNavGroups();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
