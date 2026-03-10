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
                'resources/css/wochenplan.css',
                'resources/js/wochenplan.js',
                'resources/css/rooms.css',
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
            ],
        }),
    ],
    server: {
        hmr: {
            host: 'localhost',
        },
    },
});
