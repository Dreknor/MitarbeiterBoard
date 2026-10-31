{{--
    Graduierung – Einschätzung der Lehrkraft „Schüler für Schüler“ (neue Ansicht).
    Parallel zu paedDiary.documentation.teacher-assessment; die Matrix steht in paedDiary.neu.bewertung-matrix.
    Eine Schülerin/ein Schüler nach dem anderen: Fragen als Karten mit Selbsteinschätzung (gestrichelt),
    Einschätzung 1–5, Kommentar und Coaching-Protokoll. Jedes Feld speichert für sich (eigene Warteschlange).
    Logik: graduierungBewertung() in resources/js/paed-diary-neu/graduierung.js
--}}
@extends('layouts.app')

@section('content')
<div class="pdn" x-data="graduierungBewertung(@js([
        'sessionId' => $session->id,
        'abgeschlossen' => $session->isCompleted(),
        'gruppe' => $session->type === 'group',
        'modus' => $session->answer_order_mode,
        'speichernUrl' => route('gradingDocumentation.saveTeacherAssessment'),
        'notizUrl' => route('gradingDocumentation.saveCoachingNote'),
        'modusUrl' => route('gradingDocumentation.updateAnswerOrderMode', $session->id),
        'abschliessenUrl' => route('gradingDocumentation.completeSession', $session->id),
        'zurueckUrl' => route('gradingDocumentation.index'),
        'fragen' => $questions->map(fn ($q) => ['id' => $q->id, 'text' => $q->question])->values(),
        'schueler' => $daten,
     ]))">

    <div class="pdn-screen pdn-gd-screen">
        <div class="pdn-tb">
            <h1 class="pdn-title">Einschätzung der Lehrkraft</h1>
            <span class="pdn-badge">{{ $session->klasse?->name }}</span>
            <span class="pdn-badge">{{ $session->gradingSystem?->name }}</span>
            <span class="pdn-spacer"></span>
            <form method="post" action="{{ route('paedDiary.settings.darstellung') }}" style="margin:0">
                @csrf
                <input type="hidden" name="bewertung" value="matrix">
                <div class="pdn-seg" role="group" aria-label="Bewertungsmodus">
                    <button type="button" aria-pressed="true">Schüler für Schüler</button>
                    <button type="submit" aria-pressed="false">Matrix</button>
                </div>
            </form>
            <a class="pdn-btn pdn-btn--ghost" href="{{ $session->type === 'group' ? route('gradingDocumentation.groupSession', $session->id) : route('gradingDocumentation.individualSession', $session->id) }}">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Zurück
            </a>
        </div>

        <div class="pdn-tb">
            <b x-text="vollstaendig + ' von ' + schueler.length + ' Schülern vollständig'"></b>
            <span class="pdn-gd-bar" aria-hidden="true"><span :style="'width:' + (schueler.length ? Math.round(vollstaendig / schueler.length * 100) : 0) + '%'"></span></span>
            <span class="pdn-label-sm">Nach dem Abschluss kannst du die Session {{ $reopenDays }} Tage lang wieder öffnen.</span>
            <button type="button" class="pdn-btn pdn-btn--pri" @click="abschlussFragen = true" :disabled="abgeschlossen">Abschließen …</button>
        </div>

        <div class="pdn-tb">
            @if($session->type === 'group')
                <span class="pdn-label-sm">Reihenfolge</span>
                <div class="pdn-seg" role="group" aria-label="Reihenfolge der Einschätzung">
                    <button type="button" :aria-pressed="!fragenweise" :disabled="modusWechselt || abgeschlossen" @click="modusSetzen('by_student')">Schülerweise</button>
                    <button type="button" :aria-pressed="fragenweise" :disabled="modusWechselt || abgeschlossen" @click="modusSetzen('by_question')">Fragenweise</button>
                </div>
            @endif
            <span class="pdn-spacer"></span>
            <label class="pdn-switch" style="gap:8px">
                <span x-text="fragenweise ? 'Nach der Wahl zum nächsten Schüler' : 'Nach der Wahl zur nächsten Frage'"></span>
                <input type="checkbox" x-model="automatisch"><i aria-hidden="true"></i>
            </label>
        </div>

        <div x-show="meldung" x-cloak role="alert" class="pdn-meldung pdn-meldung--danger">
            <span x-text="meldung"></span>
            <button type="button" aria-label="Meldung schließen" @click="meldung = ''">&times;</button>
        </div>
        <div x-show="abgeschlossen" class="pdn-meldung pdn-meldung--info">Diese Session ist abgeschlossen. Änderungen sind erst nach dem Wiederöffnen möglich.</div>

        @if($schueler->isEmpty() || $questions->isEmpty())
            <div class="pdn-leer">{{ $schueler->isEmpty() ? 'In dieser Session gibt es keine Schüler.' : 'Das Graduierungssystem hat keine aktiven Fragen.' }}</div>
        @else
        <div class="pdn-gd-layout">
            {{-- Schülerliste (ab Tablet) --}}
            <nav class="pdn-gd-seite" aria-label="Schüler">
                <span class="pdn-k" style="padding:0 8px 4px">Schüler</span>
                <template x-for="(s, i) in schueler" :key="'n' + s.id">
                    <button type="button" class="pdn-gd-chip" :aria-current="i === sIndex ? 'true' : false" @click="zuSchueler(i)">
                        <span x-text="s.name"></span>
                        <i class="fas fa-exclamation-circle" x-show="schuelerStatus(s) === 'fehler'" aria-label="nicht gespeichert"></i>
                        <i class="fas fa-lock" x-show="s.abgeschlossen" aria-label="abgeschlossen"></i>
                        <i class="fas fa-check-circle" x-show="!s.abgeschlossen && istVollstaendig(s)" aria-label="vollständig"></i>
                        <small x-show="!s.abgeschlossen && !istVollstaendig(s)" x-text="anzahlBewertet(s) + '/' + fragen.length"></small>
                    </button>
                </template>
            </nav>

            <div class="pdn-gd-body" x-show="aktuell">
                {{-- Schülerwahl (Handy, schmale Fenster) --}}
                <div class="pdn-gd-auswahl" style="gap:6px;align-items:flex-end">
                    <label class="pdn-field" style="flex:1;min-width:0">Schüler
                        <select @change="zuSchueler(Number($event.target.value))">
                            <template x-for="(s, i) in schueler" :key="'o' + s.id">
                                <option :value="i" :selected="i === sIndex"
                                        x-text="s.name + (s.abgeschlossen ? ' (abgeschlossen)' : (istVollstaendig(s) ? ' ✓' : ' – ' + anzahlBewertet(s) + '/' + fragen.length))"></option>
                            </template>
                        </select>
                    </label>
                </div>

                <div class="pdn-gd-kopf" x-ref="kopf">
                    <h2 x-text="aktuell?.name"></h2>
                    <span class="pdn-tag" x-show="aktuell?.abgeschlossen"><i class="fas fa-lock" aria-hidden="true"></i> abgeschlossen</span>
                    <span class="pdn-badge" x-show="aktuell && !aktuell.abgeschlossen"
                          x-text="aktuell && istVollstaendig(aktuell) ? 'Vollständig' : (aktuell ? anzahlBewertet(aktuell) + ' von ' + fragen.length + ' bewertet' : '')"></span>
                    <span class="pdn-spacer"></span>
                    <template x-if="aktuell && schuelerStatus(aktuell) === 'fehler'">
                        <span class="pdn-gd-fehler">Nicht gespeichert · <button type="button" class="pdn-gd-link" @click="erneut(aktuell)">Erneut versuchen</button></span>
                    </template>
                    <span class="pdn-label-sm" x-show="aktuell && schuelerStatus(aktuell) === 'speichert'">Speichert …</span>
                    <span class="pdn-gd-ok" x-show="aktuell && schuelerStatus(aktuell) === 'ok'">Gespeichert</span>
                </div>

                {{-- Fragenweise: Fragenleiste und aktuelle Frage --}}
                <template x-if="fragenweise">
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <div class="pdn-gd-fnav" role="group" aria-label="Frage wählen">
                            <template x-for="(f, i) in fragen" :key="'q' + f.id">
                                <button type="button" :class="{ 'is-fertig': frageFertig(f) }" :aria-pressed="i === fIndex"
                                        :title="f.text" :aria-label="'Frage ' + (i + 1) + ': ' + f.text" @click="zuFrage(i)" x-text="i + 1"></button>
                            </template>
                        </div>
                        <span class="pdn-label-sm" x-text="'Frage ' + (fIndex + 1) + ' von ' + fragen.length + ' · ' + (aktuelleFrage ? bewertetZuFrage(aktuelleFrage) : 0) + ' von ' + schueler.length + ' Schülern bewertet'"></span>
                    </div>
                </template>

                {{-- Fragen als Karten --}}
                <template x-for="f in sichtbareFragen" :key="'k' + aktuell?.id + '-' + f.id">
                    <section class="pdn-gd-karte" :id="'pdnFrage-' + f.id" :class="{ 'is-bewertet': aktuell.lehrkraft[f.id] }" :aria-labelledby="'pdnFrageText-' + f.id">
                        <div class="pdn-gd-karte__kopf">
                            <span class="pdn-gd-karte__nr" aria-hidden="true" x-text="fragen.indexOf(f) + 1"></span>
                            <h3 class="pdn-gd-karte__frage" :id="'pdnFrageText-' + f.id" x-text="f.text"></h3>
                            <span class="pdn-label-sm" x-show="status[aktuell.id + '-' + f.id] === 'speichert'">Speichert …</span>
                            <span class="pdn-gd-fehler" style="font-size:.85em" x-show="status[aktuell.id + '-' + f.id] === 'fehler'">Nicht gespeichert</span>
                        </div>
                        <div class="pdn-gd-karte__zeile">
                            <span class="pdn-label-sm">Selbsteinschätzung</span>
                            <span class="pdn-rate pdn-rate--self" :title="aktuell.selbst[f.id] ? 'Selbsteinschätzung' : 'Keine Selbsteinschätzung'"
                                  x-text="aktuell.selbst[f.id] || '–'"></span>
                            <span class="pdn-diff" x-show="differenz(aktuell, f) >= 2" x-text="'Δ ' + differenz(aktuell, f)"></span>
                        </div>
                        <div class="pdn-gd-karte__zeile">
                            <span class="pdn-label-sm">Deine Einschätzung</span>
                            <div class="pdn-wahl" role="group" :aria-label="'Einschätzung für ' + aktuell.name + ': ' + f.text">
                                <template x-for="wert in [1, 2, 3, 4, 5]" :key="'w' + wert">
                                    <button type="button" :aria-pressed="aktuell.lehrkraft[f.id] === wert" :aria-label="wert + ' von 5'"
                                            :disabled="istGesperrt(aktuell)" @click="bewerten(aktuell, f, wert)" x-text="wert"></button>
                                </template>
                            </div>
                        </div>
                        <label class="pdn-field">Kommentar (optional)
                            <textarea rows="2" :value="aktuell.kommentare[f.id] || ''" :disabled="istGesperrt(aktuell)"
                                      @change="kommentieren(aktuell, f, $event.target.value)"></textarea>
                        </label>
                    </section>
                </template>

                {{-- Coaching-Protokoll --}}
                <template x-for="s in (aktuell ? [aktuell] : [])" :key="'notiz' + s.id">
                    <div class="pdn-panelbox pdn-gd-notiz">
                        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                            <label :for="'pdnNotiz-' + s.id" style="margin:0"><b><i class="fas fa-clipboard" aria-hidden="true"></i> Coaching-Protokoll</b></label>
                            <span class="pdn-label-sm" x-show="status[s.id + '-notiz'] === 'speichert'">Speichert …</span>
                            <span class="pdn-gd-ok" style="font-size:.85em" x-show="status[s.id + '-notiz'] === 'ok'">Gespeichert</span>
                            <span class="pdn-gd-fehler" style="font-size:.85em" x-show="status[s.id + '-notiz'] === 'fehler'">Nicht gespeichert</span>
                        </div>
                        <textarea class="pdn-gd-textarea" :id="'pdnNotiz-' + s.id" rows="3" maxlength="5000" :value="s.notiz"
                                  :disabled="istGesperrt(s)" placeholder="Kurze Notiz zum Coaching-Gespräch (optional)"
                                  @change="notieren(s, $event.target.value)"></textarea>
                    </div>
                </template>

                {{-- Person vollständig: weiter zur nächsten (statt confirm()) --}}
                <div class="pdn-meldung pdn-meldung--info" style="border-radius:8px;border:0;align-items:center"
                     x-show="!fragenweise && aktuell && !istGesperrt(aktuell) && istVollstaendig(aktuell)" x-cloak>
                    <span><i class="fas fa-check-circle" aria-hidden="true"></i> Alle Fragen für <b x-text="aktuell?.name"></b> sind bewertet.</span>
                    <button type="button" class="pdn-btn pdn-btn--pri" style="font-size:1em" x-show="unvollstaendig.length" @click="weiter()">Nächster Schüler <i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                    <button type="button" class="pdn-btn pdn-btn--pri" style="font-size:1em" x-show="!unvollstaendig.length" @click="abschlussFragen = true">Abschließen …</button>
                </div>
                <p class="pdn-gd-hinweis" x-show="hinweis" x-text="hinweis" role="status"></p>
            </div>
        </div>

        <div class="pdn-gd-weiter">
            <button type="button" class="pdn-btn" @click="zuSchueler(sIndex - 1)" :disabled="sIndex === 0">
                <i class="fas fa-chevron-left" aria-hidden="true"></i> <span class="pdn-btn-text">Vorheriger Schüler</span>
            </button>
            <span class="pdn-label-sm" x-text="'Schüler ' + (sIndex + 1) + ' von ' + schueler.length"></span>
            <button type="button" class="pdn-btn pdn-btn--pri" @click="weiter()">
                <span x-text="fragenweise ? 'Weiter' : 'Nächster offener Schüler'"></span> <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>
        @endif
    </div>

    {{-- Abschluss bestätigen (statt confirm()) --}}
    <template x-if="abschlussFragen">
        <div class="pdn-dialog" role="dialog" aria-modal="true" aria-labelledby="pdnAbschlussTitel" @keydown.escape.window="if (!schliesst) abschlussFragen = false">
            <form @submit.prevent="abschliessen()">
                <h2 id="pdnAbschlussTitel" style="font-size:1.1em;font-weight:800">Session abschließen?</h2>
                <template x-if="!irgendeineBewertung">
                    <p class="pdn-gd-fehler">Bitte bewerte mindestens eine Frage, bevor du die Session abschließt.</p>
                </template>
                <template x-if="irgendeineBewertung && unvollstaendig.length">
                    <div>
                        <p>Für diese Schüler fehlen noch Einschätzungen:</p>
                        <ul style="margin:6px 0 0;padding-left:1.2em;max-height:40vh;overflow-y:auto"><template x-for="n in unvollstaendig" :key="n"><li x-text="n"></li></template></ul>
                    </div>
                </template>
                <p class="pdn-label-sm">Du kannst die Session {{ $reopenDays }} Tage lang wieder öffnen, um Einschätzungen zu ergänzen.</p>
                <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap">
                    <button type="button" class="pdn-btn" @click="abschlussFragen = false" :disabled="schliesst">Abbrechen</button>
                    <button type="submit" class="pdn-btn pdn-btn--pri" :disabled="schliesst || !irgendeineBewertung"
                            x-text="schliesst ? 'Wird abgeschlossen …' : (unvollstaendig.length ? 'Trotzdem abschließen' : 'Abschließen')"></button>
                </div>
            </form>
        </div>
    </template>
</div>
@endsection

@push('js')
    @vite(['resources/js/paed-diary-neu.js'])
@endpush
