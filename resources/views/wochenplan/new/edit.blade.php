@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-container p-4">

    {{-- Zurück-Link --}}
    @if($wpPlan->isSchuelerplan() && $wpPlan->parentPlan)
        <a href="{{ route('wp.edit', $wpPlan->parentPlan) }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            ← Zurück zum Klassenplan
        </a>
    @else
        <a href="{{ route('wp.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Übersicht
        </a>
    @endif

    {{-- Schülerplan-Infobanner --}}
    @if($wpPlan->isSchuelerplan())
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
            <div class="flex items-center gap-2">
                <span class="text-amber-600 text-base">👤</span>
                <span class="font-medium text-sm text-amber-900">
                    Individueller Plan für
                    <strong>{{ $wpPlan->schueler?->vorname }} {{ $wpPlan->schueler?->nachname }}</strong>
                </span>
            </div>
            @if($wpPlan->parentPlan)
                <div class="mt-1 text-sm text-gray-600">
                    Basiert auf:
                    <a href="{{ route('wp.edit', $wpPlan->parentPlan) }}" class="text-primary-600 underline hover:text-primary-700">
                        {{ $wpPlan->parentPlan->name }}
                    </a>
                </div>
            @endif
        </div>
    @endif

    {{-- Plan-Header --}}
    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4" x-data="{ editMeta: false }">
        <div x-show="!editMeta">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $wpPlan->is_vorlage ? 'bg-purple-100 text-purple-700' : ($wpPlan->isSchuelerplan() ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') }}">
                            {{ $wpPlan->typ }}
                        </span>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">{{ $wpPlan->name }}</h1>
                    <div class="flex flex-wrap gap-3 mt-1 text-sm text-gray-500">
                        @if($wpPlan->klasse)
                            <span>{{ $wpPlan->klasse->name }}</span>
                        @endif
                        @if($wpPlan->schueler)
                            <span>{{ $wpPlan->schueler->vorname }} {{ $wpPlan->schueler->nachname }}</span>
                        @endif
                        @if($wpPlan->gueltig_von)
                            <span>{{ $wpPlan->zeitraum }}</span>
                        @endif
                    </div>
                </div>
                @canany(['create wochenplan', 'create Wochenplan'])
                    <button @click="editMeta = true"
                            class="px-3 py-1.5 text-xs text-gray-500 border border-gray-300 rounded-md hover:bg-gray-50 flex-shrink-0">
                        Metadaten bearbeiten
                    </button>
                @endcanany
            </div>
        </div>

        {{-- Metadaten-Bearbeitung --}}
        @canany(['create wochenplan', 'create Wochenplan'])
            <div x-show="editMeta" x-cloak>
                <form method="POST" action="{{ route('wp.update', $wpPlan) }}">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Bezeichnung</label>
                            <input type="text" name="name" value="{{ old('name', $wpPlan->name) }}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Gültig von</label>
                            <input type="date" name="gueltig_von" value="{{ old('gueltig_von', $wpPlan->gueltig_von?->format('Y-m-d')) }}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Gültig bis</label>
                            <input type="date" name="gueltig_bis" value="{{ old('gueltig_bis', $wpPlan->gueltig_bis?->format('Y-m-d')) }}" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Selbsteinschätzung</label>
                            <select name="selbsteinschaetzung"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                                <option value="0" {{ $wpPlan->selbsteinschaetzung == 0 ? 'selected' : '' }}>Keine</option>
                                <option value="1" {{ $wpPlan->selbsteinschaetzung == 1 ? 'selected' : '' }}>😊 Smiley</option>
                                <option value="2" {{ $wpPlan->selbsteinschaetzung == 2 ? 'selected' : '' }}>📊 Skala 1–10</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Formatvorlage</label>
                            <select name="formatvorlage_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                                <option value="">Standard</option>
                                @foreach($formatvorlagen as $fv)
                                    <option value="{{ $fv->id }}" {{ $wpPlan->formatvorlage_id == $fv->id ? 'selected' : '' }}>
                                        {{ $fv->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Versteckte Felder für Validierung --}}
                    <input type="hidden" name="klasse_id" value="{{ $wpPlan->klasse_id }}">
                    <input type="hidden" name="schueler_id" value="{{ $wpPlan->schueler_id }}">
                    <div class="flex gap-2 mt-4">
                        <button type="submit"
                                class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">
                            Speichern
                        </button>
                        <button type="button" @click="editMeta = false"
                                class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        @endcanany
    </div>

    {{-- Aktions-Leiste --}}
    @canany(['create wochenplan', 'create Wochenplan'])
        <div class="flex flex-wrap gap-2 mb-4">
            {{-- Export --}}
            @if($wpPlan->getMedia('arbeitsblaetter')->count() > 0)
                <div x-data="{ openPdf: false }" class="relative inline-block">
                    <div class="inline-flex rounded-md shadow-sm">
                        <a href="{{ route('wp.export.pdf', $wpPlan) }}" target="_blank"
                           class="inline-flex items-center px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-l-md hover:bg-red-700">
                            📄 PDF (mit {{ $wpPlan->getMedia('arbeitsblaetter')->count() }} {{ $wpPlan->getMedia('arbeitsblaetter')->count() === 1 ? 'Anhang' : 'Anhängen' }})
                        </a>
                        <button @click="openPdf = !openPdf" type="button"
                                class="inline-flex items-center px-2 py-2 bg-red-700 text-white text-sm rounded-r-md hover:bg-red-800 border-l border-red-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                    </div>
                    <div x-show="openPdf" @click.away="openPdf = false" x-transition
                         class="absolute left-0 mt-1 w-64 bg-white rounded-md shadow-lg border border-gray-200 z-50">
                        <a href="{{ route('wp.export.pdf', $wpPlan) }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-t-md">
                            📄 PDF mit Arbeitsblättern
                        </a>
                        <a href="{{ route('wp.export.pdf', [$wpPlan, 'attachments' => 0]) }}" target="_blank"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-b-md">
                            📄 PDF ohne Arbeitsblätter
                        </a>
                    </div>
                </div>
            @else
                <a href="{{ route('wp.export.pdf', $wpPlan) }}" target="_blank"
                   class="inline-flex items-center px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">
                    📄 PDF
                </a>
            @endif
            <a href="{{ route('wp.export.word', $wpPlan) }}"
               class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                📝 Word
            </a>
            <a href="{{ route('wp.export.vorschau', $wpPlan) }}" target="_blank"
               class="inline-flex items-center px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                👁️ Vorschau
            </a>

            {{-- Duplizieren --}}
            <form method="POST" action="{{ route('wp.duplizieren', $wpPlan) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-3 py-2 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700">
                    📋 Duplizieren
                </button>
            </form>

            {{-- Kinderplan erstellen (nur für Klassenpläne) --}}
            @if($wpPlan->isKlassenplan())
                <a href="{{ route('wp.schuelerplan.create', $wpPlan) }}"
                   class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    👤 Schülerplan erstellen
                </a>
            @endif

            {{-- Als Vorlage speichern --}}
            <div x-data="{ open: false, name: '' }" class="relative">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center px-3 py-2 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700">
                    💾 Als Vorlage
                </button>
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute left-0 top-full mt-1 z-20 bg-white border border-gray-200 rounded-lg shadow-lg p-4 w-64">
                    <form action="{{ route('wp.vorlagen.speichern', $wpPlan) }}" method="POST">
                        @csrf
                        <label class="block text-xs font-medium text-gray-700 mb-1">Vorlagenname</label>
                        <input type="text" name="vorlage_name" x-model="name"
                               placeholder="z.B. Basis-Wochenplan..."
                               class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-purple-400"
                               required>
                        <button type="submit"
                                class="w-full px-3 py-1.5 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700">
                            Speichern
                        </button>
                    </form>
                </div>
            </div>

            {{-- Löschen --}}
            <form method="POST" action="{{ route('wp.destroy', $wpPlan) }}"
                  onsubmit="return confirm('Plan wirklich löschen?')" class="ml-auto">
                @csrf @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center px-3 py-2 bg-white border border-red-300 text-red-600 text-sm font-medium rounded-md hover:bg-red-50">
                    🗑️ Löschen
                </button>
            </form>
        </div>
    @endcanany

    {{-- Synchronisation: Alle Fächer (nur für Kinderpläne mit Elternplan) --}}
    @if($wpPlan->isSchuelerplan() && $wpPlan->parent_plan_id)
        <div x-data="{ confirmSyncAll: false }" class="mb-4">
            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between gap-3">
                <span class="text-sm text-blue-700">
                    🔗 Alle Fächer vom Klassenplan synchronisieren
                </span>
                <button @click="confirmSyncAll = true"
                        class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700">
                    🔄 Alle synchronisieren
                </button>
            </div>

            {{-- Bestätigungs-Dialog via x-teleport --}}
            <template x-teleport="body">
                <div x-show="confirmSyncAll"
                     x-cloak
                     class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
                     @click.self="confirmSyncAll = false">
                    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md mx-4">
                        <h3 class="font-semibold text-gray-900 mb-2">Alle Fächer synchronisieren?</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Alle Aufgaben in diesem Kinderplan werden durch die aktuellen Aufgaben des Klassenplans ersetzt.
                            <strong>Diese Aktion kann nicht rückgängig gemacht werden.</strong>
                        </p>
                        <div class="flex gap-2 justify-end">
                            <button type="button"
                                    @click="confirmSyncAll = false"
                                    class="px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                                Abbrechen
                            </button>
                            <form action="{{ route('wp.sync.all', $wpPlan) }}" method="POST">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Synchronisieren
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif

    {{-- Kinderpläne-Bereich (nur für Klassenpläne) --}}
    @if($wpPlan->isKlassenplan())
        <div class="mb-4">
            @if($wpPlan->kinderPlaene->count() > 0)
                <div class="bg-white rounded-lg border border-gray-200 p-4 mb-3">
                    <h3 class="text-sm font-semibold text-gray-800 mb-3">
                        👤 Individuelle Kinderpläne ({{ $wpPlan->kinderPlaene->count() }})
                    </h3>
                    <div class="space-y-2">
                        @foreach($wpPlan->kinderPlaene->sortBy(fn($k) => $k->schueler?->nachname) as $kinderPlan)
                            <div class="flex items-center justify-between bg-gray-50 rounded-md border border-gray-200 px-3 py-2">
                                <div>
                                    <span class="text-sm font-medium text-gray-900">
                                        👤 {{ $kinderPlan->schueler?->vorname }} {{ $kinderPlan->schueler?->nachname }}
                                    </span>
                                    @if($kinderPlan->formatvorlage)
                                        <span class="text-xs text-gray-500 ml-2">{{ $kinderPlan->formatvorlage->name }}</span>
                                    @endif
                                </div>
                                <div class="flex gap-3 text-sm">
                                    <a href="{{ route('wp.edit', $kinderPlan) }}"
                                       class="text-primary-600 hover:text-primary-700 hover:underline">
                                        Bearbeiten
                                    </a>
                                    <a href="{{ route('wp.export.pdf', $kinderPlan) }}" target="_blank"
                                       class="text-red-600 hover:text-red-700 hover:underline">
                                        PDF
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            {{-- Kinderplan erstellen Button --}}
            @canany(['create wochenplan', 'create Wochenplan'])
                <a href="{{ route('wp.schuelerplan.create', $wpPlan) }}"
                   class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700">
                    👤 Kinderplan erstellen
                </a>
            @endcanany
        </div>
    @endif

    {{-- TÄGLICHE ÜBUNGEN (optional, oberhalb der Fächer) --}}
    @if($wpPlan->taegliche_uebungen_aktiv)
        @include('wochenplan.new.components.taegliche-uebungen', ['wpPlan' => $wpPlan])
    @else
        @canany(['create wochenplan', 'create Wochenplan'])
            <div class="mb-4">
                <form method="POST" action="{{ route('wp.taegliche-uebungen.toggle', $wpPlan) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Tägliche Übungen aktivieren
                    </button>
                </form>
            </div>
        @endcanany
    @endif

    {{-- FÄCHER MIT AUFGABEN --}}
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-700 mb-3">Fächer & Aufgaben</h2>

        @forelse($wpPlan->planFaecher as $planFach)
            @include('wochenplan.new.components.fach-row', ['planFach' => $planFach, 'plan' => $wpPlan])
        @empty
            <div class="text-center py-8 text-gray-400">
                <p class="text-sm">Keine Fächer vorhanden.</p>
                <p class="text-xs mt-1">Füge unten ein Fach hinzu.</p>
            </div>
        @endforelse
    </div>

    {{-- Fach hinzufügen --}}
    @canany(['create wochenplan', 'create Wochenplan'])
        <div x-data="fachSelector()" class="mb-4">
            <form method="POST" action="{{ route('wp.fach.add', $wpPlan) }}" class="flex items-center gap-3">
                @csrf
                <select name="wp_fach_id" required
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                    <option value="">– Fach auswählen –</option>
                    @foreach($alleFaecher as $fach)
                        @php
                            $bereitsVorhanden = $wpPlan->planFaecher->contains('wp_fach_id', $fach->id);
                        @endphp
                        <option value="{{ $fach->id }}" {{ $bereitsVorhanden ? 'disabled' : '' }}>
                            {{ $fach->name }}{{ $bereitsVorhanden ? ' (bereits vorhanden)' : '' }}
                        </option>
                    @endforeach
                </select>
                <button type="submit"
                        class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">
                    + Fach hinzufügen
                </button>
            </form>
        </div>
    @endcanany

    {{-- Offene Tagebuch-Aufgaben (nur bei Schülerplänen mit offenen Aufgaben) --}}
    @if($wpPlan->isSchuelerplan())
        @include('wochenplan.new.components.diary-tasks-panel', [
            'diaryTasks'  => $diaryTasks ?? collect(),
            'plan'        => $wpPlan,
            'planFaecher' => $wpPlan->planFaecher,
        ])
    @endif

    {{-- Arbeitsblätter --}}
    @if($wpPlan->media->count() > 0 || auth()->user()->canAny(['create wochenplan', 'create Wochenplan']))
        <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
            {{-- Arbeitsblätter-Header mit Sync-Button --}}
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">📎 Arbeitsblätter</h3>

                {{-- Sync-Button nur bei Kinderplänen mit Elternplan --}}
                @if($wpPlan->parent_plan_id)
                    @canany(['create wochenplan', 'create Wochenplan'])
                        <form method="POST" action="{{ route('wp.media.sync', $wpPlan) }}">
                            @csrf
                            <button type="submit"
                                    class="text-xs px-3 py-1.5 bg-blue-50 text-blue-700 rounded-md hover:bg-blue-100 transition-colors"
                                    onclick="return confirm('Synchronisierte Dateien vom Klassenplan aktualisieren?\n\nEigene Dateien bleiben erhalten.\nBereits synchronisierte Dateien werden durch die aktuelle Version ersetzt.')">
                                🔄 Dateien vom Klassenplan sync
                            </button>
                        </form>
                    @endcanany
                @endif
            </div>

            @if($wpPlan->getMedia('arbeitsblaetter')->count() > 0)
                <ul class="space-y-2 mb-4">
                    @foreach($wpPlan->getMedia('arbeitsblaetter') as $media)
                        <li class="flex items-center justify-between bg-gray-50 rounded border border-gray-200 px-3 py-2">
                            <div class="flex items-center gap-2 min-w-0">
                                {{-- Datei-Icon --}}
                                <span class="flex-shrink-0">
                                    @if(str_contains($media->mime_type, 'pdf'))📄
                                    @elseif(str_contains($media->mime_type, 'image'))🖼️
                                    @elseif(str_contains($media->mime_type, 'word'))📝
                                    @else📎
                                    @endif
                                </span>

                                {{-- Sync-Badge --}}
                                @if($media->getCustomProperty('synced_from_plan_id'))
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-600 flex-shrink-0"
                                          title="Synchronisiert am {{ \Carbon\Carbon::parse($media->getCustomProperty('synced_at'))->format('d.m.Y H:i') }}">
                                        🔗 sync
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-600 flex-shrink-0"
                                          title="Eigene Datei">
                                        ✨ eigene
                                    </span>
                                @endif

                                {{-- Dateiname + Größe --}}
                                <a href="{{ $media->getUrl() }}" target="_blank"
                                   class="text-sm text-primary-600 hover:underline truncate">
                                    {{ $media->name ?? $media->file_name }}
                                </a>
                                <span class="text-xs text-gray-400 flex-shrink-0">
                                    ({{ $media->size >= 1048576
                                        ? number_format($media->size / 1048576, 1) . ' MB'
                                        : number_format($media->size / 1024, 0) . ' KB' }})
                                </span>
                            </div>
                            @canany(['create wochenplan', 'create Wochenplan'])
                                <form method="POST" action="{{ route('wp.media.remove', $media) }}"
                                      onsubmit="return confirm('Datei wirklich entfernen?')" class="ml-3 flex-shrink-0">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-sm text-red-500 hover:text-red-700" title="Entfernen">🗑️</button>
                                </form>
                            @endcanany
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-400 mb-4">Keine Arbeitsblätter angehängt.</p>
            @endif

            @canany(['create wochenplan', 'create Wochenplan'])
                @if($errors->has('files') || $errors->has('files.*'))
                    <div class="mb-2 text-xs text-red-600">
                        @foreach($errors->get('files.*') as $msgs)
                            @foreach($msgs as $msg)<div>{{ $msg }}</div>@endforeach
                        @endforeach
                    </div>
                @endif
                <form method="POST" action="{{ route('wp.media.add', $wpPlan) }}" enctype="multipart/form-data"
                      class="flex items-center gap-3">
                    @csrf
                    <input type="file" name="files[]" multiple
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.odt"
                           class="text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                    <button type="submit"
                            class="px-3 py-1.5 bg-primary-600 text-white text-xs font-medium rounded-md hover:bg-primary-700">
                        Hochladen
                    </button>
                </form>
                <p class="text-xs text-gray-400 mt-1">Erlaubt: PDF, Bilder, Word, ODT · Max. 10 MB pro Datei · Mehrere Dateien wählbar</p>
            @endcanany
        </div>
    @endif

</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush

