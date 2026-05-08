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
        // Position des (per x-teleport in den body verschobenen) Dropdowns – fixed-Koordinaten
        dropdownTop: 0,
        dropdownLeft: 0,
        triggerEl: null,
        _repositionHandler: null,

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
        async openDropdown(stuId, klasseId, triggerEl) {
            if (!this.$store.diary.can_manage_grading) return;

            // Wenn schon für diesen Schüler offen → schließen
            if (this.dropdownOpen && this.dropdownStuId === stuId) {
                this.closeDropdown();
                return;
            }

            this.triggerEl = triggerEl || null;
            this.dropdownStuId = stuId;
            this.dropdownKlasseId = klasseId;
            this.dropdownOpen = true;
            this.stageLoading = true;
            this.stages = [];

            // Position berechnen + Listener registrieren, damit beim Scrollen/Resize neu positioniert wird
            this.computePosition();
            this._repositionHandler = () => this.computePosition();
            window.addEventListener('scroll', this._repositionHandler, true);
            window.addEventListener('resize', this._repositionHandler);

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
                // Nach dem Laden kann sich die Höhe ändern → erneut positionieren
                this.$nextTick(() => this.computePosition());
            }
        },

        closeDropdown() {
            this.dropdownOpen = false;
            this.dropdownStuId = null;
            this.triggerEl = null;
            if (this._repositionHandler) {
                window.removeEventListener('scroll', this._repositionHandler, true);
                window.removeEventListener('resize', this._repositionHandler);
                this._repositionHandler = null;
            }
        },

        /**
         * Berechnet die fixed-Position des Dropdowns relativ zum Viewport.
         * Verhindert, dass das Dropdown durch `contain:paint` der Tabellenzellen
         * oder durch `transform`-Container abgeschnitten wird (insbesondere auf iPad).
         */
        computePosition() {
            if (!this.triggerEl) return;
            const rect = this.triggerEl.getBoundingClientRect();
            const vw = window.innerWidth || document.documentElement.clientWidth || 320;
            const vh = window.innerHeight || document.documentElement.clientHeight || 568;
            const dropdownEstWidth = 200;
            const dropdownEstHeight = 320;

            // Horizontal: linksbündig zum Trigger, aber nicht über rechten Rand
            let left = rect.left;
            if (left + dropdownEstWidth > vw - 8) {
                left = Math.max(8, vw - dropdownEstWidth - 8);
            }

            // Vertikal: unterhalb des Triggers; falls dort nicht genug Platz → oberhalb
            let top;
            if (rect.bottom + dropdownEstHeight + 6 > vh) {
                top = Math.max(8, rect.top - dropdownEstHeight - 6);
            } else {
                top = rect.bottom + 6;
            }

            this.dropdownTop = top;
            this.dropdownLeft = left;
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

