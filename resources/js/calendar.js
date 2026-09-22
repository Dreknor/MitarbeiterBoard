import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import rrulePlugin from '@fullcalendar/rrule';

/**
 * ============================================================
 * Kalender Alpine.js Komponenten
 * ============================================================
 * Alpine wird bereits durch sidebar.js (im globalen Layout) geladen
 * und gestartet. Hier registrieren wir nur die Kalender-spezifischen
 * Komponenten über window.Alpine – KEIN eigener Import, KEIN Alpine.start()!
 * ============================================================
 */
function registerCalendarComponents(Alpine) {
    if (!Alpine) {
        console.error('Kalender: Alpine nicht verfügbar!');
        return;
    }

// ─── Haupt-Komponente ──────────────────────────────────────────────────────────
Alpine.data('calendarApp', () => ({
    // FullCalendar-Instanz
    calendar: null,

    // Modal-Steuerung
    selectedEvent: null,
    showModal: false,
    showCreateModal: false,
    editingEvent: null,
    showIcalFeedModal: false,

    // Kalender-Filter
    activeCalendars: [],
    allCalendars: [],
    defaultView: 'timeGridWeek',

    // Sidebar
    sidebarVisible: true,

    // Termin-Suche
    searchQuery: '',
    searchLoading: false,
    searchResults: [],

    // Benutzerdefinierte Kalenderfarben (localStorage)
    customColors: {},

    // ─── Initialisierung ───────────────────────────────────────────────
    init() {
        this.allCalendars = JSON.parse(this.$el.dataset.calendars || '[]');
        this.activeCalendars = this.allCalendars.map(c => c.id);
        this.defaultView = this.$el.dataset.defaultView || 'timeGridWeek';

        // Gespeicherte Farben aus localStorage laden
        try {
            const saved = localStorage.getItem('calendar_custom_colors');
            this.customColors = saved ? JSON.parse(saved) : {};
        } catch (e) {
            this.customColors = {};
        }

        this.$nextTick(() => this.initFullCalendar());
    },

    // ─── FullCalendar ──────────────────────────────────────────────────
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
            events: {
                url: '/calendar/events',
                extraParams: () => ({
                    calendars: this.activeCalendars.join(','),
                }),
            },
            eventClick: (info) => this.showEventDetail(info.event),
            dateClick: (info) => {
                if (this.canCreate) this.openCreateModal(info.dateStr);
            },
            nowIndicator: true,
            weekNumbers: true,
            weekNumberCalculation: 'ISO',
            firstDay: 1,
            slotMinTime: '06:00:00',
            slotMaxTime: '24:00:00',
            height: 'auto',
            expandRows: true,
            navLinks: true,
            editable: false,
        });

        this.calendar.render();
    },

    // ─── Sidebar ───────────────────────────────────────────────────────
    toggleSidebar() {
        this.sidebarVisible = !this.sidebarVisible;
    },

    // ─── Kalender-Filter ───────────────────────────────────────────────
    toggleCalendar(calendarId) {
        const idx = this.activeCalendars.indexOf(calendarId);
        if (idx > -1) {
            this.activeCalendars.splice(idx, 1);
        } else {
            this.activeCalendars.push(calendarId);
        }
        this.calendar?.refetchEvents();
    },

    showAllCalendars() {
        this.activeCalendars = this.allCalendars.map(c => c.id);
        this.calendar?.refetchEvents();
    },

    hideAllCalendars() {
        this.activeCalendars = [];
        this.calendar?.refetchEvents();
    },

    // ─── Kalenderfarben ────────────────────────────────────────────────
    getEffectiveColor(calId) {
        const key = String(calId);
        if (this.customColors[key]) return this.customColors[key];
        const cal = this.allCalendars.find(c => String(c.id) === key);
        return cal?.farbe || '#6366f1';
    },

    setCustomColor(calId, color) {
        this.customColors = { ...this.customColors, [String(calId)]: color };
        try { localStorage.setItem('calendar_custom_colors', JSON.stringify(this.customColors)); } catch (e) {}
        this.calendar?.refetchEvents();
    },

    resetCustomColor(calId) {
        const colors = { ...this.customColors };
        delete colors[String(calId)];
        this.customColors = colors;
        try { localStorage.setItem('calendar_custom_colors', JSON.stringify(this.customColors)); } catch (e) {}
        this.calendar?.refetchEvents();
    },

    // ─── Termin-Suche ──────────────────────────────────────────────────
    async performSearch() {
        if (this.searchQuery.length < 2) {
            this.searchResults = [];
            return;
        }
        this.searchLoading = true;
        try {
            const resp = await fetch(`/calendar/suche?q=${encodeURIComponent(this.searchQuery)}`);
            this.searchResults = resp.ok ? await resp.json() : [];
        } catch (e) {
            this.searchResults = [];
        } finally {
            this.searchLoading = false;
        }
    },

    clearSearch() {
        this.searchQuery    = '';
        this.searchResults  = [];
        this.searchLoading  = false;
    },

    goToSearchResult(result) {
        if (this.calendar && result.beginn_raw) {
            this.calendar.gotoDate(result.beginn_raw.split('T')[0]);
        }
        this.clearSearch();
    },

    // ─── Termin-Detail-Modal ───────────────────────────────────────────
    showEventDetail(event) {
        // iCal-Feed-Termine haben keine DB-ID → direkt aus FullCalendar-Event-Objekt aufbauen
        if (event.extendedProps.isIcalFeed) {
            const feed = this.allCalendars.find(c => String(c.id) === String(event.extendedProps.calendarId));
            const fmtDatum = (dt, allDay) => {
                if (!dt) return '';
                const datOpt = { day: '2-digit', month: '2-digit', year: 'numeric' };
                const timeOpt = { hour: '2-digit', minute: '2-digit' };
                if (allDay) return dt.toLocaleDateString('de-DE', datOpt);
                return dt.toLocaleDateString('de-DE', datOpt) + ' ' + dt.toLocaleTimeString('de-DE', timeOpt);
            };
            this.selectedEvent = {
                titel:        event.title || '(Ohne Titel)',
                beginn:       fmtDatum(event.start, event.allDay),
                ende:         fmtDatum(event.end, event.allDay),
                ganztaegig:   event.allDay,
                ort:          event.extendedProps.ort || '',
                beschreibung: event.extendedProps.beschreibung || '',
                status:       null,
                rrule:        null,
                kalender:     {
                    id:    event.extendedProps.calendarId,
                    name:  event.extendedProps.calendarName || '',
                    farbe: feed?.farbe || '#6366f1',
                },
                teilnehmer:   [],
                can_edit:     false,
            };
            this.showModal = true;
            return;
        }

        // OxTermin → per API laden
        fetch(`/calendar/termin/${event.extendedProps.terminId}`)
            .then(r => r.json())
            .then(data => {
                this.selectedEvent = data;
                this.showModal = true;
            });
    },

    closeModal() {
        this.showModal = false;
        this.selectedEvent = null;
    },

    openCreateModal(dateStr) {
        this.showCreateModal = true;
    },

    // ─── Termin bearbeiten / löschen ───────────────────────────────────
    editEvent(event) {
        this.editingEvent = event;
        this.showModal = false;
    },

    deleteEvent(event) {
        if (!confirm(`Termin "${event.titel}" wirklich löschen?`)) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/calendar/termine/${event.id}`;

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(csrf);

        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'DELETE';
        form.appendChild(method);

        document.body.appendChild(form);
        form.submit();
    },

    // ─── RRULE → lesbarer Text (Deutsch) ──────────────────────────────
    rruleHuman(rrule) {
        if (!rrule) return '';

        const parts = {};
        rrule.split(';').forEach(p => {
            const eq = p.indexOf('=');
            if (eq > -1) parts[p.slice(0, eq).trim()] = p.slice(eq + 1).trim();
        });

        const freqMap = { DAILY: 'täglich', WEEKLY: 'wöchentlich', MONTHLY: 'monatlich', YEARLY: 'jährlich' };
        let text = freqMap[parts.FREQ] || (parts.FREQ || 'Wiederholung');

        if (parts.INTERVAL && parseInt(parts.INTERVAL) > 1) {
            const unitMap = { DAILY: 'Tage', WEEKLY: 'Wochen', MONTHLY: 'Monate', YEARLY: 'Jahre' };
            text = `alle ${parts.INTERVAL} ${unitMap[parts.FREQ] || ''}`;
        }

        if (parts.BYDAY) {
            const dayMap = { MO: 'Mo', TU: 'Di', WE: 'Mi', TH: 'Do', FR: 'Fr', SA: 'Sa', SU: 'So' };
            const days = parts.BYDAY.split(',').map(d => dayMap[d.trim()] || d).join(', ');
            text += ` (${days})`;
        }

        if (parts.UNTIL) {
            const u = parts.UNTIL;
            text += `, bis ${u.slice(6, 8)}.${u.slice(4, 6)}.${u.slice(0, 4)}`;
        } else if (parts.COUNT) {
            text += `, ${parts.COUNT}× gesamt`;
        }

        return text;
    },

    // ─── Hilfsmethoden ─────────────────────────────────────────────────
    currentWeekDate() {
        if (this.calendar) return this.calendar.getDate().toISOString().split('T')[0];
        return new Date().toISOString().split('T')[0];
    },

    get canCreate() {
        return this.$el.dataset.canCreate === 'true';
    },

    // ─── iCal-Feed löschen (AJAX DELETE) ──────────────────────────────
    async deleteIcalFeed(deleteUrl, feedName) {
        if (!confirm(`Feed "${feedName}" wirklich entfernen?`)) return;
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const resp = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (resp.ok) {
                window.location.reload();
            } else {
                alert('Fehler beim Entfernen des Feeds.');
            }
        } catch (e) {
            alert('Verbindungsfehler beim Entfernen des Feeds.');
        }
    },
}));

// ─── terminForm-Komponente (TODO 17) ──────────────────────────────────────────
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
        const editingEvent = this.$parent?.editingEvent ?? null;
        return editingEvent ? `/calendar/termine/${editingEvent.id}` : '/calendar/termine';
    },

    // Wird per x-effect aus dem Parent-Scope aufgerufen:
    // x-effect="syncFromParent(editingEvent)"
    syncFromParent(event) {
        if (!event) return;
        this.formData = {
            ox_calendar_id: event.kalender?.id || '',
            titel:          event.titel || '',
            beschreibung:   event.beschreibung || '',
            ort:            event.ort || '',
            beginn:         event.beginn_iso?.slice(0, 16) || '',
            ende:           event.ende_iso?.slice(0, 16)   || '',
            ganztaegig:     event.ganztaegig || false,
            rrule:          event.rrule || '',
        };
    },

    init() {},

    toggleDay(day) {
        const idx = this.recurrence.byDay.indexOf(day);
        if (idx > -1) this.recurrence.byDay.splice(idx, 1);
        else this.recurrence.byDay.push(day);
    },

    updateRrule() {
        if (this.recurrence.type === 'none') { this.formData.rrule = ''; return; }

        const freqByType = { daily: 'DAILY', weekly: 'WEEKLY', monthly: 'MONTHLY', custom: this.recurrence.frequency };
        const freq = freqByType[this.recurrence.type] || 'WEEKLY';
        const parts = [`FREQ=${freq}`];

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

    prepareSubmit() {
        this.updateRrule();
    },

    prepareAndSubmit(e) {
        this.updateRrule();
        // Formular nach RRULE-Aktualisierung abschicken
        e?.target?.submit();
    },
}));

} // end registerCalendarComponents

// Komponenten direkt registrieren – kein eigenes Alpine.start()!
if (window.Alpine) {
    registerCalendarComponents(window.Alpine);
} else {
    // Fallback: auf alpine:init warten (sidebar.js startet Alpine verzögert via window.load)
    document.addEventListener('alpine:init', () => registerCalendarComponents(window.Alpine));
}
