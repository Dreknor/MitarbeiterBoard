@extends('layouts.app')

@section('title', 'Mein Tag')
@section('site-title', 'Mein Tag')

@push('css')
    @vite(['resources/css/benachrichtigungen.css'])
@endpush

@section('content')
<div class="bn-wrapper">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">
                @if($tag->isToday()) Heute @elseif($tag->isTomorrow()) Morgen @else {{ $tag->locale('de')->isoFormat('dddd') }} @endif
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $tag->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('benachrichtigungen.tag', $vorher->toDateString()) }}" class="bn-btn bn-btn-secondary bn-btn-sm" aria-label="Vorheriger Tag">
                <i class="fas fa-chevron-left"></i>
            </a>
            @unless($tag->isToday())
                <a href="{{ route('benachrichtigungen.tag') }}" class="bn-btn bn-btn-secondary bn-btn-sm">Heute</a>
            @endunless
            <a href="{{ route('benachrichtigungen.tag', $nachher->toDateString()) }}" class="bn-btn bn-btn-secondary bn-btn-sm" aria-label="Nächster Tag">
                <i class="fas fa-chevron-right"></i>
            </a>
            <a href="{{ route('benachrichtigungen.einstellungen') }}" class="bn-btn bn-btn-secondary bn-btn-sm" title="Einstellungen der Tagesübersicht">
                <i class="fas fa-cog"></i>
            </a>
        </div>
    </div>

    @unless($arbeitstag)
        <div class="rounded-xl bg-amber-50 text-amber-800 text-sm px-4 py-3 mb-4">
            <i class="fas fa-info-circle"></i> Dieser Tag ist ein Wochenende oder Feiertag. Hierfür wird keine Tagesübersicht verschickt.
        </div>
    @endunless

    @forelse($bereiche as $bereich)
        <section class="bn-card mb-4">
            <div class="bn-card-head">
                <div class="flex items-center gap-3">
                    <span class="bn-icon"><i class="fas {{ $bereich['icon'] }}"></i></span>
                    <h2>{{ $bereich['label'] }}</h2>
                </div>
                <span class="text-xs font-semibold text-gray-500 bg-gray-100 rounded-full px-2.5 py-0.5">{{ $bereich['eintraege']->count() }}</span>
            </div>
            <ul>
                @foreach($bereich['eintraege'] as $eintrag)
                    <li style="border-top:1px solid #f9fafb;">
                        @if($eintrag->url)<a href="{{ $eintrag->url }}" class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50">@else<div class="flex items-start gap-3 px-5 py-3">@endif
                            <span class="w-24 shrink-0 text-sm font-semibold {{ $eintrag->hervorheben ? 'text-red-600' : 'text-gray-700' }}">{{ $eintrag->zeit ?? '' }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-gray-900">{{ $eintrag->titel }}</span>
                                @if($eintrag->details)
                                    <span class="block text-xs text-gray-500 mt-0.5">{{ $eintrag->details }}</span>
                                @endif
                            </span>
                        @if($eintrag->url)</a>@else</div>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="bn-card px-5 py-12 text-center text-gray-400">
            <i class="fas fa-mug-hot text-3xl mb-2"></i>
            <p class="text-sm">Für diesen Tag steht nichts an.</p>
        </div>
    @endforelse

    <p class="text-xs text-gray-400 mt-6">
        Welche Bereiche und Kalender hier erscheinen und wann Sie die Übersicht per Mail oder Push bekommen, legen Sie in den
        <a href="{{ route('benachrichtigungen.einstellungen') }}" class="text-blue-600 hover:underline">Einstellungen</a> fest.
    </p>
</div>
@endsection
