{{-- Zeiterfassung Übersicht Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
{{-- Zeigt welche Mitarbeiter gerade angemeldet sind --}}

@if(count($users) > 0)
    <div class="px-4 py-2 bg-green-50 border-b border-green-100">
        <span class="text-xs font-semibold text-green-700">
            <i class="fas fa-circle text-green-500 mr-1"></i>
            {{ count($users) }} {{ count($users) === 1 ? 'Person' : 'Personen' }} angemeldet
        </span>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($users as $name => $time)
            <div class="flex items-center gap-3 px-4 py-2.5">
                <div class="shrink-0 w-7 h-7 rounded-full bg-green-100 flex items-center justify-center text-green-700 text-xs font-bold">
                    {{ strtoupper(substr($name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm text-gray-800 font-medium truncate">{{ $name }}</div>
                </div>
                <div class="shrink-0 text-right">
                    <span class="text-xs text-gray-500">seit {{ $time }} Uhr</span>
                    <span class="ml-1 inline-flex items-center w-2 h-2 rounded-full bg-green-500"></span>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="px-4 py-8 text-center text-gray-400 text-sm">
        <i class="fas fa-user-clock text-2xl mb-2 block opacity-40"></i>
        Keine Mitarbeiter angemeldet
    </div>
@endif

{{-- Footer --}}
<div class="px-4 py-3 border-t border-gray-100">
    <a href="{{ url('timesheets') }}"
       class="flex items-center justify-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
        Alle Arbeitszeitnachweise →
    </a>
</div>

