@if($diaryTasks->isNotEmpty())
<div class="wp-section mt-6" x-data="diaryTasksPanel()">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <span class="text-amber-500">📋</span>
            Offene Aufgaben aus dem Tagebuch
            <span class="bg-amber-100 text-amber-800 text-xs font-medium px-2 py-0.5 rounded-full">
                {{ $diaryTasks->count() }}
            </span>
        </h3>
        @if($plan->schueler_id)
        <a href="{{ route('paedDiary.schueler.view', $plan->schueler_id) }}"
           target="_blank"
           class="text-xs text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1">
            → Alle im Tagebuch ansehen
        </a>
        @endif
    </div>

    {{-- Aufgabenliste --}}
    <div class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
        @foreach($diaryTasks as $task)
        <div class="flex items-start gap-3 px-4 py-3 bg-white hover:bg-gray-50 transition-colors
                    {{ $task->highlighted ? 'border-l-4 border-amber-400' : '' }}">

            @if($task->highlighted)
                <span class="mt-0.5 text-amber-500 flex-shrink-0 text-base" title="Priorisiert">⚠</span>
            @else
                <span class="mt-0.5 text-gray-300 flex-shrink-0 text-base">○</span>
            @endif

            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800">{{ $task->title }}</p>
                @if($task->description)
                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $task->description }}</p>
                @endif
                @if($task->due_date)
                    <p class="text-xs mt-1 @if($task->due_date->isPast()) text-red-600 font-semibold @else text-gray-400 @endif">
                        Fällig: {{ $task->due_date->format('d.m.Y') }}
                        @if($task->due_date->isPast()) (überfällig) @endif
                    </p>
                @endif
            </div>

            <button type="button"
                    @click="openModal({{ $task->id }}, @js($task->title), @js($task->description ?? ''))"
                    class="flex-shrink-0 text-xs px-3 py-1.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors whitespace-nowrap">
                + In WP
            </button>
        </div>
        @endforeach
    </div>

    {{-- Modal --}}
    <div x-show="modal.open"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         style="display: none;"
         @click.self="modal.open = false">

        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6" @click.stop>
            <h4 class="text-base font-semibold text-gray-800 mb-4">Aufgabe in Wochenplan übernehmen</h4>

            <form x-ref="modalForm" method="POST" @submit.prevent="submitForm()">
                @csrf
                <input type="hidden" name="diary_task_id" :value="modal.taskId">

                {{-- Fach auswählen --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fach</label>
                    <select x-model="selectedFachId" required
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Fach wählen …</option>
                        @foreach($planFaecher as $pf)
                            <option value="{{ $pf->id }}">{{ $pf->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Aufgabentext --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Aufgabentext</label>
                    <input type="text" name="aufgabe" x-model="modal.aufgabe"
                           required maxlength="1000"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p x-show="modal.description" class="text-xs text-gray-400 mt-1">
                        Tagebuch-Beschreibung: <span x-text="modal.description"></span>
                    </p>
                </div>

                {{-- Dauer (optional) --}}
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Dauer <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <input type="text" name="dauer" placeholder="z.B. 20 min" maxlength="50"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                {{-- Aktionen --}}
                <div class="flex justify-end gap-3">
                    <button type="button" @click="modal.open = false"
                            class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Abbrechen
                    </button>
                    <button type="submit"
                            :disabled="!selectedFachId"
                            class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        ✓ Übernehmen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

