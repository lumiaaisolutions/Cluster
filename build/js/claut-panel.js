/**
 * Claut Panel — hero con secuencia scrubbed + ensamblado al scroll.
 * Motor del rediseño 2026 (pareja de css/claut-panel.css).
 *
 * Uso: colocar el <script> exactamente donde debe aparecer el hero
 * (normalmente justo después del <script> de claut-page-header.js):
 *
 *   <script src="./js/claut-panel.js?v=..."
 *           data-eyebrow="Beneficios exclusivos"
 *           data-title="Descuentos"
 *           data-tagline="Convenios para los socios del <strong>Clúster</strong>"
 *           data-reveal=".mi-seccion, .otro-bloque"
 *           data-sequence="on"></script>
 *
 * data-eyebrow   texto pequeño sobre el título (obligatorio en la práctica)
 * data-title     título grande con gradiente (obligatorio)
 * data-tagline   subtítulo; admite <strong> (opcional)
 * data-reveal    selectores CSS (coma-separados) de bloques ESTÁTICOS que
 *                se "ensamblan" al entrar al viewport (opcional). No apuntar
 *                a contenido generado por JS después de la carga: el observer
 *                corre una sola vez al inicio.
 * data-sequence  "off" desactiva la secuencia de rayos-X (por defecto activa)
 * data-compact   "1" usa la variante baja del hero (por defecto en páginas)
 *
 * La secuencia reutiliza los frames de la landing
 * (/landing/assets/sequence/, 168 jpg) cargando 1 de cada 2 (~2 MB);
 * el navegador los cachea entre páginas porque las URLs son las mismas.
 * Respeta prefers-reduced-motion: sin secuencia, sin reveals, frame fijo.
 */
(function () {
    'use strict';

    const scriptEl = document.currentScript;
    if (!scriptEl) return;

    const cfg = {
        eyebrow: scriptEl.dataset.eyebrow || 'Clúster Intranet',
        title: scriptEl.dataset.title || document.title,
        tagline: scriptEl.dataset.tagline || '',
        reveal: scriptEl.dataset.reveal || '',
        sequence: scriptEl.dataset.sequence !== 'off',
        compact: scriptEl.dataset.compact !== '0'
    };
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- 1. Inyección del hero ---------- */
    const heroClass = cfg.compact ? 'dph-hero dph-hero--page' : 'dph-hero';
    const html = `
<section class="${heroClass}">
    <canvas class="dph-hero-canvas" aria-hidden="true"></canvas>
    <div class="dph-hero-veil" aria-hidden="true"></div>
    <div class="dph-hero-inner">
        <div class="dph-hero-copy">
            <div class="dph-greet-eyebrow-row">
                <span class="dph-greet-rule" aria-hidden="true"></span>
                <p class="dph-greet-eyebrow">${cfg.eyebrow}</p>
            </div>
            <h1 class="dph-greet-title">${cfg.title}</h1>
            ${cfg.tagline ? `<p class="dph-hero-tagline">${cfg.tagline}</p>` : ''}
            <div class="dph-hero-meta">
                <div class="dph-greet-date"></div>
                <div class="dph-social-row">
                    <a href="https://www.linkedin.com/company/clusterautomotrizmetropolitano" target="_blank" rel="noopener noreferrer" class="dph-social-btn" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="https://www.instagram.com/claumetro" target="_blank" rel="noopener noreferrer" class="dph-social-btn" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.facebook.com/Clautmet/" target="_blank" rel="noopener noreferrer" class="dph-social-btn" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                </div>
            </div>
        </div>
        <div class="dph-hero-brand" aria-hidden="true">
            <div class="dph-hero-logo-ring">
                <img src="./assets/img/apple-icon.png" alt="" class="dph-hero-logo">
            </div>
            <span class="dph-hero-brand-caption">Clúster Intranet</span>
        </div>
    </div>
    <div class="dph-hero-scrollhint" aria-hidden="true"><span></span></div>
</section>`;
    scriptEl.insertAdjacentHTML('beforebegin', html);
    const hero = scriptEl.previousElementSibling;

    /* ---------- 2. Fecha ---------- */
    const dateEl = hero.querySelector('.dph-greet-date');
    if (dateEl) {
        const fmt = new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
        dateEl.textContent = fmt.format(new Date());
    }

    /* ---------- 3. Secuencia scrubbed ---------- */
    function initSequence() {
        const canvas = hero.querySelector('.dph-hero-canvas');
        if (!canvas || !cfg.sequence) return;

        const FRAME_COUNT = 168;
        const STEP = 2;
        const FRAME_PATH = './landing/assets/sequence/ezgif-frame-';
        const ctx = canvas.getContext('2d', { alpha: false });
        const frames = new Array(FRAME_COUNT);
        let renderedIdx = -1;
        let targetIdx = 0;
        let rafPending = false;

        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        function resize() {
            const rect = hero.getBoundingClientRect();
            canvas.width = Math.max(1, Math.round(rect.width * dpr));
            canvas.height = Math.max(1, Math.round(rect.height * dpr));
            renderedIdx = -1;
            draw();
        }

        function paint(img) {
            const cw = canvas.width, ch = canvas.height;
            const scale = Math.max(cw / img.naturalWidth, ch / img.naturalHeight);
            const w = img.naturalWidth * scale, h = img.naturalHeight * scale;
            ctx.drawImage(img, (cw - w) / 2, (ch - h) / 2, w, h);
        }

        function nearestLoaded(idx) {
            for (let d = 0; d < FRAME_COUNT; d++) {
                for (const j of [idx - d, idx + d]) {
                    if (j >= 0 && j < FRAME_COUNT && frames[j] && frames[j].complete && frames[j].naturalWidth) return j;
                }
            }
            return -1;
        }

        function draw() {
            const j = nearestLoaded(targetIdx);
            if (j === -1 || j === renderedIdx) return;
            paint(frames[j]);
            renderedIdx = j;
        }

        function loadFrame(i, onload) {
            if (frames[i]) return;
            const img = new Image();
            img.decoding = 'async';
            img.src = FRAME_PATH + String(i + 1).padStart(3, '0') + '.jpg';
            if (onload) img.onload = onload;
            frames[i] = img;
        }

        loadFrame(0, draw);
        if (!reduceMotion) {
            let queued = 0;
            for (let i = STEP; i < FRAME_COUNT; i += STEP) {
                setTimeout(() => loadFrame(i, draw), 40 * (queued++));
            }
        }

        function onScroll() {
            if (reduceMotion || rafPending) return;
            rafPending = true;
            requestAnimationFrame(() => {
                rafPending = false;
                const rect = hero.getBoundingClientRect();
                const range = rect.height + window.innerHeight * 0.35;
                const progress = Math.min(Math.max((window.innerHeight * 0.2 - rect.top) / range, 0), 1);
                targetIdx = Math.round(progress * (FRAME_COUNT - 1));
                draw();
            });
        }

        // capture:true — atrapa el scroll aunque ocurra en un contenedor
        // interno con overflow y no en window.
        document.addEventListener('scroll', onScroll, { capture: true, passive: true });
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(resize, 120);
        });

        resize();
        onScroll();
    }

    /* ---------- 4. Ensamblado al scroll ---------- */
    function initReveals() {
        if (reduceMotion || !cfg.reveal || !('IntersectionObserver' in window)) return;

        let targets = [];
        try {
            targets = Array.from(document.querySelectorAll(cfg.reveal));
        } catch (e) {
            return; // selector inválido: mejor sin reveals que romper la página
        }
        if (!targets.length) return;

        targets.forEach(el => el.classList.add('dph-reveal'));

        let batch = 0;
        let batchReset;
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.style.setProperty('--dph-reveal-delay', (batch++ * 90) + 'ms');
                clearTimeout(batchReset);
                batchReset = setTimeout(() => { batch = 0; }, 250);
                entry.target.classList.add('dph-in');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

        targets.forEach(el => io.observe(el));
    }

    initSequence();
    // Los reveals esperan al DOM completo: sus selectores suelen apuntar a
    // bloques declarados después de este script.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReveals);
    } else {
        initReveals();
    }
})();
