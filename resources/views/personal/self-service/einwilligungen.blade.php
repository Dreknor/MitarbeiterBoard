@extends('layouts.app')
@push('css') @vite('resources/css/personal.css') @endpush

@section('site-title')
    Mein Profil – Einwilligungen
@endsection

@section('content')
<div class="personal-wrapper">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Mein Profil – Einwilligungen</h1>
        <a href="{{ route('self-service.index') }}" class="btn-personal-secondary text-sm">← Zurück</a>
    </div>

    @include('personal.self-service._tab_einwilligungen')
</div>
@endsection
@push('js') @vite('resources/js/personal.js') @endpush
