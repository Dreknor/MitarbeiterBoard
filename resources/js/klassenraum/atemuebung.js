/*
 * Klassenraum-Tool „Atemübung“ – Vite-Entrypoint.
 *
 * Ein Kreis wächst beim Einatmen und schrumpft beim Ausatmen (optional mit Halten).
 * Schon vor dem Start atmet der Kreis leise im gewählten Rhythmus vor. Nach Ablauf der
 * Dauer endet die Übung nach dem nächsten vollständigen Ausatmen mit einem Gong.
 * Optional: sanfter Ton bei jedem Wechsel und ruhige Hintergrundmusik (shared/musik.js).
 */

import {bindeEinstellungen, ladeEinstellungen, speichereEinstellungen} from './shared/einstellungen';
import {$, initFokusleiste, initLeerlauf, initVollbild, istKuerzel, schalteVollbild, Ton} from './shared/oberflaeche';
import {KLANGWELTEN, Musik} from './shared/musik';

const RHYTHMEN = {
    ruhig: [['ein', 4], ['aus', 6]],
    gleich: [['ein', 4], ['aus', 4]],
    viereck: [['ein', 4], ['halten', 4], ['aus', 4], ['halten', 4]],
    kurz: [['ein', 3], ['aus', 3]],
};
const TEXTE = {ein: 'Einatmen', halten: 'Halten', aus: 'Ausatmen'};
const KLEIN = 0.4;

const STANDARD = {rhythmus: 'ruhig', dauer: 2, klang: true, musik: false};
const einstellungen = ladeEinstellungen('atemuebung', STANDARD);
if (!RHYTHMEN[einstellungen.rhythmus]) einstellungen.rhythmus = STANDARD.rhythmus;
if (![0, 1, 2, 3, 5].includes(einstellungen.dauer)) einstellungen.dauer = STANDARD.dauer;
const speichern = () => speichereEinstellungen('atemuebung', einstellungen);

const el = {
    wurzel: $('#atm'),
    kreis: $('#atm-kreis'),
    phase: $('#atm-phase'),
    zahl: $('#atm-zahl'),
    rest: $('#atm-rest'),
    weiter: $('#atm-weiter'),
};

const ton = new Ton();
const musik = new Musik(KLANGWELTEN.atemuebung);
musik.setzeLautstaerke(0.45);

let status = 'bereit'; // bereit | laeuft | pause | fertig
let verstrichen = 0;   // ms seit Start (ohne Pausen)
let vorschau = 0;      // ms für das Vor-Atmen in der Planung
let letztePhase = -1;
let letzteZeit = performance.now();
let endePhase = false;

const ease = (p) => 0.5 - Math.cos(Math.PI * p) / 2;

/** Lage im Zyklus: Phase, Fortschritt (0–1) und Kreisgröße (0–1). */
function lage(ms) {
    const rhythmus = RHYTHMEN[einstellungen.rhythmus];
    const zyklus = rhythmus.reduce((s, [, sek]) => s + sek, 0) * 1000;
    let rest = ms % zyklus;
    for (let i = 0; i < rhythmus.length; i++) {
        const [art, sek] = rhythmus[i];
        if (rest < sek * 1000) {
            const p = rest / (sek * 1000);
            let groesse;
            if (art === 'ein') groesse = ease(p);
            else if (art === 'aus') groesse = 1 - ease(p);
            else groesse = rhythmus[(i + rhythmus.length - 1) % rhythmus.length][0] === 'ein' ? 1 : 0;
            return {index: i, art, sek, p, groesse, zyklusNr: Math.floor(ms / zyklus), zyklus};
        }
        rest -= sek * 1000;
    }
    return {index: 0, art: 'ein', sek: rhythmus[0][1], p: 0, groesse: 0, zyklusNr: 0, zyklus};
}

function klang(art) {
    if (!einstellungen.klang) return;
    if (art === 'ein') {
        ton.ton(392, 0, 1.6, 0.12);
        ton.ton(523.25, 0.25, 1.8, 0.1);
    } else if (art === 'aus') {
        ton.ton(523.25, 0, 1.6, 0.1);
        ton.ton(392, 0.25, 2.2, 0.12);
    }
}

function setzeKreis(groesse) {
    const skala = KLEIN + (1 - KLEIN) * groesse;
    el.kreis.style.transform = `scale(${skala.toFixed(4)})`;
    el.kreis.style.opacity = String(0.75 + 0.25 * groesse);
}

function setzeText(phase, zahl) {
    if (el.phase.textContent !== phase) el.phase.textContent = phase;
    if (el.zahl.textContent !== zahl) el.zahl.textContent = zahl;
}

function bild(jetzt) {
    const dt = Math.min(100, jetzt - letzteZeit);
    letzteZeit = jetzt;

    if (status === 'bereit') {
        vorschau += dt;
        setzeKreis(lage(vorschau).groesse);
        setzeText('Bereit?', '');
    } else if (status === 'laeuft') {
        verstrichen += dt;
        const l = lage(verstrichen);
        if (l.index !== letztePhase) {
            // Ende erst nach vollständigem Ausatmen (wenn der Zyklus wieder beginnt)
            if (endePhase && l.index === 0) {
                beende();
                requestAnimationFrame(bild);
                return;
            }
            letztePhase = l.index;
            klang(l.art);
        }
        const dauerMs = einstellungen.dauer * 60_000;
        if (dauerMs > 0 && verstrichen >= dauerMs) endePhase = true;
        setzeKreis(l.groesse);
        setzeText(TEXTE[l.art], String(Math.min(l.sek, Math.floor(l.p * l.sek) + 1)));
        zeigeRest(dauerMs);
    }
    requestAnimationFrame(bild);
}

function zeigeRest(dauerMs) {
    let text = '';
    if (status === 'pause') text = 'Pausiert';
    else if (dauerMs > 0) {
        const rest = Math.max(0, dauerMs - verstrichen);
        text = endePhase ? 'noch einmal ausatmen …' : rest > 60_000 ? `noch ${Math.ceil(rest / 60_000)} min` : 'noch < 1 min';
    }
    if (el.rest.textContent !== text) el.rest.textContent = text;
}

function setzeStatus(neu) {
    status = neu;
    el.wurzel.dataset.status = status;
    const body = document.body.classList;
    body.toggle('tmr-fokus', status !== 'bereit');
    body.toggle('tmr-laeuft', status === 'laeuft');
    body.toggle('tmr-leiste-dauerhaft', status === 'pause' || status === 'fertig');
    const symbol = el.weiter.querySelector('i');
    const text = el.weiter.querySelector('span');
    if (status === 'laeuft') {
        symbol.className = 'fas fa-pause';
        text.textContent = 'Pause';
    } else if (status === 'pause') {
        symbol.className = 'fas fa-play';
        text.textContent = 'Weiter';
    } else {
        symbol.className = 'fas fa-redo';
        text.textContent = 'Noch einmal';
    }
}

function start() {
    ton.entsperre();
    if (einstellungen.musik) musik.starte();
    verstrichen = 0;
    letztePhase = -1;
    endePhase = false;
    setzeStatus('laeuft');
    leiste.zeige();
}

function beende() {
    setzeStatus('fertig');
    setzeKreis(0);
    setzeText('Schön!', 'Ihr seid angekommen.');
    el.rest.textContent = '';
    ton.gong(0.45);
    musik.stoppe();
}

function weiterOderPause() {
    if (status === 'laeuft') {
        setzeStatus('pause');
        zeigeRest(einstellungen.dauer * 60_000);
        musik.pausiere(true);
    } else if (status === 'pause') {
        setzeStatus('laeuft');
        musik.pausiere(false);
    } else if (status === 'fertig') {
        start();
    }
}

function zurPlanung() {
    setzeStatus('bereit');
    el.rest.textContent = '';
    leiste.verberge();
    musik.stoppe();
}

// ── Auswahl ──────────────────────────────────────────────────────────────
function zeigeAuswahl() {
    document.querySelectorAll('[data-gruppe]').forEach((gruppe) => {
        const name = gruppe.dataset.gruppe;
        gruppe.querySelectorAll('[data-wert]').forEach((k) => {
            const aktiv = String(einstellungen[name]) === k.dataset.wert;
            k.setAttribute('aria-checked', String(aktiv));
            k.classList.toggle('atm-chip-aktiv', aktiv);
        });
    });
}
document.querySelectorAll('[data-gruppe]').forEach((gruppe) => {
    const name = gruppe.dataset.gruppe;
    gruppe.querySelectorAll('[data-wert]').forEach((k) => {
        k.addEventListener('click', () => {
            einstellungen[name] = name === 'dauer' ? Number(k.dataset.wert) : k.dataset.wert;
            speichern();
            zeigeAuswahl();
            if (name === 'rhythmus') vorschau = 0;
        });
    });
});

bindeEinstellungen(document, einstellungen, (name) => {
    speichern();
    if (name === 'klang' && einstellungen.klang) ton.ton(523.25, 0, 1.2, 0.1);
    if (name === 'musik' && !einstellungen.musik) musik.stoppe();
});

// ── Bedienung ────────────────────────────────────────────────────────────
const leiste = initFokusleiste($('#tmr-fokusleiste'), () => status !== 'bereit');
initVollbild($('#krt-vollbild'));
initVollbild($('#tmr-fokus-vollbild'));
initLeerlauf(() => status === 'laeuft' && !document.body.classList.contains('tmr-leiste-sichtbar'), 2000);

$('#atm-start').addEventListener('click', start);
el.weiter.addEventListener('click', () => {
    weiterOderPause();
    leiste.zeige();
});
$('#atm-ende').addEventListener('click', zurPlanung);

document.addEventListener('keydown', (e) => {
    if (!istKuerzel(e)) return;
    const taste = e.key.toLowerCase();
    if (taste === 'f') {
        schalteVollbild();
        return;
    }
    if (status === 'bereit') return;
    if (e.key === ' ' && !(e.target instanceof HTMLButtonElement)) {
        e.preventDefault();
        weiterOderPause();
    } else if (taste === 'r') {
        zurPlanung();
        return;
    }
    leiste.zeige();
});

zeigeAuswahl();
setzeStatus('bereit');
requestAnimationFrame(bild);
