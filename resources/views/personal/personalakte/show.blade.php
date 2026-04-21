@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('site-title')
    {{ $employe->vorname ?? $employe->name }} {{ $employe->familienname ?? '' }} – Personalakte
@endsection

@section('title')
    Personalverwaltung
@endsection

@section('content')
<div class="personal-wrapper">

    {{-- Seitenkopf (Titel steht bereits in der Topbar → hier nur Avatar + Meta + Actions) --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-4">
            <div class="personal-avatar">
                {{ strtoupper(substr($employe->name, 0, 1)) }}
            </div>
            <div>
                <p class="text-gray-700 font-semibold">{{ $employe->vorname ?? $employe->name }} {{ $employe->familienname ?? '' }}</p>
                <p class="text-gray-500 text-sm">{{ $employe->email }}</p>
                @if($employe->employments->isNotEmpty())
                    <p class="text-gray-400 text-xs mt-0.5">
                        {{ $employe->employments->map(fn($e) => $e->department->name ?? '–')->implode(', ') }}
                    </p>
                @endif
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('employes.index') }}" class="btn-personal-secondary text-sm">← Alle Mitarbeiter</a>
            @can('edit employe')
                <a href="{{ route('employes.show', $employe->id) }}" class="btn-personal-secondary text-sm">
                    ✏️ Stammdaten bearbeiten
                </a>
            @endcan
        </div>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
        {{ session('Meldung') }}
    </div>
    @endif

    {{-- Stammdaten-Übersicht --}}
    <div class="personal-card mb-6">
        <h2 class="text-base font-semibold text-gray-700 mb-3">Stammdaten</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="block text-gray-400 text-xs">Geburtsdatum</span>
                <span class="font-medium text-gray-800">
                    {{ optional($employe->geburtstag)->format('d.m.Y') ?? '–' }}
                </span>
            </div>
            <div>
                <span class="block text-gray-400 text-xs">Geschlecht</span>
                <span class="font-medium text-gray-800">
                    {{ $employe->employe_data?->geschlecht ?? '–' }}
                </span>
            </div>
            <div>
                <span class="block text-gray-400 text-xs">SV-Nummer</span>
                <span class="font-medium text-gray-800">
                    {{ $employe->employe_data?->sozialversicherungsnummer ?? '–' }}
                </span>
            </div>
            <div>
                <span class="block text-gray-400 text-xs">Staatsangehörigkeit</span>
                <span class="font-medium text-gray-800">
                    {{ $employe->employe_data?->staatsangehoerigkeit ?? '–' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Modul-Karten --}}
    <h2 class="text-base font-semibold text-gray-700 mb-3">Module</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">

        {{-- Verträge --}}
        @can('view contracts')
        <a href="{{ route('personal.contracts.index', $employe->id) }}"
           class="personal-card hover:shadow-md transition-shadow flex items-start gap-4 no-underline group">
            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 text-xl shrink-0">
                📋
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 group-hover:text-blue-700 transition-colors">Verträge & Anstellungen</h3>
                <p class="text-gray-500 text-sm mt-0.5">Aktive und vergangene Arbeitsverhältnisse</p>
            </div>
        </a>
        @endcan

        {{-- Dokumente --}}
        @can('view personal_documents')
        <a href="{{ route('personal.documents.index', $employe->id) }}"
           class="personal-card hover:shadow-md transition-shadow flex items-start gap-4 no-underline group">
            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 text-xl shrink-0">
                📁
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 group-hover:text-amber-700 transition-colors">Dokumente</h3>
                <p class="text-gray-500 text-sm mt-0.5">Arbeitsverträge, Zeugnisse, Bescheinigungen</p>
            </div>
        </a>
        @endcan

        {{-- Qualifikationen --}}
        @can('view qualifications')
        <a href="{{ route('personal.qualifications.index', $employe->id) }}"
           class="personal-card hover:shadow-md transition-shadow flex items-start gap-4 no-underline group">
            <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center text-green-600 text-xl shrink-0">
                🎓
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 group-hover:text-green-700 transition-colors">Qualifikationen</h3>
                <p class="text-gray-500 text-sm mt-0.5">Abschlüsse, Zertifikate, Lizenzen</p>
            </div>
        </a>
        @endcan

        {{-- Fortbildungen --}}
        @can('view trainings')
        <a href="{{ route('personal.trainings.index') }}"
           class="personal-card hover:shadow-md transition-shadow flex items-start gap-4 no-underline group">
            <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center text-purple-600 text-xl shrink-0">
                📚
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 group-hover:text-purple-700 transition-colors">Fortbildungen</h3>
                <p class="text-gray-500 text-sm mt-0.5">Teilnahmen und Katalog</p>
            </div>
        </a>
        @endcan

        {{-- Organigramm --}}
        @can('view orgchart')
        <a href="{{ route('personal.orgchart.index') }}"
           class="personal-card hover:shadow-md transition-shadow flex items-start gap-4 no-underline group">
            <div class="w-10 h-10 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 text-xl shrink-0">
                🏢
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 group-hover:text-teal-700 transition-colors">Organigramm</h3>
                <p class="text-gray-500 text-sm mt-0.5">Hierarchie & Stellenstruktur</p>
            </div>
        </a>
        @endcan

        {{-- Einwilligungen (Admin) --}}
        @can('manage personal_consents')
        <a href="{{ route('personal.consents.admin') }}"
           class="personal-card hover:shadow-md transition-shadow flex items-start gap-4 no-underline group">
            <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center text-rose-600 text-xl shrink-0">
                🔏
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 group-hover:text-rose-700 transition-colors">DSGVO-Einwilligungen</h3>
                <p class="text-gray-500 text-sm mt-0.5">Übersicht aller Einwilligungen</p>
            </div>
        </a>
        @endcan

    </div>

    {{-- Aktive Anstellungen (Kurzübersicht) --}}
    @if($employe->employments->isNotEmpty())
    <div class="personal-card">
        <h2 class="text-base font-semibold text-gray-700 mb-3">Aktive Anstellungen</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left py-2 pr-4 text-gray-500 font-medium">Bereich</th>
                        <th class="text-left py-2 pr-4 text-gray-500 font-medium">Stunden</th>
                        <th class="text-left py-2 text-gray-500 font-medium">Seit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employe->employments as $employment)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="py-2 pr-4 font-medium text-gray-800">{{ $employment->department->name ?? '–' }}</td>
                        <td class="py-2 pr-4 text-gray-600">{{ $employment->hours ?? '–' }} Std.</td>
                        <td class="py-2 text-gray-600">{{ optional($employment->start)->format('d.m.Y') ?? '–' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @can('view contracts')
        <div class="mt-3">
            <a href="{{ route('personal.contracts.index', $employe->id) }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                Alle Verträge ansehen →
            </a>
        </div>
        @endcan
    </div>
    @endif

</div>
@endsection

