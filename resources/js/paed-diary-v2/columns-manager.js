/**
 * PaedDiary v2 – Spalten-Manager Component
 *
 * Verantwortlich für:
 * - Boolean-Buttons und Text-Inputs in Tabellenzellen
 * - Spalten-Verwaltungscard (CRUD)
 * - Debounced saveColumnValue
 */

import { csrfToken, escapeHtml, formatDate } from './utils.js';

// Debounce-Timer der Text-Spalten (modulweit, damit Zellen keine eigenen Timer-Objekte brauchen)
const debounceTimers = {};

/**
 * Methoden für die Spaltenwerte einer Tabellenzelle. Werden sowohl von der schlanken
 * Zellen-Komponente columnCell() als auch von columnsManager() genutzt.
 */
const cellMethods = {
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
     * Tag (Y-m-d), von dem ein Wert übernommen wurde („bis Wochenende“), sonst null.
     */
    getColumnQuelle(colId, stuId, date) {
        return this.$store.diary.column_value_quellen?.[colId]?.[stuId]?.[date] || null;
    },

    /** Ist der angezeigte Wert von einem früheren Tag der Woche übernommen? */
    istUebernommen(colId, stuId, date) {
        return !!this.getColumnQuelle(colId, stuId, date) && this.getColumnValue(colId, stuId, date) !== '';
    },

    /** Hinweistext für übernommene Werte, z. B. „übernommen von Mo, 16.11.“ (sonst leer). */
    uebernahmeHinweis(colId, stuId, date) {
        if (!this.istUebernommen(colId, stuId, date)) return '';
        const quelle = this.getColumnQuelle(colId, stuId, date);
        const d = new Date(quelle + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        const tag = d.toLocaleDateString('de-DE', { weekday: 'short' }).replace('.', '');
        const datum = d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
        return `übernommen von ${tag}, ${datum}`;
    },

    /** Hat eine Spaltengruppe die Einstellung „bis Wochenende“? */
    gruppeBisWochenende(group) {
        return (group?.columns || []).some(c => c.bis_wochenende);
    },

    /**
     * Boolean-Button toggeln (optimistisches Update + Speichern).
     */
    async toggleBoolColumn(colId, stuId, date) {
        const current = this.getColumnValue(colId, stuId, date);
        const newVal = current === '1' ? '' : '1';

        await this._umschaltenUndSpeichern(colId, stuId, date, current, newVal);
    },

    /**
     * Ampel-Button durchklicken (4 Zustände zyklisch):
     * '' (grau/neutral, unbeantwortet) → '1' (grün/Ja) → '2' (gelb/In Bearbeitung) → '3' (rot/Nein) → '' …
     */
    async toggleAmpelColumn(colId, stuId, date) {
        const current = this.getColumnValue(colId, stuId, date);
        const order = ['', '1', '2', '3'];
        const idx = order.indexOf(current);
        const newVal = order[(idx + 1) % order.length];

        await this._umschaltenUndSpeichern(colId, stuId, date, current, newVal);
    },

    /**
     * Optimistisch setzen, speichern und danach die vom Server berechnete Woche übernehmen
     * (bei „bis Wochenende“ ändern sich auch die Folgetage). Bei Fehler zurücksetzen.
     */
    async _umschaltenUndSpeichern(colId, stuId, date, vorher, neu) {
        const quelleVorher = this.getColumnQuelle(colId, stuId, date);
        this._setColumnValueLocal(colId, stuId, date, neu);

        try {
            const antwort = await this.saveColumnValue(colId, stuId, date, neu);
            this._wocheUebernehmen(colId, stuId, antwort);
        } catch (_) {
            this._setColumnValueLocal(colId, stuId, date, vorher, quelleVorher);
        }
    },

    /**
     * Gültige Werte der Woche aus der Server-Antwort ({werte: {datum: wert}, quellen: {datum: von}})
     * in den Store schreiben.
     */
    _wocheUebernehmen(colId, stuId, antwort) {
        if (!antwort || !antwort.werte || Array.isArray(antwort.werte)) return;
        const store = this.$store.diary;
        const cv = store.column_values;
        const cq = store.column_value_quellen || {};
        cv[colId] = cv[colId] || {};
        cv[colId][stuId] = cv[colId][stuId] || {};
        cq[colId] = cq[colId] || {};
        cq[colId][stuId] = cq[colId][stuId] || {};
        const quellen = Array.isArray(antwort.quellen) ? {} : (antwort.quellen || {});
        Object.entries(antwort.werte).forEach(([datum, wert]) => {
            if (wert === null || wert === '') {
                delete cv[colId][stuId][datum];
            } else {
                cv[colId][stuId][datum] = wert;
            }
            if (quellen[datum]) {
                cq[colId][stuId][datum] = quellen[datum];
            } else {
                delete cq[colId][stuId][datum];
            }
        });
        store.column_values = { ...cv };
        store.column_value_quellen = { ...cq };
    },

    /**
     * Liefert Label + CSS-Klasse für den aktuellen Ampel-Zustand.
     */
    ampelState(colId, stuId, date) {
        const value = this.getColumnValue(colId, stuId, date);
        if (value === '1') return { label: 'Ja', cssClass: 'ampel-btn--ja' };
        if (value === '2') return { label: 'In Bearbeitung', cssClass: 'ampel-btn--bearbeitung' };
        if (value === '3') return { label: 'Nein', cssClass: 'ampel-btn--nein' };
        return { label: 'Unbeantwortet', cssClass: 'ampel-btn--neutral' };
    },

    /**
     * Text-Input mit Debounce speichern.
     */
    debouncedSaveColumn(colId, stuId, date, value) {
        const key = `${colId}-${stuId}-${date}`;
        clearTimeout(debounceTimers[key]);
        debounceTimers[key] = setTimeout(() => {
            this._setColumnValueLocal(colId, stuId, date, value.trim());
            this.saveColumnValue(colId, stuId, date, value.trim()).catch(() => {});
        }, 400);
    },

    /**
     * Lokalen Cache-Wert setzen.
     */
    _setColumnValueLocal(colId, stuId, date, value, quelle = null) {
        const cv = this.$store.diary.column_values;
        if (!cv[colId]) cv[colId] = {};
        if (!cv[colId][stuId]) cv[colId][stuId] = {};
        if (value === '') {
            delete cv[colId][stuId][date];
        } else {
            cv[colId][stuId][date] = value;
        }
        // Ein am Tag selbst gesetzter Wert ist nicht (mehr) übernommen
        const cq = this.$store.diary.column_value_quellen || {};
        if (quelle) {
            cq[colId] = cq[colId] || {};
            cq[colId][stuId] = cq[colId][stuId] || {};
            cq[colId][stuId][date] = quelle;
        } else if (cq[colId]?.[stuId]?.[date]) {
            delete cq[colId][stuId][date];
        }
        // Alpine-Reaktivität triggern durch Neuzuweisung
        this.$store.diary.column_values = { ...cv };
        this.$store.diary.column_value_quellen = { ...cq };
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
        const key = `${colId}-${stuId}-${date}`;
        const store = this.$store.diary;
        if (!resp.ok) {
            // Fehler sichtbar machen (Feld rot + Banner) statt still zu verwerfen
            store.columnSaveErrors = { ...store.columnSaveErrors, [key]: true };
            const col = (store.columns || []).find(c => String(c.id) === String(colId));
            store.showError(`„${col?.name || 'Spalte'}“ wurde nicht gespeichert. Bitte erneut eingeben.`);
            throw new Error('Speichern fehlgeschlagen');
        }
        if (store.columnSaveErrors[key]) {
            const { [key]: _, ...rest } = store.columnSaveErrors;
            store.columnSaveErrors = rest;
        }
        return await resp.json();
    },

    /** Ist das Speichern dieses Werts zuletzt fehlgeschlagen? */
    hasSaveError(colId, stuId, date) {
        return !!this.$store.diary.columnSaveErrors[`${colId}-${stuId}-${date}`];
    },
};

/**
 * Zustand und Methoden der Spaltenverwaltung – auch von der neuen Ansicht (Seitenleiste) genutzt.
 */
export function columnsManagerMethods() {
    return {
        /** Alle Spalten (inkl. deaktivierte), geladen via columnsAll */
        allColumns: [],
        /** Darf „bis Wochenende“ für Spaltengruppen umschalten (Recht wie Spaltengruppen umbenennen) */
        darfBisWochenende: false,
        /** Deaktivierte anzeigen */
        showDeactivated: false,
        /** Feedback-Text in der Verwaltungscard */
        columnsFeedback: '',
        columnsFeedbackType: 'info',
        /** Formular für neue Spalte */
        newColumnName: '',
        newColumnType: 'boolean',
        newColumnCategorySelect: '',
        newColumnNewCategory: '',
        /** Ausgewählte Ziel-Klassen (Mehrfachauswahl) für das Anlegen neuer Spalten */
        newColumnKlasseIds: [],
        /** Alle Klassen des Lehrers (id, name), aus dem data-Attribut der Card gelesen */
        allKlassenList: [],
        /** Welche Spalte gerade ein "In andere Klassen kopieren"-Panel geöffnet hat */
        copyOpenFor: null,
        /** Ausgewählte Ziel-Klassen für den Kopiervorgang */
        copyTargetIds: [],
        /** Spalte, deren Bearbeiten-Dialog offen ist (id) */
        bearbeitenFuer: null,
        /** Daten des Bearbeiten-Dialogs (vom Server, siehe PaedDiarySpaltenService::uebersicht) */
        bearbeitung: null,
        /** Formularwerte des Bearbeiten-Dialogs */
        bearbeitungForm: { name: '', category: '', type: 'boolean', klasse_ids: [] },
        bearbeitungKategorien: [],
        bearbeitungFehler: '',
        bearbeitungSpeichert: false,

        /**
         * Initialisierung: Wenn die Spalten-Card geöffnet wird,
         * lädt diese Instanz automatisch alle Spalten nach.
         */
        init() {
            try {
                this.allKlassenList = JSON.parse(this.$el.dataset.klassen || '[]');
            } catch (_) {
                this.allKlassenList = [];
            }
            this.newColumnKlasseIds = this.$store.diary.selectedKlasseId ? [this.$store.diary.selectedKlasseId] : [];
            this.$watch('$store.diary.columnsCardOpen', (open) => {
                if (open) {
                    this.loadAllColumns();
                    this.newColumnKlasseIds = this.$store.diary.selectedKlasseId ? [this.$store.diary.selectedKlasseId] : [];
                }
            });
        },

        /** Checkbox-Handler für die Ziel-Klassen-Auswahl beim Anlegen. */
        toggleNewColumnKlasse(klasseId) {
            const id = parseInt(klasseId);
            const idx = this.newColumnKlasseIds.indexOf(id);
            if (idx === -1) {
                this.newColumnKlasseIds.push(id);
            } else {
                this.newColumnKlasseIds.splice(idx, 1);
            }
        },

        // ── Spalten für die Tabellenzellen ─────────────────────────

        ...cellMethods,

        // ── Spalten-Verwaltungscard ────────────────────────────────

        toggleColumnsCard() {
            const store = this.$store.diary;
            if (store.selectedGroupId) return; // Im Gruppenmodus deaktiviert
            store.columnsCardOpen = !store.columnsCardOpen;
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
                this.darfBisWochenende = !!data.darf_bis_wochenende;
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
            if (!this.newColumnKlasseIds.length) {
                this.setFeedback('Bitte mindestens eine Klasse auswählen', 'warning');
                return;
            }

            let category = '';
            if (this.newColumnNewCategory.trim()) {
                category = this.newColumnNewCategory.trim();
            } else if (this.newColumnCategorySelect) {
                category = this.newColumnCategorySelect;
            }

            const fd = new FormData();
            fd.append('name', this.newColumnName.trim());
            fd.append('type', this.newColumnType);
            this.newColumnKlasseIds.forEach(id => fd.append('klasse_ids[]', id));
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
                    this.newColumnKlasseIds = store.selectedKlasseId ? [store.selectedKlasseId] : [];
                    const count = (j.columns || (j.column ? [j.column] : [])).length;
                    this.setFeedback(count > 1 ? `Spalte in ${count} Klassen angelegt` : 'Spalte angelegt', 'success');
                    store.loadWeek();
                    this.loadAllColumns();
                } else {
                    this.setFeedback(j.message || 'Fehler', 'danger');
                }
            } catch (_) {
                this.setFeedback('Fehler beim Anlegen', 'danger');
            }
        },

        // ── Spalte in andere Klassen kopieren ──────────────────────

        /** Öffnet/schließt das Kopier-Panel für eine Spalte. */
        toggleCopyPanel(colId) {
            this.bearbeitenFuer = null;
            this.copyOpenFor = this.copyOpenFor === colId ? null : colId;
            this.copyTargetIds = [];
        },

        /** Klassen, in die eine Spalte kopiert werden kann (alle außer der eigenen). */
        copyTargetsFor(col) {
            return this.allKlassenList.filter(k => parseInt(k.id) !== parseInt(col.klasse_id));
        },

        toggleCopyTarget(klasseId) {
            const id = parseInt(klasseId);
            const idx = this.copyTargetIds.indexOf(id);
            if (idx === -1) {
                this.copyTargetIds.push(id);
            } else {
                this.copyTargetIds.splice(idx, 1);
            }
        },

        async copyColumn(colId) {
            if (!this.copyTargetIds.length) return;
            try {
                const resp = await fetch(`/paed-diary/column/${colId}/copy`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ klasse_ids: this.copyTargetIds })
                });
                const j = await resp.json();
                if (j.success) {
                    const createdCount = (j.created || []).length;
                    const skippedCount = (j.skipped || []).length;
                    let msg = createdCount ? `Spalte in ${createdCount} Klasse(n) kopiert` : 'Keine Spalte kopiert';
                    if (skippedCount) {
                        const names = j.skipped.map(s => s.name).join(', ');
                        msg += ` (übersprungen, da Name bereits existiert: ${names})`;
                    }
                    this.setFeedback(msg, skippedCount && !createdCount ? 'warning' : 'success');
                    this.copyOpenFor = null;
                    this.copyTargetIds = [];
                    this.$store.diary.loadWeek();
                    this.loadAllColumns();
                } else {
                    this.setFeedback(j.message || 'Fehler', 'danger');
                }
            } catch (_) {
                this.setFeedback('Fehler beim Kopieren', 'danger');
            }
        },

        // ── Spalte bearbeiten (Name, Gruppe, Art, Klassen) ─────────

        /** Öffnet/schließt den Bearbeiten-Dialog einer Spalte und lädt dessen Daten. */
        async bearbeitenUmschalten(colId) {
            if (this.bearbeitenFuer === colId) {
                this.bearbeitenSchliessen();
                return;
            }
            this.copyOpenFor = null;
            this.bearbeitenFuer = colId;
            this.bearbeitung = null;
            this.bearbeitungFehler = '';
            try {
                const resp = await fetch(`/paed-diary/column/${colId}/bearbeiten`, { headers: { 'Accept': 'application/json' } });
                if (!resp.ok) throw new Error('Laden fehlgeschlagen');
                const j = await resp.json();
                if (this.bearbeitenFuer !== colId) return;
                this.bearbeitung = j.spalte;
                this.bearbeitungKategorien = j.kategorien || [];
                this.bearbeitungForm = {
                    name: j.spalte.name || '',
                    category: j.spalte.category || '',
                    type: j.spalte.type,
                    klasse_ids: j.spalte.klassen.filter(k => k.status === 'aktiv').map(k => k.id),
                };
            } catch (_) {
                this.bearbeitungFehler = 'Die Spalte konnte nicht geladen werden.';
            }
        },

        bearbeitenSchliessen() {
            this.bearbeitenFuer = null;
            this.bearbeitung = null;
            this.bearbeitungFehler = '';
        },

        bearbeitungKlasseUmschalten(klasseId) {
            const id = parseInt(klasseId);
            const ids = this.bearbeitungForm.klasse_ids;
            const idx = ids.indexOf(id);
            if (idx === -1) ids.push(id); else ids.splice(idx, 1);
        },

        /** Hinweis neben einer Klasse im Bearbeiten-Dialog. */
        bearbeitungKlasseHinweis(k) {
            const gewaehlt = this.bearbeitungForm.klasse_ids.includes(k.id);
            if (k.status === 'aktiv' && !gewaehlt) return 'wird ab heute deaktiviert';
            if (k.status === 'deaktiviert') {
                const seit = k.deaktiviert_seit ? new Date(k.deaktiviert_seit + 'T00:00:00').toLocaleDateString('de-DE') : '';
                return gewaehlt ? 'wird wieder aktiviert' : `deaktiviert seit ${seit}`;
            }
            if (k.status === 'keine' && gewaehlt) return 'wird angelegt';
            return '';
        },

        async bearbeitungSpeichern() {
            if (!this.bearbeitung || this.bearbeitungSpeichert) return;
            const colId = this.bearbeitung.id;
            this.bearbeitungSpeichert = true;
            this.bearbeitungFehler = '';
            try {
                const resp = await fetch(`/paed-diary/column/${colId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: this.bearbeitungForm.name.trim(),
                        category: this.bearbeitungForm.category.trim(),
                        type: this.bearbeitungForm.type,
                        klasse_ids: this.bearbeitungForm.klasse_ids,
                    })
                });
                const j = await resp.json().catch(() => ({}));
                if (!resp.ok || !j.success) {
                    const fehler = j.errors ? Object.values(j.errors).flat() : [];
                    this.bearbeitungFehler = fehler[0] || j.message || 'Speichern fehlgeschlagen.';
                    return;
                }
                const e = j.ergebnis || {};
                const teile = [];
                if (e.neu) teile.push(`${e.neu} Klasse(n) neu`);
                if (e.uebernommen) teile.push(`${e.uebernommen} vorhandene Spalte(n) übernommen`);
                if (e.reaktiviert) teile.push(`${e.reaktiviert} reaktiviert`);
                if (e.deaktiviert) teile.push(`${e.deaktiviert} ab heute deaktiviert`);
                this.setFeedback('Spalte gespeichert' + (teile.length ? ` (${teile.join(', ')})` : ''), 'success');
                this.bearbeitenSchliessen();
                this.$store.diary.loadWeek();
                this.loadAllColumns();
            } catch (_) {
                this.bearbeitungFehler = 'Speichern fehlgeschlagen.';
            } finally {
                this.bearbeitungSpeichert = false;
            }
        },

        async deactivateColumn(colId, bestaetigt = false) {
            const store = this.$store.diary;
            const col = this.allColumns.find(c => c.id === colId);
            // Die neue Ansicht fragt selbst (inline) nach und übergibt bestaetigt = true
            if (!col || (!bestaetigt && !confirm(`Spalte "${col.name}" ab dieser Woche deaktivieren?`))) return;

            const params = new URLSearchParams({
                week_start: formatDate(store.currentWeekStart),
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

        /** Hat die Spaltengruppe (Name aus groupedColumns) die Einstellung „bis Wochenende“? */
        gruppeNameBisWochenende(name) {
            if (!name || name === 'Unkategorisiert') return false;
            return (this.allColumns || []).some(c => c.category === name && c.bis_wochenende);
        },

        /**
         * „Gilt bis Freitag“ für eine ganze Spaltengruppe ein-/ausschalten (alle Klassen).
         */
        async setGruppeBisWochenende(name, aktiv) {
            if (!name || name === 'Unkategorisiert') return;
            try {
                const resp = await fetch('/paed-diary/column-groups/bis-wochenende', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ name, aktiv })
                });
                const j = await resp.json().catch(() => ({}));
                if (!resp.ok || !j.success) throw new Error(j.message || 'Fehler');
                this.setFeedback(aktiv
                    ? `„${name}“: Bestätigungen gelten ab jetzt bis Freitag`
                    : `„${name}“: Bestätigungen gelten wieder nur für den Tag`, 'success');
                this.$store.diary.loadWeek();
                this.loadAllColumns();
            } catch (_) {
                this.setFeedback('Einstellung konnte nicht gespeichert werden', 'danger');
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
    };
}

export function registerColumnsManager(Alpine) {
    // Schlanke Komponente für die Tabellenzellen (kein Verwaltungs-State, kein Watcher).
    // Vorher bekam jede Zelle eine komplette columnsManager()-Instanz: Beim Öffnen der
    // Spaltenverwaltung lud jede davon die Spaltenliste neu.
    Alpine.data('columnCell', () => ({ ...cellMethods }));
    Alpine.data('columnsManager', columnsManagerMethods);
}
