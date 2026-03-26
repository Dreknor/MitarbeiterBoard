/**
 * PaedDiary v2 – Spalten-Manager Component
 *
 * Verantwortlich für:
 * - Boolean-Buttons und Text-Inputs in Tabellenzellen
 * - Spalten-Verwaltungscard (CRUD)
 * - Debounced saveColumnValue
 */

import { csrfToken, escapeHtml } from './utils.js';

export function registerColumnsManager(Alpine) {
    Alpine.data('columnsManager', () => ({
        /** Spalten-Verwaltungscard sichtbar */
        columnsCardOpen: false,
        /** Alle Spalten (inkl. deaktivierte), geladen via columnsAll */
        allColumns: [],
        /** Deaktivierte anzeigen */
        showDeactivated: false,
        /** Debounce-Timers für Text-Inputs */
        _debounceTimers: {},
        /** Feedback-Text in der Verwaltungscard */
        columnsFeedback: '',
        columnsFeedbackType: 'info',
        /** Formular für neue Spalte */
        newColumnName: '',
        newColumnType: 'boolean',
        newColumnCategorySelect: '',
        newColumnNewCategory: '',

        // ── Spalten für die Tabellenzellen ─────────────────────────

        /**
         * Gibt die relevanten Spalten für einen Schüler zurück
         * (im Gruppenmodus nur die der eigenen Klasse).
         */
        getColumnsForStudent(stu) {
            const store = this.$store.diary;
            if (store.is_group) {
                return (store.columns || []).filter(c => String(c.klasse_id) === String(stu.klasse_id));
            }
            return store.columns || [];
        },

        /**
         * Gibt die Spalten gruppiert nach Kategorie zurück (für die Tabellenzellen).
         * Format: [{ category: 'Name', columns: [...] }, ...]
         */
        getGroupedColumnsForStudent(stu) {
            const cols = this.getColumnsForStudent(stu);
            const byCat = {};
            const order = [];
            cols.forEach(col => {
                const cat = col.category || '';
                if (!(cat in byCat)) {
                    byCat[cat] = [];
                    order.push(cat);
                }
                byCat[cat].push(col);
            });
            // Sortieren: benannte Kategorien alphabetisch, '' (Unkategorisiert) am Ende
            order.sort((a, b) => {
                if (a === '') return 1;
                if (b === '') return -1;
                return a.localeCompare(b, 'de');
            });
            return order.map(cat => ({ category: cat, columns: byCat[cat] }));
        },

        /**
         * Gibt den aktuellen Wert einer Spalte für Schüler/Datum zurück.
         */
        getColumnValue(colId, stuId, date) {
            return this.$store.diary.column_values?.[colId]?.[stuId]?.[date] || '';
        },

        /**
         * Boolean-Button toggeln (optimistisches Update + Speichern).
         */
        async toggleBoolColumn(colId, stuId, date) {
            const current = this.getColumnValue(colId, stuId, date);
            const newVal = current === '1' ? '' : '1';

            // Optimistisches Update im Store
            this._setColumnValueLocal(colId, stuId, date, newVal);

            try {
                await this.saveColumnValue(colId, stuId, date, newVal);
            } catch (_) {
                // Revert bei Fehler
                this._setColumnValueLocal(colId, stuId, date, current);
            }
        },

        /**
         * Text-Input mit Debounce speichern.
         */
        debouncedSaveColumn(colId, stuId, date, value) {
            const key = `${colId}-${stuId}-${date}`;
            clearTimeout(this._debounceTimers[key]);
            this._debounceTimers[key] = setTimeout(() => {
                this._setColumnValueLocal(colId, stuId, date, value.trim());
                this.saveColumnValue(colId, stuId, date, value.trim()).catch(() => {
                    console.warn('[PaedDiary v2] saveColumnValue fehlgeschlagen');
                });
            }, 400);
        },

        /**
         * Lokalen Cache-Wert setzen.
         */
        _setColumnValueLocal(colId, stuId, date, value) {
            const cv = this.$store.diary.column_values;
            if (!cv[colId]) cv[colId] = {};
            if (!cv[colId][stuId]) cv[colId][stuId] = {};
            if (value === '') {
                delete cv[colId][stuId][date];
            } else {
                cv[colId][stuId][date] = value;
            }
            // Alpine-Reaktivität triggern durch Neuzuweisung
            this.$store.diary.column_values = { ...cv };
        },

        /**
         * Spaltenwert an den Server senden.
         */
        async saveColumnValue(colId, stuId, date, value) {
            const resp = await fetch('/paed-diary/column/value', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ column_id: colId, schueler_id: stuId, date, value })
            });
            if (!resp.ok) throw new Error('Speichern fehlgeschlagen');
            return await resp.json();
        },

        // ── Spalten-Verwaltungscard ────────────────────────────────

        toggleColumnsCard() {
            const store = this.$store.diary;
            if (store.selectedGroupId) return; // Im Gruppenmodus deaktiviert
            this.columnsCardOpen = !this.columnsCardOpen;
            if (this.columnsCardOpen) this.loadAllColumns();
        },

        async loadAllColumns() {
            const store = this.$store.diary;
            if (store.selectedGroupId) return;
            try {
                const params = new URLSearchParams({ klasse_id: store.selectedKlasseId });
                const resp = await fetch('/paed-diary/columns/all?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                if (!resp.ok) throw new Error('Laden fehlgeschlagen');
                const data = await resp.json();
                this.allColumns = data.columns || [];
            } catch (_) {
                this.setFeedback('Fehler beim Laden der Spalten', 'danger');
            }
        },

        /** Spalten gruppiert nach Kategorie. */
        get groupedColumns() {
            const grouped = {};
            (this.allColumns || []).forEach(c => {
                if (!this.showDeactivated && c.deactivated_from) return;
                const cat = c.category || 'Unkategorisiert';
                (grouped[cat] = grouped[cat] || []).push(c);
            });
            return Object.entries(grouped).sort(([a], [b]) => {
                if (a === 'Unkategorisiert') return 1;
                if (b === 'Unkategorisiert') return -1;
                return a.localeCompare(b, 'de');
            });
        },

        /** Vorhandene Kategorien für das Select-Feld. */
        get existingColumnCategories() {
            return [...new Set(this.allColumns.map(c => c.category).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'de'));
        },

        async addColumn() {
            const store = this.$store.diary;
            if (!this.newColumnName.trim()) return;

            let category = '';
            if (this.newColumnNewCategory.trim()) {
                category = this.newColumnNewCategory.trim();
            } else if (this.newColumnCategorySelect) {
                category = this.newColumnCategorySelect;
            }

            const fd = new FormData();
            fd.append('name', this.newColumnName.trim());
            fd.append('type', this.newColumnType);
            fd.append('klasse_id', store.selectedKlasseId);
            if (category) fd.append('category', category);

            try {
                const resp = await fetch('/paed-diary/column', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: fd
                });
                const j = await resp.json();
                if (j.success) {
                    this.newColumnName = '';
                    this.newColumnNewCategory = '';
                    this.newColumnCategorySelect = '';
                    this.setFeedback('Spalte angelegt', 'success');
                    store.loadWeek();
                    this.loadAllColumns();
                } else {
                    this.setFeedback(j.message || 'Fehler', 'danger');
                }
            } catch (_) {
                this.setFeedback('Fehler beim Anlegen', 'danger');
            }
        },

        async deactivateColumn(colId) {
            const store = this.$store.diary;
            const col = this.allColumns.find(c => c.id === colId);
            if (!col || !confirm(`Spalte "${col.name}" ab dieser Woche deaktivieren?`)) return;

            const params = new URLSearchParams({
                week_start: new Date(store.currentWeekStart).toISOString().slice(0, 10),
                klasse_id: store.selectedKlasseId
            });
            try {
                const resp = await fetch(`/paed-diary/column/${colId}?${params.toString()}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
                });
                const j = await resp.json();
                if (j.success) {
                    this.setFeedback('Spalte deaktiviert', 'warning');
                    store.loadWeek();
                    this.loadAllColumns();
                }
            } catch (_) {
                this.setFeedback('Fehler beim Deaktivieren', 'danger');
            }
        },

        async restoreColumn(colId) {
            try {
                const resp = await fetch(`/paed-diary/column/${colId}/restore`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
                });
                const j = await resp.json();
                if (j.success) {
                    this.setFeedback('Spalte reaktiviert', 'success');
                    this.$store.diary.loadWeek();
                    this.loadAllColumns();
                }
            } catch (_) {
                this.setFeedback('Fehler beim Reaktivieren', 'danger');
            }
        },

        async updateColumnCategory(colId, category) {
            try {
                const resp = await fetch(`/paed-diary/column/${colId}/category`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    body: new URLSearchParams({ category: category || '' })
                });
                const j = await resp.json();
                if (j.success) {
                    this.setFeedback('Spaltengruppe aktualisiert', 'success');
                    this.loadAllColumns();
                } else {
                    this.setFeedback(j.message || 'Fehler', 'danger');
                }
            } catch (_) {
                this.setFeedback('Fehler beim Speichern', 'danger');
            }
        },

        setFeedback(msg, type = 'info') {
            this.columnsFeedback = msg;
            this.columnsFeedbackType = type;
            setTimeout(() => { this.columnsFeedback = ''; }, 4000);
        },
    }));
}

