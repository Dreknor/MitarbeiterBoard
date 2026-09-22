{{-- Geburtstage-Card – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
<div class="divide-y divide-gray-100">
    @forelse($geburtstage as $emp)
        @php
            $birthday = $emp->geburtstag->copy()->year(now()->year);
            if ($birthday->lt(now()->startOfDay())) {
                $birthday->addYear();
            }
            $isToday = $birthday->isToday();
            $age = $birthday->year - $emp->geburtstag->year;
        @endphp
        <div class="flex items-center gap-3 px-4 py-3 {{ $isToday ? 'bg-amber-50 border-l-4 border-amber-400' : '' }}">
            <div class="shrink-0 text-xl">🎂</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm text-gray-800 font-medium truncate">
                    {{ $emp->vorname }} {{ $emp->familienname }}
                    @if($isToday)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 ml-1">
                            Heute!
                        </span>
                    @endif
                </div>
                <div class="text-xs text-gray-500">
                    {{ $birthday->format('d.m.') }} · wird {{ $age }} Jahre
                </div>
            </div>
        </div>
    @empty
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18z"/>
            </svg>
            <p>Keine Geburtstage in den nächsten 14 Tagen</p>
        </div>
    @endforelse
</div>

