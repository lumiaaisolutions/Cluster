/**
 * api.js — Wrapper unificado de fetch para CLAUTMET Intranet
 * - credentials: 'include' siempre (BUG-012)
 * - X-CSRF-TOKEN header automático en mutaciones
 * - Manejo de 401 → /login.html
 * - Manejo de 429 → toast
 * - Soporta JSON y FormData (FormData no setea Content-Type, deja al browser)
 *
 * Uso:
 *   import { api } from '/assets/js/core/api.js';
 *   const r = await api('eventos.php');
 *   const r = await api('eventos.php', { method: 'POST', body: JSON.stringify(payload) });
 *   const r = await api('boletines.php', { method: 'POST', body: formData });  // BUG-014
 */

const META_CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

// Detección de entorno — clautmetropolitano.mx sin tilde (BUG-011)
const isProd = location.hostname.includes('clautmetropolitano');
export const API_BASE = isProd ? '/api' : (location.origin + '/api');

let _toastShown = false;

export async function api(path, opts = {}) {
  const isFormData = opts.body instanceof FormData;
  const method = (opts.method || 'GET').toUpperCase();
  const isMutation = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method);

  const headers = {
    ...(isMutation && { 'X-CSRF-TOKEN': META_CSRF() }),
    ...(!isFormData && opts.body && { 'Content-Type': 'application/json' }),
    ...opts.headers,
  };

  let url = `${API_BASE}/${path}`;
  // Cache busting para GETs frecuentes (BUG-020)
  if (method === 'GET' && opts.bust !== false) {
    url += (url.includes('?') ? '&' : '?') + 't=' + Date.now();
  }

  let res;
  try {
    res = await fetch(url, {
      credentials: 'include',
      method,
      headers,
      body: opts.body,
      signal: opts.signal,
    });
  } catch (err) {
    const { toast } = await import('./toast.js');
    toast.error('No pudimos conectar. Reintenta en un momento.');
    throw err;
  }

  if (res.status === 401) {
    // Sesión expirada
    if (!location.pathname.endsWith('/login.html')) {
      location.href = '/login.html?reason=expired';
    }
    return { success: false, message: 'Sesión expirada' };
  }

  if (res.status === 429) {
    const { toast } = await import('./toast.js');
    if (!_toastShown) {
      _toastShown = true;
      toast.error('Demasiadas solicitudes. Intenta en unos segundos.');
      setTimeout(() => (_toastShown = false), 4000);
    }
    return { success: false, message: 'Rate limited' };
  }

  // Algunos endpoints PHP devuelven texto plano cuando hay PHP warnings — manejarlo
  const text = await res.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    console.warn('[api] respuesta no-JSON', { path, status: res.status, text });
    data = { success: false, message: 'Respuesta inválida del servidor' };
  }

  if (!res.ok && data?.success === undefined) {
    data.success = false;
    data.status = res.status;
  }
  return data;
}

/**
 * Carga el CSRF token al inicio y lo inyecta en <meta>.
 * Llamar una vez al cargar la app.
 */
export async function bootstrapCsrf() {
  let meta = document.querySelector('meta[name="csrf-token"]');
  if (!meta) {
    meta = document.createElement('meta');
    meta.setAttribute('name', 'csrf-token');
    document.head.appendChild(meta);
  }
  if (meta.content) return meta.content;
  try {
    const r = await api('csrf-token.php', { bust: false });
    if (r?.token) {
      meta.content = r.token;
      return r.token;
    }
  } catch (e) {
    console.warn('[api] csrf bootstrap failed', e);
  }
  return '';
}
