/**
 * theme.js — Tema visual dinámico (theme_config) + light/dark toggle
 */

import { api } from './api.js';

let _config = null;

export async function loadThemeConfig() {
  if (_config) return _config;
  const r = await api('theme-config.php', { bust: false });
  _config = r?.success ? (r.data || {}) : {};
  applyConfigToCss();
  return _config;
}

export function getConfig(key, fallback = '') {
  return _config?.[key] ?? fallback;
}

function applyConfigToCss() {
  const root = document.documentElement;
  const map = {
    brand_color_primary: '--brand',
    brand_color_dark:    '--bg-dark',
    brand_color_light:   '--bg-light',
    brand_color_carbon:  '--carbon',
  };
  Object.entries(map).forEach(([k, cssVar]) => {
    if (_config[k]) root.style.setProperty(cssVar, _config[k]);
  });
}

export function getThemeMode() {
  return document.documentElement.getAttribute('data-theme') || 'light';
}

export function applyTheme(mode) {
  const next = mode === 'dark' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  document.dispatchEvent(new CustomEvent('themechange', { detail: { mode: next } }));
}

export function toggleTheme() {
  const cur = getThemeMode();
  applyTheme(cur === 'dark' ? 'light' : 'dark');
}

export function bootstrapTheme() {
  const saved = localStorage.getItem('theme');
  if (saved === 'dark' || saved === 'light') {
    applyTheme(saved);
  } else {
    const prefers = window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(prefers ? 'dark' : 'light');
  }
}

// Si el usuario no eligió manualmente, seguir prefers-color-scheme
window.matchMedia?.('(prefers-color-scheme: dark)')
  .addEventListener('change', e => {
    if (!localStorage.getItem('theme')) applyTheme(e.matches ? 'dark' : 'light');
  });
