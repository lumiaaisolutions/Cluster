/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './public_html/**/*.html',
    './public_html/**/*.php',
    './public_html/assets/js/**/*.js',
  ],
  darkMode: ['selector', '[data-theme="dark"]'],
  theme: {
    extend: {
      colors: {
        bg:      'var(--bg)',
        text:    'var(--text)',
        brand:   'var(--brand)',
        surface: 'var(--surface)',
        border:  'var(--border)',
        muted:   'var(--muted)',
        carbon:  'var(--carbon, #1D1D1F)',
      },
      fontFamily: {
        sans: ['InterVariable', 'Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
        display: ['InterDisplay', 'InterVariable', 'Inter', 'sans-serif'],
      },
      letterSpacing: {
        tightest: '-0.04em',
        tighter: '-0.025em',
        tight: '-0.015em',
      },
      transitionTimingFunction: {
        liquid:  'cubic-bezier(0.22, 1, 0.36, 1)',
        snappy:  'cubic-bezier(0.16, 1, 0.3, 1)',
        spring:  'cubic-bezier(0.34, 1.56, 0.64, 1)',
      },
      transitionDuration: {
        '600': '600ms',
        '900': '900ms',
      },
      maxWidth: {
        '8xl': '88rem',
        '9xl': '96rem',
      },
      animation: {
        'fade-up':    'fadeUp 800ms cubic-bezier(0.22, 1, 0.36, 1) both',
        'fade-in':    'fadeIn 600ms cubic-bezier(0.22, 1, 0.36, 1) both',
        'shimmer':    'shimmer 2s linear infinite',
      },
      keyframes: {
        fadeUp: {
          '0%':   { opacity: 0, transform: 'translateY(24px)' },
          '100%': { opacity: 1, transform: 'translateY(0)' },
        },
        fadeIn: {
          '0%':   { opacity: 0 },
          '100%': { opacity: 1 },
        },
        shimmer: {
          '0%':   { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
      },
    },
  },
  plugins: [],
};
