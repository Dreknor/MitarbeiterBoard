{{-- Zeiterfassung (Eigene) Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
{{-- E4: Wochensaldo mit Soll-/Ist-Vergleich --}}

{{-- An-/Abmelde-CTA --}}
<div class="px-4 py-3 border-b border-gray-100">
    @if($logout == 1)
        <a href="{{ url('timesheets/' . auth()->id() . '/logout') }}"
           class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold
                  bg-red-100 text-red-700 hover:bg-red-200 transition-colors no-underline">
            <i class="fas fa-sign-out-alt"></i> Jetzt abmelden
        </a>
    @else
        <a href="{{ url('timesheets/' . auth()->id() . '/login') }}"
           class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold
                  bg-green-100 text-green-700 hover:bg-green-200 transition-colors no-underline">
            <i class="fas fa-sign-in-alt"></i> Jetzt anmelden
        </a>
    @endif
</div>

{{-- Wochensaldo --}}
@php
    $wochenstunden = convertTime($duration);
@endphp
<div class="px-4 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Diese Woche</span>
    <span class="text-sm font-bold text-gray-800">{{ $wochenstunden }} h</span>
</div>

{{-- Wochenübersicht --}}
<div class="divide-y divide-gray-100">
    @for($x = \Carbon\Carbon::now()->startOfWeek(); $x->lessThanOrEqualTo(\Carbon\Carbon::now()->endOfWeek()); $x->addDay())
        @php
            $dateKey = $x->format('Y-m-d');
            $dayEntries = $days[$dateKey] ?? [];
            $isToday = $x->isToday();
            $isWeekend = $x->isWeekend();
        @endphp
        <div class="flex items-center gap-3 px-4 py-2 {{ $isToday ? 'bg-blue-50' : ($isWeekend ? 'bg-gray-50/50' : '') }}">
            <div class="text-center min-w-[2.5rem]">
                <div class="text-xs {{ $isToday ? 'font-bold text-blue-700' : 'text-gray-500' }}">
                    {{ $x->locale('de')->isoFormat('dd') }}
                </div>
                <div class="text-xs {{ $isToday ? 'font-bold text-blue-700' : 'text-gray-400' }}">
                    {{ $x->format('d.m.') }}
                </div>
            </div>
            <div class="flex-1 min-w-0">
                @if(!empty($dayEntries))
                    @foreach($dayEntries as $timesheetDay)
                        <div class="text-xs text-gray-700">
                            @if(!is_null($timesheetDay->start) || !is_null($timesheetDay->end))
                                {{ $timesheetDay?->start?->format('H:i') }} – {{ $timesheetDay?->end?->format('H:i') ?? '…' }} Uhr
                            @elseif(!is_null($timesheetDay->percent_of_workingtime))
                                {{ $timesheetDay->comment }}
                            @endif
                        </div>
                    @endforeach
                @else
                    <span class="text-xs text-gray-300">–</span>
                @endif
            </div>
            <div class="shrink-0 text-right">
                @if(!empty($dayEntries))
                    @php $dayDuration = collect($dayEntries)->sum('duration'); @endphp
                    @if($dayDuration > 0)
                        <span class="text-xs font-medium text-gray-700">{{ convertTime($dayDuration) }}h</span>
                    @endif
                @endif
            </div>
        </div>
    @endfor
</div>

{{-- Footer --}}
<div class="px-4 py-3 border-t border-gray-100">
    <a href="{{ url('timesheets/' . auth()->id()) }}"
       class="flex items-center justify-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
        Zum Arbeitszeitnachweis →
    </a>
</div>

