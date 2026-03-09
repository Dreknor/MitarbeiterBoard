import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

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

// Alpine starten
Alpine.start();

