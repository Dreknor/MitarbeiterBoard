import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

// Alpine global verfügbar machen, damit andere Skripte (z.B. wochenplan.js)
// über den alpine:init-Hook Komponenten registrieren können.
window.Alpine = Alpine;

// Sidebar-Toggle für Mobile
document.addEventListener('DOMContentLoaded', function () {
    const sidebar     = document.getElementById('tw-sidebar');
    const overlay     = document.getElementById('sidebar-overlay');
    const openBtn     = document.getElementById('sidebar-open-btn');
    const closeBtn    = document.getElementById('sidebar-close-btn');
    const reopenBtn   = document.getElementById('sidebar-reopen-btn');

    const isMobile = () => window.innerWidth < 768;

    function openSidebar() {
        if (isMobile()) {
            sidebar?.classList.add('sidebar-open');
            overlay?.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            document.body.classList.remove('sidebar-collapsed');
        }
    }

    function closeSidebar() {
        if (isMobile()) {
            sidebar?.classList.remove('sidebar-open');
            overlay?.classList.remove('active');
            document.body.style.overflow = '';
        } else {
            document.body.classList.add('sidebar-collapsed');
        }
    }

    if (openBtn)    openBtn.addEventListener('click', openSidebar);
    if (reopenBtn)  reopenBtn.addEventListener('click', openSidebar);
    if (closeBtn)   closeBtn.addEventListener('click', closeSidebar);
    if (overlay)    overlay.addEventListener('click', closeSidebar);

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
