/*
 * Klassenraum-Tools – Einstellungen pro Tool im Browser merken.
 *
 * Nur Bedien-Komfort (Grenze, Anzeige, letzte Timer-Dauer). Der Speicher kann fehlen
 * (privates Fenster, gesperrte Website-Daten) – dann gelten einfach die Standardwerte.
 */

const PRAEFIX = 'klassenraum-tools.';

export function ladeEinstellungen(schluessel, standard) {
    let gespeichert = {};
    try {
        const roh = window.localStorage.getItem(PRAEFIX + schluessel);
        gespeichert = roh ? JSON.parse(roh) : {};
    } catch (_) {
        gespeichert = {};
    }

    const ergebnis = {...standard};
    for (const [name, wert] of Object.entries(gespeichert || {})) {
        // Nur bekannte Schlüssel mit passendem Typ übernehmen
        if (name in standard && typeof wert === typeof standard[name]) {
            ergebnis[name] = wert;
        }
    }
    return ergebnis;
}

export function speichereEinstellungen(schluessel, werte) {
    try {
        window.localStorage.setItem(PRAEFIX + schluessel, JSON.stringify(werte));
    } catch (_) {
        // Speicher nicht verfügbar – Einstellungen gelten nur bis zum Neuladen.
    }
}

/**
 * Verbindet Formularfelder mit [data-einstellung="name"] mit dem Einstellungsobjekt.
 * Anzeigen mit [data-anzeige="name"] zeigen den aktuellen Wert (Format über data-format).
 */
export function bindeEinstellungen(wurzel, werte, beiAenderung) {
    const formate = {
        sekunden: (v) => `${Number(v).toLocaleString('de-DE', {maximumFractionDigits: 1})} s`,
        prozent: (v) => `${v} %`,
        zahl: (v) => String(v),
    };

    const zeigeAn = (name) => {
        wurzel.querySelectorAll(`[data-anzeige="${name}"]`).forEach((el) => {
            const format = formate[el.dataset.format] || formate.zahl;
            el.textContent = format(werte[name]);
        });
    };

    const felder = [...wurzel.querySelectorAll('[data-einstellung]')];

    const uebernehmeWerte = () => {
        felder.forEach((feld) => {
            const name = feld.dataset.einstellung;
            if (feld.type === 'checkbox') {
                feld.checked = Boolean(werte[name]);
            } else {
                feld.value = werte[name];
            }
            zeigeAn(name);
        });
    };

    felder.forEach((feld) => {
        const name = feld.dataset.einstellung;
        feld.addEventListener(feld.type === 'checkbox' ? 'change' : 'input', () => {
            werte[name] = feld.type === 'checkbox' ? feld.checked : Number(feld.value);
            zeigeAn(name);
            beiAenderung(name);
        });
    });

    uebernehmeWerte();

    return {aktualisiere: uebernehmeWerte};
}
