/**
 * toast.js — Notyf wrapper. NUNCA usar alert()/confirm()/prompt() (regla #2).
 * Notyf se carga via CDN <script>; aquí asumimos `window.Notyf` disponible.
 */

let _notyf = null;

function getNotyf() {
  if (_notyf) return _notyf;
  if (typeof window !== 'undefined' && window.Notyf) {
    _notyf = new window.Notyf({
      duration: 4000,
      position: { x: 'right', y: 'top' },
      ripple: false,
      types: [
        { type: 'success', background: '#16a34a', icon: false },
        { type: 'error',   background: '#C7252B', icon: false, duration: 6000 },
        { type: 'info',    background: '#1D1D1F', icon: false },
      ],
    });
  }
  return _notyf;
}

export const toast = {
  success: (msg) => getNotyf()?.success(msg),
  error:   (msg) => getNotyf()?.error(msg),
  info:    (msg) => getNotyf()?.open({ type: 'info', message: msg }),
};

/**
 * Modal confirmación custom (reemplazo de window.confirm).
 * Devuelve Promise<boolean>.
 */
export function confirmDialog({ title = '¿Confirmar?', message = '', okText = 'Aceptar', cancelText = 'Cancelar' } = {}) {
  return new Promise(resolve => {
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm';
    overlay.innerHTML = `
      <div class="bg-bg text-text rounded-2xl shadow-2xl max-w-md w-[90%] p-6 border border-border">
        <h3 class="text-xl font-semibold mb-2">${title}</h3>
        <p class="text-muted mb-6">${message}</p>
        <div class="flex justify-end gap-3">
          <button data-act="cancel" class="px-4 py-2 rounded-lg border border-border hover:bg-surface transition">${cancelText}</button>
          <button data-act="ok" class="px-4 py-2 rounded-lg bg-brand text-white hover:bg-brand/90 transition">${okText}</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    const close = (result) => {
      overlay.remove();
      resolve(result);
    };
    overlay.addEventListener('click', e => {
      if (e.target === overlay) close(false);
      const act = e.target.closest('[data-act]')?.dataset.act;
      if (act === 'ok') close(true);
      if (act === 'cancel') close(false);
    });
  });
}
