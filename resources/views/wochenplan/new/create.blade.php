@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@php
    $montag = now()->startOfWeek();
    $vorlageWahl = (string) old('vorlage_id', $vorlageId);
@endphp

@section('content')
<div class="wp-wrapper" style="max-width: 860px">
    <a href="{{ route('wp.index') }}" class="wp-back">← Übersicht</a>
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Neuer Wochenplan</h1>
            <p class="wp-subtitle">Ein Klassenplan gehört immer zu einer Klasse. Kinderpläne entstehen danach aus dem Klassenplan.</p>
        </div>
    </div>

    @if($klassen->isEmpty())
        <div class="wp-note wp-note-warning mb-4">Ihnen ist keine Klasse zugeordnet – ohne Klasse kann kein Plan angelegt werden.</div>
    @endif

    <form method="POST" action="{{ route('wp.store') }}" class="wp-card"
          x-data="{
              vorlage: @js($vorlageWahl),
              von: @js(old('gueltig_von', $montag->toDateString())),
              bis: @js(old('gueltig_bis', $montag->copy()->addDays(4)->toDateString())),
              woche(versatz) {
                  const d = new Date(); const tag = (d.getDay() + 6) % 7;
                  d.setDate(d.getDate() - tag + versatz * 7);
                  const f = (x) => x.toISOString().slice(0, 10);
                  this.von = f(d); const e = new Date(d); e.setDate(e.getDate() + 4); this.bis = f(e);
              }
          }">
        @csrf
        <div class="wp-card-body space-y-4">
            @if($vorlagen->isNotEmpty())
                <div>
                    <label class="wp-label" for="wp-vorlage">Vorlage (optional)</label>
                    <select id="wp-vorlage" name="vorlage_id" class="wp-select" x-model="vorlage">
                        <option value="">Leerer Plan mit Standard-Fächern</option>
                        @foreach($vorlagen as $vorlage)
                            <option value="{{ $vorlage->id }}">{{ $vorlage->vorlage_name ?? $vorlage->name }}</option>
                        @endforeach
                    </select>
                    <p class="wp-hint">Aus einer Vorlage werden Fächer, Aufgaben und tägliche Übungen übernommen.</p>
                </div>
            @endif

            <div>
                <label class="wp-label" for="wp-name">Bezeichnung</label>
                <input id="wp-name" type="text" name="name" value="{{ old('name', 'Wochenplan KW '.$montag->isoWeek()) }}" required maxlength="255"
                       class="wp-input @error('name') is-invalid @enderror">
                @error('name')<p class="wp-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="wp-label" for="wp-klasse">Klasse</label>
                <select id="wp-klasse" name="klasse_id" required class="wp-select @error('klasse_id') is-invalid @enderror">
                    <option value="">– Klasse wählen –</option>
                    @foreach($klassen as $klasse)
                        <option value="{{ $klasse->id }}" @selected(old('klasse_id') == $klasse->id || ($klassen->count() === 1 && ! old('klasse_id')))>{{ $klasse->name }}</option>
                    @endforeach
                </select>
                @error('klasse_id')<p class="wp-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                    <span class="wp-label mb-0">Zeitraum</span>
                    <div class="wp-btn-group">
                        <button type="button" class="wp-btn wp-btn-ghost wp-btn-sm" @click="woche(0)">Diese Woche</button>
                        <button type="button" class="wp-btn wp-btn-ghost wp-btn-sm" @click="woche(1)">Nächste Woche</button>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="wp-label" for="wp-von">Gültig von</label>
                        <input id="wp-von" type="date" name="gueltig_von" x-model="von" required class="wp-input @error('gueltig_von') is-invalid @enderror">
                    </div>
                    <div>
                        <label class="wp-label" for="wp-bis">Gültig bis</label>
                        <input id="wp-bis" type="date" name="gueltig_bis" x-model="bis" required class="wp-input @error('gueltig_bis') is-invalid @enderror">
                        @error('gueltig_bis')<p class="wp-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="wp-label" for="wp-selbst">Selbsteinschätzung</label>
                    <select id="wp-selbst" name="selbsteinschaetzung" class="wp-select">
                        <option value="0" @selected(old('selbsteinschaetzung') == 0)>Keine</option>
                        <option value="1" @selected(old('selbsteinschaetzung') == 1)>Smileys</option>
                        <option value="2" @selected(old('selbsteinschaetzung') == 2)>Skala 1–10</option>
                    </select>
                </div>
                <div>
                    <label class="wp-label" for="wp-format">Formatvorlage</label>
                    <select id="wp-format" name="formatvorlage_id" class="wp-select">
                        <option value="">Standard</option>
                        @foreach($formatvorlagen as $fv)
                            <option value="{{ $fv->id }}" @selected(old('formatvorlage_id') == $fv->id)>{{ $fv->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="wp-check">
                <input type="hidden" name="taegliche_uebungen_aktiv" value="0">
                <input type="checkbox" name="taegliche_uebungen_aktiv" value="1" @checked(old('taegliche_uebungen_aktiv'))> Tägliche Übungen verwenden
            </label>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
            <a href="{{ route('wp.index') }}" class="wp-btn wp-btn-secondary">Abbrechen</a>
            <button type="submit" class="wp-btn wp-btn-primary" @disabled($klassen->isEmpty())>Plan anlegen</button>
        </div>
    </form>
</div>
@endsection
