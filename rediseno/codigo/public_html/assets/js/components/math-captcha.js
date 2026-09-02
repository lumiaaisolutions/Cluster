/**
 * math-captcha.js — Captcha local sin servicios externos.
 * Genera "5 + 3 = ?" y valida en cliente + servidor.
 *
 * Uso HTML:
 *   <div data-math-captcha>
 *     <label data-captcha-label></label>
 *     <input type="number" data-captcha-input required>
 *     <input type="hidden" data-captcha-token name="captcha_token">
 *   </div>
 *
 * El token enviado al servidor es: base64( a + ":" + b + ":" + sum + ":" + nonce + ":" + hmac )
 * El servidor revalida con secret en .env (CAPTCHA_SECRET).
 */

function rand(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

async function hmac(msg, secret = 'clautmet-captcha-public') {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
    'raw', enc.encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false, ['sign']
  );
  const sig = await crypto.subtle.sign('HMAC', key, enc.encode(msg));
  return Array.from(new Uint8Array(sig)).map(b => b.toString(16).padStart(2, '0')).join('');
}

export async function initMathCaptcha(scope = document) {
  const widgets = scope.querySelectorAll('[data-math-captcha]');
  for (const w of widgets) {
    const label = w.querySelector('[data-captcha-label]');
    const input = w.querySelector('[data-captcha-input]');
    const token = w.querySelector('[data-captcha-token]');
    if (!label || !input || !token) continue;

    const a = rand(2, 9), b = rand(2, 9);
    const sum = a + b;
    const nonce = Math.random().toString(36).slice(2, 10);
    const expected = `${a}:${b}:${sum}:${nonce}`;
    const sig = await hmac(expected);
    const tokenStr = btoa(`${expected}:${sig}`);

    label.textContent = `¿Cuánto es ${a} + ${b}?`;
    input.value = '';
    token.value = tokenStr;
    input.dataset.captchaExpected = String(sum);
  }
}

export function validateMathCaptchaSync(scope = document) {
  for (const w of scope.querySelectorAll('[data-math-captcha]')) {
    const input = w.querySelector('[data-captcha-input]');
    if (!input) continue;
    const expected = parseInt(input.dataset.captchaExpected || '-1');
    if (parseInt(input.value || '0') !== expected) return false;
  }
  return true;
}
