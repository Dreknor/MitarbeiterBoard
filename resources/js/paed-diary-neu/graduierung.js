/**
 * Pädagogisches Tagebuch – neue Ansicht: Graduierung/Reflexion (Übersicht, Sessions, Einschätzung).
 * Wird von paed-diary-neu.js registriert. Gleiche Endpunkte wie die bisherigen Views
 * (gradingDocumentation.*), alle URLs kommen per Konfiguration aus der Blade-View.
 *
 *   - graduierungStart():     Übersicht (Session starten, offene/abgeschlossene Sessions)
 *   - graduierungGruppe():    Gruppensession – Selbsteinschätzungen erfassen, QR-Links
 *   - graduierungEinzel():    Einzelsession – Selbsteinschätzung eines Schülers
 *   - graduierungBewertung(): Einschätzung der Lehrkraft „Schüler für Schüler“
 */

/** Beschriftung der Skala (wie die öffentliche Schülerseite). */
export const SKALA = [
    { wert: 1, text: 'Klappt noch nicht' },
    { wert: 2, text: 'Klappt manchmal' },
    { wert: 3, text: 'Klappt oft' },
    { wert: 4, text: 'Klappt fast immer' },
    { wert: 5, text: 'Kann ich sicher' },
];

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/** POST mit JSON; wirft bei Fehlern eine Error mit der Meldung des Servers. */
async function sende(url, daten = null, fallback = 'Die Aktion ist fehlgeschlagen.') {
    let resp;
    try {
        resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: daten ? JSON.stringify(daten) : null,
        });
    } catch (e) {
        throw new Error(fallback + ' (keine Verbindung)');
    }
    const json = await resp.json().catch(() => ({}));
    if (!resp.ok) {
        const ersterFehler = json.errors ? Object.values(json.errors).flat()[0] : null;
        throw new Error(json.message || ersterFehler || fallback);
    }
    return json;
}

/** Leere PHP-Collections kommen als Array – für Zugriff per ID in Objekte umwandeln. */
function alsObjekt(wert) {
    return wert && !Array.isArray(wert) ? { ...wert } : {};
}

function lesen(schluessel, standard) {
    try {
        const wert = window.localStorage.getItem(schluessel);
        return wert === null ? standard : wert === 'true';
    } catch (e) {
        return standard;
    }
}

function schreiben(schluessel, wert) {
    try { window.localStorage.setItem(schluessel, String(wert)); } catch (e) { /* ohne Speicher weiter */ }
}

/**
 * Reihenfolge der Schritte (Schüler × Fragen): schülerweise oder fragenweise.
 * @returns {{s: number, f: number}[]} Indizes
 */
function schritte(anzahlSchueler, anzahlFragen, fragenweise) {
    const liste = [];
    if (fragenweise) {
        for (let f = 0; f < anzahlFragen; f++) for (let s = 0; s < anzahlSchueler; s++) liste.push({ s, f });
    } else {
        for (let s = 0; s < anzahlSchueler; s++) for (let f = 0; f < anzahlFragen; f++) liste.push({ s, f });
    }
    return liste;
}

export function registerGraduierung(Alpine) {
    // ── Übersicht: Session starten, offene und abgeschlossene Sessions ───────
    Alpine.data('graduierungStart', (konfig) => ({
        tab: konfig.tab || 'neu',
        gruppen: konfig.gruppen || [],
        gruppe: { klasse_id: '', group_id: '', answer_order_mode: 'by_student' },
        einzel: { klasse_id: '', schueler_id: '' },
        schuelerListe: [],
        schuelerLaedt: false,
        startet: '',
        meldung: '',
        rueckfrage: null,   // { art: 'abbrechen'|'oeffnen', id, text }
        arbeitet: false,

        get passendeGruppen() {
            const id = Number(this.gruppe.klasse_id);
            if (!id) return [];
            return this.gruppen.filter(g => (g.klassen || []).includes(id));
        },

        klasseGruppeGewaehlt() {
            // Eine Lerngruppe, die die neue Klasse nicht enthält, wieder abwählen
            if (this.gruppe.group_id && !this.passendeGruppen.some(g => String(g.id) === String(this.gruppe.group_id))) {
                this.gruppe.group_id = '';
            }
        },

        async klasseEinzelGewaehlt() {
            this.einzel.schueler_id = '';
            this.schuelerListe = [];
            if (!this.einzel.klasse_id) return;
            this.schuelerLaedt = true;
            try {
                const resp = await fetch(konfig.schuelerUrl.replace('__KLASSE__', encodeURIComponent(this.einzel.klasse_id)), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!resp.ok) throw new Error();
                this.schuelerListe = (await resp.json()).schueler || [];
            } catch (e) {
                this.meldung = 'Die Schüler der Klasse konnten nicht geladen werden.';
            } finally {
                this.schuelerLaedt = false;
            }
        },

        async starten(art) {
            if (this.startet) return;
            this.meldung = '';
            this.startet = art;
            try {
                const daten = art === 'gruppe'
                    ? { klasse_id: this.gruppe.klasse_id, group_id: this.gruppe.group_id || null, answer_order_mode: this.gruppe.answer_order_mode }
                    : { ...this.einzel };
                const json = await sende(art === 'gruppe' ? konfig.startGruppeUrl : konfig.startEinzelUrl, daten,
                    'Die Session konnte nicht gestartet werden.');
                if (!json.redirect) throw new Error('Keine Weiterleitung erhalten.');
                window.location.href = json.redirect;
            } catch (e) {
                this.meldung = e.message;
                this.startet = '';
            }
        },

        fragen(art, id, text) {
            this.rueckfrage = { art, id, text };
        },

        async bestaetigen() {
            const { art, id } = this.rueckfrage || {};
            if (!art || this.arbeitet) return;
            this.arbeitet = true;
            try {
                const url = (art === 'abbrechen' ? konfig.abbrechenUrl : konfig.oeffnenUrl).replace('__SESSION__', encodeURIComponent(id));
                const json = await sende(url, null, art === 'abbrechen'
                    ? 'Die Session konnte nicht abgebrochen werden.'
                    : 'Die Session konnte nicht wiedergeöffnet werden.');
                if (json.redirect) {
                    window.location.href = json.redirect;
                } else {
                    window.location.reload();
                }
            } catch (e) {
                this.meldung = e.message;
                this.rueckfrage = null;
                this.arbeitet = false;
            }
        },
    }));

    // ── Gruppensession: Selbsteinschätzungen erfassen ───────────────────────
    Alpine.data('graduierungGruppe', (konfig) => ({
        skala: SKALA,
        fragen: konfig.fragen || [],
        schueler: konfig.schueler || [],
        antworten: alsObjekt(konfig.antworten),   // 'schuelerId_frageId' => Wert
        finalisiert: (konfig.finalisiert || []).map(Number),
        abgeschlossen: !!konfig.abgeschlossen,
        modus: konfig.modus || 'by_student',
        sIndex: 0,
        fIndex: 0,
        speichert: false,
        modusWechselt: false,
        meldung: '',
        hinweis: '',
        fortgesetzt: 0,
        qr: null,   // { name, url, kopiert, laedt, fehler }

        init() {
            this.fortgesetzt = Object.keys(this.antworten).length;
            const erster = this.ersterOffener();
            if (erster) { this.sIndex = erster.s; this.fIndex = erster.f; }
        },

        get aktuellerSchueler() { return this.schueler[this.sIndex] || null; },
        get aktuelleFrage() { return this.fragen[this.fIndex] || null; },
        get fragenweise() { return this.modus === 'by_question'; },

        schluessel(s, f) { return s.id + '_' + f.id; },
        istFinalisiert(s) { return this.abgeschlossen || this.finalisiert.includes(Number(s.id)); },
        antwort(s, f) { return s && f ? this.antworten[this.schluessel(s, f)] : undefined; },
        anzahlBeantwortet(s) { return this.fragen.filter(f => this.antwort(s, f) !== undefined).length; },
        istVollstaendig(s) { return this.istFinalisiert(s) || this.anzahlBeantwortet(s) === this.fragen.length; },

        get offeneSchritte() {
            return schritte(this.schueler.length, this.fragen.length, this.fragenweise)
                .filter(x => !this.istFinalisiert(this.schueler[x.s]) && this.antwort(this.schueler[x.s], this.fragen[x.f]) === undefined);
        },
        get allesBeantwortet() { return this.fragen.length > 0 && this.offeneSchritte.length === 0; },
        get gesamt() { return this.schueler.length * this.fragen.length; },
        get beantwortet() { return Object.keys(this.antworten).length; },

        ersterOffener() { return this.offeneSchritte[0] || null; },

        naechsterOffener() {
            const alle = schritte(this.schueler.length, this.fragen.length, this.fragenweise);
            const pos = alle.findIndex(x => x.s === this.sIndex && x.f === this.fIndex);
            const offen = x => !this.istFinalisiert(this.schueler[x.s]) && this.antwort(this.schueler[x.s], this.fragen[x.f]) === undefined;
            return alle.slice(pos + 1).find(offen) || alle.slice(0, Math.max(pos, 0)).find(offen) || null;
        },

        async waehlen(wert) {
            const s = this.aktuellerSchueler, f = this.aktuelleFrage;
            if (!s || !f || this.speichert || this.istFinalisiert(s)) return;
            this.speichert = true;
            this.meldung = '';
            try {
                await sende(konfig.speichernUrl, { session_id: konfig.sessionId, schueler_id: s.id, question_id: f.id, self_rating: wert },
                    'Die Antwort wurde nicht gespeichert. Bitte erneut auswählen.');
                this.antworten[this.schluessel(s, f)] = wert;
                const naechster = this.naechsterOffener();
                if (naechster) { this.sIndex = naechster.s; this.fIndex = naechster.f; }
            } catch (e) {
                this.meldung = e.message;
            } finally {
                this.speichert = false;
            }
        },

        weiter() {
            const naechster = this.naechsterOffener();
            if (!naechster) {
                this.hinweis = 'Es gibt keine weiteren offenen Antworten.';
                return;
            }
            this.hinweis = '';
            this.sIndex = naechster.s;
            this.fIndex = naechster.f;
        },

        zuSchueler(index) {
            this.sIndex = index;
            this.hinweis = '';
            if (!this.fragenweise) {
                const s = this.schueler[index];
                const offen = this.fragen.findIndex(f => this.antwort(s, f) === undefined);
                this.fIndex = offen === -1 ? 0 : offen;
            }
            this.$nextTick(() => this.$refs.karte?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },

        async modusSetzen(modus) {
            if (this.modus === modus || this.modusWechselt || this.abgeschlossen) return;
            this.modusWechselt = true;
            this.meldung = '';
            try {
                const json = await sende(konfig.modusUrl, { answer_order_mode: modus }, 'Die Reihenfolge konnte nicht geändert werden.');
                this.modus = json.answer_order_mode || modus;
                const erster = this.ersterOffener();
                if (erster) { this.sIndex = erster.s; this.fIndex = erster.f; }
            } catch (e) {
                this.meldung = e.message;
            } finally {
                this.modusWechselt = false;
            }
        },

        async qrZeigen(s) {
            this.qr = { name: s.vorname + ' ' + s.nachname, url: '', kopiert: false, laedt: true, fehler: '' };
            try {
                const json = await sende(konfig.qrUrl.replace('__SCHUELER__', encodeURIComponent(s.id)), null,
                    'Der QR-Code konnte nicht erzeugt werden.');
                this.qr.url = json.url;
                this.qr.laedt = false;
                this.$nextTick(() => {
                    const ziel = this.$refs.qrBild;
                    if (!ziel) return;
                    ziel.innerHTML = '';
                    if (typeof window.QRCode !== 'undefined') {
                        new window.QRCode(ziel, { text: json.url, width: 220, height: 220 });
                    } else {
                        this.qr.fehler = 'QR-Code nicht verfügbar – bitte den Link verwenden.';
                    }
                });
            } catch (e) {
                this.qr.laedt = false;
                this.qr.fehler = e.message;
            }
        },

        async kopieren() {
            if (!this.qr?.url) return;
            try {
                await navigator.clipboard.writeText(this.qr.url);
                this.qr.kopiert = true;
            } catch (e) {
                this.$refs.qrLink?.select();
                this.qr.fehler = 'Kopieren nicht möglich – der Link ist markiert und kann mit Strg+C kopiert werden.';
            }
        },
    }));

    // ── Einzelsession: Selbsteinschätzung eines Schülers ────────────────────
    Alpine.data('graduierungEinzel', (konfig) => ({
        skala: SKALA,
        fragen: konfig.fragen || [],
        antworten: alsObjekt(konfig.antworten),   // frageId => Wert
        abgeschlossen: !!konfig.abgeschlossen,
        status: {},          // frageId => speichert | ok | fehler
        warteschlangen: {},
        meldung: '',
        fortgesetzt: 0,

        init() {
            this.fortgesetzt = Object.keys(this.antworten).length;
        },

        get beantwortet() { return this.fragen.filter(f => this.antworten[f.id]).length; },
        get fertig() { return this.fragen.length > 0 && this.beantwortet === this.fragen.length; },

        waehlen(f, wert) {
            if (this.abgeschlossen) return;
            const vorher = this.antworten[f.id];
            this.antworten[f.id] = wert;
            this.status[f.id] = 'speichert';
            const lauf = async () => {
                try {
                    await sende(konfig.speichernUrl, { session_id: konfig.sessionId, schueler_id: konfig.schuelerId, question_id: f.id, self_rating: wert },
                        'Die Antwort wurde nicht gespeichert. Bitte erneut auswählen.');
                    this.status[f.id] = 'ok';
                } catch (e) {
                    this.status[f.id] = 'fehler';
                    if (this.antworten[f.id] === wert) this.antworten[f.id] = vorher;
                    this.meldung = e.message;
                }
            };
            this.warteschlangen[f.id] = (this.warteschlangen[f.id] || Promise.resolve()).then(lauf, lauf);
        },
    }));

    // ── Einschätzung der Lehrkraft: Schüler für Schüler ─────────────────────
    Alpine.data('graduierungBewertung', (konfig) => ({
        fragen: konfig.fragen || [],
        schueler: (konfig.schueler || []).map(s => ({
            ...s,
            selbst: alsObjekt(s.selbst),
            lehrkraft: alsObjekt(s.lehrkraft),
            kommentare: alsObjekt(s.kommentare),
            notiz: s.notiz || '',
        })),
        abgeschlossen: !!konfig.abgeschlossen,
        gruppe: !!konfig.gruppe,
        modus: konfig.gruppe ? (konfig.modus || 'by_student') : 'by_student',
        sIndex: 0,
        fIndex: 0,
        status: {},          // 'schuelerId-frageId' bzw. 'schuelerId-notiz' => speichert | ok | fehler
        warteschlangen: {},
        meldung: '',
        hinweis: '',
        modusWechselt: false,
        abschlussFragen: false,
        schliesst: false,
        automatisch: lesen('pdnGraduierungAutomatisch', true),

        init() {
            const erster = this.offeneSchritte[0];
            if (erster) {
                this.sIndex = erster.s;
                this.fIndex = erster.f;
            }
            this.$watch('automatisch', wert => schreiben('pdnGraduierungAutomatisch', wert));
        },

        get fragenweise() { return this.gruppe && this.modus === 'by_question'; },
        get aktuell() { return this.schueler[this.sIndex] || null; },
        get aktuelleFrage() { return this.fragen[this.fIndex] || null; },
        get sichtbareFragen() {
            return this.fragenweise && this.aktuelleFrage ? [this.aktuelleFrage] : this.fragen;
        },

        istGesperrt(s) { return this.abgeschlossen || !!s?.abgeschlossen; },
        anzahlBewertet(s) { return this.fragen.filter(f => s.lehrkraft[f.id]).length; },
        istVollstaendig(s) { return this.fragen.length > 0 && this.anzahlBewertet(s) === this.fragen.length; },
        frageFertig(f) { return this.schueler.every(s => s.abgeschlossen || s.lehrkraft[f.id]); },
        bewertetZuFrage(f) { return this.schueler.filter(s => s.lehrkraft[f.id]).length; },

        get vollstaendig() { return this.schueler.filter(s => s.abgeschlossen || this.istVollstaendig(s)).length; },
        get unvollstaendig() { return this.schueler.filter(s => !s.abgeschlossen && !this.istVollstaendig(s)).map(s => s.name); },
        get irgendeineBewertung() { return this.schueler.some(s => this.fragen.some(f => s.lehrkraft[f.id])); },
        get offeneSchritte() {
            return schritte(this.schueler.length, this.fragen.length, this.fragenweise)
                .filter(x => !this.istGesperrt(this.schueler[x.s]) && !this.schueler[x.s].lehrkraft[this.fragen[x.f].id]);
        },

        /** Speicherstatus der aktuellen Person: fehler > speichert > ok */
        schuelerStatus(s) {
            const werte = Object.entries(this.status).filter(([k]) => k.startsWith(s.id + '-')).map(([, v]) => v);
            if (werte.includes('fehler')) return 'fehler';
            if (werte.includes('speichert')) return 'speichert';
            return werte.length ? 'ok' : '';
        },

        differenz(s, f) {
            const a = s.selbst[f.id], b = s.lehrkraft[f.id];
            return a && b ? Math.abs(a - b) : 0;
        },

        // ── Navigation ──
        zuSchueler(index) {
            if (index < 0 || index >= this.schueler.length) return;
            this.sIndex = index;
            this.hinweis = '';
            if (!this.fragenweise) {
                const s = this.schueler[index];
                const offen = this.fragen.findIndex(f => !s.lehrkraft[f.id]);
                this.fIndex = offen === -1 ? 0 : offen;
            }
            this.$nextTick(() => this.$refs.kopf?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
        },

        zuFrage(index) {
            this.fIndex = index;
            this.hinweis = '';
        },

        /** Nächster offener Schritt nach dem aktuellen (mit Umlauf). */
        naechsterOffener() {
            const alle = schritte(this.schueler.length, this.fragen.length, this.fragenweise);
            const pos = alle.findIndex(x => x.s === this.sIndex && x.f === this.fIndex);
            const offen = x => !this.istGesperrt(this.schueler[x.s]) && !this.schueler[x.s].lehrkraft[this.fragen[x.f].id];
            return alle.slice(pos + 1).find(offen) || alle.slice(0, Math.max(pos, 0)).find(offen) || null;
        },

        weiter() {
            if (this.fragenweise) {
                const naechster = this.naechsterOffener();
                if (!naechster) {
                    this.hinweis = 'Alle Einschätzungen sind erfasst.';
                    return;
                }
                this.sIndex = naechster.s;
                this.fIndex = naechster.f;
                this.hinweis = '';
                return;
            }
            // Schülerweise: zur nächsten Person mit offenen Einschätzungen, sonst einfach zur nächsten
            const n = this.schueler.length;
            for (let i = 1; i <= n; i++) {
                const index = (this.sIndex + i) % n;
                const s = this.schueler[index];
                if (!this.istGesperrt(s) && !this.istVollstaendig(s)) return this.zuSchueler(index);
            }
            this.zuSchueler((this.sIndex + 1) % n);
        },

        // ── Speichern (pro Feld nacheinander, kein globales Sperr-Flag) ──
        einreihen(schluessel, aufgabe) {
            this.status[schluessel] = 'speichert';
            const lauf = async () => {
                try {
                    await aufgabe();
                    this.status[schluessel] = 'ok';
                } catch (e) {
                    this.status[schluessel] = 'fehler';
                    this.meldung = e.message;
                }
            };
            const kette = (this.warteschlangen[schluessel] || Promise.resolve()).then(lauf, lauf);
            this.warteschlangen[schluessel] = kette;
            return kette;
        },

        speichereFrage(s, f) {
            return this.einreihen(s.id + '-' + f.id, () => sende(konfig.speichernUrl, {
                session_id: konfig.sessionId,
                schueler_id: s.id,
                question_id: f.id,
                teacher_rating: s.lehrkraft[f.id] || null,
                comment: s.kommentare[f.id] || null,
            }, 'Die Einschätzung wurde nicht gespeichert.'));
        },

        bewerten(s, f, wert) {
            if (this.istGesperrt(s)) return;
            s.lehrkraft[f.id] = wert;
            this.speichereFrage(s, f);
            this.hinweis = '';

            if (!this.automatisch) return;
            if (this.fragenweise) {
                const naechster = this.naechsterOffener();
                if (naechster) { this.sIndex = naechster.s; this.fIndex = naechster.f; }
                return;
            }
            // Schülerweise: zur nächsten offenen Frage dieser Person scrollen
            const naechste = this.fragen.find(x => !s.lehrkraft[x.id]);
            if (naechste) {
                this.$nextTick(() => document.getElementById('pdnFrage-' + naechste.id)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
            }
        },

        kommentieren(s, f, text) {
            if (this.istGesperrt(s) || (s.kommentare[f.id] || '') === text) return;
            s.kommentare[f.id] = text;
            this.speichereFrage(s, f);
        },

        notieren(s, text) {
            if (this.istGesperrt(s) || s.notiz === text) return;
            s.notiz = text;
            this.einreihen(s.id + '-notiz', () => sende(konfig.notizUrl, {
                session_id: konfig.sessionId,
                schueler_id: s.id,
                note: text || null,
            }, 'Das Coaching-Protokoll wurde nicht gespeichert.'));
        },

        erneut(s) {
            this.fragen.forEach(f => {
                if (this.status[s.id + '-' + f.id] === 'fehler') this.speichereFrage(s, f);
            });
            if (this.status[s.id + '-notiz'] === 'fehler') {
                const text = s.notiz;
                s.notiz = null;
                this.notieren(s, text);
            }
        },

        async modusSetzen(modus) {
            if (this.modus === modus || this.modusWechselt || this.abgeschlossen) return;
            this.modusWechselt = true;
            this.meldung = '';
            try {
                const json = await sende(konfig.modusUrl, { answer_order_mode: modus }, 'Die Reihenfolge konnte nicht geändert werden.');
                this.modus = json.answer_order_mode || modus;
                const erster = this.offeneSchritte[0];
                if (erster) { this.sIndex = erster.s; this.fIndex = erster.f; }
            } catch (e) {
                this.meldung = e.message;
            } finally {
                this.modusWechselt = false;
            }
        },

        async abschliessen() {
            if (this.schliesst || !this.irgendeineBewertung) return;
            this.schliesst = true;
            await Promise.all(Object.values(this.warteschlangen));
            if (Object.values(this.status).includes('fehler')) {
                this.schliesst = false;
                this.abschlussFragen = false;
                this.meldung = 'Einige Eingaben sind nicht gespeichert. Bitte zuerst „Erneut versuchen“.';
                return;
            }
            try {
                await sende(konfig.abschliessenUrl, null, 'Die Session wurde nicht abgeschlossen.');
                window.location.href = konfig.zurueckUrl;
            } catch (e) {
                this.meldung = e.message;
                this.abschlussFragen = false;
                this.schliesst = false;
            }
        },
    }));
}
