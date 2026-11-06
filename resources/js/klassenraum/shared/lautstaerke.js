/*
 * Klassenraum-Tools – gemeinsamer Motor der Lautstärke-Tools (Aquarium, Sternenhimmel, Blumenwiese).
 *
 * Aufgaben: Mikrofon → Pegel (0–100) → Zustand („ruhig“, „fast“, „laut“) mit Reaktionszeit
 * und Hysterese, sanft geglättete „Unruhe“ (0–1) für die Szene, Lautstärkeanzeige,
 * Einstellungen, Vollbild, Uhr und Zeichenschleife.
 *
 * Eine Szene ist ein Objekt mit:
 *   texte: {ruhig, fast, laut}            Statuszeilen der Anzeige
 *   mengeLabel: string                    Beschriftung des Reglers „Menge“
 *   groesse(breite, hoehe, einstellungen) bei Start und Größenänderung
 *   schritt(dt, zustand, einstellungen)   Simulation (dt in Sekunden, schon mit Tempo verrechnet)
 *   zeichne(ctx, breite, hoehe, zustand)  Zeichnen
 *   info?(zustand): string                optionale Zusatzinfo in der Anzeige
 *   besuch?(name): void                   seltenen Besuch sofort starten (Vorschau: ?besuch=name)
 *
 * Hintergrundmusik: shared/musik.js (Klangwelt je Tool-Schlüssel), abschaltbar, wird bei
 * Unruhe leiser. Läuft Musik, nutzt das Mikrofon die Echounterdrückung des Browsers,
 * damit die eigene Wiedergabe nicht als Lärm gemessen wird.
 *
 * Der Zustand enthält: status ('aus'|'ruhig'|'fast'|'laut'), pegel, grenze, unruhe (0–1),
 * ruheSeit (Sekunden ohne „zu laut“), zeit (Sekunden seit Start), mikrofon (bool).
 */

import {bindeEinstellungen, ladeEinstellungen, speichereEinstellungen} from './einstellungen';
import {Mikrofon, MikrofonFehler, MIKROFON_MELDUNGEN} from './mikrofon';
import {$, initLeerlauf, initPanel, initUhr, initVollbild, istKuerzel, schalteVollbild, Ton} from './oberflaeche';
import {formatiereDauer, klemme} from './hilfen';
import {KLANGWELTEN, Musik} from './musik';

export const STANDARD_EINSTELLUNGEN = {
    grenze: 60,
    empfindlichkeit: 50,
    reaktionszeit: 1.5,
    warnton: false,
    menge: 100,
    tempo: 100,
    uhr: true,
    anzeige: true,
    ausblenden: true,
    musik: true,
    musikLautstaerke: 40,
};

const ZIEL_UNRUHE = {aus: 0, ruhig: 0, fast: 0.35, laut: 1};

export function starteLautstaerkeTool({schluessel, szene, standard = {}}) {
    const basis = {...STANDARD_EINSTELLUNGEN, ...standard};
    const einstellungen = ladeEinstellungen(schluessel, basis);

    const el = {
        leinwand: $('#krt-szene'),
        mikroKnopf: $('#krt-mikro'),
        musikKnopf: $('#krt-musik'),
        startKarte: $('#krt-start'),
        startMikro: $('#krt-start-mikro'),
        startOhne: $('#krt-start-ohne'),
        fehler: $('#krt-fehler'),
        panel: $('#krt-panel'),
        panelKnopf: $('#krt-einstellungen-oeffnen'),
        anzeige: $('#krt-anzeige'),
        punkt: $('#krt-punkt'),
        fuellung: $('#krt-fuellung'),
        grenzMarke: $('#krt-grenze-marke'),
        status: $('#krt-status'),
        info: $('#krt-info'),
        ruhezeit: $('#krt-ruhezeit'),
        pauseKnopf: $('#krt-pause'),
        uhr: $('#krt-uhr'),
        mengeLabel: $('#krt-menge-label'),
        zuruecksetzen: $('#krt-zuruecksetzen'),
    };

    const ctx = el.leinwand.getContext('2d');
    const mikrofon = new Mikrofon();
    const ton = new Ton();
    const musik = new Musik(KLANGWELTEN[schluessel]);
    musik.setzeLautstaerke(einstellungen.musikLautstaerke / 100);
    const wenigBewegung = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    const zustand = {
        status: 'aus',
        pegel: 0,
        grenze: einstellungen.grenze,
        unruhe: 0,
        ruheSeit: 0,
        zeit: 0,
        mikrofon: false,
    };

    let druck = 0;           // 0–1: wie lange es schon über der Grenze ist (relativ zur Reaktionszeit)
    let laut = false;
    let pausiert = false;
    let letzterWarnton = -Infinity;
    let breite = 0;
    let hoehe = 0;

    if (el.mengeLabel && szene.mengeLabel) el.mengeLabel.textContent = szene.mengeLabel;

    // ── Einstellungen ────────────────────────────────────────────────────
    const anwenden = () => {
        zustand.grenze = einstellungen.grenze;
        el.grenzMarke.style.left = `${einstellungen.grenze}%`;
        document.body.classList.toggle('krt-ohne-uhr', !einstellungen.uhr);
        document.body.classList.toggle('krt-ohne-anzeige', !einstellungen.anzeige);
    };

    const bindung = bindeEinstellungen(document, einstellungen, (name) => {
        speichereEinstellungen(schluessel, einstellungen);
        anwenden();
        if (name === 'menge') szene.groesse(breite, hoehe, einstellungen);
        if (name === 'warnton' && einstellungen.warnton) ton.hinweis(0.25);
        if (name === 'musik') wendeMusikAn();
        if (name === 'musikLautstaerke') musik.setzeLautstaerke(einstellungen.musikLautstaerke / 100);
    });

    el.zuruecksetzen?.addEventListener('click', () => {
        Object.assign(einstellungen, basis);
        speichereEinstellungen(schluessel, einstellungen);
        bindung.aktualisiere();
        anwenden();
        szene.groesse(breite, hoehe, einstellungen);
        musik.setzeLautstaerke(einstellungen.musikLautstaerke / 100);
        wendeMusikAn();
    });

    anwenden();

    // ── Mikrofon ─────────────────────────────────────────────────────────
    const zeigeFehler = (text) => {
        el.fehler.textContent = text || '';
        el.fehler.hidden = !text;
    };

    const aktualisiereMikroKnopf = () => {
        const an = mikrofon.aktiv;
        el.mikroKnopf.setAttribute('aria-pressed', String(an));
        el.mikroKnopf.classList.toggle('krt-knopf-an', an);
        el.mikroKnopf.querySelector('i').className = an ? 'fas fa-microphone' : 'fas fa-microphone-slash';
        el.mikroKnopf.querySelector('span').textContent = an ? 'Hört zu' : 'Zuhören';
        el.mikroKnopf.title = an ? 'Mikrofon ausschalten (M)' : 'Mikrofon einschalten (M)';
        zustand.mikrofon = an;
    };

    const schliesseStartKarte = () => {
        el.startKarte.hidden = true;
    };

    const starteMikrofon = async () => {
        ton.entsperre();
        zeigeFehler('');
        el.mikroKnopf.disabled = true;
        el.startMikro.disabled = true;
        try {
            await mikrofon.starte({echoUnterdrueckung: einstellungen.musik});
            if (einstellungen.musik) musik.starte();
            schliesseStartKarte();
            zustand.ruheSeit = 0;
        } catch (fehler) {
            const grund = fehler instanceof MikrofonFehler ? fehler.grund : 'unbekannt';
            const text = MIKROFON_MELDUNGEN[grund] || MIKROFON_MELDUNGEN.unbekannt;
            zeigeFehler(text);
            el.startKarte.hidden = false;
        } finally {
            el.mikroKnopf.disabled = false;
            el.startMikro.disabled = false;
            aktualisiereMikroKnopf();
        }
    };

    const stoppeMikrofon = () => {
        mikrofon.stoppe();
        aktualisiereMikroKnopf();
    };

    mikrofon.beiEnde = () => {
        aktualisiereMikroKnopf();
        zeigeFehler('Das Mikrofon wurde getrennt.');
        el.startKarte.hidden = false;
    };

    el.mikroKnopf.addEventListener('click', () => (mikrofon.aktiv ? stoppeMikrofon() : starteMikrofon()));
    el.startMikro.addEventListener('click', starteMikrofon);
    el.startOhne.addEventListener('click', () => {
        ton.entsperre();
        if (einstellungen.musik) musik.starte();
        schliesseStartKarte();
    });

    // ── Musik ────────────────────────────────────────────────────────────
    function aktualisiereMusikKnopf() {
        if (!el.musikKnopf) return;
        if (!musik.verfuegbar) {
            el.musikKnopf.hidden = true;
            return;
        }
        const an = einstellungen.musik;
        el.musikKnopf.setAttribute('aria-pressed', String(an));
        el.musikKnopf.classList.toggle('krt-knopf-an', an);
        el.musikKnopf.querySelector('i').className = an ? 'fas fa-music' : 'fas fa-volume-mute';
        el.musikKnopf.title = an ? 'Musik ausschalten (T)' : 'Musik einschalten (T)';
        el.musikKnopf.setAttribute('aria-label', el.musikKnopf.title);
    }

    // Musik an/aus übernehmen; ein laufendes Mikrofon wird mit passender Echounterdrückung neu gestartet.
    async function wendeMusikAn() {
        aktualisiereMusikKnopf();
        if (einstellungen.musik) {
            // Ton erst nach einer Nutzeraktion – solange die Startkarte offen ist, startet sie die Musik
            if (el.startKarte.hidden) musik.starte();
        } else {
            musik.stoppe();
        }
        if (mikrofon.aktiv) {
            mikrofon.stoppe();
            try {
                await mikrofon.starte({echoUnterdrueckung: einstellungen.musik});
            } catch (_) {
                // Fehler zeigt der Mikrofon-Knopf (aus); kein erneuter Dialog nötig
            }
            aktualisiereMikroKnopf();
        }
    }

    const schalteMusik = () => {
        einstellungen.musik = !einstellungen.musik;
        speichereEinstellungen(schluessel, einstellungen);
        bindung.aktualisiere();
        wendeMusikAn();
    };
    el.musikKnopf?.addEventListener('click', schalteMusik);
    aktualisiereMusikKnopf();

    // ── Pause ────────────────────────────────────────────────────────────
    const setzePause = (wert) => {
        pausiert = wert;
        document.body.classList.toggle('krt-pausiert', pausiert);
        el.pauseKnopf.setAttribute('aria-pressed', String(pausiert));
        el.pauseKnopf.title = pausiert ? 'Weiter (Leertaste)' : 'Pause (Leertaste)';
        el.pauseKnopf.setAttribute('aria-label', el.pauseKnopf.title);
        el.pauseKnopf.querySelector('i').className = pausiert ? 'fas fa-play' : 'fas fa-pause';
        musik.pausiere(pausiert);
    };
    el.pauseKnopf.addEventListener('click', () => setzePause(!pausiert));

    // ── Bedienung ────────────────────────────────────────────────────────
    const panel = initPanel(el.panel, el.panelKnopf);
    initVollbild($('#krt-vollbild'));
    initUhr(el.uhr);
    initLeerlauf(() => einstellungen.ausblenden && !panel.offen() && el.startKarte.hidden);

    document.addEventListener('keydown', (e) => {
        if (!istKuerzel(e)) return;
        const taste = e.key.toLowerCase();
        if (taste === 'f') {
            schalteVollbild();
        } else if (taste === 'm') {
            mikrofon.aktiv ? stoppeMikrofon() : starteMikrofon();
        } else if (taste === 't' && musik.verfuegbar) {
            schalteMusik();
        } else if (e.key === ' ' && !(e.target instanceof HTMLButtonElement)) {
            e.preventDefault();
            setzePause(!pausiert);
        }
    });

    // Bildschirm wach halten, solange das Tool sichtbar ist (wo unterstützt)
    let wachSperre = null;
    const halteWach = async () => {
        try {
            if ('wakeLock' in navigator && document.visibilityState === 'visible') {
                wachSperre = await navigator.wakeLock.request('screen');
            }
        } catch (_) {
            wachSperre = null;
        }
    };
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !wachSperre) halteWach();
    });
    halteWach();

    // ── Zustand berechnen ────────────────────────────────────────────────
    const aktualisiereZustand = (dt) => {
        if (mikrofon.aktiv) {
            const roh = mikrofon.messe(einstellungen.empfindlichkeit);
            // schnell nach oben, langsam nach unten – ruhige Anzeige ohne Zittern
            const k = roh > zustand.pegel ? 1 - Math.exp(-dt * 12) : 1 - Math.exp(-dt * 2.5);
            zustand.pegel += (roh - zustand.pegel) * k;

            const reaktion = Math.max(0.2, einstellungen.reaktionszeit);
            if (zustand.pegel > einstellungen.grenze) {
                druck = Math.min(1, druck + dt / reaktion);
            } else {
                druck = Math.max(0, druck - dt / (reaktion * 2));
            }

            if (!laut && druck >= 1) {
                laut = true;
                if (einstellungen.warnton && zustand.zeit - letzterWarnton > 8) {
                    ton.hinweis();
                    letzterWarnton = zustand.zeit;
                }
            } else if (laut && druck <= 0.35) {
                laut = false;
            }

            if (laut) zustand.status = 'laut';
            else if (zustand.pegel >= einstellungen.grenze * 0.85 || druck > 0.25) zustand.status = 'fast';
            else zustand.status = 'ruhig';
        } else {
            zustand.pegel = Math.max(0, zustand.pegel - dt * 60);
            druck = 0;
            laut = false;
            zustand.status = 'aus';
        }

        const ziel = ZIEL_UNRUHE[zustand.status];
        const rate = ziel > zustand.unruhe ? 0.9 : 0.22;
        zustand.unruhe = klemme(zustand.unruhe + klemme(ziel - zustand.unruhe, -rate * dt, rate * dt), 0, 1);

        if (zustand.status === 'laut') zustand.ruheSeit = 0;
        else if (zustand.status !== 'fast') zustand.ruheSeit += dt;
    };

    // ── Anzeige ──────────────────────────────────────────────────────────
    let letzteAnzeige = 0;
    const aktualisiereAnzeige = () => {
        el.fuellung.style.width = `${zustand.pegel.toFixed(1)}%`;
        el.anzeige.dataset.status = pausiert ? 'pause' : zustand.status;

        let text;
        if (pausiert) text = 'Pausiert';
        else if (zustand.status === 'aus') text = 'Mikrofon ist aus';
        else text = szene.texte[zustand.status];
        if (el.status.textContent !== text) el.status.textContent = text;

        const ruhe = mikrofon.aktiv && zustand.status !== 'laut' && zustand.ruheSeit >= 5
            ? `Ruhig seit ${formatiereDauer(zustand.ruheSeit)}`
            : '';
        if (el.ruhezeit.textContent !== ruhe) el.ruhezeit.textContent = ruhe;

        const info = szene.info?.(zustand) || '';
        if (el.info.textContent !== info) el.info.textContent = info;
    };

    // ── Zeichenfläche ────────────────────────────────────────────────────
    const groesse = () => {
        const dpr = Math.min(2, window.devicePixelRatio || 1);
        breite = window.innerWidth;
        hoehe = window.innerHeight;
        el.leinwand.width = Math.round(breite * dpr);
        el.leinwand.height = Math.round(hoehe * dpr);
        el.leinwand.style.width = `${breite}px`;
        el.leinwand.style.height = `${hoehe}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        szene.groesse(breite, hoehe, einstellungen);
    };
    window.addEventListener('resize', groesse);
    groesse();

    // Vorschau eines seltenen Besuchs, z. B. /klassenraum/aquarium?besuch=schildkroete
    const vorschauBesuch = new URLSearchParams(window.location.search).get('besuch');
    if (vorschauBesuch) szene.besuch?.(vorschauBesuch);

    let letzteZeit = performance.now();
    const bild = (jetzt) => {
        const dt = Math.min(0.05, Math.max(0, (jetzt - letzteZeit) / 1000));
        letzteZeit = jetzt;

        if (!pausiert) {
            aktualisiereZustand(dt);
            zustand.zeit += dt;
            const tempo = (einstellungen.tempo / 100) * (wenigBewegung ? 0.5 : 1);
            szene.schritt(dt * tempo, zustand, einstellungen);
            musik.setzeUnruhe(zustand.unruhe);
        }
        szene.zeichne(ctx, breite, hoehe, zustand);

        if (jetzt - letzteAnzeige > 66) {
            aktualisiereAnzeige();
            letzteAnzeige = jetzt;
        }
        requestAnimationFrame(bild);
    };
    requestAnimationFrame(bild);

    aktualisiereMikroKnopf();
    setzePause(false);

    return {zustand, einstellungen};
}
