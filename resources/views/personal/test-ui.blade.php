{{-- resources/views/personal/test-ui.blade.php --}}
{{-- Temporäre Test-View für Phase 0 – Tailwind/Alpine Verifizierung --}}
@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('content')
<div class="personal-wrapper">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Personal-Modul – UI Test</h1>
    </div>

    <div class="personal-card mb-4">
        <h2 class="text-xl font-bold text-gray-900">Tailwind funktioniert ✅</h2>
        <p class="text-gray-500 mt-2">Dieser Text sollte Tailwind-Stile haben.</p>

        <div class="flex gap-2 mt-4">
            <span class="badge-green">Gültig</span>
            <span class="badge-yellow">Ablaufend</span>
            <span class="badge-red">Abgelaufen</span>
            <span class="badge-gray">Fehlend</span>
        </div>

        <div class="flex gap-2 mt-4">
            <button class="btn-personal-primary">Primär</button>
            <button class="btn-personal-secondary">Sekundär</button>
            <button class="btn-personal-danger">Danger</button>
        </div>
    </div>

    {{-- Tab-Test mit Alpine.js --}}
    <div class="personal-card" x-data="personalTabs('stammdaten')">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Tab-Navigation (Alpine.js)</h2>

        <div class="flex border-b border-gray-200 mb-4">
            <button class="personal-tab"
                    :class="isActive('stammdaten') ? 'personal-tab-active' : 'personal-tab-inactive'"
                    @click="setTab('stammdaten')">
                Stammdaten
            </button>
            <button class="personal-tab"
                    :class="isActive('vertrag') ? 'personal-tab-active' : 'personal-tab-inactive'"
                    @click="setTab('vertrag')">
                Vertrag
            </button>
            <button class="personal-tab"
                    :class="isActive('qualifikationen') ? 'personal-tab-active' : 'personal-tab-inactive'"
                    @click="setTab('qualifikationen')">
                Qualifikationen
            </button>
        </div>

        <div x-show="isActive('stammdaten')" x-cloak>
            <p class="text-gray-600">Stammdaten-Tab Inhalt</p>
            <div class="mt-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" class="input-personal" placeholder="Max Mustermann">
            </div>
        </div>

        <div x-show="isActive('vertrag')" x-cloak>
            <p class="text-gray-600">Vertrag-Tab Inhalt</p>
        </div>

        <div x-show="isActive('qualifikationen')" x-cloak>
            <p class="text-gray-600">Qualifikationen-Tab Inhalt</p>
        </div>
    </div>

    {{-- Tabelle-Test --}}
    <div class="personal-card mt-4">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Tabelle</h2>
        <table class="table-personal">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Abteilung</th>
                    <th>Status</th>
                    <th>Vertragsart</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Max Mustermann</td>
                    <td>Grundschule</td>
                    <td><span class="badge-green">Aktiv</span></td>
                    <td>Unbefristet</td>
                </tr>
                <tr>
                    <td>Erika Musterfrau</td>
                    <td>Hort</td>
                    <td><span class="badge-yellow">Ruhend</span></td>
                    <td>Befristet</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush

