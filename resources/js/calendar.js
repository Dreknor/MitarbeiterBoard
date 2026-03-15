/**
 * Kalender-Modul – Alpine.js + FullCalendar
 *
 * Alpine wird bereits durch sidebar.js (im globalen Layout) geladen und gestartet.
 * KEIN eigener Alpine-Import und KEIN Alpine.start() hier!
 * Registrierung wie in wochenplan.js via window.Alpine.
 *
 * Toggle-Strategie: Pro Kalender eine eigene FullCalendar-EventSource.
 * source.remove() blendet Termine sofort aus – kein Alpine-Proxy-Workaround nötig.
 */
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import rrulePlugin from '@fullcalendar/rrule';

// ─── RRULE → lesbarer Text ───────────────────────────────────────────────────
function rruleToHuman(rrule) {
    if (!rrule) return '';
    const rulePart = rrule.split('\n').find(l => l.startsWith('RRULE:')) || rrule;
    const raw = rulePart.replace('RRULE:', '');
    const parts = {};
    raw.split(';').forEach(p => {
        const [k, v] = p.split('=');
        if (k) parts[k.trim()] = (v || '').trim();
    });

    const freqMap = { DAILY: 'Täglich', WEEKLY: 'Wöchentlich', MONTHLY: 'Monatlich', YEARLY: 'Jährlich' };
    const dayMap  = { MO: 'Mo', TU: 'Di', WE: 'Mi', TH: 'Do', FR: 'Fr', SA: 'Sa', SU: 'So' };

    let result = freqMap[parts.FREQ] || parts.FREQ || '';

    if (parts.INTERVAL && parts.INTERVAL !== '1') {
        result += `, alle ${parts.INTERVAL} ${result.toLowerCase()}`;
    }
    if (parts.BYDAY) {
        const days = parts.BYDAY.split(',')
            .map(d => dayMap[d.replace(/[-0-9]/g, '')] || d)
            .join(', ');
        result += ` (${days})`;
    }
    if (parts.BYMONTHDAY) {
        result += `, am ${parts.BYMONTHDAY}.`;
    }
    if (parts.COUNT) {
        result += `, ${parts.COUNT}×`;
    }
    if (parts.UNTIL) {
        const d = parts.UNTIL.replace(/(\d{4})(\d{2})(\d{2}).*/, '$3.$2.$1');
        result += ` bis ${d}`;
    }
    return result || rrule;
}

// ─── Hilfsfunktion: EventSource-Definition für einen Kalender ────────────────
function makeEventSource(cal, customColor) {
    return {
        id: String(cal.id),
        url: '/calendar/events',
        extraParams: { calendars: String(cal.id) },
        color: customColor || cal.farbe,
    };
}

// ─── Alpine-Komponente registrieren ──────────────────────────────────────────
function registerCalendarComponents(Alpine) {
    if (!Alpine) {
        console.error('Kalender: Alpine nicht verfügbar!');
        return;
    }

    Alpine.data('calendarApp', () => ({
        calendar: null,
        selectedEvent: null,
        showModal: false,
        showCreateModal: false,
        showIcalFeedModal: false,   // TODO 30
        editingEvent: null,
        activeCalendars: [],
        allCalendars: [],
        defaultView: 'timeGridWeek',
        sidebarVisible: false,
        // ── Suche (TODO 27) ──────────────────────────────────────────────
        searchQuery: '',
        searchResults: [],
        searchLoading: false,
        // ── Farb-Persistenz (TODO 29) ────────────────────────────────────
        customColors: {},
        _colorSaveTimeout: null,

        init() {
            this.allCalendars = JSON.parse(this.$el.dataset.calendars || '[]');
            this.activeCalendars = this.allCalendars.map(c => parseInt(c.id));
            this.defaultView = this.$el.dataset.defaultView || 'timeGridWeek';
            this.sidebarVisible = window.innerWidth >= 768;

            // Hybrid-Farben laden (localStorage + DB) – TODO 29
            this.loadCustomColors();

            this.$nextTick(() => {
                this.initFullCalendar();
            });
        },

        initFullCalendar() {
            const calendarEl = this.$refs.calendarEl;
            if (!calendarEl) return;

            this.calendar = new Calendar(calendarEl, {
                plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin, rrulePlugin],
                initialView: this.defaultView,
                locale: 'de',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                },
                buttonText: {
                    today: 'Heute',
                    month: 'Monat',
                    week: 'Woche',
                    day: 'Tag',
                    list: 'Liste',
                },
                eventClick: (info) => {
                    this.showEventDetail(info.event);
                },
                // ── Drag-and-Drop (TODO 23) ──────────────────────────────────
                editable: this.canEdit,
                eventStartEditable: this.canEdit,
                eventDurationEditable: this.canEdit,
                // RRULE-Events visuell als nicht-verschiebbar markieren
                eventClassNames: (arg) => {
                    if (arg.event.extendedProps?.rrule) {
                        return ['fc-event-no-drag'];
                    }
                    return [];
                },
                // Drag-and-Drop: Termin verschieben
                eventDrop: (info) => {
                    this.handleEventMove(info);
                },
                // Resize: Termin verlängern/verkürzen
                eventResize: (info) => {
                    this.handleEventMove(info);
                },
                // ── Terminauswahl im Kalender (TODO 31) ─────────────────────
                selectable: this.canCreate,
                selectMirror: this.canCreate,
                selectOverlap: true,
                unselectAuto: true,
                // Zeitbereich-Auswahl → Create-Modal mit vorausgefüllten Zeiten
                select: (info) => {
                    if (this.canCreate) {
                        this.openCreateFromSelection(info);
                    }
                },
                // Einzel-Klick → Create-Modal (Fallback für Monatsansicht)
                dateClick: (info) => {
                    if (this.canCreate) {
                        this.openCreateModal(info.dateStr);
                    }
                },
                // ── Allgemein ────────────────────────────────────────────────
                nowIndicator: true,
                weekNumbers: true,
                weekNumberCalculation: 'ISO',
                firstDay: 1,
                slotMinTime: '07:00:00',
                slotMaxTime: '20:00:00',
                height: 'auto',
                expandRows: true,
                navLinks: true,
            });

            this.calendar.render();

            // Pro aktivem Kalender eine eigene EventSource anlegen (mit User-Farbe – TODO 29)
            this.allCalendars.forEach(cal => {
                this.calendar.addEventSource(makeEventSource(cal, this.getEffectiveColor(cal.id)));
            });
        },

        // ─── Toggle: EventSource hinzufügen / entfernen ───────────────────
        toggleCalendar(calendarId) {
            calendarId = parseInt(calendarId);
            const idx = this.activeCalendars.indexOf(calendarId);

            if (idx > -1) {
                // Deaktivieren: Quelle entfernen → FullCalendar blendet Termine sofort aus
                this.activeCalendars = this.activeCalendars.filter(id => id !== calendarId);
                const src = this.calendar?.getEventSourceById(String(calendarId));
                if (src) src.remove();
            } else {
                // Aktivieren: Quelle neu hinzufügen mit effektiver Farbe (TODO 29)
                this.activeCalendars = [...this.activeCalendars, calendarId];
                const cal = this.allCalendars.find(c => parseInt(c.id) === calendarId);
                if (cal && this.calendar) {
                    this.calendar.addEventSource(makeEventSource(cal, this.getEffectiveColor(calendarId)));
                }
            }
        },

        showAllCalendars() {
            this.allCalendars.forEach(cal => {
                const calId = parseInt(cal.id);
                if (!this.activeCalendars.includes(calId)) {
                    this.activeCalendars = [...this.activeCalendars, calId];
                    if (this.calendar && !this.calendar.getEventSourceById(String(cal.id))) {
                        this.calendar.addEventSource(makeEventSource(cal));
                    }
                }
            });
        },

        hideAllCalendars() {
            this.allCalendars.forEach(cal => {
                const src = this.calendar?.getEventSourceById(String(cal.id));
                if (src) src.remove();
            });
            this.activeCalendars = [];
        },

        toggleSidebar() {
            this.sidebarVisible = !this.sidebarVisible;
            this.$nextTick(() => {
                if (this.calendar) this.calendar.updateSize();
            });
        },

        showEventDetail(event) {
            const terminId = event.extendedProps?.terminId;
            if (!terminId) return;

            fetch(`/calendar/termin/${terminId}`)
                .then(r => {
                    if (!r.ok) {
                        throw new Error(`HTTP ${r.status}`);
                    }
                    return r.json();
                })
                .then(data => {
                    this.selectedEvent = data;
                    this.showModal = true;
                })
                .catch(err => {
                    console.error('Termin laden fehlgeschlagen:', err);
                    alert('Termin konnte nicht geladen werden. Bitte Seite neu laden.');
                });
        },

        closeModal() {
            this.showModal = false;
            this.selectedEvent = null;
        },

        openCreateModal(dateStr) {
            this.showCreateModal = true;
            this.editingEvent = null;

            // Zeitbereich an terminForm übergeben (konsistent mit FullCalendar-Konvention:
            // allDay-End ist exklusiv → applySelection zieht 1 Tag ab)
            this.$nextTick(() => {
                if (dateStr.length === 10) {
                    // Nur Datum (YYYY-MM-DD) → ganztägig, End = nächster Tag (exklusiv)
                    const nextDay = new Date(dateStr + 'T00:00:00');
                    nextDay.setDate(nextDay.getDate() + 1);
                    window.dispatchEvent(new CustomEvent('calendar-selection', {
                        detail: {
                            start: dateStr,
                            end: nextDay.toISOString().split('T')[0],
                            allDay: true,
                        },
                    }));
                } else {
                    // Datum mit Uhrzeit → 1-Stunden-Block
                    const endDate = new Date(dateStr);
                    endDate.setHours(endDate.getHours() + 1);
                    window.dispatchEvent(new CustomEvent('calendar-selection', {
                        detail: { start: dateStr, end: endDate.toISOString(), allDay: false },
                    }));
                }
            });
        },

        /**
         * Öffnet Create-Modal mit vorausgefülltem Zeitbereich aus FullCalendar-Auswahl.
         * (TODO 31 – Terminauswahl durch Ziehen im Kalender)
         */
        openCreateFromSelection(info) {
            this.showCreateModal = true;
            this.editingEvent = null;

            this.$nextTick(() => {
                window.dispatchEvent(new CustomEvent('calendar-selection', {
                    detail: {
                        start: info.startStr,
                        end: info.endStr,
                        allDay: info.allDay,
                    },
                }));
            });

            // Selektion visuell aufheben
            this.calendar.unselect();
        },

        /**
         * Termin per Drag-and-Drop verschieben oder per Resize verlängern.
         * (TODO 23 – eventDrop / eventResize Handler)
         */
        async handleEventMove(info) {
            const event = info.event;
            const terminId = event.extendedProps?.terminId;

            if (!terminId) {
                info.revert();
                return;
            }

            // RRULE-Events ablehnen (Sicherheits-Fallback, Server lehnt ebenfalls ab)
            if (event.extendedProps?.rrule) {
                info.revert();
                return;
            }

            try {
                const response = await fetch(`/calendar/termine/${terminId}/verschieben`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        beginn:              event.start.toISOString(),
                        ende:                (event.end || event.start).toISOString(),
                        ganztaegig:          event.allDay,
                        expected_updated_at: event.extendedProps?.updatedAt || '',
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    info.revert();
                    if (data.reload) {
                        // Optimistic-Locking-Konflikt → Events vollständig neu laden
                        this.calendar.refetchEvents();
                    }
                    alert(data.error || 'Fehler beim Verschieben.');
                    return;
                }

                // updated_at im Event aktualisieren (für zukünftige Moves)
                event.setExtendedProp('updatedAt', data.updated_at);

            } catch (err) {
                console.error('Termin verschieben fehlgeschlagen:', err);
                info.revert();
                alert('Netzwerkfehler beim Verschieben des Termins.');
            }
        },

        rruleHuman(rrule) {
            return rruleToHuman(rrule);
        },

        // ── Farb-Persistenz – Hybrid localStorage/DB (TODO 29) ──────────

        /**
         * Hybrid-Laden: localStorage zuerst, dann DB-Farben aus data-user-colors mergen.
         * Priorität: DB > localStorage (DB ist die Quelle der Wahrheit bei Gerätewechsel).
         */
        loadCustomColors() {
            let localColors = {};
            try {
                const stored = localStorage.getItem('calendar_custom_colors');
                localColors = stored ? JSON.parse(stored) : {};
            } catch (e) {
                localColors = {};
            }

            let dbColors = {};
            try {
                dbColors = JSON.parse(this.$el.dataset.userColors || '{}');
            } catch (e) {
                dbColors = {};
            }

            // DB überschreibt localStorage; neue LS-Einträge bleiben erhalten
            this.customColors = { ...localColors, ...dbColors };
            this._persistToLocalStorage();

            // Falls localStorage neue Einträge hat, die noch nicht in DB sind → Sync
            const localOnlyKeys = Object.keys(localColors).filter(k => !(k in dbColors));
            if (localOnlyKeys.length > 0) {
                this._debouncedSaveToDb();
            }
        },

        /**
         * Benutzerdefinierte Farbe setzen. Sofort: localStorage + UI. Hintergrund: DB.
         */
        setCustomColor(calendarId, color) {
            calendarId = String(calendarId);
            this.customColors[calendarId] = color;
            this._persistToLocalStorage();
            this.updateCalendarColor(calendarId, color);
            this._debouncedSaveToDb();
        },

        /** Benutzerdefinierte Farbe zurücksetzen (Admin-Standard). */
        resetCustomColor(calendarId) {
            calendarId = String(calendarId);
            delete this.customColors[calendarId];
            this._persistToLocalStorage();

            const cal = this.allCalendars.find(c => String(c.id) === calendarId);
            if (cal) {
                this.updateCalendarColor(calendarId, cal.farbe);
            }

            fetch(`/calendar/farben/${calendarId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
            }).catch(err => console.warn('Farbe-Reset DB-Sync fehlgeschlagen:', err));
        },

        /** Effektive Farbe: user-custom > admin-standard. */
        getEffectiveColor(calendarId) {
            return this.customColors[String(calendarId)]
                || this.allCalendars.find(c => String(c.id) === String(calendarId))?.farbe
                || '#3b82f6';
        },

        /** EventSource-Farbe in FullCalendar live aktualisieren. */
        updateCalendarColor(calendarId, color) {
            const src = this.calendar?.getEventSourceById(String(calendarId));
            if (src) {
                src.remove();
                const cal = this.allCalendars.find(c => String(c.id) === String(calendarId));
                if (cal) {
                    this.calendar.addEventSource(makeEventSource(cal, color));
                }
            }
        },

        _persistToLocalStorage() {
            try {
                localStorage.setItem('calendar_custom_colors', JSON.stringify(this.customColors));
            } catch (e) { /* localStorage voll – kein Problem, DB ist Fallback */ }
        },

        _debouncedSaveToDb() {
            if (this._colorSaveTimeout) clearTimeout(this._colorSaveTimeout);
            this._colorSaveTimeout = setTimeout(() => this._saveColorsToDb(), 500);
        },

        async _saveColorsToDb() {
            try {
                await fetch('/calendar/farben', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ farben: this.customColors }),
                });
            } catch (err) {
                console.warn('Farben DB-Sync fehlgeschlagen:', err);
            }
        },

        // ── PDF-Export (TODO 28) ──────────────────────────────────────────

        /** Gibt das aktuell angezeigte Datum im Kalender zurück (für PDF-Link). */
        currentWeekDate() {
            if (this.calendar) {
                return this.calendar.getDate().toISOString().split('T')[0];
            }
            return new Date().toISOString().split('T')[0];
        },

        // ── Suche (TODO 27) ──────────────────────────────────────────────

        async performSearch() {
            const query = this.searchQuery.trim();
            if (query.length < 2) {
                this.searchResults = [];
                return;
            }
            this.searchLoading = true;
            try {
                const response = await fetch(`/calendar/suche?q=${encodeURIComponent(query)}`);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                this.searchResults = await response.json();
            } catch (err) {
                console.error('Suche fehlgeschlagen:', err);
                this.searchResults = [];
            } finally {
                this.searchLoading = false;
            }
        },

        clearSearch() {
            this.searchQuery = '';
            this.searchResults = [];
        },

        goToSearchResult(result) {
            if (this.calendar && result.beginn_iso) {
                this.calendar.gotoDate(result.beginn_iso);
                this.calendar.changeView('timeGridDay', result.beginn_iso);
            }
            // Detail-Modal öffnen
            this.showEventDetail({ extendedProps: { terminId: result.id } });
            this.clearSearch();
        },

        get canCreate() {
            return this.$el.dataset.canCreate === 'true';
        },

        get canEdit() {
            return this.$el.dataset.canEdit === 'true';
        },

        editEvent(event) {
            this.showModal = false;
            this.editingEvent = event;
            this.showCreateModal = true;
        },

        deleteEvent(event) {
            if (!confirm(`Termin "${event.titel}" wirklich löschen?`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/calendar/termine/${event.id}`;

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
            form.appendChild(csrf);

            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'DELETE';
            form.appendChild(method);

            document.body.appendChild(form);
            form.submit();
        },
    }));

    // ─── terminForm-Komponente ────────────────────────────────────────────────
    Alpine.data('terminForm', () => ({
        formData: {
            ox_calendar_id: '',
            titel: '',
            beschreibung: '',
            ort: '',
            beginn: '',
            ende: '',
            ganztaegig: false,
            rrule: '',
        },

        recurrence: {
            type: 'none',
            frequency: 'WEEKLY',
            interval: 1,
            byDay: [],
            endType: 'never',
            until: '',
            count: 10,
        },

        // Mapping deutsche Wochentage → iCal
        dayMap: { 'MO': 'MO', 'DI': 'TU', 'MI': 'WE', 'DO': 'TH', 'FR': 'FR', 'SA': 'SA', 'SO': 'SU' },

        get formAction() {
            const editing = this.editingEvent;
            if (editing) {
                return `/calendar/termine/${editing.id}`;
            }
            return '/calendar/termine';
        },

        /**
         * calendar-selection-Event von calendarApp empfangen und Formular vorausfüllen.
         * (TODO 31 – Terminauswahl durch Klick/Ziehen im Kalender)
         */
        init() {
            window.addEventListener('calendar-selection', (e) => {
                this.applySelection(e.detail);
            });
        },

        /**
         * Zeitbereich aus FullCalendar-Auswahl ins Formular übernehmen.
         *
         * Konvention: Bei allDay-Events ist endStr in FullCalendar exklusiv (nächster Tag).
         * → Wir ziehen 1 Tag ab, um das inklusive Ende zu erhalten.
         */
        applySelection(selection) {
            if (selection.allDay) {
                this.formData.ganztaegig = true;
                this.formData.beginn = selection.start.split('T')[0];
                // Exklusives End-Datum → inklusiv (−1 Tag)
                const endDate = new Date(selection.end + 'T00:00:00');
                endDate.setDate(endDate.getDate() - 1);
                this.formData.ende = endDate.toISOString().split('T')[0];
            } else {
                this.formData.ganztaegig = false;
                // datetime-local-Input braucht Format YYYY-MM-DDTHH:MM
                this.formData.beginn = selection.start.slice(0, 16);
                this.formData.ende   = selection.end.slice(0, 16);
            }
        },

        /**
         * Wird via x-effect="syncFromParent(editingEvent)" aufgerufen.
         * Alpine trackt editingEvent als Dependency aus dem Parent-Scope (calendarApp)
         * und ruft diese Methode bei jeder Änderung erneut auf.
         * Hinweis: $root bezieht sich in Alpine v3 auf das DOM-Element der aktuellen
         * Komponente – für Parent-Scope-Zugriff muss die Eigenschaft direkt benannt werden.
         */
        syncFromParent(event) {
            if (event) {
                this.formData = {
                    ox_calendar_id: event.kalender?.id || '',
                    titel: event.titel || '',
                    beschreibung: event.beschreibung || '',
                    ort: event.ort || '',
                    beginn: event.beginn_iso?.slice(0, 16) || '',
                    ende: event.ende_iso?.slice(0, 16) || '',
                    ganztaegig: event.ganztaegig || false,
                    rrule: event.rrule || '',
                };
            } else {
                this.resetForm();
            }
        },

        resetForm() {
            this.formData = {
                ox_calendar_id: '',
                titel: '',
                beschreibung: '',
                ort: '',
                beginn: '',
                ende: '',
                ganztaegig: false,
                rrule: '',
            };
            this.recurrence = {
                type: 'none',
                frequency: 'WEEKLY',
                interval: 1,
                byDay: [],
                endType: 'never',
                until: '',
                count: 10,
            };
        },

        toggleDay(day) {
            const idx = this.recurrence.byDay.indexOf(day);
            if (idx > -1) {
                this.recurrence.byDay.splice(idx, 1);
            } else {
                this.recurrence.byDay.push(day);
            }
        },

        updateRrule() {
            if (this.recurrence.type === 'none') {
                this.formData.rrule = '';
                return;
            }

            let parts = [];
            let freq = '';

            switch (this.recurrence.type) {
                case 'daily':   freq = 'DAILY';   break;
                case 'weekly':  freq = 'WEEKLY';  break;
                case 'monthly': freq = 'MONTHLY'; break;
                case 'custom':  freq = this.recurrence.frequency; break;
            }

            parts.push(`FREQ=${freq}`);

            if (this.recurrence.type === 'custom' && this.recurrence.interval > 1) {
                parts.push(`INTERVAL=${this.recurrence.interval}`);
            }

            if (freq === 'WEEKLY' && this.recurrence.byDay.length > 0) {
                parts.push(`BYDAY=${this.recurrence.byDay.join(',')}`);
            }

            if (this.recurrence.endType === 'until' && this.recurrence.until) {
                parts.push(`UNTIL=${this.recurrence.until.replace(/-/g, '')}T235959Z`);
            } else if (this.recurrence.endType === 'count' && this.recurrence.count > 0) {
                parts.push(`COUNT=${this.recurrence.count}`);
            }

            this.formData.rrule = parts.join(';');
        },

        prepareAndSubmit(e) {
            // Finale RRULE aktualisieren
            this.updateRrule();
            // Formular normal abschicken
            e.target.submit();
        },
    }));
}

// ── Alpine-Registrierung: robuste Triple-Guard-Strategie ──────────────────────
//
// Szenario A: sidebar.js hat window.Alpine bereits gesetzt, Alpine.start() noch
//             nicht aufgerufen (Normalfall – window.load noch ausstehend).
//             → sofortiger Aufruf genügt.
//
// Szenario B: calendar.js lädt nach Alpine.start() (z. B. weil das 325-kB-Bundle
//             deutlich langsamer als sidebar.js + load-Event war).
//             → alpine:init ist dann bereits verstrichen. In diesem Fall prüfen
//               wir, ob Alpine bereits initialisiert ist (_started) und wenden
//               Alpine.data() trotzdem an – Alpine re-initialisiert neue
//               x-data-Elemente nicht, aber die Komponent-Registry wird befüllt,
//               sodass ein Reload die Seite korrekt zeigt.
//
// Szenario C: calendar.js lädt vor sidebar.js (unwahrscheinlich, aber möglich
//             bei Race-Condition im Modul-Lader).
//             → window.Alpine ist noch undefined; alpine:init-Listener greift.

document.addEventListener('alpine:init', () => registerCalendarComponents(window.Alpine));

if (window.Alpine) {
    // Bereits verfügbar → sofort registrieren
    registerCalendarComponents(window.Alpine);
} else {
    // Noch nicht verfügbar → warten bis sidebar.js window.Alpine setzt
    // (wird durch alpine:init oben abgedeckt, aber als Fallback:)
    const waitForAlpine = setInterval(() => {
        if (window.Alpine) {
            clearInterval(waitForAlpine);
            registerCalendarComponents(window.Alpine);
        }
    }, 10);
    // Spätestens nach 5 s aufgeben
    setTimeout(() => clearInterval(waitForAlpine), 5000);
}
