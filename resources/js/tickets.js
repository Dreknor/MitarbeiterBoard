/*
 * Ticketsystem – Vite-Entrypoint
 *
 * - Alpine-Komponente "ticketFiles": Dateiauswahl mit Drag & Drop, Vorschau-
 *   liste, Entfernen einzelner Dateien und Größenprüfung. Mehrfaches Auswählen
 *   ergänzt die Liste (praktisch am Smartphone: Foto für Foto hinzufügen).
 * - TinyMCE für Textfelder mit .ticket-editor (Skripte kommen aus public/js).
 * - Formularschutz: leere Editor-Inhalte abfangen, Doppel-Absenden verhindern.
 *
 * Alpine wird von sidebar.js global bereitgestellt und erst bei window.load
 * gestartet – Komponenten können daher hier direkt registriert werden.
 */

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
}

function fileIcon(name) {
    const ext = (name.split('.').pop() || '').toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'bmp', 'svg'].includes(ext)) return 'fa-file-image';
    if (ext === 'pdf') return 'fa-file-pdf';
    if (['doc', 'docx', 'odt'].includes(ext)) return 'fa-file-word';
    if (['xls', 'xlsx', 'ods', 'csv'].includes(ext)) return 'fa-file-excel';
    if (['zip', 'rar', '7z'].includes(ext)) return 'fa-file-archive';
    return 'fa-file';
}

function registerComponents(Alpine) {
    Alpine.data('ticketFiles', (maxFiles = 10, maxMb = 20) => ({
        files: [],
        dragover: false,
        error: '',
        transfer: null,

        init() {
            this.transfer = typeof DataTransfer !== 'undefined' ? new DataTransfer() : null;
        },

        add(list) {
            // Ohne DataTransfer (sehr alte Browser) bleibt die native Auswahl bestehen
            if (!this.transfer) {
                this.render(this.$refs.input.files);
                return;
            }

            const known = new Set(Array.from(this.transfer.files).map((f) => f.name + f.size));
            for (const file of Array.from(list)) {
                if (this.transfer.files.length >= maxFiles) {
                    this.error = 'Maximal ' + maxFiles + ' Dateien möglich.';
                    break;
                }
                if (!known.has(file.name + file.size)) {
                    this.transfer.items.add(file);
                }
            }
            this.$refs.input.files = this.transfer.files;
            this.render(this.transfer.files);
        },

        remove(index) {
            if (!this.transfer) return;
            const next = new DataTransfer();
            Array.from(this.transfer.files).forEach((file, i) => {
                if (i !== index) next.items.add(file);
            });
            this.transfer = next;
            this.$refs.input.files = next.files;
            this.render(next.files);
        },

        render(list) {
            const limit = maxMb * 1024 * 1024;
            this.files = Array.from(list).map((file) => ({
                name: file.name,
                size: formatSize(file.size),
                icon: fileIcon(file.name),
                tooBig: file.size > limit,
            }));
            const tooBig = this.files.filter((f) => f.tooBig).length;
            this.error = tooBig ? tooBig + ' Datei(en) größer als ' + maxMb + ' MB – bitte entfernen.' : '';
        },

        onChange(event) {
            this.error = '';
            this.add(event.target.files);
        },

        onDrop(event) {
            this.dragover = false;
            this.error = '';
            if (event.dataTransfer?.files?.length) {
                this.add(event.dataTransfer.files);
            }
        },
    }));
}

if (window.Alpine) {
    registerComponents(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerComponents(window.Alpine));
}

function initEditors() {
    if (!window.tinymce || !document.querySelector('.ticket-wrapper textarea.ticket-editor')) {
        return;
    }

    const small = window.matchMedia('(max-width: 640px)').matches;

    const mobileToolbar = 'bold italic | bullist numlist | link | removeformat';

    window.tinymce.init({
        selector: '.ticket-wrapper textarea.ticket-editor',
        language: 'de',
        height: small ? 220 : 280,
        width: '100%',
        menubar: false,
        branding: false,
        statusbar: false,
        toolbar_mode: 'sliding',
        plugins: ['advlist autolink lists link charmap', 'paste table code'],
        toolbar: small
            ? mobileToolbar
            : 'undo redo | bold italic underline forecolor | bullist numlist outdent indent | link table | removeformat',
        // TinyMCE 5.0 nutzt auf Touch-Geräten sonst das veraltete "mobile"-Theme
        // (graue Fläche, Vollbild-Editor). Das normale Theme funktioniert dort gut.
        mobile: {
            theme: 'silver',
            menubar: false,
            plugins: ['autolink lists link paste'],
            toolbar: mobileToolbar,
        },
        paste_data_images: false,
        table_default_attributes: { border: '1' },
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.5; }',
        setup(editor) {
            // Fehlerhinweis ausblenden, sobald geschrieben wird
            editor.on('input change', () => {
                const form = editor.getElement().closest('form');
                form?.querySelector('[data-editor-error]')?.classList.add('hidden');
            });
        },
    });
}

/*
 * Per Event-Delegation, damit auch per x-teleport verschobene Formulare
 * (z. B. der Schließen-Dialog) erfasst werden.
 */
function initFormGuards() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('.ticket-wrapper form[data-ticket-form]')) {
            return;
        }

        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        window.tinymce?.triggerSave();

        const field = form.querySelector('textarea.ticket-editor');
        if (field && field.value.replace(/<[^>]*>|&nbsp;|\s/g, '') === '') {
            event.preventDefault();
            form.querySelector('[data-editor-error]')?.classList.remove('hidden');
            return;
        }

        form.dataset.submitting = '1';
        // Erst nach dem Absenden deaktivieren – sonst fehlt der Wert des
        // auslösenden Buttons (name/value) in den Formulardaten.
        setTimeout(() => {
            form.querySelectorAll('button[type=submit]').forEach((button) => {
                button.disabled = true;
                const label = button.querySelector('[data-loading-label]');
                if (label) label.textContent = label.dataset.loadingLabel;
            });
        }, 0);
    });
}

// Zurück-Navigation (bfcache): gesperrte Formulare wieder freigeben
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('.ticket-wrapper form[data-ticket-form]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = false; });
    });
});

if (document.readyState !== 'loading') {
    initEditors();
    initFormGuards();
} else {
    document.addEventListener('DOMContentLoaded', () => {
        initEditors();
        initFormGuards();
    });
}
