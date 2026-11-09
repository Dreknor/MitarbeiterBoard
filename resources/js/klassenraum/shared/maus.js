/*
 * Klassenraum-Tools – die Klassenmaus.
 *
 * Eine eigene Figur, die in allen Lautstärke-Szenen irgendwo auftaucht, wenn es eine Weile
 * ruhig ist: Sie guckt hinter etwas hervor, bleibt ein Weilchen, winkt und verschwindet wieder –
 * bei Lärm sofort. Danach taucht sie an einer anderen Stelle wieder auf. Die Kinder dürfen sie
 * (leise) suchen.
 *
 * Damit sie immer erkennbar ist, sieht sie überall gleich aus: graues Fell, große runde
 * rosa Ohren, rosa Nase und ein rotes Halstuch mit weißen Punkten. Je nach Szene trägt sie
 * zusätzlich etwas (Schlafmütze in der Nacht, Taucheranzug im Aquarium, Forscherhut beim Vulkan).
 *
 * Ein Versteck ist {name, ebene, ort(): {x, y, s, von, richtung?, clip?, muetze?, taucher?,
 * forscher?, dunkel?, schlauch?}} – x/y = Fußpunkt der sichtbaren Maus, s = Größeneinheit (Kopfbreite),
 * von = 'unten' (taucht hinter einer Kante auf) | 'seite' (guckt seitlich hervor) |
 * 'oben' (kommt von oben, z. B. am Luftschlauch). Die Szene ruft zeichne(ctx, ebene) an der
 * Stelle ihrer Zeichenreihenfolge auf, hinter der die Maus versteckt sein soll.
 */

import {ellipse, klemme} from './hilfen';
import {blende} from './besuche';

const FELL = '#9d968f';
const FELL_HELL = '#b7b0a8';
const BAUCH = '#efe7da';
const ROSA = '#f5a9b6';
const ROSA_DUNKEL = '#de8196';
const NASE = '#ea6f8b';
const TUCH = '#e0402a';
const DUNKEL = '#241a17';
const RAND = 'rgba(48,34,30,.8)';
const ANZUG = '#f1b52f';
const MESSING = '#c8963a';

const zwischen = (a, b) => a + Math.random() * (b - a);
const sanft = (p) => p * p * (3 - 2 * p);

/**
 * Zeichnet die Maus von vorn, Fußpunkt im Ursprung, Größeneinheit s (≈ Kopfbreite).
 * Gesamthöhe ≈ 1,95 s (mit Ohren).
 */
export function zeichneMaus(ctx, s, {t = 0, blick = [0, 0], blinzeln = false, winken = 0, muetze = false, taucher = false, forscher = false} = {}) {
    ctx.save();
    ctx.scale(s, s);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const rand = (breite = 0.035) => {
        ctx.strokeStyle = RAND;
        ctx.lineWidth = breite;
        ctx.stroke();
    };

    // Schwanz
    const wedel = Math.sin(t * 2.2) * 0.06;
    ctx.beginPath();
    ctx.moveTo(0.22, -0.16);
    ctx.bezierCurveTo(0.7, -0.08, 0.86 + wedel, -0.42, 0.64 + wedel, -0.66);
    ctx.strokeStyle = RAND;
    ctx.lineWidth = 0.1;
    ctx.stroke();
    ctx.strokeStyle = taucher ? '#e7a325' : ROSA_DUNKEL;
    ctx.lineWidth = 0.065;
    ctx.stroke();

    // Füße
    [-1, 1].forEach((seite) => {
        ctx.fillStyle = taucher ? '#3b332e' : ROSA;
        ellipse(ctx, seite * 0.2, -0.06, 0.17, 0.085);
        ctx.fill();
        rand(0.03);
    });

    // Körper
    ctx.fillStyle = taucher ? ANZUG : FELL;
    ellipse(ctx, 0, -0.48, 0.4, 0.46);
    ctx.fill();
    rand();
    if (taucher) {
        ctx.fillStyle = '#6b4a2a';
        ctx.fillRect(-0.4, -0.36, 0.8, 0.08);
        ctx.fillStyle = MESSING;
        ctx.fillRect(-0.06, -0.37, 0.12, 0.1);
    } else {
        ctx.fillStyle = BAUCH;
        ellipse(ctx, 0, -0.42, 0.26, 0.31);
        ctx.fill();
    }

    // Halstuch (Erkennungszeichen) mit Zipfel
    ctx.fillStyle = TUCH;
    ctx.beginPath();
    ctx.moveTo(0.06, -0.84);
    ctx.lineTo(0.32, -0.58);
    ctx.lineTo(0.2, -0.82);
    ctx.closePath();
    ctx.fill();
    rand(0.025);
    ellipse(ctx, 0, -0.84, 0.35, 0.1);
    ctx.fill();
    rand(0.03);
    ctx.fillStyle = '#fff6ec';
    [[-0.22, -0.85], [-0.06, -0.8], [0.1, -0.86], [0.24, -0.81], [0.22, -0.68]].forEach(([x, y]) => {
        ellipse(ctx, x, y, 0.028, 0.028);
        ctx.fill();
    });
    if (taucher) {
        ctx.fillStyle = MESSING;
        ellipse(ctx, 0, -0.93, 0.46, 0.11);
        ctx.fill();
        rand(0.03);
    }

    // Arme: links an der Brust, rechts winkend
    ctx.fillStyle = taucher ? ANZUG : ROSA;
    ellipse(ctx, -0.3, -0.62, 0.085, 0.075);
    ctx.fill();
    rand(0.025);
    if (winken > 0.01) {
        const hand = [0.34 + winken * 0.3, -0.7 - winken * 0.42 + Math.sin(t * 9) * 0.05 * winken];
        ctx.beginPath();
        ctx.moveTo(0.3, -0.72);
        ctx.lineTo(hand[0], hand[1]);
        ctx.strokeStyle = RAND;
        ctx.lineWidth = 0.15;
        ctx.stroke();
        ctx.strokeStyle = taucher ? ANZUG : FELL;
        ctx.lineWidth = 0.1;
        ctx.stroke();
        ctx.fillStyle = taucher ? ANZUG : ROSA;
        ellipse(ctx, hand[0], hand[1], 0.09, 0.09);
        ctx.fill();
        rand(0.025);
    } else {
        ctx.fillStyle = taucher ? ANZUG : ROSA;
        ellipse(ctx, 0.3, -0.62, 0.085, 0.075);
        ctx.fill();
        rand(0.025);
    }

    // Ohren
    [-1, 1].forEach((seite) => {
        ctx.fillStyle = FELL;
        ellipse(ctx, seite * 0.45, -1.62, 0.31, 0.3);
        ctx.fill();
        rand();
        ctx.fillStyle = ROSA;
        ellipse(ctx, seite * 0.45, -1.6, 0.2, 0.19);
        ctx.fill();
    });

    // Kopf
    ctx.fillStyle = FELL;
    ellipse(ctx, 0, -1.27, 0.5, 0.43);
    ctx.fill();
    rand();
    ctx.fillStyle = FELL_HELL;
    ellipse(ctx, -0.08, -1.48, 0.22, 0.1, -0.2);
    ctx.fill();
    ctx.fillStyle = BAUCH;
    ellipse(ctx, 0, -1.1, 0.27, 0.17);
    ctx.fill();
    ctx.fillStyle = 'rgba(240,130,150,.45)';
    [-1, 1].forEach((seite) => {
        ellipse(ctx, seite * 0.31, -1.13, 0.08, 0.05);
        ctx.fill();
    });

    // Augen
    [-1, 1].forEach((seite) => {
        const ax = seite * 0.17;
        const ay = -1.33;
        if (blinzeln) {
            ctx.beginPath();
            ctx.moveTo(ax - 0.1, ay);
            ctx.quadraticCurveTo(ax, ay + 0.06, ax + 0.1, ay);
            ctx.strokeStyle = DUNKEL;
            ctx.lineWidth = 0.035;
            ctx.stroke();
            return;
        }
        ctx.fillStyle = '#ffffff';
        ellipse(ctx, ax, ay, 0.12, 0.15);
        ctx.fill();
        rand(0.025);
        ctx.fillStyle = DUNKEL;
        ellipse(ctx, ax + blick[0] * 0.04, ay + 0.02 + blick[1] * 0.04, 0.075, 0.085);
        ctx.fill();
        ctx.fillStyle = '#ffffff';
        ellipse(ctx, ax + blick[0] * 0.04 + 0.025, ay - 0.015 + blick[1] * 0.04, 0.026, 0.026);
        ctx.fill();
    });

    // Nase, Mund, Schnurrhaare
    ctx.fillStyle = NASE;
    ellipse(ctx, 0, -1.2, 0.085, 0.065);
    ctx.fill();
    rand(0.02);
    ctx.fillStyle = 'rgba(255,255,255,.7)';
    ellipse(ctx, -0.025, -1.22, 0.025, 0.017);
    ctx.fill();
    ctx.strokeStyle = DUNKEL;
    ctx.lineWidth = 0.03;
    ctx.beginPath();
    ctx.moveTo(0, -1.14);
    ctx.lineTo(0, -1.08);
    ctx.moveTo(-0.11, -1.08);
    ctx.quadraticCurveTo(-0.05, -1.02, 0, -1.08);
    ctx.quadraticCurveTo(0.05, -1.02, 0.11, -1.08);
    ctx.stroke();
    ctx.strokeStyle = 'rgba(60,45,40,.6)';
    ctx.lineWidth = 0.016;
    [-1, 1].forEach((seite) => {
        [[-1.2, -1.24], [-1.12, -1.12], [-1.04, -1.0]].forEach(([y0, y1]) => {
            ctx.beginPath();
            ctx.moveTo(seite * 0.22, y0 + 0.04);
            ctx.lineTo(seite * 0.58, y1);
            ctx.stroke();
        });
    });

    // Schlafmütze für die Nacht
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
    }

    // Forscherhut (Tropenhelm) mit Hutband
    if (forscher) {
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

    // Taucherhelm: Glaskugel mit Spiegelung
    if (taucher) {
        ctx.fillStyle = 'rgba(200,240,255,.16)';
        ellipse(ctx, 0, -1.4, 0.86, 0.66);
        ctx.fill();
        ctx.strokeStyle = 'rgba(225,248,255,.85)';
        ctx.lineWidth = 0.045;
        ctx.stroke();
        ctx.strokeStyle = 'rgba(255,255,255,.7)';
        ctx.lineWidth = 0.06;
        ctx.beginPath();
        ctx.ellipse(0, -1.4, 0.72, 0.53, 0, Math.PI * 1.08, Math.PI * 1.32);
        ctx.stroke();
        ctx.fillStyle = MESSING;
        ellipse(ctx, 0, -2.04, 0.1, 0.05);
        ctx.fill();
        rand(0.025);
    }
    ctx.restore();
}

export class Klassenmaus {
    /**
     * @param {Array<{name: string, ebene: string, ort: function(): object}>} verstecke
     * @param {{erstes?: number[], abstand?: number[]}} optionen Zeiten in Sekunden
     */
    constructor(verstecke, {erstes = [15, 30], abstand = [25, 50]} = {}) {
        this.verstecke = verstecke;
        this.abstand = abstand;
        this.warten = zwischen(...erstes);
        this.aktiv = null;
        this.zuletzt = null;
        this.t = 0;
        this.blasen = [];
    }

    /** Sofort auftauchen (Vorschau ?besuch=maus bzw. ?besuch=maus:<versteck>). */
    starte(name = null) {
        const auswahl = name
            ? this.verstecke.filter((v) => v.name === name)
            : this.verstecke.filter((v) => v.name !== this.zuletzt || this.verstecke.length === 1);
        if (!auswahl.length) return false;
        const versteck = auswahl[Math.floor(Math.random() * auswahl.length)];
        this.aktiv = {versteck, p: 0, zeit: 0, dauer: zwischen(20, 30), weg: false, blick: [0, 0], blickZiel: [0, 0], blickIn: 1, blinzeln: 3};
        this.zuletzt = versteck.name;
        this.blasen = [];
        return true;
    }

    schritt(dt, zustand) {
        this.t += dt;
        const a = this.aktiv;
        if (!a) {
            if (zustand.unruhe < 0.2) this.warten -= dt;
            else if (zustand.unruhe < 0.5) this.warten -= dt / 2;
            if (this.warten <= 0) this.starte();
            return;
        }
        a.zeit += dt;
        const erschrocken = zustand.unruhe > 0.45;
        if (erschrocken || a.zeit > a.dauer) a.weg = true;
        a.p = blende(a.p, a.weg ? 0 : 1, dt, a.weg ? (erschrocken ? 2.5 : 0.7) : 0.55);
        if (a.weg && a.p <= 0) {
            this.aktiv = null;
            this.warten = zwischen(...this.abstand);
            return;
        }
        // Umherschauen und Blinzeln
        a.blickIn -= dt;
        if (a.blickIn <= 0) {
            a.blickIn = zwischen(1.2, 3);
            a.blickZiel = Math.random() < 0.4 ? [0, 0] : [zwischen(-1, 1), zwischen(-0.6, 0.8)];
        }
        a.blick = a.blick.map((w, i) => w + (a.blickZiel[i] - w) * Math.min(1, dt * 6));
        a.blinzeln -= dt;
        if (a.blinzeln < -0.14) a.blinzeln = zwischen(2.5, 5);
        // Luftblasen beim Tauchen
        if (a.versteck.ort().taucher && Math.random() < dt * 2.5) this.blasen.push({x: zwischen(-0.2, 0.2), y: 0, r: zwischen(0.05, 0.11), phase: zwischen(0, 6)});
        this.blasen.forEach((b) => { b.y += dt * 1.4; });
        this.blasen = this.blasen.filter((b) => b.y < 8);
    }

    /** Wie weit die Maus gerade an einem Versteck draußen ist (0–1) – z. B. für eine offene Tür. */
    anteil(name) {
        return this.aktiv && this.aktiv.versteck.name === name ? sanft(this.aktiv.p) : 0;
    }

    istDa() {
        return Boolean(this.aktiv && this.aktiv.p > 0.6);
    }

    zeichne(ctx, ebene) {
        const a = this.aktiv;
        if (!a || a.versteck.ebene !== ebene) return;
        const o = a.versteck.ort();
        const e = sanft(klemme(a.p, 0, 1));
        let x = o.x;
        let y = o.y;
        if (o.von === 'unten') y += (1 - e) * o.s * 2.15;
        else if (o.von === 'seite') x -= (1 - e) * (o.richtung ?? 1) * o.s * 1.4;
        else if (o.von === 'oben') y = mischeY(-o.s * 2.5, o.y, e) + Math.sin(this.t * 1.1) * o.s * 0.08;

        // Winken, wenn sie ganz draußen ist (immer mal wieder)
        const takt = a.zeit % 8;
        const winken = e > 0.95 && takt > 2.5 && takt < 4.6 ? Math.sin(((takt - 2.5) / 2.1) * Math.PI) : 0;

        ctx.save();
        if (o.clip) {
            ctx.beginPath();
            o.clip(ctx);
            ctx.clip();
        }
        if (o.schlauch) {
            ctx.strokeStyle = 'rgba(30,25,20,.6)';
            ctx.lineWidth = o.s * 0.12;
            ctx.beginPath();
            ctx.moveTo(x, y - o.s * 2.05);
            ctx.bezierCurveTo(x + o.s * 0.6, y - o.s * 3.5, x - o.s * 0.4, -o.s, x + o.s * 0.3, -10);
            ctx.stroke();
            ctx.strokeStyle = '#d9cfa8';
            ctx.lineWidth = o.s * 0.07;
            ctx.stroke();
        }
        ctx.globalAlpha = Math.min(1, e * 2.5);
        if (o.dunkel) ctx.filter = 'brightness(0.72) saturate(0.85)';
        ctx.translate(x, y);
        if (o.von === 'seite') ctx.rotate(-(o.richtung ?? 1) * 0.12 * (1 - e * 0.6));
        zeichneMaus(ctx, o.s, {
            t: this.t,
            blick: a.blick,
            blinzeln: a.blinzeln < 0,
            winken,
            muetze: o.muetze,
            forscher: o.forscher,
            taucher: o.taucher,
        });
        ctx.restore();

        // Luftblasen steigen aus dem Helm auf
        if (o.taucher && this.blasen.length) {
            ctx.save();
            ctx.strokeStyle = 'rgba(220,245,255,.75)';
            ctx.lineWidth = Math.max(1, o.s * 0.025);
            this.blasen.forEach((b) => {
                const bx = x + (b.x + Math.sin(this.t * 3 + b.phase) * 0.08) * o.s;
                const by = y - o.s * 2.1 - b.y * o.s;
                ctx.globalAlpha = Math.min(1, e * 2) * klemme(1 - b.y / 8, 0, 1);
                ellipse(ctx, bx, by, b.r * o.s, b.r * o.s);
                ctx.stroke();
            });
            ctx.restore();
        }
    }
}

function mischeY(a, b, p) {
    return a + (b - a) * p;
}

/** Vorschau über ?besuch=maus bzw. ?besuch=maus:<versteck>; true, wenn der Name die Maus meint. */
export function mausBesuch(maus, name) {
    if (name === 'maus') return maus.starte();
    if (name?.startsWith('maus:')) return maus.starte(name.slice(5));
    return null;
}
