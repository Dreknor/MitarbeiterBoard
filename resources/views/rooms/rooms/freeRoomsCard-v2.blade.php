{{-- freeRoomsCard-v2.blade.php – Body only, ohne Header/Footer (cardWrapper übernimmt) --}}
@if($freeRooms && $freeRooms->count() > 0)
    <div class="p-3 grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
        @foreach($freeRooms as $room)
            <a href="{{ url('rooms/rooms/'.$room->id) }}"
               class="group flex flex-col gap-2 p-3 bg-white border border-gray-100 rounded-xl hover:border-blue-200 hover:shadow-sm no-underline">
                {{-- Raum-Name --}}
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-7 h-7 rounded-lg bg-green-100 text-green-600 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <span class="font-semibold text-gray-800 text-sm truncate">{{ $room->name }}</span>
                </div>

                {{-- Status --}}
                @if($room->nextBooking())
                    <div class="flex items-center gap-1 text-xs text-amber-600 bg-amber-50 rounded-lg px-2 py-1">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="truncate">{{ \Carbon\Carbon::parse($room->nextBooking()->start)->diffForHumans() }} belegt</span>
                    </div>
                @else
                    <div class="flex items-center gap-1 text-xs text-green-600 bg-green-50 rounded-lg px-2 py-1">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Keine Buchungen
                    </div>
                @endif
            </a>
        @endforeach
    </div>
@else
    <div class="px-4 py-8 text-center text-gray-400 text-sm">
        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
        </svg>
        <p>Derzeit keine freien Räume verfügbar</p>
    </div>
@endif

{{-- Footer-Link --}}
<div class="px-4 py-3 border-t border-gray-100">
    <a href="{{ url('rooms/rooms') }}"
       class="flex items-center justify-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
        Alle Räume anzeigen →
    </a>
</div>

