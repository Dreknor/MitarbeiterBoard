<div class="card" id="card_{{$card->id}}">
    <div class="card-header bg-gradient-directional-blue text-white">
        <div class="pull-right">
            <a href="#" class="text-white btn btn-link" onclick="disableCard({{$card->id}})">X</a>
        </div>
        <h5>{{ $card->title }}</h5>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @forelse($ticketsCardTickets as $t)
                <li class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <div>
                            <a href="{{ route('tickets.show', $t) }}">#{{ $t->id }} {{ Str::limit($t->title,60) }}</a>
                            <small class="d-block text-muted">
                                @if($t->assigned)
                                    <i class="fa fa-user"></i> {{ $t->assigned->name }}
                                @else
                                    <i class="fa fa-user-slash"></i> nicht zugewiesen
                                @endif
                            </small>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-{{ $t->status == 'waiting' ? 'warning' : 'primary' }}">{{ $t->status }}</span>
                            <br>
                            <small title="Letzte Aktivität">{{ optional($t->last_activity)->diffForHumans() }}</small>
                        </div>
                    </div>
                    @if($t->waiting_until)
                        <div class="mt-1">
                            <small>
                                <i class="fa fa-clock"></i>
                                Termin: <span class="text-{{ $t->waiting_until->isPast() ? 'danger' : 'secondary' }}">{{ $t->waiting_until->format('d.m.Y H:i') }}</span>
                            </small>
                        </div>
                    @endif
                </li>
            @empty
                <li class="list-group-item text-muted">Keine offenen Tickets</li>
            @endforelse
        </ul>
    </div>
</div>

