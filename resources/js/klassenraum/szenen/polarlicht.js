/*
 * Szene „Polarlicht“: Nachthimmel über einem stillen See. Am anderen Ufer ein Nadelwald mit
 * einer Lichtung, vorne rechts eine Holzhütte am Ufer mit Steg, zwei Stühlen und Laterne.
 *
 * Je länger es leise ist, desto mehr Polarlicht-Bänder erwachen nacheinander am Himmel und
 * spiegeln sich im Wasser; ist der Himmel voll, fallen ab und zu Sternschnuppen. Wird es laut,
 * flackern die Lichter und ziehen sich zurück (schneller als sie gekommen sind). Die Farbwelt
 * (bunt, grün, violett, eisblau) ist eine eigene Einstellung der Szene (`farben`).
 *
 * Seltene Besuche (nur bei Ruhe): Elch, der über die Lichtung am anderen Ufer zieht und am
 * Wasser trinkt; Kanu mit Laterne auf dem See. Beide spiegeln sich im Wasser.
 * Die Klassenmaus (mit Schlafmütze) guckt aus der Hüttentür, hinter der Uferkuppe oder aus dem Schilf.
 *
 * Technik: Die Bänder entstehen spaltenweise in einer Zwischenleinwand (AW × AH) und werden
 * weich hochskaliert; die Spiegelung im See ist dieselbe Leinwand, gespiegelt und in dünnen
 * Streifen leicht gewellt. Sterne und Vordergrund (Ufer, Hütte, Steg, Birke) werden je
 * Größenänderung vorgezeichnet, der Wald am anderen Ufer als Path2D.
 *
 * Vorschau: ?besuch=elch|kanu|maus, ?besuch=voll (Himmel sofort voll)
 */

import {klemme, mische, zufall, zufallMitSaat} from '../shared/hilfen';
import {Besuchsplaner, blende} from '../shared/besuche';
import {Klassenmaus, mausBesuch} from '../shared/maus';

const TAU = Math.PI * 2;
const sanft = (p) => p * p * (3 - 2 * p);

/** Farbwelten: je Band [unterer Saum, Mitte, obere Spitzen]; Reihenfolge = Werte der Einstellung `farben`. */
export const FARBWELTEN = [
    {name: 'Bunt', baender: [
        ['#8dffb0', '#3ee6a0', '#7a6bff'], ['#7df2ff', '#48b6ff', '#d277ff'], ['#d6ff8a', '#6dffb0', '#ff7fc4'],
        ['#b9a2ff', '#8a6bff', '#ff8ad1'], ['#ffd9a0', '#ff8fb8', '#a56bff'], ['#a8ffc0', '#3ee6a0', '#6f7dff'],
        ['#7df2ff', '#6de6ff', '#b28cff']]},
    {name: 'Grün', baender: [
        ['#a8ffc0', '#3ee6a0', '#6f7dff'], ['#7dffb6', '#22d3a0', '#4f8bff'], ['#c9ff9a', '#59f0a0', '#8a7bff'],
        ['#8dffd6', '#2fd9b0', '#5f6bff'], ['#b6ffb0', '#3ee6a0', '#9a7bff'], ['#a8ffc0', '#22d3a0', '#6f7dff'],
        ['#c9ff9a', '#2fd9b0', '#4f8bff']]},
    {name: 'Violett', baender: [
        ['#ffb0e6', '#c277ff', '#5f6bff'], ['#d9a2ff', '#8a6bff', '#ff7fc4'], ['#ff9ad6', '#e06bff', '#6f8bff'],
        ['#b8b0ff', '#7a6bff', '#ff8ad1'], ['#ffc0f0', '#b877ff', '#48b6ff'], ['#ffb0e6', '#8a6bff', '#5f6bff'],
        ['#d9a2ff', '#e06bff', '#48b6ff']]},
    {name: 'Eisblau', baender: [
        ['#e8ffff', '#7df2ff', '#4f8bff'], ['#b0fff0', '#48d6ff', '#6f7dff'], ['#ffffff', '#9ae6ff', '#5f9bff'],
        ['#a8f6ff', '#48b6ff', '#8a8bff'], ['#d6fff6', '#6de6ff', '#4f6bff'], ['#e8ffff', '#48d6ff', '#6f7dff'],
        ['#b0fff0', '#9ae6ff', '#4f8bff']]},
];

// Bänder: Lage, Welle, Höhe, Frequenzen/Tempi/Phasen, Strahlen. Bei „Menge“ 100 % leuchten die ersten fünf.
const BAENDER = [
    {y: 0.62, amp: 0.1, h: 0.42, f1: 1.1, f2: 2.7, s1: 0.11, s2: 0.17, p1: 0.3, p2: 1.9, rf: 14, rs: 0.5},
    {y: 0.48, amp: 0.13, h: 0.38, f1: 0.8, f2: 3.4, s1: 0.09, s2: 0.21, p1: 2.2, p2: 0.4, rf: 19, rs: -0.4},
    {y: 0.74, amp: 0.07, h: 0.3, f1: 1.6, f2: 4.1, s1: 0.14, s2: 0.12, p1: 4.0, p2: 3.1, rf: 24, rs: 0.7},
    {y: 0.36, amp: 0.12, h: 0.32, f1: 1.3, f2: 2.2, s1: 0.07, s2: 0.19, p1: 5.2, p2: 2.6, rf: 17, rs: -0.6},
    {y: 0.56, amp: 0.16, h: 0.46, f1: 0.6, f2: 3.0, s1: 0.13, s2: 0.1, p1: 1.1, p2: 5.0, rf: 21, rs: 0.45},
    {y: 0.42, amp: 0.09, h: 0.28, f1: 1.9, f2: 3.7, s1: 0.1, s2: 0.15, p1: 3.3, p2: 4.4, rf: 27, rs: -0.5},
    {y: 0.68, amp: 0.11, h: 0.36, f1: 0.9, f2: 2.4, s1: 0.12, s2: 0.18, p1: 0.9, p2: 2.2, rf: 16, rs: 0.55},
];
const STANDARD_BAENDER = 5;

// Zwischenleinwand der Bänder (wird weich hochskaliert); SPALTE = Abstand der Strahlen-Spalten
const AW = 640;
const AH = 260;
const SPALTE = 1.5;

function hexA(hex, a) {
    const n = parseInt(hex.slice(1), 16);
    return `rgba(${n >> 16},${(n >> 8) & 255},${n & 255},${a})`;
}

function neueLeinwand(breite, hoehe) {
    const leinwand = document.createElement('canvas');
    leinwand.width = Math.max(1, Math.round(breite));
    leinwand.height = Math.max(1, Math.round(hoehe));
    return leinwand;
}

/** Senkrechter Farbstreifen eines Bandes (1 px breit), wird spaltenweise gestreckt. */
function farbstreifen([saum, mitte, spitze]) {
    const s = neueLeinwand(1, 200);
    const g = s.getContext('2d');
    const verlauf = g.createLinearGradient(0, 0, 0, 200);
    verlauf.addColorStop(0, hexA(spitze, 0));
    verlauf.addColorStop(0.35, hexA(spitze, 0.16));
    verlauf.addColorStop(0.6, hexA(mitte, 0.5));
    verlauf.addColorStop(0.86, hexA(saum, 0.9));
    verlauf.addColorStop(0.95, hexA('#ffffff', 0.55));
    verlauf.addColorStop(1, hexA(saum, 0));
    g.fillStyle = verlauf;
    g.fillRect(0, 0, 1, 200);
    return s;
}

export function erzeugePolarlicht() {
    let b = 0;
    let h = 0;
    let t = 0;           // Animationszeit (läuft in Ruhe schneller)
    let echtzeit = 0;    // für Flackern, Laterne, Rauch und Wellen
    let ruhe = 0.08;     // 0–1: wie weit der Himmel schon leuchtet
    let flackern = 1;
    let anzahl = STANDARD_BAENDER;
    let farbIndex = -1;
    let streifen = [];
    const staerke = BAENDER.map(() => 0);
    const schnuppen = [];

    // Maße (in CSS-Pixeln) – in groesse() gesetzt
    let hy = 0;          // Wasserlinie am anderen Ufer
    let S = 1;           // Maßstab der Gegenstände
    let by = 0;          // Boden der Hütte
    let hx = 0;          // Hüttenmitte
    let huette = null;   // Maße der Hütte
    let land = null;     // Uferlinie vorne rechts
    let steg = null;
    let lx = 0;          // Laterne (Stegende)
    let ly = 0;
    let lichtung = [0, 0];
    let schilf = [];
    let glanz = [];
    let ferneLichter = [];
    let wald = null;     // Path2D: Wald am anderen Ufer
    let sterneEbene = null;
    let vordergrund = null;

    const aurora = neueLeinwand(AW, AH);
    const actx = aurora.getContext('2d');

    const pixelFaktor = () => Math.min(2, window.devicePixelRatio || 1);

    // Oberkante des Landes vorne rechts (links davon ist Wasser). Die Uferlinie läuft schräg nach
    // hinten und liegt hinter der Hütte deutlich über ihrem Boden – die Hütte steht ganz auf Land.
    function landOben(x) {
        const {xa, xb, xc} = land;
        if (x <= xa) return by + 10 * S;
        if (x <= xb) return by + 10 * S - 10 * S * sanft((x - xa) / (xb - xa));
        if (x <= xc) return by - 32 * S * sanft((x - xb) / (xc - xb));
        return by - 32 * S - 12 * S * sanft(klemme((x - xc) / Math.max(1, b - xc), 0, 1));
    }

    function groesse(breite, hoehe, einstellungen) {
        const neu = breite !== b || hoehe !== h;
        b = breite;
        h = hoehe;
        anzahl = klemme(Math.round((einstellungen.menge / 100) * STANDARD_BAENDER), 2, BAENDER.length);
        if (!neu && vordergrund) return;

        hy = h * 0.62;
        S = klemme(Math.min(b, h) / 720, 0.5, 1.5);
        by = hy + (h - hy) * 0.45;
        hx = b * (b < 600 ? 0.64 : 0.7);
        const w = 128 * S;
        huette = {w, wand: 66 * S, giebel: 42 * S, tiefe: 58 * S, schraeg: 0.32};
        land = {xa: hx - w * 1.05, xb: hx - w * 0.85, xc: hx - w * 0.5, unten: hx - w * 1.9};
        steg = {von: land.xa + 18 * S, bis: Math.max(16 * S, land.xa - 170 * S), y: by + 7 * S, dicke: 5 * S};
        lx = steg.bis + 12 * S;
        ly = steg.y - 13 * S;
        lichtung = [b * 0.16, b * 0.4];

        const zz = zufallMitSaat(21);
        schilf = Array.from({length: Math.round(klemme(b / 26, 14, 46))}, () => ({
            x: b * (0.04 + Math.pow(zz(), 0.8) * 0.5) + zz.zwischen(-15, 15),
            laenge: (h - hy) * zz.zwischen(0.22, 0.6),
            neigung: zz.zwischen(-0.25, 0.25),
            phase: zz.zwischen(0, TAU),
            breite: zz.zwischen(0.8, 2.0),
        }));
        glanz = Array.from({length: 60}, () => ({
            x: zz(), y: Math.pow(zz(), 1.6), laenge: zz.zwischen(0.02, 0.09), phase: zz.zwischen(0, TAU), tempo: zz.zwischen(0.4, 1.1),
        }));
        ferneLichter = [0.55, 0.6, 0.63, 0.08].map((x) => ({x: b * x, w: zz()}));

        wald = baueWald();
        sterneEbene = zeichneSterne();
        vordergrund = zeichneVordergrund();
    }

    // ── Wald am anderen Ufer ─────────────────────────────────────────────
    function baueWald() {
        const pfad = new Path2D();
        const zz = zufallMitSaat(5);
        const boden = (x) => hy - (2 + 1.5 * Math.sin(x * 0.013)) * S;
        const fichte = (x, fuss, hoehe, breite) => {
            // Spitze und drei Stufen je Seite
            pfad.moveTo(x, fuss - hoehe);
            [[0.3, 0.42], [0.55, 0.7], [0.8, 1]].forEach(([anteil, weite]) => {
                pfad.lineTo(x + breite * weite * 0.5, fuss - hoehe * (1 - anteil));
                pfad.lineTo(x + breite * weite * 0.28, fuss - hoehe * (1 - anteil) - hoehe * 0.04);
            });
            pfad.lineTo(x + breite * 0.5, fuss);
            pfad.lineTo(x - breite * 0.5, fuss);
            [[0.8, 1], [0.55, 0.7], [0.3, 0.42]].forEach(([anteil, weite]) => {
                pfad.lineTo(x - breite * weite * 0.28, fuss - hoehe * (1 - anteil) - hoehe * 0.04);
                pfad.lineTo(x - breite * weite * 0.5, fuss - hoehe * (1 - anteil));
            });
            pfad.closePath();
        };
        // Uferstreifen
        pfad.moveTo(-5, hy + 0.5);
        for (let x = -5; x <= b + 10; x += 10) pfad.lineTo(x, boden(x));
        pfad.lineTo(b + 10, hy + 0.5);
        pfad.closePath();
        // Hinter der Lichtung: ferner, niedriger Waldrand auf einer flachen Kuppe
        const [von, bis] = lichtung;
        for (let x = von - 20 * S; x < bis + 20 * S; x += zz.zwischen(2.5, 5) * S) {
            const hoehe = (5 + 9 * (0.5 + 0.5 * Math.sin(x * 0.02)) * zz.zwischen(0.6, 1.1)) * S;
            fichte(x, boden(x) - 1.5 * S, hoehe, hoehe * 0.3);
        }
        // Büsche und einzelne junge Fichten auf der Lichtung
        for (let x = von + zz.zwischen(10, 30) * S; x < bis; x += zz.zwischen(35, 90) * S) {
            if (zz() < 0.35) {
                const hoehe = zz.zwischen(8, 14) * S;
                fichte(x, boden(x) + 1, hoehe, hoehe * 0.3);
            } else {
                const breite = zz.zwischen(8, 16) * S;
                const hoehe = breite * zz.zwischen(0.3, 0.45);
                const fuss = boden(x) + 1;
                pfad.moveTo(x - breite / 2, fuss);
                pfad.bezierCurveTo(x - breite * 0.45, fuss - hoehe, x - breite * 0.1, fuss - hoehe * 1.2, x + breite * 0.05, fuss - hoehe * 0.8);
                pfad.bezierCurveTo(x + breite * 0.25, fuss - hoehe * 1.1, x + breite * 0.5, fuss - hoehe * 0.6, x + breite / 2, fuss);
                pfad.closePath();
            }
        }
        // Dichter Fichtenwald links und rechts der Lichtung, zum Rand hin niedriger auslaufend
        let x = -10;
        while (x < b + 10) {
            if (x > von && x < bis) {
                x = bis;
                continue;
            }
            const rand = klemme(Math.min(Math.abs(x - von), Math.abs(x - bis)) / (60 * S), 0, 1);
            const hoehe = (18 + 44 * (0.55 + 0.45 * Math.sin(x * 0.006 + 1.3)) * zz.zwischen(0.6, 1.1)) * S * (0.3 + 0.7 * rand);
            fichte(x, boden(x) + 1, hoehe, hoehe * zz.zwischen(0.22, 0.3));
            x += zz.zwischen(3, 7) * S;
        }
        return pfad;
    }

    // ── Vorgezeichnete Ebenen ────────────────────────────────────────────
    function neueEbene(breite, hoehe, zeichner) {
        const dpr = pixelFaktor();
        const leinwand = neueLeinwand(breite * dpr, hoehe * dpr);
        const g = leinwand.getContext('2d');
        g.setTransform(dpr, 0, 0, dpr, 0, 0);
        zeichner(g);
        return leinwand;
    }

    function zeichneSterne() {
        return neueEbene(b, Math.max(1, hy), (g) => {
            const zz = zufallMitSaat(7);
            const n = Math.round((b * hy) / 2400);
            for (let i = 0; i < n; i++) {
                const x = zz() * b;
                const y = Math.pow(zz(), 1.3) * hy;
                const m = zz();
                g.globalAlpha = 0.25 + m * 0.6;
                g.fillStyle = m > 0.85 ? '#fff2d6' : '#dff0ff';
                g.beginPath();
                g.arc(x, y, 0.4 + m * m * 1.1, 0, TAU);
                g.fill();
            }
        });
    }

    function tuer() {
        const {w, wand} = huette;
        return {x: hx - w * 0.22, breite: w * 0.24, hoehe: wand * 0.8};
    }

    function tuerPfad(g) {
        const d = tuer();
        g.beginPath();
        g.rect(d.x - d.breite / 2, by - d.hoehe, d.breite, d.hoehe);
    }

    function zeichneVordergrund() {
        return neueEbene(b, h, (g) => {
            const zz = zufallMitSaat(99);
            const {w, wand, giebel, tiefe, schraeg} = huette;
            const links = hx - w / 2;
            const rechts = hx + w / 2;
            const traufe = by - wand;
            const first = traufe - giebel;
            const dx = tiefe;
            const dy = -tiefe * schraeg;

            // Land vorne rechts
            const boden = g.createLinearGradient(0, by - 30 * S, 0, h);
            boden.addColorStop(0, '#1c2416');
            boden.addColorStop(1, '#05080a');
            g.fillStyle = boden;
            g.beginPath();
            g.moveTo(land.xa, landOben(land.xa));
            for (let x = land.xa; x <= b + 8; x += 8) g.lineTo(x, landOben(x));
            g.lineTo(b + 8, h + 5);
            g.lineTo(land.unten, h + 5);
            g.quadraticCurveTo(land.xa - 30 * S, h * 0.85, land.xa, landOben(land.xa));
            g.closePath();
            g.fill();
            // nasser Uferrand
            g.strokeStyle = 'rgba(150,190,200,0.18)';
            g.lineWidth = 1.5 * S;
            g.beginPath();
            g.moveTo(land.xa, landOben(land.xa) + 1);
            g.quadraticCurveTo(land.xa - 30 * S, h * 0.85, land.unten, h + 5);
            g.stroke();

            // Steg: Bohlen auf Pfählen, vom Ufer in den See
            g.fillStyle = '#0c0b09';
            for (let x = steg.bis + 4 * S; x < steg.von; x += 38 * S) {
                g.fillRect(x, steg.y, 3.5 * S, 14 * S);
                g.fillStyle = 'rgba(12,11,9,0.35)';
                g.fillRect(x, steg.y + 14 * S, 3.5 * S, 10 * S);
                g.fillStyle = '#0c0b09';
            }
            const holz = g.createLinearGradient(steg.bis, 0, steg.von, 0);
            holz.addColorStop(0, '#7a5530');
            holz.addColorStop(0.35, '#4a3520');
            holz.addColorStop(1, '#241b12');
            g.fillStyle = holz;
            g.beginPath();
            g.moveTo(steg.bis, steg.y);
            g.lineTo(steg.von, steg.y - 2 * S);
            g.lineTo(steg.von + 6 * S, steg.y + steg.dicke - 2 * S);
            g.lineTo(steg.bis + 4 * S, steg.y + steg.dicke);
            g.closePath();
            g.fill();
            g.strokeStyle = 'rgba(0,0,0,0.35)';
            g.lineWidth = 0.8 * S;
            for (let x = steg.bis + 7 * S; x < steg.von; x += 7 * S) {
                g.beginPath();
                g.moveTo(x, steg.y - 2 * S * ((x - steg.bis) / (steg.von - steg.bis)));
                g.lineTo(x + 2 * S, steg.y + steg.dicke - 2 * S * ((x - steg.bis) / (steg.von - steg.bis)));
                g.stroke();
            }

            // Zwei Holzstühle am Stegende, Blick auf den See (nach links)
            g.strokeStyle = '#a77a45';
            g.lineCap = 'round';
            [steg.bis + 40 * S, steg.bis + 72 * S].forEach((cx) => {
                if (cx > steg.von - 20 * S) return;
                const cb = steg.y;
                g.lineWidth = 4 * S;
                g.beginPath();
                g.moveTo(cx + 12 * S, cb - 40 * S);
                g.lineTo(cx - 2 * S, cb - 11 * S);
                g.lineTo(cx - 22 * S, cb - 14 * S);
                g.stroke();
                g.lineWidth = 2.6 * S;
                g.beginPath();
                g.moveTo(cx + 1 * S, cb - 12 * S);
                g.lineTo(cx + 7 * S, cb);
                g.moveTo(cx - 20 * S, cb - 13 * S);
                g.lineTo(cx - 24 * S, cb);
                g.stroke();
            });

            // Hütte (schwedisch rot, Satteldach) – Seitenwand perspektivisch nach hinten rechts
            g.fillStyle = '#3a1a15';
            g.beginPath();
            g.moveTo(rechts, by);
            g.lineTo(rechts + dx, by + dy);
            g.lineTo(rechts + dx, traufe + dy);
            g.lineTo(rechts, traufe);
            g.closePath();
            g.fill();
            // Giebelwand vorne
            g.fillStyle = '#5a2820';
            g.beginPath();
            g.moveTo(links, by);
            g.lineTo(links, traufe);
            g.lineTo(hx, first);
            g.lineTo(rechts, traufe);
            g.lineTo(rechts, by);
            g.closePath();
            g.fill();
            // Bretterfugen
            g.strokeStyle = 'rgba(0,0,0,0.22)';
            g.lineWidth = 1 * S;
            for (let x = links + 8 * S; x < rechts; x += 8 * S) {
                const oben = x < hx ? mische(traufe, first, (x - links) / (w / 2)) : mische(first, traufe, (x - hx) / (w / 2));
                g.beginPath();
                g.moveTo(x, by);
                g.lineTo(x, oben + 2 * S);
                g.stroke();
            }
            // Dachfläche (seitlich sichtbar) mit Überstand
            const ue = 7 * S;
            g.fillStyle = '#14161b';
            g.beginPath();
            g.moveTo(hx, first - 2 * S);
            g.lineTo(rechts + ue, traufe + ue * 0.6);
            g.lineTo(rechts + ue + dx, traufe + ue * 0.6 + dy);
            g.lineTo(hx + dx, first - 2 * S + dy);
            g.closePath();
            g.fill();
            // Dachkante vorne (Windbrett), vom Polarlicht leicht angestrahlt
            g.strokeStyle = '#c9c3b6';
            g.lineWidth = 2.5 * S;
            g.beginPath();
            g.moveTo(links - ue, traufe + ue * 0.6);
            g.lineTo(hx, first - 2 * S);
            g.lineTo(rechts + ue, traufe + ue * 0.6);
            g.stroke();
            g.strokeStyle = 'rgba(160,240,210,0.35)';
            g.lineWidth = 1.2 * S;
            g.beginPath();
            g.moveTo(hx, first - 3 * S);
            g.lineTo(hx + dx, first - 3 * S + dy);
            g.stroke();
            // Eckbretter
            g.fillStyle = '#b8b2a6';
            g.fillRect(links, traufe, 3 * S, wand);
            g.fillRect(rechts - 3 * S, traufe, 3 * S, wand);
            // Schornstein auf der Dachfläche
            const kx = hx + dx * 0.55 + 10 * S;
            const ky = first + dy * 0.55 + 6 * S;
            g.fillStyle = '#2a2420';
            g.fillRect(kx - 5 * S, ky - 20 * S, 10 * S, 22 * S);
            g.fillStyle = '#3a332d';
            g.fillRect(kx - 6 * S, ky - 22 * S, 12 * S, 3 * S);
            // Tür
            const d = tuer();
            g.fillStyle = '#1d120d';
            g.fillRect(d.x - d.breite / 2, by - d.hoehe, d.breite, d.hoehe);
            g.strokeStyle = '#b8b2a6';
            g.lineWidth = 2 * S;
            g.strokeRect(d.x - d.breite / 2, by - d.hoehe, d.breite, d.hoehe);
            // Fenster (Rahmen; das warme Licht kommt je Bild dazu)
            const f = fenster();
            g.fillStyle = '#ffcf7a';
            g.fillRect(f.x, f.y, f.g, f.g);
            g.strokeStyle = '#d8d2c4';
            g.lineWidth = 2.2 * S;
            g.strokeRect(f.x, f.y, f.g, f.g);
            g.lineWidth = 1.4 * S;
            g.beginPath();
            g.moveTo(f.x + f.g / 2, f.y);
            g.lineTo(f.x + f.g / 2, f.y + f.g);
            g.moveTo(f.x, f.y + f.g / 2);
            g.lineTo(f.x + f.g, f.y + f.g / 2);
            g.stroke();
            // Holzstapel an der Seitenwand
            g.fillStyle = '#2b2016';
            g.fillRect(rechts + 6 * S, by - 14 * S + dy * 0.2, 26 * S, 14 * S);
            g.fillStyle = 'rgba(190,150,100,0.35)';
            for (let i = 0; i < 9; i++) {
                g.beginPath();
                g.arc(rechts + 10 * S + (i % 3) * 8 * S, by - 4 * S - Math.floor(i / 3) * 4.5 * S + dy * 0.2, 1.8 * S, 0, TAU);
                g.fill();
            }

            // Ufergras
            for (let q = 0; q < 90; q++) {
                const gx = zz.zwischen(land.xa, b);
                const oben = landOben(gx);
                if (gx > links - 4 * S && gx < rechts + dx && zz() < 0.85) continue;
                const gb = oben + Math.pow(zz(), 2) * (h - oben) * 0.6;
                const gl = (10 + zz() * 26) * S;
                const warm = 1 - klemme(Math.hypot(gx - (fenster().x), gb - by) / (180 * S), 0, 1);
                g.strokeStyle = `rgba(${Math.round(40 + warm * 120)},${Math.round(52 + warm * 80)},${Math.round(30 + warm * 10)},0.85)`;
                g.lineWidth = (0.7 + zz()) * S;
                g.beginPath();
                g.moveTo(gx, gb);
                g.quadraticCurveTo(gx + zz.zwischen(-5, 5) * S, gb - gl * 0.6, gx + zz.zwischen(-10, 10) * S, gb - gl);
                g.stroke();
            }

            // Birke am rechten Rand
            g.strokeStyle = '#cfc8b8';
            g.lineWidth = 8 * S;
            g.beginPath();
            g.moveTo(b - 16 * S, landOben(b - 16 * S) + 8 * S);
            g.quadraticCurveTo(b - 34 * S, h * 0.4, b - 8 * S, h * 0.05);
            g.stroke();
            g.strokeStyle = 'rgba(20,18,14,0.75)';
            g.lineWidth = 2 * S;
            for (let i = 0; i < 14; i++) {
                const p = zz();
                const y = mische(landOben(b - 16 * S), h * 0.05, p);
                const x = b - 16 * S - Math.sin(p * Math.PI) * 10 * S + (p > 0.5 ? (p - 0.5) * 16 * S : 0);
                g.beginPath();
                g.moveTo(x - 3 * S, y);
                g.lineTo(x + zz.zwischen(-1, 3) * S, y + 1 * S);
                g.stroke();
            }
            const laub = ['#141205', '#2c260b', '#4a3c10', '#6b5417', '#8a6a1c'];
            g.globalAlpha = 0.9;
            for (let cl = 0; cl < 34; cl++) {
                const winkel = zz() * TAU;
                const radius = Math.sqrt(zz());
                const ccx = b - 6 * S + Math.cos(winkel) * radius * 150 * S;
                const ccy = h * 0.3 + Math.sin(winkel) * radius * h * 0.28;
                const waerme = klemme((ccy - h * 0.1) / (h * 0.5), 0, 1);
                for (let l = 0; l < 40; l++) {
                    const la = zz() * TAU;
                    const lr = Math.sqrt(zz()) * 32 * S;
                    g.fillStyle = laub[Math.min(4, Math.floor(zz() * 1.6 + waerme * 3.2 * zz() + 0.3))];
                    g.beginPath();
                    g.ellipse(ccx + Math.cos(la) * lr, ccy + Math.sin(la) * lr * 0.8, (2 + zz() * 3) * S, (1.2 + zz() * 1.8) * S, zz() * 3, 0, TAU);
                    g.fill();
                }
            }
            g.globalAlpha = 1;
        });
    }

    function fenster() {
        const {w, wand} = huette;
        const g = w * 0.2;
        return {x: hx + w * 0.12, y: by - wand * 0.72, g};
    }

    function schornstein() {
        const {w, wand, giebel, tiefe, schraeg} = huette;
        const first = by - wand - giebel;
        return {x: hx + tiefe * 0.55 + 10 * S, y: first - tiefe * schraeg * 0.55 + 6 * S - 22 * S};
    }

    // ── Polarlicht-Bänder ────────────────────────────────────────────────
    function aktualisiereFarben(index) {
        const i = klemme(Math.round(index) || 0, 0, FARBWELTEN.length - 1);
        if (i === farbIndex) return;
        farbIndex = i;
        streifen = FARBWELTEN[i].baender.map(farbstreifen);
    }

    function zeichneAurora() {
        actx.clearRect(0, 0, AW, AH);
        actx.globalCompositeOperation = 'lighter';
        BAENDER.forEach((band, i) => {
            const s = staerke[i];
            if (s < 0.01) return;
            const bild = streifen[i];
            for (let x = 0; x < AW; x += SPALTE) {
                const u = x / AW;
                const w1 = u * band.f1 * TAU + t * band.s1 + band.p1;
                const w2 = u * band.f2 * TAU - t * band.s2 + band.p2;
                const y = AH * (band.y + band.amp * (Math.sin(w1) * 0.62 + Math.sin(w2) * 0.38));
                const falte = 0.55 + 0.45 * Math.abs(Math.cos(w2 * 0.5 + w1));
                // feine, scharfe Strahlen: zwei Frequenzen, hoch potenziert
                const r1 = 0.5 + 0.5 * Math.sin(u * band.rf * TAU + t * band.rs + Math.sin(u * 7 + t * 0.3) * 2);
                const r2 = 0.5 + 0.5 * Math.sin(u * band.rf * 2.7 * TAU - t * band.rs * 1.3 + band.p2);
                const strahl = 0.25 + 0.75 * Math.pow(r1, 3) * (0.5 + 0.5 * r2);
                const fein = 0.75 + 0.25 * Math.sin(u * 230 + t * 1.6 + band.p1);
                const drift = 0.55 + 0.45 * Math.sin(u * TAU * 0.9 - t * 0.08 + band.p2);
                const rand = Math.min(1, u * 9, (1 - u) * 9);
                const a = s * (0.65 + 0.35 * falte) * strahl * fein * (0.55 + 0.45 * drift) * rand * flackern * 0.85;
                if (a < 0.012) continue;
                const hoehe = AH * band.h * (0.5 + 0.5 * Math.sin(w1 * 1.7 + t * 0.2 + band.p2)) * (0.55 + 0.45 * s) * (0.75 + 0.25 * r1);
                actx.globalAlpha = Math.min(1, a);
                actx.drawImage(bild, x, y - hoehe, SPALTE * 1.15, hoehe);
            }
        });
        actx.globalAlpha = 1;
        actx.globalCompositeOperation = 'source-over';
    }

    // ── Seltene Besuche ──────────────────────────────────────────────────
    /** Elch von der Seite (Blick nach +x), Fußpunkt im Ursprung, Einheit g = Schulterhöhe. */
    function zeichneElch(ctx, g, beine, kopfSenken, randlicht) {
        ctx.save();
        ctx.scale(g, g);
        // Beine: Paare mit gegenläufiger Phase; Hinterbeine mit Sprunggelenk nach hinten
        [[0.24, 0, 1], [0.32, Math.PI, 1], [-0.42, Math.PI, -1], [-0.34, 0, -1]].forEach(([x0, phase, art]) => {
            const p = beine + phase;
            const vor = Math.sin(p) * 0.11;
            const heben = Math.max(0, Math.cos(p)) * 0.05;
            const knie = art > 0 ? 0.02 : -0.07;
            ctx.beginPath();
            ctx.moveTo(x0 - 0.06, -0.6);
            ctx.lineTo(x0 + 0.06, -0.6);
            ctx.lineTo(x0 + vor * 0.5 + knie + 0.025, -0.3 - heben * 0.5);
            ctx.lineTo(x0 + vor + 0.018, -heben);
            ctx.lineTo(x0 + vor - 0.022, -heben);
            ctx.lineTo(x0 + vor * 0.5 + knie - 0.025, -0.3 - heben * 0.5);
            ctx.closePath();
            ctx.fill();
        });
        // Körper mit Widerrist-Buckel
        const ruecken = new Path2D();
        ruecken.moveTo(-0.6, -0.62);
        ruecken.bezierCurveTo(-0.68, -0.78, -0.62, -0.9, -0.45, -0.89);
        ruecken.bezierCurveTo(-0.2, -0.9, 0.05, -0.97, 0.18, -1.02);
        ruecken.bezierCurveTo(0.26, -1.04, 0.33, -1.0, 0.38, -0.93);
        const koerper = new Path2D(ruecken);
        koerper.lineTo(0.44, -0.78);
        koerper.bezierCurveTo(0.42, -0.62, 0.3, -0.56, 0.2, -0.56);
        koerper.lineTo(-0.5, -0.57);
        koerper.bezierCurveTo(-0.58, -0.57, -0.61, -0.59, -0.6, -0.62);
        koerper.closePath();
        ctx.fill(koerper);
        if (randlicht > 0) {
            ctx.strokeStyle = `rgba(150,255,210,${randlicht})`;
            ctx.lineWidth = 1.4 / g;
            ctx.stroke(ruecken);
        }
        // Hals und Kopf (senkt sich zum Trinken)
        ctx.translate(0.34, -0.9);
        ctx.rotate(kopfSenken * 1.15);
        const kopf = new Path2D();
        kopf.moveTo(-0.02, -0.07);
        kopf.lineTo(0.14, -0.08);
        kopf.bezierCurveTo(0.26, -0.06, 0.36, 0.0, 0.44, 0.09);
        kopf.bezierCurveTo(0.52, 0.11, 0.53, 0.23, 0.45, 0.25);
        kopf.bezierCurveTo(0.38, 0.26, 0.3, 0.2, 0.24, 0.17);
        kopf.bezierCurveTo(0.22, 0.24, 0.21, 0.31, 0.19, 0.33);
        kopf.bezierCurveTo(0.16, 0.3, 0.16, 0.22, 0.15, 0.15);
        kopf.lineTo(0.02, 0.16);
        kopf.closePath();
        ctx.fill(kopf);
        // Ohr
        ctx.beginPath();
        ctx.ellipse(0.1, -0.11, 0.07, 0.025, -0.6, 0, TAU);
        ctx.fill();
        // Schaufelgeweih (beide Seiten)
        [[0, 0], [0.05, -0.02]].forEach(([ox, oy]) => {
            ctx.beginPath();
            ctx.moveTo(0.12 + ox, -0.09 + oy);
            ctx.quadraticCurveTo(0.06 + ox, -0.15 + oy, 0.0 + ox, -0.2 + oy);
            ctx.lineTo(-0.13 + ox, -0.22 + oy);
            ctx.lineTo(-0.12 + ox, -0.3 + oy);
            ctx.lineTo(-0.06 + ox, -0.27 + oy);
            ctx.lineTo(-0.04 + ox, -0.36 + oy);
            ctx.lineTo(0.02 + ox, -0.29 + oy);
            ctx.lineTo(0.06 + ox, -0.37 + oy);
            ctx.lineTo(0.1 + ox, -0.28 + oy);
            ctx.lineTo(0.16 + ox, -0.33 + oy);
            ctx.lineTo(0.17 + ox, -0.24 + oy);
            ctx.quadraticCurveTo(0.14 + ox, -0.16 + oy, 0.16 + ox, -0.09 + oy);
            ctx.closePath();
            ctx.fill();
        });
        ctx.restore();
    }

    function neuerElch() {
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const e = {p: 0, alpha: 0, beine: 0, kopf: 0, trinken: 0, getrunken: false, halt: zufall(0.35, 0.6)};
        let flucht = false;
        return {
            ebene: 'ufer',
            schritt(dt, z) {
                if (z.unruhe > 0.45) flucht = true;
                const trinkt = !flucht && !e.getrunken && e.p >= e.halt;
                if (trinkt) {
                    e.trinken += dt;
                    if (e.trinken > 9) e.getrunken = true;
                } else {
                    const tempo = flucht ? 2.4 : 1;
                    e.p += (dt / 48) * tempo;
                    e.beine += dt * 2.6 * tempo;
                }
                e.kopf = blende(e.kopf, trinkt && e.trinken > 0.8 && e.trinken < 8.4 ? 1 : 0, dt, 0.7);
                e.alpha = blende(e.alpha, flucht || e.p > 0.94 ? 0 : 1, dt, 0.4);
                return e.p < 1 && !(flucht && e.alpha <= 0.01);
            },
            zeichne(ctx) {
                const [von, bis] = lichtung;
                const x = richtung > 0 ? mische(von - 30 * S, bis + 30 * S, e.p) : mische(bis + 30 * S, von - 30 * S, e.p);
                const g = 21 * S;
                const fuss = hy - 2.5 * S;
                ctx.globalAlpha = e.alpha;
                // Spiegelung im Wasser (gestaucht, blass)
                ctx.save();
                ctx.beginPath();
                ctx.rect(x - g * 2, hy, g * 4, g * 1.6);
                ctx.clip();
                ctx.translate(x, hy + (hy - fuss));
                ctx.scale(richtung, -0.8);
                ctx.globalAlpha = e.alpha * 0.35;
                ctx.fillStyle = '#02060a';
                zeichneElch(ctx, g, e.beine, e.kopf, 0);
                ctx.restore();
                // Elch mit Randlicht vom Polarlicht
                ctx.save();
                ctx.translate(x, fuss);
                ctx.scale(richtung, 1);
                ctx.fillStyle = '#04080c';
                zeichneElch(ctx, g, e.beine, e.kopf, 0.15 + ruhe * 0.4);
                ctx.restore();
                // Ringe beim Trinken
                if (e.kopf > 0.8) {
                    const r = ((echtzeit * 0.6) % 1);
                    ctx.strokeStyle = `rgba(190,230,240,${0.3 * (1 - r) * e.alpha})`;
                    ctx.lineWidth = 0.8;
                    ctx.beginPath();
                    ctx.ellipse(x + richtung * g * 0.85, hy + 1, g * 0.3 * (0.3 + r), g * 0.05 * (0.3 + r), 0, 0, TAU);
                    ctx.stroke();
                }
            },
        };
    }

    function neuesKanu() {
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const k = {p: 0, alpha: 0, dauer: zufall(55, 70), lage: zufall(0.18, 0.32), schlag: 0};
        let flucht = false;
        return {
            ebene: 'see',
            schritt(dt, z) {
                if (z.unruhe > 0.45) flucht = true;
                k.p += dt / k.dauer;
                k.schlag += dt * 0.55;
                k.alpha = blende(k.alpha, flucht || k.p > 0.92 ? 0 : 1, dt, flucht ? 0.8 : 0.3);
                return k.p < 1 && !(flucht && k.alpha <= 0.01);
            },
            zeichne(ctx) {
                const x = richtung > 0 ? mische(-0.06, 0.5, k.p) * b : mische(0.56, 0, k.p) * b;
                const y = hy + (h - hy) * k.lage;
                const g = 40 * S * (0.75 + k.lage);
                const L = g * 0.85;
                const wiegen = Math.sin(echtzeit * 1.2) * 0.012;
                ctx.globalAlpha = k.alpha;

                // Kielwasser: zwei auseinanderlaufende, blasse Linien hinter dem Boot
                ctx.strokeStyle = 'rgba(180,220,235,0.12)';
                ctx.lineWidth = 1;
                [-1, 1].forEach((v) => {
                    ctx.beginPath();
                    ctx.moveTo(x - richtung * L * 0.9, y + 1);
                    ctx.lineTo(x - richtung * L * 3.2, y + 1 + v * g * 0.06 + g * 0.04);
                    ctx.stroke();
                });

                // Laternenschein im Wasser
                const lxK = x - richtung * L * 0.8;
                const spur = ctx.createLinearGradient(0, y, 0, y + g * 1.8);
                spur.addColorStop(0, 'rgba(255,190,110,0.45)');
                spur.addColorStop(1, 'rgba(255,190,110,0)');
                ctx.fillStyle = spur;
                for (let i = 0; i < 20; i++) {
                    const wy = y + i * g * 0.09;
                    const ww = (2 + i * 0.35) * S * (0.7 + 0.3 * Math.sin(i * 1.3 + echtzeit * 2.1));
                    ctx.fillRect(lxK - ww / 2 + Math.sin(echtzeit * 1.8 + i * 0.5) * (1 + i * 0.1) * S, wy, ww, g * 0.1);
                }

                const boot = (spiegel) => {
                    ctx.save();
                    ctx.translate(x, y);
                    if (spiegel) ctx.scale(1, -0.75);
                    ctx.rotate(wiegen);
                    ctx.scale(richtung, 1);
                    // Rumpf (nur der Teil über Wasser)
                    ctx.fillStyle = spiegel ? '#02070b' : '#16191c';
                    ctx.beginPath();
                    ctx.moveTo(-L, -0.2 * g);
                    ctx.quadraticCurveTo(-L * 0.95, 0.02 * g, -L * 0.7, 0.02 * g);
                    ctx.lineTo(L * 0.7, 0.02 * g);
                    ctx.quadraticCurveTo(L * 0.95, 0.02 * g, L, -0.22 * g);
                    ctx.quadraticCurveTo(0, -0.04 * g, -L, -0.2 * g);
                    ctx.closePath();
                    ctx.fill();
                    if (!spiegel) {
                        // Bordkante, vom Laternenlicht gestreift
                        const kante = ctx.createLinearGradient(-L, 0, L, 0);
                        kante.addColorStop(0, 'rgba(255,190,110,0.85)');
                        kante.addColorStop(1, 'rgba(255,190,110,0.1)');
                        ctx.strokeStyle = kante;
                        ctx.lineWidth = 1.5 * S;
                        ctx.beginPath();
                        ctx.moveTo(-L, -0.2 * g);
                        ctx.quadraticCurveTo(0, -0.04 * g, L, -0.22 * g);
                        ctx.stroke();
                    }
                    // Paddler (sitzend) mit Paddel – alles unter der Wasserlinie wird abgeschnitten
                    const farbe = spiegel ? '#02070b' : '#0b0e11';
                    ctx.save();
                    ctx.beginPath();
                    ctx.rect(-2 * g, -2 * g, 4 * g, 2.02 * g);
                    ctx.clip();
                    ctx.fillStyle = farbe;
                    ctx.strokeStyle = farbe;
                    // Oberkörper leicht nach vorn geneigt
                    ctx.beginPath();
                    ctx.moveTo(-0.13 * g, -0.1 * g);
                    ctx.lineTo(-0.1 * g, -0.38 * g);
                    ctx.quadraticCurveTo(-0.02 * g, -0.47 * g, 0.07 * g, -0.4 * g);
                    ctx.lineTo(0.08 * g, -0.1 * g);
                    ctx.closePath();
                    ctx.fill();
                    if (!spiegel) {
                        // Randlicht der Laterne (hinter dem Paddler) auf Rücken und Mütze
                        ctx.strokeStyle = 'rgba(255,190,110,0.7)';
                        ctx.lineWidth = 0.02 * g;
                        ctx.beginPath();
                        ctx.moveTo(-0.13 * g, -0.1 * g);
                        ctx.lineTo(-0.1 * g, -0.38 * g);
                        ctx.quadraticCurveTo(-0.06 * g, -0.43 * g, -0.03 * g, -0.44 * g);
                        ctx.moveTo(-0.07 * g, -0.5 * g);
                        ctx.arc(-0.005 * g, -0.545 * g, 0.07 * g, Math.PI * 0.85, Math.PI * 1.5);
                        ctx.stroke();
                        ctx.strokeStyle = farbe;
                    }
                    // Kopf mit Wollmütze
                    ctx.beginPath();
                    ctx.arc(0.0, -0.51 * g, 0.068 * g, 0, TAU);
                    ctx.fill();
                    ctx.beginPath();
                    ctx.arc(-0.005 * g, -0.545 * g, 0.066 * g, Math.PI, 0);
                    ctx.fill();
                    ctx.beginPath();
                    ctx.arc(-0.01 * g, -0.62 * g, 0.022 * g, 0, TAU);
                    ctx.fill();
                    // Paddelzug: Blatt zieht im Wasser von vorn nach hinten, wird über Wasser zurückgeführt
                    const takt = k.schlag % 1;
                    let blattX;
                    let blattY;
                    if (takt < 0.68) {
                        blattX = mische(0.32, -0.24, sanft(takt / 0.68)) * g;
                        blattY = 0.16 * g;
                    } else {
                        const q = (takt - 0.68) / 0.32;
                        blattX = mische(-0.24, 0.32, sanft(q)) * g;
                        blattY = (-0.06 - Math.sin(q * Math.PI) * 0.08) * g;
                    }
                    const griffX = blattX * 0.35 + 0.04 * g;
                    const griffY = -0.66 * g;
                    const handX = mische(griffX, blattX, 0.42);
                    const handY = mische(griffY, blattY, 0.42);
                    ctx.lineCap = 'round';
                    ctx.lineWidth = 0.035 * g;
                    ctx.beginPath();
                    ctx.moveTo(0.0, -0.4 * g);
                    ctx.lineTo(griffX, griffY);
                    ctx.moveTo(0.02 * g, -0.38 * g);
                    ctx.lineTo(handX, handY);
                    ctx.stroke();
                    ctx.lineWidth = 0.022 * g;
                    ctx.beginPath();
                    ctx.moveTo(griffX, griffY);
                    ctx.lineTo(blattX, blattY);
                    ctx.stroke();
                    ctx.save();
                    ctx.translate(blattX, blattY);
                    ctx.rotate(Math.atan2(blattY - griffY, blattX - griffX) - Math.PI / 2);
                    ctx.beginPath();
                    ctx.ellipse(0, 0, 0.035 * g, 0.1 * g, 0, 0, TAU);
                    ctx.fill();
                    ctx.restore();
                    ctx.restore();
                    k.blatt = {x: blattX, imWasser: takt < 0.68};
                    // Laterne am Heck
                    ctx.strokeStyle = spiegel ? '#02070b' : '#0b0e11';
                    ctx.lineWidth = 1.2 * S;
                    ctx.beginPath();
                    ctx.moveTo(-L * 0.78, -0.18 * g);
                    ctx.lineTo(-L * 0.82, -0.5 * g);
                    ctx.lineTo(-L * 0.9, -0.5 * g);
                    ctx.stroke();
                    ctx.fillStyle = spiegel ? 'rgba(255,200,120,0.4)' : '#ffe2a8';
                    ctx.fillRect(-L * 0.93, -0.47 * g, 0.06 * g, 0.09 * g);
                    ctx.restore();
                };
                ctx.save();
                ctx.beginPath();
                ctx.rect(x - g * 2, y, g * 4, g);
                ctx.clip();
                ctx.globalAlpha = k.alpha * 0.4;
                boot(true);
                ctx.restore();
                boot(false);

                // Schein der Laterne
                ctx.globalCompositeOperation = 'lighter';
                const sx = x - richtung * L * 0.9;
                const sy = y - 0.43 * g;
                const schein = ctx.createRadialGradient(sx, sy, 0, sx, sy, g * 0.7);
                schein.addColorStop(0, 'rgba(255,200,110,0.55)');
                schein.addColorStop(1, 'rgba(255,170,80,0)');
                ctx.fillStyle = schein;
                ctx.fillRect(sx - g * 0.7, sy - g * 0.7, g * 1.4, g * 1.4);
                ctx.globalCompositeOperation = 'source-over';

                // Wirbel am Paddelblatt
                if (k.blatt?.imWasser) {
                    ctx.strokeStyle = `rgba(190,230,240,${0.3 * k.alpha})`;
                    ctx.lineWidth = 0.8;
                    ctx.beginPath();
                    ctx.ellipse(x + richtung * k.blatt.x, y + 1.5, g * 0.09, g * 0.02, 0, 0, TAU);
                    ctx.stroke();
                }
            },
        };
    }

    const besuche = new Besuchsplaner({elch: neuerElch, kanu: neuesKanu});

    // ── Klassenmaus: Hüttentür, hinter der Uferkuppe, aus dem Schilf (mit Schlafmütze) ──
    const mausGroesse = () => Math.min(b, h) * 0.06;
    const maus = new Klassenmaus([
        {name: 'tuer', ebene: 'tuer', ort: () => {
            const d = tuer();
            return {
                x: d.x, y: by, s: Math.min(d.breite / 1.45, d.hoehe / 2.05), von: 'seite', richtung: -1, muetze: true,
                clip: (ctx) => ctx.rect(d.x - d.breite / 2, by - d.hoehe, d.breite, d.hoehe),
            };
        }},
        {name: 'ufer', ebene: 'hinter-ufer', ort: () => {
            const x = Math.min(hx + huette.w / 2 + huette.tiefe + 40 * S, b - 70 * S);
            const s = mausGroesse() * 0.8;
            return {x, y: landOben(x) + s * 0.35, s, von: 'unten', muetze: true, dunkel: true};
        }},
        {name: 'schilf', ebene: 'schilf', ort: () => ({x: b * 0.26, y: h + mausGroesse() * 0.45, s: mausGroesse() * 1.15, von: 'unten', muetze: true, dunkel: true})},
    ]);

    // ── Ablauf ───────────────────────────────────────────────────────────
    function schritt(dt, z, einstellungen) {
        aktualisiereFarben(einstellungen.farben);
        echtzeit += dt;
        besuche.schritt(dt, z);
        maus.schritt(dt, z);

        // Ruhe sammeln (in der Stille, ca. eine Minute bis zum vollen Himmel) bzw. verlieren (bei Lärm)
        if (z.unruhe < 0.15) ruhe += (dt / 60) * (z.status === 'aus' ? 0.6 : 1);
        else if (z.unruhe > 0.6) ruhe -= dt * (0.05 + z.unruhe * 0.12);
        ruhe = klemme(ruhe, 0, 1);

        t += dt * (0.7 + ruhe * 0.9);
        const flackernZiel = z.status === 'laut' ? 0.45 + 0.3 * Math.sin(echtzeit * 30) : (z.status === 'fast' ? 0.85 : 1);
        flackern += (flackernZiel - flackern) * Math.min(1, dt * 6);

        const stufe = 0.85 / anzahl;
        staerke.forEach((s, i) => {
            const ziel = i < anzahl ? klemme((ruhe - i * stufe) / 0.25, 0, 1) : 0;
            staerke[i] = s + (ziel - s) * Math.min(1, dt * 1.5);
        });

        // Sternschnuppen bei vollem Himmel
        if (ruhe > 0.9 && z.unruhe < 0.1 && Math.random() < dt * 0.12) {
            schnuppen.push({x: zufall(0, 0.7), y: zufall(0, 0.4), leben: 0});
        }
        for (let i = schnuppen.length - 1; i >= 0; i--) {
            schnuppen[i].leben += dt * 1.4;
            if (schnuppen[i].leben > 1) schnuppen.splice(i, 1);
        }
    }

    function zeichneWasser(ctx, breite, hoehe) {
        const wh = hoehe - hy;
        const wasser = ctx.createLinearGradient(0, hy, 0, hoehe);
        wasser.addColorStop(0, `rgb(${Math.round(10 + ruhe * 6)},${Math.round(34 + ruhe * 30)},${Math.round(52 + ruhe * 14)})`);
        wasser.addColorStop(0.5, '#071a26');
        wasser.addColorStop(1, '#020810');
        ctx.fillStyle = wasser;
        ctx.fillRect(0, hy, breite, wh);

        // Spiegelung der Bänder: gespiegelt, in dünnen Streifen sanft gewellt (nahe am Ufer ruhig,
        // zum Betrachter hin stärker), blasser und bläulicher als das Original.
        const streifenAnzahl = Math.round(klemme(wh / 3, 30, 110));
        const sh = wh / streifenAnzahl;
        const quelleH = AH / streifenAnzahl;
        ctx.globalCompositeOperation = 'lighter';
        for (let j = 0; j < streifenAnzahl; j++) {
            const tiefe = j / streifenAnzahl;
            const welle = (Math.sin(j * 0.55 + echtzeit * 1.1) * 0.6 + Math.sin(j * 1.7 - echtzeit * 0.7) * 0.4) * (0.5 + tiefe * 6) * S;
            ctx.globalAlpha = 0.5 * (1 - tiefe * 0.55);
            ctx.drawImage(aurora, 0, AH - (j + 1) * quelleH, AW, quelleH, welle - 6, hy + j * sh, breite + 12, sh + 0.6);
        }
        ctx.globalAlpha = 1;
        ctx.globalCompositeOperation = 'source-over';

        // Spiegelung des Waldes (dunkel, kurz)
        ctx.save();
        ctx.translate(0, hy * 2);
        ctx.scale(1, -1);
        ctx.globalAlpha = 0.55;
        ctx.fillStyle = '#02060a';
        ctx.fill(wald);
        ctx.restore();

        // feine Glanzlinien auf dem Wasser, die langsam ziehen
        ctx.lineWidth = 1;
        glanz.forEach((gl) => {
            const y = hy + 4 + gl.y * (wh - 4);
            const nah = gl.y;
            const a = (0.05 + 0.1 * ruhe) * (0.5 + 0.5 * Math.sin(echtzeit * gl.tempo + gl.phase));
            const x = ((gl.x + echtzeit * 0.004 * gl.tempo) % 1.1 - 0.05) * breite;
            ctx.strokeStyle = `rgba(190,235,240,${a})`;
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x + gl.laenge * breite * (0.4 + nah), y);
            ctx.stroke();
        });

        // Abdunkeln zum Betrachter hin
        const tiefe = ctx.createLinearGradient(0, hy, 0, hoehe);
        tiefe.addColorStop(0, 'rgba(2,8,16,0)');
        tiefe.addColorStop(1, 'rgba(2,8,16,0.65)');
        ctx.fillStyle = tiefe;
        ctx.fillRect(0, hy, breite, wh);
    }

    function zeichne(ctx, breite, hoehe) {
        if (farbIndex < 0) aktualisiereFarben(0);
        zeichneAurora();

        // Himmel
        const himmel = ctx.createLinearGradient(0, 0, 0, hy);
        himmel.addColorStop(0, '#040818');
        himmel.addColorStop(0.55, '#0a1a3c');
        himmel.addColorStop(1, `rgb(${Math.round(14 + ruhe * 6)},${Math.round(40 + ruhe * 40)},${Math.round(66 + ruhe * 20)})`);
        ctx.fillStyle = himmel;
        ctx.fillRect(0, 0, breite, hy + 1);
        ctx.globalAlpha = 0.9 - ruhe * 0.35;
        ctx.drawImage(sterneEbene, 0, 0, breite, hy);
        ctx.globalAlpha = 1;

        // Polarlicht mit leichtem Schein
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.globalCompositeOperation = 'lighter';
        ctx.drawImage(aurora, 0, 0, breite, hy);
        ctx.globalAlpha = 0.22;
        ctx.drawImage(aurora, -breite * 0.03, -hy * 0.05, breite * 1.06, hy * 1.08);
        ctx.globalAlpha = 1;
        ctx.globalCompositeOperation = 'source-over';

        // Sternschnuppen
        schnuppen.forEach((st) => {
            const sx = st.x * breite + st.leben * 240 * S;
            const sy = st.y * hy + st.leben * 90 * S;
            const spur = ctx.createLinearGradient(sx - 70 * S, sy - 26 * S, sx, sy);
            spur.addColorStop(0, 'rgba(255,255,255,0)');
            spur.addColorStop(1, `rgba(255,255,255,${1 - st.leben})`);
            ctx.strokeStyle = spur;
            ctx.lineWidth = 1.4 * S;
            ctx.beginPath();
            ctx.moveTo(sx - 70 * S, sy - 26 * S);
            ctx.lineTo(sx, sy);
            ctx.stroke();
        });

        zeichneWasser(ctx, breite, hoehe);

        // Wald am anderen Ufer mit fernen Hüttenlichtern
        ctx.fillStyle = '#03070b';
        ctx.fill(wald);
        ferneLichter.forEach((fl) => {
            ctx.fillStyle = 'rgba(255,214,150,0.9)';
            ctx.fillRect(fl.x, hy - 4 * S, 1.6 * S, 1.6 * S);
            const laenge = (14 + fl.w * 30) * S;
            const spiegel = ctx.createLinearGradient(0, hy, 0, hy + laenge);
            spiegel.addColorStop(0, 'rgba(255,214,150,0.35)');
            spiegel.addColorStop(1, 'rgba(255,214,150,0)');
            ctx.fillStyle = spiegel;
            ctx.fillRect(fl.x, hy + 1, 1.6 * S, laenge);
        });
        besuche.zeichne(ctx, 'ufer');
        besuche.zeichne(ctx, 'see');

        // Steg-Pfähle und Laterne spiegeln sich
        const lf = 0.88 + 0.12 * Math.sin(echtzeit * 11) * Math.sin(echtzeit * 3.7);
        const laternenSpur = ctx.createLinearGradient(0, steg.y + steg.dicke, 0, steg.y + 110 * S);
        laternenSpur.addColorStop(0, `rgba(255,170,80,${0.45 * lf})`);
        laternenSpur.addColorStop(1, 'rgba(255,170,80,0)');
        ctx.fillStyle = laternenSpur;
        for (let i = 0; i < 28; i++) {
            const wy = steg.y + steg.dicke + i * 4 * S;
            const ww = (3 + i * 0.45) * S * (0.7 + 0.3 * Math.sin(i * 1.3 + echtzeit * 2.1));
            ctx.fillRect(lx - ww / 2 + Math.sin(echtzeit * 1.6 + i * 0.45) * (1 + i * 0.12) * S, wy, ww, 3.2 * S);
        }

        maus.zeichne(ctx, 'hinter-ufer');
        ctx.drawImage(vordergrund, 0, 0, breite, hoehe);
        maus.zeichne(ctx, 'tuer');

        // Fensterlicht und Rauch aus dem Schornstein
        const f = fenster();
        ctx.globalCompositeOperation = 'lighter';
        const fl = ctx.createRadialGradient(f.x + f.g / 2, f.y + f.g / 2, 0, f.x + f.g / 2, f.y + f.g / 2, f.g * 2.6);
        fl.addColorStop(0, `rgba(255,190,100,${0.35 * lf})`);
        fl.addColorStop(1, 'rgba(255,170,80,0)');
        ctx.fillStyle = fl;
        ctx.fillRect(f.x - f.g * 2.1, f.y - f.g * 2.1, f.g * 5.2, f.g * 5.2);
        ctx.globalCompositeOperation = 'source-over';
        const k = schornstein();
        for (let i = 0; i < 7; i++) {
            const alter = (echtzeit * 0.09 + i / 7) % 1;
            ctx.fillStyle = `rgba(150,160,170,${0.16 * Math.sin(alter * Math.PI)})`;
            ctx.beginPath();
            ctx.arc(k.x + alter * 30 * S + Math.sin(alter * 5 + echtzeit * 0.3) * 3 * S, k.y - alter * 70 * S, (3 + alter * 11) * S, 0, TAU);
            ctx.fill();
        }

        // Laterne am Stegende
        ctx.globalCompositeOperation = 'lighter';
        const schein = ctx.createRadialGradient(lx, ly, 0, lx, ly, 110 * S);
        schein.addColorStop(0, `rgba(255,190,100,${0.6 * lf})`);
        schein.addColorStop(0.25, `rgba(255,150,60,${0.18 * lf})`);
        schein.addColorStop(1, 'rgba(255,140,50,0)');
        ctx.fillStyle = schein;
        ctx.fillRect(lx - 110 * S, ly - 110 * S, 220 * S, 220 * S);
        ctx.globalCompositeOperation = 'source-over';
        ctx.fillStyle = '#20160c';
        ctx.fillRect(lx - 6 * S, ly - 10 * S, 12 * S, 2.5 * S);
        ctx.fillRect(lx - 6 * S, ly + 8 * S, 12 * S, 3 * S);
        ctx.fillStyle = `rgba(255,236,190,${lf})`;
        ctx.fillRect(lx - 4.5 * S, ly - 8 * S, 9 * S, 16 * S);

        // Schilf im Vordergrund (vor der Maus)
        maus.zeichne(ctx, 'schilf');
        ctx.lineCap = 'round';
        ctx.strokeStyle = `rgba(${Math.round(4 + ruhe * 14)},${Math.round(12 + ruhe * 40)},${Math.round(14 + ruhe * 30)},0.95)`;
        schilf.forEach((rd) => {
            const wiegen = Math.sin(t * 0.9 + rd.phase) * 6 * S;
            ctx.lineWidth = rd.breite * S;
            ctx.beginPath();
            ctx.moveTo(rd.x, hoehe + 4);
            ctx.quadraticCurveTo(rd.x + rd.neigung * rd.laenge * 0.4, hoehe - rd.laenge * 0.6, rd.x + rd.neigung * rd.laenge + wiegen, hoehe - rd.laenge);
            ctx.stroke();
        });
    }

    function info() {
        if (ruhe >= 0.93) return 'Der ganze Himmel leuchtet';
        return `Himmel zu ${Math.round(ruhe * 100)} % erleuchtet`;
    }

    return {
        mengeLabel: 'Anzahl der Lichtbänder',
        texte: {
            ruhig: 'Ganz still – die Polarlichter erwachen.',
            fast: 'Psst – die Lichter flackern schon.',
            laut: 'Zu laut – die Lichter ziehen sich zurück!',
        },
        groesse,
        schritt,
        zeichne,
        info,
        besuch: (name) => {
            if (name === 'voll') {
                ruhe = 1;
                return true;
            }
            return mausBesuch(maus, name) ?? besuche.starte(name);
        },
    };
}
