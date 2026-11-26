@extends('wiki.layout')

@push('js')
    <script src="{{ asset('js/plugins/tinymce/tinymce.min.js') }}"></script>
@endpush

@php
    $neu = $seite === null;
    $konfig = [
        'neu' => $neu,
        'speichernUrl' => $neu ? route('wiki.store') : route('wiki.update', $seite),
        'methode' => $neu ? 'POST' : 'PUT',
        'version' => $seite?->version,
        'uploadUrl' => $neu ? null : route('wiki.anhaenge.store', $seite),
        'bearbeitungUrl' => $neu ? null : route('wiki.bearbeitung', $seite),
        'sucheUrl' => route('wiki.suche'),
        'tagsUrl' => route('wiki.tags.vorschlaege'),
        'wikiPfad' => rtrim((string) parse_url(url('/wiki'), PHP_URL_PATH), '/') ?: '/wiki',
        'tags' => $tags,
        'andere' => $andere,
        'abbrechenUrl' => $neu ? route('wiki') : $seite->url(),
    ];
@endphp

@section('wiki')
<div class="flex flex-col gap-4" x-data="wikiEditor(@js($konfig))">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-sm text-gray-500">{{ $neu ? 'Neue Seite' : 'Seite bearbeiten' }}</div>
            @if(! $neu)
                <a href="{{ $seite->url() }}" class="text-sm font-semibold text-blue-700 hover:underline">{{ $seite->titel }}</a>
            @endif
        </div>
        @if(! $neu && $seite->istGeschuetzt())
            <span class="wiki-badge wiki-badge-grau"><i class="fas fa-shield-alt"></i> {{ $seite->systemseite ? 'Systemseite – wird bei Updates überschrieben' : 'geschützte Seite' }}</span>
        @endif
    </div>

    <div class="wiki-alert wiki-alert-gelb" x-show="andere.length > 0" x-cloak>
        <i class="fas fa-user-edit mt-0.5"></i>
        <div><strong x-text="andere.join(', ')"></strong> <span x-text="andere.length === 1 ? 'bearbeitet diese Seite gerade auch.' : 'bearbeiten diese Seite gerade auch.'"></span>
            Wer zuerst speichert, gewinnt – beim zweiten Speichern sehen Sie die Unterschiede.</div>
    </div>

    <div class="wiki-alert wiki-alert-rot" x-show="fehler" x-cloak>
        <i class="fas fa-exclamation-triangle mt-0.5"></i>
        <div x-text="fehler"></div>
    </div>

    <form class="wiki-karte" @submit.prevent="speichern()">
        <div class="wiki-karte-inhalt flex flex-col gap-4">
            <div>
                <label class="wiki-label" for="wiki-titel">Titel</label>
                <input id="wiki-titel" type="text" x-ref="titel" class="wiki-input wiki-input-titel" maxlength="150" required
                       value="{{ $titel }}" @input="geaendert = true" placeholder="Worum geht es?"
                       @if(! $neu && $seite->istStartseite()) readonly @endif>
                <template x-if="feldFehler.titel"><p class="wiki-fehler" x-text="feldFehler.titel"></p></template>
            </div>

            <div>
                <textarea id="wiki-editor" x-ref="editor" class="wiki-textarea" rows="20">{{ $inhalt }}</textarea>
                <p class="wiki-hinweistext">
                    Bilder einfach mit Strg+V einfügen oder hineinziehen. Hinweisboxen und Wiki-Links finden Sie in der Werkzeugleiste.
                    @if($neu) Dateien zum Herunterladen können Sie nach dem ersten Speichern anhängen. @endif
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @if(! $neu && $seite->istStartseite())
                    <input type="hidden" x-ref="eltern" value="">
                @else
                    <div>
                        <label class="wiki-label" for="wiki-eltern">Übergeordnete Seite</label>
                        <select id="wiki-eltern" x-ref="eltern" class="wiki-select" @change="geaendert = true">
                            <option value="">– oberste Ebene –</option>
                            @foreach($elternOptionen as $id => $text)
                                <option value="{{ $id }}" @selected((int) $parentId === (int) $id)>{{ $text }}</option>
                            @endforeach
                        </select>
                        <template x-if="feldFehler.parent_id"><p class="wiki-fehler" x-text="feldFehler.parent_id"></p></template>
                    </div>
                @endif
                <div>
                    <label class="wiki-label" for="wiki-tag-eingabe">Tags</label>
                    <div class="wiki-tags-eingabe" @click="$refs.tagEingabe.focus()">
                        <template x-for="(tag, i) in tagListe" :key="tag">
                            <span class="wiki-tag-chip"><span x-text="tag"></span><button type="button" @click.stop="tagEntfernen(i)" :aria-label="'Tag ' + tag + ' entfernen'"><i class="fas fa-times text-[9px]"></i></button></span>
                        </template>
                        <input id="wiki-tag-eingabe" type="text" x-ref="tagEingabe" x-model="tagText" @input.debounce.200ms="tagVorschlaegeLaden()"
                               @keydown.enter.prevent="tagHinzufuegen(tagText)" @keydown.comma.prevent="tagHinzufuegen(tagText)" @keydown.backspace="tagText === '' && tagListe.length && tagEntfernen(tagListe.length - 1)"
                               @blur="setTimeout(() => { tagHinzufuegen(tagText); tagVorschlaege = []; }, 150)" placeholder="Tag eingeben, Enter" autocomplete="off">
                        <div class="absolute left-0 right-0 top-full z-30 mt-1 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg" x-show="tagVorschlaege.length" x-cloak>
                            <template x-for="v in tagVorschlaege" :key="v">
                                <button type="button" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-blue-50" @mousedown.prevent="tagHinzufuegen(v)" x-text="v"></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            @if(! $neu)
                <div>
                    <div class="flex items-center justify-between">
                        <span class="wiki-label">Dateien zum Herunterladen</span>
                        <label class="wiki-btn wiki-btn-secondary wiki-btn-sm cursor-pointer">
                            <i class="fas fa-upload"></i> Datei anhängen
                            <input type="file" multiple class="sr-only" @change="dateienHochladen($event)">
                        </label>
                    </div>
                    <div class="rounded-xl border border-gray-200" :class="{ 'border-blue-400 bg-blue-50/50': dateiZiehen }"
                         @dragover.prevent="dateiZiehen = true" @dragleave="dateiZiehen = false" @drop.prevent="dateiZiehen = false; dateienHochladen($event)">
                        <ul class="divide-y divide-gray-100">
                            @foreach($dateien as $datei)
                                <li class="flex items-center gap-3 px-3 py-2 text-sm" x-data="{ weg: false }" x-show="!weg">
                                    <i class="far fa-file text-gray-400"></i>
                                    <a href="{{ route('wiki.anhang', $datei) }}" target="_blank" class="min-w-0 flex-1 truncate text-gray-800 hover:text-blue-700">{{ $datei->file_name }}</a>
                                    <span class="text-xs text-gray-400">{{ $datei->human_readable_size }}</span>
                                    <button type="button" class="wiki-btn wiki-btn-ghost wiki-btn-sm" title="Als Link in den Text einfügen" @click="dateiLinkEinfuegen('{{ route('wiki.anhang', $datei, false) }}', @js($datei->file_name))"><i class="fas fa-link"></i></button>
                                    <button type="button" class="wiki-btn wiki-btn-ghost wiki-btn-sm text-red-600" title="Entfernen" @click="dateiEntfernen('{{ route('wiki.anhaenge.destroy', [$seite, $datei]) }}').then(ok => weg = ok)"><i class="fas fa-trash-alt"></i></button>
                                </li>
                            @endforeach
                            <template x-for="d in neueDateien" :key="d.id">
                                <li class="flex items-center gap-3 px-3 py-2 text-sm">
                                    <i class="far fa-file text-emerald-500"></i>
                                    <a :href="d.url" target="_blank" class="min-w-0 flex-1 truncate text-gray-800" x-text="d.name"></a>
                                    <span class="text-xs text-gray-400" x-text="d.groesse"></span>
                                    <button type="button" class="wiki-btn wiki-btn-ghost wiki-btn-sm" title="Als Link in den Text einfügen" @click="dateiLinkEinfuegen(d.url, d.name)"><i class="fas fa-link"></i></button>
                                </li>
                            </template>
                        </ul>
                        <p class="px-3 py-2 text-xs text-gray-400" x-show="!{{ $dateien->count() }} && !neueDateien.length">Noch keine Dateien – hierher ziehen oder „Datei anhängen“.</p>
                        <p class="px-3 py-2 text-xs text-blue-700" x-show="hochladen" x-cloak><i class="fas fa-spinner fa-spin"></i> wird hochgeladen …</p>
                    </div>
                </div>
            @endif

            <div>
                <label class="wiki-label" for="wiki-notiz">Was wurde geändert? <span class="font-normal text-gray-400">(optional)</span></label>
                <input id="wiki-notiz" type="text" x-ref="notiz" class="wiki-input" maxlength="250" placeholder="{{ $neu ? 'z. B. erste Fassung' : 'z. B. Abschnitt zur Anmeldung ergänzt' }}">
            </div>
        </div>

        <div class="wiki-editor-leiste">
            <button type="submit" class="wiki-btn wiki-btn-primary" :disabled="speichert">
                <i class="fas" :class="speichert ? 'fa-spinner fa-spin' : 'fa-check'"></i> Speichern
            </button>
            <a href="{{ $konfig['abbrechenUrl'] }}" class="wiki-btn wiki-btn-ghost" @click="abbrechen($event)">Abbrechen</a>
            <span class="ml-auto hidden text-xs text-gray-400 sm:inline">Strg+S speichert</span>
        </div>
    </form>

    {{-- Wiki-Link einfügen --}}
    <div class="wiki-dialog-huelle" x-show="linkDialog" x-cloak @keydown.escape.window="linkDialog = false">
        <div class="wiki-dialog max-w-xl" @click.outside="linkDialog = false">
            <div class="wiki-dialog-kopf">
                <h2 class="text-base font-bold">Wiki-Seite verlinken</h2>
                <button type="button" class="wiki-btn wiki-btn-ghost wiki-btn-icon" @click="linkDialog = false" aria-label="Schließen"><i class="fas fa-times"></i></button>
            </div>
            <div class="wiki-dialog-inhalt flex flex-col gap-3">
                <input type="search" x-ref="linkSuche" x-model="linkBegriff" @input.debounce.200ms="linkSuchen()" class="wiki-input" placeholder="Seitentitel suchen …" @keydown.enter.prevent="linkTreffer[0] && linkEinfuegen(linkTreffer[0])">
                <div class="max-h-80 overflow-y-auto rounded-xl border border-gray-100" x-show="linkTreffer.length">
                    <template x-for="t in linkTreffer" :key="t.id">
                        <button type="button" class="block w-full border-b border-gray-100 px-3 py-2 text-left hover:bg-blue-50" @click="linkEinfuegen(t)">
                            <span class="block text-xs text-gray-400" x-text="t.pfad"></span>
                            <span class="block text-sm font-semibold text-gray-900" x-text="t.titel"></span>
                        </button>
                    </template>
                </div>
                <div class="text-sm text-gray-500" x-show="linkBegriff.length >= 2 && !linkTreffer.length">
                    Keine Seite gefunden.
                    <button type="button" class="font-semibold text-blue-700 hover:underline" @click="linkNeueSeite()">„<span x-text="linkBegriff"></span>“ als neue Seite verlinken</button>
                    <span class="block text-xs text-gray-400">Der Link erscheint rot, bis jemand die Seite anlegt.</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Konflikt beim Speichern --}}
    <div class="wiki-dialog-huelle" x-show="konflikt" x-cloak>
        <div class="wiki-dialog">
            <div class="wiki-dialog-kopf">
                <h2 class="text-base font-bold"><i class="fas fa-code-branch text-amber-500 mr-1.5"></i> Die Seite wurde inzwischen geändert</h2>
            </div>
            <div class="wiki-dialog-inhalt flex flex-col gap-3">
                <p class="text-sm text-gray-700">
                    <strong x-text="konflikt?.von"></strong> hat die Seite am <span x-text="konflikt?.zeit"></span> gespeichert, während Sie bearbeitet haben.
                    Ihre Fassung ist nicht verloren – sie steht weiter im Editor.
                </p>
                <div class="text-xs text-gray-500"><span class="rounded bg-red-200/70 px-1">rot</span> nur in der gespeicherten Fassung, <span class="rounded bg-emerald-200/70 px-1">grün</span> nur in Ihrer Fassung:</div>
                <div class="wiki-diff max-h-[50vh] overflow-y-auto rounded-xl border border-gray-200 p-2" x-html="konflikt?.diff"></div>
            </div>
            <div class="wiki-dialog-fuss">
                <button type="button" class="wiki-btn wiki-btn-ghost" @click="konflikt = null">Weiter bearbeiten</button>
                <a :href="konfig.abbrechenUrl" target="_blank" class="wiki-btn wiki-btn-secondary"><i class="fas fa-external-link-alt"></i> Gespeicherte Fassung ansehen</a>
                <button type="button" class="wiki-btn wiki-btn-primary" @click="trotzdemSpeichern()"><i class="fas fa-check"></i> Meine Fassung speichern</button>
            </div>
        </div>
    </div>
</div>
@endsection
