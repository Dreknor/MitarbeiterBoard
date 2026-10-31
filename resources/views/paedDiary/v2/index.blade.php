{{--
    PaedDiary v2 – Hauptview (Blade + Alpine.js)
    Parallel zur bestehenden v1-View unter paedDiary.index
--}}
@extends('layouts.app')

@section('content')
<div class="container-fluid"
     x-data
     x-init="$store.diary.bootstrap({{ $klasse->id }}, {{ $selectedGroup?->id ?? 'null' }})">

    {{-- Hinweis bzw. Umschalter zur neuen Ansicht --}}
    @include('paedDiary.partials._ansicht-hinweis')

    {{-- Eingabedialog (ersetzt window.prompt, z. B. Grund der Tages-Pause) --}}
    <template x-if="$store.diary.textDialog">
        <div class="pd-text-dialog" role="dialog" aria-modal="true" aria-labelledby="pdTextDialogTitle"
             @keydown.escape.window="$store.diary.closeTextDialog(null)">
            <form class="pd-text-dialog__box"
                  @submit.prevent="$store.diary.closeTextDialog($refs.pdTextInput.value.trim())">
                <h5 id="pdTextDialogTitle" class="mb-2" x-text="$store.diary.textDialog.title"></h5>
                <label for="pdTextDialogInput" class="small text-muted mb-1" x-text="$store.diary.textDialog.label"></label>
                <input id="pdTextDialogInput" x-ref="pdTextInput" type="text" maxlength="100" class="form-control"
                       :value="$store.diary.textDialog.value" x-init="$nextTick(() => $el.select())">
                <div class="d-flex justify-content-end mt-3" style="gap:8px;">
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="$store.diary.closeTextDialog(null)">Abbrechen</button>
                    <button type="submit" class="btn btn-primary btn-sm">Übernehmen</button>
                </div>
            </form>
        </div>
    </template>

    {{-- Modals --}}
    @include('paedDiary.v2.modals._group-modal')
    @include('paedDiary.v2.modals._task-modal')
    @include('paedDiary.v2.modals._appointment-modal')

    <div class="row">
        {{-- Notiz-Editor (ausklappbar) --}}
        @include('paedDiary.v2.partials._note-editor')

        {{-- Spalten-Verwaltung (ausklappbar) --}}
        @include('paedDiary.v2.partials._columns-manager')

        {{-- Hauptbereich: Header + Tabelle --}}
        <div class="col-12">
            <div class="card mb-3">
                {{-- Header mit Navigation --}}
                @include('paedDiary.v2.partials._header')

                {{-- Tabellen-Body --}}
                <div class="card-body p-2">
                    {{-- Fehler und Hinweise (statt alert()) --}}
                    <div x-show="$store.diary.errorMessage" x-cloak role="alert"
                         :class="$store.diary.messageType === 'info' ? 'alert-info' : 'alert-danger'"
                         class="alert d-flex justify-content-between align-items-start py-2 mb-2">
                        <span x-text="$store.diary.errorMessage"></span>
                        <button type="button" class="close ml-2" aria-label="Meldung schließen"
                                @click="$store.diary.errorMessage = ''">&times;</button>
                    </div>
                    {{-- Loading --}}
                    <div x-show="$store.diary.loading" x-cloak class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <div class="text-muted small mt-2">Daten werden geladen...</div>
                    </div>

                    {{-- Tabelle --}}
                    @include('paedDiary.v2.partials._diary-table')
                </div>
            </div>
        </div>

        {{-- Aufgaben-Panel --}}
        @include('paedDiary.v2.partials._tasks-panel')
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/paedDiary.css?v=20261009') }}">
<link rel="stylesheet" href="{{ asset('css/pausedToggle.css?v=20251019') }}">
<link rel="stylesheet" href="{{ asset('css/tablet-scroll-optimization.css?v=20251110') }}">
@vite(['resources/css/paed-diary-v2.css'])
<style>
.class-divider-row td { background:#f1f3f5; font-weight:bold; font-size:.75rem; }
.group-disabled { opacity:.5; pointer-events:none; }
.pd-text-dialog { position:fixed; inset:0; z-index:1060; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:16px; }
.pd-text-dialog__box { background:#fff; border-radius:8px; padding:16px; width:min(420px, 100%); box-shadow:0 10px 30px rgba(0,0,0,.2); }
/* Task-Modal: form als Flex-Container, damit modal-dialog-scrollable korrekt funktioniert */
#taskModal .modal-content > form {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
    flex: 1 1 auto;
}
#taskStudents .form-check,
#taskModal .form-check {
    padding-left: 0 !important;
}
#taskStudents .form-check-input,
#taskModal .form-check-input {
    display: inline-block !important;
    width: 14px !important;
    height: 14px !important;
    margin-left: 0 !important;
    margin-right: 6px !important;
    margin-top: 0 !important;
    vertical-align: middle !important;
    -webkit-appearance: checkbox !important;
    appearance: checkbox !important;
    opacity: 1 !important;
    visibility: visible !important;
    position: static !important;
}
</style>
@endpush

@push('js')
<script src="{{ asset('/js/tablet-scroll-optimization.js?v=20261009')}}"></script>
@vite(['resources/js/paed-diary-v2.js'])
@endpush

