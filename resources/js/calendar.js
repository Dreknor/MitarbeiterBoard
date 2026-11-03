import { Calendar } from '@fullcalendar/core';
import deLocale from '@fullcalendar/core/locales/de';
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
 *
 * - calendarApp: Hauptansicht (FullCalendar, Filter, Räume, Detail-Modal)
 * - terminForm:  Anlegen/Bearbeiten (mehrere Kalender, optional Raumbuchung)
 * - icsImport:   Vorschau/Auswahl beim ICS-Import
 * ============================================================
 */

const MOBILE_QUERY = '(max-width: 767px)';
const MAX_ROOMS = 8;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function readJson(el, key, fallback) {
    try {
        const raw = el.dataset[key];
        return raw ? JSON.parse(raw) : fallback;
    } catch (e) {
        return fallback;
    }
}

function storageGet(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (e) {
        return fallback;
    }
}

function storageSet(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* privater Modus */ }
}

const pad = (n) => String(n).padStart(2, '0');

/** Date → 'YYYY-MM-DD' (lokale Zeit) */
function toDateInput(d) {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Date → 'YYYY-MM-DDTHH:mm' (lokale Zeit, für datetime-local) */
function toDateTimeInput(d) {
    return `${toDateInput(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** RRULE → lesbarer Text (Deutsch) */
function rruleHuman(rrule) {
    if (!rrule) return '';

    const parts = {};
    rrule.replace(/^RRULE:/, '').split(';').forEach((p) => {
        const eq = p.indexOf('=');
        if (eq > -1) parts[p.slice(0, eq).trim()] = p.slice(eq + 1).trim();
    });

    const freqMap = { DAILY: 'täglich', WEEKLY: 'wöchentlich', MONTHLY: 'monatlich', YEARLY: 'jährlich' };
    let text = freqMap[parts.FREQ] || 'wiederkehrend';

    if (parts.INTERVAL && parseInt(parts.INTERVAL, 10) > 1) {
        const unitMap = { DAILY: 'Tage', WEEKLY: 'Wochen', MONTHLY: 'Monate', YEARLY: 'Jahre' };
        text = `alle ${parts.INTERVAL} ${unitMap[parts.FREQ] || ''}`.trim();
    }

    if (parts.BYDAY) {
        const dayMap = { MO: 'Mo', TU: 'Di', WE: 'Mi', TH: 'Do', FR: 'Fr', SA: 'Sa', SU: 'So' };
        const days = parts.BYDAY.split(',').map((d) => dayMap[d.trim()] || d).join(', ');
        text += ` (${days})`;
    }

    if (parts.UNTIL) {
        const u = parts.UNTIL;
        text += `, bis ${u.slice(6, 8)}.${u.slice(4, 6)}.${u.slice(0, 4)}`;
    } else if (parts.COUNT) {
        text += `, ${parts.COUNT}× gesamt`;
    }

    return text.charAt(0).toUpperCase() + text.slice(1);
}

function registerCalendarComponents(Alpine) {
    if (!Alpine) {
        console.error('Kalender: Alpine nicht verfügbar!');
        return;
    }

// ─── Haupt-Komponente ──────────────────────────────────────────────────────────
Alpine.data('calendarApp', () => ({
    calendar: null,

    // Modals
    selectedEvent: null,
    showModal: false,
    showIcalFeedModal: false,
    showImportModal: false,

    // Kalender-Filter
    activeCalendars: [],
    allCalendars: [],
    defaultView: 'timeGridWeek',

    // Räume aus der Raumplanung
    allRooms: [],
    activeRooms: [],
    roomFilter: '',
    maxRooms: MAX_ROOMS,

    // Layout
    sidebarVisible: true,
    isMobile: false,

    // Termin-Suche
    searchQuery: '',
    searchLoading: false,
    searchResults: [],

    // Benutzerdefinierte Kalenderfarben (localStorage)
    customColors: {},

    canCreate: false,
    canBookRooms: false,

    // ─── Initialisierung ───────────────────────────────────────────────
    init() {
        const el = this.$el;
        this.allCalendars = readJson(el, 'calendars', []);
        this.allRooms     = readJson(el, 'rooms', []);
        this.defaultView  = el.dataset.defaultView || 'timeGridWeek';
        this.canCreate    = el.dataset.canCreate === 'true';
        this.canBookRooms = el.dataset.canBookRooms === 'true';

        // Ausgeblendete Kalender merken (neue Kalender sind automatisch sichtbar)
        const hidden = storageGet('calendar_hidden_calendars', []);
        this.activeCalendars = this.allCalendars.map((c) => c.id).filter((id) => !hidden.includes(String(id)));

        const roomIds = this.allRooms.map((r) => r.id);
        this.activeRooms = storageGet('calendar_active_rooms', []).filter((id) => roomIds.includes(id)).slice(0, MAX_ROOMS);

        this.customColors = storageGet('calendar_custom_colors', {});

        const mq = window.matchMedia(MOBILE_QUERY);
        this.isMobile = mq.matches;
        this.sidebarVisible = !this.isMobile && storageGet('calendar_sidebar_visible', true);
        mq.addEventListener?.('change', (e) => {
            this.isMobile = e.matches;
            this.sidebarVisible = !e.matches;
        });

        // Upload-Fehler beim Import → Modal wieder öffnen
        this.showImportModal = el.dataset.importError === 'true';

        this.$nextTick(() => {
            this.initFullCalendar();

            // Nach Validierungsfehler: Formular mit alten Eingaben erneut öffnen
            const alt = readJson(el, 'formOld', null);
            if (alt) {
                window.dispatchEvent(new CustomEvent('termin-form-open', { detail: { alt } }));
            }
        });
    },

    // ─── FullCalendar ──────────────────────────────────────────────────
    initFullCalendar() {
        const calendarEl = this.$refs.calendarEl;
        if (!calendarEl) return;

        this.calendar = new Calendar(calendarEl, {
            plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin, rrulePlugin],
            locale: deLocale,
            initialView: this.isMobile ? 'listWeek' : this.defaultView,
            headerToolbar: this.isMobile
                ? { left: 'prev,next', center: 'title', right: 'today' }
                : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
            footerToolbar: this.isMobile
                ? { center: 'dayGridMonth,timeGridWeek,listWeek' }
                : false,
            buttonText: { today: 'Heute', month: 'Monat', week: 'Woche', day: 'Tag', list: 'Liste' },
            events: {
                url: '/calendar/events',
                extraParams: () => ({
                    calendars: this.activeCalendars.length ? this.activeCalendars.join(',') : 'none',
                    rooms: this.activeRooms.join(','),
                }),
                failure: () => window.$?.notify?.({ message: 'Termine konnten nicht geladen werden.' }, { type: 'danger' }),
            },
            eventDataTransform: (ev) => {
                const calId = ev.extendedProps?.calendarId;
                if (calId !== undefined && !ev.extendedProps?.isRoomBooking && this.customColors[String(calId)]) {
                    ev.color = this.customColors[String(calId)];
                }
                if (ev.rrule) {
                    ev.classNames = [...(ev.classNames || []), 'fc-event-no-drag'];
                }
                return ev;
            },
            eventDidMount: (info) => this.decorateEvent(info),
            eventClick: (info) => {
                info.jsEvent.preventDefault();
                this.showEventDetail(info.event);
            },
            selectable: this.canCreate,
            selectMirror: true,
            select: (info) => {
                this.openCreateModal({ start: info.start, end: info.end, allDay: info.allDay });
                this.calendar.unselect();
            },
            nowIndicator: true,
            weekNumbers: !this.isMobile,
            firstDay: 1,
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
            allDayText: 'ganzt.',
            dayMaxEvents: true,
            height: 'auto',
            expandRows: true,
            stickyHeaderDates: true,
            navLinks: true,
            editable: false,
            noEventsText: 'Keine Termine in diesem Zeitraum',
        });

        this.calendar.render();
    },

    /** Tooltip + Farbpunkte für Termine, die in mehreren Kalendern stehen */
    decorateEvent(info) {
        const props = info.event.extendedProps || {};
        const tooltip = [info.event.title];
        if (props.ort) tooltip.push(props.ort);

        const kalender = props.kalender || [];
        if (kalender.length > 1) {
            tooltip.push('Kalender: ' + kalender.map((k) => k.name).join(', '));
            const target = info.el.querySelector('.fc-event-title, .fc-list-event-title');
            if (target && !target.querySelector('.cal-multi-dots')) {
                const dots = document.createElement('span');
                dots.className = 'cal-multi-dots';
                kalender.slice(1, 5).forEach((k) => {
                    const dot = document.createElement('span');
                    dot.style.backgroundColor = this.getEffectiveColor(k.id);
                    dot.title = k.name;
                    dots.appendChild(dot);
                });
                target.appendChild(dots);
            }
        }
        info.el.title = tooltip.join('\n');
    },

    refetch() {
        this.calendar?.refetchEvents();
    },

    // ─── Sidebar ───────────────────────────────────────────────────────
    toggleSidebar() {
        this.sidebarVisible = !this.sidebarVisible;
        if (!this.isMobile) storageSet('calendar_sidebar_visible', this.sidebarVisible);
        // FullCalendar-Breite nach dem Ein-/Ausblenden neu berechnen
        this.$nextTick(() => this.calendar?.updateSize());
    },

    // ─── Kalender-Filter ───────────────────────────────────────────────
    persistCalendarFilter() {
        const hidden = this.allCalendars.map((c) => String(c.id)).filter((id) => !this.activeCalendars.map(String).includes(id));
        storageSet('calendar_hidden_calendars', hidden);
    },

    toggleCalendar(calendarId) {
        const idx = this.activeCalendars.indexOf(calendarId);
        if (idx > -1) this.activeCalendars.splice(idx, 1);
        else this.activeCalendars.push(calendarId);
        this.persistCalendarFilter();
        this.refetch();
    },

    showAllCalendars() {
        this.activeCalendars = this.allCalendars.map((c) => c.id);
        this.persistCalendarFilter();
        this.refetch();
    },

    hideAllCalendars() {
        this.activeCalendars = [];
        this.persistCalendarFilter();
        this.refetch();
    },

    // ─── Räume ─────────────────────────────────────────────────────────
    get filteredRooms() {
        const q = this.roomFilter.trim().toLowerCase();
        if (!q) return this.allRooms;
        return this.allRooms.filter((r) => `${r.name} ${r.nummer || ''}`.toLowerCase().includes(q));
    },

    toggleRoom(roomId) {
        const idx = this.activeRooms.indexOf(roomId);
        if (idx > -1) {
            this.activeRooms.splice(idx, 1);
        } else if (this.activeRooms.length < MAX_ROOMS) {
            this.activeRooms.push(roomId);
        } else {
            // Checkbox-Zustand zurücksetzen
            this.activeRooms = [...this.activeRooms];
            return;
        }
        storageSet('calendar_active_rooms', this.activeRooms);
        this.refetch();
    },

    clearRooms() {
        this.activeRooms = [];
        storageSet('calendar_active_rooms', []);
        this.refetch();
    },

    // ─── Kalenderfarben ────────────────────────────────────────────────
    getEffectiveColor(calId) {
        const key = String(calId);
        if (this.customColors[key]) return this.customColors[key];
        const cal = this.allCalendars.find((c) => String(c.id) === key);
        return cal?.farbe || '#6366f1';
    },

    setCustomColor(calId, color) {
        this.customColors = { ...this.customColors, [String(calId)]: color };
        storageSet('calendar_custom_colors', this.customColors);
        this.refetch();
    },

    resetCustomColor(calId) {
        const colors = { ...this.customColors };
        delete colors[String(calId)];
        this.customColors = colors;
        storageSet('calendar_custom_colors', this.customColors);
        this.refetch();
    },

    // ─── Termin-Suche ──────────────────────────────────────────────────
    async performSearch() {
        if (this.searchQuery.length < 2) {
            this.searchResults = [];
            return;
        }
        this.searchLoading = true;
        try {
            const resp = await fetch(`/calendar/suche?q=${encodeURIComponent(this.searchQuery)}`, {
                headers: { Accept: 'application/json' },
            });
            this.searchResults = resp.ok ? await resp.json() : [];
        } catch (e) {
            this.searchResults = [];
        } finally {
            this.searchLoading = false;
        }
    },

    clearSearch() {
        this.searchQuery = '';
        this.searchResults = [];
        this.searchLoading = false;
    },

    goToSearchResult(result) {
        if (this.calendar && result.beginn_raw) {
            this.calendar.gotoDate(result.beginn_raw.split('T')[0]);
        }
        this.clearSearch();
        if (this.isMobile) this.sidebarVisible = false;
        if (result.id) this.loadTermin(result.id);
    },

    // ─── Termin-Detail-Modal ───────────────────────────────────────────
    showEventDetail(event) {
        const props = event.extendedProps || {};

        // Raumbelegung oder iCal-Feed: Daten direkt aus dem FullCalendar-Event
        if (props.isRoomBooking || props.isIcalFeed) {
            const fmt = (dt, allDay) => {
                if (!dt) return '';
                const d = dt.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
                return allDay ? d : `${d} ${dt.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}`;
            };
            const sameDay = event.end && event.start && event.start.toDateString() === event.end.toDateString();

            this.selectedEvent = {
                istRaum:      !!props.isRoomBooking,
                titel:        props.isRoomBooking ? (props.buchung || event.title) : (event.title || '(Ohne Titel)'),
                beginn:       fmt(event.start, event.allDay),
                ende:         sameDay && !event.allDay
                    ? event.end.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
                    : fmt(event.end, event.allDay),
                ganztaegig:   event.allDay,
                ort:          props.isRoomBooking ? props.raum : (props.ort || ''),
                beschreibung: props.beschreibung || '',
                klassen:      props.klassen || '',
                lehrer:       props.lehrer || '',
                gebuchtVon:   props.gebuchtVon || '',
                raumUrl:      props.raumUrl || null,
                kalender:     {
                    id:    props.calendarId,
                    name:  props.calendarName || '',
                    farbe: event.backgroundColor || '#6366f1',
                },
                verbund:      [],
                teilnehmer:   [],
                can_edit:     false,
            };
            this.showModal = true;
            return;
        }

        this.loadTermin(props.terminId);
    },

    async loadTermin(terminId) {
        try {
            const resp = await fetch(`/calendar/termin/${terminId}`, { headers: { Accept: 'application/json' } });
            if (!resp.ok) throw new Error(resp.status);
            this.selectedEvent = await resp.json();
            this.showModal = true;
        } catch (e) {
            alert('Termin konnte nicht geladen werden.');
        }
    },

    closeModal() {
        this.showModal = false;
        this.selectedEvent = null;
    },

    statusText(status) {
        return { TENTATIVE: 'Vorläufig', CANCELLED: 'Abgesagt', CONFIRMED: 'Bestätigt' }[status] || status;
    },

    rruleHuman,

    // ─── Formular öffnen ───────────────────────────────────────────────
    openCreateModal(range = null) {
        if (!this.canCreate) return;
        window.dispatchEvent(new CustomEvent('termin-form-open', { detail: { range } }));
    },

    editEvent(termin) {
        this.showModal = false;
        window.dispatchEvent(new CustomEvent('termin-form-open', { detail: { termin } }));
    },

    // ─── Löschen ───────────────────────────────────────────────────────
    deleteEvent(termin, nurDieser = false) {
        const anzahl = (termin.verbund || []).length;
        const frage = nurDieser
            ? `Termin "${termin.titel}" nur aus "${termin.kalender.name}" entfernen?`
            : anzahl > 1
                ? `Termin "${termin.titel}" aus allen ${anzahl} Kalendern löschen?`
                : `Termin "${termin.titel}" wirklich löschen?`;
        const raumHinweis = termin.raum && !nurDieser ? `\n\nDie Buchung von Raum ${termin.raum.name} wird freigegeben.` : '';
        if (!confirm(frage + raumHinweis)) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/calendar/termine/${termin.id}`;

        const felder = { _token: csrfToken(), _method: 'DELETE' };
        if (nurDieser) felder.nur_dieser = '1';
        Object.entries(felder).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    },

    // ─── Hilfsmethoden ─────────────────────────────────────────────────
    currentWeekDate() {
        const d = this.calendar ? this.calendar.getDate() : new Date();
        return toDateInput(d);
    },

    get pdfCalendarsParam() {
        return this.activeCalendars.filter((id) => typeof id === 'number').join(',');
    },

    // ─── iCal-Feed löschen (AJAX DELETE) ──────────────────────────────
    async deleteIcalFeed(deleteUrl, feedName) {
        if (!confirm(`Feed "${feedName}" wirklich entfernen?`)) return;
        try {
            const resp = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (resp.ok) window.location.reload();
            else alert('Fehler beim Entfernen des Feeds.');
        } catch (e) {
            alert('Verbindungsfehler beim Entfernen des Feeds.');
        }
    },
}));

// ─── Terminformular ────────────────────────────────────────────────────────────
Alpine.data('terminForm', () => ({
    offen: false,
    sendet: false,
    terminId: null,
    updatedAt: null,
    fremdeKalender: [],
    originalRrule: '',
    schreibbar: [],

    form: {
        kalender_ids: [],
        titel: '',
        beschreibung: '',
        ort: '',
        beginn: '',
        ende: '',
        ganztaegig: false,
        rrule: '',
        raumBuchen: false,
        room_id: '',
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

    raumVerfuegbarkeit: [],
    raumLaedt: false,
    raumTimer: null,
    canBookRooms: false,

    // Mapping deutsche Wochentage → iCal
    dayMap: { MO: 'MO', DI: 'TU', MI: 'WE', DO: 'TH', FR: 'FR', SA: 'SA', SO: 'SU' },

    rruleHuman,

    init() {
        const root = this.$el.closest('[data-writable-calendars]');
        this.schreibbar = root ? readJson(root, 'writableCalendars', []) : [];
        this.canBookRooms = root?.dataset.canBookRooms === 'true';
    },

    get formAction() {
        return this.terminId ? `/calendar/termine/${this.terminId}` : '/calendar/termine';
    },

    // ─── Öffnen / Schließen ────────────────────────────────────────────
    oeffnen(detail = {}) {
        this.reset();

        if (detail.termin) {
            this.ausTermin(detail.termin);
        } else if (detail.alt) {
            this.ausAltdaten(detail.alt);
        } else {
            this.ausBereich(detail.range);
        }

        this.offen = true;
        this.$nextTick(() => {
            this.$refs.titel?.focus();
            this.raumPruefen();
        });
    },

    schliessen() {
        this.offen = false;
        this.sendet = false;
    },

    reset() {
        this.terminId = null;
        this.updatedAt = null;
        this.fremdeKalender = [];
        this.originalRrule = '';
        this.raumVerfuegbarkeit = [];
        this.form = {
            kalender_ids: this.schreibbar.length === 1 ? [this.schreibbar[0].id] : [],
            titel: '', beschreibung: '', ort: '', beginn: '', ende: '',
            ganztaegig: false, rrule: '', raumBuchen: false, room_id: '',
        };
        this.recurrence = { type: 'none', frequency: 'WEEKLY', interval: 1, byDay: [], endType: 'never', until: '', count: 10 };
    },

    /** Neuer Termin aus Kalenderauswahl (Klick/Ziehen) oder Button */
    ausBereich(range) {
        let start;
        let end;
        let allDay = false;

        if (range?.start) {
            start = new Date(range.start);
            end = range.end ? new Date(range.end) : null;
            allDay = !!range.allDay;
        } else {
            // Nächste volle Stunde
            start = new Date();
            start.setMinutes(0, 0, 0);
            start.setHours(start.getHours() + 1);
        }

        if (allDay) {
            // FullCalendar liefert ein exklusives Ende – im Formular inklusiv anzeigen
            const last = end ? new Date(end.getTime() - 86400000) : start;
            this.form.ganztaegig = true;
            this.form.beginn = toDateInput(start);
            this.form.ende = toDateInput(last < start ? start : last);
        } else {
            if (!end || end <= start) end = new Date(start.getTime() + 60 * 60000);
            this.form.beginn = toDateTimeInput(start);
            this.form.ende = toDateTimeInput(end);
        }
    },

    /** Bearbeiten: Daten aus /calendar/termin/{id} */
    ausTermin(t) {
        this.terminId = t.id;
        this.updatedAt = t.updated_at;

        const schreibbarIds = this.schreibbar.map((k) => k.id);
        const verbund = (t.verbund && t.verbund.length) ? t.verbund : [t.kalender];
        this.form.kalender_ids = verbund.map((k) => k.id).filter((id) => schreibbarIds.includes(id));
        this.fremdeKalender = verbund.filter((k) => !schreibbarIds.includes(k.id));

        this.form.titel = t.titel || '';
        this.form.beschreibung = t.beschreibung || '';
        this.form.ort = t.ort || '';
        this.form.ganztaegig = !!t.ganztaegig;
        this.form.beginn = t.beginn_formular || '';
        this.form.ende = t.ende_formular || '';

        if (t.raum) {
            this.form.raumBuchen = true;
            this.form.room_id = String(t.raum.id);
            // Ort nicht doppelt setzen, wenn er aus der Raumbuchung stammt
            if (this.form.ort === t.raum.name) this.form.ort = '';
        }

        if (t.rrule) {
            this.originalRrule = t.rrule;
            this.recurrence.type = 'keep';
            this.form.rrule = t.rrule;
        }
    },

    /** Nach Validierungsfehler: alte Eingaben */
    ausAltdaten(alt) {
        this.terminId = alt.termin_id ? parseInt(alt.termin_id, 10) : null;
        this.updatedAt = alt.updated_at || null;
        this.form.kalender_ids = alt.kalender_ids || [];
        this.form.titel = alt.titel || '';
        this.form.beschreibung = alt.beschreibung || '';
        this.form.ort = alt.ort || '';
        this.form.ganztaegig = !!alt.ganztaegig;
        this.form.beginn = alt.beginn || '';
        this.form.ende = alt.ende || '';
        this.form.room_id = alt.room_id ? String(alt.room_id) : '';
        this.form.raumBuchen = !!alt.room_id;
        if (alt.rrule) {
            this.originalRrule = alt.rrule;
            this.recurrence.type = 'keep';
            this.form.rrule = alt.rrule;
        }
    },

    // ─── Zeitfelder ────────────────────────────────────────────────────
    ganztaegigUmschalten() {
        if (this.form.ganztaegig) {
            this.form.beginn = (this.form.beginn || '').slice(0, 10);
            this.form.ende = (this.form.ende || this.form.beginn).slice(0, 10);
        } else {
            const b = (this.form.beginn || toDateInput(new Date())).slice(0, 10);
            const e = (this.form.ende || b).slice(0, 10);
            this.form.beginn = `${b}T08:00`;
            this.form.ende = `${e}T09:00`;
        }
        this.raumPruefen();
    },

    /** Beginn verschoben → Ende mit gleicher Dauer mitziehen */
    beginnGeaendert() {
        if (this.form.ende && this.form.ende < this.form.beginn) {
            if (this.form.ganztaegig) {
                this.form.ende = this.form.beginn;
            } else {
                const start = new Date(this.form.beginn);
                this.form.ende = toDateTimeInput(new Date(start.getTime() + 60 * 60000));
            }
        }
        this.raumPruefen();
    },

    // ─── Raumbuchung ───────────────────────────────────────────────────
    get raumMoeglich() {
        return !this.form.ganztaegig
            && this.recurrence.type === 'none'
            && this.form.beginn && this.form.ende
            && this.form.beginn.slice(0, 10) === this.form.ende.slice(0, 10);
    },

    get raumHinweis() {
        if (this.form.ganztaegig) return 'Raumbuchung nur für Termine mit Uhrzeit möglich.';
        if (this.recurrence.type !== 'none') return 'Für wiederkehrende Termine bitte die Raumplanung nutzen.';
        return 'Raumbuchung nur für Termine innerhalb eines Tages möglich.';
    },

    get raumStatus() {
        if (this.raumLaedt) return { text: 'Prüfe Verfügbarkeit…', klasse: 'text-gray-500' };
        if (!this.form.room_id) return { text: 'Belegte Räume sind ausgegraut.', klasse: 'text-gray-500' };
        const r = this.raumVerfuegbarkeit.find((x) => String(x.id) === String(this.form.room_id));
        if (!r) return { text: '', klasse: '' };
        return r.frei
            ? { text: `${r.name} ist frei und wird mit dem Termin gebucht.`, klasse: 'text-teal-700' }
            : { text: `${r.name} ist belegt: ${r.belegt_durch}`, klasse: 'text-red-600' };
    },

    raumName(id) {
        return this.raumVerfuegbarkeit.find((x) => String(x.id) === String(id))?.name || '';
    },

    raumPruefen() {
        if (!this.canBookRooms || !this.form.raumBuchen || !this.raumMoeglich) return;
        clearTimeout(this.raumTimer);
        this.raumTimer = setTimeout(() => this.raumLaden(), 250);
    },

    async raumLaden() {
        if (!this.form.beginn || !this.form.ende || this.form.ende <= this.form.beginn) return;
        this.raumLaedt = true;
        const params = new URLSearchParams({ beginn: this.form.beginn, ende: this.form.ende });
        if (this.terminId) params.set('termin_id', this.terminId);
        try {
            const resp = await fetch(`/calendar/raum-verfuegbarkeit?${params}`, { headers: { Accept: 'application/json' } });
            this.raumVerfuegbarkeit = resp.ok ? await resp.json() : [];
        } catch (e) {
            this.raumVerfuegbarkeit = [];
        } finally {
            this.raumLaedt = false;
        }
    },

    // ─── Wiederholung ──────────────────────────────────────────────────
    toggleDay(day) {
        const idx = this.recurrence.byDay.indexOf(day);
        if (idx > -1) this.recurrence.byDay.splice(idx, 1);
        else this.recurrence.byDay.push(day);
    },

    updateRrule() {
        const type = this.recurrence.type;
        if (type === 'keep') { this.form.rrule = this.originalRrule; return; }
        if (type === 'none') { this.form.rrule = ''; return; }

        const freqByType = { daily: 'DAILY', weekly: 'WEEKLY', monthly: 'MONTHLY', custom: this.recurrence.frequency };
        const freq = freqByType[type] || 'WEEKLY';
        const parts = [`FREQ=${freq}`];

        if (type === 'custom') {
            if (this.recurrence.interval > 1) parts.push(`INTERVAL=${this.recurrence.interval}`);
            if (freq === 'WEEKLY' && this.recurrence.byDay.length > 0) parts.push(`BYDAY=${this.recurrence.byDay.join(',')}`);
            if (this.recurrence.endType === 'until' && this.recurrence.until) {
                parts.push(`UNTIL=${this.recurrence.until.replace(/-/g, '')}T235959Z`);
            } else if (this.recurrence.endType === 'count' && this.recurrence.count > 0) {
                parts.push(`COUNT=${this.recurrence.count}`);
            }
        }

        this.form.rrule = parts.join(';');
    },

    vorAbsenden(e) {
        this.updateRrule();
        if (this.form.kalender_ids.length === 0) {
            e.preventDefault();
            return;
        }
        if (this.form.raumBuchen && this.raumMoeglich && !this.form.room_id) {
            e.preventDefault();
            alert('Bitte einen Raum auswählen oder „Raum buchen" abwählen.');
            return;
        }
        this.sendet = true;
    },
}));

// ─── ICS-Import: Vorschau & Auswahl ────────────────────────────────────────────
Alpine.data('icsImport', () => ({
    eintraege: [],
    kalender: [],
    ziel: [],
    gewaehlt: [],
    hinweise: {},
    hinweisAlle: '',
    offeneHinweise: [],
    offeneBeschreibung: [],
    suche: '',
    vergangeneAusblenden: false,
    max: 150,
    sendet: false,

    rruleHuman,

    init() {
        const el = this.$el;
        this.eintraege = readJson(el, 'eintraege', []);
        this.kalender = readJson(el, 'kalender', []);
        this.max = parseInt(el.dataset.max || '150', 10);

        const alt = readJson(el, 'alt', {});
        this.ziel = alt.kalender_ids?.length
            ? alt.kalender_ids
            : (this.kalender.length === 1 ? [this.kalender[0].id] : []);
        this.hinweisAlle = alt.hinweis_alle || '';
        this.hinweise = Array.isArray(alt.hinweise) ? { ...alt.hinweise } : (alt.hinweise || {});
        this.vergangeneAusblenden = this.eintraege.some((e) => !e.vergangen);

        this.gewaehlt = Array.isArray(alt.auswahl) ? alt.auswahl : this.standardAuswahl();

        // Neue Zielkalender → Termine abwählen, die dort bereits existieren
        this.$watch('ziel', () => {
            const dup = new Set(this.eintraege.filter((e) => this.duplikateImZiel(e).length).map((e) => e.index));
            this.gewaehlt = this.gewaehlt.filter((i) => !dup.has(i));
        });
    },

    /** Vorausgewählt: zukünftige, nicht abgesagte Termine, die im Ziel noch nicht existieren */
    standardAuswahl() {
        return this.eintraege
            .filter((e) => !e.vergangen && !e.abgesagt && this.duplikateImZiel(e).length === 0)
            .slice(0, this.max)
            .map((e) => e.index);
    },

    duplikateImZiel(e) {
        return (e.duplikate || []).filter((d) => this.ziel.includes(d.id));
    },

    get sichtbar() {
        const q = this.suche.trim().toLowerCase();
        return this.eintraege.filter((e) => {
            if (this.vergangeneAusblenden && e.vergangen) return false;
            if (!q) return true;
            return `${e.titel} ${e.ort || ''} ${e.beschreibung || ''}`.toLowerCase().includes(q);
        });
    },

    alleSichtbarenWaehlen(an) {
        const ids = this.sichtbar.map((e) => e.index);
        this.gewaehlt = an
            ? [...new Set([...this.gewaehlt, ...ids])]
            : this.gewaehlt.filter((i) => !ids.includes(i));
    },

    nurNeueWaehlen() {
        this.gewaehlt = this.standardAuswahl();
    },

    umschalten(liste, index) {
        const i = liste.indexOf(index);
        if (i > -1) liste.splice(i, 1);
        else liste.push(index);
    },

    hinweisOeffnen(index) {
        this.umschalten(this.offeneHinweise, index);
        if (this.offeneHinweise.includes(index)) {
            this.$nextTick(() => document.getElementById('hinweis-' + index)?.focus());
        }
    },

    parse(s) {
        // 'YYYY-MM-DD HH:MM:SS' als lokale Zeit
        const [d, t = '00:00:00'] = s.split(' ');
        const [y, m, day] = d.split('-').map(Number);
        const [h, min] = t.split(':').map(Number);
        return new Date(y, m - 1, day, h, min);
    },

    datum(e) {
        const b = this.parse(e.beginn);
        const opt = { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' };
        let text = b.toLocaleDateString('de-DE', opt);
        if (e.ganztaegig) {
            const last = new Date(this.parse(e.ende).getTime() - 86400000);
            if (last > b) text += ' – ' + last.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
        }
        return text;
    },

    zeit(e) {
        if (e.ganztaegig) return 'ganztägig';
        const b = this.parse(e.beginn);
        const en = this.parse(e.ende);
        const fmt = (d) => d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        const sameDay = b.toDateString() === en.toDateString();
        return sameDay
            ? `${fmt(b)} – ${fmt(en)} Uhr`
            : `${fmt(b)} Uhr – ${en.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })} ${fmt(en)} Uhr`;
    },

    vorAbsenden(ev) {
        if (this.gewaehlt.length === 0 || this.ziel.length === 0 || this.gewaehlt.length > this.max) {
            ev.preventDefault();
            return;
        }
        const anzahl = this.gewaehlt.length * this.ziel.length;
        if (!confirm(`${this.gewaehlt.length} Termin(e) in ${this.ziel.length} Kalender importieren (${anzahl} Einträge in OX)?`)) {
            ev.preventDefault();
            return;
        }
        this.sendet = true;
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
