@if($diaryTasks->isNotEmpty())
<div class="wp-section mt-6">
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
    <div class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden bg-white">
        @foreach($diaryTasks as $task)
        @php $formRef = 'wpForm' . $task->id; $baseUrl = url('wp/aufgabe/aus-tagebuch'); @endphp
        <div x-data="{ open: false, fachId: '' }"
             class="px-4 py-3 transition-colors {{ $task->highlighted ? 'border-l-4 border-amber-400 bg-amber-50/40' : 'hover:bg-gray-50' }}">

            {{-- Aufgaben-Zeile --}}
            <div class="flex items-start gap-3">
                @if($task->highlighted)
                    <span class="mt-0.5 text-amber-500 shrink-0 text-base" title="Priorisiert">⚠</span>
                @else
                    <span class="mt-0.5 text-gray-300 shrink-0 text-base">○</span>
                @endif

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800">{{ $task->title }}</p>
                    @if($task->description)
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $task->description }}</p>
                    @endif
                    @if($task->due_date)
                        @php $isOverdue = $task->due_date->isPast(); @endphp
                        <p class="text-xs mt-1 {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                            Fällig: {{ $task->due_date->format('d.m.Y') }}
                            @if($isOverdue) (überfällig) @endif
                        </p>
                    @endif
                </div>

                <button type="button"
                        x-on:click="open = !open"
                        :class="open ? 'bg-blue-700 ring-2 ring-blue-300' : 'bg-blue-600 hover:bg-blue-700'"
                        class="shrink-0 text-xs px-3 py-1.5 text-white rounded-md transition-all whitespace-nowrap">
                    <span x-show="!open">+ In WP übernehmen</span>
                    <span x-show="open" x-cloak>✕ Abbrechen</span>
                </button>
            </div>

            {{-- Inline-Formular (aufklappbar) --}}
            <div x-show="open"
                 x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-3 pt-3 border-t border-blue-100">

                {{-- Die form-action wird per JS-Click auf den Button gesetzt --}}
                <form id="{{ $formRef }}"
                      method="POST"
                      action="">
                    @csrf
                    <input type="hidden" name="diary_task_id" value="{{ $task->id }}">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                        {{-- Fach --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Fach</label>
                            <select x-model="fachId" required
                                    class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                                <option value="">Fach wählen …</option>
                                @foreach($planFaecher as $pf)
                                    <option value="{{ $pf->id }}">{{ $pf->display_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Aufgabentext --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Aufgabentext</label>
                            <input type="text" name="aufgabe"
                                   value="{{ $task->title }}"
                                   required maxlength="1000"
                                   class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        {{-- Dauer + Speichern-Button --}}
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Dauer <span class="text-gray-400 font-normal">(opt.)</span></label>
                                <input type="text" name="dauer" placeholder="z.B. 20 min" maxlength="50"
                                       class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex items-end">
                                <button type="button"
                                        :disabled="!fachId"
                                        x-on:click="if (fachId) { let f = document.getElementById('{{ $formRef }}'); f.action = '{{ $baseUrl }}/' + fachId; f.submit(); }"
                                        class="px-3 py-1.5 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors whitespace-nowrap">
                                    ✓ Übernehmen
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>
        @endforeach
    </div>
</div>
@endif

