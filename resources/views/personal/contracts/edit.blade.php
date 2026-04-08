@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('content')
<div class="personal-wrapper">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Anstellung bearbeiten</h1>
        <a href="{{ route('personal.contracts.index', $employe->id) }}"
           class="btn-personal-secondary text-sm">← Zurück</a>
    </div>

    <div class="personal-card">
        <form method="POST" action="{{ route('personal.contracts.update', $employment->id) }}"
              x-data="{ type: '{{ $employment->employment_type?->value ?? 'regulaer' }}', contractType: '{{ $employment->contract_type?->value ?? 'unbefristet' }}' }">
            @csrf @method('PUT')
            @include('personal.contracts._form')
        </form>
    </div>
</div>
@endsection
@push('js')
    @vite('resources/js/personal.js')
@endpush

