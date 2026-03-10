<div class="rooms-wrapper" id="card_{{$card->id}}">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-3">
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-500 text-white">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <h2 class="font-semibold text-sm">Freie Räume</h2>
                @if($freeRooms && $freeRooms->count() > 0)
                    <span class="text-xs bg-white bg-opacity-25 px-2 py-0.5 rounded-full font-medium">
                        {{ $freeRooms->count() }}
                    </span>
                @endif
            </div>
            <button onclick="disableCard('{{$card->id}}')" title="Karte schließen"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-white opacity-70 hover:opacity-100 hover:bg-white hover:bg-opacity-20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="p-3">
            @if($freeRooms && $freeRooms->count() > 0)
                <div class="grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2.5">
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
                                    <span class="truncate">{{ Carbon\Carbon::parse($room->nextBooking()->start)->diffForHumans() }} belegt</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1 text-xs text-green-600 bg-green-50 rounded-lg px-2 py-1">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Keine Buchungen
                                </div>
                            @endif

                            {{-- Details-Link-Pfeil --}}
                            <div class="flex items-center justify-end text-blue-400 group-hover:text-blue-600 text-xs gap-1">
                                <span>Details</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                    <svg class="w-10 h-10 mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    <p class="text-sm">Derzeit keine freien Räume verfügbar</p>
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="px-3 pb-3">
            <a href="{{ url('rooms/rooms') }}"
               class="flex items-center justify-center gap-2 w-full py-2 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 text-sm font-medium no-underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Alle Räume anzeigen
            </a>
        </div>
    </div>
</div>

