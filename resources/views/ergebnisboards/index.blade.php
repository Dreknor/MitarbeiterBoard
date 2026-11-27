@extends('layouts.app')

@push('css')
    @vite(['resources/css/ergebnisboards.css', 'resources/js/ergebnisboards.js'])
@endpush

@section('content')
<div class="erg-wrapper erg-schmal">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="erg-page-title">Boards</h1>
            <p class="erg-page-sub">Ergebnisse aus Sitzungen, pädagogischen Tagen und Projekten festhalten, priorisieren und weiterverfolgen.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($darfVorlagen && Route::has('ergebnisboards.vorlagen.index'))
                <a href="{{ route('ergebnisboards.vorlagen.index') }}" class="erg-btn erg-btn-secondary">
                    <i class="fas fa-shapes"></i> Vorlagen verwalten
                </a>
            @endif
            @if($darfAnlegen)
                <a href="{{ route('ergebnisboards.create') }}" class="erg-btn erg-btn-primary">
                    <i class="fas fa-plus"></i> Neues Board
                </a>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <nav class="erg-segment" aria-label="Ansicht">
            <a href="{{ route('ergebnisboards.index', array_filter(['q' => $q])) }}" class="{{ $archiv ? '' : 'is-active' }}" @if(!$archiv) aria-current="page" @endif>
                <i class="fas fa-th-large"></i> Aktuell
            </a>
            <a href="{{ route('ergebnisboards.index', array_filter(['archiv' => 1, 'q' => $q])) }}" class="{{ $archiv ? 'is-active' : '' }}" @if($archiv) aria-current="page" @endif>
                <i class="fas fa-archive"></i> Archiv
            </a>
        </nav>
        <form method="get" action="{{ route('ergebnisboards.index') }}" class="flex items-center gap-2 w-full sm:w-auto" role="search">
            @if($archiv)<input type="hidden" name="archiv" value="1">@endif
            <div class="relative flex-1 sm:w-72">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="search" name="q" value="{{ $q }}" class="erg-input" style="padding-left: 2rem" placeholder="Titel oder Untertitel suchen …" aria-label="Boards durchsuchen">
            </div>
            <button type="submit" class="erg-btn erg-btn-secondary">Suchen</button>
        </form>
    </div>

    @if($boards->isEmpty())
        <div class="erg-card">
            <div class="erg-empty">
                <i class="fas fa-th-large"></i>
                @if($q !== '')
                    <span>Keine Boards zu „{{ $q }}“ gefunden.</span>
                @elseif($archiv)
                    <span>Im Archiv liegen keine Boards.</span>
                @else
                    <span>Noch keine Boards.</span>
                    @if($darfAnlegen)
                        <a href="{{ route('ergebnisboards.create') }}" class="erg-btn erg-btn-primary mt-2"><i class="fas fa-plus"></i> Erstes Board anlegen</a>
                    @endif
                @endif
            </div>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($boards as $board)
                @php($vorlage = $vorlagen[$board->vorlage] ?? null)
                <a href="{{ route('ergebnisboards.show', $board) }}" class="erg-kachel">
                    <div class="flex items-start gap-3">
                        <span class="erg-kachel-icon" aria-hidden="true"><i class="{{ $vorlage['icon'] ?? 'fas fa-th' }}"></i></span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-bold text-slate-900 leading-snug">{{ $board->titel }}</h2>
                            <p class="text-xs text-slate-500">{{ $vorlage['titel'] ?? $board->vorlage }}</p>
                        </div>
                        @if($board->archiviert_at)
                            <span class="erg-chip shrink-0"><i class="fas fa-archive"></i> Archiv</span>
                        @endif
                    </div>
                    @if($board->untertitel)
                        <p class="text-sm text-slate-600 line-clamp-2">{{ $board->untertitel }}</p>
                    @endif
                    <div class="erg-meta mt-auto pt-2 border-t border-slate-100">
                        <span><i class="far fa-sticky-note"></i> {{ $board->karten_count }} {{ $board->karten_count === 1 ? 'Karte' : 'Karten' }}</span>
                        @if($board->meeting)
                            <span class="truncate max-w-full"><i class="far fa-calendar-alt"></i> {{ $board->meeting->title ?: 'Sitzung' }}{{ $board->meeting->date ? ' ('.\Illuminate\Support\Carbon::parse($board->meeting->date)->format('d.m.Y').')' : '' }}</span>
                        @endif
                        <span><i class="far fa-user"></i> {{ $board->ersteller?->name ?? '–' }}</span>
                        <span title="Zuletzt geändert"><i class="far fa-clock"></i> {{ $board->updated_at?->format('d.m.Y') }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        @if($boards->hasPages())
            <div class="mt-5">{{ $boards->links() }}</div>
        @endif
    @endif
</div>
@endsection
