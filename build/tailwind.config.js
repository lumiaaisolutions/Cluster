/**
 * Tailwind compilado para producción (CSP Fase A — reemplaza cdn.tailwindcss.com).
 *
 * Fusión de los tailwind.config inline que tenían las páginas (boletines,
 * comites, contacto, descuentos, profile) — la divergencia 'clúster-red':
 * 'black' de contacto.html era config muerta (la página no usa esa clase).
 *
 * Rebuild:
 *   cd build && npx tailwindcss@3.4.17 -c tailwind.config.js \
 *     -i src/tailwind.in.css -o dist/tailwind.css --minify
 *
 * El safelist cubre las clases con nombre unicode (clúster-*) — el extractor
 * de contenido de Tailwind no es confiable con caracteres no-ASCII — y las
 * variantes que el JS arma por concatenación.
 */
module.exports = {
  content: [
    './*.html',
    './*.php',
    './pages/*.html',
    './admin/*.php',
    './js/*.js',
    './assets/js/*.js',
    './components/**/*.html',
    './templates/**/*.php'
  ],
  safelist: [
    { pattern: /^(bg|text|border)-(clúster|cluster)-(red|red-dark|red-light|gray|dark)$/, variants: ['hover'] },
    'font-inter'
  ],
  theme: {
    extend: {
      colors: {
        'clúster-red': '#C7252B',
        'clúster-red-dark': '#A31E23',
        'clúster-red-light': '#E8434A',
        'clúster-gray': '#F5F5F7',
        'clúster-dark': '#09090b',
        'cluster-red': '#C7252B',
        'cluster-red-dark': '#A31E23',
        'cluster-red-light': '#E8434A'
      },
      fontFamily: {
        'inter': ['Inter', 'sans-serif']
      }
    }
  },
  corePlugins: { preflight: true }
};
