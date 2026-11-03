@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('title')
    Personalverwaltung
@endsection

@section('site-title')
    Urlaubsanspruch für Gruppen
@endsection

@section('content')
<div class="personal-wrapper"
     x-data="{
        gruppe: @js((string) old('group_id', '')),
        tage: @js((string) old('holiday_claim', 30)),
        ab: @js((string) old('date_start', now()->format('Y-m-d'))),
        gruppen: @js($groups->mapWithKeys(fn ($g) => [$g->id => ['name' => $g->name, 'anzahl' => $g->users_count]])),
        get auswahl() { return this.gruppen[this.gruppe] ?? null },
        get datum() { return this.ab ? new Date(this.ab).toLocaleDateString('de-DE') : '–' },
        bestaetigen(e) {
            if (!this.auswahl) return;
            if (!confirm(`Urlaubsanspruch für ${this.auswahl.anzahl} Personen der Gruppe „${this.auswahl.name}“ ab ${this.datum} auf ${this.tage} Tage festlegen?`)) {
                e.preventDefault();
            }
        }
     }">

    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Urlaubsanspruch für Gruppen</h1>
            <p class="text-gray-500 text-sm mt-1">Legt den Anspruch für alle Mitglieder einer Gruppe auf einmal fest.</p>
        </div>
        <a href="{{ route('employes.index') }}" class="btn-personal-secondary text-sm">← Mitarbeitende</a>
    </div>

    @if($groups->isEmpty())
        <div class="alert-warning text-sm">Es sind keine Gruppen vorhanden. Bitte zuerst eine Gruppe anlegen.</div>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form method="POST" action="{{ route('employes.bulk-holiday-claim.update') }}" class="personal-card lg:col-span-2 space-y-4"
              @submit="bestaetigen($event)">
            @csrf
            <div>
                <label for="group_id" class="personal-label">Gruppe *</label>
                <select name="group_id" id="group_id" class="personal-input" required x-model="gruppe">
                    <option value="">— bitte wählen —</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }} ({{ $group->users_count }})</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="holiday_claim" class="personal-label">Urlaubsanspruch (Tage pro Jahr) *</label>
                    <input type="number" name="holiday_claim" id="holiday_claim" class="personal-input" min="1" required x-model="tage">
                </div>
                <div>
                    <label for="date_start" class="personal-label">Gültig ab *</label>
                    <input type="date" name="date_start" id="date_start" class="personal-input" required x-model="ab">
                </div>
            </div>
            <p class="text-xs text-gray-500">
                Es wird je Person ein neuer Anspruch ab dem Datum gespeichert – nur bei Personen, deren aktueller Anspruch abweicht.
                Frühere Ansprüche bleiben erhalten.
            </p>
            <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                <a href="{{ route('employes.index') }}" class="btn-personal-secondary">Abbrechen</a>
                <button type="submit" class="btn-personal-primary">Urlaubsanspruch festlegen</button>
            </div>
        </form>

        <div class="personal-card text-sm" x-show="auswahl" x-cloak>
            <h2 class="text-base font-semibold text-gray-900 mb-3">Vorschau</h2>
            <dl class="space-y-2">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Gruppe</dt><dd class="font-medium" x-text="auswahl?.name"></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Mitglieder</dt><dd class="font-medium" x-text="auswahl?.anzahl"></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Anspruch</dt><dd class="font-medium"><span x-text="tage"></span> Tage</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Gültig ab</dt><dd class="font-medium" x-text="datum"></dd></div>
            </dl>
        </div>
    </div>
    @endif
</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush
