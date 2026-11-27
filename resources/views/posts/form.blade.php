@extends('layouts.app')

@push('css')
    @vite(['resources/css/posts.css', 'resources/js/posts.js'])
@endpush

@push('js')
    <script src="{{ asset('js/plugins/tinymce/tinymce.min.js') }}"></script>
@endpush

@php
    $bearbeiten = $post->exists;
    $warVeroeffentlicht = $bearbeiten && $post->warVeroeffentlicht();
    $ausgewaehlt = collect(old('groups', $ausgewaehlt))->map(fn ($id) => (int) $id)->all();
    $bestaetigung = (bool) old('bestaetigung_erforderlich', $post->bestaetigung_erforderlich);
    $hatteBestaetigung = $warVeroeffentlicht && $post->bestaetigung_erforderlich;
    $maxMb = max(1, (int) round(((int) config('app.maxFileSize', 15000)) / 1024));
@endphp

@section('content')
<div class="nachr-wrapper">
    <a href="{{ $zurueck }}" class="nachr-back"><i class="fas fa-arrow-left"></i> Zurück</a>

    <div class="mb-5">
        <h1 class="nachr-page-title">{{ $bearbeiten ? 'Nachricht bearbeiten' : 'Neue Nachricht' }}</h1>
        <p class="nachr-page-sub">Kurze Ankündigungen oder ausführliche Informationen für eine oder mehrere Gruppen.</p>
    </div>

    @if($errors->any())
        <div class="nachr-alert nachr-alert-amber mb-4">
            <i class="fas fa-exclamation-triangle mt-0.5"></i>
            <ul>
                @foreach($errors->all() as $fehler)
                    <li>{{ $fehler }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" enctype="multipart/form-data" data-nachr-form
          action="{{ $bearbeiten ? route('posts.update', $post) : route('posts.store') }}"
          x-data="{ bestaetigung: {{ $bestaetigung ? 'true' : 'false' }} }"
          class="grid gap-5 lg:grid-cols-3">
        @csrf
        @if($bearbeiten) @method('put') @endif
        <input type="hidden" name="_formular" value="1">

        {{-- Inhalt --}}
        <section class="nachr-card lg:col-span-2">
            <div class="nachr-card-body flex flex-col gap-5">
                <div>
                    <label for="header" class="nachr-label">Überschrift <span class="nachr-required">*</span></label>
                    <input type="text" id="header" name="header" maxlength="255" required autocomplete="off"
                           value="{{ old('header', $post->header) }}"
                           class="nachr-input @error('header') is-invalid @enderror">
                    @error('header')<p class="nachr-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="kurzfassung" class="nachr-label">Kurzfassung <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea id="kurzfassung" name="kurzfassung" rows="2" maxlength="500"
                              class="nachr-textarea @error('kurzfassung') is-invalid @enderror"
                              placeholder="Ein bis zwei Sätze für das Dashboard und die Benachrichtigung">{{ old('kurzfassung', $post->kurzfassung) }}</textarea>
                    <p class="nachr-hint">Ohne Kurzfassung wird der Anfang des Textes angezeigt.</p>
                    @error('kurzfassung')<p class="nachr-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="text" class="nachr-label">Text</label>
                    <textarea id="text" name="text" rows="12" class="nachr-textarea nachr-editor">{{ old('text', $post->text) }}</textarea>
                    @error('text')<p class="nachr-error">{{ $message }}</p>@enderror
                </div>

                {{-- Anhänge --}}
                <div x-data="nachrFiles({{ $maxMb }})">
                    <span class="nachr-label">Anhänge</span>
                    <label class="nachr-dropzone" :class="{ 'is-over': dragover }"
                           @dragover.prevent="dragover = true" @dragleave.prevent="dragover = false" @drop.prevent="onDrop($event)">
                        <i class="fas fa-cloud-upload-alt text-2xl"></i>
                        <span>Dateien hierher ziehen oder <span class="text-blue-600 font-semibold">auswählen</span></span>
                        <span class="text-xs">Bilder werden in der Nachricht angezeigt, andere Dateien zum Herunterladen angeboten (max. {{ $maxMb }} MB je Datei).</span>
                        <input type="file" name="files[]" multiple class="sr-only" x-ref="input" @change="onChange($event)">
                    </label>
                    <ul class="mt-2 flex flex-col gap-1" x-show.important="files.length" x-cloak>
                        <template x-for="(file, index) in files" :key="file.name + index">
                            <li class="flex items-center gap-2 text-sm rounded-lg bg-gray-50 px-3 py-1.5" :class="{ 'text-red-600': file.tooBig }">
                                <i class="fas fa-file text-gray-400"></i>
                                <span class="flex-1 truncate" x-text="file.name"></span>
                                <span class="text-xs text-gray-400" x-text="file.size"></span>
                                <button type="button" class="text-gray-400 hover:text-red-600" @click="remove(index)" title="Entfernen"><i class="fas fa-times"></i></button>
                            </li>
                        </template>
                    </ul>
                    <p class="nachr-error" x-show="error" x-text="error" x-cloak></p>
                    @error('files.*')<p class="nachr-error">{{ $message }}</p>@enderror

                    @if($bearbeiten && $post->media->isNotEmpty())
                        <ul class="mt-3 flex flex-col gap-1">
                            @foreach($post->media as $media)
                                <li class="flex items-center gap-2 text-sm rounded-lg border border-gray-200 px-3 py-1.5">
                                    <i class="fas {{ $media->collection_name === 'images' ? 'fa-image' : 'fa-file' }} text-gray-400"></i>
                                    <a href="{{ route('posts.files', [$post, $media]) }}" target="_blank" rel="noopener" class="flex-1 truncate hover:text-blue-600">{{ $media->file_name }}</a>
                                    <button type="submit" form="datei-loeschen-{{ $media->id }}" class="text-gray-400 hover:text-red-600" title="Datei entfernen"
                                            onclick="return confirm('Datei entfernen?')"><i class="fas fa-trash"></i></button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>

        {{-- Einstellungen --}}
        <div class="flex flex-col gap-5">
            <section class="nachr-card">
                <div class="nachr-card-head"><h2 class="nachr-card-title"><i class="fas fa-users"></i> Gruppen <span class="nachr-required">*</span></h2></div>
                <div class="nachr-card-body">
                    @if($gruppen->isEmpty())
                        <p class="text-sm text-gray-500">Sie dürfen in keine Gruppe posten.</p>
                    @else
                        <div class="nachr-gruppen @error('groups') border-red-400 @enderror">
                            @foreach($gruppen as $gruppe)
                                <label>
                                    <input type="checkbox" name="groups[]" value="{{ $gruppe->id }}" class="accent-blue-600" @checked(in_array($gruppe->id, $ausgewaehlt, true))>
                                    <span class="truncate">{{ $gruppe->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('groups')<p class="nachr-error">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="nachr-card">
                <div class="nachr-card-head"><h2 class="nachr-card-title"><i class="fas fa-sliders-h"></i> Anzeige</h2></div>
                <div class="nachr-card-body flex flex-col gap-4">
                    <label class="nachr-check">
                        <input type="checkbox" name="auf_dashboard" value="1" @checked(old('auf_dashboard', $bearbeiten ? $post->auf_dashboard : true))>
                        <span>Auch auf dem Dashboard zeigen<span class="nachr-hint block">Sonst erscheint die Nachricht nur auf der Seite „Nachrichten“ der Gruppe.</span></span>
                    </label>
                    <div>
                        <label for="sichtbar_bis" class="nachr-label">Sichtbar bis <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="date" id="sichtbar_bis" name="sichtbar_bis" class="nachr-input"
                               value="{{ old('sichtbar_bis', $post->sichtbar_bis?->format('Y-m-d')) }}">
                        <p class="nachr-hint">Danach wird die Nachricht automatisch archiviert.</p>
                        @error('sichtbar_bis')<p class="nachr-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="nachr-card">
                <div class="nachr-card-head"><h2 class="nachr-card-title"><i class="fas fa-signature"></i> Lesebestätigung</h2></div>
                <div class="nachr-card-body flex flex-col gap-4">
                    @if($darfBestaetigung)
                        <label class="nachr-check">
                            <input type="checkbox" name="bestaetigung_erforderlich" value="1" x-model="bestaetigung">
                            <span>Lesebestätigung anfordern<span class="nachr-hint block">Alle Mitglieder der Gruppen müssen „Gelesen und verstanden“ bestätigen – auch später hinzukommende.</span></span>
                        </label>
                        <div x-show="bestaetigung" x-cloak>
                            <label for="bestaetigung_frist" class="nachr-label">Frist <span class="text-gray-400 font-normal">(optional)</span></label>
                            <input type="date" id="bestaetigung_frist" name="bestaetigung_frist" class="nachr-input @error('bestaetigung_frist') is-invalid @enderror"
                                   value="{{ old('bestaetigung_frist', $post->bestaetigung_frist?->format('Y-m-d')) }}">
                            <p class="nachr-hint">Erinnerung an Offene 2 Tage vorher und am Fristtag.</p>
                            @error('bestaetigung_frist')<p class="nachr-error">{{ $message }}</p>@enderror
                        </div>
                    @else
                        <input type="hidden" name="bestaetigung_erforderlich" value="{{ $post->bestaetigung_erforderlich ? 1 : 0 }}">
                        <input type="hidden" name="bestaetigung_frist" value="{{ $post->bestaetigung_frist?->format('Y-m-d') }}">
                        <p class="text-sm text-gray-500">
                            {{ $post->bestaetigung_erforderlich ? 'Für diese Nachricht ist eine Lesebestätigung angefordert.' : 'Lesebestätigungen dürfen nur Personen mit entsprechendem Recht anfordern.' }}
                        </p>
                    @endif

                    @if($hatteBestaetigung)
                        <div class="flex flex-col gap-2" x-show="bestaetigung">
                            <span class="nachr-label mb-0">Bisherige Bestätigungen</span>
                            <label class="nachr-option">
                                <input type="radio" name="bestaetigungen_zuruecksetzen" value="0" class="mt-1 accent-blue-600" @checked(! old('bestaetigungen_zuruecksetzen'))>
                                <span class="text-sm"><strong>Behalten</strong><span class="block text-xs text-gray-500">Nur Korrektur (z. B. Tippfehler)</span></span>
                            </label>
                            <label class="nachr-option">
                                <input type="radio" name="bestaetigungen_zuruecksetzen" value="1" class="mt-1 accent-blue-600" @checked(old('bestaetigungen_zuruecksetzen'))>
                                <span class="text-sm"><strong>Zurücksetzen</strong><span class="block text-xs text-gray-500">Wesentliche Änderung – alle müssen erneut bestätigen</span></span>
                            </label>
                        </div>
                    @endif
                </div>
            </section>

            <div class="flex flex-col gap-2">
                @if($warVeroeffentlicht)
                    <input type="hidden" name="released" value="1">
                    <button type="submit" class="nachr-btn nachr-btn-primary"><i class="fas fa-save"></i> Speichern</button>
                @else
                    <button type="submit" name="released" value="1" class="nachr-btn nachr-btn-primary"><i class="fas fa-paper-plane"></i> Veröffentlichen</button>
                    <button type="submit" name="released" value="0" class="nachr-btn nachr-btn-secondary"><i class="fas fa-pen"></i> Als Entwurf speichern</button>
                @endif
            </div>
        </div>
    </form>

    {{-- Lösch-Formulare für vorhandene Anhänge (außerhalb des Hauptformulars) --}}
    @if($bearbeiten)
        @foreach($post->media as $media)
            <form method="post" action="{{ route('posts.files.destroy', [$post, $media]) }}" id="datei-loeschen-{{ $media->id }}" class="hidden">
                @csrf
                @method('delete')
            </form>
        @endforeach
    @endif
</div>
@endsection
