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
function makeEventSource(cal) {
    return {
        id: String(cal.id),
        url: '/calendar/events',
        extraParams: { calendars: String(cal.id) },
        color: cal.farbe,
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
        editingEvent: null,
        activeCalendars: [],
        allCalendars: [],
        defaultView: 'timeGridWeek',
        sidebarVisible: false,

        init() {
            this.allCalendars = JSON.parse(this.$el.dataset.calendars || '[]');
            this.activeCalendars = this.allCalendars.map(c => parseInt(c.id));
            this.defaultView = this.$el.dataset.defaultView || 'timeGridWeek';
            this.sidebarVisible = window.innerWidth >= 768;

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
                // Keine globale events-URL – jeder Kalender bekommt seine eigene Quelle
                eventClick: (info) => {
                    this.showEventDetail(info.event);
                },
                dateClick: (info) => {
                    if (this.canCreate) {
                        this.openCreateModal(info.dateStr);
                    }
                },
                nowIndicator: true,
                weekNumbers: true,
                weekNumberCalculation: 'ISO',
                firstDay: 1,
                slotMinTime: '07:00:00',
                slotMaxTime: '20:00:00',
                height: 'auto',
                expandRows: true,
                navLinks: true,
                editable: false,
            });

            this.calendar.render();

            // Pro aktivem Kalender eine eigene EventSource anlegen
            this.allCalendars.forEach(cal => {
                this.calendar.addEventSource(makeEventSource(cal));
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
                // Aktivieren: Quelle neu hinzufügen → Events werden nachgeladen
                this.activeCalendars = [...this.activeCalendars, calendarId];
                const cal = this.allCalendars.find(c => parseInt(c.id) === calendarId);
                if (cal && this.calendar) {
                    this.calendar.addEventSource(makeEventSource(cal));
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
            this.editingEvent = null;
        },

        editEvent(event) {
            this.showModal = false;
            this.editingEvent = event;
            this.showCreateModal = false;
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

        rruleHuman(rrule) {
            return rruleToHuman(rrule);
        },

        get canCreate() {
            return this.$el.dataset.canCreate === 'true';
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
            const editing = this.$root.editingEvent;
            if (editing) {
                return `/calendar/termine/${editing.id}`;
            }
            return '/calendar/termine';
        },

        /**
         * Wird via x-effect="syncFromParent($root.editingEvent)" aufgerufen.
         * Alpine trackt $root.editingEvent als Dependency und ruft diese Methode
         * bei jeder Änderung erneut auf – kein $watch auf Magic Properties nötig.
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

// Sofort registrieren (window.Alpine wurde von sidebar.js gesetzt) UND
// als Fallback auf alpine:init warten (falls calendar.js vor sidebar.js lädt)
registerCalendarComponents(window.Alpine);
document.addEventListener('alpine:init', () => registerCalendarComponents(window.Alpine));
