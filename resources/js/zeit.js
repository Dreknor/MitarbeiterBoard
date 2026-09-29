/*
 * Zeitwirtschaft – Vite-Entrypoint (Urlaub, Arbeitszeitnachweis, Dienstplan, Terminal)
 *
 * Alpine-Komponenten:
 *   - urlaubsAntrag     Antragsformular mit Live-Vorschau (Tage, Rest, Team-Überschneidungen)
 *   - teamKalender      Gruppenfilter für den Monatskalender (merkt sich die Auswahl)
 *   - dienstplanEditor  Raster mit Tages-/Wochenansicht, Dialogen, Drag & Drop (Desktop)
 *                       und Listenansicht (Smartphone)
 *   - pinPad            PIN-Eingabe am Zeiterfassungs-Terminal
 *
 * Alpine wird von sidebar.js global bereitgestellt und erst bei window.load gestartet.
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function requestJson(url, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
        },
        credentials: 'same-origin',
    };
    if (data !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }
    const response = await fetch(url, options);
    let body = null;
    try { body = await response.json(); } catch (e) { body = null; }
    if (!response.ok) {
        const message = body?.errors ? Object.values(body.errors).flat().join(' ') : (body?.message || 'Die Aktion ist fehlgeschlagen.');
        const error = new Error(message);
        error.status = response.status;
        throw error;
    }
    return body;
}

const toMin = (t) => {
    if (!t) return null;
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
};
const toTime = (min) => `${String(Math.floor(min / 60)).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`;
const snap = (min, step = 15) => Math.round(min / step) * step;
const storage = {
    get(key, fallback = null) { try { return window.localStorage.getItem(key) ?? fallback; } catch (e) { return fallback; } },
    set(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* privat/gesperrt */ } },
};

function registerComponents(Alpine) {
    // ------------------------------------------------------------------
    // Urlaubsantrag
    // ------------------------------------------------------------------
    Alpine.data('urlaubsAntrag', (cfg) => ({
        employe: cfg.employe ?? '',
        start: cfg.start ?? '',
        end: cfg.end ?? '',
        halberTag: false,
        vorschau: null,
        laedt: false,
        fehler: '',
        timer: null,

        init() {
            this.$watch('start', (wert) => {
                if (wert && (!this.end || this.end < wert)) this.end = wert;
                this.aktualisieren();
            });
            ['end', 'employe', 'halberTag'].forEach((feld) => this.$watch(feld, () => this.aktualisieren()));
        },

        get eintaegig() { return this.start && this.start === this.end; },
        get fuerAlle() { return this.employe === 'all'; },

        aktualisieren() {
            clearTimeout(this.timer);
            if (!this.eintaegig) this.halberTag = false;
            if (!this.start || !this.end || this.fuerAlle || !this.employe) { this.vorschau = null; return; }
            this.timer = setTimeout(() => this.laden(), 250);
        },

        async laden() {
            this.laedt = true;
            this.fehler = '';
            const params = new URLSearchParams({ employe_id: this.employe, start_date: this.start, end_date: this.end, half_day: this.halberTag ? 1 : 0 });
            try {
                this.vorschau = await requestJson(`${cfg.previewUrl}?${params}`);
            } catch (e) {
                this.vorschau = null;
                this.fehler = e.message;
            } finally {
                this.laedt = false;
            }
        },

        zahl(wert) {
            return Number(wert ?? 0).toLocaleString('de-DE', { maximumFractionDigits: 1 });
        },
    }));

    // ------------------------------------------------------------------
    // Teamkalender (Gruppenfilter)
    // ------------------------------------------------------------------
    Alpine.data('teamKalender', () => ({
        gruppe: storage.get('zw.urlaub.gruppe', 'alle'),
        suche: '',
        init() {
            this.$watch('gruppe', (wert) => storage.set('zw.urlaub.gruppe', wert));
        },
        sichtbar(gruppen, name) {
            const passtGruppe = this.gruppe === 'alle' || (gruppen || []).map(String).includes(String(this.gruppe));
            const passtName = !this.suche || name.toLowerCase().includes(this.suche.toLowerCase());
            return passtGruppe && passtName;
        },
    }));

    // ------------------------------------------------------------------
    // Dienstplan-Editor
    // ------------------------------------------------------------------
    Alpine.data('dienstplanEditor', (cfg) => ({
        days: cfg.days,
        employes: cfg.employes,
        fenster: cfg.fenster,
        events: cfg.raster.events,
        zeiten: cfg.raster.zeiten,
        konflikte: cfg.raster.konflikte || {},
        stunden: cfg.raster.stunden || {},
        checks: cfg.raster.checks || {},
        offeneAenderungen: cfg.offeneAenderungen || 0,
        ansicht: storage.get('zw.dienstplan.ansicht', 'tag'),
        tagIndex: 0,
        dialog: null,
        ev: {},
        zt: {},
        busy: false,
        fehler: '',
        toast: null,
        toastTimer: null,
        drag: null,
        auswahl: null,
        pxProMin: 1.25,

        init() {
            const hash = window.location.hash.replace('#tag-', '');
            const heute = new Date().toISOString().slice(0, 10);
            let index = this.days.findIndex((d) => d.date === hash);
            if (index < 0) index = this.days.findIndex((d) => d.date === heute);
            this.tagIndex = index < 0 ? 0 : index;
            this.$watch('ansicht', (wert) => storage.set('zw.dienstplan.ansicht', wert));
            this.$watch('tagIndex', () => history.replaceState(null, '', `#tag-${this.tag.date}`));
            if (cfg.meldung) this.zeigeToast(cfg.meldung.type, cfg.meldung.text);
        },

        // ---- Ableitungen ----
        get tag() { return this.days[this.tagIndex]; },
        get fensterStart() { return toMin(this.fenster.start); },
        get fensterEnde() { return toMin(this.fenster.end); },
        get hoehe() { return (this.fensterEnde - this.fensterStart) * this.pxProMin; },
        get stundenLinien() {
            const linien = [];
            for (let m = Math.ceil(this.fensterStart / 60) * 60; m <= this.fensterEnde; m += 60) linien.push(m);
            return linien;
        },

        eventsFuer(empId, date) {
            return this.events
                .map((e) => (this.drag && this.drag.moved && this.drag.id === e.id ? { ...e, employe_id: this.drag.empId, date: this.drag.date, start: toTime(this.drag.startMin), end: toTime(this.drag.startMin + this.drag.dauer) } : e))
                .filter((e) => e.employe_id === empId && e.date === date)
                .sort((a, b) => a.start.localeCompare(b.start));
        },
        merkliste(date) { return this.events.filter((e) => e.employe_id === null && e.date === date && !(this.drag?.moved && this.drag.id === e.id)); },
        zeitFuer(empId, date) { return this.zeiten.find((z) => z.employe_id === empId && z.date === date) || null; },
        konflikteFuer(empId, date) { return (this.konflikte[empId] || {})[date] || []; },
        hatKonflikteAm(date) { return this.employes.some((e) => this.konflikteFuer(e.id, date).length > 0); },
        checksAm(date) { return Object.entries(this.checks[date] || {}); },
        stundenVon(empId) { return this.stunden[empId] || { geplant: 0, vertrag: 0 }; },
        zeitLabel(z) { return z && z.start ? `${z.start}–${z.end}` : 'frei'; },
        anzahlAm(empId, date) { return this.eventsFuer(empId, date).filter((e) => !e.abwesend).length; },
        abwesenheitAm(empId, date) { return this.eventsFuer(empId, date).find((e) => e.abwesend) || null; },

        stil(e) {
            const start = Math.max(toMin(e.start), this.fensterStart);
            const ende = Math.min(toMin(e.end), this.fensterEnde);
            return `top:${(start - this.fensterStart) * this.pxProMin}px;height:${Math.max(14, (ende - start) * this.pxProMin)}px`;
        },
        auswahlStil() {
            if (!this.auswahl) return '';
            const von = Math.min(this.auswahl.von, this.auswahl.bis);
            const bis = Math.max(this.auswahl.von, this.auswahl.bis);
            return `top:${(von - this.fensterStart) * this.pxProMin}px;height:${Math.max(4, (bis - von) * this.pxProMin)}px`;
        },
        linieStil(min) { return `top:${(min - this.fensterStart) * this.pxProMin}px`; },

        // ---- Zeiger-Interaktion (Desktop-Raster) ----
        minuteAus(timeline, clientY) {
            const rect = timeline.getBoundingClientRect();
            const min = snap(this.fensterStart + (clientY - rect.top) / this.pxProMin);
            return Math.min(Math.max(min, this.fensterStart), this.fensterEnde);
        },

        auswahlStart(event, empId, date) {
            if (event.button !== 0 || event.target !== event.currentTarget) return;
            const min = this.minuteAus(event.currentTarget, event.clientY);
            this.auswahl = { empId, date, von: min, bis: min + 15, timeline: event.currentTarget };
            event.currentTarget.setPointerCapture?.(event.pointerId);
        },
        auswahlBewegen(event) {
            if (!this.auswahl) return;
            this.auswahl.bis = this.minuteAus(this.auswahl.timeline, event.clientY);
        },
        auswahlEnde() {
            if (!this.auswahl) return;
            const von = Math.min(this.auswahl.von, this.auswahl.bis);
            let bis = Math.max(this.auswahl.von, this.auswahl.bis);
            if (bis - von < 15) bis = von + 30;
            const { empId, date } = this.auswahl;
            this.auswahl = null;
            this.neuerTermin(empId, date, toTime(von), toTime(Math.min(bis, this.fensterEnde)));
        },

        ziehenStart(event, e) {
            if (event.button !== 0 || e.abwesend) return;
            event.stopPropagation();
            const rect = event.currentTarget.getBoundingClientRect();
            this.drag = {
                id: e.id, ev: e, moved: false,
                startX: event.clientX, startY: event.clientY,
                offsetMin: e.employe_id ? (event.clientY - rect.top) / this.pxProMin : 0,
                empId: e.employe_id, date: e.date, startMin: toMin(e.start),
                dauer: toMin(e.end) - toMin(e.start),
            };
            const bewegen = (ev) => this.ziehenBewegen(ev);
            const ende = (ev) => {
                window.removeEventListener('pointermove', bewegen);
                window.removeEventListener('pointerup', ende);
                this.ziehenEnde(ev);
            };
            window.addEventListener('pointermove', bewegen);
            window.addEventListener('pointerup', ende);
        },
        ziehenBewegen(event) {
            const d = this.drag;
            if (!d) return;
            if (!d.moved && Math.abs(event.clientX - d.startX) + Math.abs(event.clientY - d.startY) < 5) return;
            d.moved = true;
            const unter = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-timeline]');
            if (!unter) return;
            d.empId = Number(unter.dataset.employe);
            d.date = unter.dataset.date;
            const rect = unter.getBoundingClientRect();
            let min = snap(this.fensterStart + (event.clientY - rect.top) / this.pxProMin - d.offsetMin);
            min = Math.max(this.fensterStart, Math.min(min, this.fensterEnde - Math.min(d.dauer, this.fensterEnde - this.fensterStart)));
            d.startMin = min;
        },
        async ziehenEnde() {
            const d = this.drag;
            if (!d) return;
            if (!d.moved) { this.drag = null; this.bearbeiten(d.ev); return; }
            try {
                const antwort = await requestJson(cfg.urls.drop, 'PATCH', {
                    task: `task_${d.id}`, employe_id: d.empId, date: d.date,
                    start: toTime(d.startMin), end: toTime(d.startMin + d.dauer),
                });
                if (antwort.conflict) this.zeigeToast('warning', 'Überschneidung – der Termin wurde nicht verschoben.');
            } catch (e) {
                this.zeigeToast('danger', e.message);
            } finally {
                this.drag = null;
                await this.neuLaden();
            }
        },

        // ---- Dialoge ----
        neuerTermin(empId = null, date = null, start = null, end = null) {
            this.fehler = '';
            this.ev = {
                id: null, event: '', date: date || this.tag.date,
                start: start || this.fenster.start,
                end: end || toTime(Math.min(toMin(start || this.fenster.start) + 60, this.fensterEnde)),
                employes: empId ? [empId] : [],
            };
            this.dialog = 'event';
            this.$nextTick(() => this.$refs.eventName?.focus());
        },
        bearbeiten(e) {
            if (e.abwesend) return;
            this.fehler = '';
            this.ev = { id: e.id, event: e.event, date: e.date, start: e.start, end: e.end, employes: e.employe_id ? [e.employe_id] : [] };
            this.dialog = 'event';
        },
        zeitBearbeiten(empId, date) {
            const z = this.zeitFuer(empId, date);
            const emp = this.employes.find((e) => e.id === empId);
            this.fehler = '';
            this.zt = { employe_id: empId, name: emp?.name ?? '', date, start: z?.start ?? '', end: z?.end ?? '', function: z?.function ?? '' };
            this.dialog = 'zeit';
        },
        schliessen() { this.dialog = null; this.fehler = ''; },

        async ausfuehren(aktion, erfolg) {
            this.busy = true;
            this.fehler = '';
            try {
                const antwort = await aktion();
                this.dialog = null;
                await this.neuLaden();
                this.zeigeToast(antwort?.type || 'success', antwort?.message || erfolg);
            } catch (e) {
                this.fehler = e.message;
            } finally {
                this.busy = false;
            }
        },

        speichernTermin() {
            const daten = { event: this.ev.event, date: this.ev.date, start: this.ev.start, end: this.ev.end, employes: this.ev.employes };
            return this.ausfuehren(() => (this.ev.id
                ? requestJson(`${cfg.urls.events}/${this.ev.id}`, 'PUT', daten)
                : requestJson(cfg.urls.eventStore, 'POST', daten)), 'Termin gespeichert.');
        },
        loeschenTermin() {
            if (!this.ev.id || !window.confirm('Termin wirklich löschen?')) return;
            return this.ausfuehren(() => requestJson(`${cfg.urls.events}/${this.ev.id}`, 'DELETE'), 'Termin gelöscht.');
        },
        merken() {
            return this.ausfuehren(() => requestJson(`${cfg.urls.events}/${this.ev.id}/remember`, 'POST'), 'Termin liegt in der Merkliste.');
        },
        speichernZeit(frei = false) {
            const daten = {
                roster_id: cfg.rosterId, employe_id: this.zt.employe_id, date: this.zt.date,
                start: frei ? null : (this.zt.start || null), end: frei ? null : (this.zt.end || null),
                function: frei ? null : (this.zt.function || null),
            };
            return this.ausfuehren(() => requestJson(cfg.urls.workingTime, 'POST', daten), 'Arbeitszeit gespeichert.');
        },
        tagLeeren() {
            if (!window.confirm(`Alle Dienste und Termine am ${this.tag.label} entfernen?`)) return;
            return this.ausfuehren(() => requestJson(cfg.urls.trashDay, 'DELETE', { roster_id: cfg.rosterId, date: this.tag.date }), 'Tag geleert.');
        },
        toggleEmploye(id) {
            const i = this.ev.employes.indexOf(id);
            if (i >= 0) this.ev.employes.splice(i, 1); else this.ev.employes.push(id);
        },

        async neuLaden() {
            try {
                const daten = await requestJson(cfg.urls.data);
                this.events = daten.events;
                this.zeiten = daten.zeiten;
                this.konflikte = daten.konflikte || {};
                this.stunden = daten.stunden || {};
                this.checks = daten.checks || {};
                this.offeneAenderungen = daten.offeneAenderungen || 0;
            } catch (e) {
                this.zeigeToast('danger', 'Aktualisieren fehlgeschlagen – bitte Seite neu laden.');
            }
        },

        zeigeToast(type, text) {
            clearTimeout(this.toastTimer);
            this.toast = { type, text };
            this.toastTimer = setTimeout(() => { this.toast = null; }, 4500);
        },
    }));

    // ------------------------------------------------------------------
    // PIN-Pad (Terminal)
    // ------------------------------------------------------------------
    Alpine.data('pinPad', (cfg = {}) => ({
        pin: '',
        max: cfg.max ?? 10,
        taste(ziffer) { if (this.pin.length < this.max) this.pin += String(ziffer); },
        loeschen() { this.pin = this.pin.slice(0, -1); },
        leeren() { this.pin = ''; },
        tastatur(event) {
            if (/^\d$/.test(event.key)) this.taste(event.key);
            else if (event.key === 'Backspace') this.loeschen();
            else if (event.key === 'Enter' && this.pin.length >= 6) this.$refs.form?.requestSubmit();
        },
    }));
}

if (window.Alpine) {
    registerComponents(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerComponents(window.Alpine));
}

// Doppeltes Absenden verhindern und Bestätigungen für destruktive Aktionen
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.closest('.zeit-wrapper')) return;

    const frage = form.dataset.confirm;
    if (frage && !window.confirm(frage)) {
        event.preventDefault();
        return;
    }
    if (form.dataset.submitting) {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    setTimeout(() => form.querySelectorAll('button[type=submit]').forEach((b) => { b.disabled = true; }), 0);
}, true);

window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('.zeit-wrapper form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('button[type=submit]').forEach((b) => { b.disabled = false; });
    });
});
