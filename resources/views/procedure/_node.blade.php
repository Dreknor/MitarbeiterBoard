@php
    $stepId   = $step->id;
    $hasKids  = $step->childs->isNotEmpty();
    $isDone   = $step->done == 1;
    $depth    = $depth ?? 0;
    $canEdit  = $canEdit ?? false;
    $procedureId = $procedure->id ?? ($step->procedure_id ?? 0);

    $isOverdue = !$isDone && $step->endDate && $step->endDate->isPast();
    $isDueSoon = !$isDone && !$isOverdue && $step->endDate && $step->endDate->diffInDays(now()) <= 3;
    $hasDate   = $step->endDate !== null;

    // Schritt-Daten als JSON für Alpine-Panel (sicher kodiert)
    $stepData = json_encode([
        'id'                => $stepId,
        'name'              => $step->name,
        'description'       => $step->description,
        'done'              => $isDone,
        'endDate'           => $step->endDate?->format('Y-m-d'),
        'endDateFormatted'  => $step->endDate?->format('d.m.Y'),
        'completedAt'       => $step->completed_at?->format('d.m.Y H:i'),
        'sort_order'        => $step->sort_order ?? 0,
        'position'          => $step->position ? ['id' => $step->position->id, 'name' => $step->position->name] : null,
        'users'             => $step->users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'initial' => mb_substr($u->name, 0, 1)])->values()->all(),
        'canEdit'           => $canEdit,
        'doneUrl'           => url('procedure/step/'.$stepId.'/done'),
        'completeUrl'       => url('procedure/steps/'.$stepId.'/complete'),
        'reopenUrl'         => url('procedure/steps/'.$stepId.'/reopen'),
        'removeUserBase'    => url('procedure/step/'.$stepId.'/users'),
        'addUserUrl'        => url('procedure/step/addUser'),
        'deleteUrl'         => url('procedure/step/'.$stepId.'/delete'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

<div class="flex flex-col items-center"
     data-step-id="{{ $stepId }}"
     data-sort-order="{{ $step->sort_order ?? 0 }}"
     @if($depth === 0) data-step-root="1" @endif>

    {{-- ── Schritt-Karte ──────────────────────────────── --}}
    <div class="relative cursor-pointer group" @click="selectStep({{ $stepData }})">

        {{-- Drag-Handle (nur im Bearbeitungsmodus sichtbar) --}}
        @if($canEdit)
        <div data-drag-handle
             class="absolute -left-5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 cursor-grab active:cursor-grabbing opacity-0 group-hover:opacity-100 transition-opacity text-lg select-none"
             title="Ziehen zum Verschieben"
             @click.stop>⠿</div>
        @endif

        <div class="rounded-xl shadow-md px-4 py-3 min-w-[160px] max-w-[210px] text-center transition-all border-2 select-none"
             :class="selectedStep && selectedStep.id === {{ $stepId }}
                 ? 'border-blue-500 bg-blue-50 shadow-lg scale-105'
                 : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-md'"
        >
            {{-- Status-Badge --}}
            <div class="mb-1 flex justify-center">
                @if($isDone)
                    <span class="badge-step-done">✓ Erledigt</span>
                @elseif($isOverdue)
                    <span class="badge-step-overdue">Überfällig</span>
                @elseif($isDueSoon)
                    <span class="badge-step-due">{{ $step->endDate->format('d.m.') }}</span>
                @elseif($hasDate)
                    <span class="badge-step-active">{{ $step->endDate->format('d.m.') }}</span>
                @else
                    <span class="badge-step-open">offen</span>
                @endif
            </div>

            {{-- Name --}}
            <p class="font-semibold text-sm text-gray-900 leading-tight break-words">{{ $step->name }}</p>

            {{-- Position --}}
            @if($step->position)
            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $step->position->name }}</p>
            @endif

            {{-- User-Avatare --}}
            @if($step->users->isNotEmpty())
            <div class="flex justify-center flex-wrap gap-0.5 mt-2">
                @foreach($step->users->take(3) as $user)
                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 font-semibold text-xs flex items-center justify-center shrink-0"
                     title="{{ $user->name }}">
                    {{ mb_substr($user->name, 0, 1) }}
                </div>
                @endforeach
                @if($step->users->count() > 3)
                <div class="w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs flex items-center justify-center">
                    +{{ $step->users->count() - 3 }}
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Expand/Collapse Button (wenn Kinder vorhanden) --}}
        @if($hasKids)
        <button @click.stop="toggle({{ $stepId }})"
                class="absolute -bottom-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-white border border-gray-300 rounded-full text-xs flex items-center justify-center hover:bg-gray-100 shadow z-10 shrink-0">
            <span x-text="isExpanded({{ $stepId }}) ? '−' : '+'">+</span>
        </button>
        @endif
    </div>

    {{-- "+ Unterschritt" Button – direkt unter der Karte, VOR den Kindern --}}
    {{-- So bleibt er immer nah an seiner Karte, unabhängig von der Subtree-Tiefe --}}
    @if($canEdit)
    <div class="mt-2 mb-1"
         @if($hasKids) x-show="isExpanded({{ $stepId }})" @endif>
        <button @click.stop="openAddStep({{ $stepId }})"
                class="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-blue-600 border border-dashed border-gray-300 rounded-lg px-2 py-1 hover:border-blue-400 transition-colors whitespace-nowrap">
            + Unterschritt
        </button>
    </div>
    @endif

    {{-- Verbindungslinie nach unten --}}
    @if($hasKids)
    <div class="w-px h-6 bg-gray-300 mt-3" x-show="isExpanded({{ $stepId }})"></div>
    @endif

    {{-- Kinder-Ebene --}}
    @if($hasKids)
    <div x-show="isExpanded({{ $stepId }})"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="flex gap-6 relative"
         data-sortable-container
         data-procedure-id="{{ $procedureId }}"
         data-parent-id="{{ $stepId }}">

        {{-- Horizontale Verbindungslinie über Kinder (wenn > 1) --}}
        @if($step->childs->count() > 1)
        <div class="absolute top-0 left-8 right-8 h-px bg-gray-300"></div>
        @endif

        @foreach($step->childs->sortBy(fn($s) => [$s->sort_order, $s->id]) as $child)
        <div class="flex flex-col items-center">
            <div class="w-px h-6 bg-gray-300"></div>
            @include('procedure._node', [
                'step'        => $child,
                'depth'       => $depth + 1,
                'canEdit'     => $canEdit,
                'users'       => $users ?? collect(),
                'positions'   => $positions ?? collect(),
                'procedure'   => $procedure ?? null,
                'procedureId' => $procedureId,
            ])
        </div>
        @endforeach
    </div>
    @endif
</div>

