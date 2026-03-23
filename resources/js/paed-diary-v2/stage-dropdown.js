/**
 * PaedDiary v2 – Stage-Dropdown Component
 *
 * Zeigt ein Dropdown mit verfügbaren Graduierungsstufen
 * und erlaubt den Wechsel per AJAX.
 */

import { csrfToken, escapeHtml } from './utils.js';

export function registerStageDropdown(Alpine) {
    Alpine.data('stageDropdown', () => ({
        dropdownOpen: false,
        dropdownStuId: null,
        dropdownKlasseId: null,
        stages: [],
        stageLoading: false,
        stageSaving: false,

        /**
         * Gibt HTML für das Stufen-Symbol eines Schülers zurück.
         */
        stageHtml(stu) {
            const store = this.$store.diary;
            if (!stu.stage) {
                return '<span class="badge badge-light" title="Stufe setzen">Stufe</span>';
            }
            if (stu.stage.image_url) {
                return `<img src="${escapeHtml(stu.stage.image_url)}" alt="${escapeHtml(stu.stage.name)}" title="${escapeHtml(stu.stage.name)}" class="stage-image" style="width:42px;height:42px;object-fit:contain;">`;
            }
            if (stu.stage.symbol) {
                return `<span class="badge badge-info" title="${escapeHtml(stu.stage.name)}">${escapeHtml(stu.stage.symbol)}</span>`;
            }
            return `<span class="badge badge-secondary" title="${escapeHtml(stu.stage.name)}">${escapeHtml(stu.stage.name)}</span>`;
        },

        /**
         * Dropdown für einen Schüler öffnen und Stufen laden.
         */
        async openDropdown(stuId, klasseId) {
            if (!this.$store.diary.can_manage_grading) return;

            // Wenn schon für diesen Schüler offen → schließen
            if (this.dropdownOpen && this.dropdownStuId === stuId) {
                this.closeDropdown();
                return;
            }

            this.dropdownStuId = stuId;
            this.dropdownKlasseId = klasseId;
            this.dropdownOpen = true;
            this.stageLoading = true;
            this.stages = [];

            try {
                const resp = await fetch(`/paed-diary/klasse/${klasseId}/stages`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                this.stages = data.stages || [];
            } catch (_) {
                this.stages = [];
            } finally {
                this.stageLoading = false;
            }
        },

        closeDropdown() {
            this.dropdownOpen = false;
            this.dropdownStuId = null;
        },

        /**
         * Stufe für den Schüler setzen (optimistisches Update).
         */
        async selectStage(stageId) {
            if (this.stageSaving) return;
            this.stageSaving = true;

            const fd = new FormData();
            fd.append('grading_stage_id', stageId || '');
            fd.append('schueler_id', this.dropdownStuId);

            try {
                const resp = await fetch('/paed-diary/change-stage', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: fd
                });
                const j = await resp.json();
                if (j.success) {
                    // Optimistisches Store-Update
                    const stu = this.$store.diary.schueler.find(
                        s => String(s.id) === String(this.dropdownStuId)
                    );
                    if (stu) {
                        stu.stage = j.new_stage
                            ? { id: j.new_stage.id, name: j.new_stage.name, symbol: j.new_stage.symbol, image_url: j.new_stage.image_url || null }
                            : null;
                    }
                    this.closeDropdown();
                } else {
                    alert(j.message || 'Fehler beim Speichern');
                }
            } catch (_) {
                alert('Netzwerkfehler');
            } finally {
                this.stageSaving = false;
            }
        },
    }));
}

