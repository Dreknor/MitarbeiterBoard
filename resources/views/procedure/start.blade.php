@extends('layouts.app')

@push('css')
    @vite('resources/css/procedure.css')
@endpush

@section('content')
    <div class="container-fluid procedure-wrapper"
         x-data="procedureTree({ canEdit: {{ $canEdit ? 'true' : 'false' }}, csrfToken: '{{ csrf_token() }}' })">

        {{-- Zurück-Link --}}
        <a href="{{ url('procedure') }}" class="btn-procedure-secondary inline-flex items-center gap-2 mb-4 text-sm py-1.5 px-3">
            <i class="fas fa-arrow-left text-xs"></i> Zurück
        </a>

        {{-- Header-Karte --}}
        <div class="procedure-card mb-4">
            @if($procedure->started_at != null && ($canEdit ?? false))
                {{-- Bearbeitbarer Titel für gestartete Prozesse --}}
                <div x-show="!editingHeader">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">
                                <span class="text-gray-400 font-normal text-base">{{ $procedure->category->name }}:</span>
                                {{ $procedure->name }}
                            </h2>
                            @if($procedure->description)
                            <p class="text-sm text-gray-500 mt-1">{!! $procedure->description !!}</p>
                            @endif
                        </div>
                        <button @click="editingHeader = true" type="button"
                                class="btn-procedure-secondary text-xs py-1 px-2 shrink-0">
                            <i class="fas fa-edit"></i> Bearbeiten
                        </button>
                    </div>
                </div>

                {{-- Bearbeitungsformular (via Alpine gesteuert) --}}
                <div x-show="editingHeader" x-cloak class="mt-1">
                    <form action="{{ url('procedure/'.$procedure->id.'/update') }}" method="post" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="procedure-label">Prozessname <span class="text-red-500">*</span></label>
                            <input type="text" class="input-procedure" name="name"
                                   value="{{ $procedure->name }}" required>
                        </div>
                        <div>
                            <label class="procedure-label">Beschreibung</label>
                            <textarea class="input-procedure" name="description"
                                      rows="3">{{ $procedure->description }}</textarea>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-procedure-success text-sm py-1.5 px-3">
                                <i class="fas fa-save"></i> Speichern
                            </button>
                            <button type="button" @click="editingHeader = false"
                                    class="btn-procedure-secondary text-sm py-1.5 px-3">
                                <i class="fas fa-times"></i> Abbrechen
                            </button>
                        </div>
                    </form>
                </div>

            @else
                {{-- Normaler Titel (Template oder kein Bearbeitungsrecht) --}}
                <h2 class="text-lg font-bold text-gray-900">
                    <span class="text-gray-400 font-normal text-base">{{ $procedure->category->name }}:</span>
                    {{ $procedure->name }}
                </h2>
                @if($procedure->description)
                <p class="text-sm text-gray-500 mt-1">{{ $procedure->description }}</p>
                @endif
            @endif
        </div>

        {{-- ── MODUS A: Vorlage noch nicht gestartet ── --}}
        @if($procedure->started_at == null)
            @if($canEdit ?? false)
            <div class="procedure-card">
                <h3 class="font-semibold text-gray-800 mb-4">Prozess starten</h3>
                <form action="{{ url('procedure/'.$procedure->id.'/start') }}" method="post" class="space-y-4">
                    @csrf
                    <div>
                        <label class="procedure-label" for="name">
                            Bezeichnung des Prozesses <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="name"
                               placeholder="Bezeichnung für Prozess eingeben"
                               class="input-procedure" required>
                    </div>
                    <div>
                        <label class="procedure-label" for="started_at">
                            Prozess startet am <span class="text-red-500">*</span>
                        </label>
                        <input type="date" required name="started_at" id="started_at"
                               value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}"
                               class="input-procedure max-w-xs">
                    </div>
                    <button type="submit" class="btn-procedure-success">
                        <i class="fas fa-play"></i> Starten
                    </button>
                </form>
            </div>
            @else
            <div class="procedure-card">
                <p class="text-gray-500 text-sm">Dieser Prozess wurde noch nicht gestartet.</p>
            </div>
            @endif

        {{-- ── MODUS B: Laufender Prozess – Baum-Ansicht ── --}}
        @else
            {{-- Toolbar --}}
            @if($canEdit ?? false)
            <div class="flex gap-2 mb-4 flex-wrap">
                <button @click="expandAll()" class="btn-procedure-secondary text-xs py-1.5 px-3">
                    <i class="fas fa-expand-alt"></i> Alle aufklappen
                </button>
                <button @click="collapseAll()" class="btn-procedure-secondary text-xs py-1.5 px-3">
                    <i class="fas fa-compress-alt"></i> Alle zuklappen
                </button>
                <button @click="openAddStep(null)" class="btn-procedure-success text-xs py-1.5 px-3">
                    <i class="fas fa-plus"></i> Schritt hinzufügen
                </button>
            </div>
            @endif

            {{-- Schritt-Baum --}}
            @php $rootSteps = $procedure->steps->where('parent', null)->sortBy(fn($s) => [$s->sort_order, $s->id]); @endphp
            @if($rootSteps->isNotEmpty())
                <div class="flex gap-6 overflow-x-auto pb-4"
                     data-sortable-container
                     data-procedure-id="{{ $procedure->id }}"
                     data-parent-id="">
                    @foreach($rootSteps as $step)
                        @include('procedure._node', [
                            'step'        => $step,
                            'depth'       => 0,
                            'canEdit'     => $canEdit ?? false,
                            'users'       => $users,
                            'positions'   => $positions,
                            'procedure'   => $procedure,
                            'procedureId' => $procedure->id,
                        ])
                    @endforeach
                </div>
            @else
                <p class="text-gray-400 text-sm italic">Dieser Prozess hat noch keine Schritte.</p>
            @endif

            {{-- Detail-Panel --}}
            <div class="fixed top-0 right-0 h-full w-80 max-w-full bg-white shadow-2xl z-50 overflow-y-auto border-l border-gray-200 transition-transform"
                 :class="selectedStep ? 'translate-x-0' : 'translate-x-full'"
                 style="transition: transform 0.25s ease;">
                @include('procedure._detail_panel')
            </div>

            {{-- Schritt hinzufügen – Inline-Formular --}}
            <div x-show="addingStep" x-cloak class="procedure-card mt-4 border-dashed border-2 border-gray-200">
                <h4 class="font-semibold text-sm text-gray-800 mb-3">Neuen Schritt anlegen</h4>
                <form action="{{ url('procedure/'.$procedure->id.'/step') }}" method="post" class="space-y-3">
                    @csrf
                    <input type="hidden" name="parent" :value="addingStepParent ?? ''">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="procedure-label">Bezeichnung <span class="text-red-500">*</span></label>
                            <input type="text" name="name" class="input-procedure" required>
                        </div>
                        <div>
                            <label class="procedure-label">Verantwortliche Position <span class="text-red-500">*</span></label>
                            <select name="position_id" class="input-procedure" required>
                                <option disabled selected value=""></option>
                                @foreach($positions as $position)
                                    <option value="{{ $position->id }}">{{ $position->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="procedure-label">Dauer (Tage) <span class="text-red-500">*</span></label>
                            <input type="number" class="input-procedure" name="durationDays" min="1" step="1" required>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-procedure-success text-sm py-1.5 px-3">
                            <i class="fas fa-save"></i> Speichern
                        </button>
                        <button type="button" @click="addingStep = false"
                                class="btn-procedure-secondary text-sm py-1.5 px-3">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        @endif


        {{-- Backdrop für Detail-Panel --}}
        <div x-show="selectedStep !== null"
             @click="closePanel()"
             class="fixed inset-0 bg-black/20 z-40"
             style="display:none"></div>

        {{-- Toast-Container --}}
        <div class="fixed bottom-4 right-4 z-[60] space-y-2 pointer-events-none">
            <template x-for="toast in toasts" :key="toast.id">
                <div class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-white text-sm"
                     :class="{'bg-green-600':toast.type==='success','bg-red-600':toast.type==='error','bg-amber-500':toast.type==='warning','bg-blue-600':toast.type==='info'}"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0 translate-y-2">
                    <span x-text="toast.message" class="flex-1"></span>
                    <button @click="removeToast(toast.id)" class="opacity-70 hover:opacity-100 text-lg font-bold leading-none">×</button>
                </div>
            </template>
        </div>
    </div>
@endsection

@push('js')
    @vite('resources/js/procedure.js')
@endpush
