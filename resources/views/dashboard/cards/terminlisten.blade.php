{{-- Offene Terminlisten Card (N3) – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
@can('see terminlisten')
    @if($offeneTerminlisten->isEmpty())
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-clipboard-check text-2xl mb-2 block opacity-40"></i>
            Keine offenen Terminlisten
        </div>
    @else
        <div class="px-4 py-2 bg-amber-50 border-b border-amber-100">
            <span class="text-xs font-semibold text-amber-700">
                <i class="fas fa-exclamation-circle mr-1"></i>
                {{ $offeneTerminlisten->count() }} {{ $offeneTerminlisten->count() === 1 ? 'Terminliste' : 'Terminlisten' }} ohne Eintrag
            </span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($offeneTerminlisten as $liste)
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-800 font-medium truncate">{{ $liste->listenname }}</div>
                        @if($liste->ende)
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-clock mr-1"></i>Bis {{ $liste->ende->format('d.m.Y') }}
                                @if($liste->ende->isPast())
                                    <span class="ml-1 text-red-600 font-medium">abgelaufen</span>
                                @elseif($liste->ende->diffInDays(now()) <= 3)
                                    <span class="ml-1 text-amber-600 font-medium">bald</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <a href="{{ url('listen/' . $liste->id) }}"
                       class="shrink-0 inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium
                              bg-blue-600 text-white hover:bg-blue-700 no-underline">
                        <i class="fas fa-plus mr-1"></i> Eintragen
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Footer --}}
    <div class="px-4 py-3 border-t border-gray-100">
        <a href="{{ url('listen') }}"
           class="flex items-center justify-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
            Alle Terminlisten →
        </a>
    </div>
@else
    <div class="px-4 py-8 text-center text-gray-400 text-sm">
        Keine Berechtigung für Terminlisten.
    </div>
@endcan

