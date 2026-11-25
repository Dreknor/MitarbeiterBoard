/*
 * Ergebnisboards – Vite-Entrypoint
 *
 * - Alpine-Komponente "ergebnisboard": Board-Seite (Darstellung, Bearbeitungsmodus, Karten-Dialog,
 *   Felder-Dialog, Zugriffe, Drag & Drop mit SortableJS, PNG-Export mit html2canvas-pro).
 * - Alpine-Komponente "ergZugriffPicker": Personen/Gruppen/Rollen mit Recht je Eintrag
 *   (auf der Board-Seite im Dialog, im Anlegen-Formular mit hidden inputs).
 * - Alpine-Komponente "ergVorlagenEditor": Vorlagen-Editor mit Live-Vorschau des Rasters.
 *
 * Jede schreibende Anfrage sendet `version`; die Antwort `data` ersetzt den Zustand komplett
 * (409 → aktueller Stand aus `data` + Hinweis, 422 → Fehlermeldung im Dialog).
 * Alpine wird von sidebar.js global bereitgestellt und erst bei window.load gestartet.
 */
import Sortable from 'sortablejs';

const STATUS_LABEL = { offen: 'Offen', in_arbeit: 'In Arbeit', erledigt: 'Erledigt' };
const BEARBEITEN_SCHLUESSEL = 'ergebnisboards.bearbeiten';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const kopie = (wert) => JSON.parse(JSON.stringify(wert ?? null));
const norm = (s) => (s || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

function groesse(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
}

function datumDe(iso) {
    if (!iso) return '';
    const [j, m, t] = iso.split('-');
    return `${t}.${m}.${j}`;
}

function slug(text) {
    return norm(text).replace(/ß/g, 'ss').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60) || 'board';
}

function speicherLesen(schluessel) {
    try { return window.localStorage.getItem(schluessel); } catch (e) { return null; }
}
function speicherSchreiben(schluessel, wert) {
    try { window.localStorage.setItem(schluessel, wert); } catch (e) { /* egal */ }
}

function zeitDe(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const heute = new Date();
    const gestern = new Date(heute.getFullYear(), heute.getMonth(), heute.getDate() - 1);
    const uhr = d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    if (d.toDateString() === heute.toDateString()) return `heute, ${uhr}`;
    if (d.toDateString() === gestern.toDateString()) return `gestern, ${uhr}`;
    return `${d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })}, ${uhr}`;
}

/** Zustand eines Kommentarbereichs (Karte oder Board). */
function leereKommentare() {
    return { liste: [], geladen: false, laedt: false, text: '', fehler: '', bearbeiteId: null, bearbeiteText: '' };
}

/** Zustand eines Verlaufs (Karte oder Board). */
function leererVerlauf() {
    return { eintraege: [], geladen: false, laedt: false, mehr: false, filter: 'alle', darfWiederherstellen: false };
}

function leereKarte(bereich) {
    return {
        id: null, bereich, titel: '', zusatz: '', etikett: '', fokus: false, breit: false,
        faellig_am: '', zustaendige: [], zustaendigeNamen: [], anhaenge: [], aufgabe: null, ueberfaellig: false,
        kommentare: 0,
    };
}

class AnfrageFehler extends Error {
    constructor(meldung, art, fehler = {}) {
        super(meldung);
        this.art = art;
        this.fehler = fehler;
    }
}

function registerComponents(Alpine) {
    // ------------------------------------------------------------------
    // Board-Seite
    // ------------------------------------------------------------------
    Alpine.data('ergebnisboard', (konfig) => ({
        board: konfig.daten,
        urls: konfig.urls,
        personen: konfig.personen || [],
        farben: konfig.farben || {},
        userId: konfig.userId ? Number(konfig.userId) : null,
        maxDateiKb: konfig.maxDateiKb || 20480,
        statusLabel: STATUS_LABEL,

        bearbeiten: false,
        nurMeine: false,
        laedt: false,
        toasts: [],
        dialog: null,          // karte | felder | zugriff | einstellungen | export | seitenleiste
        letzterFokus: null,

        // Karten-Dialog
        kartenReiter: 'details',   // details | kommentare | verlauf
        form: leereKarte(null),
        formFehler: '',
        personSuche: '',
        aufgabeDatum: '',
        hochladen: false,

        // Felder-Dialog
        felder: [],
        felderFehler: '',

        // Zugriff-Dialog
        zugriffEintraege: [],
        meetingLesen: false,
        zugriffFehler: '',

        // Einstellungen
        verknuepfung: { meeting_id: '', theme_id: '' },
        einstellungenFehler: '',

        // Export
        exportUrl: null,
        exportBlob: null,
        exportBreite: 0,

        // Kommentare & Verlauf (je Karte im Dialog und fürs Board in der Seitenleiste)
        seitenReiter: 'kommentare',    // kommentare | verlauf
        kom: { karte: leereKommentare(), board: leereKommentare() },
        verl: { karte: leererVerlauf(), board: leererVerlauf() },
        // @-Erwähnung: offene Vorschlagsliste
        erw: { offen: false, ziel: null, feld: null, start: 0, suche: '', markiert: 0 },

        sortables: [],

        init() {
            this.bearbeiten = this.board.darf_schreiben && speicherLesen(BEARBEITEN_SCHLUESSEL) === '1';
            this.meetingLesen = !!this.board.meeting_lesen;
            this.$watch('bearbeiten', (an) => {
                speicherSchreiben(BEARBEITEN_SCHLUESSEL, an ? '1' : '0');
                this.sortablesAktualisieren();
            });
            this.$watch('nurMeine', () => this.sortablesAktualisieren());
            this.$nextTick(() => this.ankerOeffnen());
            window.addEventListener('hashchange', () => this.ankerOeffnen());
        },

        /** Links aus Benachrichtigungen: #karte-12, #karte-12-kommentare, #kommentare, #verlauf. */
        ankerOeffnen() {
            const hash = window.location.hash || '';
            const m = hash.match(/^#karte-(\d+)(?:-(kommentare|verlauf))?$/);
            if (m) {
                const k = this.board.karten.find((x) => x.id === Number(m[1]));
                if (k) {
                    this.karteOeffnen(k, m[2] || 'details');
                } else {
                    this.toast('Diese Karte gibt es nicht mehr.', 'warnung');
                }
            } else if (hash === '#kommentare' || hash === '#verlauf') {
                this.seitenleisteOeffnen(hash.slice(1));
            }
        },

        // ---------------- abgeleitete Daten ----------------

        get spaltenAnzahl() {
            return (this.board.spalten || '1fr').trim().split(/\s+/).length;
        },
        get hauptbereiche() {
            return (this.board.bereiche || []).filter((b) => !b.eltern);
        },
        get maxZeile() {
            return Math.max(1, ...this.hauptbereiche.map((b) => b.zeile + b.zeilen - 1));
        },
        /** Feld ganz rechts über die volle Höhe (wie „Klärung & Schulleitung“) nimmt die Legende auf. */
        get seitenfeld() {
            if (this.board.bereiche_frei || this.spaltenAnzahl < 2) return null;
            return this.hauptbereiche.find((b) => b.spalte === this.spaltenAnzahl && b.spalten === 1
                && b.zeile === 1 && b.zeilen >= this.maxZeile) || null;
        },
        get boardMinBreite() {
            if (this.board.bereiche_frei) return Math.max(0, this.spaltenAnzahl * 230);
            return Math.max(720, this.spaltenAnzahl * 320 + (this.board.achsen ? 60 : 0));
        },
        get hatFokus() {
            return this.board.karten.some((k) => k.fokus);
        },
        get schreibbar() {
            return this.board.darf_schreiben && this.bearbeiten;
        },
        get ziehbar() {
            return this.schreibbar && !this.nurMeine;
        },
        get legendeImSeitenfeld() {
            return !!(this.board.legende && this.seitenfeld);
        },
        get achseXSpalten() {
            return this.seitenfeld ? `1 / ${this.spaltenAnzahl}` : '1 / -1';
        },
        get meineAnzahl() {
            return this.board.karten.filter((k) => this.istMeine(k)).length;
        },

        unterbereiche(schluessel) {
            return (this.board.bereiche || []).filter((b) => b.eltern === schluessel);
        },
        bereich(schluessel) {
            return (this.board.bereiche || []).find((b) => b.schluessel === schluessel) || null;
        },
        bereichTitel(schluessel) {
            const b = this.bereich(schluessel);
            if (!b) return schluessel;
            const eltern = b.eltern ? this.bereich(b.eltern) : null;
            return eltern ? `${eltern.titel} › ${b.titel}` : b.titel;
        },
        karten(schluessel) {
            return this.board.karten
                .filter((k) => k.bereich === schluessel && (!this.nurMeine || this.istMeine(k)))
                .sort((a, b) => (a.sort - b.sort) || (a.id - b.id));
        },
        anzahl(schluessel) {
            return this.board.karten.filter((k) => k.bereich === schluessel).length;
        },
        istMeine(k) {
            return this.userId !== null && (k.zustaendige || []).some((z) => Number(z.id) === this.userId);
        },
        feldStil(b) {
            if (this.board.bereiche_frei) return `grid-column: ${b.spalte} / span 1; grid-row: 1 / span 1`;
            return `grid-column: ${b.spalte} / span ${b.spalten}; grid-row: ${b.zeile} / span ${b.zeilen}`;
        },
        legendeTeile(text) {
            const i = (text || '').indexOf(':');
            if (i < 1 || i > 30) return ['', text || ''];
            return [text.slice(0, i + 1), text.slice(i + 1)];
        },
        datum: datumDe,
        zeit: zeitDe,
        groesse,

        // ---------------- Anfragen ----------------

        url(name, ersatz = {}) {
            let u = this.urls[name];
            Object.entries(ersatz).forEach(([k, v]) => { u = u.replace(`__${k}__`, v); });
            return u;
        },

        async anfrage(methode, url, daten = null) {
            const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' };
            const optionen = { method: methode, headers, credentials: 'same-origin' };
            if (daten instanceof FormData) {
                optionen.body = daten;
            } else if (daten !== null) {
                headers['Content-Type'] = 'application/json';
                optionen.body = JSON.stringify(daten);
            }

            this.laedt = true;
            let antwort;
            try {
                antwort = await fetch(url, optionen);
            } catch (e) {
                this.laedt = false;
                this.toast('Keine Verbindung zum Server.', 'fehler');
                throw new AnfrageFehler('Keine Verbindung', 'netz');
            }
            this.laedt = false;

            let json = {};
            try { json = await antwort.json(); } catch (e) { json = {}; }

            if (antwort.status === 409) {
                if (json.data) this.uebernehmen(json.data);
                this.toast(json.message || 'Das Board wurde zwischenzeitlich geändert.', 'warnung', 6000);
                throw new AnfrageFehler(json.message || 'Konflikt', 'konflikt');
            }
            if (antwort.status === 422) {
                const fehler = json.errors || {};
                const erste = Object.values(fehler).flat()[0] || json.message || 'Bitte Eingaben prüfen.';
                throw new AnfrageFehler(erste, 'validierung', fehler);
            }
            if (antwort.status === 419) {
                this.toast('Sitzung abgelaufen – bitte Seite neu laden.', 'fehler', 8000);
                throw new AnfrageFehler('Sitzung abgelaufen', 'http');
            }
            if (!antwort.ok) {
                const meldung = antwort.status === 403 ? 'Dafür fehlt Ihnen die Berechtigung.' : (json.message || `Fehler (${antwort.status})`);
                this.toast(meldung, 'fehler');
                throw new AnfrageFehler(meldung, 'http');
            }

            if (json.data) this.uebernehmen(json.data);
            if (json.message) this.toast(json.message, 'ok');
            return json;
        },

        uebernehmen(daten) {
            const warSchreibbar = this.board.darf_schreiben;
            this.board = daten;
            this.meetingLesen = !!daten.meeting_lesen;
            if (warSchreibbar && !daten.darf_schreiben) this.bearbeiten = false;
            // Offenen Karten-Dialog mit dem neuen Stand der Karte abgleichen (Anhänge, Aufgabe)
            if (this.dialog === 'karte' && this.form.id) {
                const k = daten.karten.find((x) => x.id === this.form.id);
                if (!k) {
                    this.dialogSchliessen();
                } else {
                    this.form.anhaenge = k.anhaenge;
                    this.form.aufgabe = k.aufgabe;
                    this.form.kommentare = k.kommentare || 0;
                }
            }
            this.$nextTick(() => this.sortablesAktualisieren());
        },

        toast(text, art = 'ok', dauer = 3500) {
            if (!text) return null;
            const id = Date.now() + Math.random();
            this.toasts.push({ id, text, art });
            if (dauer > 0) setTimeout(() => this.toastWeg(id), dauer);
            return id;
        },
        toastWeg(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },

        // ---------------- Dialoge allgemein ----------------

        dialogOeffnen(name) {
            this.letzterFokus = document.activeElement;
            this.dialog = name;
            this.$nextTick(() => {
                const el = this.$root.querySelector(`[data-dialog="${name}"]`);
                const ziel = el?.querySelector('[data-autofocus]') || el?.querySelector('input:not([type=hidden]):not([disabled]), select, textarea, button');
                ziel?.focus();
            });
        },
        dialogSchliessen() {
            this.erwaehnungSchliessen();
            if (window.location.hash && /^#(karte-\d+|kommentare|verlauf)/.test(window.location.hash)) {
                history.replaceState(null, '', window.location.pathname + window.location.search);
            }
            this.dialog = null;
            this.formFehler = '';
            this.felderFehler = '';
            this.zugriffFehler = '';
            this.einstellungenFehler = '';
            const zurueck = this.letzterFokus;
            this.$nextTick(() => zurueck?.focus?.());
        },
        /** Tab innerhalb des offenen Dialogs halten. */
        fokusFalle(e) {
            const el = this.$root.querySelector(`[data-dialog="${this.dialog}"]`);
            if (!el) return;
            const ziele = [...el.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
                .filter((x) => x.offsetParent !== null);
            if (!ziele.length) return;
            const erstes = ziele[0];
            const letztes = ziele[ziele.length - 1];
            if (e.shiftKey && document.activeElement === erstes) { e.preventDefault(); letztes.focus(); }
            else if (!e.shiftKey && document.activeElement === letztes) { e.preventDefault(); erstes.focus(); }
        },

        // ---------------- Kopf, Achsen, Legende ----------------

        async kopfSpeichern(feld, el) {
            if (!this.schreibbar) return;
            const wert = el.innerText.replace(/\s+/g, ' ').trim();
            if (wert === (this.board[feld] || '')) return;
            if (feld === 'titel' && wert === '') {
                el.innerText = this.board.titel;
                this.toast('Der Titel darf nicht leer sein.', 'warnung');
                return;
            }
            try {
                await this.anfrage('PUT', this.urls.update, { [feld]: wert || null, version: this.board.version });
            } catch (e) {
                el.innerText = this.board[feld] || '';
                if (e.art === 'validierung') this.toast(e.message, 'fehler');
            }
        },
        async achseSpeichern(achse, schluessel, el) {
            if (!this.schreibbar || !this.board.achsen) return;
            const wert = el.innerText.replace(/\s+/g, ' ').trim();
            if (wert === (this.board.achsen[achse]?.[schluessel] || '')) return;
            const achsen = kopie(this.board.achsen);
            achsen[achse][schluessel] = wert;
            try {
                await this.anfrage('PUT', this.urls.update, { achsen, version: this.board.version });
            } catch (e) {
                el.innerText = this.board.achsen[achse]?.[schluessel] || '';
                if (e.art === 'validierung') this.toast(e.message, 'fehler');
            }
        },
        async legendeSpeichern(index, el) {
            if (!this.schreibbar || !this.board.legende) return;
            const wert = el.innerText.replace(/\s+/g, ' ').trim();
            const legende = kopie(this.board.legende);
            if (index === null) {
                if (wert === (legende.titel || '')) return;
                legende.titel = wert;
            } else {
                if (wert === (legende.eintraege[index]?.text || '')) return;
                legende.eintraege[index].text = wert;
            }
            try {
                await this.anfrage('PUT', this.urls.update, { legende, version: this.board.version });
            } catch (e) {
                el.innerText = index === null ? (this.board.legende.titel || '') : (this.board.legende.eintraege[index]?.text || '');
                if (e.art === 'validierung') this.toast(e.message, 'fehler');
            }
        },
        enterBlur(e) {
            e.preventDefault();
            e.target.blur();
        },
        async farbschemaSetzen(schema) {
            if (schema === this.board.farbschema) return;
            if (!this.board.darf_schreiben) {
                this.board.farbschema = schema; // nur Ansicht
                return;
            }
            try {
                await this.anfrage('PUT', this.urls.update, { farbschema: schema, version: this.board.version });
            } catch (e) { /* Hinweis kam bereits */ }
        },

        // ---------------- Karten ----------------

        karteOeffnen(k, reiter = 'details') {
            this.form = {
                id: k.id,
                bereich: k.bereich,
                titel: k.titel,
                zusatz: k.zusatz || '',
                etikett: k.etikett || '',
                fokus: !!k.fokus,
                breit: !!k.breit,
                faellig_am: k.faellig_am || '',
                zustaendige: (k.zustaendige || []).map((z) => Number(z.id)),
                zustaendigeNamen: k.zustaendige || [],
                anhaenge: k.anhaenge || [],
                aufgabe: k.aufgabe,
                ueberfaellig: k.ueberfaellig,
                kommentare: k.kommentare || 0,
            };
            this.formFehler = '';
            this.personSuche = '';
            this.aufgabeDatum = '';
            this.kom.karte = leereKommentare();
            this.verl.karte = leererVerlauf();
            this.dialogOeffnen('karte');
            this.reiterWaehlen(reiter);
        },
        /** Reiter im Karten-Dialog; Kommentare/Verlauf werden beim ersten Öffnen geladen. */
        reiterWaehlen(reiter) {
            this.kartenReiter = this.form.id ? reiter : 'details';
            this.erwaehnungSchliessen();
            if (this.kartenReiter === 'kommentare' && !this.kom.karte.geladen) this.kommentareLaden('karte');
            if (this.kartenReiter === 'verlauf' && !this.verl.karte.geladen) this.verlaufLaden('karte');
            if (this.kartenReiter === 'kommentare') {
                this.$nextTick(() => this.$root.querySelector('[data-dialog="karte"] [data-kommentar-eingabe]')?.focus());
            }
        },
        neueKarte(bereich) {
            if (!this.schreibbar) return;
            this.kartenReiter = 'details';
            this.form = leereKarte(bereich);
            this.formFehler = '';
            this.personSuche = '';
            this.dialogOeffnen('karte');
        },
        get formRaster2() {
            return (this.bereich(this.form.bereich)?.raster || 1) === 2;
        },
        get bereichOptionen() {
            const liste = [];
            this.hauptbereiche.forEach((b) => {
                liste.push({ wert: b.schluessel, text: b.titel });
                this.unterbereiche(b.schluessel).forEach((u) => liste.push({ wert: u.schluessel, text: '— ' + u.titel }));
            });
            return liste;
        },
        get personenGefiltert() {
            const q = norm(this.personSuche);
            return this.personen.filter((p) => q === '' || norm(p.name).includes(q) || this.form.zustaendige?.includes(p.id));
        },
        personName(id) {
            return this.personen.find((p) => p.id === id)?.name
                || this.form.zustaendigeNamen?.find((z) => Number(z.id) === id)?.name || 'Unbekannt';
        },
        async karteSpeichern() {
            this.formFehler = '';
            if (!this.form.titel.trim()) {
                this.formFehler = 'Bitte einen Titel eingeben.';
                return;
            }
            const daten = {
                bereich: this.form.bereich,
                titel: this.form.titel.trim(),
                zusatz: this.form.zusatz.trim() || null,
                etikett: this.form.etikett.trim() || null,
                fokus: this.form.fokus,
                breit: this.formRaster2 ? this.form.breit : false,
                faellig_am: this.form.faellig_am || null,
                zustaendige: this.form.zustaendige,
                version: this.board.version,
            };
            try {
                if (this.form.id) {
                    await this.anfrage('PUT', this.url('kartenUpdate', { KARTE: this.form.id }), daten);
                    this.toast('Karte gespeichert.');
                    this.dialogSchliessen();
                } else {
                    const vorher = new Set(this.board.karten.map((k) => k.id));
                    await this.anfrage('POST', this.urls.kartenStore, daten);
                    this.toast('Karte angelegt.');
                    const neu = this.board.karten.find((k) => !vorher.has(k.id));
                    this.dialogSchliessen();
                    if (neu) this.$nextTick(() => this.$root.querySelector(`[data-karte-id="${neu.id}"]`)?.focus());
                }
            } catch (e) {
                if (e.art === 'validierung') this.formFehler = e.message;
                else if (e.art === 'konflikt') this.formFehler = 'Das Board wurde inzwischen geändert – bitte prüfen und erneut speichern.';
            }
        },
        async karteLoeschen() {
            if (!this.form.id) return;
            const anhang = this.form.anhaenge.length ? ` und ${this.form.anhaenge.length} Anhang/Anhänge` : '';
            if (!window.confirm(`Karte „${this.form.titel}“${anhang} wirklich löschen?`)) return;
            try {
                await this.anfrage('DELETE', this.url('kartenDestroy', { KARTE: this.form.id }), { version: this.board.version });
                this.toast('Karte gelöscht.');
                this.dialogSchliessen();
            } catch (e) {
                if (e.art === 'validierung') this.formFehler = e.message;
            }
        },
        async dateienHochladen(event) {
            const dateien = [...(event.target.files || [])];
            event.target.value = '';
            if (!dateien.length || !this.form.id) return;
            const zuGross = dateien.find((d) => d.size > this.maxDateiKb * 1024);
            if (zuGross) {
                this.formFehler = `„${zuGross.name}“ ist größer als ${Math.round(this.maxDateiKb / 1024)} MB.`;
                return;
            }
            const fd = new FormData();
            dateien.forEach((d) => fd.append('dateien[]', d));
            this.hochladen = true;
            this.formFehler = '';
            try {
                await this.anfrage('POST', this.url('dateienStore', { KARTE: this.form.id }), fd);
            } catch (e) {
                if (e.art === 'validierung') this.formFehler = e.message;
            } finally {
                this.hochladen = false;
            }
        },
        async dateiLoeschen(anhang) {
            if (!window.confirm(`Datei „${anhang.name}“ entfernen?`)) return;
            try {
                await this.anfrage('DELETE', this.url('dateienDestroy', { KARTE: this.form.id, MEDIA: anhang.id }));
            } catch (e) {
                if (e.art === 'validierung') this.formFehler = e.message;
            }
        },
        async aufgabeAnlegen() {
            if (!this.form.id) return;
            try {
                await this.anfrage('POST', this.url('aufgabe', { KARTE: this.form.id }), { datum: this.aufgabeDatum || null });
            } catch (e) {
                if (e.art === 'validierung') this.formFehler = e.message;
            }
        },
        async aufgabeErledigen(karteId) {
            try {
                await this.anfrage('POST', this.url('aufgabeErledigen', { KARTE: karteId }), {});
            } catch (e) {
                if (e.art === 'validierung') this.toast(e.message, 'fehler');
            }
        },

        // ---------------- Kommentare ----------------

        /** ziel = karte (offene Karte im Dialog) | board */
        kommentarUrl(ziel) {
            const basis = this.urls.kommentare;
            return ziel === 'karte' && this.form.id ? `${basis}?karte=${this.form.id}` : basis;
        },
        kommentareUebernehmen(ziel, json) {
            this.kom[ziel].liste = json.kommentare || [];
            this.kom[ziel].geladen = true;
            if (ziel === 'karte') {
                const k = this.board.karten.find((x) => x.id === json.karte);
                if (k) k.kommentare = json.anzahl;
                this.form.kommentare = json.anzahl;
            } else {
                this.board.kommentare_board = json.anzahl;
            }
            this.$nextTick(() => {
                const liste = this.$root.querySelector(`[data-kommentare="${ziel}"]`);
                if (liste) liste.scrollTop = liste.scrollHeight;
            });
        },
        async kommentareLaden(ziel) {
            this.kom[ziel].laedt = true;
            try {
                this.kommentareUebernehmen(ziel, await this.anfrage('GET', this.kommentarUrl(ziel)));
            } catch (e) { /* Hinweis kam bereits */ } finally {
                this.kom[ziel].laedt = false;
            }
        },
        async kommentarSenden(ziel) {
            const z = this.kom[ziel];
            z.fehler = '';
            if (!z.text.trim()) return;
            const daten = { text: z.text };
            if (ziel === 'karte') daten.karte = this.form.id;
            try {
                this.kommentareUebernehmen(ziel, await this.anfrage('POST', this.urls.kommentare, daten));
                z.text = '';
                this.erwaehnungSchliessen();
            } catch (e) {
                if (e.art === 'validierung') z.fehler = e.message;
            }
        },
        kommentarBearbeiten(ziel, k) {
            this.kom[ziel].bearbeiteId = k.id;
            this.kom[ziel].bearbeiteText = k.text;
            this.$nextTick(() => this.$root.querySelector(`[data-kommentar-bearbeiten="${k.id}"]`)?.focus());
        },
        async kommentarSpeichern(ziel) {
            const z = this.kom[ziel];
            z.fehler = '';
            try {
                const json = await this.anfrage('PUT', this.url('kommentarUpdate', { KOMMENTAR: z.bearbeiteId }), { text: z.bearbeiteText });
                this.kommentareUebernehmen(ziel, json);
                z.bearbeiteId = null;
                this.erwaehnungSchliessen();
            } catch (e) {
                if (e.art === 'validierung') z.fehler = e.message;
            }
        },
        async kommentarLoeschen(ziel, k) {
            if (!window.confirm('Kommentar löschen?')) return;
            try {
                this.kommentareUebernehmen(ziel, await this.anfrage('DELETE', this.url('kommentarDestroy', { KOMMENTAR: k.id })));
            } catch (e) {
                if (e.art === 'validierung') this.kom[ziel].fehler = e.message;
            }
        },
        kommentarTaste(e, ziel, feld) {
            if (this.erw.offen && this.erw.ziel === ziel && this.erw.feld === feld) {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    const n = this.erwaehnungVorschlaege.length;
                    if (n) this.erw.markiert = (this.erw.markiert + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
                    return;
                }
                if ((e.key === 'Enter' || e.key === 'Tab') && this.erwaehnungVorschlaege.length) {
                    e.preventDefault();
                    this.erwaehnungWaehlen(this.erwaehnungVorschlaege[this.erw.markiert], e.target);
                    return;
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.erwaehnungSchliessen();
                    return;
                }
            }
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                if (feld === 'text') this.kommentarSenden(ziel);
                else this.kommentarSpeichern(ziel);
            }
        },

        // ---------------- @-Erwähnungen ----------------

        /** Beim Tippen: „@…“ direkt vor dem Cursor → Vorschläge (Personen mit Leserecht). */
        erwaehnungPruefen(e, ziel, feld) {
            const el = e.target;
            const vorher = el.value.slice(0, el.selectionStart);
            const m = vorher.match(/(?:^|\s)@([^\s@]{0,30}(?: [^\s@]{0,30})?)$/u);
            if (!m) {
                this.erwaehnungSchliessen();
                return;
            }
            this.erw = { offen: true, ziel, feld, start: el.selectionStart - m[1].length - 1, suche: m[1], markiert: 0 };
        },
        get erwaehnungVorschlaege() {
            if (!this.erw.offen) return [];
            const q = norm(this.erw.suche).trim();
            return this.personen
                .filter((p) => Number(p.id) !== this.userId && (q === '' || norm(p.name).includes(q)))
                .slice(0, 8);
        },
        erwaehnungWaehlen(p, el = null) {
            if (!p) return;
            const z = this.kom[this.erw.ziel];
            const feld = this.erw.feld;
            const alt = z[feld] || '';
            const ende = el ? el.selectionStart : this.erw.start + 1 + this.erw.suche.length;
            const einfuegen = `@${p.name} `;
            z[feld] = alt.slice(0, this.erw.start) + einfuegen + alt.slice(ende);
            const pos = this.erw.start + einfuegen.length;
            this.erwaehnungSchliessen();
            this.$nextTick(() => {
                if (el) { el.focus(); el.setSelectionRange(pos, pos); }
            });
        },
        erwaehnungSchliessen() {
            this.erw = { offen: false, ziel: null, feld: null, start: 0, suche: '', markiert: 0 };
        },

        // ---------------- Verlauf ----------------

        async verlaufLaden(ziel, weiter = false) {
            const v = this.verl[ziel];
            const params = new URLSearchParams();
            if (ziel === 'karte') params.set('karte', this.form.id);
            else params.set('filter', v.filter);
            if (weiter && v.eintraege.length) params.set('vor', v.eintraege[v.eintraege.length - 1].id);
            v.laedt = true;
            try {
                const json = await this.anfrage('GET', `${this.urls.verlauf}?${params}`);
                v.eintraege = weiter ? v.eintraege.concat(json.eintraege) : json.eintraege;
                v.mehr = !!json.mehr;
                v.darfWiederherstellen = !!json.darf_wiederherstellen;
                v.geladen = true;
            } catch (e) { /* Hinweis kam bereits */ } finally {
                v.laedt = false;
            }
        },
        async karteWiederherstellen(ziel, eintrag) {
            if (!window.confirm(`Karte „${(eintrag.text.match(/„([^“]*)“/) || [])[1] || ''}“ wiederherstellen?`)) return;
            try {
                await this.anfrage('POST', this.url('wiederherstellen', { KARTE: eintrag.karte_id }), {});
                this.verlaufLaden(ziel);
            } catch (e) {
                if (e.art === 'validierung') this.toast(e.message, 'fehler');
            }
        },
        /** Verlaufseintrag anklicken: Karte öffnen, wenn es sie (noch) gibt. */
        verlaufKarte(eintrag) {
            return eintrag.karte_id ? this.board.karten.find((k) => k.id === eintrag.karte_id) || null : null;
        },

        // ---------------- Seitenleiste (Board-Kommentare & Verlauf) ----------------

        seitenleisteOeffnen(reiter = 'kommentare') {
            this.dialogOeffnen('seitenleiste');
            this.seitenReiterWaehlen(reiter);
        },
        seitenReiterWaehlen(reiter) {
            this.seitenReiter = reiter;
            this.erwaehnungSchliessen();
            if (reiter === 'kommentare') this.kommentareLaden('board');
            if (reiter === 'verlauf') this.verlaufLaden('board');
        },

        // ---------------- Drag & Drop ----------------

        sortableInit(el, schluessel) {
            if (el._ergSortable) return;
            el._ergSortable = Sortable.create(el, {
                group: 'erg-karten',
                animation: 150,
                draggable: '[data-karte-id]',
                ghostClass: 'erg-ghost',
                chosenClass: 'erg-chosen',
                delayOnTouchOnly: true,
                delay: 180,
                disabled: !this.ziehbar,
                onChoose: (evt) => {
                    this._herkunft = { eltern: evt.item.parentNode, naechstes: evt.item.nextSibling };
                },
                onEnd: (evt) => this.abgelegt(evt),
            });
            el.dataset.bereich = schluessel;
            this.sortables.push(el);
        },
        sortablesAktualisieren() {
            this.sortables = this.sortables.filter((el) => el.isConnected);
            this.sortables.forEach((el) => el._ergSortable?.option('disabled', !this.ziehbar));
        },
        async abgelegt(evt) {
            const { item, from, to } = evt;
            if (from === to && evt.oldIndex === evt.newIndex) return;

            const ids = (liste) => [...liste.querySelectorAll(':scope > [data-karte-id]')].map((x) => Number(x.dataset.karteId));
            const ordnung = { [to.dataset.bereich]: ids(to) };
            if (from !== to) ordnung[from.dataset.bereich] = ids(from);

            // DOM zurücksetzen – Alpine rendert die Karten aus dem Zustand neu
            const h = this._herkunft;
            if (h?.eltern) h.eltern.insertBefore(item, h.naechstes && h.naechstes.parentNode === h.eltern ? h.naechstes : null);

            const karteId = Number(item.dataset.karteId);
            // Optimistisch umsortieren
            Object.entries(ordnung).forEach(([bereich, liste]) => {
                liste.forEach((id, pos) => {
                    const k = this.board.karten.find((x) => x.id === id);
                    if (k) { k.bereich = bereich; k.sort = pos + 1; }
                });
            });

            try {
                await this.anfrage('POST', this.urls.sortierung, { ordnung, version: this.board.version });
            } catch (e) {
                if (e.art !== 'konflikt') this.neuLaden();
                return;
            }

            const ziel = this.bereich(to.dataset.bereich);
            const karte = this.board.karten.find((k) => k.id === karteId);
            if (from !== to && ziel?.status === 'erledigt' && karte?.aufgabe && !karte.aufgabe.erledigt
                && window.confirm('Aufgabe als erledigt markieren?')) {
                await this.aufgabeErledigen(karteId);
            }
        },
        async neuLaden() {
            try { await this.anfrage('GET', this.urls.daten); } catch (e) { /* egal */ }
        },

        // ---------------- Felder ----------------

        felderOeffnen() {
            const liste = [];
            this.hauptbereiche.forEach((b) => {
                liste.push({ ...kopie(b), neu: false, unter: this.unterbereiche(b.schluessel).map((u) => ({ ...kopie(u), neu: false })) });
            });
            this.felder = liste;
            this.felderFehler = '';
            this.dialogOeffnen('felder');
        },
        feldNeu() {
            this.felder.push({ schluessel: null, titel: 'Neues Feld', hinweis: '', badge: '', farbe: 'grau', raster: 1, status: null, eltern: null, neu: true, unter: [] });
        },
        unterNeu(feld) {
            feld.unter.push({ schluessel: null, titel: 'Unterbereich', hinweis: '', badge: '', farbe: feld.farbe, raster: feld.raster, status: null, eltern: feld.schluessel, neu: true });
        },
        verschieben(liste, index, richtung) {
            const ziel = index + richtung;
            if (ziel < 0 || ziel >= liste.length) return;
            const [x] = liste.splice(index, 1);
            liste.splice(ziel, 0, x);
        },
        feldEntfernbar(feld, istUnter) {
            const schluessel = feld.schluessel;
            if (!istUnter && !this.board.bereiche_frei && !feld.neu) return false;
            if (feld.neu) return true;
            if (this.anzahl(schluessel) > 0) return false;
            return !(feld.unter || []).length;
        },
        entfernenHinweis(feld, istUnter) {
            if (!istUnter && !this.board.bereiche_frei && !feld.neu) return 'Felder dieser Vorlage bleiben bestehen';
            if (!feld.neu && this.anzahl(feld.schluessel) > 0) return 'Enthält noch Karten';
            if ((feld.unter || []).length) return 'Enthält noch Unterbereiche';
            return 'Feld entfernen';
        },
        async felderSpeichern() {
            this.felderFehler = '';
            const bereiche = [];
            const eintrag = (f, eltern) => ({
                schluessel: f.schluessel || null,
                titel: (f.titel || '').trim() || 'Feld',
                hinweis: (f.hinweis || '').trim() || null,
                badge: (f.badge || '').trim() || null,
                farbe: f.farbe || 'grau',
                raster: Number(f.raster) === 2 ? 2 : 1,
                eltern,
                status: this.board.bereiche_frei ? (f.status || null) : (f.status ?? null),
            });
            this.felder.forEach((f) => {
                bereiche.push(eintrag(f, null));
                (f.unter || []).forEach((u) => bereiche.push(eintrag(u, f.schluessel)));
            });
            try {
                await this.anfrage('PUT', this.urls.bereiche, { bereiche, version: this.board.version });
                this.toast('Felder gespeichert.');
                this.dialogSchliessen();
            } catch (e) {
                if (e.art === 'validierung') this.felderFehler = e.message;
                else if (e.art === 'konflikt') this.felderOeffnen();
            }
        },

        // ---------------- Zugriff ----------------

        zugriffOeffnen() {
            this.zugriffFehler = '';
            this.meetingLesen = !!this.board.meeting_lesen;
            this.dialogOeffnen('zugriff');
        },
        async zugriffeSpeichern() {
            this.zugriffFehler = '';
            const daten = { zugriffe: this.zugriffEintraege };
            if (this.board.meeting) daten.meeting_lesen = this.meetingLesen;
            try {
                const json = await this.anfrage('POST', this.urls.zugriffe, daten);
                if (json.personen) this.personen = json.personen;
                if (json.auswahl) window.dispatchEvent(new CustomEvent('erg-zugriffe-setzen', { detail: json.auswahl }));
                this.dialogSchliessen();
            } catch (e) {
                if (e.art === 'validierung') this.zugriffFehler = e.message;
            }
        },

        // ---------------- Einstellungen (Verknüpfung) ----------------

        einstellungenOeffnen() {
            this.verknuepfung = {
                meeting_id: this.board.meeting ? String(this.board.meeting.id) : '',
                theme_id: this.board.theme ? String(this.board.theme.id) : '',
            };
            this.einstellungenFehler = '';
            this.dialogOeffnen('einstellungen');
        },
        async verknuepfungSpeichern() {
            this.einstellungenFehler = '';
            try {
                await this.anfrage('PUT', this.urls.update, {
                    meeting_id: this.verknuepfung.meeting_id ? Number(this.verknuepfung.meeting_id) : null,
                    theme_id: this.verknuepfung.theme_id ? Number(this.verknuepfung.theme_id) : null,
                    version: this.board.version,
                });
                this.toast('Verknüpfung gespeichert.');
            } catch (e) {
                if (e.art === 'validierung') this.einstellungenFehler = e.message;
            }
        },

        // ---------------- Export ----------------

        async pngExport() {
            if (this.laedt) return;
            document.activeElement?.blur?.();
            const hinweis = this.toast('Grafik wird erstellt …', 'laedt', 0);
            try {
                const { default: html2canvas } = await import('html2canvas-pro');
                await new Promise((r) => setTimeout(r, 120));
                const el = this.$refs.board;
                const hintergrund = this.board.farbschema === 'papier' ? '#faf7f2' : '#ffffff';
                const canvas = await html2canvas(el, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: hintergrund,
                    logging: false,
                    ignoreElements: (x) => x.classList?.contains('erg-no-export'),
                    onclone: (doc) => {
                        const b = doc.getElementById('erg-board');
                        if (b) {
                            b.classList.add('is-export');
                            b.classList.remove('is-bearbeiten');
                            b.style.margin = '0';
                            b.style.boxShadow = 'none';
                        }
                    },
                });
                const blob = await new Promise((resolve, reject) => {
                    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Blob'))), 'image/png', 1.0);
                });
                if (this.exportUrl) URL.revokeObjectURL(this.exportUrl);
                this.exportBlob = blob;
                this.exportUrl = URL.createObjectURL(blob);
                this.exportBreite = canvas.width;
                this.toastWeg(hinweis);
                this.dialogOeffnen('export');
            } catch (e) {
                console.error('PNG-Export fehlgeschlagen', e);
                this.toastWeg(hinweis);
                this.toast('PNG-Export fehlgeschlagen. Bitte „PDF“ nutzen.', 'fehler', 5000);
            }
        },
        get exportDateiname() {
            return slug(this.board.titel) + '.png';
        },
        async exportKopieren() {
            if (!this.exportBlob) return;
            try {
                if (navigator.clipboard && window.ClipboardItem) {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': this.exportBlob })]);
                    this.toast('Bild in die Zwischenablage kopiert.');
                } else {
                    this.toast('Zwischenablage wird hier nicht unterstützt – bitte Rechtsklick auf das Bild.', 'warnung', 5000);
                }
            } catch (e) {
                this.toast('Bitte Rechtsklick auf das Vorschaubild → „Bild kopieren“.', 'warnung', 5000);
            }
        },
        exportNeuerTab() {
            if (this.exportUrl) window.open(this.exportUrl, '_blank', 'noopener');
        },
    }));

    // ------------------------------------------------------------------
    // Zugriffs-Picker (Board-Seite + Anlegen-Formular)
    // ------------------------------------------------------------------
    Alpine.data('ergZugriffPicker', (optionen, auswahl = [], formular = false) => ({
        optionen: optionen || { users: [], groups: [], roles: [] },
        eintraege: (auswahl || []).map((e) => ({ typ: e.typ, id: Number(e.id), recht: e.recht })),
        formular,
        tabs: [
            { key: 'user', quelle: 'users', label: 'Personen', icon: 'fas fa-user', placeholder: 'Person suchen …' },
            { key: 'group', quelle: 'groups', label: 'Gruppen', icon: 'fas fa-users', placeholder: 'Gruppe suchen …' },
            { key: 'role', quelle: 'roles', label: 'Rollen', icon: 'fas fa-id-badge', placeholder: 'Rolle suchen …' },
        ],
        tab: 'user',
        query: '',
        offen: false,
        markiert: 0,

        init() {
            this.melden();
            this.$watch('eintraege', () => this.melden());
        },
        melden() {
            this.$dispatch('erg-zugriffe', kopie(this.eintraege));
        },
        setzen(liste) {
            this.eintraege = (liste || []).map((e) => ({ typ: e.typ, id: Number(e.id), recht: e.recht }));
        },
        get aktuellerTab() { return this.tabs.find((t) => t.key === this.tab); },
        get treffer() {
            const q = norm(this.query);
            const quelle = this.optionen[this.aktuellerTab.quelle] || [];
            return quelle
                .filter((o) => !this.eintraege.some((e) => e.typ === this.tab && e.id === o.id))
                .filter((o) => q === '' || norm(o.name).includes(q))
                .slice(0, 50);
        },
        name(e) {
            const quelle = this.tabs.find((t) => t.key === e.typ)?.quelle;
            return (this.optionen[quelle] || []).find((o) => o.id === e.id)?.name || 'Unbekannt';
        },
        icon(typ) { return this.tabs.find((t) => t.key === typ)?.icon || 'fas fa-user'; },
        bewegen(schritt) {
            if (!this.treffer.length) return;
            this.offen = true;
            this.markiert = (this.markiert + schritt + this.treffer.length) % this.treffer.length;
        },
        waehlen(o) {
            if (!o) return;
            this.eintraege.push({ typ: this.tab, id: o.id, recht: 'lesen' });
            this.query = '';
            this.markiert = 0;
            this.$refs.suche?.focus();
        },
        entfernen(index) {
            this.eintraege.splice(index, 1);
        },
        get zusammenfassung() {
            const n = (typ) => this.eintraege.filter((e) => e.typ === typ).length;
            const teile = [];
            if (n('user')) teile.push(n('user') + (n('user') === 1 ? ' Person' : ' Personen'));
            if (n('group')) teile.push(n('group') + (n('group') === 1 ? ' Gruppe' : ' Gruppen'));
            if (n('role')) teile.push(n('role') + (n('role') === 1 ? ' Rolle' : ' Rollen'));
            return teile.join(' · ');
        },
    }));

    registerVorlagenEditor(Alpine);
}

// ----------------------------------------------------------------------
// Vorlagen-Editor (ergebnisboards/vorlagen/form): Definition bearbeiten + Live-Vorschau
// des Rasters wie auf der Board-Seite. Speichern als JSON (POST store / PUT update),
// Regeln serverseitig in ErgebnisVorlagenService.
// ----------------------------------------------------------------------
const VORLAGEN_ICONS = [
    'fas fa-th', 'fas fa-th-large', 'fas fa-border-all', 'fas fa-columns', 'fas fa-route', 'fas fa-traffic-light',
    'fas fa-bullseye', 'fas fa-lightbulb', 'fas fa-tasks', 'fas fa-check-circle', 'fas fa-flag', 'fas fa-star',
    'fas fa-users', 'fas fa-comments', 'fas fa-chart-line', 'fas fa-puzzle-piece', 'fas fa-seedling', 'fas fa-compass',
    'fas fa-exclamation-triangle', 'fas fa-thumbs-up', 'fas fa-thumbs-down', 'fas fa-calendar-alt', 'fas fa-bolt', 'far fa-clone',
];
const ICON_MUSTER = /^(fas|far|fab) fa-[a-z0-9-]+$/;
const SPALTE_MUSTER = /^(\d{1,4}(?:\.\d+)?)(fr|px|%)$/;

function spaltenZerlegen(spalten) {
    const teile = (spalten || '1fr').trim().split(/\s+/).map((t) => {
        const m = t.match(SPALTE_MUSTER);
        return m ? { wert: Number(m[1]), einheit: m[2] } : { wert: 1, einheit: 'fr' };
    });
    return teile.length ? teile.slice(0, 6) : [{ wert: 1, einheit: 'fr' }];
}

function registerVorlagenEditor(Alpine) {
    Alpine.data('ergVorlagenEditor', (konfig) => {
        const d = konfig.definition || {};
        let lfd = 0;
        const zeile = (b) => ({
            _id: ++lfd,
            schluessel: b.schluessel || null,
            titel: b.titel || '',
            hinweis: b.hinweis || '',
            badge: b.badge || '',
            farbe: b.farbe || 'grau',
            icon: b.icon || '',
            spalte: Number(b.spalte) || 1,
            spalten: Number(b.spalten) || 1,
            zeile: Number(b.zeile) || 1,
            zeilen: Number(b.zeilen) || 1,
            raster: Number(b.raster) === 2 ? 2 : 1,
            eltern: b.eltern || '',
            status: b.status || '',
        });
        const achsen = d.achsen || {};

        return {
            urls: konfig.urls,
            methode: konfig.methode || 'POST',
            farben: konfig.farben || {},
            statusListe: konfig.status || [],
            statusLabel: STATUS_LABEL,
            icons: VORLAGEN_ICONS,

            titel: d.titel || '',
            beschreibung: d.beschreibung || '',
            icon: d.icon || 'fas fa-th',
            aktiv: d.aktiv !== false,
            bereicheFrei: !!d.bereiche_frei,
            autoStatus: !!d.auto_status,
            breiten: spaltenZerlegen(d.spalten),

            achseY: !!achsen.y,
            achseX: !!achsen.x,
            achsen: {
                y: Object.assign({ titel: '', zusatz: '', oben: '', unten: '' }, achsen.y || {}),
                x: Object.assign({ titel: '', ziel: '', links: '', rechts: '' }, achsen.x || {}),
            },
            legendeAn: !!(d.legende && (d.legende.eintraege || []).length),
            legende: {
                titel: d.legende?.titel || 'Legende',
                eintraege: (d.legende?.eintraege || []).map((e) => ({ _id: ++lfd, farbe: e.farbe || 'grau', text: e.text || '' })),
            },
            bereiche: (d.bereiche || []).map(zeile),
            offen: null,          // _id des aufgeklappten Feldes

            laedt: false,
            fehler: [],
            geaendert: false,

            init() {
                if (!this.bereiche.length) this.feldHinzufuegen();
                window.addEventListener('beforeunload', (e) => {
                    if (this.geaendert && !this.laedt) { e.preventDefault(); e.returnValue = ''; }
                });
            },

            // ---------------- Spalten ----------------

            get spaltenAnzahl() { return this.breiten.length; },
            spaltenSetzen(n) {
                n = Math.max(1, Math.min(6, Number(n) || 1));
                while (this.breiten.length < n) this.breiten.push({ wert: 1, einheit: 'fr' });
                this.breiten.splice(n);
            },
            get spaltenString() {
                return this.breiten.map((b) => {
                    let w = Number(b.wert);
                    if (!Number.isFinite(w) || w <= 0) w = 1;
                    w = Math.min(9999, Math.round(w * 100) / 100);
                    return `${w}${['fr', 'px', '%'].includes(b.einheit) ? b.einheit : 'fr'}`;
                }).join(' ');
            },
            zahlen(n) { return Array.from({ length: n }, (_, i) => i + 1); },

            // ---------------- Felder ----------------

            get hauptfelder() { return this.bereiche.filter((b) => !b.eltern); },
            unterbereiche(schluessel) { return this.bereiche.filter((b) => b.eltern && b.eltern === schluessel); },
            hatKinder(b) { return !!b.schluessel && this.bereiche.some((x) => x.eltern === b.schluessel); },
            elternOptionen(b) { return this.hauptfelder.filter((h) => h._id !== b._id && h.schluessel); },
            elternTitel(schluessel) { return this.bereiche.find((b) => b.schluessel === schluessel)?.titel || '–'; },
            ausserhalb(b) { return !b.eltern && !this.bereicheFrei && (b.spalte + b.spalten - 1) > this.spaltenAnzahl; },
            get warnungen() {
                const w = [];
                if (!this.hauptfelder.length) w.push('Mindestens ein Hauptfeld ist nötig.');
                this.bereiche.forEach((b) => {
                    if (this.ausserhalb(b)) w.push(`„${b.titel || 'Feld'}“ liegt außerhalb der ${this.spaltenAnzahl} Spalten.`);
                    if (!b.titel.trim()) w.push('Ein Feld hat noch keinen Titel.');
                });
                return [...new Set(w)];
            },

            neuerSchluessel() {
                const belegt = new Set(this.bereiche.map((b) => b.schluessel));
                let n = this.bereiche.length + 1;
                while (belegt.has(`feld_${n}`)) n++;
                return `feld_${n}`;
            },
            feldHinzufuegen(eltern = '') {
                const maxZeile = Math.max(0, ...this.hauptfelder.map((b) => b.zeile + b.zeilen - 1));
                const feld = zeile({
                    schluessel: this.neuerSchluessel(),
                    titel: eltern ? 'Unterbereich' : `Feld ${this.hauptfelder.length + 1}`,
                    farbe: eltern ? (this.bereiche.find((b) => b.schluessel === eltern)?.farbe || 'grau') : 'blau',
                    spalte: this.bereicheFrei ? Math.min(this.hauptfelder.length + 1, this.spaltenAnzahl) : 1,
                    spalten: this.bereicheFrei ? 1 : this.spaltenAnzahl,
                    zeile: this.bereicheFrei ? 1 : maxZeile + 1,
                    eltern,
                });
                if (eltern) {
                    // hinter dem Hauptfeld und seinen bisherigen Unterbereichen einfügen
                    const indizes = this.bereiche.map((b, i) => (b.schluessel === eltern || b.eltern === eltern ? i : -1)).filter((i) => i >= 0);
                    this.bereiche.splice(Math.max(...indizes) + 1, 0, feld);
                } else {
                    this.bereiche.push(feld);
                }
                this.offen = feld._id;
                this.geaendert = true;
                this.$nextTick(() => this.$root.querySelector(`[data-feld="${feld._id}"] input`)?.focus());
            },
            feldEntfernen(index) {
                const b = this.bereiche[index];
                const kinder = this.hatKinder(b);
                if (!window.confirm(kinder
                    ? `Feld „${b.titel}“ entfernen? Seine Unterbereiche werden zu eigenen Hauptfeldern.`
                    : `Feld „${b.titel}“ entfernen?`)) return;
                if (kinder) this.bereiche.forEach((x) => { if (x.eltern === b.schluessel) x.eltern = ''; });
                this.bereiche.splice(index, 1);
                this.geaendert = true;
            },
            feldVerschieben(index, schritt) {
                const ziel = index + schritt;
                if (ziel < 0 || ziel >= this.bereiche.length) return;
                const [b] = this.bereiche.splice(index, 1);
                this.bereiche.splice(ziel, 0, b);
                this.geaendert = true;
            },
            feldUmschalten(b) { this.offen = this.offen === b._id ? null : b._id; },
            spalteGeaendert(b) { b.spalten = Math.min(b.spalten, Math.max(1, this.spaltenAnzahl - b.spalte + 1)); },

            legendeHinzufuegen() { this.legende.eintraege.push({ _id: ++lfd, farbe: 'grau', text: '' }); },
            legendeEntfernen(i) { this.legende.eintraege.splice(i, 1); },

            // ---------------- Vorschau (wie partials/board) ----------------

            get maxZeile() { return Math.max(1, ...this.hauptfelder.map((b) => b.zeile + b.zeilen - 1)); },
            get seitenfeld() {
                if (this.bereicheFrei || this.spaltenAnzahl < 2) return null;
                return this.hauptfelder.find((b) => b.spalte === this.spaltenAnzahl && b.spalten === 1
                    && b.zeile === 1 && b.zeilen >= this.maxZeile) || null;
            },
            get legendeVorschau() {
                return this.legendeAn ? this.legende.eintraege.filter((e) => e.text.trim()) : [];
            },
            get legendeImSeitenfeld() { return this.legendeVorschau.length > 0 && !!this.seitenfeld; },
            get vorschauMinBreite() {
                if (this.bereicheFrei) return this.spaltenAnzahl * 200;
                return Math.max(560, this.spaltenAnzahl * 230 + (this.achseY ? 60 : 0));
            },
            feldStil(b) {
                if (this.bereicheFrei) return `grid-column: ${b.spalte} / span 1; grid-row: 1 / span 1`;
                const spalte = Math.min(b.spalte, this.spaltenAnzahl);
                const spalten = Math.max(1, Math.min(b.spalten, this.spaltenAnzahl - spalte + 1));
                return `grid-column: ${spalte} / span ${spalten}; grid-row: ${b.zeile} / span ${b.zeilen}`;
            },
            get achseXSpalten() { return this.seitenfeld ? `1 / ${this.spaltenAnzahl}` : '1 / -1'; },
            iconGueltig(wert) { return !wert || ICON_MUSTER.test(wert.trim()); },

            // ---------------- Speichern ----------------

            nutzdaten() {
                const leer = (s) => ((s || '').trim() === '' ? null : s.trim());
                const achse = (o) => Object.fromEntries(Object.entries(o).map(([k, v]) => [k, (v || '').trim()]).filter(([, v]) => v !== ''));
                const achsenDaten = {};
                if (this.achseY) achsenDaten.y = achse(this.achsen.y);
                if (this.achseX) achsenDaten.x = achse(this.achsen.x);
                return {
                    titel: this.titel.trim(),
                    beschreibung: leer(this.beschreibung),
                    icon: leer(this.icon),
                    aktiv: !!this.aktiv,
                    spalten: this.spaltenString,
                    bereiche_frei: !!this.bereicheFrei,
                    auto_status: !!this.autoStatus,
                    achsen: Object.keys(achsenDaten).length ? achsenDaten : null,
                    legende: this.legendeVorschau.length
                        ? { titel: leer(this.legende.titel) || 'Legende', eintraege: this.legendeVorschau.map((e) => ({ farbe: e.farbe, text: e.text.trim() })) }
                        : null,
                    bereiche: this.bereiche.map((b) => ({
                        schluessel: b.schluessel,
                        titel: b.titel.trim(),
                        hinweis: leer(b.hinweis),
                        badge: leer(b.badge),
                        farbe: b.farbe,
                        icon: leer(b.icon),
                        spalte: Number(b.spalte),
                        spalten: Number(b.spalten),
                        zeile: Number(b.zeile),
                        zeilen: Number(b.zeilen),
                        raster: Number(b.raster) === 2 ? 2 : 1,
                        eltern: b.eltern || null,
                        status: b.status || null,
                    })),
                };
            },

            async speichern() {
                if (this.laedt) return;
                this.fehler = [];
                this.laedt = true;
                let antwort;
                let json = {};
                try {
                    antwort = await fetch(this.urls.speichern, {
                        method: this.methode,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json', 'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(this.nutzdaten()),
                    });
                    try { json = await antwort.json(); } catch (e) { json = {}; }
                } catch (e) {
                    this.laedt = false;
                    this.fehler = ['Keine Verbindung zum Server.'];
                    return;
                }

                if (antwort.ok) {
                    this.geaendert = false;
                    window.location.href = json.redirect || this.urls.index;
                    return;
                }
                this.laedt = false;
                if (antwort.status === 422) {
                    this.fehler = [...new Set(Object.values(json.errors || {}).flat())];
                    if (!this.fehler.length) this.fehler = [json.message || 'Bitte Eingaben prüfen.'];
                } else if (antwort.status === 419) {
                    this.fehler = ['Sitzung abgelaufen – bitte Seite neu laden.'];
                } else if (antwort.status === 403) {
                    this.fehler = ['Dafür fehlt Ihnen die Berechtigung.'];
                } else {
                    this.fehler = [json.message || `Fehler (${antwort.status})`];
                }
                this.$nextTick(() => this.$refs.fehler?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
            },
        };
    });
}

if (window.Alpine) {
    registerComponents(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerComponents(window.Alpine));
}
