{{--
    Graduierung – abgeschlossene Reflexionen eines Schülers (neue Ansicht, parallel zu
    paedDiary.documentation.schueler-overview). Je Session: Coaching-Protokoll und je Frage
    Selbsteinschätzung (gestrichelt), Einschätzung der Lehrkraft, Abweichung und Kommentar.
--}}
@extends('layouts.app')

@section('content')
<div class="pdn">
    <div class="pdn-screen">
        <div class="pdn-tb">
            <h1 class="pdn-title">Reflexionen · {{ $schueler->vorname }} {{ $schueler->nachname }}</h1>
            <span class="pdn-badge">{{ $klasse->name }}</span>
            <span class="pdn-spacer"></span>
            <a class="pdn-btn" href="{{ route('paedDiary.schueler.view', $schueler->id) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Zurück zur Schülerakte</a>
        </div>

        <div class="pdn-gd-body">
            @if($sessions->isEmpty())
                <div class="pdn-leer pdn-gd-leer">Für {{ $schueler->vorname }} liegen noch keine abgeschlossenen Reflexionen vor.</div>
            @else
                <p class="pdn-gd-hinweis">
                    <span class="pdn-rate pdn-rate--self">3</span> Selbsteinschätzung ·
                    <span class="pdn-rate">3</span> Einschätzung der Lehrkraft (1 = klappt noch nicht … 5 = kann ich sicher) ·
                    <span class="pdn-diff">Δ</span> Abweichung ab 2 Punkten
                </p>
                @foreach($sessions as $session)
                    @php
                        $selbst = $session->studentAnswers->where('schueler_id', $schueler->id)->pluck('self_rating', 'question_id');
                        $bewertungen = $session->teacherAssessments->where('schueler_id', $schueler->id)->keyBy('question_id');
                        $notiz = $session->coachingNotes->firstWhere('schueler_id', $schueler->id)?->note;
                        $fragen = $session->gradingSystem?->questions ?? collect();
                    @endphp
                    <details class="pdn-panelbox pdn-gd-session" @if($loop->first) open @endif>
                        <summary>
                            <span>{{ $session->completed_at->format('d.m.Y') }}</span>
                            <span class="pdn-badge">{{ $session->gradingSystem?->name }}</span>
                            <span class="pdn-tag">{{ $session->isGroupSession() ? 'Gruppe' : 'Einzeln' }}</span>
                            <span class="pdn-label-sm" style="font-weight:400">{{ $session->user?->name }}</span>
                        </summary>

                        @if($notiz)
                            <div>
                                <span class="pdn-k">Coaching-Protokoll</span>
                                <div class="pdn-gd-coaching">{{ $notiz }}</div>
                            </div>
                        @endif

                        @if($fragen->isEmpty())
                            <p class="pdn-gd-hinweis">Das Graduierungssystem enthält keine Fragen mehr.</p>
                        @else
                            <table class="pdn-table pdn-gd-tabelle">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width:45%">Frage</th>
                                        <th scope="col">Selbst</th>
                                        <th scope="col">Lehrkraft</th>
                                        <th scope="col">Kommentar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($fragen as $frage)
                                        @php
                                            $s = $selbst[$frage->id] ?? null;
                                            $l = $bewertungen[$frage->id]->teacher_rating ?? null;
                                            $kommentar = $bewertungen[$frage->id]->comment ?? null;
                                        @endphp
                                        <tr>
                                            <th scope="row" style="font-weight:400">{{ $frage->question }}</th>
                                            <td data-label="Selbst"><span class="pdn-rate pdn-rate--self" title="Selbsteinschätzung">{{ $s ?: '–' }}</span></td>
                                            <td data-label="Lehrkraft">
                                                <span class="pdn-rate" title="Einschätzung der Lehrkraft">{{ $l ?: '–' }}</span>
                                                @if($s && $l && abs($s - $l) >= 2)
                                                    <span class="pdn-diff">Δ {{ abs($s - $l) }}</span>
                                                @endif
                                            </td>
                                            <td data-label="Kommentar" style="overflow-wrap:anywhere">{{ $kommentar ?: '–' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </details>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection

@push('js')
    @vite(['resources/js/paed-diary-neu.js'])
@endpush
