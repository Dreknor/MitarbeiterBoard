@extends('layouts.app')

@push('css')
    @vite('resources/css/procedure.css')
@endpush

@section('content')
<div class="procedure-wrapper">
    @if(session('Meldung'))
        <div class="alert-{{ session('type', 'info') }}">{{ session('Meldung') }}</div>
    @endif

    <script>window.location.replace('{{ url('procedure') }}#automation');</script>

    <div class="text-center py-16 text-gray-400">
        <p class="text-sm mb-3">Weiterleitung zur Positionsverwaltung…</p>
        <a href="{{ url('procedure') }}#automation" class="btn-procedure-primary text-sm inline-flex">Zur Automatisierung</a>
    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/procedure.js')
@endpush
