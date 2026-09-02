/**
 * auth.js — Sesión y autorización CLAUTMET
 * Roles: superadmin, admin, empleado_cluster, empresa_socio, empleado, empresa
 */

import { api } from './api.js';

let _user = null;
let _userPromise = null;

export async function getUser({ refresh = false } = {}) {
  if (!refresh && _user) return _user;
  if (_userPromise) return _userPromise;
  _userPromise = api('auth/login-compatible.php?action=check').then(r => {
    _user = r?.success ? r.data : null;
    _userPromise = null;
    return _user;
  });
  return _userPromise;
}

export function getCachedUser() {
  return _user;
}

export function clearUserCache() {
  _user = null;
  _userPromise = null;
}

/**
 * Normaliza el rol y verifica permiso.
 * Reglas:
 *   - superadmin tiene acceso a todo
 *   - 'admin' === 'Administrador' === 'root' (case-insensitive)
 *   - empleado_cluster ≈ empleado con departamento
 *   - empresa_socio ≈ empresa
 */
export function userHasRole(user, allowed = []) {
  if (!user) return false;
  const isSuper = Number(user.superadmin) === 1;
  if (allowed.includes('superadmin') && isSuper) return true;
  const role = String(user.user_rol || user.rol || '').toLowerCase();
  const normalized =
    role === 'administrador' || role === 'root' ? 'admin' :
    role === 'empresa' ? 'empresa_socio' :
    role === 'empleado' ? (user.departamento ? 'empleado_cluster' : 'empleado') :
    role;
  if (allowed.includes(normalized)) return true;
  if (isSuper) return true; // superadmin entra a todo
  return false;
}

export async function requireRole(allowed = []) {
  const u = await getUser();
  if (!u) {
    location.href = '/login.html?reason=auth';
    return null;
  }
  if (!userHasRole(u, allowed)) {
    location.href = '/app/dashboard.html?reason=forbidden';
    return null;
  }
  return u;
}

export async function logout() {
  try { await api('auth/logout.php', { method: 'POST' }); } catch {}
  clearUserCache();
  location.href = '/login.html';
}

export async function login(email, password) {
  return api('auth/login-compatible.php?action=login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
}
