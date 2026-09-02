/**
 * Sidebar administrativo unificado — un único botón (#claut-header-menu-btn,
 * junto al título de cada página) controla todo: en desktop colapsa/expande
 * el riel de iconos, en móvil abre/cierra el drawer. Cuelga de
 * #claut-admin-sidebar / #claut-header-menu-btn / #claut-admin-sidebar-overlay
 * (todos opcionales).
 */
(function () {
  'use strict';
  const sidebar = document.getElementById('claut-admin-sidebar');
  if (!sidebar) return;

  const headerMenuBtn = document.getElementById('claut-header-menu-btn');
  const overlay = document.getElementById('claut-admin-sidebar-overlay');
  const KEY = 'claut-admin-sidebar-collapsed';

  if (localStorage.getItem(KEY) === '1') sidebar.classList.add('collapsed');

  function toggleCollapse() {
    sidebar.classList.toggle('collapsed');
    localStorage.setItem(KEY, sidebar.classList.contains('collapsed') ? '1' : '0');
  }

  function openMobile() {
    sidebar.classList.add('show');
    if (overlay) overlay.classList.add('show');
  }
  function closeMobile() {
    sidebar.classList.remove('show');
    if (overlay) overlay.classList.remove('show');
  }
  if (overlay) overlay.addEventListener('click', closeMobile);

  // Botón del header: en móvil abre/cierra el drawer, en desktop colapsa/expande
  if (headerMenuBtn) {
    headerMenuBtn.addEventListener('click', () => {
      if (window.matchMedia('(max-width: 1024px)').matches) {
        sidebar.classList.contains('show') ? closeMobile() : openMobile();
      } else {
        toggleCollapse();
      }
    });
  }
})();
