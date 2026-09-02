/**
 * Claut Wizard — navegación genérica para modales multi-paso.
 * Aplica a cualquier .claut-wizard dentro de un .claut-wizard-backdrop.
 * No asume nombres de campos: solo mueve entre .claut-wizard-panel[data-step]
 * y sincroniza .claut-wizard-step-btn[data-step].
 *
 * Validación opcional por paso: si un panel tiene [data-wizard-validate],
 * se ejecuta window[ese-nombre-de-función](panelEl) antes de avanzar;
 * debe devolver true/false.
 */
(function () {
  'use strict';

  function initWizard(root) {
    const steps = Array.from(root.querySelectorAll('.claut-wizard-step-btn'));
    const panels = Array.from(root.querySelectorAll('.claut-wizard-panel'));
    const prevBtns = root.querySelectorAll('[data-wizard-prev]');
    const nextBtns = root.querySelectorAll('[data-wizard-next]');
    const submitBtns = root.querySelectorAll('[data-wizard-submit]');
    if (!steps.length || !panels.length) return;

    let current = 1;
    const total = steps.length;

    function render() {
      steps.forEach(s => {
        const n = parseInt(s.dataset.step, 10);
        s.classList.toggle('active', n === current);
        s.classList.toggle('done', n < current);
      });
      panels.forEach(p => p.classList.toggle('active', parseInt(p.dataset.step, 10) === current));
      prevBtns.forEach(b => { b.style.visibility = current === 1 ? 'hidden' : 'visible'; });
      nextBtns.forEach(b => { b.style.display = current === total ? 'none' : 'inline-flex'; });
      submitBtns.forEach(b => { b.style.display = current === total ? 'inline-flex' : 'none'; });
    }

    function goTo(n) {
      if (n < 1 || n > total) return;
      current = n;
      render();
    }

    function validateCurrent() {
      const panel = panels.find(p => parseInt(p.dataset.step, 10) === current);
      const fnName = panel && panel.dataset.wizardValidate;
      if (fnName && typeof window[fnName] === 'function') {
        return window[fnName](panel);
      }
      return true;
    }

    nextBtns.forEach(b => b.addEventListener('click', () => {
      if (validateCurrent()) goTo(current + 1);
    }));
    prevBtns.forEach(b => b.addEventListener('click', () => goTo(current - 1)));
    steps.forEach(s => s.addEventListener('click', () => {
      const n = parseInt(s.dataset.step, 10);
      if (n <= current || n === current + 1) goTo(n);
    }));

    root.__clautWizardReset = () => goTo(1);
    render();
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.claut-wizard').forEach(initWizard);
  });
})();
