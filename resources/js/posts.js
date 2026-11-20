/*
 * Nachrichten – Vite-Entrypoint
 *
 * - Alpine-Komponente "nachrFiles": Dateiauswahl mit Drag & Drop und Vorschauliste.
 * - TinyMCE für Textfelder mit .nachr-editor (Skripte kommen aus public/js).
 * - Formularschutz: Doppel-Absenden verhindern.
 *
 * Alpine wird von sidebar.js global bereitgestellt und erst bei window.load gestartet.
 */

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
}

function registerComponents(Alpine) {
    Alpine.data('nachrFiles', (maxMb = 15) => ({
        files: [],
        dragover: false,
        error: '',
        transfer: null,

        init() {
            this.transfer = typeof DataTransfer !== 'undefined' ? new DataTransfer() : null;
        },

        add(list) {
            if (!this.transfer) {
                this.render(this.$refs.input.files);
                return;
            }
            const known = new Set(Array.from(this.transfer.files).map((f) => f.name + f.size));
            for (const file of Array.from(list)) {
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
    if (!window.tinymce || !document.querySelector('.nachr-wrapper textarea.nachr-editor')) {
        return;
    }

    const small = window.matchMedia('(max-width: 640px)').matches;
    const mobileToolbar = 'bold italic | bullist numlist | link | removeformat';

    window.tinymce.init({
        selector: '.nachr-wrapper textarea.nachr-editor',
        language: 'de',
        height: small ? 280 : 420,
        width: '100%',
        menubar: false,
        branding: false,
        toolbar_mode: 'sliding',
        plugins: ['advlist autolink lists link charmap', 'paste table code wordcount'],
        toolbar: small
            ? mobileToolbar
            : 'undo redo | formatselect | bold italic underline forecolor backcolor | alignleft aligncenter alignright | bullist numlist outdent indent | link table | removeformat',
        mobile: {
            theme: 'silver',
            menubar: false,
            plugins: ['autolink lists link paste'],
            toolbar: mobileToolbar,
        },
        paste_data_images: false,
        table_default_attributes: { border: '1' },
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.55; }',
    });
}

function initFormGuards() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('.nachr-wrapper form[data-nachr-form]')) {
            return;
        }
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        window.tinymce?.triggerSave();
        form.dataset.submitting = '1';
        setTimeout(() => {
            form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = true; });
        }, 0);
    });
}

window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('.nachr-wrapper form[data-nachr-form]').forEach((form) => {
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
