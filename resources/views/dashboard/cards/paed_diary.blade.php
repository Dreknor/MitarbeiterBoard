{{-- Pädagogisches Tagebuch – Dashboard Card --}}
@can('view paed diary')

@if($paedKlassen->isEmpty())
    <div data-card-empty="true" class="px-4 py-8 text-center text-gray-400 text-sm">
        <i class="fas fa-book-reader text-2xl mb-2 block opacity-40"></i>
        Keine Klassen zugewiesen
    </div>
@else

    {{-- Heutige Termine --}}
    @if($paedHeuteTermine->isNotEmpty())
        <div class="px-4 py-2 border-b border-gray-100 bg-blue-50">
            <div class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">
                <i class="fas fa-calendar-day mr-1"></i> Heute
            </div>
            <div class="space-y-1">
                @foreach($paedHeuteTermine->take(3) as $termin)
                    <div class="flex items-start gap-2 text-xs">
                        <span class="shrink-0 font-mono text-blue-700">
                            @if(!empty($termin['start_time']))
                                {{ \Carbon\Carbon::parse($termin['start_time'])->format('H:i') }}
                            @else
                                <span class="text-blue-400">–</span>
                            @endif
                        </span>
                        <span class="flex-1 text-gray-800 truncate">{{ $termin['title'] }}</span>
                    </div>
                @endforeach
                @if($paedHeuteTermine->count() > 3)
                    <div class="text-xs text-blue-600">
                        + {{ $paedHeuteTermine->count() - 3 }} weitere
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Offene Aufgaben --}}
    @if($paedOffeneTasks->isNotEmpty())
        <div class="divide-y divide-gray-100">
            <div class="px-4 py-2 bg-gray-50">
                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    <i class="fas fa-tasks mr-1 opacity-60"></i>
                    {{ $paedOffeneTasks->count() }} offene {{ $paedOffeneTasks->count() === 1 ? 'Aufgabe' : 'Aufgaben' }}
                </span>
            </div>
            @foreach($paedOffeneTasks as $task)
                @php
                    $ueberfaellig = $task->due_date && $task->due_date->isPast();
                    $baldfaellig  = $task->due_date && !$ueberfaellig && $task->due_date->lte(now()->addDays(3));
                @endphp
                <div class="flex items-start gap-3 px-4 py-2.5">
                    <div class="shrink-0 mt-0.5">
                        @if($task->highlighted)
                            <i class="fas fa-star text-amber-400 text-xs" title="Wichtig"></i>
                        @elseif($ueberfaellig)
                            <i class="fas fa-exclamation-circle text-red-500 text-xs"></i>
                        @else
                            <i class="far fa-circle text-gray-300 text-xs"></i>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-800 truncate">{{ $task->title }}</div>
                        <div class="text-xs text-gray-500 mt-0.5 flex flex-wrap items-center gap-x-2">
                            @if($task->klasse)
                                <span><i class="fas fa-chalkboard-teacher opacity-50 mr-0.5"></i>{{ $task->klasse->name }}</span>
                            @endif
                            @if($task->schueler)
                                <span><i class="fas fa-user-graduate opacity-50 mr-0.5"></i>{{ $task->schueler->vorname }} {{ $task->schueler->nachname }}</span>
                            @endif
                            @if($task->due_date)
                                <span class="{{ $ueberfaellig ? 'text-red-600 font-medium' : ($baldfaellig ? 'text-amber-600' : '') }}">
                                    <i class="far fa-clock opacity-50 mr-0.5"></i>{{ $task->due_date->format('d.m.Y') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Letzter Eintrag --}}
    @if($paedLetzterEintrag)
        <div class="px-4 py-2 border-t border-gray-100 text-xs text-gray-500">
            <i class="fas fa-pen-alt opacity-60 mr-1"></i>
            Letzter Eintrag:
            <span class="font-medium text-gray-700">{{ $paedLetzterEintrag->klasse->name ?? '–' }}</span>
            &middot; {{ $paedLetzterEintrag->datum->format('d.m.Y') }}
        </div>
    @endif

    {{-- Klassen-Shortcuts --}}
    <div class="px-4 py-3 border-t border-gray-100">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
            <i class="fas fa-chalkboard mr-1 opacity-60"></i>
            {{ $paedKlassen->count() }} {{ $paedKlassen->count() === 1 ? 'Klasse' : 'Klassen' }}
        </div>
        <div class="flex flex-wrap gap-1.5">
            @foreach($paedKlassen->take(8) as $klasse)
                @php $offen = $paedOffeneCounts[$klasse->id] ?? 0; @endphp
                <a href="{{ route('paedDiary.index', ['klasse' => $klasse->id]) }}"
                   class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium
                          bg-blue-50 text-blue-700 hover:bg-blue-100 no-underline">
                    {{ $klasse->name }}
                    @if($offen > 0)
                        <span class="inline-flex items-center justify-center min-w-[1rem] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold">
                            {{ $offen }}
                        </span>
                    @endif
                </a>
            @endforeach
            @if($paedKlassen->count() > 8)
                <span class="inline-flex items-center px-2 py-1 text-xs text-gray-500">
                    + {{ $paedKlassen->count() - 8 }}
                </span>
            @endif
        </div>
    </div>

    {{-- Footer --}}
    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
        <a href="{{ route('paedDiary.index') }}"
           class="text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
            <i class="fas fa-book-open text-xs mr-1"></i> Zum Tagebuch
        </a>
        <a href="{{ route('paedDiary.index') }}#new-entry"
           class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-medium
                  bg-blue-600 text-white hover:bg-blue-700 no-underline">
            <i class="fas fa-plus"></i> Neuer Eintrag
        </a>
    </div>

@endif

@else
    <div data-card-empty="true" class="px-4 py-8 text-center text-gray-400 text-sm">
        Keine Berechtigung für das pädagogische Tagebuch.
    </div>
@endcan


