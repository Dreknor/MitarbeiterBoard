@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-wrapper"
     x-data="wpMatrix(@js($matrix), @js(['statusUrl' => route('wp.status.setzen'), 'mehrereUrl' => route('wp.fortschritt.mehrere', $wpPlan)]))" x-cloak>
    <a href="{{ route('wp.edit', $wpPlan) }}" class="wp-back">← Zurück zum Plan</a>
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Fortschritt: {{ $wpPlan->name }}</h1>
            <p class="wp-subtitle">
                {{ $wpPlan->klasse ? 'Klasse '.$wpPlan->klasse->name.' · ' : '' }}{{ $wpPlan->zeitraum }}
                · Klick schaltet offen → teilweise → erledigt. Umschalt-/Strg-Klick wählt mehrere Zellen aus.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600" aria-label="Legende">
            <span class="wp-status is-offen" aria-hidden="true"></span> offen
            <span class="wp-status is-teilweise" aria-hidden="true"></span> teilweise
            <span class="wp-status is-erledigt" aria-hidden="true">✓</span> erledigt
            <span class="wp-status is-offen hat-notiz" aria-hidden="true"></span> mit Notiz
        </div>
    </div>

    {{-- Mehrfachauswahl --}}
    <div x-show.important="auswahl.length" x-transition class="wp-note wp-note-info mb-3 items-center">
        <span class="flex-1" x-text="auswahl.length + ' Zelle(n) ausgewählt'"></span>
        <button type="button" class="wp-btn wp-btn-success wp-btn-sm" @click="mehrere('erledigt')">erledigt</button>
        <button type="button" class="wp-btn wp-btn-warning wp-btn-sm" @click="mehrere('teilweise')">teilweise</button>
        <button type="button" class="wp-btn wp-btn-secondary wp-btn-sm" @click="mehrere('offen')">offen</button>
        <button type="button" class="wp-btn wp-btn-ghost wp-btn-sm" @click="auswahl = []">Auswahl aufheben</button>
    </div>

    <template x-if="!matrix.zeilen.length || !matrix.spalten.length">
        <div class="wp-card wp-empty">
            <div class="wp-empty-icon">✓</div>
            <span x-text="!matrix.spalten.length ? 'Der Plan hat noch keine Aufgaben.' : 'Der Klasse sind keine Kinder zugeordnet.'"></span>
        </div>
    </template>

    <template x-if="matrix.zeilen.length && matrix.spalten.length">
        <div class="wp-matrix-scroll">
            <table class="wp-matrix">
                <thead>
                    <tr>
                        <th class="wp-matrix-name">Kind</th>
                        <th class="text-left">Stand</th>
                        <template x-for="(s, i) in matrix.spalten" :key="s.id">
                            <th class="text-center" :title="s.fach + ': ' + s.aufgabe">
                                <div class="wp-matrix-kopf mx-auto" x-text="s.aufgabe"></div>
                                <div class="mt-1 text-[10px] font-normal text-gray-400" x-text="s.fach"></div>
                                <button type="button" class="mt-1 text-[10px] font-semibold text-emerald-700 hover:underline" @click="alleErledigt(i)" title="Für alle Kinder erledigt">alle ✓</button>
                            </th>
                        </template>
                        <th class="text-left">Weitere (Kinderplan)</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="z in matrix.zeilen" :key="z.schueler.id">
                        <tr>
                            <th class="wp-matrix-name">
                                <span x-text="z.schueler.name"></span>
                                <template x-if="z.kinderplan">
                                    <a :href="z.kinderplan.url" class="ml-1 text-xs font-semibold text-amber-700" title="Hat einen eigenen Kinderplan">Kinderplan</a>
                                </template>
                            </th>
                            <td><span class="wp-zaehler" :class="zaehlerKlasse(z)" x-text="zaehler(z)"></span></td>
                            <template x-for="(zelle, i) in z.zellen" :key="i">
                                <td class="text-center">
                                    <template x-if="zelle">
                                        <button type="button" class="wp-status" :class="['is-' + zelle.status, zelle.notiz && 'hat-notiz', istAusgewaehlt(zelle, z) && 'is-ausgewaehlt']"
                                                :title="titel(zelle, z, matrix.spalten[i])" :aria-label="titel(zelle, z, matrix.spalten[i])"
                                                @click="klick($event, zelle, z)" @contextmenu.prevent="notizOeffnen(zelle, z)" x-text="zeichen[zelle.status]"></button>
                                    </template>
                                    <template x-if="!zelle"><span class="text-gray-300" title="Nicht im Kinderplan">–</span></template>
                                </td>
                            </template>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="w in z.weitere" :key="w.id">
                                        <button type="button" class="wp-status" :class="['is-' + w.status, w.notiz && 'hat-notiz']"
                                                :title="w.aufgabe + ': ' + text[w.status] + (w.notiz ? '\nNotiz: ' + w.notiz : '')"
                                                @click="setzen(w, z.schueler.id, {offen:'teilweise',teilweise:'erledigt',erledigt:'offen'}[w.status], w.notiz); " @contextmenu.prevent="notizOeffnen(w, z)" x-text="zeichen[w.status]"></button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>
    <p class="wp-hint mt-2">Rechtsklick (bzw. lange tippen) auf eine Zelle: Notiz bearbeiten. Notizen sind nur für Pädagog:innen sichtbar.</p>

    {{-- Notiz --}}
    <template x-if="notiz">
    <div class="wp-overlay" @keydown.escape.window="notiz = null" @click.self="notiz = null">
        <form class="wp-dialog" @submit.prevent="notizSpeichern()">
            <div class="wp-dialog-head"><h3 class="text-lg font-bold" x-text="'Notiz – ' + (notiz?.name || '')"></h3></div>
            <div class="wp-dialog-body space-y-3">
                <div class="flex gap-2">
                    <template x-for="s in ['offen', 'teilweise', 'erledigt']" :key="s">
                        <button type="button" class="wp-btn wp-btn-sm" :class="notiz.status === s ? 'wp-btn-primary' : 'wp-btn-secondary'"
                                @click="notiz.status = s" x-text="text[s]"></button>
                    </template>
                </div>
                <textarea class="wp-textarea" rows="3" maxlength="255" x-model="notiz.text" placeholder="z. B. Seite 12 fehlt noch"></textarea>
            </div>
            <div class="wp-dialog-foot">
                <button type="button" class="wp-btn wp-btn-secondary" @click="notiz = null">Abbrechen</button>
                <button type="submit" class="wp-btn wp-btn-primary">Speichern</button>
            </div>
        </form>
    </div>
    </template>

    <div x-show.important="toast" x-transition class="wp-toast" :class="toast && ('is-' + toast.art)" role="status" aria-live="polite">
        <span x-text="toast?.text"></span>
    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush
