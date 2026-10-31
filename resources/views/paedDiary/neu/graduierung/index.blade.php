{{--
    Graduierung/Reflexion – Übersicht (neue Ansicht, parallel zu paedDiary.documentation.index)
    Session starten (Gruppe/einzeln), offene Sessions fortsetzen/abbrechen, abgeschlossene Sessions
    innerhalb der Frist wieder öffnen. Logik: graduierungStart() in resources/js/paed-diary-neu/graduierung.js
--}}
@extends('layouts.app')

@php
    $mitSystem = $klassen->filter(fn ($k) => $k->gradingSystem);
    $details = function ($session) {
        if ($session->type === 'individual') {
            return $session->schueler ? $session->schueler->nachname . ', ' . $session->schueler->vorname : '–';
        }
        return $session->group_id ? ($session->group?->name ?? 'Lerngruppe') : 'Alle Schüler';
    };
@endphp

@section('content')
<div class="pdn" x-data="graduierungStart(@js([
        'tab' => $openSessions->isNotEmpty() ? 'offen' : 'neu',
        'gruppen' => $groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'klassen' => $g->klassen->pluck('id')->map(fn ($id) => (int) $id)->values()])->values(),
        'startGruppeUrl' => route('gradingDocumentation.startGroup'),
        'startEinzelUrl' => route('gradingDocumentation.startIndividual'),
        'schuelerUrl' => route('paedDiary.klasse.schueler', ['klasse' => '__KLASSE__']),
        'abbrechenUrl' => route('gradingDocumentation.cancelSession', ['session' => '__SESSION__']),
        'oeffnenUrl' => route('gradingDocumentation.reopenSession', ['session' => '__SESSION__']),
     ]))">

    <div class="pdn-screen">
        <div class="pdn-tb">
            <h1 class="pdn-title">Graduierung &amp; Reflexion</h1>
            <span class="pdn-spacer"></span>
            <a class="pdn-btn" href="{{ route('paedDiary.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Zurück zum Tagebuch</a>
        </div>

        <div class="pdn-main">
            <div class="pdn-tabs" role="tablist" aria-label="Bereiche">
                <button type="button" role="tab" id="pdnTabNeu" aria-controls="pdnPanelNeu" :aria-selected="tab === 'neu'" @click="tab = 'neu'">Neue Session</button>
                <button type="button" role="tab" id="pdnTabOffen" aria-controls="pdnPanelOffen" :aria-selected="tab === 'offen'" @click="tab = 'offen'">Offen <em>{{ $openSessions->count() }}</em></button>
                <button type="button" role="tab" id="pdnTabFertig" aria-controls="pdnPanelFertig" :aria-selected="tab === 'fertig'" @click="tab = 'fertig'">Abgeschlossen (Schuljahr) <em>{{ $completedSessions->count() }}</em></button>
            </div>

            <div x-show="meldung" x-cloak role="alert" class="pdn-meldung pdn-meldung--danger" style="border-radius:8px">
                <span x-text="meldung"></span>
                <button type="button" aria-label="Meldung schließen" @click="meldung = ''">&times;</button>
            </div>

            {{-- ═══════════ Neue Session ═══════════ --}}
            <section id="pdnPanelNeu" role="tabpanel" aria-labelledby="pdnTabNeu" x-show="tab === 'neu'" x-cloak>
                <div style="display:flex;flex-direction:column;gap:14px">
                @if($mitSystem->isEmpty())
                    <div class="pdn-leer pdn-gd-leer">Keiner deiner Klassen ist ein Graduierungssystem zugeordnet.</div>
                @else
                    <div class="pdn-twocol">
                        <form class="pdn-panelbox pdn-gd-form" @submit.prevent="starten('gruppe')">
                            <h3><i class="fas fa-users" aria-hidden="true"></i> Gruppe</h3>
                            <p>Alle Schüler einer Klasse oder Lerngruppe schätzen sich nacheinander selbst ein – am Gerät der Lehrkraft oder per QR-Code am eigenen Gerät.</p>
                            <label class="pdn-field">Klasse
                                <select x-model="gruppe.klasse_id" @change="klasseGruppeGewaehlt()" required>
                                    <option value="">Bitte wählen …</option>
                                    @foreach($mitSystem as $klasse)
                                        <option value="{{ $klasse->id }}">{{ $klasse->name }} ({{ $klasse->gradingSystem->name }})</option>
                                    @endforeach
                                </select>
                            </label>
                            @if($groups->isNotEmpty())
                                <label class="pdn-field">Lerngruppe (optional)
                                    <select x-model="gruppe.group_id" :disabled="!passendeGruppen.length">
                                        <option value="">Alle Schüler der Klasse</option>
                                        <template x-for="g in passendeGruppen" :key="'g' + g.id">
                                            <option :value="g.id" x-text="g.name"></option>
                                        </template>
                                    </select>
                                    <span class="pdn-label-sm" x-show="gruppe.klasse_id && !passendeGruppen.length">Keine deiner Lerngruppen enthält diese Klasse.</span>
                                </label>
                            @endif
                            <fieldset class="pdn-field" style="border:0;padding:0;margin:0">
                                <legend style="font-size:1em;margin-bottom:4px">Reihenfolge</legend>
                                <div class="pdn-gd-radios">
                                    <label class="pdn-gd-radio">
                                        <input type="radio" name="answer_order_mode" value="by_student" x-model="gruppe.answer_order_mode">
                                        <span>Schülerweise <small>Ein Schüler beantwortet alle Fragen, dann folgt der nächste.</small></span>
                                    </label>
                                    <label class="pdn-gd-radio">
                                        <input type="radio" name="answer_order_mode" value="by_question" x-model="gruppe.answer_order_mode">
                                        <span>Fragenweise <small>Alle beantworten zuerst Frage 1, dann Frage 2 usw.</small></span>
                                    </label>
                                </div>
                                <span class="pdn-label-sm">Gilt auch für die anschließende Einschätzung der Lehrkraft und lässt sich später ändern.</span>
                            </fieldset>
                            <button type="submit" class="pdn-btn pdn-btn--pri" :disabled="!gruppe.klasse_id || !!startet">
                                <i class="fas" :class="startet === 'gruppe' ? 'fa-spinner fa-spin' : 'fa-play'" aria-hidden="true"></i>
                                <span x-text="startet === 'gruppe' ? 'Wird gestartet …' : 'Gruppe starten'"></span>
                            </button>
                        </form>

                        <form class="pdn-panelbox pdn-gd-form" @submit.prevent="starten('einzel')">
                            <h3><i class="fas fa-user" aria-hidden="true"></i> Einzeln</h3>
                            <p>Ein Schüler schätzt sich zu allen Fragen ein, danach folgt deine Einschätzung.</p>
                            <label class="pdn-field">Klasse
                                <select x-model="einzel.klasse_id" @change="klasseEinzelGewaehlt()" required>
                                    <option value="">Bitte wählen …</option>
                                    @foreach($mitSystem as $klasse)
                                        <option value="{{ $klasse->id }}">{{ $klasse->name }} ({{ $klasse->gradingSystem->name }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="pdn-field">Schüler
                                <select x-model="einzel.schueler_id" :disabled="!einzel.klasse_id || schuelerLaedt" required>
                                    <option value="" x-text="schuelerLaedt ? 'Lädt …' : (einzel.klasse_id ? 'Bitte wählen …' : 'Erst Klasse wählen')"></option>
                                    <template x-for="s in schuelerListe" :key="'s' + s.id">
                                        <option :value="s.id" x-text="s.nachname + ', ' + s.vorname"></option>
                                    </template>
                                </select>
                            </label>
                            <button type="submit" class="pdn-btn pdn-btn--pri" :disabled="!einzel.schueler_id || !!startet">
                                <i class="fas" :class="startet === 'einzel' ? 'fa-spinner fa-spin' : 'fa-play'" aria-hidden="true"></i>
                                <span x-text="startet === 'einzel' ? 'Wird gestartet …' : 'Einzeln starten'"></span>
                            </button>
                        </form>
                    </div>
                    <p class="pdn-gd-hinweis"><i class="fas fa-info-circle" aria-hidden="true"></i> Nur Klassen mit Graduierungssystem stehen zur Wahl. Gibt es für die Auswahl schon eine offene Session, wird sie fortgesetzt.</p>
                @endif
                </div>
            </section>

            {{-- ═══════════ Offene Sessions ═══════════ --}}
            <section id="pdnPanelOffen" role="tabpanel" aria-labelledby="pdnTabOffen" x-show="tab === 'offen'" x-cloak>
                @if($openSessions->isEmpty())
                    <div class="pdn-leer pdn-gd-leer">Du hast keine offenen Sessions.</div>
                @else
                    <table class="pdn-table pdn-gd-tabelle">
                        <thead>
                            <tr><th scope="col">Art</th><th scope="col">Klasse</th><th scope="col">Teilnehmende</th><th scope="col">Gestartet</th><th scope="col"><span class="sr-only">Aktionen</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach($openSessions as $offen)
                                <tr>
                                    <td data-label="Art"><span class="pdn-badge">{{ $offen->type === 'group' ? 'Gruppe' : 'Einzeln' }}</span></td>
                                    <th scope="row" data-label="Klasse">{{ $offen->klasse?->name }}</th>
                                    <td data-label="Teilnehmende">
                                        {{ $details($offen) }}
                                        @if($offen->type === 'group')
                                            <div><span class="pdn-tag">{{ $offen->answer_order_mode_label }}</span></div>
                                        @endif
                                    </td>
                                    <td data-label="Gestartet">{{ $offen->started_at?->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="pdn-gd-aktionen" style="justify-content:flex-end">
                                            <a class="pdn-btn pdn-btn--pri" href="{{ $offen->type === 'group' ? route('gradingDocumentation.groupSession', $offen->id) : route('gradingDocumentation.individualSession', $offen->id) }}">
                                                <i class="fas fa-play" aria-hidden="true"></i> Fortsetzen
                                            </a>
                                            <a class="pdn-btn" href="{{ route('gradingDocumentation.teacherAssessment', $offen->id) }}">
                                                <i class="fas fa-user-check" aria-hidden="true"></i> Einschätzen
                                            </a>
                                            <button type="button" class="pdn-btn pdn-btn--ghost" style="color:var(--pdn-danger)"
                                                    @click="fragen('abbrechen', {{ $offen->id }}, @js(($offen->klasse?->name ?? '') . ' – ' . $details($offen)))">
                                                <i class="fas fa-times" aria-hidden="true"></i> Abbrechen
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            {{-- ═══════════ Abgeschlossene Sessions ═══════════ --}}
            <section id="pdnPanelFertig" role="tabpanel" aria-labelledby="pdnTabFertig" x-show="tab === 'fertig'" x-cloak>
                <div style="display:flex;flex-direction:column;gap:10px">
                <p class="pdn-gd-hinweis"><i class="fas fa-info-circle" aria-hidden="true"></i> Sessions lassen sich bis {{ $reopenDays }} Tage nach dem Abschluss wieder öffnen.</p>
                @if($completedSessions->isEmpty())
                    <div class="pdn-leer pdn-gd-leer">Im aktuellen Schuljahr hast du noch keine Session abgeschlossen.</div>
                @else
                    <table class="pdn-table pdn-gd-tabelle">
                        <thead>
                            <tr><th scope="col">Art</th><th scope="col">Klasse</th><th scope="col">Teilnehmende</th><th scope="col">Gestartet</th><th scope="col">Abgeschlossen</th><th scope="col"><span class="sr-only">Aktionen</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach($completedSessions as $fertig)
                                <tr>
                                    <td data-label="Art"><span class="pdn-badge">{{ $fertig->type === 'group' ? 'Gruppe' : 'Einzeln' }}</span></td>
                                    <th scope="row" data-label="Klasse">{{ $fertig->klasse?->name }}</th>
                                    <td data-label="Teilnehmende">
                                        {{ $details($fertig) }}
                                        @if($fertig->type === 'group')
                                            <div><span class="pdn-tag">{{ $fertig->answer_order_mode_label }}</span></div>
                                        @endif
                                    </td>
                                    <td data-label="Gestartet">{{ $fertig->started_at?->format('d.m.Y H:i') }}</td>
                                    <td data-label="Abgeschlossen">
                                        {{ $fertig->completed_at?->format('d.m.Y H:i') }}
                                        <div class="pdn-label-sm">{{ $fertig->completed_at?->diffForHumans() }}</div>
                                    </td>
                                    <td>
                                        <div class="pdn-gd-aktionen" style="justify-content:flex-end">
                                            @if($fertig->canBeReopened())
                                                <button type="button" class="pdn-btn"
                                                        @click="fragen('oeffnen', {{ $fertig->id }}, @js(($fertig->klasse?->name ?? '') . ' – ' . $details($fertig)))">
                                                    <i class="fas fa-folder-open" aria-hidden="true"></i> Wieder öffnen
                                                </button>
                                            @else
                                                <span class="pdn-tag" title="Die Frist zum Wiederöffnen ist abgelaufen"><i class="fas fa-lock" aria-hidden="true"></i> Gesperrt</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                </div>
            </section>
        </div>
    </div>

    {{-- Rückfrage (statt confirm()) --}}
    <template x-if="rueckfrage">
        <div class="pdn-dialog" role="dialog" aria-modal="true" aria-labelledby="pdnRueckfrageTitel" @keydown.escape.window="if (!arbeitet) rueckfrage = null">
            <form @submit.prevent="bestaetigen()">
                <h2 id="pdnRueckfrageTitel" style="font-size:1.1em;font-weight:800" x-text="rueckfrage.art === 'abbrechen' ? 'Session abbrechen?' : 'Session wieder öffnen?'"></h2>
                <p><b x-text="rueckfrage.text"></b></p>
                <p x-show="rueckfrage.art === 'abbrechen'">Die Session wird gelöscht – alle bisherigen Selbsteinschätzungen und Einschätzungen gehen verloren.</p>
                <p x-show="rueckfrage.art === 'oeffnen'">Die Session wird wieder bearbeitbar. Abgeschlossene Schüler werden ebenfalls wieder geöffnet.</p>
                <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap">
                    <button type="button" class="pdn-btn" @click="rueckfrage = null" :disabled="arbeitet">Zurück</button>
                    <button type="submit" class="pdn-btn pdn-btn--pri" :disabled="arbeitet"
                            :style="rueckfrage.art === 'abbrechen' ? 'background:var(--pdn-danger);border-color:var(--pdn-danger)' : ''"
                            x-text="arbeitet ? 'Bitte warten …' : (rueckfrage.art === 'abbrechen' ? 'Session abbrechen' : 'Wieder öffnen')"></button>
                </div>
            </form>
        </div>
    </template>
</div>
@endsection

@push('js')
    @vite(['resources/js/paed-diary-neu.js'])
@endpush
