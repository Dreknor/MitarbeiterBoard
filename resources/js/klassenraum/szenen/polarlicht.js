/*
 * Szene „Polarlicht“: Nachthimmel über einem stillen See mit Hütte, Steg und Laterne.
 *
 * Je länger es leise ist, desto mehr Polarlicht-Bänder erwachen nacheinander am Himmel und
 * spiegeln sich im Wasser; ist der Himmel voll, fallen ab und zu Sternschnuppen. Wird es laut,
 * flackern die Lichter und ziehen sich zurück (schneller als sie gekommen sind). Die Farbwelt
 * (bunt, grün, violett, eisblau) ist eine eigene Einstellung der Szene (`farben`).
 *
 * Seltene Besuche (nur bei Ruhe): Elch am fernen Ufer, Kanu mit Laterne auf dem See.
 * Die Klassenmaus (mit Schlafmütze) guckt aus der Hüttentür, hinter dem Ufer oder aus dem Schilf.
 * Sterne und Vordergrund (Ufer, Steg, Hütte, Birke) werden je Größenänderung vorgezeichnet;
 * die Bänder entstehen in einer kleinen Zwischenleinwand und werden hochskaliert (weicher Schein).
 *
 * Vorschau: ?besuch=elch|kanu|maus, ?besuch=voll (Himmel sofort voll)
 */

import {klemme, zufall, zufallMitSaat} from '../shared/hilfen';
import {Besuchsplaner, blende} from '../shared/besuche';
import {Klassenmaus, mausBesuch} from '../shared/maus';

const TAU = Math.PI * 2;

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
    {y: 0.62, amp: 0.1, h: 0.46, f1: 1.1, f2: 2.7, s1: 0.11, s2: 0.17, p1: 0.3, p2: 1.9, rf: 9, rs: 0.5},
    {y: 0.48, amp: 0.13, h: 0.42, f1: 0.8, f2: 3.4, s1: 0.09, s2: 0.21, p1: 2.2, p2: 0.4, rf: 13, rs: -0.4},
    {y: 0.74, amp: 0.07, h: 0.34, f1: 1.6, f2: 4.1, s1: 0.14, s2: 0.12, p1: 4.0, p2: 3.1, rf: 17, rs: 0.7},
    {y: 0.36, amp: 0.12, h: 0.36, f1: 1.3, f2: 2.2, s1: 0.07, s2: 0.19, p1: 5.2, p2: 2.6, rf: 11, rs: -0.6},
    {y: 0.56, amp: 0.16, h: 0.52, f1: 0.6, f2: 3.0, s1: 0.13, s2: 0.1, p1: 1.1, p2: 5.0, rf: 15, rs: 0.45},
    {y: 0.42, amp: 0.09, h: 0.3, f1: 1.9, f2: 3.7, s1: 0.1, s2: 0.15, p1: 3.3, p2: 4.4, rf: 19, rs: -0.5},
    {y: 0.68, amp: 0.11, h: 0.4, f1: 0.9, f2: 2.4, s1: 0.12, s2: 0.18, p1: 0.9, p2: 2.2, rf: 12, rs: 0.55},
];
const STANDARD_BAENDER = 5;

// Zwischenleinwand der Bänder (klein, wird weich hochskaliert)
const AW = 360;
const AH = 200;

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
    const s = neueLeinwand(1, 160);
    const g = s.getContext('2d');
    const verlauf = g.createLinearGradient(0, 0, 0, 160);
    verlauf.addColorStop(0, hexA(spitze, 0));
    verlauf.addColorStop(0.3, hexA(spitze, 0.2));
    verlauf.addColorStop(0.55, hexA(mitte, 0.6));
    verlauf.addColorStop(0.84, hexA(saum, 1));
    verlauf.addColorStop(0.95, hexA('#ffffff', 0.9));
    verlauf.addColorStop(1, hexA(saum, 0));
    g.fillStyle = verlauf;
    g.fillRect(0, 0, 1, 160);
    return s;
}

export function erzeugePolarlicht() {
    let b = 0;
    let h = 0;
    let t = 0;           // Animationszeit (läuft in Ruhe schneller)
    let echtzeit = 0;    // für Flackern und Laterne
    let ruhe = 0.08;     // 0–1: wie weit der Himmel schon leuchtet
    let flackern = 1;
    let anzahl = STANDARD_BAENDER;
    let farbIndex = -1;
    let streifen = [];
    const staerke = BAENDER.map(() => 0);
    const schnuppen = [];

    // Maße (in CSS-Pixeln) – in groesse() gesetzt
    let hy = 0;   // Horizont
    let S = 1;    // Maßstab der Gegenstände
    let by = 0;   // Steg-/Hüttenboden
    let hx = 0;   // Hüttenmitte
    let lx = 0;   // Laterne
    let ly = 0;
    let schilf = [];
    let ferneLichter = [];
    let sterneEbene = null;
    let vordergrund = null;

    const aurora = neueLeinwand(AW, AH);
    const actx = aurora.getContext('2d');

    const pixelFaktor = () => Math.min(2, window.devicePixelRatio || 1);
    const fernesUfer = (x) => hy - (5 + 5 * Math.sin(x * 0.021) + 3 * Math.sin(x * 0.11 + 2) + 2 * Math.sin(x * 0.37)) * S;
    const huette = () => ({bw: 170 * S, tw: 108 * S, hh: 100 * S});

    function groesse(breite, hoehe, einstellungen) {
        const neu = breite !== b || hoehe !== h;
        b = breite;
        h = hoehe;
        anzahl = klemme(Math.round((einstellungen.menge / 100) * STANDARD_BAENDER), 2, BAENDER.length);
        if (!neu && vordergrund) return;

        hy = h * 0.64;
        S = klemme(Math.min(b, h) / 720, 0.5, 1.5);
        by = hy + (h - hy) * 0.4;
        hx = b * (b < 600 ? 0.56 : 0.66);
        lx = Math.min(hx + 235 * S, b - 30 * S);
        ly = by - 20 * S;

        const zz = zufallMitSaat(21);
        schilf = Array.from({length: Math.round(klemme(b / 26, 14, 46))}, () => ({
            x: b * (0.08 + Math.pow(zz(), 0.8) * 0.62) + zz.zwischen(-15, 15),
            laenge: (h - hy) * zz.zwischen(0.25, 0.75),
            neigung: zz.zwischen(-0.25, 0.25),
            phase: zz.zwischen(0, TAU),
            breite: zz.zwischen(0.8, 2.2),
        }));
        ferneLichter = Array.from({length: 5}, () => ({x: b * zz.zwischen(0.12, 0.62), w: zz()}));

        sterneEbene = zeichneSterne();
        vordergrund = zeichneVordergrund();
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
            const n = Math.round((b * hy) / 2600);
            for (let i = 0; i < n; i++) {
                const x = zz() * b;
                const y = Math.pow(zz(), 1.3) * hy;
                const m = zz();
                g.globalAlpha = 0.25 + m * 0.6;
                g.fillStyle = m > 0.85 ? '#fff2d6' : '#dff0ff';
                g.beginPath();
                g.arc(x, y, 0.4 + m * m * 1.2, 0, TAU);
                g.fill();
            }
        });
    }

    function zeichneVordergrund() {
        return neueEbene(b, h, (g) => {
            const zz = zufallMitSaat(99);
            const {bw, tw, hh} = huette();
            const oben = by - hh;

            // Ufer rechts
            const ufer = g.createLinearGradient(0, by - 10 * S, 0, h);
            ufer.addColorStop(0, '#2a2a10');
            ufer.addColorStop(1, '#070a06');
            g.fillStyle = ufer;
            g.beginPath();
            g.moveTo(b + 5, by - 30 * S);
            g.quadraticCurveTo(lx + 40 * S, by - 26 * S, lx - 20 * S, by + 14 * S);
            g.quadraticCurveTo(lx + 10 * S, h * 0.9, b - 60 * S, h + 5);
            g.lineTo(b + 5, h + 5);
            g.closePath();
            g.fill();

            // Steg
            const steg = g.createLinearGradient(hx - bw * 0.2, 0, lx + 30 * S, 0);
            steg.addColorStop(0, '#1c1a14');
            steg.addColorStop(0.75, '#6b4a22');
            steg.addColorStop(1, '#a8743a');
            g.fillStyle = steg;
            g.beginPath();
            g.moveTo(hx - bw * 0.15, by);
            g.lineTo(lx + 60 * S, by - 6 * S);
            g.lineTo(lx + 70 * S, by + 4 * S);
            g.lineTo(hx - bw * 0.1, by + 11 * S);
            g.closePath();
            g.fill();
            g.fillStyle = 'rgba(0,0,0,0.5)';
            g.fillRect(hx - bw * 0.1, by + 9 * S, lx - hx + 70 * S, 3 * S);

            // Hütte: sechseckiger Korpus aus drei sichtbaren Flächen
            const unten = [hx - bw / 2, hx - bw * 0.12, hx + bw * 0.3, hx + bw / 2];
            const dach = [hx - tw / 2, hx - tw * 0.12, hx + tw * 0.3, hx + tw / 2];
            ['#3c4541', '#8f9382', '#c2b596'].forEach((farbe, i) => {
                g.fillStyle = farbe;
                g.beginPath();
                g.moveTo(unten[i], by);
                g.lineTo(dach[i], oben);
                g.lineTo(dach[i + 1], oben);
                g.lineTo(unten[i + 1], by);
                g.closePath();
                g.fill();
            });
            // Tür (dunkel – die Klassenmaus guckt hier heraus)
            g.fillStyle = '#2b2218';
            tuerPfad(g);
            g.fill();
            g.strokeStyle = 'rgba(255,180,90,0.55)';
            g.lineWidth = 1.5 * S;
            g.stroke();
            // kleine Fenster
            g.fillStyle = '#bfe8ff';
            for (let k = 0; k < 3; k++) {
                g.beginPath();
                g.arc(hx - bw * 0.4 + k * 9 * S + 16 * S, by - 22 * S, 2.2 * S, 0, TAU);
                g.fill();
            }
            // Dach mit Rauchabzug
            g.fillStyle = '#10141a';
            g.beginPath();
            g.moveTo(dach[0] - 8 * S, oben + 2 * S);
            g.lineTo(hx - 6 * S, oben - 34 * S);
            g.lineTo(hx + 6 * S, oben - 34 * S);
            g.lineTo(dach[3] + 8 * S, oben + 2 * S);
            g.closePath();
            g.fill();
            g.fillRect(hx - 5 * S, oben - 44 * S, 10 * S, 11 * S);
            g.beginPath();
            g.arc(hx, oben - 45 * S, 7 * S, Math.PI, 0);
            g.fill();

            // Zwei Holzstühle auf dem Steg
            g.strokeStyle = '#b9864a';
            g.lineCap = 'round';
            for (let c = 0; c < 2; c++) {
                const cx = hx + bw * (0.78 + c * 0.3);
                const cb = by - 3 * S;
                if (cx > lx - 18 * S) break;
                g.lineWidth = 4.5 * S;
                g.beginPath();
                g.moveTo(cx - 12 * S, cb - 44 * S);
                g.lineTo(cx + 2 * S, cb - 12 * S);
                g.lineTo(cx + 24 * S, cb - 16 * S);
                g.stroke();
                g.lineWidth = 3 * S;
                g.beginPath();
                g.moveTo(cx - 2 * S, cb - 14 * S);
                g.lineTo(cx - 8 * S, cb);
                g.moveTo(cx + 22 * S, cb - 15 * S);
                g.lineTo(cx + 26 * S, cb);
                g.stroke();
            }

            // Ufergras im Laternenlicht
            for (let q = 0; q < 70; q++) {
                const gx = lx - 10 * S + zz() * (b - lx + 20 * S);
                const gb = by + zz() * (h - by) * 0.7;
                const gl = (16 + zz() * 34) * S;
                const nah = 1 - klemme(Math.abs(gx - lx) / (220 * S), 0, 1);
                g.strokeStyle = `rgba(${Math.round(70 + nah * 150)},${Math.round(60 + nah * 100)},${Math.round(14 + nah * 20)},0.85)`;
                g.lineWidth = (0.8 + zz()) * S;
                g.beginPath();
                g.moveTo(gx, gb);
                g.quadraticCurveTo(gx + zz.zwischen(-6, 6) * S, gb - gl * 0.6, gx + zz.zwischen(-11, 11) * S, gb - gl);
                g.stroke();
            }

            // Birke am rechten Rand
            g.strokeStyle = '#17130a';
            g.lineWidth = 9 * S;
            g.beginPath();
            g.moveTo(b - 14 * S, by + 10 * S);
            g.quadraticCurveTo(b - 34 * S, h * 0.4, b - 8 * S, h * 0.05);
            g.stroke();
            const laub = ['#141205', '#2c260b', '#4a3c10', '#6b5417', '#8a6a1c'];
            g.globalAlpha = 0.9;
            for (let cl = 0; cl < 34; cl++) {
                const winkel = zz() * TAU;
                const radius = Math.sqrt(zz());
                const ccx = b - 6 * S + Math.cos(winkel) * radius * 150 * S;
                const ccy = h * 0.3 + Math.sin(winkel) * radius * h * 0.3;
                const waerme = klemme((ccy - h * 0.1) / (h * 0.5), 0, 1);
                for (let l = 0; l < 46; l++) {
                    const la = zz() * TAU;
                    const lr = Math.sqrt(zz()) * 34 * S;
                    g.fillStyle = laub[Math.min(4, Math.floor(zz() * 1.6 + waerme * 3.2 * zz() + 0.3))];
                    g.beginPath();
                    g.ellipse(ccx + Math.cos(la) * lr, ccy + Math.sin(la) * lr * 0.8, (2 + zz() * 3.5) * S, (1.2 + zz() * 2) * S, zz() * 3, 0, TAU);
                    g.fill();
                }
            }
            g.globalAlpha = 1;
        });
    }

    function tuerPfad(g) {
        const {bw, hh} = huette();
        const oben = by - hh;
        const x0 = hx + bw * 0.02;
        const x1 = hx + bw * 0.24;
        g.beginPath();
        g.moveTo(x0, by - 2 * S);
        g.lineTo(x0 + 5 * S, oben + 16 * S);
        g.lineTo(x1 - 3 * S, oben + 16 * S);
        g.lineTo(x1 + 4 * S, by - 2 * S);
        g.closePath();
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
            const streifenBild = streifen[i];
            for (let x = 0; x < AW; x += 2) {
                const u = x / AW;
                const w1 = u * band.f1 * TAU + t * band.s1 + band.p1;
                const w2 = u * band.f2 * TAU - t * band.s2 + band.p2;
                const y = AH * (band.y + band.amp * (Math.sin(w1) * 0.62 + Math.sin(w2) * 0.38));
                const falte = 0.55 + 0.45 * Math.abs(Math.cos(w2 * 0.5 + w1));
                const strahl = 0.5 + 0.5 * Math.sin(u * band.rf * TAU + t * band.rs + Math.sin(u * 7 + t * 0.3) * 2);
                const fein = 0.72 + 0.28 * Math.sin(u * 95 + t * 1.6 + band.p1);
                const drift = 0.55 + 0.45 * Math.sin(u * TAU * 0.9 - t * 0.08 + band.p2);
                const rand = Math.min(1, u * 9, (1 - u) * 9);
                const a = s * (0.7 + 0.3 * falte) * (0.4 + 0.6 * strahl * strahl) * fein * (0.55 + 0.45 * drift) * rand * flackern * 0.95;
                if (a < 0.012) continue;
                const hoehe = AH * band.h * (0.55 + 0.45 * Math.sin(w1 * 1.7 + t * 0.2 + band.p2)) * (0.6 + 0.4 * s);
                actx.globalAlpha = Math.min(1, a);
                actx.drawImage(streifenBild, x, y - hoehe, 2.3, hoehe);
            }
        });
        actx.globalAlpha = 1;
        actx.globalCompositeOperation = 'source-over';
    }

    // ── Seltene Besuche ──────────────────────────────────────────────────
    function neuerElch() {
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const e = {p: 0, alpha: 0, schritt: 0, dauer: zufall(40, 55)};
        let flucht = false;
        return {
            ebene: 'ufer',
            schritt(dt, z) {
                if (z.unruhe > 0.45) flucht = true;
                e.p += dt / e.dauer * (flucht ? 2.5 : 1);
                e.schritt += dt * (flucht ? 6 : 2.2);
                e.alpha = blende(e.alpha, flucht || e.p > 0.92 ? 0 : 1, dt, 0.5);
                return e.p < 1 && !(flucht && e.alpha <= 0.01);
            },
            zeichne(ctx) {
                const x = (richtung > 0 ? -0.05 + e.p * 0.75 : 0.7 - e.p * 0.75) * b;
                const g = 34 * S;
                ctx.globalAlpha = e.alpha;
                ctx.translate(x, fernesUfer(x) + 1);
                ctx.scale(richtung, 1);
                ctx.fillStyle = '#02060b';
                // Beine (abwechselnd)
                [-0.32, -0.18, 0.2, 0.34].forEach((bx, i) => {
                    const schwung = Math.sin(e.schritt + (i % 2) * Math.PI) * 0.06;
                    ctx.fillRect((bx + schwung) * g, -0.55 * g, 0.07 * g, 0.55 * g);
                });
                // Körper, Buckel, Hals und Kopf
                ctx.beginPath();
                ctx.ellipse(0, -0.68 * g, 0.48 * g, 0.2 * g, 0, 0, TAU);
                ctx.fill();
                ctx.beginPath();
                ctx.moveTo(0.18 * g, -0.82 * g);
                ctx.quadraticCurveTo(0.42 * g, -1.0 * g, 0.55 * g, -0.86 * g);
                ctx.lineTo(0.72 * g, -0.7 * g);
                ctx.lineTo(0.66 * g, -0.6 * g);
                ctx.lineTo(0.4 * g, -0.66 * g);
                ctx.closePath();
                ctx.fill();
                // Schaufelgeweih
                ctx.beginPath();
                ctx.ellipse(0.5 * g, -1.0 * g, 0.16 * g, 0.06 * g, -0.4, 0, TAU);
                ctx.ellipse(0.62 * g, -1.0 * g, 0.14 * g, 0.05 * g, 0.5, 0, TAU);
                ctx.fill();
            },
        };
    }

    function neuesKanu() {
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const k = {p: 0, alpha: 0, dauer: zufall(45, 60), lage: zufall(0.22, 0.4)};
        let flucht = false;
        return {
            ebene: 'see',
            schritt(dt, z) {
                if (z.unruhe > 0.45) flucht = true;
                k.p += dt / k.dauer;
                k.alpha = blende(k.alpha, flucht || k.p > 0.9 ? 0 : 1, dt, flucht ? 0.8 : 0.3);
                return k.p < 1 && !(flucht && k.alpha <= 0.01);
            },
            zeichne(ctx) {
                const x = (richtung > 0 ? -0.05 + k.p * 0.6 : 0.55 - k.p * 0.6) * b;
                const y = hy + (h - hy) * k.lage;
                const g = 44 * S;
                const wiegen = Math.sin(echtzeit * 1.3) * 0.04;
                ctx.globalAlpha = k.alpha;
                // Lichtspur der Laterne im Wasser
                const spur = ctx.createLinearGradient(0, y, 0, y + g * 1.6);
                spur.addColorStop(0, 'rgba(255,190,100,0.4)');
                spur.addColorStop(1, 'rgba(255,190,100,0)');
                ctx.fillStyle = spur;
                ctx.fillRect(x + 0.35 * g * richtung - 2 * S, y, 4 * S, g * 1.6);
                ctx.translate(x, y);
                ctx.rotate(wiegen);
                ctx.scale(richtung, 1);
                ctx.fillStyle = '#05090f';
                ctx.beginPath();
                ctx.moveTo(-0.7 * g, -0.12 * g);
                ctx.quadraticCurveTo(0, 0.14 * g, 0.7 * g, -0.12 * g);
                ctx.lineTo(0.6 * g, 0);
                ctx.quadraticCurveTo(0, 0.08 * g, -0.6 * g, 0);
                ctx.closePath();
                ctx.fill();
                // Person mit Paddel
                ctx.beginPath();
                ctx.ellipse(-0.1 * g, -0.3 * g, 0.09 * g, 0.18 * g, 0, 0, TAU);
                ctx.arc(-0.1 * g, -0.55 * g, 0.08 * g, 0, TAU);
                ctx.fill();
                ctx.strokeStyle = '#05090f';
                ctx.lineWidth = 2 * S;
                ctx.beginPath();
                ctx.moveTo(-0.35 * g, -0.6 * g);
                ctx.lineTo(0.15 * g, 0.05 * g);
                ctx.stroke();
                // Laterne am Bug
                const schein = ctx.createRadialGradient(0.35 * g, -0.3 * g, 0, 0.35 * g, -0.3 * g, 0.5 * g);
                schein.addColorStop(0, 'rgba(255,200,110,0.8)');
                schein.addColorStop(1, 'rgba(255,170,80,0)');
                ctx.fillStyle = schein;
                ctx.fillRect(-0.15 * g, -0.8 * g, g, g);
            },
        };
    }

    const besuche = new Besuchsplaner({elch: neuerElch, kanu: neuesKanu});

    // ── Klassenmaus: Hüttentür, hinter dem Ufer, aus dem Schilf (mit Schlafmütze) ──
    const mausGroesse = () => Math.min(b, h) * 0.06;
    const maus = new Klassenmaus([
        {name: 'tuer', ebene: 'tuer', ort: () => {
            const {bw, hh} = huette();
            const breite = bw * 0.22;
            return {
                x: hx + bw * 0.13, y: by - 2 * S, s: Math.min(breite / 1.45, (hh - 18 * S) / 2.05), von: 'seite', richtung: -1, muetze: true,
                clip: (ctx) => tuerPfad(ctx),
            };
        }},
        {name: 'ufer', ebene: 'hinter-ufer', ort: () => {
            const x = Math.min(lx + 55 * S, b - 70 * S);
            return {x, y: by - 22 * S, s: mausGroesse() * 0.8, von: 'unten', muetze: true, dunkel: true};
        }},
        {name: 'schilf', ebene: 'schilf', ort: () => ({x: b * 0.3, y: h + mausGroesse() * 0.45, s: mausGroesse() * 1.15, von: 'unten', muetze: true, dunkel: true})},
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

    function zeichne(ctx, breite, hoehe) {
        if (farbIndex < 0) aktualisiereFarben(0);
        zeichneAurora();

        // Himmel
        const himmel = ctx.createLinearGradient(0, 0, 0, hy);
        himmel.addColorStop(0, '#040818');
        himmel.addColorStop(0.55, '#0a1a3c');
        himmel.addColorStop(1, `rgb(${Math.round(14 + ruhe * 6)},${Math.round(44 + ruhe * 50)},${Math.round(70 + ruhe * 22)})`);
        ctx.fillStyle = himmel;
        ctx.fillRect(0, 0, breite, hy + 1);
        ctx.globalAlpha = 0.9 - ruhe * 0.35;
        ctx.drawImage(sterneEbene, 0, 0, breite, hy);
        ctx.globalAlpha = 1;

        // Polarlicht mit weichem Schein
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.globalCompositeOperation = 'lighter';
        ctx.drawImage(aurora, 0, 0, breite, hy);
        ctx.globalAlpha = 0.4;
        ctx.drawImage(aurora, -breite * 0.05, -hy * 0.08, breite * 1.1, hy * 1.14);
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
            ctx.lineWidth = 1.6 * S;
            ctx.beginPath();
            ctx.moveTo(sx - 70 * S, sy - 26 * S);
            ctx.lineTo(sx, sy);
            ctx.stroke();
        });

        // Wasser mit Spiegelung der Bänder
        const wh = hoehe - hy;
        const wasser = ctx.createLinearGradient(0, hy, 0, hoehe);
        wasser.addColorStop(0, '#0b2a3c');
        wasser.addColorStop(1, '#030912');
        ctx.fillStyle = wasser;
        ctx.fillRect(0, hy, breite, wh);
        const scheiben = 34;
        const sh = wh / scheiben;
        const ash = (AH * 0.9) / scheiben;
        ctx.globalCompositeOperation = 'lighter';
        ctx.globalAlpha = 0.6;
        for (let j = 0; j < scheiben; j++) {
            const versatz = Math.sin(j * 0.8 + t * 1.5) * (1.5 + j * 0.45) * S;
            ctx.drawImage(aurora, 0, AH - (j + 1) * ash, AW, ash, versatz - 8, hy + j * sh, breite + 16, sh + 1);
        }
        ctx.globalAlpha = 1;
        ctx.globalCompositeOperation = 'source-over';
        const tiefe = ctx.createLinearGradient(0, hy, 0, hoehe);
        tiefe.addColorStop(0, 'rgba(3,9,18,0)');
        tiefe.addColorStop(1, 'rgba(3,9,18,0.72)');
        ctx.fillStyle = tiefe;
        ctx.fillRect(0, hy, breite, wh);

        // Fernes Ufer mit Lichtern
        ctx.fillStyle = '#03080d';
        ctx.beginPath();
        ctx.moveTo(0, hy + 3);
        for (let x = 0; x <= breite + 6; x += 6) ctx.lineTo(x, fernesUfer(x));
        ctx.lineTo(breite, hy + 3);
        ctx.closePath();
        ctx.fill();
        ferneLichter.forEach((fl) => {
            ctx.fillStyle = 'rgba(255,214,150,0.9)';
            ctx.fillRect(fl.x, hy - 2 * S, 2 * S, 2 * S);
            const laenge = (20 + fl.w * 40) * S;
            const spiegel = ctx.createLinearGradient(0, hy, 0, hy + laenge);
            spiegel.addColorStop(0, 'rgba(255,214,150,0.45)');
            spiegel.addColorStop(1, 'rgba(255,214,150,0)');
            ctx.fillStyle = spiegel;
            ctx.fillRect(fl.x, hy + 2, 2 * S, laenge);
        });
        besuche.zeichne(ctx, 'ufer');
        besuche.zeichne(ctx, 'see');

        // Spiegelbild der Hütte, Laternenschein auf dem Wasser
        ctx.fillStyle = 'rgba(2,6,10,0.55)';
        ctx.beginPath();
        ctx.moveTo(hx - 85 * S, by + 11 * S);
        ctx.lineTo(hx + 85 * S, by + 11 * S);
        ctx.lineTo(hx + 58 * S, by + 105 * S);
        ctx.lineTo(hx - 58 * S, by + 105 * S);
        ctx.closePath();
        ctx.fill();
        const lf = 0.88 + 0.12 * Math.sin(echtzeit * 11) * Math.sin(echtzeit * 3.7);
        const laternenSpur = ctx.createLinearGradient(0, by + 6 * S, 0, by + 120 * S);
        laternenSpur.addColorStop(0, `rgba(255,170,80,${0.5 * lf})`);
        laternenSpur.addColorStop(1, 'rgba(255,170,80,0)');
        ctx.fillStyle = laternenSpur;
        ctx.fillRect(lx - 5 * S + Math.sin(t * 2) * 2 * S, by + 8 * S, 10 * S, 112 * S);

        maus.zeichne(ctx, 'hinter-ufer');
        ctx.drawImage(vordergrund, 0, 0, breite, hoehe);
        maus.zeichne(ctx, 'tuer');

        // Laterne
        ctx.globalCompositeOperation = 'lighter';
        const schein = ctx.createRadialGradient(lx, ly, 0, lx, ly, 150 * S);
        schein.addColorStop(0, `rgba(255,190,100,${0.7 * lf})`);
        schein.addColorStop(0.25, `rgba(255,150,60,${0.22 * lf})`);
        schein.addColorStop(1, 'rgba(255,140,50,0)');
        ctx.fillStyle = schein;
        ctx.fillRect(lx - 150 * S, ly - 150 * S, 300 * S, 300 * S);
        ctx.globalCompositeOperation = 'source-over';
        ctx.fillStyle = '#20160c';
        ctx.fillRect(lx - 8 * S, ly - 14 * S, 16 * S, 3 * S);
        ctx.fillRect(lx - 8 * S, ly + 12 * S, 16 * S, 4 * S);
        ctx.fillStyle = `rgba(255,236,190,${lf})`;
        ctx.fillRect(lx - 6 * S, ly - 11 * S, 12 * S, 23 * S);

        // Schilf im Vordergrund (vor der Maus)
        maus.zeichne(ctx, 'schilf');
        ctx.lineCap = 'round';
        ctx.strokeStyle = `rgba(${Math.round(4 + ruhe * 14)},${Math.round(12 + ruhe * 46)},${Math.round(14 + ruhe * 34)},0.95)`;
        schilf.forEach((rd) => {
            const wiegen = Math.sin(t * 0.9 + rd.phase) * 7 * S;
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
