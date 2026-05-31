/**
 * Prozesse-Modul – Haupt-App Alpine.js Komponente
 * Steuert Tab-Navigation, Filter, Suche und Toast-Benachrichtigungen.
 */

document.addEventListener('alpine:init', () => {
    window.Alpine.data('procedureApp', () => ({
        activeTab: 'active',
        search: '',
        statusFilter: '',
        categoryFilter: null,
        toasts: [],
        _toastCounter: 0,

        init() {
            // Tab aus URL-Hash lesen
            const hash = window.location.hash.replace('#', '');
            if (['active', 'templates', 'automation'].includes(hash)) {
                this.activeTab = hash;
            }
            // URL-Hash bei Tab-Wechsel aktualisieren
            this.$watch('activeTab', (val) => {
                window.history.replaceState(null, '', '#' + val);
            });
        },

        setTab(tab) {
            this.activeTab = tab;
            // Filter zurücksetzen beim Tab-Wechsel
            this.search = '';
            this.statusFilter = '';
            this.categoryFilter = null;
        },

        /**
         * Aktive Prozesse filtern (nach Suche + Status).
         * Erwartet Array mit { id, name, category, steps_done, steps_total, steps_overdue, steps_due_soon, progress }.
         */
        filteredProcedures(procedures) {
            if (!procedures) return [];
            let result = procedures;

            // Volltext-Suche
            if (this.search.trim()) {
                const q = this.search.toLowerCase();
                result = result.filter(p =>
                    p.name.toLowerCase().includes(q) ||
                    (p.category && p.category.name.toLowerCase().includes(q))
                );
            }

            // Status-Filter
            if (this.statusFilter === 'overdue') {
                result = result.filter(p => p.steps_overdue > 0);
            } else if (this.statusFilter === 'due') {
                result = result.filter(p => p.steps_due_soon > 0 && p.steps_overdue === 0);
            } else if (this.statusFilter === 'open') {
                result = result.filter(p => p.progress < 100);
            } else if (this.statusFilter === 'done') {
                result = result.filter(p => p.steps_total > 0 && p.progress === 100);
            }

            return result;
        },

        /**
         * Vorlagen filtern (nach Suche + Kategorie).
         */
        filteredTemplates(templates, categoryId) {
            if (!templates) return [];
            let result = templates;
            if (categoryId !== null && categoryId !== undefined) {
                result = result.filter(t => t.category_id === categoryId);
            }
            if (this.search.trim()) {
                const q = this.search.toLowerCase();
                result = result.filter(t => t.name.toLowerCase().includes(q));
            }
            return result;
        },

        /** Toast erstellen */
        addToast(message, type = 'success', duration = 3500) {
            const id = ++this._toastCounter;
            this.toasts.push({ id, message, type });
            setTimeout(() => this.removeToast(id), duration);
        },

        removeToast(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
    }));

    // Global verfügbar machen für Nicht-Alpine-Kontext
    window.procedureToast = function (message, type = 'success') {
        const event = new CustomEvent('procedure-toast', { detail: { message, type } });
        document.dispatchEvent(event);
    };
});

