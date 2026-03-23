{{--
    PaedDiary v2 – Hauptview (Blade + Alpine.js)
    Parallel zur bestehenden v1-View unter paedDiary.index
--}}
@extends('layouts.app')

@section('content')
<div class="container-fluid"
     x-data
     x-init="$store.diary.bootstrap({{ $klasse->id }}, {{ $selectedGroup?->id ?? 'null' }})">

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
<link rel="stylesheet" href="{{ asset('css/paedDiary.css?v=20251019') }}">
<link rel="stylesheet" href="{{ asset('css/pausedToggle.css?v=20251019') }}">
<link rel="stylesheet" href="{{ asset('css/tablet-scroll-optimization.css?v=20251110') }}">
@vite(['resources/css/paed-diary-v2.css'])
<style>
.class-divider-row td { background:#f1f3f5; font-weight:bold; font-size:.75rem; }
.group-disabled { opacity:.5; pointer-events:none; }
#taskStudents .form-check-input,
#taskModal .form-check-input {
    display: inline-block !important;
    width: 14px !important;
    height: 14px !important;
    margin-right: 6px !important;
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
<script src="{{ asset('/js/tablet-scroll-optimization.js?v=20251110')}}"></script>
@vite(['resources/js/paed-diary-v2.js'])
@endpush

