/**
 * PaedDiary v2 – Appointment-Manager Component
 *
 * Verantwortlich für:
 * - Termin-CRUD (Modal)
 * - Wiederkehrende Termine
 * - Lösch-Dialog mit Optionen (Vorkommen-Scope + Schüler-Scope)
 */

import { csrfToken, formatTime, trimText } from './utils.js';

export function registerAppointmentManager(Alpine) {
    Alpine.data('appointmentManager', () => ({
        formId: null,
        formTitle: '',
        formDescription: '',
        formStartDate: '',
        formStartTime: '',
        formEndTime: '',
        formIsRecurring: false,
        formRecurringType: 'weekly',
        formRecurringInterval: 1,
        formRecurringEndDate: '',
        formKlasseIds: [],
        formGroupIds: [],
        formSchuelerIds: [],
        formUserId: null,
        appointmentSaving: false,
        appointmentFeedback: '',

        // Lösch-Dialog
        showingDeleteOptions: false,
        deleteMode: 'all',          // 'only_this' | 'this_and_future' | 'all'
        deleteSchuelerScope: 'all', // 'all' | 'specific'
        deleteSchuelerIds: [],      // bei scope = 'specific': ausgewählte Schüler-IDs

        openCreateModal() {
            this.resetForm();
            this.$nextTick(() => $('#appointmentModal').modal('show'));
        },

        openEditModal(apt) {
            this.resetForm();
            this.formId = apt.id;
            this.formTitle = apt.title || '';
            this.formDescription = apt.description || '';
            this.formStartDate = apt.date || '';
            this.formStartTime = formatTime(apt.start_time) || '';
            this.formEndTime = formatTime(apt.end_time) || '';
            this.formIsRecurring = !!apt.is_recurring;
            this.formRecurringType = apt.recurring_type || 'weekly';
            this.formRecurringInterval = apt.recurring_interval || 1;
            this.formRecurringEndDate = apt.recurring_end_date || '';
            this.formUserId = apt.user_id || null;

            if (Array.isArray(apt.klassen)) {
                this.formKlasseIds = apt.klassen.map(k => String(k.id));
            }
            if (Array.isArray(apt.groups)) {
                this.formGroupIds = apt.groups.map(g => String(g.id));
            }
            if (Array.isArray(apt.schueler)) {
                this.formSchuelerIds = apt.schueler.map(s => String(s.id));
            }

            this.$nextTick(() => $('#appointmentModal').modal('show'));
        },

        resetForm() {
            this.formId = null;
            this.formTitle = '';
            this.formDescription = '';
            this.formStartDate = '';
            this.formStartTime = '';
            this.formEndTime = '';
            this.formIsRecurring = false;
            this.formRecurringType = 'weekly';
            this.formRecurringInterval = 1;
            this.formRecurringEndDate = '';
            this.formKlasseIds = [];
            this.formGroupIds = [];
            this.formSchuelerIds = [];
            this.formUserId = null;
            this.appointmentSaving = false;
            this.appointmentFeedback = '';
            this.showingDeleteOptions = false;
            this.deleteMode = 'all';
            this.deleteSchuelerScope = 'all';
            this.deleteSchuelerIds = [];
        },

        showDeleteDialog() {
            if (!this.formId) return;
            this.deleteMode = 'all';
            this.deleteSchuelerScope = 'all';
            this.deleteSchuelerIds = [...this.formSchuelerIds];
            this.showingDeleteOptions = true;
        },

        cancelDelete() {
            this.showingDeleteOptions = false;
        },

        async confirmDelete() {
            if (!this.formId) return;

            const body = new URLSearchParams();
            body.append('_method', 'DELETE');
            body.append('delete_mode', this.deleteMode);
            if (this.formStartDate) body.append('occurrence_date', this.formStartDate);

            if (this.deleteSchuelerScope === 'specific' && this.deleteSchuelerIds.length > 0) {
                this.deleteSchuelerIds.forEach(id => body.append('schueler_ids[]', id));
            }

            try {
                const resp = await fetch(`/paed-diary/appointments/${this.formId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: body.toString(),
                });
                if (resp.status === 403) {
                    this.appointmentFeedback = 'Keine Berechtigung zum Löschen dieses Termins.';
                    this.showingDeleteOptions = false;
                    return;
                }
                const j = await resp.json();
                if (j.success) {
                    $('#appointmentModal').modal('hide');
                    this.$store.diary.loadAppointments();
                } else {
                    this.appointmentFeedback = j.message || 'Fehler beim Löschen';
                    this.showingDeleteOptions = false;
                }
            } catch (_) {
                this.appointmentFeedback = 'Netzwerkfehler beim Löschen';
                this.showingDeleteOptions = false;
            }
        },

        async saveAppointment() {
            if (this.appointmentSaving) return;
            this.appointmentSaving = true;

            const fd = new FormData();
            fd.append('title', this.formTitle);
            fd.append('description', this.formDescription);
            fd.append('start_date', this.formStartDate);
            if (this.formStartTime) fd.append('start_time', this.formStartTime);
            if (this.formEndTime) fd.append('end_time', this.formEndTime);
            fd.append('is_recurring', this.formIsRecurring ? '1' : '0');
            if (this.formIsRecurring) {
                fd.append('recurring_type', this.formRecurringType);
                fd.append('recurring_interval', this.formRecurringInterval);
                if (this.formRecurringEndDate) fd.append('recurring_end_date', this.formRecurringEndDate);
            }
            this.formKlasseIds.forEach(id => fd.append('klasse_ids[]', id));
            this.formGroupIds.forEach(id => fd.append('group_ids[]', id));
            this.formSchuelerIds.forEach(id => fd.append('schueler_ids[]', id));

            const url = this.formId
                ? `/paed-diary/appointments/${this.formId}`
                : '/paed-diary/appointments';
            const method = this.formId ? 'PUT' : 'POST';

            if (method === 'PUT') fd.append('_method', 'PUT');

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: fd
                });
                const j = await resp.json();
                if (j.success) {
                    $('#appointmentModal').modal('hide');
                    this.$store.diary.loadAppointments();
                } else {
                    this.appointmentFeedback = j.message || 'Fehler beim Speichern';
                }
            } catch (_) {
                this.appointmentFeedback = 'Netzwerkfehler';
            } finally {
                this.appointmentSaving = false;
            }
        },


        async togglePause() {
            if (!this.formId) return;
            try {
                const resp = await fetch(`/paed-diary/appointments/${this.formId}/toggle-pause`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
                });
                const j = await resp.json();
                if (j.success) {
                    $('#appointmentModal').modal('hide');
                    this.$store.diary.loadAppointments();
                }
            } catch (_) {
                this.appointmentFeedback = 'Fehler';
            }
        },
    }));
}

