{{-- freeRoomsCard-v2.blade.php – Body only, ohne Header/Footer (cardWrapper übernimmt) --}}
@php
    $allRooms = $bookableRooms ?? $freeRooms;
@endphp
@if($allRooms && $allRooms->count() > 0)
    <div class="fr-card-body p-3">
        {{-- Suchfeld (vanilla JS, da x-html kein Alpine initialisiert) --}}
        <div class="relative mb-2.5">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                </svg>
            </div>
            <input type="search" placeholder="Raum suchen …"
                   class="w-full rounded-xl border border-gray-200 pl-8 pr-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50"
                   oninput="
                       const q = this.value.toLowerCase().trim();
                       const wrapper = this.closest('.fr-card-body');
                       wrapper.querySelectorAll('[data-room-name]').forEach(el => {
                           const match = !q || el.dataset.roomName.toLowerCase().includes(q);
                           const isFree = el.dataset.roomFree === '1';
                           // Ohne Suche: nur freie zeigen; mit Suche: alle Treffer zeigen
                           el.style.display = (!q && isFree) || (q && match) ? '' : 'none';
                       });
                       const noRes = wrapper.querySelector('.fr-no-results');
                       if (noRes) {
                           const vis = [...wrapper.querySelectorAll('[data-room-name]')].filter(e => e.style.display !== 'none').length;
                           noRes.style.display = q && vis === 0 ? '' : 'none';
                       }
                   ">
        </div>
        <p class="fr-no-results hidden text-xs text-center text-gray-400 py-2">Kein Raum gefunden</p>
        <div class="grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
        @foreach($allRooms as $room)
            @php $isFree = $freeRooms->contains('id', $room->id); @endphp
            <a data-room-name="{{ $room->name }}"
               data-room-free="{{ $isFree ? '1' : '0' }}"
               href="{{ url('rooms/rooms/'.$room->id) }}"
               style="{{ !$isFree ? 'display:none' : '' }}"
               class="group flex flex-col gap-2 p-3 bg-white border border-gray-100 rounded-xl hover:border-blue-200 hover:shadow-sm no-underline">
                {{-- Raum-Name --}}
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-7 h-7 rounded-lg {{ $isFree ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500' }} flex items-center justify-center shrink-0">
                        @if($isFree)
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @endif
                    </div>
                    <span class="font-semibold text-gray-800 text-sm truncate">{{ $room->name }}</span>
                </div>

                {{-- Status --}}
                @if(!$isFree)
                    <div class="flex items-center gap-1 text-xs text-red-600 bg-red-50 rounded-lg px-2 py-1">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Derzeit belegt
                    </div>
                @elseif($room->nextBooking())
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
        </div>{{-- /.grid --}}
    </div>{{-- /.fr-card-body --}}
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
