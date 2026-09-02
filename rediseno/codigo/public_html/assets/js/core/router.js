/**
 * router.js — Page transitions con Swup (cargado via CDN).
 * Solo activo en zonas autenticadas (/app/*) para sentir SPA sin React.
 *
 * Uso: incluir en HTML donde quieras transiciones:
 *   <script src="https://cdn.jsdelivr.net/npm/swup@4.6.0/dist/Swup.min.js"></script>
 *   <script type="module" src="/assets/js/core/router.js"></script>
 *   <main id="main" class="transition-[opacity,transform] duration-300"> ... </main>
 */

let _swup = null;

export function initRouter() {
  if (typeof window === 'undefined' || !window.Swup) return null;
  if (_swup) return _swup;

  _swup = new window.Swup({
    containers: ['#main'],
    cache: { disable: false },
    animationSelector: '[data-swup-anim]',
    linkSelector: 'a[href^="/app/"], a[href^="/admin/"], a[data-swup]',
  });

  // Re-init de módulos por ruta
  _swup.hooks.on('content:replace', () => {
    runRouteModule();
    document.dispatchEvent(new CustomEvent('routechange'));
  });

  // Primer load
  runRouteModule();
  return _swup;
}

async function runRouteModule() {
  const path = location.pathname;
  const map = {
    '/app/dashboard.html':       '/assets/js/modules/dashboard.js',
    '/app/directorio.html':      '/assets/js/modules/directorio.js',
    '/app/empresa-detalle.html': '/assets/js/modules/empresa-detalle.js',
    '/app/eventos.html':         '/assets/js/modules/eventos.js',
    '/app/evento-detalle.html':  '/assets/js/modules/evento-detalle.js',
    '/app/calendario.html':      '/assets/js/modules/calendario.js',
    '/app/comites.html':         '/assets/js/modules/comites.js',
    '/app/comite-detalle.html':  '/assets/js/modules/comite-detalle.js',
    '/app/boletines.html':       '/assets/js/modules/boletines.js',
    '/app/documentos.html':      '/assets/js/modules/documentos.js',
    '/app/descuentos.html':      '/assets/js/modules/descuentos.js',
    '/app/perfil.html':          '/assets/js/modules/perfil.js',
    '/app/perfil-empresa.html':  '/assets/js/modules/perfil-empresa.js',
    '/app/buzon.html':           '/assets/js/modules/buzon.js',
    '/app/notificaciones.html':  '/assets/js/modules/notificaciones.js',
    '/admin/empresas.html':      '/assets/js/modules/admin-empresas.js',
    '/admin/eventos.html':       '/assets/js/modules/admin-eventos.js',
    '/admin/configuracion.html': '/assets/js/modules/admin-config.js',
  };
  const mod = map[path];
  if (mod) {
    try { await import(mod); } catch (e) { console.warn('[router] no module for', path, e); }
  }
}

export function navigate(url) {
  if (_swup) _swup.navigate(url);
  else location.href = url;
}
