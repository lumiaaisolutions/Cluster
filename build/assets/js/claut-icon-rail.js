/**
 * Claut Icon Rail — marca el ítem activo según la URL actual. El riel es
 * el único componente de navegación de socio (vertical en escritorio,
 * tab-bar horizontal en móvil vía CSS) — no requiere JS de toggle.
 */
(function () {
  'use strict';
  const rail = document.querySelector('.claut-icon-rail');
  if (!rail) return;

  const current = window.location.pathname.split('/').pop() || 'dashboard.html';
  rail.querySelectorAll('.claut-icon-rail-item').forEach(item => {
    const href = (item.getAttribute('href') || '').split('/').pop();
    item.classList.toggle('active', href === current);
  });
})();
