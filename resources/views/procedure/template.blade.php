@extends('layouts.app')

@push('css')
    @vite('resources/css/procedure.css')
@endpush

@section('content')
<div class="procedure-wrapper">
    @if(session('Meldung'))
        <div class="alert-{{ session('type', 'info') }}">{{ session('Meldung') }}</div>
    @endif

    {{-- Weiterleitung zur neuen Single-Page --}}
    <script>window.location.replace('{{ url('procedure') }}#templates');</script>

    <div class="text-center py-16 text-gray-400">
        <p class="text-sm mb-3">Weiterleitung zur Vorlagenverwaltung…</p>
        <a href="{{ url('procedure') }}#templates" class="btn-procedure-primary text-sm inline-flex">Zur Vorlagenverwaltung</a>
    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/procedure.js')
@endpush
