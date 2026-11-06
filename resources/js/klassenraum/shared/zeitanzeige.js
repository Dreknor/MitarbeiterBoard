/*
 * Klassenraum-Tools – Darstellungen der Restzeit (Timer und Arbeitsphasen).
 *
 * Jede Darstellung zeichnet nur den Anteil der verbleibenden Zeit (1 = voll, 0 = abgelaufen)
 * als SVG; die Zeitlogik bleibt im jeweiligen Tool. Farbe über die CSS-Variable --tmr-farbe.
 * Bewusst ohne Animationen, die ablenken (nur die Kerzenflamme bewegt sich sehr ruhig).
 *
 *   scheibe – Zeitscheibe (voller Kreis = eingestellte Dauer)
 *   sanduhr – Sand rinnt von oben nach unten
 *   kerze   – Kerze brennt herunter
 *   raupe   – Raupe frisst sich an einem Zweig entlang durch die Blätter
 */

import {klemme} from './hilfen';

export const DARSTELLUNGEN = {
    scheibe: {titel: 'Zeitscheibe', icon: 'fas fa-chart-pie'},
    sanduhr: {titel: 'Sanduhr', icon: 'fas fa-hourglass-half'},
    kerze: {titel: 'Kerze', icon: 'fas fa-fire'},
    raupe: {titel: 'Raupe', icon: 'fas fa-bug'},
};

const SVG = 'http://www.w3.org/2000/svg';

function svg(name, attribute = {}, eltern = null) {
    const element = document.createElementNS(SVG, name);
    for (const [schluessel, wert] of Object.entries(attribute)) element.setAttribute(schluessel, String(wert));
    if (eltern) eltern.appendChild(element);
    return element;
}

let zaehler = 0;

// ── Zeitscheibe ──────────────────────────────────────────────────────────
function scheibe(wurzel) {
    svg('circle', {cx: 100, cy: 100, r: 96, class: 'tmr-ziffernblatt'}, wurzel);
    const striche = svg('g', {}, wurzel);
    for (let i = 0; i < 60; i++) {
        const w = (i / 60) * Math.PI * 2;
        const lang = i % 5 === 0;
        const r1 = lang ? 82 : 88;
        svg('line', {
            x1: 100 + Math.sin(w) * r1, y1: 100 - Math.cos(w) * r1,
            x2: 100 + Math.sin(w) * 93, y2: 100 - Math.cos(w) * 93,
            class: lang ? 'tmr-strich tmr-strich-lang' : 'tmr-strich',
        }, striche);
    }
    const keil = svg('path', {class: 'tmr-keil'}, wurzel);
    svg('circle', {cx: 100, cy: 100, r: 5, class: 'tmr-nabe'}, wurzel);

    return (anteil) => {
        const r = 78;
        let d = '';
        if (anteil >= 0.9999) d = `M100,${100 - r} A${r},${r} 0 1,1 99.99,${100 - r} Z`;
        else if (anteil > 0) {
            const w = anteil * Math.PI * 2;
            d = `M100,100 L100,${100 - r} A${r},${r} 0 ${anteil > 0.5 ? 1 : 0},1 ${(100 + Math.sin(w) * r).toFixed(2)},${(100 - Math.cos(w) * r).toFixed(2)} Z`;
        }
        keil.setAttribute('d', d);
    };
}

// ── Sanduhr ──────────────────────────────────────────────────────────────
function sanduhr(wurzel) {
    const id = `tmr-glas-${++zaehler}`;
    const glas = 'M58,26 H142 C142,62 106,84 103,100 C106,116 142,138 142,174 H58 C58,138 94,116 97,100 C94,84 58,62 58,26 Z';
    const defs = svg('defs', {}, wurzel);
    const clip = svg('clipPath', {id}, defs);
    svg('path', {d: glas}, clip);

    // Holzrahmen
    svg('rect', {x: 44, y: 14, width: 112, height: 12, rx: 5, class: 'tmr-holz'}, wurzel);
    svg('rect', {x: 44, y: 174, width: 112, height: 12, rx: 5, class: 'tmr-holz'}, wurzel);
    svg('rect', {x: 48, y: 24, width: 6, height: 152, rx: 3, class: 'tmr-holz'}, wurzel);
    svg('rect', {x: 146, y: 24, width: 6, height: 152, rx: 3, class: 'tmr-holz'}, wurzel);
    svg('path', {d: glas, class: 'tmr-glas'}, wurzel);

    const sand = svg('g', {'clip-path': `url(#${id})`}, wurzel);
    const oben = svg('rect', {x: 50, width: 100, class: 'tmr-farbig'}, sand);
    const strahl = svg('line', {x1: 100, x2: 100, y1: 100, class: 'tmr-sandstrahl'}, sand);
    const unten = svg('path', {class: 'tmr-farbig'}, sand);
    svg('path', {d: glas, class: 'tmr-glas-rand'}, wurzel);

    return (anteil, laeuft) => {
        // Höhe nach Volumen grob angenähert (oberer Kolben wird nach unten schmaler)
        const hoeheOben = 70 * Math.sqrt(anteil);
        oben.setAttribute('y', String(100 - hoeheOben));
        oben.setAttribute('height', String(hoeheOben));
        const hoeheUnten = 66 * (1 - Math.sqrt(1 - (1 - anteil)));
        const boden = 174;
        const kuppe = boden - Math.max(2, 66 * (1 - anteil) * 0.85 + hoeheUnten * 0.15);
        unten.setAttribute('d', `M50,${boden} L50,${kuppe + 10} Q100,${kuppe - 10} 150,${kuppe + 10} L150,${boden} Z`);
        strahl.setAttribute('y2', String(kuppe));
        strahl.style.display = laeuft && anteil > 0.002 && anteil < 0.999 ? '' : 'none';
    };
}

// ── Kerze ────────────────────────────────────────────────────────────────
function kerze(wurzel) {
    const id = `tmr-schein-${++zaehler}`;
    const defs = svg('defs', {}, wurzel);
    const verlauf = svg('radialGradient', {id}, defs);
    svg('stop', {offset: '0%', 'stop-color': '#fde68a', 'stop-opacity': 0.75}, verlauf);
    svg('stop', {offset: '100%', 'stop-color': '#fde68a', 'stop-opacity': 0}, verlauf);

    svg('ellipse', {cx: 100, cy: 184, rx: 58, ry: 9, class: 'tmr-teller'}, wurzel);
    const schein = svg('circle', {r: 38, fill: `url(#${id})`}, wurzel);
    const koerper = svg('rect', {x: 76, width: 48, rx: 6, class: 'tmr-farbig'}, wurzel);
    const glanz = svg('rect', {x: 82, width: 8, rx: 4, class: 'tmr-glanz'}, wurzel);
    const oberkante = svg('ellipse', {cx: 100, rx: 24, ry: 4.5, class: 'tmr-kerze-oben'}, wurzel);
    const docht = svg('line', {x1: 100, x2: 100, class: 'tmr-docht'}, wurzel);
    const flamme = svg('g', {class: 'tmr-flamme'}, wurzel);
    const aussen = svg('path', {class: 'tmr-flamme-aussen'}, flamme);
    const innen = svg('path', {class: 'tmr-flamme-innen'}, flamme);
    const rauch = svg('path', {class: 'tmr-rauch', d: ''}, wurzel);

    return (anteil) => {
        const hoehe = 14 + 136 * anteil;
        const oben = 182 - hoehe;
        koerper.setAttribute('y', String(oben));
        koerper.setAttribute('height', String(hoehe));
        glanz.setAttribute('y', String(oben + 8));
        glanz.setAttribute('height', String(Math.max(0, hoehe - 16)));
        oberkante.setAttribute('cy', String(oben + 1));
        docht.setAttribute('y1', String(oben));
        docht.setAttribute('y2', String(oben - 9));
        const y = oben - 8;
        aussen.setAttribute('d', `M100,${y - 30} C113,${y - 14} 111,${y - 2} 100,${y + 2} C89,${y - 2} 87,${y - 14} 100,${y - 30} Z`);
        innen.setAttribute('d', `M100,${y - 16} C106,${y - 8} 105,${y - 1} 100,${y + 1} C95,${y - 1} 94,${y - 8} 100,${y - 16} Z`);
        flamme.style.transformOrigin = `100px ${y + 2}px`;
        schein.setAttribute('cx', '100');
        schein.setAttribute('cy', String(y - 12));
        const brennt = anteil > 0;
        flamme.style.display = brennt ? '' : 'none';
        schein.style.display = brennt ? '' : 'none';
        rauch.setAttribute('d', brennt ? '' : `M100,${oben - 9} c-6,-10 6,-16 0,-26 c-6,-10 6,-16 0,-24`);
    };
}

// ── Raupe ────────────────────────────────────────────────────────────────
function raupe(wurzel) {
    const blaetterAnzahl = 8;
    const zweigY = 140;
    const xBlatt = (i) => 34 + i * (140 / (blaetterAnzahl - 1));

    svg('path', {d: `M8,${zweigY + 4} Q100,${zweigY - 6} 192,${zweigY + 2}`, class: 'tmr-zweig'}, wurzel);
    const blaetter = Array.from({length: blaetterAnzahl}, (_, i) => {
        const g = svg('g', {}, wurzel);
        const neigung = i % 2 === 0 ? -14 : 14;
        svg('line', {x1: xBlatt(i), y1: zweigY, x2: xBlatt(i), y2: zweigY - 16, class: 'tmr-stiel'}, g);
        svg('ellipse', {cx: xBlatt(i), cy: zweigY - 46, rx: 13, ry: 31, class: 'tmr-blatt', transform: `rotate(${neigung} ${xBlatt(i)} ${zweigY - 16})`}, g);
        svg('line', {x1: xBlatt(i), y1: zweigY - 18, x2: xBlatt(i), y2: zweigY - 72, class: 'tmr-blattader', transform: `rotate(${neigung} ${xBlatt(i)} ${zweigY - 16})`}, g);
        return g;
    });
    const koerper = svg('g', {}, wurzel);
    const kopf = svg('g', {}, wurzel);
    svg('circle', {cx: 0, cy: 0, r: 11, class: 'tmr-raupe-kopf'}, kopf);
    svg('circle', {cx: 4, cy: -3, r: 2.6, fill: '#fff'}, kopf);
    svg('circle', {cx: 4.8, cy: -3, r: 1.4, fill: '#0f172a'}, kopf);
    svg('path', {d: 'M-2,-10 q-2,-9 -7,-11 M4,-10 q2,-9 7,-11', class: 'tmr-fuehler'}, kopf);
    const falter = svg('g', {class: 'tmr-falter'}, wurzel);
    svg('ellipse', {cx: -14, cy: -8, rx: 15, ry: 11, class: 'tmr-farbig', transform: 'rotate(-20 -14 -8)'}, falter);
    svg('ellipse', {cx: 14, cy: -8, rx: 15, ry: 11, class: 'tmr-farbig', transform: 'rotate(20 14 -8)'}, falter);
    svg('ellipse', {cx: -10, cy: 10, rx: 9, ry: 8, class: 'tmr-farbig'}, falter);
    svg('ellipse', {cx: 10, cy: 10, rx: 9, ry: 8, class: 'tmr-farbig'}, falter);
    svg('ellipse', {cx: 0, cy: 0, rx: 3, ry: 15, fill: '#334155'}, falter);

    let letzteSegmente = -1;
    return (anteil) => {
        const gefressen = (1 - anteil) * blaetterAnzahl;
        blaetter.forEach((blatt, i) => {
            // Blatt i ist ab „gefressen > i“ angeknabbert, ab „gefressen ≥ i + 1“ ganz weg
            const rest = klemme(i + 1 - gefressen, 0, 1);
            blatt.style.opacity = rest <= 0.01 ? '0' : '1';
            const x = xBlatt(i);
            blatt.setAttribute('transform', `translate(${x} ${zweigY}) scale(${0.25 + 0.75 * rest}) translate(${-x} ${-zweigY})`);
        });

        const fertig = anteil <= 0;
        const kopfX = Math.min(184, xBlatt(0) - 14 + gefressen * (140 / (blaetterAnzahl - 1)));
        kopf.setAttribute('transform', `translate(${kopfX} ${zweigY - 10})`);
        kopf.style.display = fertig ? 'none' : '';
        koerper.style.display = fertig ? 'none' : '';
        falter.setAttribute('transform', `translate(100 ${zweigY - 60}) scale(1.6)`);
        falter.style.display = fertig ? '' : 'none';

        // Die Raupe wird mit jedem Blatt länger
        const segmente = 3 + Math.floor(gefressen);
        if (segmente !== letzteSegmente) {
            koerper.replaceChildren();
            for (let i = segmente; i >= 1; i--) {
                svg('circle', {cx: 0, cy: 0, r: 9, class: i % 2 ? 'tmr-farbig' : 'tmr-farbig tmr-raupe-hell', 'data-i': i}, koerper);
            }
            letzteSegmente = segmente;
        }
        [...koerper.children].forEach((segment) => {
            const i = Number(segment.dataset.i);
            const x = kopfX - i * 12;
            segment.setAttribute('cx', String(x));
            segment.setAttribute('cy', String(zweigY - 9 - (i % 2 ? 2 : 0)));
            segment.style.display = x < 4 ? 'none' : '';
        });
    };
}

const BAUER = {scheibe, sanduhr, kerze, raupe};

/**
 * @param {HTMLElement} behaelter  Element, in das die Darstellung gezeichnet wird
 */
export function erzeugeZeitanzeige(behaelter) {
    let art = null;
    let zeichne = () => {};
    let letzterAnteil = 1;
    let letztesLaufen = false;

    return {
        setzeArt(neueArt) {
            const gewaehlt = BAUER[neueArt] ? neueArt : 'scheibe';
            if (gewaehlt === art) return;
            art = gewaehlt;
            behaelter.replaceChildren();
            behaelter.dataset.art = art;
            const wurzel = svg('svg', {viewBox: '0 0 200 200', class: 'tmr-svg', 'aria-hidden': 'true'});
            behaelter.appendChild(wurzel);
            zeichne = BAUER[art](wurzel);
            zeichne(letzterAnteil, letztesLaufen);
        },
        zeige(anteil, laeuft = false) {
            letzterAnteil = klemme(anteil, 0, 1);
            letztesLaufen = laeuft;
            zeichne(letzterAnteil, laeuft);
        },
    };
}
