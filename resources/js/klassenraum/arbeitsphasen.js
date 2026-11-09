/*
 * Klassenraum-Tool „Arbeitsphasen“ – Vite-Entrypoint.
 *
 * Mehrere Schritte laufen nacheinander ab (z. B. 8 min Lesen → 2 min Aufräumen → Sitzkreis).
 * Ein Schritt mit 0 Minuten ist „offen“ (ohne Zeitbegrenzung, endet per „Nächster Schritt“).
 * Am Ende jedes Schritts erklingt ein Gong; der nächste startet automatisch oder nach „Weiter“.
 * Die Darstellung der Restzeit teilt sich das Tool mit dem Timer (shared/zeitanzeige.js).
 *
 * Zustände: bereit (Planung) · laeuft · pause · wartet (Schritt vorbei, warten auf „Weiter“)
 *           · offen (Schritt ohne Zeit) · fertig
 */

import {bindeEinstellungen, ladeEinstellungen, speichereEinstellungen} from './shared/einstellungen';
import {$, initFokusleiste, initLeerlauf, initPanel, initUhr, initVollbild, istKuerzel, schalteVollbild, Ton} from './shared/oberflaeche';
import {formatiereDauer, klemme} from './shared/hilfen';
import {DARSTELLUNGEN, erzeugeZeitanzeige} from './shared/zeitanzeige';

const SCHRITT_FARBEN = ['#2563eb', '#16a34a', '#ea580c', '#7c3aed', '#e11d48', '#0891b2'];
const MAX_SCHRITTE = 12;

const VORLAGEN = {
    lesen: [['Lesen', 8], ['Aufräumen', 2], ['Sitzkreis', 0]],
    arbeit: [['Stillarbeit', 15], ['Partnerarbeit', 10], ['Vorstellen', 10]],
    stationen: [['Station 1', 8], ['Wechseln', 1], ['Station 2', 8], ['Wechseln', 1], ['Station 3', 8], ['Wechseln', 1], ['Station 4', 8], ['Abschlusskreis', 0]],
    ankommen: [['Ankommen', 5], ['Arbeitsphase', 20], ['Bewegungspause', 3]],
};
const ausVorlage = (liste) => liste.map(([name, minuten]) => ({name, minuten}));

const STANDARD = {
    schritte: ausVorlage(VORLAGEN.lesen),
    darstellung: 'scheibe',
    automatisch: true,
    sekunden: false,
    gong: true,
    gongLautstaerke: 60,
    uhr: true,
};

const einstellungen = ladeEinstellungen('arbeitsphasen', STANDARD);
const pruefeSchritte = (liste) => Array.isArray(liste)
    && liste.length > 0
    && liste.every((s) => s && typeof s.name === 'string' && Number.isFinite(s.minuten));
if (!pruefeSchritte(einstellungen.schritte)) einstellungen.schritte = ausVorlage(VORLAGEN.lesen);
einstellungen.schritte = einstellungen.schritte.slice(0, MAX_SCHRITTE).map((s) => ({
    name: s.name.slice(0, 40),
    minuten: klemme(Math.round(s.minuten), 0, 180),
}));
if (!DARSTELLUNGEN[einstellungen.darstellung]) einstellungen.darstellung = STANDARD.darstellung;

const speichern = () => speichereEinstellungen('arbeitsphasen', einstellungen);
const schritte = () => einstellungen.schritte;
const farbe = (i) => SCHRITT_FARBEN[i % SCHRITT_FARBEN.length];
const uhrzeit = new Intl.DateTimeFormat('de-DE', {hour: '2-digit', minute: '2-digit'});
const ton = new Ton();

const el = {
    wurzel: $('#abl'),
    liste: $('#abl-schritte'),
    gesamt: $('#abl-gesamt'),
    fortschritt: $('#abl-fortschritt'),
    rahmen: $('#tmr-rahmen'),
    name: $('#abl-name'),
    zeit: $('#abl-zeit'),
    danach: $('#abl-danach'),
    info: $('#abl-info'),
    weiter: $('#abl-weiter'),
    leiste: $('#tmr-fokusleiste'),
};

const anzeige = erzeugeZeitanzeige($('#tmr-anzeige'));

let status = 'bereit';
let index = 0;
let dauerMs = 0;
let restMs = 0;
let endeMs = 0;
let fertigSeit = 0;

// ── Planung ──────────────────────────────────────────────────────────────
function knopf(icon, titel, beiKlick, deaktiviert = false) {
    const k = document.createElement('button');
    k.type = 'button';
    k.className = 'krt-knopf krt-knopf-rund';
    k.title = titel;
    k.setAttribute('aria-label', titel);
    k.disabled = deaktiviert;
    k.innerHTML = `<i class="${icon}"></i>`;
    k.addEventListener('click', beiKlick);
    return k;
}

function zeigeGesamt() {
    const minuten = schritte().reduce((summe, s) => summe + s.minuten, 0);
    const offen = schritte().some((s) => s.minuten === 0);
    el.gesamt.textContent = `${minuten} min${offen ? ' + offener Schritt' : ''}`;
}

function zeichneListe() {
    el.liste.replaceChildren();
    schritte().forEach((schritt, i) => {
        const zeile = document.createElement('li');
        zeile.className = 'abl-schritt';
        zeile.style.setProperty('--farbe', farbe(i));

        const nummer = document.createElement('span');
        nummer.className = 'abl-nummer';
        nummer.textContent = String(i + 1);

        const name = document.createElement('input');
        name.type = 'text';
        name.className = 'abl-name-feld';
        name.maxLength = 40;
        name.value = schritt.name;
        name.placeholder = 'Name des Schritts';
        name.setAttribute('aria-label', `Name von Schritt ${i + 1}`);
        name.addEventListener('input', () => {
            schritt.name = name.value.slice(0, 40);
            speichern();
        });

        const feld = document.createElement('label');
        feld.className = 'tmr-feld';
        const minuten = document.createElement('input');
        minuten.type = 'number';
        minuten.min = '0';
        minuten.max = '180';
        minuten.inputMode = 'numeric';
        minuten.value = String(schritt.minuten);
        minuten.setAttribute('aria-label', `Minuten für Schritt ${i + 1}`);
        minuten.addEventListener('change', () => {
            schritt.minuten = klemme(parseInt(minuten.value, 10) || 0, 0, 180);
            minuten.value = String(schritt.minuten);
            speichern();
            zeigeGesamt();
        });
        const einheit = document.createElement('span');
        einheit.textContent = 'min';
        feld.append(minuten, einheit);

        const verschiebe = (richtung) => {
            const liste = schritte();
            const ziel = i + richtung;
            [liste[i], liste[ziel]] = [liste[ziel], liste[i]];
            speichern();
            zeichneListe();
        };

        zeile.append(
            nummer,
            name,
            feld,
            knopf('fas fa-arrow-up', 'Nach oben', () => verschiebe(-1), i === 0),
            knopf('fas fa-arrow-down', 'Nach unten', () => verschiebe(1), i === schritte().length - 1),
            knopf('fas fa-trash-alt', 'Schritt entfernen', () => {
                schritte().splice(i, 1);
                speichern();
                zeichneListe();
            }, schritte().length <= 1),
        );
        el.liste.appendChild(zeile);
    });
    $('#abl-hinzufuegen').disabled = schritte().length >= MAX_SCHRITTE;
    zeigeGesamt();
}

$('#abl-hinzufuegen').addEventListener('click', () => {
    if (schritte().length >= MAX_SCHRITTE) return;
    schritte().push({name: '', minuten: 5});
    speichern();
    zeichneListe();
    el.liste.lastElementChild?.querySelector('.abl-name-feld')?.focus();
});

document.querySelectorAll('[data-vorlage]').forEach((k) => {
    k.addEventListener('click', () => {
        einstellungen.schritte = ausVorlage(VORLAGEN[k.dataset.vorlage]);
        speichern();
        zeichneListe();
    });
});

function setzeDarstellung() {
    anzeige.setzeArt(einstellungen.darstellung);
    el.rahmen.dataset.art = einstellungen.darstellung;
    document.querySelectorAll('.tmr-darstellung').forEach((k) => {
        const aktiv = k.dataset.darstellung === einstellungen.darstellung;
        k.setAttribute('aria-checked', String(aktiv));
        k.classList.toggle('tmr-chip-aktiv', aktiv);
    });
}
document.querySelectorAll('.tmr-darstellung').forEach((k) => {
    k.addEventListener('click', () => {
        einstellungen.darstellung = k.dataset.darstellung;
        speichern();
        setzeDarstellung();
    });
});

// ── Ablauf ───────────────────────────────────────────────────────────────
const gong = () => {
    if (einstellungen.gong) ton.gong(einstellungen.gongLautstaerke / 100);
};

function starteSchritt(i) {
    index = i;
    const schritt = schritte()[i];
    document.documentElement.style.setProperty('--tmr-farbe', farbe(i));
    if (schritt.minuten > 0) {
        dauerMs = schritt.minuten * 60_000;
        restMs = dauerMs;
        endeMs = Date.now() + restMs;
        status = 'laeuft';
    } else {
        status = 'offen';
    }
    anzeigen();
    leiste.zeige();
}

function start() {
    ton.entsperre();
    // leere Namen bekommen einen Platzhalter
    schritte().forEach((s, i) => {
        if (!s.name.trim()) s.name = `Schritt ${i + 1}`;
    });
    speichern();
    if (panel.offen()) panel.schliesse();
    if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
    starteSchritt(0);
}

function naechster() {
    if (status === 'bereit' || status === 'fertig') return;
    if (index < schritte().length - 1) starteSchritt(index + 1);
    else beendeAblauf();
}

/** Einen Schritt zurück; im ersten Schritt beginnt dieser von vorn, nach dem Ende geht es zum letzten. */
function vorheriger() {
    if (status === 'bereit') return;
    if (status === 'fertig') starteSchritt(schritte().length - 1);
    else starteSchritt(Math.max(0, index - 1));
}

function beendeAblauf() {
    status = 'fertig';
    fertigSeit = Date.now();
    anzeigen();
}

function weiterOderPause() {
    if (status === 'laeuft') {
        restMs = Math.max(0, endeMs - Date.now());
        status = 'pause';
    } else if (status === 'pause') {
        endeMs = Date.now() + restMs;
        status = 'laeuft';
    } else if (status === 'wartet' || status === 'offen') {
        naechster();
        return;
    } else if (status === 'fertig') {
        starteSchritt(0);
        return;
    }
    anzeigen();
}

function plusMinute() {
    if (status === 'laeuft' || status === 'pause') {
        dauerMs += 60_000;
        restMs = (status === 'laeuft' ? endeMs - Date.now() : restMs) + 60_000;
        endeMs = Date.now() + restMs;
        anzeigen();
    } else if (status === 'wartet') {
        // abgelaufenen Schritt um eine Minute verlängern
        dauerMs = 60_000;
        restMs = 60_000;
        endeMs = Date.now() + restMs;
        status = 'laeuft';
        anzeigen();
    }
}

function zurPlanung() {
    status = 'bereit';
    leiste.verberge();
    anzeigen();
}

function restText(ms) {
    if (einstellungen.sekunden) return formatiereDauer(Math.ceil(ms / 1000));
    return ms > 60_000 ? `noch ${Math.ceil(ms / 60_000)} min` : 'noch < 1 min';
}

function setzeKnopf(k, icon, text) {
    const symbol = k.querySelector('i');
    const beschriftung = k.querySelector('span');
    if (symbol.className !== icon) symbol.className = icon;
    if (beschriftung.textContent !== text) beschriftung.textContent = text;
}

function zeichneFortschritt() {
    const anzahl = schritte().length;
    if (el.fortschritt.children.length !== anzahl) {
        el.fortschritt.replaceChildren(...schritte().map(() => document.createElement('li')));
    }
    [...el.fortschritt.children].forEach((punkt, i) => {
        punkt.className = 'abl-punkt';
        punkt.style.setProperty('--farbe', farbe(i));
        const zustand = status === 'fertig' || i < index ? 'erledigt' : i === index ? 'aktuell' : 'kommt';
        punkt.dataset.zustand = zustand;
        const text = `${zustand === 'erledigt' ? '✓ ' : ''}${schritte()[i].name}`;
        if (punkt.textContent !== text) punkt.textContent = text;
    });
}

function anzeigen() {
    const body = document.body.classList;
    body.toggle('tmr-fokus', status !== 'bereit');
    body.toggle('tmr-laeuft', status === 'laeuft' || status === 'offen');
    body.toggle('tmr-leiste-dauerhaft', ['pause', 'wartet', 'fertig'].includes(status));
    el.wurzel.dataset.status = status;
    if (status === 'bereit') {
        document.title = 'Arbeitsphasen · Klassenraum-Tools';
        return;
    }

    const schritt = schritte()[index];
    const naechsterSchritt = schritte()[index + 1];
    let name = schritt.name;
    let zeit = '';
    let danach = '';
    let info = '';
    let anteil = 0;

    if (status === 'laeuft' || status === 'pause') {
        zeit = status === 'pause' ? `${restText(restMs)} · Pause` : restText(restMs);
        anteil = restMs / dauerMs;
        if (status === 'laeuft') info = `Ende um ${uhrzeit.format(new Date(endeMs))} Uhr`;
    } else if (status === 'wartet') {
        zeit = 'Zeit ist um!';
    } else if (status === 'offen') {
        zeit = naechsterSchritt ? 'ohne Zeitbegrenzung' : '';
    } else if (status === 'fertig') {
        name = 'Geschafft!';
        zeit = `alle ${schritte().length} Schritte erledigt`;
    }

    if (status === 'fertig') danach = '';
    else if (naechsterSchritt) {
        const minuten = naechsterSchritt.minuten > 0 ? ` · ${naechsterSchritt.minuten} min` : '';
        danach = `${status === 'wartet' ? 'Als Nächstes' : 'Danach'}: <b></b>${minuten}`;
    } else danach = 'Letzter Schritt';

    if (el.name.textContent !== name) el.name.textContent = name;
    if (el.zeit.textContent !== zeit) el.zeit.textContent = zeit;
    if (el.danach.dataset.text !== danach + (naechsterSchritt?.name ?? '')) {
        el.danach.innerHTML = danach;
        el.danach.querySelector('b')?.append(naechsterSchritt?.name ?? '');
        el.danach.dataset.text = danach + (naechsterSchritt?.name ?? '');
    }
    if (el.info.textContent !== info) el.info.textContent = info;
    anzeige.zeige(anteil, status === 'laeuft');
    zeichneFortschritt();

    if (status === 'laeuft') setzeKnopf(el.weiter, 'fas fa-pause', 'Pause');
    else if (status === 'pause') setzeKnopf(el.weiter, 'fas fa-play', 'Weiter');
    else if (status === 'wartet') setzeKnopf(el.weiter, 'fas fa-play', naechsterSchritt ? `Weiter: ${naechsterSchritt.name}` : 'Weiter');
    else if (status === 'offen') setzeKnopf(el.weiter, 'fas fa-step-forward', naechsterSchritt ? `Weiter: ${naechsterSchritt.name}` : 'Abschließen');
    else setzeKnopf(el.weiter, 'fas fa-redo', 'Neu starten');
    $('#abl-naechster').hidden = status === 'fertig' || status === 'wartet' || status === 'offen';
    $('#abl-plus').hidden = !['laeuft', 'pause', 'wartet'].includes(status);

    document.title = `${name}${zeit ? ` · ${zeit}` : ''} · Arbeitsphasen`;
}

function takt() {
    if (status === 'laeuft') {
        restMs = endeMs - Date.now();
        if (restMs <= 0) {
            restMs = 0;
            gong();
            if (index < schritte().length - 1) {
                if (einstellungen.automatisch) starteSchritt(index + 1);
                else status = 'wartet';
            } else {
                beendeAblauf();
            }
        }
    }
    if (status !== 'bereit') anzeigen();
}

// ── Bedienung ────────────────────────────────────────────────────────────
const anwenden = () => document.body.classList.toggle('krt-ohne-uhr', !einstellungen.uhr);
bindeEinstellungen(document, einstellungen, (name) => {
    speichern();
    anwenden();
    if (name === 'gongLautstaerke' || (name === 'gong' && einstellungen.gong)) ton.gong(einstellungen.gongLautstaerke / 100);
    anzeigen();
});

const panel = initPanel($('#krt-panel'), $('#krt-einstellungen-oeffnen'));
const leiste = initFokusleiste(el.leiste, () => status !== 'bereit');
initVollbild($('#krt-vollbild'));
initVollbild($('#tmr-fokus-vollbild'));
initUhr($('#krt-uhr'));
initLeerlauf(() => (status === 'laeuft' || status === 'offen') && !document.body.classList.contains('tmr-leiste-sichtbar'), 2000);

$('#abl-start').addEventListener('click', start);
el.weiter.addEventListener('click', () => {
    weiterOderPause();
    leiste.zeige();
});
$('#abl-naechster').addEventListener('click', () => {
    gong();
    naechster();
});
$('#abl-zurueck').addEventListener('click', vorheriger);
$('#abl-plus').addEventListener('click', () => {
    plusMinute();
    leiste.zeige();
});
$('#abl-ende').addEventListener('click', zurPlanung);

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
    } else if (e.key === 'ArrowLeft') {
        vorheriger();
    } else if (taste === 'n' || e.key === 'ArrowRight') {
        if (status === 'laeuft' || status === 'pause') gong();
        naechster();
    } else if (taste === 'r') {
        zurPlanung();
        return;
    }
    leiste.zeige();
});

setInterval(takt, 200);

document.documentElement.style.setProperty('--tmr-farbe', farbe(0));
zeichneListe();
setzeDarstellung();
anwenden();
anzeigen();
