@can('view calendar')
{{-- Kalender-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
<div class="divide-y divide-gray-100">
    @forelse($naechsteTermine as $termin)
        <a href="{{ route('calendar.index') }}"
           class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 no-underline"
           style="border-left: 3px solid {{ $termin->kalender->farbe ?? '#3b82f6' }}">
            {{-- Datum + Uhrzeit --}}
            <div class="shrink-0 text-center min-w-[40px]">
                <div class="text-sm font-bold text-gray-800">
                    {{ $termin->beginn->timezone('Europe/Berlin')->format('d.m.') }}
                </div>
                @if(!$termin->ganztaegig)
                    <div class="text-xs text-gray-500">
                        {{ $termin->beginn->timezone('Europe/Berlin')->format('H:i') }}
                    </div>
                @endif
            </div>
            {{-- Titel + Ort --}}
            <div class="flex-1 min-w-0">
                <div class="text-sm text-gray-800 truncate">{{ $termin->titel }}</div>
                @if($termin->ort)
                    <div class="text-xs text-gray-500 truncate">
                        <i class="fas fa-map-marker-alt opacity-60 mr-1"></i>{{ $termin->ort }}
                    </div>
                @endif
            </div>
        </a>
    @empty
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p>Keine anstehenden Termine.</p>
        </div>
    @endforelse
</div>

{{-- Footer --}}
<div class="px-4 py-3 border-t border-gray-100">
    <a href="{{ route('calendar.index') }}"
       class="flex items-center justify-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
        Alle Termine anzeigen →
    </a>
</div>
@endcan

