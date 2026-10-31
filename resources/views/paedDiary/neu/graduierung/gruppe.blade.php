{{--
    Graduierung – Gruppensession (neue Ansicht, parallel zu paedDiary.documentation.group-session)
    Die Schüler schätzen sich nacheinander selbst ein (schülerweise oder fragenweise), am Gerät der
    Lehrkraft oder per QR-Code/Link am eigenen Gerät. Logik: graduierungGruppe() in
    resources/js/paed-diary-neu/graduierung.js
--}}
@extends('layouts.app')

@section('content')
<div class="pdn" x-data="graduierungGruppe(@js([
        'sessionId' => $session->id,
        'abgeschlossen' => $session->isCompleted(),
        'modus' => $session->answer_order_mode,
        'speichernUrl' => route('gradingDocumentation.saveStudentAnswer'),
        'modusUrl' => route('gradingDocumentation.updateAnswerOrderMode', $session->id),
        'qrUrl' => route('gradingDocumentation.generateQRToken', ['session' => $session->id, 'schueler' => '__SCHUELER__']),
        'fragen' => $questions->map(fn ($q) => ['id' => $q->id, 'text' => $q->question])->values(),
        'schueler' => $schueler->map(fn ($s) => ['id' => $s->id, 'vorname' => $s->vorname, 'nachname' => $s->nachname])->values(),
        'antworten' => $session->studentAnswers
            ->whereIn('schueler_id', $schueler->pluck('id'))
            ->mapWithKeys(fn ($a) => [$a->schueler_id . '_' . $a->question_id => (int) $a->self_rating]),
        'finalisiert' => $finalisiert,
     ]))">

    <div class="pdn-screen">
        <div class="pdn-tb">
            <h1 class="pdn-title">Selbsteinschätzung · Gruppe</h1>
            <span class="pdn-badge">{{ $session->klasse?->name }}</span>
            <span class="pdn-badge">{{ $session->gradingSystem?->name }}</span>
            <span class="pdn-spacer"></span>
            <a class="pdn-btn" href="{{ route('gradingDocumentation.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Übersicht</a>
            <a class="pdn-btn pdn-btn--pri" href="{{ route('gradingDocumentation.teacherAssessment', $session->id) }}">
                <i class="fas fa-user-check" aria-hidden="true"></i> Zur Einschätzung der Lehrkraft
            </a>
        </div>

        <div class="pdn-tb">
            <b x-text="beantwortet + ' von ' + gesamt + ' Antworten'"></b>
            <span class="pdn-gd-bar" aria-hidden="true"><span :style="'width:' + (gesamt ? Math.round(beantwortet / gesamt * 100) : 0) + '%'"></span></span>
            <div class="pdn-seg" role="group" aria-label="Reihenfolge der Antworten">
                <button type="button" :aria-pressed="!fragenweise" :disabled="modusWechselt || abgeschlossen" @click="modusSetzen('by_student')">Schülerweise</button>
                <button type="button" :aria-pressed="fragenweise" :disabled="modusWechselt || abgeschlossen" @click="modusSetzen('by_question')">Fragenweise</button>
            </div>
        </div>

        <div x-show="meldung" x-cloak role="alert" class="pdn-meldung pdn-meldung--danger">
            <span x-text="meldung"></span>
            <button type="button" aria-label="Meldung schließen" @click="meldung = ''">&times;</button>
        </div>
        @if($session->isCompleted())
            <div class="pdn-meldung pdn-meldung--info">Diese Session ist abgeschlossen. Änderungen sind erst nach dem Wiederöffnen in der Übersicht möglich.</div>
        @else
            <div class="pdn-meldung pdn-meldung--info" x-show="fortgesetzt" x-cloak>
                <span><i class="fas fa-history" aria-hidden="true"></i> Session fortgesetzt: <b x-text="fortgesetzt"></b> gespeicherte Antworten geladen. Jede Antwort wird sofort gespeichert.</span>
                <button type="button" aria-label="Hinweis schließen" @click="fortgesetzt = 0">&times;</button>
            </div>
        @endif

        <div class="pdn-gd-body">
            @if($schueler->isEmpty() || $questions->isEmpty())
                <div class="pdn-leer pdn-gd-leer">
                    {{ $schueler->isEmpty() ? 'In dieser Session gibt es keine Schüler.' : 'Das Graduierungssystem hat keine aktiven Fragen.' }}
                </div>
            @else
                {{-- Aktuelle Frage --}}
                <section class="pdn-gd-buehne" x-ref="karte" aria-live="polite" x-show="!allesBeantwortet && aktuellerSchueler">
                    <div class="pdn-gd-buehne__kopf">
                        <span class="pdn-gd-name" x-text="aktuellerSchueler ? aktuellerSchueler.vorname + ' ' + aktuellerSchueler.nachname : ''"></span>
                        <span class="pdn-label-sm" x-text="'Frage ' + (fIndex + 1) + ' von ' + fragen.length"></span>
                    </div>
                    <div class="pdn-gd-punkte" aria-hidden="true">
                        <template x-for="(f, i) in fragen" :key="'p' + f.id">
                            <i :class="{ 'is-fertig': antwort(aktuellerSchueler, f) !== undefined, 'is-aktuell': i === fIndex }"></i>
                        </template>
                    </div>
                    <p class="pdn-gd-frage" x-text="aktuelleFrage?.text"></p>
                    <template x-if="aktuellerSchueler && istFinalisiert(aktuellerSchueler)">
                        <p class="pdn-label-sm">Die Einschätzung dieses Schülers ist bereits abgeschlossen.</p>
                    </template>
                    <div class="pdn-skala" role="group" :aria-label="'Selbsteinschätzung: ' + (aktuelleFrage?.text || '')">
                        <template x-for="stufe in skala" :key="'w' + stufe.wert">
                            <button type="button" @click="waehlen(stufe.wert)"
                                    :aria-pressed="antwort(aktuellerSchueler, aktuelleFrage) === stufe.wert"
                                    :disabled="speichert || (aktuellerSchueler && istFinalisiert(aktuellerSchueler))">
                                <span class="pdn-skala__lvl" aria-hidden="true">
                                    <template x-for="n in 5" :key="n"><i :class="{ 'is-an': n <= stufe.wert }"></i></template>
                                </span>
                                <span x-text="stufe.text"></span>
                            </button>
                        </template>
                    </div>
                    <div class="pdn-gd-aktionen">
                        <span class="pdn-label-sm" x-show="speichert"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Speichert …</span>
                        <button type="button" class="pdn-btn" @click="weiter()" :disabled="speichert">
                            <i class="fas fa-forward" aria-hidden="true"></i> <span x-text="fragenweise ? 'Ohne Antwort weiter' : 'Überspringen'"></span>
                        </button>
                        <span class="pdn-label-sm" x-show="hinweis" x-text="hinweis"></span>
                    </div>
                </section>

                <div class="pdn-panelbox" x-show="allesBeantwortet && !abgeschlossen" x-cloak style="align-items:flex-start">
                    <h3><i class="fas fa-check-circle" style="color:var(--pdn-ok)" aria-hidden="true"></i> Alle Selbsteinschätzungen sind erfasst</h3>
                    <p>Jetzt kannst du deine eigene Einschätzung abgeben.</p>
                    <a class="pdn-btn pdn-btn--pri" href="{{ route('gradingDocumentation.teacherAssessment', $session->id) }}">
                        <i class="fas fa-user-check" aria-hidden="true"></i> Zur Einschätzung der Lehrkraft
                    </a>
                </div>

                {{-- Übersicht aller Schüler --}}
                <section aria-labelledby="pdnSchuelerTitel" style="display:flex;flex-direction:column;gap:8px">
                    <h2 id="pdnSchuelerTitel" class="pdn-k">Schüler</h2>
                    <table class="pdn-table pdn-gd-tabelle pdn-gd-liste">
                        <thead>
                            <tr><th scope="col">Name</th><th scope="col">Fortschritt</th><th scope="col"><span class="sr-only">Aktionen</span></th></tr>
                        </thead>
                        <tbody>
                            <template x-for="(s, i) in schueler" :key="'z' + s.id">
                                <tr :class="{ 'is-aktuell': i === sIndex && !allesBeantwortet }">
                                    <th scope="row" x-text="s.nachname + ', ' + s.vorname"></th>
                                    <td data-label="Fortschritt">
                                        <span x-text="anzahlBeantwortet(s) + ' / ' + fragen.length"></span>
                                        <i class="fas fa-check-circle pdn-gd-ok" x-show="anzahlBeantwortet(s) === fragen.length" aria-label="vollständig"></i>
                                        <span class="pdn-tag" x-show="istFinalisiert(s)"><i class="fas fa-lock" aria-hidden="true"></i> abgeschlossen</span>
                                    </td>
                                    <td>
                                        <div class="pdn-gd-aktionen" style="justify-content:flex-end">
                                            <button type="button" class="pdn-btn" @click="zuSchueler(i)" :aria-pressed="i === sIndex" :disabled="istFinalisiert(s)">
                                                <i class="fas fa-arrow-right" aria-hidden="true"></i> Jetzt dran
                                            </button>
                                            <button type="button" class="pdn-btn" @click="qrZeigen(s)" :disabled="abgeschlossen || istFinalisiert(s)"
                                                    :aria-label="'QR-Code und Link für ' + s.vorname + ' ' + s.nachname">
                                                <i class="fas fa-qrcode" aria-hidden="true"></i> <span class="pdn-btn-text">QR-Code</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </section>
            @endif
        </div>
    </div>

    {{-- QR-Code und Link für das eigene Gerät --}}
    <template x-if="qr">
        <div class="pdn-dialog" role="dialog" aria-modal="true" aria-labelledby="pdnQrTitel" @keydown.escape.window="qr = null">
            <form @submit.prevent="qr = null" style="align-items:center;text-align:center">
                <h2 id="pdnQrTitel" style="font-size:1.1em;font-weight:800" x-text="'QR-Code für ' + qr.name"></h2>
                <p x-show="qr.laedt" class="pdn-label-sm"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Wird erzeugt …</p>
                <div x-ref="qrBild" aria-label="QR-Code" role="img" style="min-height:0"></div>
                <p class="pdn-label-sm">Mit dem QR-Code oder dem Link beantwortet der Schüler die Fragen am eigenen Gerät, ohne Anmeldung. Der Zugang gilt 24 Stunden.</p>
                <label class="pdn-field" style="width:100%;text-align:left" x-show="qr.url">Link
                    <input type="text" readonly x-ref="qrLink" :value="qr.url" x-on:focus="$event.target.select()">
                </label>
                <p x-show="qr.fehler" class="pdn-gd-fehler" role="alert" x-text="qr.fehler"></p>
                <p x-show="qr.kopiert" class="pdn-gd-ok" role="status">Link kopiert.</p>
                <div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap">
                    <button type="button" class="pdn-btn" @click="kopieren()" :disabled="!qr.url"><i class="fas fa-copy" aria-hidden="true"></i> Link kopieren</button>
                    <button type="submit" class="pdn-btn pdn-btn--pri">Schließen</button>
                </div>
            </form>
        </div>
    </template>
</div>
@endsection

@push('js')
    <script src="{{ asset('js/qrcode.min.js') }}"></script>
    @vite(['resources/js/paed-diary-neu.js'])
@endpush
