@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    {{ $employe->vorname }} {{ $employe->familienname }} – Neue Anstellung
@endsection

@section('title')
    Personalverwaltung
@endsection

@section('content')
<div class="personal-wrapper">
    @include('personal.partials._akte_header', ['active' => 'vertraege'])

    <div class="personal-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Neue Anstellung</h2>
            <a href="{{ route('personal.contracts.index', $employe->id) }}" class="btn-personal-secondary text-sm">← Zurück</a>
        </div>
        <form method="POST" action="{{ route('personal.contracts.store', $employe->id) }}"
              x-data="contractForm(@js($formConfig))">
            @csrf
            @include('personal.contracts._form')
        </form>
    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush
