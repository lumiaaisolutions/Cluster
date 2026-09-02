/**
 * Claut Tabs — navegación genérica de sub-módulos dentro de un panel.
 * Aplica a cualquier grupo de .claut-tab-btn[data-tab] que controle
 * paneles .claut-tab-pane con id="{data-tab}Tab".
 */
(function () {
  'use strict';

  function initTabGroup(buttons) {
    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        const pane = document.getElementById(target + 'Tab');
        if (!pane) return;

        buttons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const group = pane.parentElement;
        Array.from(group.children).forEach(el => {
          if (el.classList.contains('claut-tab-pane')) el.classList.remove('active');
        });
        pane.classList.add('active');

        btn.dispatchEvent(new CustomEvent('claut-tab-shown', { bubbles: true, detail: { tab: target } }));
      });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.claut-tabs').forEach(group => {
      initTabGroup(Array.from(group.querySelectorAll('.claut-tab-btn')));
    });
  });
})();
