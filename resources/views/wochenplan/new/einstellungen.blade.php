@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@php
    $felder = [
        'wochenplan_ordner_vorlage' => ['Ordner eines Plans', $platzhalter, false],
        'wochenplan_datei_vorlage' => ['Dateiname eines Plans (ohne Endung)', $platzhalter, false],
        'wochenplan_kinderplan_vorlage' => ['Dateiname eines Kinderplans (im Ordner des Klassenplans)', array_merge($platzhalter, $platzhalterKind), true],
    ];
@endphp

@section('content')
<div class="wp-wrapper" style="max-width: 960px"
     x-data="wpEinstellungen(@js(collect($werte)->only(array_merge(['wochenplan_nextcloud_basisordner'], array_keys($felder)))->all()), @js(['vorschauUrl' => route('wp.einstellungen.vorschau')]))">
    <a href="{{ route('wp.index') }}" class="wp-back">← Übersicht</a>
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Einstellungen Wochenplan</h1>
            <p class="wp-subtitle">Gilt für die ganze Schule. Die Werte stehen auch unter Einstellungen → Wochenplan.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('wp.einstellungen.update') }}" class="space-y-4">
        @csrf

        {{-- Tagebuch --}}
        <section class="wp-card">
            <div class="wp-card-head"><h2 class="wp-card-title">📓 Pädagogisches Tagebuch</h2></div>
            <div class="wp-card-body space-y-2">
                <label class="wp-switch">
                    <input type="hidden" name="wochenplan_tagebuch_anbindung" value="0">
                    <input type="checkbox" name="wochenplan_tagebuch_anbindung" value="1" @checked($werte['wochenplan_tagebuch_anbindung'])>
                    <span class="wp-switch-track"></span>
                    <span class="text-sm font-semibold text-gray-800">Anbindung an das Pädagogische Tagebuch</span>
                </label>
                <ul class="ml-14 list-disc text-sm text-gray-600" style="list-style: disc; padding-left: 1rem">
                    <li>Offene Tagebuch-Aufgaben in Kinderpläne übernehmen</li>
                    <li>„Erledigt“ in beide Richtungen abgleichen</li>
                    <li>Wochenplan-Stand je Kind in der Wochenansicht (beide Darstellungen) mit Abhaken</li>
                    <li>Wochenpläne des Kindes in der Schülerakte</li>
                </ul>
                <p class="wp-hint ml-14">Beim Ausschalten bleiben gespeicherte Verknüpfungen erhalten und wirken nach dem Einschalten wieder. Die Fortschritts-Matrix im Wochenplan bleibt immer verfügbar.</p>
            </div>
        </section>

        {{-- Nextcloud --}}
        <section class="wp-card">
            <div class="wp-card-head">
                <h2 class="wp-card-title">☁ Nextcloud-Ablage</h2>
                @if($aktiv)
                    <span class="wp-badge wp-badge-green">aktiv</span>
                @else
                    <span class="wp-badge wp-badge-gray">aus</span>
                @endif
            </div>
            <div class="wp-card-body space-y-4">
                @unless($konfiguriert)
                    <div class="wp-note wp-note-warning">
                        Für die Nextcloud fehlen Zugangsdaten. In der <code>.env</code> eintragen:
                        <code>NEXTCLOUD_WOCHENPLAN_URL</code>, <code>NEXTCLOUD_WOCHENPLAN_USERNAME</code>, <code>NEXTCLOUD_WOCHENPLAN_PASSWORD</code>
                        (Standard: die Werte von <code>NEXTCLOUD_*</code>).
                    </div>
                @endunless

                <label class="wp-switch">
                    <input type="hidden" name="wochenplan_nextcloud_aktiv" value="0">
                    <input type="checkbox" name="wochenplan_nextcloud_aktiv" value="1" @checked($werte['wochenplan_nextcloud_aktiv'])>
                    <span class="wp-switch-track"></span>
                    <span class="text-sm font-semibold text-gray-800">Arbeitsblätter und fertige Pläne (PDF + Word) in der Nextcloud ablegen</span>
                </label>

                <div>
                    <label class="wp-label" for="wp-basis">Basisordner (Dienst-Konto)</label>
                    <input id="wp-basis" type="text" name="wochenplan_nextcloud_basisordner" class="wp-input @error('wochenplan_nextcloud_basisordner') is-invalid @enderror"
                           x-model="werte.wochenplan_nextcloud_basisordner" required maxlength="255">
                    @error('wochenplan_nextcloud_basisordner')<p class="wp-error">{{ $message }}</p>@enderror
                    <p class="wp-hint">Diesen Ordner in der Nextcloud für die Pädagog:innen freigeben.</p>
                </div>

                @foreach($felder as $feld => [$titel, $erlaubt, $kind])
                    <div>
                        <label class="wp-label" for="wp-{{ $feld }}">{{ $titel }}</label>
                        <input id="wp-{{ $feld }}" type="text" name="{{ $feld }}" class="wp-input @error($feld) is-invalid @enderror"
                               :class="fehler.{{ $feld }} && 'is-invalid'" x-model="werte.{{ $feld }}" required maxlength="255">
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach($erlaubt as $p)
                                <button type="button" class="wp-pill" @click="einfuegen('{{ $feld }}', '{{ $p }}')">{{ '{'.$p.'}' }}</button>
                            @endforeach
                        </div>
                        @error($feld)<p class="wp-error">{{ $message }}</p>@enderror
                        <p class="wp-error" x-show.important="fehler.{{ $feld }}" x-text="fehler.{{ $feld }}"></p>
                    </div>
                @endforeach

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                    <p class="wp-section-title mb-1">Vorschau (Klasse 3a, aktuelle Woche, Kind Mia Muster)</p>
                    <ul class="space-y-0.5 font-mono text-xs text-gray-700">
                        <template x-for="zeile in beispiel" :key="zeile"><li x-text="zeile"></li></template>
                    </ul>
                </div>
                <p class="wp-hint">
                    Eine geänderte Struktur gilt für neue und geänderte Pläne; vorhandene Ordner ziehen beim nächsten Speichern um.
                    Gelöschte Pläne und Dateien landen im Ordner „_gelöscht“.
                </p>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="wp-btn wp-btn-primary">Einstellungen speichern</button>
        </div>
    </form>

    <section class="wp-card mt-6">
        <div class="wp-card-head"><h2 class="wp-card-title">Ablage-Status</h2></div>
        <div class="wp-card-body flex flex-wrap items-center gap-4">
            <span class="text-sm text-gray-700">{{ $statistik['ausstehend'] }} Pläne warten auf Ablage</span>
            <span class="text-sm {{ $statistik['fehler'] ? 'text-red-700 font-semibold' : 'text-gray-700' }}">{{ $statistik['fehler'] }} Ablagen fehlgeschlagen</span>
            <span class="text-sm {{ $statistik['dateien_fehler'] ? 'text-red-700 font-semibold' : 'text-gray-700' }}">{{ $statistik['dateien_fehler'] }} Arbeitsblätter mit Fehler</span>
            <form method="POST" action="{{ route('wp.einstellungen.neu-ablegen') }}" class="ml-auto"
                  onsubmit="return confirm('Alle Pläne neu in der Nextcloud ablegen? Vorhandene Ordner werden dabei an die aktuelle Struktur angepasst.')">
                @csrf
                <button type="submit" class="wp-btn wp-btn-secondary wp-btn-sm" @disabled(! $aktiv)>Alle Pläne neu ablegen</button>
            </form>
        </div>
    </section>
</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush
