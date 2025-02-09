@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-md-3">
                <div class="card">
                    <div class="card-header bg-gradient-directional-blue text-white">
                        <h6>
                            abgeschlossene Tickets
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            @forelse($tickets as $ticket)
                                <li class="list-group-item
                                @if(isset($show_ticket) and $show_ticket->id == $ticket->id)
                                    list-group-item-info
                                @endif
                                ">
                                    <a href="{{ route('tickets.archiveTicket', $ticket->id) }}">{{ $ticket->title }}</a>
                                </li>
                            @empty
                                <li class="list-group-item">
                                    Es sind keine abgeschlossenen Tickets vorhanden.
                                </li>
                            @endforelse

                        </ul>
                    </div>
                </div>

            </div>
            <div class="col-12 col-md-9">
                @if(isset($show_ticket))
                    @include('ticketsystem.show')
                @endif
            </div>
        </div>
    </div>
@endsection


