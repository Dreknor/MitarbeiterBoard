@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-wrapper" style="max-width: 860px">
    <a href="{{ route('wp.edit', $wpPlan) }}" class="wp-back">← Zurück zum Plan</a>
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Kinderpläne erstellen</h1>
            <p class="wp-subtitle">Basiert auf <strong>{{ $wpPlan->name }}</strong>{{ $wpPlan->klasse ? ' · Klasse '.$wpPlan->klasse->name : '' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('wp.schuelerplan.store', $wpPlan) }}" class="wp-card"
          x-data="{ auswahl: [], alle(ids) { this.auswahl = ids.map(String) } }">
        @csrf
        <div class="wp-card-body space-y-4">
            @error('schueler_ids')<div class="wp-note wp-note-danger">{{ $message }}</div>@enderror

            @if($hatNiveaus)
                <div class="wp-note wp-note-info">
                    Dieser Plan enthält Aufgaben mit Niveau. Wählen Sie je Kind ein Niveau – dann übernimmt der Kinderplan nur Aufgaben ohne Niveau und die des gewählten Niveaus.
                </div>
            @endif

            @php $verfuegbar = $schueler->reject(fn ($s) => in_array($s->id, $bereitsVorhanden))->pluck('id')->values(); @endphp
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="wp-label mb-0">Kinder auswählen</span>
                <div class="wp-btn-group">
                    <button type="button" class="wp-btn wp-btn-ghost wp-btn-sm" @click="alle(@js($verfuegbar))">Alle auswählen</button>
                    <button type="button" class="wp-btn wp-btn-ghost wp-btn-sm" @click="auswahl = []">Keine</button>
                </div>
            </div>

            <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200" style="max-height: 28rem; overflow-y: auto">
                @forelse($schueler as $kind)
                    @php $vorhanden = in_array($kind->id, $bereitsVorhanden); @endphp
                    <li class="flex flex-wrap items-center gap-3 px-3 py-2 {{ $vorhanden ? 'bg-gray-50' : '' }}">
                        <label class="wp-check flex-1 min-w-0">
                            <input type="checkbox" name="schueler_ids[]" value="{{ $kind->id }}" x-model="auswahl" @disabled($vorhanden)>
                            <span class="{{ $vorhanden ? 'text-gray-400' : '' }}">{{ $kind->nachname }}, {{ $kind->vorname }}</span>
                            @if(! $wpPlan->klasse && $kind->klasse)
                                <span class="text-xs text-gray-400">({{ $kind->klasse->name }})</span>
                            @endif
                            @if($vorhanden)
                                <span class="wp-badge wp-badge-gray">hat schon einen Kinderplan</span>
                            @endif
                        </label>
                        @if($hatNiveaus && ! $vorhanden)
                            <select name="niveau[{{ $kind->id }}]" class="wp-select wp-input-sm" style="max-width: 11rem" aria-label="Niveau für {{ $kind->vorname }}">
                                <option value="">alle Niveaus</option>
                                @foreach(\App\Models\Wochenplan\WpPlan::NIVEAUS as $n => $name)
                                    <option value="{{ $n }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </li>
                @empty
                    <li class="px-3 py-4 text-sm text-gray-500">Keine Kinder gefunden.</li>
                @endforelse
            </ul>

            <div>
                <label class="wp-label" for="wp-format">Formatvorlage für die Kinderpläne</label>
                <select id="wp-format" name="formatvorlage_id" class="wp-select">
                    <option value="">Wie der Klassenplan</option>
                    @foreach($formatvorlagen as $fv)
                        <option value="{{ $fv->id }}">{{ $fv->name }}</option>
                    @endforeach
                </select>
                <p class="wp-hint">Z. B. eine Vorlage mit großer Schrift für einzelne Kinder.</p>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-3">
            <span class="mr-auto text-sm text-gray-500" x-text="auswahl.length + ' ausgewählt'"></span>
            <a href="{{ route('wp.edit', $wpPlan) }}" class="wp-btn wp-btn-secondary">Abbrechen</a>
            <button type="submit" class="wp-btn wp-btn-warning" :disabled="!auswahl.length">Kinderpläne erstellen</button>
        </div>
    </form>
</div>
@endsection
