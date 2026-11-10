/*
 * Klassenraum-Tools – die Klassentiere neben der Klassenmaus (shared/maus.js): Fuchs, Faultier,
 * Lemur und Eule. Welches Tier in den Lautstärke-Szenen auftaucht, stellt jede Klasse selbst ein
 * (Einstellung „klassentier“, siehe KLASSENTIERE in shared/maus.js).
 *
 * Alle Tiere haben dieselbe Schnittstelle wie zeichneMaus(): von vorn, Fußpunkt im Ursprung,
 * Größeneinheit s (≈ Kopfbreite), Gesamthöhe ≈ 1,95 s; Optionen t, blick, blinzeln, winken.
 * Jedes Tier hat ein eigenes Erkennungszeichen (Fuchs: grüner Strickschal, Faultier: Blüte am Ohr,
 * Lemur: Ringelschwanz, Eule: runde Brille). Die Verkleidungen der Szenen (Schlafmütze,
 * Forscherhut, Taucherhelm) setzt zeichneVerkleidung() passend zur Kopfform auf.
 * Eigene Figuren – keine fremden Maskottchen nachzeichnen.
 */

import {ellipse} from './hilfen';

const RAND = 'rgba(48,34,30,.8)';
const AUGE = '#241a17';
const SCHAL = '#2f8f6b';
const SCHAL_STREIFEN = '#f3e7c8';


function werkzeug(ctx) {
    return (breite = 0.035) => {
        ctx.strokeStyle = RAND;
        ctx.lineWidth = breite;
        ctx.stroke();
    };
}

function augenpaar(ctx, rand, {x = 0.18, y = -1.3, rx = 0.11, ry = 0.13, pupille = 0.07, iris = null, blick = [0, 0], blinzeln = false, schraeg = 0, lid = AUGE}) {
    [-1, 1].forEach((seite) => {
        const ax = seite * x;
        if (blinzeln) {
            ctx.beginPath();
            ctx.moveTo(ax - rx * 0.9, y);
            ctx.quadraticCurveTo(ax, y + 0.06, ax + rx * 0.9, y);
            ctx.strokeStyle = lid;
            ctx.lineWidth = 0.035;
            ctx.stroke();
            return;
        }
        ctx.fillStyle = iris || '#ffffff';
        ellipse(ctx, ax, y, rx, ry, seite * schraeg);
        ctx.fill();
        rand(0.025);
        ctx.fillStyle = AUGE;
        ellipse(ctx, ax + blick[0] * rx * 0.35, y + 0.012 + blick[1] * ry * 0.3, pupille, pupille * 1.12);
        ctx.fill();
        ctx.fillStyle = '#ffffff';
        ellipse(ctx, ax + blick[0] * rx * 0.35 + pupille * 0.35, y - pupille * 0.25 + blick[1] * ry * 0.3, pupille * 0.34, pupille * 0.34);
        ctx.fill();
    });
}

function winkArm(ctx, rand, {t, winken, farbeArm, farbeHand, ansatz = [0.28, -0.72], dicke = 0.12}) {
    const hand = [ansatz[0] + 0.06 + winken * 0.3, ansatz[1] + 0.02 - winken * 0.42 + Math.sin(t * 9) * 0.05 * winken];
    ctx.beginPath();
    ctx.moveTo(ansatz[0], ansatz[1]);
    ctx.lineTo(hand[0], hand[1]);
    ctx.strokeStyle = RAND;
    ctx.lineWidth = dicke + 0.05;
    ctx.stroke();
    ctx.strokeStyle = farbeArm;
    ctx.lineWidth = dicke;
    ctx.stroke();
    ctx.fillStyle = farbeHand;
    ellipse(ctx, hand[0], hand[1], 0.09, 0.09);
    ctx.fill();
    rand(0.025);
    return hand;
}

const FUCHS = {fell: '#e2782c', fellHell: '#f0964a', bauch: '#fbf0de', dunkel: '#3b2620', ohrInnen: '#f6d9b8', spitze: '#2e211c', schwanzSpitze: '#fbf3e6'};

// ── Fuchs (Rotfuchs) ───────────────────────────────────────────────────
export function zeichneFuchs(ctx, s, {t = 0, blick = [0, 0], blinzeln = false, winken = 0} = {}) {
    const f = FUCHS;
    const schal = true;
    ctx.save();
    ctx.scale(s, s);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const rand = (breite = 0.035) => {
        ctx.strokeStyle = RAND;
        ctx.lineWidth = breite;
        ctx.stroke();
    };

    // Buschiger Schwanz (hinter dem Körper), wedelt leicht
    ctx.save();
    ctx.translate(0.22, -0.28);
    ctx.rotate(Math.sin(t * 1.8) * 0.07);
    ctx.translate(-0.22, 0.28);
    const schwanz = new Path2D();
    schwanz.moveTo(0.18, -0.16);
    schwanz.bezierCurveTo(0.62, -0.06, 1.02, -0.3, 1.02, -0.72);
    schwanz.bezierCurveTo(1.02, -1.0, 0.84, -1.12, 0.7, -1.04);
    schwanz.bezierCurveTo(0.8, -0.86, 0.74, -0.56, 0.44, -0.46);
    schwanz.bezierCurveTo(0.32, -0.42, 0.24, -0.36, 0.2, -0.3);
    schwanz.closePath();
    ctx.fillStyle = f.fell;
    ctx.fill(schwanz);
    ctx.save();
    ctx.clip(schwanz);
    ctx.fillStyle = f.schwanzSpitze;
    ellipse(ctx, 0.86, -1.0, 0.24, 0.2, 0.4);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.18)';
    ellipse(ctx, 0.7, -0.45, 0.25, 0.08, -0.6);
    ctx.fill();
    ctx.restore();
    ctx.strokeStyle = RAND;
    ctx.lineWidth = 0.035;
    ctx.stroke(schwanz);
    ctx.restore();

    // Pfoten (dunkle „Socken“)
    [-1, 1].forEach((seite) => {
        ctx.fillStyle = f.dunkel;
        ellipse(ctx, seite * 0.19, -0.06, 0.16, 0.085);
        ctx.fill();
        rand(0.03);
    });

    // Körper und heller Brustlatz
    ctx.fillStyle = f.fell;
    ellipse(ctx, 0, -0.48, 0.38, 0.46);
    ctx.fill();
    rand();
    ctx.fillStyle = f.bauch;
    ctx.beginPath();
    ctx.moveTo(-0.22, -0.84);
    ctx.quadraticCurveTo(0, -0.74, 0.22, -0.84);
    ctx.quadraticCurveTo(0.2, -0.42, 0, -0.14);
    ctx.quadraticCurveTo(-0.2, -0.42, -0.22, -0.84);
    ctx.fill();

    // Arme: links an der Brust, rechts winkend
    ctx.fillStyle = f.dunkel;
    ellipse(ctx, -0.29, -0.58, 0.085, 0.13, 0.2);
    ctx.fill();
    rand(0.025);
    if (winken > 0.01) {
        const hand = [0.34 + winken * 0.3, -0.7 - winken * 0.42 + Math.sin(t * 9) * 0.05 * winken];
        ctx.beginPath();
        ctx.moveTo(0.28, -0.72);
        ctx.lineTo(hand[0], hand[1]);
        ctx.strokeStyle = RAND;
        ctx.lineWidth = 0.17;
        ctx.stroke();
        ctx.strokeStyle = f.fell;
        ctx.lineWidth = 0.12;
        ctx.stroke();
        ctx.fillStyle = f.dunkel;
        ellipse(ctx, hand[0], hand[1], 0.09, 0.09);
        ctx.fill();
        rand(0.025);
    } else {
        ctx.fillStyle = f.dunkel;
        ellipse(ctx, 0.29, -0.58, 0.085, 0.13, -0.2);
        ctx.fill();
        rand(0.025);
    }

    // Schal (Erkennungszeichen des Fuchses): grün gestrickt mit hellen Streifen und Fransen
    if (schal) {
        const ende = Math.sin(t * 1.5) * 0.02;
        ctx.fillStyle = SCHAL;
        ctx.beginPath();
        ctx.moveTo(-0.24, -0.86);
        ctx.lineTo(-0.3 + ende, -0.5);
        ctx.lineTo(-0.14 + ende, -0.5);
        ctx.lineTo(-0.1, -0.84);
        ctx.closePath();
        ctx.fill();
        rand(0.025);
        ctx.fillStyle = SCHAL_STREIFEN;
        ctx.fillRect(-0.285 + ende, -0.66, 0.155, 0.04);
        ctx.strokeStyle = SCHAL;
        ctx.lineWidth = 0.025;
        for (let i = 0; i < 4; i++) {
            ctx.beginPath();
            ctx.moveTo(-0.29 + ende + i * 0.045, -0.5);
            ctx.lineTo(-0.3 + ende + i * 0.045, -0.43);
            ctx.stroke();
        }
        ctx.fillStyle = SCHAL;
        ellipse(ctx, 0, -0.86, 0.36, 0.1);
        ctx.fill();
        rand(0.03);
        ctx.strokeStyle = SCHAL_STREIFEN;
        ctx.lineWidth = 0.035;
        [-0.2, 0, 0.2].forEach((x) => {
            ctx.beginPath();
            ctx.moveTo(x - 0.02, -0.94);
            ctx.lineTo(x + 0.02, -0.78);
            ctx.stroke();
        });
    }

    // Ohren: groß, spitz, dunkle Spitzen
    [-1, 1].forEach((seite) => {
        const ohr = new Path2D();
        ohr.moveTo(seite * 0.44, -1.34);
        ohr.quadraticCurveTo(seite * 0.56, -1.7, seite * 0.5, -1.96);
        ohr.quadraticCurveTo(seite * 0.28, -1.78, seite * 0.1, -1.54);
        ohr.closePath();
        ctx.fillStyle = f.fell;
        ctx.fill(ohr);
        ctx.save();
        ctx.clip(ohr);
        ctx.fillStyle = f.spitze;
        ctx.fillRect(seite > 0 ? 0.2 : -0.8, -2.1, 0.6, 0.3);
        ctx.restore();
        ctx.strokeStyle = RAND;
        ctx.lineWidth = 0.035;
        ctx.stroke(ohr);
        ctx.fillStyle = f.ohrInnen;
        ctx.beginPath();
        ctx.moveTo(seite * 0.4, -1.4);
        ctx.quadraticCurveTo(seite * 0.47, -1.64, seite * 0.45, -1.78);
        ctx.quadraticCurveTo(seite * 0.3, -1.66, seite * 0.18, -1.52);
        ctx.closePath();
        ctx.fill();
    });

    // Kopf: runde Stirn, seitliche Wangenbüschel, schmale Schnauze
    const kopf = new Path2D();
    kopf.moveTo(0, -1.64);
    kopf.bezierCurveTo(0.32, -1.64, 0.5, -1.47, 0.52, -1.24);
    kopf.lineTo(0.62, -1.1);
    kopf.quadraticCurveTo(0.44, -1.09, 0.38, -1.05);
    kopf.quadraticCurveTo(0.2, -0.9, 0, -0.88);
    kopf.quadraticCurveTo(-0.2, -0.9, -0.38, -1.05);
    kopf.quadraticCurveTo(-0.44, -1.09, -0.62, -1.1);
    kopf.lineTo(-0.52, -1.24);
    kopf.bezierCurveTo(-0.5, -1.47, -0.32, -1.64, 0, -1.64);
    kopf.closePath();
    ctx.fillStyle = f.fell;
    ctx.fill(kopf);
    ctx.save();
    ctx.clip(kopf);
    ctx.fillStyle = f.fellHell;
    ellipse(ctx, -0.1, -1.52, 0.22, 0.08, -0.2);
    ctx.fill();
    // weiße Wangen und Schnauze
    ctx.fillStyle = f.bauch;
    ctx.beginPath();
    ctx.moveTo(-0.66, -1.12);
    ctx.quadraticCurveTo(-0.42, -1.27, -0.15, -1.2);
    ctx.quadraticCurveTo(0, -1.3, 0.15, -1.2);
    ctx.quadraticCurveTo(0.42, -1.27, 0.66, -1.12);
    ctx.lineTo(0.66, -0.8);
    ctx.lineTo(-0.66, -0.8);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
    ctx.strokeStyle = RAND;
    ctx.lineWidth = 0.035;
    ctx.stroke(kopf);
    ctx.fillStyle = 'rgba(240,130,110,.35)';
    [-1, 1].forEach((seite) => {
        ellipse(ctx, seite * 0.33, -1.14, 0.08, 0.045);
        ctx.fill();
    });

    // Augen (leicht mandelförmig)
    [-1, 1].forEach((seite) => {
        const ax = seite * 0.19;
        const ay = -1.33;
        if (blinzeln) {
            ctx.beginPath();
            ctx.moveTo(ax - 0.1, ay);
            ctx.quadraticCurveTo(ax, ay + 0.06, ax + 0.1, ay);
            ctx.strokeStyle = AUGE;
            ctx.lineWidth = 0.035;
            ctx.stroke();
            return;
        }
        ctx.fillStyle = '#ffffff';
        ellipse(ctx, ax, ay, 0.11, 0.13, seite * -0.25);
        ctx.fill();
        rand(0.025);
        ctx.fillStyle = AUGE;
        ellipse(ctx, ax + blick[0] * 0.035, ay + 0.015 + blick[1] * 0.035, 0.068, 0.08);
        ctx.fill();
        ctx.fillStyle = '#ffffff';
        ellipse(ctx, ax + blick[0] * 0.035 + 0.022, ay - 0.015 + blick[1] * 0.035, 0.024, 0.024);
        ctx.fill();
    });

    // Nase und Mund
    ctx.fillStyle = AUGE;
    ellipse(ctx, 0, -1.03, 0.075, 0.055);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.6)';
    ellipse(ctx, -0.022, -1.046, 0.022, 0.014);
    ctx.fill();
    ctx.strokeStyle = AUGE;
    ctx.lineWidth = 0.028;
    ctx.beginPath();
    ctx.moveTo(0, -0.98);
    ctx.lineTo(0, -0.95);
    ctx.moveTo(-0.08, -0.95);
    ctx.quadraticCurveTo(-0.04, -0.91, 0, -0.95);
    ctx.quadraticCurveTo(0.04, -0.91, 0.08, -0.95);
    ctx.stroke();
    ctx.restore();
}

// ── Faultier ───────────────────────────────────────────────────────────
export function zeichneFaultier(ctx, s, {t = 0, blick = [0, 0], blinzeln = false, winken = 0} = {}) {
    const FELL = '#9c8467';
    const FELL_DUNKEL = '#76624b';
    const BAUCH = '#bba585';
    const GESICHT = '#f0e3c9';
    const MASKE = '#4b3729';
    const KRALLE = '#efe6d4';
    ctx.save();
    ctx.scale(s, s);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const rand = werkzeug(ctx);

    // Füße mit Krallen
    [-1, 1].forEach((seite) => {
        ctx.fillStyle = FELL_DUNKEL;
        ellipse(ctx, seite * 0.2, -0.07, 0.16, 0.09);
        ctx.fill();
        rand(0.03);
        ctx.strokeStyle = KRALLE;
        ctx.lineWidth = 0.025;
        [-0.06, 0, 0.06].forEach((dx) => {
            ctx.beginPath();
            ctx.moveTo(seite * 0.2 + dx, -0.03);
            ctx.lineTo(seite * 0.2 + dx * 1.3, 0.02);
            ctx.stroke();
        });
    });

    // Körper, zottelig
    ctx.fillStyle = FELL;
    ellipse(ctx, 0, -0.5, 0.41, 0.48);
    ctx.fill();
    rand();
    ctx.fillStyle = BAUCH;
    ellipse(ctx, 0, -0.44, 0.25, 0.32);
    ctx.fill();
    ctx.strokeStyle = 'rgba(80,60,40,.35)';
    ctx.lineWidth = 0.02;
    [[-0.08, -0.55], [0.06, -0.42], [-0.04, -0.3], [0.1, -0.62]].forEach(([x, y]) => {
        ctx.beginPath();
        ctx.moveTo(x - 0.04, y);
        ctx.quadraticCurveTo(x, y + 0.04, x + 0.04, y);
        ctx.stroke();
    });

    // Lange Arme mit Krallen, die den Bauch umarmen; rechts ggf. winkend
    const arm = (seite) => {
        ctx.fillStyle = FELL;
        ellipse(ctx, seite * 0.27, -0.56, 0.11, 0.24, seite * -0.5);
        ctx.fill();
        rand(0.03);
        ctx.strokeStyle = KRALLE;
        ctx.lineWidth = 0.03;
        [-0.04, 0.0, 0.04].forEach((dy) => {
            ctx.beginPath();
            ctx.moveTo(seite * 0.13, -0.4 + dy);
            ctx.lineTo(seite * 0.05, -0.38 + dy * 1.4);
            ctx.stroke();
        });
    };
    arm(-1);
    if (winken > 0.01) {
        const hand = winkArm(ctx, rand, {t, winken, farbeArm: FELL, farbeHand: FELL_DUNKEL, ansatz: [0.3, -0.7], dicke: 0.14});
        ctx.strokeStyle = KRALLE;
        ctx.lineWidth = 0.028;
        [-0.05, 0, 0.05].forEach((dx) => {
            ctx.beginPath();
            ctx.moveTo(hand[0] + dx, hand[1] - 0.06);
            ctx.lineTo(hand[0] + dx * 1.3, hand[1] - 0.15);
            ctx.stroke();
        });
    } else {
        arm(1);
    }

    // Kopf mit Zotteln
    ctx.fillStyle = FELL;
    ctx.beginPath();
    ctx.moveTo(-0.5, -1.2);
    ctx.bezierCurveTo(-0.52, -1.5, -0.3, -1.68, 0, -1.68);
    ctx.bezierCurveTo(0.3, -1.68, 0.52, -1.5, 0.5, -1.2);
    ctx.bezierCurveTo(0.5, -0.98, 0.3, -0.86, 0, -0.86);
    ctx.bezierCurveTo(-0.3, -0.86, -0.5, -0.98, -0.5, -1.2);
    ctx.closePath();
    ctx.fill();
    rand();
    ctx.fillStyle = FELL;
    [[-0.18, -1.66, -0.4], [0.02, -1.71, 0], [0.2, -1.65, 0.4]].forEach(([x, y, w]) => {
        ctx.beginPath();
        ctx.moveTo(x - 0.08, y + 0.05);
        ctx.quadraticCurveTo(x + w * 0.1, y - 0.1, x + 0.08 + w * 0.05, y + 0.04);
        ctx.fill();
        rand(0.025);
    });
    // helles Gesicht
    ctx.fillStyle = GESICHT;
    ellipse(ctx, 0, -1.2, 0.4, 0.31);
    ctx.fill();
    // dunkle Augenstreifen (typisch Faultier)
    ctx.fillStyle = MASKE;
    [-1, 1].forEach((seite) => {
        ellipse(ctx, seite * 0.2, -1.24, 0.15, 0.075, seite * 0.35);
        ctx.fill();
    });
    augenpaar(ctx, rand, {x: 0.19, y: -1.25, rx: 0.075, ry: 0.08, pupille: 0.05, blick, blinzeln});
    ctx.fillStyle = 'rgba(240,130,110,.35)';
    [-1, 1].forEach((seite) => {
        ellipse(ctx, seite * 0.3, -1.1, 0.07, 0.04);
        ctx.fill();
    });
    // Nase und gemütliches Lächeln
    ctx.fillStyle = MASKE;
    ellipse(ctx, 0, -1.12, 0.09, 0.06);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.5)';
    ellipse(ctx, -0.025, -1.135, 0.025, 0.015);
    ctx.fill();
    ctx.strokeStyle = MASKE;
    ctx.lineWidth = 0.03;
    ctx.beginPath();
    ctx.moveTo(-0.13, -1.03);
    ctx.quadraticCurveTo(0, -0.95, 0.13, -1.03);
    ctx.stroke();

    // Erkennungszeichen: Blüte hinter dem Ohr
    ctx.save();
    ctx.translate(0.36, -1.52);
    ctx.rotate(Math.sin(t * 1.2) * 0.08);
    ctx.fillStyle = '#f472b6';
    for (let i = 0; i < 5; i++) {
        const w = (i / 5) * Math.PI * 2;
        ellipse(ctx, Math.cos(w) * 0.09, Math.sin(w) * 0.09, 0.08, 0.06, w);
        ctx.fill();
        rand(0.02);
    }
    ctx.fillStyle = '#fde047';
    ellipse(ctx, 0, 0, 0.045, 0.045);
    ctx.fill();
    rand(0.02);
    ctx.restore();
    ctx.restore();
}

// ── Lemur (Katta) ──────────────────────────────────────────────────────
export function zeichneLemur(ctx, s, {t = 0, blick = [0, 0], blinzeln = false, winken = 0} = {}) {
    const FELL = '#aeaaa6';
    const FELL_DUNKEL = '#5b5754';
    const BAUCH = '#f1ede6';
    const SCHWARZ = '#2a2624';
    ctx.save();
    ctx.scale(s, s);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const rand = werkzeug(ctx);

    // Langer, schwarz-weiß geringelter Schwanz, nach oben geschwungen
    const wedel = Math.sin(t * 1.6) * 0.05;
    const schwanz = new Path2D();
    schwanz.moveTo(0.18, -0.22);
    schwanz.bezierCurveTo(0.7, -0.2, 0.86, -0.6, 0.72, -1.0);
    schwanz.bezierCurveTo(0.6, -1.36, 0.78 + wedel, -1.7, 0.98 + wedel, -1.66);
    ctx.strokeStyle = RAND;
    ctx.lineWidth = 0.2;
    ctx.stroke(schwanz);
    ctx.strokeStyle = '#f5f2ec';
    ctx.lineWidth = 0.15;
    ctx.stroke(schwanz);
    ctx.setLineDash([0.11, 0.11]);
    ctx.lineCap = 'butt';
    ctx.strokeStyle = SCHWARZ;
    ctx.stroke(schwanz);
    ctx.setLineDash([]);
    ctx.lineCap = 'round';

    // Füße
    [-1, 1].forEach((seite) => {
        ctx.fillStyle = FELL_DUNKEL;
        ellipse(ctx, seite * 0.18, -0.06, 0.15, 0.08);
        ctx.fill();
        rand(0.03);
    });

    // Körper
    ctx.fillStyle = FELL;
    ellipse(ctx, 0, -0.5, 0.36, 0.46);
    ctx.fill();
    rand();
    ctx.fillStyle = BAUCH;
    ellipse(ctx, 0, -0.44, 0.23, 0.33);
    ctx.fill();

    // Arme
    ctx.fillStyle = FELL;
    ellipse(ctx, -0.29, -0.56, 0.085, 0.17, 0.25);
    ctx.fill();
    rand(0.025);
    ctx.fillStyle = FELL_DUNKEL;
    ellipse(ctx, -0.25, -0.42, 0.07, 0.06);
    ctx.fill();
    if (winken > 0.01) {
        winkArm(ctx, rand, {t, winken, farbeArm: FELL, farbeHand: FELL_DUNKEL, ansatz: [0.27, -0.72], dicke: 0.11});
    } else {
        ctx.fillStyle = FELL;
        ellipse(ctx, 0.29, -0.56, 0.085, 0.17, -0.25);
        ctx.fill();
        rand(0.025);
        ctx.fillStyle = FELL_DUNKEL;
        ellipse(ctx, 0.25, -0.42, 0.07, 0.06);
        ctx.fill();
    }

    // Ohren: seitlich, spitz-rund, mit hellem Rand
    [-1, 1].forEach((seite) => {
        ctx.fillStyle = BAUCH;
        ellipse(ctx, seite * 0.46, -1.46, 0.17, 0.12, seite * -0.6);
        ctx.fill();
        rand();
        ctx.fillStyle = FELL_DUNKEL;
        ellipse(ctx, seite * 0.44, -1.45, 0.1, 0.06, seite * -0.6);
        ctx.fill();
    });

    // Kopf: weißes Gesicht, graue Kappe, schwarze Schnauze
    const kopf = new Path2D();
    kopf.moveTo(0, -1.66);
    kopf.bezierCurveTo(0.34, -1.66, 0.48, -1.46, 0.46, -1.24);
    kopf.bezierCurveTo(0.44, -1.06, 0.2, -0.98, 0.12, -0.92);
    kopf.quadraticCurveTo(0, -0.86, -0.12, -0.92);
    kopf.bezierCurveTo(-0.2, -0.98, -0.44, -1.06, -0.46, -1.24);
    kopf.bezierCurveTo(-0.48, -1.46, -0.34, -1.66, 0, -1.66);
    kopf.closePath();
    ctx.fillStyle = '#fbf9f5';
    ctx.fill(kopf);
    ctx.save();
    ctx.clip(kopf);
    ctx.fillStyle = FELL_DUNKEL;
    ellipse(ctx, 0, -1.74, 0.36, 0.22);
    ctx.fill();
    ctx.fillStyle = SCHWARZ;
    [-1, 1].forEach((seite) => {
        ellipse(ctx, seite * 0.18, -1.3, 0.15, 0.16, seite * 0.5);
        ctx.fill();
    });
    ctx.beginPath();
    ctx.moveTo(-0.07, -1.24);
    ctx.lineTo(0.07, -1.24);
    ctx.lineTo(0.15, -0.9);
    ctx.lineTo(-0.15, -0.9);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
    ctx.strokeStyle = RAND;
    ctx.lineWidth = 0.035;
    ctx.stroke(kopf);
    // große bernsteinfarbene Augen
    augenpaar(ctx, rand, {x: 0.18, y: -1.3, rx: 0.1, ry: 0.105, pupille: 0.055, iris: '#f4a53a', blick, blinzeln, lid: '#e2ddd4'});
    // Nase
    ctx.fillStyle = '#111';
    ellipse(ctx, 0, -0.97, 0.065, 0.045);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.5)';
    ellipse(ctx, -0.02, -0.985, 0.02, 0.012);
    ctx.fill();
    ctx.restore();
}

// ── Eule ───────────────────────────────────────────────────────────────
export function zeichneEule(ctx, s, {t = 0, blick = [0, 0], blinzeln = false, winken = 0} = {}) {
    const GEFIEDER = '#8b6a4a';
    const GEFIEDER_DUNKEL = '#664c33';
    const BAUCH = '#ead8b9';
    const SCHLEIER = '#f3e7d0';
    const GELB = '#e9a23b';
    ctx.save();
    ctx.scale(s, s);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const rand = werkzeug(ctx);

    // Krallen
    ctx.strokeStyle = GELB;
    ctx.lineWidth = 0.045;
    [-1, 1].forEach((seite) => {
        [-0.06, 0, 0.06].forEach((dx) => {
            ctx.beginPath();
            ctx.moveTo(seite * 0.16, -0.1);
            ctx.lineTo(seite * 0.16 + dx * 1.4, 0);
            ctx.stroke();
        });
    });

    // Körper (Ei-Form) mit Brustfedern
    ctx.fillStyle = GEFIEDER;
    ellipse(ctx, 0, -0.62, 0.46, 0.58);
    ctx.fill();
    rand();
    ctx.fillStyle = BAUCH;
    ellipse(ctx, 0, -0.5, 0.3, 0.4);
    ctx.fill();
    ctx.strokeStyle = 'rgba(102,76,51,.6)';
    ctx.lineWidth = 0.022;
    [[-0.12, -0.7], [0.12, -0.7], [0, -0.58], [-0.14, -0.46], [0.14, -0.46], [0, -0.34], [-0.1, -0.22], [0.1, -0.22]].forEach(([x, y]) => {
        ctx.beginPath();
        ctx.moveTo(x - 0.05, y - 0.02);
        ctx.quadraticCurveTo(x, y + 0.04, x + 0.05, y - 0.02);
        ctx.stroke();
    });

    // Flügel (rechts winkt)
    const fluegel = (seite, hoch) => {
        ctx.save();
        ctx.translate(seite * 0.38, -0.86);
        ctx.rotate(seite * (0.15 + hoch * 1.6) + (hoch ? Math.sin(t * 9) * 0.15 * hoch : 0) * seite);
        ctx.fillStyle = GEFIEDER_DUNKEL;
        ellipse(ctx, 0, 0.26, 0.13, 0.3);
        ctx.fill();
        rand(0.03);
        ctx.strokeStyle = 'rgba(234,216,185,.45)';
        ctx.lineWidth = 0.02;
        [0.2, 0.32, 0.44].forEach((y) => {
            ctx.beginPath();
            ctx.moveTo(-0.07, y);
            ctx.lineTo(0.07, y + 0.04);
            ctx.stroke();
        });
        ctx.restore();
    };
    fluegel(-1, 0);
    fluegel(1, winken > 0.01 ? winken : 0);

    // Kopf mit Federohren
    ctx.fillStyle = GEFIEDER;
    [-1, 1].forEach((seite) => {
        ctx.beginPath();
        ctx.moveTo(seite * 0.2, -1.52);
        ctx.quadraticCurveTo(seite * 0.42, -1.72, seite * 0.48, -1.9);
        ctx.quadraticCurveTo(seite * 0.5, -1.6, seite * 0.44, -1.4);
        ctx.closePath();
        ctx.fill();
        rand(0.03);
    });
    ellipse(ctx, 0, -1.22, 0.5, 0.4);
    ctx.fill();
    rand();
    // Gesichtsschleier
    ctx.fillStyle = SCHLEIER;
    [-1, 1].forEach((seite) => {
        ellipse(ctx, seite * 0.2, -1.24, 0.22, 0.23);
        ctx.fill();
    });
    ctx.strokeStyle = 'rgba(139,106,74,.55)';
    ctx.lineWidth = 0.025;
    [-1, 1].forEach((seite) => {
        ctx.beginPath();
        ctx.arc(seite * 0.2, -1.24, 0.22, 0, Math.PI * 2);
        ctx.stroke();
    });
    // große Augen
    augenpaar(ctx, rand, {x: 0.2, y: -1.25, rx: 0.13, ry: 0.13, pupille: 0.075, iris: '#f7c548', blick, blinzeln});
    // Erkennungszeichen: runde Brille
    ctx.strokeStyle = '#6b4f2a';
    ctx.lineWidth = 0.03;
    [-1, 1].forEach((seite) => {
        ctx.beginPath();
        ctx.arc(seite * 0.2, -1.25, 0.165, 0, Math.PI * 2);
        ctx.stroke();
    });
    ctx.beginPath();
    ctx.moveTo(-0.04, -1.29);
    ctx.quadraticCurveTo(0, -1.33, 0.04, -1.29);
    ctx.stroke();
    // Schnabel
    ctx.fillStyle = GELB;
    ctx.beginPath();
    ctx.moveTo(-0.06, -1.13);
    ctx.lineTo(0.06, -1.13);
    ctx.lineTo(0, -1.0);
    ctx.closePath();
    ctx.fill();
    rand(0.025);
    ctx.restore();
}


// ── Verkleidungen der Szenen ──────────────────────────────────────────
// Kopf je Tier: oben = Scheitel (ohne Ohren), mitte = Kopfmitte (Einheit s, nach oben negativ)
const KOPF = {
    fuchs: {oben: -1.7, mitte: -1.3},
    faultier: {oben: -1.66, mitte: -1.27},
    lemur: {oben: -1.64, mitte: -1.3},
    eule: {oben: -1.6, mitte: -1.24},
};

/** Schlafmütze, Forscherhut oder Taucherhelm für Fuchs, Faultier, Lemur und Eule (die Maus hat eigene). */
export function zeichneVerkleidung(ctx, s, tier, {t = 0, muetze = false, forscher = false, taucher = false} = {}) {
    const kopf = KOPF[tier];
    if (!kopf || !(muetze || forscher || taucher)) return;
    ctx.save();
    ctx.scale(s, s);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const rand = werkzeug(ctx);

    if (muetze || forscher) {
        // gleiche Form wie bei der Maus, auf den Scheitel des Tiers gesetzt
        ctx.save();
        ctx.translate(0, kopf.oben + 1.7);
        if (muetze) {
            const zipfel = Math.sin(t * 1.3) * 0.03;
            ctx.fillStyle = '#4263c9';
            ctx.beginPath();
            ctx.moveTo(-0.38, -1.52);
            ctx.quadraticCurveTo(-0.24, -2.02, 0.14, -1.98);
            ctx.quadraticCurveTo(0.48, -1.94, 0.66 + zipfel, -1.66);
            ctx.quadraticCurveTo(0.42, -1.78, 0.38, -1.52);
            ctx.closePath();
            ctx.fill();
            rand();
            ctx.fillStyle = '#ffe27a';
            [[-0.1, -1.8], [0.2, -1.85], [0.02, -1.64]].forEach(([x, y]) => {
                ellipse(ctx, x, y, 0.035, 0.035);
                ctx.fill();
            });
            ctx.fillStyle = '#f4f1ea';
            ellipse(ctx, 0, -1.52, 0.41, 0.075);
            ctx.fill();
            rand(0.025);
            ellipse(ctx, 0.66 + zipfel, -1.64, 0.075, 0.075);
            ctx.fill();
            rand(0.025);
        } else {
            ctx.fillStyle = '#e3cf9c';
            ellipse(ctx, 0, -1.56, 0.62, 0.11);
            ctx.fill();
            rand();
            ctx.beginPath();
            ctx.moveTo(-0.4, -1.58);
            ctx.bezierCurveTo(-0.4, -2.0, 0.4, -2.0, 0.4, -1.58);
            ctx.closePath();
            ctx.fill();
            rand();
            ctx.fillStyle = '#8a6a3c';
            ctx.fillRect(-0.4, -1.68, 0.8, 0.08);
            ctx.fillStyle = 'rgba(255,255,255,.35)';
            ellipse(ctx, -0.14, -1.86, 0.12, 0.04, -0.3);
            ctx.fill();
        }
        ctx.restore();
    }

    if (taucher) {
        // Messingkragen am Hals und Glaskugel um den ganzen Kopf (samt Ohren)
        const mitte = kopf.mitte - 0.12;
        ctx.fillStyle = '#c8963a';
        ellipse(ctx, 0, -0.77, 0.44, 0.07);
        ctx.fill();
        rand(0.03);
        ctx.fillStyle = 'rgba(200,240,255,.16)';
        ellipse(ctx, 0, mitte, 0.86, 0.7);
        ctx.fill();
        ctx.strokeStyle = 'rgba(225,248,255,.85)';
        ctx.lineWidth = 0.045;
        ctx.stroke();
        ctx.strokeStyle = 'rgba(255,255,255,.7)';
        ctx.lineWidth = 0.06;
        ctx.beginPath();
        ctx.ellipse(0, mitte, 0.72, 0.56, 0, Math.PI * 1.08, Math.PI * 1.32);
        ctx.stroke();
        ctx.fillStyle = '#c8963a';
        ellipse(ctx, 0, mitte - 0.68, 0.1, 0.05);
        ctx.fill();
        rand(0.025);
    }
    ctx.restore();
}
