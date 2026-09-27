{{-- Einzelne Aufgabe – erwartet $task, $ui, $me, $taskService, $initials --}}
@php
    $rows      = $task->taskUsers->sortBy(fn ($r) => [$r->completed_at === null ? 1 : 0, $r->user?->name]);
    $doneRows  = $rows->whereNotNull('completed_at');
    $total     = $rows->count();
    $percent   = $total ? round($doneRows->count() / $total * 100) : 0;
    $overdue   = ! $task->completed && $task->date->lt(now()->startOfDay());
    $openForMe = $task->isOpenFor($me);
    $canDelete = ! $task->completed && $taskService->canDelete($task, $me);
@endphp
<li class="rounded-xl border-[1px] px-3.5 py-2.5 {{ $task->completed ? 'border-emerald-100 bg-emerald-50/40' : ($overdue ? 'border-red-100 bg-red-50/40' : 'border-gray-200 bg-white') }}">
    <div class="flex items-start gap-2.5">
        <span class="mt-0.5 shrink-0 {{ $task->completed ? 'text-emerald-500' : ($openForMe ? 'text-blue-500' : 'text-gray-300') }}">
            <i class="{{ $task->completed ? 'fas fa-check-circle' : 'far fa-circle' }}"></i>
        </span>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium break-words {{ $task->completed ? 'text-gray-500 line-through' : 'text-gray-900' }}">{{ $task->task }}</p>

            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1 text-xs text-gray-500">
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold whitespace-nowrap {{ $overdue ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700' }}"
                      title="{{ $overdue ? 'überfällig' : 'fällig' }}">
                    <i class="far fa-calendar"></i> {{ $task->date->format('d.m.Y') }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <i class="fas {{ $task->isPersonal() ? 'fa-user' : 'fa-users' }} text-gray-400"></i> {{ $task->ownerLabel() }}
                </span>
                @if($task->creator)
                    <span class="text-gray-400">· von {{ $task->creator->name }}</span>
                @endif
            </div>

            @if($task->completed)
                <p class="text-xs text-emerald-700 mt-1">
                    <i class="fas fa-check"></i>
                    erledigt{{ $task->completed_at ? ' am ' . $task->completed_at->format('d.m.Y') : '' }}{{ $task->isPersonal() && $task->completedBy ? ' von ' . $task->completedBy->name : '' }}{{ $task->isCollective() && $total ? ' – alle ' . $total . ' Zuständigen' : '' }}
                </p>
            @endif

            @if($task->isCollective() && $total)
                <details class="mt-2" @if(! $task->completed && $total <= 6) open @endif>
                    <summary class="cursor-pointer select-none" style="list-style: none;">
                        <span class="flex items-center gap-2 text-xs text-gray-600">
                            <span class="block flex-1 h-1.5 rounded-full bg-gray-200 overflow-hidden" aria-hidden="true">
                                <span class="block h-full rounded-full bg-emerald-500" style="width: {{ $percent }}%"></span>
                            </span>
                            <span class="shrink-0 font-semibold whitespace-nowrap">{{ $doneRows->count() }}/{{ $total }} erledigt</span>
                            <i class="fas fa-chevron-down text-[10px] text-gray-400"></i>
                        </span>
                    </summary>
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($rows as $row)
                            @continue(! $row->user)
                            <li class="inline-flex items-center gap-1.5 rounded-full border-[1px] pl-0.5 pr-2.5 py-0.5 text-xs whitespace-nowrap {{ $row->completed_at ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-gray-50 border-gray-200 text-gray-600' }}"
                                title="{{ $row->completed_at ? 'erledigt am ' . $row->completed_at->format('d.m.Y H:i') : 'noch offen' }}">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[9px] font-bold {{ $row->completed_at ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                                    @if($row->completed_at)<i class="fas fa-check"></i>@else{{ $initials($row->user->name) }}@endif
                                </span>
                                {{ $row->user->name }}
                                @if($row->completed_at)
                                    <span class="text-emerald-600/70">{{ $row->completed_at->format('d.m.') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if($openForMe || $canDelete)
                <div class="flex items-center justify-end gap-1.5 mt-2">
                    @if($canDelete)
                        <form action="{{ route('tasks.destroy', $task) }}" method="POST" onsubmit="return confirm('Aufgabe wirklich löschen?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-transparent text-gray-400 hover:text-red-600 hover:bg-red-50 cursor-pointer" style="border:0" title="Aufgabe löschen" aria-label="Aufgabe löschen">
                                <i class="far fa-trash-alt text-xs"></i>
                            </button>
                        </form>
                    @endif
                    @if($openForMe)
                        <a href="{{ route('tasks.complete', $task) }}" class="{{ $ui }}-btn {{ $ui }}-btn-success {{ $ui }}-btn-sm">
                            <i class="fas fa-check"></i> {{ $task->isCollective() ? 'Meinen Teil erledigt' : 'Erledigt' }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</li>
