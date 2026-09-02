/**
 * i18n.js — Bilingüe ES/EN solo UI (D4 fricciones)
 * Uso:
 *   <span data-i18n="nav.directorio"></span>
 *   <input data-i18n-attr="placeholder:auth.email_placeholder">
 *   t('auth.welcome', { name: 'Juan' }) -> reemplaza {name}
 */

let _dict = {};
let _lang = 'es';

export function getLang() {
  return _lang;
}

export async function loadLang(lang) {
  _lang = lang || localStorage.getItem('lang') || 'es';
  if (!['es', 'en'].includes(_lang)) _lang = 'es';
  try {
    const res = await fetch(`/i18n/${_lang}.json?t=${Date.now()}`);
    _dict = await res.json();
  } catch {
    _dict = {};
  }
  localStorage.setItem('lang', _lang);
  document.documentElement.lang = _lang;
  applyTranslations();
  document.dispatchEvent(new CustomEvent('langchange', { detail: { lang: _lang } }));
}

function getKey(path) {
  return path.split('.').reduce((o, k) => o?.[k], _dict);
}

export function t(key, vars = {}) {
  let v = getKey(key) ?? key;
  if (typeof v === 'string') {
    Object.entries(vars).forEach(([k, val]) => {
      v = v.replace(new RegExp(`\\{${k}\\}`, 'g'), val);
    });
  }
  return v;
}

function applyTranslations(root = document) {
  root.querySelectorAll('[data-i18n]').forEach(el => {
    const v = getKey(el.dataset.i18n);
    if (v) el.textContent = v;
  });
  root.querySelectorAll('[data-i18n-html]').forEach(el => {
    const v = getKey(el.dataset.i18nHtml);
    if (v) el.innerHTML = v;
  });
  root.querySelectorAll('[data-i18n-attr]').forEach(el => {
    el.dataset.i18nAttr.split(';').forEach(spec => {
      const [attr, key] = spec.split(':').map(s => s.trim());
      const v = getKey(key);
      if (v) el.setAttribute(attr, v);
    });
  });
}

export function switchLang(lang) {
  if (lang === _lang) return;
  loadLang(lang);
}

export const refreshDom = applyTranslations;
