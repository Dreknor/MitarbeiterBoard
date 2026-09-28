@extends('layouts.app')

@push('css')
    @vite(['resources/css/tickets.css', 'resources/js/tickets.js'])
@endpush

@section('content')
@php
    $isEditor = auth()->user()->can('edit tickets');
    $listQuery = request()->getQueryString();
    $hasDetail = (bool) $show_ticket;
    $openCreate = !$hasDetail && ($errors->any() || request()->boolean('neu'));
    $activeFilters = collect([$filters['status'], $filters['priority'], $filters['category']])->filter()->count()
        + ($filters['sort'] !== 'activity' ? 1 : 0)
        + ($filters['scope'] === 'created' ? 1 : 0);
    $withQuery = fn ($url) => $url.($listQuery ? '?'.$listQuery : '');
@endphp

<div class="ticket-wrapper" x-data="{ create: {{ $openCreate ? 'true' : 'false' }} }">

    {{-- Kopfbereich --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Ticketsystem</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                @if($isEditor) Anfragen bearbeiten, zuweisen und nachverfolgen @else Deine Anfragen an das Support-Team @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('tickets.archive') }}" class="tkt-btn tkt-btn-secondary" title="Abgeschlossene Tickets">
                <i class="fas fa-archive"></i><span class="hidden sm:inline">Archiv</span>
            </a>
            @if($isEditor)
                <a href="{{ route('categories.index') }}" class="tkt-btn tkt-btn-secondary" title="Kategorien verwalten">
                    <i class="fas fa-tags"></i><span class="hidden md:inline">Kategorien</span>
                </a>
            @endif
            <a href="{{ route('tickets.index', ['neu' => 1]) }}" class="tkt-btn tkt-btn-primary"
               @if(!$hasDetail) @click.prevent="create = true; $nextTick(() => { window.scrollTo({ top: 0, behavior: 'smooth' }); document.getElementById('title')?.focus(); })" @endif>
                <i class="fas fa-plus"></i><span>Neues Ticket</span>
            </a>
        </div>
    </div>

    {{-- Kennzahlen / Schnellfilter (Bearbeiter) --}}
    @if($stats)
        <div class="{{ $hasDetail ? 'hidden lg:grid' : 'grid' }} grid-cols-2 lg:grid-cols-4 gap-3 mb-5"
             @if(!$hasDetail) :class="{ 'hidden lg:grid': create }" @endif>
            <a href="{{ route('tickets.index') }}" class="tkt-stat {{ $filters['scope'] === 'all' && !$filters['status'] ? 'is-active' : '' }}">
                <span class="tkt-stat-icon bg-blue-50 text-blue-600"><i class="fas fa-inbox"></i></span>
                <span class="min-w-0"><span class="tkt-stat-value block">{{ $stats['open'] }}</span><span class="tkt-stat-label block">offen gesamt</span></span>
            </a>
            <a href="{{ route('tickets.index', ['scope' => 'unassigned']) }}" class="tkt-stat {{ $filters['scope'] === 'unassigned' ? 'is-active' : '' }}">
                <span class="tkt-stat-icon bg-amber-50 text-amber-600"><i class="fas fa-user-slash"></i></span>
                <span class="min-w-0"><span class="tkt-stat-value block">{{ $stats['unassigned'] }}</span><span class="tkt-stat-label block">nicht zugewiesen</span></span>
            </a>
            <a href="{{ route('tickets.index', ['scope' => 'mine']) }}" class="tkt-stat {{ $filters['scope'] === 'mine' ? 'is-active' : '' }}">
                <span class="tkt-stat-icon bg-emerald-50 text-emerald-600"><i class="fas fa-user-check"></i></span>
                <span class="min-w-0"><span class="tkt-stat-value block">{{ $stats['mine'] }}</span><span class="tkt-stat-label block">mir zugewiesen</span></span>
            </a>
            <a href="{{ route('tickets.index', ['status' => 'waiting']) }}" class="tkt-stat {{ $filters['status'] === 'waiting' && $filters['scope'] === 'all' ? 'is-active' : '' }}">
                <span class="tkt-stat-icon {{ $stats['overdue'] ? 'bg-red-50 text-red-600' : 'bg-violet-50 text-violet-600' }}"><i class="fas fa-hourglass-half"></i></span>
                <span class="min-w-0">
                    <span class="tkt-stat-value block">{{ $stats['waiting'] }}</span>
                    <span class="tkt-stat-label block">
                        wartend
                        @if($stats['overdue'])
                            <span class="text-red-600 font-semibold">· {{ $stats['overdue'] }} überfällig</span>
                        @endif
                    </span>
                </span>
            </a>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-12 items-start">

        {{-- ── Liste ─────────────────────────────────────────────── --}}
        <aside class="lg:col-span-5 xl:col-span-4 flex flex-col gap-5 {{ $hasDetail ? 'hidden lg:flex' : '' }}"
               @if(!$hasDetail) :class="{ 'hidden lg:flex': create }" @endif>

            <section class="tkt-card" x-data="{ filtersOpen: {{ $activeFilters > 0 ? 'true' : 'false' }} }">
                <div class="tkt-card-head">
                    <h2 class="tkt-card-title">
                        <i class="fas fa-ticket-alt"></i>
                        @if($isEditor) Offene Tickets @else Meine offenen Tickets @endif
                    </h2>
                    <span class="tkt-badge tkt-badge-gray">{{ $tickets->count() }}</span>
                </div>

                <form method="get" action="{{ route('tickets.index') }}" class="px-4 py-3 border-b border-gray-100 sm:px-5">
                    <div class="flex gap-2">
                        <div class="tkt-search flex-1 min-w-0">
                            <i class="fas fa-search"></i>
                            <input type="search" name="q" value="{{ $filters['q'] }}" class="tkt-input" placeholder="Suchen: Titel, Text oder #Nr." aria-label="Tickets durchsuchen">
                        </div>
                        <button type="button" class="tkt-btn tkt-btn-secondary relative" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" title="Filter">
                            <i class="fas fa-sliders-h"></i>
                            <span class="hidden sm:inline">Filter</span>
                            @if($activeFilters)
                                <span class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center">{{ $activeFilters }}</span>
                            @endif
                        </button>
                    </div>

                    <div x-show="filtersOpen" x-collapse x-cloak>
                        <div class="grid grid-cols-2 gap-2 pt-3">
                            @if($isEditor)
                                <label class="col-span-2 sm:col-span-1">
                                    <span class="tkt-label text-xs">Ansicht</span>
                                    <select name="scope" class="tkt-select" onchange="this.form.submit()">
                                        <option value="all" @selected($filters['scope'] == 'all')>Alle</option>
                                        <option value="mine" @selected($filters['scope'] == 'mine')>Mir zugewiesen</option>
                                        <option value="unassigned" @selected($filters['scope'] == 'unassigned')>Nicht zugewiesen</option>
                                        <option value="created" @selected($filters['scope'] == 'created')>Von mir erstellt</option>
                                    </select>
                                </label>
                            @endif
                            <label>
                                <span class="tkt-label text-xs">Status</span>
                                <select name="status" class="tkt-select" onchange="this.form.submit()">
                                    <option value="">alle</option>
                                    <option value="open" @selected($filters['status'] == 'open')>offen</option>
                                    <option value="waiting" @selected($filters['status'] == 'waiting')>wartend</option>
                                </select>
                            </label>
                            <label>
                                <span class="tkt-label text-xs">Priorität</span>
                                <select name="priority" class="tkt-select" onchange="this.form.submit()">
                                    <option value="">alle</option>
                                    @foreach(\App\Models\Ticket::PRIORITY_LABELS as $value => $label)
                                        <option value="{{ $value }}" @selected($filters['priority'] == $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @if($categories->isNotEmpty())
                                <label>
                                    <span class="tkt-label text-xs">Kategorie</span>
                                    <select name="category" class="tkt-select" onchange="this.form.submit()">
                                        <option value="">alle</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected($filters['category'] == $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            <label>
                                <span class="tkt-label text-xs">Sortierung</span>
                                <select name="sort" class="tkt-select" onchange="this.form.submit()">
                                    <option value="activity" @selected($filters['sort'] == 'activity')>Letzte Aktivität</option>
                                    <option value="priority" @selected($filters['sort'] == 'priority')>Priorität</option>
                                    <option value="created" @selected($filters['sort'] == 'created')>Neueste zuerst</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    @if($listQuery)
                        <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-1 mt-2 text-xs font-medium text-blue-600 hover:text-blue-800">
                            <i class="fas fa-times"></i> Filter zurücksetzen
                        </a>
                    @endif
                </form>

                <div class="tkt-list rounded-b-2xl lg:max-h-[calc(100vh-22rem)] lg:overflow-y-auto">
                    @forelse($tickets as $ticket)
                        @include('ticketsystem.partials.list-item', [
                            'ticket' => $ticket,
                            'href' => $withQuery(route('tickets.show', $ticket->id)),
                            'active' => $show_ticket && $show_ticket->id == $ticket->id,
                            'showOwner' => $isEditor,
                        ])
                    @empty
                        <div class="tkt-empty">
                            <span class="tkt-empty-icon"><i class="fas {{ $listQuery ? 'fa-filter' : 'fa-check' }}"></i></span>
                            @if($listQuery)
                                <p class="font-medium text-gray-700">Keine Tickets für diese Filter</p>
                                <a href="{{ route('tickets.index') }}" class="text-blue-600 font-medium">Filter zurücksetzen</a>
                            @else
                                <p class="font-medium text-gray-700">Alles erledigt!</p>
                                <p>Es gibt keine offenen Tickets.</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </section>

            @if($pinned->isNotEmpty())
                <section class="tkt-card">
                    <div class="tkt-card-head">
                        <h2 class="tkt-card-title"><i class="fas fa-thumbtack"></i> Angepinnt</h2>
                        <span class="tkt-badge tkt-badge-gray">{{ $pinned->count() }}</span>
                    </div>
                    <ul>
                        @foreach($pinned as $ticket)
                            <li>
                                <a href="{{ route('tickets.show', $ticket->id) }}"
                                   class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 hover:bg-gray-50 sm:px-5 {{ $show_ticket && $show_ticket->id == $ticket->id ? 'bg-blue-50' : '' }}">
                                    <i class="fas fa-thumbtack text-amber-400 text-xs"></i>
                                    <span class="flex-1 min-w-0 truncate text-sm font-medium text-gray-800">
                                        {{ $ticket->title }}
                                    </span>
                                    @if($ticket->isClosed())
                                        <span class="tkt-badge tkt-badge-gray">geschlossen</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>

        {{-- ── Detail / neues Ticket ─────────────────────────────── --}}
        <main class="lg:col-span-7 xl:col-span-8 min-w-0">
            @if($show_ticket)
                <a href="{{ $withQuery(route('tickets.index')) }}" class="lg:hidden inline-flex items-center gap-2 mb-3 text-sm font-semibold text-blue-600">
                    <i class="fas fa-arrow-left"></i> Alle Tickets
                </a>
                @include('ticketsystem.show')
            @else
                <div class="{{ $openCreate ? '' : 'hidden' }} lg:block" :class="{ 'hidden': !create }">
                    <button type="button" class="lg:hidden inline-flex items-center gap-2 mb-3 text-sm font-semibold text-blue-600" @click="create = false">
                        <i class="fas fa-arrow-left"></i> Zurück zur Liste
                    </button>
                    @include('ticketsystem.create')
                </div>
            @endif
        </main>
    </div>
</div>
@endsection

@push('js')
    <script src="{{ asset('js/plugins/tinymce/tinymce.min.js') }}"></script>
@endpush
