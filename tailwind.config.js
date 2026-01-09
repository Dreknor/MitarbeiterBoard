module.exports = {
  // Scan only diagnostic-related files to avoid conflicts
  content: [
    "./resources/views/diagnostics/**/*.blade.php",
    "./resources/js/diagnostics.js",
  ],
  // Disable preflight (CSS reset) to prevent conflicts with Bootstrap
  corePlugins: {
    preflight: false,
  },
  // Make Tailwind styles more specific to win over Bootstrap when needed
  important: '.diagnostic-wrapper',
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
      },
    },
  },
  plugins: [],
}

