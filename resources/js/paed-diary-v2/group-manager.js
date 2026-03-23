/**
 * PaedDiary v2 – Gruppen-Manager Component
 *
 * Verantwortlich für:
 * - Gruppen-CRUD (Modal)
 * - Gruppen-Dropdown aktualisieren
 */

import { csrfToken, escapeHtml } from './utils.js';

export function registerGroupManager(Alpine) {
    Alpine.data('groupManager', () => ({
        formId: null,
        formName: '',
        formKlasseIds: [],
        groupStatusText: '',
        groupFeedback: '',
        groupFeedbackType: 'info',

        openModal() {
            this.resetForm();
            this.$store.diary.loadGroups();
            this.$nextTick(() => $('#groupModal').modal('show'));
        },

        editGroup(group) {
            this.formId = group.id;
            this.formName = group.name;
            this.formKlasseIds = (group.klassen || []).map(k => String(k.id));
        },

        cancelEdit() {
            this.resetForm();
        },

        resetForm() {
            this.formId = null;
            this.formName = '';
            this.formKlasseIds = [];
            this.groupStatusText = '';
            this.groupFeedback = '';
        },

        async saveGroup() {
            if (this.formKlasseIds.length < 2) {
                this.setFeedback('Mindestens 2 Klassen wählen', 'warning');
                return;
            }
            this.groupStatusText = 'Speichere...';

            const fd = new FormData();
            fd.append('name', this.formName);
            this.formKlasseIds.forEach(id => fd.append('klasse_ids[]', id));

            const url = this.formId
                ? `/paed-diary/class-groups/${this.formId}`
                : '/paed-diary/class-groups';

            // PUT via _method Spoofing
            if (this.formId) fd.append('_method', 'PUT');

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: fd
                });
                const j = await resp.json();
                if (j.success) {
                    this.groupStatusText = 'Gespeichert';
                    this.$store.diary.loadGroups();
                    this.resetForm();
                    // Wenn neue Gruppe erstellt: direkt auswählen
                    if (!this.formId && j.group) {
                        this.$store.diary.changeGroup(j.group.id);
                    }
                } else {
                    this.groupStatusText = j.message || 'Fehler';
                }
            } catch (_) {
                this.groupStatusText = 'Fehler';
            }
        },

        async deleteGroup(groupId) {
            if (!confirm('Gruppe wirklich löschen?')) return;

            try {
                const resp = await fetch(`/paed-diary/class-groups/${groupId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
                });
                const j = await resp.json();
                if (j.success) {
                    this.setFeedback('Gelöscht', 'success');
                    this.$store.diary.loadGroups();
                    // Falls die gelöschte Gruppe aktiv war: zurücksetzen
                    if (String(this.$store.diary.selectedGroupId) === String(groupId)) {
                        this.$store.diary.changeGroup(null);
                    }
                }
            } catch (_) {
                this.setFeedback('Fehler beim Löschen', 'danger');
            }
        },

        setFeedback(msg, type = 'info') {
            this.groupFeedback = msg;
            this.groupFeedbackType = type;
            setTimeout(() => { this.groupFeedback = ''; }, 4000);
        },

        isKlasseSelected(klasseId) {
            return this.formKlasseIds.includes(String(klasseId));
        },

        toggleKlasse(klasseId) {
            const id = String(klasseId);
            if (this.formKlasseIds.includes(id)) {
                this.formKlasseIds = this.formKlasseIds.filter(x => x !== id);
            } else {
                this.formKlasseIds = [...this.formKlasseIds, id];
            }
        },
    }));
}

