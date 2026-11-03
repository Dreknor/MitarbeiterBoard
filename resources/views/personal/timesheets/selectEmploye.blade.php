@extends('layouts.app')

@section('title')
    Arbeitszeitnachweise
@endsection

@section('site-title')
    Arbeitszeitnachweise
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@php
    $hm = function ($sekunden) {
        $minuten = (int) round($sekunden / 60);
        return ($minuten < 0 ? '−' : ($minuten > 0 ? '+' : '')).intdiv(abs($minuten), 60).':'.str_pad((string) (abs($minuten) % 60), 2, '0', STR_PAD_LEFT);
    };
@endphp

@section('content')
<div class="zeit-wrapper" x-data="{ suche: '', filter: 'alle' }">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="zw-page-title">Arbeitszeitnachweise</h1>
            <p class="zw-page-sub">Status für {{ $vormonat->locale('de')->isoFormat('MMMM YYYY') }} und aktuelles Stundenkonto</p>
        </div>
        @if($eigener)
            <a href="{{ route('timesheets.show', auth()->id()) }}" class="zw-btn zw-btn-secondary"><i class="fas fa-user"></i> Mein Nachweis</a>
        @endif
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input type="search" x-model="suche" class="zw-input sm:max-w-xs" placeholder="Name suchen">
        <div class="zw-pills">
            <button type="button" class="zw-pill" :class="filter === 'alle' && 'is-active'" @click="filter = 'alle'">Alle</button>
            <button type="button" class="zw-pill" :class="filter === 'eingereicht' && 'is-active'" @click="filter = 'eingereicht'">Zu prüfen</button>
            <button type="button" class="zw-pill" :class="filter === 'offen' && 'is-active'" @click="filter = 'offen'">Noch offen</button>
            <button type="button" class="zw-pill" :class="filter === 'pruefung' && 'is-active'" @click="filter = 'pruefung'">Prüfung nötig</button>
        </div>
    </div>

    <section class="zw-card">
        @if($employes->isEmpty())
            <div class="zw-empty"><i class="fas fa-users"></i> Keine Mitarbeitenden in deiner Zuständigkeit.</div>
        @else
            <ul class="zw-list">
                @foreach($employes as $person)
                    @php
                        $letzter = $person->timesheets->first(fn ($t) => (int) $t->year === $vormonat->year && (int) $t->month === $vormonat->month);
                        $aktuell = $person->timesheets->first();
                        $status = $letzter?->status ?? 'offen';
                        $pruefung = $person->timesheets->contains('requires_review', true);
                    @endphp
                    <li x-show="(!suche || @js(mb_strtolower($person->name)).includes(suche.toLowerCase())) && (filter === 'alle' || filter === @js($status) || (filter === 'pruefung' && @js($pruefung)))">
                        <div class="zw-row flex-wrap">
                            <a href="{{ route('timesheets.show', [$person->id, $vormonat->format('Y-m')]) }}" class="flex-1 min-w-[12rem]">
                                <div class="font-semibold text-gray-900">{{ $person->name }}</div>
                                <div class="text-xs text-gray-500">
                                    Stundenkonto {{ $aktuell ? $hm($aktuell->working_time_account).' h' : '–' }}
                                    @if($aktuell && $aktuell->holidays_rest !== null) · Resturlaub {{ \App\Services\Personal\Zeit\UrlaubskontoService::format((float) $aktuell->holidays_rest) }} @endif
                                </div>
                            </a>
                            <span class="zw-badge {{ ['offen' => 'zw-badge-gray', 'eingereicht' => 'zw-badge-blue', 'abgeschlossen' => 'zw-badge-green'][$status] }}">
                                {{ $vormonat->locale('de')->isoFormat('MMM') }}: {{ $letzter?->status_label ?? 'Offen' }}
                            </span>
                            @if($pruefung)<span class="zw-badge zw-badge-amber"><i class="fas fa-exclamation-triangle"></i> Prüfung</span>@endif
                            <div class="flex items-center gap-1">
                                <a href="{{ route('timesheets.show', $person->id) }}" class="zw-btn zw-btn-sm zw-btn-ghost">aktueller Monat</a>
                                <a href="{{ route('timesheets.overview', $person->id) }}" class="zw-btn-icon is-sm" title="Verlauf"><i class="fas fa-chart-line"></i></a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
