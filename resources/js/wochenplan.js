import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

// Register Alpine plugins
Alpine.plugin(collapse);

// Make Alpine available globally
window.Alpine = Alpine;

/**
 * ============================================================
 * Wochenplan Alpine.js Komponenten
 * ============================================================
 */

/**
 * Inline-Aufgabenbearbeitung
 * Verwendung: <div x-data="aufgabeForm()">
 */
Alpine.data('aufgabeForm', () => ({
    editing: false,
    adding: false,
    aufgabe: '',
    dauer: '',

    startEdit(text, dauer) {
        this.aufgabe = text;
        this.dauer = dauer || '';
        this.editing = true;
        this.$nextTick(() => this.$refs.input?.focus());
    },

    startAdd() {
        this.aufgabe = '';
        this.dauer = '';
        this.adding = true;
        this.$nextTick(() => this.$refs.newInput?.focus());
    },

    cancel() {
        this.editing = false;
        this.adding = false;
    }
}));

/**
 * Fach-Auswahl-Dropdown
 * Verwendung: <div x-data="fachSelector()" data-faecher='[...]'>
 */
Alpine.data('fachSelector', () => ({
    open: false,
    search: '',
    faecher: [],

    init() {
        this.faecher = JSON.parse(this.$el.dataset.faecher || '[]');
    },

    get filtered() {
        if (!this.search) return this.faecher;
        return this.faecher.filter(f =>
            f.name.toLowerCase().includes(this.search.toLowerCase())
        );
    },

    toggle() {
        this.open = !this.open;
        if (this.open) {
            this.$nextTick(() => this.$refs.searchInput?.focus());
        }
    },

    close() {
        this.open = false;
        this.search = '';
    }
}));

/**
 * Drag & Drop Sortierung für Aufgaben
 * Verwendung: <div x-data="sortable()" data-reorder-url="...">
 */
Alpine.data('sortable', () => ({
    dragging: null,

    dragStart(e, id) {
        this.dragging = id;
        e.dataTransfer.effectAllowed = 'move';
        e.target.classList.add('opacity-50');
    },

    dragEnd(e) {
        e.target.classList.remove('opacity-50');
        this.dragging = null;
    },

    dragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    },

    dragEnter(e) {
        e.preventDefault();
        if (e.currentTarget !== e.target) return;
        e.currentTarget.classList.add('border-blue-400', 'bg-blue-50');
    },

    dragLeave(e) {
        e.currentTarget.classList.remove('border-blue-400', 'bg-blue-50');
    },

    async drop(e, targetId) {
        e.preventDefault();
        e.currentTarget.classList.remove('border-blue-400', 'bg-blue-50');

        if (this.dragging === targetId) return;

        const reorderUrl = this.$el.dataset.reorderUrl;
        if (!reorderUrl) return;

        // Neue Reihenfolge aus DOM ermitteln
        const items = [...this.$el.querySelectorAll('[data-id]')];
        const order = items.map((el, idx) => ({
            id: parseInt(el.dataset.id),
            sort_order: idx
        }));

        try {
            const response = await fetch(reorderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ order }),
            });

            if (response.ok) {
                window.location.reload();
            }
        } catch (err) {
            console.error('Reorder failed:', err);
        }
    }
}));

/**
 * Einfacher Tab-Switcher
 * Verwendung: <div x-data="tabs('aktuelle')">
 */
Alpine.data('tabs', (defaultTab) => ({
    active: defaultTab,

    isActive(tab) {
        return this.active === tab;
    },

    setActive(tab) {
        this.active = tab;
    }
}));

/**
 * Bestätigungs-Dialog (generisch)
 * Verwendung: <div x-data="confirmAction()">
 */
Alpine.data('confirmAction', () => ({
    open: false,

    show() {
        this.open = true;
    },

    hide() {
        this.open = false;
    }
}));

/**
 * Formatvorlagen-Editor mit Live-Vorschau
 * Verwendung: <div x-data="formatvorlageEditor()">
 *   <form id="wp-formatvorlage-form" data-preview-url="...">...</form>
 *   <iframe id="wp-formatvorlage-preview">...</iframe>
 * </div>
 */
Alpine.data('formatvorlageEditor', () => ({
    previewLoading: false,

    init() {
        // Initiale Vorschau laden sobald das Create-Formular bereit ist
        // (beim Edit ist bereits eine src gesetzt, daher nur ohne src laden)
        this.$nextTick(() => {
            const frame = document.getElementById('wp-formatvorlage-preview');
            if (frame && !frame.getAttribute('src')) {
                this.updatePreview();
            }
        });
    },

    getForm() {
        return document.getElementById('wp-formatvorlage-form')
            || (this.$el.tagName === 'FORM' ? this.$el : this.$el.querySelector('form'));
    },

    async updatePreview() {
        const form = this.getForm();
        if (!form) return;

        const previewUrl = form.dataset.previewUrl;
        if (!previewUrl) return;

        const previewFrame = document.getElementById('wp-formatvorlage-preview');
        if (!previewFrame) return;

        this.previewLoading = true;

        const formData = new FormData(form);
        // FormData in plain object umwandeln (Checkboxen werden nur gesendet wenn gecheckt)
        const config = {};
        for (const [key, value] of formData.entries()) {
            config[key] = value;
        }

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(config),
            });

            if (response.ok) {
                previewFrame.srcdoc = await response.text();
                // src entfernen damit srcdoc aktiv ist
                previewFrame.removeAttribute('src');
            }
        } catch (err) {
            console.error('Preview update failed:', err);
        } finally {
            this.previewLoading = false;
        }
    }
}));

// Alpine starten
Alpine.start();

