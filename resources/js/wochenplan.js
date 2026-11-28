/**
 * Wochenplan – Alpine.js-Komponenten (ohne Bootstrap).
 *
 * Alpine wird von sidebar.js global geladen und gestartet; hier werden nur die
 * Komponenten registriert (kein eigener Alpine-Import, kein Alpine.start()).
 *
 * - wpEditor:            Editor eines Plans (JSON-Endpunkte, jede Antwort liefert den ganzen Plan)
 * - wpMatrix:            Status je Kind (Klasse × Aufgaben)
 * - wpEinstellungen:     Live-Vorschau der Nextcloud-Ordnerstruktur
 * - wpBibliothek:        Aufgaben-Bibliothek (Seite)
 * - formatvorlageEditor: Formatvorlagen mit Live-Vorschau
 */
import Sortable from 'sortablejs';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** Fetch-Hilfe: JSON rein/raus, wirft bei Fehlern ein Error-Objekt mit status/daten. */
async function anfrage(method, url, body = null) {
    const optionen = {
        method,
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    };
    if (body instanceof FormData) {
        optionen.body = body;
    } else if (body !== null) {
        optionen.headers['Content-Type'] = 'application/json';
        optionen.body = JSON.stringify(body);
    }

    const antwort = await fetch(url, optionen);
    let daten = null;
    try {
        daten = await antwort.json();
    } catch (e) {
        daten = null;
    }

    if (!antwort.ok) {
        const fehler = new Error(
            daten?.errors ? Object.values(daten.errors).flat()[0] : (daten?.message || 'Die Aktion ist fehlgeschlagen.')
        );
        fehler.status = antwort.status;
        fehler.daten = daten;
        throw fehler;
    }

    return daten;
}

const STATUS_FOLGE = { offen: 'teilweise', teilweise: 'erledigt', erledigt: 'offen' };
const STATUS_ZEICHEN = { offen: '', teilweise: '', erledigt: '✓' }; // „teilweise“ zeichnet CSS (halber Kreis)
const STATUS_TEXT = { offen: 'offen', teilweise: 'teilweise', erledigt: 'erledigt' };

/** Gemeinsame Hilfen für Meldungen. */
function meldungen() {
    return {
        toast: null,
        _toastTimer: null,
        zeige(text, art = 'success') {
            this.toast = { text, art };
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.toast = null; }, art === 'error' ? 6000 : 3500);
        },
    };
}

function registerWochenplanComponents(Alpine) {
    if (!Alpine) {
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Editor
    // ─────────────────────────────────────────────────────────────────────────
    Alpine.data('wpEditor', (plan, config) => ({
        ...meldungen(),
        plan,
        config,
        busy: false,
        metaOffen: false,
        meta: {},
        zu: {},                 // eingeklappte Fächer
        neu: {},                // Formular „Aufgabe hinzufügen“ je Fach
        bearbeiten: null,       // { id, aufgabe, dauer, niveau, link, basis }
        uebungNeu: '',
        uebungNeuKuerzel: '',
        uebungBearbeiten: null, // { id, aufgabe, kuerzel, basis }
        fachNeu: '',
        freiesFeld: { offen: false, name: '', symbol: '' },
        fachBearbeiten: null,   // { id, custom_name, symbol, frei, katalog_name }
        dialog: null,           // 'fortschreiben' | 'vorlage' | 'abgleich' | 'bibliothek' | 'nextcloud' | 'loeschen'
        abgleich: { vorschau: null, fachId: null, angepassteBehalten: true, eigeneBehalten: true, laedt: false },
        vorlageName: '',
        mitKinderplaenen: true,
        bibliothek: { q: '', fachId: '', zielFach: null, liste: [], laedt: false },
        nextcloud: { pfad: '', eintraege: [], auswahl: [], laedt: false },
        tagebuch: { aufgaben: config.tagebuchAufgaben || [], offen: null, fachId: '', text: '', dauer: '' },
        dragover: false,
        hochladen: false,
        _herkunft: null,

        init() {
            this.metaZuruecksetzen();
            this.plan.faecher.forEach((f) => { this.neu[f.id] = this.leereAufgabe(); });
        },

        get basis() { return `${this.config.basisUrl}/${this.plan.id}`; },
        get darf() { return !!this.plan.darf_bearbeiten; },
        get hatFehler() {
            return this.plan.ablage?.status === 'fehler'
                || this.plan.dateien.some((d) => !d.vom_klassenplan && ['fehler', 'fehlt'].includes(d.status));
        },
        get anzahlAufgaben() { return this.plan.faecher.reduce((n, f) => n + f.aufgaben.length, 0); },
        get freieFaecher() {
            const vorhanden = this.plan.faecher.map((f) => f.wp_fach_id);
            return this.config.faecher.filter((f) => !vorhanden.includes(f.id));
        },
        niveauName(n) { return this.config.niveaus[n] || ''; },
        leereAufgabe() { return { aufgabe: '', dauer: '', niveau: '', link: '', freiwillig: false, freiarbeit: false, offen: false }; },

        metaZuruecksetzen() {
            this.meta = {
                name: this.plan.name,
                gueltig_von: this.plan.gueltig_von,
                gueltig_bis: this.plan.gueltig_bis,
                selbsteinschaetzung: this.plan.selbsteinschaetzung,
                formatvorlage_id: this.plan.formatvorlage_id ?? '',
                klasse_id: this.plan.klasse_id ?? '',
                niveau: this.plan.niveau ?? '',
                vorlage_name: this.plan.vorlage_name ?? '',
            };
        },

        /** Führt eine Anfrage aus und übernimmt den gelieferten Plan-Stand. */
        async senden(method, url, body = null, erfolg = null) {
            this.busy = true;
            try {
                const daten = await anfrage(method, url, body);
                if (daten?.plan) {
                    this.stand(daten.plan);
                }
                if (erfolg || daten?.meldung) {
                    this.zeige(daten?.meldung || erfolg);
                }
                return daten;
            } catch (e) {
                if (e.status === 409 && e.daten?.plan) {
                    this.stand(e.daten.plan);
                    this.bearbeiten = null;
                    this.uebungBearbeiten = null;
                    this.zeige(e.message, 'warning');
                } else {
                    this.zeige(e.message, 'error');
                }
                return null;
            } finally {
                this.busy = false;
            }
        },

        stand(neuerPlan) {
            this.plan = neuerPlan;
            this.plan.faecher.forEach((f) => { if (!this.neu[f.id]) this.neu[f.id] = this.leereAufgabe(); });
            if (!this.metaOffen) this.metaZuruecksetzen();
        },

        async neuLaden() {
            const daten = await anfrage('GET', `${this.basis}/daten`).catch(() => null);
            if (daten?.plan) this.stand(daten.plan);
        },

        // ── Eckdaten ─────────────────────────────────────────────────────────
        async metaSpeichern() {
            const body = { ...this.meta, version: this.plan.version };
            ['formatvorlage_id', 'klasse_id', 'niveau'].forEach((k) => { if (body[k] === '') body[k] = null; });
            if (!this.plan.ist_klassenplan) delete body.klasse_id;
            if (!this.plan.ist_kinderplan) delete body.niveau;
            if (!this.plan.ist_vorlage) delete body.vorlage_name;
            if (this.plan.ist_kinderplan && this.plan.eltern) { delete body.gueltig_von; delete body.gueltig_bis; }
            const daten = await this.senden('PUT', this.basis, body, 'Gespeichert.');
            if (daten) this.metaOffen = false;
        },

        // ── Fächer ───────────────────────────────────────────────────────────
        async fachHinzufuegen() {
            if (!this.fachNeu) return;
            await this.senden('POST', `${this.basis}/faecher`, { wp_fach_id: Number(this.fachNeu) }, 'Fach hinzugefügt.');
            this.fachNeu = '';
        },
        async freiesFeldAnlegen() {
            const f = this.freiesFeld;
            if (!f.name.trim() && !f.symbol.trim()) return;
            const daten = await this.senden('POST', `${this.basis}/faecher`, { custom_name: f.name || null, symbol: f.symbol || null }, 'Freies Feld hinzugefügt.');
            if (daten) this.freiesFeld = { offen: false, name: '', symbol: '' };
        },
        fachBearbeitenStarten(fach) {
            this.fachBearbeiten = { id: fach.id, custom_name: fach.custom_name || '', symbol: fach.eigenes_symbol || '', frei: fach.frei, katalog_name: fach.katalog_name };
        },
        async fachSpeichern() {
            const f = this.fachBearbeiten;
            if (!f) return;
            const daten = await this.senden('PUT', `${this.basis}/faecher/${f.id}`, { custom_name: f.custom_name || null, symbol: f.symbol || null }, 'Fach gespeichert.');
            if (daten) this.fachBearbeiten = null;
        },
        async fachEntfernen(fach) {
            const text = fach.aufgaben.length
                ? `Fach „${fach.name}“ mit ${fach.aufgaben.length} Aufgabe(n) entfernen?`
                : `Fach „${fach.name}“ entfernen?`;
            if (!window.confirm(text)) return;
            await this.senden('DELETE', `${this.basis}/faecher/${fach.id}`, null, 'Fach entfernt.');
        },
        sortierbarFaecher(el) {
            if (!this.darf || el._wpSortable) return;
            el._wpSortable = Sortable.create(el, {
                handle: '[data-griff-fach]',
                draggable: '[data-fach-id]',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onStart: (evt) => { this._herkunft = { eltern: evt.item.parentNode, naechstes: evt.item.nextSibling }; },
                onEnd: (evt) => {
                    if (evt.oldIndex === evt.newIndex) return;
                    const ids = [...el.querySelectorAll(':scope > [data-fach-id]')].map((x) => Number(x.dataset.fachId));
                    this.domZurueck(evt.item);
                    this.plan.faecher.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id));
                    this.senden('POST', `${this.basis}/faecher/sortieren`, { ids });
                },
            });
        },

        // ── Aufgaben ─────────────────────────────────────────────────────────
        async aufgabeAnlegen(fach) {
            const f = this.neu[fach.id];
            if (!f || !f.aufgabe.trim()) return;
            const body = { aufgabe: f.aufgabe, dauer: f.dauer || null, niveau: f.niveau || null, link: f.link || null, freiwillig: !!f.freiwillig, freiarbeit: !!f.freiarbeit };
            const daten = await this.senden('POST', `${this.basis}/faecher/${fach.id}/aufgaben`, body);
            if (daten) {
                this.neu[fach.id] = { ...this.leereAufgabe(), offen: true };
                this.$nextTick(() => document.getElementById(`wp-neu-${fach.id}`)?.focus());
            }
        },
        aufgabeStarten(a) {
            this.bearbeiten = { id: a.id, aufgabe: a.aufgabe, dauer: a.dauer || '', niveau: a.niveau || '', link: a.link || '', freiwillig: !!a.freiwillig, freiarbeit: !!a.freiarbeit, basis: a.aufgabe };
            this.$nextTick(() => document.getElementById(`wp-edit-${a.id}`)?.focus());
        },
        async aufgabeSpeichern() {
            const b = this.bearbeiten;
            if (!b || !b.aufgabe.trim()) return;
            const daten = await this.senden('PUT', `${this.basis}/aufgaben/${b.id}`, {
                aufgabe: b.aufgabe, dauer: b.dauer || null, niveau: b.niveau || null, link: b.link || null,
                freiwillig: !!b.freiwillig, freiarbeit: !!b.freiarbeit, basis: b.basis,
            });
            if (daten) this.bearbeiten = null;
        },
        async aufgabeLoeschen(a) {
            if (!window.confirm('Aufgabe löschen?')) return;
            await this.senden('DELETE', `${this.basis}/aufgaben/${a.id}`);
        },
        async inBibliothek(a) {
            await this.senden('POST', `${this.basis}/aufgaben/${a.id}/bibliothek`);
        },
        sortierbarAufgaben(el) {
            if (!this.darf || el._wpSortable) return;
            el._wpSortable = Sortable.create(el, {
                group: 'wp-aufgaben',
                handle: '[data-griff-aufgabe]',
                draggable: '[data-aufgabe-id]',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onStart: (evt) => { this._herkunft = { eltern: evt.item.parentNode, naechstes: evt.item.nextSibling }; },
                onEnd: (evt) => {
                    if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
                    const eintraege = [];
                    const listen = evt.from === evt.to ? [evt.to] : [evt.from, evt.to];
                    listen.forEach((liste) => {
                        const fachId = Number(liste.dataset.fachListe);
                        liste.querySelectorAll(':scope > [data-aufgabe-id]').forEach((x) => {
                            eintraege.push({ id: Number(x.dataset.aufgabeId), wp_plan_fach_id: fachId });
                        });
                    });
                    this.domZurueck(evt.item);
                    this.senden('POST', `${this.basis}/aufgaben/sortieren`, { eintraege });
                },
            });
        },
        /** Sortable verschiebt DOM-Knoten – zurücksetzen, Alpine rendert aus dem Zustand neu. */
        domZurueck(item) {
            const h = this._herkunft;
            if (h?.eltern) h.eltern.insertBefore(item, h.naechstes && h.naechstes.parentNode === h.eltern ? h.naechstes : null);
            this._herkunft = null;
        },

        // ── Tägliche Übungen ─────────────────────────────────────────────────
        async uebungenSchalten(aktiv) {
            await this.senden('POST', `${this.basis}/uebungen/schalten`, { aktiv });
        },
        async uebungAnlegen() {
            if (!this.uebungNeu.trim()) return;
            const daten = await this.senden('POST', `${this.basis}/uebungen`, { aufgabe: this.uebungNeu, kuerzel: this.uebungNeuKuerzel || null });
            if (daten) { this.uebungNeu = ''; this.uebungNeuKuerzel = ''; }
        },
        async uebungSpeichern() {
            const u = this.uebungBearbeiten;
            if (!u || !u.aufgabe.trim()) return;
            const daten = await this.senden('PUT', `${this.basis}/uebungen/${u.id}`, { aufgabe: u.aufgabe, kuerzel: u.kuerzel || null, basis: u.basis });
            if (daten) this.uebungBearbeiten = null;
        },
        async uebungLoeschen(u) {
            if (!window.confirm('Übung löschen?')) return;
            await this.senden('DELETE', `${this.basis}/uebungen/${u.id}`);
        },
        sortierbarUebungen(el) {
            if (!this.darf || el._wpSortable) return;
            el._wpSortable = Sortable.create(el, {
                handle: '[data-griff-uebung]',
                draggable: '[data-uebung-id]',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onStart: (evt) => { this._herkunft = { eltern: evt.item.parentNode, naechstes: evt.item.nextSibling }; },
                onEnd: (evt) => {
                    if (evt.oldIndex === evt.newIndex) return;
                    const ids = [...el.querySelectorAll(':scope > [data-uebung-id]')].map((x) => Number(x.dataset.uebungId));
                    this.domZurueck(evt.item);
                    this.senden('POST', `${this.basis}/uebungen/sortieren`, { ids });
                },
            });
        },

        // ── Abgleich Kinderplan ↔ Klassenplan ────────────────────────────────
        async abgleichOeffnen(fachId = null) {
            this.abgleich = { vorschau: null, fachId, angepassteBehalten: true, eigeneBehalten: true, laedt: true };
            this.dialog = 'abgleich';
            try {
                const url = `${this.basis}/abgleich` + (fachId ? `?wp_fach_id=${fachId}` : '');
                const daten = await anfrage('GET', url);
                this.abgleich.vorschau = daten.vorschau;
            } catch (e) {
                this.zeige(e.message, 'error');
                this.dialog = null;
            } finally {
                this.abgleich.laedt = false;
            }
        },
        async abgleichen() {
            const daten = await this.senden('POST', `${this.basis}/abgleich`, {
                wp_fach_id: this.abgleich.fachId,
                angepasste_behalten: this.abgleich.angepassteBehalten,
                eigene_behalten: this.abgleich.eigeneBehalten,
                version: this.plan.version,
            });
            if (daten) this.dialog = null;
        },

        // ── Arbeitsblätter ───────────────────────────────────────────────────
        async dateienHochladen(dateien) {
            if (!dateien || !dateien.length) return;
            const form = new FormData();
            [...dateien].forEach((d) => form.append('files[]', d));
            this.hochladen = true;
            await this.senden('POST', `${this.basis}/dateien`, form, `${dateien.length} Datei(en) hochgeladen.`);
            this.hochladen = false;
            if (this.$refs.dateiInput) this.$refs.dateiInput.value = '';
        },
        async dateiEntfernen(d) {
            if (!window.confirm(`„${d.name}“ entfernen?`)) return;
            await this.senden('DELETE', `${this.basis}/dateien/${d.id}`, null, 'Datei entfernt.');
        },
        async erneutVersuchen() {
            await this.senden('POST', `${this.basis}/ablage/erneut`);
        },
        // Angepasste Arbeitsblätter: Abzeichen je Klassenplan-Datei und „Angepasste Fassung erzeugen“
        abInfo(d) {
            const info = this.plan.arbeitsblaetter?.dateien?.[d.id];
            if (!info) return null;
            const text = {
                erkennung: ['wird erkannt …', 'wp-badge-blue'],
                zu_pruefen: ['zur Prüfung', 'wp-badge-amber'],
                entwurf: ['zur Prüfung', 'wp-badge-amber'],
                fehler: ['Fehler', 'wp-badge-red'],
            }[info.status];
            if (text) return { ...info, text: '👓 ' + text[0], klasse: text[1] };
            return { ...info, text: '👓 angepasst für ' + info.fassungen + (info.fassungen === 1 ? ' Kind' : ' Kinder'), klasse: 'wp-badge-violet' };
        },
        async dateiAnpassen(d) {
            const daten = await this.senden('POST', d.anpassen_url);
            if (daten?.url && window.confirm((daten.meldung || '') + '\n\nJetzt zur Prüfung öffnen?')) {
                window.location.href = daten.url;
            }
        },
        dateiStatus(d) {
            return {
                lokal: ['Nur im MitarbeiterBoard', 'wp-badge-gray'],
                ausstehend: ['Wird hochgeladen', 'wp-badge-blue'],
                abgelegt: ['In Nextcloud', 'wp-badge-green'],
                fehler: ['Upload fehlgeschlagen', 'wp-badge-red'],
                fehlt: ['In Nextcloud nicht gefunden', 'wp-badge-red'],
            }[d.status] || [d.status, 'wp-badge-gray'];
        },
        dateiSymbol(d) {
            if ((d.mime_type || '').includes('pdf')) return '📄';
            if ((d.mime_type || '').startsWith('image/')) return '🖼️';
            if ((d.mime_type || '').includes('word') || (d.mime_type || '').includes('opendocument')) return '📝';
            return '📎';
        },
        ablageText() {
            const a = this.plan.ablage || {};
            if (!a.aktiv) return null;
            return {
                ausstehend: ['Ablage in Kürze', 'wp-badge-blue'],
                abgelegt: [`In Nextcloud abgelegt${a.abgelegt_at ? ' · ' + a.abgelegt_at : ''}`, 'wp-badge-green'],
                fehler: ['Ablage fehlgeschlagen', 'wp-badge-red'],
            }[a.status] || ['Noch nicht abgelegt', 'wp-badge-gray'];
        },

        // ── Nextcloud-Dateiauswahl ───────────────────────────────────────────
        async nextcloudOeffnen(pfad = '') {
            this.dialog = 'nextcloud';
            this.nextcloud.laedt = true;
            try {
                const daten = await anfrage('GET', `${this.basis}/nextcloud?pfad=${encodeURIComponent(pfad)}`);
                this.nextcloud.pfad = daten.pfad;
                this.nextcloud.eintraege = daten.eintraege;
            } catch (e) {
                this.zeige(e.message, 'error');
            } finally {
                this.nextcloud.laedt = false;
            }
        },
        nextcloudHoch() {
            const teile = this.nextcloud.pfad.split('/').filter(Boolean);
            teile.pop();
            this.nextcloudOeffnen(teile.join('/'));
        },
        nextcloudUmschalten(e) {
            const i = this.nextcloud.auswahl.indexOf(e.pfad);
            if (i >= 0) this.nextcloud.auswahl.splice(i, 1); else this.nextcloud.auswahl.push(e.pfad);
        },
        async nextcloudUebernehmen() {
            if (!this.nextcloud.auswahl.length) return;
            const daten = await this.senden('POST', `${this.basis}/nextcloud`, { pfade: this.nextcloud.auswahl }, 'Dateien verknüpft.');
            if (daten) { this.dialog = null; this.nextcloud.auswahl = []; }
        },

        // ── Aufgaben-Bibliothek ──────────────────────────────────────────────
        bibliothekOeffnen(fach) {
            this.bibliothek.zielFach = fach.id;
            this.bibliothek.fachId = fach.wp_fach_id || '';
            this.dialog = 'bibliothek';
            this.bibliothekSuchen();
        },
        async bibliothekSuchen() {
            this.bibliothek.laedt = true;
            const p = new URLSearchParams();
            if (this.bibliothek.q) p.set('q', this.bibliothek.q);
            if (this.bibliothek.fachId) p.set('wp_fach_id', this.bibliothek.fachId);
            if (this.config.klassenstufe) p.set('klassenstufe', this.config.klassenstufe);
            try {
                const daten = await anfrage('GET', `${this.config.basisUrl}/bibliothek?${p}`);
                this.bibliothek.liste = daten.bausteine;
            } catch (e) {
                this.zeige(e.message, 'error');
            } finally {
                this.bibliothek.laedt = false;
            }
        },
        async bausteinEinfuegen(b) {
            await this.senden('POST', `${this.basis}/faecher/${this.bibliothek.zielFach}/aufgaben`, {
                aufgabe: b.aufgabe, dauer: b.dauer, niveau: b.niveau, link: b.link,
            }, 'Aufgabe eingefügt.');
        },

        // ── Tagebuch-Aufgaben übernehmen ─────────────────────────────────────
        tagebuchStarten(t) {
            this.tagebuch.offen = t.id;
            this.tagebuch.text = t.title;
            this.tagebuch.dauer = '';
            this.tagebuch.fachId = this.plan.faecher[0]?.id || '';
        },
        async tagebuchUebernehmen(t) {
            if (!this.tagebuch.fachId || !this.tagebuch.text.trim()) return;
            const daten = await this.senden('POST', `${this.basis}/faecher/${this.tagebuch.fachId}/tagebuch`, {
                diary_task_id: t.id, aufgabe: this.tagebuch.text, dauer: this.tagebuch.dauer || null,
            }, 'Aufgabe aus dem Tagebuch übernommen.');
            if (daten) {
                this.tagebuch.aufgaben = this.tagebuch.aufgaben.filter((x) => x.id !== t.id);
                this.tagebuch.offen = null;
            }
        },
    }));

    // ─────────────────────────────────────────────────────────────────────────
    // Status je Kind (Matrix)
    // ─────────────────────────────────────────────────────────────────────────
    Alpine.data('wpMatrix', (matrix, config) => ({
        ...meldungen(),
        matrix,
        config,
        auswahl: [],          // ["aufgabeId:schuelerId"]
        notiz: null,          // { zelle, schuelerId, text }
        zeichen: STATUS_ZEICHEN,
        text: STATUS_TEXT,

        schluessel(zelle, zeile) { return `${zelle.aufgabe_id}:${zeile.schueler.id}`; },
        istAusgewaehlt(zelle, zeile) { return this.auswahl.includes(this.schluessel(zelle, zeile)); },
        titel(zelle, zeile, spalte) {
            let t = `${zeile.schueler.name} – ${spalte ? spalte.aufgabe : zelle.aufgabe}: ${this.text[zelle.status]}`;
            if (zelle.notiz) t += `\nNotiz: ${zelle.notiz}`;
            if (zelle.von) t += `\n${zelle.von}, ${zelle.am}`;
            return t;
        },
        zaehler(z) {
            const s = z.zusammenfassung;
            return `${s.erledigt} ✓ · ${s.teilweise} ◐ von ${s.gesamt}`;
        },
        zaehlerKlasse(z) {
            const s = z.zusammenfassung;
            if (!s.gesamt) return 'is-leer';
            return s.erledigt === s.gesamt ? 'is-fertig' : 'is-offen';
        },

        klick(evt, zelle, zeile) {
            if (evt.shiftKey || evt.ctrlKey || evt.metaKey) {
                const k = this.schluessel(zelle, zeile);
                const i = this.auswahl.indexOf(k);
                if (i >= 0) this.auswahl.splice(i, 1); else this.auswahl.push(k);
                return;
            }
            this.setzen(zelle, zeile.schueler.id, STATUS_FOLGE[zelle.status], zelle.notiz);
        },

        async setzen(zelle, schuelerId, status, notiz) {
            const vorher = { status: zelle.status, notiz: zelle.notiz };
            zelle.status = status;
            try {
                const daten = await anfrage('POST', this.config.statusUrl, {
                    wp_aufgabe_id: zelle.aufgabe_id ?? zelle.id, schueler_id: schuelerId, status, notiz: notiz || null,
                });
                Object.assign(zelle, { status: daten.eintrag.status, notiz: daten.eintrag.notiz, von: daten.eintrag.von, am: daten.eintrag.am });
                this.zusammenfassungNeu();
            } catch (e) {
                Object.assign(zelle, vorher);
                this.zeige(e.message, 'error');
            }
        },

        notizOeffnen(zelle, zeile) {
            this.notiz = { zelle, schuelerId: zeile.schueler.id, name: zeile.schueler.name, status: zelle.status, text: zelle.notiz || '' };
        },
        async notizSpeichern() {
            const n = this.notiz;
            await this.setzen(n.zelle, n.schuelerId, n.status, n.text);
            this.notiz = null;
        },

        async mehrere(status, zellen = null) {
            const liste = zellen || this.auswahl.map((k) => {
                const [a, s] = k.split(':').map(Number);
                return { wp_aufgabe_id: a, schueler_id: s };
            });
            if (!liste.length) return;
            try {
                const daten = await anfrage('POST', this.config.mehrereUrl, { status, zellen: liste });
                this.matrix = daten.matrix;
                this.auswahl = [];
                this.zeige(`${daten.anzahl} Einträge auf „${this.text[status]}“ gesetzt.`);
            } catch (e) {
                this.zeige(e.message, 'error');
            }
        },
        alleErledigt(index) {
            const zellen = this.matrix.zeilen
                .map((z) => (z.zellen[index] ? { wp_aufgabe_id: z.zellen[index].aufgabe_id, schueler_id: z.schueler.id } : null))
                .filter(Boolean);
            this.mehrere('erledigt', zellen);
        },

        zusammenfassungNeu() {
            this.matrix.zeilen.forEach((z) => {
                const alle = z.zellen.filter(Boolean).concat(z.weitere);
                z.zusammenfassung = {
                    erledigt: alle.filter((x) => x.status === 'erledigt').length,
                    teilweise: alle.filter((x) => x.status === 'teilweise').length,
                    gesamt: alle.length,
                };
            });
        },
    }));

    // ─────────────────────────────────────────────────────────────────────────
    // Einstellungen: Live-Vorschau der Ordnerstruktur
    // ─────────────────────────────────────────────────────────────────────────
    Alpine.data('wpEinstellungen', (werte, config) => ({
        werte,
        config,
        beispiel: [],
        fehler: {},
        _timer: null,
        init() {
            this.vorschau();
            this.$watch('werte', () => {
                clearTimeout(this._timer);
                this._timer = setTimeout(() => this.vorschau(), 300);
            }, { deep: true });
        },
        einfuegen(feld, platzhalter) {
            this.werte[feld] = (this.werte[feld] || '') + `{${platzhalter}}`;
        },
        async vorschau() {
            try {
                const daten = await anfrage('POST', this.config.vorschauUrl, this.werte);
                this.beispiel = daten.beispiel;
                this.fehler = {};
            } catch (e) {
                this.beispiel = [];
                this.fehler = e.daten?.fehler || {};
            }
        },
    }));

    // ─────────────────────────────────────────────────────────────────────────
    // Aufgaben-Bibliothek (Seite)
    // ─────────────────────────────────────────────────────────────────────────
    Alpine.data('wpBibliothek', (config) => ({
        ...meldungen(),
        config,
        q: '',
        fachId: '',
        klassenstufe: '',
        liste: [],
        laedt: false,
        neu: { aufgabe: '', dauer: '', niveau: '', link: '', wp_fach_id: '', klassenstufe: '' },
        init() { this.suchen(); },
        async suchen() {
            this.laedt = true;
            const p = new URLSearchParams();
            if (this.q) p.set('q', this.q);
            if (this.fachId) p.set('wp_fach_id', this.fachId);
            if (this.klassenstufe) p.set('klassenstufe', this.klassenstufe);
            try {
                const daten = await anfrage('GET', `${this.config.url}?${p}`);
                this.liste = daten.bausteine;
            } catch (e) {
                this.zeige(e.message, 'error');
            } finally {
                this.laedt = false;
            }
        },
        async anlegen() {
            if (!this.neu.aufgabe.trim()) return;
            const body = { ...this.neu };
            Object.keys(body).forEach((k) => { if (body[k] === '') body[k] = null; });
            try {
                const daten = await anfrage('POST', this.config.url, body);
                this.zeige(daten.meldung);
                this.neu = { aufgabe: '', dauer: '', niveau: '', link: '', wp_fach_id: this.neu.wp_fach_id, klassenstufe: this.neu.klassenstufe };
                this.suchen();
            } catch (e) {
                this.zeige(e.message, 'error');
            }
        },
        async loeschen(b) {
            if (!window.confirm('Aus der Bibliothek entfernen?')) return;
            try {
                await anfrage('DELETE', `${this.config.url}/${b.id}`);
                this.liste = this.liste.filter((x) => x.id !== b.id);
            } catch (e) {
                this.zeige(e.message, 'error');
            }
        },
    }));

    // ─────────────────────────────────────────────────────────────────────────
    // Formatvorlagen-Editor mit Live-Vorschau
    // ─────────────────────────────────────────────────────────────────────────
    Alpine.data('formatvorlageEditor', () => ({
        previewLoading: false,

        init() {
            this.$nextTick(() => {
                const frame = document.getElementById('wp-formatvorlage-preview');
                if (frame && !frame.getAttribute('src')) {
                    this.updatePreview();
                }
            });
        },

        getForm() {
            return document.getElementById('wp-formatvorlage-form')
                || (this.$el.tagName === 'FORM' ? this.$el : this.$el.querySelector('form'));
        },

        async updatePreview() {
            const form = this.getForm();
            const previewUrl = form?.dataset.previewUrl;
            const previewFrame = document.getElementById('wp-formatvorlage-preview');
            if (!form || !previewUrl || !previewFrame) return;

            this.previewLoading = true;
            const config = {};
            for (const [key, value] of new FormData(form).entries()) {
                config[key] = value;
            }

            try {
                const response = await fetch(previewUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify(config),
                });
                if (response.ok) {
                    previewFrame.srcdoc = await response.text();
                    previewFrame.removeAttribute('src');
                }
            } catch (err) {
                console.error('Vorschau fehlgeschlagen:', err);
            } finally {
                this.previewLoading = false;
            }
        },
    }));
}

if (window.Alpine) {
    registerWochenplanComponents(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerWochenplanComponents(window.Alpine));
}
