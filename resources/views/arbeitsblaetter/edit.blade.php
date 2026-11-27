@extends('layouts.app')

@push('css')
    @vite(['resources/css/arbeitsblaetter.css', 'resources/js/arbeitsblaetter.js'])
@endpush

@section('content')
<div class="ab-wrapper ab-editor" x-data="abEditor(@js([
        'blatt' => $daten,
        'typen' => $typen,
        'profile' => $vorschauProfile,
    ]))" x-cloak @keydown.ctrl.s.window.prevent="speichern()" @keydown.meta.s.window.prevent="speichern()" @beforeunload.window="warnen($event)">

    {{-- Kopf --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div class="min-w-0 flex-1">
            <a href="{{ route('arbeitsblaetter.index') }}" class="ab-back mb-2"><i class="fas fa-arrow-left"></i> Bibliothek</a>
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" class="ab-input text-lg font-bold sm:w-96" value="{{ $blatt->titel }}" x-model="blatt.titel" @input="geaendert()" :disabled="!blatt.darf_bearbeiten" maxlength="200" aria-label="Titel">
                <span class="ab-status" :class="'ab-status-' + blatt.status" x-text="blatt.status_text"></span>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <select class="ab-select ab-input-sm w-auto" x-model="blatt.wp_fach_id" @change="geaendert()" :disabled="!blatt.darf_bearbeiten" aria-label="Fach">
                    <option value="">Fach –</option>
                    @foreach($faecher as $fach)<option value="{{ $fach->id }}">{{ $fach->name }}</option>@endforeach
                </select>
                <select class="ab-select ab-input-sm w-auto" x-model="blatt.klassenstufe" @change="geaendert()" :disabled="!blatt.darf_bearbeiten" aria-label="Klassenstufe">
                    <option value="">Klassenstufe –</option>
                    @for($i = 1; $i <= 13; $i++)<option value="{{ $i }}">Klasse {{ $i }}</option>@endfor
                </select>
                <span class="ab-meta" x-show="blatt.ersteller">Erstellt von <span x-text="blatt.ersteller"></span></span>
                <span class="ab-meta" x-show="blatt.ki"><i class="fas fa-robot"></i> <span x-text="blatt.ki ? (blatt.ki.modell + ', ' + blatt.ki.seiten + ' S.') : ''"></span></span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a :href="blatt.urls.ausgabe" class="ab-btn ab-btn-secondary" x-show="blatt.darf_ausgeben" data-tour="ab-ausgabe"><i class="fas fa-print"></i> Ausgabe</a>
            <form method="post" action="{{ route('arbeitsblaetter.duplizieren', $blatt) }}">
                @csrf
                <button type="submit" class="ab-btn ab-btn-ghost" title="Kopie anlegen"><i class="fas fa-copy"></i><span class="hidden sm:inline">Duplizieren</span></button>
            </form>
            @can('delete', $blatt)
                <form method="post" action="{{ route('arbeitsblaetter.destroy', $blatt) }}" onsubmit="return confirm('Blatt wirklich löschen? Fassungen in Wochenplänen bleiben bestehen.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="ab-btn ab-btn-danger" title="Löschen"><i class="fas fa-trash"></i></button>
                </form>
            @endcan
            <button type="button" class="ab-btn ab-btn-ghost" x-show="blatt.darf_bearbeiten && blatt.originale.length" @click="protokollOeffnen()" title="Anfragen an die KI und ihre Antworten (Fehlersuche)"><i class="fas fa-bug"></i><span class="hidden sm:inline">KI-Protokoll</span></button>
            <button type="button" class="ab-icon-btn" data-tour-start="angepasste-arbeitsblaetter-editor" title="Rundgang"><i class="fas fa-question-circle"></i></button>
        </div>
    </div>

    {{-- Hinweise --}}
    <div class="flex flex-col gap-2 mb-4">
        <div class="ab-alert ab-alert-blue" x-show.important="blatt.status === 'erkennung'"><i class="fas fa-spinner fa-spin mt-0.5"></i>
            <div class="flex-1">Das Blatt wird gerade erkannt. Das dauert meist unter einer Minute – die Seite aktualisiert sich von selbst.
                <div class="text-amber-800 mt-1" x-show="blatt.fehler"><i class="fas fa-exclamation-triangle"></i> <span x-text="blatt.fehler"></span></div></div>
            <button type="button" class="ab-btn ab-btn-secondary ab-btn-sm self-start" x-show="blatt.darf_bearbeiten" @click="abbrechen()">Abbrechen und selbst erfassen</button></div>
        <div class="ab-alert ab-alert-red" x-show.important="blatt.status === 'fehler'"><i class="fas fa-exclamation-circle mt-0.5"></i>
            <div><span x-text="blatt.fehler || 'Die Erkennung ist fehlgeschlagen.'"></span> Sie können die Bausteine manuell eingeben<span x-show="!blatt.erkennung_grund"> oder die Erkennung <button type="button" class="underline font-semibold" @click="erkennen()">erneut starten</button></span>.</div></div>
        <div class="ab-alert ab-alert-amber" x-show.important="blatt.status === 'zu_pruefen'"><i class="fas fa-clipboard-check mt-0.5"></i>
            <div>Bitte mit dem Original vergleichen: Texte, Lücken, Reihenfolge und Abbildungen. Danach unten <strong>Freigeben</strong>.</div></div>
        <div class="ab-alert ab-alert-gruen" x-show.important="blatt.status === 'freigegeben'"><i class="fas fa-check-circle mt-0.5"></i>
            <div>Freigegeben<span x-show="blatt.pruefer"> von <span x-text="blatt.pruefer"></span></span> am <span x-text="blatt.freigegeben_at"></span>. Änderungen machen vorhandene Fassungen veraltet – danach erneut freigeben.</div></div>
        <template x-if="blatt.wochenplan">
            <div class="ab-alert ab-alert-blue"><i class="fas fa-calendar-week mt-0.5"></i>
                <div>Aus dem Wochenplan <strong x-text="blatt.wochenplan.plan"></strong> (<span x-text="blatt.wochenplan.klasse"></span>), Datei „<span x-text="blatt.wochenplan.datei"></span>“.
                    <span x-text="blatt.wochenplan.fassungen + ' angepasste Fassung(en) in Kinderplänen.'"></span>
                    <a x-show="blatt.wochenplan.url" :href="blatt.wochenplan.url" class="underline font-semibold">Zum Wochenplan</a></div></div>
        </template>
        <div class="ab-alert ab-alert-blue" x-show.important="blatt.erkennung_grund && blatt.originale.length && blatt.status !== 'freigegeben' && !blatt.struktur.bausteine.length"><i class="fas fa-keyboard mt-0.5"></i>
            <div x-text="blatt.erkennung_grund"></div></div>
    </div>

    <div class="ab-editor-raster">
        {{-- Original --}}
        <section class="ab-original" aria-label="Original" data-tour="ab-original" x-show="blatt.originale.length">
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 border-b border-slate-200">
                <h2 class="ab-card-title"><i class="fas fa-image"></i> Original</h2>
                <div class="flex items-center gap-1">
                    <button type="button" class="ab-icon-btn" @click="zoom = Math.max(0.5, zoom - 0.25)" title="Verkleinern"><i class="fas fa-search-minus"></i></button>
                    <span class="text-xs text-slate-500 w-10 text-center" x-text="Math.round(zoom * 100) + '%'"></span>
                    <button type="button" class="ab-icon-btn" @click="zoom = Math.min(3, zoom + 0.25)" title="Vergrößern"><i class="fas fa-search-plus"></i></button>
                </div>
            </div>
            <div class="ab-alert ab-alert-amber rounded-none border-x-0 border-t-0" x-show.important="schneiden">
                <i class="fas fa-crop-alt mt-0.5"></i>
                <div class="flex-1">Rahmen um die Abbildung ziehen.
                    <label class="inline-flex items-center gap-1 ml-2"><input type="checkbox" x-model="kontrast"> kontrastreich (Schwarz-Weiß)</label></div>
                <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click="schneiden = null">Abbrechen</button>
            </div>
            <div class="ab-original-flaeche" x-ref="original">
                <template x-for="(seite, si) in seiten" :key="si">
                    <div class="ab-original-seite" :class="schneiden && 'ab-schneiden'" :style="`width:${zoom * 100}%`"
                         @pointerdown="boxStart($event, si)" @pointermove="boxZiehen($event)" @pointerup="boxEnde($event)">
                        <img x-show="seite.art === 'bild'" :src="seite.url" :alt="`Seite ${si + 1}`" draggable="false" :data-seite="si" style="width:100%">
                        <canvas x-show="seite.art === 'pdf'" :data-seite="si" style="width:100%"></canvas>
                        <template x-for="h in hinweisBoxen(si)" :key="h.id">
                            <span class="ab-box" :style="boxStil(h.box)" :title="h.beschreibung"></span>
                        </template>
                        <span class="ab-box is-zeichnen" x-show="zeichnung && zeichnung.seite === si" :style="zeichnung ? boxStil(zeichnung.box) : ''"></span>
                    </div>
                </template>
                <p class="p-4 text-sm text-slate-500" x-show="pdfFehler" x-text="pdfFehler"></p>
            </div>
        </section>

        <div class="flex flex-col gap-4 min-w-0" :class="!blatt.originale.length && 'lg:col-span-2'">
            {{-- Bausteine --}}
            <section class="ab-card" aria-label="Bausteine" data-tour="ab-bausteine">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 border-b border-slate-200">
                    <h2 class="ab-card-title"><i class="fas fa-th-list"></i> Bausteine <span class="ab-chip" x-text="anzahl()"></span></h2>
                    <div class="flex flex-wrap gap-1" x-show="blatt.darf_bearbeiten">
                        <template x-for="(label, typ) in typen" :key="typ">
                            <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click="hinzufuegen(typ)" :title="label + ' hinzufügen'"><i class="fas" :class="icon(typ)"></i><span class="hidden xl:inline" x-text="label"></span></button>
                        </template>
                    </div>
                </div>
                <div class="p-3 flex flex-col gap-2" x-ref="liste">
                    <template x-for="(b, i) in blatt.struktur.bausteine" :key="b.id">
                        <div class="ab-baustein" :class="aktiv === b.id && 'is-aktiv'" @click="aktiv = b.id" :data-id="b.id">
                            @include('arbeitsblaetter.partials.baustein', ['ebene' => 0])
                            <template x-if="b.typ === 'aufgabe'">
                                <div class="ab-kinder" :data-eltern="b.id" x-init="kinderSortierbar($el, b)">
                                    <template x-for="(k, ki) in b.kinder" :key="k.id">
                                        <div class="ab-baustein" :class="aktiv === k.id && 'is-aktiv'" @click.stop="aktiv = k.id" :data-id="k.id">
                                            @include('arbeitsblaetter.partials.baustein', ['ebene' => 1])
                                        </div>
                                    </template>
                                    <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm self-start" x-show="blatt.darf_bearbeiten" @click.stop="teilaufgabe(b)"><i class="fas fa-plus"></i> Teilaufgabe</button>
                                </div>
                            </template>
                        </div>
                    </template>
                    <div class="ab-empty" x-show.important="!blatt.struktur.bausteine.length">
                        <i class="fas fa-th-list"></i>
                        <p x-show="blatt.status === 'erkennung'">Die Bausteine erscheinen nach der Erkennung.</p>
                        <p x-show="blatt.status !== 'erkennung'">Noch keine Bausteine. Oben einen Typ wählen, z. B. „Überschrift“ oder „Aufgabe“.</p>
                    </div>
                </div>
            </section>

            {{-- Vorschau --}}
            <section class="ab-vorschau" aria-label="Vorschau" data-tour="ab-vorschau">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 border-b border-slate-200">
                    <h2 class="ab-card-title"><i class="fas fa-eye"></i> Vorschau</h2>
                    <select class="ab-select ab-input-sm w-auto max-w-full" x-model="profil" @change="vorschauLaden()" aria-label="Profil für die Vorschau">
                        <template x-for="p in profile" :key="p.wert"><option :value="p.wert" x-text="p.label"></option></template>
                    </select>
                </div>
                <iframe x-ref="vorschau" title="Vorschau des angepassten Blatts" sandbox="allow-same-origin"></iframe>
            </section>
        </div>
    </div>

    {{-- Leiste --}}
    <div class="ab-leiste" x-show.important="blatt.darf_bearbeiten">
        <div class="text-sm text-slate-500">
            <span x-show="speichert"><i class="fas fa-spinner fa-spin"></i> Speichert …</span>
            <span x-show="!speichert && ungespeichert">Ungespeicherte Änderungen</span>
            <span x-show="!speichert && !ungespeichert && gespeichertUm" x-text="'Gespeichert ' + gespeichertUm"></span>
            <span class="text-red-600 ml-2" x-show="meldungFehler" x-text="meldungFehler"></span>
            <span class="text-emerald-700 ml-2" x-show="meldung" x-text="meldung"></span>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="ab-btn ab-btn-secondary" x-show="blatt.originale.length && blatt.status !== 'erkennung' && !blatt.erkennung_grund" @click="erkennen()" title="Bausteine neu erkennen (werden angehängt)"><i class="fas fa-robot"></i><span class="hidden sm:inline">Erkennen</span></button>
            <button type="button" class="ab-btn ab-btn-secondary" @click="speichern()" :disabled="speichert || !ungespeichert"><i class="fas fa-save"></i> Speichern</button>
            <button type="button" class="ab-btn ab-btn-gruen" @click="freigeben()" :disabled="speichert || !blatt.struktur.bausteine.length" data-tour="ab-freigeben">
                <i class="fas fa-check"></i> <span x-text="blatt.status === 'freigegeben' ? 'Erneut freigeben' : 'Freigeben'"></span>
            </button>
        </div>
    </div>

    {{-- KI-Protokoll (Fehlersuche) --}}
    <template x-if="protokoll">
        <div class="ab-dialog-hintergrund" @keydown.escape.window="protokoll = null" @click.self="protokoll = null">
            <div class="ab-dialog" style="max-width: 72rem" role="dialog" aria-modal="true" aria-labelledby="ab-protokoll-titel">
                <div class="ab-card-body">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h2 id="ab-protokoll-titel" class="ab-card-title"><i class="fas fa-bug"></i> KI-Protokoll</h2>
                        <button type="button" class="ab-icon-btn" @click="protokoll = null" title="Schließen"><i class="fas fa-times"></i></button>
                    </div>
                    <p class="ab-hint mt-0 mb-3">Die letzten Anfragen an die KI für dieses Blatt (30 Tage aufbewahrt). Bilddaten sind durch Platzhalter ersetzt; es werden keine Namen oder Profile gesendet.</p>
                    <p class="text-sm text-slate-500" x-show="!protokoll.length">Noch keine Aufrufe protokolliert.</p>
                    <template x-for="e in protokoll" :key="e.id">
                        <details class="mb-2 rounded-xl border border-slate-200">
                            <summary class="cursor-pointer px-3 py-2 text-sm flex flex-wrap items-center gap-2">
                                <span class="ab-status" :class="e.http_status === 200 ? 'ab-status-freigegeben' : 'ab-status-fehler'" x-text="e.http_status ? 'HTTP ' + e.http_status : 'keine Antwort'"></span>
                                <span class="font-semibold" x-text="e.zeit"></span>
                                <span class="text-slate-500" x-text="e.anbieter + ' · ' + e.modell + ' · ' + e.dauer"></span>
                                <span class="text-red-700" x-show="e.fehler" x-text="e.fehler"></span>
                            </summary>
                            <div class="px-3 pb-3 grid gap-3">
                                <template x-if="e.inhalt">
                                    <div><h3 class="ab-gruppe-titel mt-1">Erkannter Inhalt (vor der Aufbereitung)</h3>
                                        <pre class="text-xs bg-slate-50 border border-slate-200 rounded-lg p-2 overflow-auto max-h-96 whitespace-pre-wrap" x-text="e.inhalt"></pre></div>
                                </template>
                                <template x-if="e.nutzung">
                                    <div><h3 class="ab-gruppe-titel mt-1">Token</h3><pre class="text-xs bg-slate-50 border border-slate-200 rounded-lg p-2 overflow-auto" x-text="JSON.stringify(e.nutzung, null, 2)"></pre></div>
                                </template>
                                <div><h3 class="ab-gruppe-titel mt-1">Anfrage</h3>
                                    <pre class="text-xs bg-slate-50 border border-slate-200 rounded-lg p-2 overflow-auto max-h-96 whitespace-pre-wrap" x-text="e.anfrage"></pre></div>
                                <div><h3 class="ab-gruppe-titel mt-1">Rohe Antwort</h3>
                                    <pre class="text-xs bg-slate-50 border border-slate-200 rounded-lg p-2 overflow-auto max-h-96 whitespace-pre-wrap" x-text="e.antwort || '–'"></pre></div>
                            </div>
                        </details>
                    </template>
                </div>
            </div>
        </div>
    </template>

    {{-- Versionskonflikt --}}
    <template x-if="konflikt">
        <div class="ab-dialog-hintergrund" @keydown.escape.window="konflikt = null">
            <div class="ab-dialog" role="dialog" aria-modal="true" aria-labelledby="ab-konflikt-titel">
                <div class="ab-card-body">
                    <h2 id="ab-konflikt-titel" class="ab-card-title mb-2"><i class="fas fa-exclamation-triangle text-amber-500"></i> Zwischenzeitlich geändert</h2>
                    <p class="text-sm text-slate-600 mb-4">Jemand anderes hat das Blatt gespeichert, während Sie es bearbeitet haben.</p>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="ab-btn ab-btn-secondary" @click="konfliktLaden()">Neu laden (meine Änderungen verwerfen)</button>
                        <button type="button" class="ab-btn ab-btn-primary" @click="konfliktUeberschreiben()">Meine Fassung speichern</button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<x-tour id="angepasste-arbeitsblaetter-editor" :steps="[
    ['target' => 'ab-original', 'title' => 'Original', 'text' => 'Links das fotografierte bzw. hochgeladene Original. Zum Ausschneiden einer Abbildung bei einem Bild-Baustein „Ausschneiden“ wählen und einen Rahmen ziehen.'],
    ['target' => 'ab-bausteine', 'title' => 'Bausteine', 'text' => 'Das erkannte Blatt als Bausteine. Texte korrigieren, Lücken mit ___ (drei oder mehr Unterstriche) markieren, Reihenfolge per Ziehen ändern, Teilaufgaben einer Aufgabe zuordnen.'],
    ['target' => 'ab-vorschau', 'title' => 'Vorschau', 'text' => 'So sieht das Blatt mit einem Sehprofil aus. Schrift und Größe kommen immer aus dem Profil des Kindes – nie von Hand.'],
    ['target' => 'ab-freigeben', 'title' => 'Freigeben', 'text' => 'Nach der Prüfung freigeben. Blätter aus dem Wochenplan landen dann automatisch als angepasste Fassung in den Kinderplänen.'],
    ['target' => 'ab-ausgabe', 'title' => 'Ausgabe', 'text' => 'Fassungen für einzelne Kinder oder Profil-Vorlagen als PDF/Word herunterladen oder in der Nextcloud ablegen.'],
]" />
@endsection
