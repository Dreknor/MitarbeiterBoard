import Sortable from 'sortablejs';

/**
 * ============================================================
 * Dashboard Alpine.js Komponente
 * ============================================================
 * Alpine wird bereits durch sidebar.js geladen und gestartet.
 * Hier registrieren wir nur die Dashboard-Komponente via
 * window.Alpine.data() – KEIN eigener Alpine.start()!
 * ============================================================
 */
function registerDashboardComponents(Alpine) {
    if (!Alpine) {
        console.error('Dashboard: Alpine nicht verfügbar!');
        return;
    }

    Alpine.data('dashboardApp', (initialCards) => ({
        cards: initialCards || [],
        editMode: false,
        showSettings: false,
        _sortable: null,
        _saving: false,

        init() {
            // SortableJS initialisieren (deaktiviert bis editMode aktiv)
            this.$nextTick(() => {
                if (this.$refs.grid) {
                    this._sortable = Sortable.create(this.$refs.grid, {
                        handle: '.drag-handle',
                        animation: 150,
                        disabled: true,
                        onEnd: (evt) => this.reorder(evt),
                    });
                }
            });
        },

        toggleEditMode() {
            this.editMode = !this.editMode;
            if (this._sortable) {
                this._sortable.option('disabled', !this.editMode);
            }
        },

        reorder(evt) {
            // Neue Reihenfolge aus DOM lesen und cards[].order aktualisieren
            const items = this.$refs.grid.querySelectorAll('[data-card-id]');
            items.forEach((el, index) => {
                const cardId = parseInt(el.dataset.cardId);
                const card = this.cards.find(c => c.id === cardId);
                if (card) {
                    card.order = index;
                }
            });
        },

        resizeCard(cardId, width) {
            const card = this.cards.find(c => c.id === cardId);
            if (card) {
                card.width = width;
            }
        },

        toggleCard(cardId) {
            const card = this.cards.find(c => c.id === cardId);
            if (card) {
                card.active = !card.active;
            }
        },

        async saveLayout() {
            if (this._saving) return;
            this._saving = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const response = await fetch('/dashboard/layout', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        cards: this.cards.map(c => ({
                            id: c.id,
                            order: c.order,
                            width: c.width,
                            active: c.active,
                        })),
                    }),
                });
                if (response.ok) {
                    this.showSettings = false;
                    this.editMode = false;
                    if (this._sortable) {
                        this._sortable.option('disabled', true);
                    }
                }
            } finally {
                this._saving = false;
            }
        },

        async resetLayout() {
            if (!confirm('Layout wirklich zurücksetzen? Alle Anpassungen gehen verloren.')) {
                return;
            }
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const response = await fetch('/dashboard/layout/reset', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.ok) {
                location.reload();
            }
        },

        get activeCards() {
            return this.cards
                .filter(c => c.active)
                .sort((a, b) => a.order - b.order);
        },

        get inactiveCards() {
            return this.cards.filter(c => !c.active);
        },

        widthClass(width) {
            // Responsive col-span: mobile 1col, tablet 2col, desktop 4col grid
            const map = {
                sm:   'col-span-1',
                md:   'col-span-1 md:col-span-2',
                lg:   'col-span-1 md:col-span-2 lg:col-span-3',
                full: 'col-span-full',
            };
            return map[width] ?? map.md;
        },
    }));
}

// Komponenten direkt registrieren – kein eigenes Alpine.start()!
if (window.Alpine) {
    registerDashboardComponents(window.Alpine);
} else {
    // Fallback: auf alpine:init warten (sidebar.js startet Alpine verzögert via window.load)
    document.addEventListener('alpine:init', () => registerDashboardComponents(window.Alpine));
}

