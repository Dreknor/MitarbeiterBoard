import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/diagnostics.css',
                'resources/js/diagnostics.js'
            ],
            refresh: [
                'resources/views/diagnostics/**/*.blade.php',
                'app/Http/Controllers/DiagnosticController.php',
                'app/Http/Controllers/DiagnosticAdminController.php'
            ],
        }),
    ],
    server: {
        hmr: {
            host: 'localhost',
        },
    },
});

