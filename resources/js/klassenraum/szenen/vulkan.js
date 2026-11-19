/*
 * Szene „Vulkan“: Ein uralter Vulkan schlummert inmitten von Dinosauriern und Urwaldpflanzen.
 *
 * Ist es leise, grasen die Dinos entspannt, schlendern umher und aus dem Krater steigt nur ein
 * dünnes Rauchfähnchen. Wird es laut, beginnt der Vulkan zu grollen: Der Boden bebt, dunkler
 * Rauch quillt hervor, der Krater glüht, Lava läuft die Flanken hinab und glühende Brocken
 * fliegen heraus – er droht auszubrechen (bricht aber nie richtig aus). Die Dinos schauen erst
 * erschrocken hoch und flüchten bei anhaltendem Lärm in den Urwald. Der „Druck“ im Vulkan baut
 * sich bei Lärm auf und nur langsam wieder ab – Ruhe zahlt sich also aus.
 *
 * Seltene Besuche (nur bei Ruhe): Flugsaurier, Dino-Nest mit schlüpfendem Baby, Riesenlibelle.
 * Die Klassenmaus (mit Forscherhut) guckt hinter einem Felsen oder aus dem Farn.
 * Dinos in szenen/vulkan-dinos.js; Unbewegtes wird je Größenänderung in Ebenen vorgezeichnet.
 *
 * Vorschau: ?besuch=flugsaurier|nest|libelle|maus, ?besuch=grollen (Vulkan sofort unter Druck)
 */

import {ellipse, glatt, klemme, mische, mischeFarbe, zufall, zufallMitSaat} from '../shared/hilfen';
import {Besuchsplaner, blende} from '../shared/besuche';
import {Klassenmaus, mausBesuch} from '../shared/maus';
import {zeichneDreihorn, zeichneFlitzer, zeichneLanghals, zeichneStego} from './vulkan-dinos';

const ARTEN = {
    langhals: {zeichner: zeichneLanghals, groesse: 0.23, tempo: 0.18, mut: [0.5, 0.62]},
    dreihorn: {zeichner: zeichneDreihorn, groesse: 0.17, tempo: 0.22, mut: [0.45, 0.58]},
    stego: {zeichner: zeichneStego, groesse: 0.16, tempo: 0.2, mut: [0.45, 0.58]},
    flitzer: {zeichner: zeichneFlitzer, groesse: 0.09, tempo: 0.5, mut: [0.3, 0.42]},
};
const KONTUR = 'rgba(40,30,22,.55)';

function verlauf(c, y0, y1, oben, unten) {
    const v = c.createLinearGradient(0, y0, 0, y1);
    v.addColorStop(0, oben);
    v.addColorStop(1, unten);
    return v;
}

/** Palmwedel als gefiederter Bogen. */
function wedel(c, x, y, laenge, winkel, farbe, zz) {
    const ex = x + Math.cos(winkel) * laenge;
    const ey = y + Math.sin(winkel) * laenge;
    const kx = x + Math.cos(winkel) * laenge * 0.5;
    const ky = y + Math.sin(winkel) * laenge * 0.5 - laenge * 0.28;
    c.strokeStyle = farbe;
    c.lineCap = 'round';
    for (let i = 1; i <= 14; i++) {
        const t = i / 15;
        const px = (1 - t) ** 2 * x + 2 * (1 - t) * t * kx + t * t * ex;
        const py = (1 - t) ** 2 * y + 2 * (1 - t) * t * ky + t * t * ey;
        const blatt = laenge * 0.32 * Math.sin(t * Math.PI) + laenge * 0.04;
        c.lineWidth = Math.max(1.5, laenge * 0.045);
        [-1, 1].forEach((seite) => {
            c.beginPath();
            c.moveTo(px, py);
            c.quadraticCurveTo(px + Math.cos(winkel + seite * 1.2) * blatt * 0.5, py + Math.sin(winkel + seite * 1.2) * blatt * 0.5 - blatt * 0.1,
                px + Math.cos(winkel + seite * 1.9) * blatt + zz.zwischen(-2, 2), py + Math.sin(winkel + seite * 1.9) * blatt + blatt * 0.35);
            c.stroke();
        });
    }
    c.lineWidth = Math.max(1.5, laenge * 0.035);
    c.beginPath();
    c.moveTo(x, y);
    c.quadraticCurveTo(kx, ky, ex, ey);
    c.stroke();
}

function palme(c, x, fuss, hoehe, zz, farben = ['#3f8f3a', '#5daa45', '#7cc455'], neigung = 0) {
    const kx = x + hoehe * neigung;
    const ky = fuss - hoehe;
    // Stamm aus Ringen
    const ringe = 12;
    for (let i = 0; i < ringe; i++) {
        const t = i / ringe;
        const px = x + (kx - x) * t * t;
        const py = fuss - hoehe * t;
        const r = hoehe * (0.045 - t * 0.015);
        c.fillStyle = i % 2 ? '#a07848' : '#8a6338';
        ellipse(c, px, py, r, hoehe * 0.05);
        c.fill();
        c.strokeStyle = 'rgba(60,40,20,.5)';
        c.lineWidth = 1;
        c.stroke();
    }
    const n = 8;
    for (let i = 0; i < n; i++) {
        const winkel = -Math.PI / 2 + (i / (n - 1) - 0.5) * Math.PI * 1.5 + zz.zwischen(-0.15, 0.15);
        wedel(c, kx, ky, hoehe * zz.zwischen(0.4, 0.52), winkel + (Math.abs(winkel + Math.PI / 2) > 0.6 ? 0.35 * Math.sign(winkel + Math.PI / 2) : 0), farben[i % farben.length], zz);
    }
    c.fillStyle = '#6b4a2b';
    ellipse(c, kx, ky, hoehe * 0.04, hoehe * 0.035);
    c.fill();
}

/** Farnbusch: Wedel fächerförmig aus einem Punkt. */
function farn(c, x, y, groesse, zz, farben = ['#2f7a35', '#46963f', '#62b04a']) {
    const n = 9;
    for (let i = 0; i < n; i++) {
        const winkel = -Math.PI / 2 + (i / (n - 1) - 0.5) * Math.PI * 1.25 + zz.zwischen(-0.1, 0.1);
        wedel(c, x, y, groesse * zz.zwischen(0.75, 1), winkel, farben[i % farben.length], zz);
    }
}

function stein(c, x, y, rx, ry, hell, dunkel) {
    c.fillStyle = verlauf(c, y - ry, y + ry, hell, dunkel);
    c.beginPath();
    c.moveTo(x - rx, y + ry * 0.6);
    c.bezierCurveTo(x - rx * 1.05, y - ry * 0.4, x - rx * 0.5, y - ry * 1.1, x + rx * 0.05, y - ry);
    c.bezierCurveTo(x + rx * 0.7, y - ry * 0.95, x + rx * 1.05, y - ry * 0.2, x + rx, y + ry * 0.6);
    c.closePath();
    c.fill();
    c.strokeStyle = KONTUR;
    c.lineWidth = 1.5;
    c.stroke();
    c.fillStyle = 'rgba(255,255,255,.18)';
    ellipse(c, x - rx * 0.3, y - ry * 0.55, rx * 0.35, ry * 0.18, -0.2);
    c.fill();
}

function busch(c, x, y, r, farben, zz) {
    for (let i = 0; i < 7; i++) {
        c.fillStyle = farben[i % farben.length];
        ellipse(c, x + zz.zwischen(-1, 1) * r * 0.8, y - zz.zwischen(0, 0.6) * r, r * zz.zwischen(0.45, 0.7), r * zz.zwischen(0.4, 0.6));
        c.fill();
    }
}

export function erzeugeVulkan() {
    let b = 0;
    let h = 0;
    let t = 0;
    let mengenFaktor = 1;
    let ebeneLandschaft = null;
    let ebeneVorne = null;
    let vulkan = null;
    let wolken = [];
    let dinos = [];
    const rauch = [];
    const funken = [];
    let rauchRest = 0;
    let funkenRest = 0;
    let druck = 0;       // baut sich bei Lärm auf, sinkt bei Ruhe langsam
    let aktivitaet = 0;  // sichtbare Unruhe des Vulkans (0–1)
    let grollen = 0;     // Hüllkurve eines Bebens
    let naechstesGrollen = 2;
    let wackeln = [0, 0];

    const weideOben = () => h * 0.7;
    const weideUnten = () => h * 0.9;
    const tiefe = (y) => mische(0.72, 1.05, klemme((y - weideOben()) / (weideUnten() - weideOben()), 0, 1));

    function neueEbene(zeichner) {
        const dpr = Math.min(2, window.devicePixelRatio || 1);
        const leinwand = document.createElement('canvas');
        leinwand.width = Math.max(1, Math.round(b * dpr));
        leinwand.height = Math.max(1, Math.round(h * dpr));
        const c = leinwand.getContext('2d');
        c.setTransform(dpr, 0, 0, dpr, 0, 0);
        zeichner(c);
        return leinwand;
    }

    function baueVulkan() {
        const cx = b * 0.62;
        const basis = h * 0.64;
        const kraterY = h * 0.2;
        const kraterB = Math.min(b * 0.05, h * 0.09);
        const links = cx - Math.min(b * 0.3, h * 0.62);
        const rechts = cx + Math.min(b * 0.3, h * 0.62);
        const spur = (x0, x1, y1, saat) => {
            const zz = zufallMitSaat(saat);
            return Array.from({length: 16}, (_, i) => {
                const p = i / 15;
                return [mische(x0, x1, p * p) + zz.zwischen(-1, 1) * kraterB * 0.12, mische(kraterY + 2, y1, p)];
            });
        };
        vulkan = {
            cx, basis, kraterY, kraterB, links, rechts,
            spuren: [
                spur(cx - kraterB * 0.5, cx - (rechts - links) * 0.17, kraterY + (basis - kraterY) * 0.62, 5),
                spur(cx + kraterB * 0.1, cx + (rechts - links) * 0.04, kraterY + (basis - kraterY) * 0.45, 6),
                spur(cx + kraterB * 0.6, cx + (rechts - links) * 0.2, kraterY + (basis - kraterY) * 0.7, 7),
            ],
        };
    }

    function vulkanPfad(c) {
        const {cx, basis, kraterY, kraterB, links, rechts} = vulkan;
        c.beginPath();
        c.moveTo(links, basis);
        c.bezierCurveTo(links + (cx - links) * 0.45, basis - h * 0.05, cx - kraterB - (cx - links) * 0.18, kraterY + h * 0.14, cx - kraterB, kraterY);
        c.quadraticCurveTo(cx, kraterY - h * 0.012, cx + kraterB, kraterY);
        c.bezierCurveTo(cx + kraterB + (rechts - cx) * 0.18, kraterY + h * 0.14, rechts - (rechts - cx) * 0.45, basis - h * 0.05, rechts, basis);
        c.closePath();
    }

    /** Halbe Breite des Kegels in Höhe y (konkave Flanken). */
    function halbbreite(y) {
        const {kraterY, basis, kraterB, links, rechts} = vulkan;
        const p = klemme((y - kraterY) / (basis - kraterY), 0, 1);
        return mische(kraterB, (rechts - links) / 2, p ** 1.6);
    }

    /** Punkt auf einer Flanke: u = −1 … 1 (links … rechts), p = 0 (Krater) … 1 (Fuß). */
    function flanke(u, p) {
        const {cx, kraterY, basis} = vulkan;
        const y = mische(kraterY, basis, p);
        return [cx + u * halbbreite(y), y];
    }

    function zeichneVulkanKoerper(c, zz) {
        const {cx, basis, kraterY, kraterB, links, rechts, spuren} = vulkan;
        const hoehe = basis - kraterY;
        // Grundton: Licht von links oben, Schattenseite rechts
        vulkanPfad(c);
        const licht = c.createLinearGradient(links, 0, rechts, 0);
        licht.addColorStop(0, '#c4926a');
        licht.addColorStop(0.42, '#a8754f');
        licht.addColorStop(0.56, '#83573d');
        licht.addColorStop(1, '#5b3c2c');
        c.fillStyle = licht;
        c.fill();
        c.save();
        vulkanPfad(c);
        c.clip();

        // oben verrußt, unten wärmer
        const russ = c.createLinearGradient(0, kraterY, 0, basis);
        russ.addColorStop(0, 'rgba(45,28,22,.6)');
        russ.addColorStop(0.3, 'rgba(45,28,22,.1)');
        russ.addColorStop(1, 'rgba(160,110,60,.15)');
        c.fillStyle = russ;
        c.fillRect(links, kraterY - 10, rechts - links, hoehe + 10);

        // Gesteinsschichten quer über den Kegel
        for (let k = 1; k < 14; k++) {
            const p = (k / 14) ** 1.15;
            const y = mische(kraterY, basis, p);
            const hb = halbbreite(y);
            c.strokeStyle = k % 2 ? 'rgba(255,225,185,.13)' : 'rgba(55,32,22,.18)';
            c.lineWidth = Math.max(2, hoehe * zz.zwischen(0.008, 0.018));
            c.beginPath();
            for (let i = 0; i <= 30; i++) {
                const u = -1 + i / 15;
                const x = cx + u * hb * 1.05;
                const yy = y + (1 - u * u) * hoehe * 0.025 * p + Math.sin(i * 1.7 + k) * hoehe * 0.004;
                i ? c.lineTo(x, yy) : c.moveTo(x, yy);
            }
            c.stroke();
        }

        // Erosionsrinnen: Schattenkeil rechts, Lichtkante links
        const rinnen = 16;
        for (let i = 0; i < rinnen; i++) {
            const u = -0.95 + (i / (rinnen - 1)) * 1.9 + zz.zwischen(-0.04, 0.04);
            const laenge = zz.zwischen(0.55, 0.95);
            const punkte = [];
            for (let s = 0; s <= 12; s++) {
                const p = 0.03 + (s / 12) * laenge;
                const [x, y] = flanke(u * mische(0.85, 1, p), p);
                punkte.push([x + Math.sin(s * 1.3 + i) * hoehe * 0.006, y]);
            }
            const breite = (s) => hoehe * 0.022 * Math.sin((s / 12) * Math.PI * 0.9 + 0.1) * (u > 0 ? 1.25 : 0.9);
            c.fillStyle = u > 0.1 ? 'rgba(40,24,18,.32)' : 'rgba(60,36,24,.26)';
            c.beginPath();
            punkte.forEach(([x, y], s) => (s ? c.lineTo(x, y) : c.moveTo(x, y)));
            for (let s = punkte.length - 1; s >= 0; s--) c.lineTo(punkte[s][0] + breite(s), punkte[s][1] + breite(s) * 0.2);
            c.closePath();
            c.fill();
            c.strokeStyle = u < 0.1 ? 'rgba(255,228,190,.35)' : 'rgba(255,220,180,.14)';
            c.lineWidth = Math.max(1, hoehe * 0.004);
            c.beginPath();
            punkte.forEach(([x, y], s) => (s ? c.lineTo(x - 1.5, y) : c.moveTo(x - 1.5, y)));
            c.stroke();
        }

        // erkaltete Lavaströme (dort fließt bei Lärm die neue Lava)
        spuren.forEach((spur) => {
            const n = spur.length;
            const breite = (s) => kraterB * mische(0.22, 0.5, s / (n - 1));
            const rand = [];
            const gegen = [];
            spur.forEach(([x, y], s) => {
                const w = breite(s) * (1 + Math.sin(s * 2.1) * 0.15);
                rand.push([x - w, y]);
                gegen.push([x + w, y]);
            });
            c.fillStyle = 'rgba(98,66,50,.85)';
            c.beginPath();
            rand.forEach(([x, y], s) => (s ? c.lineTo(x, y) : c.moveTo(x, y)));
            const [ex, ey] = spur[n - 1];
            c.quadraticCurveTo(ex, ey + kraterB * 0.5, gegen[n - 1][0], gegen[n - 1][1]);
            for (let s = n - 1; s >= 0; s--) c.lineTo(gegen[s][0], gegen[s][1]);
            c.closePath();
            c.fill();
            c.strokeStyle = 'rgba(30,18,14,.4)';
            c.lineWidth = 1.5;
            c.stroke();
            // Wülste und Lichtkanten im Strom
            spur.forEach(([x, y], s) => {
                if (s % 2) return;
                const w = breite(s);
                c.fillStyle = 'rgba(170,125,95,.35)';
                ellipse(c, x - w * 0.3, y, w * 0.5, w * 0.22, 0.2);
                c.fill();
            });
        });

        // Felsbrocken mit Licht und Schatten
        for (let i = 0; i < 160; i++) {
            const p = 0.04 + zz() ** 1.25 * 0.92;
            const u = zz.zwischen(-0.92, 0.92);
            const [x, y] = flanke(u, p);
            const r = hoehe * zz.zwischen(0.006, 0.016) * (0.6 + p * 0.6);
            const ecken = 6;
            c.beginPath();
            for (let e = 0; e < ecken; e++) {
                const w = (e / ecken) * Math.PI * 2;
                const rr = r * zz.zwischen(0.7, 1.15);
                const px = x + Math.cos(w) * rr;
                const py = y + Math.sin(w) * rr * 0.7;
                e ? c.lineTo(px, py) : c.moveTo(px, py);
            }
            c.closePath();
            c.fillStyle = mischeFarbe('#b88a63', '#5a3d2c', (u + 1) / 2 * 0.8 + zz() * 0.2);
            c.fill();
            c.strokeStyle = 'rgba(40,24,18,.35)';
            c.lineWidth = 1;
            c.stroke();
            c.fillStyle = 'rgba(255,232,200,.3)';
            ellipse(c, x - r * 0.3, y - r * 0.3, r * 0.4, r * 0.2, -0.3);
            c.fill();
        }
        // feine Körnung
        for (let i = 0; i < 900; i++) {
            const [x, y] = flanke(zz.zwischen(-1, 1), zz());
            c.fillStyle = zz() < 0.5 ? 'rgba(255,235,205,.18)' : 'rgba(40,22,16,.2)';
            c.fillRect(x, y, zz.zwischen(1, 2.5), zz.zwischen(1, 2));
        }

        // Grün, das die unteren Hänge hinaufwächst
        for (let zunge = 0; zunge < 7; zunge++) {
            const u = -0.85 + zunge * 0.28 + zz.zwischen(-0.06, 0.06);
            const bis = zz.zwischen(0.7, 0.82);
            for (let p = 1.0; p > bis; p -= 0.045) {
                const anteil = (p - bis) / (1 - bis);
                const [x, y] = flanke(u * mische(0.95, 1, p), p);
                const r = hoehe * (0.014 + 0.03 * anteil);
                busch(c, x + zz.zwischen(-1, 1) * r * 0.6, y + r * 0.3, r, ['#4a933c', '#5ea646', '#78bd52'], zz);
                if (anteil > 0.4) busch(c, x + zz.zwischen(1, 2) * r * (zz() < 0.5 ? -1 : 1), y + r * 0.5, r * 0.8, ['#4a933c', '#5ea646', '#78bd52'], zz);
            }
        }
        for (let i = 0; i < 36; i++) {
            const x = zz.zwischen(links + 10, rechts - 10);
            busch(c, x, basis + zz.zwischen(-0.012, 0.008) * h, h * zz.zwischen(0.025, 0.045), ['#4f9a3e', '#62ad48', '#7cc254'], zz);
        }
        c.restore();
        c.strokeStyle = 'rgba(45,28,20,.6)';
        c.lineWidth = 2;
        vulkanPfad(c);
        c.stroke();

        // Krater: Rand mit Lichtkante, gezackte Öffnung
        c.fillStyle = '#4a2e24';
        ellipse(c, cx, kraterY, kraterB * 1.02, hoehe * 0.032);
        c.fill();
        c.fillStyle = '#26140f';
        ellipse(c, cx, kraterY - hoehe * 0.004, kraterB * 0.82, hoehe * 0.02);
        c.fill();
        c.strokeStyle = 'rgba(255,215,170,.45)';
        c.lineWidth = Math.max(1.5, hoehe * 0.005);
        c.beginPath();
        c.ellipse(cx, kraterY, kraterB * 1.0, hoehe * 0.03, 0, Math.PI * 0.05, Math.PI * 0.95);
        c.stroke();
        for (let i = 0; i < 14; i++) {
            const w = Math.PI + (i / 13) * Math.PI;
            const x = cx + Math.cos(w) * kraterB * 0.95;
            const y = kraterY + Math.sin(w) * hoehe * 0.028;
            c.fillStyle = i % 2 ? '#6b4634' : '#5a3a2c';
            ellipse(c, x, y, hoehe * 0.012, hoehe * 0.008, zz.zwischen(-0.5, 0.5));
            c.fill();
        }
    }

    // ── Unbewegtes ───────────────────────────────────────────────────────
    function zeichneLandschaft(c) {
        const zz = zufallMitSaat(21);
        // ferne Berge
        c.fillStyle = '#9cb9c9';
        c.beginPath();
        c.moveTo(0, h * 0.62);
        for (let x = 0; x <= b + 20; x += 20) c.lineTo(x, h * 0.5 + Math.sin(x * 0.006) * h * 0.04 + Math.sin(x * 0.017 + 1) * h * 0.02);
        c.lineTo(b, h * 0.62);
        c.fill();
        c.fillStyle = '#86a9a0';
        c.beginPath();
        c.moveTo(0, h * 0.64);
        for (let x = 0; x <= b + 20; x += 20) c.lineTo(x, h * 0.56 + Math.sin(x * 0.009 + 2) * h * 0.03);
        c.lineTo(b, h * 0.64);
        c.fill();

        // Vulkan
        zeichneVulkanKoerper(c, zz);
        const {links, rechts} = vulkan;

        // Ebene mit Gras
        c.fillStyle = verlauf(c, h * 0.62, h, '#b4d97a', '#7fb24a');
        c.beginPath();
        c.moveTo(0, h * 0.64);
        for (let x = 0; x <= b + 20; x += 20) c.lineTo(x, h * 0.635 + Math.sin(x * 0.01) * h * 0.006);
        c.lineTo(b, h);
        c.lineTo(0, h);
        c.fill();
        // Teich
        c.fillStyle = '#7ec4d6';
        ellipse(c, b * 0.17, h * 0.69, b * 0.09, h * 0.022);
        c.fill();
        c.fillStyle = 'rgba(255,255,255,.45)';
        ellipse(c, b * 0.15, h * 0.685, b * 0.03, h * 0.004);
        c.fill();
        // Urwaldsaum am Horizont
        for (let i = 0; i < b / 26; i++) {
            const x = zz() * b;
            if (x > links + 30 && x < rechts - 30) continue;
            busch(c, x, h * 0.645, h * zz.zwischen(0.02, 0.035), ['#3f8a3a', '#55a044', '#6cb64c'], zz);
        }
        [[0.05, 0.13], [0.11, 0.1], [0.3, 0.12], [0.9, 0.14], [0.96, 0.11], [0.36, 0.08]].forEach(([anteil, hoehe], i) => {
            palme(c, b * anteil, h * 0.655, h * hoehe, zz, ['#4b9440', '#62ab4a', '#80c25a'], i % 2 ? 0.12 : -0.1);
        });
        // Grasbüschel und Blumen auf der Weide
        for (let i = 0; i < b / 4; i++) {
            const y = zz.zwischen(h * 0.66, h);
            c.strokeStyle = zz.aus(['#6aa33e', '#8cc152', '#5a9236']);
            c.lineWidth = 1.5;
            const x = zz() * b;
            const l = h * 0.012 * (0.6 + (y / h - 0.66) * 2);
            c.beginPath();
            c.moveTo(x, y);
            c.lineTo(x - l * 0.3, y - l);
            c.moveTo(x, y);
            c.lineTo(x + l * 0.35, y - l * 0.9);
            c.stroke();
        }
        for (let i = 0; i < b / 30; i++) {
            c.fillStyle = zz.aus(['#f9d24b', '#ffffff', '#f28c8c', '#c99adb']);
            ellipse(c, zz() * b, zz.zwischen(h * 0.68, h * 0.95), 2.5, 2.5);
            c.fill();
        }
        // kleine Farne und Steine im Mittelgrund
        [[0.4, 0.69], [0.86, 0.7], [0.08, 0.74]].forEach(([fx, fy]) => farn(c, b * fx, h * fy, h * 0.05, zz));
        [[0.47, 0.68, 0.02], [0.79, 0.72, 0.025]].forEach(([sx, sy, sr]) => stein(c, b * sx, h * sy, h * sr * 1.4, h * sr, '#c3b6a3', '#8d8070'));
    }

    function zeichneVorne(c) {
        const zz = zufallMitSaat(33);
        // Gras ganz vorn
        c.fillStyle = verlauf(c, h * 0.93, h, '#7fb24a', '#5f9338');
        c.beginPath();
        c.moveTo(0, h);
        for (let x = 0; x <= b + 20; x += 20) c.lineTo(x, h * 0.955 + Math.sin(x * 0.02) * h * 0.008);
        c.lineTo(b, h);
        c.fill();
        // Felsen (Versteck der Maus) und Farnbusch
        stein(c, b * 0.43, h * 0.965, Math.min(b * 0.075, h * 0.15), h * 0.075, '#c9bca8', '#8a7d6c');
        farn(c, b * 0.72, h * 1.02, h * 0.17, zz, ['#2c7333', '#3f8f3a', '#5aa846']);
        // rahmende Palmen und Farne
        palme(c, b * 0.03, h * 1.02, h * 0.5, zz, ['#2f7a35', '#46963f', '#62b04a'], 0.1);
        palme(c, b * 0.985, h * 1.02, h * 0.46, zz, ['#2f7a35', '#46963f', '#62b04a'], -0.12);
        farn(c, b * 0.06, h * 1.03, h * 0.22, zz);
        farn(c, b * 0.95, h * 1.03, h * 0.24, zz);
        busch(c, b * 0.13, h * 1.0, h * 0.06, ['#2f7a35', '#3f8f3a', '#56a444'], zz);
        busch(c, b * 0.88, h * 1.0, h * 0.06, ['#2f7a35', '#3f8f3a', '#56a444'], zz);
        // Blüten
        for (let i = 0; i < 14; i++) {
            const x = zz() < 0.5 ? zz.zwischen(0, b * 0.18) : zz.zwischen(b * 0.82, b);
            c.fillStyle = zz.aus(['#f472b6', '#fbbf24', '#fb7185']);
            ellipse(c, x, zz.zwischen(h * 0.9, h * 0.99), 4, 4);
            c.fill();
        }
    }

    // ── Dinos ────────────────────────────────────────────────────────────
    function neuerDino(art, baby = false, eltern = null) {
        const a = ARTEN[art];
        const y = zufall(weideOben(), weideUnten());
        return {
            art, baby, eltern,
            x: eltern ? eltern.x + zufall(-0.06, 0.06) * b : zufall(0.15, 0.85) * b,
            y: eltern ? klemme(eltern.y + zufall(-0.03, 0.03) * h, weideOben(), weideUnten()) : y,
            g: h * a.groesse * (baby ? 0.5 : 1),
            richtung: Math.random() < 0.5 ? -1 : 1,
            ziel: null, warte: zufall(0, 4), frisst: false, laeuft: false, schaut: false,
            phase: zufall(0, 6), mut: baby ? 0.3 : zufall(...a.mut), flieht: false,
        };
    }

    function erzeugeDinos() {
        dinos = [];
        const f = mengenFaktor;
        const langhals = neuerDino('langhals');
        dinos.push(langhals);
        if (f >= 0.5) dinos.push(neuerDino('langhals', true, langhals));
        for (let i = 0; i < Math.max(1, Math.round(2 * f)); i++) dinos.push(neuerDino('dreihorn'));
        if (f >= 0.6) dinos.push(neuerDino('dreihorn', true, dinos.find((d) => d.art === 'dreihorn' && !d.baby)));
        dinos.push(neuerDino('stego'));
        for (let i = 0; i < Math.max(1, Math.round(3 * f)); i++) dinos.push(neuerDino('flitzer'));
    }

    function schrittDino(d, dt, z) {
        const a = ARTEN[d.art];
        if (z.unruhe > d.mut || druck > 0.85) d.flieht = true;
        else if (d.flieht && z.unruhe < d.mut - 0.12 && druck < 0.7) {
            d.flieht = false;
            d.ziel = {x: zufall(0.15, 0.85) * b, y: zufall(weideOben(), weideUnten())};
        }
        d.schaut = !d.flieht && aktivitaet > 0.3;
        if (d.flieht) {
            d.ziel = {x: d.x < b / 2 ? -0.1 * b : 1.1 * b, y: d.y};
            d.frisst = false;
        } else if (d.schaut) {
            d.ziel = null;
            d.frisst = false;
            d.laeuft = false;
        } else if (!d.ziel) {
            d.warte -= dt;
            if (d.warte <= 0) {
                if (Math.random() < 0.55 && !d.frisst) {
                    d.frisst = true;
                    d.warte = zufall(4, 9);
                } else {
                    d.frisst = false;
                    const basis = d.eltern && !d.eltern.flieht ? d.eltern : null;
                    d.ziel = basis
                        ? {x: basis.x - basis.richtung * zufall(0.04, 0.08) * b, y: klemme(basis.y + zufall(-0.02, 0.03) * h, weideOben(), weideUnten())}
                        : {x: zufall(0.12, 0.88) * b, y: zufall(weideOben(), weideUnten())};
                }
            }
        }
        d.laeuft = false;
        if (d.ziel) {
            const dx = d.ziel.x - d.x;
            const dy = d.ziel.y - d.y;
            const abstand = Math.hypot(dx, dy);
            const tempo = a.tempo * h * (d.flieht ? 2.6 : 1) * tiefe(d.y) * (d.baby ? 1.2 : 1);
            if (abstand < 3) {
                d.ziel = null;
                d.warte = zufall(1, 4);
            } else {
                const s = Math.min(abstand, tempo * dt);
                d.x += dx / abstand * s;
                d.y += dy / abstand * s;
                if (Math.abs(dx) > 1) d.richtung = Math.sign(dx);
                d.laeuft = true;
            }
        }
        d.phase += dt * (d.laeuft ? (d.flieht ? 9 : 5) : 1.4);
    }

    function zeichneDino(ctx, d) {
        const g = d.g * tiefe(d.y);
        ctx.save();
        ctx.translate(d.x, d.y);
        ctx.fillStyle = 'rgba(40,60,20,.22)';
        ellipse(ctx, 0, 0, g * (d.art === 'flitzer' ? 0.35 : 0.55), g * 0.06);
        ctx.fill();
        // Zittern, wenn der Vulkan grollt
        if (d.schaut && grollen > 0.05) ctx.translate(Math.sin(t * 70 + d.phase) * 1.2, 0);
        ctx.scale(d.richtung, 1);
        ARTEN[d.art].zeichner(ctx, g, {ph: d.phase, laeuft: d.laeuft, frisst: d.frisst, schaut: d.schaut, baby: d.baby});
        ctx.restore();
    }

    // ── Seltene Besuche ──────────────────────────────────────────────────
    function neuerFlugsaurier() {
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const f = {p: 0, y: zufall(0.12, 0.3), weg: 0, dauer: zufall(22, 30)};
        return {
            ebene: 'himmel',
            schritt(dt, z) {
                f.p += dt / f.dauer;
                if (z.unruhe > 0.45) f.weg += dt * 0.25;
                return f.p < 1 && f.y - f.weg > -0.2;
            },
            zeichne(ctx) {
                const x = mische(-0.1, 1.1, richtung > 0 ? f.p : 1 - f.p) * b;
                const y = (f.y - f.weg + Math.sin(f.p * 18) * 0.015) * h;
                const s = Math.min(b, h) * 0.07;
                const schlag = Math.sin(t * 5);
                ctx.translate(x, y);
                ctx.scale(richtung, 1);
                ctx.fillStyle = '#c0744a';
                ctx.strokeStyle = KONTUR;
                ctx.lineWidth = 1.5;
                [-1, 1].forEach((seite) => {
                    ctx.beginPath();
                    ctx.moveTo(-s * 0.15, 0);
                    ctx.quadraticCurveTo(-s * 0.3, -s * 0.5 * schlag * seite - s * 0.1, -s * 0.05, -s * 0.9 * schlag * seite);
                    ctx.quadraticCurveTo(s * 0.2, -s * 0.3 * schlag * seite, s * 0.2, 0);
                    ctx.closePath();
                    ctx.fill();
                    ctx.stroke();
                });
                ctx.fillStyle = '#d98b5f';
                ellipse(ctx, 0, 0, s * 0.35, s * 0.09);
                ctx.fill();
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(s * 0.3, -s * 0.05);
                ctx.lineTo(s * 0.7, s * 0.02);
                ctx.lineTo(s * 0.3, s * 0.06);
                ctx.closePath();
                ctx.fill();
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(s * 0.28, -s * 0.05);
                ctx.lineTo(s * 0.12, -s * 0.24);
                ctx.lineTo(s * 0.22, -s * 0.02);
                ctx.fill();
                ctx.fillStyle = '#1d1712';
                ellipse(ctx, s * 0.38, -s * 0.01, s * 0.025, s * 0.025);
                ctx.fill();
            },
        };
    }

    function neuesNest() {
        const n = {x: zufall(0.55, 0.65) * b, y: h * 0.9, zeit: 0, dauer: 34, raus: 0, alpha: 0};
        return {
            ebene: 'boden',
            schritt(dt, z) {
                n.zeit += dt;
                const ende = n.zeit > n.dauer;
                n.alpha = blende(n.alpha, ende ? 0 : 1, dt, 0.8);
                const ziel = !ende && n.zeit > 7 && z.unruhe < 0.4 ? 1 : 0;
                n.raus = blende(n.raus, ziel, dt, ziel ? 0.5 : 2);
                return !(ende && n.alpha <= 0);
            },
            zeichne(ctx) {
                const s = h * 0.05;
                ctx.globalAlpha = n.alpha;
                ctx.translate(n.x, n.y);
                // Nest
                ctx.fillStyle = '#a77b4a';
                ellipse(ctx, 0, 0, s * 1.3, s * 0.35);
                ctx.fill();
                ctx.strokeStyle = '#7a5530';
                ctx.lineWidth = 2;
                for (let i = 0; i < 9; i++) {
                    ctx.beginPath();
                    ctx.moveTo(-s * 1.2 + i * s * 0.3, -s * 0.15);
                    ctx.lineTo(-s * 1.0 + i * s * 0.28, s * 0.2);
                    ctx.stroke();
                }
                const wackeln = n.zeit < 7 ? Math.sin(n.zeit * 12) * 0.12 * Math.min(1, n.zeit / 2) : 0;
                ctx.save();
                ctx.rotate(wackeln);
                if (n.raus > 0.02) {
                    // Baby schaut aus der Schale
                    ctx.save();
                    ctx.translate(0, -s * 0.5 - n.raus * s * 0.5);
                    zeichneFlitzer(ctx, s * 1.6, {ph: t, baby: true, schaut: n.raus < 0.6});
                    ctx.restore();
                }
                // Eierschale (unten), oben aufgebrochen sobald geschlüpft
                ctx.fillStyle = '#f4ecd6';
                ctx.strokeStyle = KONTUR;
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                if (n.zeit < 7) {
                    ctx.ellipse(0, -s * 0.55, s * 0.5, s * 0.66, 0, 0, Math.PI * 2);
                } else {
                    ctx.moveTo(-s * 0.5, -s * 0.5);
                    for (let i = 0; i <= 6; i++) ctx.lineTo(-s * 0.5 + i * s / 6, -s * 0.5 - (i % 2 ? s * 0.18 : 0));
                    ctx.bezierCurveTo(s * 0.55, -s * 0.1, s * 0.3, s * 0.1, 0, s * 0.1);
                    ctx.bezierCurveTo(-s * 0.3, s * 0.1, -s * 0.55, -s * 0.1, -s * 0.5, -s * 0.5);
                }
                ctx.fill();
                ctx.stroke();
                ctx.fillStyle = 'rgba(120,170,90,.5)';
                [[-0.2, -0.3], [0.15, -0.15], [0.05, -0.75]].forEach(([dx, dy]) => {
                    if (n.zeit >= 7 && dy < -0.5) return;
                    ellipse(ctx, dx * s, dy * s, s * 0.08, s * 0.06);
                    ctx.fill();
                });
                ctx.restore();
            },
        };
    }

    function neueLibelle() {
        const l = {zeit: 0, dauer: 20, weg: 0, phase: zufall(0, 6)};
        return {
            ebene: 'vorne',
            schritt(dt, z) {
                l.zeit += dt;
                if (z.unruhe > 0.45 || l.zeit > l.dauer) l.weg += dt * 0.6;
                return l.weg < 1.2;
            },
            zeichne(ctx) {
                const x = (0.5 + Math.sin(l.zeit * 0.35 + l.phase) * 0.35) * b + l.weg * b;
                const y = (0.55 + Math.sin(l.zeit * 0.9 + l.phase) * 0.12 - l.weg * 0.4) * h;
                const s = Math.min(b, h) * 0.05;
                const richtung = Math.cos(l.zeit * 0.35 + l.phase) >= 0 ? 1 : -1;
                ctx.translate(x, y);
                ctx.scale(richtung, 1);
                ctx.globalAlpha = 0.85;
                ctx.fillStyle = 'rgba(200,235,255,.55)';
                ctx.strokeStyle = 'rgba(80,120,140,.6)';
                ctx.lineWidth = 1;
                const schlag = Math.sin(t * 40) * 0.25;
                [[-0.05, -1, 0.6], [-0.05, 1, 0.55], [-0.25, -1, 0.55], [-0.25, 1, 0.5]].forEach(([dx, seite, l2]) => {
                    ellipse(ctx, dx * s, seite * s * 0.18 * (1 + schlag), s * l2 * 0.55, s * 0.12, seite * 0.25);
                    ctx.fill();
                    ctx.stroke();
                });
                ctx.globalAlpha = 1;
                ctx.fillStyle = '#2a9d8f';
                ellipse(ctx, -s * 0.45, 0, s * 0.55, s * 0.05);
                ctx.fill();
                ctx.fillStyle = '#264653';
                ellipse(ctx, s * 0.1, 0, s * 0.12, s * 0.09);
                ctx.fill();
                ctx.fillStyle = '#e9c46a';
                ellipse(ctx, s * 0.22, 0, s * 0.08, s * 0.08);
                ctx.fill();
            },
        };
    }

    const besuche = new Besuchsplaner({
        flugsaurier: neuerFlugsaurier,
        nest: neuesNest,
        libelle: neueLibelle,
    });

    // ── Klassenmaus (mit Forscherhut): hinter dem Felsen oder im Farn ──
    const mausGroesse = () => Math.min(b, h) * 0.065;
    const maus = new Klassenmaus([
        {name: 'fels', ebene: 'vorne', ort: () => ({x: b * 0.43, y: h * 0.935, s: mausGroesse(), von: 'unten', forscher: true})},
        {name: 'farn', ebene: 'vorne', ort: () => ({x: b * 0.72, y: h * 0.905, s: mausGroesse(), von: 'unten', forscher: true})},
    ]);

    // ── Simulation ───────────────────────────────────────────────────────
    function groesse(breite, hoehe, einstellungen) {
        const neu = breite !== b || hoehe !== h;
        const faktor = einstellungen.menge / 100;
        b = breite;
        h = hoehe;
        if (neu || !vulkan) {
            baueVulkan();
            const zz = zufallMitSaat(4);
            wolken = Array.from({length: 4}, () => ({x: zz(), y: zz.zwischen(0.06, 0.2), groesse: zz.zwischen(0.05, 0.09), tempo: zz.zwischen(0.004, 0.008)}));
            ebeneLandschaft = neueEbene(zeichneLandschaft);
            ebeneVorne = neueEbene(zeichneVorne);
            rauch.length = 0;
            funken.length = 0;
        }
        if (neu || dinos.length === 0 || faktor !== mengenFaktor) {
            mengenFaktor = faktor;
            erzeugeDinos();
        }
    }

    function schritt(dt, z) {
        t += dt;
        besuche.schritt(dt, z);
        maus.schritt(dt, z);

        // Druck im Vulkan: steigt bei Lärm, sinkt bei Ruhe langsam
        if (z.unruhe > 0.55) druck += dt * 0.09 * z.unruhe;
        else if (z.unruhe < 0.2) druck -= dt * 0.05;
        else druck -= dt * 0.01;
        druck = klemme(druck, 0, 1);
        aktivitaet = blende(aktivitaet, Math.max(glatt(z.unruhe) * 0.85, druck), dt, 0.5);

        // Beben
        naechstesGrollen -= dt;
        if (aktivitaet > 0.45 && naechstesGrollen <= 0) {
            grollen = 1;
            naechstesGrollen = zufall(2.5, 5) * (1.3 - aktivitaet);
        }
        grollen = Math.max(0, grollen - dt * 1.2);
        const staerke = grollen * klemme((aktivitaet - 0.45) * 8, 0, 4);
        wackeln = [Math.sin(t * 61) * staerke, Math.sin(t * 47 + 1) * staerke * 0.6];

        // Rauch
        const {cx, kraterY, kraterB} = vulkan;
        rauchRest += dt * (0.8 + aktivitaet * 6);
        while (rauchRest > 1) {
            rauchRest -= 1;
            rauch.push({
                x: cx + zufall(-0.4, 0.4) * kraterB, y: kraterY - 4, r: kraterB * zufall(0.15, 0.25) * (1 + aktivitaet * 2.5),
                vx: zufall(0.004, 0.012) * b, vy: -zufall(0.03, 0.05) * h * (1 + aktivitaet * 1.5),
                alter: 0, dauer: zufall(8, 13), dunkel: aktivitaet,
            });
        }
        for (let i = rauch.length - 1; i >= 0; i--) {
            const r = rauch[i];
            r.alter += dt;
            r.x += r.vx * dt;
            r.y += r.vy * dt;
            r.vy *= 1 - dt * 0.08;
            r.r += dt * kraterB * (0.08 + r.dunkel * 0.2);
            if (r.alter > r.dauer || r.y < -r.r * 2) rauch.splice(i, 1);
        }

        // glühende Brocken
        if (aktivitaet > 0.62) {
            funkenRest += dt * (aktivitaet - 0.62) * 14;
            while (funkenRest > 1) {
                funkenRest -= 1;
                funken.push({x: cx + zufall(-0.5, 0.5) * kraterB, y: kraterY, vx: zufall(-0.12, 0.12) * h, vy: -zufall(0.28, 0.48) * h, alter: 0, r: zufall(2.5, 5)});
            }
        }
        for (let i = funken.length - 1; i >= 0; i--) {
            const f = funken[i];
            f.alter += dt;
            f.vy += h * 0.55 * dt;
            f.x += f.vx * dt;
            f.y += f.vy * dt;
            if (f.alter > 2.6 || f.y > vulkan.basis) funken.splice(i, 1);
        }

        wolken.forEach((w) => {
            w.x += w.tempo * dt;
            if (w.x > 1.15) w.x -= 1.3;
        });
        dinos.forEach((d) => schrittDino(d, dt, z));
    }

    // ── Zeichnen ─────────────────────────────────────────────────────────
    function zeichneHimmel(ctx) {
        const a = aktivitaet;
        const himmel = ctx.createLinearGradient(0, 0, 0, h * 0.65);
        himmel.addColorStop(0, mischeFarbe('#6fc0ee', '#6f5a62', a * 0.85));
        himmel.addColorStop(0.7, mischeFarbe('#bfe6f5', '#c98a6a', a * 0.85));
        himmel.addColorStop(1, mischeFarbe('#f6ecc4', '#e3a36b', a * 0.85));
        ctx.fillStyle = himmel;
        ctx.fillRect(-10, -10, b + 20, h + 20);
        // Sonne
        const sx = b * 0.14;
        const sy = h * 0.15;
        const sr = Math.min(b, h) * 0.06;
        const schein = ctx.createRadialGradient(sx, sy, sr * 0.5, sx, sy, sr * 3.5);
        schein.addColorStop(0, `rgba(255,240,170,${0.5 * (1 - a * 0.7)})`);
        schein.addColorStop(1, 'rgba(255,240,170,0)');
        ctx.fillStyle = schein;
        ctx.fillRect(sx - sr * 4, sy - sr * 4, sr * 8, sr * 8);
        ctx.fillStyle = mischeFarbe('#ffe066', '#e8b27a', a);
        ellipse(ctx, sx, sy, sr, sr);
        ctx.fill();
        // Wolken
        wolken.forEach((w) => {
            const x = w.x * b;
            const y = w.y * h;
            const r = w.groesse * Math.max(b, h * 1.5);
            ctx.fillStyle = mischeFarbe('#ffffff', '#b9a49a', a);
            [[-0.4, 0.06, 0.28], [-0.12, -0.08, 0.36], [0.2, -0.04, 0.32], [0.45, 0.07, 0.24], [0.02, 0.1, 0.34]].forEach(([dx, dy, rr]) => {
                ellipse(ctx, x + dx * r, y + dy * r, rr * r, rr * r * 0.62);
                ctx.fill();
            });
        });
    }

    function zeichneVulkanLeben(ctx) {
        const a = aktivitaet;
        const {cx, kraterY, kraterB, spuren} = vulkan;
        // Glut im Krater
        if (a > 0.05) {
            const glut = ctx.createRadialGradient(cx, kraterY, 0, cx, kraterY, kraterB * 3.2);
            glut.addColorStop(0, `rgba(255,170,60,${0.75 * a})`);
            glut.addColorStop(1, 'rgba(255,120,40,0)');
            ctx.fillStyle = glut;
            ctx.fillRect(cx - kraterB * 3.5, kraterY - kraterB * 3.5, kraterB * 7, kraterB * 7);
            ctx.fillStyle = mischeFarbe('#5a2a18', '#ffb347', a);
            ellipse(ctx, cx, kraterY, kraterB * 0.85, h * 0.009);
            ctx.fill();
        }
        // Lava läuft die Flanken hinab
        const laenge = klemme((a - 0.35) / 0.5, 0, 1);
        if (laenge > 0.01) {
            spuren.forEach((spur, i) => {
                const n = Math.max(2, Math.round(spur.length * klemme(laenge * (1.15 - i * 0.12), 0, 1)));
                const teil = spur.slice(0, n);
                [[Math.max(6, kraterB * 0.3), 'rgba(255,110,40,.35)'], [Math.max(3, kraterB * 0.14), '#ff7b2e'], [Math.max(1.5, kraterB * 0.06), '#ffd166']].forEach(([breite, farbe]) => {
                    ctx.strokeStyle = farbe;
                    ctx.lineWidth = breite;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    ctx.beginPath();
                    teil.forEach(([x, y], k) => (k ? ctx.lineTo(x, y) : ctx.moveTo(x, y)));
                    ctx.stroke();
                });
            });
        }
        // Rauch
        rauch.forEach((r) => {
            const lebens = r.alter / r.dauer;
            const alpha = Math.min(1, r.alter * 1.5) * (1 - lebens) * (0.35 + r.dunkel * 0.35);
            ctx.fillStyle = mischeFarbe('#f4f1ec', '#4f4643', r.dunkel * 0.9, alpha);
            ellipse(ctx, r.x, r.y, r.r, r.r * 0.85);
            ctx.fill();
        });
        // Brocken
        funken.forEach((f) => {
            const schein = ctx.createRadialGradient(f.x, f.y, 0, f.x, f.y, f.r * 3);
            schein.addColorStop(0, 'rgba(255,200,90,.8)');
            schein.addColorStop(1, 'rgba(255,120,40,0)');
            ctx.fillStyle = schein;
            ctx.fillRect(f.x - f.r * 3, f.y - f.r * 3, f.r * 6, f.r * 6);
            ctx.fillStyle = '#ffdd88';
            ellipse(ctx, f.x, f.y, f.r * 0.7, f.r * 0.7);
            ctx.fill();
        });
    }

    function zeichne(ctx, breite, hoehe, z) {
        ctx.save();
        ctx.translate(wackeln[0], wackeln[1]);
        zeichneHimmel(ctx);
        besuche.zeichne(ctx, 'himmel', z);
        ctx.drawImage(ebeneLandschaft, 0, 0, breite, hoehe);
        zeichneVulkanLeben(ctx);
        [...dinos].sort((a, c) => a.y - c.y).forEach((d) => zeichneDino(ctx, d));
        besuche.zeichne(ctx, 'boden', z);
        maus.zeichne(ctx, 'vorne');
        ctx.drawImage(ebeneVorne, 0, 0, breite, hoehe);
        besuche.zeichne(ctx, 'vorne', z);
        if (aktivitaet > 0.01) {
            ctx.fillStyle = `rgba(80,35,25,${0.16 * aktivitaet})`;
            ctx.fillRect(-10, -10, breite + 20, hoehe + 20);
        }
        ctx.restore();
    }

    function info() {
        if (aktivitaet > 0.62) return 'Der Vulkan droht auszubrechen!';
        if (aktivitaet > 0.4) return 'Der Vulkan grollt …';
        const entspannt = dinos.filter((d) => !d.flieht && !d.schaut).length;
        return `Entspannte Dinos: ${entspannt} von ${dinos.length}`;
    }

    function besuch(name) {
        const mausDa = mausBesuch(maus, name);
        if (mausDa !== null) return mausDa;
        if (name === 'grollen') {
            druck = 0.8;
            aktivitaet = 0.8;
            return true;
        }
        return besuche.starte(name);
    }

    return {
        mengeLabel: 'Anzahl der Dinos',
        texte: {
            ruhig: 'Ganz still – die Dinos grasen entspannt.',
            fast: 'Psst – der Vulkan wird unruhig.',
            laut: 'Zu laut – der Vulkan grollt und droht auszubrechen!',
        },
        groesse,
        schritt,
        zeichne,
        info,
        besuch,
    };
}

