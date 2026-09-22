{{-- Aufgaben-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
{{-- E2: Fälligkeits-Gruppierung in "Überfällig", "Diese Woche", "Später" --}}
@php
    $remindDays = config('config.tasks.remind', 3);
    $now = \Carbon\Carbon::now();
    $endOfWeek = $now->copy()->endOfWeek();

    $all = $tasks ?? collect();

    $ueberfaellig = $all->filter(fn($t) => $t && $t->date->isPast())->sortBy('date');
    $dieseWoche   = $all->filter(fn($t) => $t && !$t->date->isPast() && $t->date->lte($endOfWeek))->sortBy('date');
    $spaeter      = $all->filter(fn($t) => $t && $t->date->gt($endOfWeek))->sortBy('date');
@endphp

@if($all->isEmpty())
    <div class="px-4 py-8 text-center text-gray-400 text-sm">
        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p>Keine offenen Aufgaben</p>
    </div>
@else
    <div class="divide-y divide-gray-100">

        {{-- Überfällig --}}
        @if($ueberfaellig->isNotEmpty())
            <div class="px-4 py-2 bg-red-50">
                <span class="text-xs font-semibold text-red-600 uppercase tracking-wide">
                    <i class="fas fa-exclamation-circle mr-1"></i> Überfällig
                </span>
            </div>
            @foreach($ueberfaellig as $task)
                @if($task)
                    <div class="flex items-start gap-3 px-4 py-3 bg-red-50/40">
                        <div class="text-center min-w-[3.5rem]">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                {{ $task->date->format('d.m.') }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-800 font-medium">{{ $task->taskable->name ?? '' }}</div>
                            <div class="text-xs text-gray-600 mt-0.5 truncate">{{ $task->task }}</div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            @if($task->theme?->group)
                                <a href="{{ url($task->theme->group->name . '/themes/' . $task->theme_id) }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 no-underline"
                                   title="Zum Thema">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            @endif
                            <a href="{{ url('tasks/' . $task->id . '/complete') }}"
                               class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700 hover:bg-green-200 no-underline">
                                <i class="far fa-check-square mr-1"></i>Erledigt
                            </a>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif

        {{-- Diese Woche --}}
        @if($dieseWoche->isNotEmpty())
            <div class="px-4 py-2 bg-amber-50">
                <span class="text-xs font-semibold text-amber-600 uppercase tracking-wide">
                    <i class="fas fa-clock mr-1"></i> Diese Woche
                </span>
            </div>
            @foreach($dieseWoche as $task)
                @if($task)
                    <div class="flex items-start gap-3 px-4 py-3 bg-amber-50/30">
                        <div class="text-center min-w-[3.5rem]">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                {{ $task->date->format('d.m.') }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-800 font-medium">{{ $task->taskable->name ?? '' }}</div>
                            <div class="text-xs text-gray-600 mt-0.5 truncate">{{ $task->task }}</div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            @if($task->theme?->group)
                                <a href="{{ url($task->theme->group->name . '/themes/' . $task->theme_id) }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 no-underline"
                                   title="Zum Thema">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            @endif
                            <a href="{{ url('tasks/' . $task->id . '/complete') }}"
                               class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700 hover:bg-green-200 no-underline">
                                <i class="far fa-check-square mr-1"></i>Erledigt
                            </a>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif

        {{-- Später --}}
        @if($spaeter->isNotEmpty())
            <div class="px-4 py-2 bg-gray-50">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    <i class="fas fa-calendar mr-1"></i> Später
                </span>
            </div>
            @foreach($spaeter as $task)
                @if($task)
                    <div class="flex items-start gap-3 px-4 py-3">
                        <div class="text-center min-w-[3.5rem]">
                            <span class="text-xs text-gray-500">{{ $task->date->format('d.m.Y') }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-800 font-medium">{{ $task->taskable->name ?? '' }}</div>
                            <div class="text-xs text-gray-600 mt-0.5 truncate">{{ $task->task }}</div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            @if($task->theme?->group)
                                <a href="{{ url($task->theme->group->name . '/themes/' . $task->theme_id) }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 no-underline"
                                   title="Zum Thema">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            @endif
                            <a href="{{ url('tasks/' . $task->id . '/complete') }}"
                               class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700 hover:bg-green-200 no-underline">
                                <i class="far fa-check-square mr-1"></i>Erledigt
                            </a>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>
@endif

