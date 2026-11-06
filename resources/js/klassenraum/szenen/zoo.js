/*
 * Szene „Zoo“: Elefanten, Giraffen, Affen und Pinguine in ihren Gehegen.
 *
 * Je länger es ruhig ist, desto mehr Tierbabys trauen sich aus dem Stall (nacheinander).
 * Nach längerer Ruhe kommt eine Tierpflegerin oder ein Tierpfleger über den Weg und füttert
 * ein Gehege – die Tiere kommen zum Futter, fressen und freuen sich (Herzen). Die Giraffen
 * fressen aus einem Futterkorb auf Kopfhöhe.
 * Wird es laut, verstecken sich zuerst die Babys, dann die großen Tiere im Stall; die Fütterung
 * wird abgebrochen, und anhaltender Lärm kostet wieder Tierbabys.
 *
 * Darstellung: Tiere und Pfleger:innen in szenen/zoo-tiere.js. Alles Unbewegte (Himmel,
 * Landschaft mit Gehegen und Häusern, Zaun und Weg) wird bei jeder Größenänderung einmal
 * in eigene Ebenen vorgezeichnet und pro Bild nur noch kopiert – so bleibt die Szene trotz
 * vieler Details flüssig.
 *
 * Vorschau: ?besuch=babys (alle Babys freigeschaltet), ?besuch=fuetterung (Fütterung sofort)
 */

import {ellipse, glatt, klemme, mische, mitAlpha, zufall, zufallAus, zufallMitSaat} from '../shared/hilfen';
import {zeichneAffe, zeichneElefant, zeichneGiraffe, zeichnePfleger, zeichnePinguin} from './zoo-tiere';

const GEHEGE = [
    {art: 'elefant', name: 'Elefanten', futter: 'heu'},
    {art: 'giraffe', name: 'Giraffen', futter: 'blaetter'},
    {art: 'affe', name: 'Affen', futter: 'bananen'},
    {art: 'pinguin', name: 'Pinguine', futter: 'fische'},
];
const ANZAHL = {elefant: 2, giraffe: 2, affe: 3, pinguin: 4};
const GROESSE = {elefant: 0.95, giraffe: 0.62, affe: 0.5, pinguin: 0.5};
const TEMPO = {elefant: 0.22, giraffe: 0.3, affe: 0.8, pinguin: 0.4};
const HOEHE = {elefant: 0.95, giraffe: 1.9, affe: 0.95, pinguin: 0.95};
const FUTTER_FARBE = {heu: '#e0b54a', blaetter: '#65a30d', bananen: '#facc15', fische: '#64748b'};
// Reihenfolge, in der die Babys bei anhaltender Ruhe erscheinen
const BABYS = ['elefant', 'pinguin', 'giraffe', 'affe', 'pinguin'];
const SEKUNDEN_PRO_BABY = 15;

const ZEICHNER = {elefant: zeichneElefant, giraffe: zeichneGiraffe, affe: zeichneAffe, pinguin: zeichnePinguin};

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

export function erzeugeZoo() {
    let b = 0;
    let h = 0;
    let W = 0;
    let g0 = 0;
    let gehege = [];
    let tiere = [];
    let wolken = [];
    let t = 0;
    let babyStufe = 0;
    let fuetterung = null;
    let fuetterWarten = zufall(30, 45);
    let fuetterNaechstes = Math.floor(Math.random() * GEHEGE.length);
    let mengenFaktor = 1;
    let ebeneHimmel = null;
    let ebeneLandschaft = null;
    let ebeneVorne = null;
    const herzen = [];

    const tiefe = (y) => mische(0.72, 1, klemme((y - h * 0.62) / (h * 0.2), 0, 1));
    const bereich = (ge) => ({xMin: ge.x0 + W * 0.1, xMax: ge.x1 - W * 0.1, yMin: h * 0.665, yMax: h * 0.79});
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
        if (ge.art === 'affe' && Math.random() < 0.45) {
            const platz = zufallAus(ge.plaetze);
            tier.zx = platz.x + zufall(-0.04, 0.04) * W;
            tier.zy = platz.y;
        } else {
            const bb = bereich(ge);
            tier.zx = zufall(bb.xMin, bb.xMax);
            tier.zy = zufall(bb.yMin, bb.yMax);
        }
    }

    function neuesTier(ge, baby) {
        const bb = bereich(ge);
        const tier = {
            ge, art: ge.art, baby, saat: Math.floor(zufall(1, 9999)),
            g: g0 * GROESSE[ge.art] * (baby ? 0.55 : 1),
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
            const anzahl = Math.max(1, Math.round(ANZAHL[ge.art] * mengenFaktor));
            for (let k = 0; k < anzahl; k++) tiere.push(neuesTier(ge, false));
        });
        const babysJeArt = {};
        BABYS.forEach((art, nummer) => {
            const ge = gehege.find((g) => g.art === art);
            const eltern = tiere.filter((x) => x.ge === ge && !x.baby);
            babysJeArt[art] = (babysJeArt[art] ?? -1) + 1;
            const baby = neuesTier(ge, true);
            baby.eltern = eltern[babysJeArt[art] % eltern.length];
            baby.nummer = nummer;
            baby.flieht = true;
            baby.alpha = 0;
            baby.x = ge.tuer.x;
            baby.y = ge.tuer.y;
            tiere.push(baby);
        });
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

    function zeichneHimmel(c) {
        const himmel = c.createLinearGradient(0, 0, 0, h * 0.6);
        himmel.addColorStop(0, '#4fa3dc');
        himmel.addColorStop(0.7, '#a9d8f0');
        himmel.addColorStop(1, '#e6f4fa');
        c.fillStyle = himmel;
        c.fillRect(0, 0, b, h * 0.62);
        const sx = b * 0.62;
        const sy = h * 0.12;
        const sr = Math.min(b, h) * 0.045;
        const schein = c.createRadialGradient(sx, sy, sr * 0.5, sx, sy, sr * 5);
        schein.addColorStop(0, 'rgba(255,244,200,.75)');
        schein.addColorStop(1, 'rgba(255,244,200,0)');
        c.fillStyle = schein;
        c.fillRect(sx - sr * 5, sy - sr * 5, sr * 10, sr * 10);
        const sonne = c.createRadialGradient(sx - sr * 0.3, sy - sr * 0.3, 0, sx, sy, sr);
        sonne.addColorStop(0, '#fffbe6');
        sonne.addColorStop(1, '#fcd34d');
        c.fillStyle = sonne;
        ellipse(c, sx, sy, sr, sr);
        c.fill();
    }

    function zeichneStall(c, haus) {
        const {x, basis, breite, hoehe, tuerB, tuerH} = haus;
        const links = x - breite / 2;
        // Schatten am Boden
        c.fillStyle = 'rgba(40,30,15,.18)';
        ellipse(c, x + breite * 0.08, basis + 2, breite * 0.62, breite * 0.07);
        c.fill();
        // Steinsockel
        const sockel = hoehe * 0.16;
        c.fillStyle = verlauf(c, basis - sockel, basis, '#a8a29e', '#78716c');
        c.fillRect(links, basis - sockel, breite, sockel);
        c.strokeStyle = 'rgba(60,55,50,.35)';
        c.lineWidth = 1;
        const steinB = breite / 7;
        for (let reihe = 0; reihe < 2; reihe++) {
            const y = basis - sockel + reihe * sockel / 2;
            linie(c, links, y, links + breite, y);
            for (let s = (reihe % 2) * steinB / 2; s < breite; s += steinB) linie(c, links + s, y, links + s, y + sockel / 2);
        }
        // Bretterwand
        const wand = c.createLinearGradient(links, 0, links + breite, 0);
        wand.addColorStop(0, '#9a6a40');
        wand.addColorStop(0.5, '#b88152');
        wand.addColorStop(1, '#8a5c35');
        c.fillStyle = wand;
        c.fillRect(links, basis - hoehe, breite, hoehe - sockel);
        c.strokeStyle = 'rgba(70,40,20,.35)';
        for (let s = links + breite / 9; s < links + breite; s += breite / 9) linie(c, s, basis - hoehe, s, basis - sockel);
        // Fenster
        if (breite > hoehe * 0.9) {
            const fx = links + breite * 0.15;
            const fy = basis - hoehe * 0.78;
            const fb = breite * 0.16;
            c.fillStyle = '#5b3a20';
            c.fillRect(fx - 3, fy - 3, fb + 6, fb * 0.8 + 6);
            c.fillStyle = verlauf(c, fy, fy + fb * 0.8, '#bfe3f5', '#7fb5d1');
            c.fillRect(fx, fy, fb, fb * 0.8);
            c.strokeStyle = '#5b3a20';
            c.lineWidth = 2;
            linie(c, fx + fb / 2, fy, fx + fb / 2, fy + fb * 0.8);
            linie(c, fx, fy + fb * 0.4, fx + fb, fy + fb * 0.4);
        }
        // Tür (Rundbogen) mit dunklem Inneren
        const tuer = c.createLinearGradient(0, basis - tuerH, 0, basis);
        tuer.addColorStop(0, '#1c120b');
        tuer.addColorStop(1, '#3b2416');
        c.fillStyle = '#6b4226';
        c.beginPath();
        c.moveTo(x - tuerB / 2 - 4, basis);
        c.lineTo(x - tuerB / 2 - 4, basis - tuerH + tuerB / 2);
        c.arc(x, basis - tuerH + tuerB / 2, tuerB / 2 + 4, Math.PI, 0);
        c.lineTo(x + tuerB / 2 + 4, basis);
        c.fill();
        c.fillStyle = tuer;
        c.beginPath();
        c.moveTo(x - tuerB / 2, basis);
        c.lineTo(x - tuerB / 2, basis - tuerH + tuerB / 2);
        c.arc(x, basis - tuerH + tuerB / 2, tuerB / 2, Math.PI, 0);
        c.lineTo(x + tuerB / 2, basis);
        c.fill();
        // Dach mit Ziegelreihen
        const dachH = breite * 0.3;
        const dach = new Path2D();
        dach.moveTo(links - breite * 0.1, basis - hoehe + 2);
        dach.lineTo(x, basis - hoehe - dachH);
        dach.lineTo(links + breite * 1.1, basis - hoehe + 2);
        dach.closePath();
        c.fillStyle = verlauf(c, basis - hoehe - dachH, basis - hoehe, '#b4441f', '#7c2d12');
        c.fill(dach);
        c.save();
        c.clip(dach);
        c.strokeStyle = 'rgba(60,20,8,.35)';
        c.lineWidth = 1;
        for (let y = basis - hoehe - dachH + 5; y < basis - hoehe; y += 5) linie(c, links - breite * 0.1, y, links + breite * 1.1, y);
        c.restore();
        c.fillStyle = 'rgba(0,0,0,.18)';
        c.fillRect(links, basis - hoehe, breite, hoehe * 0.06);
    }

    function zeichneLandschaft(c) {
        const zz = zufallMitSaat(31);
        // Hügel in der Ferne (dunstig)
        [['#a9c9b0', h * 0.5, 0.004, 1.2], ['#8fb994', h * 0.53, 0.006, 2.8]].forEach(([ton, basis, frequenz, phase]) => {
            c.fillStyle = ton;
            c.beginPath();
            c.moveTo(0, h * 0.6);
            for (let x = 0; x <= b + 20; x += 20) c.lineTo(x, basis + Math.sin(x * frequenz + phase) * h * 0.02);
            c.lineTo(b, h * 0.6);
            c.fill();
        });
        // Parkbäume mit Stämmen und mehrlagigen Kronen
        const baeume = Array.from({length: Math.round(b / 30)}, () => ({
            x: zz.zwischen(-0.02, 1.02) * b, r: h * zz.zwischen(0.035, 0.065), pappel: zz() < 0.15,
            ton: zz.aus([['#4f8f3c', '#2f6627'], ['#5f9e47', '#3b7a2e'], ['#6aaa50', '#447f34']]),
        })).sort((a, d) => a.r - d.r);
        baeume.forEach((baum) => {
            const fuss = h * 0.575;
            c.fillStyle = '#5b4632';
            c.fillRect(baum.x - baum.r * 0.08, fuss - baum.r * 0.9, baum.r * 0.16, baum.r * 0.9);
            if (baum.pappel) {
                const v = c.createLinearGradient(baum.x - baum.r * 0.5, 0, baum.x + baum.r * 0.5, 0);
                v.addColorStop(0, baum.ton[0]);
                v.addColorStop(1, baum.ton[1]);
                c.fillStyle = v;
                ellipse(c, baum.x, fuss - baum.r * 1.6, baum.r * 0.45, baum.r * 1.3);
                c.fill();
                return;
            }
            [[-0.45, -0.75, 0.6], [0.45, -0.8, 0.62], [0, -1.15, 0.7], [-0.15, -0.95, 0.65]].forEach(([dx, dy, rr]) => {
                const v = c.createRadialGradient(baum.x + (dx - 0.2) * baum.r, fuss + (dy - 0.25) * baum.r, 0, baum.x + dx * baum.r, fuss + dy * baum.r, rr * baum.r);
                v.addColorStop(0, baum.ton[0]);
                v.addColorStop(1, baum.ton[1]);
                c.fillStyle = v;
                ellipse(c, baum.x + dx * baum.r, fuss + dy * baum.r, rr * baum.r, rr * baum.r * 0.9);
                c.fill();
            });
        });

        // Gehegeböden mit Struktur
        gehege.forEach((ge) => {
            const oben = h * 0.575;
            const unten = h * 0.845;
            const farben = {
                elefant: ['#d6bc8b', '#c4a06d'], giraffe: ['#c7cf85', '#a8b765'], affe: ['#a2c76d', '#7ea951'], pinguin: ['#e3e9ee', '#c3cfd9'],
            }[ge.art];
            c.fillStyle = verlauf(c, oben, unten, farben[0], farben[1]);
            c.fillRect(ge.x0, oben, W + 1, unten - oben);
            c.save();
            c.beginPath();
            c.rect(ge.x0, oben, W, unten - oben);
            c.clip();
            const anzahl = Math.round(W * 0.25);
            if (ge.art === 'elefant') {
                for (let i = 0; i < anzahl; i++) {
                    c.fillStyle = zz() < 0.5 ? 'rgba(150,110,60,.18)' : 'rgba(255,240,210,.25)';
                    ellipse(c, ge.x0 + zz() * W, oben + zz() * (unten - oben), zz.zwischen(4, 16), zz.zwischen(2, 5));
                    c.fill();
                }
                // Fußspuren
                c.strokeStyle = 'rgba(110,80,45,.3)';
                c.lineWidth = 1.5;
                for (let i = 0; i < 7; i++) {
                    const fx = ge.x0 + zz.zwischen(0.15, 0.9) * W;
                    const fy = zz.zwischen(h * 0.7, h * 0.83);
                    ellipse(c, fx, fy, W * 0.018, h * 0.006);
                    c.stroke();
                    ellipse(c, fx + W * 0.05, fy + h * 0.004, W * 0.018, h * 0.006);
                    c.stroke();
                }
            } else if (ge.art === 'pinguin') {
                for (let i = 0; i < anzahl * 0.4; i++) {
                    const sx = ge.x0 + zz() * W;
                    const sy = oben + h * 0.03 + zz() * (unten - oben - h * 0.03);
                    stein(c, sx, sy, zz.zwischen(4, 12), zz.zwischen(3, 7), '#f1f5f9', '#94a3b8');
                }
                c.fillStyle = 'rgba(255,255,255,.7)';
                for (let i = 0; i < 6; i++) {
                    ellipse(c, ge.x0 + zz() * W, oben + zz() * (unten - oben), zz.zwischen(10, 30), zz.zwischen(3, 7));
                    c.fill();
                }
            } else {
                const grasFarben = ge.art === 'giraffe' ? ['#b4b85a', '#8f9a45', '#d0c77a'] : ['#6a9e3f', '#4f8a32', '#8cbf5a'];
                for (let i = 0; i < anzahl * 1.6; i++) {
                    const gy = oben + h * 0.02 + zz() * (unten - oben - h * 0.02);
                    grasbueschel(c, ge.x0 + zz() * W, gy, h * mische(0.008, 0.02, (gy - oben) / (unten - oben)), grasFarben, zz);
                }
                if (ge.art === 'affe') {
                    c.fillStyle = 'rgba(110,80,50,.45)';
                    ellipse(c, ge.kletterbaum.x, ge.kletterbaum.fuss, W * 0.12, h * 0.018);
                    c.fill();
                    for (let i = 0; i < 12; i++) {
                        c.fillStyle = zz.aus(['#fde047', '#f9a8d4', '#ffffff']);
                        ellipse(c, ge.x0 + zz() * W, zz.zwischen(h * 0.68, h * 0.83), 2.5, 2.5);
                        c.fill();
                    }
                }
            }
            c.restore();

            // Natursteinmauer hinten
            const mauerHell = ge.art === 'pinguin' ? '#eef2f6' : '#b8afa6';
            const mauerDunkel = ge.art === 'pinguin' ? '#9fb0c0' : '#857a70';
            c.fillStyle = verlauf(c, h * 0.54, h * 0.6, mauerHell, mauerDunkel);
            c.beginPath();
            c.moveTo(ge.x0, h * 0.6);
            for (let x = ge.x0; x <= ge.x1 + 8; x += 8) c.lineTo(x, h * 0.565 - Math.abs(Math.sin(x * 0.07)) * h * 0.012 - zz() * h * 0.006);
            c.lineTo(ge.x1, h * 0.6);
            c.fill();
            c.strokeStyle = 'rgba(60,50,40,.25)';
            c.lineWidth = 1;
            for (let i = 0; i < 6; i++) {
                const rx = ge.x0 + zz() * W;
                linie(c, rx, h * 0.57, rx + zz.zwischen(-6, 6), h * 0.595);
            }
        });

        // Ausstattung der Gehege
        gehege.forEach((ge) => {
            if (ge.art === 'elefant') {
                zeichneStall(c, ge.haus);
                // Heuhaufen
                const hx = ge.x0 + W * 0.5;
                const hy = h * 0.655;
                c.fillStyle = verlauf(c, hy - h * 0.035, hy, '#e8c766', '#b98d2c');
                c.beginPath();
                c.ellipse(hx, hy, W * 0.075, h * 0.035, 0, Math.PI, Math.PI * 2);
                c.fill();
                c.strokeStyle = 'rgba(140,100,20,.6)';
                c.lineWidth = 1;
                for (let i = 0; i < 14; i++) {
                    const ax = hx + zz.zwischen(-0.06, 0.06) * W;
                    linie(c, ax, hy - zz() * h * 0.03, ax + zz.zwischen(-8, 8), hy - zz() * h * 0.03 - 4);
                }
                // Baumstamm
                const lx = ge.x0 + W * 0.14;
                const ly = h * 0.8;
                rundRechteck(c, lx, ly - h * 0.03, W * 0.2, h * 0.03, h * 0.015);
                c.fillStyle = verlauf(c, ly - h * 0.03, ly, '#8b6a47', '#5b4430');
                c.fill();
                c.fillStyle = '#c9a87a';
                ellipse(c, lx + W * 0.2, ly - h * 0.015, h * 0.012, h * 0.015);
                c.fill();
                c.strokeStyle = '#8b6a47';
                ellipse(c, lx + W * 0.2, ly - h * 0.015, h * 0.006, h * 0.008);
                c.stroke();
            } else if (ge.art === 'giraffe') {
                zeichneStall(c, ge.haus);
                // Akazie
                const tx = ge.baum.x;
                const fuss = h * 0.69;
                const top = h * 0.43;
                const stamm = c.createLinearGradient(tx - W * 0.02, 0, tx + W * 0.02, 0);
                stamm.addColorStop(0, '#5d4330');
                stamm.addColorStop(0.5, '#8a6a4c');
                stamm.addColorStop(1, '#5d4330');
                c.fillStyle = stamm;
                c.beginPath();
                c.moveTo(tx - W * 0.022, fuss);
                c.quadraticCurveTo(tx - W * 0.005, (fuss + top) / 2, tx - W * 0.008, top + h * 0.02);
                c.lineTo(tx + W * 0.008, top + h * 0.02);
                c.quadraticCurveTo(tx + W * 0.01, (fuss + top) / 2, tx + W * 0.022, fuss);
                c.fill();
                c.strokeStyle = '#6b4f37';
                c.lineCap = 'round';
                c.lineWidth = W * 0.01;
                linie(c, tx, h * 0.5, tx - W * 0.1, top + h * 0.01);
                linie(c, tx, h * 0.48, tx + W * 0.09, top);
                [[-0.08, 0, 0.16, 0.032], [0.07, -0.012, 0.15, 0.03], [-0.005, -0.038, 0.13, 0.026]].forEach(([dx, dy, rx, ry]) => {
                    const v = c.createLinearGradient(0, top + dy * h - ry * h, 0, top + dy * h + ry * h);
                    v.addColorStop(0, '#6fae48');
                    v.addColorStop(1, '#3f7a2a');
                    c.fillStyle = v;
                    ellipse(c, tx + dx * W, top + dy * h, rx * W, ry * h);
                    c.fill();
                    c.fillStyle = 'rgba(255,255,255,.12)';
                    for (let i = 0; i < 20; i++) {
                        ellipse(c, tx + dx * W + zz.zwischen(-0.85, 0.85) * rx * W, top + dy * h + zz.zwischen(-0.7, 0.3) * ry * h, 1.5, 1.5);
                        c.fill();
                    }
                });
                // Futterkorb auf Kopfhöhe
                const korb = ge.korb;
                c.fillStyle = verlauf(c, korb.y, korb.boden, '#8b6a47', '#5d4330');
                c.fillRect(korb.x - W * 0.009, korb.y, W * 0.018, korb.boden - korb.y);
                c.fillStyle = verlauf(c, korb.y - h * 0.022, korb.y + h * 0.012, '#c08a3e', '#7c5520');
                c.beginPath();
                c.moveTo(korb.x - W * 0.055, korb.y - h * 0.022);
                c.lineTo(korb.x + W * 0.055, korb.y - h * 0.022);
                c.lineTo(korb.x + W * 0.038, korb.y + h * 0.012);
                c.lineTo(korb.x - W * 0.038, korb.y + h * 0.012);
                c.fill();
                c.strokeStyle = 'rgba(70,40,15,.6)';
                c.lineWidth = 1;
                for (let i = -2; i <= 2; i++) linie(c, korb.x + i * W * 0.018, korb.y - h * 0.022, korb.x + i * W * 0.013, korb.y + h * 0.012);
            } else if (ge.art === 'affe') {
                const {x: tx, fuss, krone} = ge.kletterbaum;
                [[-0.13, -0.05, 0.11], [0.12, -0.07, 0.1], [0, -0.1, 0.12]].forEach(([dx, dy, r]) => {
                    const v = c.createRadialGradient(tx + (dx - 0.04) * W, krone + (dy - 0.03) * h, 0, tx + dx * W, krone + dy * h, r * W);
                    v.addColorStop(0, '#5f9e47');
                    v.addColorStop(1, '#2f6627');
                    c.fillStyle = v;
                    ellipse(c, tx + dx * W, krone + dy * h, r * W, r * W * 0.8);
                    c.fill();
                });
                const stamm = c.createLinearGradient(tx - W * 0.03, 0, tx + W * 0.03, 0);
                stamm.addColorStop(0, '#4a3322');
                stamm.addColorStop(0.5, '#7a5638');
                stamm.addColorStop(1, '#4a3322');
                c.fillStyle = stamm;
                c.fillRect(tx - W * 0.026, krone, W * 0.052, fuss - krone);
                c.strokeStyle = 'rgba(40,25,15,.4)';
                c.lineWidth = 1;
                for (let y = krone + 8; y < fuss; y += zz.zwischen(8, 16)) linie(c, tx - W * 0.02, y, tx + W * zz.zwischen(-0.01, 0.02), y + 4);
                c.strokeStyle = '#6b4a2f';
                c.lineCap = 'round';
                c.lineWidth = W * 0.022;
                linie(c, tx, h * 0.585, tx - W * 0.27, h * 0.56);
                linie(c, tx, h * 0.515, tx + W * 0.29, h * 0.49);
                // Baumhaus aus Brettern
                c.fillStyle = '#7a5638';
                c.fillRect(tx - W * 0.16, krone, W * 0.32, h * 0.013);
                c.fillStyle = verlauf(c, krone - h * 0.075, krone, '#b07a42', '#8a5a2c');
                c.fillRect(tx - W * 0.1, krone - h * 0.075, W * 0.2, h * 0.075);
                c.strokeStyle = 'rgba(60,35,15,.4)';
                for (let s = tx - W * 0.1 + W * 0.025; s < tx + W * 0.1; s += W * 0.025) linie(c, s, krone - h * 0.075, s, krone);
                c.fillStyle = verlauf(c, krone - h * 0.125, krone - h * 0.074, '#9a3412', '#6b2410');
                c.beginPath();
                c.moveTo(tx - W * 0.13, krone - h * 0.074);
                c.lineTo(tx, krone - h * 0.125);
                c.lineTo(tx + W * 0.13, krone - h * 0.074);
                c.fill();
                c.fillStyle = '#1c120b';
                c.beginPath();
                c.moveTo(tx - W * 0.03, krone);
                c.lineTo(tx - W * 0.03, krone - h * 0.035);
                c.arc(tx, krone - h * 0.035, W * 0.03, Math.PI, 0);
                c.lineTo(tx + W * 0.03, krone);
                c.fill();
            } else {
                // Felsenhöhle der Pinguine
                const {x, basis, breite, hoehe} = ge.hoehle;
                [[-0.38, -0.25, 0.22, 0.3], [0.36, -0.22, 0.2, 0.28], [-0.12, -0.75, 0.3, 0.32], [0.18, -0.7, 0.26, 0.3], [0.02, -0.95, 0.24, 0.2]].forEach(([dx, dy, rx, ry]) => {
                    stein(c, x + dx * breite, basis + dy * hoehe, rx * breite, ry * hoehe * 1.1, '#e2e8f0', '#64748b');
                });
                c.fillStyle = 'rgba(255,255,255,.85)';
                ellipse(c, x - breite * 0.05, basis - hoehe * 1.05, breite * 0.18, hoehe * 0.08);
                c.fill();
                const loch = c.createLinearGradient(0, basis - hoehe * 0.6, 0, basis);
                loch.addColorStop(0, '#0b1220');
                loch.addColorStop(1, '#1e293b');
                c.fillStyle = loch;
                c.beginPath();
                c.moveTo(x - breite * 0.17, basis);
                c.quadraticCurveTo(x - breite * 0.14, basis - hoehe * 0.7, x, basis - hoehe * 0.68);
                c.quadraticCurveTo(x + breite * 0.14, basis - hoehe * 0.7, x + breite * 0.17, basis);
                c.fill();
            }
        });

        // Trennmauern aus Naturstein
        for (let i = 1; i < gehege.length; i++) {
            const x = i * W;
            const mauer = new Path2D();
            mauer.moveTo(x - 4, h * 0.56);
            mauer.lineTo(x + 4, h * 0.56);
            mauer.lineTo(x + 10, h * 0.845);
            mauer.lineTo(x - 10, h * 0.845);
            mauer.closePath();
            const v = c.createLinearGradient(x - 10, 0, x + 10, 0);
            v.addColorStop(0, '#a8a29e');
            v.addColorStop(0.5, '#cfc8c1');
            v.addColorStop(1, '#8a817a');
            c.fillStyle = v;
            c.fill(mauer);
            c.save();
            c.clip(mauer);
            c.strokeStyle = 'rgba(60,55,50,.35)';
            c.lineWidth = 1;
            for (let y = h * 0.57; y < h * 0.845; y += h * 0.022) linie(c, x - 12, y, x + 12, y + 2);
            c.restore();
        }
    }

    function zeichneVorne(c) {
        const zz = zufallMitSaat(77);
        // Grünstreifen mit Blumen
        c.fillStyle = verlauf(c, h * 0.84, h * 0.88, '#7fb069', '#5e8f4b');
        c.fillRect(0, h * 0.84, b, h * 0.04);
        for (let i = 0; i < b / 6; i++) grasbueschel(c, zz() * b, h * zz.zwischen(0.855, 0.88), h * 0.014, ['#5e8f4b', '#7fb069', '#4a7a3a'], zz);
        for (let i = 0; i < b / 40; i++) {
            c.fillStyle = zz.aus(['#f43f5e', '#facc15', '#a855f7', '#ffffff']);
            ellipse(c, zz() * b, h * zz.zwischen(0.855, 0.875), 2.5, 2.5);
            c.fill();
        }

        // Holzzaun
        const pfosten = W / 8;
        for (let x = 0; x <= b + 1; x += pfosten) {
            const v = c.createLinearGradient(x - 4, 0, x + 4, 0);
            v.addColorStop(0, '#6b4226');
            v.addColorStop(0.5, '#a0693f');
            v.addColorStop(1, '#5b3720');
            c.fillStyle = v;
            c.beginPath();
            c.moveTo(x - 4, h * 0.865);
            c.lineTo(x - 4, h * 0.812);
            c.lineTo(x, h * 0.805);
            c.lineTo(x + 4, h * 0.812);
            c.lineTo(x + 4, h * 0.865);
            c.fill();
        }
        [h * 0.822, h * 0.846].forEach((y) => {
            c.fillStyle = verlauf(c, y, y + h * 0.009, '#a0693f', '#6b4226');
            c.fillRect(0, y, b, h * 0.009);
            c.fillStyle = 'rgba(0,0,0,.15)';
            c.fillRect(0, y + h * 0.009, b, 2);
        });

        // Schilder
        const schrift = klemme(W * 0.065, 11, 22);
        c.font = `700 ${schrift}px ui-sans-serif, system-ui, sans-serif`;
        c.textAlign = 'center';
        c.textBaseline = 'middle';
        gehege.forEach((ge) => {
            const bb = c.measureText(ge.name).width + schrift * 1.6;
            const bh = schrift * 1.8;
            const y = h * 0.842;
            c.fillStyle = 'rgba(0,0,0,.2)';
            rundRechteck(c, ge.mitte - bb / 2 + 3, y - bh / 2 + 3, bb, bh, 6);
            c.fill();
            c.fillStyle = verlauf(c, y - bh / 2, y + bh / 2, '#b07a48', '#7a4e2b');
            rundRechteck(c, ge.mitte - bb / 2, y - bh / 2, bb, bh, 6);
            c.fill();
            c.strokeStyle = 'rgba(60,35,15,.35)';
            c.lineWidth = 1;
            for (let i = 1; i < 4; i++) linie(c, ge.mitte - bb / 2 + 4, y - bh / 2 + (bh / 4) * i, ge.mitte + bb / 2 - 4, y - bh / 2 + (bh / 4) * i + 1);
            c.fillStyle = '#d6d3d1';
            [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sy]) => {
                ellipse(c, ge.mitte + sx * (bb / 2 - 6), y + sy * (bh / 2 - 6), 1.8, 1.8);
                c.fill();
            });
            c.fillStyle = 'rgba(0,0,0,.35)';
            c.fillText(ge.name, ge.mitte + 1, y + 2);
            c.fillStyle = '#fef3c7';
            c.fillText(ge.name, ge.mitte, y + 1);
        });

        // Besucherweg aus Pflastersteinen
        c.fillStyle = verlauf(c, h * 0.878, h, '#ddcfae', '#c6b48c');
        c.fillRect(0, h * 0.878, b, h * 0.122);
        c.fillStyle = '#a8a29e';
        c.fillRect(0, h * 0.876, b, h * 0.006);
        let y = h * 0.884;
        let reihe = 0;
        while (y < h) {
            const hoehe = h * (0.012 + (y - h * 0.88) / h * 0.06);
            const breite = hoehe * 2.2;
            for (let x = -((reihe % 2) * breite) / 2; x < b; x += breite) {
                c.fillStyle = zz.aus(['#e2d5b6', '#d6c7a4', '#cbbb96', '#ddd0af']);
                rundRechteck(c, x + 1.5, y + 1, breite - 3, hoehe - 2, 3);
                c.fill();
            }
            y += hoehe;
            reihe++;
        }
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
        const gi = index ?? fuetterNaechstes;
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
        let verstecken = z.unruhe > tier.mut;
        if (tier.baby) verstecken = verstecken || !tier.freigeschaltet || tier.eltern.flieht;
        const ruhigGenug = z.unruhe < tier.mut - 0.1 && (!tier.baby || (tier.freigeschaltet && !tier.eltern.flieht));
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
            ziel = ge.korb
                ? {x: ge.korb.x + (idx % 2 ? 1 : -1) * tier.g * (tier.baby ? 0.9 : 0.5), y: ge.korb.boden + (idx % 2 ? 3 : -3)}
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
        const anzahlBabys = tiere.filter((x) => x.baby).length;
        if (z.unruhe < 0.15) babyStufe += dt / SEKUNDEN_PRO_BABY;
        else if (z.unruhe > 0.6) babyStufe -= (dt / 5) * z.unruhe;
        babyStufe = klemme(babyStufe, 0, anzahlBabys);
        tiere.forEach((x) => {
            if (x.baby) x.freigeschaltet = babyStufe >= x.nummer + 1;
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
                v.addColorStop(0, 'rgba(255,255,255,.98)');
                v.addColorStop(1, 'rgba(214,226,236,.95)');
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
        ctx.fillStyle = pinguin ? '#cbd5e1' : 'rgba(120,90,55,.55)';
        ellipse(ctx, w.x, w.y + w.ry * 0.1, w.rx * 1.07, w.ry * 1.35);
        ctx.fill();
        const v = ctx.createLinearGradient(0, w.y - w.ry, 0, w.y + w.ry);
        v.addColorStop(0, pinguin ? '#7dd3fc' : '#93c5d8');
        v.addColorStop(1, pinguin ? '#0369a1' : '#3b7a8f');
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
                stein(ctx, w.x + fx * w.rx, w.y + fy * w.ry, W * 0.035, h * 0.012, '#f1f5f9', '#94a3b8');
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
        ctx.strokeStyle = '#1f2937';
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

        if (unruhe > 0.01) {
            ctx.fillStyle = `rgba(30,41,59,${0.16 * unruhe})`;
            ctx.fillRect(0, 0, breite, hoehe);
        }
    }

    function info() {
        if (fuetterung && !fuetterung.eilig) return `Fütterung: ${gehege[fuetterung.gi].name}`;
        const babys = tiere.filter((x) => x.baby);
        const draussen = babys.filter((x) => !x.flieht && x.alpha > 0.5).length;
        return `Tierbabys: ${draussen} von ${babys.length}`;
    }

    function besuch(name) {
        if (name === 'babys') babyStufe = tiere.filter((x) => x.baby).length;
        else if (name === 'fuetterung') {
            babyStufe = Math.max(babyStufe, 2);
            starteFuetterung();
        } else return false;
        return true;
    }

    return {
        mengeLabel: 'Anzahl der Tiere',
        texte: {
            ruhig: 'Psst – die Tierbabys trauen sich heraus.',
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
