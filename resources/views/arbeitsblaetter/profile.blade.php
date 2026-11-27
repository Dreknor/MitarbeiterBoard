@extends('layouts.app')

@push('css')
    @vite(['resources/css/arbeitsblaetter.css', 'resources/js/arbeitsblaetter.js'])
@endpush

@section('content')
<div class="ab-wrapper ab-schmal" x-data="abProfile(@js([
        'kinder' => $schueler->map(fn ($s) => ['id' => $s->id, 'name' => $s->vorname.' '.$s->nachname, 'klasse' => $s->klasse?->name])->values(),
        'profile' => (object) $profile->all(),
        'vorlagen' => $vorlagen->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'werte' => $v->wirksameWerte(), 'kurz' => \App\Support\AbProfil::kurz($v->wirksameWerte())])->values(),
        'felder' => $felder,
        'standard' => $standard,
        'darfPflegen' => $darfPflegen,
        'urls' => [
            'vorschau' => route('arbeitsblaetter.profile.vorschau'),
            'kind' => route('arbeitsblaetter.profile.speichern', '__ID__'),
            'vorlage' => route('arbeitsblaetter.profile.vorlagen.update', '__ID__'),
            'vorlageNeu' => route('arbeitsblaetter.profile.vorlagen.store'),
        ],
    ]))" x-cloak>
    @can('create adapted worksheets')
        <a href="{{ route('arbeitsblaetter.index') }}" class="ab-back mb-3"><i class="fas fa-arrow-left"></i> Bibliothek</a>
    @endcan
    <h1 class="ab-page-title">Sehprofile</h1>
    <p class="ab-page-sub mb-4">Je Kind einmal festlegen, wie Arbeitsblätter gesetzt werden: Schrift, Größe, Abstände, Farben, Format. Keine Diagnosen oder Sehwerte eintragen – nur Darstellungswünsche.</p>

    <div class="ab-alert ab-alert-gruen mb-4" x-show.important="meldung" role="status"><i class="fas fa-check-circle mt-0.5"></i><div x-text="meldung"></div></div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <form method="get" action="{{ route('arbeitsblaetter.profile') }}" class="flex items-center gap-2">
            <label for="ab-klasse" class="text-sm font-medium text-slate-700">Klasse</label>
            <select id="ab-klasse" name="klasse" class="ab-select w-auto" onchange="this.form.submit()">
                <option value="">Alle Kinder mit Profil</option>
                @foreach($klassen as $klasse)
                    <option value="{{ $klasse->id }}" @selected($klasseId === $klasse->id)>{{ $klasse->name }}</option>
                @endforeach
            </select>
        </form>
        @if(! $darfPflegen)
            <span class="ab-hint mt-0"><i class="fas fa-lock"></i> Profile pflegen dürfen nur Personen mit dem Recht „Sehprofile pflegen“.</span>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="ab-card lg:col-span-2 overflow-hidden">
            <table class="ab-tabelle">
                <thead><tr><th>Kind</th><th>Profil</th><th class="w-24"></th></tr></thead>
                <tbody>
                    <template x-for="kind in kinder" :key="kind.id">
                        <tr>
                            <td><div class="font-semibold" x-text="kind.name"></div><div class="text-xs text-slate-500" x-text="kind.klasse || 'ohne Klasse'"></div></td>
                            <td>
                                <template x-if="profile[kind.id]">
                                    <div>
                                        <span x-text="profile[kind.id].kurz"></span>
                                        <span class="ab-chip ml-1" x-show="!profile[kind.id].aktiv">inaktiv</span>
                                        <div class="text-xs text-slate-500" x-show="profile[kind.id].vorlage_id" x-text="'Vorlage: ' + (vorlagen.find(v => v.id === profile[kind.id].vorlage_id)?.name || '')"></div>
                                    </div>
                                </template>
                                <span class="text-slate-400 text-sm" x-show="!profile[kind.id]">kein Profil</span>
                            </td>
                            <td class="text-right">
                                <button type="button" class="ab-btn ab-btn-secondary ab-btn-sm" x-show="darfPflegen" @click="kindOeffnen(kind)">
                                    <i class="fas" :class="profile[kind.id] ? 'fa-pen' : 'fa-plus'"></i> <span x-text="profile[kind.id] ? 'Ändern' : 'Anlegen'"></span>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!kinder.length"><td colspan="3" class="text-center text-slate-500 py-8">
                        {{ $klasseId ? 'Keine Kinder in dieser Klasse.' : 'Noch keine Kinder mit Profil – oben eine Klasse wählen, um eines anzulegen.' }}
                    </td></tr>
                </tbody>
            </table>
        </div>

        <div class="ab-card">
            <div class="ab-card-body">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="ab-card-title"><i class="fas fa-layer-group"></i> Profil-Vorlagen</h2>
                    <button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" x-show="darfPflegen" @click="vorlageOeffnen()"><i class="fas fa-plus"></i> Neu</button>
                </div>
                <template x-for="v in vorlagen" :key="v.id">
                    <div class="flex items-center justify-between gap-2 py-2 border-b border-slate-100">
                        <div class="min-w-0"><div class="font-semibold text-sm" x-text="v.name"></div><div class="text-xs text-slate-500" x-text="v.kurz"></div></div>
                        <button type="button" class="ab-icon-btn" x-show="darfPflegen" @click="vorlageOeffnen(v)" title="Bearbeiten"><i class="fas fa-pen"></i></button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Bearbeiten: Werte links, Beispielblatt rechts --}}
    <template x-if="offen">
        <div class="ab-dialog-hintergrund" @keydown.escape.window="offen = null">
            <div class="ab-dialog" style="max-width: 72rem" role="dialog" aria-modal="true" aria-labelledby="ab-profil-titel">
                <div class="ab-card-body">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h2 id="ab-profil-titel" class="ab-card-title" x-text="offen.art === 'kind' ? 'Sehprofil: ' + offen.name : (offen.id ? 'Vorlage bearbeiten' : 'Neue Vorlage')"></h2>
                        <button type="button" class="ab-icon-btn" @click="offen = null" title="Schließen"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <template x-if="offen.art === 'vorlage'">
                                <div class="mb-3">
                                    <label class="ab-label" for="ab-vorlage-name">Name</label>
                                    <input id="ab-vorlage-name" type="text" class="ab-input" x-model="offen.name" maxlength="100">
                                </div>
                            </template>
                            <template x-if="offen.art === 'kind'">
                                <div class="grid gap-3 sm:grid-cols-2 mb-1">
                                    <div>
                                        <label class="ab-label" for="ab-vorlage">Ausgangsvorlage</label>
                                        <select id="ab-vorlage" class="ab-select" x-model="offen.vorlage_id" @change="vorlageGewaehlt()">
                                            <option value="">ohne (Standard)</option>
                                            <template x-for="v in vorlagen" :key="v.id"><option :value="v.id" x-text="v.name" :selected="Number(offen.vorlage_id) === v.id"></option></template>
                                        </select>
                                        <p class="ab-hint">Geänderte Werte (•) weichen von der Vorlage ab.</p>
                                    </div>
                                    <label class="ab-check self-center"><input type="checkbox" x-model="offen.aktiv"> Profil aktiv (Fassungen werden erzeugt)</label>
                                </div>
                            </template>

                            <template x-for="(eintraege, gruppe) in gruppen()" :key="gruppe">
                                <div>
                                    <h3 class="ab-gruppe-titel" x-text="gruppe"></h3>
                                    <div class="grid gap-2">
                                        <template x-for="[feld, def] in eintraege" :key="feld">
                                            <div>
                                                <label class="text-sm text-slate-700 flex items-center gap-1" :for="'ab-f-' + feld">
                                                    <span x-text="def.label"></span><span class="text-indigo-600 font-bold" x-show="abweichend(feld)">•</span>
                                                </label>
                                                <template x-if="def.typ === 'zahl'">
                                                    <div class="ab-regler">
                                                        <input type="range" :id="'ab-f-' + feld" :min="def.min" :max="def.max" :step="def.schritt" x-model.number="offen.werte[feld]" @input="vorschau()">
                                                        <span class="text-sm text-right tabular-nums" x-text="String(offen.werte[feld]).replace('.', ',') + ' ' + (def.einheit || '')"></span>
                                                    </div>
                                                </template>
                                                <template x-if="def.typ === 'auswahl'">
                                                    <select class="ab-select ab-input-sm" :id="'ab-f-' + feld" x-model="offen.werte[feld]" @change="vorschau()">
                                                        <template x-for="w in def.werte" :key="w"><option :value="w" x-text="({{ \Illuminate\Support\Js::from($schriften + $farbschemata) }})[w] || w" :selected="offen.werte[feld] === w"></option></template>
                                                    </select>
                                                </template>
                                                <template x-if="def.typ === 'schalter'">
                                                    <label class="ab-check"><input type="checkbox" :id="'ab-f-' + feld" x-model="offen.werte[feld]" @change="vorschau()"> ja</label>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <p class="ab-hint mt-3" x-show="offen.werte.farbschema === 'invertiert'"><i class="fas fa-tint"></i> Invertierter Druck: dunkler Seitenhintergrund, hoher Tonerverbrauch.</p>
                        </div>
                        <div>
                            <h3 class="ab-gruppe-titel mt-0">Beispielblatt</h3>
                            <iframe x-ref="beispiel" title="Beispielblatt mit diesem Profil" sandbox="allow-same-origin" class="w-full rounded-xl border border-slate-200" style="height: 60vh"></iframe>
                        </div>
                    </div>
                    <div class="ab-alert ab-alert-red mt-3" x-show.important="fehler" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><div x-text="fehler"></div></div>
                    <div class="flex flex-wrap justify-between gap-2 mt-4">
                        <button type="button" class="ab-btn ab-btn-danger" x-show="offen.art === 'kind' ? !offen.neu : offen.id" @click="entfernen()"><i class="fas fa-trash"></i> <span x-text="offen.art === 'kind' ? 'Profil entfernen' : 'Vorlage löschen'"></span></button>
                        <span></span>
                        <div class="flex gap-2">
                            <button type="button" class="ab-btn ab-btn-secondary" @click="offen = null">Abbrechen</button>
                            <button type="button" class="ab-btn ab-btn-primary" @click="speichern()" :disabled="speichert"><i class="fas fa-save"></i> Speichern</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
