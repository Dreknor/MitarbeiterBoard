@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('content')
<div class="personal-wrapper"
     x-data="orgChart({{ json_encode($treeData) }})"
     x-init="init()"
     x-cloak>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Organigramm</h1>
        <div class="flex flex-wrap gap-2">
            <input x-model="search"
                   @input="expandToMatches(tree, search)"
                   type="search"
                   placeholder="Mitarbeiter suchen…"
                   class="input-personal w-56 text-sm">
            <button @click="expandAll(tree)" class="btn-personal-secondary text-xs">Alle aufklappen</button>
            <button @click="collapseAll(tree)" class="btn-personal-secondary text-xs">Alle zuklappen</button>
            @can('export orgchart')
            <a href="{{ route('personal.orgchart.export.pdf') }}"
               class="btn-personal-secondary text-sm">📄 PDF Export</a>
            @endcan
            @can('manage orgchart')
            <a href="{{ route('personal.orgchart.positions.index') }}"
               class="btn-personal-primary text-sm">⚙️ Stellen verwalten</a>
            @endcan
        </div>
    </div>

    {{-- Organigramm-Baum --}}
    <div class="overflow-auto pb-8">
        @if($treeData)
            <div class="flex justify-center">
                @include('personal.orgchart._node', ['position' => $treeData])
            </div>
        @else
            <div class="text-gray-400 text-center py-16">
                <p class="text-lg mb-2">Kein Organigramm vorhanden</p>
                @can('manage orgchart')
                <a href="{{ route('personal.orgchart.positions.create') }}" class="btn-personal-primary text-sm">
                    Erste Position anlegen
                </a>
                @endcan
            </div>
        @endif
    </div>

    {{-- Detail-Panel (Slide-in von rechts) --}}
    <div x-show="selectedPosition !== null"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-full opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-x-0 opacity-100"
         x-transition:leave-end="translate-x-full opacity-0"
         class="fixed right-0 top-0 h-full w-96 bg-white shadow-2xl border-l border-gray-200 z-50 overflow-y-auto"
         style="display: none;">
        @include('personal.orgchart._detail_panel')
    </div>

    {{-- Backdrop --}}
    <div x-show="selectedPosition !== null"
         @click="closePanel()"
         class="fixed inset-0 bg-black/20 z-40"
         style="display: none;"></div>

</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush

