/*
 * Szene „Aquarium“: Fische ziehen ruhig ihre Bahnen. Steigt die Unruhe, verstecken sie
 * sich nacheinander hinter den Felsen, Pflanzen wiegen stärker und das Wasser trübt ein.
 */

import {ellipse, glatt, klemme, mischeFarbe, mitAlpha, zufall, zufallAus, zufallMitSaat} from '../shared/hilfen';

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

export function erzeugeAquarium() {
    let b = 0;
    let h = 0;
    let sandY = 0;
    let felsen = [];
    let pflanzenHinten = [];
    let pflanzenVorne = [];
    let korallen = [];
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

            korallen = Array.from({length: 5}, (_, i) => ({
                x: b * (0.08 + i * 0.21) + zz.zwischen(-0.04, 0.04) * b,
                groesse: Math.min(b, h) * zz.zwischen(0.06, 0.1),
                farbe: zz.aus(KORALLEN),
                saat: 100 + i,
            }));

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

    function schritt(dt, z) {
        t += dt;

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

        korallen.forEach((k) => zeichneKoralle(ctx, k));

        // Fische (hinter Felsen und vorderen Pflanzen)
        fische.forEach((f) => zeichneFisch(ctx, f, trueb));

        // Felsen und Pflanzen vorne
        felsen.forEach((fels) => zeichneFels(ctx, fels, trueb));
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
    };
}
