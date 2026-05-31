import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/dashboard.css',
                'resources/js/dashboard.js',
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
                'resources/css/hort-planung.css',

                // Personal-Modul (Phase 0)
                'resources/css/personal.css',
                'resources/js/personal.js',

                // Prozesse-Modul (Phase 2)
                'resources/css/procedure.css',
                'resources/js/procedure.js',

                // Meetings-Modul (Tailwind-Migration)
                'resources/css/meetings.css',

                // Themenarchiv (Tailwind-Migration, ersetzt CDN)
                'resources/css/themes-archive.css',
                'resources/js/themes-archive.js',
            ],
            refresh: [
                'resources/views/dashboard/**/*.blade.php',
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
                'resources/views/personal/hort_planung/**/*.blade.php',
                'app/Http/Controllers/Personal/HortPlanungController.php',
                // Personal-Modul (Phase 0)
                'resources/views/personal/**/*.blade.php',
                'app/Http/Controllers/Personal/*.php',
                // Prozesse-Modul (Phase 2)
                'resources/views/procedure/**/*.blade.php',
                'app/Http/Controllers/ProcedureController.php',
                // Meetings-Modul (Tailwind-Migration)
                'resources/views/meetings/**/*.blade.php',
                'app/Http/Controllers/MeetingController.php',
                // Themenarchiv (Tailwind-Migration)
                'resources/views/themes/archive.blade.php',
            ],
        }),
    ],
    server: {
        hmr: {
            host: 'localhost',
        },
    },
});
