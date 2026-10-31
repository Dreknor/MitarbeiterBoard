{{--
    Graduierung – Einzelsession (neue Ansicht, parallel zu paedDiary.documentation.individual-session)
    Ein Schüler schätzt sich zu allen Fragen selbst ein; jede Antwort speichert für sich.
    Logik: graduierungEinzel() in resources/js/paed-diary-neu/graduierung.js
--}}
@extends('layouts.app')

@section('content')
<div class="pdn" x-data="graduierungEinzel(@js([
        'sessionId' => $session->id,
        'schuelerId' => $schueler?->id,
        'abgeschlossen' => $session->isCompleted(),
        'speichernUrl' => route('gradingDocumentation.saveStudentAnswer'),
        'fragen' => $questions->map(fn ($q) => ['id' => $q->id, 'text' => $q->question])->values(),
        'antworten' => $session->studentAnswers
            ->where('schueler_id', $schueler?->id)
            ->mapWithKeys(fn ($a) => [$a->question_id => (int) $a->self_rating]),
     ]))">

    <div class="pdn-screen">
        <div class="pdn-tb">
            <h1 class="pdn-title">Selbsteinschätzung · {{ $schueler ? $schueler->vorname . ' ' . $schueler->nachname : 'Einzeln' }}</h1>
            <span class="pdn-badge">{{ $session->klasse?->name }}</span>
            <span class="pdn-badge">{{ $session->gradingSystem?->name }}</span>
            <span class="pdn-spacer"></span>
            <a class="pdn-btn" href="{{ route('gradingDocumentation.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Übersicht</a>
            <a class="pdn-btn pdn-btn--pri" href="{{ route('gradingDocumentation.teacherAssessment', $session->id) }}">
                <i class="fas fa-user-check" aria-hidden="true"></i> Zur Einschätzung der Lehrkraft
            </a>
        </div>

        <div class="pdn-tb">
            <b x-text="beantwortet + ' von ' + fragen.length + ' Fragen beantwortet'"></b>
            <span class="pdn-gd-bar" aria-hidden="true"><span :style="'width:' + (fragen.length ? Math.round(beantwortet / fragen.length * 100) : 0) + '%'"></span></span>
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
            @if($questions->isEmpty())
                <div class="pdn-leer pdn-gd-leer">Das Graduierungssystem hat keine aktiven Fragen.</div>
            @else
                <template x-for="(f, i) in fragen" :key="'f' + f.id">
                    <section class="pdn-gd-karte" :class="{ 'is-bewertet': antworten[f.id] }" :aria-labelledby="'pdnFrage' + f.id">
                        <div class="pdn-gd-karte__kopf">
                            <span class="pdn-gd-karte__nr" x-text="i + 1" aria-hidden="true"></span>
                            <h2 class="pdn-gd-karte__frage" :id="'pdnFrage' + f.id" x-text="f.text"></h2>
                            <span class="pdn-label-sm" x-show="status[f.id] === 'speichert'">Speichert …</span>
                            <span class="pdn-gd-ok" x-show="status[f.id] === 'ok'" style="font-size:.85em">Gespeichert</span>
                            <span class="pdn-gd-fehler" x-show="status[f.id] === 'fehler'" style="font-size:.85em">Nicht gespeichert</span>
                        </div>
                        <div class="pdn-skala pdn-skala--quer" role="group" :aria-label="'Selbsteinschätzung: ' + f.text">
                            <template x-for="stufe in skala" :key="'w' + f.id + '-' + stufe.wert">
                                <button type="button" @click="waehlen(f, stufe.wert)" :aria-pressed="antworten[f.id] === stufe.wert" :disabled="abgeschlossen">
                                    <span class="pdn-skala__lvl" aria-hidden="true">
                                        <template x-for="n in 5" :key="n"><i :class="{ 'is-an': n <= stufe.wert }"></i></template>
                                    </span>
                                    <span x-text="stufe.text"></span>
                                </button>
                            </template>
                        </div>
                    </section>
                </template>

                <div class="pdn-panelbox" x-show="fertig && !abgeschlossen" x-cloak style="align-items:flex-start">
                    <h3><i class="fas fa-check-circle" style="color:var(--pdn-ok)" aria-hidden="true"></i> Alle Fragen sind beantwortet</h3>
                    <p>Jetzt kannst du deine eigene Einschätzung abgeben.</p>
                    <div class="pdn-gd-aktionen">
                        <a class="pdn-btn pdn-btn--pri" href="{{ route('gradingDocumentation.teacherAssessment', $session->id) }}">
                            <i class="fas fa-user-check" aria-hidden="true"></i> Zur Einschätzung der Lehrkraft
                        </a>
                        <a class="pdn-btn" href="{{ route('gradingDocumentation.index') }}">Zur Übersicht</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('js')
    @vite(['resources/js/paed-diary-neu.js'])
@endpush
