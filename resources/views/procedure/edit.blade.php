@extends('layouts.app')

@push('css')
    @vite('resources/css/procedure.css')
@endpush

@section('content')
<div class="procedure-wrapper"
     x-data="procedureTree({
         canEdit: {{ ($canEdit ?? false) ? 'true' : 'false' }},
         csrfToken: '{{ csrf_token() }}'
     })"
     x-init="init()"
     x-cloak>

    {{-- ── Flash-Meldung ──────────────────────────────────── --}}
    @if(session('Meldung'))
    <div class="procedure-flash procedure-flash-{{ session('type', 'info') }}"
         x-data="{show:true}" x-show="show">
        <span>{{ session('Meldung') }}</span>
        <button @click="show=false" class="ml-auto text-current opacity-60 hover:opacity-100 text-lg leading-none">×</button>
    </div>
    @endif

    {{-- ── Kopfzeile ──────────────────────────────────────── --}}
    <div class="procedure-card mb-4">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-400 uppercase font-semibold tracking-wide mb-0.5">
                    {{ $procedure->category->name ?? 'Prozess' }} · Vorlage bearbeiten
                </p>
                <h1 class="text-xl font-bold text-gray-900 leading-tight mb-1">{{ $procedure->name }}</h1>
                @if($procedure->description)
                    <p class="text-sm text-gray-500">{!! $procedure->description !!}</p>
                @endif
            </div>
            <a href="{{ url('procedure') }}" class="btn-procedure-secondary text-xs whitespace-nowrap">← Zurück</a>
        </div>

        @if($canEdit ?? false)
        <div class="mt-4 border-t border-gray-100 pt-4" x-data="{ editOpen: false }">
            <button type="button" @click="editOpen = !editOpen" class="btn-procedure-secondary text-xs">
                <span x-text="editOpen ? '✕ Abbrechen' : '✏ Name / Beschreibung bearbeiten'"></span>
            </button>
            <template x-if="editOpen">
                <form action="{{ url('procedure/'.$procedure->id.'/update') }}" method="post" class="mt-3 space-y-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="procedure-label">Prozessname <span class="text-red-500">*</span></label>
                        <input type="text" name="name" class="input-procedure" value="{{ $procedure->name }}" required>
                    </div>
                    <div>
                        <label class="procedure-label">Beschreibung</label>
                        <textarea name="description" class="input-procedure" rows="2">{{ $procedure->description }}</textarea>
                    </div>
                    <button type="submit" class="btn-procedure-success text-sm">Speichern</button>
                </form>
            </template>
        </div>
        @endif
    </div>

    {{-- ── Schritt-Baum ────────────────────────────────────── --}}
    @php
        $rootSteps = $procedure->steps->where('parent', null)->sortBy(fn($s) => [$s->sort_order, $s->id]);
    @endphp

    <div class="procedure-card overflow-x-auto">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-800">Schritte</h2>
            @if($canEdit ?? false)
            <button type="button"
                    class="btn-procedure-primary text-xs"
                    @click="openAddStep(null)">
                + Schritt hinzufügen
            </button>
            @endif
        </div>

        @if($rootSteps->isNotEmpty())
        <div class="flex gap-6 overflow-x-auto pb-4"
             data-sortable-container
             data-procedure-id="{{ $procedure->id }}"
             data-parent-id="">
            @foreach($rootSteps as $step)
            <div class="flex flex-col items-center">
                @include('procedure._node', [
                    'step'        => $step,
                    'depth'       => 0,
                    'canEdit'     => $canEdit ?? false,
                    'users'       => $users ?? collect(),
                    'positions'   => $positions ?? collect(),
                    'procedure'   => $procedure,
                    'procedureId' => $procedure->id,
                ])
            </div>
            @endforeach
        </div>
        @elseif($procedure->steps->isNotEmpty())
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
            Es kann kein Start-Schritt gefunden werden.
            Startschritte dürfen keinen Vorgängerschritt haben.
        </div>
        @else
        <div class="text-center py-10 text-gray-400">
            <p class="text-sm mb-3">Noch keine Schritte vorhanden.</p>
            @if($canEdit ?? false)
            <button type="button" @click="openAddStep(null)" class="btn-procedure-primary text-sm">
                + Ersten Schritt erstellen
            </button>
            @endif
        </div>
        @endif
    </div>

    {{-- ── Vorlage starten ─────────────────────────────────── --}}
    @if($canEdit ?? false)
    <div class="procedure-card mt-4">
        <h2 class="font-semibold text-gray-800 mb-3">Prozess auf Basis dieser Vorlage starten</h2>
        <a href="{{ url('procedure/'.$procedure->id.'/start') }}" class="btn-procedure-success text-sm">
            ▶ Vorlage starten
        </a>
    </div>
    @endif

    {{-- ── Side-Panel ──────────────────────────────────────── --}}
    <div class="fixed inset-y-0 right-0 w-96 max-w-full bg-white shadow-2xl border-l border-gray-200 z-50
                transform transition-transform duration-300 overflow-y-auto"
         :class="selectedStep ? 'translate-x-0' : 'translate-x-full'"
         x-show="selectedStep">
        @include('procedure._detail_panel', [
            'procedure' => $procedure,
            'users'     => $users ?? collect(),
            'positions' => $positions ?? collect(),
        ])
    </div>
    <div x-show="selectedStep" @click="closePanel()"
         class="fixed inset-0 bg-black/20 z-40"></div>

    {{-- ── Modals ──────────────────────────────────────────── --}}
    {{-- Add-Step Drawer --}}
    <div x-show="addingStep"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/30 z-50 flex items-center justify-center p-4"
         style="display:none"
         @keydown.escape.window="addingStep = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop>
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-900">Neuer Schritt</h3>
                <button @click="addingStep = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
            </div>
            <form action="{{ url('procedure/'.$procedure->id.'/step') }}" method="post" class="space-y-4">
                @csrf
                <input type="hidden" name="parent" x-bind:value="addingStepParent ?? ''">
                <div>
                    <label class="procedure-label">Bezeichnung <span class="text-red-500">*</span></label>
                    <input name="name" type="text" class="input-procedure" required autofocus>
                </div>
                <div>
                    <label class="procedure-label">Beschreibung</label>
                    <textarea name="description" rows="3" class="input-procedure"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="procedure-label">Verantwortliche Position <span class="text-red-500">*</span></label>
                        <select name="position_id" class="input-procedure" required>
                            <option value="" disabled selected></option>
                            @foreach($positions ?? [] as $pos)
                            <option value="{{ $pos->id }}">{{ $pos->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="procedure-label">Dauer (Tage) <span class="text-red-500">*</span></label>
                        <input type="number" name="durationDays" min="1" step="1" class="input-procedure" required>
                    </div>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-procedure-primary flex-1">Speichern</button>
                    <button type="button" @click="addingStep = false" class="btn-procedure-secondary">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>

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
