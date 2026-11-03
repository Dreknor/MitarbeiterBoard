{{-- Ein Ticket in der Seitenliste: Titel im Vordergrund, Metadaten dezent --}}
@php
    $statusDot = ['open' => 'bg-blue-500', 'waiting' => 'bg-amber-400', 'closed' => 'bg-gray-400'][$ticket->status] ?? 'bg-gray-400';
@endphp
<a href="{{ $href }}"
   class="tkt-item prio-{{ $ticket->priority }} {{ $active ? 'is-active' : '' }}"
   @if($active) aria-current="page" @endif>
    <div class="flex items-start justify-between gap-3">
        <h3 class="tkt-item-title tkt-clamp-2 min-w-0">{{ $ticket->title }}</h3>
        <span class="shrink-0 text-[11px] text-gray-400 whitespace-nowrap pt-0.5" title="Letzte Aktivität">
            {{ $ticket->last_activity?->shortRelativeDiffForHumans() }}
        </span>
    </div>

    <div class="tkt-item-meta">
        @if($ticket->priority === 'high' && !$ticket->isClosed())
            <span class="font-semibold text-red-600"><i class="fas fa-arrow-up text-red-500"></i>Hoch</span>
        @endif

        @if($ticket->isWaitingOverdue())
            <span class="font-semibold text-red-600"><i class="fas fa-hourglass-end text-red-500"></i>überfällig</span>
        @elseif(!($archive ?? false))
            <span class="inline-flex items-center gap-1.5">
                <span class="tkt-dot {{ $statusDot }}"></span>
                {{ $ticket->status_label }}@if($ticket->isWaiting() && $ticket->waiting_until) bis {{ $ticket->waiting_until->format('d.m.') }}@endif
            </span>
        @else
            <span><i class="fas fa-check"></i>{{ ($ticket->closed_at ?? $ticket->updated_at)?->format('d.m.Y') }}</span>
        @endif

        @if($ticket->category)
            <span class="truncate"><i class="fas fa-tag"></i>{{ $ticket->category->name }}</span>
        @endif

        @if(!($archive ?? false))
            @if($ticket->assigned)
                <span class="truncate"><i class="fas fa-user-check"></i>{{ $ticket->assigned->name }}</span>
            @else
                <span class="text-amber-700"><i class="fas fa-user-slash text-amber-500"></i>offen</span>
            @endif
        @elseif($showOwner ?? false)
            <span class="truncate"><i class="fas fa-user-edit"></i>{{ $ticket->user?->name ?? 'unbekannt' }}</span>
        @endif

        @if(!empty($ticket->comments_count))
            <span class="ml-auto"><i class="far fa-comment"></i>{{ $ticket->comments_count }}</span>
        @endif
    </div>

    @if(($showOwner ?? false) && !($archive ?? false))
        <p class="mt-1 text-[11px] text-gray-400 truncate">von {{ $ticket->user?->name ?? 'unbekannt' }}</p>
    @endif
</a>
