{{--
    Graduierung – Einschätzung der Lehrkraft als Matrix (neue Ansicht, Bewertungsmodus „Matrix“).
    Eine Zeile je Schüler, eine Spalte je Frage; die Selbsteinschätzung steht gestrichelt davor.
    Jede Zelle speichert für sich (eigene Warteschlange, sichtbarer Status). Gleiche Endpunkte wie
    die Ansicht „Schüler für Schüler“ (gradingDocumentation.saveTeacherAssessment / completeSession).
--}}
@extends('layouts.app')

@section('content')
<div class="pdn" x-data="bewertungsMatrix(@js([
        'sessionId' => $session->id,
        'abgeschlossen' => $session->isCompleted(),
        'speichernUrl' => route('gradingDocumentation.saveTeacherAssessment'),
        'abschliessenUrl' => route('gradingDocumentation.completeSession', $session->id),
        'zurueckUrl' => route('gradingDocumentation.index'),
        'fragen' => $questions->map(fn ($q) => ['id' => $q->id, 'text' => $q->question])->values(),
        'schueler' => $matrix,
     ]))">

    <div class="pdn-screen">
        <div class="pdn-tb">
            <h1 class="pdn-title">Einschätzung der Lehrkraft</h1>
            <span class="pdn-badge">{{ $session->klasse?->name }}</span>
            <span class="pdn-badge">{{ $session->gradingSystem?->name }}</span>
            <span class="pdn-spacer"></span>
            <form method="post" action="{{ route('paedDiary.settings.darstellung') }}" style="margin:0">
                @csrf
                <input type="hidden" name="bewertung" value="schuelerweise">
                <div class="pdn-seg" role="group" aria-label="Bewertungsmodus">
                    <button type="submit" aria-pressed="false">Schüler für Schüler</button>
                    <button type="button" aria-pressed="true">Matrix</button>
                </div>
            </form>
            <a class="pdn-btn pdn-btn--ghost" href="{{ $session->type === 'group' ? route('gradingDocumentation.groupSession', $session->id) : route('gradingDocumentation.individualSession', $session->id) }}">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Zurück
            </a>
        </div>

        <div class="pdn-tb">
            <b x-text="vollstaendig + ' von ' + schueler.length + ' Schülern vollständig'"></b>
            <span style="flex:1;min-width:120px;height:8px;background:var(--pdn-surface-2);border-radius:999px;overflow:hidden">
                <span style="display:block;height:100%;background:var(--pdn-accent)" :style="'width:' + (schueler.length ? Math.round(vollstaendig / schueler.length * 100) : 0) + '%'"></span>
            </span>
            <span class="pdn-label-sm">Nach dem Abschluss kannst du die Session {{ $reopenDays }} Tage lang wieder öffnen.</span>
            <button type="button" class="pdn-btn pdn-btn--pri" @click="abschlussFragen = true" :disabled="abgeschlossen">Abschließen …</button>
        </div>

        <div x-show="meldung" x-cloak role="alert" class="pdn-meldung pdn-meldung--danger">
            <span x-text="meldung"></span>
            <button type="button" aria-label="Meldung schließen" @click="meldung = ''">&times;</button>
        </div>
        <div x-show="abgeschlossen" class="pdn-meldung pdn-meldung--info">Diese Session ist abgeschlossen. Änderungen sind erst nach dem Wiederöffnen möglich.</div>

        <div class="pdn-scroll">
            <table class="pdn-table" style="min-width:720px">
                <thead>
                    <tr>
                        <th scope="col">Schüler</th>
                        <template x-for="(f, i) in fragen" :key="'f' + f.id">
                            <th scope="col" :title="f.text"><span x-text="'F' + (i + 1)"></span><div class="pdn-label-sm" style="font-weight:400" x-text="f.text"></div></th>
                        </template>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="s in schueler" :key="'s' + s.id">
                        <tr>
                            <th scope="row" x-text="s.name"></th>
                            <template x-for="f in fragen" :key="'c' + s.id + '-' + f.id">
                                <td>
                                    <span class="pdn-rate pdn-rate--self" title="Selbsteinschätzung" x-text="s.selbst[f.id] || '–'"></span>
                                    <span class="pdn-seg" role="group" :aria-label="'Einschätzung für ' + s.name + ': ' + f.text" style="margin-left:6px">
                                        <template x-for="wert in [1, 2, 3, 4, 5]" :key="'w' + wert">
                                            <button type="button" :aria-pressed="s.lehrkraft[f.id] === wert"
                                                    :disabled="abgeschlossen || s.abgeschlossen"
                                                    @click="bewerten(s, f, wert)" x-text="wert"
                                                    style="min-width:30px;padding:4px 6px"></button>
                                        </template>
                                    </span>
                                    <span class="pdn-diff" x-show="s.selbst[f.id] && s.lehrkraft[f.id] && Math.abs(s.selbst[f.id] - s.lehrkraft[f.id]) >= 2"
                                          x-text="'Δ ' + Math.abs(s.selbst[f.id] - s.lehrkraft[f.id])"></span>
                                </td>
                            </template>
                            <td>
                                <span class="pdn-label-sm" x-show="s.abgeschlossen">Abgeschlossen</span>
                                <span x-show="!s.abgeschlossen && zeilenStatus(s) === 'fehler'" style="color:var(--pdn-danger);font-weight:700">
                                    Nicht gespeichert · <button type="button" class="pdn-btn pdn-btn--ghost" style="min-height:24px;padding:0 4px" @click="erneut(s)">Erneut versuchen</button>
                                </span>
                                <span x-show="!s.abgeschlossen && zeilenStatus(s) === 'speichert'" class="pdn-label-sm">Speichert …</span>
                                <span x-show="!s.abgeschlossen && zeilenStatus(s) === 'ok'" style="color:var(--pdn-ok);font-weight:700" x-text="istVollstaendig(s) ? 'Vollständig' : 'Gespeichert'"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Abschluss bestätigen (statt confirm()) --}}
    <template x-if="abschlussFragen">
        <div class="pdn-dialog" role="dialog" aria-modal="true" aria-labelledby="pdnAbschlussTitel" @keydown.escape.window="abschlussFragen = false">
            <form @submit.prevent="abschliessen()">
                <h2 id="pdnAbschlussTitel" style="font-size:1.1em;font-weight:800">Session abschließen?</h2>
                <template x-if="unvollstaendig.length">
                    <div>
                        <p>Für diese Schüler fehlen noch Einschätzungen:</p>
                        <ul style="margin:6px 0 0;padding-left:1.2em"><template x-for="n in unvollstaendig" :key="n"><li x-text="n"></li></template></ul>
                    </div>
                </template>
                <p class="pdn-label-sm">Du kannst die Session {{ $reopenDays }} Tage lang wieder öffnen, um Einschätzungen zu ergänzen.</p>
                <div style="display:flex;justify-content:flex-end;gap:8px">
                    <button type="button" class="pdn-btn" @click="abschlussFragen = false">Abbrechen</button>
                    <button type="submit" class="pdn-btn pdn-btn--pri" x-text="unvollstaendig.length ? 'Trotzdem abschließen' : 'Abschließen'"></button>
                </div>
            </form>
        </div>
    </template>
</div>
@endsection

@push('js')
    @vite(['resources/js/paed-diary-neu.js'])
@endpush
