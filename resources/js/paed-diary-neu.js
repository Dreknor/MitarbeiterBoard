/**
 * Pädagogisches Tagebuch – neue Ansicht (Vite-Entrypoint)
 *
 * Läuft parallel zur bisherigen Ansicht (paed-diary-v2.js) und nutzt deren Store und Komponenten
 * (gleiche Endpunkte, gleiche Logik). Ergänzt werden:
 *   - diaryNeu():       Wochenansicht mit wählbarem Layout (klassisch | kompakt | karten)
 *   - notizSeite():     Notiz-Editor als Seitenleiste (Logik aus noteEditor())
 *   - schuelerAkte():   neue Schülerakte (Verlauf nach Wochen, Seitenleiste)
 *   - reflexionNetz():  Spinnennetzdiagramm der Reflexion (SVG, ohne externe Bibliothek)
 *
 * Darstellungs-Einstellungen werden pro Person gespeichert (POST /paed-diary/settings/darstellung).
 */

import '../css/paed-diary-neu.css';
import { registerDiaryStore } from './paed-diary-v2/store.js';
import { diaryTableMethods } from './paed-diary-v2/diary-table.js';
import { registerDiaryTable } from './paed-diary-v2/diary-table.js';
import { registerNoteEditor, noteEditorMethods } from './paed-diary-v2/note-editor.js';
import { registerColumnsManager } from './paed-diary-v2/columns-manager.js';
import { registerTaskPanel } from './paed-diary-v2/task-panel.js';
import { registerStageDropdown } from './paed-diary-v2/stage-dropdown.js';
import { registerAppointmentManager } from './paed-diary-v2/appointment-manager.js';
import { registerGroupManager } from './paed-diary-v2/group-manager.js';
import { csrfToken } from './paed-diary-v2/utils.js';

/** Kombiniert Objekte inkl. Gettern (Spread würde Getter einmalig auswerten). */
function kombiniere(...teile) {
    const ziel = {};
    teile.forEach(teil => Object.defineProperties(ziel, Object.getOwnPropertyDescriptors(teil)));
    return ziel;
}

/** Speichert einzelne Darstellungs-Werte für die angemeldete Person. */
async function speichereDarstellung(werte) {
    const resp = await fetch('/paed-diary/settings/darstellung', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(werte),
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    return (await resp.json()).darstellung;
}

const LAYOUTS = ['klassisch', 'kompakt', 'karten'];

function registerNeu(Alpine) {
    // ── Wochenansicht ──────────────────────────────────────────────
    Alpine.data('diaryNeu', (darstellung = {}) => kombiniere(diaryTableMethods(), {
        darstellung: { layout: 'klassisch', schrift: 'standard', kategorie_farben: true, pausierte: false, ...darstellung },
        darstellungOffen: false,
        filterOffen: false,

        initNeu() {
            const store = this.$store.diary;
            store.showPaused = !!this.darstellung.pausierte;
            // Auf dem Handy ist die breite Tabelle unpraktisch: dort Tageskarten statt „Klassisch“
            // zeigen (nur für diese Sitzung, die gespeicherte Wahl bleibt unverändert)
            if (window.innerWidth < 768 && this.darstellung.layout === 'klassisch') {
                this.darstellung.layout = 'karten';
            }
        },

        get wrapperKlassen() {
            return {
                ['pdn--' + this.darstellung.layout]: true,
                'pdn--gross': this.darstellung.schrift === 'gross',
                'pdn--farben': !!this.darstellung.kategorie_farben,
            };
        },

        async setze(schluessel, wert) {
            const vorher = this.darstellung[schluessel];
            this.darstellung[schluessel] = wert;
            if (schluessel === 'pausierte') this.$store.diary.showPaused = !!wert;
            try {
                await speichereDarstellung({ [schluessel]: wert });
            } catch (_) {
                this.darstellung[schluessel] = vorher;
                if (schluessel === 'pausierte') this.$store.diary.showPaused = !!vorher;
                this.$store.diary.showError('Die Einstellung wurde nicht gespeichert. Bitte erneut versuchen.');
            }
        },

        layoutWaehlen(layout) {
            if (LAYOUTS.includes(layout)) this.setze('layout', layout);
        },

        /** Inline-Style für Kategoriefarben einer Notiz */
        farbe(entry) {
            return entry?.category_color ? `--kat:${entry.category_color}` : '';
        },

        /** Alle Notizen einer Zelle, nach Kategorie sortiert (für Kompakt und Karten) */
        notizen(stuId, date) {
            return this.getGroupedEntries(stuId, date).flatMap(g => g.entries);
        },

        neueNotiz(stuId, date) {
            this.$dispatch('diary-new-entry', { date, stuId });
        },

        bearbeiten(entry, date) {
            this.$dispatch('diary-edit-entry', { entryId: entry.id, date });
        },

        ampelKlasse(wert) {
            return { '1': 'is-ja', '2': 'is-mitte', '3': 'is-nein' }[wert] || '';
        },

        boolKlasse(wert) {
            return wert === '1' ? 'is-ja' : '';
        },

        tagName(day) {
            return day?.label || '';
        },

        /** Kategorien, die in der Woche tatsächlich vorkommen (Legende im Kompakt-Layout) */
        get genutzteKategorien() {
            const store = this.$store.diary;
            const ids = new Set((store.entries || []).map(e => e.category_id).filter(Boolean));
            return (store.categories || []).filter(c => ids.has(c.id));
        },
    }));

    // ── Notiz-Editor als Seitenleiste ─────────────────────────────
    // Gleiche Logik wie noteEditor() (Speichern, Löschen, Schülerauswahl), nur andere Darstellung.
    Alpine.data('notizSeite', () => kombiniere(noteEditorMethods(), {
        init() {
            this.$watch('editorOpen', offen => {
                document.body.style.overflow = offen ? 'hidden' : '';
                if (offen) this.$nextTick(() => this.$refs.notizText?.focus());
            });
        },
    }));
}

// ── Schülerakte ──────────────────────────────────────────────────
function registerSchuelerAkte(Alpine) {
    Alpine.data('schuelerAkte', (konfig) => ({
        schuelerId: konfig.schuelerId,
        darstellung: konfig.darstellung || {},
        tab: 'verlauf',
        laden: false,
        fehler: '',
        daten: null,
        von: konfig.von,
        bis: konfig.bis,
        zeitraum: konfig.zeitraum,
        kategorie: '',
        suche: '',
        vertraulicheZeigen: true,
        zielText: '',

        init() {
            this.ladeDaten();
        },

        async neuesZiel() {
            const text = this.zielText.trim();
            if (!text) return;
            try {
                const resp = await fetch(`/paed-diary/schueler/${this.schuelerId}/goals`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({ goal_text: text }),
                });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok || !json.goal) throw new Error(json.message || 'Das Ziel wurde nicht gespeichert.');
                this.daten.goals = [json.goal, ...(this.daten.goals || [])];
                this.zielText = '';
            } catch (e) {
                this.fehler = e.message;
            }
        },

        async zielErreicht(ziel, erreicht) {
            try {
                const resp = await fetch(`/paed-diary/goals/${ziel.id}/achieve`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({ achieved: erreicht }),
                });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok) throw new Error(json.message || 'Das Ziel wurde nicht aktualisiert.');
                ziel.achieved_at = json.achieved_at;
                ziel.formatted_achieved_at = json.formatted_achieved_at;
                ziel.achieved_by = json.achieved_by;
            } catch (e) {
                this.fehler = e.message;
            }
        },

        setzeZeitraum(art) {
            this.zeitraum = art;
            const heute = new Date();
            const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            if (art === '30tage') {
                const von = new Date(); von.setDate(heute.getDate() - 30);
                this.von = iso(von);
            } else if (art === 'halbjahr') {
                this.von = konfig.halbjahrBeginn;
            } else if (art === 'schuljahr') {
                this.von = konfig.schuljahrBeginn;
            }
            this.bis = iso(heute);
            this.ladeDaten();
        },

        async ladeDaten() {
            this.laden = true;
            this.fehler = '';
            try {
                const params = new URLSearchParams({ date_from: this.von, date_to: this.bis });
                const resp = await fetch(`/paed-diary/schueler/${this.schuelerId}/data?${params}`, { headers: { 'Accept': 'application/json' } });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok) {
                    const details = json.errors ? Object.values(json.errors).flat().join(' ') : '';
                    throw new Error(details || json.message || 'Die Daten konnten nicht geladen werden.');
                }
                this.daten = json;
            } catch (e) {
                this.fehler = e.message;
            } finally {
                this.laden = false;
            }
        },

        get eintraege() {
            const liste = this.daten?.entries || [];
            const suche = this.suche.trim().toLowerCase();
            return liste.filter(e => {
                if (!this.vertraulicheZeigen && e.dossier_only) return false;
                if (this.kategorie === 'none' && e.category_id) return false;
                if (this.kategorie && this.kategorie !== 'none' && String(e.category_id) !== this.kategorie) return false;
                if (!suche) return true;
                return [e.content, e.user, e.category].join(' ').toLowerCase().includes(suche);
            });
        },

        /** Einträge und Stufenwechsel als Verlauf, nach Kalenderwochen gruppiert (neueste zuerst) */
        get verlauf() {
            const punkte = this.eintraege.map(e => ({ art: 'eintrag', datum: e.date_from || e.date, e }));
            (this.daten?.stage_history || []).forEach(h => {
                const datum = (h.at || '').slice(0, 10);
                if (datum && datum >= this.von && datum <= this.bis && !this.suche && !this.kategorie) {
                    punkte.push({ art: 'stufe', datum, h });
                }
            });
            (konfig.reflexionen || []).forEach(r => {
                if (r.date && r.date >= this.von && r.date <= this.bis && !this.suche && !this.kategorie) {
                    punkte.push({ art: 'reflexion', datum: r.date, r });
                }
            });
            punkte.sort((a, b) => b.datum.localeCompare(a.datum));

            const wochen = [];
            punkte.forEach(p => {
                const kw = this.kalenderwoche(p.datum);
                let woche = wochen[wochen.length - 1];
                if (!woche || woche.kw !== kw.schluessel) {
                    woche = { kw: kw.schluessel, titel: kw.titel, punkte: [] };
                    wochen.push(woche);
                }
                woche.punkte.push(p);
            });
            return wochen;
        },

        kalenderwoche(iso) {
            const [y, m, d] = iso.split('-').map(Number);
            const datum = new Date(y, m - 1, d);
            const montag = new Date(datum); montag.setDate(datum.getDate() - ((datum.getDay() + 6) % 7));
            const freitag = new Date(montag); freitag.setDate(montag.getDate() + 4);
            const donnerstag = new Date(montag); donnerstag.setDate(montag.getDate() + 3);
            const jahresbeginn = new Date(donnerstag.getFullYear(), 0, 1);
            const kw = 1 + Math.round(((donnerstag - jahresbeginn) / 86400000 - 3 + ((jahresbeginn.getDay() + 6) % 7)) / 7);
            const f = x => String(x.getDate()).padStart(2, '0') + '.' + String(x.getMonth() + 1).padStart(2, '0') + '.';
            return { schluessel: donnerstag.getFullYear() + '-' + kw, titel: `KW ${kw} · ${f(montag)}–${f(freitag)}` };
        },

        datum(iso) {
            if (!iso) return '';
            const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' });
        },

        datumLang(iso) {
            if (!iso) return '';
            const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('de-DE');
        },

        farbe(e) {
            return e?.category_color ? `--kat:${e.category_color}` : '';
        },

        get offeneAufgaben() {
            return (this.daten?.tasks || []).filter(t => t.status !== 'closed').length;
        },

        get aktuellesZiel() {
            const ziele = this.daten?.goals || [];
            return ziele.find(z => !z.achieved_at) || null;
        },

        spaltenWert(spalte, datum) {
            const wert = this.daten?.column_values?.[datum]?.[spalte.id]?.value ?? '';
            if (spalte.type === 'boolean') return wert === '1' ? 'Ja' : '–';
            if (spalte.type === 'ampel') return { '1': 'Ja', '2': 'In Bearbeitung', '3': 'Nein' }[wert] || '–';
            return wert || '–';
        },

        get spaltenTage() {
            return Object.keys(this.daten?.column_values || {}).sort().reverse();
        },

        exportUrl(art) {
            const params = new URLSearchParams({ date_from: this.von, date_to: this.bis });
            if (art === 'excel') {
                if (this.kategorie && this.kategorie !== 'none') params.append('category', this.kategorie);
                if (this.suche.trim()) params.append('search', this.suche.trim());
                return `/paed-diary/schueler/${this.schuelerId}/export/excel?${params}`;
            }
            params.append('include_confidential', this.vertraulicheZeigen ? '1' : '0');
            return `/paed-diary/schueler/${this.schuelerId}/dossier.pdf?${params}`;
        },
    }));

    // ── Spinnennetz der Reflexion (SVG) ─────────────────────────────
    Alpine.data('reflexionNetz', (reflexionen) => ({
        reflexionen: reflexionen || [],
        auswahl: (reflexionen || []).length - 1,
        groesse: 360,
        rand: 70,

        get aktuell() {
            return this.reflexionen[this.auswahl] || null;
        },

        get fragen() {
            return this.aktuell?.questions || [];
        },

        punkt(index, wert) {
            const n = Math.max(this.fragen.length, 3);
            const winkel = (Math.PI * 2 * index) / n - Math.PI / 2;
            const radius = ((this.groesse / 2 - this.rand) * (wert || 0)) / 5;
            const mitte = this.groesse / 2;
            return [mitte + Math.cos(winkel) * radius, mitte + Math.sin(winkel) * radius];
        },

        polygon(wertFeld) {
            return this.fragen.map((f, i) => this.punkt(i, f[wertFeld]).join(',')).join(' ');
        },

        ring(stufe) {
            return this.fragen.map((_, i) => this.punkt(i, stufe).join(',')).join(' ');
        },

        achse(i) {
            const [x, y] = this.punkt(i, 5);
            return { x1: this.groesse / 2, y1: this.groesse / 2, x2: x, y2: y };
        },

        beschriftung(i) {
            const [x, y] = this.punkt(i, 6.1);
            const mitte = this.groesse / 2;
            const anker = Math.abs(x - mitte) < 8 ? 'middle' : (x > mitte ? 'start' : 'end');
            const text = this.fragen[i]?.text || '';
            return { x, y, anker, text: text.length > 26 ? text.slice(0, 25) + '…' : text, voll: text };
        },

        get hatSelbst() { return this.fragen.some(f => f.self); },
        get hatLehrkraft() { return this.fragen.some(f => f.teacher); },
    }));
}

// ── Graduierung: Einschätzung der Lehrkraft als Matrix ─────────────
function registerBewertungsMatrix(Alpine) {
    Alpine.data('bewertungsMatrix', (konfig) => ({
        fragen: konfig.fragen || [],
        schueler: (konfig.schueler || []).map(s => ({
            ...s,
            // leere Collections kommen als Array – für Zugriff per Fragen-ID in Objekte umwandeln
            selbst: Array.isArray(s.selbst) ? {} : { ...s.selbst },
            lehrkraft: Array.isArray(s.lehrkraft) ? {} : { ...s.lehrkraft },
            kommentare: Array.isArray(s.kommentare) ? {} : { ...s.kommentare },
        })),
        abgeschlossen: !!konfig.abgeschlossen,
        status: {},          // 'schuelerId-frageId' => speichert | ok | fehler
        warteschlangen: {},  // pro Zelle nacheinander speichern (kein globales Sperr-Flag)
        meldung: '',
        abschlussFragen: false,

        istVollstaendig(s) {
            return this.fragen.every(f => s.lehrkraft[f.id]);
        },

        get vollstaendig() {
            return this.schueler.filter(s => s.abgeschlossen || this.istVollstaendig(s)).length;
        },

        get unvollstaendig() {
            return this.schueler.filter(s => !s.abgeschlossen && !this.istVollstaendig(s)).map(s => s.name);
        },

        zeilenStatus(s) {
            const werte = this.fragen.map(f => this.status[s.id + '-' + f.id]).filter(Boolean);
            if (werte.includes('fehler')) return 'fehler';
            if (werte.includes('speichert')) return 'speichert';
            return werte.length || this.fragen.some(f => s.lehrkraft[f.id]) ? 'ok' : '';
        },

        bewerten(s, f, wert) {
            s.lehrkraft[f.id] = wert;
            const schluessel = s.id + '-' + f.id;
            this.status[schluessel] = 'speichert';
            const lauf = () => this.speichern(s, f, schluessel);
            this.warteschlangen[schluessel] = (this.warteschlangen[schluessel] || Promise.resolve()).then(lauf, lauf);
        },

        async speichern(s, f, schluessel) {
            try {
                const resp = await fetch(konfig.speichernUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({
                        session_id: konfig.sessionId,
                        schueler_id: s.id,
                        question_id: f.id,
                        teacher_rating: s.lehrkraft[f.id],
                        comment: s.kommentare[f.id] || null,
                    }),
                });
                if (!resp.ok) {
                    const json = await resp.json().catch(() => ({}));
                    throw new Error(json.message || 'Die Einschätzung wurde nicht gespeichert.');
                }
                this.status[schluessel] = 'ok';
            } catch (e) {
                this.status[schluessel] = 'fehler';
                this.meldung = e.message;
            }
        },

        erneut(s) {
            this.fragen.forEach(f => {
                if (this.status[s.id + '-' + f.id] === 'fehler' && s.lehrkraft[f.id]) this.bewerten(s, f, s.lehrkraft[f.id]);
            });
        },

        async abschliessen() {
            this.abschlussFragen = false;
            await Promise.all(Object.values(this.warteschlangen));
            try {
                const resp = await fetch(konfig.abschliessenUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok) throw new Error(json.message || 'Die Session wurde nicht abgeschlossen.');
                window.location.href = konfig.zurueckUrl;
            } catch (e) {
                this.meldung = e.message;
            }
        },
    }));
}

function registerAll(Alpine) {
    registerBewertungsMatrix(Alpine);
    registerDiaryStore(Alpine);
    registerDiaryTable(Alpine);
    registerNoteEditor(Alpine);
    registerColumnsManager(Alpine);
    registerTaskPanel(Alpine);
    registerStageDropdown(Alpine);
    registerAppointmentManager(Alpine);
    registerGroupManager(Alpine);
    registerNeu(Alpine);
    registerSchuelerAkte(Alpine);
}

if (window.Alpine) {
    registerAll(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => {
        if (window.Alpine) registerAll(window.Alpine);
    });
}
