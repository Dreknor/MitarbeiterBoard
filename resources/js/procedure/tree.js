/**
 * Prozesse-Modul – Schritt-Baum Alpine.js Komponente (für start.blade.php)
 * Verwaltet: Baum-Expand/Collapse, Detail-Panel, Kommentare, Verlauf, AJAX-Aktionen,
 *            Drag-&-Drop per SortableJS (Phase 3).
 */

import Sortable from 'sortablejs';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('procedureTree', (config) => ({
        // Konfiguration
        canEdit: config?.canEdit ?? false,
        csrfToken: config?.csrfToken ?? '',

        // Baum-Zustand
        expanded: {},

        // Header-Bearbeitung
        editingHeader: false,

        // Detail-Panel
        selectedStep: null,
        panelTab: 'info',

        // Kommentare
        comments: [],
        commentsLoading: false,
        newComment: '',
        submittingComment: false,

        // Verlauf
        historyItems: [],
        historyLoading: false,

        // Schritt hinzufügen
        addingStep: false,
        addingStepParent: null,

        // Step-Aktionen
        completingStep: false,
        reopeningStep: false,

        // Toasts
        toasts: [],
        _toastCtr: 0,

        init() {
            // Alle Root-Ebenen aufklappen
            document.querySelectorAll('[data-step-root]').forEach(el => {
                const id = parseInt(el.dataset.stepId);
                if (id) this.expanded[id] = true;
            });

            // Drag-&-Drop initialisieren wenn Bearbeitungsmodus
            if (this.canEdit) {
                this.$nextTick(() => this._initSortable());
            }
        },

        /** SortableJS initialisieren – für alle sortable Container im DOM */
        _initSortable() {
            document.querySelectorAll('[data-sortable-container]').forEach(container => {
                const procedureId = parseInt(container.dataset.procedureId);
                const parentId    = container.dataset.parentId
                    ? parseInt(container.dataset.parentId)
                    : null;

                Sortable.create(container, {
                    group:          'procedure-steps',
                    animation:      150,
                    handle:         '[data-drag-handle]',
                    ghostClass:     'sortable-ghost',
                    chosenClass:    'sortable-chosen',
                    dragoverBubble: false,

                    onEnd: (evt) => {
                        const orderedIds = Array.from(evt.to.querySelectorAll(':scope > [data-step-id]'))
                            .map(el => parseInt(el.dataset.stepId))
                            .filter(Boolean);

                        const newParentId = evt.to.dataset.parentId
                            ? parseInt(evt.to.dataset.parentId)
                            : null;

                        this._saveReorder(procedureId, newParentId, orderedIds);
                    },
                });
            });
        },

        /** Bulk-Reorder an Backend senden */
        async _saveReorder(procedureId, parentId, orderedIds) {
            try {
                const resp = await fetch('/procedure/steps/reorder', {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ procedure_id: procedureId, parent_id: parentId, ordered_ids: orderedIds }),
                });
                if (resp.ok) {
                    this.addToast('Reihenfolge gespeichert.', 'success', 2000);
                } else {
                    const err = await resp.json().catch(() => ({}));
                    this.addToast(err.message ?? 'Fehler beim Speichern der Reihenfolge', 'error');
                }
            } catch (e) {
                console.error('Reorder-Fehler', e);
                this.addToast('Netzwerkfehler beim Speichern', 'error');
            }
        },

        /** Expand/Collapse eines Knotens */
        toggle(id) {
            this.expanded[id] = !this.expanded[id];
        },

        isExpanded(id) {
            return !!this.expanded[id];
        },

        expandAll() {
            document.querySelectorAll('[data-step-id]').forEach(el => {
                const id = parseInt(el.dataset.stepId);
                if (id) this.expanded[id] = true;
            });
        },

        collapseAll() {
            document.querySelectorAll('[data-step-id]').forEach(el => {
                const id = parseInt(el.dataset.stepId);
                if (id) this.expanded[id] = false;
            });
        },

        /** Schritt im Detail-Panel öffnen */
        selectStep(step) {
            this.selectedStep = step;
            this.panelTab = 'info';
            this.comments = [];
            this.historyItems = [];
        },

        closePanel() {
            this.selectedStep = null;
        },

        /** ── AJAX: Schritt erledigen (B-15) ─────────────────── */
        async completeStep() {
            if (!this.selectedStep || this.completingStep) return;
            this.completingStep = true;
            try {
                const resp = await fetch(`/procedure/steps/${this.selectedStep.id}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: '{}',
                });
                const data = await resp.json().catch(() => ({}));
                if (resp.ok) {
                    this.selectedStep = {
                        ...this.selectedStep,
                        done:        true,
                        completedAt: data.completed_at ?? '',
                    };
                    this.addToast('Schritt als erledigt markiert.', 'success');
                    if (data.procedure_completed) {
                        this.addToast('🎉 Alle Schritte erledigt – Prozess abgeschlossen!', 'info', 5000);
                    }
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    this.addToast(data.message ?? 'Fehler beim Erledigen', 'error');
                }
            } catch (e) {
                this.addToast('Netzwerkfehler', 'error');
            } finally {
                this.completingStep = false;
            }
        },

        /** ── AJAX: Schritt wieder öffnen (B-16) ───────────────── */
        async reopenStep() {
            if (!this.selectedStep || this.reopeningStep) return;
            this.reopeningStep = true;
            try {
                const resp = await fetch(`/procedure/steps/${this.selectedStep.id}/reopen`, {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: '{}',
                });
                const data = await resp.json().catch(() => ({}));
                if (resp.ok) {
                    this.selectedStep = { ...this.selectedStep, done: false, completedAt: null };
                    this.addToast('Schritt wieder geöffnet.', 'warning');
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    this.addToast(data.message ?? 'Fehler', 'error');
                }
            } catch (e) {
                this.addToast('Netzwerkfehler', 'error');
            } finally {
                this.reopeningStep = false;
            }
        },

        /** Kommentare nachladen */
        async loadComments(stepId) {
            if (!stepId) return;
            this.commentsLoading = true;
            this.comments = [];
            try {
                const resp = await fetch(`/procedure/steps/${stepId}/comments`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (resp.ok) {
                    const data = await resp.json();
                    this.comments = (data.data ?? data).map(c => ({
                        ...c,
                        user: c.user ?? c.author ?? null,
                        created_at_formatted: c.created_at
                            ? new Date(c.created_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                            : '',
                        canDelete: c.canDelete ?? c.can_delete ?? c.is_mine ?? false,
                    }));
                }
            } catch (e) {
                console.error('Fehler beim Laden der Kommentare', e);
            } finally {
                this.commentsLoading = false;
            }
        },

        /** Verlauf nachladen */
        async loadHistory(stepId) {
            if (!stepId) return;
            this.historyLoading = true;
            this.historyItems = [];
            try {
                const resp = await fetch(`/procedure/api/steps/${stepId}/history`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (resp.ok) {
                    const data = await resp.json();
                    this.historyItems = data.data ?? [];
                }
            } catch (e) {
                console.error('Fehler beim Laden des Verlaufs', e);
            } finally {
                this.historyLoading = false;
            }
        },

        /** Kommentar speichern */
        async addComment(stepId) {
            if (!stepId || !this.newComment.trim() || this.submittingComment) return;
            this.submittingComment = true;
            try {
                const resp = await fetch(`/procedure/steps/${stepId}/comments`, {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ body: this.newComment }),
                });
                if (resp.ok) {
                    const data = await resp.json();
                    const notified = data.data?.notified ?? 0;
                    this.newComment = '';
                    await this.loadComments(stepId);
                    this.addToast(
                        notified > 0
                            ? `Kommentar gespeichert · ${notified} Verantwortliche per Mail informiert`
                            : 'Kommentar gespeichert',
                        'success'
                    );
                } else {
                    const err = await resp.json().catch(() => ({}));
                    this.addToast(err.message ?? 'Fehler beim Speichern', 'error');
                }
            } catch (e) {
                this.addToast('Netzwerkfehler beim Speichern', 'error');
            } finally {
                this.submittingComment = false;
            }
        },

        /** Kommentar löschen */
        async deleteComment(stepId, commentId) {
            if (!confirm('Kommentar wirklich löschen?')) return;
            try {
                const resp = await fetch(`/procedure/steps/${stepId}/comments/${commentId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (resp.ok) {
                    await this.loadComments(stepId);
                    this.addToast('Kommentar gelöscht', 'warning');
                } else {
                    this.addToast('Fehler beim Löschen', 'error');
                }
            } catch (e) {
                this.addToast('Netzwerkfehler', 'error');
            }
        },

        /** Schritt-Formular öffnen */
        openAddStep(parentId) {
            this.addingStepParent = parentId ?? null;
            this.addingStep = true;
        },

        /** Status-Hilfsmethoden */
        isOverdueStep(step) {
            if (!step || step.done || !step.endDate) return false;
            return new Date(step.endDate) < new Date(new Date().toDateString());
        },

        isDueSoonStep(step) {
            if (!step || step.done || !step.endDate) return false;
            if (this.isOverdueStep(step)) return false;
            const diff = (new Date(step.endDate) - new Date(new Date().toDateString())) / (1000 * 60 * 60 * 24);
            return diff <= 3;
        },

        /** Datum formatieren */
        formatDate(dateStr) {
            if (!dateStr) return '';
            try {
                return new Date(dateStr).toLocaleDateString('de-DE', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit',
                });
            } catch {
                return dateStr;
            }
        },

        /** Toasts */
        addToast(message, type = 'success', duration = 3500) {
            const id = ++this._toastCtr;
            this.toasts.push({ id, message, type });
            setTimeout(() => this.removeToast(id), duration);
        },

        removeToast(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
    }));
});


