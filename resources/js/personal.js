/**
 * Personal-Modul – Alpine.js
 *
 * Nur initialisieren wenn noch nicht global (z.B. durch app.js) gestartet.
 */
import Alpine from 'alpinejs';

// Organigramm-Komponente
import './personal/orgchart.js';

// Gemeinsame Alpine-Komponente: Tab-Navigation
Alpine.data('personalTabs', (defaultTab = null) => ({
    activeTab: defaultTab,

    setTab(tab) {
        this.activeTab = tab;
        // Tab-Namen im URL-Hash speichern (Direktlink)
        if (history.replaceState) {
            history.replaceState(null, '', '#' + tab);
        }
    },

    isActive(tab) {
        return this.activeTab === tab;
    },

    init() {
        // Tab aus URL-Hash lesen
        const hash = window.location.hash.replace('#', '');
        if (hash) this.activeTab = hash;
    },
}));

// Alpine.js starten (nur wenn noch nicht gestartet)
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}

