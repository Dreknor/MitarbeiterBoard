/*
 * Angepasste Arbeitsblätter – Vite-Entrypoint
 *
 * - Alpine "abErfassung": Kamera-Seiten aufnehmen, vier Ecken ziehen, perspektivisch entzerren
 *   (eigene Homographie im Canvas, kein OpenCV), Upload als JPEG; alternativ Datei-Upload.
 * - Alpine "abEditor": Prüf-Editor – Original (Bilder bzw. PDF über pdfjs-dist), Bausteinliste
 *   (SortableJS, Typ ändern, Lücken, Teilaufgaben), Abbildungen auf dem Original ausschneiden,
 *   Live-Vorschau (gleiche Blade-Vorlage wie das PDF), Speichern mit `version` (409-Dialog), Freigabe.
 * - Alpine "abProfile": Sehprofile je Kind und Profil-Vorlagen mit Live-Beispielblatt.
 * - Alpine "abEinstellungen": Vorschau des Ablagepfads, Verbindungen prüfen.
 *
 * Alpine wird von sidebar.js global bereitgestellt und erst bei window.load gestartet.
 */
import Sortable from 'sortablejs';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

// Datendateien von pdf.js (siehe pdfjsDaten() in vite.config.js): Build → /build/pdfjs/, Dev-Server → node_modules
const PDFJS_DATEN = import.meta.env.DEV ? new URL('/node_modules/pdfjs-dist/', import.meta.url).href : new URL('../pdfjs/', import.meta.url).href;
const pdfQuelle = (url) => ({
    url,
    withCredentials: true,
    wasmUrl: PDFJS_DATEN + 'wasm/',
    iccUrl: PDFJS_DATEN + 'iccs/',
    standardFontDataUrl: PDFJS_DATEN + 'standard_fonts/',
});

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const kopie = (wert) => JSON.parse(JSON.stringify(wert ?? null));
const neueId = () => 'n' + Date.now().toString(36).slice(-5) + Math.random().toString(36).slice(2, 6);

async function anfrage(url, { methode = 'GET', daten = null, formular = null, text = false } = {}) {
    const headers = { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', Accept: text ? 'text/html' : 'application/json' };
    let body = null;
    if (formular) {
        body = formular;
    } else if (daten !== null) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(daten);
    }
    const antwort = await fetch(url, { method: methode, headers, body, credentials: 'same-origin' });
    if (text && antwort.ok) {
        return { status: antwort.status, ok: true, text: await antwort.text() };
    }
    let json = {};
    try { json = await antwort.json(); } catch (e) { /* leer */ }
    if (!antwort.ok && antwort.status !== 409) {
        const fehler = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Fehler ' + antwort.status);
        const err = new Error(fehler);
        err.status = antwort.status;
        err.daten = json;
        throw err;
    }
    return { status: antwort.status, ok: antwort.ok, ...json };
}

function groesse(bytes) {
    if (bytes < 1024 * 1024) return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
}

/** Sortable: DOM-Verschiebung zurücknehmen (Alpine rendert neu) und Indizes melden. */
function sortierbar(el, optionen, nachher) {
    return new Sortable(el, {
        animation: 150,
        draggable: '.ab-baustein',
        ghostClass: 'ab-sortable-ghost',
        filter: 'input, textarea, select',
        preventOnFilter: false,
        ...optionen,
        onEnd(evt) {
            const { oldDraggableIndex: alt, newDraggableIndex: neu, item, from } = evt;
            if (alt === neu || alt === undefined) return;
            const andere = [...from.querySelectorAll(':scope > .ab-baustein')].filter((e) => e !== item);
            from.insertBefore(item, andere[alt] || null);
            nachher(alt, neu);
        },
    });
}

/* ─────────────────────────────────────────────────────────────────────────
 * Perspektivische Entzerrung (Homographie aus vier Punktpaaren)
 * ───────────────────────────────────────────────────────────────────────── */

/** Löst A·x = b (Gauß mit Pivotsuche). */
function loesen(A, b) {
    const n = b.length;
    for (let i = 0; i < n; i++) {
        let max = i;
        for (let r = i + 1; r < n; r++) if (Math.abs(A[r][i]) > Math.abs(A[max][i])) max = r;
        [A[i], A[max]] = [A[max], A[i]];
        [b[i], b[max]] = [b[max], b[i]];
        for (let r = i + 1; r < n; r++) {
            const f = A[r][i] / A[i][i];
            for (let c = i; c < n; c++) A[r][c] -= f * A[i][c];
            b[r] -= f * b[i];
        }
    }
    const x = new Array(n).fill(0);
    for (let i = n - 1; i >= 0; i--) {
        let s = b[i];
        for (let c = i + 1; c < n; c++) s -= A[i][c] * x[c];
        x[i] = s / A[i][i];
    }
    return x;
}

/** Homographie, die Punkte `von` (Ziel-Rechteck) auf `nach` (Viereck im Foto) abbildet. */
export function homographie(von, nach) {
    const A = [];
    const b = [];
    for (let i = 0; i < 4; i++) {
        const [x, y] = von[i];
        const [u, v] = nach[i];
        A.push([x, y, 1, 0, 0, 0, -u * x, -u * y]);
        b.push(u);
        A.push([0, 0, 0, x, y, 1, -v * x, -v * y]);
        b.push(v);
    }
    const h = loesen(A, b);
    return [...h, 1];
}

const abstand = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]);

/** Entzerrt das Viereck `ecken` (Pixel, Reihenfolge oben-links, oben-rechts, unten-rechts, unten-links). */
export function entzerren(quelle, ecken, maxSeite = 2000) {
    const [ol, or, ur, ul] = ecken;
    let breite = (abstand(ol, or) + abstand(ul, ur)) / 2;
    let hoehe = (abstand(ol, ul) + abstand(or, ur)) / 2;
    const faktor = Math.min(1, maxSeite / Math.max(breite, hoehe));
    breite = Math.max(1, Math.round(breite * faktor));
    hoehe = Math.max(1, Math.round(hoehe * faktor));

    const sw = quelle.width;
    const sh = quelle.height;
    const qctx = quelle.getContext('2d', { willReadFrequently: true });
    const src = qctx.getImageData(0, 0, sw, sh).data;

    const ziel = document.createElement('canvas');
    ziel.width = breite;
    ziel.height = hoehe;
    const zctx = ziel.getContext('2d');
    const bild = zctx.createImageData(breite, hoehe);
    const dst = bild.data;
    const H = homographie([[0, 0], [breite, 0], [breite, hoehe], [0, hoehe]], ecken);

    for (let y = 0; y < hoehe; y++) {
        for (let x = 0; x < breite; x++) {
            const w = H[6] * x + H[7] * y + H[8];
            const u = (H[0] * x + H[1] * y + H[2]) / w;
            const v = (H[3] * x + H[4] * y + H[5]) / w;
            // bilineare Interpolation
            const x0 = Math.floor(u);
            const y0 = Math.floor(v);
            const o = (y * breite + x) * 4;
            if (x0 < 0 || y0 < 0 || x0 >= sw - 1 || y0 >= sh - 1) {
                dst[o] = dst[o + 1] = dst[o + 2] = 255;
                dst[o + 3] = 255;
                continue;
            }
            const fx = u - x0;
            const fy = v - y0;
            const i00 = (y0 * sw + x0) * 4;
            const i10 = i00 + 4;
            const i01 = i00 + sw * 4;
            const i11 = i01 + 4;
            for (let k = 0; k < 3; k++) {
                const oben = src[i00 + k] * (1 - fx) + src[i10 + k] * fx;
                const unten = src[i01 + k] * (1 - fx) + src[i11 + k] * fx;
                dst[o + k] = oben * (1 - fy) + unten * fy;
            }
            dst[o + 3] = 255;
        }
    }
    zctx.putImageData(bild, 0, 0);
    return ziel;
}

function bildLaden(datei) {
    return new Promise((ok, fehler) => {
        const url = URL.createObjectURL(datei);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); ok(img); };
        img.onerror = () => { URL.revokeObjectURL(url); fehler(new Error('Das Bild konnte nicht geladen werden.')); };
        img.src = url;
    });
}

/** Bild in Arbeitscanvas (längste Seite max. 2400 px). */
function alsCanvas(img, max = 2400) {
    const f = Math.min(1, max / Math.max(img.naturalWidth || img.width, img.naturalHeight || img.height));
    const c = document.createElement('canvas');
    c.width = Math.round((img.naturalWidth || img.width) * f);
    c.height = Math.round((img.naturalHeight || img.height) * f);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    return c;
}

function canvasDrehen(c) {
    const n = document.createElement('canvas');
    n.width = c.height;
    n.height = c.width;
    const ctx = n.getContext('2d');
    ctx.translate(n.width, 0);
    ctx.rotate(Math.PI / 2);
    ctx.drawImage(c, 0, 0);
    return n;
}

/** Vorschau-Seite (A4/A3 in mm) auf die Breite des iframes verkleinern. */
function einpassen(rahmen) {
    try {
        const doc = rahmen.contentDocument;
        const seite = doc?.querySelector('.ab-seite');
        if (!seite) return;
        doc.body.style.zoom = '';
        const faktor = Math.min(1, (rahmen.clientWidth - 24) / seite.offsetWidth);
        doc.body.style.zoom = String(Math.max(0.3, faktor));
    } catch (e) { /* ohne Zugriff unverändert */ }
}

const alsBlob = (canvas, typ = 'image/jpeg', qualitaet = 0.88) => new Promise((ok) => canvas.toBlob(ok, typ, qualitaet));

/**
 * Ohne Queue-Worker merkt sich der Server die Erkennung nur als Auftrag (`erkennung_wartet`); die geöffnete
 * Seite startet ihn mit einer eigenen, lange laufenden Anfrage und fragt parallel den Status ab.
 */
const angestossen = new Set();
function erkennungAnstossen(blatt) {
    if (!blatt?.erkennung_wartet || !blatt.urls?.ausfuehren || angestossen.has(blatt.id)) return;
    angestossen.add(blatt.id);
    anfrage(blatt.urls.ausfuehren, { methode: 'POST' })
        .catch(() => { /* Status kommt über die Abfrage; der Scheduler übernimmt notfalls */ })
        .finally(() => angestossen.delete(blatt.id));
}

/**
 * Originale als zeichenbare Quellen laden (Seitenreihenfolge wie im Editor): PDF-Seiten werden mit
 * pdf.js in Canvas gerendert, Fotos als Bild geladen. Für das Ausschneiden im Schnellmodus.
 */
async function originaleLaden(originale) {
    const seiten = [];
    for (const o of originale) {
        if (o.mime === 'application/pdf') {
            const pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
            const doc = await pdfjs.getDocument(pdfQuelle(o.url)).promise;
            for (let nr = 1; nr <= doc.numPages; nr++) {
                const seite = await doc.getPage(nr);
                const viewport = seite.getViewport({ scale: 2 });
                const canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                await seite.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                seiten.push(canvas);
            }
        } else {
            seiten.push(await new Promise((ok) => {
                const bild = new Image();
                bild.onload = () => ok(bild);
                bild.onerror = () => ok(null);
                bild.src = o.url;
            }));
        }
    }
    return seiten;
}

/** Relative Box [x1, y1, x2, y2] aus einer Quelle (Canvas/Bild) als PNG – null, wenn leer. */
// rand: Box rundum vergrößern (Anteil der Seite) – KI-Boxen schneiden Motive oft knapp ab; der Server
// entfernt danach weißen Rand und Reste der Nachbarbilder (feinschnitt)
async function ausschnittBlob(quelle, box, rand = 0) {
    const breite = quelle.naturalWidth || quelle.width;
    const hoehe = quelle.naturalHeight || quelle.height;
    if (breite < 2 || hoehe < 2) return null;
    const max = Math.max(...box);
    const b = (max > 1.0001 ? box.map((v) => v / (max > 100 ? 1000 : 100)) : box)
        .map((v, i) => v + (i < 2 ? -rand : rand))
        .map((v) => Math.min(1, Math.max(0, v)));
    if (b[2] - b[0] < 0.005 || b[3] - b[1] < 0.005) return null;
    const sw = (b[2] - b[0]) * breite;
    const sh = (b[3] - b[1]) * hoehe;
    const f = Math.min(1, 1600 / Math.max(sw, sh));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(sw * f));
    c.height = Math.max(1, Math.round(sh * f));
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(quelle, b[0] * breite, b[1] * hoehe, sw, sh, 0, 0, c.width, c.height);
    return alsBlob(c, 'image/png');
}

function registerComponents(Alpine) {
    /* ─────────────────────────────────────────────────────────────────────
     * Erfassung
     * ───────────────────────────────────────────────────────────────────── */
    Alpine.data('abErfassung', (konfig) => {
        let quelle = null; // Arbeitscanvas der aktuellen Seite (nicht reaktiv)
        const warteschlange = [];

        return {
            art: 'kamera',
            titel: '',
            fach: '',
            stufe: '',
            erkennen: !!konfig.ki,
            ki: !!konfig.ki,
            maxSeiten: konfig.maxSeiten,
            seiten: [], // {blob, vorschau}
            aktuell: false,
            ecken: [],
            datei: null,
            fehler: '',
            beschaeftigt: false,
            groesse,

            async bilderGewaehlt(e) {
                this.fehler = '';
                const dateien = [...(e.target.files || [])];
                e.target.value = '';
                if (!dateien.length) return;
                this.art = 'kamera';
                warteschlange.push(...dateien);
                if (!this.aktuell) await this.naechstes();
            },

            async naechstes() {
                const datei = warteschlange.shift();
                if (!datei) { this.aktuell = false; return; }
                if (this.seiten.length >= this.maxSeiten) {
                    this.fehler = 'Höchstens ' + this.maxSeiten + ' Seiten pro Blatt.';
                    warteschlange.length = 0;
                    return;
                }
                try {
                    quelle = alsCanvas(await bildLaden(datei));
                } catch (err) {
                    this.fehler = err.message;
                    return this.naechstes();
                }
                this.aktuell = true;
                this.eckenZuruecksetzen();
                await this.$nextTick();
                this.zeichnen();
            },

            eckenZuruecksetzen() {
                const r = 0.05;
                this.ecken = [{ x: r, y: r }, { x: 1 - r, y: r }, { x: 1 - r, y: 1 - r }, { x: r, y: 1 - r }];
                this.$nextTick(() => this.zeichnen());
            },

            drehen() {
                if (!quelle) return;
                quelle = canvasDrehen(quelle);
                this.eckenZuruecksetzen();
            },

            zeichnen() {
                const c = this.$refs.leinwand;
                if (!c || !quelle) return;
                c.width = quelle.width;
                c.height = quelle.height;
                const ctx = c.getContext('2d');
                ctx.drawImage(quelle, 0, 0);
                const p = this.ecken.map((e) => [e.x * c.width, e.y * c.height]);
                ctx.save();
                ctx.fillStyle = 'rgba(15, 23, 42, .45)';
                ctx.beginPath();
                ctx.rect(0, 0, c.width, c.height);
                ctx.moveTo(...p[0]);
                p.slice(1).forEach((q) => ctx.lineTo(...q));
                ctx.closePath();
                ctx.fill('evenodd');
                ctx.strokeStyle = '#818cf8';
                ctx.lineWidth = Math.max(3, c.width / 300);
                ctx.beginPath();
                ctx.moveTo(...p[0]);
                p.slice(1).forEach((q) => ctx.lineTo(...q));
                ctx.closePath();
                ctx.stroke();
                ctx.restore();
            },

            griffStart(e, i) {
                const flaeche = this.$refs.leinwand.getBoundingClientRect();
                const bewegen = (ev) => {
                    this.ecken[i] = {
                        x: Math.min(1, Math.max(0, (ev.clientX - flaeche.left) / flaeche.width)),
                        y: Math.min(1, Math.max(0, (ev.clientY - flaeche.top) / flaeche.height)),
                    };
                    this.zeichnen();
                };
                const ende = () => {
                    window.removeEventListener('pointermove', bewegen);
                    window.removeEventListener('pointerup', ende);
                };
                window.addEventListener('pointermove', bewegen);
                window.addEventListener('pointerup', ende);
            },

            async uebernehmen() {
                if (!quelle) return;
                this.beschaeftigt = true;
                await new Promise((r) => setTimeout(r, 20));
                try {
                    const punkte = this.ecken.map((e) => [e.x * quelle.width, e.y * quelle.height]);
                    const ergebnis = entzerren(quelle, punkte);
                    const blob = await alsBlob(ergebnis);
                    this.seiten.push({ blob, vorschau: URL.createObjectURL(blob) });
                } finally {
                    this.beschaeftigt = false;
                }
                quelle = null;
                await this.naechstes();
            },

            verwerfen() {
                quelle = null;
                this.naechstes();
            },

            seiteEntfernen(i) {
                URL.revokeObjectURL(this.seiten[i].vorschau);
                this.seiten.splice(i, 1);
            },

            dateiGewaehlt(e) {
                this.fehler = '';
                const datei = e.target.files?.[0] || null;
                e.target.value = '';
                if (!datei) return;
                if (datei.size > 20 * 1024 * 1024) {
                    this.fehler = 'Die Datei ist größer als 20 MB.';
                    return;
                }
                this.art = 'upload';
                this.datei = datei;
            },

            bereit() {
                if (this.art === 'manuell') return true;
                if (this.art === 'kamera') return this.seiten.length > 0 && !this.aktuell;
                return !!this.datei;
            },

            async absenden() {
                this.fehler = '';
                const fd = new FormData();
                fd.append('art', this.art);
                fd.append('titel', this.titel);
                if (this.fach) fd.append('wp_fach_id', this.fach);
                if (this.stufe) fd.append('klassenstufe', this.stufe);
                fd.append('erkennen', this.erkennen && this.ki ? '1' : '0');
                if (this.art === 'kamera') {
                    this.seiten.forEach((s, i) => fd.append('dateien[]', s.blob, `seite-${i + 1}.jpg`));
                } else if (this.art === 'upload') {
                    fd.append('dateien[]', this.datei, this.datei.name);
                }
                this.beschaeftigt = true;
                try {
                    const antwort = await anfrage(konfig.url, { methode: 'POST', formular: fd });
                    window.location.href = antwort.url;
                } catch (err) {
                    this.fehler = err.message;
                    this.beschaeftigt = false;
                }
            },
        };
    });

    /* ─────────────────────────────────────────────────────────────────────
     * Schnell / Erweitert (Erfassungsseite) – die Wahl merkt sich der Browser
     * ───────────────────────────────────────────────────────────────────── */
    Alpine.data('abModus', (schnellMoeglich) => ({
        schnellMoeglich: !!schnellMoeglich,
        modus: 'schnell',
        init() {
            let gemerkt = null;
            try { gemerkt = localStorage.getItem('arbeitsblaetter.modus'); } catch (e) { /* ohne Speicher */ }
            this.modus = this.schnellMoeglich && gemerkt !== 'erweitert' ? 'schnell' : 'erweitert';
        },
        setzen(modus) {
            if (modus === 'schnell' && !this.schnellMoeglich) return;
            this.modus = modus;
            try { localStorage.setItem('arbeitsblaetter.modus', modus); } catch (e) { /* ohne Speicher */ }
        },
    }));

    /* Schnellmodus: Datei(en) + „Für wen?“ → hochladen → Statusseite */
    Alpine.data('abSchnell', (konfig) => {
        let gemerkt = {};
        try { gemerkt = JSON.parse(localStorage.getItem('arbeitsblaetter.schnell-ziele') || '{}') || {}; } catch (e) { gemerkt = {}; }
        const kinderIds = konfig.kinder.map((k) => k.id);
        const vorlagenIds = konfig.vorlagen.map((v) => v.id);
        return {
            kinder: konfig.kinder,
            vorlagen: konfig.vorlagen,
            dateien: [],
            gewaehltKinder: (gemerkt.kinder || []).filter((id) => kinderIds.includes(id)),
            gewaehltVorlagen: (gemerkt.vorlagen || []).filter((id) => vorlagenIds.includes(id)),
            suche: '',
            fehler: '',
            beschaeftigt: false,
            groesse,

            gewaehlt(e) {
                this.fehler = '';
                const neu = [...(e.target.files || [])];
                e.target.value = '';
                if (!neu.length) return;
                if (neu.some((d) => d.size > 20 * 1024 * 1024)) {
                    this.fehler = 'Eine Datei ist größer als 20 MB.';
                    return;
                }
                const bilder = neu.every((d) => d.type.startsWith('image/'));
                // mehrere Fotos dürfen sich sammeln, eine PDF/Word-Datei steht allein
                this.dateien = bilder && this.dateien.every((d) => d.type.startsWith('image/')) ? [...this.dateien, ...neu] : neu.slice(0, 1);
                if (this.dateien.length > konfig.maxSeiten) {
                    this.dateien = this.dateien.slice(0, konfig.maxSeiten);
                    this.fehler = `Höchstens ${konfig.maxSeiten} Seiten pro Blatt.`;
                }
            },

            gefilterteKinder() {
                const s = this.suche.trim().toLowerCase();
                return s ? this.kinder.filter((k) => (k.name + ' ' + k.klasse).toLowerCase().includes(s)) : this.kinder;
            },

            bereit() {
                return this.dateien.length > 0 && (this.gewaehltKinder.length + this.gewaehltVorlagen.length) > 0;
            },

            async absenden() {
                this.fehler = '';
                if (!this.bereit()) {
                    this.fehler = this.dateien.length ? 'Bitte auswählen, für wen das Blatt ist.' : 'Bitte ein Foto oder eine Datei wählen.';
                    return;
                }
                const fd = new FormData();
                this.dateien.forEach((d, i) => fd.append('dateien[]', d, d.name || `seite-${i + 1}.jpg`));
                this.gewaehltKinder.forEach((id) => fd.append('kinder[]', id));
                this.gewaehltVorlagen.forEach((id) => fd.append('vorlagen[]', id));
                try { localStorage.setItem('arbeitsblaetter.schnell-ziele', JSON.stringify({ kinder: this.gewaehltKinder, vorlagen: this.gewaehltVorlagen })); } catch (e) { /* ohne Speicher */ }
                this.beschaeftigt = true;
                try {
                    const antwort = await anfrage(konfig.url, { methode: 'POST', formular: fd });
                    window.location.href = antwort.url;
                } catch (err) {
                    this.fehler = err.message;
                    this.beschaeftigt = false;
                }
            },
        };
    });

    /* Schnellmodus-Statusseite: Erkennung abwarten → Abbildungen aus dem Original schneiden → freigeben + setzen */
    Alpine.data('abSchnellStatus', (konfig) => ({
        blatt: konfig.blatt,
        ergebnis: konfig.fertig,
        schritt: 'erkennung',
        fehler: '',
        vorschau: '',
        schritte: [
            { key: 'erkennung', text: 'Blatt wird von der KI erkannt' },
            { key: 'bilder', text: 'Abbildungen werden ausgeschnitten' },
            { key: 'satz', text: 'Angepasste Fassungen werden gesetzt' },
        ],

        init() {
            if (this.ergebnis) {
                this.fertig(this.ergebnis);
                return;
            }
            this.ablauf();
        },

        schrittIndex() {
            return this.schritte.findIndex((s) => s.key === this.schritt);
        },

        fertig(ergebnis) {
            this.ergebnis = ergebnis;
            this.schritt = 'fertig';
            this.vorschau = ergebnis.fassungen[0]?.ansehen || '';
            if (ergebnis.titel) this.blatt.titel = ergebnis.titel;
        },

        abbruch(meldung) {
            this.fehler = meldung;
            this.schritt = 'fehler';
        },

        async ablauf() {
            try {
                while (this.blatt.status === 'erkennung') {
                    erkennungAnstossen(this.blatt);
                    await new Promise((r) => setTimeout(r, 3000));
                    this.blatt = (await anfrage(this.blatt.urls.daten)).blatt;
                }
                if (this.blatt.status === 'fehler' || !this.blatt.struktur.bausteine.length) {
                    return this.abbruch(this.blatt.fehler || 'Auf dem Blatt wurde nichts erkannt.');
                }
                this.schritt = 'bilder';
                await this.bilderAusschneiden();
                this.schritt = 'satz';
                const antwort = await anfrage(konfig.fertigUrl, { methode: 'POST', daten: { version: this.blatt.version } });
                if (antwort.status === 409) return this.abbruch(antwort.message || 'Das Blatt wurde zwischenzeitlich geändert.');
                this.fertig(antwort);
            } catch (err) {
                this.abbruch(err.message);
            }
        },

        /** Abbildungen mit KI-Box, aber ohne Bild (PDF-Originale), im Browser ausschneiden und speichern. */
        async bilderAusschneiden() {
            const offen = [];
            this.blatt.struktur.bausteine.forEach((b) => [b, ...(b.kinder || [])].forEach((x) => {
                if (x.typ === 'bild' && x.quelle?.box && !x.media_id) offen.push(x);
            }));
            if (!offen.length) return;

            const seiten = await originaleLaden(this.blatt.originale);
            let geaendert = false;
            // je vier Abbildungen gleichzeitig hochladen (Sammelbilder werden in viele Einzelbilder zerlegt)
            const eine = async (b) => {
                const quelle = seiten[(b.quelle.seite || 1) - 1];
                const blob = quelle ? await ausschnittBlob(quelle, b.quelle.box, 0.02) : null;
                if (!blob) return;
                const fd = new FormData();
                fd.append('bild', blob, 'abbildung.png');
                fd.append('feinschnitt', '1'); // ungenaue KI-Box: Reste der Nachbarbilder und weißen Rand abschneiden
                try {
                    const antwort = await anfrage(this.blatt.urls.abbildung, { methode: 'POST', formular: fd });
                    b.media_id = antwort.id;
                    geaendert = true;
                } catch (e) { /* diese Abbildung bleibt ohne Bild – Hinweis kommt vom Server */ }
            };
            for (let i = 0; i < offen.length; i += 4) {
                await Promise.all(offen.slice(i, i + 4).map(eine));
            }
            if (!geaendert) return;
            const antwort = await anfrage(this.blatt.urls.speichern, { methode: 'PUT', daten: { version: this.blatt.version, struktur: this.blatt.struktur } });
            if (antwort.status === 409) throw new Error(antwort.message || 'Das Blatt wurde zwischenzeitlich geändert.');
            this.blatt.version = antwort.blatt.version;
        },
    }));

    /* ─────────────────────────────────────────────────────────────────────
     * Prüf-Editor
     * ───────────────────────────────────────────────────────────────────── */
    Alpine.data('abEditor', (konfig) => {
        const pdfDokumente = []; // pdf.js-Objekte außerhalb von Alpine (keine Proxies)
        let speicherTimer = null;
        let vorschauTimer = null;
        let pollTimer = null;
        let zaehler = 0;

        const ICONS = {
            ueberschrift: 'fa-heading', anweisung: 'fa-bullhorn', text: 'fa-align-left', aufgabe: 'fa-list-ol',
            ankreuzen: 'fa-check-square', tabelle: 'fa-table', schreiblinien: 'fa-grip-lines', bild: 'fa-image',
            kasten: 'fa-square', rechenfeld: 'fa-th', zeichenflaeche: 'fa-pencil-alt',
        };

        const segmenteZuText = (segmente) => (segmente || []).map((s) => (s.typ === 'luecke' ? '_'.repeat(Math.max(3, s.laenge || 4)) : (s.text || ''))).join('');
        const textZuSegmente = (text) => (text || '').split(/(_{3,})/).filter((t) => t !== '').map((t) => (/^_{3,}$/.test(t) ? { typ: 'luecke', laenge: Math.min(40, t.length) } : { typ: 'text', text: t }));

        function leererBaustein(typ, text = '') {
            const id = neueId();
            switch (typ) {
                case 'ueberschrift':
                case 'anweisung': return { id, typ, text };
                case 'text': return { id, typ, inhalt: textZuSegmente(text) };
                case 'aufgabe': return { id, typ, nummer: '', inhalt: textZuSegmente(text), kinder: [] };
                case 'ankreuzen': return { id, typ, frage: text, optionen: ['', ''], mehrfach: false, angekreuzt: [] };
                case 'zeichenflaeche': return { id, typ, hoehe: 6 };
                case 'kasten': return { id, typ, titel: '', inhalt: textZuSegmente(text) };
                case 'rechenfeld': return { id, typ, reihen: 2, inhalt: [] };
                case 'tabelle': return { id, typ, zeilen: [[text, ''], ['', '']], kopfzeile: false };
                case 'schreiblinien': return { id, typ, anzahl: 3, lineatur: 'einfach' };
                case 'bild': return { id, typ, media_id: null, beschreibung: text, quelle: null };
                default: return { id, typ: 'text', inhalt: textZuSegmente(text) };
            }
        }

        function klartext(b) {
            switch (b.typ) {
                case 'ueberschrift':
                case 'anweisung': return b.text || '';
                case 'text':
                case 'aufgabe': return segmenteZuText(b.inhalt);
                case 'ankreuzen': return b.frage || '';
                case 'tabelle': return (b.zeilen?.[0] || []).join(' ');
                case 'kasten': return [b.titel, segmenteZuText(b.inhalt)].filter(Boolean).join('\n');
                case 'rechenfeld': return segmenteZuText(b.inhalt);
                case 'bild': return b.beschreibung || '';
                default: return '';
            }
        }

        return {
            blatt: kopie(konfig.blatt),
            typen: konfig.typen,
            profile: konfig.profile,
            profil: '',
            aktiv: null,
            zoom: 1,
            seiten: [],
            pdfFehler: '',
            schneiden: null,
            kontrast: false,
            zeichnung: null,
            konflikt: null,
            speichert: false,
            ungespeichert: false,
            gespeichertUm: '',
            meldung: '',
            meldungFehler: '',

            init() {
                this.seitenAufbauen();
                this.$nextTick(() => {
                    if (this.$refs.liste) {
                        sortierbar(this.$refs.liste, { handle: '.ab-griffleiste' }, (alt, neu) => {
                            const liste = this.blatt.struktur.bausteine;
                            liste.splice(neu, 0, liste.splice(alt, 1)[0]);
                            this.geaendert();
                        });
                    }
                    this.vorschauLaden();
                });
                if (this.blatt.status === 'erkennung') {
                    erkennungAnstossen(this.blatt);
                    this.pollen();
                }
            },

            icon(typ) { return ICONS[typ] || 'fa-square'; },

            anzahl() {
                return this.blatt.struktur.bausteine.reduce((n, b) => n + 1 + (b.kinder?.length || 0), 0);
            },

            // ── Original ───────────────────────────────────────────────
            async seitenAufbauen() {
                const seiten = [];
                for (const o of this.blatt.originale) {
                    if (o.mime === 'application/pdf') {
                        try {
                            const pdfjs = await import('pdfjs-dist');
                            pdfjs.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
                            const doc = await pdfjs.getDocument(pdfQuelle(o.url)).promise;
                            pdfDokumente.push(doc);
                            for (let nr = 1; nr <= doc.numPages; nr++) seiten.push({ art: 'pdf', doc: pdfDokumente.length - 1, nr, url: o.url });
                        } catch (err) {
                            this.pdfFehler = 'Das PDF konnte nicht angezeigt werden: ' + err.message;
                        }
                    } else {
                        seiten.push({ art: 'bild', url: o.url });
                    }
                }
                this.seiten = seiten;
                await this.$nextTick();
                for (let i = 0; i < seiten.length; i++) {
                    if (seiten[i].art !== 'pdf') continue;
                    const canvas = this.$refs.original.querySelector(`canvas[data-seite="${i}"]`);
                    const seite = await pdfDokumente[seiten[i].doc].getPage(seiten[i].nr);
                    const viewport = seite.getViewport({ scale: 2 });
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    await seite.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                }
            },

            hinweisBoxen(si) {
                const boxen = [];
                const sammeln = (b) => {
                    if (b.typ === 'bild' && b.quelle && b.quelle.seite === si + 1) boxen.push({ id: b.id, box: b.quelle.box, beschreibung: b.beschreibung });
                };
                this.blatt.struktur.bausteine.forEach((b) => { sammeln(b); (b.kinder || []).forEach(sammeln); });
                return boxen;
            },

            boxStil(box) {
                const [x1, y1, x2, y2] = box;
                return `left:${x1 * 100}%;top:${y1 * 100}%;width:${(x2 - x1) * 100}%;height:${(y2 - y1) * 100}%`;
            },

            schneidenStart(b) {
                this.schneiden = b.id;
                this.aktiv = b.id;
                this.$refs.original?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },

            relativ(e, el) {
                const r = el.getBoundingClientRect();
                return [Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)), Math.min(1, Math.max(0, (e.clientY - r.top) / r.height))];
            },

            boxStart(e, si) {
                if (!this.schneiden) return;
                e.preventDefault();
                e.currentTarget.setPointerCapture?.(e.pointerId);
                const [x, y] = this.relativ(e, e.currentTarget);
                this.zeichnung = { seite: si, start: [x, y], box: [x, y, x, y] };
            },

            boxZiehen(e) {
                if (!this.zeichnung) return;
                const [x, y] = this.relativ(e, e.currentTarget);
                const [sx, sy] = this.zeichnung.start;
                this.zeichnung.box = [Math.min(sx, x), Math.min(sy, y), Math.max(sx, x), Math.max(sy, y)];
            },

            async boxEnde() {
                if (!this.zeichnung) return;
                const { seite, box } = this.zeichnung;
                this.zeichnung = null;
                if (box[2] - box[0] < 0.01 || box[3] - box[1] < 0.01) return;
                const b = this.finden(this.schneiden)?.[0];
                this.schneiden = null;
                if (b) await this.ausschneiden(b, seite, box);
            },

            async vorschlagSchneiden(b) {
                if (!b.quelle) return;
                await this.ausschneiden(b, b.quelle.seite - 1, b.quelle.box);
            },

            async ausschneiden(b, seite, box) {
                this.meldungFehler = '';
                const tag = this.seiten[seite]?.art === 'pdf' ? 'canvas' : 'img';
                const quelle = this.$refs.original.querySelector(`${tag}[data-seite="${seite}"]`);
                const breite = quelle ? (quelle.naturalWidth || quelle.width) : 0;
                const hoehe = quelle ? (quelle.naturalHeight || quelle.height) : 0;
                if (!quelle || breite < 2 || hoehe < 2) {
                    this.meldungFehler = `Seite ${seite + 1} des Originals ist noch nicht geladen – bitte kurz warten und erneut versuchen.`;
                    return;
                }
                // Boxen der KI im Format 0–1000 bzw. 0–100 auf 0–1 bringen
                const max = Math.max(...box);
                if (max > 1.0001) box = box.map((v) => v / (max > 100 ? 1000 : 100));
                box = box.map((v) => Math.min(1, Math.max(0, v)));
                if (box[2] - box[0] < 0.005 || box[3] - box[1] < 0.005) {
                    this.meldungFehler = 'Der vorgeschlagene Ausschnitt ist leer – bitte auf dem Original selbst ausschneiden.';
                    return;
                }
                const sx = box[0] * breite;
                const sy = box[1] * hoehe;
                const sw = Math.max(1, (box[2] - box[0]) * breite);
                const sh = Math.max(1, (box[3] - box[1]) * hoehe);
                const f = Math.min(1, 1600 / Math.max(sw, sh));
                const c = document.createElement('canvas');
                c.width = Math.round(sw * f);
                c.height = Math.round(sh * f);
                const ctx = c.getContext('2d', { willReadFrequently: true });
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, c.width, c.height);
                ctx.drawImage(quelle, sx, sy, sw, sh, 0, 0, c.width, c.height);
                if (this.kontrast) {
                    const daten = ctx.getImageData(0, 0, c.width, c.height);
                    const d = daten.data;
                    for (let i = 0; i < d.length; i += 4) {
                        const hell = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
                        d[i] = d[i + 1] = d[i + 2] = hell > 150 ? 255 : 0;
                    }
                    ctx.putImageData(daten, 0, 0);
                }
                const blob = await alsBlob(c, 'image/png');
                const fd = new FormData();
                fd.append('bild', blob, 'abbildung.png');
                try {
                    const antwort = await anfrage(this.blatt.urls.abbildung, { methode: 'POST', formular: fd });
                    this.blatt.abbildungen = { ...this.blatt.abbildungen, [antwort.id]: antwort.url };
                    b.media_id = antwort.id;
                    b.quelle = { seite: seite + 1, box: box.map((v) => Math.round(v * 10000) / 10000) };
                    this.geaendert();
                } catch (err) {
                    this.meldungFehler = err.message;
                }
            },

            async bildHochladen(b, e) {
                const datei = e.target.files?.[0];
                e.target.value = '';
                if (!datei) return;
                const fd = new FormData();
                fd.append('bild', datei);
                try {
                    const antwort = await anfrage(this.blatt.urls.abbildung, { methode: 'POST', formular: fd });
                    this.blatt.abbildungen = { ...this.blatt.abbildungen, [antwort.id]: antwort.url };
                    b.media_id = antwort.id;
                    this.geaendert();
                } catch (err) {
                    this.meldungFehler = err.message;
                }
            },

            // ── Bausteine ──────────────────────────────────────────────
            /** @returns {[object, array, number, object|null]|null} Baustein, Liste, Index, Eltern */
            finden(id) {
                const liste = this.blatt.struktur.bausteine;
                for (let i = 0; i < liste.length; i++) {
                    if (liste[i].id === id) return [liste[i], liste, i, null];
                    const kinder = liste[i].kinder || [];
                    for (let k = 0; k < kinder.length; k++) if (kinder[k].id === id) return [kinder[k], kinder, k, liste[i]];
                }
                return null;
            },

            hinzufuegen(typ) {
                const b = leererBaustein(typ);
                const treffer = this.aktiv ? this.finden(this.aktiv) : null;
                if (treffer && !treffer[3]) {
                    this.blatt.struktur.bausteine.splice(treffer[2] + 1, 0, b);
                } else {
                    this.blatt.struktur.bausteine.push(b);
                }
                this.aktiv = b.id;
                this.geaendert();
                this.$nextTick(() => this.$refs.liste?.querySelector(`[data-id="${b.id}"] :is(textarea, input)`)?.focus());
            },

            teilaufgabe(aufgabe) {
                const b = leererBaustein('aufgabe');
                b.nummer = String.fromCharCode(97 + (aufgabe.kinder?.length || 0)) + ')';
                delete b.kinder;
                aufgabe.kinder = [...(aufgabe.kinder || []), b];
                this.aktiv = b.id;
                this.geaendert();
            },

            loeschen(id) {
                const t = this.finden(id);
                if (!t) return;
                const [b, liste, i] = t;
                if ((b.kinder || []).length && !confirm('Aufgabe mit Teilaufgaben löschen?')) return;
                liste.splice(i, 1);
                this.geaendert();
            },

            verschieben(id, richtung) {
                const t = this.finden(id);
                if (!t) return;
                const [, liste, i] = t;
                const ziel = i + richtung;
                if (ziel < 0 || ziel >= liste.length) return;
                liste.splice(ziel, 0, liste.splice(i, 1)[0]);
                this.geaendert();
            },

            kannGruppieren(id) {
                const t = this.finden(id);
                if (!t || t[3]) return false;
                const [b, liste, i] = t;
                return i > 0 && liste[i - 1].typ === 'aufgabe' && !(b.kinder || []).length;
            },

            gruppieren(id) {
                if (!this.kannGruppieren(id)) return;
                const [b, liste, i] = this.finden(id);
                liste.splice(i, 1);
                const kind = { ...b };
                delete kind.kinder;
                liste[i - 1].kinder = [...(liste[i - 1].kinder || []), kind];
                this.geaendert();
            },

            loesen(id) {
                const t = this.finden(id);
                if (!t || !t[3]) return;
                const [b, kinder, k, eltern] = t;
                kinder.splice(k, 1);
                const liste = this.blatt.struktur.bausteine;
                const index = liste.indexOf(eltern);
                liste.splice(index + 1, 0, b.typ === 'aufgabe' ? { ...b, kinder: [] } : b);
                this.geaendert();
            },

            typAendern(b, typ) {
                if (b.typ === typ) return;
                const t = this.finden(b.id);
                if (!t) return;
                const [alt, liste, i, eltern] = t;
                const neu = leererBaustein(typ, klartext(alt));
                neu.id = alt.id;
                if (alt.typ === 'aufgabe' && typ === 'text') neu.inhalt = kopie(alt.inhalt);
                if (alt.typ === 'text' && typ === 'aufgabe') neu.inhalt = kopie(alt.inhalt);
                if (eltern && neu.kinder) delete neu.kinder;
                liste.splice(i, 1, neu);
                // Teilaufgaben bleiben erhalten (hinter dem Baustein)
                if (!eltern && (alt.kinder || []).length && typ !== 'aufgabe') liste.splice(i + 1, 0, ...alt.kinder);
                if (!eltern && typ === 'aufgabe') neu.kinder = alt.kinder || [];
                this.geaendert();
            },

            segText(b) { return segmenteZuText(b.inhalt); },

            kreuzUmschalten(b, index) {
                const liste = b.angekreuzt || [];
                b.angekreuzt = liste.includes(index) ? liste.filter((i) => i !== index) : [...liste, index];
                this.geaendert();
            },

            segSetzen(b, text) {
                b.inhalt = textZuSegmente(text);
                this.geaendert();
            },

            lueckeEinfuegen(b, e) {
                const feld = e.target.closest('.ab-baustein')?.querySelector('textarea');
                const text = this.segText(b);
                const pos = feld ? feld.selectionStart ?? text.length : text.length;
                const neu = text.slice(0, pos) + ' ____ ' + text.slice(pos);
                this.segSetzen(b, neu);
                this.$nextTick(() => { if (feld) { feld.focus(); feld.selectionStart = feld.selectionEnd = pos + 6; } });
            },

            tabelle(b, was, richtung) {
                const zeilen = b.zeilen;
                const spalten = zeilen[0]?.length || 1;
                if (was === 'zeile') {
                    if (richtung > 0) zeilen.push(new Array(spalten).fill(''));
                    else if (zeilen.length > 1) zeilen.pop();
                } else if (richtung > 0 && spalten < 10) {
                    zeilen.forEach((z) => z.push(''));
                } else if (richtung < 0 && spalten > 1) {
                    zeilen.forEach((z) => z.pop());
                }
                this.geaendert();
            },

            hoehe(el) {
                el.style.height = 'auto';
                el.style.height = Math.min(400, el.scrollHeight + 2) + 'px';
            },

            kinderSortierbar(el, aufgabe) {
                if (!this.blatt.darf_bearbeiten) return;
                sortierbar(el, { handle: '.ab-griffleiste' }, (alt, neu) => {
                    const kinder = aufgabe.kinder;
                    kinder.splice(neu, 0, kinder.splice(alt, 1)[0]);
                    this.geaendert();
                });
            },

            // ── Speichern, Vorschau, Freigabe ──────────────────────────
            geaendert() {
                if (!this.blatt.darf_bearbeiten) return;
                zaehler++;
                this.ungespeichert = true;
                this.meldung = '';
                clearTimeout(vorschauTimer);
                vorschauTimer = setTimeout(() => this.vorschauLaden(), 700);
                clearTimeout(speicherTimer);
                speicherTimer = setTimeout(() => this.speichern(), 4000);
            },

            nutzdaten() {
                return {
                    version: this.blatt.version,
                    titel: this.blatt.titel,
                    wp_fach_id: this.blatt.wp_fach_id || null,
                    klassenstufe: this.blatt.klassenstufe || null,
                    struktur: this.blatt.struktur,
                };
            },

            async speichern() {
                clearTimeout(speicherTimer);
                if (!this.ungespeichert || this.speichert || !this.blatt.darf_bearbeiten) return true;
                this.speichert = true;
                this.meldungFehler = '';
                const stand = zaehler;
                try {
                    const antwort = await anfrage(this.blatt.urls.speichern, { methode: 'PUT', daten: this.nutzdaten() });
                    if (antwort.status === 409) {
                        this.konflikt = antwort.blatt;
                        return false;
                    }
                    this.uebernehmen(antwort.blatt, stand === zaehler);
                    this.ungespeichert = stand !== zaehler;
                    this.gespeichertUm = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                    return true;
                } catch (err) {
                    this.meldungFehler = err.message;
                    return false;
                } finally {
                    this.speichert = false;
                }
            },

            /** Serverstand übernehmen; bei zwischenzeitlichen Eingaben nur Version/Status. */
            uebernehmen(server, ganz = true) {
                if (ganz) {
                    const abbildungen = { ...this.blatt.abbildungen, ...server.abbildungen };
                    this.blatt = { ...server, abbildungen };
                } else {
                    Object.assign(this.blatt, { version: server.version, status: server.status, status_text: server.status_text, pruefhinweise: server.pruefhinweise || [] });
                }
            },

            konfliktLaden() {
                this.uebernehmen(this.konflikt, true);
                this.konflikt = null;
                this.ungespeichert = false;
                this.vorschauLaden();
            },

            konfliktUeberschreiben() {
                this.blatt.version = this.konflikt.version;
                this.konflikt = null;
                this.speichern();
            },

            async vorschauLaden() {
                const rahmen = this.$refs.vorschau;
                if (!rahmen) return;
                try {
                    const antwort = await anfrage(this.blatt.urls.vorschau, {
                        methode: 'POST', text: true,
                        daten: { struktur: this.blatt.struktur, titel: this.blatt.titel, profil: this.profil },
                    });
                    const scroll = rahmen.contentWindow?.scrollY || 0;
                    rahmen.srcdoc = antwort.text;
                    rahmen.onload = () => { einpassen(rahmen); rahmen.contentWindow?.scrollTo(0, scroll); };
                } catch (err) {
                    this.meldungFehler = 'Vorschau: ' + err.message;
                }
            },

            async freigeben(trotzdem = false) {
                if (this.ungespeichert && !(await this.speichern())) return;
                try {
                    const antwort = await anfrage(this.blatt.urls.freigeben, { methode: 'POST', daten: { version: this.blatt.version, trotzdem } });
                    if (antwort.status === 409) {
                        this.konflikt = antwort.blatt;
                        return;
                    }
                    this.uebernehmen(antwort.blatt, true);
                    this.meldung = antwort.meldung;
                } catch (err) {
                    const hinweise = err.daten?.hinweise;
                    if (err.status === 422 && hinweise?.length) {
                        this.blatt.pruefhinweise = hinweise;
                        const text = 'Vor der Freigabe bitte prüfen:\n\n• ' + hinweise.map((h) => h.text).join('\n• ') + '\n\nTrotzdem freigeben?';
                        if (window.confirm(text)) return this.freigeben(true);
                        return;
                    }
                    this.meldungFehler = err.message;
                }
            },

            hinweisZeigen(id) {
                this.aktiv = id;
                this.$nextTick(() => document.querySelector(`.ab-baustein[data-id="${id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
            },

            async erkennen() {
                if (this.ungespeichert && !(await this.speichern())) return;
                const n = this.anzahl();
                if (n && !window.confirm(`Die ${n} vorhandenen Bausteine werden durch das neue Erkennungsergebnis ersetzt. Fortfahren?`)) return;
                try {
                    const antwort = await anfrage(this.blatt.urls.erkennen, { methode: 'POST' });
                    this.uebernehmen(antwort.blatt, true);
                    this.meldung = antwort.meldung;
                    erkennungAnstossen(antwort.blatt);
                    this.pollen();
                } catch (err) {
                    this.meldungFehler = err.message;
                }
            },

            protokoll: null,

            async protokollOeffnen() {
                try {
                    const antwort = await anfrage(this.blatt.urls.protokoll);
                    this.protokoll = antwort.eintraege;
                } catch (err) {
                    this.meldungFehler = err.message;
                }
            },

            async abbrechen() {
                clearTimeout(pollTimer);
                try {
                    const antwort = await anfrage(this.blatt.urls.abbrechen, { methode: 'POST' });
                    this.uebernehmen(antwort.blatt, true);
                    this.meldung = antwort.meldung;
                } catch (err) {
                    this.meldungFehler = err.message;
                }
            },

            pollen() {
                clearTimeout(pollTimer);
                pollTimer = setTimeout(async () => {
                    try {
                        const antwort = await anfrage(this.blatt.urls.daten);
                        if (antwort.blatt.status === 'erkennung') {
                            erkennungAnstossen(antwort.blatt);
                            this.blatt.fehler = antwort.blatt.fehler;
                            return this.pollen();
                        }
                        if (this.ungespeichert) {
                            this.meldung = 'Die Erkennung ist fertig – bitte speichern und die Seite neu laden.';
                            Object.assign(this.blatt, { status: antwort.blatt.status, status_text: antwort.blatt.status_text });
                        } else {
                            this.uebernehmen(antwort.blatt, true);
                            this.vorschauLaden();
                        }
                    } catch (err) {
                        this.pollen();
                    }
                }, 4000);
            },

            warnen(e) {
                if (this.ungespeichert) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            },
        };
    });

    /* ─────────────────────────────────────────────────────────────────────
     * Sehprofile und Profil-Vorlagen
     * ───────────────────────────────────────────────────────────────────── */
    Alpine.data('abProfile', (konfig) => {
        let vorschauTimer = null;
        return {
            kinder: konfig.kinder,          // [{id, name, klasse}]
            profile: konfig.profile,        // {schueler_id: {vorlage_id, aktiv, werte, kurz, geaendert}}
            vorlagen: konfig.vorlagen,      // [{id, name, werte, kurz}]
            felder: konfig.felder,
            standard: konfig.standard,
            darfPflegen: konfig.darfPflegen,
            urls: konfig.urls,
            offen: null,                    // {art: 'kind'|'vorlage', id, name, vorlage_id, aktiv, werte}
            fehler: '',
            meldung: '',
            speichert: false,

            gruppen() {
                const g = {};
                Object.entries(this.felder).forEach(([feld, def]) => { (g[def.gruppe] ||= []).push([feld, def]); });
                return g;
            },

            vorlageWerte(id) {
                return kopie(this.vorlagen.find((v) => v.id === Number(id))?.werte || this.standard);
            },

            kindOeffnen(kind) {
                const p = this.profile[kind.id];
                this.offen = {
                    art: 'kind', id: kind.id, name: kind.name,
                    vorlage_id: p ? (p.vorlage_id || '') : (this.vorlagen[0]?.id || ''),
                    aktiv: p ? p.aktiv : true,
                    werte: p ? kopie(p.werte) : this.vorlageWerte(this.vorlagen[0]?.id),
                    neu: !p,
                };
                this.oeffnen();
            },

            vorlageOeffnen(v = null) {
                this.offen = { art: 'vorlage', id: v?.id || null, name: v?.name || '', werte: kopie(v?.werte || this.standard) };
                this.oeffnen();
            },

            oeffnen() {
                this.fehler = '';
                this.$nextTick(() => this.vorschau());
            },

            vorlageGewaehlt() {
                this.offen.werte = this.vorlageWerte(this.offen.vorlage_id);
                this.vorschau();
            },

            abweichend(feld) {
                if (this.offen?.art !== 'kind') return false;
                const basis = this.vorlageWerte(this.offen.vorlage_id);
                return String(basis[feld]) !== String(this.offen.werte[feld]);
            },

            vorschau() {
                clearTimeout(vorschauTimer);
                vorschauTimer = setTimeout(async () => {
                    if (!this.offen || !this.$refs.beispiel) return;
                    try {
                        const antwort = await anfrage(this.urls.vorschau, { methode: 'POST', text: true, daten: { werte: this.offen.werte } });
                        const rahmen = this.$refs.beispiel;
                        rahmen.onload = () => einpassen(rahmen);
                        rahmen.srcdoc = antwort.text;
                    } catch (err) {
                        this.fehler = err.message;
                    }
                }, 400);
            },

            async speichern() {
                this.speichert = true;
                this.fehler = '';
                try {
                    if (this.offen.art === 'kind') {
                        const antwort = await anfrage(this.urls.kind.replace('__ID__', this.offen.id), {
                            methode: 'PUT', daten: { vorlage_id: this.offen.vorlage_id || null, aktiv: this.offen.aktiv, werte: this.offen.werte },
                        });
                        this.profile = { ...this.profile, [this.offen.id]: antwort.profil };
                        this.meldung = antwort.meldung;
                    } else {
                        const url = this.offen.id ? this.urls.vorlage.replace('__ID__', this.offen.id) : this.urls.vorlageNeu;
                        const antwort = await anfrage(url, { methode: this.offen.id ? 'PUT' : 'POST', daten: { name: this.offen.name, werte: this.offen.werte } });
                        const i = this.vorlagen.findIndex((v) => v.id === antwort.vorlage.id);
                        if (i >= 0) this.vorlagen.splice(i, 1, antwort.vorlage); else this.vorlagen.push(antwort.vorlage);
                        this.meldung = antwort.meldung;
                    }
                    this.offen = null;
                } catch (err) {
                    this.fehler = err.message;
                } finally {
                    this.speichert = false;
                }
            },

            async entfernen() {
                if (!confirm(this.offen.art === 'kind' ? 'Profil entfernen? Vorhandene Fassungen bleiben, werden aber nicht mehr aktualisiert.' : 'Vorlage löschen?')) return;
                try {
                    if (this.offen.art === 'kind') {
                        const antwort = await anfrage(this.urls.kind.replace('__ID__', this.offen.id), { methode: 'DELETE' });
                        const p = { ...this.profile };
                        delete p[this.offen.id];
                        this.profile = p;
                        this.meldung = antwort.meldung;
                    } else {
                        const antwort = await anfrage(this.urls.vorlage.replace('__ID__', this.offen.id), { methode: 'DELETE' });
                        this.vorlagen = this.vorlagen.filter((v) => v.id !== this.offen.id);
                        this.meldung = antwort.meldung;
                    }
                    this.offen = null;
                } catch (err) {
                    this.fehler = err.message;
                }
            },
        };
    });

    /* ─────────────────────────────────────────────────────────────────────
     * Einstellungen
     * ───────────────────────────────────────────────────────────────────── */
    Alpine.data('abEinstellungen', (konfig) => {
        let timer = null;
        return {
            ordner: konfig.ordner,
            datei: konfig.datei,
            beispiel: '',
            fehler: {},
            status: konfig.status,
            prueft: false,

            init() { this.vorschau(); },

            vorschau() {
                clearTimeout(timer);
                timer = setTimeout(async () => {
                    try {
                        const antwort = await anfrage(konfig.vorschauUrl, { methode: 'POST', daten: { ab_ordner_vorlage: this.ordner, ab_datei_vorlage: this.datei } });
                        this.beispiel = antwort.beispiel;
                        this.fehler = {};
                    } catch (err) {
                        this.fehler = err.daten?.fehler || { ab_ordner_vorlage: err.message };
                        this.beispiel = '';
                    }
                }, 400);
            },

            async pruefen() {
                this.prueft = true;
                try {
                    const antwort = await anfrage(konfig.pruefenUrl, { methode: 'POST' });
                    this.status = antwort.status;
                } finally {
                    this.prueft = false;
                }
            },
        };
    });
}

if (window.Alpine) {
    registerComponents(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerComponents(window.Alpine));
}
