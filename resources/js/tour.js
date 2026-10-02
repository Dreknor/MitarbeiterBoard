/*
 * Geführte Touren – wiederverwendbar für alle Bereiche (Vite-Entrypoint)
 *
 * Verwendung in Blade (lädt dieses Skript selbst nach):
 *
 *   <x-tour id="urlaub" :steps="[
 *       ['title' => 'Willkommen', 'text' => 'Ohne Ziel = Dialog in der Bildschirmmitte.'],
 *       ['target' => 'urlaub-antrag', 'title' => 'Urlaub beantragen', 'text' => '…'],
 *       ['selector' => '#entscheiden', 'title' => '…', 'text' => '…'],
 *       ['target' => 'tab-urlaub', 'activate' => '[data-tab=urlaub]', 'title' => '…', 'text' => '…'],
 *   ]" :next="['label' => 'Weiter: …', 'url' => route('…', ['tour' => 'naechste-tour'])]" />
 *
 *   <button type="button" data-tour-start="urlaub">Tour</button>
 *
 * Schritt-Optionen:
 *   target    Name aus data-tour="…" am Element (hervorgehoben, erstes sichtbares Element)
 *   selector  alternativ beliebiger CSS-Selektor
 *   activate  CSS-Selektor, der vor dem Schritt angeklickt wird (z. B. Tab-Reiter)
 *   title / text  Klartext; Absätze im Text durch Leerzeile trennen
 * Schritte, deren Ziel fehlt oder unsichtbar ist (z. B. fehlende Berechtigung), werden übersprungen.
 *
 * Eine Tour startet automatisch beim ersten Besuch (pro Person, Browser und Version) oder
 * über ?tour=<id> in der URL. Nach dem ersten Autostart erscheint sie nur noch über den
 * Tour-Knopf. Gesehen-Status liegt nur im localStorage (Komfortfunktion).
 */
import '../css/tour.css';

const PAD = 6;
const GAP = 12;
const MOBIL = 640;

const storage = {
    get(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } },
    set(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* privat/gesperrt */ } },
};

const warte = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const frame = () => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

function sichtbar(el) {
    if (!el || !el.getClientRects().length) return false;
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0 && getComputedStyle(el).visibility !== 'hidden';
}

function findeZiel(step) {
    const selector = step.selector ?? (step.target ? `[data-tour="${CSS.escape(step.target)}"]` : null);
    if (!selector) return null;
    return [...document.querySelectorAll(selector)].find(sichtbar) ?? false;
}

function el(tag, klasse, text) {
    const node = document.createElement(tag);
    if (klasse) node.className = klasse;
    if (text !== undefined) node.textContent = text;
    return node;
}

class Tour {
    constructor(config) {
        this.config = config;
        this.steps = config.steps ?? [];
        this.index = -1;
        this.ziel = null;
        this.onKey = this.onKey.bind(this);
        this.reposition = () => { if (!this.raf) this.raf = requestAnimationFrame(() => { this.raf = null; this.positionieren(); }); };
    }

    get key() {
        return `mb-tour:${this.config.user ?? 'gast'}:${this.config.id}:v${this.config.version ?? 1}`;
    }

    get gesehen() {
        return storage.get(this.key) !== null;
    }

    start() {
        if (Tour.aktiv) Tour.aktiv.beenden(false);
        Tour.aktiv = this;
        this.zuvorFokus = document.activeElement;
        this.aufbauen();
        document.addEventListener('keydown', this.onKey);
        window.addEventListener('resize', this.reposition);
        document.addEventListener('scroll', this.reposition, true);
        this.gehe(0, 1);
    }

    aufbauen() {
        this.root = el('div', 'mbt-root');
        this.blocker = el('div', 'mbt-blocker');
        this.spot = el('div', 'mbt-spot');
        this.box = el('div', 'mbt-box');
        this.box.setAttribute('role', 'dialog');
        this.box.setAttribute('aria-modal', 'true');
        this.box.setAttribute('aria-labelledby', 'mbt-title');
        this.box.setAttribute('aria-describedby', 'mbt-text');

        const kopf = el('div', 'mbt-head');
        this.zaehler = el('span', 'mbt-count');
        const schliessen = el('button', 'mbt-close', '×');
        schliessen.type = 'button';
        schliessen.title = 'Tour beenden';
        schliessen.setAttribute('aria-label', 'Tour beenden');
        schliessen.addEventListener('click', () => this.beenden(true));
        kopf.append(this.zaehler, schliessen);

        this.titel = el('h2', 'mbt-title');
        this.titel.id = 'mbt-title';
        this.text = el('div', 'mbt-text');
        this.text.id = 'mbt-text';

        const fuss = el('div', 'mbt-foot');
        this.punkte = el('div', 'mbt-dots');
        this.zurueck = el('button', 'mbt-btn mbt-btn-ghost', 'Zurück');
        this.zurueck.type = 'button';
        this.zurueck.addEventListener('click', () => this.gehe(this.index - 1, -1));
        this.weiter = el('button', 'mbt-btn mbt-btn-primary');
        this.weiter.type = 'button';
        this.weiter.addEventListener('click', () => this.vor());
        this.naechsteSeite = el('a', 'mbt-btn mbt-btn-primary');
        this.naechsteSeite.addEventListener('click', () => this.merken());
        const aktionen = el('div', 'mbt-actions');
        aktionen.append(this.zurueck, this.weiter, this.naechsteSeite);
        fuss.append(this.punkte, aktionen);

        this.box.append(kopf, this.titel, this.text, fuss);
        this.root.append(this.blocker, this.spot, this.box);
        document.body.append(this.root);
    }

    vor() {
        if (this.istLetzter(this.index)) this.beenden(true);
        else this.gehe(this.index + 1, 1);
    }

    istLetzter(index) {
        return !this.steps.slice(index + 1).some((step) => findeZiel(step) !== false || step.activate);
    }

    async gehe(index, richtung) {
        if (index < 0) return;
        if (index >= this.steps.length) { this.beenden(true); return; }
        const step = this.steps[index];
        this.laeuft = (this.laeuft ?? 0) + 1;
        const lauf = this.laeuft;

        if (step.activate) {
            document.querySelector(step.activate)?.click();
            await frame();
            await warte(30);
            if (lauf !== this.laeuft) return;
        }

        const ziel = findeZiel(step);
        if (ziel === false) { this.gehe(index + richtung, richtung || 1); return; }

        this.index = index;
        this.ziel = ziel;
        this.anzeigen(step);
        if (ziel) {
            ziel.style.scrollMarginTop = '84px';
            ziel.style.scrollMarginBottom = window.innerWidth < MOBIL ? '45vh' : '24px';
            const hoch = ziel.getBoundingClientRect().height > window.innerHeight * 0.6;
            ziel.scrollIntoView({ behavior: 'smooth', block: hoch || window.innerWidth < MOBIL ? 'start' : 'center' });
        }
        this.positionieren();
    }

    anzeigen(step) {
        const nummer = this.steps.slice(0, this.index + 1).filter((s) => findeZiel(s) !== false || s.activate).length;
        const gesamt = this.steps.filter((s) => findeZiel(s) !== false || s.activate).length;
        this.zaehler.textContent = `${nummer} von ${gesamt}`;
        this.punkte.replaceChildren(...Array.from({ length: gesamt }, (_, i) => el('span', i === nummer - 1 ? 'is-active' : '')));

        this.titel.textContent = step.title ?? '';
        this.text.replaceChildren(...String(step.text ?? '').split(/\n\s*\n/).map((absatz) => el('p', null, absatz.trim())));

        const letzter = this.istLetzter(this.index);
        const next = this.config.next;
        this.zurueck.hidden = nummer <= 1;
        this.weiter.textContent = letzter ? (next ? 'Beenden' : 'Fertig') : 'Weiter';
        this.weiter.className = `mbt-btn ${letzter && next ? 'mbt-btn-ghost' : 'mbt-btn-primary'}`;
        this.naechsteSeite.hidden = !(letzter && next);
        if (letzter && next) {
            this.naechsteSeite.textContent = next.label ?? 'Weiter';
            this.naechsteSeite.href = next.url;
        }
        this.box.classList.toggle('is-center', !this.ziel);
        this.spot.hidden = !this.ziel;
        (letzter && next ? this.naechsteSeite : this.weiter).focus({ preventScroll: true });
    }

    positionieren() {
        if (!this.root) return;
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const mobil = vw < MOBIL;
        this.box.classList.toggle('is-sheet', mobil && !!this.ziel);

        if (!this.ziel) {
            Object.assign(this.box.style, { top: '', left: '' });
            return;
        }

        const r = this.ziel.getBoundingClientRect();
        const top = Math.max(r.top - PAD, -PAD);
        const bottom = Math.min(r.bottom + PAD, vh + PAD);
        Object.assign(this.spot.style, {
            top: `${top}px`,
            left: `${Math.max(r.left - PAD, 0)}px`,
            width: `${Math.min(r.width + PAD * 2, vw)}px`,
            height: `${Math.max(bottom - top, 0)}px`,
        });

        if (mobil) {
            Object.assign(this.box.style, { top: '', left: '' });
            return;
        }

        const bw = this.box.offsetWidth;
        const bh = this.box.offsetHeight;
        let y;
        if (bottom + GAP + bh <= vh - 8) y = bottom + GAP;
        else if (top - GAP - bh >= 8) y = top - GAP - bh;
        else y = vh - bh - 16;
        const x = Math.min(Math.max(r.left, 16), vw - bw - 16);
        Object.assign(this.box.style, { top: `${Math.max(y, 8)}px`, left: `${Math.max(x, 16)}px` });
    }

    onKey(event) {
        if (event.key === 'Escape') { event.preventDefault(); this.beenden(true); }
        else if (event.key === 'ArrowRight') { event.preventDefault(); this.vor(); }
        else if (event.key === 'ArrowLeft') { event.preventDefault(); this.gehe(this.index - 1, -1); }
        else if (event.key === 'Tab') {
            // Fokus im Dialog halten
            const fokussierbar = [...this.box.querySelectorAll('button, a[href]')].filter((n) => !n.hidden);
            const erster = fokussierbar[0];
            const letzter = fokussierbar[fokussierbar.length - 1];
            if (event.shiftKey && document.activeElement === erster) { event.preventDefault(); letzter.focus(); }
            else if (!event.shiftKey && document.activeElement === letzter) { event.preventDefault(); erster.focus(); }
        }
    }

    merken() {
        storage.set(this.key, new Date().toISOString());
    }

    beenden(merken) {
        if (merken) this.merken();
        this.laeuft = (this.laeuft ?? 0) + 1;
        document.removeEventListener('keydown', this.onKey);
        window.removeEventListener('resize', this.reposition);
        document.removeEventListener('scroll', this.reposition, true);
        this.root?.remove();
        this.root = null;
        if (Tour.aktiv === this) Tour.aktiv = null;
        this.zuvorFokus?.focus?.({ preventScroll: true });
    }
}

function touren() {
    const liste = new Map();
    document.querySelectorAll('script[type="application/json"][data-tour-config]').forEach((node) => {
        try {
            const config = JSON.parse(node.textContent);
            if (config?.id && Array.isArray(config.steps)) liste.set(config.id, new Tour(config));
        } catch (e) { /* ungültige Konfiguration ignorieren */ }
    });
    return liste;
}

async function init() {
    const liste = touren();
    if (!liste.size) return;

    document.addEventListener('click', (event) => {
        const knopf = event.target.closest('[data-tour-start]');
        const tour = knopf && liste.get(knopf.dataset.tourStart);
        if (!tour) return;
        event.preventDefault();
        tour.start();
    });

    // Alpine (sidebar.js) startet erst bei window.load – danach sind x-show/x-cloak aufgelöst
    await warte(400);

    const url = new URL(window.location.href);
    const angefordert = url.searchParams.get('tour');
    if (angefordert) {
        url.searchParams.delete('tour');
        history.replaceState(history.state, '', url.toString());
    }
    const tour = liste.get(angefordert);
    if (tour) {
        tour.start();
        return;
    }

    const autoTour = [...liste.values()].find((t) => t.config.auto !== false && !t.gesehen);
    if (!autoTour) return;

    autoTour.merken();
    autoTour.start();
}

window.MbTour = { start: (id) => touren().get(id)?.start() };

if (document.readyState === 'complete') init();
else window.addEventListener('load', init);
