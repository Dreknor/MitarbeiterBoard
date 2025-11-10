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

    // ==========================================
    // PASSIVE EVENT LISTENERS
    // ==========================================

    // Füge passive Event Listener für bessere Scroll-Performance hinzu
    function addPassiveListeners() {
        const scrollContainers = document.querySelectorAll('.table-responsive, .modal-body, #tasksList, .card-body');

        scrollContainers.forEach(container => {
            // Entferne existierende Listener und füge passive hinzu
            container.addEventListener('touchstart', function(e) {
                // Speichere Start-Position für Momentum-Berechnung
                this._touchStartY = e.touches[0].clientY;
            }, { passive: true });

            container.addEventListener('touchmove', function(e) {
                // Erlaube natives Scrolling
            }, { passive: true });
        });
    }

    // ==========================================
    // PREVENT SCROLL BLOCKING
    // ==========================================

    // Verhindere, dass interaktive Elemente das Scrollen blockieren
    function optimizeInteractiveElements() {
        // Buttons, Links und Inputs in scrollbaren Containern
        const interactiveElements = document.querySelectorAll(
            '.table-responsive button, .table-responsive a, .table-responsive input, ' +
            '.table-responsive select, .table-responsive textarea, ' +
            '.modal-body button, .modal-body a, .modal-body input'
        );

        interactiveElements.forEach(element => {
            element.addEventListener('touchstart', function(e) {
                // Verhindere Scroll-Blocking, aber erlaube Click-Events
                e.stopPropagation();
            }, { passive: true });
        });
    }

    // ==========================================
    // DEBOUNCED SCROLL OPTIMIZATION
    // ==========================================

    // Reduziere Recalculations während des Scrollens
    let scrollTimeout;
    function optimizeScrollPerformance() {
        const scrollContainers = document.querySelectorAll('.table-responsive');

        scrollContainers.forEach(container => {
            container.addEventListener('scroll', function() {
                // Deaktiviere aufwendige Rendering-Operationen während des Scrollens
                this.classList.add('is-scrolling');

                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    this.classList.remove('is-scrolling');
                }, 150);
            }, { passive: true });
        });
    }

    // ==========================================
    // VIRTUAL SCROLL HELPER (für lange Listen)
    // ==========================================

    // Optimiere sehr lange Tabellen durch intelligentes Rendern
    function optimizeLongTables() {
        const tables = document.querySelectorAll('#entriesTable, #tasksTable, #columnsTable');

        tables.forEach(table => {
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const rows = tbody.querySelectorAll('tr');
            if (rows.length < 50) return; // Nur für lange Tabellen

            // Füge Intersection Observer für Lazy-Rendering hinzu
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                rootMargin: '100px' // Lade Zeilen 100px vor Sichtbarkeit
            });

            rows.forEach(row => {
                observer.observe(row);
            });
        });
    }

    // ==========================================
    // TOUCH GESTURE OPTIMIZATION
    // ==========================================

    // Optimiere horizontales Wischen in Tabellen
    function optimizeTableSwipe() {
        const tableContainers = document.querySelectorAll('.table-responsive');

        tableContainers.forEach(container => {
            let touchStartX = 0;
            let touchStartY = 0;

            container.addEventListener('touchstart', function(e) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            }, { passive: true });

            container.addEventListener('touchmove', function(e) {
                const touchEndX = e.touches[0].clientX;
                const touchEndY = e.touches[0].clientY;

                const deltaX = Math.abs(touchEndX - touchStartX);
                const deltaY = Math.abs(touchEndY - touchStartY);

                // Wenn primär horizontal gewischt wird, optimiere für horizontales Scrollen
                if (deltaX > deltaY) {
                    container.style.overflowY = 'hidden';
                } else {
                    container.style.overflowY = 'auto';
                }
            }, { passive: true });

            container.addEventListener('touchend', function() {
                // Stelle Overflow nach Touch zurück
                setTimeout(() => {
                    container.style.overflowY = 'auto';
                }, 100);
            }, { passive: true });
        });
    }

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
    // REDUCE REPAINT DURING SCROLL
    // ==========================================

    // CSS-Klasse für Scroll-Optimierung
    const style = document.createElement('style');
    style.textContent = `
        /* Reduziere Repaints während des Scrollens */
        .is-scrolling * {
            pointer-events: none !important;
        }

        .is-scrolling {
            pointer-events: auto !important;
        }

        /* Optimiere Visibility für außerhalb sichtbarer Bereich */
        .table-responsive tr:not(.visible) {
            content-visibility: auto;
        }

        /* Optimiere Rendering-Performance */
        .table-responsive.is-scrolling {
            will-change: scroll-position;
        }
    `;
    document.head.appendChild(style);

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
        addPassiveListeners();
        optimizeInteractiveElements();
        optimizeScrollPerformance();
        optimizeLongTables();
        optimizeTableSwipe();
        enableSmoothScroll();
        rememberScrollPositions();
        fixModalScroll();

        // Beobachte dynamisch hinzugefügte Inhalte
        const observer = new MutationObserver(() => {
            // Re-optimiere wenn neue Inhalte hinzugefügt werden
            addPassiveListeners();
            optimizeInteractiveElements();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('Tablet Scroll Optimierungen aktiviert ✓');
    }

    // Starte Initialisierung
    init();

})();

