/*
 * Klassenraum-Tools – kleine Hilfsfunktionen (Zahlen, Farben, Zufall).
 */

export const klemme = (wert, min, max) => Math.min(max, Math.max(min, wert));
export const mische = (a, b, t) => a + (b - a) * t;
export const glatt = (t) => {
    const x = klemme(t, 0, 1);
    return x * x * (3 - 2 * x);
};

/**
 * Reproduzierbarer Zufall (mulberry32) – damit Pflanzen, Hügel usw. nach einer
 * Größenänderung an derselben Stelle bleiben.
 */
export function zufallMitSaat(saat = 1) {
    let s = saat >>> 0;
    const naechste = () => {
        s = (s + 0x6d2b79f5) >>> 0;
        let t = s;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
    naechste.zwischen = (a, b) => a + naechste() * (b - a);
    naechste.ganz = (a, b) => Math.floor(a + naechste() * (b - a + 1));
    naechste.aus = (liste) => liste[Math.floor(naechste() * liste.length)];
    return naechste;
}

export const zufall = (a = 0, b = 1) => a + Math.random() * (b - a);
export const zufallAus = (liste) => liste[Math.floor(Math.random() * liste.length)];

function hexZuRgb(hex) {
    const h = hex.replace('#', '');
    const v = parseInt(h.length === 3 ? h.split('').map((c) => c + c).join('') : h, 16);
    return [(v >> 16) & 255, (v >> 8) & 255, v & 255];
}

const rgbCache = new Map();
function rgb(hex) {
    let wert = rgbCache.get(hex);
    if (!wert) {
        wert = hexZuRgb(hex);
        rgbCache.set(hex, wert);
    }
    return wert;
}

/** Mischt zwei Hex-Farben und gibt einen CSS-Farbwert zurück. */
export function mischeFarbe(a, b, t, alpha = 1) {
    const x = rgb(a);
    const y = rgb(b);
    const k = klemme(t, 0, 1);
    const r = Math.round(mische(x[0], y[0], k));
    const g = Math.round(mische(x[1], y[1], k));
    const bl = Math.round(mische(x[2], y[2], k));
    return alpha >= 1 ? `rgb(${r},${g},${bl})` : `rgba(${r},${g},${bl},${alpha})`;
}

export function mitAlpha(hex, alpha) {
    const [r, g, b] = rgb(hex);
    return `rgba(${r},${g},${b},${klemme(alpha, 0, 1)})`;
}

/** Zeichnet eine Ellipse als eigenen Pfad und füllt sie. */
export function ellipse(ctx, x, y, rx, ry, drehung = 0) {
    ctx.beginPath();
    ctx.ellipse(x, y, Math.max(0.1, rx), Math.max(0.1, ry), drehung, 0, Math.PI * 2);
}

export function formatiereDauer(sekunden) {
    const s = Math.max(0, Math.floor(sekunden));
    const m = Math.floor(s / 60);
    const h = Math.floor(m / 60);
    const zwei = (n) => String(n).padStart(2, '0');
    return h > 0 ? `${h}:${zwei(m % 60)}:${zwei(s % 60)}` : `${m}:${zwei(s % 60)}`;
}
