/**
 * Claut Page Header — breadcrumb + navbar interno reutilizable (v2).
 *
 * Reemplaza el bloque <nav class="porsche-navbar">...</nav> duplicado en
 * dashboard, descuentos, boletines, comites, contacto, eventos,
 * empresas-convenio y profile.
 *
 * Uso: colocar este <script> exactamente donde antes iba el <nav>:
 *   <script src="./assets/js/claut-page-header.js?v=..."
 *           data-breadcrumb="Descuentos"
 *           data-welcome="Descuentos"></script>
 *
 * v2 — AUTOCONTENIDO EN ESTILO: inyecta su propio <style> con TODO el
 * diseño del navbar, pill de usuario, dropdown y botón admin usando
 * clases propias (prefijo cph-). Razón: la v1 estilizaba el dropdown con
 * utilidades de Tailwind (w-52, py-2.5, rounded-xl...) que NO existen en
 * páginas sin Tailwind CDN (dashboard.html usa el CSS compilado de Argon)
 * — ahí el dropdown se veía como texto plano sin caja. Con CSS propio el
 * componente se ve idéntico en todas las páginas, sin depender de qué
 * framework cargue cada una. El <style> se inyecta en el <body> (donde
 * vive este script), después de los CSS del <head>, así gana los empates
 * de especificidad/!important contra reglas legacy por orden de aparición.
 *
 * También es autosuficiente en datos: lee la sesión de localStorage/
 * sessionStorage (mismas claves que el resto del sitio) y puebla el botón
 * de usuario + dropdown + botón de Admin Panel él mismo.
 *
 * IDs que expone (compatibilidad con JS existente de cada página):
 * welcomeMessage, welcomeText, userMenuBtn, userDisplayName, userDropdown,
 * dropdownUserName, dropdownUserRole, loginMenuItem, adminPanelButton.
 */
(function () {
    'use strict';

    const scriptEl = document.currentScript;
    if (!scriptEl) return;
    const breadcrumb = scriptEl.dataset.breadcrumb || '';
    const welcome = scriptEl.dataset.welcome || breadcrumb;

    /* ---------- Estilos autocontenidos ---------- */
    if (!document.getElementById('cph-styles')) {
        const css = `
/* Navbar interno — fuente única de verdad (gana a reglas legacy por orden) */
body .porsche-navbar[navbar-main] {
  position: sticky !important;
  top: calc(var(--header-height, 80px) + 10px) !important;
  z-index: 500 !important;
  display: block !important;
  width: 100% !important;
  max-width: 100% !important;
  margin: 24px 0 20px 0 !important;
  padding: 0 !important;
  background: rgba(14, 9, 10, 0.72) !important;
  border: 1px solid transparent !important;
  border-radius: 1.25rem !important;
  backdrop-filter: blur(22px) saturate(160%) !important;
  -webkit-backdrop-filter: blur(22px) saturate(160%) !important;
  box-shadow: 0 10px 34px -14px rgba(0, 0, 0, 0.7) !important;
  overflow: visible !important;
  transform: none !important;
}
.porsche-navbar[navbar-main]::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  padding: 1px;
  background: linear-gradient(120deg, rgba(199,37,43,0.55), rgba(255,255,255,0.07) 42%, rgba(199,37,43,0.28));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
  pointer-events: none;
  opacity: 0.85;
}
.cph-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  width: 100%;
  padding: 0.8rem 1.5rem;
}
.cph-left { min-width: 0; }
.cph-breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 0.3rem 0;
  padding: 0;
  list-style: none;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}
.cph-breadcrumb li { display: flex; align-items: center; gap: 0.5rem; color: rgba(255,255,255,0.65); }
.cph-breadcrumb li:first-child { color: rgba(255,255,255,0.4); }
.cph-breadcrumb li:first-child::before {
  content: '';
  width: 6px; height: 6px; border-radius: 50%;
  background: #C7252B;
  box-shadow: 0 0 8px rgba(199,37,43,0.9);
  flex-shrink: 0;
}
.cph-crumb-sep { color: rgba(255,255,255,0.3); font-weight: 400; }
.cph-welcome {
  margin: 0;
  font-size: 1.15rem !important;
  font-weight: 700;
  letter-spacing: -0.02em !important;
  line-height: 1.25;
  background: linear-gradient(135deg, #f8fafc, rgba(248,250,252,0.8));
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}
.cph-actions { display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; }

/* Pill de usuario */
.cph-user-wrap { position: relative; }
.cph-user-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.8rem 0.35rem 0.35rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: #fff;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 999px;
  cursor: pointer;
  transition: transform 180ms cubic-bezier(0.23,1,0.32,1), background 180ms ease, border-color 180ms ease;
}
.cph-user-btn:hover { transform: translateY(-1px); background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); }
.cph-user-btn:active { transform: scale(0.97); }
.cph-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 26px; height: 26px;
  border-radius: 50%;
  background: linear-gradient(135deg, #C7252B, #a02622);
  font-size: 0.7rem;
  font-weight: 800;
  flex-shrink: 0;
}
.cph-user-name {
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cph-chevron { font-size: 0.65rem; opacity: 0.6; }
@media (max-width: 640px) { .cph-user-name { display: none; } }

/* Dropdown */
.cph-dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  width: 13rem;
  padding: 0.4rem 0;
  border-radius: 0.9rem;
  background: rgba(20,20,22,0.98);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.1);
  box-shadow: 0 20px 40px rgba(0,0,0,0.5);
  z-index: 1000;
}
.cph-dropdown.hidden { display: none !important; }
.cph-dd-head { padding: 0.6rem 1rem 0.65rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
.cph-dd-name { margin: 0; font-size: 0.85rem; font-weight: 600; color: #f8fafc; line-height: 1.3; }
.cph-dd-role { margin: 0.1rem 0 0; font-size: 0.7rem; color: #94a3b8; line-height: 1.3; text-transform: capitalize; }
.cph-dd-item {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  width: 100%;
  padding: 0.6rem 1rem;
  font-size: 0.8rem;
  font-weight: 500;
  color: #cbd5e1;
  background: transparent;
  border: none;
  cursor: pointer;
  text-decoration: none;
  text-align: left;
  transition: background 150ms ease;
}
.cph-dd-item i { width: 16px; text-align: center; color: #71717a; }
.cph-dd-item:hover { background: rgba(255,255,255,0.06); color: #fff; }
.cph-dd-item--danger { color: #f87171; }
.cph-dd-item--danger i { color: #ef4444; }
.cph-dd-item--danger:hover { background: rgba(239,68,68,0.1); color: #f87171; }

/* Iniciar sesión */
.cph-login-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.55rem 1.1rem;
  font-size: 0.8rem;
  font-weight: 700;
  color: #fff;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 999px;
  text-decoration: none;
  transition: transform 180ms cubic-bezier(0.23,1,0.32,1), opacity 180ms ease;
}
.cph-login-btn:hover { transform: translateY(-1px); opacity: 0.85; }

/* Botón Admin Panel — círculo rojo unificado */
.cph-admin-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px; height: 44px;
  border-radius: 50%;
  background: linear-gradient(135deg, #C7252B, #a02622);
  box-shadow: 0 2px 8px rgba(201,48,44,0.35);
  flex-shrink: 0;
  text-decoration: none;
  transition: transform 180ms cubic-bezier(0.23,1,0.32,1), box-shadow 180ms ease;
}
.cph-admin-btn i { color: #fff; font-size: 1.05rem; }
.cph-admin-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(201,48,44,0.35), 0 0 0 5px rgba(199,37,43,0.15);
}
.cph-admin-btn:active { transform: scale(0.94); }

@media (max-width: 640px) {
  body .porsche-navbar[navbar-main] { position: relative !important; top: auto !important; border-radius: 1rem !important; margin-top: 12px !important; }
  .cph-row { padding: 0.7rem 1rem; }
  .cph-welcome { font-size: 1rem !important; }
}`;
        const styleEl = document.createElement('style');
        styleEl.id = 'cph-styles';
        styleEl.textContent = css;
        // En el <body>, junto al script: después de los CSS del <head>,
        // gana los empates contra reglas legacy por orden de aparición.
        scriptEl.parentNode.insertBefore(styleEl, scriptEl);
    }

    /* ---------- Markup ---------- */
    const html = `
<nav class="porsche-navbar" navbar-main navbar-scroll="false">
    <div class="cph-row">
        <div class="cph-left">
            <ol class="cph-breadcrumb">
                <li>Intranet</li>
                <li><span class="cph-crumb-sep">/</span>${breadcrumb}</li>
            </ol>
            <div id="welcomeMessage">
                <h6 class="porsche-welcome cph-welcome" id="welcomeText">${welcome}</h6>
            </div>
        </div>

        <div class="cph-actions">
            <!-- Botón de usuario + dropdown -->
            <div class="cph-user-wrap" id="claut-user-menu-wrap" style="display:none;">
                <button class="cph-user-btn" onclick="toggleUserMenu()" id="userMenuBtn" type="button">
                    <span class="cph-avatar" id="userAvatar">?</span>
                    <span class="cph-user-name" id="userDisplayName">Usuario</span>
                    <i class="fas fa-chevron-down cph-chevron"></i>
                </button>
                <div class="cph-dropdown hidden" id="userDropdown">
                    <div class="cph-dd-head">
                        <p class="cph-dd-name" id="dropdownUserName">Usuario</p>
                        <p class="cph-dd-role" id="dropdownUserRole">Rol</p>
                    </div>
                    <a href="profile.html" class="cph-dd-item">
                        <i class="fas fa-user-circle"></i>
                        Mi Perfil
                    </a>
                    <button onclick="handleLogout()" class="cph-dd-item cph-dd-item--danger" type="button">
                        <i class="fas fa-sign-out-alt"></i>
                        Cerrar Sesión
                    </button>
                </div>
            </div>

            <!-- Iniciar sesión (usuarios no autenticados) -->
            <a href="./pages/sign-in.html" id="loginMenuItem" class="cph-login-btn" style="display:none;">
                <i class="fa fa-user"></i>Iniciar Sesión
            </a>

            <!-- Botón Panel de Administración (solo para admins) -->
            <a href="./admin-panel.html?login=success" id="adminPanelButton" title="Panel de Administración"
                class="cph-admin-btn" style="display:none;">
                <i class="fas fa-cogs"></i>
            </a>
        </div>
    </div>
</nav>`;

    scriptEl.insertAdjacentHTML('beforebegin', html);

    /* ---------- Lectura de sesión: mismas fuentes que el resto del sitio ---------- */
    function getUserData() {
        const sources = [
            localStorage.getItem('userData'),
            localStorage.getItem('currentUser'),
            sessionStorage.getItem('userSession'),
            localStorage.getItem('userInfo'),
            sessionStorage.getItem('loginData')
        ];
        for (const source of sources) {
            if (!source) continue;
            try {
                const parsed = JSON.parse(source);
                if (parsed && (parsed.nombre || parsed.name || parsed.email)) return parsed;
            } catch (e) { /* no era JSON, ignorar fuente */ }
        }
        return null;
    }

    function renderSession() {
        const userData = getUserData();
        const menuWrap = document.getElementById('claut-user-menu-wrap');
        const loginItem = document.getElementById('loginMenuItem');
        const adminBtn = document.getElementById('adminPanelButton');

        if (!userData) {
            if (menuWrap) menuWrap.style.display = 'none';
            if (loginItem) loginItem.style.display = 'flex';
            if (adminBtn) adminBtn.style.display = 'none';
            return;
        }

        const nombre = userData.nombre || userData.name || userData.email || 'Usuario';
        const rol = userData.rol || userData.role || userData.empresa || userData.nombre_empresa || 'Miembro';

        const displayName = document.getElementById('userDisplayName');
        const dropdownName = document.getElementById('dropdownUserName');
        const dropdownRole = document.getElementById('dropdownUserRole');
        const avatar = document.getElementById('userAvatar');
        const welcomeTextEl = document.getElementById('welcomeText');

        if (displayName) displayName.textContent = nombre;
        if (dropdownName) dropdownName.textContent = nombre;
        if (dropdownRole) dropdownRole.textContent = rol;
        if (avatar) avatar.textContent = nombre.trim().charAt(0).toUpperCase() || '?';
        if (welcomeTextEl && breadcrumb) welcomeTextEl.textContent = `Bienvenido, ${nombre}`;

        if (menuWrap) menuWrap.style.display = 'block';
        if (loginItem) loginItem.style.display = 'none';

        const rolLower = String(rol).toLowerCase();
        const esAdmin = ['admin', 'administrador', 'root'].includes(rolLower);
        if (adminBtn) adminBtn.style.display = esAdmin ? 'flex' : 'none';
    }

    if (typeof window.toggleUserMenu !== 'function') {
        window.toggleUserMenu = function () {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown) dropdown.classList.toggle('hidden');
        };
    }

    if (typeof window.handleLogout !== 'function') {
        window.handleLogout = function () {
            if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                localStorage.removeItem('userData');
                localStorage.removeItem('currentUser');
                sessionStorage.clear();
                window.location.href = './pages/sign-in.html';
            }
        };
    }

    document.addEventListener('click', function (event) {
        const userMenu = document.getElementById('userMenuBtn');
        const dropdown = document.getElementById('userDropdown');
        if (userMenu && dropdown && !userMenu.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });

    renderSession();
    // Reintentar tras la carga por si la sesión se guarda de forma
    // asíncrona (fetch a la API) después de que este script ya corrió.
    document.addEventListener('DOMContentLoaded', renderSession);
    window.addEventListener('load', renderSession);
})();
