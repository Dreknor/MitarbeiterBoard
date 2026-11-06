/*
 * Klassenraum-Tools – entspannte Hintergrundmusik für die Lautstärke-Tools.
 *
 * Die Musik wird live im Browser erzeugt (Web Audio), es gibt keine Audiodateien:
 * langsame Klangflächen über einer Akkordfolge, vereinzelte Melodietöne aus einer
 * Tonleiter, ein Hall und eine leise Klangtextur je Thema (Blasen, Funkeln, Vögel).
 * Jede Klangwelt ist nur eine Konfiguration in KLANGWELTEN.
 *
 * Wird es im Raum zu laut, wird die Musik leiser und dumpfer (setzeUnruhe).
 */

import {klemme, mische} from './hilfen';

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
        const bis = this.ctx.currentTime + 2;

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
