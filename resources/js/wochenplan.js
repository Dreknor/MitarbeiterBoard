/**
 * ============================================================
 * Wochenplan Alpine.js Komponenten
 * ============================================================
 *
 * Alpine wird bereits durch sidebar.js (im globalen Layout) geladen
 * und gestartet. Hier registrieren wir nur die Wochenplan-spezifischen
 * Komponenten über den alpine:init-Hook (oder direkt, falls Alpine
 * bereits initialisiert wurde).
 *
 * WICHTIG: Kein eigener Alpine-Import und kein Alpine.start() hier!
 * ============================================================
 */

function registerWochenplanComponents(Alpine) {
    if (!Alpine) {
        console.error('Wochenplan: Alpine nicht verfügbar!');
        return;
    }

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
                    previewFrame.removeAttribute('src');
                }
            } catch (err) {
                console.error('Preview update failed:', err);
            } finally {
                this.previewLoading = false;
            }
        }
    }));

    /**
     * Tagebuch-Aufgaben Panel – Modal für Übernahme in Wochenplan
     * Verwendung: <div x-data="diaryTasksPanel()">
     */
    Alpine.data('diaryTasksPanel', () => ({
        modal: {
            open: false,
            taskId: null,
            aufgabe: '',
            description: '',
        },
        selectedFachId: '',

        openModal(taskId, title, description) {
            this.modal.taskId      = taskId;
            this.modal.aufgabe     = title;
            this.modal.description = description || '';
            this.selectedFachId    = '';
            this.modal.open        = true;

            this.$nextTick(() => {
                // Fokus auf das Aufgabentext-Feld setzen
                this.$refs.modalForm?.querySelector('[name="aufgabe"]')?.focus();
            });
        },

        submitForm() {
            if (!this.selectedFachId) return;

            const form = this.$refs.modalForm;
            // Ziel-URL mit der gewählten Fach-ID
            form.action = `/wp/aufgabe/aus-tagebuch/${this.selectedFachId}`;
            form.removeAttribute('x-on:submit.prevent');
            form.submit();
        },
    }));
}

// Komponenten direkt registrieren.
// sidebar.js läuft dank defer-Reihenfolge (head vor body) als erstes und setzt
// window.Alpine. Alpine.start() wird erst beim window-load-Event aufgerufen,
// daher ist die Registrierung hier immer rechtzeitig.
if (window.Alpine) {
    registerWochenplanComponents(window.Alpine);
} else {
    // Fallback: auf alpine:init warten (sollte nicht vorkommen, aber sicher ist sicher)
    document.addEventListener('alpine:init', () => registerWochenplanComponents(window.Alpine));
}
