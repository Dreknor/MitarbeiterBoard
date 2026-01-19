@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Dokumentationen - {{ $schueler->vorname }} {{ $schueler->nachname }}</h5>
                        <small class="text-muted">Klasse: {{ $klasse->name }}</small>
                    </div>
                    <a href="{{ route('paedDiary.schueler.view', $schueler->id) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                </div>
                <div class="card-body">
                    @if($sessions->isEmpty())
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Für diesen Schüler liegen noch keine Dokumentationen vor.
                        </div>
                    @else
                        <div class="accordion" id="documentationAccordion">
                            @foreach($sessions as $session)
                                <div class="card mb-2">
                                    <div class="card-header" id="heading{{ $session->id }}">
                                        <h6 class="mb-0">
                                            <button class="btn btn-link" type="button" data-toggle="collapse"
                                                    data-target="#collapse{{ $session->id }}" aria-expanded="false">
                                                <i class="fas fa-calendar-alt"></i>
                                                {{ $session->completed_at->format('d.m.Y H:i') }} Uhr
                                                <span class="badge badge-info ml-2">{{ $session->gradingSystem->name }}</span>
                                                <span class="badge badge-secondary ml-1">
                                                    {{ $session->isGroupSession() ? 'Gruppe' : 'Einzeln' }}
                                                </span>
                                            </button>
                                        </h6>
                                    </div>
                                    <div id="collapse{{ $session->id }}" class="collapse"
                                         data-parent="#documentationAccordion">
                                        <div class="card-body">
                                            <p class="text-muted mb-3">
                                                <strong>Lehrer:</strong> {{ $session->user->name }}
                                            </p>

                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 50%;">Frage</th>
                                                            <th class="text-center">Selbsteinschätzung</th>
                                                            <th class="text-center">Lehrereinschätzung</th>
                                                            <th>Kommentar</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($session->gradingSystem->questions()->orderBy('sort_order')->get() as $question)
                                                            @php
                                                                $studentAnswer = $session->studentAnswers->where('schueler_id', $schueler->id)
                                                                                                         ->where('question_id', $question->id)
                                                                                                         ->first();
                                                                $teacherAssessment = $session->teacherAssessments->where('schueler_id', $schueler->id)
                                                                                                                  ->where('question_id', $question->id)
                                                                                                                  ->first();
                                                            @endphp
                                                            <tr>
                                                                <td>{{ $question->question }}</td>
                                                                <td class="text-center">
                                                                    @if($studentAnswer)
                                                                        @php
                                                                            $rating = $studentAnswer->self_rating;
                                                                            $icons = [
                                                                                1 => 'fas fa-frown text-danger',
                                                                                2 => 'fas fa-frown-open text-warning',
                                                                                3 => 'fas fa-meh text-secondary',
                                                                                4 => 'fas fa-smile text-info',
                                                                                5 => 'fas fa-grin-stars text-success'
                                                                            ];
                                                                            $labels = [
                                                                                1 => 'Sehr schlecht',
                                                                                2 => 'Schlecht',
                                                                                3 => 'Mittel',
                                                                                4 => 'Gut',
                                                                                5 => 'Sehr gut'
                                                                            ];
                                                                        @endphp
                                                                        <i class="{{ $icons[$rating] }}" style="font-size: 1.5rem;"
                                                                           title="{{ $labels[$rating] }}"></i>
                                                                    @else
                                                                        <span class="text-muted">-</span>
                                                                    @endif
                                                                </td>
                                                                <td class="text-center">
                                                                    @if($teacherAssessment && $teacherAssessment->teacher_rating)
                                                                        @php
                                                                            $rating = $teacherAssessment->teacher_rating;
                                                                            $icons = [
                                                                                1 => 'fas fa-frown text-danger',
                                                                                2 => 'fas fa-frown-open text-warning',
                                                                                3 => 'fas fa-meh text-secondary',
                                                                                4 => 'fas fa-smile text-info',
                                                                                5 => 'fas fa-grin-stars text-success'
                                                                            ];
                                                                            $labels = [
                                                                                1 => 'Sehr schlecht',
                                                                                2 => 'Schlecht',
                                                                                3 => 'Mittel',
                                                                                4 => 'Gut',
                                                                                5 => 'Sehr gut'
                                                                            ];
                                                                        @endphp
                                                                        <i class="{{ $icons[$rating] }}" style="font-size: 1.5rem;"
                                                                           title="{{ $labels[$rating] }}"></i>
                                                                    @else
                                                                        <span class="text-muted">-</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if($teacherAssessment && $teacherAssessment->comment)
                                                                        <small>{{ $teacherAssessment->comment }}</small>
                                                                    @else
                                                                        <span class="text-muted">-</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

