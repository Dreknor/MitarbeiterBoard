@extends('layouts.app')
@push('css') @vite('resources/css/personal.css') @endpush

@section('content')
<div class="personal-wrapper" x-data="personalTabs('uebersicht')" x-init="init()" x-cloak>

    <div class="flex items-center gap-4 mb-6">
        <div class="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xl">
            {{ substr(auth()->user()->name, 0, 1) }}
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mein Profil – Verträge</h1>
        </div>
        <a href="{{ route('self-service.index') }}" class="btn-personal-secondary text-sm ml-auto">← Zurück</a>
    </div>

    @include('personal.self-service._tab_vertraege')

</div>
@endsection
@push('js') @vite('resources/js/personal.js') @endpush

