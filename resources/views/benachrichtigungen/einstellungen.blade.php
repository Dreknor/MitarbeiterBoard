@extends('layouts.app')

@section('title', 'Benachrichtigungen – Einstellungen')
@section('site-title', 'Benachrichtigungen')

@push('css')
    @vite(['resources/css/benachrichtigungen.css'])
@endpush

@section('content')
@php
    $tv = $einstellung;
    $tvAlt = old('tagesvorschau', []);
    $tvWert = fn (string $feld, $standard) => array_key_exists($feld, $tvAlt) ? $tvAlt[$feld] : $standard;
    $hatAlteEingabe = !empty($tvAlt);
    $bereichAktiv = function ($quelle) use ($tv, $tvAlt, $hatAlteEingabe) {
        if ($hatAlteEingabe) {
            return in_array($quelle->bereich(), $tvAlt['bereiche'] ?? [], true);
        }
        return $quelle->aktivFuer(auth()->user(), $tv);
    };
    $kalenderAktiv = fn (int $id) => in_array($id, array_map('intval', $hatAlteEingabe ? ($tvAlt['kalender_ids'] ?? []) : ($tv->kalender_ids ?? [])), true);
    $zeigtKalender = $quellen->contains(fn ($q) => $q->bereich() === 'kalender');
@endphp

<div class="bn-wrapper">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="min-w-0">
            <a href="{{ route('benachrichtigungen.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
                <i class="fas fa-arrow-left"></i> Benachrichtigungen
            </a>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900 mt-1">Einstellungen</h1>
            <p class="text-sm text-gray-500 mt-0.5">Legen Sie fest, worüber und wie Sie informiert werden.</p>
        </div>
        @if($wikiUrl)
            <a href="{{ $wikiUrl }}" class="bn-btn bn-btn-secondary"><i class="fas fa-question-circle"></i> Hilfe</a>
        @endif
    </div>

    {{-- ── Push auf diesem Gerät ─────────────────────────────────────────── --}}
    <section class="bn-card mb-6"
             x-data="{
                status: 'laedt',
                meldung: '',
                fehler: false,
                beschaeftigt: false,
                init() { this.pruefen(); },
                pruefen() {
                    if (!window.MbPush) { this.status = 'nicht-unterstuetzt'; return; }
                    window.MbPush.status().then(s => this.status = s).catch(() => this.status = 'inaktiv');
                },
                ausfuehren(aktion, erfolg) {
                    this.beschaeftigt = true; this.meldung = ''; this.fehler = false;
                    window.MbPush[aktion]()
                        .then(() => { this.meldung = erfolg; this.pruefen(); })
                        .catch(e => { this.meldung = e.message; this.fehler = true; this.pruefen(); })
                        .finally(() => this.beschaeftigt = false);
                }
             }">
        <div class="bn-card-head">
            <div class="flex items-center gap-3 min-w-0">
                <span class="bn-icon"><i class="fas fa-mobile-alt"></i></span>
                <div class="min-w-0">
                    <h2>Push-Benachrichtigungen auf diesem Gerät</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Push muss auf jedem Gerät (Handy, Tablet, PC) einmal aktiviert werden.
                        @if($hatPush) Auf mindestens einem Ihrer Geräte ist Push aktiv. @endif
                    </p>
                </div>
            </div>
        </div>
        <div class="px-5 py-4 flex flex-wrap items-center gap-3">
            <span class="text-sm" x-show="status === 'laedt'"><i class="fas fa-spinner fa-spin"></i> Prüfe …</span>
            <span class="text-sm text-emerald-700 font-medium" x-show="status === 'aktiv'" x-cloak><i class="fas fa-check-circle"></i> Push ist auf diesem Gerät aktiv.</span>
            <span class="text-sm text-gray-600" x-show="status === 'inaktiv'" x-cloak>Push ist auf diesem Gerät nicht aktiv.</span>
            <span class="text-sm text-amber-700" x-show="status === 'blockiert'" x-cloak><i class="fas fa-exclamation-triangle"></i> Benachrichtigungen sind im Browser blockiert. Bitte in den Website-Einstellungen des Browsers erlauben.</span>
            <span class="text-sm text-gray-600" x-show="status === 'nicht-unterstuetzt'" x-cloak>Dieser Browser unterstützt keine Push-Benachrichtigungen. Auf dem iPhone: Seite zuerst über „Teilen → Zum Home-Bildschirm“ als App hinzufügen.</span>

            <div class="flex flex-wrap gap-2 ml-auto">
                <button type="button" class="bn-btn bn-btn-primary bn-btn-sm" x-show="status === 'inaktiv'" x-cloak :disabled="beschaeftigt"
                        @click="ausfuehren('aktivieren', 'Push wurde aktiviert.')">
                    <i class="fas fa-bell"></i> Push auf diesem Gerät aktivieren
                </button>
                <button type="button" class="bn-btn bn-btn-secondary bn-btn-sm" x-show="status === 'aktiv'" x-cloak :disabled="beschaeftigt"
                        @click="ausfuehren('test', 'Test-Benachrichtigung wurde gesendet.')">
                    <i class="fas fa-paper-plane"></i> Test senden
                </button>
                <button type="button" class="bn-btn bn-btn-danger bn-btn-sm" x-show="status === 'aktiv'" x-cloak :disabled="beschaeftigt"
                        @click="ausfuehren('deaktivieren', 'Push wurde auf diesem Gerät deaktiviert.')">
                    <i class="fas fa-bell-slash"></i> Deaktivieren
                </button>
            </div>
            <p class="w-full text-sm" x-show="meldung" x-cloak :class="fehler ? 'text-red-600' : 'text-emerald-700'" x-text="meldung"></p>
        </div>
    </section>

    <form method="POST" action="{{ route('benachrichtigungen.einstellungen.speichern') }}">
        @csrf
        @method('PUT')

        {{-- ── Kategorien ─────────────────────────────────────────────────── --}}
        <section class="bn-card mb-6">
            <div class="bn-card-head">
                <div class="flex items-center gap-3">
                    <span class="bn-icon"><i class="fas fa-sliders-h"></i></span>
                    <div>
                        <h2>Worüber möchten Sie informiert werden?</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            In der Glocke erscheint immer alles. <strong>Mail „Zusammenfassung“</strong> bündelt alles in einer Mail am Nachmittag ({{ config('benachrichtigungen.zusammenfassung.uhrzeit') }} Uhr).
                        </p>
                    </div>
                </div>
            </div>

            <div class="hidden sm:grid grid-cols-12 gap-3 px-5 py-2 text-xs font-medium text-gray-500 bg-gray-50">
                <div class="col-span-6">Kategorie</div>
                <div class="col-span-2 text-center">Push</div>
                <div class="col-span-4">Mail</div>
            </div>

            @foreach($kategorien as $schluessel => $kategorie)
                @php
                    $wert = $werte[$schluessel];
                    $push = old("kategorien.$schluessel.push", $wert['push'] ? '1' : '0') === '1';
                    $mail = old("kategorien.$schluessel.mail", $wert['mail']);
                @endphp
                <div class="grid grid-cols-12 gap-3 items-center px-5 py-3" style="border-top:1px solid #f3f4f6;">
                    <div class="col-span-12 sm:col-span-6 flex items-start gap-3 min-w-0">
                        <span class="bn-icon"><i class="fas {{ $kategorie['icon'] }}"></i></span>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900">{{ $kategorie['label'] }}</div>
                            <div class="text-xs text-gray-500">{{ $kategorie['beschreibung'] }}</div>
                        </div>
                    </div>
                    <div class="col-span-4 sm:col-span-2 flex sm:justify-center items-center gap-2">
                        <span class="sm:hidden text-xs text-gray-500">Push</span>
                        <input type="hidden" name="kategorien[{{ $schluessel }}][push]" value="0">
                        <label class="bn-switch" title="Push für {{ $kategorie['label'] }}">
                            <input type="checkbox" name="kategorien[{{ $schluessel }}][push]" value="1" @checked($push)
                                   aria-label="Push für {{ $kategorie['label'] }}">
                            <span></span>
                        </label>
                    </div>
                    <div class="col-span-8 sm:col-span-4 flex items-center gap-2">
                        <span class="sm:hidden text-xs text-gray-500">Mail</span>
                        <div class="bn-segment" role="radiogroup" aria-label="Mail für {{ $kategorie['label'] }}">
                            @foreach($mailModi as $modus => $modusLabel)
                                <label>
                                    <input type="radio" name="kategorien[{{ $schluessel }}][mail]" value="{{ $modus }}" @checked($mail === $modus)>
                                    <span>{{ $modusLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ── Tagesübersicht ─────────────────────────────────────────────── --}}
        <section class="bn-card mb-6"
                 x-data="{
                    zeitpunkt: @js($tvWert('zeitpunkt', $tv->zeitpunkt)),
                    uhrzeit: @js($tvWert('uhrzeit', $tv->uhrzeitKurz())),
                    fenster: @js($fenster),
                    zeiten() {
                        const [von, bis] = this.fenster[this.zeitpunkt];
                        const liste = [];
                        let [h, m] = von.split(':').map(Number);
                        const [bh, bm] = bis.split(':').map(Number);
                        while (h < bh || (h === bh && m <= bm)) {
                            liste.push(String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'));
                            m += 15; if (m >= 60) { m = 0; h++; }
                        }
                        return liste;
                    },
                    zeitpunktGewechselt() {
                        if (!this.zeiten().includes(this.uhrzeit)) {
                            this.uhrzeit = this.zeitpunkt === 'vorabend' ? '19:00' : '06:30';
                        }
                    }
                 }">
            <div class="bn-card-head">
                <div class="flex items-center gap-3">
                    <span class="bn-icon"><i class="fas fa-sun"></i></span>
                    <div>
                        <h2>Tagesübersicht „Dein Tag“</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Eine kurze Übersicht über alles, was an einem Arbeitstag ansteht. Sie kommt nur, wenn auch etwas ansteht, und nicht an Tagen, an denen Sie selbst abwesend sind.
                        </p>
                    </div>
                </div>
                <a href="{{ route('benachrichtigungen.tag') }}" class="bn-btn bn-btn-secondary bn-btn-sm shrink-0" target="_blank">
                    <i class="fas fa-eye"></i> Vorschau
                </a>
            </div>

            <div class="px-5 py-4 grid gap-5 sm:grid-cols-2">
                {{-- Versandweg --}}
                <div>
                    <div class="text-xs font-medium text-gray-500 mb-2">Zustellen per</div>
                    <div class="flex flex-col gap-3">
                        <label class="flex items-center gap-3">
                            <input type="hidden" name="tagesvorschau[per_mail]" value="0">
                            <span class="bn-switch">
                                <input type="checkbox" name="tagesvorschau[per_mail]" value="1" @checked((string) $tvWert('per_mail', $tv->per_mail ? '1' : '0') === '1')>
                                <span></span>
                            </span>
                            <span class="text-sm text-gray-800">E-Mail</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="hidden" name="tagesvorschau[per_push]" value="0">
                            <span class="bn-switch">
                                <input type="checkbox" name="tagesvorschau[per_push]" value="1" @checked((string) $tvWert('per_push', $tv->per_push ? '1' : '0') === '1')>
                                <span></span>
                            </span>
                            <span class="text-sm text-gray-800">Push (Kurzfassung)</span>
                        </label>
                    </div>
                </div>

                {{-- Zeitpunkt --}}
                <div>
                    <div class="text-xs font-medium text-gray-500 mb-2">Wann?</div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="bn-segment" role="radiogroup" aria-label="Zeitpunkt">
                            <label>
                                <input type="radio" name="tagesvorschau[zeitpunkt]" value="morgens" x-model="zeitpunkt" @change="zeitpunktGewechselt()">
                                <span>am Morgen</span>
                            </label>
                            <label>
                                <input type="radio" name="tagesvorschau[zeitpunkt]" value="vorabend" x-model="zeitpunkt" @change="zeitpunktGewechselt()">
                                <span>am Vorabend</span>
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="tv-uhrzeit" class="text-sm text-gray-600">um</label>
                            <select id="tv-uhrzeit" name="tagesvorschau[uhrzeit]" class="bn-select" style="width:auto;" x-model="uhrzeit">
                                <template x-for="z in zeiten()" :key="z">
                                    <option :value="z" x-text="z" :selected="z === uhrzeit"></option>
                                </template>
                            </select>
                            <span class="text-sm text-gray-600">Uhr</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2" x-show="zeitpunkt === 'vorabend'" x-cloak>
                        Am Vorabend erhalten Sie die Übersicht für den nächsten Arbeitstag, am Freitag also schon für Montag.
                    </p>
                </div>

                {{-- Bereiche --}}
                <div class="sm:col-span-2">
                    <div class="text-xs font-medium text-gray-500 mb-2">Was soll enthalten sein?</div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($quellen as $quelle)
                            <label class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-gray-50" style="border:1px solid #f3f4f6;">
                                <input type="checkbox" class="bn-check" name="tagesvorschau[bereiche][]" value="{{ $quelle->bereich() }}" @checked($bereichAktiv($quelle))>
                                <i class="fas {{ $quelle->icon() }} text-gray-400 w-4 text-center"></i>
                                <span class="text-sm text-gray-800">{{ $quelle->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Kalender --}}
                @if($zeigtKalender)
                    <div class="sm:col-span-2">
                        <div class="text-xs font-medium text-gray-500 mb-2">Termine aus diesen Kalendern</div>
                        @if($kalender->isEmpty())
                            <p class="text-sm text-gray-500">Ihnen stehen keine Kalender zur Verfügung.</p>
                        @else
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($kalender as $k)
                                    <label class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-gray-50" style="border:1px solid #f3f4f6;">
                                        <input type="checkbox" class="bn-check" name="tagesvorschau[kalender_ids][]" value="{{ $k->id }}" @checked($kalenderAktiv($k->id))>
                                        <span class="w-3 h-3 rounded-full shrink-0" style="background: {{ $farben[$k->id] ?? $k->farbe ?? '#3b82f6' }};"></span>
                                        <span class="text-sm text-gray-800">{{ $k->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        <label class="flex items-center gap-3 mt-3">
                            <input type="hidden" name="tagesvorschau[eingeladene_termine]" value="0">
                            <span class="bn-switch">
                                <input type="checkbox" name="tagesvorschau[eingeladene_termine]" value="1" @checked((string) $tvWert('eingeladene_termine', $tv->eingeladene_termine ? '1' : '0') === '1')>
                                <span></span>
                            </span>
                            <span class="text-sm text-gray-800">Außerdem alle Termine, zu denen ich eingeladen bin</span>
                        </label>
                    </div>
                @endif
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="bn-btn bn-btn-primary">
                <i class="fas fa-save"></i> Einstellungen speichern
            </button>
        </div>
    </form>
</div>
@endsection
