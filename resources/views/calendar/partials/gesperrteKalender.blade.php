{{-- Kalender, in die die Person schreiben dürfte, OX das MitarbeiterBoard aber nur lesen lässt. --}}
@if(($gesperrt ?? collect())->isNotEmpty())
    <div class="mt-2 flex flex-col gap-1.5">
        @foreach($gesperrt as $cal)
            <div class="flex items-center gap-2 px-2.5 py-1.5 border border-dashed border-gray-300 rounded-md bg-gray-50 cursor-not-allowed"
                 title="{{ \App\Models\OxCalendar::OX_SPERRE_HINWEIS }}">
                <i class="fas fa-lock text-xs text-gray-400 w-4 text-center"></i>
                <span class="w-2.5 h-2.5 rounded-full shrink-0 opacity-50" style="background-color: {{ $cal->farbe }}"></span>
                <span class="text-sm text-gray-500 truncate">{{ $cal->name }}</span>
                <span class="ml-auto text-xs text-gray-400 shrink-0">nur lesbar</span>
            </div>
        @endforeach
        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2.5 py-1.5">
            <i class="fas fa-info-circle mr-1"></i>
            {{ $gesperrt->count() === 1 ? 'Dieser Kalender ist' : 'Diese Kalender sind' }} gerade nicht wählbar:
            {{ \App\Models\OxCalendar::OX_SPERRE_HINWEIS }}
            Sobald die Rechte vergeben sind, wird {{ $gesperrt->count() === 1 ? 'er' : 'sie' }} bei der nächsten Synchronisation automatisch freigeschaltet.
        </p>
    </div>
@endif
