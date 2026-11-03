/**
 * Pädagogisches Tagebuch – neue Ansicht (Vite-Entrypoint)
 *
 * Läuft parallel zur bisherigen Ansicht (paed-diary-v2.js) und nutzt deren Store und Komponenten
 * (gleiche Endpunkte, gleiche Logik). Ergänzt werden:
 *   - diaryNeu():       Wochenansicht mit wählbarem Layout (klassisch | kompakt | karten)
 *   - notizSeite():     Notiz-Editor als Seitenleiste (Logik aus noteEditor())
 *   - schuelerAkte():   neue Schülerakte (Verlauf nach Wochen, Seitenleiste)
 *   - reflexionNetz():  Spinnennetzdiagramm der Reflexion (SVG, ohne externe Bibliothek)
 *   - elterngespraecheAkte(): Elterngespräche der Schülerakte (aus paed-diary-v2/elterngespraeche.js)
 *
 * Darstellungs-Einstellungen werden pro Person gespeichert (POST /paed-diary/settings/darstellung).
 */

import '../css/paed-diary-neu.css';
import '../css/paed-diary-neu-graduierung.css';
import { registerDiaryStore } from './paed-diary-v2/store.js';
import { diaryTableMethods } from './paed-diary-v2/diary-table.js';
import { registerDiaryTable } from './paed-diary-v2/diary-table.js';
import { registerNoteEditor, noteEditorMethods } from './paed-diary-v2/note-editor.js';
import { registerColumnsManager, columnsManagerMethods } from './paed-diary-v2/columns-manager.js';
import { registerTaskPanel, taskPanelMethods } from './paed-diary-v2/task-panel.js';
import { registerStageDropdown } from './paed-diary-v2/stage-dropdown.js';
import { registerAppointmentManager } from './paed-diary-v2/appointment-manager.js';
import { registerGroupManager } from './paed-diary-v2/group-manager.js';
import { registerElterngespraeche } from './paed-diary-v2/elterngespraeche.js';
import { csrfToken } from './paed-diary-v2/utils.js';
import { registerGraduierung } from './paed-diary-neu/graduierung.js';

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

        // ── Stufen-Symbol ──────────────────────────────────────────
        kaputteBilder: {},

        /** Bild der Stufe, sofern vorhanden und ladbar */
        stufeBild(stu) {
            const url = stu?.stage?.image_url;
            return url && !this.kaputteBilder[url] ? url : null;
        },

        bildFehlt(stu) {
            const url = stu?.stage?.image_url;
            if (url) this.kaputteBilder = { ...this.kaputteBilder, [url]: true };
        },

        /** Kürzel im Kreis: Symbol (max. 2 Zeichen) oder Anfangsbuchstaben des Stufennamens */
        stufeKurz(stu) {
            const stage = stu?.stage;
            if (!stage) return '+';
            const symbol = (stage.symbol || '').trim();
            if (symbol && symbol.length <= 2) return symbol;
            const woerter = (stage.name || '?').trim().split(/\s+/);
            return (woerter.length > 1 ? woerter[0][0] + woerter[1][0] : woerter[0].slice(0, 2)).toUpperCase();
        },

        stufeKlasse(stu) {
            if (!stu?.stage) return 'pdn-stage--leer';
            return this.stufeBild(stu) ? 'pdn-stage--bild' : 'pdn-stage--gesetzt';
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

        /** Kurzform einer Spaltengruppe für die Zelle (voller Name im Tooltip) */
        kuerzel(name) {
            const text = (name || '').trim();
            return text.length <= 4 ? text : text.slice(0, 3);
        },

        /** Spaltengruppen der Woche mit ihren Spaltennamen (Legende über der Tabelle) */
        get spaltenLegende() {
            const gruppen = new Map();
            (this.$store.diary.columns || []).forEach(c => {
                const name = c.category || 'Ohne Gruppe';
                if (!gruppen.has(name)) gruppen.set(name, []);
                if (!gruppen.get(name).includes(c.name)) gruppen.get(name).push(c.name);
            });
            return [...gruppen.entries()]
                .map(([name, spalten]) => ({ name, spalten }))
                .sort((a, b) => (a.name === 'Ohne Gruppe') - (b.name === 'Ohne Gruppe') || a.name.localeCompare(b.name, 'de'));
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
    // ── Spaltenverwaltung als Seitenleiste (Logik aus columnsManager()) ──
    Alpine.data('spaltenSeite', () => kombiniere(columnsManagerMethods(), {
        /** Spalte, deren Deaktivierung gerade bestätigt wird */
        bestaetigen: null,
        /** Spalte, deren Gruppe gerade geändert wird */
        gruppeFuer: null,
        gruppeNeu: '',
        typen: { boolean: 'Ja/Nein', ampel: 'Ampel', text: 'Text', number: 'Zahl' },

        schliessen() {
            this.$store.diary.columnsCardOpen = false;
            this.bestaetigen = null;
            this.gruppeFuer = null;
            this.copyOpenFor = null;
        },

        async deaktivieren(col) {
            await this.deactivateColumn(col.id, true);
            this.bestaetigen = null;
        },

        gruppeAendern(col) {
            this.gruppeFuer = col.id;
            this.gruppeNeu = col.category || '';
        },

        async gruppeSpeichern(col) {
            await this.updateColumnCategory(col.id, this.gruppeNeu.trim());
            this.gruppeFuer = null;
            // Gruppen-Überschriften in der Woche aktualisieren
            this.$store.diary.loadWeek();
        },
    }));

    // ── Aufgaben als Seitenleiste (Logik aus taskPanel(), ohne Bootstrap-Modal) ──
    Alpine.data('aufgabeSeite', () => kombiniere(taskPanelMethods(), {
        offen: false,
        fehler: '',

        /** Neue Aufgabe; optional mit vorausgewähltem Schüler ({ stuId }) */
        openCreateModal(detail = {}) {
            this.editingTaskId = null;
            this.taskFormTitle = '';
            this.taskFormDescription = '';
            this.taskFormDueDate = '';
            this.taskFormHighlighted = false;
            this.taskFormSchuelerIds = detail?.stuId ? [String(detail.stuId)] : [];
            this.oeffnen();
        },

        openEditModal(task) {
            this.editingTaskId = task.id;
            this.taskFormTitle = task.title || '';
            this.taskFormDescription = task.description || '';
            this.taskFormDueDate = task.due_date || '';
            this.taskFormHighlighted = !!task.highlighted;
            this.taskFormSchuelerIds = task.schueler_id ? [String(task.schueler_id)] : [];
            this.oeffnen();
        },

        oeffnen() {
            this.fehler = '';
            this.offen = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.aufgabeTitel?.focus());
        },

        schliessen() {
            this.offen = false;
            document.body.style.overflow = '';
        },

        /** Schnellauswahl Fälligkeit: Tage ab heute bzw. nächster Montag */
        faellig(art) {
            const d = new Date();
            if (art === 'morgen') d.setDate(d.getDate() + 1);
            if (art === 'montag') d.setDate(d.getDate() + (((8 - d.getDay()) % 7) || 7));
            if (art === 'zweiWochen') d.setDate(d.getDate() + 14);
            this.taskFormDueDate = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        },

        schuelerDerKlasse(klasseId) {
            return (this.$store.diary.schueler || []).filter(s => s.klasse_id === klasseId);
        },

        alleDerKlasseGewaehlt(klasseId) {
            const ids = this.schuelerDerKlasse(klasseId).map(s => String(s.id));
            return ids.length > 0 && ids.every(id => this.taskFormSchuelerIds.includes(id));
        },

        klasseUmschalten(klasseId) {
            const ids = this.schuelerDerKlasse(klasseId).map(s => String(s.id));
            this.taskFormSchuelerIds = this.alleDerKlasseGewaehlt(klasseId)
                ? this.taskFormSchuelerIds.filter(id => !ids.includes(id))
                : [...new Set([...this.taskFormSchuelerIds, ...ids])];
        },

        async saveTask() {
            if (this.taskSaving) return;
            if (!this.taskFormTitle.trim()) return;
            if (!this.editingTaskId && !this.taskFormSchuelerIds.length) {
                this.fehler = 'Bitte mindestens einen Schüler auswählen.';
                return;
            }
            this.taskSaving = true;
            this.fehler = '';
            const store = this.$store.diary;
            const daten = {
                title: this.taskFormTitle.trim(),
                description: this.taskFormDescription,
                due_date: this.taskFormDueDate || null,
                highlighted: this.taskFormHighlighted,
                schueler_ids: this.taskFormSchuelerIds,
                klasse_id: store.selectedKlasseId,
            };
            if (store.selectedGroupId) daten.group_id = store.selectedGroupId;

            try {
                await jsonAnfrage(this.editingTaskId ? `/paed-diary/task/${this.editingTaskId}` : '/paed-diary/task',
                    this.editingTaskId ? 'PUT' : 'POST', daten);
                this.schliessen();
                store.loadWeek();
            } catch (e) {
                this.fehler = e.message;
            } finally {
                this.taskSaving = false;
            }
        },
    }));

    Alpine.data('notizSeite', () => kombiniere(noteEditorMethods(), {
        // Die Seitenleiste liegt fest am Rand – kein Sprung ans Seitenende beim Öffnen
        zumEditorScrollen: false,

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

        /** Wechselt zum Tab „Elterngespräche“ und öffnet das Gespräch im Editor. */
        gespraechOeffnen(id) {
            this.tab = 'gespraeche';
            this.$nextTick(() => window.dispatchEvent(new CustomEvent('elterngespraech-oeffnen', { detail: id })));
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
            (this.$store.elterngespraeche?.liste || []).forEach(g => {
                if (g.status !== 'abgesagt' && g.date >= this.von && g.date <= this.bis && !this.suche && !this.kategorie) {
                    punkte.push({ art: 'gespraech', datum: g.date, g });
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

        /**
         * Komplettes Diagramm als SVG-Markup (per x-html). <template x-for> funktioniert innerhalb
         * von <svg> nicht (SVG-Templates haben kein `content`), daher keine Alpine-Schleifen im SVG.
         */
        get netzSvg() {
            if (!this.fragen.length) return '';
            const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const teile = [];
            [1, 2, 3, 4, 5].forEach(stufe => teile.push(
                `<polygon points="${this.ring(stufe)}" fill="none" stroke="#d9dee6" stroke-width="${stufe === 5 ? 1.5 : 1}"></polygon>`));
            this.fragen.forEach((f, i) => {
                const a = this.achse(i);
                const b = this.beschriftung(i);
                teile.push(`<line x1="${a.x1}" y1="${a.y1}" x2="${a.x2}" y2="${a.y2}" stroke="#d9dee6"></line>`);
                teile.push(`<text x="${b.x}" y="${b.y}" text-anchor="${b.anker}" dominant-baseline="middle"><title>${esc(b.voll)}</title>${esc(b.text)}</text>`);
            });
            if (this.hatLehrkraft) {
                teile.push(`<polygon points="${this.polygon('teacher')}" fill="rgba(21,128,61,.18)" stroke="#15803d" stroke-width="2"></polygon>`);
            }
            if (this.hatSelbst) {
                teile.push(`<polygon points="${this.polygon('self')}" fill="rgba(29,78,216,.14)" stroke="#1d4ed8" stroke-width="2" stroke-dasharray="5 4"></polygon>`);
            }
            this.fragen.forEach((f, i) => {
                if (f.teacher) { const [x, y] = this.punkt(i, f.teacher); teile.push(`<circle cx="${x}" cy="${y}" r="3.5" fill="#15803d"></circle>`); }
                if (f.self) { const [x, y] = this.punkt(i, f.self); teile.push(`<circle cx="${x}" cy="${y}" r="3.5" fill="#1d4ed8"></circle>`); }
            });
            return teile.join('');
        },
    }));
}

// ── Kategorien & Spaltengruppen ────────────────────────────────────
/** Abgestimmte Farben: kräftig genug als Rand, als 14-%-Fläche hinter dunkler Schrift gut lesbar */
const KATEGORIE_PALETTE = [
    { wert: '#1d4ed8', name: 'Blau' },
    { wert: '#0e7490', name: 'Petrol' },
    { wert: '#047857', name: 'Grün' },
    { wert: '#4d7c0f', name: 'Oliv' },
    { wert: '#a16207', name: 'Ocker' },
    { wert: '#c2410c', name: 'Orange' },
    { wert: '#be123c', name: 'Rot' },
    { wert: '#9d174d', name: 'Beere' },
    { wert: '#7c3aed', name: 'Violett' },
    { wert: '#475569', name: 'Schiefer' },
];

async function jsonAnfrage(url, methode = 'GET', daten = null) {
    const resp = await fetch(url, {
        method: methode,
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: daten ? JSON.stringify(daten) : undefined,
    });
    const json = await resp.json().catch(() => ({}));
    if (!resp.ok) {
        const details = json.errors ? Object.values(json.errors).flat().join(' ') : '';
        throw new Error(details || json.message || 'Die Änderung wurde nicht gespeichert.');
    }
    return json;
}

function registerKategorien(Alpine) {
    Alpine.data('kategorienVerwaltung', (konfig) => ({
        darfGlobal: !!konfig.darfGlobal,
        palette: KATEGORIE_PALETTE,
        tab: 'kategorien',
        kategorien: [],
        geladen: false,
        gruppen: [],
        gruppenGeladen: false,
        neu: { name: '', color: KATEGORIE_PALETTE[0].wert, global: false },
        speichert: false,
        farbOffen: null,
        bearbeitet: null,
        neuerName: '',
        loeschen: null,
        gruppeBearbeitet: null,
        neuerGruppenName: '',
        meldung: '',
        meldungTyp: 'ok',

        init() {
            this.lade();
        },

        melde(text, typ = 'ok') {
            this.meldung = text;
            this.meldungTyp = typ;
        },

        istPalette(farbe) {
            return !farbe || this.palette.some(f => f.wert === farbe);
        },

        async lade() {
            try {
                this.kategorien = (await jsonAnfrage('/paed-diary/categories')).categories || [];
            } catch (e) {
                this.melde(e.message, 'fehler');
            } finally {
                this.geladen = true;
            }
        },

        async ladeGruppen() {
            if (this.gruppenGeladen) return;
            try {
                this.gruppen = (await jsonAnfrage('/paed-diary/column-groups')).groups || [];
            } catch (e) {
                this.melde(e.message, 'fehler');
            } finally {
                this.gruppenGeladen = true;
            }
        },

        /** Bearbeiten-Endpunkt: eigene oder globale Kategorie */
        url(k) {
            return k.is_global ? `/paed-diary/categories/global/${k.id}` : `/paed-diary/categories/${k.id}/rename`;
        },

        async anlegen() {
            const name = this.neu.name.trim();
            if (!name) return;
            this.speichert = true;
            try {
                const url = this.neu.global && this.darfGlobal ? '/paed-diary/categories/global' : '/paed-diary/categories';
                const json = await jsonAnfrage(url, 'POST', { name, color: this.neu.color });
                this.kategorien = [...this.kategorien, { ...json.category, entries_count: 0, hidden: false }]
                    .sort((a, b) => (b.is_global - a.is_global) || a.name.localeCompare(b.name, 'de'));
                this.neu = { name: '', color: this.neu.color, global: this.neu.global };
                this.melde(`„${name}“ wurde angelegt.`);
            } catch (e) {
                this.melde(e.message, 'fehler');
            } finally {
                this.speichert = false;
            }
        },

        async farbeSetzen(k, farbe) {
            const vorher = k.color;
            k.color = farbe;
            this.farbOffen = null;
            try {
                await jsonAnfrage(this.url(k), 'PUT', { color: farbe });
            } catch (e) {
                k.color = vorher;
                this.melde(e.message, 'fehler');
            }
        },

        bearbeiten(k) {
            this.bearbeitet = k.id;
            this.neuerName = k.name;
        },

        async umbenennen(k) {
            const name = this.neuerName.trim();
            if (!name || name === k.name) { this.bearbeitet = null; return; }
            try {
                const json = await jsonAnfrage(this.url(k), 'PUT', { name });
                k.name = json.name;
                this.bearbeitet = null;
            } catch (e) {
                this.melde(e.message, 'fehler');
            }
        },

        async sichtbarkeit(k) {
            try {
                const json = await jsonAnfrage(`/paed-diary/categories/${k.id}/toggle-hidden`, 'POST');
                k.hidden = !!json.hidden;
            } catch (e) {
                this.melde(e.message, 'fehler');
            }
        },

        async entfernen(k) {
            try {
                await jsonAnfrage(k.is_global ? `/paed-diary/categories/global/${k.id}` : `/paed-diary/categories/${k.id}`, 'DELETE');
                this.kategorien = this.kategorien.filter(x => x.id !== k.id);
                this.loeschen = null;
                this.melde(`„${k.name}“ wurde gelöscht.`);
            } catch (e) {
                this.melde(e.message, 'fehler');
            }
        },

        async gruppeUmbenennen(g) {
            const name = this.neuerGruppenName.trim();
            if (!name || name === g.name) { this.gruppeBearbeitet = null; return; }
            try {
                const json = await jsonAnfrage('/paed-diary/column-groups/rename', 'POST', { old_name: g.name, new_name: name });
                this.melde(`Spaltengruppe umbenannt (${json.updated} Spalten).`);
                g.name = name;
                this.gruppeBearbeitet = null;
            } catch (e) {
                this.melde(e.message, 'fehler');
            }
        },
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
    registerGraduierung(Alpine);
    registerKategorien(Alpine);
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
    registerElterngespraeche(Alpine);
    registerSchuelerAkte(Alpine);
}

if (window.Alpine) {
    registerAll(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => {
        if (window.Alpine) registerAll(window.Alpine);
    });
}
