{{--
    Aufgabenliste eines Themas (Gruppen-Themen und Meeting-Themen).
    Erwartet: $tasks (ThemeTaskService::tasksForTheme), $ui ('thm' | 'mtg')
--}}
@inject('taskService', 'App\Services\Tasks\ThemeTaskService')
@php
    $ui        = $ui ?? 'thm';
    $me        = auth()->user();
    $openTasks = $tasks->where('completed', false)->values();
    $doneTasks = $tasks->where('completed', true)->sortByDesc('completed_at')->values();
    $initials  = fn ($name) => \Illuminate\Support\Str::of($name)->explode(' ')->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->take(2)->implode('');
@endphp

@if($tasks->isEmpty())
    <p class="text-sm text-gray-400 italic">Keine Aufgaben</p>
@else
    <ul class="space-y-2.5">
        @foreach($openTasks as $task)
            @include('tasks.partials.theme_task_item')
        @endforeach
    </ul>
    @if($openTasks->isEmpty())
        <p class="text-sm text-gray-400 italic">Alle Aufgaben sind erledigt.</p>
    @endif

    @if($doneTasks->isNotEmpty())
        <details class="mt-3 group">
            <summary class="cursor-pointer select-none" style="list-style: none;">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-gray-700">
                    <i class="fas fa-chevron-right text-[10px] transition-transform group-open:rotate-90"></i>
                    Erledigte Aufgaben ({{ $doneTasks->count() }})
                </span>
            </summary>
            <ul class="space-y-2.5 mt-2.5">
                @foreach($doneTasks as $task)
                    @include('tasks.partials.theme_task_item')
                @endforeach
            </ul>
        </details>
    @endif
@endif
