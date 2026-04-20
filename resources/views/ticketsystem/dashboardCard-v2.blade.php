{{-- Tickets-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
<div class="divide-y divide-gray-100">
    @forelse($ticketsCardTickets as $ticket)
        <a href="{{ route('tickets.show', $ticket) }}"
           class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 no-underline">
            <div class="flex-1 min-w-0">
                <div class="text-sm text-gray-800 truncate">
                    <span class="text-gray-400 text-xs">#{{ $ticket->id }}</span>
                    {{ \Illuminate\Support\Str::limit($ticket->title, 60) }}
                </div>
                <div class="text-xs text-gray-500 mt-0.5">
                    @if($ticket->assigned)
                        <i class="fas fa-user opacity-60 mr-1"></i>{{ $ticket->assigned->name }}
                    @else
                        <i class="fas fa-user-slash opacity-60 mr-1"></i>nicht zugewiesen
                    @endif
                    @if($ticket->waiting_until)
                        &nbsp;·&nbsp;
                        <i class="fas fa-clock opacity-60 mr-1"></i>
                        <span class="{{ $ticket->waiting_until->isPast() ? 'text-red-600 font-medium' : '' }}">
                            {{ $ticket->waiting_until->format('d.m.Y H:i') }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="shrink-0">
                @if($ticket->status === 'waiting')
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                        wartend
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                        offen
                    </span>
                @endif
            </div>
        </a>
    @empty
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p>Keine offenen Tickets</p>
        </div>
    @endforelse
</div>

{{-- Footer --}}
<div class="px-4 py-3 border-t border-gray-100">
    <a href="{{ route('tickets.index') }}"
       class="flex items-center justify-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
        Alle Tickets anzeigen →
    </a>
</div>

