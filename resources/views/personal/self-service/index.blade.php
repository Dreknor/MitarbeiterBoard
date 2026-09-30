@extends('layouts.app')

@push('css')
    @vite('resources/css/personal.css')
@endpush

@section('content')
<div class="personal-wrapper" x-data="personalTabs('uebersicht')" x-init="init()" x-cloak>

    {{-- Seitenkopf --}}
    <div class="flex items-center gap-4 mb-6">
        <div class="shrink-0 w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xl">
            {{ substr(auth()->user()->name, 0, 1) }}
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mein Profil</h1>
            <p class="text-gray-500 text-sm">{{ auth()->user()->email }}</p>
        </div>
        <button type="button" class="btn-personal-secondary text-sm ml-auto" data-tour-start="mein-profil" title="Kurze Einführung starten">
            <i class="fas fa-route"></i> Tour
        </button>
    </div>

    @if(session('Meldung'))
    <div class="rounded-lg p-4 mb-4 {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
        {{ session('Meldung') }}
    </div>
    @endif

    {{-- Tab-Navigation --}}
    <div class="flex border-b border-gray-200 mb-6 overflow-x-auto" data-tour="profil-tabs">
        @foreach(array_filter([
            ['key' => 'uebersicht',      'label' => 'Übersicht'],
            ['key' => 'vertraege',       'label' => 'Verträge'],
            ['key' => 'urlaub',          'label' => 'Urlaub & Abwesenheiten'],
            ['key' => 'einwilligungen',  'label' => 'Einwilligungen'],
            auth()->user()->can('view paed diary') ? ['key' => 'app', 'label' => 'Pädagogen-App'] : null,
        ]) as $tab)
        <button type="button" data-tab="{{ $tab['key'] }}" @click="setTab('{{ $tab['key'] }}')"
                :class="isActive('{{ $tab['key'] }}') ? 'personal-tab personal-tab-active' : 'personal-tab personal-tab-inactive'">
            {{ $tab['label'] }}
        </button>
        @endforeach
    </div>

    {{-- Tab: Übersicht --}}
    <div x-show="isActive('uebersicht')">
        @include('personal.self-service._tab_uebersicht')
    </div>

    {{-- Tab: Verträge --}}
    <div x-show="isActive('vertraege')" data-tour="tab-vertraege">
        @include('personal.self-service._tab_vertraege')
    </div>

    {{-- Tab: Urlaub & Abwesenheiten (Urlaubskonto + eigene Abwesenheiten) --}}
    <div x-show="isActive('urlaub')" data-tour="tab-urlaub">
        @include('personal.self-service._tab_urlaub')
    </div>

    {{-- Dokumente, Qualifikationen und Gespräche sind noch nicht umgesetzt und daher ausgeblendet --}}

    {{-- Tab: Einwilligungen --}}
    <div x-show="isActive('einwilligungen')" data-tour="tab-einwilligungen">
        @include('personal.self-service._tab_einwilligungen')
    </div>

    {{-- Tab: Pädagogen-App (App verbinden, Meine App-Geräte) --}}
    @can('view paed diary')
    <div x-show="isActive('app')">
        @include('personal.self-service._tab_app')
    </div>
    @endcan

</div>

@php
    // Nächste Station der Einführungstour
    $tourWeiter = match (true) {
        auth()->user()->canAny(['has holidays', 'approve holidays']) => ['label' => 'Weiter: Urlaub', 'url' => route('holidays.index', ['tour' => 'urlaub'])],
        auth()->user()->can('has timesheet') => ['label' => 'Weiter: Arbeitszeitnachweis', 'url' => route('timesheets.show', ['user' => auth()->id(), 'tour' => 'arbeitszeit'])],
        default => null,
    };
@endphp
<x-tour id="mein-profil" :next="$tourWeiter" :steps="[
    [
        'target' => 'profil-tabs',
        'title' => 'Mein Profil',
        'text' => 'Alles zu deiner Anstellung an einem Ort, aufgeteilt in Reiter. Die Tour öffnet die wichtigsten Reiter nacheinander für dich.',
    ],
    [
        'target' => 'anstellung',
        'activate' => '[data-tab=uebersicht]',
        'title' => 'Aktuelle Anstellung und Historie',
        'text' => 'Hier siehst du deine aktuelle Anstellung mit Wochenstunden und Befristung.

Über „Anstellungshistorie anzeigen“ klappst du alle früheren Vertragsversionen auf – Änderungsverträge und interne Wechsel sind gekennzeichnet.',
    ],
    [
        'target' => 'tab-vertraege',
        'activate' => '[data-tab=vertraege]',
        'title' => 'Verträge',
        'text' => 'Alle deine Anstellungen im Detail, auch bereits beendete: Abteilung, Laufzeit und Wochenstunden. Aus deinem Vertrag berechnen sich jetzt deine Soll-Arbeitszeit (Stellenanteil) und dein Urlaubsanspruch (Arbeitstage pro Woche).',
    ],
    [
        'target' => 'tab-einwilligungen',
        'activate' => '[data-tab=einwilligungen]',
        'title' => 'Einwilligungen',
        'text' => 'Deine Datenschutz-Einwilligungen kannst du hier selbst erteilen oder jederzeit widerrufen.',
    ],
    [
        'target' => 'tab-urlaub',
        'activate' => '[data-tab=urlaub]',
        'title' => 'Urlaub & Abwesenheiten',
        'text' => 'Dein Urlaubskonto für das gewählte Jahr: Anspruch plus Übertrag aus dem Vorjahr, abzüglich genehmigtem Urlaub, ergibt deinen Resturlaub. Droht Resturlaub zu verfallen, siehst du hier einen Hinweis.

Daneben stehen alle Urlaube und Abwesenheiten des Jahres. Mit ‹ › wechselst du das Jahr.',
    ],
]" />
@endsection

@push('js')
    @vite('resources/js/personal.js')
@endpush

