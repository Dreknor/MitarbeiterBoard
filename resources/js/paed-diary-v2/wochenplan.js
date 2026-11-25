/**
 * Wochenplan im Pädagogischen Tagebuch (beide Darstellungen).
 *
 * Nur aktiv, wenn die schulweite Einstellung „Anbindung an das Pädagogische Tagebuch“
 * eingeschaltet ist (Konfiguration aus #wp-tagebuch-config, gerendert von
 * paedDiary/partials/wochenplan.blade.php).
 *
 * - lädt je Klasse und Woche die Zusammenfassung „5 ✓ · 2 ◐ von 8“ (GET wp/klasse/{id}/woche)
 * - Seitenleiste mit den Aufgaben eines Kindes zum Abhaken (GET wp/schueler/{id}/woche, POST wp/status)
 * Eigenes, schlankes CSS (Präfix wpt-) – kein Tailwind, damit die Tagebuch-Seiten unberührt bleiben.
 */
import '../../css/wochenplan-tagebuch.css';
import { csrfToken, formatDate } from './utils.js';

const FOLGE = { offen: 'teilweise', teilweise: 'erledigt', erledigt: 'offen' };

function konfiguration() {
    try {
        return JSON.parse(document.getElementById('wp-tagebuch-config')?.textContent || '{}');
    } catch (e) {
        return {};
    }
}

async function holen(url, optionen = {}) {
    const antwort = await fetch(url, {
        credentials: 'same-origin',
        ...optionen,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/json', ...(optionen.headers || {}) },
    });
    const daten = await antwort.json().catch(() => null);
    if (!antwort.ok) {
        throw new Error(daten?.message || 'Wochenplan konnte nicht geladen werden.');
    }

    return daten;
}

export function registerWochenplanStore(Alpine) {
    if (Alpine.store('wochenplan')) {
        return;
    }

    const config = konfiguration();

    Alpine.store('wochenplan', {
        aktiv: !!config.aktiv,
        basis: config.basisUrl || '/wp',
        stand: {},            // schueler_id → {erledigt, teilweise, gesamt}
        panel: null,          // Daten der Seitenleiste
        laedt: false,
        fehler: '',
        notizFuer: null,      // Aufgaben-ID, deren Notizfeld offen ist
        _timer: null,
        _schluessel: '',

        init() {
            if (!this.aktiv) return;
            Alpine.effect(() => {
                const diary = Alpine.store('diary');
                if (!diary) return;
                const start = diary.currentWeekStart;
                const klassen = [...new Set((diary.schueler || []).map((s) => s.klasse_id).filter(Boolean))];
                clearTimeout(this._timer);
                this._timer = setTimeout(() => this.laden(klassen, start), 250);
            });
        },

        async laden(klassen, start) {
            const datum = formatDate(start instanceof Date ? start : new Date(start));
            const schluessel = datum + '|' + klassen.join(',');
            if (schluessel === this._schluessel) return;
            this._schluessel = schluessel;
            const neu = {};
            await Promise.all(klassen.map(async (id) => {
                try {
                    const daten = await holen(`${this.basis}/klasse/${id}/woche?datum=${datum}`);
                    Object.assign(neu, daten.schueler || {});
                } catch (e) {
                    // Kein Zugriff auf diese Klasse → einfach ohne Anzeige
                }
            }));
            this.stand = neu;
        },

        fuer(schuelerId) {
            return this.aktiv ? (this.stand[schuelerId] || null) : null;
        },
        text(s) {
            return s ? `${s.erledigt} ✓ · ${s.teilweise} ◐ von ${s.gesamt}` : '';
        },
        klasse(s) {
            if (!s || !s.gesamt) return 'is-leer';
            return s.erledigt === s.gesamt ? 'is-fertig' : 'is-offen';
        },

        async oeffnen(stu) {
            if (!this.aktiv) return;
            const diary = Alpine.store('diary');
            const datum = formatDate(diary?.currentWeekStart ? new Date(diary.currentWeekStart) : new Date());
            this.panel = { schueler: { id: stu.id, name: stu.name }, plaene: [], datum };
            this.laedt = true;
            this.fehler = '';
            try {
                this.panel = { ...(await holen(`${this.basis}/schueler/${stu.id}/woche?datum=${datum}`)), datum };
            } catch (e) {
                this.fehler = e.message;
            } finally {
                this.laedt = false;
            }
        },
        schliessen() {
            this.panel = null;
            this.notizFuer = null;
        },

        async setzen(aufgabe, status = null, notiz = undefined) {
            const neuerStatus = status || FOLGE[aufgabe.status];
            const vorher = { status: aufgabe.status, notiz: aufgabe.notiz };
            aufgabe.status = neuerStatus;
            if (notiz !== undefined) aufgabe.notiz = notiz;
            try {
                const daten = await holen(`${this.basis}/status`, {
                    method: 'POST',
                    body: JSON.stringify({
                        wp_aufgabe_id: aufgabe.id,
                        schueler_id: this.panel.schueler.id,
                        status: neuerStatus,
                        notiz: aufgabe.notiz || null,
                        quelle: 'tagebuch',
                    }),
                });
                Object.assign(aufgabe, { status: daten.eintrag.status, notiz: daten.eintrag.notiz, von: daten.eintrag.von, am: daten.eintrag.am });
                this.zusammenfassen();
            } catch (e) {
                Object.assign(aufgabe, vorher);
                this.fehler = e.message;
            }
        },

        zusammenfassen() {
            if (!this.panel) return;
            const alle = this.panel.plaene.flatMap((p) => p.faecher.flatMap((f) => f.aufgaben));
            const s = {
                erledigt: alle.filter((a) => a.status === 'erledigt').length,
                teilweise: alle.filter((a) => a.status === 'teilweise').length,
                gesamt: alle.length,
            };
            this.panel.zusammenfassung = s;
            this.stand = { ...this.stand, [this.panel.schueler.id]: s };
        },
    });
}
