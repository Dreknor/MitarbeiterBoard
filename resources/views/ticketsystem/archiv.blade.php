@extends('layouts.app')

@push('css')
    @vite(['resources/css/tickets.css', 'resources/js/tickets.js'])
@endpush

@section('content')
@php
    $hasDetail = (bool) $show_ticket;
    $listQuery = request()->getQueryString();
    $isEditor = auth()->user()->can('edit tickets');
@endphp

<div class="ticket-wrapper">

    {{-- Kopfbereich --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="min-w-0">
            <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-800 mb-1">
                <i class="fas fa-arrow-left"></i> Offene Tickets
            </a>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Archiv</h1>
            <p class="text-sm text-gray-500 mt-0.5">Abgeschlossene Tickets{{ $isEditor ? '' : ', die du erstellt hast' }}</p>
        </div>
        <a href="{{ route('tickets.index', ['neu' => 1]) }}" class="tkt-btn tkt-btn-primary">
            <i class="fas fa-plus"></i><span>Neues Ticket</span>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12 items-start">
        <aside class="min-w-0 lg:col-span-5 xl:col-span-4 {{ $hasDetail ? 'hidden lg:block' : '' }}">
            <section class="tkt-card">
                <div class="tkt-card-head">
                    <h2 class="tkt-card-title"><i class="fas fa-archive"></i> Geschlossen</h2>
                    <span class="tkt-badge tkt-badge-gray">{{ $tickets->total() }}</span>
                </div>
                <form method="get" action="{{ route('tickets.archive') }}" class="px-4 py-3 border-b border-gray-100 sm:px-5">
                    <div class="tkt-search">
                        <i class="fas fa-search"></i>
                        <input type="search" name="q" value="{{ $search }}" class="tkt-input" placeholder="Suchen: Titel, Text oder #Nr." aria-label="Archiv durchsuchen">
                    </div>
                    @if($search !== '')
                        <a href="{{ route('tickets.archive') }}" class="inline-flex items-center gap-1 mt-2 text-xs font-medium text-blue-600 hover:text-blue-800">
                            <i class="fas fa-times"></i> Suche zurücksetzen
                        </a>
                    @endif
                </form>

                <div class="tkt-list {{ $tickets->hasPages() ? '' : 'rounded-b-2xl' }}">
                    @forelse($tickets as $ticket)
                        @include('ticketsystem.partials.list-item', [
                            'ticket' => $ticket,
                            'href' => route('tickets.archiveTicket', $ticket->id).($listQuery ? '?'.$listQuery : ''),
                            'active' => $show_ticket && $show_ticket->id == $ticket->id,
                            'showOwner' => $isEditor,
                            'archive' => true,
                        ])
                    @empty
                        <div class="tkt-empty">
                            <span class="tkt-empty-icon"><i class="fas fa-archive"></i></span>
                            <p>{{ $search !== '' ? 'Keine Treffer für diese Suche.' : 'Noch keine abgeschlossenen Tickets.' }}</p>
                        </div>
                    @endforelse
                </div>

                @include('ticketsystem.partials.pagination', ['paginator' => $tickets])
            </section>
        </aside>

        <main class="lg:col-span-7 xl:col-span-8 min-w-0">
            @if($show_ticket)
                <a href="{{ route('tickets.archive').($listQuery ? '?'.$listQuery : '') }}" class="lg:hidden inline-flex items-center gap-2 mb-3 text-sm font-semibold text-blue-600">
                    <i class="fas fa-arrow-left"></i> Zurück zum Archiv
                </a>
                @include('ticketsystem.show')
            @else
                <div class="hidden lg:block tkt-card">
                    <div class="tkt-empty py-16">
                        <span class="tkt-empty-icon"><i class="fas fa-mouse-pointer"></i></span>
                        <p class="font-medium text-gray-700">Ticket auswählen</p>
                        <p>Wähle links ein Ticket, um den Verlauf anzuzeigen.</p>
                    </div>
                </div>
            @endif
        </main>
    </div>
</div>
@endsection
