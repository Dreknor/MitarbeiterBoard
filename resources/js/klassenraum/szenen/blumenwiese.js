/*
 * Szene „Blumenwiese“: Sonne, blühende Blumen und Schmetterlinge. Steigt die Unruhe,
 * schieben sich graue Wolken vor die Sonne, die Blüten schließen sich nacheinander,
 * die Schmetterlinge fliegen davon und es beginnt zu regnen.
 *
 * Landschaft: großer Baum (wiegt bei Unruhe stärker und verliert Blätter), Bäume in der Ferne,
 * Büsche mit Beeren, Holzzaun und Fliegenpilze.
 *
 * Seltene Besuche (nur bei Ruhe): Igel (rollt sich bei Lärm ein), Hase, Vogelschwarm.
 * Wird es nach einem Regen wieder ruhig, erscheint ein Regenbogen.
 */

import {ellipse, glatt, klemme, mische, mischeFarbe, mitAlpha, zufall, zufallAus, zufallMitSaat} from '../shared/hilfen';
import {Besuchsplaner, blende} from '../shared/besuche';
import {Klassenmaus, mausBesuch} from '../shared/maus';

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
    let fernBaeume = [];
    let buesche = [];
    let pilze = [];
    let krone = [];
    let aepfel = [];
    const blaetter = [];
    let blattRest = 0;

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

            // Landschaft (reproduzierbar, damit sie nach einer Größenänderung gleich bleibt)
            const deko = zufallMitSaat(21);
            fernBaeume = [0.05, 0.27, 0.33, 0.47, 0.58].map((anteil) => ({
                x: (anteil + deko.zwischen(-0.02, 0.02)) * b,
                groesse: Math.min(b, h) * deko.zwischen(0.035, 0.055),
                ton: deko.aus(['#86b860', '#7aae58', '#94c46c']),
            }));
            buesche = [0.15, 0.42, 0.7].map((anteil) => ({
                x: (anteil + deko.zwischen(-0.03, 0.03)) * b,
                groesse: Math.min(b, h) * deko.zwischen(0.05, 0.075),
                beeren: Array.from({length: 6}, () => [deko.zwischen(-0.8, 0.8), deko.zwischen(-0.9, -0.2)]),
                beerenFarbe: deko.aus(['#dc2626', '#7c3aed', '#f97316']),
            }));
            pilze = [0.31, 0.36, 0.61].map((anteil) => ({
                x: (anteil + deko.zwischen(-0.02, 0.02)) * b,
                groesse: Math.min(b, h) * deko.zwischen(0.022, 0.034),
            }));
            krone = Array.from({length: 16}, (_, i) => {
                const w = (i / 16) * Math.PI * 2;
                const abstand = i < 6 ? deko.zwischen(0.05, 0.25) : deko.zwischen(0.3, 0.55);
                return {dx: Math.cos(w) * abstand * 1.15, dy: Math.sin(w) * abstand * 0.8, r: deko.zwischen(0.22, 0.34), phase: deko.zwischen(0, 6)};
            });
            aepfel = Array.from({length: 7}, () => [deko.zwischen(-0.55, 0.55), deko.zwischen(-0.35, 0.4)]);

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

    // ── Seltene Besuche ──────────────────────────────────────────────────
    let regnete = false;

    function neuerRegenbogen() {
        const rb = {zeit: 0, dauer: 40, alpha: 0};
        let flucht = false;
        return {
            ebene: 'regenbogen',
            schritt(dt, z) {
                rb.zeit += dt;
                if (z.unruhe > 0.4) flucht = true;
                const ziel = flucht || rb.zeit > rb.dauer ? 0 : 1;
                rb.alpha = blende(rb.alpha, ziel, dt, flucht ? 0.8 : 0.2);
                return !(ziel === 0 && rb.alpha <= 0.005);
            },
            zeichne(ctx) {
                const radius = Math.min(b * 0.4, h * 0.6);
                const mx = b * 0.64;
                const my = h * 0.74;
                const breiteBand = radius * 0.035;
                ['#ef4444', '#f97316', '#facc15', '#22c55e', '#3b82f6', '#6366f1', '#a855f7'].forEach((farbe, i) => {
                    ctx.strokeStyle = mitAlpha(farbe, 0.42 * rb.alpha);
                    ctx.lineWidth = breiteBand;
                    ctx.beginPath();
                    ctx.arc(mx, my, radius - i * breiteBand, Math.PI, Math.PI * 2);
                    ctx.stroke();
                });
            },
        };
    }

    function neuerIgel() {
        const g = klemme(Math.min(b, h) * 0.12, 50, 130);
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const ig = {x: richtung > 0 ? -g : b + g, phase: 0, rund: 0, alpha: 1};
        return {
            ebene: 'vorne',
            schritt(dt, z) {
                const einrollen = z.unruhe > 0.45;
                ig.rund = blende(ig.rund, einrollen ? 1 : 0, dt, 2.5);
                if (ig.rund < 0.05) {
                    ig.x += richtung * g * 0.55 * dt;
                    ig.phase += dt * 6;
                }
                return richtung > 0 ? ig.x < b + g * 1.5 : ig.x > -g * 1.5;
            },
            zeichne(ctx) {
                const y = h * 0.975;
                ctx.translate(ig.x, y);
                ctx.scale(richtung, 1);
                const rund = glatt(ig.rund);
                if (rund < 0.98) {
                    // Beine
                    ctx.fillStyle = '#5b4330';
                    [-0.25, -0.05, 0.12, 0.28].forEach((bx, i) => {
                        const schritt = Math.sin(ig.phase + i * Math.PI / 2) * g * 0.04;
                        ellipse(ctx, bx * g + schritt, -g * 0.03, g * 0.05, g * 0.06);
                        ctx.fill();
                    });
                }
                // Stacheln
                const zacken = 13;
                const rx = mische(g * 0.42, g * 0.3, rund);
                const ry = mische(g * 0.3, g * 0.3, rund);
                const cy = -g * mische(0.13, 0.3, rund);
                ctx.fillStyle = '#6b4f36';
                ctx.beginPath();
                const bogenEnde = mische(Math.PI, Math.PI * 2, rund);
                for (let i = 0; i <= zacken; i++) {
                    const w = Math.PI + (i / zacken) * bogenEnde - (rund > 0.5 ? Math.PI * 0.5 * rund : 0);
                    const aussen = i % 2 === 0 ? 1.18 : 1;
                    const px = Math.cos(w) * rx * aussen;
                    const py = cy + Math.sin(w) * ry * aussen;
                    i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
                }
                ctx.closePath();
                ctx.fill();
                ctx.fillStyle = '#8a6a4b';
                ellipse(ctx, 0, cy, rx * 0.9, ry * 0.85);
                ctx.fill();
                ctx.strokeStyle = 'rgba(60,40,25,.6)';
                ctx.lineWidth = Math.max(1, g * 0.02);
                for (let i = 0; i < 9; i++) {
                    const w = Math.PI * 1.05 + (i / 8) * Math.PI * 0.9;
                    ctx.beginPath();
                    ctx.moveTo(Math.cos(w) * rx * 0.4, cy + Math.sin(w) * ry * 0.4);
                    ctx.lineTo(Math.cos(w) * rx * 0.95, cy + Math.sin(w) * ry * 0.95);
                    ctx.stroke();
                }
                if (rund < 0.6) {
                    // Gesicht
                    ctx.globalAlpha = 1 - rund / 0.6;
                    ctx.fillStyle = '#d8b48a';
                    ctx.beginPath();
                    ctx.moveTo(rx * 0.75, cy - ry * 0.35);
                    ctx.quadraticCurveTo(rx * 1.5, -g * 0.08, rx * 1.35, -g * 0.03);
                    ctx.quadraticCurveTo(rx * 0.9, 0, rx * 0.6, -g * 0.02);
                    ctx.fill();
                    ctx.fillStyle = '#1f2937';
                    ellipse(ctx, rx * 1.36, -g * 0.05, g * 0.03, g * 0.03);
                    ctx.fill();
                    ellipse(ctx, rx * 1.0, cy - ry * 0.05, g * 0.025, g * 0.025);
                    ctx.fill();
                }
            },
        };
    }

    function neuerHase() {
        const g = klemme(Math.min(b, h) * 0.1, 44, 120);
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const hs = {x: richtung > 0 ? -g : b + g, sprung: 0, pause: 0, ohren: 0};
        let flucht = false;
        return {
            ebene: 'wiese',
            schritt(dt, z) {
                if (z.unruhe > 0.4) flucht = true;
                if (hs.pause > 0 && !flucht) {
                    hs.pause -= dt;
                    hs.ohren += dt * 4;
                } else {
                    hs.sprung += dt * (flucht ? 3 : 1.6);
                    hs.x += richtung * g * (flucht ? 4 : 1.6) * dt;
                    if (hs.sprung >= 1) {
                        hs.sprung = 0;
                        if (!flucht && Math.random() < 0.3) hs.pause = zufall(1.5, 3.5);
                    }
                }
                return richtung > 0 ? hs.x < b + g * 2 : hs.x > -g * 2;
            },
            zeichne(ctx) {
                const boden = wiese(hs.x, h * 0.72, 2.9) + g * 0.05;
                const hoch = Math.sin(hs.sprung * Math.PI) * g * 0.6;
                const streckung = Math.sin(hs.sprung * Math.PI);
                ctx.translate(hs.x, boden - hoch);
                ctx.scale(richtung, 1);
                ctx.fillStyle = '#9c7f63';
                // Hinterlauf und Körper
                ellipse(ctx, -g * 0.18, -g * 0.12, g * 0.22, g * 0.16);
                ctx.fill();
                ellipse(ctx, 0, -g * 0.22, g * (0.32 + streckung * 0.08), g * 0.2, -0.15 * streckung);
                ctx.fill();
                // Schwänzchen
                ctx.fillStyle = '#f8fafc';
                ellipse(ctx, -g * 0.34, -g * 0.24, g * 0.07, g * 0.07);
                ctx.fill();
                // Kopf und Ohren
                ctx.fillStyle = '#9c7f63';
                ellipse(ctx, g * 0.3, -g * 0.36, g * 0.13, g * 0.11);
                ctx.fill();
                const zucken = Math.sin(hs.ohren) * 0.15;
                [[0.24, -0.2 + zucken], [0.32, 0.05]].forEach(([ox, w]) => {
                    ctx.save();
                    ctx.translate(g * ox, -g * 0.44);
                    ctx.rotate(w - 0.2 * streckung);
                    ctx.fillStyle = '#9c7f63';
                    ellipse(ctx, 0, -g * 0.17, g * 0.05, g * 0.18);
                    ctx.fill();
                    ctx.fillStyle = '#e8b4b8';
                    ellipse(ctx, 0, -g * 0.17, g * 0.025, g * 0.13);
                    ctx.fill();
                    ctx.restore();
                });
                ctx.fillStyle = '#1f2937';
                ellipse(ctx, g * 0.34, -g * 0.39, g * 0.025, g * 0.025);
                ctx.fill();
                // Vorderpfote
                ctx.fillStyle = '#8a6e54';
                ellipse(ctx, g * 0.18, -g * 0.04 + streckung * g * 0.05, g * 0.05, g * 0.07);
                ctx.fill();
            },
        };
    }

    function neueVoegel() {
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const y = zufall(0.12, 0.32);
        const vogelGroesse = klemme(Math.min(b, h) * 0.018, 8, 20);
        const schwarm = Array.from({length: 3 + Math.floor(Math.random() * 3)}, (_, i) => ({
            dx: -i * vogelGroesse * 3 + zufall(-8, 8), dy: (i % 2 ? 1 : -1) * i * vogelGroesse * 1.2, phase: zufall(0, 6),
        }));
        const v = {x: richtung > 0 ? -b * 0.1 : b * 1.1};
        return {
            ebene: 'himmel',
            schritt(dt, z) {
                v.x += richtung * b / (z.unruhe > 0.4 ? 6 : 14) * dt;
                schwarm.forEach((vogel) => { vogel.phase += dt * 7; });
                return richtung > 0 ? v.x < b * 1.3 : v.x > -b * 0.3;
            },
            zeichne(ctx) {
                ctx.strokeStyle = '#334155';
                ctx.lineWidth = Math.max(1.5, vogelGroesse * 0.18);
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                schwarm.forEach((vogel) => {
                    const x = v.x + vogel.dx * richtung;
                    const yy = y * h + vogel.dy + Math.sin(vogel.phase * 0.3) * 4;
                    const fluegel = Math.sin(vogel.phase) * vogelGroesse * 0.6;
                    ctx.beginPath();
                    ctx.moveTo(x - vogelGroesse, yy - fluegel);
                    ctx.quadraticCurveTo(x - vogelGroesse * 0.4, yy - fluegel * 0.2, x, yy);
                    ctx.quadraticCurveTo(x + vogelGroesse * 0.4, yy - fluegel * 0.2, x + vogelGroesse, yy - fluegel);
                    ctx.stroke();
                });
            },
        };
    }

    const besuche = new Besuchsplaner({
        regenbogen: neuerRegenbogen,
        igel: neuerIgel,
        hase: neuerHase,
        voegel: neueVoegel,
    }, {manuell: ['regenbogen']});

    // ── Klassenmaus: guckt über den Hügel oder vorne aus dem Gras ──
    const mausGroesse = () => Math.min(b, h) * 0.06;
    const huegelOrt = (anteil) => () => {
        const s = mausGroesse();
        return {x: b * anteil, y: wiese(b * anteil, h * 0.72, 2.9) + s * 0.3, s, von: 'unten'};
    };
    const maus = new Klassenmaus([
        {name: 'gras', ebene: 'gras', ort: () => ({x: b * 0.56, y: h + mausGroesse() * 0.45, s: mausGroesse() * 1.15, von: 'unten'})},
        {name: 'gras-links', ebene: 'gras', ort: () => ({x: b * 0.24, y: h + mausGroesse() * 0.45, s: mausGroesse() * 1.15, von: 'unten'})},
        {name: 'huegel', ebene: 'huegel', ort: huegelOrt(0.42)},
        {name: 'huegel-links', ebene: 'huegel', ort: huegelOrt(0.62)},
    ]);

    function schritt(dt, z) {
        t += dt;
        besuche.schritt(dt, z);
        maus.schritt(dt, z);
        // Regenbogen als Belohnung, wenn es nach dem Regen wieder ruhig wird
        if (z.unruhe > 0.6) regnete = true;
        else if (regnete && z.unruhe < 0.12) {
            regnete = false;
            besuche.starte('regenbogen');
        }

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
        // Blätter fallen vom großen Baum, wenn es stürmisch wird
        blattRest += dt * Math.max(0, z.unruhe - 0.3) * 12;
        while (blattRest > 1) {
            blattRest -= 1;
            const baum = baumDaten();
            blaetter.push({
                x: baum.x + zufall(-0.6, 0.6) * baum.g, y: baum.kroneY + zufall(-0.3, 0.3) * baum.g,
                phase: zufall(0, 6), farbe: zufallAus(['#4d7c0f', '#65a30d', '#a16207', '#ca8a04']), groesse: zufall(5, 9),
            });
        }
        for (let i = blaetter.length - 1; i >= 0; i--) {
            const bl = blaetter[i];
            bl.phase += dt * 3;
            bl.y += (40 + Math.sin(bl.phase) * 20) * dt;
            bl.x += (60 * z.unruhe + Math.cos(bl.phase) * 30) * dt;
            if (bl.y > h * 1.02 || bl.x > b + 20) blaetter.splice(i, 1);
        }
        if (blaetter.length > 120) blaetter.splice(0, blaetter.length - 120);

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

    // ── Landschaft ───────────────────────────────────────────────────────
    function baumDaten() {
        const x = b * 0.83;
        const g = Math.min(h * 0.34, b * 0.2);
        const boden = wiese(x, h * 0.66, 1.7) + h * 0.03;
        return {x, g, boden, kroneY: boden - g * 1.15};
    }

    function zeichneFernBaeume(ctx, gewitter) {
        fernBaeume.forEach((fb) => {
            const boden = wiese(fb.x, h * 0.6, 0.4) + fb.groesse * 0.3;
            ctx.fillStyle = mischeFarbe('#8a6b4f', '#5f5a55', gewitter);
            ctx.fillRect(fb.x - fb.groesse * 0.08, boden - fb.groesse * 1.1, fb.groesse * 0.16, fb.groesse * 1.1);
            ctx.fillStyle = mischeFarbe(fb.ton, '#6f8070', gewitter);
            ellipse(ctx, fb.x, boden - fb.groesse * 1.35, fb.groesse * 0.6, fb.groesse * 0.7);
            ctx.fill();
        });
    }

    function zeichneBaum(ctx, gewitter, z) {
        const {x, g, boden, kroneY} = baumDaten();
        const wind = Math.sin(t * 0.7) * (0.01 + z.unruhe * 0.035) + z.unruhe * 0.03;
        ctx.save();
        ctx.translate(x, boden);
        ctx.rotate(wind * 0.25);
        // Stamm mit zwei Ästen
        ctx.fillStyle = mischeFarbe('#7a5536', '#4d3f33', gewitter);
        ctx.beginPath();
        ctx.moveTo(-g * 0.09, 0);
        ctx.quadraticCurveTo(-g * 0.05, -g * 0.5, -g * 0.05, -g * 1.0);
        ctx.lineTo(g * 0.05, -g * 1.0);
        ctx.quadraticCurveTo(g * 0.05, -g * 0.5, g * 0.1, 0);
        ctx.fill();
        ctx.strokeStyle = ctx.fillStyle;
        ctx.lineCap = 'round';
        ctx.lineWidth = g * 0.045;
        ctx.beginPath();
        ctx.moveTo(-g * 0.02, -g * 0.6);
        ctx.quadraticCurveTo(-g * 0.2, -g * 0.75, -g * 0.32, -g * 0.95);
        ctx.moveTo(g * 0.02, -g * 0.7);
        ctx.quadraticCurveTo(g * 0.22, -g * 0.82, g * 0.3, -g * 1.02);
        ctx.stroke();
        // Astloch
        ctx.fillStyle = 'rgba(40,25,15,.6)';
        ellipse(ctx, g * 0.01, -g * 0.42, g * 0.025, g * 0.035);
        ctx.fill();
        ctx.restore();

        // Krone: dunkle, mittlere und helle Blattballen, die im Wind leicht schwingen
        const kx = x + wind * g * 0.9;
        const toene = [['#3f7a2a', '#45583f', 0.06, 0.05], ['#5a9e38', '#56684d', 0, 0], ['#7cbf4c', '#66785c', -0.05, -0.06]];
        toene.forEach(([farbe, sturm, ox, oy], stufe) => {
            ctx.fillStyle = mischeFarbe(farbe, sturm, gewitter);
            krone.forEach((teil, i) => {
                if (stufe === 2 && i % 2 === 1) return;
                const schwingen = Math.sin(t * 1.3 + teil.phase) * g * (0.008 + z.unruhe * 0.02);
                const r = teil.r * g * (stufe === 2 ? 0.7 : 1);
                ellipse(ctx, kx + (teil.dx + ox) * g + schwingen, kroneY + (teil.dy + oy) * g, r, r * 0.9);
                ctx.fill();
            });
        });
        // Äpfel
        ctx.fillStyle = mischeFarbe('#dc2626', '#7f5050', gewitter);
        aepfel.forEach(([ax, ay]) => {
            ellipse(ctx, kx + ax * g, kroneY + ay * g, g * 0.035, g * 0.035);
            ctx.fill();
        });
    }

    function zeichneBuesche(ctx, gewitter) {
        buesche.forEach((bu) => {
            const boden = wiese(bu.x, h * 0.66, 1.7) + bu.groesse * 0.35;
            ctx.fillStyle = mischeFarbe('#3f8a2e', '#4a5e45', gewitter);
            [[-0.55, -0.35, 0.55], [0.5, -0.35, 0.55], [0, -0.6, 0.7]].forEach(([dx, dy, r]) => {
                ellipse(ctx, bu.x + dx * bu.groesse, boden + dy * bu.groesse, r * bu.groesse, r * bu.groesse * 0.85);
                ctx.fill();
            });
            ctx.fillStyle = mischeFarbe(bu.beerenFarbe, '#6b6b6b', gewitter * 0.6);
            bu.beeren.forEach(([bx, by]) => {
                ellipse(ctx, bu.x + bx * bu.groesse, boden + by * bu.groesse, bu.groesse * 0.07, bu.groesse * 0.07);
                ctx.fill();
            });
        });
    }

    function zeichneZaun(ctx, gewitter) {
        const von = b * 0.015;
        const bis = b * 0.21;
        const hoeheZaun = Math.min(b, h) * 0.06;
        const holz = mischeFarbe('#b08155', '#6b6259', gewitter);
        const boden = (x) => wiese(x, h * 0.72, 2.9) + hoeheZaun * 0.15;
        ctx.strokeStyle = holz;
        ctx.lineCap = 'round';
        ctx.lineWidth = Math.max(3, hoeheZaun * 0.12);
        [0.35, 0.72].forEach((anteil) => {
            ctx.beginPath();
            for (let x = von; x <= bis; x += 10) {
                const y = boden(x) - hoeheZaun * anteil;
                x === von ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            }
            ctx.stroke();
        });
        ctx.fillStyle = holz;
        const pfostenBreite = Math.max(5, hoeheZaun * 0.16);
        for (let x = von; x <= bis; x += b * 0.035) {
            const y = boden(x);
            ctx.beginPath();
            ctx.moveTo(x - pfostenBreite / 2, y);
            ctx.lineTo(x - pfostenBreite / 2, y - hoeheZaun);
            ctx.lineTo(x, y - hoeheZaun - pfostenBreite * 0.6);
            ctx.lineTo(x + pfostenBreite / 2, y - hoeheZaun);
            ctx.lineTo(x + pfostenBreite / 2, y);
            ctx.fill();
        }
    }

    function zeichnePilze(ctx, gewitter) {
        pilze.forEach((p) => {
            const y = h * 0.965;
            const g = p.groesse;
            ctx.fillStyle = '#f5f0e6';
            ctx.fillRect(p.x - g * 0.18, y - g * 0.75, g * 0.36, g * 0.75);
            ctx.fillStyle = mischeFarbe('#dc2626', '#8a5a5a', gewitter);
            ctx.beginPath();
            ctx.ellipse(p.x, y - g * 0.7, g * 0.62, g * 0.5, 0, Math.PI, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            [[-0.3, -0.95], [0.1, -1.05], [0.35, -0.85], [-0.05, -0.8]].forEach(([dx, dy]) => {
                ellipse(ctx, p.x + dx * g, y + dy * g, g * 0.08, g * 0.065);
                ctx.fill();
            });
        });
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

        besuche.zeichne(ctx, 'regenbogen', z);

        // Wolken (helle Schönwetterwolken immer, graue bei Unruhe)
        const faktor = glatt(z.unruhe * 1.15);
        wolken.forEach((w) => {
            if (w.hell || faktor > 0.001) zeichneWolke(ctx, w, faktor, w.hell ? gewitter : 0.55 + gewitter * 0.3);
        });

        besuche.zeichne(ctx, 'himmel', z);

        // Hügel
        [[0.6, '#a5d66b', '#7e9670', 0.4], [0.66, '#7cbf4a', '#66805a', 1.7], [0.72, '#5fa83a', '#526d48', 2.9]].forEach(([basis, hell, dunkel, phase], ebene) => {
            if (ebene === 2) maus.zeichne(ctx, 'huegel');
            ctx.fillStyle = mischeFarbe(hell, dunkel, gewitter);
            ctx.beginPath();
            ctx.moveTo(0, hoehe);
            for (let x = 0; x <= breite + 20; x += 20) ctx.lineTo(x, wiese(x, hoehe * basis, phase));
            ctx.lineTo(breite, hoehe);
            ctx.fill();

            // Landschaft zwischen den Hügeln, damit Fußpunkte vom nächsten Hügel verdeckt werden
            if (ebene === 0) zeichneFernBaeume(ctx, gewitter);
            if (ebene === 1) {
                zeichneBuesche(ctx, gewitter);
                zeichneBaum(ctx, gewitter, z);
            }
            if (ebene === 2) zeichneZaun(ctx, gewitter);
        });

        besuche.zeichne(ctx, 'wiese', z);

        // Blumen von hinten nach vorne
        blumen.forEach((bl) => zeichneBlume(ctx, bl, gewitter));

        besuche.zeichne(ctx, 'vorne', z);
        zeichnePilze(ctx, gewitter);

        maus.zeichne(ctx, 'gras');

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

        // fallende Blätter
        blaetter.forEach((bl) => {
            ctx.fillStyle = bl.farbe;
            ellipse(ctx, bl.x, bl.y, bl.groesse, bl.groesse * 0.45, bl.phase);
            ctx.fill();
        });

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
        besuch: (name) => mausBesuch(maus, name) ?? besuche.starte(name),
    };
}
