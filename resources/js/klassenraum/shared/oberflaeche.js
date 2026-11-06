/*
 * Klassenraum-Tools – gemeinsame Bedienelemente aller Vollbild-Tools:
 * Vollbild, automatisches Ausblenden, Uhr, Einstellungsfenster und Töne.
 */

export const $ = (selektor, wurzel = document) => wurzel.querySelector(selektor);

export function istVollbild() {
    return Boolean(document.fullscreenElement || document.webkitFullscreenElement);
}

export function schalteVollbild() {
    const el = document.documentElement;
    try {
        if (istVollbild()) {
            (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        } else {
            const ergebnis = (el.requestFullscreen || el.webkitRequestFullscreen).call(el);
            ergebnis?.catch?.(() => {});
        }
    } catch (_) {
        // Vollbild vom Browser verweigert (z. B. in einem iframe) – einfach ignorieren.
    }
}

export function initVollbild(knopf) {
    if (!knopf) return;
    const el = document.documentElement;
    if (!el.requestFullscreen && !el.webkitRequestFullscreen) {
        knopf.hidden = true;
        return;
    }

    const symbol = knopf.querySelector('i');
    const aktualisiere = () => {
        const an = istVollbild();
        knopf.setAttribute('aria-pressed', String(an));
        knopf.title = an ? 'Vollbild beenden (F)' : 'Vollbild (F)';
        knopf.setAttribute('aria-label', knopf.title);
        if (symbol) symbol.className = an ? 'fas fa-compress' : 'fas fa-expand';
    };

    knopf.addEventListener('click', schalteVollbild);
    document.addEventListener('fullscreenchange', aktualisiere);
    document.addEventListener('webkitfullscreenchange', aktualisiere);
    aktualisiere();
}

/**
 * Blendet Bedienelemente nach kurzer Inaktivität aus (Klasse krt-leerlauf am body),
 * solange erlaubt() true liefert.
 */
export function initLeerlauf(erlaubt, wartezeit = 3500) {
    let zeitgeber = null;

    const wecken = () => {
        document.body.classList.remove('krt-leerlauf');
        clearTimeout(zeitgeber);
        zeitgeber = setTimeout(() => {
            if (erlaubt()) document.body.classList.add('krt-leerlauf');
        }, wartezeit);
    };

    ['pointermove', 'pointerdown', 'keydown', 'touchstart', 'wheel'].forEach((ereignis) => {
        window.addEventListener(ereignis, wecken, {passive: true});
    });
    wecken();

    return {wecken};
}

export function initUhr(el) {
    if (!el) return;
    const zeit = new Intl.DateTimeFormat('de-DE', {hour: '2-digit', minute: '2-digit'});
    const datum = new Intl.DateTimeFormat('de-DE', {weekday: 'short', day: '2-digit', month: 'short'});
    const b = document.createElement('b');
    const span = document.createElement('span');
    el.replaceChildren(b, span);

    const tick = () => {
        const jetzt = new Date();
        b.textContent = zeit.format(jetzt);
        span.textContent = datum.format(jetzt);
    };
    tick();
    setInterval(tick, 1000);
}

/** Seitliches Einstellungsfenster. */
export function initPanel(panel, oeffnenKnopf) {
    let zuletztFokus = null;

    const setze = (offen) => {
        panel.hidden = !offen;
        document.body.classList.toggle('krt-panel-offen', offen);
        oeffnenKnopf?.setAttribute('aria-expanded', String(offen));
        if (offen) {
            zuletztFokus = document.activeElement;
            panel.querySelector('input, button, select')?.focus({preventScroll: true});
        } else if (zuletztFokus && document.contains(zuletztFokus)) {
            zuletztFokus.focus({preventScroll: true});
        }
    };

    oeffnenKnopf?.addEventListener('click', () => setze(panel.hidden));
    panel.querySelectorAll('[data-panel-schliessen]').forEach((k) => k.addEventListener('click', () => setze(false)));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hidden) setze(false);
    });

    return {
        offen: () => !panel.hidden,
        schliesse: () => setze(false),
    };
}

/**
 * Kompakte Steuerleiste im Fokusmodus (Timer, Arbeitsphasen, Atemübung): erscheint nur kurz
 * nach Antippen oder Tastendruck (body.tmr-leiste-sichtbar). Mausbewegungen zeigen nichts an.
 * Dauerhaft sichtbar ist sie, solange das Tool body.tmr-leiste-dauerhaft setzt.
 *
 * @param {HTMLElement} leiste
 * @param {function(): boolean} aktiv  true, solange der Fokusmodus läuft
 */
export function initFokusleiste(leiste, aktiv, sichtbarMs = 4000) {
    let zeitgeber = null;

    const verberge = () => {
        clearTimeout(zeitgeber);
        document.body.classList.remove('tmr-leiste-sichtbar');
    };
    const zeige = () => {
        if (!aktiv()) return;
        document.body.classList.add('tmr-leiste-sichtbar');
        clearTimeout(zeitgeber);
        zeitgeber = setTimeout(verberge, sichtbarMs);
    };

    document.addEventListener('pointerdown', (e) => {
        if (!leiste.contains(e.target)) zeige();
    });

    return {zeige, verberge};
}

/** true, wenn das Tastenkürzel nicht in einem Eingabefeld getippt wurde. */
export function istKuerzel(e) {
    const ziel = e.target;
    if (e.ctrlKey || e.metaKey || e.altKey) return false;
    if (!ziel || !(ziel instanceof HTMLElement)) return true;
    if (ziel.isContentEditable) return false;
    const tag = ziel.tagName;
    if (tag === 'TEXTAREA' || tag === 'SELECT') return false;
    if (tag === 'INPUT' && !['range', 'checkbox', 'radio', 'button'].includes(ziel.type)) return false;
    return true;
}

/**
 * Sanfte, synthetisch erzeugte Töne (Web Audio) – keine Audiodateien nötig.
 * Browser erlauben Ton erst nach einer Nutzeraktion, daher entsperre() bei Klicks aufrufen.
 */
export class Ton {
    constructor() {
        this.kontext = null;
    }

    entsperre() {
        try {
            if (!this.kontext) {
                const AudioKontext = window.AudioContext || window.webkitAudioContext;
                if (!AudioKontext) return null;
                this.kontext = new AudioKontext();
            }
            if (this.kontext.state === 'suspended') this.kontext.resume().catch(() => {});
        } catch (_) {
            this.kontext = null;
        }
        return this.kontext;
    }

    /** Einzelner sanfter Ton, versatz in Sekunden ab jetzt. */
    ton(frequenz, versatz = 0, dauer = 1.2, lautstaerke = 0.2, typ = 'sine') {
        if (!this.entsperre()) return;
        this.#note(frequenz, this.kontext.currentTime + 0.02 + versatz, dauer, lautstaerke, typ);
    }

    #note(frequenz, start, dauer, lautstaerke, typ = 'sine') {
        const ctx = this.kontext;
        const osz = ctx.createOscillator();
        const huelle = ctx.createGain();
        osz.type = typ;
        osz.frequency.value = frequenz;
        huelle.gain.setValueAtTime(0.0001, start);
        huelle.gain.exponentialRampToValueAtTime(lautstaerke, start + 0.02);
        huelle.gain.exponentialRampToValueAtTime(0.0001, start + dauer);
        osz.connect(huelle).connect(ctx.destination);
        osz.start(start);
        osz.stop(start + dauer + 0.05);
    }

    /** Klangschalen-ähnlicher Gong (Timer-Ende). */
    gong(lautstaerke = 0.5) {
        if (!this.entsperre()) return;
        const t = this.kontext.currentTime + 0.02;
        const grund = 392;
        [[1, 1], [2.01, 0.45], [2.76, 0.3], [5.4, 0.12]].forEach(([faktor, anteil]) => {
            this.#note(grund * faktor, t, 4.5 / Math.sqrt(faktor), lautstaerke * anteil * 0.6);
        });
    }

    /** Kurzer, freundlicher Hinweis (zwei Töne abwärts) für „zu laut“. */
    hinweis(lautstaerke = 0.35) {
        if (!this.entsperre()) return;
        const t = this.kontext.currentTime + 0.02;
        this.#note(784, t, 0.9, lautstaerke * 0.5, 'triangle');
        this.#note(587, t + 0.28, 1.2, lautstaerke * 0.5, 'triangle');
    }
}
