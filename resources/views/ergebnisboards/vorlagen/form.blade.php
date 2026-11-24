@extends('layouts.app')

@push('css')
    @vite(['resources/css/ergebnisboards.css', 'resources/js/ergebnisboards.js'])
@endpush

@php
    $konfig = [
        'definition' => $definition,
        'farben' => $farben,
        'status' => $status,
        'methode' => $vorlage ? 'PUT' : 'POST',
        'urls' => [
            'speichern' => $vorlage ? route('ergebnisboards.vorlagen.update', $vorlage) : route('ergebnisboards.vorlagen.store'),
            'index' => route('ergebnisboards.vorlagen.index'),
        ],
    ];
@endphp

@section('content')
<div class="erg-wrapper" x-data="ergVorlagenEditor(@js($konfig))" @input="geaendert = true" @change="geaendert = true">
    <a href="{{ route('ergebnisboards.vorlagen.index') }}" class="erg-back mb-3"><i class="fas fa-arrow-left"></i> Vorlagen</a>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="erg-page-title">{{ $vorlage ? 'Vorlage bearbeiten' : 'Neue Vorlage' }}</h1>
            <p class="erg-page-sub">Raster festlegen: Spalten, Felder und deren Position. Die Vorschau zeigt, wie ein neues Board aussieht.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('ergebnisboards.vorlagen.index') }}" class="erg-btn erg-btn-ghost">Abbrechen</a>
            <button type="button" class="erg-btn erg-btn-primary" @click="speichern()" :disabled="laedt">
                <span class="erg-spinner" x-show.important="laedt"></span>
                <i class="fas fa-check" x-show.important="!laedt"></i> Speichern
            </button>
        </div>
    </div>

    <div x-ref="fehler">
        <div class="erg-alert erg-alert-red mb-4" role="alert" x-show.important="fehler.length" x-cloak>
            <i class="fas fa-exclamation-circle mt-0.5"></i>
            <ul><template x-for="f in fehler" :key="f"><li x-text="f"></li></template></ul>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,32rem)_minmax(0,1fr)] items-start">
        {{-- ---------------- Formular ---------------- --}}
        <div class="flex flex-col gap-5 min-w-0">
            <section class="erg-card">
                <div class="erg-card-body flex flex-col gap-4">
                    <h2 class="erg-card-title"><i class="fas fa-info-circle"></i> Allgemein</h2>
                    <div>
                        <label class="erg-label" for="erg-v-titel">Titel <span class="erg-required">*</span></label>
                        <input id="erg-v-titel" type="text" class="erg-input" maxlength="255" required x-model="titel" placeholder="z. B. Zielscheibe">
                    </div>
                    <div>
                        <label class="erg-label" for="erg-v-beschreibung">Beschreibung</label>
                        <textarea id="erg-v-beschreibung" class="erg-textarea" rows="2" maxlength="500" x-model="beschreibung"
                                  placeholder="Wofür eignet sich die Vorlage?"></textarea>
                    </div>
                    <div>
                        <span class="erg-label">Icon</span>
                        <div class="flex flex-wrap gap-1 mb-2" role="group" aria-label="Icon wählen">
                            <template x-for="i in icons" :key="i">
                                <button type="button" class="erg-icon-btn border border-slate-200" :class="icon === i ? 'bg-emerald-50 text-emerald-700 border-emerald-400' : ''"
                                        @click="icon = i; geaendert = true" :aria-label="i" :aria-pressed="icon === i" :title="i"><i :class="i"></i></button>
                            </template>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="erg-kachel-icon w-9 h-9 text-base" aria-hidden="true"><i :class="icon"></i></span>
                            <input type="text" class="erg-input" maxlength="60" x-model="icon" placeholder="fas fa-th" aria-label="Icon (Font-Awesome-Klasse)">
                        </div>
                        <p class="erg-error" x-show.important="!iconGueltig(icon)">Bitte eine Font-Awesome-Klasse wie „fas fa-star“ angeben – sonst wird „fas fa-th“ verwendet.</p>
                    </div>
                    <label class="erg-check">
                        <input type="checkbox" x-model="aktiv">
                        <span>Aktiv <span class="block text-xs text-slate-500">Nur aktive Vorlagen stehen beim Anlegen eines Boards zur Wahl.</span></span>
                    </label>
                </div>
            </section>

            <section class="erg-card">
                <div class="erg-card-body flex flex-col gap-4">
                    <h2 class="erg-card-title"><i class="fas fa-columns"></i> Raster</h2>
                    <div>
                        <label class="erg-label" for="erg-v-spalten">Anzahl Spalten</label>
                        <select id="erg-v-spalten" class="erg-select" :value="spaltenAnzahl" @change="spaltenSetzen($event.target.value)">
                            <template x-for="n in zahlen(6)" :key="n">
                                <option :value="n" x-text="n" :selected="n === spaltenAnzahl"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <span class="erg-label">Breite je Spalte</span>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <template x-for="(s, i) in breiten" :key="i">
                                <div class="flex items-center gap-1">
                                    <span class="text-xs font-semibold text-slate-500 w-5 shrink-0" x-text="(i + 1) + '.'"></span>
                                    <input type="number" min="0.5" max="9999" step="0.5" class="erg-input erg-input-sm" x-model.number="s.wert"
                                           :aria-label="'Breite Spalte ' + (i + 1)">
                                    <select class="erg-select erg-input-sm" style="width: 4.25rem" x-model="s.einheit" :aria-label="'Einheit Spalte ' + (i + 1)">
                                        <option value="fr">fr</option>
                                        <option value="px">px</option>
                                        <option value="%">%</option>
                                    </select>
                                </div>
                            </template>
                        </div>
                        <p class="erg-hint">„fr“ = Anteil: 1fr 2fr 1fr macht die mittlere Spalte doppelt so breit. Ergibt: <code class="text-slate-700" x-text="spaltenString"></code></p>
                    </div>
                    <label class="erg-check">
                        <input type="checkbox" x-model="bereicheFrei">
                        <span>Felder frei änderbar <span class="block text-xs text-slate-500">Im Board dürfen Hauptfelder (Spalten) ergänzt, entfernt und umsortiert werden – wie bei Kanban. Jedes Feld belegt dann eine Spalte in der ersten Zeile.</span></span>
                    </label>
                    <label class="erg-check">
                        <input type="checkbox" x-model="autoStatus">
                        <span>Status automatisch aus Aufgaben <span class="block text-xs text-slate-500">Karten mit verknüpfter Aufgabe wandern in das Feld mit passendem Status (offen / in Arbeit / erledigt).</span></span>
                    </label>
                </div>
            </section>

            <section class="erg-card">
                <div class="erg-card-body flex flex-col gap-4">
                    <h2 class="erg-card-title"><i class="fas fa-arrows-alt"></i> Achsen &amp; Legende <span class="text-xs font-normal text-slate-400">optional</span></h2>

                    <label class="erg-check">
                        <input type="checkbox" x-model="achseY">
                        <span>Senkrechte Achse (links)</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2" x-show.important="achseY">
                        <input type="text" class="erg-input erg-input-sm" maxlength="60" x-model="achsen.y.titel" placeholder="Titel, z. B. Aufwand" aria-label="Senkrechte Achse: Titel">
                        <input type="text" class="erg-input erg-input-sm" maxlength="60" x-model="achsen.y.zusatz" placeholder="Zusatz" aria-label="Senkrechte Achse: Zusatz">
                        <input type="text" class="erg-input erg-input-sm" maxlength="60" x-model="achsen.y.oben" placeholder="oben, z. B. Langsam" aria-label="Senkrechte Achse: oben">
                        <input type="text" class="erg-input erg-input-sm" maxlength="60" x-model="achsen.y.unten" placeholder="unten, z. B. Schnell" aria-label="Senkrechte Achse: unten">
                    </div>

                    <label class="erg-check">
                        <input type="checkbox" x-model="achseX">
                        <span>Waagerechte Achse (unten)</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2" x-show.important="achseX">
                        <input type="text" class="erg-input erg-input-sm" maxlength="60" x-model="achsen.x.titel" placeholder="Titel, z. B. Ertrag" aria-label="Waagerechte Achse: Titel">
                        <input type="text" class="erg-input erg-input-sm" maxlength="60" x-model="achsen.x.ziel" placeholder="Ziel, z. B. Erfolg" aria-label="Waagerechte Achse: Ziel">
                        <input type="text" class="erg-input erg-input-sm" maxlength="60" x-model="achsen.x.links" placeholder="links" aria-label="Waagerechte Achse: links">
                        <input type="text" class="erg-input erg-input-sm" maxlength="60" x-model="achsen.x.rechts" placeholder="rechts" aria-label="Waagerechte Achse: rechts">
                    </div>

                    <label class="erg-check">
                        <input type="checkbox" x-model="legendeAn" @change="if (legendeAn && !legende.eintraege.length) legendeHinzufuegen()">
                        <span>Legende</span>
                    </label>
                    <div class="flex flex-col gap-2" x-show.important="legendeAn">
                        <input type="text" class="erg-input erg-input-sm" maxlength="80" x-model="legende.titel" placeholder="Titel der Legende" aria-label="Titel der Legende">
                        <template x-for="(e, i) in legende.eintraege" :key="e._id">
                            <div class="flex items-center gap-2" :class="'erg-farbe-' + e.farbe">
                                <span class="erg-punkt"></span>
                                <select class="erg-select erg-input-sm" style="width: 7rem" x-model="e.farbe" :aria-label="'Farbe Eintrag ' + (i + 1)">
                                    <template x-for="(label, key) in farben" :key="key">
                                        <option :value="key" x-text="label" :selected="key === e.farbe"></option>
                                    </template>
                                </select>
                                <input type="text" class="erg-input erg-input-sm" maxlength="120" x-model="e.text" placeholder="z. B. 1. Sofort: Quick Wins starten" :aria-label="'Text Eintrag ' + (i + 1)">
                                <button type="button" class="erg-icon-btn" @click="legendeEntfernen(i); geaendert = true" aria-label="Eintrag entfernen"><i class="fas fa-times"></i></button>
                            </div>
                        </template>
                        <button type="button" class="erg-btn erg-btn-ghost erg-btn-sm self-start" @click="legendeHinzufuegen(); geaendert = true"><i class="fas fa-plus"></i> Eintrag</button>
                        <p class="erg-hint mt-0">Leere Einträge werden nicht gespeichert. Text vor einem Doppelpunkt erscheint fett.</p>
                    </div>
                </div>
            </section>

            <section class="erg-card">
                <div class="erg-card-body flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="erg-card-title"><i class="fas fa-th"></i> Felder <span class="erg-chip" x-text="bereiche.length"></span></h2>
                        <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm" @click="feldHinzufuegen()"><i class="fas fa-plus"></i> Feld</button>
                    </div>

                    <div class="erg-alert erg-alert-amber" x-show.important="warnungen.length" x-cloak>
                        <i class="fas fa-exclamation-triangle mt-0.5"></i>
                        <ul><template x-for="w in warnungen" :key="w"><li x-text="w"></li></template></ul>
                    </div>

                    <template x-for="(b, i) in bereiche" :key="b._id">
                        <div class="erg-feld-zeile" :class="['erg-farbe-' + b.farbe, { 'is-unter': b.eltern }]" :data-feld="b._id">
                            <div class="flex items-center gap-2">
                                <button type="button" class="flex-1 min-w-0 flex items-center gap-2 text-left" @click="feldUmschalten(b)" :aria-expanded="offen === b._id">
                                    <i class="fas fa-chevron-right text-xs text-slate-400 transition-transform" :class="offen === b._id ? 'rotate-90' : ''"></i>
                                    <i :class="b.icon || (b.eltern ? 'fas fa-level-up-alt rotate-90' : 'far fa-square')" class="text-sm" style="color: var(--erg-icon)"></i>
                                    <span class="truncate text-sm font-bold" style="color: var(--erg-text)" x-text="b.titel || 'Ohne Titel'"></span>
                                    <span class="text-[11px] text-slate-500 shrink-0" x-show.important="!b.eltern && !bereicheFrei"
                                          x-text="'Sp. ' + b.spalte + (b.spalten > 1 ? '–' + (b.spalte + b.spalten - 1) : '') + ' · Z. ' + b.zeile + (b.zeilen > 1 ? '–' + (b.zeile + b.zeilen - 1) : '')"></span>
                                    <span class="text-[11px] text-slate-500 shrink-0" x-show.important="b.eltern" x-text="'in ' + elternTitel(b.eltern)"></span>
                                    <i class="fas fa-exclamation-triangle text-amber-600 text-xs shrink-0" x-show.important="ausserhalb(b)" title="außerhalb der Spalten"></i>
                                </button>
                                <button type="button" class="erg-icon-btn" @click="feldVerschieben(i, -1)" :disabled="i === 0" aria-label="Nach oben"><i class="fas fa-arrow-up"></i></button>
                                <button type="button" class="erg-icon-btn" @click="feldVerschieben(i, 1)" :disabled="i === bereiche.length - 1" aria-label="Nach unten"><i class="fas fa-arrow-down"></i></button>
                                <button type="button" class="erg-icon-btn hover:text-red-600" @click="feldEntfernen(i)" aria-label="Feld entfernen"><i class="far fa-trash-alt"></i></button>
                            </div>

                            <div class="flex flex-col gap-2 pt-1" x-show.important="offen === b._id">
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="col-span-2">
                                        <label class="erg-label text-xs" :for="'erg-f-titel-' + b._id">Titel <span class="erg-required">*</span></label>
                                        <input :id="'erg-f-titel-' + b._id" type="text" class="erg-input erg-input-sm" maxlength="80" x-model="b.titel">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="erg-label text-xs" :for="'erg-f-hinweis-' + b._id">Hinweis</label>
                                        <input :id="'erg-f-hinweis-' + b._id" type="text" class="erg-input erg-input-sm" maxlength="200" x-model="b.hinweis" placeholder="Kurze Erklärung unter dem Titel">
                                    </div>
                                    <div>
                                        <label class="erg-label text-xs" :for="'erg-f-badge-' + b._id">Badge</label>
                                        <input :id="'erg-f-badge-' + b._id" type="text" class="erg-input erg-input-sm" maxlength="60" x-model="b.badge" placeholder="z. B. Sofort umsetzen">
                                    </div>
                                    <div>
                                        <label class="erg-label text-xs" :for="'erg-f-icon-' + b._id">Icon</label>
                                        <input :id="'erg-f-icon-' + b._id" type="text" class="erg-input erg-input-sm" maxlength="60" x-model="b.icon" list="erg-icon-liste" placeholder="fas fa-star">
                                    </div>
                                </div>
                                <div>
                                    <span class="erg-label text-xs">Farbe</span>
                                    <div class="erg-farbwahl" role="group" aria-label="Farbe">
                                        <template x-for="(label, key) in farben" :key="key">
                                            <button type="button" :class="['erg-farbe-' + key, { 'is-active': b.farbe === key }]" :title="label" :aria-label="label"
                                                    :aria-pressed="b.farbe === key" @click="b.farbe = key; geaendert = true"></button>
                                        </template>
                                    </div>
                                </div>
                                <div>
                                    <label class="erg-label text-xs" :for="'erg-f-eltern-' + b._id">Unterbereich von</label>
                                    <select :id="'erg-f-eltern-' + b._id" class="erg-select erg-input-sm" x-model="b.eltern" :disabled="hatKinder(b)">
                                        <option value="">– Hauptfeld –</option>
                                        <template x-for="h in elternOptionen(b)" :key="h._id">
                                            <option :value="h.schluessel" x-text="h.titel || h.schluessel" :selected="h.schluessel === b.eltern"></option>
                                        </template>
                                    </select>
                                    <p class="erg-hint" x-show.important="hatKinder(b)">Hat selbst Unterbereiche und bleibt deshalb Hauptfeld.</p>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" x-show.important="!b.eltern">
                                    <div>
                                        <label class="erg-label text-xs" :for="'erg-f-spalte-' + b._id">Spalte</label>
                                        <select :id="'erg-f-spalte-' + b._id" class="erg-select erg-input-sm" x-model.number="b.spalte" @change="spalteGeaendert(b)">
                                            <template x-for="n in zahlen(spaltenAnzahl)" :key="n">
                                                <option :value="n" x-text="n" :selected="n === b.spalte"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div x-show.important="!bereicheFrei">
                                        <label class="erg-label text-xs" :for="'erg-f-spalten-' + b._id">Breite</label>
                                        <select :id="'erg-f-spalten-' + b._id" class="erg-select erg-input-sm" x-model.number="b.spalten">
                                            <template x-for="n in zahlen(Math.max(1, spaltenAnzahl - b.spalte + 1))" :key="n">
                                                <option :value="n" x-text="n + (n === 1 ? ' Spalte' : ' Spalten')" :selected="n === b.spalten"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div x-show.important="!bereicheFrei">
                                        <label class="erg-label text-xs" :for="'erg-f-zeile-' + b._id">Zeile</label>
                                        <select :id="'erg-f-zeile-' + b._id" class="erg-select erg-input-sm" x-model.number="b.zeile">
                                            <template x-for="n in zahlen(12)" :key="n">
                                                <option :value="n" x-text="n" :selected="n === b.zeile"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div x-show.important="!bereicheFrei">
                                        <label class="erg-label text-xs" :for="'erg-f-zeilen-' + b._id">Höhe</label>
                                        <select :id="'erg-f-zeilen-' + b._id" class="erg-select erg-input-sm" x-model.number="b.zeilen">
                                            <template x-for="n in zahlen(12)" :key="n">
                                                <option :value="n" x-text="n + (n === 1 ? ' Zeile' : ' Zeilen')" :selected="n === b.zeilen"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="erg-label text-xs" :for="'erg-f-raster-' + b._id">Karten</label>
                                        <select :id="'erg-f-raster-' + b._id" class="erg-select erg-input-sm" x-model.number="b.raster">
                                            <option value="1" :selected="b.raster === 1">untereinander</option>
                                            <option value="2" :selected="b.raster === 2">2 nebeneinander</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="erg-label text-xs" :for="'erg-f-status-' + b._id">Status</label>
                                        <select :id="'erg-f-status-' + b._id" class="erg-select erg-input-sm" x-model="b.status">
                                            <option value="">– keiner –</option>
                                            <template x-for="s in statusListe" :key="s">
                                                <option :value="s" x-text="statusLabel[s] || s" :selected="s === b.status"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                                <p class="erg-hint mt-0" x-show.important="b.status">Karten, deren Aufgabe diesen Status erreicht, landen hier (bei „Status automatisch aus Aufgaben“ alle drei Stufen, sonst nur „Erledigt“).</p>
                                <button type="button" class="erg-btn erg-btn-ghost erg-btn-sm self-start" x-show.important="!b.eltern && b.schluessel"
                                        @click="feldHinzufuegen(b.schluessel)"><i class="fas fa-plus"></i> Unterbereich</button>
                            </div>
                        </div>
                    </template>

                    <button type="button" class="erg-btn erg-btn-secondary erg-btn-sm self-start" @click="feldHinzufuegen()"><i class="fas fa-plus"></i> Feld hinzufügen</button>
                    <datalist id="erg-icon-liste">
                        <template x-for="i in icons" :key="i"><option :value="i"></option></template>
                    </datalist>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    @if($vorlage)
                        <form method="post" action="{{ route('ergebnisboards.vorlagen.destroy', $vorlage) }}"
                              onsubmit="return confirm('Vorlage „{{ addslashes($vorlage->titel) }}“ löschen? Bestehende Boards bleiben unverändert.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="erg-btn erg-btn-danger" @click="geaendert = false"><i class="far fa-trash-alt"></i> Löschen</button>
                        </form>
                    @endif
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('ergebnisboards.vorlagen.index') }}" class="erg-btn erg-btn-ghost">Abbrechen</a>
                    <button type="button" class="erg-btn erg-btn-primary" @click="speichern()" :disabled="laedt"><i class="fas fa-check"></i> Speichern</button>
                </div>
            </div>
        </div>

        {{-- ---------------- Live-Vorschau (wie partials/board) ---------------- --}}
        <aside class="xl:sticky xl:top-4 min-w-0" aria-label="Vorschau">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="erg-card-title"><i class="far fa-eye"></i> Vorschau</h2>
                <span class="text-xs text-slate-500">mit Beispielkarten</span>
            </div>
            <div class="erg-board-scroll">
                <div class="erg-board" :class="{ 'is-frei': bereicheFrei }" :style="'min-width:' + vorschauMinBreite + 'px; padding: 1.25rem'">
                    <header class="erg-kopf">
                        <div class="min-w-0 flex-1">
                            <div class="erg-kopf-badges"><i :class="icon"></i><span x-text="titel || 'Neue Vorlage'"></span></div>
                            <div class="erg-titel">Titel des Boards</div>
                            <p class="erg-untertitel" x-show.important="beschreibung" x-text="beschreibung"></p>
                        </div>
                    </header>

                    <div :class="achseY ? 'erg-mit-achse' : ''">
                        <template x-if="achseY">
                            <div class="erg-achse erg-achse-y">
                                <div class="flex flex-col items-center gap-1">
                                    <i class="fas fa-arrow-up text-slate-600 text-xs"></i>
                                    <span class="erg-vertikal text-[10px] font-bold uppercase tracking-wider py-1" x-text="achsen.y.oben"></span>
                                </div>
                                <div class="flex flex-col items-center gap-3 my-auto">
                                    <span class="erg-vertikal text-xs font-extrabold tracking-[0.2em] uppercase text-rose-700/80" x-show.important="achsen.y.zusatz" x-text="achsen.y.zusatz"></span>
                                    <span class="w-px h-8 bg-slate-300" x-show.important="achsen.y.zusatz"></span>
                                    <span class="erg-vertikal text-xs font-extrabold tracking-[0.2em] uppercase text-slate-700" x-text="achsen.y.titel || 'Achse'"></span>
                                </div>
                                <div class="flex flex-col items-center gap-1">
                                    <span class="erg-vertikal text-[10px] font-bold uppercase tracking-wider py-1" x-text="achsen.y.unten"></span>
                                    <i class="fas fa-arrow-down text-slate-600 text-xs"></i>
                                </div>
                            </div>
                        </template>

                        <div class="erg-raster" :style="'grid-template-columns:' + spaltenString">
                            <template x-for="feld in hauptfelder" :key="feld._id">
                                <section class="erg-feld" :style="feldStil(feld)"
                                         :class="['erg-farbe-' + feld.farbe, { 'is-mittig': !bereicheFrei && feld.zeilen > 1 && !(seitenfeld && seitenfeld._id === feld._id) }]">
                                    <div class="erg-feld-kopf">
                                        <div class="min-w-0">
                                            <span class="erg-feld-badge" x-show.important="feld.badge" x-text="feld.badge"></span>
                                            <h3 class="erg-feld-titel" x-text="feld.titel || 'Ohne Titel'"></h3>
                                            <p class="erg-feld-hinweis" x-show.important="feld.hinweis" x-text="feld.hinweis"></p>
                                        </div>
                                        <template x-if="feld.icon">
                                            <div class="erg-feld-icon" aria-hidden="true"><i :class="feld.icon"></i></div>
                                        </template>
                                    </div>
                                    <div class="erg-karten" :class="{ 'is-raster-2': feld.raster === 2 }">
                                        <div class="erg-karte is-klein"><div class="erg-karte-titel">Beispielkarte</div><div class="erg-karte-zusatz">Zusatz</div></div>
                                        <div class="erg-karte is-klein"><div class="erg-karte-titel">Beispielkarte</div></div>
                                    </div>
                                    <template x-for="unter in unterbereiche(feld.schluessel)" :key="unter._id">
                                        <div class="erg-unterbereich" :class="'erg-farbe-' + unter.farbe">
                                            <div>
                                                <span class="erg-unterbereich-titel"><span class="erg-punkt"></span><span x-text="unter.titel || 'Ohne Titel'"></span></span>
                                                <span class="erg-feld-badge mt-1" x-show.important="unter.badge" x-text="unter.badge"></span>
                                                <p class="erg-feld-hinweis mt-0.5" x-show.important="unter.hinweis" x-text="unter.hinweis"></p>
                                            </div>
                                            <div class="erg-karten" :class="{ 'is-raster-2': unter.raster === 2 }">
                                                <div class="erg-karte is-klein"><div class="erg-karte-titel">Beispielkarte</div></div>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="legendeImSeitenfeld && seitenfeld._id === feld._id">
                                        <div class="mt-auto">
                                            <div class="erg-legende">
                                                <div class="erg-legende-titel" x-text="legende.titel || 'Legende'"></div>
                                                <template x-for="e in legendeVorschau" :key="e._id">
                                                    <div class="flex items-center gap-2" :class="'erg-farbe-' + e.farbe"><span class="erg-punkt"></span><span x-text="e.text"></span></div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </section>
                            </template>
                        </div>
                    </div>

                    <template x-if="achseX">
                        <div :class="achseY ? 'erg-mit-achse' : ''">
                            <div x-show.important="achseY"></div>
                            <div class="erg-raster items-center" :style="'grid-template-columns:' + spaltenString">
                                <div class="erg-achse erg-achse-x" :style="'grid-column:' + achseXSpalten">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500" x-text="achsen.x.links"></span>
                                    <div class="flex items-center gap-3 flex-1 mx-6">
                                        <span class="text-xs font-extrabold uppercase tracking-[0.2em] text-slate-700" x-text="achsen.x.titel || 'Achse'"></span>
                                        <div class="erg-achse-linie"></div>
                                        <span class="text-xs font-extrabold uppercase tracking-[0.2em] text-emerald-800" x-text="achsen.x.ziel"></span>
                                    </div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700" x-text="achsen.x.rechts"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="legendeVorschau.length && !legendeImSeitenfeld">
                        <div class="erg-legende" style="flex-direction: row; flex-wrap: wrap; align-items: center; column-gap: 1.25rem;">
                            <div class="erg-legende-titel" x-text="legende.titel || 'Legende'"></div>
                            <template x-for="e in legendeVorschau" :key="e._id">
                                <div class="flex items-center gap-2" :class="'erg-farbe-' + e.farbe"><span class="erg-punkt"></span><span x-text="e.text"></span></div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
            <p class="erg-hint" x-show.important="bereicheFrei">Bei frei änderbaren Feldern steht jedes Hauptfeld in seiner Spalte in einer Zeile; auf schmalen Bildschirmen untereinander.</p>
        </aside>
    </div>
</div>
@endsection
