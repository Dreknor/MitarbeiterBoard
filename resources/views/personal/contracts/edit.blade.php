@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    {{ $employe->vorname }} {{ $employe->familienname }} – Anstellung bearbeiten
@endsection

@section('title')
    Personalverwaltung
@endsection

@section('content')
<div class="personal-wrapper">
    @include('personal.partials._akte_header', ['active' => 'vertraege'])

    <div class="personal-card">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <h2 class="text-lg font-semibold text-gray-900">
                Anstellung bearbeiten
                <span class="text-sm font-normal text-gray-500">
                    · {{ $employment->department?->name ?? '—' }} seit {{ $employment->start?->format('d.m.Y') }}
                </span>
            </h2>
            <a href="{{ route('personal.contracts.index', $employe->id) }}" class="btn-personal-secondary text-sm">← Zurück</a>
        </div>
        @if($employment->status?->value === 'beendet')
            <div class="alert-warning text-sm">Diese Anstellung ist bereits beendet. Änderungen wirken rückwirkend und werden im Änderungsverlauf protokolliert.</div>
        @endif
        <form method="POST" action="{{ route('personal.contracts.update', $employment->id) }}"
              x-data="contractForm(@js($formConfig))">
            @csrf @method('PUT')
            @include('personal.contracts._form')
        </form>
    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush
