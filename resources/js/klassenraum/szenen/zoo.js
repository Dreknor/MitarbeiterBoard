/*
 * Szene „Zoo“: Elefanten, Giraffen mit Zebras (Savanne), Affen und Pinguine in ihren Gehegen.
 *
 * Zu Beginn sind die Gehege leer. Je länger es ruhig ist, desto mehr Tiere kommen nach und nach
 * aus ihren Ställen – abwechselnd in allen Gehegen, zuletzt die Tierbabys. Anhaltender Lärm
 * schickt die zuletzt gekommenen Tiere wieder hinein.
 * Nach längerer Ruhe kommt eine Tierpflegerin oder ein Tierpfleger über den Weg und füttert
 * ein Gehege – die Tiere kommen zum Futter, fressen und freuen sich (Herzen). Die Giraffen
 * fressen aus einem Futterkorb auf Kopfhöhe.
 * Wird es laut, verstecken sich zuerst die Babys, dann die großen Tiere im Stall; die Fütterung
 * wird abgebrochen.
 *
 * Stil: warme Bilderbuch-Illustration – gedämpfte Oliv- und Salbeitöne, goldenes Licht, weiche
 * warmbraune Konturen, große Bäume als Rahmen, Papierkörnung. Tiere und Pfleger:innen in
 * szenen/zoo-tiere.js. Alles Unbewegte (Himmel, Landschaft mit Gehegen und Häusern, Bachlauf,
 * Zaun, Weg, Rahmenbäume, Körnung) wird bei jeder Größenänderung einmal in eigene Ebenen
 * vorgezeichnet und pro Bild nur noch kopiert – so bleibt die Szene trotz vieler Details flüssig.
 *
 * Vorschau: ?besuch=alle bzw. ?besuch=babys (alle Tiere draußen), ?besuch=fuetterung (Fütterung sofort)
 */

import {ellipse, glatt, klemme, mische, mitAlpha, zufall, zufallAus, zufallMitSaat} from '../shared/hilfen';
import {zeichneAffe, zeichneElefant, zeichneGiraffe, zeichnePfleger, zeichnePinguin, zeichneZebra} from './zoo-tiere';

// art = Gestaltung des Geheges, bewohner = Tierart → Anzahl (bei Menge 100 %)
const GEHEGE = [
    {art: 'elefant', name: 'Elefanten', futter: 'heu', bewohner: {elefant: 2}},
    {art: 'giraffe', name: 'Savanne', futter: 'blaetter', bewohner: {giraffe: 2, zebra: 2}},
    {art: 'affe', name: 'Affen', futter: 'bananen', bewohner: {affe: 3}},
    {art: 'pinguin', name: 'Pinguine', futter: 'fische', bewohner: {pinguin: 4}},
];
const GROESSE = {elefant: 0.95, giraffe: 0.62, zebra: 0.78, affe: 0.5, pinguin: 0.5};
const TEMPO = {elefant: 0.22, giraffe: 0.3, zebra: 0.35, affe: 0.8, pinguin: 0.4};
const HOEHE = {elefant: 0.95, giraffe: 1.9, zebra: 0.85, affe: 0.95, pinguin: 0.95};
const KONTUR = 'rgba(62,40,22,.5)';
const FUTTER_FARBE = {heu: '#e0b54a', blaetter: '#65a30d', bananen: '#facc15', fische: '#64748b'};
// Babys (in dieser Reihenfolge) kommen erst, wenn alle großen Tiere draußen sind
const BABYS = ['elefant', 'pinguin', 'giraffe', 'zebra', 'affe', 'pinguin'];
// Sekunden Ruhe bis zum ersten Tier, je weiterem großen Tier und je Baby
const ERSTES_TIER = 2;
const SEKUNDEN_PRO_TIER = 6;
const SEKUNDEN_PRO_BABY = 12;

const ZEICHNER = {elefant: zeichneElefant, giraffe: zeichneGiraffe, zebra: zeichneZebra, affe: zeichneAffe, pinguin: zeichnePinguin};

function linie(ctx, x1, y1, x2, y2) {
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();
}

function verlauf(ctx, y0, y1, oben, unten) {
    const v = ctx.createLinearGradient(0, y0, 0, y1);
    v.addColorStop(0, oben);
    v.addColorStop(1, unten);
    return v;
}

function rundRechteck(ctx, x, y, breite, hoehe, r) {
    const rr = Math.min(r, Math.abs(breite) / 2, Math.abs(hoehe) / 2);
    ctx.beginPath();
    ctx.moveTo(x + rr, y);
    ctx.lineTo(x + breite - rr, y);
    ctx.quadraticCurveTo(x + breite, y, x + breite, y + rr);
    ctx.lineTo(x + breite, y + hoehe - rr);
    ctx.quadraticCurveTo(x + breite, y + hoehe, x + breite - rr, y + hoehe);
    ctx.lineTo(x + rr, y + hoehe);
    ctx.quadraticCurveTo(x, y + hoehe, x, y + hoehe - rr);
    ctx.lineTo(x, y + rr);
    ctx.quadraticCurveTo(x, y, x + rr, y);
}

function herz(ctx, x, y, s) {
    ctx.beginPath();
    ctx.moveTo(x, y + s * 0.3);
    ctx.bezierCurveTo(x - s, y - s * 0.4, x - s * 0.5, y - s, x, y - s * 0.45);
    ctx.bezierCurveTo(x + s * 0.5, y - s, x + s, y - s * 0.4, x, y + s * 0.3);
    ctx.fill();
}

function grasbueschel(c, x, y, hoehe, farben, zz) {
    c.lineCap = 'round';
    for (let i = 0; i < 5; i++) {
        c.strokeStyle = farben[i % farben.length];
        c.lineWidth = Math.max(1, hoehe * 0.12);
        const neigung = (i - 2) * 0.35 + zz.zwischen(-0.15, 0.15);
        c.beginPath();
        c.moveTo(x + (i - 2) * hoehe * 0.08, y);
        c.quadraticCurveTo(x + Math.sin(neigung) * hoehe * 0.4, y - hoehe * 0.6, x + Math.sin(neigung) * hoehe * 0.8, y - hoehe * zz.zwischen(0.8, 1.1));
        c.stroke();
    }
}

function stein(c, x, y, rx, ry, hell, dunkel) {
    const v = c.createRadialGradient(x - rx * 0.35, y - ry * 0.5, 0, x, y, Math.max(rx, ry) * 1.2);
    v.addColorStop(0, hell);
    v.addColorStop(1, dunkel);
    c.fillStyle = v;
    ellipse(c, x, y, rx, ry);
    c.fill();
}

/** Blätterwerk im Bilderbuchstil: dunkle Grundform, darauf viele kleine Blätter, oben heller. */
function blattwerk(c, x, y, rx, ry, [dunkel, mittel, hell], zz, blatt = 4) {
    c.fillStyle = dunkel;
    ellipse(c, x, y, rx, ry);
    c.fill();
    const anzahl = klemme(Math.round((rx * ry) / (blatt * blatt) * 0.9), 10, 700);
    for (let i = 0; i < anzahl; i++) {
        const w = zz() * Math.PI * 2;
        const a = Math.sqrt(zz());
        const px = x + Math.cos(w) * rx * a;
        const py = y + Math.sin(w) * ry * a;
        const licht = (py - (y - ry)) / (2 * ry) + (px - x) / (rx * 6);
        c.fillStyle = licht < 0.38 ? hell : licht < 0.72 ? mittel : dunkel;
        ellipse(c, px, py, blatt * zz.zwischen(0.8, 1.3), blatt * zz.zwischen(0.45, 0.65), zz() * Math.PI);
        c.fill();
    }
}

function baum(c, x, fuss, groesse, farben, zz, stamm = ['#6b4e36', '#4a3424']) {
    const s = groesse;
    const v = c.createLinearGradient(x - s * 0.08, 0, x + s * 0.08, 0);
    v.addColorStop(0, stamm[1]);
    v.addColorStop(0.45, stamm[0]);
    v.addColorStop(1, stamm[1]);
    c.fillStyle = v;
    c.beginPath();
    c.moveTo(x - s * 0.08, fuss);
    c.quadraticCurveTo(x - s * 0.03, fuss - s * 0.5, x - s * 0.04, fuss - s * 0.95);
    c.lineTo(x + s * 0.04, fuss - s * 0.95);
    c.quadraticCurveTo(x + s * 0.03, fuss - s * 0.5, x + s * 0.09, fuss);
    c.fill();
    c.strokeStyle = KONTUR;
    c.lineWidth = 1;
    c.stroke();
    [[-0.32, -1.05, 0.42, 0.32], [0.3, -1.1, 0.4, 0.3], [0, -1.32, 0.46, 0.34]].forEach(([dx, dy, rx, ry]) => {
        blattwerk(c, x + dx * s, fuss + dy * s, rx * s, ry * s, farben, zz, Math.max(2, s * 0.05));
    });
}

function menschlein(c, x, y, groesse, kleidung, zz) {
    const s = groesse;
    c.fillStyle = '#4a3a2c';
    c.fillRect(x - s * 0.08, y - s * 0.42, s * 0.06, s * 0.42);
    c.fillRect(x + s * 0.02, y - s * 0.42, s * 0.06, s * 0.42);
    c.fillStyle = kleidung;
    rundRechteck(c, x - s * 0.13, y - s * 0.82, s * 0.26, s * 0.44, s * 0.08);
    c.fill();
    c.fillStyle = zz.aus(['#f1c9a0', '#c68a5b', '#8d5a3b', '#e0ac7e']);
    ellipse(c, x, y - s * 0.92, s * 0.1, s * 0.11);
    c.fill();
    c.fillStyle = zz.aus(['#4a2e1a', '#2d1b10', '#b8862f']);
    ellipse(c, x, y - s * 0.98, s * 0.1, s * 0.06);
    c.fill();
}

export function erzeugeZoo() {
    let b = 0;
    let h = 0;
    let W = 0;
    let g0 = 0;
    let gehege = [];
    let tiere = [];
    let wolken = [];
    let t = 0;
    let ruheZeit = 0;
    let maxSchwelle = 0;
    let fuetterung = null;
    let fuetterWarten = zufall(30, 45);
    let fuetterNaechstes = Math.floor(Math.random() * GEHEGE.length);
    let mengenFaktor = 1;
    let ebeneHimmel = null;
    let ebeneLandschaft = null;
    let ebeneVorne = null;
    let ebeneKoernung = null;
    const herzen = [];

    const tiefe = (y) => mische(0.72, 1, klemme((y - h * 0.62) / (h * 0.2), 0, 1));
    const bereich = (ge) => ({xMin: ge.x0 + W * 0.1, xMax: ge.x1 - W * 0.1, yMin: h * 0.665, yMax: h * 0.78});
    const imWasser = (ge, x, y) => ge.wasser
        && ((x - ge.wasser.x) / ge.wasser.rx) ** 2 + ((y - ge.wasser.y) / ge.wasser.ry) ** 2 < 1;

    // ── Aufbau ───────────────────────────────────────────────────────────
    function baueGehege() {
        const hausBreite = Math.min(g0 * 1.4, W * 0.38);
        gehege = GEHEGE.map((vorlage, i) => {
            const x0 = i * W;
            const ge = {...vorlage, i, x0, x1: x0 + W, mitte: x0 + W / 2, futterListe: [], wasser: null, plaetze: []};
            if (ge.art === 'elefant') {
                ge.wasser = {x: x0 + W * 0.3, y: h * 0.7, rx: W * 0.17, ry: h * 0.03};
                ge.haus = {x: x0 + W * 0.74, basis: h * 0.635, breite: hausBreite, hoehe: hausBreite * 0.7, tuerB: hausBreite * 0.38, tuerH: hausBreite * 0.55};
            } else if (ge.art === 'giraffe') {
                ge.baum = {x: x0 + W * 0.28};
                const korbBoden = h * 0.75;
                ge.korb = {x: x0 + W * 0.52, boden: korbBoden, y: korbBoden - g0 * GROESSE.giraffe * tiefe(korbBoden) * 1.68};
                ge.haus = {x: x0 + W * 0.78, basis: h * 0.635, breite: hausBreite * 0.72, hoehe: hausBreite * 1.2, tuerB: hausBreite * 0.3, tuerH: hausBreite * 0.9};
            } else if (ge.art === 'affe') {
                ge.kletterbaum = {x: x0 + W * 0.52, fuss: h * 0.75, krone: h * 0.43};
                ge.plaetze = [{x: x0 + W * 0.3, y: h * 0.565}, {x: x0 + W * 0.76, y: h * 0.497}];
            } else {
                ge.wasser = {x: x0 + W * 0.4, y: h * 0.725, rx: W * 0.25, ry: h * 0.035};
                ge.hoehle = {x: x0 + W * 0.79, basis: h * 0.64, breite: hausBreite * 0.95, hoehe: hausBreite * 0.6};
            }
            if (ge.haus) ge.tuer = {x: ge.haus.x, y: ge.haus.basis};
            else if (ge.hoehle) ge.tuer = {x: ge.hoehle.x, y: ge.hoehle.basis};
            else ge.tuer = {x: ge.kletterbaum.x, y: ge.kletterbaum.krone};
            return ge;
        });
    }

    function waehleZiel(tier) {
        const ge = tier.ge;
        if (tier.art === 'affe' && Math.random() < 0.45) {
            const platz = zufallAus(ge.plaetze);
            tier.zx = platz.x + zufall(-0.04, 0.04) * W;
            tier.zy = platz.y;
        } else {
            const bb = bereich(ge);
            tier.zx = zufall(bb.xMin, bb.xMax);
            tier.zy = zufall(bb.yMin, bb.yMax);
        }
    }

    function neuesTier(ge, art, baby) {
        const bb = bereich(ge);
        const tier = {
            ge, art, baby, saat: Math.floor(zufall(1, 9999)),
            g: g0 * GROESSE[art] * (baby ? 0.55 : 1),
            x: zufall(bb.xMin, bb.xMax), y: zufall(bb.yMin, bb.yMax), zx: 0, zy: 0,
            richtung: Math.random() < 0.5 ? 1 : -1, dirZiel: 1, phase: zufall(0, 6), warten: zufall(0, 3),
            mut: baby ? zufall(0.12, 0.22) : zufall(0.3, 0.7),
            flieht: false, alpha: 1, laeuft: false, frisst: false, freigeschaltet: !baby, begruesst: false,
        };
        tier.dirZiel = tier.richtung;
        waehleZiel(tier);
        return tier;
    }

    function erzeugeTiere() {
        tiere = [];
        gehege.forEach((ge) => {
            Object.entries(ge.bewohner).forEach(([art, anzahl]) => {
                const n = Math.max(1, Math.round(anzahl * mengenFaktor));
                for (let k = 0; k < n; k++) tiere.push(neuesTier(ge, art, false));
            });
        });
        const babysJeArt = {};
        BABYS.forEach((art, nummer) => {
            const ge = gehege.find((g) => g.bewohner[art]);
            const eltern = tiere.filter((x) => x.ge === ge && x.art === art && !x.baby);
            babysJeArt[art] = (babysJeArt[art] ?? -1) + 1;
            const baby = neuesTier(ge, art, true);
            baby.eltern = eltern[babysJeArt[art] % eltern.length];
            baby.nummer = nummer;
            tiere.push(baby);
        });

        // Reihenfolge des Erscheinens: große Tiere abwechselnd aus allen Gehegen, dann die Babys
        const warteschlangen = gehege.map((ge) => {
            const arten = Object.keys(ge.bewohner);
            const jeArt = arten.map((art) => tiere.filter((x) => x.ge === ge && x.art === art && !x.baby));
            const liste = [];
            for (let i = 0; jeArt.some((l) => l.length > i); i++) jeArt.forEach((l) => l[i] && liste.push(l[i]));
            return liste;
        });
        const reihenfolge = [];
        for (let i = 0; warteschlangen.some((l) => l.length > i); i++) warteschlangen.forEach((l) => l[i] && reihenfolge.push(l[i]));
        reihenfolge.push(...tiere.filter((x) => x.baby).sort((a, c) => a.nummer - c.nummer));
        let schwelle = ERSTES_TIER;
        reihenfolge.forEach((tier) => {
            tier.schwelle = schwelle;
            schwelle += tier.baby ? SEKUNDEN_PRO_BABY : SEKUNDEN_PRO_TIER;
            // alle Tiere warten zu Beginn im Stall
            tier.freigeschaltet = false;
            tier.flieht = true;
            tier.alpha = 0;
            tier.x = tier.ge.tuer.x;
            tier.y = tier.ge.tuer.y;
        });
        maxSchwelle = schwelle;
    }

    // ── Vorgezeichnete Ebenen ────────────────────────────────────────────
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

    // Farben im Bilderbuchstil
    const LAUB_FERN = ['#a3b48a', '#b2c198', '#c3cfa9'];
    const LAUB_MITTE = ['#6f8a4a', '#86a05a', '#a4b86c'];
    const LAUB_NAH = ['#4f6b33', '#68873f', '#8daa52'];
    const LAUB_RAHMEN = ['#3e5528', '#587a34', '#7fa046'];

    function zeichneHimmel(c) {
        const himmel = c.createLinearGradient(0, 0, 0, h * 0.6);
        himmel.addColorStop(0, '#c4dccf');
        himmel.addColorStop(0.55, '#e7ecd5');
        himmel.addColorStop(1, '#f6ebcb');
        c.fillStyle = himmel;
        c.fillRect(0, 0, b, h * 0.62);
        const sx = b * 0.55;
        const sy = h * 0.06;
        const schein = c.createRadialGradient(sx, sy, 0, sx, sy, Math.max(b, h) * 0.55);
        schein.addColorStop(0, 'rgba(255,246,214,.95)');
        schein.addColorStop(0.35, 'rgba(255,240,200,.35)');
        schein.addColorStop(1, 'rgba(255,240,200,0)');
        c.fillStyle = schein;
        c.fillRect(0, 0, b, h * 0.62);
    }

    function zeichneStall(c, haus, zz) {
        const {x, basis, breite, hoehe, tuerB, tuerH} = haus;
        const links = x - breite / 2;
        c.fillStyle = 'rgba(70,50,25,.2)';
        ellipse(c, x + breite * 0.06, basis + 2, breite * 0.65, breite * 0.08);
        c.fill();
        // Steinsockel
        const sockel = hoehe * 0.15;
        c.fillStyle = verlauf(c, basis - sockel, basis, '#c2b8a3', '#988d78');
        c.fillRect(links, basis - sockel, breite, sockel);
        // Bretterwand
        const wand = c.createLinearGradient(links, 0, links + breite, 0);
        wand.addColorStop(0, '#a87a4c');
        wand.addColorStop(0.5, '#c4945f');
        wand.addColorStop(1, '#94683f');
        c.fillStyle = wand;
        c.fillRect(links, basis - hoehe, breite, hoehe - sockel);
        c.strokeStyle = 'rgba(80,50,25,.4)';
        c.lineWidth = 1;
        for (let s = links + breite / 8; s < links + breite; s += breite / 8) linie(c, s, basis - hoehe, s + zz.zwischen(-1, 1), basis - sockel);
        // Fenster mit Laden
        if (breite > hoehe * 0.9) {
            const fx = links + breite * 0.13;
            const fy = basis - hoehe * 0.78;
            const fb = breite * 0.17;
            c.fillStyle = '#5e3d24';
            c.fillRect(fx - 3, fy - 3, fb + 6, fb * 0.8 + 6);
            c.fillStyle = verlauf(c, fy, fy + fb * 0.8, '#f7e2a6', '#d9b56a');
            c.fillRect(fx, fy, fb, fb * 0.8);
            c.strokeStyle = '#5e3d24';
            c.lineWidth = 2;
            linie(c, fx + fb / 2, fy, fx + fb / 2, fy + fb * 0.8);
        }
        // Tür
        c.fillStyle = '#6b4a2c';
        c.beginPath();
        c.moveTo(x - tuerB / 2 - 4, basis);
        c.lineTo(x - tuerB / 2 - 4, basis - tuerH + tuerB / 2);
        c.arc(x, basis - tuerH + tuerB / 2, tuerB / 2 + 4, Math.PI, 0);
        c.lineTo(x + tuerB / 2 + 4, basis);
        c.fill();
        c.fillStyle = verlauf(c, basis - tuerH, basis, '#241810', '#3d2a1b');
        c.beginPath();
        c.moveTo(x - tuerB / 2, basis);
        c.lineTo(x - tuerB / 2, basis - tuerH + tuerB / 2);
        c.arc(x, basis - tuerH + tuerB / 2, tuerB / 2, Math.PI, 0);
        c.lineTo(x + tuerB / 2, basis);
        c.fill();
        c.strokeStyle = KONTUR;
        c.lineWidth = 1.2;
        c.strokeRect(links, basis - hoehe, breite, hoehe);
        // Strohdach
        const dachH = breite * 0.38;
        const dach = new Path2D();
        dach.moveTo(links - breite * 0.14, basis - hoehe + 4);
        dach.quadraticCurveTo(x - breite * 0.25, basis - hoehe - dachH * 0.75, x, basis - hoehe - dachH);
        dach.quadraticCurveTo(x + breite * 0.25, basis - hoehe - dachH * 0.75, links + breite * 1.14, basis - hoehe + 4);
        dach.quadraticCurveTo(x, basis - hoehe - 4, links - breite * 0.14, basis - hoehe + 4);
        c.fillStyle = verlauf(c, basis - hoehe - dachH, basis - hoehe, '#dcbc6e', '#a98840');
        c.fill(dach);
        c.save();
        c.clip(dach);
        c.strokeStyle = 'rgba(120,85,30,.45)';
        c.lineWidth = 1;
        for (let i = 0; i < breite * 0.9; i++) {
            const sx = links - breite * 0.14 + zz() * breite * 1.28;
            const sy = basis - hoehe - zz() * dachH;
            linie(c, sx, sy, sx + (sx - x) * 0.06, sy + zz.zwischen(5, 10));
        }
        c.restore();
        c.strokeStyle = KONTUR;
        c.lineWidth = 1.3;
        c.stroke(dach);
    }

    function zeichneLandschaft(c) {
        const zz = zufallMitSaat(31);
        const blatt = Math.max(2.2, Math.min(b, h) * 0.0045);
        // ferner Wald (dunstig)
        for (let x = -40; x < b + 60; x += zz.zwischen(40, 70)) {
            blattwerk(c, x, h * 0.46 + zz.zwischen(-0.02, 0.02) * h, h * zz.zwischen(0.05, 0.08), h * zz.zwischen(0.06, 0.09), LAUB_FERN, zz, blatt * 0.8);
        }
        c.fillStyle = 'rgba(244,236,208,.35)';
        c.fillRect(0, h * 0.35, b, h * 0.25);
        // Bäume dahinter mit Stämmen
        const reihe = Array.from({length: Math.round(b / 110)}, (_, i) => ({x: (i + zz.zwischen(0.2, 0.8)) * 110, g: h * zz.zwischen(0.12, 0.17)}));
        reihe.forEach((bm) => baum(c, bm.x, h * 0.565, bm.g, LAUB_MITTE, zz));
        // Besucherweg hinten mit kleinen Spaziergängern und Bänken
        c.fillStyle = verlauf(c, h * 0.548, h * 0.572, '#eadcb6', '#d9c59a');
        c.fillRect(0, h * 0.55, b, h * 0.022);
        for (let i = 0; i < 4; i++) {
            const bx = zz.zwischen(0.1, 0.9) * b;
            c.fillStyle = '#7a5434';
            c.fillRect(bx, h * 0.552, h * 0.03, h * 0.004);
            c.fillRect(bx, h * 0.545, h * 0.03, h * 0.003);
        }
        for (let i = 0; i < 7; i++) {
            menschlein(c, zz.zwischen(0.05, 0.95) * b, h * 0.566, h * 0.03, zz.aus(['#c0583e', '#4f7fa8', '#e0b04a', '#7a9a52', '#a8698f']), zz);
        }

        // Gehegeböden in warmen Tönen
        gehege.forEach((ge) => {
            const oben = h * 0.57;
            const unten = h * 0.83;
            const farben = {elefant: ['#dcbd85', '#c49a5c'], giraffe: ['#cdc77c', '#a9a958'], affe: ['#a6c06a', '#829f4c'], pinguin: ['#ddd6c4', '#bfb6a1']}[ge.art];
            c.fillStyle = verlauf(c, oben, unten, farben[0], farben[1]);
            c.fillRect(ge.x0, oben, W + 1, unten - oben);
            c.save();
            c.beginPath();
            c.rect(ge.x0, oben, W, unten - oben);
            c.clip();
            const anzahl = Math.round(W * 0.3);
            if (ge.art === 'elefant') {
                for (let i = 0; i < anzahl; i++) {
                    c.fillStyle = zz() < 0.5 ? 'rgba(150,105,55,.18)' : 'rgba(255,240,205,.28)';
                    ellipse(c, ge.x0 + zz() * W, oben + zz() * (unten - oben), zz.zwischen(4, 16), zz.zwischen(2, 5));
                    c.fill();
                }
                c.strokeStyle = 'rgba(115,80,40,.3)';
                c.lineWidth = 1.4;
                for (let i = 0; i < 7; i++) {
                    const fx = ge.x0 + zz.zwischen(0.15, 0.9) * W;
                    const fy = zz.zwischen(h * 0.7, h * 0.8);
                    ellipse(c, fx, fy, W * 0.018, h * 0.006);
                    c.stroke();
                    ellipse(c, fx + W * 0.05, fy + h * 0.004, W * 0.018, h * 0.006);
                    c.stroke();
                }
            } else if (ge.art === 'pinguin') {
                for (let i = 0; i < anzahl * 0.4; i++) {
                    stein(c, ge.x0 + zz() * W, oben + h * 0.03 + zz() * (unten - oben - h * 0.03), zz.zwischen(4, 12), zz.zwischen(3, 7), '#ece5d4', '#a39a86');
                }
            } else {
                const grasFarben = ge.art === 'giraffe' ? ['#a39f4c', '#c2b867', '#8a8a3e'] : ['#6f9440', '#5a7f34', '#8fb058'];
                for (let i = 0; i < anzahl * 1.7; i++) {
                    const gy = oben + h * 0.02 + zz() * (unten - oben - h * 0.02);
                    grasbueschel(c, ge.x0 + zz() * W, gy, h * mische(0.009, 0.022, (gy - oben) / (unten - oben)), grasFarben, zz);
                }
                if (ge.art === 'affe') {
                    c.fillStyle = 'rgba(115,85,50,.4)';
                    ellipse(c, ge.kletterbaum.x, ge.kletterbaum.fuss, W * 0.12, h * 0.018);
                    c.fill();
                }
                for (let i = 0; i < 10; i++) {
                    c.fillStyle = zz.aus(['#f2d45c', '#f4b6c2', '#fbf6e9']);
                    ellipse(c, ge.x0 + zz() * W, zz.zwischen(h * 0.68, h * 0.82), 2.4, 2.4);
                    c.fill();
                }
            }
            c.restore();
            // Hecke hinten als Gehegegrenze (Pinguine: Felsen)
            for (let x = ge.x0 + 6; x < ge.x1; x += zz.zwischen(18, 30)) {
                if (ge.art === 'pinguin') stein(c, x, h * 0.575, zz.zwischen(12, 22), zz.zwischen(7, 11), '#e3dccb', '#9b917d');
                else blattwerk(c, x, h * 0.575, zz.zwischen(14, 24), zz.zwischen(8, 12), LAUB_NAH, zz, blatt * 0.8);
            }
        });

        // Ausstattung der Gehege
        gehege.forEach((ge) => {
            if (ge.art === 'elefant') {
                zeichneStall(c, ge.haus, zz);
                const hx = ge.x0 + W * 0.5;
                const hy = h * 0.655;
                c.fillStyle = verlauf(c, hy - h * 0.035, hy, '#e6c56c', '#b48a34');
                c.beginPath();
                c.ellipse(hx, hy, W * 0.075, h * 0.035, 0, Math.PI, Math.PI * 2);
                c.fill();
                c.strokeStyle = 'rgba(130,95,25,.6)';
                c.lineWidth = 1;
                for (let i = 0; i < 16; i++) {
                    const ax = hx + zz.zwischen(-0.06, 0.06) * W;
                    linie(c, ax, hy - zz() * h * 0.03, ax + zz.zwischen(-8, 8), hy - zz() * h * 0.03 - 4);
                }
                const lx = ge.x0 + W * 0.14;
                const ly = h * 0.79;
                rundRechteck(c, lx, ly - h * 0.03, W * 0.2, h * 0.03, h * 0.015);
                c.fillStyle = verlauf(c, ly - h * 0.03, ly, '#93704a', '#5e4630');
                c.fill();
                c.strokeStyle = KONTUR;
                c.stroke();
                c.fillStyle = '#d2b07e';
                ellipse(c, lx + W * 0.2, ly - h * 0.015, h * 0.012, h * 0.015);
                c.fill();
            } else if (ge.art === 'giraffe') {
                zeichneStall(c, ge.haus, zz);
                // Akazie
                const tx = ge.baum.x;
                const fuss = h * 0.69;
                const top = h * 0.43;
                c.fillStyle = verlauf(c, top, fuss, '#7a5a3e', '#5b4129');
                c.beginPath();
                c.moveTo(tx - W * 0.022, fuss);
                c.quadraticCurveTo(tx - W * 0.005, (fuss + top) / 2, tx - W * 0.008, top + h * 0.02);
                c.lineTo(tx + W * 0.008, top + h * 0.02);
                c.quadraticCurveTo(tx + W * 0.01, (fuss + top) / 2, tx + W * 0.022, fuss);
                c.fill();
                c.strokeStyle = KONTUR;
                c.lineWidth = 1;
                c.stroke();
                c.strokeStyle = '#6b4f37';
                c.lineCap = 'round';
                c.lineWidth = W * 0.01;
                linie(c, tx, h * 0.5, tx - W * 0.1, top + h * 0.01);
                linie(c, tx, h * 0.48, tx + W * 0.09, top);
                [[-0.08, 0, 0.16, 0.03], [0.07, -0.012, 0.15, 0.028], [-0.005, -0.036, 0.13, 0.024]].forEach(([dx, dy, rx, ry]) => {
                    blattwerk(c, tx + dx * W, top + dy * h, rx * W, ry * h, LAUB_NAH, zz, blatt * 0.9);
                });
                // Futterkorb
                const korb = ge.korb;
                c.fillStyle = verlauf(c, korb.y, korb.boden, '#93704a', '#5e4630');
                c.fillRect(korb.x - W * 0.009, korb.y, W * 0.018, korb.boden - korb.y);
                c.fillStyle = verlauf(c, korb.y - h * 0.022, korb.y + h * 0.012, '#c99a52', '#8a6326');
                c.beginPath();
                c.moveTo(korb.x - W * 0.055, korb.y - h * 0.022);
                c.lineTo(korb.x + W * 0.055, korb.y - h * 0.022);
                c.lineTo(korb.x + W * 0.038, korb.y + h * 0.012);
                c.lineTo(korb.x - W * 0.038, korb.y + h * 0.012);
                c.closePath();
                c.fill();
                c.strokeStyle = KONTUR;
                c.stroke();
            } else if (ge.art === 'affe') {
                const {x: tx, fuss, krone} = ge.kletterbaum;
                [[-0.15, -0.06, 0.13, 0.1], [0.14, -0.08, 0.12, 0.09], [0, -0.12, 0.14, 0.1]].forEach(([dx, dy, rx, ry]) => {
                    blattwerk(c, tx + dx * W, krone + dy * h, rx * W, ry * h, LAUB_NAH, zz, blatt);
                });
                c.fillStyle = verlauf(c, krone, fuss, '#7a5638', '#4f3622');
                c.fillRect(tx - W * 0.026, krone, W * 0.052, fuss - krone);
                c.strokeStyle = 'rgba(50,30,15,.45)';
                c.lineWidth = 1;
                for (let y = krone + 8; y < fuss; y += zz.zwischen(8, 16)) linie(c, tx - W * 0.02, y, tx + W * zz.zwischen(-0.01, 0.02), y + 4);
                c.strokeStyle = KONTUR;
                c.strokeRect(tx - W * 0.026, krone, W * 0.052, fuss - krone);
                c.strokeStyle = '#6b4a2f';
                c.lineCap = 'round';
                c.lineWidth = W * 0.022;
                linie(c, tx, h * 0.585, tx - W * 0.27, h * 0.56);
                linie(c, tx, h * 0.515, tx + W * 0.29, h * 0.49);
                // Baumhaus mit Strohdach
                c.fillStyle = '#7a5638';
                c.fillRect(tx - W * 0.16, krone, W * 0.32, h * 0.013);
                zeichneStall(c, {x: tx, basis: krone, breite: W * 0.2, hoehe: h * 0.075, tuerB: W * 0.05, tuerH: h * 0.05}, zz);
            } else {
                const {x, basis, breite, hoehe} = ge.hoehle;
                [[-0.38, -0.25, 0.22, 0.3], [0.36, -0.22, 0.2, 0.28], [-0.12, -0.75, 0.3, 0.32], [0.18, -0.7, 0.26, 0.3], [0.02, -0.95, 0.24, 0.2]].forEach(([dx, dy, rx, ry]) => {
                    stein(c, x + dx * breite, basis + dy * hoehe, rx * breite, ry * hoehe * 1.1, '#e6dfcf', '#8c8270');
                    c.strokeStyle = KONTUR;
                    c.lineWidth = 1;
                    ellipse(c, x + dx * breite, basis + dy * hoehe, rx * breite, ry * hoehe * 1.1);
                    c.stroke();
                });
                c.fillStyle = verlauf(c, basis - hoehe * 0.6, basis, '#1d1712', '#3a3026');
                c.beginPath();
                c.moveTo(x - breite * 0.17, basis);
                c.quadraticCurveTo(x - breite * 0.14, basis - hoehe * 0.7, x, basis - hoehe * 0.68);
                c.quadraticCurveTo(x + breite * 0.14, basis - hoehe * 0.7, x + breite * 0.17, basis);
                c.fill();
            }
        });

        // Hecken zwischen den Gehegen
        for (let i = 1; i < gehege.length; i++) {
            const x = i * W;
            for (let y = h * 0.58; y < h * 0.82; y += h * 0.028) {
                const p = (y - h * 0.58) / (h * 0.24);
                blattwerk(c, x + zz.zwischen(-4, 4), y, W * mische(0.035, 0.07, p), h * mische(0.022, 0.04, p), LAUB_NAH, zz, blatt);
            }
            stein(c, x - W * 0.04, h * 0.815, W * 0.04, h * 0.018, '#d8cfbd', '#8f8573');
        }
    }

    function zeichneVorne(c) {
        const zz = zufallMitSaat(77);
        const blatt = Math.max(2.4, Math.min(b, h) * 0.005);

        // felsige Kante vor den Gehegen
        for (let x = -10; x < b + 20; x += zz.zwischen(16, 30)) {
            const rx = zz.zwischen(12, 24);
            const ry = zz.zwischen(7, 12);
            const y = h * 0.818 + zz.zwischen(-0.006, 0.006) * h;
            stein(c, x, y, rx, ry, '#d9cfba', '#8d826f');
            c.strokeStyle = KONTUR;
            c.lineWidth = 1;
            ellipse(c, x, y, rx, ry);
            c.stroke();
        }
        // Bachlauf
        const bach = c.createLinearGradient(0, h * 0.828, 0, h * 0.852);
        bach.addColorStop(0, '#9ec3b6');
        bach.addColorStop(1, '#79a59b');
        c.fillStyle = bach;
        c.fillRect(0, h * 0.83, b, h * 0.022);
        c.strokeStyle = 'rgba(255,255,255,.5)';
        c.lineWidth = 1.2;
        for (let i = 0; i < b / 40; i++) {
            const x = zz() * b;
            linie(c, x, h * zz.zwischen(0.834, 0.848), x + zz.zwischen(8, 20), h * zz.zwischen(0.834, 0.848));
        }
        // Grünstreifen mit Gras und Blumen
        c.fillStyle = verlauf(c, h * 0.85, h * 0.89, '#8fae58', '#6f8f45');
        c.fillRect(0, h * 0.85, b, h * 0.04);
        for (let i = 0; i < b / 5; i++) grasbueschel(c, zz() * b, h * zz.zwischen(0.862, 0.892), h * 0.016, ['#5f8a3a', '#7fa34c', '#4f7530'], zz);
        for (let i = 0; i < b / 35; i++) {
            c.fillStyle = zz.aus(['#e8674f', '#f2d45c', '#fbf6e9', '#c99adb']);
            ellipse(c, zz() * b, h * zz.zwischen(0.86, 0.885), 2.6, 2.6);
            c.fill();
        }

        // rustikaler Holzzaun
        const holz = (x0, y0, breite, hoehe) => {
            c.fillStyle = verlauf(c, y0, y0 + hoehe, '#a87a4a', '#6e4a2b');
            rundRechteck(c, x0, y0, breite, hoehe, Math.min(breite, hoehe) / 2);
            c.fill();
            c.strokeStyle = KONTUR;
            c.lineWidth = 1;
            c.stroke();
        };
        [h * 0.85, h * 0.871].forEach((y) => holz(-5, y + zz.zwischen(-1, 1), b + 10, h * 0.01));
        for (let x = 0; x <= b + 1; x += W / 6) holz(x - 4, h * 0.838 + zz.zwischen(-2, 2), 8, h * 0.056);

        // Schilder
        const schrift = klemme(W * 0.065, 11, 22);
        c.font = `700 ${schrift}px ui-rounded, "Trebuchet MS", system-ui, sans-serif`;
        c.textAlign = 'center';
        c.textBaseline = 'middle';
        gehege.forEach((ge) => {
            const bb = c.measureText(ge.name).width + schrift * 1.6;
            const bh = schrift * 1.8;
            const y = h * 0.862;
            c.fillStyle = 'rgba(50,30,15,.25)';
            rundRechteck(c, ge.mitte - bb / 2 + 3, y - bh / 2 + 3, bb, bh, 7);
            c.fill();
            c.fillStyle = verlauf(c, y - bh / 2, y + bh / 2, '#c4935a', '#8c5f33');
            rundRechteck(c, ge.mitte - bb / 2, y - bh / 2, bb, bh, 7);
            c.fill();
            c.strokeStyle = KONTUR;
            c.lineWidth = 1.5;
            c.stroke();
            c.fillStyle = '#5e3d24';
            c.fillText(ge.name, ge.mitte + 1, y + 2);
            c.fillStyle = '#fbf0d5';
            c.fillText(ge.name, ge.mitte, y + 1);
        });

        // sandiger Weg
        c.fillStyle = verlauf(c, h * 0.89, h, '#efe0bb', '#dcc69a');
        c.beginPath();
        c.moveTo(0, h * 0.893);
        for (let x = 0; x <= b + 20; x += 20) c.lineTo(x, h * 0.893 + Math.sin(x * 0.02) * h * 0.003);
        c.lineTo(b, h);
        c.lineTo(0, h);
        c.fill();
        for (let i = 0; i < b / 4; i++) {
            c.fillStyle = zz.aus(['rgba(150,120,70,.25)', 'rgba(255,250,235,.5)', 'rgba(170,140,90,.3)']);
            ellipse(c, zz() * b, h * zz.zwischen(0.9, 1), zz.zwischen(1, 3), zz.zwischen(0.8, 2));
            c.fill();
        }

        // große Bäume als Rahmen (links und rechts) mit überhängenden Kronen
        [[b * 0.025, 1], [b * 0.975, -1]].forEach(([x, seite]) => {
            const v = c.createLinearGradient(x - h * 0.05, 0, x + h * 0.05, 0);
            v.addColorStop(0, '#4a3424');
            v.addColorStop(0.5, '#6e5038');
            v.addColorStop(1, '#3e2b1d');
            c.fillStyle = v;
            c.beginPath();
            c.moveTo(x - h * 0.06, h);
            c.quadraticCurveTo(x - h * 0.03 * seite, h * 0.55, x + h * 0.04 * seite, h * 0.12);
            c.lineTo(x + h * 0.085 * seite, h * 0.12);
            c.quadraticCurveTo(x + h * 0.03 * seite, h * 0.55, x + h * 0.06, h);
            c.fill();
            c.strokeStyle = KONTUR;
            c.lineWidth = 1.5;
            c.stroke();
            c.strokeStyle = 'rgba(40,25,12,.35)';
            c.lineWidth = 1;
            for (let y = h * 0.2; y < h; y += zz.zwischen(10, 22)) linie(c, x - h * 0.02, y, x + h * 0.02, y + zz.zwischen(-3, 6));
            // Äste ins Bild
            c.strokeStyle = '#5a4030';
            c.lineCap = 'round';
            c.lineWidth = h * 0.016;
            c.beginPath();
            c.moveTo(x + h * 0.05 * seite, h * 0.22);
            c.quadraticCurveTo(x + b * 0.1 * seite, h * 0.12, x + b * 0.2 * seite, h * 0.08);
            c.stroke();
            // Kronen über der Ecke
            [[0.0, 0.02, 0.17, 0.14], [0.1, -0.02, 0.15, 0.11], [0.2, 0.03, 0.14, 0.09], [0.05, 0.16, 0.1, 0.09], [0.28, -0.03, 0.1, 0.07]].forEach(([dx, dy, rx, ry]) => {
                blattwerk(c, x + dx * b * seite, dy * h, rx * b, ry * h, LAUB_RAHMEN, zz, blatt * 1.2);
            });
        });
        // Büsche mit großen Blättern in den unteren Ecken
        [[b * 0.03, 1], [b * 0.97, -1]].forEach(([x, seite]) => {
            blattwerk(c, x + b * 0.04 * seite, h * 0.96, b * 0.07, h * 0.08, LAUB_RAHMEN, zz, blatt * 1.6);
            for (let i = 0; i < 6; i++) {
                const w = -Math.PI / 2 + (i - 2.5) * 0.35;
                c.save();
                c.translate(x + b * 0.03 * seite, h * 1.0);
                c.rotate(w * seite + (seite < 0 ? Math.PI : 0) * 0);
                c.fillStyle = i % 2 ? '#5a7f34' : '#4a6b2c';
                ellipse(c, 0, -h * 0.09, h * 0.025, h * 0.09);
                c.fill();
                c.strokeStyle = 'rgba(255,255,255,.2)';
                linie(c, 0, -h * 0.01, 0, -h * 0.17);
                c.restore();
            }
        });
    }

    function zeichneKoernung(c) {
        // Papierkörnung und warme Vignette – einmal vorgezeichnet, pro Bild nur kopiert
        const zz = zufallMitSaat(5);
        const anzahl = Math.round((b * h) / 90);
        for (let i = 0; i < anzahl; i++) {
            c.fillStyle = zz() < 0.5 ? 'rgba(90,60,25,.05)' : 'rgba(255,248,230,.06)';
            c.fillRect(zz() * b, zz() * h, zz.zwischen(1, 2.5), zz.zwischen(1, 2.5));
        }
        const v = c.createRadialGradient(b / 2, h * 0.45, Math.min(b, h) * 0.35, b / 2, h * 0.5, Math.max(b, h) * 0.75);
        v.addColorStop(0, 'rgba(60,40,20,0)');
        v.addColorStop(1, 'rgba(60,40,20,.22)');
        c.fillStyle = v;
        c.fillRect(0, 0, b, h);
    }

    function groesse(breite, hoehe, einstellungen) {
        const neu = breite !== b || hoehe !== h;
        const faktor = einstellungen.menge / 100;
        b = breite;
        h = hoehe;
        W = b / GEHEGE.length;
        g0 = Math.min(W * 0.42, h * 0.17);

        if (neu || gehege.length === 0) {
            const zz = zufallMitSaat(9);
            baueGehege();
            wolken = Array.from({length: 5}, () => ({
                x: zz(), y: zz.zwischen(0.05, 0.28), groesse: zz.zwischen(0.06, 0.11), tempo: zz.zwischen(0.004, 0.009),
            }));
            ebeneHimmel = neueEbene(zeichneHimmel);
            ebeneLandschaft = neueEbene(zeichneLandschaft);
            ebeneVorne = neueEbene(zeichneVorne);
            ebeneKoernung = neueEbene(zeichneKoernung);
        }
        if (neu || tiere.length === 0 || faktor !== mengenFaktor) {
            mengenFaktor = faktor;
            erzeugeTiere();
            gehege.forEach((ge) => { ge.futterListe = []; });
            fuetterung = null;
        }
    }

    // ── Fütterung ────────────────────────────────────────────────────────
    function starteFuetterung(index = null) {
        // nur dort füttern, wo schon Tiere draußen sind
        const mitTieren = (i) => tiere.some((x) => x.ge === gehege[i] && x.freigeschaltet);
        let gi = index ?? fuetterNaechstes;
        for (let n = 0; n < gehege.length && !mitTieren(gi); n++) gi = (gi + 1) % gehege.length;
        if (!mitTieren(gi)) {
            fuetterWarten = 10;
            return;
        }
        fuetterNaechstes = (gi + 1) % gehege.length;
        const richtung = gehege[gi].mitte < b / 2 ? 1 : -1;
        fuetterung = {
            gi, richtung, x: richtung > 0 ? -80 : b + 80, phase: 0, zustand: 'kommt', zeit: 0, geworfen: false, eilig: false,
            pflegerin: Math.random() < 0.5,
            haut: zufallAus(['#f1c9a0', '#e0ac7e', '#c68a5b', '#8d5a3b']),
            haar: zufallAus(['#7c4a1e', '#2d1b10', '#d4a24c', '#a3461d']),
        };
        fuetterWarten = zufall(50, 90);
    }

    function wirfFutter(f, ge) {
        const gp = g0 * 0.75;
        for (let k = 0; k < 5; k++) {
            ge.futterListe.push({
                art: ge.futter,
                sx: f.x + f.richtung * gp * 0.2, sy: h * 0.95 - gp * 0.95,
                zx: ge.korb ? ge.korb.x + zufall(-0.02, 0.02) * W : ge.mitte + zufall(-0.22, 0.22) * W,
                zy: ge.korb ? ge.korb.y - zufall(0, 0.01) * h : zufall(h * 0.71, h * 0.78),
                flug: 0, verzoegerung: k * 0.18, rest: 1, drehung: zufall(0, 6),
            });
        }
    }

    function schrittFuetterung(dt, z) {
        gehege.forEach((ge) => {
            ge.futterListe.forEach((futter) => {
                if (futter.verzoegerung > 0) futter.verzoegerung -= dt;
                else futter.flug = Math.min(1, futter.flug + dt / 0.9);
            });
            ge.futterListe = ge.futterListe.filter((futter) => futter.rest > 0);
        });

        if (!fuetterung) {
            if (z.unruhe < 0.15) fuetterWarten -= dt;
            if (fuetterWarten <= 0) starteFuetterung();
            return;
        }
        const f = fuetterung;
        const ge = gehege[f.gi];
        if (z.unruhe > 0.5 && f.zustand !== 'geht') {
            f.zustand = 'geht';
            f.eilig = true;
        }
        const tempo = W * 0.3 * (f.eilig ? 3 : 1);
        if (f.zustand !== 'wirft') f.phase += dt * 7 * (f.eilig ? 1.6 : 1);
        if (f.zustand === 'kommt') {
            const dx = ge.mitte - f.x;
            if (Math.abs(dx) <= tempo * dt) {
                f.x = ge.mitte;
                f.zustand = 'wirft';
                f.zeit = 0;
            } else {
                f.x += Math.sign(dx) * tempo * dt;
            }
        } else if (f.zustand === 'wirft') {
            f.zeit += dt;
            if (!f.geworfen && f.zeit > 0.6) {
                wirfFutter(f, ge);
                f.geworfen = true;
            }
            if (f.zeit > 3.4) f.zustand = 'geht';
        } else {
            f.x += f.richtung * tempo * dt;
            if (f.x < -120 || f.x > b + 120) fuetterung = null;
        }
    }

    // ── Tiere bewegen ────────────────────────────────────────────────────
    function schrittTier(tier, dt, z) {
        const ge = tier.ge;
        const verstecken = z.unruhe > tier.mut || !tier.freigeschaltet || (tier.baby && tier.eltern.flieht);
        const ruhigGenug = z.unruhe < tier.mut - 0.1 && tier.freigeschaltet && (!tier.baby || !tier.eltern.flieht);
        if (!tier.flieht && verstecken) {
            tier.flieht = true;
        } else if (tier.flieht && ruhigGenug) {
            tier.flieht = false;
            tier.warten = zufall(0.2, 1.5);
            waehleZiel(tier);
        }

        const futterDa = ge.futterListe.filter((f) => f.flug >= 1 && f.rest > 0);
        const nachbarn = tiere.filter((x) => x.ge === ge);
        let meinFutter = null;
        let ziel;
        if (tier.flieht) {
            ziel = ge.tuer;
        } else if (futterDa.length) {
            const idx = nachbarn.indexOf(tier);
            meinFutter = futterDa[idx % futterDa.length];
            const abstand = tier.art === 'zebra' ? 1.3 : tier.baby ? 0.9 : 0.5;
            ziel = ge.korb
                ? {x: ge.korb.x + (idx % 2 ? 1 : -1) * tier.g * abstand, y: ge.korb.boden + (idx % 2 ? 3 : -3) + (tier.art === 'zebra' ? h * 0.02 : 0)}
                : {x: meinFutter.zx + (idx % 2 ? 1 : -1) * tier.g * 0.35, y: meinFutter.zy + 2};
        } else if (tier.baby) {
            const e = tier.eltern;
            ziel = {x: e.x - e.richtung * e.g * 0.7, y: e.y + 3};
        } else {
            ziel = {x: tier.zx, y: tier.zy};
        }

        const dx = ziel.x - tier.x;
        const dy = ziel.y - tier.y;
        const d = Math.hypot(dx, dy) || 0.0001;
        let tempo = tier.g * TEMPO[tier.art] * (tier.baby ? 1.5 : 1) * (tier.flieht ? 2.8 : 1);
        if (tier.baby && !tier.flieht && !meinFutter) tempo *= klemme(d / (tier.g * 2), 0.3, 2.5);
        const amZiel = d <= Math.max(1.5, tempo * dt * 1.2);
        const bewege = () => {
            tier.x += (dx / d) * tempo * dt;
            tier.y += (dy / d) * tempo * dt;
            tier.laeuft = true;
            if (Math.abs(dx) > 0.5) tier.dirZiel = Math.sign(dx);
        };

        tier.frisst = false;
        tier.laeuft = false;
        if (tier.flieht) {
            if (!amZiel) bewege();
            if (d < tier.g * 0.5) tier.alpha = Math.max(0, tier.alpha - dt * 1.6);
            if (tier.alpha <= 0) tier.begruesst = false;
        } else {
            tier.alpha = Math.min(1, tier.alpha + dt * 1.2);
            if (tier.baby && !tier.begruesst && tier.alpha > 0.5) {
                tier.begruesst = true;
                herzen.push({x: tier.x, y: tier.y - tier.g * HOEHE[tier.art], alter: 0});
            }
            if (tier.alpha < 0.6) {
                // kommt gerade aus der Tür
            } else if (meinFutter && amZiel) {
                tier.frisst = true;
                meinFutter.rest -= dt / 16;
                if (Math.random() < dt * 0.5) {
                    herzen.push({x: tier.x + zufall(-0.2, 0.2) * tier.g, y: tier.y - tier.g * HOEHE[tier.art] * tiefe(tier.y), alter: 0});
                }
                const zielX = ge.korb ? ge.korb.x : meinFutter.zx;
                if (Math.abs(zielX - tier.x) > 1) tier.dirZiel = Math.sign(zielX - tier.x);
            } else if (!meinFutter && !tier.baby && tier.warten > 0) {
                tier.warten -= dt;
            } else if (!amZiel) {
                bewege();
            } else if (!tier.baby && !meinFutter) {
                tier.warten = zufall(2, 6);
                waehleZiel(tier);
            }
        }
        tier.richtung += (tier.dirZiel - tier.richtung) * Math.min(1, dt * 5);
        tier.phase += dt * (tier.laeuft ? 5 : tier.frisst ? 4 : 1.2);
    }

    function schritt(dt, z) {
        t += dt;
        // Ruhe sammeln: jedes Tier kommt heraus, sobald seine Schwelle erreicht ist;
        // anhaltender Lärm schickt die zuletzt gekommenen Tiere wieder hinein (mit kleiner Hysterese)
        if (z.unruhe < 0.15) ruheZeit += dt;
        else if (z.unruhe > 0.6) ruheZeit -= dt * 2.5 * z.unruhe;
        ruheZeit = klemme(ruheZeit, 0, maxSchwelle + 5);
        tiere.forEach((x) => {
            x.freigeschaltet = x.freigeschaltet ? ruheZeit >= x.schwelle - 3 : ruheZeit >= x.schwelle;
        });

        schrittFuetterung(dt, z);
        tiere.forEach((tier) => schrittTier(tier, dt, z));

        for (let i = herzen.length - 1; i >= 0; i--) {
            herzen[i].alter += dt;
            herzen[i].y -= 18 * dt;
            if (herzen[i].alter > 1.8) herzen.splice(i, 1);
        }
        // Wolken ziehen langsam nach rechts und kommen links wieder herein
        wolken.forEach((w) => {
            w.x += w.tempo * dt;
            if (w.x > 1.15) w.x -= 1.3;
        });
    }

    // ── Zeichnen (bewegte Teile) ─────────────────────────────────────────
    function zeichneWolken(ctx) {
        wolken.forEach((w) => {
            const x = w.x * b;
            const y = w.y * h;
            const r = w.groesse * Math.max(b, h * 1.5);
            [[-0.4, 0.06, 0.28], [-0.12, -0.08, 0.36], [0.2, -0.04, 0.32], [0.45, 0.07, 0.24], [0.02, 0.1, 0.34]].forEach(([dx, dy, rr]) => {
                const v = ctx.createLinearGradient(0, y + (dy - rr * 0.6) * r, 0, y + (dy + rr * 0.6) * r);
                v.addColorStop(0, 'rgba(255,251,238,.97)');
                v.addColorStop(1, 'rgba(232,222,196,.95)');
                ctx.fillStyle = v;
                ellipse(ctx, x + dx * r, y + dy * r, rr * r, rr * r * 0.62);
                ctx.fill();
            });
        });
    }

    function zeichneWasser(ctx, ge) {
        const w = ge.wasser;
        const pinguin = ge.art === 'pinguin';
        // Rand: Steine bzw. feuchter Schlamm
        ctx.fillStyle = pinguin ? '#cfc6b2' : 'rgba(125,92,55,.55)';
        ellipse(ctx, w.x, w.y + w.ry * 0.1, w.rx * 1.07, w.ry * 1.35);
        ctx.fill();
        const v = ctx.createLinearGradient(0, w.y - w.ry, 0, w.y + w.ry);
        v.addColorStop(0, pinguin ? '#a9d6cf' : '#a9c8bb');
        v.addColorStop(1, pinguin ? '#4f8f94' : '#6a978d');
        ctx.fillStyle = v;
        ellipse(ctx, w.x, w.y, w.rx, w.ry);
        ctx.fill();
        // Himmelsspiegelung und Lichtkringel
        ctx.save();
        ellipse(ctx, w.x, w.y, w.rx, w.ry);
        ctx.clip();
        ctx.fillStyle = 'rgba(255,255,255,.18)';
        ellipse(ctx, w.x - w.rx * 0.3, w.y - w.ry * 0.5, w.rx * 0.5, w.ry * 0.3);
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,.45)';
        ctx.lineWidth = 1.2;
        for (let i = 0; i < 5; i++) {
            const wx = w.x + Math.sin(t * 0.4 + i * 1.7) * w.rx * 0.6;
            const wy = w.y + Math.cos(t * 0.3 + i) * w.ry * 0.5;
            ctx.beginPath();
            ctx.ellipse(wx, wy, w.rx * 0.12, w.ry * 0.12, 0, Math.PI, Math.PI * 2);
            ctx.stroke();
        }
        ctx.restore();
        if (pinguin) {
            [[-0.95, 0.4], [0.9, 0.5], [-0.5, -0.95], [0.4, -0.98]].forEach(([fx, fy]) => {
                stein(ctx, w.x + fx * w.rx, w.y + fy * w.ry, W * 0.035, h * 0.012, '#ece5d4', '#9b917d');
            });
        }
    }

    function zeichneSeile(ctx, ge) {
        const tx = ge.kletterbaum.x;
        ctx.strokeStyle = '#c9a66b';
        ctx.lineWidth = Math.max(2, W * 0.006);
        ctx.lineCap = 'round';
        const links = Math.sin(t * 0.8) * W * 0.015;
        linie(ctx, tx - W * 0.2, h * 0.565, tx - W * 0.2 + links, h * 0.665);
        ctx.beginPath();
        ctx.arc(tx - W * 0.2 + links, h * 0.675, W * 0.018, 0, Math.PI * 2);
        ctx.stroke();
        // Reifenschaukel
        const rechts = Math.sin(t * 0.7 + 1) * W * 0.02;
        linie(ctx, tx + W * 0.22, h * 0.495, tx + W * 0.22 + rechts, h * 0.64);
        ctx.strokeStyle = '#3a2f28';
        ctx.lineWidth = Math.max(3, W * 0.014);
        ellipse(ctx, tx + W * 0.22 + rechts, h * 0.655, W * 0.035, h * 0.016);
        ctx.stroke();
    }

    function zeichneFutter(ctx, f) {
        if (f.flug <= 0) return;
        let x = f.zx;
        let y = f.zy;
        if (f.flug < 1) {
            x = mische(f.sx, f.zx, f.flug);
            y = mische(f.sy, f.zy, f.flug) - Math.sin(f.flug * Math.PI) * h * 0.12;
        }
        const s = g0 * 0.1 * tiefe(f.zy) * (0.35 + 0.65 * Math.max(0, f.rest));
        ctx.save();
        ctx.translate(x, y);
        if (f.flug < 1) ctx.rotate(f.drehung + f.flug * 6);
        if (f.art === 'heu') {
            ctx.fillStyle = verlauf(ctx, -s * 0.8, 0, '#ecc65a', '#b98d2c');
            ellipse(ctx, 0, -s * 0.3, s * 1.3, s * 0.55);
            ctx.fill();
            ctx.strokeStyle = '#a07a22';
            ctx.lineWidth = 1;
            for (let i = -2; i <= 2; i++) linie(ctx, i * s * 0.4, -s * 0.1, i * s * 0.5 + s * 0.2, -s * 0.7);
            const apfel = ctx.createRadialGradient(s * 0.5, -s * 0.8, 0, s * 0.6, -s * 0.7, s * 0.45);
            apfel.addColorStop(0, '#fca5a5');
            apfel.addColorStop(1, '#b91c1c');
            ctx.fillStyle = apfel;
            ellipse(ctx, s * 0.6, -s * 0.7, s * 0.42, s * 0.4);
            ctx.fill();
        } else if (f.art === 'blaetter') {
            [['#3f6212', -0.5], ['#4d7c0f', 0], ['#65a30d', 0.5]].forEach(([ton, w]) => {
                ctx.fillStyle = ton;
                ellipse(ctx, Math.sin(w) * s * 0.6, -s * 0.5, s * 0.4, s * 0.9, w);
                ctx.fill();
            });
        } else if (f.art === 'bananen') {
            ctx.lineCap = 'round';
            [['#facc15', 0, 0], ['#eab308', s * 0.3, -s * 0.2]].forEach(([ton, ox, oy]) => {
                ctx.strokeStyle = ton;
                ctx.lineWidth = s * 0.45;
                ctx.beginPath();
                ctx.arc(ox, -s * 1.1 + oy, s, 0.22 * Math.PI, 0.78 * Math.PI);
                ctx.stroke();
            });
        } else {
            const fisch = ctx.createLinearGradient(0, -s * 0.7, 0, s * 0.1);
            fisch.addColorStop(0, '#475569');
            fisch.addColorStop(1, '#cbd5e1');
            ctx.fillStyle = fisch;
            ellipse(ctx, 0, -s * 0.3, s, s * 0.38);
            ctx.fill();
            ctx.beginPath();
            ctx.moveTo(-s * 0.85, -s * 0.3);
            ctx.lineTo(-s * 1.4, -s * 0.65);
            ctx.lineTo(-s * 1.4, s * 0.05);
            ctx.fill();
            ctx.fillStyle = '#0f172a';
            ellipse(ctx, s * 0.55, -s * 0.38, s * 0.08, s * 0.08);
            ctx.fill();
        }
        ctx.restore();
    }

    function zeichneTier(ctx, tier) {
        if (tier.alpha <= 0.01) return;
        const s = tier.art === 'affe' ? tiefe(Math.max(tier.y, h * 0.72)) : tiefe(tier.y);
        const g = tier.g * s;
        const wasser = imWasser(tier.ge, tier.x, tier.y);
        const hop = tier.art === 'affe' && tier.laeuft ? Math.abs(Math.sin(tier.phase * 0.55)) * g * 0.25 : 0;
        ctx.save();
        ctx.globalAlpha = tier.alpha;
        ctx.translate(tier.x, tier.y - hop);
        if (!wasser) {
            const schatten = ctx.createRadialGradient(0, hop, 0, 0, hop, g * 0.5);
            schatten.addColorStop(0, 'rgba(0,0,0,.25)');
            schatten.addColorStop(1, 'rgba(0,0,0,0)');
            ctx.fillStyle = schatten;
            ellipse(ctx, g * 0.05, hop + 1, g * 0.5, g * 0.08);
            ctx.fill();
        } else {
            const wasserlinie = -g * (tier.art === 'pinguin' ? 0.32 : 0.3);
            ctx.beginPath();
            ctx.rect(-g * 3, -g * 4, g * 6, g * 4 + wasserlinie);
            ctx.clip();
        }
        const richtung = tier.richtung >= 0 ? Math.max(0.15, tier.richtung) : Math.min(-0.15, tier.richtung);
        ctx.scale(richtung, 1);
        ZEICHNER[tier.art](ctx, g, tier.phase, tier.laeuft, tier.baby, tier.frisst, tier.saat);
        ctx.restore();
        if (wasser) {
            ctx.strokeStyle = mitAlpha('#ffffff', 0.75 * tier.alpha);
            ctx.lineWidth = 1.5;
            ellipse(ctx, tier.x, tier.y - g * 0.3, g * (0.35 + Math.sin(t * 2 + tier.phase) * 0.04), g * 0.06);
            ctx.stroke();
        }
    }

    function zeichne(ctx, breite, hoehe, z) {
        const unruhe = glatt(z.unruhe);
        ctx.drawImage(ebeneHimmel, 0, 0, breite, hoehe);
        zeichneWolken(ctx);
        ctx.drawImage(ebeneLandschaft, 0, 0, breite, hoehe);
        gehege.forEach((ge) => {
            if (ge.wasser) zeichneWasser(ctx, ge);
            if (ge.kletterbaum) zeichneSeile(ctx, ge);
        });

        gehege.forEach((ge) => ge.futterListe.filter((f) => f.flug >= 1).forEach((f) => zeichneFutter(ctx, f)));
        [...tiere].sort((a, c) => a.y - c.y).forEach((tier) => zeichneTier(ctx, tier));
        herzen.forEach((hz) => {
            ctx.fillStyle = mitAlpha('#f43f5e', 1 - hz.alter / 1.8);
            herz(ctx, hz.x + Math.sin(hz.alter * 4) * 4, hz.y, g0 * 0.07);
        });

        ctx.drawImage(ebeneVorne, 0, 0, breite, hoehe);
        if (fuetterung) {
            ctx.save();
            ctx.translate(fuetterung.x, hoehe * 0.95);
            ctx.scale(fuetterung.richtung, 1);
            zeichnePfleger(ctx, g0 * 0.78, fuetterung, FUTTER_FARBE[gehege[fuetterung.gi].futter]);
            ctx.restore();
        }
        gehege.forEach((ge) => ge.futterListe.filter((f) => f.flug < 1).forEach((f) => zeichneFutter(ctx, f)));

        ctx.drawImage(ebeneKoernung, 0, 0, breite, hoehe);
        if (unruhe > 0.01) {
            ctx.fillStyle = `rgba(45,40,50,${0.18 * unruhe})`;
            ctx.fillRect(0, 0, breite, hoehe);
        }
    }

    function info() {
        if (fuetterung && !fuetterung.eilig) return `Fütterung: ${gehege[fuetterung.gi].name}`;
        const draussen = tiere.filter((x) => !x.flieht && x.alpha > 0.5).length;
        return `Tiere draußen: ${draussen} von ${tiere.length}`;
    }

    function besuch(name) {
        if (name === 'alle' || name === 'babys') ruheZeit = maxSchwelle;
        else if (name === 'fuetterung') {
            ruheZeit = maxSchwelle;
            tiere.forEach((x) => { x.freigeschaltet = true; });
            starteFuetterung();
        } else return false;
        return true;
    }

    return {
        mengeLabel: 'Anzahl der Tiere',
        texte: {
            ruhig: 'Psst – nach und nach kommen die Tiere heraus.',
            fast: 'Etwas laut – die Tiere werden unruhig.',
            laut: 'Zu laut – alle Tiere verstecken sich im Stall!',
        },
        groesse,
        schritt,
        zeichne,
        info,
        besuch,
    };
}
