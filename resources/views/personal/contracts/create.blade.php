@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('content')
<div class="personal-wrapper">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Neue Anstellung</h1>
        <a href="{{ route('personal.contracts.index', $employe->id) }}"
           class="btn-personal-secondary text-sm">← Zurück</a>
    </div>

    <div class="personal-card">
        <form method="POST" action="{{ route('personal.contracts.store', $employe->id) }}"
              x-data="{ type: 'regulaer', contractType: 'unbefristet' }">
            @csrf
            @include('personal.contracts._form')
        </form>
    </div>
</div>
@endsection
@push('js')
    @vite('resources/js/personal.js')
@endpush

