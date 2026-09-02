/**
 * Metrics Widget — Tarjeta de métricas para el dashboard del socio.
 *
 * Inyecta una tarjeta con vistas del perfil de empresa del usuario actual.
 * Punto de anclaje: <div id="metrics-widget-mount"></div>
 */
(function () {
  'use strict';
  if (window.__claut_metrics_loaded) return;
  window.__claut_metrics_loaded = true;

  const css = `
    .mw-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 18px;
      padding: 24px;
      font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .mw-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .mw-head h3 { font-size: 14px; font-weight: 800; margin: 0; color: #0f172a; text-transform: uppercase; letter-spacing: 0.06em; }
    .mw-head i { color: #C7252B; }
    .mw-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
    @media (max-width: 768px) { .mw-grid { grid-template-columns: repeat(2, 1fr); } }
    .mw-stat { background: #f8fafc; padding: 16px; border-radius: 12px; }
    .mw-stat-num { font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1; }
    .mw-stat-label { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.06em; margin-top: 6px; }
    .mw-chart { margin-top: 20px; display: flex; align-items: flex-end; gap: 3px; height: 60px; }
    .mw-bar { flex: 1; background: linear-gradient(180deg, #C7252B 0%, #fca5a5 100%); border-radius: 3px 3px 0 0; min-height: 4px; transition: opacity 0.2s; }
    .mw-bar:hover { opacity: 0.7; }
    .mw-empty { padding: 24px; text-align: center; color: #94a3b8; font-size: 13px; }
  `;

  function inject() {
    if (document.getElementById('mw-css')) return;
    const s = document.createElement('style');
    s.id = 'mw-css'; s.textContent = css;
    document.head.appendChild(s);
  }

  async function load() {
    const mount = document.getElementById('metrics-widget-mount');
    if (!mount) return;
    inject();

    try {
      const res = await fetch('/api/metricas.php?action=mi_empresa', { credentials: 'include' });
      const data = await res.json();
      if (!data.success || !data.data) {
        mount.innerHTML = '';
        return;
      }
      const m = data.data;

      // Construir barras: últimos 30 días (rellenar días sin datos con 0)
      const today = new Date();
      const days = [];
      for (let i = 29; i >= 0; i--) {
        const d = new Date(today); d.setDate(d.getDate() - i);
        const key = d.toISOString().split('T')[0];
        const found = (m.serie_diaria || []).find(s => s.dia === key);
        days.push({ dia: key, vistas: found ? Number(found.vistas) : 0 });
      }
      const max = Math.max(1, ...days.map(d => d.vistas));

      mount.innerHTML = `
        <div class="mw-card">
          <div class="mw-head">
            <h3><i class="fas fa-chart-line"></i> Métricas de mi empresa</h3>
            <small style="color:#64748b;font-size:11px;">Últimos 30 días</small>
          </div>
          <div class="mw-grid">
            <div class="mw-stat">
              <div class="mw-stat-num">${m.vistas_total.toLocaleString()}</div>
              <div class="mw-stat-label">Vistas totales</div>
            </div>
            <div class="mw-stat">
              <div class="mw-stat-num">${m.vistas_30d.toLocaleString()}</div>
              <div class="mw-stat-label">Últimos 30 días</div>
            </div>
            <div class="mw-stat">
              <div class="mw-stat-num">${m.vistas_7d.toLocaleString()}</div>
              <div class="mw-stat-label">Últimos 7 días</div>
            </div>
            <div class="mw-stat">
              <div class="mw-stat-num">${m.vistas_unicas_30d.toLocaleString()}</div>
              <div class="mw-stat-label">Visitantes únicos</div>
            </div>
          </div>
          <div class="mw-chart" title="Vistas diarias últimos 30 días">
            ${days.map(d => `<div class="mw-bar" style="height:${(d.vistas/max)*100}%" title="${d.dia}: ${d.vistas} vistas"></div>`).join('')}
          </div>
        </div>
      `;
    } catch (e) {
      console.warn('[metrics-widget]', e);
      mount.innerHTML = '';
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
