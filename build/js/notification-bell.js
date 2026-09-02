/**
 * Notification Bell — Componente in-app de notificaciones
 *
 * Inyecta una campana con badge de no-leídos en cualquier header.
 * Cuelga de un elemento con id="notification-bell-mount" (o cae al body).
 *
 * Uso en cualquier página autenticada:
 *   <script src="./js/notification-bell.js" defer></script>
 *   <div id="notification-bell-mount"></div>  (opcional: punto de anclaje)
 *
 * Consume:  GET  /api/notificaciones.php?action=list
 *           GET  /api/notificaciones.php?action=unread_count
 *           POST /api/notificaciones.php  body:{action:'mark_read', id}
 *           POST /api/notificaciones.php  body:{action:'mark_all_read'}
 *
 * NO depende de Tailwind ni de otros frameworks. Estilos inline.
 */
(function () {
  'use strict';

  if (window.__claut_bell_loaded) return;
  window.__claut_bell_loaded = true;

  const POLL_INTERVAL_MS = 60000; // 1 minuto
  const API_URL = '/api/notificaciones.php';

  // ============================================================
  // CSS — inyectado una sola vez
  // ============================================================
  const css = `
    .claut-bell-wrap {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .claut-bell-btn {
      width: 40px; height: 40px;
      border-radius: 12px;
      background: transparent;
      border: none;
      color: rgba(255,255,255,0.7);
      cursor: pointer;
      display: inline-flex; align-items: center; justify-content: center;
      transition: background 0.2s ease, color 0.2s ease;
      position: relative;
      padding: 0;
    }
    .claut-bell-btn:hover { background: rgba(255,255,255,0.08); color: #fff; }
    .claut-bell-btn svg { width: 20px; height: 20px; }
    .claut-bell-badge {
      position: absolute;
      top: -2px; right: -2px;
      min-width: 18px; height: 18px;
      padding: 0 4px;
      background: #C7252B;
      color: #fff;
      border-radius: 999px;
      font-size: 10px;
      font-weight: 800;
      display: none;
      align-items: center; justify-content: center;
      border: 2px solid #fff;
      font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
    }
    .claut-bell-badge.visible { display: inline-flex; }
    .claut-bell-panel {
      position: absolute;
      top: calc(100% + 12px);
      right: 0;
      width: 380px;
      max-width: calc(100vw - 24px);
      max-height: 480px;
      background: #101013;
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: 18px;
      box-shadow: 0 24px 56px -12px rgba(0,0,0,0.5);
      z-index: 9999;
      overflow: hidden;
      display: none;
      flex-direction: column;
      font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
    }
    .claut-bell-panel.open { display: flex; }
    .claut-bell-header {
      padding: 16px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      display: flex; justify-content: space-between; align-items: center;
    }
    .claut-bell-header h6 {
      margin: 0;
      font-size: 14px;
      font-weight: 800;
      color: #f8fafc;
      letter-spacing: -0.01em;
    }
    .claut-bell-markall {
      background: none; border: none; cursor: pointer;
      color: #C7252B; font-size: 11px; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.06em;
    }
    .claut-bell-markall:hover { text-decoration: underline; }
    .claut-bell-list {
      flex: 1;
      overflow-y: auto;
      padding: 4px 0;
    }
    .claut-bell-item {
      padding: 14px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.05);
      cursor: pointer;
      transition: background 0.15s ease;
      display: flex; gap: 12px; align-items: flex-start;
    }
    .claut-bell-item:hover { background: rgba(255,255,255,0.04); }
    .claut-bell-item.unread { background: rgba(199,37,43,0.08); }
    .claut-bell-item.unread::before {
      content: '';
      flex-shrink: 0;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #C7252B;
      margin-top: 6px;
    }
    .claut-bell-item:not(.unread)::before {
      content: '';
      flex-shrink: 0;
      width: 8px;
      margin-top: 6px;
    }
    .claut-bell-item-body { flex: 1; min-width: 0; }
    .claut-bell-item-title {
      font-size: 13px; font-weight: 700; color: #f8fafc;
      margin: 0 0 4px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .claut-bell-item-msg {
      font-size: 12px; color: #94a3b8; line-height: 1.5;
      margin: 0;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .claut-bell-item-time {
      font-size: 10px; color: #64748b;
      margin-top: 4px;
      font-weight: 600;
      letter-spacing: 0.04em;
    }
    .claut-bell-empty {
      padding: 40px 24px;
      text-align: center;
      color: #64748b;
      font-size: 13px;
    }
    .claut-bell-loading {
      padding: 24px;
      text-align: center;
      color: #64748b;
      font-size: 12px;
    }
  `;

  function injectCSS() {
    if (document.getElementById('claut-bell-css')) return;
    const s = document.createElement('style');
    s.id = 'claut-bell-css';
    s.textContent = css;
    document.head.appendChild(s);
  }

  // ============================================================
  // HTML
  // ============================================================
  function buildHTML() {
    const wrap = document.createElement('div');
    wrap.className = 'claut-bell-wrap';
    wrap.innerHTML = `
      <button class="claut-bell-btn" id="claut-bell-btn" aria-label="Notificaciones">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="claut-bell-badge" id="claut-bell-badge">0</span>
      </button>
      <div class="claut-bell-panel" id="claut-bell-panel">
        <div class="claut-bell-header">
          <h6>Notificaciones</h6>
          <button class="claut-bell-markall" id="claut-bell-markall">Marcar todas leídas</button>
        </div>
        <div class="claut-bell-list" id="claut-bell-list">
          <div class="claut-bell-loading">Cargando…</div>
        </div>
      </div>
    `;
    return wrap;
  }

  // ============================================================
  // FETCH helpers
  // ============================================================
  async function fetchJSON(url, opts = {}) {
    try {
      const res = await fetch(url, {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        ...opts,
      });
      return await res.json();
    } catch (e) {
      console.warn('[bell] fetch error:', e);
      return null;
    }
  }

  async function getList()      { return fetchJSON(`${API_URL}?action=list&limit=15`); }
  async function getCount()     { return fetchJSON(`${API_URL}?action=unread_count`); }
  async function markRead(id)   { return fetchJSON(API_URL, { method: 'POST', body: JSON.stringify({ action: 'mark_read', id }) }); }
  async function markAllRead()  { return fetchJSON(API_URL, { method: 'POST', body: JSON.stringify({ action: 'mark_all_read' }) }); }

  // ============================================================
  // RENDER
  // ============================================================
  function timeAgo(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    const s = Math.floor((Date.now() - d.getTime()) / 1000);
    if (s < 60) return 'AHORA';
    if (s < 3600) return Math.floor(s / 60) + ' MIN';
    if (s < 86400) return Math.floor(s / 3600) + ' H';
    if (s < 604800) return Math.floor(s / 86400) + ' D';
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' }).toUpperCase();
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
  }

  function renderList(items) {
    const list = document.getElementById('claut-bell-list');
    if (!list) return;
    if (!Array.isArray(items) || items.length === 0) {
      list.innerHTML = '<div class="claut-bell-empty">No tienes notificaciones</div>';
      return;
    }
    list.innerHTML = items.map(n => {
      const unread = !Number(n.leido);
      const title = escapeHtml(n.titulo || 'Notificación');
      const msg = escapeHtml(n.contenido || n.mensaje || '');
      const time = escapeHtml(timeAgo(n.fecha_creacion || n.created_at || n.fecha));
      return `
        <div class="claut-bell-item ${unread ? 'unread' : ''}" data-id="${escapeHtml(n.id)}">
          <div class="claut-bell-item-body">
            <p class="claut-bell-item-title">${title}</p>
            <p class="claut-bell-item-msg">${msg}</p>
            <div class="claut-bell-item-time">${time}</div>
          </div>
        </div>
      `;
    }).join('');
    list.querySelectorAll('.claut-bell-item.unread').forEach(el => {
      el.addEventListener('click', async () => {
        const id = el.dataset.id;
        if (!id) return;
        el.classList.remove('unread');
        await markRead(id);
        refreshCount();
      });
    });
  }

  function renderBadge(n) {
    const badge = document.getElementById('claut-bell-badge');
    if (!badge) return;
    const count = Math.max(0, parseInt(n, 10) || 0);
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.classList.add('visible');
    } else {
      badge.classList.remove('visible');
    }
  }

  // ============================================================
  // LIFECYCLE
  // ============================================================
  async function refreshCount() {
    const data = await getCount();
    if (data && data.success !== false) {
      renderBadge(data.count ?? data.data?.count ?? 0);
    }
  }

  async function refreshList() {
    const data = await getList();
    const items = data?.data?.notificaciones || data?.data?.notifications || data?.data || [];
    renderList(Array.isArray(items) ? items : []);
  }

  function mount() {
    injectCSS();
    const target = document.getElementById('notification-bell-mount') || document.body;
    const node = buildHTML();
    target.appendChild(node);

    const btn = document.getElementById('claut-bell-btn');
    const panel = document.getElementById('claut-bell-panel');
    const markallBtn = document.getElementById('claut-bell-markall');

    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const isOpen = panel.classList.toggle('open');
      if (isOpen) await refreshList();
    });
    document.addEventListener('click', (e) => {
      if (panel.classList.contains('open') && !panel.contains(e.target) && !btn.contains(e.target)) {
        panel.classList.remove('open');
      }
    });
    markallBtn.addEventListener('click', async () => {
      await markAllRead();
      panel.querySelectorAll('.claut-bell-item.unread').forEach(el => el.classList.remove('unread'));
      refreshCount();
    });

    refreshCount();
    setInterval(refreshCount, POLL_INTERVAL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();
