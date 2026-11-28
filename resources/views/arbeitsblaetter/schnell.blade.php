@extends('layouts.app')

@push('css')
    @vite(['resources/css/arbeitsblaetter.css', 'resources/js/arbeitsblaetter.js'])
@endpush

@section('content')
{{-- Schnellmodus: Erkennung abwarten → Abbildungen im Browser ausschneiden → freigeben → Fassungen herunterladen --}}
<div class="ab-wrapper ab-schmal" x-data="abSchnellStatus(@js([
        'blatt' => $daten,
        'fertig' => $fertig,
        'fertigUrl' => route('arbeitsblaetter.schnell.fertig', $blatt),
    ]))" x-cloak>
    <a href="{{ route('arbeitsblaetter.index') }}" class="ab-back mb-3"><i class="fas fa-arrow-left"></i> Bibliothek</a>
    <h1 class="ab-page-title mb-1" x-text="blatt.titel"></h1>
    <p class="ab-page-sub mb-5">Schnellmodus – das Blatt wird ohne Prüfung gesetzt. <a href="{{ route('arbeitsblaetter.edit', $blatt) }}" class="underline">Im erweiterten Modus prüfen und bearbeiten</a></p>

    {{-- Fortschritt --}}
    <ol class="ab-schritte mb-5" x-show.important="schritt !== 'fertig' && schritt !== 'fehler'">
        <template x-for="(s, i) in schritte" :key="s.key">
            <li :class="{ 'is-fertig': i < schrittIndex(), 'is-aktiv': i === schrittIndex() }">
                <i class="fas" :class="i < schrittIndex() ? 'fa-check-circle' : (i === schrittIndex() ? 'fa-spinner fa-spin' : 'fa-circle')"></i>
                <span x-text="s.text"></span>
            </li>
        </template>
    </ol>
    <div class="ab-alert ab-alert-amber mb-4" x-show.important="schritt === 'erkennung' && blatt.fehler"><i class="fas fa-exclamation-triangle mt-0.5"></i><div x-text="blatt.fehler"></div></div>

    {{-- Fehler --}}
    <div class="ab-alert ab-alert-red mb-4" x-show.important="schritt === 'fehler'" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i>
        <div><span x-text="fehler"></span>
            <a href="{{ route('arbeitsblaetter.edit', $blatt) }}" class="underline font-semibold ml-1">Im erweiterten Modus weiter</a></div></div>

    {{-- Fertig --}}
    <template x-if="schritt === 'fertig' && ergebnis">
        <div>
            <div class="ab-alert ab-alert-gruen mb-4"><i class="fas fa-check-circle mt-0.5"></i>
                <div>Fertig! <span x-text="ergebnis.fassungen.length === 1 ? 'Die angepasste Fassung steht bereit.' : ergebnis.fassungen.length + ' angepasste Fassungen stehen bereit.'"></span></div></div>

            <div class="ab-alert ab-alert-amber mb-4" x-show.important="ergebnis.hinweise.length"><i class="fas fa-search mt-0.5"></i>
                <div><strong>Bitte kurz ansehen</strong> – die KI hat das Blatt ohne Prüfung gesetzt:
                    <ul class="list-disc ml-5 mt-1"><template x-for="h in ergebnis.hinweise" :key="h"><li x-text="h"></li></template></ul>
                    <a href="{{ route('arbeitsblaetter.edit', $blatt) }}" class="underline font-semibold">Im erweiterten Modus nachbessern</a></div></div>

            <div class="ab-card mb-4">
                <div class="ab-card-body">
                    <ul class="ab-fassungen">
                        <template x-for="f in ergebnis.fassungen" :key="f.id">
                            <li>
                                <span class="font-semibold" x-text="f.empfaenger"></span>
                                <span class="flex flex-wrap gap-2">
                                    <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click="vorschau = f.ansehen" :class="vorschau === f.ansehen && 'is-active'"><i class="fas fa-eye"></i> Ansehen</button>
                                    <a :href="f.pdf" class="ab-btn ab-btn-primary ab-btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
                                    <a :href="f.docx" class="ab-btn ab-btn-secondary ab-btn-sm" x-show="f.docx"><i class="fas fa-file-word"></i> Word</a>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
            <iframe x-show="vorschau" :src="vorschau" class="ab-schnell-vorschau" title="Vorschau der angepassten Fassung"></iframe>

            <div class="flex flex-wrap justify-between gap-2 mt-4">
                <a href="{{ route('arbeitsblaetter.edit', $blatt) }}" class="ab-btn ab-btn-secondary"><i class="fas fa-sliders-h"></i> Erweitert bearbeiten</a>
                <a href="{{ route('arbeitsblaetter.create') }}" class="ab-btn ab-btn-primary"><i class="fas fa-bolt"></i> Nächstes Blatt</a>
            </div>
        </div>
    </template>

    {{-- unsichtbare Arbeitsfläche für die Originale (Ausschneiden der Abbildungen) --}}
    <div x-ref="original" class="hidden" aria-hidden="true"></div>
</div>
@endsection
