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

  function init() {
    injectGreeting();
    injectBars();
    stripCollapsedOnMobile();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
