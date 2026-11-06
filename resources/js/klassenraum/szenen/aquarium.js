/*
 * Szene „Aquarium“: Fische ziehen ruhig ihre Bahnen. Steigt die Unruhe, verstecken sie
 * sich nacheinander hinter den Felsen, Pflanzen wiegen stärker und das Wasser trübt ein.
 *
 * Bewohner: Korallenriff (Ast-, Hirn-, Fächer-, Röhren- und Weichkorallen), Seeanemone mit zwei
 * Clownfischen (verkriechen sich bei Lärm), Krabben (flüchten unter die Felsen), Seesterne,
 * Seeigel, eine Schnecke (zieht sich ins Haus zurück) und eine Muräne in der Felsspalte.
 *
 * Seltene Besuche (nur bei Ruhe): Meeresschildkröte, Qualle, Seepferdchen, Rochen,
 * Kugelfisch (bläst sich bei Lärm auf).
 */

import {ellipse, glatt, klemme, mische, mischeFarbe, mitAlpha, zufall, zufallAus, zufallMitSaat} from '../shared/hilfen';
import {Besuchsplaner, blende} from '../shared/besuche';

const ARTEN = [
    {koerper: '#f59e0b', bauch: '#fde68a', flosse: '#ea580c', streifen: null, form: 0.34},
    {koerper: '#f97316', bauch: '#ffedd5', flosse: '#1f2937', streifen: '#ffffff', form: 0.36},
    {koerper: '#38bdf8', bauch: '#e0f2fe', flosse: '#0284c7', streifen: null, form: 0.24},
    {koerper: '#facc15', bauch: '#fef9c3', flosse: '#1e3a8a', streifen: '#1e3a8a', form: 0.5},
    {koerper: '#a78bfa', bauch: '#ede9fe', flosse: '#7c3aed', streifen: null, form: 0.3},
    {koerper: '#34d399', bauch: '#d1fae5', flosse: '#059669', streifen: '#065f46', form: 0.28},
    {koerper: '#f472b6', bauch: '#fce7f3', flosse: '#db2777', streifen: null, form: 0.38},
    {koerper: '#60a5fa', bauch: '#dbeafe', flosse: '#facc15', streifen: '#1d4ed8', form: 0.42},
    {koerper: '#ef4444', bauch: '#fee2e2', flosse: '#b91c1c', streifen: '#fecaca', form: 0.3},
];

const KORALLEN = ['#f97362', '#fb923c', '#f472b6', '#c084fc', '#fbbf24'];
const RIFF_FARBEN = {
    ast: KORALLEN,
    hirn: ['#d9a066', '#c7b35a', '#b98b5e'],
    faecher: ['#a855f7', '#f97316', '#ec4899'],
    roehren: ['#facc15', '#fb923c', '#f87171'],
    weich: ['#f9a8d4', '#c4b5fd', '#fdba74'],
};

function linieZiehen(ctx, x1, y1, x2, y2) {
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();
}

export function erzeugeAquarium() {
    let b = 0;
    let h = 0;
    let sandY = 0;
    let felsen = [];
    let pflanzenHinten = [];
    let pflanzenVorne = [];
    let korallen = [];
    let anemone = null;
    let clowns = [];
    let krabben = [];
    let seesterne = [];
    let seeigel = [];
    let schnecke = null;
    let muraene = null;
    let anemoneOffen = 1;
    let kiesel = [];
    let strahlen = [];
    const fische = [];
    const blasen = [];
    const schwebeteilchen = [];
    let t = 0;
    let blasenZeit = 0;

    const sandHoehe = (x) => sandY + Math.sin(x * 0.009) * h * 0.012 + Math.sin(x * 0.023 + 1.3) * h * 0.006;

    const neuesZiel = (f) => {
        f.zx = zufall(b * 0.06, b * 0.94);
        f.zy = zufall(h * 0.12, sandY - h * 0.1);
    };

    const neuerFisch = (art) => {
        const laenge = klemme(Math.min(b, h * 1.6) * zufall(0.045, 0.075), 34, 120);
        const f = {
            art,
            laenge,
            x: zufall(b * 0.1, b * 0.9),
            y: zufall(h * 0.15, sandY - h * 0.12),
            vx: zufall(-1, 1) * laenge * 0.3,
            vy: 0,
            richtung: 1,
            phase: zufall(0, Math.PI * 2),
            mut: zufall(0.22, 0.75),
            flieht: false,
            versteckt: false,
            versteck: 0,
            zx: 0,
            zy: 0,
            tempo: zufall(0.8, 1.2),
        };
        neuesZiel(f);
        return f;
    };

    const versteckPunkt = (f) => {
        const fels = felsen[f.versteck % felsen.length];
        return {
            x: fels.x + Math.sin(t * 0.4 + f.phase) * fels.b * 0.12,
            y: fels.y + fels.h * 0.05 + Math.cos(t * 0.3 + f.phase) * fels.h * 0.08,
        };
    };

    function groesse(breite, hoehe, einstellungen) {
        const neu = breite !== b || hoehe !== h;
        b = breite;
        h = hoehe;
        sandY = h * 0.84;
        const zz = zufallMitSaat(7);

        if (neu) {
            // Felsen = Verstecke (vorne, verdecken die Fische)
            felsen = [0.14, 0.58, 0.88].map((anteil, i) => {
                const fb = b * zz.zwischen(0.15, 0.2);
                const fh = Math.max(h * 0.2, fb * 0.62);
                return {x: b * anteil + zz.zwischen(-0.03, 0.03) * b, y: sandY - fh * 0.32, b: fb, h: fh, saat: 11 + i};
            });

            pflanzenHinten = Array.from({length: Math.round(b / 55)}, () => ({
                x: zz.zwischen(0, b),
                hoehe: h * zz.zwischen(0.2, 0.5),
                phase: zz.zwischen(0, 6),
                farbe: zz.aus(['#1d6b5b', '#1f7a62', '#235f63']),
                breite: zz.zwischen(4, 7),
            }));

            pflanzenVorne = [];
            felsen.forEach((fels) => {
                for (let i = 0; i < 4; i++) {
                    pflanzenVorne.push({
                        x: fels.x + zz.zwischen(-0.6, 0.6) * fels.b,
                        hoehe: h * zz.zwischen(0.16, 0.34),
                        phase: zz.zwischen(0, 6),
                        farbe: zz.aus(['#2f9e6e', '#3cae74', '#2b8a63']),
                        breite: zz.zwischen(6, 9),
                    });
                }
            });

            // Korallenriff: verschiedene Arten über den ganzen Boden verteilt
            const typen = ['ast', 'hirn', 'faecher', 'roehren', 'weich'];
            korallen = Array.from({length: 13}, (_, i) => {
                const typ = typen[(i * 3 + 1) % typen.length];
                return {
                    typ,
                    x: (0.03 + i * 0.076 + zz.zwischen(-0.015, 0.015)) * b,
                    tiefe: zz.zwischen(0, 0.05),
                    groesse: Math.min(b, h) * zz.zwischen(0.065, 0.11),
                    farbe: zz.aus(RIFF_FARBEN[typ]),
                    saat: 100 + i,
                    phase: zz.zwischen(0, 6),
                };
            });
            const einheit = Math.min(b, h);
            anemone = {x: b * 0.37, groesse: einheit * 0.1};
            clowns = [0, Math.PI].map((phase) => ({
                art: ARTEN[1], laenge: einheit * 0.045, phase, x: anemone.x, y: sandY, vx: 0, vy: 0, richtung: 1, winkel: phase,
            }));
            krabben = [0.3, 0.74].map((anteil, i) => ({
                x: b * anteil, zx: b * anteil, richtung: 1, phase: i * 2, warten: 0, alpha: 1, groesse: einheit * 0.032,
            }));
            seesterne = [
                {x: b * 0.47, y: 0.07, farbe: '#fb923c', groesse: einheit * 0.03, drehung: 0.3},
                {x: b * 0.79, y: 0.09, farbe: '#a855f7', groesse: einheit * 0.026, drehung: 1.1},
                {fels: 1, dx: -0.25, farbe: '#ef4444', groesse: einheit * 0.028, drehung: 0.7},
            ];
            seeigel = [{fels: 2, dx: -0.3, groesse: einheit * 0.022}, {fels: 0, dx: 0.32, groesse: einheit * 0.018}];
            schnecke = {x: b * 0.62, richtung: -1, raus: 1, groesse: einheit * 0.026};
            muraene = {fels: 0, raus: 0, phase: 0, maul: 0};

            kiesel = Array.from({length: Math.round(b / 9)}, () => ({
                x: zz.zwischen(0, b),
                y: zz.zwischen(0.02, 0.15),
                r: zz.zwischen(1.5, 4.5),
                farbe: zz.aus(['#b89a6c', '#c9ad80', '#9c8460', '#e3cfa7']),
            }));

            strahlen = Array.from({length: 6}, () => ({
                x: zz.zwischen(-0.1, 1.0),
                breite: zz.zwischen(0.04, 0.1),
                phase: zz.zwischen(0, 6),
            }));
        }

        const anzahl = Math.round(14 * (einstellungen.menge / 100));
        while (fische.length > anzahl) fische.pop();
        while (fische.length < anzahl) {
            const f = neuerFisch(ARTEN[fische.length % ARTEN.length]);
            f.versteck = fische.length % 3;
            fische.push(f);
        }
        fische.forEach((f) => {
            f.x = klemme(f.x, 0, b);
            f.y = klemme(f.y, h * 0.1, sandY - h * 0.05);
            neuesZiel(f);
        });

        if (schwebeteilchen.length === 0) {
            for (let i = 0; i < 70; i++) {
                schwebeteilchen.push({x: Math.random(), y: Math.random(), r: zufall(1, 3), phase: zufall(0, 6)});
            }
        }
    }

    // ── Seltene Besuche ──────────────────────────────────────────────────
    function neueSchildkroete() {
        const g = klemme(Math.min(b, h) * 0.13, 60, 150);
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const k = {x: richtung > 0 ? -g * 1.5 : b + g * 1.5, y: zufall(h * 0.22, h * 0.45), phase: 0, alpha: 0};
        return {
            ebene: 'mitte',
            schritt(dt, z) {
                const flucht = z.unruhe > 0.45;
                k.x += richtung * (b / 45) * (flucht ? 3.5 : 1) * dt;
                k.y += Math.sin(t * 0.35) * 5 * dt;
                k.phase += dt * (flucht ? 6 : 1.4);
                k.alpha = blende(k.alpha, 1, dt, 0.5);
                return richtung > 0 ? k.x < b + g * 2 : k.x > -g * 2;
            },
            zeichne(ctx) {
                const schlag = Math.sin(k.phase);
                ctx.globalAlpha = k.alpha;
                ctx.translate(k.x, k.y);
                ctx.scale(richtung, 1);
                ctx.rotate(Math.sin(k.phase * 0.5) * 0.04);
                // hintere Flosse
                ctx.fillStyle = '#4f7d4c';
                ellipse(ctx, -g * 0.42, g * 0.12, g * 0.16, g * 0.06, 0.5 + schlag * 0.3);
                ctx.fill();
                // Kopf und Hals
                ctx.fillStyle = '#6f9a5e';
                ellipse(ctx, g * 0.42, g * 0.02, g * 0.16, g * 0.08, -0.1);
                ctx.fill();
                ellipse(ctx, g * 0.58, -g * 0.02, g * 0.12, g * 0.1);
                ctx.fill();
                ctx.fillStyle = '#0f172a';
                ellipse(ctx, g * 0.63, -g * 0.05, g * 0.022, g * 0.022);
                ctx.fill();
                // Bauchpanzer
                ctx.fillStyle = '#d9c58a';
                ellipse(ctx, 0, g * 0.09, g * 0.46, g * 0.08);
                ctx.fill();
                // Rückenpanzer mit Schildern
                const panzer = new Path2D();
                panzer.moveTo(-g * 0.5, g * 0.08);
                panzer.bezierCurveTo(-g * 0.42, -g * 0.42, g * 0.36, -g * 0.44, g * 0.46, g * 0.06);
                panzer.quadraticCurveTo(0, g * 0.16, -g * 0.5, g * 0.08);
                const v = ctx.createLinearGradient(0, -g * 0.4, 0, g * 0.1);
                v.addColorStop(0, '#7c9a3d');
                v.addColorStop(1, '#3f6a2b');
                ctx.fillStyle = v;
                ctx.fill(panzer);
                ctx.save();
                ctx.clip(panzer);
                ctx.fillStyle = 'rgba(40,70,25,.45)';
                [[-0.25, -0.12], [0.02, -0.2], [0.27, -0.1], [-0.08, 0.0], [0.17, 0.02], [-0.33, 0.02]].forEach(([sx, sy]) => {
                    ellipse(ctx, sx * g, sy * g, g * 0.1, g * 0.07);
                    ctx.fill();
                });
                ctx.restore();
                ctx.strokeStyle = 'rgba(230,220,160,.6)';
                ctx.lineWidth = Math.max(1.5, g * 0.02);
                ctx.stroke(panzer);
                // vordere Flosse (paddelt)
                ctx.fillStyle = '#5f8f55';
                ctx.save();
                ctx.translate(g * 0.22, g * 0.12);
                ctx.rotate(0.55 + schlag * 0.5);
                ellipse(ctx, g * 0.2, 0, g * 0.26, g * 0.07);
                ctx.fill();
                ctx.restore();
            },
        };
    }

    function neueQualle() {
        const g = klemme(Math.min(b, h) * 0.06, 28, 80);
        const q = {x: zufall(b * 0.15, b * 0.85), y: sandY + g, phase: 0, alpha: 0, farbe: zufallAus(['#f0abfc', '#c4b5fd', '#fda4af'])};
        let flucht = false;
        return {
            ebene: 'mitte',
            schritt(dt, z) {
                if (z.unruhe > 0.45) flucht = true;
                q.phase += dt * 1.5;
                const puls = (Math.sin(q.phase) + 1) / 2;
                q.y -= (8 + puls * 26) * dt * (flucht ? 2.5 : 1);
                q.x += Math.sin(q.phase * 0.25) * 10 * dt;
                q.alpha = blende(q.alpha, flucht ? 0 : 0.9, dt, flucht ? 0.8 : 0.3);
                return q.y > -g * 3 && !(flucht && q.alpha <= 0.01);
            },
            zeichne(ctx) {
                const puls = (Math.sin(q.phase) + 1) / 2;
                const w = g * (1 + puls * 0.12);
                const hh = g * (0.85 - puls * 0.15);
                ctx.globalAlpha = q.alpha;
                ctx.translate(q.x, q.y);
                const schein = ctx.createRadialGradient(0, -hh * 0.4, 0, 0, -hh * 0.4, g * 2.2);
                schein.addColorStop(0, mitAlpha(q.farbe, 0.35));
                schein.addColorStop(1, mitAlpha(q.farbe, 0));
                ctx.fillStyle = schein;
                ctx.fillRect(-g * 2.2, -g * 2.6, g * 4.4, g * 4.4);
                // Tentakel
                ctx.strokeStyle = mitAlpha(q.farbe, 0.65);
                ctx.lineWidth = Math.max(1.5, g * 0.04);
                ctx.lineCap = 'round';
                for (let i = 0; i < 6; i++) {
                    const x0 = -w * 0.7 + (i / 5) * w * 1.4;
                    ctx.beginPath();
                    ctx.moveTo(x0, 0);
                    for (let j = 1; j <= 6; j++) {
                        ctx.lineTo(x0 + Math.sin(q.phase * 1.3 + i + j * 0.8) * g * 0.12, j * g * 0.28);
                    }
                    ctx.stroke();
                }
                // Schirm
                ctx.fillStyle = mitAlpha(q.farbe, 0.6);
                ctx.beginPath();
                ctx.moveTo(-w, 0);
                ctx.bezierCurveTo(-w, -hh * 1.35, w, -hh * 1.35, w, 0);
                for (let i = 4; i >= 0; i--) {
                    ctx.quadraticCurveTo(-w + (i + 0.5) * (w * 2 / 5), g * 0.12, -w + i * (w * 2 / 5), 0);
                }
                ctx.fill();
                ctx.fillStyle = 'rgba(255,255,255,.35)';
                ellipse(ctx, -w * 0.3, -hh * 0.65, w * 0.25, hh * 0.18, -0.4);
                ctx.fill();
            },
        };
    }

    function neuesSeepferdchen() {
        const g = klemme(Math.min(b, h) * 0.17, 70, 170);
        const pflanze = zufallAus(pflanzenHinten.filter((p) => p.x > b * 0.25 && p.x < b * 0.8)) || {x: b * 0.5};
        const sp = {x: pflanze.x + g * 0.25, y: sandY - h * 0.2, alpha: 0, zeit: 0, phase: zufall(0, 6)};
        const dauer = 24;
        let flucht = false;
        return {
            ebene: 'mitte',
            schritt(dt, z) {
                if (z.unruhe > 0.4) flucht = true;
                sp.zeit += dt;
                sp.phase += dt;
                const ziel = flucht || sp.zeit > dauer ? 0 : 1;
                sp.alpha = blende(sp.alpha, ziel, dt, flucht ? 1 : 0.35);
                return !(ziel === 0 && sp.alpha <= 0.01);
            },
            zeichne(ctx) {
                ctx.globalAlpha = sp.alpha;
                ctx.translate(sp.x, sp.y + Math.sin(sp.phase * 1.2) * g * 0.05);
                ctx.scale(g, g);
                ctx.rotate(Math.sin(sp.phase * 0.7) * 0.05);
                const koerper = '#f59e0b';
                ctx.lineCap = 'round';
                // Schwanz mit Kringel
                ctx.strokeStyle = koerper;
                ctx.lineWidth = 0.075;
                ctx.beginPath();
                ctx.moveTo(0.03, 0.08);
                ctx.quadraticCurveTo(-0.06, 0.26, 0.0, 0.36);
                ctx.arc(0.05, 0.36, 0.05, Math.PI, Math.PI * 2.6);
                ctx.stroke();
                // Rumpf
                ctx.fillStyle = koerper;
                ellipse(ctx, 0.05, -0.06, 0.12, 0.2, -0.15);
                ctx.fill();
                // Rückenflosse (flattert)
                ctx.fillStyle = 'rgba(253,230,138,.85)';
                ellipse(ctx, -0.09, -0.04, 0.05 * (0.6 + Math.abs(Math.sin(sp.phase * 9)) * 0.4), 0.08, 0.2);
                ctx.fill();
                // Kopf und Schnauze
                ctx.fillStyle = koerper;
                ellipse(ctx, 0.0, -0.33, 0.085, 0.09);
                ctx.fill();
                ellipse(ctx, 0.13, -0.3, 0.09, 0.032, 0.2);
                ctx.fill();
                // Krone
                ctx.beginPath();
                ctx.moveTo(-0.06, -0.4);
                ctx.lineTo(-0.02, -0.47);
                ctx.lineTo(0.0, -0.4);
                ctx.lineTo(0.04, -0.45);
                ctx.lineTo(0.05, -0.38);
                ctx.fill();
                // Bauchringe
                ctx.strokeStyle = 'rgba(180,83,9,.55)';
                ctx.lineWidth = 0.012;
                for (let i = 0; i < 5; i++) {
                    ctx.beginPath();
                    ctx.arc(0.04, -0.2 + i * 0.07, 0.11, 0.1, 1.1);
                    ctx.stroke();
                }
                // Auge
                ctx.fillStyle = '#ffffff';
                ellipse(ctx, 0.03, -0.35, 0.028, 0.028);
                ctx.fill();
                ctx.fillStyle = '#0f172a';
                ellipse(ctx, 0.036, -0.35, 0.015, 0.015);
                ctx.fill();
            },
        };
    }

    function neuerRochen() {
        const g = klemme(Math.min(b, h) * 0.16, 70, 190);
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const r = {x: richtung > 0 ? -g * 1.5 : b + g * 1.5, y: zufall(h * 0.45, h * 0.62), phase: 0, alpha: 0};
        return {
            ebene: 'mitte',
            schritt(dt, z) {
                r.x += richtung * (b / 32) * (z.unruhe > 0.45 ? 3 : 1) * dt;
                r.y += Math.sin(t * 0.4) * 6 * dt;
                r.phase += dt * 2.2;
                r.alpha = blende(r.alpha, 1, dt, 0.5);
                return richtung > 0 ? r.x < b + g * 2 : r.x > -g * 2;
            },
            zeichne(ctx) {
                const welle = Math.sin(r.phase) * g * 0.08;
                ctx.globalAlpha = r.alpha;
                ctx.translate(r.x, r.y);
                ctx.scale(richtung, 1);
                // Schwanz
                ctx.strokeStyle = '#334155';
                ctx.lineWidth = Math.max(2, g * 0.025);
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(-g * 0.3, 0);
                ctx.quadraticCurveTo(-g * 0.6, Math.sin(r.phase * 0.7) * g * 0.06, -g * 0.85, Math.sin(r.phase * 0.7 + 1) * g * 0.08);
                ctx.stroke();
                // Körper mit wellenden Flügelspitzen (leicht von oben gesehen)
                const koerper = new Path2D();
                koerper.moveTo(g * 0.38, 0);
                koerper.quadraticCurveTo(g * 0.15, -g * 0.12 - welle, -g * 0.05, -g * 0.36 - welle);
                koerper.quadraticCurveTo(-g * 0.12, -g * 0.1, -g * 0.32, 0);
                koerper.quadraticCurveTo(-g * 0.12, g * 0.1, -g * 0.05, g * 0.26 + welle * 0.5);
                koerper.quadraticCurveTo(g * 0.15, g * 0.1 + welle * 0.5, g * 0.38, 0);
                const v = ctx.createLinearGradient(0, -g * 0.35, 0, g * 0.25);
                v.addColorStop(0, '#64748b');
                v.addColorStop(1, '#475569');
                ctx.fillStyle = v;
                ctx.fill(koerper);
                ctx.fillStyle = 'rgba(226,232,240,.35)';
                [[0.05, -0.12], [-0.08, -0.04], [0.12, 0.03], [-0.02, 0.08]].forEach(([fx, fy]) => {
                    ellipse(ctx, fx * g, fy * g, g * 0.025, g * 0.025);
                    ctx.fill();
                });
                ctx.fillStyle = '#0f172a';
                ellipse(ctx, g * 0.24, -g * 0.04, g * 0.018, g * 0.018);
                ctx.fill();
            },
        };
    }

    function neuerKugelfisch() {
        const g = klemme(Math.min(b, h) * 0.05, 26, 60);
        const richtung = Math.random() < 0.5 ? 1 : -1;
        const k = {x: richtung > 0 ? -g * 2 : b + g * 2, y: zufall(h * 0.25, h * 0.55), phase: 0, aufgeblasen: 0};
        return {
            ebene: 'mitte',
            schritt(dt, z) {
                k.aufgeblasen = blende(k.aufgeblasen, z.unruhe > 0.35 ? 1 : 0, dt, z.unruhe > 0.35 ? 2.5 : 0.4);
                k.x += richtung * (b / 55) * (1 - k.aufgeblasen * 0.6) * dt;
                k.y += Math.sin(t * 0.8 + k.phase) * 8 * dt;
                k.phase += dt * (3 - k.aufgeblasen * 2);
                return richtung > 0 ? k.x < b + g * 3 : k.x > -g * 3;
            },
            zeichne(ctx) {
                const a = glatt(k.aufgeblasen);
                const rx = g * mische(0.55, 0.85, a);
                const ry = g * mische(0.38, 0.82, a);
                ctx.translate(k.x, k.y);
                ctx.scale(richtung, 1);
                // Schwanzflosse
                ctx.fillStyle = '#ca8a04';
                ctx.beginPath();
                ctx.moveTo(-rx * 0.9, 0);
                ctx.lineTo(-rx * 1.35, -ry * 0.35 + Math.sin(k.phase * 3) * 3);
                ctx.lineTo(-rx * 1.35, ry * 0.35 + Math.sin(k.phase * 3) * 3);
                ctx.fill();
                // Stacheln (nur aufgeblasen)
                if (a > 0.05) {
                    ctx.strokeStyle = `rgba(120,80,20,${a})`;
                    ctx.lineWidth = 1.5;
                    for (let i = 0; i < 18; i++) {
                        const w = (i / 18) * Math.PI * 2;
                        linieZiehen(ctx, Math.cos(w) * rx, Math.sin(w) * ry, Math.cos(w) * (rx + g * 0.18 * a), Math.sin(w) * (ry + g * 0.18 * a));
                    }
                }
                const v = ctx.createLinearGradient(0, -ry, 0, ry);
                v.addColorStop(0, '#eab308');
                v.addColorStop(0.6, '#fde047');
                v.addColorStop(1, '#fef9c3');
                ctx.fillStyle = v;
                ellipse(ctx, 0, 0, rx, ry);
                ctx.fill();
                ctx.fillStyle = 'rgba(120,80,20,.35)';
                [[-0.3, -0.35], [0.05, -0.5], [-0.1, -0.1], [0.3, -0.25]].forEach(([fx, fy]) => {
                    ellipse(ctx, fx * rx, fy * ry, g * 0.06, g * 0.05);
                    ctx.fill();
                });
                ctx.fillStyle = '#ffffff';
                ellipse(ctx, rx * 0.55, -ry * 0.2, g * 0.12, g * 0.12);
                ctx.fill();
                ctx.fillStyle = '#0f172a';
                ellipse(ctx, rx * 0.6, -ry * 0.2, g * 0.06, g * 0.06);
                ctx.fill();
                ctx.strokeStyle = '#78350f';
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                ctx.arc(rx * 0.9, ry * 0.1, g * 0.06, -0.8, 0.8);
                ctx.stroke();
            },
        };
    }

    const besuche = new Besuchsplaner({
        schildkroete: neueSchildkroete,
        qualle: neueQualle,
        seepferdchen: neuesSeepferdchen,
        rochen: neuerRochen,
        kugelfisch: neuerKugelfisch,
    });

    // ── Bewohner am Boden ────────────────────────────────────────────────
    function schrittBewohner(dt, z) {
        const unruhig = z.unruhe > 0.45;

        // Anemone schließt sich bei Lärm, Clownfische verkriechen sich
        anemoneOffen = blende(anemoneOffen, unruhig ? 0.3 : 1, dt, unruhig ? 1.5 : 0.4);
        const anemoneOben = sandHoehe(anemone.x) + h * 0.03 - anemone.groesse * 0.5;
        clowns.forEach((c, i) => {
            c.winkel += dt * (0.9 + i * 0.2);
            const radius = anemone.groesse * 0.7 * anemoneOffen;
            const nx = anemone.x + Math.cos(c.winkel) * radius;
            const ny = anemoneOben - anemone.groesse * 0.35 * anemoneOffen + Math.sin(c.winkel * 1.3) * anemone.groesse * 0.2;
            c.vx = (nx - c.x) / Math.max(dt, 0.001);
            c.vy = (ny - c.y) / Math.max(dt, 0.001);
            c.x = nx;
            c.y = ny;
            if (Math.abs(c.vx) > 2) c.richtung += (Math.sign(c.vx) - c.richtung) * Math.min(1, dt * 6);
            c.phase += dt * 6;
        });

        // Krabben laufen seitwärts über den Sand und flüchten unter die Felsen
        krabben.forEach((k) => {
            let ziel = k.zx;
            if (unruhig) {
                const fels = felsen.reduce((naechster, f) => (Math.abs(f.x - k.x) < Math.abs(naechster.x - k.x) ? f : naechster), felsen[0]);
                ziel = fels.x + Math.sign(k.x - fels.x || 1) * fels.b * 0.3;
            }
            const dx = ziel - k.x;
            const tempo = k.groesse * (unruhig ? 6 : 1.6);
            if (Math.abs(dx) > tempo * dt) {
                if (k.warten > 0 && !unruhig) {
                    k.warten -= dt;
                } else {
                    k.x += Math.sign(dx) * tempo * dt;
                    k.phase += dt * (unruhig ? 18 : 9);
                }
            } else if (!unruhig) {
                k.zx = klemme(k.x + zufall(-0.25, 0.25) * b, b * 0.05, b * 0.95);
                k.warten = zufall(1, 4);
            }
            k.alpha = blende(k.alpha, unruhig && Math.abs(dx) < k.groesse * 2 ? 0 : 1, dt, unruhig ? 2 : 0.6);
        });

        // Schnecke kriecht langsam, zieht sich bei Lärm ins Haus zurück
        schnecke.raus = blende(schnecke.raus, unruhig ? 0 : 1, dt, unruhig ? 2 : 0.3);
        if (schnecke.raus > 0.9) {
            schnecke.x += schnecke.richtung * schnecke.groesse * 0.25 * dt;
            if (schnecke.x < b * 0.5 || schnecke.x > b * 0.7) schnecke.richtung *= -1;
        }

        // Muräne schaut regelmäßig aus der Felsspalte
        muraene.phase += dt;
        const zyklus = muraene.phase % 14;
        const zielRaus = unruhig ? 0 : zyklus < 9 ? 1 : 0.15;
        muraene.raus = blende(muraene.raus, zielRaus, dt, unruhig ? 2.5 : 0.35);
        muraene.maul = Math.max(0, Math.sin(muraene.phase * 0.9)) * muraene.raus;
    }

    function zeichneRiffKoralle(ctx, k) {
        const basis = sandHoehe(k.x) + k.tiefe * h + 3;
        const s = k.groesse;
        const zz = zufallMitSaat(k.saat);
        if (k.typ === 'ast') {
            zeichneKoralle(ctx, {...k, x: k.x});
            return;
        }
        if (k.typ === 'hirn') {
            ctx.fillStyle = k.farbe;
            ctx.beginPath();
            ctx.ellipse(k.x, basis, s * 0.55, s * 0.45, 0, Math.PI, Math.PI * 2);
            ctx.fill();
            ctx.save();
            ctx.beginPath();
            ctx.ellipse(k.x, basis, s * 0.55, s * 0.45, 0, Math.PI, Math.PI * 2);
            ctx.clip();
            ctx.strokeStyle = 'rgba(90,60,30,.35)';
            ctx.lineWidth = Math.max(1.5, s * 0.03);
            for (let i = 1; i <= 4; i++) {
                ctx.beginPath();
                for (let w = Math.PI; w <= Math.PI * 2 + 0.01; w += 0.12) {
                    const r = 1 - i * 0.2 + Math.sin(w * 9 + i + zz() * 0.5) * 0.04;
                    const x = k.x + Math.cos(w) * s * 0.55 * r;
                    const y = basis + Math.sin(w) * s * 0.45 * r;
                    w === Math.PI ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
                }
                ctx.stroke();
            }
            ctx.restore();
        } else if (k.typ === 'faecher') {
            const sway = Math.sin(t * 0.7 + k.phase) * s * 0.05;
            ctx.fillStyle = mitAlpha(k.farbe, 0.35);
            ctx.strokeStyle = k.farbe;
            ctx.lineWidth = Math.max(1.5, s * 0.025);
            ctx.beginPath();
            ctx.moveTo(k.x, basis);
            for (let i = 0; i <= 10; i++) {
                const w = Math.PI * 1.1 + (i / 10) * Math.PI * 0.8;
                ctx.lineTo(k.x + Math.cos(w) * s * 0.7 + sway, basis + Math.sin(w) * s * 0.95);
            }
            ctx.closePath();
            ctx.fill();
            for (let i = 0; i <= 8; i++) {
                const w = Math.PI * 1.12 + (i / 8) * Math.PI * 0.76;
                linieZiehen(ctx, k.x, basis, k.x + Math.cos(w) * s * 0.68 + sway, basis + Math.sin(w) * s * 0.92);
            }
            for (let r = 0.35; r < 1; r += 0.22) {
                ctx.beginPath();
                ctx.ellipse(k.x + sway * r, basis, s * 0.7 * r, s * 0.95 * r, 0, Math.PI * 1.12, Math.PI * 1.88);
                ctx.stroke();
            }
        } else if (k.typ === 'roehren') {
            for (let i = 0; i < 5; i++) {
                const x = k.x + (i - 2) * s * 0.17 + zz.zwischen(-3, 3);
                const hoch = s * zz.zwischen(0.45, 0.95);
                const breit = s * 0.13;
                ctx.fillStyle = k.farbe;
                ctx.fillRect(x - breit / 2, basis - hoch, breit, hoch);
                ctx.fillStyle = 'rgba(255,255,255,.35)';
                ctx.fillRect(x - breit / 2, basis - hoch, breit * 0.3, hoch);
                ctx.fillStyle = '#7c2d12';
                ellipse(ctx, x, basis - hoch, breit * 0.5, breit * 0.22);
                ctx.fill();
            }
        } else if (k.typ === 'weich') {
            ctx.lineCap = 'round';
            for (let i = 0; i < 6; i++) {
                const neigung = (i - 2.5) * 0.28;
                const sway = Math.sin(t * 1.1 + k.phase + i) * s * 0.08;
                const ex = k.x + Math.sin(neigung) * s * 0.7 + sway;
                const ey = basis - Math.cos(neigung) * s * 0.8;
                ctx.strokeStyle = k.farbe;
                ctx.lineWidth = s * 0.14;
                ctx.beginPath();
                ctx.moveTo(k.x, basis);
                ctx.quadraticCurveTo(k.x + Math.sin(neigung) * s * 0.2, basis - s * 0.4, ex, ey);
                ctx.stroke();
                ctx.fillStyle = 'rgba(255,255,255,.55)';
                for (let j = 1; j <= 3; j++) {
                    const p = j / 4;
                    ellipse(ctx, mische(k.x, ex, p), mische(basis, ey, p), s * 0.025, s * 0.025);
                    ctx.fill();
                }
            }
        }
    }

    function zeichneAnemone(ctx, trueb) {
        const s = anemone.groesse;
        const basis = sandHoehe(anemone.x) + h * 0.03;
        const oben = basis - s * 0.5;
        ctx.fillStyle = mischeFarbe('#be185d', '#5b4a4a', trueb);
        ctx.beginPath();
        ctx.moveTo(anemone.x - s * 0.28, basis);
        ctx.quadraticCurveTo(anemone.x - s * 0.2, oben + s * 0.1, anemone.x - s * 0.32, oben);
        ctx.lineTo(anemone.x + s * 0.32, oben);
        ctx.quadraticCurveTo(anemone.x + s * 0.2, oben + s * 0.1, anemone.x + s * 0.28, basis);
        ctx.fill();
        clowns.forEach((c) => zeichneFisch(ctx, c, trueb));
        ctx.lineCap = 'round';
        const laenge = s * 0.75 * anemoneOffen;
        for (let i = 0; i < 22; i++) {
            const anteil = i / 21;
            const w = -Math.PI * 0.95 + anteil * Math.PI * 0.9;
            const sway = Math.sin(t * 1.4 + i * 0.7) * s * 0.08 * anemoneOffen;
            const sx = anemone.x + (anteil - 0.5) * s * 0.6;
            const ex = sx + Math.cos(w) * laenge * 0.6 + sway;
            const ey = oben + Math.sin(w) * laenge;
            ctx.strokeStyle = mischeFarbe('#f472b6', '#7a6a6a', trueb);
            ctx.lineWidth = Math.max(2, s * 0.06);
            ctx.beginPath();
            ctx.moveTo(sx, oben);
            ctx.quadraticCurveTo(sx + sway, oben - laenge * 0.5, ex, ey);
            ctx.stroke();
            ctx.fillStyle = '#fbcfe8';
            ellipse(ctx, ex, ey, s * 0.04, s * 0.04);
            ctx.fill();
        }
    }

    function zeichneKrabbe(ctx, k) {
        if (k.alpha <= 0.01) return;
        const s = k.groesse;
        const y = sandHoehe(k.x) + h * 0.045;
        const schritt = Math.sin(k.phase);
        ctx.save();
        ctx.globalAlpha = k.alpha;
        ctx.translate(k.x, y);
        ctx.strokeStyle = '#b91c1c';
        ctx.lineWidth = Math.max(1.5, s * 0.12);
        ctx.lineCap = 'round';
        for (const seite of [-1, 1]) {
            for (let i = 0; i < 3; i++) {
                const knick = seite * s * (1.1 + i * 0.15);
                const wackeln = (i % 2 ? schritt : -schritt) * s * 0.15;
                ctx.beginPath();
                ctx.moveTo(seite * s * 0.5, -s * 0.2 + i * s * 0.12);
                ctx.lineTo(knick, -s * 0.55 + i * s * 0.15 + wackeln);
                ctx.lineTo(knick + seite * s * 0.25, s * 0.05);
                ctx.stroke();
            }
        }
        // Scheren
        ctx.fillStyle = '#ef4444';
        for (const seite of [-1, 1]) {
            linieZiehen(ctx, seite * s * 0.45, -s * 0.45, seite * s * 0.8, -s * 0.95);
            ellipse(ctx, seite * s * 0.85, -s * 1.05, s * 0.28, s * 0.2, seite * 0.5);
            ctx.fill();
        }
        // Körper und Stielaugen
        ctx.fillStyle = '#dc2626';
        ellipse(ctx, 0, -s * 0.35, s * 0.7, s * 0.45);
        ctx.fill();
        ctx.strokeStyle = '#991b1b';
        ctx.lineWidth = Math.max(1, s * 0.07);
        for (const seite of [-1, 1]) {
            linieZiehen(ctx, seite * s * 0.2, -s * 0.7, seite * s * 0.25, -s * 1.0);
            ctx.fillStyle = '#ffffff';
            ellipse(ctx, seite * s * 0.25, -s * 1.05, s * 0.11, s * 0.11);
            ctx.fill();
            ctx.fillStyle = '#0f172a';
            ellipse(ctx, seite * s * 0.25, -s * 1.05, s * 0.06, s * 0.06);
            ctx.fill();
        }
        ctx.restore();
    }

    function zeichneSeestern(ctx, st) {
        let x = st.x;
        let y = st.y !== undefined ? sandHoehe(st.x) + st.y * h : 0;
        if (st.fels !== undefined) {
            const fels = felsen[st.fels];
            x = fels.x + st.dx * fels.b;
            y = fels.y - fels.h * 0.35;
        }
        const s = st.groesse;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(st.drehung + Math.sin(t * 0.1) * 0.03);
        ctx.fillStyle = st.farbe;
        ctx.beginPath();
        for (let i = 0; i < 10; i++) {
            const w = (i / 10) * Math.PI * 2 - Math.PI / 2;
            const r = i % 2 === 0 ? s : s * 0.42;
            const px = Math.cos(w) * r;
            const py = Math.sin(w) * r * 0.75;
            i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
        }
        ctx.closePath();
        ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,.45)';
        for (let i = 0; i < 5; i++) {
            const w = (i / 5) * Math.PI * 2 - Math.PI / 2;
            ellipse(ctx, Math.cos(w) * s * 0.5, Math.sin(w) * s * 0.38, s * 0.08, s * 0.08);
            ctx.fill();
        }
        ctx.restore();
    }

    function zeichneSeeigel(ctx, si) {
        const fels = felsen[si.fels];
        const x = fels.x + si.dx * fels.b;
        const y = fels.y - fels.h * 0.45;
        const s = si.groesse;
        ctx.strokeStyle = '#3b0764';
        ctx.lineWidth = 1.5;
        for (let i = 0; i < 20; i++) {
            const w = Math.PI + (i / 19) * Math.PI;
            const lang = s * (1.6 + Math.sin(t * 0.8 + i) * 0.08);
            linieZiehen(ctx, x, y, x + Math.cos(w) * lang, y + Math.sin(w) * lang);
        }
        ctx.fillStyle = '#6b21a8';
        ellipse(ctx, x, y, s, s * 0.8);
        ctx.fill();
    }

    function zeichneSchnecke(ctx) {
        const s = schnecke.groesse;
        const y = sandHoehe(schnecke.x) + h * 0.06;
        ctx.save();
        ctx.translate(schnecke.x, y);
        ctx.scale(schnecke.richtung, 1);
        const raus = glatt(schnecke.raus);
        if (raus > 0.02) {
            ctx.fillStyle = '#d6c7a1';
            ctx.beginPath();
            ctx.moveTo(-s * 0.8, 0);
            ctx.lineTo(s * (0.4 + 0.8 * raus), 0);
            ctx.quadraticCurveTo(s * (0.6 + 0.8 * raus), -s * 0.4 * raus, s * 0.3, -s * 0.35);
            ctx.lineTo(-s * 0.8, -s * 0.1);
            ctx.fill();
            ctx.strokeStyle = '#a8977a';
            ctx.lineWidth = Math.max(1, s * 0.08);
            linieZiehen(ctx, s * (0.4 + 0.6 * raus), -s * 0.3 * raus, s * (0.55 + 0.75 * raus), -s * 0.85 * raus);
            ctx.fillStyle = '#1f2937';
            ellipse(ctx, s * (0.55 + 0.75 * raus), -s * 0.85 * raus, s * 0.07, s * 0.07);
            ctx.fill();
        }
        ctx.fillStyle = '#b45309';
        ellipse(ctx, -s * 0.15, -s * 0.55, s * 0.6, s * 0.55);
        ctx.fill();
        ctx.strokeStyle = '#fde68a';
        ctx.lineWidth = Math.max(1.5, s * 0.1);
        ctx.beginPath();
        for (let w = 0; w < Math.PI * 4; w += 0.2) {
            const r = s * 0.5 * (1 - w / (Math.PI * 4.5));
            const px = -s * 0.15 + Math.cos(w) * r;
            const py = -s * 0.55 + Math.sin(w) * r * 0.9;
            w === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
        }
        ctx.stroke();
        ctx.restore();
    }

    function zeichneMuraene(ctx, trueb) {
        const fels = felsen[muraene.fels];
        const x = fels.x + fels.b * 0.18;
        const y = fels.y - fels.h * 0.15;
        const s = fels.h * 0.18;
        ctx.fillStyle = '#111827';
        ellipse(ctx, x, y, s * 0.9, s * 0.6);
        ctx.fill();
        const raus = glatt(muraene.raus);
        if (raus < 0.02) return;
        ctx.save();
        ctx.beginPath();
        ctx.rect(x - s * 0.9, y - s * 3, s * 6, s * 4);
        ctx.clip();
        ctx.translate(x + raus * s * 1.4 - s * 0.6, y);
        ctx.rotate(-0.25);
        ctx.fillStyle = mischeFarbe('#7c9a3a', '#4b5a3a', trueb);
        ctx.beginPath();
        ctx.moveTo(-s * 1.2, -s * 0.35);
        ctx.quadraticCurveTo(s * 0.4, -s * 0.55, s * 0.9, -s * 0.12 - muraene.maul * s * 0.12);
        ctx.lineTo(s * 0.35, 0);
        ctx.lineTo(s * 0.9, s * 0.12 + muraene.maul * s * 0.12);
        ctx.quadraticCurveTo(s * 0.4, s * 0.45, -s * 1.2, s * 0.35);
        ctx.fill();
        ctx.fillStyle = 'rgba(30,40,20,.35)';
        [[-0.6, -0.1], [-0.2, 0.15], [0.1, -0.2]].forEach(([fx, fy]) => {
            ellipse(ctx, fx * s, fy * s, s * 0.09, s * 0.07);
            ctx.fill();
        });
        ctx.fillStyle = '#fde047';
        ellipse(ctx, s * 0.45, -s * 0.22, s * 0.09, s * 0.09);
        ctx.fill();
        ctx.fillStyle = '#0f172a';
        ellipse(ctx, s * 0.47, -s * 0.22, s * 0.045, s * 0.045);
        ctx.fill();
        ctx.restore();
    }

    function schritt(dt, z) {
        t += dt;
        besuche.schritt(dt, z);
        schrittBewohner(dt, z);

        fische.forEach((f) => {
            if (!f.flieht && z.unruhe > f.mut) f.flieht = true;
            else if (f.flieht && z.unruhe < f.mut - 0.2) {
                f.flieht = false;
                neuesZiel(f);
            }

            let zielX = f.zx;
            let zielY = f.zy;
            if (f.flieht) {
                const p = versteckPunkt(f);
                zielX = p.x;
                zielY = p.y;
            }

            const dx = zielX - f.x;
            const dy = zielY - f.y;
            const abstand = Math.hypot(dx, dy) || 1;
            const nahe = f.flieht && abstand < f.laenge;
            const geschw = f.laenge * 0.45 * f.tempo * (f.flieht ? (nahe ? 0.6 : 5) : 1);
            f.versteckt = f.flieht && abstand < f.laenge * 1.5;
            const lenkung = Math.min(1, dt * (f.flieht ? 4 : 1.1));
            f.vx += ((dx / abstand) * geschw - f.vx) * lenkung;
            f.vy += ((dy / abstand) * geschw * (f.flieht ? 1 : 0.6) - f.vy) * lenkung;
            f.x += f.vx * dt;
            f.y += f.vy * dt;

            if (!f.flieht && abstand < f.laenge * 0.8) neuesZiel(f);

            if (Math.abs(f.vx) > 4) {
                f.richtung += (Math.sign(f.vx) - f.richtung) * Math.min(1, dt * 5);
            }
            f.phase += dt * (3 + (Math.hypot(f.vx, f.vy) / f.laenge) * 5);
        });

        // Luftblasen
        blasenZeit += dt * (1.6 + z.unruhe * 3);
        while (blasenZeit > 1) {
            blasenZeit -= 1;
            const quelle = Math.random() < 0.6 ? zufallAus(felsen) : null;
            blasen.push({
                x: quelle ? quelle.x + zufall(-0.2, 0.2) * quelle.b : zufall(0, b),
                y: quelle ? quelle.y - quelle.h * 0.3 : sandY,
                r: zufall(2, 6),
                phase: zufall(0, 6),
                tempo: zufall(30, 60),
            });
        }
        for (let i = blasen.length - 1; i >= 0; i--) {
            const bl = blasen[i];
            bl.y -= bl.tempo * dt;
            bl.x += Math.sin(t * 2 + bl.phase) * 12 * dt;
            if (bl.y < -10) blasen.splice(i, 1);
        }
        if (blasen.length > 160) blasen.splice(0, blasen.length - 160);
    }

    function zeichnePflanze(ctx, p, sway, alpha = 1) {
        const segmente = 9;
        const fussY = sandHoehe(p.x) + 4;
        ctx.strokeStyle = mitAlpha(p.farbe, alpha);
        ctx.fillStyle = mitAlpha(p.farbe, alpha);
        ctx.lineWidth = p.breite;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(p.x, fussY);
        const punkte = [];
        for (let i = 1; i <= segmente; i++) {
            const anteil = i / segmente;
            const x = p.x + Math.sin(t * 0.9 + p.phase + i * 0.45) * sway * anteil * anteil;
            const y = fussY - p.hoehe * anteil;
            punkte.push([x, y, anteil]);
            ctx.lineTo(x, y);
        }
        ctx.stroke();
        punkte.forEach(([x, y, anteil], i) => {
            if (i === punkte.length - 1) return;
            const seite = i % 2 === 0 ? 1 : -1;
            const groesse = p.breite * 1.6 * (1.1 - anteil * 0.5);
            ellipse(ctx, x + seite * groesse * 0.9, y, groesse, groesse * 0.45, seite * -0.6);
            ctx.fill();
        });
    }

    function zeichneFels(ctx, fels, trueb) {
        const zz = zufallMitSaat(fels.saat);
        const farbe = mischeFarbe('#3f5361', '#2c3a3a', trueb);
        const hell = mischeFarbe('#5d7482', '#3d4a45', trueb);
        ctx.fillStyle = farbe;
        ctx.beginPath();
        const n = 9;
        const punkte = [];
        for (let i = 0; i <= n; i++) {
            const w = Math.PI + (i / n) * Math.PI;
            const r = 1 + zz.zwischen(-0.12, 0.12);
            punkte.push([fels.x + Math.cos(w) * fels.b * 0.5 * r, fels.y + Math.sin(w) * fels.h * 0.75 * r]);
        }
        ctx.moveTo(fels.x - fels.b * 0.55, sandHoehe(fels.x) + 8);
        punkte.forEach(([x, y]) => ctx.lineTo(x, y));
        ctx.lineTo(fels.x + fels.b * 0.55, sandHoehe(fels.x) + 8);
        ctx.closePath();
        ctx.fill();

        // Lichtkante oben
        ctx.save();
        ctx.clip();
        const verlauf = ctx.createLinearGradient(0, fels.y - fels.h * 0.75, 0, fels.y);
        verlauf.addColorStop(0, hell);
        verlauf.addColorStop(1, mitAlpha('#000000', 0));
        ctx.fillStyle = verlauf;
        ctx.fillRect(fels.x - fels.b, fels.y - fels.h, fels.b * 2, fels.h);
        // Moosflecken
        for (let i = 0; i < 6; i++) {
            ctx.fillStyle = mitAlpha('#2f7d5a', 0.55);
            ellipse(ctx, fels.x + zz.zwischen(-0.4, 0.4) * fels.b, fels.y - zz.zwischen(0.1, 0.6) * fels.h, zz.zwischen(6, 16), zz.zwischen(3, 7));
            ctx.fill();
        }
        ctx.restore();
    }

    function zeichneKoralle(ctx, k) {
        const zz = zufallMitSaat(k.saat);
        const ast = (x, y, laenge, winkel, tiefe) => {
            const x2 = x + Math.cos(winkel) * laenge;
            const y2 = y + Math.sin(winkel) * laenge;
            ctx.lineWidth = Math.max(2, tiefe * 3.2);
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x2, y2);
            ctx.stroke();
            if (tiefe > 0) {
                ast(x2, y2, laenge * 0.72, winkel - zz.zwischen(0.25, 0.55), tiefe - 1);
                ast(x2, y2, laenge * 0.72, winkel + zz.zwischen(0.25, 0.55), tiefe - 1);
            } else {
                ctx.beginPath();
                ctx.arc(x2, y2, 3, 0, Math.PI * 2);
                ctx.fill();
            }
        };
        ctx.strokeStyle = k.farbe;
        ctx.fillStyle = k.farbe;
        ctx.lineCap = 'round';
        ast(k.x, sandHoehe(k.x) + 3, k.groesse * 0.45, -Math.PI / 2, 3);
    }

    function zeichneFisch(ctx, f, trueb) {
        const L = f.laenge;
        const H = L * f.art.form;
        const schwanz = Math.sin(f.phase) * 0.35;

        ctx.save();
        ctx.translate(f.x, f.y);
        ctx.rotate(klemme(f.vy / (L * 2), -0.35, 0.35) * Math.sign(f.richtung || 1));
        ctx.scale(f.richtung >= 0 ? Math.max(0.08, f.richtung) : Math.min(-0.08, f.richtung), 1);

        // Schwanzflosse
        ctx.save();
        ctx.translate(-L * 0.38, 0);
        ctx.rotate(schwanz);
        ctx.fillStyle = f.art.flosse;
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.quadraticCurveTo(-L * 0.12, -H * 0.2, -L * 0.25, -H * 0.75);
        ctx.quadraticCurveTo(-L * 0.17, 0, -L * 0.25, H * 0.75);
        ctx.quadraticCurveTo(-L * 0.12, H * 0.2, 0, 0);
        ctx.fill();
        ctx.restore();

        // Rückenflosse
        ctx.fillStyle = f.art.flosse;
        ctx.beginPath();
        ctx.moveTo(L * 0.12, -H * 0.82);
        ctx.quadraticCurveTo(-L * 0.05, -H * 1.35, -L * 0.25, -H * 0.62);
        ctx.lineTo(-L * 0.1, -H * 0.7);
        ctx.fill();

        // Körper
        const koerper = new Path2D();
        koerper.moveTo(L * 0.5, 0);
        koerper.bezierCurveTo(L * 0.42, -H * 1.05, -L * 0.25, -H * 1.0, -L * 0.42, 0);
        koerper.bezierCurveTo(-L * 0.25, H * 1.0, L * 0.42, H * 1.05, L * 0.5, 0);
        const verlauf = ctx.createLinearGradient(0, -H, 0, H);
        verlauf.addColorStop(0, f.art.koerper);
        verlauf.addColorStop(0.55, f.art.koerper);
        verlauf.addColorStop(1, f.art.bauch);
        ctx.fillStyle = verlauf;
        ctx.fill(koerper);

        if (f.art.streifen) {
            ctx.save();
            ctx.clip(koerper);
            ctx.fillStyle = f.art.streifen;
            [0.18, -0.1].forEach((sx) => ctx.fillRect(L * sx, -H * 1.2, L * 0.08, H * 2.4));
            ctx.restore();
        }

        // Brustflosse
        ctx.fillStyle = mitAlpha(f.art.flosse, 0.85);
        ellipse(ctx, L * 0.05, H * 0.25, L * 0.11, H * 0.22, 0.6 + Math.sin(f.phase * 1.3) * 0.25);
        ctx.fill();

        // Auge
        ctx.fillStyle = '#ffffff';
        ellipse(ctx, L * 0.3, -H * 0.18, Math.max(2.5, L * 0.065), Math.max(2.5, L * 0.065));
        ctx.fill();
        ctx.fillStyle = '#0f172a';
        ellipse(ctx, L * 0.315, -H * 0.18, Math.max(1.4, L * 0.035), Math.max(1.4, L * 0.035));
        ctx.fill();

        // Trübung färbt die Fische mit ein
        if (trueb > 0.02) {
            ctx.globalAlpha = trueb * 0.45;
            ctx.fillStyle = '#4b5a46';
            ctx.fill(koerper);
            ctx.globalAlpha = 1;
        }
        ctx.restore();
    }

    function zeichne(ctx, breite, hoehe, z) {
        const trueb = glatt(z.unruhe);

        // Wasser
        const wasser = ctx.createLinearGradient(0, 0, 0, hoehe);
        wasser.addColorStop(0, mischeFarbe('#127c93', '#56664f', trueb));
        wasser.addColorStop(0.55, mischeFarbe('#0a4c6a', '#3a4636', trueb));
        wasser.addColorStop(1, mischeFarbe('#06263f', '#262c22', trueb));
        ctx.fillStyle = wasser;
        ctx.fillRect(0, 0, breite, hoehe);

        // Lichtstrahlen
        ctx.save();
        ctx.globalCompositeOperation = 'lighter';
        strahlen.forEach((s) => {
            const x = (s.x + Math.sin(t * 0.08 + s.phase) * 0.03) * breite;
            const w = s.breite * breite;
            const alpha = (0.07 + Math.sin(t * 0.3 + s.phase) * 0.025) * (1 - trueb * 0.85);
            const v = ctx.createLinearGradient(0, 0, 0, sandY);
            v.addColorStop(0, `rgba(190,240,255,${alpha})`);
            v.addColorStop(1, 'rgba(190,240,255,0)');
            ctx.fillStyle = v;
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x + w, 0);
            ctx.lineTo(x + w * 2.2 + breite * 0.12, sandY);
            ctx.lineTo(x + breite * 0.12, sandY);
            ctx.fill();
        });
        ctx.restore();

        // Wasserlinien
        ctx.strokeStyle = `rgba(255,255,255,${0.05 * (1 - trueb)})`;
        ctx.lineWidth = 1;
        for (let i = 1; i <= 4; i++) {
            const y = hoehe * 0.09 * i;
            ctx.beginPath();
            for (let x = 0; x <= breite; x += 24) {
                const yy = y + Math.sin(x * 0.012 + t * 0.6 + i) * 5;
                x === 0 ? ctx.moveTo(x, yy) : ctx.lineTo(x, yy);
            }
            ctx.stroke();
        }

        const sway = 10 + z.unruhe * 22;

        // Pflanzen hinten
        pflanzenHinten.forEach((p) => zeichnePflanze(ctx, p, sway * 0.8, 0.7));

        // Sand
        ctx.fillStyle = mischeFarbe('#d6b98a', '#8d8264', trueb);
        ctx.beginPath();
        ctx.moveTo(0, hoehe);
        for (let x = 0; x <= breite + 20; x += 20) ctx.lineTo(x, sandHoehe(x));
        ctx.lineTo(breite, hoehe);
        ctx.fill();
        const sandSchatten = ctx.createLinearGradient(0, sandY, 0, hoehe);
        sandSchatten.addColorStop(0, 'rgba(0,0,0,0)');
        sandSchatten.addColorStop(1, 'rgba(60,40,10,0.35)');
        ctx.fillStyle = sandSchatten;
        ctx.fillRect(0, sandY - 20, breite, hoehe - sandY + 20);
        kiesel.forEach((k) => {
            ctx.fillStyle = k.farbe;
            ellipse(ctx, k.x, sandHoehe(k.x) + k.y * hoehe, k.r * 1.4, k.r);
            ctx.fill();
        });

        korallen.forEach((k) => zeichneRiffKoralle(ctx, k));
        seesterne.filter((st) => st.fels === undefined).forEach((st) => zeichneSeestern(ctx, st));
        zeichneSchnecke(ctx);

        // Fische (hinter Felsen und vorderen Pflanzen)
        fische.forEach((f) => zeichneFisch(ctx, f, trueb));
        besuche.zeichne(ctx, 'mitte', z);

        // Felsen mit Bewohnern, Anemone, Krabben und Pflanzen vorne
        felsen.forEach((fels) => zeichneFels(ctx, fels, trueb));
        zeichneMuraene(ctx, trueb);
        seeigel.forEach((si) => zeichneSeeigel(ctx, si));
        seesterne.filter((st) => st.fels !== undefined).forEach((st) => zeichneSeestern(ctx, st));
        zeichneAnemone(ctx, trueb);
        krabben.forEach((k) => zeichneKrabbe(ctx, k));
        pflanzenVorne.forEach((p) => zeichnePflanze(ctx, p, sway, 1));

        // Blasen
        blasen.forEach((bl) => {
            ctx.strokeStyle = 'rgba(220,245,255,0.55)';
            ctx.lineWidth = 1.2;
            ellipse(ctx, bl.x, bl.y, bl.r, bl.r);
            ctx.stroke();
            ctx.fillStyle = 'rgba(255,255,255,0.35)';
            ellipse(ctx, bl.x - bl.r * 0.35, bl.y - bl.r * 0.35, bl.r * 0.3, bl.r * 0.3);
            ctx.fill();
        });

        // Trübung
        if (trueb > 0.01) {
            ctx.fillStyle = `rgba(74,86,62,${0.4 * trueb})`;
            ctx.fillRect(0, 0, breite, hoehe);
            schwebeteilchen.forEach((s) => {
                const x = ((s.x + Math.sin(t * 0.2 + s.phase) * 0.02) % 1) * breite;
                const y = ((s.y + t * 0.01) % 1) * hoehe;
                ctx.fillStyle = `rgba(150,140,100,${0.45 * trueb})`;
                ellipse(ctx, x, y, s.r, s.r);
                ctx.fill();
            });
        }

        // Vignette
        const vignette = ctx.createRadialGradient(breite / 2, hoehe / 2, Math.min(breite, hoehe) * 0.35, breite / 2, hoehe / 2, Math.max(breite, hoehe) * 0.75);
        vignette.addColorStop(0, 'rgba(0,0,0,0)');
        vignette.addColorStop(1, 'rgba(0,10,20,0.45)');
        ctx.fillStyle = vignette;
        ctx.fillRect(0, 0, breite, hoehe);
    }

    function info() {
        const sichtbar = fische.filter((f) => !f.versteckt).length;
        return `${sichtbar} von ${fische.length} Fischen sichtbar`;
    }

    return {
        mengeLabel: 'Anzahl der Fische',
        texte: {
            ruhig: 'Ruhiges Wasser – die Fische ziehen ihre Bahnen.',
            fast: 'Etwas unruhig – die Fische werden wachsam.',
            laut: 'Zu laut – die Fische verstecken sich!',
        },
        groesse,
        schritt,
        zeichne,
        info,
        besuch: (name) => besuche.starte(name),
    };
}
