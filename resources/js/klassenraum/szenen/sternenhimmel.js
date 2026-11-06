/*
 * Szene „Sternenhimmel“: In der Stille gehen nach und nach Sterne auf; ist der Himmel voll,
 * erscheint der Große Wagen. Nach längerer Ruhe fallen Sternschnuppen. Bei Lärm ziehen
 * Wolken auf, Sterne verlöschen und im Haus geht das Licht an.
 *
 * Seltene Besuche (nur bei Ruhe): Komet, Polarlicht, Eule auf dem Dach, Glühwürmchen.
 */

import {ellipse, glatt, klemme, mische, mischeFarbe, zufall, zufallMitSaat} from '../shared/hilfen';
import {Besuchsplaner, blende} from '../shared/besuche';

const GESAMT = 320;

// Großer Wagen (normiert im Bildausschnitt), Verbindungslinien als Indexpaare
const WAGEN = [[0.17, 0.2], [0.24, 0.17], [0.3, 0.2], [0.36, 0.25], [0.37, 0.36], [0.47, 0.38], [0.49, 0.27]];
const WAGEN_LINIEN = [[0, 1], [1, 2], [2, 3], [3, 4], [4, 5], [5, 6], [6, 3]];

export function erzeugeSternenhimmel() {
    let b = 0;
    let h = 0;
    let sterne = [];
    let wolken = [];
    let baeume = [];
    let haus = null;
    let sichtbar = GESAMT * 0.15;
    let maximum = GESAMT;
    let t = 0;
    let naechsteSchnuppe = 12;
    let licht = 0;
    const schnuppen = [];

    const huegel = (x, basis, a1, a2, phase) => basis + Math.sin(x * a1 + phase) * h * 0.03 + Math.sin(x * a2 + phase * 2) * h * 0.015;
    const huegelHinten = (x) => huegel(x, h * 0.8, 0.004, 0.011, 0.6);
    const huegelVorne = (x) => huegel(x, h * 0.88, 0.006, 0.017, 2.1);

    function groesse(breite, hoehe, einstellungen) {
        const neu = breite !== b || hoehe !== h;
        b = breite;
        h = hoehe;
        maximum = Math.round(GESAMT * klemme(einstellungen.menge / 100, 0.2, 1.5));

        if (neu || sterne.length === 0) {
            const zz = zufallMitSaat(3);
            sterne = Array.from({length: Math.round(GESAMT * 1.5)}, () => ({
                x: zz(),
                y: zz.zwischen(0, 0.78),
                r: zz() < 0.08 ? zz.zwischen(1.8, 2.6) : zz.zwischen(0.6, 1.6),
                phase: zz.zwischen(0, 6.3),
                tempo: zz.zwischen(0.6, 2.2),
                farbe: zz.aus(['#ffffff', '#fff7e0', '#e0ecff', '#ffe9c2']),
                wagen: -1,
                alpha: 0,
            }));
            // Sterne des Großen Wagens kommen zuletzt (Belohnung für langen Ruhe-Abschnitt)
            WAGEN.forEach(([x, y], i) => {
                const index = Math.round(GESAMT * (0.82 + i * 0.025));
                sterne[index] = {...sterne[index], x, y, r: 2.6, farbe: '#fffbea', wagen: i};
            });

            wolken = Array.from({length: 8}, (_, i) => {
                const vonLinks = i % 2 === 0;
                return {
                    ziel: zz.zwischen(0.05, 0.95),
                    y: zz.zwischen(0.06, 0.6),
                    groesse: zz.zwischen(0.16, 0.3),
                    start: vonLinks ? zz.zwischen(-0.6, -0.35) : zz.zwischen(1.35, 1.6),
                    drift: zz.zwischen(0, 6),
                    teile: Array.from({length: 6}, () => [zz.zwischen(-0.5, 0.5), zz.zwischen(-0.12, 0.12), zz.zwischen(0.2, 0.36)]),
                };
            });

            baeume = Array.from({length: Math.round(b / 70)}, () => ({
                x: zz.zwischen(0, b),
                hoehe: h * zz.zwischen(0.05, 0.11),
            })).filter((baum) => Math.abs(baum.x - b * 0.68) > b * 0.07);

            haus = {x: b * 0.68, breite: Math.max(60, b * 0.07)};
        }
    }

    // ── Seltene Besuche ──────────────────────────────────────────────────
    const bewoelkung = (z) => glatt(z.unruhe);

    function neuerKomet() {
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const k = {fortschritt: 0, y: zufall(0.1, 0.3), neigung: zufall(0.02, 0.08), dauer: zufall(28, 36)};
        return {
            ebene: 'himmel',
            schritt(dt) {
                k.fortschritt += dt / k.dauer;
                return k.fortschritt < 1;
            },
            zeichne(ctx, z) {
                const p = richtung > 0 ? k.fortschritt : 1 - k.fortschritt;
                const x = mische(-0.1, 1.1, p) * b;
                const y = (k.y + k.neigung * p) * h;
                const laenge = Math.min(b, h) * 0.35;
                const a = Math.sin(k.fortschritt * Math.PI) * (1 - bewoelkung(z));
                const sx = x - richtung * laenge;
                const sy = y - k.neigung * h * 0.3;
                const schweif = ctx.createLinearGradient(x, y, sx, sy);
                schweif.addColorStop(0, `rgba(210,235,255,${0.75 * a})`);
                schweif.addColorStop(1, 'rgba(160,200,255,0)');
                ctx.fillStyle = schweif;
                ctx.beginPath();
                ctx.moveTo(x, y - 3);
                ctx.quadraticCurveTo((x + sx) / 2, y - laenge * 0.06, sx, sy - laenge * 0.08);
                ctx.lineTo(sx, sy + laenge * 0.05);
                ctx.quadraticCurveTo((x + sx) / 2, y + laenge * 0.03, x, y + 3);
                ctx.fill();
                const kopf = ctx.createRadialGradient(x, y, 0, x, y, 14);
                kopf.addColorStop(0, `rgba(255,255,255,${a})`);
                kopf.addColorStop(1, 'rgba(200,230,255,0)');
                ctx.fillStyle = kopf;
                ctx.fillRect(x - 14, y - 14, 28, 28);
            },
        };
    }

    function neuesPolarlicht() {
        const pl = {zeit: 0, dauer: 40, alpha: 0};
        // Farbe, Phase, Stärke, Lage der leuchtenden Unterkante (Anteil der Höhe), Höhe der Strahlen
        const vorhaenge = [['#34d399', 0.0, 0.5, 0.36, 0.24], ['#2dd4bf', 2.1, 0.3, 0.3, 0.18], ['#a78bfa', 4.2, 0.22, 0.27, 0.14]];
        let flucht = false;
        return {
            ebene: 'hintergrund',
            schritt(dt, z) {
                pl.zeit += dt;
                if (z.unruhe > 0.5) flucht = true;
                const ziel = flucht || pl.zeit > pl.dauer ? 0 : 1;
                pl.alpha = blende(pl.alpha, ziel, dt, 0.15);
                return !(ziel === 0 && pl.alpha <= 0.005);
            },
            zeichne(ctx, z) {
                const a = pl.alpha * (1 - bewoelkung(z));
                if (a <= 0.005) return;
                ctx.globalCompositeOperation = 'lighter';
                ctx.lineCap = 'round';
                vorhaenge.forEach(([farbe, phase, staerke, lage, hoeheAnteil]) => {
                    const unten = (x) => h * lage + Math.sin(x * 0.0035 + t * 0.12 + phase) * h * 0.06 + Math.sin(x * 0.011 - t * 0.09) * h * 0.015;
                    const hoehe = h * hoeheAnteil;
                    // Verlauf: hell an der Unterkante, nach oben auslaufend
                    const verlauf = ctx.createLinearGradient(0, h * lage + h * 0.08, 0, h * lage - h * 0.08 - hoehe);
                    verlauf.addColorStop(0, 'rgba(0,0,0,0)');
                    verlauf.addColorStop(0.18, farbe);
                    verlauf.addColorStop(0.45, farbe);
                    verlauf.addColorStop(1, 'rgba(0,0,0,0)');
                    // weicher Schleier
                    ctx.globalAlpha = a * staerke * 0.35;
                    ctx.fillStyle = verlauf;
                    ctx.beginPath();
                    for (let x = 0; x <= b + 20; x += 20) {
                        const y = unten(x) - hoehe;
                        x === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
                    }
                    for (let x = b + 20; x >= 0; x -= 20) ctx.lineTo(x, unten(x));
                    ctx.fill();
                    // senkrechte Strahlen, langsam flackernd
                    ctx.strokeStyle = verlauf;
                    ctx.lineWidth = Math.max(2, b / 400);
                    for (let x = 0; x <= b; x += 6) {
                        const flackern = Math.sin(x * 0.061 + t * 0.7 + phase * 3) * 0.5 + Math.sin(x * 0.017 - t * 0.25 + phase) * 0.5;
                        if (flackern <= 0) continue;
                        ctx.globalAlpha = a * staerke * flackern;
                        const y = unten(x);
                        ctx.beginPath();
                        ctx.moveTo(x, y);
                        ctx.lineTo(x, y - hoehe * (0.55 + 0.45 * flackern));
                        ctx.stroke();
                    }
                });
            },
        };
    }

    function neueEule() {
        const e = {zeit: 0, dauer: 30, alpha: 0, blinzeln: 3, kopf: 0, kopfZiel: 0};
        let flucht = false;
        return {
            ebene: 'vorne',
            schritt(dt, z) {
                e.zeit += dt;
                if (z.unruhe > 0.45) flucht = true;
                e.blinzeln -= dt;
                if (e.blinzeln < -0.18) e.blinzeln = zufall(2, 5);
                if (Math.random() < dt * 0.3) e.kopfZiel = zufall(-0.35, 0.35);
                e.kopf += (e.kopfZiel - e.kopf) * Math.min(1, dt * 3);
                const ziel = flucht || e.zeit > e.dauer ? 0 : 1;
                e.alpha = blende(e.alpha, ziel, dt, flucht ? 1.2 : 0.4);
                return !(ziel === 0 && e.alpha <= 0.01);
            },
            zeichne(ctx) {
                const {x, breite} = haus;
                const boden = huegelVorne(x) + 4;
                const first = boden - breite * 0.62 - breite * 0.45;
                const g = breite * 0.5;
                ctx.globalAlpha = e.alpha;
                ctx.translate(x, first + 2);
                // Körper
                ctx.fillStyle = '#5b4a6e';
                ellipse(ctx, 0, -g * 0.45, g * 0.32, g * 0.45);
                ctx.fill();
                ctx.fillStyle = '#7a6890';
                ellipse(ctx, 0, -g * 0.36, g * 0.2, g * 0.3);
                ctx.fill();
                // Kopf (dreht leicht)
                ctx.save();
                ctx.translate(0, -g * 0.95);
                ctx.rotate(e.kopf * 0.4);
                ctx.fillStyle = '#5b4a6e';
                ellipse(ctx, 0, 0, g * 0.3, g * 0.25);
                ctx.fill();
                ctx.beginPath();
                ctx.moveTo(-g * 0.28, -g * 0.1);
                ctx.lineTo(-g * 0.22, -g * 0.36);
                ctx.lineTo(-g * 0.1, -g * 0.18);
                ctx.moveTo(g * 0.28, -g * 0.1);
                ctx.lineTo(g * 0.22, -g * 0.36);
                ctx.lineTo(g * 0.1, -g * 0.18);
                ctx.fill();
                const zu = e.blinzeln < 0;
                [-1, 1].forEach((seite) => {
                    const ax = seite * g * 0.12 + e.kopf * g * 0.08;
                    if (zu) {
                        ctx.strokeStyle = '#f5d76e';
                        ctx.lineWidth = Math.max(1, g * 0.03);
                        ctx.beginPath();
                        ctx.moveTo(ax - g * 0.07, 0);
                        ctx.lineTo(ax + g * 0.07, 0);
                        ctx.stroke();
                    } else {
                        const schein = ctx.createRadialGradient(ax, 0, 0, ax, 0, g * 0.2);
                        schein.addColorStop(0, 'rgba(255,220,110,.45)');
                        schein.addColorStop(1, 'rgba(255,220,110,0)');
                        ctx.fillStyle = schein;
                        ctx.fillRect(ax - g * 0.2, -g * 0.2, g * 0.4, g * 0.4);
                        ctx.fillStyle = '#fcd34d';
                        ellipse(ctx, ax, 0, g * 0.085, g * 0.085);
                        ctx.fill();
                        ctx.fillStyle = '#111827';
                        ellipse(ctx, ax, 0, g * 0.04, g * 0.04);
                        ctx.fill();
                    }
                });
                ctx.fillStyle = '#e0a458';
                ctx.beginPath();
                ctx.moveTo(-g * 0.03, g * 0.05);
                ctx.lineTo(g * 0.03, g * 0.05);
                ctx.lineTo(0, g * 0.13);
                ctx.fill();
                ctx.restore();
            },
        };
    }

    function neueGluehwuermchen() {
        const gw = {zeit: 0, dauer: 34, alpha: 0};
        const tiere = Array.from({length: 22}, () => ({
            x: zufall(0.05, 0.95), y: zufall(0.78, 0.95), phase: zufall(0, 6), tempo: zufall(0.4, 1.1),
        }));
        let flucht = false;
        return {
            ebene: 'vorne',
            schritt(dt, z) {
                gw.zeit += dt;
                if (z.unruhe > 0.45) flucht = true;
                tiere.forEach((tier) => {
                    tier.phase += dt * tier.tempo;
                    tier.x += Math.sin(tier.phase * 0.7) * 0.004 * dt;
                    tier.y += Math.cos(tier.phase * 0.9) * 0.003 * dt;
                });
                const ziel = flucht || gw.zeit > gw.dauer ? 0 : 1;
                gw.alpha = blende(gw.alpha, ziel, dt, flucht ? 1 : 0.25);
                return !(ziel === 0 && gw.alpha <= 0.01);
            },
            zeichne(ctx) {
                tiere.forEach((tier) => {
                    const leuchten = Math.max(0, Math.sin(tier.phase * 2.2)) * gw.alpha;
                    if (leuchten < 0.02) return;
                    const x = tier.x * b;
                    const y = tier.y * h;
                    const schein = ctx.createRadialGradient(x, y, 0, x, y, 10);
                    schein.addColorStop(0, `rgba(230,255,140,${0.9 * leuchten})`);
                    schein.addColorStop(1, 'rgba(230,255,140,0)');
                    ctx.fillStyle = schein;
                    ctx.fillRect(x - 10, y - 10, 20, 20);
                });
            },
        };
    }

    const besuche = new Besuchsplaner({
        komet: neuerKomet,
        polarlicht: neuesPolarlicht,
        eule: neueEule,
        gluehwuermchen: neueGluehwuermchen,
    });

    function schritt(dt, z) {
        t += dt;
        besuche.schritt(dt, z);

        // Sterne sammeln (in der Stille) bzw. verlieren (bei Lärm)
        if (z.unruhe < 0.15) sichtbar += dt * (maximum / 75) * (z.status === 'aus' ? 0.6 : 1);
        else if (z.unruhe > 0.6) sichtbar -= dt * (maximum / 10) * z.unruhe;
        sichtbar = klemme(sichtbar, maximum * 0.08, maximum);

        sterne.forEach((s, i) => {
            const ziel = klemme(sichtbar - i, 0, 1);
            s.alpha += (ziel - s.alpha) * Math.min(1, dt * 1.5);
        });

        // Licht im Haus: geht bei Lärm an, nach Ruhe wieder aus
        const lichtZiel = z.unruhe > 0.55 ? 1 : 0;
        licht += (lichtZiel - licht) * Math.min(1, dt * (lichtZiel ? 3 : 0.4));

        // Sternschnuppen nach längerer Ruhe
        naechsteSchnuppe -= dt;
        if (naechsteSchnuppe <= 0) {
            naechsteSchnuppe = zufall(9, 22);
            if (z.unruhe < 0.1 && (z.ruheSeit > 15 || z.status === 'aus')) {
                schnuppen.push({x: zufall(0.15, 0.85), y: zufall(0.05, 0.35), winkel: zufall(0.35, 0.7), alter: 0, dauer: zufall(0.9, 1.5)});
            }
        }
        for (let i = schnuppen.length - 1; i >= 0; i--) {
            schnuppen[i].alter += dt;
            if (schnuppen[i].alter > schnuppen[i].dauer) schnuppen.splice(i, 1);
        }
    }

    function zeichneWolke(ctx, w, faktor) {
        const groesse = w.groesse * Math.max(b, h * 1.4);
        const x = mische(w.start, w.ziel, faktor) * b + Math.sin(t * 0.05 + w.drift) * b * 0.02;
        const y = w.y * h;
        w.teile.forEach(([dx, dy, r]) => {
            const v = ctx.createRadialGradient(x + dx * groesse, y + dy * groesse - r * groesse * 0.4, 0, x + dx * groesse, y + dy * groesse, r * groesse);
            v.addColorStop(0, 'rgba(92,102,140,0.95)');
            v.addColorStop(0.6, 'rgba(58,66,102,0.92)');
            v.addColorStop(1, 'rgba(40,46,78,0)');
            ctx.fillStyle = v;
            ellipse(ctx, x + dx * groesse, y + dy * groesse, r * groesse, r * groesse * 0.62);
            ctx.fill();
        });
    }

    function zeichneHaus(ctx) {
        const {x, breite} = haus;
        const boden = huegelVorne(x) + 4;
        const hoehe = breite * 0.62;
        ctx.fillStyle = '#070b1f';
        ctx.fillRect(x - breite / 2, boden - hoehe, breite, hoehe);
        ctx.beginPath();
        ctx.moveTo(x - breite * 0.62, boden - hoehe + 2);
        ctx.lineTo(x, boden - hoehe - breite * 0.45);
        ctx.lineTo(x + breite * 0.62, boden - hoehe + 2);
        ctx.fill();
        ctx.fillRect(x + breite * 0.2, boden - hoehe - breite * 0.42, breite * 0.12, breite * 0.3);

        const fenster = [[-0.28, 0.35], [0.12, 0.35]];
        fenster.forEach(([fx, fy], i) => {
            const an = i === 0 ? licht : klemme(licht * 1.4 - 0.4, 0, 1);
            const wx = x + fx * breite;
            const wy = boden - hoehe + fy * hoehe;
            const fb = breite * 0.18;
            if (an > 0.02) {
                const schein = ctx.createRadialGradient(wx + fb / 2, wy + fb / 2, 0, wx + fb / 2, wy + fb / 2, fb * 3);
                schein.addColorStop(0, `rgba(255,200,110,${0.35 * an})`);
                schein.addColorStop(1, 'rgba(255,200,110,0)');
                ctx.fillStyle = schein;
                ctx.fillRect(wx - fb * 3, wy - fb * 3, fb * 7, fb * 7);
            }
            ctx.fillStyle = mischeFarbe('#141a35', '#ffd38a', an);
            ctx.fillRect(wx, wy, fb, fb);
        });
    }

    function zeichne(ctx, breite, hoehe, z) {
        const bewoelkt = glatt(z.unruhe);

        const himmel = ctx.createLinearGradient(0, 0, 0, hoehe);
        himmel.addColorStop(0, mischeFarbe('#03061a', '#151a2c', bewoelkt));
        himmel.addColorStop(0.55, mischeFarbe('#0d1640', '#232a44', bewoelkt));
        himmel.addColorStop(0.85, mischeFarbe('#2a3576', '#363d5a', bewoelkt));
        ctx.fillStyle = himmel;
        ctx.fillRect(0, 0, breite, hoehe);

        besuche.zeichne(ctx, 'hintergrund', z);

        // Milchstraße (dezent)
        ctx.save();
        ctx.globalAlpha = 0.12 * (1 - bewoelkt) * klemme(sichtbar / maximum, 0.3, 1);
        ctx.translate(breite * 0.5, hoehe * 0.3);
        ctx.rotate(-0.35);
        const band = ctx.createLinearGradient(0, -hoehe * 0.12, 0, hoehe * 0.12);
        band.addColorStop(0, 'rgba(180,190,255,0)');
        band.addColorStop(0.5, 'rgba(200,205,255,1)');
        band.addColorStop(1, 'rgba(180,190,255,0)');
        ctx.fillStyle = band;
        ctx.fillRect(-breite, -hoehe * 0.12, breite * 2, hoehe * 0.24);
        ctx.restore();

        // Sterne
        sterne.forEach((s) => {
            if (s.alpha < 0.01) return;
            const funkeln = 0.65 + Math.sin(t * s.tempo + s.phase) * 0.35;
            const a = s.alpha * funkeln;
            const x = s.x * breite;
            const y = s.y * hoehe;
            if (s.r > 1.7) {
                const schein = ctx.createRadialGradient(x, y, 0, x, y, s.r * 5);
                schein.addColorStop(0, `rgba(255,250,230,${0.35 * a})`);
                schein.addColorStop(1, 'rgba(255,250,230,0)');
                ctx.fillStyle = schein;
                ctx.fillRect(x - s.r * 5, y - s.r * 5, s.r * 10, s.r * 10);
            }
            ctx.globalAlpha = a;
            ctx.fillStyle = s.farbe;
            ellipse(ctx, x, y, s.r, s.r);
            ctx.fill();
            ctx.globalAlpha = 1;
        });

        // Großer Wagen: Linien, wenn alle sieben Sterne leuchten
        const wagenSterne = sterne.filter((s) => s.wagen >= 0).sort((a, c) => a.wagen - c.wagen);
        const wagenAlpha = Math.min(...wagenSterne.map((s) => s.alpha));
        if (wagenAlpha > 0.05) {
            ctx.strokeStyle = `rgba(200,215,255,${0.35 * wagenAlpha * (1 - bewoelkt)})`;
            ctx.lineWidth = 1.5;
            ctx.setLineDash([4, 6]);
            WAGEN_LINIEN.forEach(([a, c]) => {
                ctx.beginPath();
                ctx.moveTo(wagenSterne[a].x * breite, wagenSterne[a].y * hoehe);
                ctx.lineTo(wagenSterne[c].x * breite, wagenSterne[c].y * hoehe);
                ctx.stroke();
            });
            ctx.setLineDash([]);
        }

        // Sternschnuppen
        schnuppen.forEach((s) => {
            const fortschritt = s.alter / s.dauer;
            const laenge = Math.min(breite, hoehe) * 0.25;
            const x = s.x * breite + Math.cos(s.winkel) * laenge * 1.6 * fortschritt;
            const y = s.y * hoehe + Math.sin(s.winkel) * laenge * 1.6 * fortschritt;
            const a = Math.sin(fortschritt * Math.PI) * (1 - bewoelkt);
            const spur = ctx.createLinearGradient(x, y, x - Math.cos(s.winkel) * laenge, y - Math.sin(s.winkel) * laenge);
            spur.addColorStop(0, `rgba(255,255,255,${a})`);
            spur.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.strokeStyle = spur;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x - Math.cos(s.winkel) * laenge, y - Math.sin(s.winkel) * laenge);
            ctx.stroke();
        });

        besuche.zeichne(ctx, 'himmel', z);

        // Mond
        const mr = Math.min(breite, hoehe) * 0.065;
        const mx = breite * 0.83;
        const my = hoehe * 0.2;
        const mondSchein = ctx.createRadialGradient(mx, my, mr * 0.8, mx, my, mr * 4);
        mondSchein.addColorStop(0, 'rgba(255,240,200,0.22)');
        mondSchein.addColorStop(1, 'rgba(255,240,200,0)');
        ctx.fillStyle = mondSchein;
        ctx.fillRect(mx - mr * 4, my - mr * 4, mr * 8, mr * 8);
        ctx.save();
        ctx.beginPath();
        ctx.arc(mx, my, mr, 0, Math.PI * 2);
        ctx.clip();
        ctx.fillStyle = '#fff1c9';
        ctx.fillRect(mx - mr, my - mr, mr * 2, mr * 2);
        ctx.fillStyle = 'rgba(214,196,150,0.5)';
        [[-0.3, -0.2, 0.18], [0.25, 0.3, 0.13], [0.1, -0.45, 0.09], [-0.15, 0.45, 0.1]].forEach(([dx, dy, r]) => {
            ellipse(ctx, mx + dx * mr, my + dy * mr, r * mr, r * mr);
            ctx.fill();
        });
        ctx.restore();

        // Wolken
        const wolkenFaktor = glatt(z.unruhe * 1.15);
        if (wolkenFaktor > 0.001) wolken.forEach((w) => zeichneWolke(ctx, w, wolkenFaktor));
        if (bewoelkt > 0.01) {
            ctx.fillStyle = `rgba(20,24,40,${0.35 * bewoelkt})`;
            ctx.fillRect(0, 0, breite, hoehe);
        }

        // Landschaft
        ctx.fillStyle = '#0d1430';
        ctx.beginPath();
        ctx.moveTo(0, hoehe);
        for (let x = 0; x <= breite + 20; x += 20) ctx.lineTo(x, huegelHinten(x));
        ctx.lineTo(breite, hoehe);
        ctx.fill();

        ctx.fillStyle = '#060a1d';
        baeume.forEach((baum) => {
            const fuss = huegelVorne(baum.x) + 6;
            ctx.beginPath();
            ctx.moveTo(baum.x, fuss - baum.hoehe);
            ctx.lineTo(baum.x - baum.hoehe * 0.32, fuss);
            ctx.lineTo(baum.x + baum.hoehe * 0.32, fuss);
            ctx.fill();
        });
        ctx.beginPath();
        ctx.moveTo(0, hoehe);
        for (let x = 0; x <= breite + 20; x += 20) ctx.lineTo(x, huegelVorne(x));
        ctx.lineTo(breite, hoehe);
        ctx.fill();

        zeichneHaus(ctx);
        besuche.zeichne(ctx, 'vorne', z);
    }

    return {
        mengeLabel: 'Anzahl der Sterne',
        texte: {
            ruhig: 'Ganz still – neue Sterne gehen auf.',
            fast: 'Psst – die Sterne flackern schon.',
            laut: 'Zu laut – Wolken verdecken die Sterne!',
        },
        groesse,
        schritt,
        zeichne,
        info: () => `${Math.round(sichtbar)} von ${maximum} Sternen`,
        besuch: (name) => besuche.starte(name),
    };
}
