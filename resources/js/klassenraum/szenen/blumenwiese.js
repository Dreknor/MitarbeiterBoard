/*
 * Szene „Blumenwiese“: Sonne, blühende Blumen und Schmetterlinge. Steigt die Unruhe,
 * schieben sich graue Wolken vor die Sonne, die Blüten schließen sich nacheinander,
 * die Schmetterlinge fliegen davon und es beginnt zu regnen.
 */

import {ellipse, glatt, klemme, mische, mischeFarbe, mitAlpha, zufall, zufallAus, zufallMitSaat} from '../shared/hilfen';

const BLUETEN = ['#f43f5e', '#fbbf24', '#a855f7', '#f97316', '#ec4899', '#ffffff', '#60a5fa', '#facc15'];
const FALTER = [['#f97316', '#1f2937'], ['#38bdf8', '#0c4a6e'], ['#facc15', '#78350f'], ['#f472b6', '#831843'], ['#a3e635', '#365314']];

export function erzeugeBlumenwiese() {
    let b = 0;
    let h = 0;
    let blumen = [];
    let halme = [];
    let wolken = [];
    const falter = [];
    const tropfen = [];
    let t = 0;
    let regenRest = 0;

    const wiese = (x, basis, phase) => basis + Math.sin(x * 0.005 + phase) * h * 0.025 + Math.sin(x * 0.013 + phase * 3) * h * 0.012;

    const kopf = (bl) => {
        const neigung = Math.sin(t * 1.1 + bl.phase) * bl.wind;
        return {
            x: bl.x + neigung,
            y: bl.fussY - bl.stiel * (0.92 + bl.offen * 0.08),
        };
    };

    const neuerFalter = (farben) => {
        const f = {
            farben,
            x: zufall(0, b),
            y: zufall(h * 0.3, h * 0.6),
            vx: 0,
            vy: 0,
            phase: zufall(0, 6),
            groesse: klemme(Math.min(b, h) * zufall(0.022, 0.034), 12, 34),
            ziel: null,
            sitzt: 0,
            mut: zufall(0.25, 0.6),
            weg: false,
        };
        return f;
    };

    function groesse(breite, hoehe, einstellungen) {
        const neu = breite !== b || hoehe !== h;
        b = breite;
        h = hoehe;
        const faktor = einstellungen.menge / 100;

        if (neu || blumen.length !== Math.round(30 * faktor)) {
            const zz = zufallMitSaat(5);
            blumen = Array.from({length: Math.round(30 * faktor)}, () => {
                const tiefe = zz.zwischen(0, 1); // 0 = hinten, 1 = vorne
                const x = zz.zwischen(0.02, 0.98) * b;
                const skalierung = mische(0.55, 1.15, tiefe);
                return {
                    x,
                    tiefe,
                    fussY: mische(h * 0.68, h * 1.0, tiefe),
                    stiel: Math.min(h, b) * zz.zwischen(0.14, 0.22) * skalierung,
                    radius: Math.min(h, b) * zz.zwischen(0.03, 0.045) * skalierung,
                    farbe: zz.aus(BLUETEN),
                    mitte: zz.aus(['#facc15', '#f59e0b', '#78350f']),
                    blaetter: zz.ganz(5, 8),
                    phase: zz.zwischen(0, 6.3),
                    mut: zz.zwischen(0.2, 0.75),
                    offen: 1,
                    wind: 4,
                };
            }).sort((a, c) => a.tiefe - c.tiefe);

            halme = Array.from({length: Math.round(b / 7)}, () => ({
                x: zz.zwischen(0, b),
                hoehe: h * zz.zwischen(0.03, 0.08),
                phase: zz.zwischen(0, 6),
                farbe: zz.aus(['#4d7c0f', '#65a30d', '#3f6212', '#84cc16']),
            }));

            wolken = Array.from({length: 7}, (_, i) => ({
                ziel: zz.zwischen(0.0, 1.0),
                y: zz.zwischen(0.04, 0.32),
                groesse: zz.zwischen(0.14, 0.24),
                start: i % 2 === 0 ? zz.zwischen(-0.6, -0.35) : zz.zwischen(1.35, 1.6),
                teile: Array.from({length: 6}, () => [zz.zwischen(-0.5, 0.5), zz.zwischen(-0.1, 0.12), zz.zwischen(0.2, 0.34)]),
                hell: i < 2,
            }));
        }

        const anzahl = Math.round(6 * faktor);
        while (falter.length > anzahl) falter.pop();
        while (falter.length < anzahl) falter.push(neuerFalter(FALTER[falter.length % FALTER.length]));
    }

    function schritt(dt, z) {
        t += dt;

        blumen.forEach((bl) => {
            const ziel = z.unruhe > bl.mut ? 0 : (z.unruhe < bl.mut - 0.15 ? 1 : bl.offen > 0.5 ? 1 : 0);
            bl.offen = klemme(bl.offen + klemme(ziel - bl.offen, -dt * 1.2, dt * 0.5), 0, 1);
            bl.wind = 4 + z.unruhe * 16;
        });

        falter.forEach((f) => {
            if (!f.weg && z.unruhe > f.mut) {
                f.weg = true;
                f.ziel = {x: f.x < b / 2 ? -b * 0.15 : b * 1.15, y: zufall(-h * 0.1, h * 0.3)};
                f.sitzt = 0;
            } else if (f.weg && z.unruhe < f.mut - 0.2) {
                f.weg = false;
                f.ziel = null;
            }

            if (f.sitzt > 0) {
                f.sitzt -= dt;
                if (f.ziel?.blume) {
                    const k = kopf(f.ziel.blume);
                    f.x = k.x;
                    f.y = k.y - f.ziel.blume.radius * 0.3;
                    if (f.ziel.blume.offen < 0.5) f.sitzt = 0;
                }
                f.phase += dt * 2;
                if (f.sitzt <= 0) f.ziel = null;
                return;
            }

            if (!f.ziel) {
                const offen = blumen.filter((bl) => bl.offen > 0.8);
                if (offen.length && Math.random() < 0.6) {
                    f.ziel = {blume: zufallAus(offen)};
                } else {
                    f.ziel = {x: zufall(b * 0.05, b * 0.95), y: zufall(h * 0.25, h * 0.65)};
                }
            }

            let zx;
            let zy;
            if (f.ziel.blume) {
                const k = kopf(f.ziel.blume);
                zx = k.x;
                zy = k.y - f.ziel.blume.radius * 0.3;
            } else {
                zx = f.ziel.x;
                zy = f.ziel.y;
            }

            const dx = zx - f.x;
            const dy = zy - f.y;
            const abstand = Math.hypot(dx, dy) || 1;
            const geschw = f.weg ? 260 : 90;
            f.vx += ((dx / abstand) * geschw - f.vx) * Math.min(1, dt * 1.6);
            f.vy += ((dy / abstand) * geschw - f.vy) * Math.min(1, dt * 1.6);
            f.x += (f.vx + Math.sin(t * 3 + f.phase) * 30) * dt;
            f.y += (f.vy + Math.cos(t * 4.3 + f.phase) * 40) * dt;
            f.phase += dt * (f.weg ? 30 : 18);

            if (abstand < 12 && !f.weg) {
                if (f.ziel.blume) f.sitzt = zufall(2, 5);
                else f.ziel = null;
            }
        });

        // Regen
        const regenRate = Math.pow(glatt((z.unruhe - 0.35) / 0.65), 1.4) * 420;
        regenRest += regenRate * dt;
        while (regenRest > 1) {
            regenRest -= 1;
            tropfen.push({x: zufall(-0.1, 1.05) * b, y: zufall(-h * 0.1, 0), tempo: zufall(700, 1000), laenge: zufall(10, 20)});
        }
        for (let i = tropfen.length - 1; i >= 0; i--) {
            const tr = tropfen[i];
            tr.y += tr.tempo * dt;
            tr.x += tr.tempo * 0.12 * dt;
            if (tr.y > h) tropfen.splice(i, 1);
        }
        if (tropfen.length > 600) tropfen.splice(0, tropfen.length - 600);
    }

    function zeichneWolke(ctx, w, faktor, grau) {
        const groesse = w.groesse * Math.max(b, h * 1.4);
        const x = (w.hell ? w.ziel + Math.sin(t * 0.02 + w.ziel * 9) * 0.05 : mische(w.start, w.ziel, faktor)) * b;
        const y = w.y * h;
        w.teile.forEach(([dx, dy, r]) => {
            ctx.fillStyle = mischeFarbe('#ffffff', '#7b8794', grau, w.hell ? 0.9 : 0.96);
            ellipse(ctx, x + dx * groesse, y + dy * groesse, r * groesse, r * groesse * 0.6);
            ctx.fill();
        });
    }

    function zeichneBlume(ctx, bl, gewitter) {
        const k = kopf(bl);
        const o = glatt(bl.offen);

        // Stiel
        ctx.strokeStyle = mischeFarbe('#3f8f2a', '#36603a', gewitter);
        ctx.lineWidth = Math.max(2, bl.radius * 0.18);
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(bl.x, bl.fussY);
        ctx.quadraticCurveTo(bl.x + (k.x - bl.x) * 0.2, bl.fussY - bl.stiel * 0.5, k.x, k.y);
        ctx.stroke();
        // Blatt
        ctx.fillStyle = mischeFarbe('#4caf50', '#3d6b42', gewitter);
        const by = bl.fussY - bl.stiel * 0.35;
        ellipse(ctx, bl.x + bl.radius * 0.5, by, bl.radius * 0.7, bl.radius * 0.25, -0.5);
        ctx.fill();

        // Blüte
        const n = bl.blaetter;
        const laenge = bl.radius * mische(0.75, 1, o);
        for (let i = 0; i < n; i++) {
            const offenWinkel = (i / n) * Math.PI * 2 + bl.phase;
            const zuWinkel = -Math.PI / 2 + (i - (n - 1) / 2) * 0.16;
            const w = mische(zuWinkel, offenWinkel, o);
            const abstand = laenge * mische(0.32, 0.55, o);
            ctx.fillStyle = mischeFarbe(bl.farbe, '#6b7280', gewitter * 0.35);
            ellipse(ctx, k.x + Math.cos(w) * abstand, k.y + Math.sin(w) * abstand, laenge * 0.55, laenge * mische(0.2, 0.3, o), w);
            ctx.fill();
        }
        // Kelchblätter am geschlossenen Knospen-Fuß
        if (o < 0.6) {
            ctx.fillStyle = mitAlpha('#4caf50', 1 - o / 0.6);
            ellipse(ctx, k.x, k.y + laenge * 0.05, laenge * 0.3, laenge * 0.2);
            ctx.fill();
        }
        // Blütenmitte
        if (o > 0.05) {
            ctx.fillStyle = bl.mitte;
            ellipse(ctx, k.x, k.y, bl.radius * 0.3 * o, bl.radius * 0.3 * o);
            ctx.fill();
        }
    }

    function zeichneFalter(ctx, f) {
        const schlag = 0.2 + Math.abs(Math.sin(f.phase)) * 0.8;
        const g = f.groesse;
        ctx.save();
        ctx.translate(f.x, f.y);
        ctx.rotate(klemme(f.vx / 400, -0.4, 0.4));
        [-1, 1].forEach((seite) => {
            ctx.save();
            ctx.scale(seite * schlag, 1);
            ctx.fillStyle = f.farben[0];
            ellipse(ctx, g * 0.5, -g * 0.3, g * 0.55, g * 0.42, -0.4);
            ctx.fill();
            ellipse(ctx, g * 0.38, g * 0.28, g * 0.38, g * 0.3, 0.5);
            ctx.fill();
            ctx.fillStyle = f.farben[1];
            ellipse(ctx, g * 0.65, -g * 0.38, g * 0.13, g * 0.13);
            ctx.fill();
            ctx.restore();
        });
        ctx.fillStyle = '#1f2937';
        ellipse(ctx, 0, 0, g * 0.09, g * 0.42);
        ctx.fill();
        ctx.strokeStyle = '#1f2937';
        ctx.lineWidth = 1.2;
        ctx.beginPath();
        ctx.moveTo(0, -g * 0.38);
        ctx.quadraticCurveTo(-g * 0.15, -g * 0.7, -g * 0.25, -g * 0.75);
        ctx.moveTo(0, -g * 0.38);
        ctx.quadraticCurveTo(g * 0.15, -g * 0.7, g * 0.25, -g * 0.75);
        ctx.stroke();
        ctx.restore();
    }

    function zeichne(ctx, breite, hoehe, z) {
        const gewitter = glatt(z.unruhe);

        const himmel = ctx.createLinearGradient(0, 0, 0, hoehe * 0.75);
        himmel.addColorStop(0, mischeFarbe('#4fb3ea', '#5d6874', gewitter));
        himmel.addColorStop(1, mischeFarbe('#d6f0ff', '#a3adb7', gewitter));
        ctx.fillStyle = himmel;
        ctx.fillRect(0, 0, breite, hoehe);

        // Sonne
        const sr = Math.min(breite, hoehe) * 0.075;
        const sx = breite * 0.16;
        const sy = hoehe * 0.18;
        const sonneAlpha = 1 - gewitter * 0.85;
        ctx.save();
        ctx.translate(sx, sy);
        ctx.rotate(t * 0.05);
        ctx.strokeStyle = `rgba(255,214,90,${0.6 * sonneAlpha})`;
        ctx.lineWidth = Math.max(3, sr * 0.08);
        ctx.lineCap = 'round';
        for (let i = 0; i < 12; i++) {
            const w = (i / 12) * Math.PI * 2;
            const puls = 1 + Math.sin(t * 1.5 + i) * 0.06;
            ctx.beginPath();
            ctx.moveTo(Math.cos(w) * sr * 1.3, Math.sin(w) * sr * 1.3);
            ctx.lineTo(Math.cos(w) * sr * 1.75 * puls, Math.sin(w) * sr * 1.75 * puls);
            ctx.stroke();
        }
        ctx.restore();
        const schein = ctx.createRadialGradient(sx, sy, sr * 0.5, sx, sy, sr * 3.5);
        schein.addColorStop(0, `rgba(255,236,150,${0.45 * sonneAlpha})`);
        schein.addColorStop(1, 'rgba(255,236,150,0)');
        ctx.fillStyle = schein;
        ctx.fillRect(sx - sr * 4, sy - sr * 4, sr * 8, sr * 8);
        ctx.fillStyle = mischeFarbe('#ffd23f', '#c9c3a0', gewitter);
        ellipse(ctx, sx, sy, sr, sr);
        ctx.fill();

        // Wolken (helle Schönwetterwolken immer, graue bei Unruhe)
        const faktor = glatt(z.unruhe * 1.15);
        wolken.forEach((w) => {
            if (w.hell || faktor > 0.001) zeichneWolke(ctx, w, faktor, w.hell ? gewitter : 0.55 + gewitter * 0.3);
        });

        // Hügel
        [[0.6, '#a5d66b', '#7e9670', 0.4], [0.66, '#7cbf4a', '#66805a', 1.7], [0.72, '#5fa83a', '#526d48', 2.9]].forEach(([basis, hell, dunkel, phase]) => {
            ctx.fillStyle = mischeFarbe(hell, dunkel, gewitter);
            ctx.beginPath();
            ctx.moveTo(0, hoehe);
            for (let x = 0; x <= breite + 20; x += 20) ctx.lineTo(x, wiese(x, hoehe * basis, phase));
            ctx.lineTo(breite, hoehe);
            ctx.fill();
        });

        // Blumen von hinten nach vorne
        blumen.forEach((bl) => zeichneBlume(ctx, bl, gewitter));

        // Grashalme vorne
        halme.forEach((g) => {
            const wind = Math.sin(t * 1.4 + g.phase) * (3 + z.unruhe * 10);
            ctx.strokeStyle = mischeFarbe(g.farbe, '#3d5233', gewitter * 0.6);
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(g.x, hoehe + 2);
            ctx.quadraticCurveTo(g.x + wind * 0.3, hoehe - g.hoehe * 0.5, g.x + wind, hoehe - g.hoehe);
            ctx.stroke();
        });

        falter.forEach((f) => zeichneFalter(ctx, f));

        // Regen
        if (tropfen.length) {
            ctx.strokeStyle = 'rgba(225,235,255,0.55)';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            tropfen.forEach((tr) => {
                ctx.moveTo(tr.x, tr.y);
                ctx.lineTo(tr.x - tr.laenge * 0.12, tr.y - tr.laenge);
            });
            ctx.stroke();
        }

        if (gewitter > 0.01) {
            ctx.fillStyle = `rgba(40,48,60,${0.22 * gewitter})`;
            ctx.fillRect(0, 0, breite, hoehe);
        }
    }

    return {
        mengeLabel: 'Anzahl der Blumen',
        texte: {
            ruhig: 'Sonnenschein – die Blumen blühen.',
            fast: 'Etwas laut – die Blumen werden unruhig.',
            laut: 'Zu laut – die Blüten schließen sich und es regnet!',
        },
        groesse,
        schritt,
        zeichne,
        info: () => `${blumen.filter((bl) => bl.offen > 0.5).length} von ${blumen.length} Blüten offen`,
    };
}
