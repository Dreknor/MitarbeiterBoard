/*
 * Wiki – Vite-Entrypoint (Alpine kommt global aus sidebar.js und startet bei window.load).
 *
 * Komponenten:
 *   wikiRahmen  – Tastenkürzel (Strg+K, „/“), mobile Navigation, Lightbox, Toast
 *   wikiSuche   – Live-Suche in der Kopfleiste (JSON von wiki.suche)
 *   wikiKnoten  – auf-/zuklappbarer Knoten im Seitenbaum (Zustand in localStorage)
 *   wikiSeite   – Favorit, aktiver Abschnitt im Inhaltsverzeichnis, Anker kopieren, Bilder vergrößern
 *   wikiEditor  – TinyMCE (Bilder per Strg+V/Drag & Drop sofort hochladen, Hinweisboxen, Wiki-Links),
 *                 Tags, Dateien, Speichern per fetch mit Konfliktdialog (409), Hinweis „bearbeitet gerade“
 *   wikiStruktur – Seitenbaum per SortableJS ordnen
 */
import Sortable from 'sortablejs';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function anfrage(url, { methode = 'GET', daten = null, formData = null } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
    let body = null;
    if (formData) {
        body = formData;
    } else if (daten !== null) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(daten);
    }
    const antwort = await fetch(url, { method: methode, headers, body, credentials: 'same-origin' });
    let json = {};
    try { json = await antwort.json(); } catch (e) { /* keine JSON-Antwort */ }

    return { ok: antwort.ok, status: antwort.status, json };
}

function ersterFehler(json) {
    if (json?.errors) {
        const erster = Object.values(json.errors)[0];
        return Array.isArray(erster) ? erster[0] : String(erster);
    }
    return json?.message || 'Das hat leider nicht geklappt. Bitte erneut versuchen.';
}

function speicherLesen(schluessel, standard) {
    try { const wert = localStorage.getItem(schluessel); return wert === null ? standard : JSON.parse(wert); } catch (e) { return standard; }
}
function speicherSchreiben(schluessel, wert) {
    try { localStorage.setItem(schluessel, JSON.stringify(wert)); } catch (e) { /* privat */ }
}

const EDITOR_STIL = `
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 16px; line-height: 1.65; color: #1f2937; margin: 1rem 1.25rem; }
h2 { font-size: 1.4rem; border-bottom: 1px solid #f3f4f6; padding-bottom: .25em; } h3 { font-size: 1.15rem; }
img { max-width: 100%; height: auto; border-radius: 6px; }
table { border-collapse: collapse; } th, td { border: 1px solid #d1d5db; padding: .35em .6em; } th { background: #f8fafc; }
pre { background: #0f172a; color: #e2e8f0; padding: .8em 1em; border-radius: 8px; }
blockquote { border-left: 4px solid #e5e7eb; margin-left: 0; padding-left: 1em; color: #4b5563; }
figure.image { display: table; margin: 1em 0; } figure.image figcaption { display: table-caption; caption-side: bottom; text-align: center; color: #6b7280; font-size: .85em; }
.wiki-bild-klein { max-width: 260px; } .wiki-bild-mittel { max-width: 520px; } .wiki-bild-voll { width: 100%; }
.wiki-bild-links { float: left; margin: .3em 1.2em .6em 0; } .wiki-bild-rechts { float: right; margin: .3em 0 .6em 1.2em; }
.wiki-hinweis { border: 1px solid; border-radius: 10px; padding: .7em 1em; margin: 1em 0; }
.wiki-hinweis-info { background: #eff6ff; border-color: #bfdbfe; } .wiki-hinweis-tipp { background: #ecfdf5; border-color: #a7f3d0; } .wiki-hinweis-warnung { background: #fffbeb; border-color: #fde68a; }
a { color: #1d4ed8; }
`;

function registrieren(Alpine) {
    Alpine.data('wikiRahmen', () => ({
        navOffen: false,
        lightbox: null,
        meldung: '',
        _toastTimer: null,

        tastatur(e) {
            const ziel = e.target;
            const tippt = ziel && (ziel.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(ziel.tagName));
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.sucheFokussieren();
            } else if (e.key === '/' && !tippt) {
                e.preventDefault();
                this.sucheFokussieren();
            }
        },
        sucheFokussieren() {
            const feld = this.$root.querySelector('.wiki-suchfeld input');
            feld?.focus();
            feld?.select();
        },
        toast(text) {
            this.meldung = text;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.meldung = ''; }, 2500);
        },
    }));

    Alpine.data('wikiSuche', (url) => ({
        begriff: '',
        treffer: [],
        aktiv: -1,
        offen: false,
        laedt: false,
        _nr: 0,

        init() { this.begriff = this.$refs.eingabe.value || ''; },

        async suchen() {
            const q = this.begriff.trim();
            if (q.length < 2) { this.treffer = []; this.offen = false; return; }
            const nr = ++this._nr;
            this.laedt = true;
            const { ok, json } = await anfrage(url + '?q=' + encodeURIComponent(q));
            if (nr !== this._nr) return;
            this.laedt = false;
            this.treffer = ok ? (json.treffer || []) : [];
            this.aktiv = this.treffer.length ? 0 : -1;
            this.offen = true;
        },
        weiter(schritt) {
            if (!this.treffer.length) return;
            this.offen = true;
            this.aktiv = (this.aktiv + schritt + this.treffer.length) % this.treffer.length;
        },
        absenden(e) {
            if (this.offen && this.aktiv >= 0 && this.treffer[this.aktiv]) {
                e.preventDefault();
                window.location.href = this.treffer[this.aktiv].url;
            }
        },
    }));

    Alpine.data('wikiKnoten', (id, offenStart) => ({
        offen: offenStart,
        init() {
            const gespeichert = speicherLesen('wiki.baum.offen', {});
            if (!offenStart && gespeichert[id]) this.offen = true;
        },
        umschalten() {
            this.offen = !this.offen;
            const gespeichert = speicherLesen('wiki.baum.offen', {});
            if (this.offen) gespeichert[id] = 1; else delete gespeichert[id];
            speicherSchreiben('wiki.baum.offen', gespeichert);
        },
    }));

    Alpine.data('wikiSeite', (favoritStart, favoritUrl) => ({
        favorit: favoritStart,
        abschnitt: null,

        init() {
            const ueberschriften = this.$root.querySelectorAll('.wiki-inhalt .wiki-ueberschrift');
            if (!ueberschriften.length || !('IntersectionObserver' in window)) return;
            const beobachter = new IntersectionObserver((eintraege) => {
                eintraege.forEach((e) => { if (e.isIntersecting) this.abschnitt = e.target.id; });
            }, { rootMargin: '0px 0px -70% 0px' });
            ueberschriften.forEach((h) => beobachter.observe(h));
        },
        async favoritUmschalten() {
            const { ok, json } = await anfrage(favoritUrl, { methode: 'POST', daten: {} });
            if (ok) {
                this.favorit = json.favorit;
                this.$dispatch('wiki-toast', this.favorit ? 'Zu den Favoriten hinzugefügt' : 'Aus den Favoriten entfernt');
            }
        },
        linkKopieren(url) {
            navigator.clipboard?.writeText(url).then(() => this.$dispatch('wiki-toast', 'Link kopiert'));
        },
        inhaltKlick(e) {
            const anker = e.target.closest('[data-wiki-anker]');
            if (anker) {
                e.preventDefault();
                const url = window.location.href.split('#')[0] + anker.getAttribute('href');
                history.replaceState(null, '', anker.getAttribute('href'));
                this.linkKopieren(url);
                return;
            }
            const bild = e.target.closest('img[data-wiki-lightbox]');
            if (bild && !e.target.closest('a')) {
                this.lightbox = bild.src;
            }
        },
    }));

    Alpine.data('wikiEditor', (konfig) => ({
        konfig,
        version: konfig.version,
        andere: konfig.andere || [],
        tagListe: [...(konfig.tags || [])],
        tagText: '',
        tagVorschlaege: [],
        neueDateien: [],
        hochladen: false,
        dateiZiehen: false,
        speichert: false,
        geaendert: false,
        fehler: '',
        feldFehler: {},
        konflikt: null,
        linkDialog: false,
        linkBegriff: '',
        linkTreffer: [],
        _editor: null,
        _ping: null,

        init() {
            this.editorStarten();
            window.addEventListener('beforeunload', (e) => {
                if (this.geaendert && !this.speichert) { e.preventDefault(); e.returnValue = ''; }
            });
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); this.speichern(); }
            });
            if (konfig.bearbeitungUrl) {
                this._ping = setInterval(() => this.bearbeitungMelden(), 30000);
                window.addEventListener('pagehide', () => {
                    const fd = new FormData();
                    fd.append('_token', csrf());
                    fd.append('ende', '1');
                    navigator.sendBeacon?.(konfig.bearbeitungUrl, fd);
                });
            }
        },

        editorStarten() {
            if (!window.tinymce) { setTimeout(() => this.editorStarten(), 100); return; }
            const komponente = this;
            const klein = window.matchMedia('(max-width: 640px)').matches;

            window.tinymce.init({
                target: this.$refs.editor,
                language: 'de',
                height: klein ? 420 : 620,
                min_height: 360,
                menubar: false,
                branding: false,
                statusbar: true,
                elementpath: false,
                toolbar_mode: 'sliding',
                toolbar_sticky: true,
                plugins: 'autoresize advlist autolink lists link image table code paste searchreplace fullscreen wordcount charmap hr',
                toolbar: klein
                    ? 'undo redo | formatselect | bold italic | bullist numlist | wikilink image | hinweis'
                    : 'undo redo | formatselect | bold italic underline strikethrough | bullist numlist outdent indent | wikilink link image wikidatei table | hinweis blockquote hr | removeformat searchreplace fullscreen code',
                block_formats: 'Absatz=p; Überschrift 2=h2; Überschrift 3=h3; Überschrift 4=h4; Code-Block=pre',
                formats: {
                    hinweis_info: { block: 'div', classes: 'wiki-hinweis wiki-hinweis-info', wrapper: true },
                    hinweis_tipp: { block: 'div', classes: 'wiki-hinweis wiki-hinweis-tipp', wrapper: true },
                    hinweis_warnung: { block: 'div', classes: 'wiki-hinweis wiki-hinweis-warnung', wrapper: true },
                },
                autoresize_bottom_margin: 40,
                max_height: 1400,
                relative_urls: false,
                convert_urls: false,
                link_context_toolbar: true,
                link_title: false,
                target_list: [{ title: 'Gleiches Fenster', value: '' }, { title: 'Neues Fenster', value: '_blank' }],
                default_link_target: '',
                image_caption: true,
                image_dimensions: false,
                image_title: false,
                image_advtab: false,
                image_description: true,
                image_uploadtab: true,
                image_class_list: [
                    { title: 'Mittel', value: 'wiki-bild-mittel' },
                    { title: 'Volle Breite', value: 'wiki-bild-voll' },
                    { title: 'Klein', value: 'wiki-bild-klein' },
                    { title: 'Mittel, links (Text fließt herum)', value: 'wiki-bild-mittel wiki-bild-links' },
                    { title: 'Mittel, rechts (Text fließt herum)', value: 'wiki-bild-mittel wiki-bild-rechts' },
                    { title: 'Klein, links', value: 'wiki-bild-klein wiki-bild-links' },
                    { title: 'Klein, rechts', value: 'wiki-bild-klein wiki-bild-rechts' },
                    { title: 'Originalgröße', value: '' },
                ],
                automatic_uploads: true,
                paste_data_images: true,
                images_reuse_filename: false,
                images_upload_handler: (blobInfo, erfolg, fehler) => komponente.bildHochladen(blobInfo, erfolg, fehler),
                paste_webkit_styles: 'none',
                paste_retain_style_properties: '',
                paste_word_valid_elements: 'b,strong,i,em,u,s,h1,h2,h3,h4,p,ol,ul,li,a[href],table,thead,tbody,tr,td,th,br,img[src|alt]',
                table_default_attributes: {},
                table_default_styles: {},
                table_class_list: [],
                invalid_styles: 'font-family font-size color background-color line-height',
                content_style: EDITOR_STIL,
                setup(editor) {
                    komponente._editor = editor;
                    editor.ui.registry.addButton('wikilink', {
                        icon: 'bookmark',
                        tooltip: 'Wiki-Seite verlinken',
                        onAction: () => komponente.linkDialogOeffnen(),
                    });
                    editor.ui.registry.addButton('wikidatei', {
                        icon: 'upload',
                        tooltip: komponente.konfig.uploadUrl ? 'Datei hochladen und verlinken' : 'Dateien nach dem ersten Speichern',
                        onAction: () => komponente.dateiWaehlen(),
                    });
                    editor.ui.registry.addMenuButton('hinweis', {
                        text: 'Hinweis',
                        icon: 'warning',
                        tooltip: 'Hinweisbox',
                        fetch: (callback) => callback(['info', 'tipp', 'warnung'].map((art) => ({
                            type: 'togglemenuitem',
                            text: { info: 'Info', tipp: 'Tipp', warnung: 'Achtung' }[art],
                            onAction: () => editor.formatter.toggle('hinweis_' + art),
                            onSetup: (api) => { api.setActive(editor.formatter.match('hinweis_' + art)); return () => {}; },
                        }))),
                    });
                    editor.on('input change undo redo', () => { komponente.geaendert = true; });
                    editor.addShortcut('meta+s', 'Speichern', () => komponente.speichern());
                },
            });
        },

        bildHochladen(blobInfo, erfolg, fehler) {
            const blob = blobInfo.blob();
            if (!this.konfig.uploadUrl) {
                // Neue Seite: Bild bleibt eingebettet und wird beim ersten Speichern zum Anhang
                erfolg('data:' + blob.type + ';base64,' + blobInfo.base64());
                return;
            }
            const fd = new FormData();
            fd.append('datei', blob, blobInfo.filename() || 'bild.png');
            anfrage(this.konfig.uploadUrl, { methode: 'POST', formData: fd }).then(({ ok, json }) => {
                if (ok && json.location) { erfolg(json.location); this.geaendert = true; } else { fehler(ersterFehler(json)); }
            }).catch(() => fehler('Upload fehlgeschlagen'));
        },

        dateiWaehlen() {
            if (!this.konfig.uploadUrl) {
                this.$dispatch('wiki-toast', 'Dateien können Sie nach dem ersten Speichern anhängen.');
                return;
            }
            const eingabe = document.createElement('input');
            eingabe.type = 'file';
            eingabe.multiple = true;
            eingabe.onchange = () => this.dateienHochladen({ target: eingabe }, true);
            eingabe.click();
        },

        async dateienHochladen(e, verlinken = false) {
            const dateien = Array.from(e.dataTransfer?.files || e.target?.files || []);
            if (!dateien.length || !this.konfig.uploadUrl) return;
            const fd = new FormData();
            dateien.forEach((d) => fd.append('dateien[]', d));
            this.hochladen = true;
            const { ok, json } = await anfrage(this.konfig.uploadUrl, { methode: 'POST', formData: fd });
            this.hochladen = false;
            if (!ok) { this.fehler = ersterFehler(json); return; }
            (json.dateien || []).forEach((d) => {
                if (d.bild && verlinken) {
                    this._editor?.insertContent('<img src="' + d.url + '" alt="" class="wiki-bild-mittel">');
                } else if (!d.bild || !verlinken) {
                    this.neueDateien.push(d);
                    if (verlinken) this.dateiLinkEinfuegen(d.url, d.name);
                }
            });
            if (e.target && 'value' in e.target) e.target.value = '';
            this.$dispatch('wiki-toast', 'Hochgeladen');
        },

        dateiLinkEinfuegen(url, name) {
            const ed = this._editor;
            if (!ed) return;
            const a = ed.dom.createHTML('a', { href: url, class: 'wiki-datei' }, ed.dom.encode(name));
            ed.insertContent(a + '&nbsp;');
            this.geaendert = true;
        },

        async dateiEntfernen(url) {
            if (!confirm('Datei aus der Liste entfernen? Ältere Fassungen behalten ihren Link noch 90 Tage.')) return false;
            const { ok, json } = await anfrage(url, { methode: 'DELETE' });
            if (!ok) { this.fehler = ersterFehler(json); return false; }
            return true;
        },

        // ---------------- Tags ----------------
        tagHinzufuegen(text) {
            const tag = (text || '').replace(/,/g, ' ').trim().replace(/\s+/g, ' ').slice(0, 40);
            this.tagText = '';
            this.tagVorschlaege = [];
            if (!tag || this.tagListe.some((t) => t.toLowerCase() === tag.toLowerCase()) || this.tagListe.length >= 20) return;
            this.tagListe.push(tag);
            this.geaendert = true;
        },
        tagEntfernen(i) { this.tagListe.splice(i, 1); this.geaendert = true; },
        async tagVorschlaegeLaden() {
            const q = this.tagText.trim();
            if (!q) { this.tagVorschlaege = []; return; }
            const { ok, json } = await anfrage(this.konfig.tagsUrl + '?q=' + encodeURIComponent(q));
            this.tagVorschlaege = ok ? (json.tags || []).filter((t) => !this.tagListe.some((v) => v.toLowerCase() === t.toLowerCase())).slice(0, 8) : [];
        },

        // ---------------- Wiki-Links ----------------
        linkDialogOeffnen() {
            const auswahl = this._editor?.selection.getContent({ format: 'text' }) || '';
            this.linkBegriff = auswahl.trim();
            this.linkTreffer = [];
            this.linkDialog = true;
            this.$nextTick(() => { this.$refs.linkSuche.focus(); if (this.linkBegriff) this.linkSuchen(); });
        },
        async linkSuchen() {
            const q = this.linkBegriff.trim();
            if (q.length < 2) { this.linkTreffer = []; return; }
            const { ok, json } = await anfrage(this.konfig.sucheUrl + '?q=' + encodeURIComponent(q));
            this.linkTreffer = ok ? (json.treffer || []) : [];
        },
        linkEinfuegen(treffer) {
            const pfad = new URL(treffer.url, window.location.origin).pathname;
            this.linkSetzen(pfad, treffer.titel);
        },
        linkNeueSeite() {
            const titel = this.linkBegriff.trim();
            const slug = titel.toLowerCase()
                .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            if (!slug) return;
            this.linkSetzen(this.konfig.wikiPfad + '/' + slug, titel);
        },
        linkSetzen(href, titel) {
            const ed = this._editor;
            this.linkDialog = false;
            if (!ed) return;
            ed.focus();
            const text = ed.selection.getContent({ format: 'text' });
            if (text) {
                ed.execCommand('mceInsertLink', false, { href });
            } else {
                ed.insertContent(ed.dom.createHTML('a', { href }, ed.dom.encode(titel)) + '&nbsp;');
            }
            this.geaendert = true;
        },

        // ---------------- Speichern ----------------
        daten() {
            this.tagHinzufuegen(this.tagText);
            return {
                titel: this.$refs.titel.value,
                inhalt: this._editor ? this._editor.getContent() : this.$refs.editor.value,
                parent_id: this.$refs.eltern?.value || null,
                tags: this.tagListe,
                notiz: this.$refs.notiz.value,
                version: this.version,
            };
        },

        async speichern() {
            if (this.speichert) return;
            if (this._editor?.uploadImages) {
                await new Promise((fertig) => this._editor.uploadImages(() => fertig()));
            }
            this.speichert = true;
            this.fehler = '';
            this.feldFehler = {};
            const { ok, status, json } = await anfrage(this.konfig.speichernUrl, { methode: this.konfig.methode, daten: this.daten() });
            if (ok) {
                this.geaendert = false;
                window.location.href = json.url;
                return;
            }
            this.speichert = false;
            if (status === 409 && json.konflikt) {
                this.konflikt = json.konflikt;
                return;
            }
            if (status === 422 && json.errors) {
                Object.entries(json.errors).forEach(([feld, meldungen]) => { this.feldFehler[feld] = meldungen[0]; });
            }
            this.fehler = ersterFehler(json);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        trotzdemSpeichern() {
            this.version = this.konflikt.version;
            this.konflikt = null;
            this.speichern();
        },

        abbrechen(e) {
            if (this.geaendert && !confirm('Änderungen verwerfen?')) { e.preventDefault(); return; }
            this.geaendert = false;
        },

        async bearbeitungMelden() {
            const { ok, json } = await anfrage(this.konfig.bearbeitungUrl, { methode: 'POST', daten: {} });
            if (ok) this.andere = json.andere || [];
        },
    }));

    Alpine.data('wikiStruktur', (url) => ({
        geaendert: false,
        speichert: false,
        fehler: '',

        init() {
            this.$refs.wurzel.querySelectorAll('ul').forEach((liste) => {
                Sortable.create(liste, {
                    group: 'wiki-struktur',
                    animation: 150,
                    fallbackOnBody: true,
                    swapThreshold: 0.6,
                    emptyInsertThreshold: 12,
                    onEnd: () => { this.geaendert = true; },
                });
            });
            window.addEventListener('beforeunload', (e) => {
                if (this.geaendert && !this.speichert) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        knoten() {
            const ergebnis = [];
            this.$refs.wurzel.querySelectorAll('li[data-id]').forEach((li) => {
                const liste = li.parentElement;
                const elternLi = liste.closest('li[data-id]');
                ergebnis.push({
                    id: Number(li.dataset.id),
                    parent_id: elternLi ? Number(elternLi.dataset.id) : null,
                    sortierung: Array.from(liste.children).indexOf(li),
                });
            });
            return ergebnis;
        },

        async speichern() {
            this.speichert = true;
            this.fehler = '';
            const { ok, json } = await anfrage(url, { methode: 'POST', daten: { knoten: this.knoten() } });
            this.speichert = false;
            if (!ok) { this.fehler = ersterFehler(json); return; }
            this.geaendert = false;
            window.location.reload();
        },
    }));
}

if (window.Alpine) {
    registrieren(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registrieren(window.Alpine));
}
