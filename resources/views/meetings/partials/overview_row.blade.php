@php
    $d           = $meeting->date->locale('de');
    $isToday     = $meeting->date->isToday();
    $isCancelled = (bool) $meeting->cancelled;
    $extraUsers  = $meeting->participantUsers->count();
@endphp
<a href="{{ route('meetings.show', $meeting) }}" class="mtg-row group {{ $isCancelled ? 'is-cancelled' : '' }}">
    <div class="mtg-datetile {{ $isCancelled ? 'is-cancelled' : ($isToday ? 'is-today' : '') }}">
        <span class="mtg-datetile-day">{{ $d->isoFormat('dd') }}</span>
        <span class="mtg-datetile-num">{{ $d->format('d') }}</span>
        <span class="mtg-datetile-month">{{ $d->isoFormat('MMM') }}</span>
    </div>

    <div class="flex-1 min-w-0 py-0.5">
        <div class="flex flex-wrap items-center gap-2 mb-1">
            <h3 class="text-base font-bold text-gray-900 truncate group-hover:text-blue-700">{{ $meeting->title }}</h3>
            @if($meeting->isFree())
                <span class="mtg-badge mtg-badge-free"><i class="fas fa-globe"></i> Frei</span>
            @else
                <span class="mtg-badge mtg-badge-group"><i class="fas fa-users"></i> {{ $meeting->group?->name }}</span>
            @endif
            @if($isCancelled)
                <span class="mtg-badge mtg-badge-red">Abgesagt</span>
            @endif
            @if((int) $meeting->creator_id === (int) auth()->id())
                <span class="mtg-badge mtg-badge-amber"><i class="fas fa-crown"></i> Organisiert</span>
            @endif
        </div>
        <div class="mtg-meta">
            <span><i class="far fa-clock"></i>{{ $meeting->start_time }} – {{ $meeting->end_time }}</span>
            @if($meeting->roomBooking?->room)
                <span><i class="fas fa-door-open"></i>{{ $meeting->roomBooking->room->name }}</span>
            @elseif($meeting->location)
                <span><i class="fas fa-map-marker-alt"></i>{{ $meeting->location }}</span>
            @endif
            @if($meeting->effectiveMeetingUrl())
                <span><i class="fas fa-video"></i>Online</span>
            @endif
            <span><i class="far fa-comments"></i>{{ $meeting->themes_count }} {{ $meeting->themes_count === 1 ? 'Thema' : 'Themen' }}</span>
            @if($extraUsers || $meeting->participantGroups->isNotEmpty() || $meeting->participantRoles->isNotEmpty())
                <span class="truncate">
                    <i class="fas fa-user-friends"></i>
                    {{ collect([
                        $extraUsers ? $extraUsers . ' ' . ($extraUsers === 1 ? 'Person' : 'Personen') : null,
                        $meeting->participantGroups->pluck('name')->implode(', ') ?: null,
                        $meeting->participantRoles->pluck('name')->implode(', ') ?: null,
                    ])->filter()->implode(' · ') }}
                </span>
            @endif
        </div>
    </div>

    <div class="hidden sm:flex items-center text-gray-300 group-hover:text-blue-500 pr-1">
        <i class="fas fa-chevron-right"></i>
    </div>
</a>
