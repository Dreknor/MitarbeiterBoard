@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-4 col-xl-3">
                <div class="card">
                    <div class="card-header bg-gradient-directional-blue text-white">
                        <h6 class="mb-0">
                            Abgeschlossene Tickets
                            <span class="badge badge-light float-right">{{ $tickets->total() }}</span>
                        </h6>
                    </div>
                    <div class="card-body p-2">
                        <form method="get" action="{{ route('tickets.archive') }}" class="mb-2">
                            <div class="input-group input-group-sm">
                                <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Suche (Titel, Text, #Nr.)">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="submit"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </form>
                        <ul class="list-group list-group-flush">
                            @forelse($tickets as $ticket)
                                <li class="list-group-item px-2 @if($show_ticket && $show_ticket->id == $ticket->id) list-group-item-info @endif">
                                    <a href="{{ route('tickets.archiveTicket', $ticket->id) }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">
                                        <span class="text-muted small">#{{ $ticket->id }}</span> {{ $ticket->title }}
                                    </a>
                                    <div class="small text-muted">
                                        @if($ticket->category) {{ $ticket->category->name }} · @endif
                                        geschlossen {{ ($ticket->closed_at ?? $ticket->updated_at)?->format('d.m.Y') }}
                                    </div>
                                </li>
                            @empty
                                <li class="list-group-item">
                                    Es sind keine abgeschlossenen Tickets vorhanden.
                                </li>
                            @endforelse
                        </ul>
                        <div class="mt-2">
                            {{ $tickets->links() }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8 col-xl-9 mt-3 mt-lg-0">
                @if($show_ticket)
                    @include('ticketsystem.show')
                @endif
            </div>
        </div>
    </div>
@endsection
