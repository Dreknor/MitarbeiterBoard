// Tablet Touch & Scroll Optimization für Pädagogisches Tagebuch
// Verbessert die Touch-Responsivität und Scroll-Performance auf Tablets

(function() {
    'use strict';

    // Prüfe ob wir auf einem Touch-Gerät sind
    const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

    if (!isTouchDevice) {
        // Nicht auf Touch-Geräten, keine Optimierung nötig
        return;
    }

    console.log('Tablet Touch Optimization aktiviert');

    // HINWEIS: Frühere Funktionen (passive Listener per MutationObserver,
    // stopPropagation auf touchstart, `.is-scrolling * { pointer-events:none }`,
    // content-visibility auf Tabellenzeilen, overflow-Umschaltung beim Wischen)
    // wurden entfernt: Sie haben bei jeder DOM-Änderung tausende Listener
    // angehäuft und Taps auf Touch-Geräten verschluckt (z. B. Stufen-Menü).

    // ==========================================
    // SMOOTH SCROLL POLYFILL
    // ==========================================

    // Füge smooth scrolling für ältere iOS-Versionen hinzu
    function enableSmoothScroll() {
        // Prüfe ob smooth scroll bereits unterstützt wird
        if ('scrollBehavior' in document.documentElement.style) {
            return; // Native Unterstützung vorhanden
        }

        // Polyfill für ältere Browser
        const scrollContainers = document.querySelectorAll('.table-responsive, .modal-body');

        scrollContainers.forEach(container => {
            const originalScrollTo = container.scrollTo;

            container.scrollTo = function(options) {
                if (typeof options === 'object' && options.behavior === 'smooth') {
                    // Implementiere smooth scroll manuell
                    const start = this.scrollTop;
                    const target = options.top || 0;
                    const duration = 300;
                    const startTime = performance.now();

                    const animateScroll = (currentTime) => {
                        const elapsed = currentTime - startTime;
                        const progress = Math.min(elapsed / duration, 1);

                        // Easing function
                        const easeInOutQuad = progress < 0.5
                            ? 2 * progress * progress
                            : 1 - Math.pow(-2 * progress + 2, 2) / 2;

                        this.scrollTop = start + (target - start) * easeInOutQuad;

                        if (progress < 1) {
                            requestAnimationFrame(animateScroll);
                        }
                    };

                    requestAnimationFrame(animateScroll);
                } else {
                    originalScrollTo.apply(this, arguments);
                }
            };
        });
    }

    // ==========================================
    // SCROLL POSITION MEMORY
    // ==========================================

    // Merke Scroll-Positionen beim Navigieren zwischen Tabs
    function rememberScrollPositions() {
        const tabContents = document.querySelectorAll('.tab-pane .table-responsive');
        const scrollPositions = new Map();

        tabContents.forEach((container, index) => {
            // Speichere Position beim Scrollen
            container.addEventListener('scroll', function() {
                scrollPositions.set(index, this.scrollTop);
            }, { passive: true });
        });

        // Stelle Position wieder her wenn Tab aktiviert wird
        const tabs = document.querySelectorAll('[data-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function() {
                const pane = document.querySelector(this.getAttribute('href'));
                const container = pane?.querySelector('.table-responsive');
                const index = Array.from(tabContents).indexOf(container);

                if (container && scrollPositions.has(index)) {
                    setTimeout(() => {
                        container.scrollTop = scrollPositions.get(index);
                    }, 50);
                }
            });
        });
    }

    // ==========================================
    // MODAL SCROLL FIX
    // ==========================================

    // Verhindere Body-Scroll wenn Modal offen ist
    function fixModalScroll() {
        const modals = document.querySelectorAll('.modal');

        modals.forEach(modal => {
            modal.addEventListener('shown.bs.modal', function() {
                // Aktiviere Touch-Scrolling im Modal
                const modalBody = this.querySelector('.modal-body');
                if (modalBody) {
                    modalBody.style.webkitOverflowScrolling = 'touch';
                    modalBody.style.overflowScrolling = 'touch';
                }
            });
        });
    }

    // ==========================================
    // INITIALIZATION
    // ==========================================

    // Initialisiere alle Optimierungen wenn DOM bereit ist
    function init() {
        // Warte bis DOM vollständig geladen ist
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }

        console.log('Initialisiere Tablet Scroll Optimierungen...');

        // Aktiviere alle Optimierungen
        enableSmoothScroll();
        rememberScrollPositions();
        fixModalScroll();

        console.log('Tablet Scroll Optimierungen aktiviert ✓');
    }

    // Starte Initialisierung
    init();

})();

