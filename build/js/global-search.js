/**
 * Global Search — barra de búsqueda flotante en el dashboard
 *
 * Activación: tecla "/" o "Ctrl+K" / "Cmd+K"
 * Consume:    GET /api/search.php?q=...
 *
 * Self-contained. No requiere Tailwind. Sin dependencias externas.
 */
(function () {
  'use strict';

  if (window.__claut_search_loaded) return;
  window.__claut_search_loaded = true;

  const API_URL = '/api/search.php';
  let debounceTimer = null;

  const css = `
    .gs-overlay {
      position: fixed; inset: 0;
      background: rgba(15,23,42,0.55);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      z-index: 99990;
      display: none;
      align-items: flex-start;
      justify-content: center;
      padding: 80px 16px 16px;
      animation: gsFadeIn 0.18s ease;
    }
    .gs-overlay.open { display: flex; }
    @keyframes gsFadeIn { from { opacity: 0 } to { opacity: 1 } }
    .gs-box {
      width: 100%; max-width: 640px;
      background: #101013;
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: 18px;
      box-shadow: 0 30px 80px -12px rgba(0,0,0,0.6);
      overflow: hidden;
      font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
    }
    .gs-input-wrap {
      display: flex; align-items: center;
      padding: 16px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      gap: 12px;
    }
    .gs-input-wrap i { color: #94a3b8; font-size: 18px; }
    .gs-input {
      flex: 1;
      border: none; outline: none;
      font-size: 16px;
      font-weight: 500;
      color: #f8fafc;
      background: transparent;
    }
    .gs-input::placeholder { color: #64748b; }
    .gs-esc {
      font-size: 10px;
      color: #94a3b8;
      padding: 4px 8px;
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: 6px;
      letter-spacing: 0.06em;
      font-weight: 700;
    }
    .gs-results {
      max-height: 480px;
      overflow-y: auto;
      padding: 8px 0;
    }
    .gs-item {
      display: flex; align-items: center; gap: 14px;
      padding: 12px 20px;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      transition: background 0.12s ease;
    }
    .gs-item:hover, .gs-item.active { background: rgba(199,37,43,0.1); }
    .gs-icon {
      width: 36px; height: 36px;
      flex-shrink: 0;
      border-radius: 10px;
      background: rgba(255,255,255,0.06);
      display: inline-flex; align-items: center; justify-content: center;
      color: #e02d33;
      font-size: 15px;
    }
    .gs-body { flex: 1; min-width: 0; }
    .gs-title {
      font-size: 14px; font-weight: 700; color: #f8fafc;
      margin: 0 0 2px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .gs-sub {
      font-size: 12px; color: #94a3b8;
      margin: 0;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .gs-type {
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 2px 8px;
      border-radius: 99px;
      background: rgba(199,37,43,0.15);
      color: #e02d33;
      flex-shrink: 0;
    }
    .gs-empty, .gs-hint {
      padding: 32px 24px;
      text-align: center;
      color: #64748b;
      font-size: 13px;
    }
    .gs-hint kbd {
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: 6px;
      padding: 2px 8px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      font-weight: 700;
      color: #cbd5e1;
      margin: 0 2px;
    }
  `;

  function injectCSS() {
    if (document.getElementById('gs-css')) return;
    const s = document.createElement('style');
    s.id = 'gs-css'; s.textContent = css;
    document.head.appendChild(s);
  }

  function buildHTML() {
    const ovl = document.createElement('div');
    ovl.id = 'gs-overlay';
    ovl.className = 'gs-overlay';
    ovl.innerHTML = `
      <div class="gs-box" role="dialog" aria-modal="true" aria-label="Búsqueda global">
        <div class="gs-input-wrap">
          <i class="fas fa-magnifying-glass"></i>
          <input id="gs-input" class="gs-input" type="text" placeholder="Buscar empresas, eventos, documentos, descuentos…" autocomplete="off">
          <span class="gs-esc">ESC</span>
        </div>
        <div class="gs-results" id="gs-results">
          <div class="gs-hint">
            Empieza a escribir para buscar.<br>
            <small>Atajo: presiona <kbd>/</kbd> o <kbd>Ctrl</kbd>+<kbd>K</kbd> para abrir.</small>
          </div>
        </div>
      </div>
    `;
    return ovl;
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[c]);
  }

  async function doSearch(q) {
    const results = document.getElementById('gs-results');
    if (q.length < 2) {
      results.innerHTML = '<div class="gs-hint">Escribe al menos 2 caracteres.</div>';
      return;
    }
    results.innerHTML = '<div class="gs-hint"><i class="fas fa-spinner fa-spin"></i> Buscando…</div>';
    try {
      const res = await fetch(`${API_URL}?q=${encodeURIComponent(q)}&limit=30`, { credentials: 'include' });
      const data = await res.json();
      const items = data?.data?.results || [];
      if (!items.length) {
        results.innerHTML = '<div class="gs-empty">Sin resultados para "<strong>' + escapeHtml(q) + '</strong>"</div>';
        return;
      }
      results.innerHTML = items.map(r => `
        <a class="gs-item" href="${escapeHtml(r.url)}">
          <span class="gs-icon"><i class="fas ${escapeHtml(r.icon || 'fa-file')}"></i></span>
          <div class="gs-body">
            <p class="gs-title">${escapeHtml(r.title)}</p>
            <p class="gs-sub">${escapeHtml(r.subtitle || '')}</p>
          </div>
          <span class="gs-type">${escapeHtml(r.type)}</span>
        </a>
      `).join('');
    } catch (e) {
      results.innerHTML = '<div class="gs-empty">Error al buscar: ' + escapeHtml(e.message) + '</div>';
    }
  }

  function open() {
    document.getElementById('gs-overlay').classList.add('open');
    setTimeout(() => document.getElementById('gs-input').focus(), 50);
  }
  function close() {
    document.getElementById('gs-overlay').classList.remove('open');
    const input = document.getElementById('gs-input');
    if (input) input.value = '';
  }

  function init() {
    injectCSS();
    document.body.appendChild(buildHTML());

    const input = document.getElementById('gs-input');
    const overlay = document.getElementById('gs-overlay');

    input.addEventListener('input', (e) => {
      clearTimeout(debounceTimer);
      const q = e.target.value.trim();
      debounceTimer = setTimeout(() => doSearch(q), 260);
    });

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) close();
    });

    document.addEventListener('keydown', (e) => {
      // Abrir con "/" o Ctrl+K / Cmd+K
      const target = e.target;
      const isTyping = target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName);
      if ((e.key === '/' && !isTyping) || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) {
        e.preventDefault();
        open();
      }
      if (e.key === 'Escape' && overlay.classList.contains('open')) {
        close();
      }
    });

    // Botón visible dentro del header (opcional, además del atajo de teclado)
    const fab = document.createElement('button');
    fab.id = 'gs-fab';
    fab.className = 'claut-header-btn';
    fab.title = 'Buscar (presiona / o Ctrl+K)';
    fab.setAttribute('aria-label', 'Búsqueda global');
    fab.innerHTML = '<i class="fas fa-magnifying-glass" style="font-size:16px;"></i>';
    fab.addEventListener('click', open);
    const target = document.getElementById('global-search-mount') || document.body;
    if (target !== document.body) {
      target.appendChild(fab);
    } else {
      fab.style.cssText = 'position:fixed;top:80px;right:80px;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.14);color:#f8fafc;cursor:pointer;z-index:9989;display:inline-flex;align-items:center;justify-content:center;transition:background 0.2s;padding:0;';
      document.body.appendChild(fab);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
