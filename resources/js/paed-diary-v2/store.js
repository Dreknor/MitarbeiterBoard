/**
 * PaedDiary v2 – Zentraler Alpine-Store
 *
 * Single Source of Truth für alle Wochendaten, Navigation
 * und gemeinsam genutzten State.
 */

import { formatDate, startOfWeek, addDays, csrfToken } from './utils.js';

export function registerDiaryStore(Alpine) {
    Alpine.store('diary', {
        // ── Initialisierung ────────────────────────────────────────
        loading: false,
        initialized: false,

        // ── Navigation ─────────────────────────────────────────────
        currentWeekStart: startOfWeek(new Date()),
        selectedKlasseId: null,
        selectedGroupId: null,

        // ── Wochendaten (via loadWeek) ─────────────────────────────
        days: [],
        schueler: [],
        entries: [],
        columns: [],
        column_values: {},
        tasks: [],
        pauses: [],
        absences: [],
        categories: [],
        hidden_category_ids: [],
        klassen: [],
        is_group: false,
        can_manage_grading: false,
        show_column_categories: false,
        open_entries: [],

        // ── UI-Toggles (komponentenübergreifend) ────────────────────
        showPaused: false,
        columnsCardOpen: false,
        hideAllCategoryHeadings: localStorage.getItem('paedDiary_hideAllHeadings') === '1',
        filterUncategorized: localStorage.getItem('paedDiary_filterUncategorized') === '1',

        // ── Termine (separat geladen) ──────────────────────────────
        appointments: [],

        // ── Gruppen (separat geladen) ──────────────────────────────
        groups: [],

        // ── Computed-artige Getter ─────────────────────────────────

        /** Pause-Map: { entryId: { schuelerId: { date: true } } } */
        get pauseMap() {
            const map = {};
            (this.pauses || []).forEach(p => {
                if (!map[p.entry_id]) map[p.entry_id] = {};
                if (!map[p.entry_id][p.schueler_id]) map[p.entry_id][p.schueler_id] = {};
                map[p.entry_id][p.schueler_id][p.date] = true;
            });
            return map;
        },

        /** Abwesenheits-Map: { schuelerId: { datum: true } } */
        get absenceMap() {
            const map = {};
            (this.absences || []).forEach(a => {
                if (!map[a.schueler_id]) map[a.schueler_id] = {};
                map[a.schueler_id][a.datum] = true;
            });
            return map;
        },

        /** Heutiges Datum als YYYY-MM-DD */
        get todayStr() {
            return formatDate(new Date());
        },

        /** Wochen-Label: "DD.MM.YYYY - DD.MM.YYYY" */
        get weekLabel() {
            const start = new Date(this.currentWeekStart);
            const end = addDays(start, 4);
            return start.toLocaleDateString('de-DE') + ' – ' + end.toLocaleDateString('de-DE');
        },

        // ── Hilfsmethoden ──────────────────────────────────────────

        isPaused(entryId, stuId, date) {
            return !!(this.pauseMap[entryId]?.[stuId]?.[date]);
        },

        isAbsent(stuId, date) {
            return !!(this.absenceMap[stuId]?.[date]);
        },

        // ── Kern-Methoden ──────────────────────────────────────────

        /**
         * Initialisiert den Store mit der angegebenen Klasse/Gruppe.
         * HINWEIS: Darf NICHT "init" heißen – Alpine ruft init() auf Stores
         * automatisch ohne Argumente auf, was zu klasse_id=undefined führt.
         *
         * @param {number} klasseId
         * @param {number|null} groupId
         */
        bootstrap(klasseId, groupId) {
            this.selectedKlasseId = klasseId;
            this.selectedGroupId = groupId || null;
            this.loadWeek();
            this.loadGroups();
            this.initialized = true;
        },

        /**
         * Lädt alle Wochendaten vom Server (API: GET /paed-diary/week).
         */
        async loadWeek() {
            this.loading = true;
            const params = new URLSearchParams({
                week_start: formatDate(this.currentWeekStart)
            });
            if (this.selectedGroupId) {
                params.append('group_id', this.selectedGroupId);
            } else {
                params.append('klasse_id', this.selectedKlasseId);
            }

            try {
                const resp = await fetch('/paed-diary/week?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();

                // Alle State-Properties mit Serverdaten aktualisieren
                this.days = data.days || [];
                this.schueler = data.schueler || [];
                this.entries = data.entries || [];
                this.columns = data.columns || [];
                this.column_values = data.column_values || {};
                this.tasks = data.tasks || [];
                this.pauses = data.pauses || [];
                this.absences = data.absences || [];
                this.categories = data.categories || [];
                this.hidden_category_ids = (data.hidden_category_ids || []).map(Number);
                this.klassen = data.klassen || [];
                this.is_group = data.is_group || false;
                this.can_manage_grading = data.can_manage_grading || false;
                this.show_column_categories = data.show_column_categories || false;
                this.open_entries = data.open_entries || [];

                // Termine separat nachladen
                this.loadAppointments();
            } catch (e) {
                console.error('[PaedDiary v2] loadWeek Fehler:', e);
            } finally {
                this.loading = false;
            }
        },

        /**
         * Lädt Termine für die aktuelle Woche (API: GET /paed-diary/appointments).
         */
        async loadAppointments() {
            const params = new URLSearchParams({
                start_date: formatDate(this.currentWeekStart),
                end_date: formatDate(addDays(this.currentWeekStart, 6))
            });
            if (this.selectedGroupId) {
                params.append('group_id', this.selectedGroupId);
            } else {
                params.append('klasse_id', this.selectedKlasseId);
            }
            try {
                const resp = await fetch('/paed-diary/appointments?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                this.appointments = data.appointments || [];
            } catch (e) {
                console.error('[PaedDiary v2] loadAppointments Fehler:', e);
                this.appointments = [];
            }
        },

        /**
         * Lädt die Klassengruppen des Nutzers (API: GET /paed-diary/class-groups).
         */
        async loadGroups() {
            try {
                const resp = await fetch('/paed-diary/class-groups', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                this.groups = data.groups || [];
            } catch (e) {
                console.error('[PaedDiary v2] loadGroups Fehler:', e);
                this.groups = [];
            }
        },

        // ── Navigation ─────────────────────────────────────────────

        prevWeek() {
            this.currentWeekStart = addDays(this.currentWeekStart, -7);
            this.loadWeek();
        },

        nextWeek() {
            this.currentWeekStart = addDays(this.currentWeekStart, 7);
            this.loadWeek();
        },

        goToday() {
            this.currentWeekStart = startOfWeek(new Date());
            this.loadWeek();
        },

        changeKlasse(klasseId) {
            this.selectedKlasseId = parseInt(klasseId);
            this.selectedGroupId = null;
            this.currentWeekStart = startOfWeek(new Date());
            this.loadWeek();
        },

        changeGroup(groupId) {
            this.selectedGroupId = groupId ? parseInt(groupId) : null;
            if (this.selectedGroupId) this.columnsCardOpen = false;
            this.loadWeek();
        },

        /**
         * Spaltengruppen-Überschriften ein-/ausblenden (server-persistiert).
         */
        async toggleShowColumnCategories() {
            const newVal = !this.show_column_categories;
            // Optimistisches UI-Update
            this.show_column_categories = newVal;

            try {
                const resp = await fetch('/paed-diary/settings/show-categories', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ show_column_categories: newVal })
                });
                const j = await resp.json();
                if (!j.success) {
                    // Revert bei Fehler
                    this.show_column_categories = !newVal;
                }
            } catch (_) {
                this.show_column_categories = !newVal;
            }
        },
    });
}

