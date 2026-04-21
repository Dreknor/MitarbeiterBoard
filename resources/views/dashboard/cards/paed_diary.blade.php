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

    {{-- ─── Offene Einträge ─── --}}
    @if($paedOffeneEintraege->isNotEmpty())
        <div class="px-4 py-1.5 bg-gray-50 border-b border-gray-100">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                <i class="fas fa-pen-alt mr-1 opacity-60"></i>
                {{ $paedOffeneEintraege->count() }} offene {{ $paedOffeneEintraege->count() === 1 ? 'Eintrag' : 'Einträge' }}
            </span>
        </div>
        <div class="divide-y divide-gray-100 border-b border-gray-100">
            @foreach($paedOffeneEintraege as $eintrag)
                <a href="{{ route('paedDiary.index', ['klasse' => $eintrag->klasse_id]) }}"
                   class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 no-underline">
                    @if($eintrag->category)
                        <span class="shrink-0 w-2 h-2 rounded-full"
                              style="background-color: {{ $eintrag->category->color ?? '#6b7280' }}"></span>
                    @else
                        <i class="fas fa-pen-alt text-gray-300 text-xs shrink-0"></i>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="text-xs text-gray-700 truncate">
                            {{ $eintrag->klasse->name ?? '–' }}
                            @if($eintrag->category)
                                <span class="text-gray-400">· {{ $eintrag->category->name }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400">{{ $eintrag->datum->format('d.m.Y') }}</div>
                    </div>
                    <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                        offen
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ─── Offene Dokumentationen ─── --}}
    @if($paedOffeneDokus->isNotEmpty())
        <div class="px-4 py-1.5 bg-gray-50 border-b border-gray-100">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                <i class="fas fa-clipboard-check mr-1 opacity-60"></i>
                {{ $paedOffeneDokus->count() }} offene {{ $paedOffeneDokus->count() === 1 ? 'Dokumentation' : 'Dokumentationen' }}
            </span>
        </div>
        <div class="divide-y divide-gray-100 border-b border-gray-100">
            @foreach($paedOffeneDokus as $doku)
                @php
                    $sessionRoute = $doku->type === 'group'
                        ? route('gradingDocumentation.groupSession', $doku)
                        : route('gradingDocumentation.individualSession', $doku);
                @endphp
                <a href="{{ $sessionRoute }}"
                   class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 no-underline">
                    <div class="shrink-0 w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600">
                        <i class="fas {{ $doku->type === 'group' ? 'fa-users' : 'fa-user' }} text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs text-gray-800 truncate font-medium">
                            {{ $doku->gradingSystem->name ?? '–' }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ $doku->klasse->name ?? '–' }}
                            @if($doku->type === 'individual' && $doku->schueler)
                                · {{ $doku->schueler->vorname }} {{ $doku->schueler->nachname }}
                            @elseif($doku->type === 'group' && $doku->group)
                                · {{ $doku->group->name }}
                            @endif
                            · gestartet {{ $doku->started_at->diffForHumans() }}
                        </div>
                    </div>
                    <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                        offen
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ─── Offene Aufgaben ─── --}}
    @if($paedOffeneTasks->isNotEmpty())
        <div class="px-4 py-1.5 bg-gray-50 border-b border-gray-100">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                <i class="fas fa-tasks mr-1 opacity-60"></i>
                {{ $paedOffeneTasks->count() }} offene {{ $paedOffeneTasks->count() === 1 ? 'Aufgabe' : 'Aufgaben' }}
            </span>
        </div>
        <div class="divide-y divide-gray-100 border-b border-gray-100">
            @foreach($paedOffeneTasks as $task)
                @php
                    $ueberfaellig = $task->due_date && $task->due_date->isPast();
                    $baldfaellig  = $task->due_date && !$ueberfaellig && $task->due_date->lte(now()->addDays(3));
                @endphp
                <div class="flex items-start gap-3 px-4 py-2">
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
                        <div class="text-xs text-gray-800 truncate">{{ $task->title }}</div>
                        <div class="text-xs text-gray-500 mt-0.5 flex flex-wrap items-center gap-x-2">
                            @if($task->klasse)
                                <span>{{ $task->klasse->name }}</span>
                            @endif
                            @if($task->schueler)
                                <span>{{ $task->schueler->vorname }} {{ $task->schueler->nachname }}</span>
                            @endif
                            @if($task->due_date)
                                <span class="{{ $ueberfaellig ? 'text-red-600 font-medium' : ($baldfaellig ? 'text-amber-600' : '') }}">
                                    {{ $task->due_date->format('d.m.') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Wenn alles leer --}}
    @if($paedOffeneEintraege->isEmpty() && $paedOffeneTasks->isEmpty() && $paedOffeneDokus->isEmpty() && $paedHeuteTermine->isEmpty())
        <div class="px-4 py-6 text-center text-gray-400 text-sm">
            <i class="fas fa-check-circle text-green-400 text-2xl mb-2 block opacity-60"></i>
            Alles erledigt – keine offenen Einträge
        </div>
    @endif

    {{-- Klassen-Shortcuts --}}
    <div class="px-4 py-3 border-t border-gray-100">
        <div class="flex flex-wrap gap-1.5">
            @foreach($paedKlassen->take(8) as $klasse)
                @php $offen = $paedOffeneCounts[$klasse->id] ?? 0; @endphp
                <a href="{{ route('paedDiary.index', ['klasse' => $klasse->id]) }}"
                   class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium
                          bg-blue-50 text-blue-700 hover:bg-blue-100 no-underline">
                    {{ $klasse->name }}
                    @if($offen > 0)
                        <span class="inline-flex items-center justify-center min-w-4 h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold">
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
        <a href="{{ route('gradingDocumentation.index') }}"
           class="text-sm text-purple-600 hover:text-purple-800 no-underline font-medium">
            <i class="fas fa-clipboard-check text-xs mr-1"></i> Dokumentationen
        </a>
    </div>

@endif

@else
    <div data-card-empty="true" class="px-4 py-8 text-center text-gray-400 text-sm">
        Keine Berechtigung für das pädagogische Tagebuch.
    </div>
@endcan

