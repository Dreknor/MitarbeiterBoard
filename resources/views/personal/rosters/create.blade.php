@extends('layouts.app')

@section('title')
    Neuer Dienstplan
@endsection

@section('site-title')
    Dienstplanung
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@section('content')
<div class="zeit-wrapper max-w-3xl"
     x-data="{ typ: @js(old('type', 'normal')), start: @js(old('start_date', $vorschlag->format('Y-m-d'))), weitere: 0,
               get montag() { if (!this.start) return null; const d = new Date(this.start + 'T12:00:00'); const tag = (d.getDay() + 6) % 7; d.setDate(d.getDate() - tag); return d; },
               wochen() { const liste = []; if (!this.montag) return liste; for (let i = 1; i <= this.weitere; i++) { const d = new Date(this.montag); d.setDate(d.getDate() + 7 * i); liste.push(d.toISOString().slice(0, 10)); } return liste; },
               label(iso) { const d = new Date(iso + 'T12:00:00'); return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }); } }">
    <a href="{{ route('roster.index') }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>Dienstpläne</a>
    <h1 class="zw-page-title mt-1 mb-4">Neuer Dienstplan – {{ $department->name }}</h1>

    <form action="{{ route('roster.store') }}" method="post" class="zw-card" autocomplete="off">
        @csrf
        <input type="hidden" name="department_id" value="{{ $department->id }}">

        <div class="zw-card-body flex flex-col gap-5">
            <div>
                <span class="zw-label">Art</span>
                <div class="zw-segment">
                    <input type="radio" name="type" id="typ-normal" value="normal" x-model="typ">
                    <label for="typ-normal"><i class="far fa-calendar"></i> Wochenplan</label>
                    <input type="radio" name="type" id="typ-template" value="template" x-model="typ">
                    <label for="typ-template"><i class="far fa-copy"></i> Vorlage</label>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="start_date" class="zw-label">Woche ab</label>
                    <input type="date" name="start_date" id="start_date" x-model="start" class="zw-input @error('start_date') is-invalid @enderror" required>
                    <p class="zw-hint">Wird auf den Montag der Woche gesetzt.</p>
                </div>
                <div>
                    <label for="comment" class="zw-label">Kommentar</label>
                    <input type="text" name="comment" id="comment" maxlength="190" class="zw-input" value="{{ old('comment') }}" placeholder="z. B. Ferienbetreuung">
                </div>
            </div>

            <div>
                <label for="used_template" class="zw-label">Inhalte übernehmen aus</label>
                <select name="used_template" id="used_template" class="zw-select">
                    <option value="">– leer beginnen –</option>
                    @if($templates->isNotEmpty())
                        <optgroup label="Vorlagen">
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" @selected(old('used_template') == $template->id)>Vorlage vom {{ $template->start_date->format('d.m.Y') }}{{ $template->comment ? ' – '.$template->comment : '' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if($vorlagenPlaene->isNotEmpty())
                        <optgroup label="Bisherige Wochen">
                            @foreach($vorlagenPlaene as $plan)
                                <option value="{{ $plan->id }}" @selected(old('used_template') == $plan->id)>KW {{ $plan->start_date->isoWeek() }} ({{ $plan->start_date->format('d.m.Y') }}){{ $plan->comment ? ' – '.$plan->comment : '' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                <p class="zw-hint">Dienste und Termine werden übernommen. Feiertage und genehmigter Urlaub/Abwesenheiten werden automatisch berücksichtigt; wer abwesend ist, wird für den Tag nicht eingeplant.</p>
            </div>

            <div x-show="typ === 'normal'">
                <label for="weitere" class="zw-label">Gleichen Plan zusätzlich für die folgenden Wochen anlegen</label>
                <div class="flex items-center gap-3">
                    <input type="range" id="weitere" min="0" max="12" x-model.number="weitere" class="flex-1">
                    <span class="w-24 text-sm font-semibold" x-text="weitere === 0 ? 'keine' : weitere + ' Woche(n)'"></span>
                </div>
                <template x-for="woche in wochen()" :key="woche">
                    <input type="hidden" name="weitere_wochen[]" :value="woche">
                </template>
                <p class="zw-hint" x-show="weitere > 0">Bis einschließlich Woche ab <span x-text="wochen().length ? label(wochen()[wochen().length - 1]) : ''"></span>. Bereits vorhandene Wochen werden übersprungen.</p>
            </div>
        </div>

        <div class="flex justify-end gap-2 px-4 py-3 sm:px-5 border-t border-gray-100">
            <a href="{{ route('roster.index') }}" class="zw-btn zw-btn-secondary">Abbrechen</a>
            <button type="submit" class="zw-btn zw-btn-primary"><i class="fas fa-save"></i> Anlegen</button>
        </div>
    </form>
</div>
@endsection
