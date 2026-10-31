{{--
    Pädagogisches Tagebuch – neue Schülerakte (parallel zur bisherigen Ansicht paedDiary.schueler)
    Links: Stufe, Ziel, Hinweise, Kennzahlen. Rechts: Verlauf nach Wochen, Aufgaben, Beobachtungen,
    Graduierung (Spinnennetz der Reflexion) und Diagnose. In der Schülerakte sind alle Einträge
    sichtbar, auch vertrauliche (mit Kennzeichnung).
--}}
@extends('layouts.app')

@php
    $schuljahrBeginn = \Carbon\Carbon::parse(config('config.schuljahresbeginn'))->startOfDay();
    $halbjahrWechsel = $schuljahrBeginn->copy()->addYear()->month(2)->day(1);
    $halbjahrBeginn = now()->gte($halbjahrWechsel) ? $halbjahrWechsel : $schuljahrBeginn;
    $startVon = $darstellung['schueler_zeitraum'] === '30tage' ? now()->subDays(30) : $schuljahrBeginn;
    $stufen = $klasse->gradingSystem?->stages()->orderBy('sort_order')->get() ?? collect();
    $stufeIndex = $schueler->grading_stage_id ? $stufen->search(fn ($s) => $s->id === $schueler->grading_stage_id) : false;
    $initialen = mb_substr($schueler->vorname, 0, 1) . mb_substr($schueler->nachname, 0, 1);
@endphp

@section('content')
<div class="pdn" :class="{ 'pdn--gross': darstellung.schrift === 'gross', 'pdn--farben': darstellung.kategorie_farben }"
     x-data="schuelerAkte(@js([
        'schuelerId' => $schueler->id,
        'darstellung' => $darstellung,
        'von' => $startVon->toDateString(),
        'bis' => now()->toDateString(),
        'zeitraum' => $darstellung['schueler_zeitraum'],
        'schuljahrBeginn' => $schuljahrBeginn->toDateString(),
        'halbjahrBeginn' => $halbjahrBeginn->toDateString(),
        'reflexionen' => $reflexionen,
     ]))">

    <div class="pdn-screen">
        {{-- ── Kopf ── --}}
        <div class="pdn-phead">
            <div class="pdn-avatar" aria-hidden="true">{{ $initialen }}</div>
            <div style="display:flex;flex-direction:column;gap:2px;min-width:0">
                <span class="pdn-crumbs">Pädagogisches Tagebuch › {{ $klasse->name }}</span>
                <h1>{{ $schueler->vorname }} {{ $schueler->nachname }}</h1>
            </div>
            <span class="pdn-spacer"></span>
            <div class="pdn-ansicht-aktionen" style="display:flex;flex-wrap:wrap;gap:6px">
                <a class="pdn-btn" href="{{ $zurueckUrl }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Zurück zur Woche</a>
                <a class="pdn-btn" :href="exportUrl('excel')"><i class="fas fa-file-excel" aria-hidden="true"></i><span class="pdn-btn-text">Excel-Export</span></a>
                <a class="pdn-btn pdn-btn--pri" :href="exportUrl('pdf')" target="_blank" rel="noopener"><i class="fas fa-file-pdf" aria-hidden="true"></i><span class="pdn-btn-text">Dossier (PDF)</span></a>
                <form method="post" action="{{ route('paedDiary.settings.darstellung') }}" style="margin:0">
                    @csrf
                    <input type="hidden" name="ansicht" value="bisher">
                    <button type="submit" class="pdn-btn pdn-btn--ghost" title="Zur bisherigen Ansicht wechseln">Bisherige Ansicht</button>
                </form>
            </div>
        </div>

        <div class="pdn-profile">
            {{-- ── Seitenleiste ── --}}
            <aside class="pdn-side" aria-label="Überblick">
                <div class="pdn-sblock">
                    <span class="pdn-k">Graduierung</span>
                    @if($schueler->grading_stage)
                        <div class="pdn-stagebig">
                            <span class="pdn-stage">
                                @if($schueler->grading_stage->image_url)
                                    <img src="{{ $schueler->grading_stage->image_url }}" alt="">
                                @else
                                    {{ mb_substr($schueler->grading_stage->symbol ?: $schueler->grading_stage->name, 0, 2) }}
                                @endif
                            </span>
                            <div>
                                <b>{{ $schueler->grading_stage->name }}</b>
                                <div class="pdn-label-sm">
                                    @if($currentStageSince) seit {{ \Carbon\Carbon::parse($currentStageSince)->format('d.m.Y') }} @endif
                                    @if($stufeIndex !== false) · Stufe {{ $stufeIndex + 1 }} von {{ $stufen->count() }} @endif
                                </div>
                            </div>
                        </div>
                        @if($stufeIndex !== false && $stufen->count() > 1)
                            <div class="pdn-ladder" aria-hidden="true">
                                @foreach($stufen as $i => $s)<span class="{{ $i <= $stufeIndex ? 'is-on' : '' }}"></span>@endforeach
                            </div>
                        @endif
                    @else
                        <span class="pdn-label-sm">Noch keine Stufe vergeben.</span>
                    @endif
                </div>

                <div class="pdn-sblock">
                    <span class="pdn-k">Aktuelles Ziel</span>
                    <template x-if="aktuellesZiel">
                        <div class="pdn-goal">
                            <div x-text="'„' + aktuellesZiel.goal_text + '“'"></div>
                            <div class="pdn-label-sm" style="margin-top:4px" x-text="'gesetzt am ' + (aktuellesZiel.formatted_created_at || '') + (aktuellesZiel.user ? ' · ' + aktuellesZiel.user : '')"></div>
                            <button type="button" class="pdn-btn" style="margin-top:8px" @click="zielErreicht(aktuellesZiel, true)">Als erreicht markieren</button>
                        </div>
                    </template>
                    <form @submit.prevent="neuesZiel()" style="display:flex;flex-direction:column;gap:6px">
                        <label class="pdn-field"><span x-text="aktuellesZiel ? 'Neues Ziel' : 'Ziel, an dem ich arbeiten möchte'"></span>
                            <textarea x-model="zielText" maxlength="2000" style="min-height:60px" placeholder="z. B. Ich frage nach, bevor ich anfange."></textarea>
                        </label>
                        <button type="submit" class="pdn-btn" :disabled="!zielText.trim()">Ziel speichern</button>
                    </form>
                </div>

                <template x-if="(daten?.absence_patterns || []).length">
                    <div class="pdn-sblock">
                        <span class="pdn-k">Hinweis</span>
                        <template x-for="a in daten.absence_patterns" :key="a.type">
                            <div class="pdn-warn" :title="a.details"><b x-text="a.label + ':'"></b> <span x-text="a.summary"></span></div>
                        </template>
                    </div>
                </template>

                <div class="pdn-sblock">
                    <span class="pdn-k" x-text="'Zeitraum ' + datumLang(von) + '–' + datumLang(bis)"></span>
                    <div class="pdn-kv">
                        <span>Einträge</span><b x-text="(daten?.entries || []).length"></b>
                        <span>Offene Aufgaben</span><b x-text="offeneAufgaben"></b>
                        <span>Reflexionen</span><b>{{ $gradingSessions->count() }}</b>
                        <span>Letzte Reflexion</span><b>{{ $lastGraduationAt ? $lastGraduationAt->format('d.m.Y') : '–' }}</b>
                    </div>
                </div>
            </aside>

            {{-- ── Hauptbereich ── --}}
            <div class="pdn-main">
                <div class="pdn-tabs" role="tablist" aria-label="Bereiche der Schülerakte">
                    <button type="button" role="tab" :aria-selected="tab === 'verlauf'" @click="tab = 'verlauf'">Verlauf <em x-text="eintraege.length"></em></button>
                    <button type="button" role="tab" :aria-selected="tab === 'aufgaben'" @click="tab = 'aufgaben'">Aufgaben <em x-text="(daten?.tasks || []).length"></em></button>
                    <button type="button" role="tab" :aria-selected="tab === 'beobachtungen'" @click="tab = 'beobachtungen'">Beobachtungen</button>
                    <button type="button" role="tab" :aria-selected="tab === 'graduierung'" @click="tab = 'graduierung'">Graduierung <em>{{ $gradingSessions->count() }}</em></button>
                    @if($canViewDiagnostics)
                        <button type="button" role="tab" :aria-selected="tab === 'diagnose'" @click="tab = 'diagnose'">Diagnose <em>{{ $diagnosticSessions->count() }}</em></button>
                    @endif
                </div>

                {{-- Zeitraum und Filter --}}
                <div class="pdn-range" x-show="tab !== 'graduierung' && tab !== 'diagnose'">
                    <div class="pdn-seg" role="group" aria-label="Zeitraum">
                        <button type="button" @click="setzeZeitraum('30tage')" :aria-pressed="zeitraum === '30tage'">30 Tage</button>
                        <button type="button" @click="setzeZeitraum('halbjahr')" :aria-pressed="zeitraum === 'halbjahr'">Halbjahr</button>
                        <button type="button" @click="setzeZeitraum('schuljahr')" :aria-pressed="zeitraum === 'schuljahr'">Schuljahr</button>
                    </div>
                    <label class="sr-only" for="pdnVon">Von</label>
                    <input id="pdnVon" type="date" x-model="von" @change="zeitraum = ''; ladeDaten()">
                    <label class="sr-only" for="pdnBis">Bis</label>
                    <input id="pdnBis" type="date" x-model="bis" @change="zeitraum = ''; ladeDaten()">
                    <span class="pdn-spacer"></span>
                    <template x-if="tab === 'verlauf'">
                        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                            <label class="sr-only" for="pdnKat">Kategorie</label>
                            <select id="pdnKat" x-model="kategorie">
                                <option value="">Alle Kategorien</option>
                                <option value="none">Ohne Kategorie</option>
                                <template x-for="c in (daten?.categories || [])" :key="'fc-' + c.id">
                                    <option :value="String(c.id)" x-text="c.name"></option>
                                </template>
                            </select>
                            <label class="sr-only" for="pdnSuche">Suche</label>
                            <input id="pdnSuche" type="search" x-model="suche" placeholder="Suchen …">
                            <label class="pdn-switch" style="font-size:.9em">Vertrauliche zeigen
                                <input type="checkbox" x-model="vertraulicheZeigen"><i></i>
                            </label>
                        </div>
                    </template>
                </div>

                <div x-show="fehler" x-cloak role="alert" class="pdn-meldung pdn-meldung--danger" style="border-radius:8px">
                    <span x-text="fehler"></span>
                </div>
                <div x-show="laden" x-cloak class="pdn-leer" role="status"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Wird geladen …</div>

                {{-- ── Verlauf ── --}}
                <section x-show="tab === 'verlauf' && !laden" class="pdn-timeline" aria-label="Verlauf">
                    <template x-if="daten && !verlauf.length">
                        <p class="pdn-leer">Keine Einträge im gewählten Zeitraum.</p>
                    </template>
                    <template x-for="woche in verlauf" :key="woche.kw">
                        <div class="pdn-week">
                            <span class="pdn-k" x-text="woche.titel"></span>
                            <template x-for="(p, i) in woche.punkte" :key="woche.kw + '-' + i">
                                <div class="pdn-tl" :class="{ 'pdn-tl--stufe': p.art === 'stufe', 'pdn-tl--reflexion': p.art === 'reflexion' }">
                                    <span class="pdn-d" style="white-space:pre-line"
                                          x-text="datum(p.datum) + (p.art === 'eintrag' && p.e.date_to && p.e.date_to !== p.e.date_from ? '\nbis ' + datum(p.e.date_to) : '')"></span>
                                    <div>
                                        <template x-if="p.art === 'eintrag'">
                                            <div>
                                                <div class="pdn-txt" x-text="p.e.content"></div>
                                                <div class="pdn-meta">
                                                    <span class="pdn-tag" x-show="p.e.category" x-text="p.e.category" :data-farbe="p.e.category_color ? '' : null" :style="farbe(p.e)"></span>
                                                    <span class="pdn-tag pdn-tag--conf" x-show="p.e.dossier_only" title="Nur in Schülerakte und Dossier sichtbar">Vertraulich</span>
                                                    <span x-text="p.e.user || ''"></span>
                                                    <span x-show="!p.e.completed_at">· offen</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="p.art === 'stufe'">
                                            <div>
                                                <div class="pdn-txt"><b>Stufenwechsel:</b> <span x-text="(p.h.previous_stage_name || 'keine Stufe') + ' → ' + (p.h.stage_name || 'keine Stufe')"></span></div>
                                                <div class="pdn-meta" x-text="p.h.changed_by_name || ''"></div>
                                            </div>
                                        </template>
                                        <template x-if="p.art === 'reflexion'">
                                            <div>
                                                <div class="pdn-txt"><b>Reflexion abgeschlossen</b> <span x-text="p.r.system ? '(' + p.r.system + ')' : ''"></span></div>
                                                <div class="pdn-meta">
                                                    <span x-text="p.r.type === 'group' ? 'Gruppe' : 'Einzeln'"></span>
                                                    <span x-text="p.r.teacher_name || ''"></span>
                                                    <button type="button" class="pdn-btn pdn-btn--ghost" style="min-height:24px;padding:0 6px" @click="tab = 'graduierung'">Spinnennetz ansehen</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </section>

                {{-- ── Aufgaben ── --}}
                <section x-show="tab === 'aufgaben' && !laden" x-cloak aria-label="Aufgaben" class="pdn-scroll">
                    <table class="pdn-table">
                        <thead><tr><th>Erstellt</th><th>Aufgabe</th><th>Fällig</th><th>Status</th></tr></thead>
                        <tbody>
                            <template x-for="t in (daten?.tasks || [])" :key="'t-' + t.id">
                                <tr>
                                    <td x-text="t.created_at"></td>
                                    <td><b x-text="t.title"></b><div class="pdn-label-sm" x-text="t.description || ''"></div></td>
                                    <td x-text="t.due_date || '–'"></td>
                                    <td><span class="pdn-tag" x-text="t.status === 'closed' ? 'Erledigt' : (t.highlighted ? 'Offen · wichtig' : 'Offen')"></span></td>
                                </tr>
                            </template>
                            <template x-if="daten && !(daten.tasks || []).length">
                                <tr><td colspan="4" class="pdn-leer">Keine Aufgaben im Zeitraum.</td></tr>
                            </template>
                        </tbody>
                    </table>
                </section>

                {{-- ── Beobachtungen (Zusatzspalten) ── --}}
                <section x-show="tab === 'beobachtungen' && !laden" x-cloak aria-label="Beobachtungen" class="pdn-scroll">
                    <template x-if="daten && (!(daten.columns || []).length || !spaltenTage.length)">
                        <p class="pdn-leer">Keine Beobachtungen im Zeitraum.</p>
                    </template>
                    <template x-if="daten && (daten.columns || []).length && spaltenTage.length">
                        <table class="pdn-table">
                            <thead>
                                <tr>
                                    <th>Tag</th>
                                    <template x-for="s in daten.columns" :key="'sh-' + s.id"><th x-text="s.name"></th></template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="tag in spaltenTage" :key="'st-' + tag">
                                    <tr>
                                        <td x-text="datum(tag)"></td>
                                        <template x-for="s in daten.columns" :key="'sv-' + tag + s.id"><td x-text="spaltenWert(s, tag)"></td></template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                </section>

                {{-- ── Graduierung: Spinnennetz der Reflexion ── --}}
                <section x-show="tab === 'graduierung'" x-cloak aria-label="Graduierung">
                    @if($reflexionen->isEmpty())
                        <p class="pdn-leer">Für {{ $schueler->vorname }} gibt es noch keine abgeschlossene Reflexion.</p>
                    @else
                        <div class="pdn-twocol" x-data="reflexionNetz(@js($reflexionen))">
                            <div class="pdn-panelbox">
                                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
                                    <h3>Reflexion als Spinnennetz</h3>
                                    <label class="sr-only" for="pdnReflexion">Reflexion wählen</label>
                                    <select id="pdnReflexion" class="pdn-sel" x-model.number="auswahl">
                                        <template x-for="(r, i) in reflexionen" :key="'rs-' + r.id">
                                            <option :value="i" x-text="r.formatted_date + (r.system ? ' · ' + r.system : '')"></option>
                                        </template>
                                    </select>
                                </div>

                                <svg class="pdn-netz" :viewBox="'0 0 ' + groesse + ' ' + groesse" role="img"
                                     :aria-label="'Spinnennetz der Reflexion vom ' + (aktuell?.formatted_date || '') + ': Selbsteinschätzung und Einschätzung der Lehrkraft je Frage, Skala 1 bis 5'">
                                    {{-- Ringe 1–5 --}}
                                    <template x-for="stufe in [1, 2, 3, 4, 5]" :key="'ring' + stufe">
                                        <polygon :points="ring(stufe)" fill="none" stroke="#d9dee6" :stroke-width="stufe === 5 ? 1.5 : 1"></polygon>
                                    </template>
                                    {{-- Achsen und Beschriftung --}}
                                    <template x-for="(f, i) in fragen" :key="'ax' + f.id">
                                        <g>
                                            <line :x1="achse(i).x1" :y1="achse(i).y1" :x2="achse(i).x2" :y2="achse(i).y2" stroke="#d9dee6"></line>
                                            <text :x="beschriftung(i).x" :y="beschriftung(i).y" :text-anchor="beschriftung(i).anker" dominant-baseline="middle">
                                                <title x-text="beschriftung(i).voll"></title>
                                                <tspan x-text="beschriftung(i).text"></tspan>
                                            </text>
                                        </g>
                                    </template>
                                    {{-- Selbsteinschätzung (gestrichelt) und Lehrkraft --}}
                                    <polygon x-show="hatLehrkraft" :points="polygon('teacher')" fill="rgba(21,128,61,.18)" stroke="#15803d" stroke-width="2"></polygon>
                                    <polygon x-show="hatSelbst" :points="polygon('self')" fill="rgba(29,78,216,.14)" stroke="#1d4ed8" stroke-width="2" stroke-dasharray="5 4"></polygon>
                                    <template x-for="(f, i) in fragen" :key="'pt' + f.id">
                                        <g>
                                            <circle x-show="f.teacher" :cx="punkt(i, f.teacher)[0]" :cy="punkt(i, f.teacher)[1]" r="3.5" fill="#15803d"></circle>
                                            <circle x-show="f.self" :cx="punkt(i, f.self)[0]" :cy="punkt(i, f.self)[1]" r="3.5" fill="#1d4ed8"></circle>
                                        </g>
                                    </template>
                                </svg>

                                <div class="pdn-netz-legende">
                                    <span><i style="background:#1d4ed8"></i> Selbsteinschätzung</span>
                                    <span><i style="background:#15803d"></i> Einschätzung der Lehrkraft</span>
                                    <span>Skala: 1 klappt noch nicht … 5 kann ich sicher</span>
                                </div>
                            </div>

                            <div class="pdn-panelbox">
                                <h3 x-text="'Fragen der Reflexion vom ' + (aktuell?.formatted_date || '')"></h3>
                                <p class="pdn-label-sm" x-text="(aktuell?.type === 'group' ? 'Gruppe' : 'Einzeln') + (aktuell?.teacher_name ? ' · ' + aktuell.teacher_name : '')"></p>
                                <div class="pdn-scroll">
                                    <table class="pdn-table">
                                        <thead><tr><th>Frage</th><th title="Selbsteinschätzung">Selbst</th><th title="Einschätzung der Lehrkraft">Lehrkraft</th></tr></thead>
                                        <tbody>
                                            <template x-for="f in fragen" :key="'ft' + f.id">
                                                <tr>
                                                    <td>
                                                        <span x-text="f.text"></span>
                                                        <div class="pdn-label-sm" x-show="f.comment" x-text="f.comment"></div>
                                                    </td>
                                                    <td><span class="pdn-rate pdn-rate--self" x-text="f.self || '–'"></span></td>
                                                    <td>
                                                        <span class="pdn-rate" x-text="f.teacher || '–'"></span>
                                                        <span class="pdn-diff" x-show="f.self && f.teacher && Math.abs(f.self - f.teacher) >= 2" x-text="'Δ ' + Math.abs(f.self - f.teacher)"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <template x-if="aktuell?.coaching_note">
                                    <div class="pdn-warn"><b>Coaching-Protokoll:</b> <span x-text="aktuell.coaching_note"></span></div>
                                </template>
                            </div>
                        </div>
                    @endif

                    {{-- Stufen-Historie --}}
                    <div class="pdn-panelbox" style="margin-top:16px">
                        <h3>Stufen-Historie</h3>
                        <template x-if="!(daten?.stage_history || []).length">
                            <p class="pdn-label-sm">Noch keine Stufenwechsel.</p>
                        </template>
                        <template x-if="(daten?.stage_history || []).length">
                            <div class="pdn-scroll">
                                <table class="pdn-table">
                                    <thead><tr><th>Datum</th><th>Neu</th><th>Vorher</th><th>Geändert von</th></tr></thead>
                                    <tbody>
                                        <template x-for="(h, i) in daten.stage_history" :key="'sh' + i">
                                            <tr>
                                                <td x-text="datumLang(h.at)"></td>
                                                <td x-text="h.stage_name || '–'"></td>
                                                <td x-text="h.previous_stage_name || '–'"></td>
                                                <td x-text="h.changed_by_name || '–'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                </section>

                {{-- ── Diagnose ── --}}
                @if($canViewDiagnostics)
                    <section x-show="tab === 'diagnose'" x-cloak aria-label="Diagnose" style="display:flex;flex-direction:column;gap:16px">
                        <div class="pdn-panelbox">
                            <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
                                <h3>Aktuelle Ziele aus den Diagnosen</h3>
                                <a class="pdn-btn" href="{{ route('diagnostic.areas', $schueler->id) }}">Zu den Diagnosebögen</a>
                            </div>
                            @forelse($currentDiagnosticGoals->groupBy('goal.stage.area.name') as $bereich => $ziele)
                                <div>
                                    <span class="pdn-k">{{ $bereich }}</span>
                                    <table class="pdn-table">
                                        <tbody>
                                            @foreach($ziele as $assessment)
                                                <tr>
                                                    <td style="width:90px"><span class="pdn-tag">{{ $assessment->goal->code }}</span></td>
                                                    <td>{{ $assessment->goal->description }}</td>
                                                    <td style="width:110px" class="pdn-label-sm">{{ $assessment->session?->session_date?->format('d.m.Y') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @empty
                                <p class="pdn-label-sm">Keine aktuellen Ziele erfasst.</p>
                            @endforelse
                        </div>
                        <div class="pdn-panelbox">
                            <h3>Diagnose-Sitzungen</h3>
                            @if($diagnosticSessions->isEmpty())
                                <p class="pdn-label-sm">Noch keine Diagnose-Sitzungen.</p>
                            @else
                                <div class="pdn-scroll">
                                    <table class="pdn-table">
                                        <thead><tr><th>Datum</th><th>Bereich</th><th>Status</th><th>Erstellt von</th><th></th></tr></thead>
                                        <tbody>
                                            @foreach($diagnosticSessions as $dSession)
                                                <tr>
                                                    <td>{{ $dSession->session_date?->format('d.m.Y') }}</td>
                                                    <td>{{ $dSession->area?->name }}</td>
                                                    <td>{{ $dSession->is_completed ? 'Abgeschlossen' : 'In Bearbeitung' }}</td>
                                                    <td>{{ $dSession->user?->name }}</td>
                                                    <td style="text-align:right"><a class="pdn-btn" href="{{ route('diagnostic.session', $dSession->id) }}">Ansehen</a></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
    @vite(['resources/js/paed-diary-neu.js'])
@endpush
