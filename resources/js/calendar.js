import Alpine from 'alpinejs';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import rrulePlugin from '@fullcalendar/rrule';

// Alpine.js-Komponente für den Kalender
Alpine.data('calendarApp', () => ({
    calendar: null,
    selectedEvent: null,
    showModal: false,
    showCreateModal: false,
    activeCalendars: [],
    allCalendars: [],
    defaultView: 'timeGridWeek',

    init() {
        // Kalender-Daten aus Blade-Variablen lesen
        this.allCalendars = JSON.parse(this.$el.dataset.calendars || '[]');
        this.activeCalendars = this.allCalendars.map(c => c.id);
        this.defaultView = this.$el.dataset.defaultView || 'timeGridWeek';

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
            events: {
                url: '/calendar/events',
                extraParams: () => ({
                    calendars: this.activeCalendars.join(','),
                }),
            },
            eventClick: (info) => {
                this.showEventDetail(info.event);
            },
            dateClick: (info) => {
                if (this.canCreate) {
                    this.openCreateModal(info.dateStr);
                }
            },
            // Weitere FullCalendar-Optionen
            nowIndicator: true,
            weekNumbers: true,
            weekNumberCalculation: 'ISO',
            firstDay: 1, // Montag
            slotMinTime: '07:00:00',
            slotMaxTime: '22:00:00',  // Bis 22 Uhr, damit Abendtermine sichtbar sind
            height: 'auto',
            expandRows: true,
            navLinks: true,
            editable: false, // Drag&Drop deaktiviert (OX = Datenhoheit)
        });

        this.calendar.render();
    },

    toggleCalendar(calendarId) {
        const idx = this.activeCalendars.indexOf(calendarId);
        if (idx > -1) {
            this.activeCalendars.splice(idx, 1);
        } else {
            this.activeCalendars.push(calendarId);
        }
        this.calendar.refetchEvents();
    },

    showEventDetail(event) {
        // Event-Details per AJAX laden
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
        // Datum vorbelegen (wird in TODO 17 erweitert)
    },

    /**
     * Gibt das ISO-Datum der aktuell im Kalender angezeigten Woche zurück.
     * Wird für den PDF-Export-Button genutzt.
     */
    currentWeekDate() {
        if (this.calendar) {
            return this.calendar.getDate().toISOString().split('T')[0];
        }
        return new Date().toISOString().split('T')[0];
    },

    // Wird in TODO 17 ergänzt
    get canCreate() {
        return this.$el.dataset.canCreate === 'true';
    },
}));

Alpine.start();

