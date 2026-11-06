/*
 * Zoo – Zeichnungen der Tiere und der Tierpfleger:innen (Seitenansicht, Blick nach rechts,
 * Ursprung zwischen den Füßen, g = Grundgröße). Licht kommt von oben: Flächen haben Verläufe,
 * Konturen sind weich abgedunkelt; Hautfalten, Fellstruktur und Muster sorgen für Realismus.
 */

import {ellipse, mische, zufallMitSaat} from '../shared/hilfen';

const KONTUR = 'rgba(25,18,10,.3)';

function verlauf(ctx, y0, y1, oben, unten) {
    const v = ctx.createLinearGradient(0, y0, 0, y1);
    v.addColorStop(0, oben);
    v.addColorStop(1, unten);
    return v;
}

function konturiere(ctx, pfad, g, staerke = 0.012) {
    ctx.strokeStyle = KONTUR;
    ctx.lineWidth = Math.max(0.8, g * staerke);
    ctx.stroke(pfad);
}

function linie(ctx, x1, y1, x2, y2) {
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();
}

/** Röhre mit abnehmender Dicke entlang einer quadratischen Kurve (Rüssel, Schwänze). */
function roehre(ctx, p0, p1, p2, r0, r1, fuellung, schritte = 18) {
    ctx.fillStyle = fuellung;
    for (let i = 0; i <= schritte; i++) {
        const t = i / schritte;
        const x = (1 - t) ** 2 * p0[0] + 2 * (1 - t) * t * p1[0] + t * t * p2[0];
        const y = (1 - t) ** 2 * p0[1] + 2 * (1 - t) * t * p1[1] + t * t * p2[1];
        ellipse(ctx, x, y, mische(r0, r1, t), mische(r0, r1, t));
        ctx.fill();
    }
}

// ══ Elefant ══════════════════════════════════════════════════════════════
export function zeichneElefant(ctx, g, ph, laeuft, baby, frisst) {
    const schritt = laeuft ? Math.sin(ph) : 0;
    const beinH = baby ? 0.3 : 0.36;
    const oben = baby ? '#b3bac1' : '#9ea5ac';
    const unten = baby ? '#8a9199' : '#6c737a';
    const fern = baby ? '#7f868e' : '#5f666d';
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    const bein = (x, versatz, farbeOben, farbeUnten) => {
        const bx = x * g + versatz * g * 0.05;
        const top = -(beinH + 0.14) * g;
        const breite = 0.14 * g;
        const p = new Path2D();
        p.moveTo(bx - breite * 0.52, top);
        p.quadraticCurveTo(bx - breite * 0.66, -beinH * g * 0.45, bx - breite * 0.52, -g * 0.015);
        p.quadraticCurveTo(bx, g * 0.012, bx + breite * 0.56, -g * 0.015);
        p.quadraticCurveTo(bx + breite * 0.64, -beinH * g * 0.5, bx + breite * 0.5, top);
        p.closePath();
        ctx.fillStyle = verlauf(ctx, top, 0, farbeOben, farbeUnten);
        ctx.fill(p);
        konturiere(ctx, p, g);
        ctx.strokeStyle = 'rgba(30,30,35,.2)';
        ctx.lineWidth = Math.max(0.6, g * 0.008);
        for (let i = 0; i < 4; i++) {
            const y = -beinH * g * (0.25 + i * 0.12);
            ctx.beginPath();
            ctx.moveTo(bx - breite * 0.4, y);
            ctx.quadraticCurveTo(bx, y + g * 0.015, bx + breite * 0.4, y);
            ctx.stroke();
        }
        ctx.fillStyle = '#ddd5c4';
        for (let j = -1; j <= 1; j++) {
            ellipse(ctx, bx + j * breite * 0.28, -g * 0.018, breite * 0.11, g * 0.016);
            ctx.fill();
        }
    };

    // abgewandte Beine
    bein(-0.24, -schritt, fern, '#4e555b');
    bein(0.2, schritt, fern, '#4e555b');

    // Schwanz mit Quaste
    const wedeln = Math.sin(ph * 0.7) * g * 0.03;
    ctx.strokeStyle = unten;
    ctx.lineWidth = Math.max(1, g * 0.022);
    ctx.beginPath();
    ctx.moveTo(-g * 0.5, -g * 0.66);
    ctx.quadraticCurveTo(-g * 0.57, -g * 0.55, -g * 0.55 + wedeln, -g * 0.4);
    ctx.stroke();
    ctx.fillStyle = '#2f3337';
    ellipse(ctx, -g * 0.55 + wedeln, -g * 0.38, g * 0.018, g * 0.035);
    ctx.fill();

    // Körper
    const kp = new Path2D();
    kp.moveTo(-0.5 * g, -0.52 * g);
    kp.bezierCurveTo(-0.53 * g, -0.8 * g, -0.32 * g, -0.92 * g, -0.06 * g, -0.9 * g);
    kp.bezierCurveTo(0.14 * g, -0.92 * g, 0.3 * g, -0.88 * g, 0.38 * g, -0.76 * g);
    kp.lineTo(0.43 * g, -0.52 * g);
    kp.bezierCurveTo(0.36 * g, -0.38 * g, 0.1 * g, -0.36 * g, -0.1 * g, -0.38 * g);
    kp.bezierCurveTo(-0.32 * g, -0.38 * g, -0.48 * g, -0.42 * g, -0.5 * g, -0.52 * g);
    ctx.fillStyle = verlauf(ctx, -0.92 * g, -0.36 * g, oben, unten);
    ctx.fill(kp);
    // Licht auf dem Rücken
    ctx.save();
    ctx.clip(kp);
    const glanz = ctx.createRadialGradient(-0.05 * g, -0.86 * g, 0, -0.05 * g, -0.86 * g, 0.45 * g);
    glanz.addColorStop(0, 'rgba(255,255,255,.18)');
    glanz.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = glanz;
    ctx.fillRect(-0.6 * g, -1 * g, 1.2 * g, 0.7 * g);
    // Hautfalten
    ctx.strokeStyle = 'rgba(30,30,35,.16)';
    ctx.lineWidth = Math.max(0.6, g * 0.008);
    [[-0.38, -0.7, 0.12], [-0.3, -0.58, 0.1], [0.24, -0.66, 0.1], [0.3, -0.55, 0.08], [0.0, -0.45, 0.14]].forEach(([fx, fy, r]) => {
        ctx.beginPath();
        ctx.arc(fx * g, fy * g, r * g, 1.1 * Math.PI, 1.6 * Math.PI);
        ctx.stroke();
    });
    ctx.restore();
    konturiere(ctx, kp, g);

    // zugewandte Beine
    bein(-0.36, schritt, '#858c93', '#5d646a');
    bein(0.3, -schritt, '#858c93', '#5d646a');

    // Kopf (beim Baby größer)
    ctx.save();
    const kf = baby ? 1.18 : 1;
    ctx.translate(0.48 * g, -0.78 * g);
    ctx.scale(kf, kf);
    ctx.translate(-0.48 * g, 0.78 * g);

    // Rüssel: verjüngte Röhre mit Ringfalten
    const spitze = frisst
        ? [0.7 * g + Math.sin(ph * 1.5) * g * 0.03, -0.1 * g + Math.sin(ph * 1.5) * g * 0.03]
        : [0.66 * g + Math.sin(ph * 0.6) * g * 0.04, -0.22 * g];
    const p0 = [0.62 * g, -0.64 * g];
    const p1 = [0.76 * g, -0.42 * g];
    roehre(ctx, p0, p1, spitze, 0.075 * g, 0.032 * g, '#8b9299');
    ctx.strokeStyle = 'rgba(30,30,35,.22)';
    ctx.lineWidth = Math.max(0.6, g * 0.007);
    for (let i = 2; i < 14; i += 2) {
        const t = i / 16;
        const x = (1 - t) ** 2 * p0[0] + 2 * (1 - t) * t * p1[0] + t * t * spitze[0];
        const y = (1 - t) ** 2 * p0[1] + 2 * (1 - t) * t * p1[1] + t * t * spitze[1];
        const r = mische(0.07, 0.03, t) * g;
        linie(ctx, x - r * 0.8, y - r * 0.3, x + r * 0.8, y + r * 0.3);
    }

    const kopf = new Path2D();
    kopf.moveTo(0.32 * g, -0.82 * g);
    kopf.bezierCurveTo(0.36 * g, -0.99 * g, 0.56 * g, -1.01 * g, 0.62 * g, -0.86 * g);
    kopf.bezierCurveTo(0.66 * g, -0.76 * g, 0.67 * g, -0.68 * g, 0.64 * g, -0.6 * g);
    kopf.lineTo(0.5 * g, -0.55 * g);
    kopf.bezierCurveTo(0.42 * g, -0.56 * g, 0.34 * g, -0.62 * g, 0.32 * g, -0.82 * g);
    ctx.fillStyle = verlauf(ctx, -1 * g, -0.55 * g, '#a4abb2', '#7a8188');
    ctx.fill(kopf);
    konturiere(ctx, kopf, g);

    // Stoßzahn
    if (!baby) {
        const zahn = new Path2D();
        zahn.moveTo(0.57 * g, -0.6 * g);
        zahn.quadraticCurveTo(0.66 * g, -0.48 * g, 0.78 * g, -0.5 * g);
        zahn.quadraticCurveTo(0.67 * g, -0.53 * g, 0.62 * g, -0.62 * g);
        zahn.closePath();
        ctx.fillStyle = verlauf(ctx, -0.62 * g, -0.48 * g, '#fbf7ec', '#d8ccb0');
        ctx.fill(zahn);
        konturiere(ctx, zahn, g, 0.008);
    }

    // Auge mit Falten
    ctx.fillStyle = '#1c1917';
    ellipse(ctx, 0.55 * g, -0.84 * g, 0.018 * g, 0.014 * g);
    ctx.fill();
    ctx.strokeStyle = 'rgba(30,30,35,.3)';
    ctx.lineWidth = Math.max(0.6, g * 0.007);
    ctx.beginPath();
    ctx.arc(0.55 * g, -0.84 * g, 0.032 * g, 1.1 * Math.PI, 1.9 * Math.PI);
    ctx.stroke();

    // Ohr (großes afrikanisches Ohr mit Adern, fächelt langsam)
    ctx.save();
    ctx.translate(0.42 * g, -0.8 * g);
    ctx.scale(0.92 + Math.sin(ph * 0.45) * 0.07, 1);
    ctx.translate(-0.42 * g, 0.8 * g);
    const ohr = new Path2D();
    ohr.moveTo(0.42 * g, -0.95 * g);
    ohr.bezierCurveTo(0.3 * g, -1.03 * g, 0.12 * g, -0.99 * g, 0.1 * g, -0.82 * g);
    ohr.bezierCurveTo(0.08 * g, -0.66 * g, 0.14 * g, -0.52 * g, 0.24 * g, -0.46 * g);
    ohr.bezierCurveTo(0.3 * g, -0.44 * g, 0.34 * g, -0.52 * g, 0.36 * g, -0.6 * g);
    ohr.bezierCurveTo(0.4 * g, -0.7 * g, 0.44 * g, -0.82 * g, 0.42 * g, -0.95 * g);
    const ohrVerlauf = ctx.createRadialGradient(0.32 * g, -0.75 * g, 0, 0.26 * g, -0.75 * g, 0.3 * g);
    ohrVerlauf.addColorStop(0, '#a99ea1');
    ohrVerlauf.addColorStop(1, '#6f767c');
    ctx.fillStyle = ohrVerlauf;
    ctx.fill(ohr);
    ctx.strokeStyle = 'rgba(70,55,60,.35)';
    ctx.lineWidth = Math.max(0.6, g * 0.006);
    [[0.36, -0.88, 0.2, -0.8, 0.16, -0.66], [0.36, -0.78, 0.26, -0.7, 0.22, -0.56], [0.38, -0.7, 0.32, -0.62, 0.3, -0.54]].forEach(([a, b2, c, d, e, f]) => {
        ctx.beginPath();
        ctx.moveTo(a * g, b2 * g);
        ctx.quadraticCurveTo(c * g, d * g, e * g, f * g);
        ctx.stroke();
    });
    konturiere(ctx, ohr, g);
    ctx.restore();
    ctx.restore();
}

// ══ Giraffe ══════════════════════════════════════════════════════════════
const musterBilder = new Map();

/** Netzmuster der Giraffe einmal vorrendern (Bereich x −0.5…0.9 g, y −2.1…−0.3 g). */
function giraffenMuster(saat) {
    if (musterBilder.has(saat)) return musterBilder.get(saat);
    const G = 180;
    const bild = document.createElement('canvas');
    bild.width = Math.round(1.4 * G);
    bild.height = Math.round(1.8 * G);
    const c = bild.getContext('2d');
    c.fillStyle = '#f1dfb8';
    c.fillRect(0, 0, bild.width, bild.height);
    const zz = zufallMitSaat(saat);
    const raster = 0.085;
    for (let y = -2.12; y < -0.28; y += raster) {
        for (let x = -0.52; x < 0.92; x += raster) {
            const cx = (x + zz.zwischen(-0.018, 0.018) + 0.5) * G;
            const cy = (y + zz.zwischen(-0.018, 0.018) + 2.1) * G;
            const r = G * zz.zwischen(0.03, 0.037);
            c.fillStyle = zz() < 0.5 ? '#b5652b' : '#a3561f';
            c.beginPath();
            for (let k = 0; k < 6; k++) {
                const w = (k / 6) * Math.PI * 2 + zz.zwischen(-0.25, 0.25);
                const rr = r * zz.zwischen(0.85, 1.1);
                const px = cx + Math.cos(w) * rr;
                const py = cy + Math.sin(w) * rr;
                k === 0 ? c.moveTo(px, py) : c.lineTo(px, py);
            }
            c.closePath();
            c.fill();
        }
    }
    musterBilder.set(saat, bild);
    return bild;
}

export function zeichneGiraffe(ctx, g, ph, laeuft, baby, frisst, saat = 1) {
    const schritt = laeuft ? Math.sin(ph) : 0;
    const beinH = (baby ? 0.64 : 0.8) * g;
    const halsL = (baby ? 0.62 : 0.86) * g;
    const r = -beinH;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    const bein = (x, s, fern) => {
        const bx = x * g;
        const top = r - 0.08 * g;
        const knie = r + beinH * 0.48;
        const kx = bx + s * g * 0.035;
        const fx = bx + s * g * 0.07;
        const p = new Path2D();
        p.moveTo(bx - 0.055 * g, top);
        p.quadraticCurveTo(bx - 0.05 * g, (top + knie) / 2, kx - 0.028 * g, knie);
        p.lineTo(fx - 0.022 * g, -0.03 * g);
        p.lineTo(fx + 0.022 * g, -0.03 * g);
        p.lineTo(kx + 0.03 * g, knie);
        p.quadraticCurveTo(bx + 0.05 * g, (top + knie) / 2, bx + 0.055 * g, top);
        p.closePath();
        ctx.fillStyle = verlauf(ctx, top, 0, fern ? '#c99a52' : '#e2b56a', fern ? '#cdbb98' : '#efe2c6');
        ctx.fill(p);
        konturiere(ctx, p, g, 0.008);
        ctx.fillStyle = fern ? 'rgba(130,70,25,.7)' : 'rgba(160,85,30,.85)';
        ellipse(ctx, bx, top + 0.08 * g, 0.03 * g, 0.035 * g);
        ctx.fill();
        ellipse(ctx, bx + 0.005 * g, top + 0.17 * g, 0.022 * g, 0.026 * g);
        ctx.fill();
        ctx.fillStyle = '#3b2a1c';
        ctx.beginPath();
        ctx.moveTo(fx - 0.026 * g, -0.032 * g);
        ctx.lineTo(fx + 0.026 * g, -0.032 * g);
        ctx.lineTo(fx + 0.03 * g, 0);
        ctx.lineTo(fx - 0.028 * g, 0);
        ctx.fill();
    };

    bein(-0.26, -schritt, true);
    bein(0.16, schritt, true);

    // Schwanz
    ctx.strokeStyle = '#a7743c';
    ctx.lineWidth = Math.max(1, g * 0.014);
    const schwanzEnde = [-0.44 * g + Math.sin(ph * 0.8) * g * 0.02, r + 0.26 * g];
    ctx.beginPath();
    ctx.moveTo(-0.37 * g, r - 0.1 * g);
    ctx.quadraticCurveTo(-0.43 * g, r + 0.05 * g, schwanzEnde[0], schwanzEnde[1]);
    ctx.stroke();
    ctx.fillStyle = '#1f1712';
    ellipse(ctx, schwanzEnde[0], schwanzEnde[1] + 0.03 * g, 0.018 * g, 0.045 * g);
    ctx.fill();

    // Hals und Körper als gemeinsame Fläche (für das Netzmuster)
    const winkel = (frisst ? 0.42 + Math.sin(ph * 1.2) * 0.05 : 0.3) + Math.sin(ph * 0.4) * 0.03;
    const basisX = 0.2 * g;
    const basisY = r - 0.28 * g;
    const kopfX = basisX + Math.sin(winkel) * halsL;
    const kopfY = basisY - Math.cos(winkel) * halsL;

    const koerper = new Path2D();
    koerper.moveTo(-0.38 * g, r - 0.12 * g);
    koerper.bezierCurveTo(-0.34 * g, r - 0.3 * g, 0.0, r - 0.3 * g, 0.2 * g, r - 0.36 * g);
    koerper.bezierCurveTo(0.34 * g, r - 0.34 * g, 0.36 * g, r - 0.12 * g, 0.26 * g, r - 0.02 * g);
    koerper.bezierCurveTo(0.1 * g, r + 0.03 * g, -0.2 * g, r + 0.03 * g, -0.32 * g, r - 0.02 * g);
    koerper.bezierCurveTo(-0.4 * g, r - 0.05 * g, -0.4 * g, r - 0.1 * g, -0.38 * g, r - 0.12 * g);
    const hals = new Path2D();
    hals.moveTo(0.06 * g, r - 0.3 * g);
    hals.quadraticCurveTo(basisX + Math.sin(winkel) * halsL * 0.5 - 0.06 * g, basisY - Math.cos(winkel) * halsL * 0.5, kopfX - 0.05 * g, kopfY + 0.02 * g);
    hals.lineTo(kopfX + 0.045 * g, kopfY + 0.07 * g);
    hals.quadraticCurveTo(basisX + Math.sin(winkel) * halsL * 0.5 + 0.08 * g, basisY - Math.cos(winkel) * halsL * 0.5 + 0.06 * g, 0.34 * g, r - 0.16 * g);
    hals.closePath();

    ctx.save();
    const flaeche = new Path2D();
    flaeche.addPath(koerper);
    flaeche.addPath(hals);
    ctx.clip(flaeche);
    ctx.drawImage(giraffenMuster(saat), -0.5 * g, -2.1 * g, 1.4 * g, 1.8 * g);
    // Schattierung: Bauch und Halsvorderseite dunkler, Rücken heller
    const schatten = ctx.createLinearGradient(0, r - 0.36 * g, 0, r + 0.03 * g);
    schatten.addColorStop(0, 'rgba(255,240,210,.18)');
    schatten.addColorStop(1, 'rgba(70,35,10,.3)');
    ctx.fillStyle = schatten;
    ctx.fillRect(-0.5 * g, r - 0.4 * g, 1 * g, 0.45 * g);
    ctx.restore();
    konturiere(ctx, koerper, g, 0.009);
    konturiere(ctx, hals, g, 0.009);

    // Mähne
    ctx.strokeStyle = '#7a4a22';
    ctx.lineWidth = Math.max(1, g * 0.012);
    for (let i = 1; i < 10; i++) {
        const p = i / 10;
        const x = mische(0.06 * g, kopfX - 0.05 * g, p);
        const y = mische(r - 0.3 * g, kopfY + 0.02 * g, p);
        linie(ctx, x, y, x - 0.03 * g, y + 0.012 * g);
    }

    bein(-0.15, schritt, false);
    bein(0.26, -schritt, false);

    // Kopf
    ctx.save();
    ctx.translate(kopfX, kopfY);
    ctx.rotate(0.55 + winkel * 0.5);
    // Hörnchen (Ossikone) mit dunklen Haarbüscheln
    ctx.strokeStyle = '#c98f45';
    ctx.lineWidth = Math.max(1, g * 0.02);
    linie(ctx, -0.01 * g, -0.05 * g, -0.05 * g, -0.14 * g);
    linie(ctx, 0.03 * g, -0.05 * g, 0.0, -0.14 * g);
    ctx.fillStyle = '#2b1d12';
    ellipse(ctx, -0.05 * g, -0.15 * g, 0.018 * g, 0.018 * g);
    ctx.fill();
    ellipse(ctx, 0.0, -0.15 * g, 0.018 * g, 0.018 * g);
    ctx.fill();
    // Ohr
    ctx.fillStyle = '#d9a660';
    ellipse(ctx, -0.07 * g, -0.03 * g, 0.06 * g, 0.022 * g, -0.4);
    ctx.fill();
    const kp = new Path2D();
    kp.moveTo(-0.04 * g, -0.05 * g);
    kp.bezierCurveTo(0.08 * g, -0.08 * g, 0.2 * g, -0.05 * g, 0.26 * g, 0.02 * g);
    kp.bezierCurveTo(0.28 * g, 0.06 * g, 0.25 * g, 0.09 * g, 0.2 * g, 0.085 * g);
    kp.bezierCurveTo(0.12 * g, 0.08 * g, 0.02 * g, 0.07 * g, -0.04 * g, 0.03 * g);
    kp.closePath();
    ctx.fillStyle = verlauf(ctx, -0.07 * g, 0.09 * g, '#ecc98a', '#c38f53');
    ctx.fill(kp);
    konturiere(ctx, kp, g, 0.008);
    ctx.fillStyle = 'rgba(95,65,40,.4)';
    ellipse(ctx, 0.21 * g, 0.045 * g, 0.055 * g, 0.04 * g);
    ctx.fill();
    ctx.fillStyle = '#2b1d12';
    ellipse(ctx, 0.235 * g, 0.03 * g, 0.012 * g, 0.008 * g);
    ctx.fill();
    // Auge mit Glanzpunkt und Wimpern
    ctx.fillStyle = '#1c130c';
    ellipse(ctx, 0.07 * g, -0.012 * g, 0.022 * g, 0.018 * g);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.8)';
    ellipse(ctx, 0.077 * g, -0.018 * g, 0.006 * g, 0.006 * g);
    ctx.fill();
    ctx.strokeStyle = '#1c130c';
    ctx.lineWidth = Math.max(0.6, g * 0.005);
    for (let i = 0; i < 3; i++) linie(ctx, (0.055 + i * 0.012) * g, -0.03 * g, (0.05 + i * 0.014) * g, -0.042 * g);
    ctx.restore();
}

// ══ Affe ═════════════════════════════════════════════════════════════════
export function zeichneAffe(ctx, g, ph, laeuft, baby, frisst) {
    const fellO = baby ? '#9b7450' : '#86613f';
    const fellU = baby ? '#6f4f33' : '#5a3d25';
    const gesicht = '#e1b08c';
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const schwung = Math.sin(ph);

    const kopf = (x, y, r, blick = 1) => {
        // Fellkranz, Ohr, nacktes Gesicht mit Schnauze
        ctx.fillStyle = verlauf(ctx, y - r, y + r, fellO, fellU);
        ellipse(ctx, x, y, r, r * 0.95);
        ctx.fill();
        ctx.fillStyle = '#c99a78';
        ellipse(ctx, x - r * 0.55, y + r * 0.05, r * 0.22, r * 0.26);
        ctx.fill();
        ctx.fillStyle = '#b5866a';
        ellipse(ctx, x - r * 0.55, y + r * 0.05, r * 0.12, r * 0.15);
        ctx.fill();
        ctx.fillStyle = 'rgba(205,170,120,.55)';
        ellipse(ctx, x + r * 0.3 * blick, y + r * 0.12, r * 0.72, r * 0.72);
        ctx.fill();
        ctx.fillStyle = gesicht;
        ellipse(ctx, x + r * 0.4 * blick, y + r * 0.12, r * 0.55, r * 0.58);
        ctx.fill();
        ctx.fillStyle = '#d29f7d';
        ellipse(ctx, x + r * 0.75 * blick, y + r * 0.35, r * 0.32, r * 0.26);
        ctx.fill();
        ctx.fillStyle = 'rgba(80,45,25,.45)';
        ellipse(ctx, x + r * 0.45 * blick, y - r * 0.12, r * 0.22, r * 0.08);
        ctx.fill();
        ctx.fillStyle = '#1c130c';
        ellipse(ctx, x + r * 0.5 * blick, y - r * 0.02, r * 0.08, r * 0.09);
        ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,.75)';
        ellipse(ctx, x + r * 0.52 * blick, y - r * 0.05, r * 0.025, r * 0.025);
        ctx.fill();
        ctx.fillStyle = '#5b3a28';
        ellipse(ctx, x + r * 0.95 * blick, y + r * 0.3, r * 0.05, r * 0.04);
        ctx.fill();
        ctx.strokeStyle = '#7a4a34';
        ctx.lineWidth = Math.max(0.6, r * 0.06);
        ctx.beginPath();
        ctx.moveTo(x + r * 0.6 * blick, y + r * 0.5);
        ctx.quadraticCurveTo(x + r * 0.78 * blick, y + r * 0.56, x + r * 0.95 * blick, y + r * 0.48);
        ctx.stroke();
    };

    if (laeuft) {
        // auf allen Vieren
        const s = schwung * g * 0.08;
        ctx.strokeStyle = fellU;
        ctx.lineWidth = g * 0.04;
        ctx.beginPath();
        ctx.moveTo(-0.26 * g, -0.48 * g);
        ctx.bezierCurveTo(-0.52 * g, -0.52 * g, -0.58 * g, -0.86 * g + schwung * g * 0.04, -0.44 * g, -0.96 * g);
        ctx.stroke();
        ctx.strokeStyle = '#4f3520';
        ctx.lineWidth = g * 0.07;
        linie(ctx, 0.16 * g, -0.42 * g, 0.22 * g - s, -0.02 * g);
        ctx.beginPath();
        ctx.moveTo(-0.2 * g, -0.42 * g);
        ctx.lineTo(-0.3 * g + s, -0.22 * g);
        ctx.lineTo(-0.22 * g + s, -0.02 * g);
        ctx.stroke();
        const k = new Path2D();
        k.moveTo(0.26 * g, -0.5 * g);
        k.bezierCurveTo(0.12 * g, -0.64 * g, -0.18 * g, -0.64 * g, -0.3 * g, -0.52 * g);
        k.bezierCurveTo(-0.36 * g, -0.42 * g, -0.3 * g, -0.32 * g, -0.18 * g, -0.32 * g);
        k.bezierCurveTo(0.0, -0.3 * g, 0.18 * g, -0.32 * g, 0.26 * g, -0.4 * g);
        k.closePath();
        ctx.fillStyle = verlauf(ctx, -0.64 * g, -0.3 * g, fellO, fellU);
        ctx.fill(k);
        konturiere(ctx, k, g, 0.01);
        ctx.strokeStyle = fellU;
        ctx.lineWidth = g * 0.075;
        linie(ctx, 0.2 * g, -0.42 * g, 0.26 * g + s, -0.02 * g);
        ctx.beginPath();
        ctx.moveTo(-0.14 * g, -0.42 * g);
        ctx.lineTo(-0.24 * g - s, -0.22 * g);
        ctx.lineTo(-0.16 * g - s, -0.02 * g);
        ctx.stroke();
        ctx.fillStyle = '#3b2717';
        [[0.26 * g + s, 0], [-0.16 * g - s, 0]].forEach(([fx]) => {
            ellipse(ctx, fx + 0.02 * g, -0.015 * g, 0.04 * g, 0.018 * g);
            ctx.fill();
        });
        kopf(0.34 * g, -0.56 * g, (baby ? 0.17 : 0.13) * g);
        return;
    }

    // sitzend
    ctx.strokeStyle = fellU;
    ctx.lineWidth = g * 0.04;
    ctx.beginPath();
    ctx.moveTo(-0.14 * g, -0.1 * g);
    ctx.bezierCurveTo(-0.4 * g, -0.02 * g, -0.6 * g, -0.06 * g, -0.56 * g, -0.22 * g + Math.sin(ph * 0.5) * g * 0.03);
    ctx.stroke();
    // abgewandter Arm
    ctx.strokeStyle = '#4f3520';
    ctx.lineWidth = g * 0.065;
    linie(ctx, 0.06 * g, -0.5 * g, 0.18 * g, -0.03 * g);
    // Rumpf
    const rumpf = new Path2D();
    rumpf.moveTo(-0.16 * g, -0.08 * g);
    rumpf.bezierCurveTo(-0.24 * g, -0.3 * g, -0.12 * g, -0.6 * g, 0.02 * g, -0.62 * g);
    rumpf.bezierCurveTo(0.16 * g, -0.6 * g, 0.18 * g, -0.36 * g, 0.14 * g, -0.08 * g);
    rumpf.closePath();
    ctx.fillStyle = verlauf(ctx, -0.62 * g, -0.06 * g, fellO, fellU);
    ctx.fill(rumpf);
    konturiere(ctx, rumpf, g, 0.01);
    ctx.fillStyle = 'rgba(230,190,150,.45)';
    ellipse(ctx, 0.05 * g, -0.32 * g, 0.07 * g, 0.16 * g);
    ctx.fill();
    // Oberschenkel und Fuß
    ctx.fillStyle = fellU;
    ellipse(ctx, 0.06 * g, -0.13 * g, 0.16 * g, 0.09 * g, -0.15);
    ctx.fill();
    ctx.fillStyle = '#3b2717';
    ellipse(ctx, 0.2 * g, -0.025 * g, 0.06 * g, 0.022 * g);
    ctx.fill();
    // zugewandter Arm
    ctx.strokeStyle = fellO;
    ctx.lineWidth = g * 0.07;
    if (frisst) {
        const y = -0.62 * g + Math.sin(ph * 2) * g * 0.03;
        ctx.beginPath();
        ctx.moveTo(0.06 * g, -0.48 * g);
        ctx.lineTo(0.2 * g, -0.36 * g);
        ctx.lineTo(0.16 * g, y);
        ctx.stroke();
        ctx.strokeStyle = '#facc15';
        ctx.lineWidth = g * 0.045;
        ctx.beginPath();
        ctx.arc(0.2 * g, y - 0.04 * g, 0.06 * g, 0.4 * Math.PI, 1.1 * Math.PI);
        ctx.stroke();
    } else {
        ctx.beginPath();
        ctx.moveTo(0.06 * g, -0.48 * g);
        ctx.quadraticCurveTo(0.2 * g, -0.32 * g, 0.16 * g + schwung * g * 0.01, -0.16 * g);
        ctx.stroke();
    }
    kopf(0.05 * g, -0.74 * g, (baby ? 0.17 : 0.14) * g);
}

// ══ Pinguin (Kaiserpinguin) ══════════════════════════════════════════════
export function zeichnePinguin(ctx, g, ph, laeuft, baby) {
    const wackeln = laeuft ? Math.sin(ph) * (baby ? 0.12 : 0.09) : Math.sin(ph * 0.4) * 0.02;
    ctx.rotate(wackeln);
    ctx.lineCap = 'round';

    if (baby) {
        // Küken: graues Daunenkleid mit schwarzem Kopf und weißer Maske
        const koerper = new Path2D();
        koerper.moveTo(0, -0.62 * g);
        koerper.bezierCurveTo(0.26 * g, -0.6 * g, 0.28 * g, -0.06 * g, 0.06 * g, -0.03 * g);
        koerper.bezierCurveTo(-0.2 * g, -0.02 * g, -0.27 * g, -0.4 * g, 0, -0.62 * g);
        ctx.fillStyle = '#2d3138';
        ellipse(ctx, -0.07 * g, -0.02 * g, 0.06 * g, 0.022 * g);
        ctx.fill();
        ellipse(ctx, 0.08 * g, -0.02 * g, 0.06 * g, 0.022 * g);
        ctx.fill();
        ctx.fillStyle = verlauf(ctx, -0.62 * g, 0, '#b9bec6', '#8b9099');
        ctx.fill(koerper);
        ctx.fillStyle = 'rgba(255,255,255,.2)';
        for (let i = 0; i < 14; i++) {
            const w = (i / 14) * Math.PI * 2;
            ellipse(ctx, Math.cos(w) * 0.2 * g, -0.32 * g + Math.sin(w) * 0.26 * g, 0.03 * g, 0.03 * g);
            ctx.fill();
        }
        ctx.fillStyle = '#16191f';
        ellipse(ctx, 0.02 * g, -0.68 * g, 0.16 * g, 0.15 * g);
        ctx.fill();
        ctx.fillStyle = '#f8fafc';
        ellipse(ctx, 0.07 * g, -0.67 * g, 0.09 * g, 0.1 * g);
        ctx.fill();
        ctx.fillStyle = '#16191f';
        ellipse(ctx, 0.095 * g, -0.69 * g, 0.022 * g, 0.022 * g);
        ctx.fill();
        ctx.beginPath();
        ctx.moveTo(0.15 * g, -0.68 * g);
        ctx.lineTo(0.23 * g, -0.66 * g);
        ctx.lineTo(0.15 * g, -0.64 * g);
        ctx.fill();
        return;
    }

    // Füße
    ctx.fillStyle = '#1f232a';
    ellipse(ctx, -0.06 * g, -0.015 * g, 0.08 * g, 0.025 * g);
    ctx.fill();
    ellipse(ctx, 0.1 * g, -0.015 * g, 0.08 * g, 0.025 * g);
    ctx.fill();

    const koerper = new Path2D();
    koerper.moveTo(0.02 * g, -0.98 * g);
    koerper.bezierCurveTo(0.12 * g, -0.98 * g, 0.17 * g, -0.9 * g, 0.15 * g, -0.8 * g);
    koerper.bezierCurveTo(0.14 * g, -0.74 * g, 0.24 * g, -0.6 * g, 0.24 * g, -0.42 * g);
    koerper.bezierCurveTo(0.24 * g, -0.2 * g, 0.18 * g, -0.04 * g, 0.04 * g, -0.03 * g);
    koerper.bezierCurveTo(-0.12 * g, -0.03 * g, -0.21 * g, -0.18 * g, -0.21 * g, -0.42 * g);
    koerper.bezierCurveTo(-0.21 * g, -0.68 * g, -0.12 * g, -0.98 * g, 0.02 * g, -0.98 * g);
    ctx.fillStyle = verlauf(ctx, -0.98 * g, 0, '#2b313b', '#0e1218');
    ctx.fill(koerper);

    // Bauch mit gelblichem Brustansatz
    const bauch = new Path2D();
    bauch.moveTo(0.12 * g, -0.74 * g);
    bauch.bezierCurveTo(0.24 * g, -0.6 * g, 0.24 * g, -0.2 * g, 0.12 * g, -0.06 * g);
    bauch.bezierCurveTo(0.02 * g, -0.04 * g, -0.06 * g, -0.12 * g, -0.06 * g, -0.3 * g);
    bauch.bezierCurveTo(-0.06 * g, -0.5 * g, 0.02 * g, -0.7 * g, 0.12 * g, -0.74 * g);
    const bauchVerlauf = ctx.createLinearGradient(0, -0.74 * g, 0, -0.05 * g);
    bauchVerlauf.addColorStop(0, '#fde9a8');
    bauchVerlauf.addColorStop(0.25, '#fffaf0');
    bauchVerlauf.addColorStop(1, '#e8e4dc');
    ctx.fillStyle = bauchVerlauf;
    ctx.fill(bauch);
    konturiere(ctx, koerper, g, 0.01);

    // Ohrfleck (orange-gelb), Auge, Schnabel
    const fleck = ctx.createLinearGradient(0, -0.86 * g, 0, -0.66 * g);
    fleck.addColorStop(0, '#f59e0b');
    fleck.addColorStop(1, 'rgba(253,230,138,.2)');
    ctx.fillStyle = fleck;
    ctx.beginPath();
    ctx.moveTo(0.02 * g, -0.86 * g);
    ctx.quadraticCurveTo(0.1 * g, -0.84 * g, 0.12 * g, -0.72 * g);
    ctx.quadraticCurveTo(0.04 * g, -0.74 * g, 0.02 * g, -0.86 * g);
    ctx.fill();
    ctx.fillStyle = '#0b0d11';
    ellipse(ctx, 0.08 * g, -0.88 * g, 0.016 * g, 0.014 * g);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.7)';
    ellipse(ctx, 0.084 * g, -0.884 * g, 0.005 * g, 0.005 * g);
    ctx.fill();
    ctx.fillStyle = '#111318';
    ctx.beginPath();
    ctx.moveTo(0.14 * g, -0.9 * g);
    ctx.quadraticCurveTo(0.24 * g, -0.88 * g, 0.31 * g, -0.82 * g);
    ctx.lineTo(0.15 * g, -0.84 * g);
    ctx.fill();
    ctx.fillStyle = '#f08a5d';
    ctx.beginPath();
    ctx.moveTo(0.15 * g, -0.845 * g);
    ctx.lineTo(0.29 * g, -0.825 * g);
    ctx.lineTo(0.15 * g, -0.825 * g);
    ctx.fill();

    // Flügel (Flosse) am Körper, beim Watscheln leicht abgespreizt
    ctx.save();
    ctx.translate(-0.02 * g, -0.66 * g);
    ctx.rotate(0.12 + (laeuft ? Math.abs(Math.sin(ph)) * 0.35 : 0));
    const fluegel = new Path2D();
    fluegel.moveTo(0, 0);
    fluegel.quadraticCurveTo(-0.1 * g, 0.18 * g, -0.06 * g, 0.42 * g);
    fluegel.quadraticCurveTo(0.0, 0.3 * g, 0.04 * g, 0.02 * g);
    fluegel.closePath();
    ctx.fillStyle = '#151a21';
    ctx.fill(fluegel);
    ctx.strokeStyle = 'rgba(255,255,255,.18)';
    ctx.lineWidth = Math.max(0.6, g * 0.008);
    ctx.stroke(fluegel);
    ctx.restore();

    // Glanz auf dem Rücken
    ctx.strokeStyle = 'rgba(255,255,255,.14)';
    ctx.lineWidth = Math.max(1, g * 0.02);
    ctx.beginPath();
    ctx.arc(0.0, -0.5 * g, 0.18 * g, 1.1 * Math.PI, 1.45 * Math.PI);
    ctx.stroke();
}

// ══ Tierpfleger:in ═══════════════════════════════════════════════════════
export function zeichnePfleger(ctx, gp, f, futterFarbe) {
    const geht = f.zustand !== 'wirft';
    const schwung = geht ? Math.sin(f.phase) : 0;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Schatten
    const sch = ctx.createRadialGradient(0, 0, 0, 0, 0, gp * 0.25);
    sch.addColorStop(0, 'rgba(0,0,0,.22)');
    sch.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = sch;
    ellipse(ctx, 0, 0, gp * 0.25, gp * 0.05);
    ctx.fill();

    // Beine mit Knie (zwei Segmente), Stiefel
    const bein = (richtung, farbe) => {
        const huefte = [0, -gp * 0.47];
        const knie = [richtung * gp * 0.07, -gp * 0.24];
        const fuss = [richtung * gp * 0.12, -gp * 0.02];
        ctx.strokeStyle = farbe;
        ctx.lineWidth = gp * 0.085;
        ctx.beginPath();
        ctx.moveTo(...huefte);
        ctx.lineTo(...knie);
        ctx.lineTo(...fuss);
        ctx.stroke();
        ctx.fillStyle = '#4a3222';
        ctx.beginPath();
        ctx.moveTo(fuss[0] - gp * 0.04, fuss[1] - gp * 0.05);
        ctx.lineTo(fuss[0] + gp * 0.08, fuss[1] - gp * 0.02);
        ctx.lineTo(fuss[0] + gp * 0.08, fuss[1] + gp * 0.015);
        ctx.lineTo(fuss[0] - gp * 0.04, fuss[1] + gp * 0.015);
        ctx.fill();
    };
    bein(-schwung, '#3d4a2e');

    // hinterer Arm mit Eimer
    const eimerX = -gp * 0.1 - schwung * gp * 0.06;
    ctx.strokeStyle = '#2f6b3a';
    ctx.lineWidth = gp * 0.065;
    linie(ctx, -gp * 0.03, -gp * 0.76, eimerX, -gp * 0.5);
    ctx.fillStyle = '#e0ac7e';
    ellipse(ctx, eimerX, -gp * 0.49, gp * 0.03, gp * 0.03);
    ctx.fill();
    ctx.strokeStyle = '#6b7280';
    ctx.lineWidth = Math.max(1, gp * 0.012);
    ctx.beginPath();
    ctx.arc(eimerX, -gp * 0.45, gp * 0.08, Math.PI * 1.15, Math.PI * 1.85);
    ctx.stroke();
    const eimer = ctx.createLinearGradient(eimerX - gp * 0.09, 0, eimerX + gp * 0.09, 0);
    eimer.addColorStop(0, '#9ca3af');
    eimer.addColorStop(0.4, '#e5e7eb');
    eimer.addColorStop(1, '#6b7280');
    ctx.fillStyle = eimer;
    ctx.beginPath();
    ctx.moveTo(eimerX - gp * 0.09, -gp * 0.46);
    ctx.lineTo(eimerX + gp * 0.09, -gp * 0.46);
    ctx.lineTo(eimerX + gp * 0.07, -gp * 0.28);
    ctx.lineTo(eimerX - gp * 0.07, -gp * 0.28);
    ctx.fill();
    ctx.fillStyle = futterFarbe;
    ellipse(ctx, eimerX, -gp * 0.46, gp * 0.088, gp * 0.024);
    ctx.fill();

    // Rumpf (Hemd mit Brusttasche und Gürtel)
    const hemd = ctx.createLinearGradient(-gp * 0.13, 0, gp * 0.13, 0);
    hemd.addColorStop(0, '#2f6b3a');
    hemd.addColorStop(0.55, '#3f8a4c');
    hemd.addColorStop(1, '#2c6437');
    ctx.fillStyle = hemd;
    ctx.beginPath();
    ctx.moveTo(-gp * 0.12, -gp * 0.8);
    ctx.quadraticCurveTo(0, -gp * 0.84, gp * 0.12, -gp * 0.8);
    ctx.lineTo(gp * 0.11, -gp * 0.47);
    ctx.lineTo(-gp * 0.11, -gp * 0.47);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = '#3d4a2e';
    ctx.fillRect(-gp * 0.11, -gp * 0.5, gp * 0.22, gp * 0.04);
    ctx.fillStyle = '#c9a227';
    ctx.fillRect(gp * 0.02, -gp * 0.5, gp * 0.04, gp * 0.04);
    ctx.strokeStyle = 'rgba(0,0,0,.25)';
    ctx.lineWidth = Math.max(0.6, gp * 0.008);
    ctx.strokeRect(gp * 0.02, -gp * 0.72, gp * 0.07, gp * 0.06);
    ctx.fillStyle = '#fef08a';
    ellipse(ctx, gp * 0.055, -gp * 0.69, gp * 0.015, gp * 0.015);
    ctx.fill();

    bein(schwung, '#4b5e3a');

    // Hals, Kopf, Haare, Kappe
    ctx.fillStyle = f.haut;
    ctx.fillRect(-gp * 0.03, -gp * 0.86, gp * 0.06, gp * 0.06);
    if (f.pflegerin) {
        ctx.fillStyle = f.haar;
        ellipse(ctx, -gp * 0.1, -gp * 0.88 + Math.sin(f.phase) * gp * 0.01, gp * 0.045, gp * 0.08, 0.5);
        ctx.fill();
    }
    ctx.fillStyle = f.haut;
    ellipse(ctx, gp * 0.01, -gp * 0.93, gp * 0.08, gp * 0.095);
    ctx.fill();
    ctx.fillStyle = f.haar;
    ellipse(ctx, -gp * 0.04, -gp * 0.95, gp * 0.06, gp * 0.06);
    ctx.fill();
    ctx.fillStyle = '#2f6b3a';
    ctx.beginPath();
    ctx.arc(gp * 0.0, -gp * 0.97, gp * 0.085, Math.PI * 1.02, -0.05);
    ctx.fill();
    ctx.fillStyle = '#245530';
    ctx.beginPath();
    ctx.ellipse(gp * 0.1, -gp * 0.975, gp * 0.07, gp * 0.016, 0.1, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#c9a227';
    ellipse(ctx, gp * 0.02, -gp * 1.0, gp * 0.018, gp * 0.014);
    ctx.fill();
    // Gesicht
    ctx.fillStyle = '#1f2937';
    ellipse(ctx, gp * 0.055, -gp * 0.935, gp * 0.01, gp * 0.012);
    ctx.fill();
    ctx.fillStyle = 'rgba(0,0,0,.18)';
    ellipse(ctx, gp * 0.085, -gp * 0.915, gp * 0.012, gp * 0.018);
    ctx.fill();
    ctx.strokeStyle = '#7c2d12';
    ctx.lineWidth = Math.max(0.6, gp * 0.008);
    ctx.beginPath();
    ctx.arc(gp * 0.055, -gp * 0.885, gp * 0.022, 0.15 * Math.PI, 0.75 * Math.PI);
    ctx.stroke();

    // vorderer Arm: schwingt beim Gehen, wirft beim Füttern
    const wurf = f.zustand === 'wirft' && f.zeit > 0.2 && f.zeit < 1.8;
    const schulter = [gp * 0.03, -gp * 0.76];
    const hand = wurf ? [gp * 0.2, -gp * 1.02] : [gp * 0.07 + schwung * gp * 0.1, -gp * 0.5];
    const ellbogen = wurf ? [gp * 0.14, -gp * 0.86] : [gp * 0.05 + schwung * gp * 0.05, -gp * 0.62];
    ctx.strokeStyle = '#3f8a4c';
    ctx.lineWidth = gp * 0.065;
    ctx.beginPath();
    ctx.moveTo(...schulter);
    ctx.lineTo(...ellbogen);
    ctx.stroke();
    ctx.strokeStyle = f.haut;
    ctx.lineWidth = gp * 0.05;
    ctx.beginPath();
    ctx.moveTo(...ellbogen);
    ctx.lineTo(...hand);
    ctx.stroke();
}
