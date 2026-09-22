/**
 * PaedDiary v2 – Task-Panel Component
 *
 * Verantwortlich für:
 * - Aufgaben-Seitenpanel mit offenen Aufgaben + offenen Notizen
 * - Aufgaben-CRUD (Modal)
 * - Task-Badges auf Schülernamen
 */

import { csrfToken, escapeHtml, trimText } from './utils.js';

export function registerTaskPanel(Alpine) {
    Alpine.data('taskPanel', () => ({
        editingTaskId: null,
        taskFormTitle: '',
        taskFormDescription: '',
        taskFormDueDate: '',
        taskFormHighlighted: true,
        taskFormSchuelerIds: [],
        taskSaving: false,

        /** Alle offenen Items (Tasks + offene Einträge). */
        get allOpenItems() {
            const store = this.$store.diary;
            const openEntries = (store.entries || []).filter(e => !e.completed_at).map(e => ({
                id: 'entry-' + e.id,
                is_entry: true,
                entry_id: e.id,
                schueler_ids: e.schueler_ids || [],
                title: e.content,
                user: e.user,
            }));
            return [...(store.tasks || []), ...openEntries];
        },

        /** Offene Items vorhanden? */
        get hasOpenItems() {
            return this.allOpenItems.length > 0;
        },

        /** Items gruppiert nach Schüler für die Panel-Anzeige. */
        get itemsByStudent() {
            const store = this.$store.diary;
            const byStudent = {};

            this.allOpenItems.forEach(item => {
                const studentIds = item.is_entry ? (item.schueler_ids || []) : [item.schueler_id];
                studentIds.forEach(stuId => {
                    if (!byStudent[stuId]) byStudent[stuId] = [];
                    byStudent[stuId].push(item);
                });
            });

            // Sortiert nach Schülername
            return Object.entries(byStudent)
                .map(([stuId, items]) => {
                    const stu = (store.schueler || []).find(s => String(s.id) === String(stuId));
                    return { stuId, name: stu?.name || ('Schüler ' + stuId), items };
                })
                .sort((a, b) => a.name.localeCompare(b.name, 'de'));
        },

        /** Aufgaben für einen bestimmten Schüler (für Badges). */
        getTasksForStudent(stuId) {
            return this.allOpenItems.filter(t =>
                t.is_entry ? (t.schueler_ids || []).includes(stuId) : t.schueler_id === stuId
            );
        },

        openCreateModal() {
            this.editingTaskId = null;
            this.taskFormTitle = '';
            this.taskFormDescription = '';
            this.taskFormDueDate = '';
            this.taskFormHighlighted = true;
            this.taskFormSchuelerIds = [];
            this.$nextTick(() => $('#taskModal').modal('show'));
        },

        openEditModal(task) {
            this.editingTaskId = task.id;
            this.taskFormTitle = task.title || '';
            this.taskFormDescription = task.description || '';
            this.taskFormDueDate = task.due_date || '';
            this.taskFormHighlighted = !!task.highlighted;
            this.taskFormSchuelerIds = task.schueler_id ? [String(task.schueler_id)] : [];
            this.$nextTick(() => $('#taskModal').modal('show'));
        },

        async saveTask() {
            if (this.taskSaving) return;
            this.taskSaving = true;

            const store = this.$store.diary;
            const fd = new FormData();
            fd.append('title', this.taskFormTitle);
            fd.append('description', this.taskFormDescription);
            if (this.taskFormDueDate) fd.append('due_date', this.taskFormDueDate);
            fd.append('highlighted', this.taskFormHighlighted ? '1' : '0');
            this.taskFormSchuelerIds.forEach(id => fd.append('schueler_ids[]', id));
            fd.append('klasse_id', store.selectedKlasseId);
            if (store.selectedGroupId) fd.append('group_id', store.selectedGroupId);

            const url = this.editingTaskId
                ? `/paed-diary/task/${this.editingTaskId}`
                : '/paed-diary/task';
            const method = this.editingTaskId ? 'PUT' : 'POST';

            try {
                const resp = await fetch(url, {
                    method: method === 'PUT' ? 'PUT' : 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: fd
                });
                const j = await resp.json();
                if (j.success) {
                    $('#taskModal').modal('hide');
                    store.loadWeek();
                }
            } catch (_) {
                alert('Fehler beim Speichern der Aufgabe');
            } finally {
                this.taskSaving = false;
            }
        },

        async closeTask(taskId) {
            try {
                const resp = await fetch(`/paed-diary/task/${taskId}/close`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
                });
                const j = await resp.json();
                if (j.success) {
                    this.$store.diary.loadWeek();
                }
            } catch (_) {
                alert('Fehler beim Schließen der Aufgabe');
            }
        },

        /**
         * Notiz aus dem Panel abschließen.
         * `stuId` stammt aus der Gruppierung (itemsByStudent), sodass bei Einträgen
         * mit mehreren Schülern nur der jeweils angezeigte Schüler beendet wird.
         */
        async completeEntryFromPanel(entryId, stuId) {
            try {
                const resp = await fetch(`/paed-diary/entry/${entryId}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        completed_at: new Date().toISOString().slice(0, 10),
                        schueler_id: stuId,
                    })
                });
                const j = await resp.json();
                if (j.success) this.$store.diary.loadWeek();
            } catch (_) {
                alert('Fehler');
            }
        },

        trimText(str, len) { return trimText(str, len); },
    }));
}

