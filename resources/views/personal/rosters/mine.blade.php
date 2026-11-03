@extends('layouts.app')

@section('title')
    Mein Dienstplan
@endsection

@section('site-title')
    Mein Dienstplan
@endsection

@push('css')
    @vite(['resources/css/zeit.css', 'resources/js/zeit.js'])
@endpush

@section('content')
<div class="zeit-wrapper max-w-4xl">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div>
            <h1 class="zw-page-title">Mein Dienstplan</h1>
            <p class="zw-page-sub">Veröffentlichte Dienste und Termine der nächsten drei Wochen.</p>
        </div>
        <div class="flex items-center gap-1">
            <a href="{{ route('roster.mine', ['woche' => $woche->copy()->subWeek()->toDateString()]) }}" class="zw-btn-icon" title="Woche zurück"><i class="fas fa-chevron-left"></i></a>
            @unless($woche->isSameDay(now()->startOfWeek()))
                <a href="{{ route('roster.mine') }}" class="zw-btn zw-btn-sm zw-btn-ghost">Heute</a>
            @endunless
            <a href="{{ route('roster.mine', ['woche' => $woche->copy()->addWeek()->toDateString()]) }}" class="zw-btn-icon" title="Woche vor"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    @foreach(collect($tage)->chunk(7) as $wochenTage)
        @php($montag = $wochenTage->first()['date'])
        @php($plan = $plaene->first(fn ($p) => $p->start_date->isSameDay($montag)))
        <section class="zw-card mb-4">
            <div class="zw-card-head">
                <h2 class="zw-card-title"><i class="far fa-calendar"></i> KW {{ $montag->isoWeek() }} · {{ $montag->format('d.m.') }}–{{ $montag->copy()->endOfWeek()->format('d.m.Y') }}</h2>
                @if($plan)
                    <a href="{{ route('roster.export.pdf', $plan->id) }}" target="_blank" class="zw-btn zw-btn-sm zw-btn-ghost"><i class="fas fa-file-pdf"></i><span class="hidden sm:inline">Gesamtplan {{ $plan->department->name }}</span></a>
                @endif
            </div>
            <ul class="zw-list">
                @foreach($wochenTage as $tag)
                    @php($leer = $tag['zeiten']->isEmpty() && $tag['termine']->isEmpty() && !$tag['abwesenheit'])
                    <li class="flex gap-4 px-4 py-3 sm:px-5 {{ $tag['date']->isToday() ? 'bg-blue-50/60' : '' }} {{ $leer && $tag['date']->isWeekend() ? 'hidden sm:flex' : '' }}">
                        <div class="w-14 shrink-0 text-center">
                            <div class="text-xs uppercase text-gray-500">{{ $tag['date']->locale('de')->isoFormat('dd') }}</div>
                            <div class="text-lg font-bold {{ $tag['date']->isToday() ? 'text-blue-700' : 'text-gray-900' }}">{{ $tag['date']->format('d.') }}</div>
                        </div>
                        <div class="flex-1 min-w-0 flex flex-col gap-1.5">
                            @if($tag['feiertag'])<span class="zw-badge zw-badge-blue self-start">{{ $tag['feiertag'] }}</span>@endif
                            @if($tag['abwesenheit'])<span class="zw-badge zw-badge-amber self-start"><i class="fas fa-umbrella-beach"></i> {{ $tag['abwesenheit'] }}</span>@endif
                            @foreach($tag['zeiten'] as $zeit)
                                <div class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="font-semibold text-gray-900">{{ $zeit->start ? $zeit->start->format('H:i').'–'.$zeit->end?->format('H:i').' Uhr' : 'frei' }}</span>
                                    @if($zeit->function)<span class="text-sm text-gray-600">{{ $zeit->function }}</span>@endif
                                    <span class="text-xs text-gray-400">{{ $zeit->roster?->department?->name }}</span>
                                </div>
                            @endforeach
                            @foreach($tag['termine'] as $termin)
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="tabular-nums text-xs text-gray-500 w-24 shrink-0">{{ $termin->start?->format('H:i') }}–{{ $termin->end?->format('H:i') }}</span>
                                    <span class="{{ \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($termin->event), 'pause') ? 'text-gray-500' : 'text-gray-900' }}">{{ $termin->event }}</span>
                                </div>
                            @endforeach
                            @if($leer)<span class="text-sm text-gray-300">–</span>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <section class="zw-card">
        <div class="zw-card-head">
            <h2 class="zw-card-title"><i class="fas fa-rss"></i> Kalender-Abo</h2>
        </div>
        <div class="zw-card-body flex flex-col gap-3 text-sm">
            <p class="text-gray-600">Mit dem Abo erscheinen deine veröffentlichten Dienste automatisch im Kalender deines Smartphones (iOS, Android, Outlook, Thunderbird). Der Link ist persönlich – bitte nicht weitergeben.</p>
            @if($feedUrl)
                <div class="flex flex-col sm:flex-row gap-2" x-data="{ kopiert: false }">
                    <input type="text" readonly value="{{ $feedUrl }}" class="zw-input font-mono text-xs" @focus="$el.select()">
                    <button type="button" class="zw-btn zw-btn-secondary shrink-0" @click="navigator.clipboard?.writeText(@js($feedUrl)); kopiert = true; setTimeout(() => kopiert = false, 2000)">
                        <i class="far fa-copy"></i> <span x-text="kopiert ? 'Kopiert' : 'Kopieren'">Kopieren</span>
                    </button>
                    <a href="{{ str_replace(['https://', 'http://'], 'webcal://', $feedUrl) }}" class="zw-btn zw-btn-primary shrink-0"><i class="far fa-calendar-plus"></i> Abonnieren</a>
                </div>
                <div class="flex gap-2">
                    <form action="{{ route('roster.feed-token') }}" method="post" data-confirm="Neuen Link erstellen? Der bisherige Link funktioniert danach nicht mehr.">
                        @csrf
                        <button type="submit" class="zw-btn zw-btn-sm zw-btn-ghost"><i class="fas fa-sync-alt"></i> Neuen Link erstellen</button>
                    </form>
                    <form action="{{ route('roster.feed-token') }}" method="post" data-confirm="Kalender-Abo deaktivieren?">
                        @csrf
                        <input type="hidden" name="revoke" value="1">
                        <button type="submit" class="zw-btn zw-btn-sm zw-btn-danger-ghost"><i class="fas fa-ban"></i> Deaktivieren</button>
                    </form>
                </div>
            @else
                <form action="{{ route('roster.feed-token') }}" method="post">
                    @csrf
                    <button type="submit" class="zw-btn zw-btn-primary"><i class="fas fa-link"></i> Abo-Link erstellen</button>
                </form>
            @endif
        </div>
    </section>
</div>
@endsection
