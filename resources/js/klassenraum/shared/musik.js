/*
 * Klassenraum-Tools – entspannte Hintergrundmusik für die Lautstärke-Tools.
 *
 * Die Musik wird live im Browser erzeugt (Web Audio), es gibt keine Audiodateien:
 * langsame Klangflächen über einer Akkordfolge, vereinzelte Melodietöne aus einer
 * Tonleiter, ein Hall und eine leise Klangtextur je Thema (Blasen, Funkeln, Vögel).
 * Jede Klangwelt ist nur eine Konfiguration in KLANGWELTEN.
 *
 * Wird es im Raum zu laut, wird die Musik leiser und dumpfer (setzeUnruhe).
 *
 * In der Adventszeit (setzeWeihnachten) spielt statt der Klangwelt ein ruhiges Klavier
 * Weihnachtslieder (shared/weihnachtslieder.js): Melodie rechts, Begleitung links (gebrochene
 * Akkorde, Bass–Akkord oder Walzer), in zufälliger Reihenfolge mit kleinen Pausen dazwischen;
 * Hall und Dämpfung bei Lärm bleiben gleich.
 */

import {klemme, mische} from './hilfen';
import {AKKORDE, WEIHNACHTSLIEDER} from './weihnachtslieder';

const mzf = (midi) => 440 * Math.pow(2, (midi - 69) / 12);
const zwischen = (a, b) => a + Math.random() * (b - a);
const ausListe = (liste) => liste[Math.floor(Math.random() * liste.length)];

export const KLANGWELTEN = {
    // D-dorisch: warm, tief, schwebend
    aquarium: {
        grundton: 50,
        akkorde: [[0, 3, 7, 10, 14], [-4, 0, 3, 7, 10], [3, 7, 10, 14], [-2, 2, 5, 9]],
        akkordDauer: [10, 14],
        flaeche: {typ: 'triangle', filter: 650, lautstaerke: 0.045, anschlag: 3, ausklang: 5, verstimmung: 7},
        tonleiter: [0, 2, 3, 5, 7, 9, 10],
        melodie: {oktave: 24, typ: 'sine', abstand: [1.8, 4.5], dauer: 3, lautstaerke: 0.06, obertoene: [[2, 0.22], [3, 0.06]], anschlag: 0.02},
        hall: {dauer: 4.5, anteil: 0.6},
        textur: 'blasen',
    },
    // A-lydisch: weit, ruhig, glasklar
    sternenhimmel: {
        grundton: 45,
        akkorde: [[0, 4, 7, 11, 14], [-3, 0, 4, 7, 11], [5, 9, 12, 16], [7, 11, 14, 18]],
        akkordDauer: [14, 18],
        flaeche: {typ: 'sine', filter: 1300, lautstaerke: 0.055, anschlag: 5, ausklang: 6, verstimmung: 5},
        tonleiter: [0, 2, 4, 6, 7, 9, 11],
        melodie: {oktave: 36, typ: 'sine', abstand: [2.6, 6], dauer: 4, lautstaerke: 0.04, obertoene: [[4, 0.12], [2, 0.18]], anschlag: 0.01},
        hall: {dauer: 6, anteil: 0.7},
        textur: 'funkeln',
    },
    // G-Dur-pentatonisch, Marimba-artig: fröhlich, aber ruhig
    zoo: {
        grundton: 55,
        akkorde: [[0, 4, 7, 11], [-3, 0, 4, 7], [5, 9, 12, 16], [7, 11, 14, 17]],
        akkordDauer: [7, 9],
        flaeche: {typ: 'triangle', filter: 950, lautstaerke: 0.03, anschlag: 2, ausklang: 3.5, verstimmung: 6},
        tonleiter: [0, 2, 4, 7, 9],
        melodie: {oktave: 12, typ: 'triangle', abstand: [0.55, 1.5], dauer: 0.6, lautstaerke: 0.05, obertoene: [[3, 0.06], [4, 0.04]], anschlag: 0.006},
        hall: {dauer: 2.2, anteil: 0.3},
        textur: 'voegel',
    },
    // E-Moll-pentatonisch, Holz-Marimba über tiefer Fläche: Urzeit, aber gemütlich
    vulkan: {
        grundton: 40,
        akkorde: [[0, 7, 12, 15], [-4, 3, 8, 12], [-2, 5, 10, 14], [-5, 2, 7, 10]],
        akkordDauer: [9, 12],
        flaeche: {typ: 'triangle', filter: 700, lautstaerke: 0.04, anschlag: 3, ausklang: 4.5, verstimmung: 6},
        tonleiter: [0, 3, 5, 7, 10],
        melodie: {oktave: 24, typ: 'triangle', abstand: [0.9, 2.4], dauer: 0.8, lautstaerke: 0.05, obertoene: [[3, 0.07], [5, 0.03]], anschlag: 0.006},
        hall: {dauer: 3, anteil: 0.4},
        textur: 'voegel',
    },
    // Fis-Moll mit großer None, langsame gläserne Flächen: kalte, klare Polarnacht
    polarlicht: {
        grundton: 42,
        akkorde: [[0, 7, 14, 15], [-4, 3, 10, 14], [-2, 5, 12, 17], [-7, 0, 7, 14]],
        akkordDauer: [15, 19],
        flaeche: {typ: 'sine', filter: 1100, lautstaerke: 0.055, anschlag: 6, ausklang: 7, verstimmung: 6},
        tonleiter: [0, 2, 3, 7, 9],
        melodie: {oktave: 36, typ: 'sine', abstand: [3, 7], dauer: 4.5, lautstaerke: 0.035, obertoene: [[2, 0.16], [5, 0.05]], anschlag: 0.02},
        hall: {dauer: 6.5, anteil: 0.75},
        textur: 'funkeln',
    },
    // C-Dur, sehr langsam und leise: zum Atmen
    atemuebung: {
        grundton: 48,
        akkorde: [[0, 7, 12, 16], [-3, 4, 9, 12], [5, 9, 12, 17], [-5, 2, 7, 11]],
        akkordDauer: [12, 16],
        flaeche: {typ: 'sine', filter: 900, lautstaerke: 0.05, anschlag: 5, ausklang: 6, verstimmung: 4},
        tonleiter: [0, 2, 4, 7, 9],
        melodie: {oktave: 24, typ: 'sine', abstand: [4, 8], dauer: 4, lautstaerke: 0.028, obertoene: [[2, 0.15]], anschlag: 0.05},
        hall: {dauer: 5, anteil: 0.6},
        textur: null,
    },
    // C-Dur-pentatonisch: hell, leicht, sonnig
    blumenwiese: {
        grundton: 48,
        akkorde: [[0, 4, 7, 11], [-3, 0, 4, 7], [5, 9, 12, 16], [7, 11, 14, 16]],
        akkordDauer: [8, 10],
        flaeche: {typ: 'triangle', filter: 1000, lautstaerke: 0.035, anschlag: 2.5, ausklang: 4, verstimmung: 6},
        tonleiter: [0, 2, 4, 7, 9],
        melodie: {oktave: 24, typ: 'triangle', abstand: [0.7, 2], dauer: 1, lautstaerke: 0.05, obertoene: [[4, 0.08]], anschlag: 0.008},
        hall: {dauer: 2.8, anteil: 0.38},
        textur: 'voegel',
    },
};

function hallImpuls(ctx, dauer) {
    const laenge = Math.round(ctx.sampleRate * dauer);
    const puffer = ctx.createBuffer(2, laenge, ctx.sampleRate);
    for (let kanal = 0; kanal < 2; kanal++) {
        const daten = puffer.getChannelData(kanal);
        let vorher = 0;
        for (let i = 0; i < laenge; i++) {
            // leicht tiefpassgefiltertes Rauschen, exponentiell abklingend
            vorher = vorher * 0.6 + (Math.random() * 2 - 1) * 0.4;
            daten[i] = vorher * Math.pow(1 - i / laenge, 3);
        }
    }
    return puffer;
}

export class Musik {
    constructor(welt) {
        this.welt = welt;
        this.ctx = null;
        this.an = false;
        this.pausiert = false;
        this.lautstaerke = 0.4;
        this.unruhe = 0;
        this.zeitgeber = null;
        this.stufe = 0;
        this.tonIndex = 0;
        this.weihnachten = false;
        this.lied = null;
        this.letztesLied = -1;
    }

    /** Weihnachtslieder statt Klangwelt (Adventszeit). Wechsel greift sofort. */
    setzeWeihnachten(an) {
        an = Boolean(an);
        if (an === this.weihnachten) return;
        this.weihnachten = an;
        this.lied = null;
        if (this.ctx && this.an) {
            const t = this.ctx.currentTime;
            this.naechstesLied = t + 1.5;
            this.naechsterAkkord = t + 1;
            this.naechsteNote = t + 3;
            this.naechsteTextur = t + zwischen(6, 12);
        }
    }

    get verfuegbar() {
        return Boolean(this.welt && (window.AudioContext || window.webkitAudioContext));
    }

    #aufbauen() {
        const AudioKontext = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioKontext();
        this.ctx = ctx;

        this.bus = ctx.createGain();
        this.filter = ctx.createBiquadFilter();
        this.filter.type = 'lowpass';
        this.filter.frequency.value = 7000;
        this.filter.Q.value = 0.4;

        const trocken = ctx.createGain();
        trocken.gain.value = 1 - this.welt.hall.anteil * 0.5;
        const hall = ctx.createConvolver();
        hall.buffer = hallImpuls(ctx, this.welt.hall.dauer);
        const nass = ctx.createGain();
        nass.gain.value = this.welt.hall.anteil;

        this.master = ctx.createGain();
        this.master.gain.value = 0;
        const begrenzer = ctx.createDynamicsCompressor();
        begrenzer.threshold.value = -14;
        begrenzer.ratio.value = 6;

        this.bus.connect(this.filter);
        this.filter.connect(trocken).connect(this.master);
        this.filter.connect(hall).connect(nass).connect(this.master);
        this.master.connect(begrenzer).connect(ctx.destination);
    }

    starte() {
        if (!this.verfuegbar) return;
        if (!this.ctx) this.#aufbauen();
        this.ctx.resume().catch(() => {});
        if (this.an) return;
        this.an = true;

        const t = this.ctx.currentTime;
        this.naechsterAkkord = t + 0.1;
        this.naechsteNote = t + 2.5;
        this.naechsteTextur = t + zwischen(6, 12);
        this.naechstesLied = t + 0.5;
        this.lied = null;
        this.tonIndex = Math.floor(this.welt.tonleiter.length / 2);
        clearInterval(this.zeitgeber);
        this.zeitgeber = setInterval(() => this.#plane(), 250);
        this.#plane();
        this.#anwenden(2.5);
    }

    stoppe() {
        if (!this.ctx || !this.an) return;
        this.an = false;
        this.#anwenden(0.6);
        clearInterval(this.zeitgeber);
        this.zeitgeber = null;
    }

    pausiere(wert) {
        this.pausiert = wert;
        if (!this.ctx) return;
        if (wert) this.ctx.suspend().catch(() => {});
        else if (this.an) this.ctx.resume().catch(() => {});
    }

    setzeLautstaerke(wert) {
        this.lautstaerke = klemme(wert, 0, 1);
        this.#anwenden(0.4);
    }

    /** 0 = ruhig … 1 = zu laut: Musik wird leiser und dumpfer. */
    setzeUnruhe(wert) {
        if (Math.abs(wert - this.unruhe) < 0.02) return;
        this.unruhe = wert;
        this.#anwenden(0.8);
    }

    #anwenden(zeitkonstante) {
        if (!this.ctx) return;
        const t = this.ctx.currentTime;
        const ziel = this.an ? this.lautstaerke * (1 - 0.7 * this.unruhe) : 0;
        this.master.gain.setTargetAtTime(ziel, t, zeitkonstante);
        this.filter.frequency.setTargetAtTime(mische(7000, 650, this.unruhe), t, 0.8);
    }

    // ── Planung (immer ~2 s im Voraus, damit es auch in Hintergrund-Tabs nicht stockt) ──
    #plane() {
        if (!this.an || this.pausiert || !this.ctx) return;
        const jetzt = this.ctx.currentTime;
        const bis = jetzt + 2;

        if (this.weihnachten) {
            this.#planeLieder(jetzt, bis);
            return;
        }

        // Lief der Zeitgeber lange nicht (gedrosselter Hintergrund-Tab, Laptop im Ruhezustand),
        // liegen die Planzeiten weit zurück. Nicht alles Verpasste auf einmal nachholen –
        // das wären Tausende Klangknoten gleichzeitig –, sondern ab jetzt weiterspielen.
        if (this.naechsterAkkord < jetzt - 1) this.naechsterAkkord = jetzt + 0.1;
        if (this.naechsteNote < jetzt - 1) this.naechsteNote = jetzt + zwischen(...this.welt.melodie.abstand);
        if (this.naechsteTextur < jetzt - 1) this.naechsteTextur = jetzt + zwischen(7, 18);

        while (this.naechsterAkkord < bis) {
            const dauer = zwischen(...this.welt.akkordDauer);
            this.#akkord(this.welt.akkorde[this.stufe % this.welt.akkorde.length], this.naechsterAkkord, dauer);
            this.stufe++;
            this.naechsterAkkord += dauer * 0.85;
        }
        while (this.naechsteNote < bis) {
            const m = this.welt.melodie;
            if (Math.random() < 0.8) {
                this.#melodieton(this.naechsteNote);
                if (Math.random() < 0.25) this.#melodieton(this.naechsteNote + zwischen(0.25, 0.6));
            }
            this.naechsteNote += zwischen(...m.abstand);
        }
        while (this.naechsteTextur < bis) {
            this.#textur(this.naechsteTextur);
            this.naechsteTextur += zwischen(7, 18);
        }
    }

    // ── Weihnachtslieder (Klavier) ───────────────────────────────────────
    #planeLieder(jetzt, bis) {
        // Nach langer Pause (Hintergrund-Tab, Ruhezustand) nicht nachholen, sondern neu ansetzen
        if (this.lied && this.lied.ende < jetzt - 1) this.lied = null;
        if (!this.lied) {
            if (this.naechstesLied === undefined || this.naechstesLied < jetzt - 1) this.naechstesLied = jetzt + 0.5;
            if (this.naechstesLied > bis) return;
            this.lied = this.#neuesLied(this.naechstesLied);
        }
        const l = this.lied;
        while (l.index < l.ereignisse.length) {
            const e = l.ereignisse[l.index];
            const zeit = l.start + e.zeit;
            if (zeit > bis) break;
            l.index++;
            if (zeit < jetzt - 0.2) continue;
            this.#klavier(Math.max(jetzt, zeit), e.ton, e.dauer, e.staerke);
        }
        if (l.index >= l.ereignisse.length) {
            this.naechstesLied = l.ende + zwischen(4, 7);
            this.lied = null;
        }
    }

    /** Lied in einzelne Klaviertöne zerlegen: Melodie rechts, Begleitung links (je nach Liedart). */
    #neuesLied(start) {
        let nr = Math.floor(Math.random() * WEIHNACHTSLIEDER.length);
        if (nr === this.letztesLied && WEIHNACHTSLIEDER.length > 1) nr = (nr + 1) % WEIHNACHTSLIEDER.length;
        this.letztesLied = nr;
        const lied = WEIHNACHTSLIEDER[nr];
        const e = lied.einheit;
        const ereignisse = [];
        // etwas menschliche Ungenauigkeit in Zeit und Anschlag
        const ton = (zeit, midi, dauer, staerke) => ereignisse.push({
            zeit: Math.max(0, zeit + zwischen(-0.012, 0.012)), ton: midi, dauer, staerke: klemme(staerke + zwischen(-0.06, 0.06), 0.15, 1),
        });
        let versatz = 0;
        for (let w = 0; w < (lied.wiederholen || 1); w++) {
            let zeit = versatz;
            lied.melodie.forEach(([midi, dauer], i) => {
                // Melodie: Phrasenanfänge etwas betonter, lange Töne klingen aus
                if (midi !== null) ton(zeit, midi, dauer * e * 0.95, 0.72 + (i % 4 === 0 ? 0.08 : 0));
                zeit += dauer * e;
            });
            zeit = versatz;
            lied.akkorde.forEach(([name, dauer]) => {
                this.#begleitmuster(lied, AKKORDE[name], zeit, dauer, ton);
                zeit += dauer * e;
            });
            versatz = zeit + e;
        }
        ereignisse.sort((a, b) => a.zeit - b.zeit);
        return {start, ereignisse, index: 0, ende: start + versatz};
    }

    /**
     * Linke Hand: 'arpeggio' (gebrochene Akkorde, mit Pedal gehalten), 'alberti' (Bass, oben, Mitte, oben),
     * 'stride' (Bass – Akkord)
     * oder 'walzer' (Bass – Akkord – Akkord). schritt = Länge eines Begleittons in Einheiten.
     */
    #begleitmuster(lied, akkord, start, dauer, ton) {
        const e = lied.einheit;
        const art = lied.begleitung || 'arpeggio';
        const schritt = lied.schritt || 1;
        const ende = start + dauer * e;
        const [a, b, c] = akkord.toene;
        if (art === 'arpeggio' || art === 'alberti') {
            const folge = art === 'alberti' ? [akkord.bass, c, b, c] : [akkord.bass, a, b, c, b, a];
            for (let i = 0, z = start; z < ende - 0.01; i++, z += schritt * e) {
                // Pedal: jeder Ton klingt bis zum Akkordwechsel
                ton(z, folge[i % folge.length], ende - z + 0.15, i === 0 ? 0.5 : 0.36);
            }
            return;
        }
        const takt = art === 'walzer' ? 3 : 2;
        for (let i = 0, z = start; z < ende - 0.01; i++, z += schritt * e) {
            if (i % takt === 0) {
                ton(z, akkord.bass, schritt * e * 1.6, 0.5);
            } else {
                akkord.toene.forEach((midi) => ton(z, midi, schritt * e * 0.8, 0.3));
            }
        }
    }

    /**
     * Klavierton: leicht unharmonische Teiltöne (steife Saite), zwei minimal verstimmte Saiten,
     * schneller Anfangsabfall und langes Nachklingen (tiefe Töne länger), höhere Teiltöne
     * klingen schneller ab, kurzes Hammergeräusch; beim Loslassen dämpft der Dämpfer.
     */
    #klavier(t, midi, dauer, staerke = 0.6) {
        const ctx = this.ctx;
        const frequenz = mzf(midi);
        const nachklang = klemme(4.5 - (midi - 40) * 0.07, 1.2, 4.5);
        const loslassen = t + Math.max(0.12, dauer);
        const ende = loslassen + 0.35;
        const lautstaerke = 0.095 * Math.pow(staerke, 1.4) * klemme(1.25 - (midi - 48) * 0.01, 0.8, 1.25);

        const huelle = ctx.createGain();
        huelle.gain.setValueAtTime(0.0001, t);
        huelle.gain.exponentialRampToValueAtTime(lautstaerke, t + 0.004);
        huelle.gain.exponentialRampToValueAtTime(lautstaerke * 0.45, t + 0.18);
        huelle.gain.exponentialRampToValueAtTime(Math.max(0.0001, lautstaerke * 0.45 * Math.exp(-(loslassen - t - 0.18) / nachklang)), loslassen);
        huelle.gain.exponentialRampToValueAtTime(0.0001, ende);
        // weicher Anschlag = dunkler Klang
        const ton = ctx.createBiquadFilter();
        ton.type = 'lowpass';
        ton.frequency.setValueAtTime(klemme(frequenz * (3 + staerke * 6), 900, 9000), t);
        ton.frequency.exponentialRampToValueAtTime(klemme(frequenz * 2.2, 500, 5000), t + nachklang * 0.6);
        ton.connect(huelle).connect(this.bus);

        const steif = 0.00035;
        [[1, 1], [2, 0.55], [3, 0.3], [4, 0.17], [5, 0.1], [6, 0.06], [7, 0.035]].forEach(([k, anteil]) => {
            const f = frequenz * k * Math.sqrt(1 + steif * k * k);
            if (f > 12000) return;
            const g = ctx.createGain();
            g.gain.setValueAtTime(anteil, t);
            g.gain.exponentialRampToValueAtTime(anteil * 0.02, t + nachklang / (0.6 + k * 0.5));
            g.connect(ton);
            (k === 1 ? [-1.2, 1.2] : [0]).forEach((cent) => {
                const osz = ctx.createOscillator();
                osz.type = 'sine';
                osz.frequency.value = f;
                osz.detune.value = cent;
                osz.connect(g);
                osz.start(t);
                osz.stop(ende + 0.05);
            });
        });

        // Hammer: ein paar Millisekunden gefiltertes Rauschen
        if (!this.rauschen) {
            this.rauschen = ctx.createBuffer(1, Math.round(ctx.sampleRate * 0.05), ctx.sampleRate);
            const d = this.rauschen.getChannelData(0);
            for (let i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / d.length, 4);
        }
        const hammer = ctx.createBufferSource();
        hammer.buffer = this.rauschen;
        const band = ctx.createBiquadFilter();
        band.type = 'bandpass';
        band.frequency.value = klemme(frequenz * 4, 1200, 5000);
        band.Q.value = 0.8;
        const hammerPegel = ctx.createGain();
        hammerPegel.gain.value = lautstaerke * 0.25 * staerke;
        hammer.connect(band).connect(hammerPegel).connect(this.bus);
        hammer.start(t);
    }

    #akkord(stufen, t, dauer) {
        const f = this.welt.flaeche;
        const ende = t + dauer + f.ausklang;
        stufen.forEach((halbton) => {
            const frequenz = mzf(this.welt.grundton + halbton);
            const huelle = this.ctx.createGain();
            huelle.gain.setValueAtTime(0, t);
            huelle.gain.linearRampToValueAtTime(f.lautstaerke / Math.sqrt(stufen.length), t + f.anschlag);
            huelle.gain.setValueAtTime(f.lautstaerke / Math.sqrt(stufen.length), t + dauer);
            huelle.gain.linearRampToValueAtTime(0, ende);

            const tiefpass = this.ctx.createBiquadFilter();
            tiefpass.type = 'lowpass';
            tiefpass.frequency.value = f.filter;
            tiefpass.connect(huelle).connect(this.bus);

            [-f.verstimmung, f.verstimmung].forEach((cent) => {
                const osz = this.ctx.createOscillator();
                osz.type = f.typ;
                osz.frequency.value = frequenz;
                osz.detune.value = cent;
                osz.connect(tiefpass);
                osz.start(t);
                osz.stop(ende + 0.1);
            });
        });
    }

    #melodieton(t) {
        const m = this.welt.melodie;
        const leiter = this.welt.tonleiter;
        // kleine Schritte durch die Tonleiter (über zwei Oktaven)
        this.tonIndex = klemme(this.tonIndex + ausListe([-2, -1, -1, 1, 1, 2, 0]), 0, leiter.length * 2 - 1);
        const oktave = Math.floor(this.tonIndex / leiter.length) * 12;
        const frequenz = mzf(this.welt.grundton + m.oktave + leiter[this.tonIndex % leiter.length] + oktave);

        const huelle = this.ctx.createGain();
        huelle.gain.setValueAtTime(0.0001, t);
        huelle.gain.exponentialRampToValueAtTime(m.lautstaerke, t + m.anschlag);
        huelle.gain.exponentialRampToValueAtTime(0.0001, t + m.dauer);
        huelle.connect(this.bus);

        [[1, 1], ...m.obertoene].forEach(([faktor, anteil]) => {
            const osz = this.ctx.createOscillator();
            osz.type = faktor === 1 ? m.typ : 'sine';
            osz.frequency.value = frequenz * faktor;
            const g = this.ctx.createGain();
            g.gain.value = anteil;
            osz.connect(g).connect(huelle);
            osz.start(t);
            osz.stop(t + m.dauer + 0.05);
        });
    }

    #kurzton(t, {frequenz, bis = null, dauer, lautstaerke, typ = 'sine'}) {
        const osz = this.ctx.createOscillator();
        osz.type = typ;
        osz.frequency.setValueAtTime(frequenz, t);
        if (bis) osz.frequency.exponentialRampToValueAtTime(bis, t + dauer * 0.8);
        const huelle = this.ctx.createGain();
        huelle.gain.setValueAtTime(0.0001, t);
        huelle.gain.exponentialRampToValueAtTime(lautstaerke, t + 0.01);
        huelle.gain.exponentialRampToValueAtTime(0.0001, t + dauer);
        osz.connect(huelle).connect(this.bus);
        osz.start(t);
        osz.stop(t + dauer + 0.05);
    }

    #textur(t) {
        if (this.welt.textur === 'blasen') {
            const anzahl = Math.floor(zwischen(1, 4));
            for (let i = 0; i < anzahl; i++) {
                const start = zwischen(280, 520);
                this.#kurzton(t + i * zwischen(0.12, 0.3), {frequenz: start, bis: start * zwischen(2, 3), dauer: 0.12, lautstaerke: 0.025});
            }
        } else if (this.welt.textur === 'funkeln') {
            const leiter = this.welt.tonleiter;
            const anzahl = Math.floor(zwischen(3, 6));
            for (let i = 0; i < anzahl; i++) {
                const ton = this.welt.grundton + 48 + ausListe(leiter);
                this.#kurzton(t + i * zwischen(0.07, 0.14), {frequenz: mzf(ton), dauer: 1.2, lautstaerke: 0.012});
            }
        } else if (this.welt.textur === 'voegel') {
            const anzahl = Math.floor(zwischen(2, 5));
            const basis = zwischen(2400, 3400);
            for (let i = 0; i < anzahl; i++) {
                this.#kurzton(t + i * zwischen(0.12, 0.2), {frequenz: basis, bis: basis * zwischen(1.2, 1.5), dauer: 0.09, lautstaerke: 0.012});
            }
        }
    }
}
