@extends('layouts.app')

@push('css')
    @vite(['resources/css/arbeitsblaetter.css', 'resources/js/arbeitsblaetter.js'])
@endpush

@section('content')
<div class="ab-wrapper ab-schmal" x-data="abErfassung({
        url: @js(route('arbeitsblaetter.store')),
        maxSeiten: {{ $maxSeiten }},
        ki: @js($kiAktiv && !$kiGesperrt),
    })" x-cloak>
    <a href="{{ route('arbeitsblaetter.index') }}" class="ab-back mb-3"><i class="fas fa-arrow-left"></i> Bibliothek</a>
    <h1 class="ab-page-title mb-1">Neues Arbeitsblatt</h1>
    <p class="ab-page-sub mb-5">Das Original wird erkannt und als Bausteine aufbereitet. Danach prüfen Sie das Ergebnis und geben es frei.</p>

    @if(!$kiAktiv)
        <div class="ab-alert ab-alert-blue mb-4"><i class="fas fa-info-circle mt-0.5"></i><div>Es ist keine KI-Erkennung eingerichtet. Das Original erscheint im Editor neben einer leeren Bausteinliste zum Abtippen.</div></div>
    @elseif($kiGesperrt)
        <div class="ab-alert ab-alert-amber mb-4"><i class="fas fa-exclamation-triangle mt-0.5"></i><div>{{ $kiGesperrt }}</div></div>
    @endif

    <div class="ab-card mb-4">
        <div class="ab-card-body grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-3">
                <label class="ab-label" for="ab-titel">Titel</label>
                <input id="ab-titel" type="text" class="ab-input" x-model="titel" maxlength="200" placeholder="z. B. Brüche – Seite 12 (sonst aus dem Dateinamen)">
            </div>
            <div>
                <label class="ab-label" for="ab-fach">Fach</label>
                <select id="ab-fach" class="ab-select" x-model="fach">
                    <option value="">–</option>
                    @foreach($faecher as $fach)<option value="{{ $fach->id }}">{{ $fach->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="ab-label" for="ab-stufe">Klassenstufe</label>
                <select id="ab-stufe" class="ab-select" x-model="stufe">
                    <option value="">–</option>
                    @for($i = 1; $i <= 13; $i++)<option value="{{ $i }}">Klasse {{ $i }}</option>@endfor
                </select>
            </div>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3 mb-4" role="radiogroup" aria-label="Quelle">
        <button type="button" class="ab-quelle" :class="art === 'kamera' && 'is-active'" @click="art = 'kamera'; $refs.kamera.click()">
            <i class="fas fa-camera"></i><span class="font-semibold">Fotografieren</span>
            <span class="text-xs text-slate-500">Seiten nacheinander aufnehmen, Ecken zurechtziehen</span>
        </button>
        <button type="button" class="ab-quelle" :class="art === 'upload' && 'is-active'" @click="art = 'upload'; $refs.datei.click()">
            <i class="fas fa-file-upload"></i><span class="font-semibold">Datei hochladen</span>
            <span class="text-xs text-slate-500">PDF, Foto (JPG/PNG) oder Word (DOC/DOCX/ODT), max. 20 MB</span>
        </button>
        <button type="button" class="ab-quelle" :class="art === 'manuell' && 'is-active'" @click="art = 'manuell'">
            <i class="fas fa-keyboard"></i><span class="font-semibold">Abtippen</span>
            <span class="text-xs text-slate-500">Leeres Blatt, Bausteine selbst eingeben</span>
        </button>
    </div>
    <input type="file" x-ref="kamera" accept="image/*" capture="environment" multiple class="hidden" @change="bilderGewaehlt($event)">
    <input type="file" x-ref="datei" accept=".{{ str_replace(',', ',.', $endungen) }}" class="hidden" @change="dateiGewaehlt($event)">

    {{-- Kamera: Ecken ziehen --}}
    <template x-if="art === 'kamera'">
        <div class="ab-card mb-4">
            <div class="ab-card-body">
                <template x-if="aktuell">
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <h2 class="ab-card-title"><i class="fas fa-crop-alt"></i> Seite <span x-text="seiten.length + 1"></span>: Ecken an das Blatt ziehen</h2>
                            <div class="flex gap-2">
                                <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click="drehen()"><i class="fas fa-redo"></i> Drehen</button>
                                <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" @click="eckenZuruecksetzen()"><i class="fas fa-expand"></i> Ganzes Bild</button>
                            </div>
                        </div>
                        <div class="ab-ecken" x-ref="ecken">
                            <canvas x-ref="leinwand"></canvas>
                            <template x-for="(p, i) in ecken" :key="i">
                                <span class="ab-griff" :style="`left:${p.x * 100}%;top:${p.y * 100}%`" @pointerdown.prevent="griffStart($event, i)" role="slider" :aria-label="`Ecke ${i + 1}`" tabindex="0"></span>
                            </template>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2 mt-3">
                            <button type="button" class="ab-btn ab-btn-secondary" @click="verwerfen()">Verwerfen</button>
                            <button type="button" class="ab-btn ab-btn-primary" @click="uebernehmen()" :disabled="beschaeftigt"><i class="fas fa-check"></i> Seite übernehmen</button>
                        </div>
                    </div>
                </template>
                <template x-if="!aktuell">
                    <div class="flex flex-wrap items-center gap-3">
                        <template x-for="(s, i) in seiten" :key="i">
                            <div class="ab-seite-mini">
                                <img :src="s.vorschau" :alt="`Seite ${i + 1}`">
                                <button type="button" class="ab-icon-btn absolute top-1 right-1 bg-white/90" @click="seiteEntfernen(i)" title="Seite entfernen"><i class="fas fa-times"></i></button>
                            </div>
                        </template>
                        <button type="button" class="ab-btn ab-btn-secondary" @click="$refs.kamera.click()" x-show="seiten.length < maxSeiten">
                            <i class="fas fa-camera"></i> <span x-text="seiten.length ? 'Weitere Seite' : 'Foto aufnehmen'"></span>
                        </button>
                        <p class="text-sm text-slate-500" x-show="!seiten.length">Noch keine Seite aufgenommen.</p>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <template x-if="art === 'upload' && datei">
        <div class="ab-alert ab-alert-blue mb-4"><i class="fas fa-file mt-0.5"></i><div><strong x-text="datei.name"></strong> <span x-text="'(' + groesse(datei.size) + ')'"></span>
            <button type="button" class="underline ml-2" @click="$refs.datei.click()">andere Datei</button></div></div>
    </template>

    <template x-if="fehler">
        <div class="ab-alert ab-alert-red mb-4" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><div x-text="fehler"></div></div>
    </template>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="ab-check" x-show="art !== 'manuell'">
            <input type="checkbox" x-model="erkennen" :disabled="!ki">
            <span>Mit KI erkennen <span class="text-slate-500" x-show="!ki">(nicht verfügbar)</span></span>
        </label>
        <span x-show="art === 'manuell'"></span>
        <button type="button" class="ab-btn ab-btn-primary" @click="absenden()" :disabled="!bereit() || beschaeftigt">
            <i class="fas" :class="beschaeftigt ? 'fa-spinner fa-spin' : 'fa-arrow-right'"></i>
            <span x-text="art === 'manuell' ? 'Leeres Blatt anlegen' : 'Weiter zum Editor'"></span>
        </button>
    </div>
</div>
@endsection
