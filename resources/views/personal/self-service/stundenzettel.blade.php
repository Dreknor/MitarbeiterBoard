@extends('layouts.app')
@push('css') @vite('resources/css/personal.css') @endpush
@section('content')
<div class="personal-wrapper">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Mein Profil</h1>
        <a href="{{ route('self-service.index') }}" class="btn-personal-secondary text-sm">← Zurück</a>
    </div>
    <div class="personal-card text-center text-gray-400 py-12">
        Dieser Bereich wird in einer zukünftigen Phase verfügbar.
    </div>
</div>
@endsection
@push('js') @vite('resources/js/personal.js') @endpush
