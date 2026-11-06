/*
 * Szene „Sternenhimmel“: In der Stille gehen nach und nach Sterne auf; ist der Himmel voll,
 * erscheint der Große Wagen. Nach längerer Ruhe fallen Sternschnuppen. Bei Lärm ziehen
 * Wolken auf, Sterne verlöschen und im Haus geht das Licht an.
 */

import {ellipse, glatt, klemme, mische, mischeFarbe, zufall, zufallMitSaat} from '../shared/hilfen';

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

    function schritt(dt, z) {
        t += dt;

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
    };
}
