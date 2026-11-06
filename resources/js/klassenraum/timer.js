/*
 * Klassenraum-Tool „Timer“ – Vite-Entrypoint.
 *
 * Großer Countdown mit schwindender Zeitscheibe (voller Kreis = eingestellte Dauer).
 * Die Restzeit wird aus einer festen Endzeit berechnet, damit der Timer auch in einem
 * Hintergrund-Tab oder bei ruckelnder Darstellung exakt bleibt.
 */

import {bindeEinstellungen, ladeEinstellungen, speichereEinstellungen} from './shared/einstellungen';
import {$, initLeerlauf, initPanel, initUhr, initVollbild, istKuerzel, schalteVollbild, Ton} from './shared/oberflaeche';
import {formatiereDauer, klemme} from './shared/hilfen';

const FARBEN = {rot: '#e11d48', orange: '#ea580c', gruen: '#16a34a', blau: '#2563eb', lila: '#7c3aed'};
const MAX_SEKUNDEN = 180 * 60;

const STANDARD = {
    dauer: 300,
    farbe: 'rot',
    gong: true,
    gongLautstaerke: 60,
    ueberziehen: true,
    uhr: true,
    ausblenden: true,
    aufgabe: '',
};

const einstellungen = ladeEinstellungen('timer', STANDARD);
if (!FARBEN[einstellungen.farbe]) einstellungen.farbe = STANDARD.farbe;
einstellungen.dauer = klemme(Math.round(einstellungen.dauer), 5, MAX_SEKUNDEN);

const el = {
    wurzel: $('#tmr'),
    keil: $('#tmr-keil'),
    striche: $('#tmr-striche'),
    zeit: $('#tmr-zeit'),
    hinweis: $('#tmr-hinweis'),
    aufgabe: $('#tmr-aufgabe'),
    minuten: $('#tmr-minuten'),
    sekunden: $('#tmr-sekunden'),
    start: $('#tmr-start'),
    reset: $('#tmr-reset'),
    panel: $('#krt-panel'),
};

const ton = new Ton();
const speichern = () => speichereEinstellungen('timer', einstellungen);
const uhrzeit = new Intl.DateTimeFormat('de-DE', {hour: '2-digit', minute: '2-digit'});

let status = 'bereit'; // bereit | laeuft | pause | fertig
let restMs = einstellungen.dauer * 1000;
let endeMs = 0;

// ── Zifferblatt ──────────────────────────────────────────────────────────
for (let i = 0; i < 60; i++) {
    const w = (i / 60) * Math.PI * 2;
    const lang = i % 5 === 0;
    const r1 = lang ? 82 : 88;
    const strich = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    strich.setAttribute('x1', String(100 + Math.sin(w) * r1));
    strich.setAttribute('y1', String(100 - Math.cos(w) * r1));
    strich.setAttribute('x2', String(100 + Math.sin(w) * 93));
    strich.setAttribute('y2', String(100 - Math.cos(w) * 93));
    strich.setAttribute('class', lang ? 'tmr-strich tmr-strich-lang' : 'tmr-strich');
    el.striche.appendChild(strich);
}

function keilPfad(anteil) {
    const a = klemme(anteil, 0, 1);
    const r = 78;
    if (a <= 0) return '';
    if (a >= 0.9999) return `M100,${100 - r} A${r},${r} 0 1,1 99.99,${100 - r} Z`;
    const w = a * Math.PI * 2;
    const x = 100 + Math.sin(w) * r;
    const y = 100 - Math.cos(w) * r;
    return `M100,100 L100,${100 - r} A${r},${r} 0 ${a > 0.5 ? 1 : 0},1 ${x.toFixed(3)},${y.toFixed(3)} Z`;
}

// ── Darstellung ──────────────────────────────────────────────────────────
function setzeFarbe() {
    document.documentElement.style.setProperty('--tmr-farbe', FARBEN[einstellungen.farbe]);
    document.querySelectorAll('.tmr-farbe').forEach((k) => {
        k.setAttribute('aria-checked', String(k.dataset.farbe === einstellungen.farbe));
    });
}

function zeigeEingaben() {
    el.minuten.value = Math.floor(einstellungen.dauer / 60);
    el.sekunden.value = einstellungen.dauer % 60;
    document.querySelectorAll('.tmr-chip').forEach((chip) => {
        chip.classList.toggle('tmr-chip-aktiv', Number(chip.dataset.minuten) * 60 === einstellungen.dauer);
    });
}

function anzeigen() {
    const jetzt = Date.now();
    let text;
    let anteil;
    let hinweis;

    if (status === 'fertig') {
        const drueber = (jetzt - endeMs) / 1000;
        text = einstellungen.ueberziehen ? `+${formatiereDauer(drueber)}` : '0:00';
        anteil = 0;
        hinweis = 'Die Zeit ist um!';
    } else {
        const sekunden = Math.ceil(restMs / 1000);
        text = formatiereDauer(sekunden);
        anteil = restMs / (einstellungen.dauer * 1000);
        if (status === 'laeuft') hinweis = `Ende um ${uhrzeit.format(new Date(endeMs))} Uhr`;
        else if (status === 'pause') hinweis = 'Pausiert';
        else hinweis = 'Bereit';
    }

    if (el.zeit.textContent !== text) el.zeit.textContent = text;
    if (el.hinweis.textContent !== hinweis) el.hinweis.textContent = hinweis;
    el.keil.setAttribute('d', keilPfad(anteil));
    el.wurzel.dataset.status = status;
    el.wurzel.classList.toggle('tmr-knapp', status === 'laeuft' && restMs <= 10_000);
    document.body.classList.toggle('tmr-laeuft', status === 'laeuft');

    const titel = einstellungen.aufgabe ? `${einstellungen.aufgabe} · ` : '';
    document.title = status === 'bereit' ? 'Timer · Klassenraum-Tools' : `${text} · ${titel}Timer`;

    const symbol = el.start.querySelector('i');
    const beschriftung = el.start.querySelector('span');
    if (status === 'laeuft') {
        symbol.className = 'fas fa-pause';
        beschriftung.textContent = 'Pause';
    } else {
        symbol.className = 'fas fa-play';
        beschriftung.textContent = status === 'pause' ? 'Weiter' : status === 'fertig' ? 'Neu starten' : 'Start';
    }
}

// ── Steuerung ────────────────────────────────────────────────────────────
function setzeDauer(sekunden) {
    einstellungen.dauer = klemme(Math.round(sekunden), 5, MAX_SEKUNDEN);
    speichern();
    status = 'bereit';
    restMs = einstellungen.dauer * 1000;
    zeigeEingaben();
    anzeigen();
}

function start() {
    ton.entsperre();
    if (status === 'bereit' || status === 'fertig') restMs = einstellungen.dauer * 1000;
    endeMs = Date.now() + restMs;
    status = 'laeuft';
    anzeigen();
}

function pause() {
    restMs = Math.max(0, endeMs - Date.now());
    status = 'pause';
    anzeigen();
}

function zuruecksetzen() {
    status = 'bereit';
    restMs = einstellungen.dauer * 1000;
    anzeigen();
}

function umschalten() {
    if (status === 'laeuft') pause();
    else start();
}

function aendere(deltaSekunden) {
    if (status === 'laeuft' || status === 'pause') {
        // Laufenden Timer verlängern/verkürzen, Scheibe bleibt stimmig
        const neuRest = Math.max(1000, (status === 'laeuft' ? endeMs - Date.now() : restMs) + deltaSekunden * 1000);
        einstellungen.dauer = klemme(Math.max(einstellungen.dauer + deltaSekunden, Math.ceil(neuRest / 1000)), 5, MAX_SEKUNDEN);
        restMs = Math.min(neuRest, einstellungen.dauer * 1000);
        if (status === 'laeuft') endeMs = Date.now() + restMs;
        speichern();
        zeigeEingaben();
        anzeigen();
    } else {
        setzeDauer(einstellungen.dauer + deltaSekunden);
    }
}

document.querySelectorAll('.tmr-chip').forEach((chip) => {
    chip.addEventListener('click', () => setzeDauer(Number(chip.dataset.minuten) * 60));
});
document.querySelectorAll('[data-plus]').forEach((knopf) => {
    knopf.addEventListener('click', () => aendere(Number(knopf.dataset.plus)));
});

const ausEingaben = () => {
    const m = klemme(parseInt(el.minuten.value, 10) || 0, 0, 180);
    const s = klemme(parseInt(el.sekunden.value, 10) || 0, 0, 59);
    setzeDauer(m * 60 + s);
};
el.minuten.addEventListener('change', ausEingaben);
el.sekunden.addEventListener('change', ausEingaben);

el.start.addEventListener('click', umschalten);
el.reset.addEventListener('click', zuruecksetzen);

el.aufgabe.value = einstellungen.aufgabe;
el.aufgabe.addEventListener('input', () => {
    einstellungen.aufgabe = el.aufgabe.value.trim().slice(0, 60);
    speichern();
    anzeigen();
});
el.aufgabe.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') el.aufgabe.blur();
});

document.querySelectorAll('.tmr-farbe').forEach((knopf) => {
    knopf.addEventListener('click', () => {
        einstellungen.farbe = knopf.dataset.farbe;
        speichern();
        setzeFarbe();
    });
});

const anwenden = () => document.body.classList.toggle('krt-ohne-uhr', !einstellungen.uhr);
bindeEinstellungen(document, einstellungen, (name) => {
    speichern();
    anwenden();
    if (name === 'gongLautstaerke' || (name === 'gong' && einstellungen.gong)) ton.gong(einstellungen.gongLautstaerke / 100);
    anzeigen();
});

const panel = initPanel(el.panel, $('#krt-einstellungen-oeffnen'));
initVollbild($('#krt-vollbild'));
initUhr($('#krt-uhr'));
initLeerlauf(() => einstellungen.ausblenden && status === 'laeuft' && !panel.offen() && document.activeElement !== el.aufgabe);

document.addEventListener('keydown', (e) => {
    if (!istKuerzel(e)) return;
    const taste = e.key.toLowerCase();
    if (e.key === ' ' && !(e.target instanceof HTMLButtonElement)) {
        e.preventDefault();
        umschalten();
    } else if (taste === 'r') {
        zuruecksetzen();
    } else if (taste === 'f') {
        schalteVollbild();
    }
});

// ── Takt ─────────────────────────────────────────────────────────────────
function takt() {
    if (status === 'laeuft') {
        restMs = endeMs - Date.now();
        if (restMs <= 0) {
            restMs = 0;
            status = 'fertig';
            if (einstellungen.gong) ton.gong(einstellungen.gongLautstaerke / 100);
        }
    }
    anzeigen();
}
setInterval(takt, 200);

setzeFarbe();
anwenden();
zeigeEingaben();
anzeigen();
