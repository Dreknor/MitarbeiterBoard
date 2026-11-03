@extends('layouts.app')

@section('title') Auto-Umplanung @endsection
@section('site-title') Dienstplan: Auto-Umplanung @endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@section('content')
<div class="zeit-wrapper">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <a href="{{ route('roster.show', $roster->id) }}" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-arrow-left mr-1"></i>zum Dienstplan</a>
            <h1 class="zw-page-title mt-1">Auto-Umplanung</h1>
            <p class="zw-page-sub">{{ $roster->department->name }} · Woche ab {{ $roster->start_date->format('d.m.Y') }} – Abwesenheiten simulieren und Termine automatisch neu verteilen.</p>
        </div>
        @if($hasUndo ?? false)
            <form action="{{ route('roster.autoPlan.undo', $roster->id) }}" method="post" data-confirm="Letzte Auto-Umplanung wirklich rückgängig machen?">
                @csrf
                <button type="submit" class="zw-btn zw-btn-warning"><i class="fas fa-undo"></i> Letzte Übernahme rückgängig</button>
            </form>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5 mb-5">
        {{-- Simulation --}}
        <form method="get" action="{{ route('roster.autoPlan', $roster->id) }}" class="zw-card xl:col-span-3" x-data>
            <div class="zw-card-head">
                <h2 class="zw-card-title"><i class="fas fa-user-slash"></i> Wer fällt aus?</h2>
                <button type="submit" class="zw-btn zw-btn-sm zw-btn-primary"><i class="fas fa-magic"></i> Neu berechnen</button>
            </div>
            <div class="zw-card-body">
                <p class="zw-hint mb-3">Ganze Woche über die Spalte „Woche“, einzelne Tage im Raster. Tages-Auswahl hat Vorrang.</p>
                <div class="overflow-x-auto">
                    <table class="zw-table text-xs">
                        <thead>
                        <tr>
                            <th class="sticky left-0 bg-gray-50">Person</th>
                            <th class="text-center">Woche</th>
                            @foreach($days as $d)
                                <th class="text-center">{{ $d->locale('de')->isoFormat('dd') }}<br><span class="font-normal">{{ $d->format('d.m.') }}</span></th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($employes as $e)
                            <tr>
                                <td class="sticky left-0 bg-white font-medium whitespace-nowrap">{{ $e->name }}</td>
                                <td class="text-center"><input type="checkbox" class="w-4 h-4" name="simulate_absent[]" value="{{ $e->id }}" @checked(in_array($e->id, $simulate ?? []))></td>
                                @foreach($days as $d)
                                    @php($dKey = $d->format('Y-m-d'))
                                    <td class="text-center"><input type="checkbox" class="w-4 h-4" name="simulate_absent_day[{{ $dKey }}][]" value="{{ $e->id }}" @checked(in_array($e->id, $simulate_per_day[$dKey] ?? []))></td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="zw-btn zw-btn-sm zw-btn-ghost mt-2" @click="$root.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false)">Auswahl leeren</button>
            </div>
        </form>

        {{-- Anforderungen --}}
        <section class="zw-card xl:col-span-2">
            <div class="zw-card-head">
                <h2 class="zw-card-title"><i class="fas fa-clipboard-check"></i> Aufgaben-Anforderungen</h2>
            </div>
            <ul class="zw-list">
                @forelse($requirements as $req)
                    <li class="px-4 py-3 sm:px-5" x-data="{ bearbeiten: false }">
                        <div class="flex items-center gap-2" x-show.important="!bearbeiten">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-900">{{ $req->event_name }} @if($req->adjust_working_time)<span class="zw-badge zw-badge-amber ml-1" title="Arbeitszeit darf angepasst werden">AZ anpassbar</span>@endif</div>
                                <div class="text-xs text-gray-500">{{ $req->required_start?->format('H:i') ?? '—' }} – {{ $req->required_end?->format('H:i') ?? '—' }}</div>
                            </div>
                            <button type="button" class="zw-btn-icon is-sm" @click="bearbeiten = true" title="Bearbeiten"><i class="fas fa-pen"></i></button>
                            <form method="post" action="{{ route('roster.taskRequirements.destroy', $req) }}" data-confirm="Anforderung löschen?">
                                @csrf @method('DELETE')
                                <button type="submit" class="zw-btn-icon is-sm text-red-600" title="Löschen"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        <form x-show.important="bearbeiten" x-cloak method="post" action="{{ route('roster.taskRequirements.update', $req) }}" class="grid grid-cols-2 gap-2">
                            @csrf @method('PUT')
                            <input type="text" name="event_name" value="{{ $req->event_name }}" class="zw-input col-span-2" required maxlength="120">
                            <input type="time" name="required_start" value="{{ $req->required_start?->format('H:i') }}" class="zw-input">
                            <input type="time" name="required_end" value="{{ $req->required_end?->format('H:i') }}" class="zw-input">
                            <label class="zw-check col-span-2"><input type="checkbox" name="adjust_working_time" value="1" @checked($req->adjust_working_time)> Arbeitszeit darf angepasst werden</label>
                            <button type="button" class="zw-btn zw-btn-sm zw-btn-secondary" @click="bearbeiten = false">Abbrechen</button>
                            <button type="submit" class="zw-btn zw-btn-sm zw-btn-primary">Speichern</button>
                        </form>
                    </li>
                @empty
                    <li class="zw-empty"><i class="fas fa-clipboard"></i> Noch keine Anforderungen.</li>
                @endforelse
            </ul>
            <form method="post" action="{{ route('roster.taskRequirements.store', $roster->id) }}" class="zw-card-body border-t border-gray-100 grid grid-cols-2 gap-2">
                @csrf
                <p class="col-span-2 zw-section-title">Neue Anforderung</p>
                <input type="text" name="event_name" class="zw-input col-span-2" required maxlength="120" placeholder="Terminname, z. B. Frühdienst">
                <input type="time" name="required_start" class="zw-input" aria-label="Erforderlicher Beginn">
                <input type="time" name="required_end" class="zw-input" aria-label="Erforderliches Ende">
                <label class="zw-check col-span-2"><input type="checkbox" name="adjust_working_time" value="1"> Arbeitszeit darf angepasst werden</label>
                <button type="submit" class="zw-btn zw-btn-sm zw-btn-primary col-span-2"><i class="fas fa-plus"></i> Hinzufügen</button>
            </form>
        </section>
    </div>

    @isset($summary)
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
            <div class="zw-stat"><span class="zw-stat-value">{{ $summary['betroffene_events'] }}</span><span class="zw-stat-label">betroffene Termine</span></div>
            <div class="zw-stat is-positive"><span class="zw-stat-value">{{ $summary['neu_zugewiesen'] }}</span><span class="zw-stat-label">neu zugewiesen</span></div>
            <div class="zw-stat {{ $summary['nicht_zuweisbar'] > 0 ? 'is-negative' : '' }}"><span class="zw-stat-value">{{ $summary['nicht_zuweisbar'] }}</span><span class="zw-stat-label">nicht zuweisbar</span></div>
            <div class="zw-stat"><span class="zw-stat-value">{{ $summary['zusatz_minuten'] }}</span><span class="zw-stat-label">Zusatz-Minuten</span></div>
            <div class="zw-stat"><span class="zw-stat-value">{{ $summary['neue_pausen'] }}</span><span class="zw-stat-label">neue Pausen</span></div>
        </div>
    @endisset

    <section class="zw-card" x-data="{ alle: false }">
        <div class="zw-card-head">
            <h2 class="zw-card-title"><i class="fas fa-lightbulb"></i> Vorschläge</h2>
            @if(count($suggestions) > 0)
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="w-4 h-4" x-model="alle" @change="$root.querySelectorAll('input[data-vorschlag]').forEach(c => c.checked = alle)"> alle auswählen</label>
            @endif
        </div>
        @if(count($suggestions) === 0)
            <div class="zw-empty"><i class="fas fa-check-circle"></i> Keine Änderungen notwendig.</div>
        @else
            <form method="post" action="{{ route('roster.autoPlan.apply', $roster->id) }}" data-confirm="Ausgewählte Vorschläge übernehmen?">
                @csrf
                <ul class="zw-list">
                    @foreach($suggestions as $s)
                        @php($req = $s['requirement'] ?? null)
                        <li class="px-4 py-3 sm:px-5 flex gap-3 {{ ($s['is_new'] ?? false) ? 'bg-emerald-50/50' : (($s['is_changed'] ?? false) ? 'bg-amber-50/50' : '') }}">
                            <input type="checkbox" name="selected[]" value="{{ $s['index'] }}" data-vorschlag class="w-4 h-4 mt-1 shrink-0">
                            <div class="flex-1 min-w-0 grid gap-1 md:grid-cols-4 md:gap-4">
                                <div>
                                    <div class="font-semibold text-gray-900">{{ $s['event_name'] ?? 'Termin '.$s['event_id'] }}</div>
                                    <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($s['date'])->locale('de')->isoFormat('dd, DD.MM.') }}</div>
                                    @if($s['is_new'] ?? false)<span class="zw-badge zw-badge-green mt-1">neu</span>@elseif($s['is_changed'] ?? false)<span class="zw-badge zw-badge-amber mt-1">geändert</span>@endif
                                </div>
                                <div class="text-sm">
                                    <span class="text-gray-500">{{ $s['from']['name'] ?? '—' }}</span>
                                    <i class="fas fa-arrow-right text-gray-300 mx-1"></i>
                                    <span class="font-semibold {{ ($s['action'] ?? '') === 'unassign' ? 'text-red-600' : 'text-gray-900' }}">{{ $s['to']['name'] ?? 'Merkliste' }}</span>
                                    <div class="text-xs text-gray-500">{{ $s['reason'] }}</div>
                                </div>
                                <div class="text-xs text-gray-600">
                                    @if($req)
                                        Anforderung: {{ $req['function'] }} {{ $req['start'] ?? '—' }}–{{ $req['end'] ?? '—' }}
                                    @endif
                                    @if($s['adjust_working_time'])
                                        <div class="text-blue-700">Arbeitszeit: @if($s['adjust_working_time']['new_start'])Beginn {{ $s['adjust_working_time']['new_start'] }} @endif @if($s['adjust_working_time']['new_end'])Ende {{ $s['adjust_working_time']['new_end'] }} @endif (+{{ $s['adjust_working_time']['added_minutes'] }} Min.)</div>
                                    @endif
                                </div>
                                <div class="text-xs">
                                    @if($s['add_break'])
                                        <label class="zw-check py-1"><input type="checkbox" name="break_selected[]" value="{{ $s['index'] }}"> Pause {{ $s['add_break']['start'] }}–{{ $s['add_break']['end'] }}</label>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 sm:px-5 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Nur markierte Vorschläge werden übernommen; Pausen nur, wenn zusätzlich markiert. Die Übernahme lässt sich eine Stunde lang rückgängig machen.</p>
                    <button type="submit" class="zw-btn zw-btn-success shrink-0"><i class="fas fa-check"></i> Ausgewählte übernehmen</button>
                </div>
            </form>
        @endif
    </section>
</div>
@endsection
