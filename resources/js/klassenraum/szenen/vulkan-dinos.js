/*
 * Vulkan – Zeichnungen der Dinosaurier (freundlicher Bilderbuch-Comicstil).
 * Seitenansicht mit Blick nach rechts, Ursprung zwischen den Füßen, g = Grundgröße;
 * gezeichnet wird in Einheiten von g (ctx.scale). Optionen:
 *   laeuft (Beine schwingen), frisst (Kopf am Boden), schaut (Kopf hoch, Blick zum Vulkan),
 *   baby (größerer Kopf), ph (Animationsphase).
 */

import {ellipse} from '../shared/hilfen';

const RAND = 'rgba(38,30,22,.72)';

function verlauf(ctx, y0, y1, oben, unten) {
    const v = ctx.createLinearGradient(0, y0, 0, y1);
    v.addColorStop(0, oben);
    v.addColorStop(1, unten);
    return v;
}

function fuelle(ctx, pfad, [oben, unten], y0, y1, rand = 0.018) {
    ctx.fillStyle = verlauf(ctx, y0, y1, oben, unten);
    ctx.fill(pfad);
    ctx.strokeStyle = RAND;
    ctx.lineWidth = rand;
    ctx.stroke(pfad);
}

function glatt(pfad, pts, neu) {
    if (neu) pfad.moveTo(pts[0][0], pts[0][1]);
    else pfad.lineTo(pts[0][0], pts[0][1]);
    for (let i = 1; i < pts.length - 1; i++) {
        pfad.quadraticCurveTo(pts[i][0], pts[i][1], (pts[i][0] + pts[i + 1][0]) / 2, (pts[i][1] + pts[i + 1][1]) / 2);
    }
    pfad.lineTo(pts[pts.length - 1][0], pts[pts.length - 1][1]);
}

/** Schlauchform durch Punkte [x, y, breite]; liefert Fläche und Seitenränder (ohne Enden). */
function schlauch(pts) {
    const links = [];
    const rechts = [];
    pts.forEach((p, i) => {
        const a = pts[Math.max(0, i - 1)];
        const b = pts[Math.min(pts.length - 1, i + 1)];
        const nx = a[1] - b[1];
        const ny = b[0] - a[0];
        const l = Math.hypot(nx, ny) || 1;
        links.push([p[0] + nx / l * p[2] / 2, p[1] + ny / l * p[2] / 2]);
        rechts.push([p[0] - nx / l * p[2] / 2, p[1] - ny / l * p[2] / 2]);
    });
    const flaeche = new Path2D();
    glatt(flaeche, links, true);
    glatt(flaeche, [...rechts].reverse(), false);
    flaeche.closePath();
    const rand = new Path2D();
    glatt(rand, links, true);
    glatt(rand, rechts, true);
    return {flaeche, rand};
}

function zeichneSchlauch(ctx, pts, farben, y0, y1, spitzeZu = true) {
    const {flaeche, rand} = schlauch(pts);
    ctx.fillStyle = verlauf(ctx, y0, y1, farben[0], farben[1]);
    ctx.fill(flaeche);
    ctx.strokeStyle = RAND;
    ctx.lineWidth = 0.018;
    ctx.stroke(spitzeZu ? flaeche : rand);
}

/** Quadratische Kurve abtasten → Punkte mit Breite von b0 nach b1. */
function kurve(p0, p1, p2, b0, b1, n = 8) {
    return Array.from({length: n + 1}, (_, i) => {
        const t = i / n;
        return [
            (1 - t) ** 2 * p0[0] + 2 * (1 - t) * t * p1[0] + t * t * p2[0],
            (1 - t) ** 2 * p0[1] + 2 * (1 - t) * t * p1[1] + t * t * p2[1],
            b0 + (b1 - b0) * t,
        ];
    });
}

function auge(ctx, x, y, r, blick = [0, 0]) {
    ctx.fillStyle = '#ffffff';
    ellipse(ctx, x, y, r, r * 1.08);
    ctx.fill();
    ctx.strokeStyle = RAND;
    ctx.lineWidth = r * 0.25;
    ctx.stroke();
    ctx.fillStyle = '#1d1712';
    ellipse(ctx, x + blick[0] * r * 0.35, y + blick[1] * r * 0.35, r * 0.58, r * 0.62);
    ctx.fill();
    ctx.fillStyle = '#ffffff';
    ellipse(ctx, x + blick[0] * r * 0.35 + r * 0.2, y + blick[1] * r * 0.35 - r * 0.22, r * 0.2, r * 0.2);
    ctx.fill();
}

/** Säulenbein (Sauropoden, Dreihorn): schwingt um den oberen Ansatz, Zehennägel vorn. */
function bein(ctx, x, oben, breite, schwung, farben, fern) {
    const laenge = -oben;
    ctx.save();
    ctx.translate(x, oben);
    ctx.rotate(-schwung * 0.32);
    const p = new Path2D();
    p.moveTo(-breite / 2, 0);
    p.quadraticCurveTo(-breite * 0.52, laenge * 0.6, -breite * 0.5, laenge * 0.88);
    p.quadraticCurveTo(-breite * 0.62, laenge, -breite * 0.45, laenge);
    p.lineTo(breite * 0.62, laenge);
    p.quadraticCurveTo(breite * 0.7, laenge * 0.9, breite * 0.5, laenge * 0.85);
    p.quadraticCurveTo(breite * 0.55, laenge * 0.5, breite / 2, 0);
    fuelle(ctx, p, farben, 0, laenge);
    if (fern) {
        ctx.fillStyle = 'rgba(20,30,20,.2)';
        ctx.fill(p);
    }
    ctx.fillStyle = '#f3ead2';
    [-0.12, 0.15, 0.4].forEach((dx) => {
        ellipse(ctx, dx * breite, laenge - breite * 0.08, breite * 0.11, breite * 0.08);
        ctx.fill();
    });
    ctx.restore();
}

function lasse(ctx, g, malen) {
    ctx.save();
    ctx.scale(g, g);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    malen();
    ctx.restore();
}

// ══ Langhals (Brachiosaurus) ══════════════════════════════════════════
const LANGHALS = ['#a7d58e', '#5f9c56'];

export function zeichneLanghals(ctx, g, {ph = 0, laeuft = false, frisst = false, schaut = false, baby = false} = {}) {
    const s = laeuft ? Math.sin(ph) : 0;
    lasse(ctx, g, () => {
        bein(ctx, -0.18, -0.38, 0.12, -s, LANGHALS, true);
        bein(ctx, 0.22, -0.44, 0.12, s, LANGHALS, true);

        const wedel = Math.sin(ph * 0.6) * 0.03;
        zeichneSchlauch(ctx, [[-0.28, -0.5, 0.22], [-0.55, -0.44, 0.13], [-0.8, -0.32, 0.06], [-0.98, -0.26 + wedel, 0.012]], LANGHALS, -0.6, -0.2);

        const koerper = new Path2D();
        koerper.moveTo(-0.38, -0.44);
        koerper.bezierCurveTo(-0.36, -0.72, 0.1, -0.8, 0.3, -0.62);
        koerper.bezierCurveTo(0.42, -0.52, 0.38, -0.32, 0.26, -0.3);
        koerper.bezierCurveTo(0.05, -0.25, -0.26, -0.26, -0.36, -0.34);
        koerper.closePath();
        fuelle(ctx, koerper, LANGHALS, -0.8, -0.26);
        ctx.save();
        ctx.clip(koerper);
        ctx.fillStyle = 'rgba(240,250,210,.55)';
        ellipse(ctx, 0.0, -0.27, 0.32, 0.08);
        ctx.fill();
        ctx.fillStyle = 'rgba(60,110,55,.45)';
        [[-0.2, -0.62, 0.05], [-0.05, -0.68, 0.06], [0.12, -0.66, 0.045], [-0.3, -0.52, 0.035]].forEach(([x, y, r]) => {
            ellipse(ctx, x, y, r, r * 0.8);
            ctx.fill();
        });
        ctx.restore();

        // Hals und Kopf
        const kopf = frisst ? [0.7, -0.27 + Math.sin(ph * 1.4) * 0.015] : schaut ? [0.4, -1.28] : [0.52, -1.16 + Math.sin(ph * 0.5) * 0.02];
        const ctrl = frisst ? [0.6, -0.72] : [0.42, -0.95];
        zeichneSchlauch(ctx, kurve([0.18, -0.6], ctrl, kopf, 0.24, 0.09), LANGHALS, -1.2, -0.3, false);
        ctx.save();
        ctx.translate(kopf[0], kopf[1]);
        ctx.rotate(frisst ? 1.25 : schaut ? -0.35 : 0.15);
        const k = baby ? 1.35 : 1;
        ctx.scale(k, k);
        const kp = new Path2D();
        kp.moveTo(-0.07, -0.03);
        kp.bezierCurveTo(-0.05, -0.12, 0.06, -0.13, 0.1, -0.07);
        kp.bezierCurveTo(0.16, -0.06, 0.21, -0.02, 0.2, 0.03);
        kp.bezierCurveTo(0.18, 0.07, 0.04, 0.07, -0.04, 0.05);
        kp.closePath();
        fuelle(ctx, kp, LANGHALS, -0.12, 0.07);
        auge(ctx, 0.05, -0.04, 0.026, schaut ? [0.4, -0.9] : [0.6, 0]);
        ctx.strokeStyle = RAND;
        ctx.lineWidth = 0.012;
        ctx.beginPath();
        ctx.moveTo(0.1, 0.035);
        ctx.quadraticCurveTo(0.15, 0.05, 0.19, 0.03);
        ctx.stroke();
        ctx.restore();

        bein(ctx, -0.24, -0.38, 0.13, s, LANGHALS, false);
        bein(ctx, 0.16, -0.44, 0.13, -s, LANGHALS, false);
    });
}

// ══ Dreihorn (Triceratops) ════════════════════════════════════════════
const DREIHORN = ['#7cc3c4', '#3d8890'];

export function zeichneDreihorn(ctx, g, {ph = 0, laeuft = false, frisst = false, schaut = false, baby = false} = {}) {
    const s = laeuft ? Math.sin(ph) : 0;
    lasse(ctx, g, () => {
        bein(ctx, -0.2, -0.28, 0.12, -s, DREIHORN, true);
        bein(ctx, 0.18, -0.28, 0.12, s, DREIHORN, true);
        zeichneSchlauch(ctx, [[-0.28, -0.38, 0.2], [-0.5, -0.32, 0.1], [-0.68, -0.22, 0.015]], DREIHORN, -0.5, -0.2);

        const koerper = new Path2D();
        koerper.moveTo(-0.36, -0.34);
        koerper.bezierCurveTo(-0.34, -0.62, 0.15, -0.66, 0.3, -0.48);
        koerper.bezierCurveTo(0.38, -0.4, 0.34, -0.22, 0.22, -0.2);
        koerper.bezierCurveTo(0.0, -0.16, -0.25, -0.16, -0.34, -0.24);
        koerper.closePath();
        fuelle(ctx, koerper, DREIHORN, -0.64, -0.18);
        ctx.save();
        ctx.clip(koerper);
        ctx.fillStyle = 'rgba(235,250,235,.5)';
        ellipse(ctx, 0, -0.19, 0.3, 0.07);
        ctx.fill();
        ctx.strokeStyle = 'rgba(30,90,95,.35)';
        ctx.lineWidth = 0.025;
        [-0.2, -0.06, 0.08].forEach((x) => {
            ctx.beginPath();
            ctx.moveTo(x, -0.62);
            ctx.quadraticCurveTo(x + 0.03, -0.5, x, -0.42);
            ctx.stroke();
        });
        ctx.restore();

        // Kopf mit Nackenschild und drei Hörnern
        ctx.save();
        ctx.translate(0.28, -0.42);
        ctx.rotate(frisst ? 0.5 + Math.sin(ph * 1.4) * 0.04 : schaut ? -0.25 : 0.05);
        const k = baby ? 1.3 : 1;
        ctx.scale(k, k);
        const schild = new Path2D();
        schild.ellipse(-0.02, -0.07, 0.15, 0.19, -0.35, 0, Math.PI * 2);
        ctx.fillStyle = '#f2cc5c';
        for (let i = 0; i < 11; i++) {
            const w = Math.PI * 0.55 + (i / 10) * Math.PI * 1.25;
            const ex = -0.02 + Math.cos(w) * 0.15 * Math.cos(-0.35) - Math.sin(w) * 0.19 * Math.sin(-0.35);
            const ey = -0.07 + Math.cos(w) * 0.15 * Math.sin(-0.35) + Math.sin(w) * 0.19 * Math.cos(-0.35);
            ellipse(ctx, ex, ey, 0.035, 0.035);
            ctx.fill();
            ctx.strokeStyle = RAND;
            ctx.lineWidth = 0.012;
            ctx.stroke();
        }
        fuelle(ctx, schild, ['#f6a65a', '#e0773f'], -0.3, 0.1);
        ctx.fillStyle = 'rgba(255,230,160,.6)';
        ellipse(ctx, -0.04, -0.12, 0.08, 0.12, -0.35);
        ctx.fill();
        // hinteres Stirnhorn
        const horn = (dx, dy, farbe) => {
            const p = new Path2D();
            p.moveTo(0.04 + dx, -0.11 + dy);
            p.quadraticCurveTo(0.11 + dx, -0.22 + dy, 0.24 + dx, -0.32 + dy);
            p.quadraticCurveTo(0.13 + dx, -0.17 + dy, 0.11 + dx, -0.09 + dy);
            p.closePath();
            ctx.fillStyle = farbe;
            ctx.fill(p);
            ctx.strokeStyle = RAND;
            ctx.lineWidth = 0.012;
            ctx.stroke(p);
        };
        horn(-0.035, 0.01, '#d9cfb4');
        const gesicht = new Path2D();
        gesicht.moveTo(-0.06, -0.12);
        gesicht.bezierCurveTo(0.08, -0.17, 0.22, -0.1, 0.3, 0.0);
        gesicht.lineTo(0.31, 0.04);
        gesicht.bezierCurveTo(0.24, 0.09, 0.1, 0.1, -0.05, 0.06);
        gesicht.closePath();
        fuelle(ctx, gesicht, DREIHORN, -0.16, 0.1);
        horn(0, 0, '#f6efdc');
        ctx.fillStyle = '#f6efdc';
        ctx.beginPath();
        ctx.moveTo(0.19, -0.07);
        ctx.lineTo(0.25, -0.16);
        ctx.lineTo(0.25, -0.05);
        ctx.closePath();
        ctx.fill();
        ctx.strokeStyle = RAND;
        ctx.lineWidth = 0.012;
        ctx.stroke();
        ctx.fillStyle = '#5b4636';
        ctx.beginPath();
        ctx.moveTo(0.26, -0.015);
        ctx.lineTo(0.33, 0.03);
        ctx.lineTo(0.25, 0.065);
        ctx.closePath();
        ctx.fill();
        auge(ctx, 0.1, -0.06, 0.03, schaut ? [0.3, -0.9] : [0.6, 0.1]);
        ctx.strokeStyle = RAND;
        ctx.lineWidth = 0.012;
        ctx.beginPath();
        ctx.moveTo(0.12, 0.05);
        ctx.quadraticCurveTo(0.18, 0.07, 0.24, 0.05);
        ctx.stroke();
        ctx.restore();

        bein(ctx, -0.24, -0.28, 0.13, s, DREIHORN, false);
        bein(ctx, 0.14, -0.28, 0.13, -s, DREIHORN, false);
    });
}

// ══ Stegosaurus ═══════════════════════════════════════════════════════
const STEGO = ['#f0cf7a', '#c9963a'];

export function zeichneStego(ctx, g, {ph = 0, laeuft = false, frisst = false, schaut = false, baby = false} = {}) {
    const s = laeuft ? Math.sin(ph) : 0;
    lasse(ctx, g, () => {
        bein(ctx, -0.22, -0.34, 0.12, -s, STEGO, true);
        bein(ctx, 0.18, -0.24, 0.1, s, STEGO, true);

        // Schwanz mit Stacheln
        const wedel = Math.sin(ph * 0.7) * 0.03;
        const ende = [-0.86, -0.46 + wedel];
        zeichneSchlauch(ctx, [[-0.3, -0.4, 0.2], [-0.55, -0.42, 0.11], [ende[0], ende[1], 0.02]], STEGO, -0.5, -0.3);
        [0.62, 0.85].forEach((t) => {
            const x = -0.55 + (ende[0] + 0.55) * t;
            const y = -0.42 + (ende[1] + 0.42) * t - 0.02;
            [[-0.07, -0.13], [0.02, -0.14]].forEach(([dx, dy]) => {
                ctx.fillStyle = '#efe5cb';
                ctx.beginPath();
                ctx.moveTo(x - 0.022, y + 0.01);
                ctx.lineTo(x + dx, y + dy);
                ctx.lineTo(x + 0.022, y + 0.01);
                ctx.closePath();
                ctx.fill();
                ctx.strokeStyle = RAND;
                ctx.lineWidth = 0.012;
                ctx.stroke();
            });
        });

        // Rückenplatten entlang des Rückens
        const ruecken = (t) => {
            const p0 = [-0.4, -0.32];
            const p1 = [-0.3, -0.76];
            const p2 = [0.15, -0.76];
            const p3 = [0.3, -0.42];
            const u = 1 - t;
            return [
                u ** 3 * p0[0] + 3 * u * u * t * p1[0] + 3 * u * t * t * p2[0] + t ** 3 * p3[0],
                u ** 3 * p0[1] + 3 * u * u * t * p1[1] + 3 * u * t * t * p2[1] + t ** 3 * p3[1],
            ];
        };
        for (let i = 0; i < 7; i++) {
            const t = 0.12 + i * 0.11;
            const [x, y] = ruecken(t);
            const [xa, ya] = ruecken(t - 0.02);
            const [xb, yb] = ruecken(t + 0.02);
            const l = Math.hypot(xb - xa, yb - ya);
            const nx = (yb - ya) / l;
            const ny = -(xb - xa) / l;
            const groesse = 0.1 + Math.sin(t * Math.PI) * 0.07;
            const p = new Path2D();
            p.moveTo(x - (xb - xa) / l * groesse * 0.5, y - (yb - ya) / l * groesse * 0.5);
            p.lineTo(x + nx * groesse * 0.75 - (xb - xa) / l * groesse * 0.25, y + ny * groesse * 0.75);
            p.lineTo(x + nx * groesse * 1.1, y + ny * groesse * 1.1);
            p.lineTo(x + nx * groesse * 0.7 + (xb - xa) / l * groesse * 0.35, y + ny * groesse * 0.7);
            p.lineTo(x + (xb - xa) / l * groesse * 0.5, y + (yb - ya) / l * groesse * 0.5);
            p.closePath();
            fuelle(ctx, p, ['#f28c5b', '#d9543a'], y - groesse, y, 0.014);
        }

        const koerper = new Path2D();
        koerper.moveTo(-0.4, -0.32);
        koerper.bezierCurveTo(-0.3, -0.76, 0.15, -0.76, 0.3, -0.42);
        koerper.bezierCurveTo(0.33, -0.3, 0.28, -0.2, 0.18, -0.19);
        koerper.bezierCurveTo(0.0, -0.15, -0.28, -0.16, -0.38, -0.22);
        koerper.closePath();
        fuelle(ctx, koerper, STEGO, -0.74, -0.16);
        ctx.save();
        ctx.clip(koerper);
        ctx.fillStyle = 'rgba(255,248,220,.55)';
        ellipse(ctx, -0.04, -0.2, 0.3, 0.07);
        ctx.fill();
        ctx.fillStyle = 'rgba(170,110,40,.35)';
        [[-0.18, -0.55], [0.0, -0.6], [0.14, -0.5], [-0.28, -0.42]].forEach(([x, y]) => {
            ellipse(ctx, x, y, 0.05, 0.035);
            ctx.fill();
        });
        ctx.restore();

        // kleiner Kopf vorn unten
        const kopf = frisst ? [0.42, -0.15 + Math.sin(ph * 1.4) * 0.01] : schaut ? [0.4, -0.42] : [0.42, -0.25];
        zeichneSchlauch(ctx, kurve([0.2, -0.34], [0.34, -0.34], kopf, 0.14, 0.08, 6), STEGO, -0.4, -0.1, false);
        ctx.save();
        ctx.translate(kopf[0], kopf[1]);
        ctx.rotate(frisst ? 0.7 : schaut ? -0.3 : 0.15);
        const k = baby ? 1.35 : 1;
        ctx.scale(k, k);
        const kp = new Path2D();
        kp.moveTo(-0.05, -0.04);
        kp.bezierCurveTo(0.0, -0.09, 0.1, -0.08, 0.15, -0.02);
        kp.bezierCurveTo(0.17, 0.02, 0.13, 0.05, 0.08, 0.05);
        kp.bezierCurveTo(0.02, 0.05, -0.04, 0.04, -0.05, 0.02);
        kp.closePath();
        fuelle(ctx, kp, STEGO, -0.08, 0.05);
        auge(ctx, 0.05, -0.025, 0.022, schaut ? [0.3, -0.9] : [0.6, 0]);
        ctx.restore();

        bein(ctx, -0.28, -0.34, 0.13, s, STEGO, false);
        bein(ctx, 0.12, -0.24, 0.11, -s, STEGO, false);
    });
}

// ══ Flitzer (kleiner Zweibeiner) ══════════════════════════════════════
const FLITZER = ['#b5d86a', '#6a994e'];

export function zeichneFlitzer(ctx, g, {ph = 0, laeuft = false, frisst = false, schaut = false, baby = false} = {}) {
    const s = laeuft ? Math.sin(ph * 1.3) : 0;
    const hupf = laeuft ? Math.abs(Math.sin(ph * 1.3)) * 0.04 : 0;
    lasse(ctx, g, () => {
        const zweibein = (vorne, fern) => {
            const w = (vorne ? s : -s) * 0.5;
            const huefte = [0, -0.38 - hupf];
            const knie = [huefte[0] + 0.08 + Math.sin(w) * 0.12, -0.2 - hupf * 0.5];
            const fuss = [huefte[0] + Math.sin(w) * 0.2, 0 - Math.max(0, w) * 0.06];
            zeichneSchlauch(ctx, [[...huefte, 0.11], [...knie, 0.06], [...fuss, 0.04]], fern ? ['#8fb257', '#557d3c'] : FLITZER, -0.4, 0);
            ctx.fillStyle = '#e9e3c8';
            ellipse(ctx, fuss[0] + 0.04, fuss[1] - 0.01, 0.05, 0.02);
            ctx.fill();
        };
        zweibein(false, true);

        const wedel = Math.sin(ph * 0.9) * 0.04;
        zeichneSchlauch(ctx, [[-0.1, -0.45 - hupf, 0.16], [-0.35, -0.42 - hupf, 0.08], [-0.62, -0.46 + wedel - hupf, 0.012]], FLITZER, -0.55, -0.35);
        const koerper = new Path2D();
        koerper.ellipse(0.02, -0.46 - hupf, 0.2, 0.13, -0.15, 0, Math.PI * 2);
        fuelle(ctx, koerper, FLITZER, -0.6, -0.33);
        ctx.save();
        ctx.clip(koerper);
        ctx.strokeStyle = 'rgba(56,102,65,.55)';
        ctx.lineWidth = 0.028;
        [-0.1, 0.0, 0.1].forEach((x) => {
            ctx.beginPath();
            ctx.moveTo(x, -0.6 - hupf);
            ctx.lineTo(x + 0.03, -0.48 - hupf);
            ctx.stroke();
        });
        ctx.fillStyle = 'rgba(245,250,215,.6)';
        ellipse(ctx, 0.05, -0.37 - hupf, 0.14, 0.05, -0.15);
        ctx.fill();
        ctx.restore();

        // Hals und Kopf
        const kopf = frisst ? [0.34, -0.24] : schaut ? [0.22, -0.78 - hupf] : [0.26, -0.7 - hupf];
        zeichneSchlauch(ctx, kurve([0.12, -0.52 - hupf], [0.22, -0.6 - hupf], kopf, 0.1, 0.07, 5), FLITZER, -0.8, -0.3, false);
        ctx.save();
        ctx.translate(kopf[0], kopf[1]);
        ctx.rotate(frisst ? 1.0 : schaut ? -0.35 : 0);
        const k = baby ? 1.3 : 1;
        ctx.scale(k, k);
        const kp = new Path2D();
        kp.moveTo(-0.08, -0.02);
        kp.bezierCurveTo(-0.07, -0.11, 0.06, -0.12, 0.12, -0.05);
        kp.bezierCurveTo(0.18, -0.03, 0.2, 0.02, 0.17, 0.04);
        kp.bezierCurveTo(0.1, 0.06, -0.02, 0.06, -0.07, 0.03);
        kp.closePath();
        fuelle(ctx, kp, FLITZER, -0.12, 0.06);
        auge(ctx, 0.03, -0.045, 0.035, schaut ? [0.3, -0.9] : [0.6, 0]);
        ctx.strokeStyle = RAND;
        ctx.lineWidth = 0.012;
        ctx.beginPath();
        ctx.moveTo(0.08, 0.03);
        ctx.quadraticCurveTo(0.12, 0.05, 0.16, 0.03);
        ctx.stroke();
        ctx.restore();

        // Ärmchen
        ctx.strokeStyle = FLITZER[1];
        ctx.lineWidth = 0.035;
        ctx.beginPath();
        ctx.moveTo(0.14, -0.45 - hupf);
        ctx.lineTo(0.2, -0.37 - hupf + s * 0.02);
        ctx.stroke();
        zweibein(true, false);
    });
}

/** Ungefähre Höhe über dem Fußpunkt (in g) – für Herzen u. Ä. */
export const DINO_HOEHE = {langhals: 1.3, dreihorn: 0.7, stego: 0.85, flitzer: 0.85};
