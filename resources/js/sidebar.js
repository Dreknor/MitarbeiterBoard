import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

// Alpine global verfügbar machen, damit andere Skripte (z.B. wochenplan.js)
// über den alpine:init-Hook Komponenten registrieren können.
window.Alpine = Alpine;

// Sidebar-Toggle für Mobile
document.addEventListener('DOMContentLoaded', function () {
    const sidebar   = document.getElementById('tw-sidebar');
    const overlay   = document.getElementById('sidebar-overlay');
    const openBtn   = document.getElementById('sidebar-open-btn');
    const closeBtn  = document.getElementById('sidebar-close-btn');

    function openSidebar() {
        if (sidebar)  sidebar.classList.add('sidebar-open');
        if (overlay)  overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar)  sidebar.classList.remove('sidebar-open');
        if (overlay)  overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (openBtn)  openBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay)  overlay.addEventListener('click', closeSidebar);

    // Escape-Taste schließt Sidebar
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });
});

// Alpine erst beim window-load starten, damit alle anderen deferred Vite-Module
// (wochenplan.js, diagnostics.js etc.) ihre Alpine.data()-Komponenten
// auf window.Alpine registriert haben, bevor Alpine den DOM initialisiert.
window.addEventListener('load', () => {
    Alpine.start();
});
