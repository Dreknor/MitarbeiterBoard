import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/sidebar.css',
                'resources/js/sidebar.js',
                'resources/css/diagnostics.css',
                'resources/js/diagnostics.js',
                'resources/css/paed-diary.css',
                'resources/css/paed-diary-v2.css',
                'resources/js/paed-diary-v2.js',
                'resources/css/wochenplan.css',
                'resources/js/wochenplan.js',
                'resources/css/rooms.css',
                'resources/css/calendar.css',
                'resources/js/calendar.js',
            ],
            refresh: [
                'resources/views/layouts/**/*.blade.php',
                'resources/views/diagnostics/**/*.blade.php',
                'app/Http/Controllers/DiagnosticController.php',
                'app/Http/Controllers/DiagnosticAdminController.php',
                'resources/views/paedDiary/**/*.blade.php',
                'resources/views/wochenplan/**/*.blade.php',
                'app/Http/Controllers/Wochenplan/*.php',
                'resources/views/rooms/**/*.blade.php',
                'app/Http/Controllers/RoomController.php',
                'resources/views/calendar/**/*.blade.php',
                'app/Http/Controllers/CalendarController.php',
                'app/Http/Controllers/CalendarAdminController.php',
            ],
        }),
    ],
    server: {
        hmr: {
            host: 'localhost',
        },
    },
});
