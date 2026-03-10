module.exports = {
  // Scan diagnostic and paedDiary files
  content: [
    "./resources/views/diagnostics/**/*.blade.php",
    "./resources/js/diagnostics.js",
    "./resources/views/paedDiary/**/*.blade.php",
    "./resources/views/wochenplan/**/*.blade.php",
    "./resources/js/wochenplan.js",
    "./resources/views/rooms/**/*.blade.php",
    "./resources/css/rooms.css",
  ],
  // Disable preflight (CSS reset) to prevent conflicts with Bootstrap
  corePlugins: {
    preflight: false,
  },
  // Make Tailwind styles more specific to win over Bootstrap when needed
  important: true,
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

