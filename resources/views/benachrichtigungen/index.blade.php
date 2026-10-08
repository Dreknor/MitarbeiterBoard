@extends('layouts.app')

@section('title', 'Benachrichtigungen')
@section('site-title', 'Benachrichtigungen')

@push('css')
    @vite(['resources/css/benachrichtigungen.css'])
@endpush

@section('content')
<div class="bn-wrapper">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Benachrichtigungen</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                @if($ungelesen > 0)
                    {{ $ungelesen }} ungelesen
                @else
                    Alles gelesen
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($ungelesen > 0)
                <form method="POST" action="{{ route('benachrichtigungen.gelesen') }}">
                    @csrf
                    <button type="submit" class="bn-btn bn-btn-secondary">
                        <i class="fas fa-check-double"></i> Alle als gelesen markieren
                    </button>
                </form>
            @endif
            <a href="{{ route('benachrichtigungen.tag') }}" class="bn-btn bn-btn-secondary">
                <i class="fas fa-sun"></i> Mein Tag
            </a>
            <a href="{{ route('benachrichtigungen.einstellungen') }}" class="bn-btn bn-btn-primary">
                <i class="fas fa-cog"></i> Einstellungen
            </a>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
        <div class="w-full sm:w-64">
            <label for="kategorie" class="block text-xs font-medium text-gray-500 mb-1">Kategorie</label>
            <select name="kategorie" id="kategorie" class="bn-select" onchange="this.form.submit()">
                <option value="">Alle Kategorien</option>
                @foreach($kategorien as $schluessel => $kategorie)
                    <option value="{{ $schluessel }}" @selected($filterKategorie === $schluessel)>{{ $kategorie['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="bn-segment" role="radiogroup" aria-label="Status">
            <label>
                <input type="radio" name="status" value="" @checked(!$nurUngelesen) onchange="this.form.submit()">
                <span>Alle</span>
            </label>
            <label>
                <input type="radio" name="status" value="ungelesen" @checked($nurUngelesen) onchange="this.form.submit()">
                <span>Ungelesen</span>
            </label>
        </div>
        <noscript><button type="submit" class="bn-btn bn-btn-secondary bn-btn-sm">Filtern</button></noscript>
    </form>

    <div class="bn-card">
        @forelse($benachrichtigungen as $n)
            @php
                $kat = $kategorien[$n->kategorie] ?? null;
                $titel = \App\Http\Controllers\BenachrichtigungController::titel($n);
                $text = \App\Http\Controllers\BenachrichtigungController::text($n);
            @endphp
            <a href="{{ route('benachrichtigungen.oeffnen', $n->id) }}"
               class="flex items-start gap-3 px-4 sm:px-5 py-3.5 hover:bg-gray-50 {{ $n->read_at ? '' : 'bg-blue-50/60' }}"
               style="border-bottom:1px solid #f3f4f6;">
                <span class="bn-icon"><i class="fas {{ $kat['icon'] ?? 'fa-bell' }}"></i></span>
                <span class="flex-1 min-w-0">
                    <span class="flex items-start justify-between gap-3">
                        <span class="font-semibold text-gray-900 text-sm">{{ $titel }}</span>
                        @unless($n->read_at)
                            <span class="shrink-0 mt-1.5 w-2 h-2 rounded-full bg-blue-600" title="ungelesen"></span>
                        @endunless
                    </span>
                    @if($text && $text !== $titel)
                        <span class="block text-sm text-gray-600 mt-0.5">{{ $text }}</span>
                    @endif
                    <span class="block text-xs text-gray-400 mt-1">
                        {{ $kat['label'] ?? 'Allgemein' }} · {{ $n->created_at->format('d.m.Y H:i') }} ({{ $n->created_at->diffForHumans() }})
                    </span>
                </span>
            </a>
        @empty
            <div class="px-5 py-12 text-center text-gray-400">
                <i class="fas fa-bell-slash text-3xl mb-2"></i>
                <p class="text-sm">Keine Benachrichtigungen{{ $filterKategorie || $nurUngelesen ? ' für diesen Filter' : '' }}.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $benachrichtigungen->links() }}
    </div>

    <p class="text-xs text-gray-400 mt-6">
        Gelesene Benachrichtigungen werden nach {{ config('benachrichtigungen.aufbewahrung_tage') }} Tagen automatisch gelöscht.
        @if($wikiUrl)
            <a href="{{ $wikiUrl }}" class="text-blue-600 hover:underline">Hilfe zu Benachrichtigungen</a>
        @endif
    </p>
</div>
@endsection
