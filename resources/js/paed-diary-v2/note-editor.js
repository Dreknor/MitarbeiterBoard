/**
 * PaedDiary v2 – Notiz-Editor Component
 *
 * Verantwortlich für:
 * - Erstellen/Bearbeiten/Löschen von Einträgen
 * - Formular-State (Datum, Inhalt, Schüler, Kategorie)
 * - Editor öffnen/schließen
 */

import { csrfToken } from './utils.js';

export function registerNoteEditor(Alpine) {
    Alpine.data('noteEditor', () => ({
        editorOpen: false,
        editingEntryId: null,
        saving: false,
        statusText: '',

        // Formular-Felder
        formDate: new Date().toISOString().slice(0, 10),
        formContent: '',
        formSchuelerIds: [],
        formCategoryId: '',
        formNewCategory: '',
        formCompleted: false,
        formDossierOnly: false,

        /**
         * Prüft, ob aktuell ALLE verfügbaren Schüler ausgewählt sind
         * (über alle Klassen/Gruppen hinweg).
         */
        get isAllSelected() {
            const all = (this.$store.diary.schueler || []).map(s => String(s.id));
            return all.length > 0 && all.every(id => this.formSchuelerIds.includes(id));
        },

        /**
         * Prüft, ob alle Schüler einer bestimmten Klasse ausgewählt sind.
         */
        isKlasseSelected(klasseId) {
            const ids = (this.$store.diary.schueler || [])
                .filter(s => s.klasse_id === klasseId)
                .map(s => String(s.id));
            return ids.length > 0 && ids.every(id => this.formSchuelerIds.includes(id));
        },

        /**
         * Wählt alle verfügbaren Schüler aus/ab.
         */
        toggleAll(checked) {
            const all = (this.$store.diary.schueler || []).map(s => String(s.id));
            if (checked) {
                this.formSchuelerIds = Array.from(new Set([...this.formSchuelerIds, ...all]));
            } else {
                this.formSchuelerIds = this.formSchuelerIds.filter(id => !all.includes(id));
            }
        },

        /**
         * Wählt alle Schüler einer Klasse aus/ab (Gruppen-Modus).
         */
        toggleKlasse(klasseId, checked) {
            const ids = (this.$store.diary.schueler || [])
                .filter(s => s.klasse_id === klasseId)
                .map(s => String(s.id));
            if (checked) {
                this.formSchuelerIds = Array.from(new Set([...this.formSchuelerIds, ...ids]));
            } else {
                this.formSchuelerIds = this.formSchuelerIds.filter(id => !ids.includes(id));
            }
        },

        /**
         * Editor öffnen für neuen Eintrag (optional mit Datum + Schüler).
         */
        openForNew(detail) {
            this.resetForm();
            if (detail?.date) this.formDate = detail.date;
            if (detail?.stuId) this.formSchuelerIds = [String(detail.stuId)];
            this.editorOpen = true;
            this.$nextTick(() => {
                const el = this.$refs?.editorCard || this.$el;
                el?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
            });
        },

        /**
         * Editor öffnen für vorhandenen Eintrag.
         */
        openForEdit(detail) {
            const entry = (this.$store.diary.entries || []).find(
                e => String(e.id) === String(detail?.entryId)
            );
            if (!entry) return;

            this.resetForm();
            this.editingEntryId = entry.id;
            this.formDate = detail?.date || entry.date;
            this.formContent = entry.content || '';
            this.formSchuelerIds = (entry.schueler_ids || []).map(String);
            this.formCategoryId = entry.category_id ? String(entry.category_id) : '';
            this.formCompleted = !!entry.completed_at;
            this.formDossierOnly = !!entry.dossier_only;
            this.editorOpen = true;
            this.$nextTick(() => {
                const el = this.$refs?.editorCard || this.$el;
                el?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
            });
        },

        /**
         * Editor schließen.
         */
        closeEditor() {
            this.editorOpen = false;
            this.statusText = '';
        },

        /**
         * Formular zurücksetzen.
         */
        resetForm() {
            this.editingEntryId = null;
            this.formDate = new Date().toISOString().slice(0, 10);
            this.formContent = '';
            this.formSchuelerIds = [];
            this.formCategoryId = '';
            this.formNewCategory = '';
            this.formCompleted = false;
            this.formDossierOnly = false;
            this.saving = false;
            this.statusText = '';
        },

        /**
         * Eintrag speichern (Erstellen oder Aktualisieren).
         */
        async saveEntry() {
            if (this.saving) return;
            this.saving = true;
            this.statusText = 'Speichere...';

            const store = this.$store.diary;
            const fd = new FormData();
            fd.append('date', this.formDate);
            fd.append('content', this.formContent);
            this.formSchuelerIds.forEach(id => fd.append('schueler_ids[]', id));

            if (this.formNewCategory.trim()) {
                fd.append('new_category', this.formNewCategory.trim());
            } else if (this.formCategoryId) {
                fd.append('category_id', this.formCategoryId);
            }

            if (this.formCompleted) fd.append('completed', '1');
            if (this.formDossierOnly) fd.append('dossier_only', '1');

            if (store.selectedGroupId) fd.append('group_id', store.selectedGroupId);
            if (store.selectedKlasseId) fd.append('klasse_id', store.selectedKlasseId);

            const url = this.editingEntryId
                ? `/paed-diary/entry/${this.editingEntryId}`
                : '/paed-diary/entry';

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: fd
                });
                const j = await resp.json();
                if (j.success) {
                    this.statusText = 'Gespeichert';
                    this.closeEditor();
                    store.loadWeek();
                } else {
                    this.statusText = j.message || 'Fehler';
                }
            } catch (_) {
                this.statusText = 'Fehler beim Speichern';
            } finally {
                this.saving = false;
            }
        },

        /**
         * Eintrag löschen.
         */
        async deleteEntry() {
            if (!this.editingEntryId) return;
            if (!confirm('Eintrag wirklich löschen?')) return;

            this.statusText = 'Lösche...';
            const store = this.$store.diary;
            const fd = new FormData();
            if (store.selectedKlasseId) fd.append('klasse_id', store.selectedKlasseId);

            try {
                const resp = await fetch(`/paed-diary/entry/${this.editingEntryId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: fd
                });
                const j = await resp.json();
                if (j.success) {
                    this.statusText = 'Gelöscht';
                    this.closeEditor();
                    store.loadWeek();
                } else {
                    this.statusText = j.message || 'Löschen fehlgeschlagen';
                }
            } catch (_) {
                this.statusText = 'Fehler beim Löschen';
            }
        },
    }));
}

