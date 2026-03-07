@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-container p-4 max-w-2xl mx-auto">

    {{-- Zurück-Link --}}
    <a href="{{ route('wp.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zur Übersicht
    </a>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Neuer Wochenplan</h1>

    {{-- Aus Vorlage erstellen --}}
    @if($vorlagen->count() > 0)
        @php $preselectedVorlageId = request('vorlage_id'); @endphp
        <div x-data="{ vorlageId: '{{ old('vorlage_id', $preselectedVorlageId) }}' }" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <label class="block text-sm font-medium text-blue-800 mb-2">Aus Vorlage erstellen (optional)</label>
            <select x-model="vorlageId" @change="document.getElementById('vorlage_id').value = vorlageId"
                    class="w-full px-3 py-2 border border-blue-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">– Keine Vorlage –</option>
                @foreach($vorlagen as $vorlage)
                    <option value="{{ $vorlage->id }}"
                        {{ (old('vorlage_id', $preselectedVorlageId) == $vorlage->id) ? 'selected' : '' }}>
                        {{ $vorlage->vorlage_name ?? $vorlage->name }}
                        ({{ $vorlage->planFaecher->count() }} Fächer)
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-blue-600 mt-1">Wird eine Vorlage gewählt, werden die Fächer & Aufgaben übernommen.</p>
        </div>
    @endif

    {{-- Formular --}}
    <form method="POST" action="{{ route('wp.store') }}" x-data="{ vorlageSelected: false }">
        @csrf
        <input type="hidden" name="vorlage_id" id="vorlage_id" value="{{ old('vorlage_id') }}">

        @if($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-lg border border-gray-200 p-5 space-y-4">

            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bezeichnung <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                       placeholder="z.B. 11. Wochenplan SJ 2025/2026">
            </div>

            {{-- Klasse --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Klasse</label>
                <select name="klasse_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                    <option value="">– Keine Klasse (Vorlage/Individuell) –</option>
                    @foreach($klassen as $klasse)
                        <option value="{{ $klasse->id }}" {{ old('klasse_id') == $klasse->id ? 'selected' : '' }}>
                            {{ $klasse->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Zeitraum --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gültig von <span class="text-red-500">*</span></label>
                    <input type="date" name="gueltig_von" value="{{ old('gueltig_von') }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gültig bis <span class="text-red-500">*</span></label>
                    <input type="date" name="gueltig_bis" value="{{ old('gueltig_bis') }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>

            {{-- Selbsteinschätzung --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Selbsteinschätzung</label>
                <select name="selbsteinschaetzung"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                    <option value="0" {{ old('selbsteinschaetzung', 0) == 0 ? 'selected' : '' }}>Keine</option>
                    <option value="1" {{ old('selbsteinschaetzung') == 1 ? 'selected' : '' }}>😊 Smiley</option>
                    <option value="2" {{ old('selbsteinschaetzung') == 2 ? 'selected' : '' }}>📊 Skala 1–10</option>
                </select>
            </div>

            {{-- Formatvorlage --}}
            @if($formatvorlagen->count() > 0)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Formatvorlage</label>
                    <select name="formatvorlage_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white">
                        <option value="">Standard</option>
                        @foreach($formatvorlagen as $fv)
                            <option value="{{ $fv->id }}" {{ old('formatvorlage_id') == $fv->id ? 'selected' : '' }}>
                                {{ $fv->name }}{{ $fv->is_default ? ' (Standard)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

        </div>

        <div class="mt-4 flex gap-3">
            <button type="submit"
                    class="flex-1 py-2.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                Plan erstellen
            </button>
            <a href="{{ route('wp.index') }}"
               class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
                Abbrechen
            </a>
        </div>

    </form>
</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush

