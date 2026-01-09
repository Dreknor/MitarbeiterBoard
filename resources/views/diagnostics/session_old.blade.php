@extends('layouts.app')

@section('content')
<div class="container-fluid" x-data="diagnosticSession">
    <div class="row">
        <div class="col-12">
            {{-- Header --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <a href="{{ route('diagnostic.areas', $session->schueler_id) }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück
                    </a>
                    <h4 class="d-inline ml-2">
                        <i class="fas fa-clipboard-check"></i>
                        {{ $session->schueler->name }} - {{ $session->area->name }}
                    </h4>
                </div>
                <div>
                    <small class="text-muted mr-3">
                        <i class="fas fa-calendar"></i> {{ $session->session_date->format('d.m.Y') }}
                    </small>
                    @if($session->is_completed)
                        <span class="badge badge-success">Abgeschlossen</span>
                    @else
                        <span class="badge badge-warning">In Bearbeitung</span>
                    @endif
                </div>
            </div>

            {{-- Bereichsziel --}}
            @if($session->area->description)
                <div class="card mb-3 border-primary">
                    <div class="card-body bg-light">
                        <h6 class="mb-1"><i class="fas fa-bullseye"></i> Bereichsziel:</h6>
                        <p class="mb-0">{{ $session->area->description }}</p>
                    </div>
                </div>
            @endif

            {{-- Auto-Save Indicator --}}
            <div class="fixed-top" style="top: 70px; right: 20px; z-index: 1000;">
                <div x-show="saving" x-transition class="alert alert-info alert-sm mb-0 shadow-sm" style="width: auto; display: none;">
                    <i class="fas fa-spinner fa-spin"></i> Speichert...
                </div>
                <div x-show="saved" x-transition class="alert alert-success alert-sm mb-0 shadow-sm" style="width: auto; display: none;">
                    <i class="fas fa-check"></i> Gespeichert
                </div>
                <div x-show="error" x-transition class="alert alert-danger alert-sm mb-0 shadow-sm" style="width: auto; display: none;">
                    <i class="fas fa-exclamation-triangle"></i> Fehler beim Speichern
                </div>
            </div>

            {{-- Stufen-Tabs --}}
            <ul class="nav nav-tabs mb-3" role="tablist">
                @foreach($session->area->stages as $index => $stage)
                    <li class="nav-item">
                        <a class="nav-link {{ $index === 0 ? 'active' : '' }}"
                           id="stage-{{ $stage->id }}-tab"
                           data-toggle="tab"
                           href="#stage-{{ $stage->id }}"
                           role="tab">
                            {{ $stage->name }}
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Stufen-Content --}}
            <div class="tab-content">
                @foreach($session->area->stages as $index => $stage)
                    <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                         id="stage-{{ $stage->id }}"
                         role="tabpanel">

                        {{-- Stufenziel --}}
                        @if($stage->goal_description)
                            <div class="card mb-3 border-info">
                                <div class="card-body bg-light">
                                    <h6 class="mb-1"><i class="fas fa-flag"></i> Stufenziel:</h6>
                                    <p class="mb-0">{{ $stage->goal_description }}</p>
                                </div>
                            </div>
                        @endif

                        {{-- Notizen zur Stufe --}}
                        <div class="card mb-3">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="fas fa-sticky-note"></i> Notizen zu {{ $stage->name }}</h6>
                            </div>
                            <div class="card-body">
                                <textarea
                                    class="form-control"
                                    rows="3"
                                    placeholder="Notizen zu dieser Stufe..."
                                    x-model="stageNotes[{{ $stage->id }}]"
                                    @input.debounce.500ms="saveStageNote({{ $stage->id }})"
                                    {{ $session->is_completed ? 'disabled' : '' }}
                                >{{ $session->stageNotes->where('diagnostic_stage_id', $stage->id)->first()->notes ?? '' }}</textarea>
                            </div>
                        </div>

                        {{-- Ziele --}}
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-list"></i> Ziele ({{ $stage->goals->count() }})</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 80px;">Code</th>
                                                <th>Beschreibung</th>
                                                <th style="width: 120px;" class="text-center">Historie</th>
                                                <th style="width: 280px;" class="text-center">Bewertung</th>
                                                <th style="width: 120px;" class="text-center">Aktuell</th>
                                                <th style="width: 80px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($stage->goals as $goal)
                                                @php
                                                    $assessment = $session->assessments->where('diagnostic_goal_id', $goal->id)->first();
                                                    $currentRating = $assessment->rating ?? null;
                                                    $isCurrentGoal = $assessment->is_current_goal ?? false;
                                                    $history = $historicalData[$goal->id] ?? [];
                                                    $goalComments = $comments[$goal->id] ?? collect();
                                                @endphp
                                                <tr>
                                                    {{-- Code --}}
                                                    <td class="align-middle">
                                                        <strong>{{ $goal->code }}</strong>
                                                    </td>

                                                    {{-- Beschreibung --}}
                                                    <td class="align-middle">
                                                        {{ $goal->description }}
                                                        @if($goalComments->count() > 0)
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-comment"></i> {{ $goalComments->count() }} Kommentar(e)
                                                            </small>
                                                        @endif
                                                    </td>

                                                    {{-- Historie --}}
                                                    <td class="align-middle text-center">
                                                        <div class="d-flex justify-content-center align-items-center">
                                                            @forelse($history as $h)
                                                                <span class="history-circle mr-1"
                                                                      style="background-color: {{ $h['color'] }};"
                                                                      title="{{ $h['date'] }}: {{ $h['rating_text'] }}"
                                                                      data-toggle="tooltip">
                                                                </span>
                                                            @empty
                                                                <small class="text-muted">-</small>
                                                            @endforelse
                                                        </div>
                                                    </td>

                                                    {{-- Bewertung --}}
                                                    <td class="align-middle text-center">
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button"
                                                                    class="btn rating-btn {{ $currentRating === 'white' ? 'active' : '' }}"
                                                                    style="background-color: white; border: 1px solid #ccc;"
                                                                    @click="saveAssessment({{ $goal->id }}, 'white')"
                                                                    {{ $session->is_completed ? 'disabled' : '' }}>
                                                                <i class="fas fa-check"></i> Kann es
                                                            </button>
                                                            <button type="button"
                                                                    class="btn rating-btn {{ $currentRating === 'gray' ? 'active' : '' }}"
                                                                    style="background-color: #ccc; border: 1px solid #999;"
                                                                    @click="saveAssessment({{ $goal->id }}, 'gray')"
                                                                    {{ $session->is_completed ? 'disabled' : '' }}>
                                                                <i class="fas fa-crosshairs"></i> Aktuell
                                                            </button>
                                                            <button type="button"
                                                                    class="btn rating-btn {{ $currentRating === 'dark_gray' ? 'active' : '' }}"
                                                                    style="background-color: #666; color: white; border: 1px solid #444;"
                                                                    @click="saveAssessment({{ $goal->id }}, 'dark_gray')"
                                                                    {{ $session->is_completed ? 'disabled' : '' }}>
                                                                <i class="fas fa-times"></i> Kann es nicht
                                                            </button>
                                                        </div>
                                                    </td>

                                                    {{-- Aktuelles Ziel Checkbox --}}
                                                    <td class="align-middle text-center">
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox"
                                                                   class="custom-control-input"
                                                                   id="current-{{ $goal->id }}"
                                                                   :checked="currentGoals[{{ $goal->id }}]"
                                                                   @click="toggleCurrentGoal({{ $goal->id }}, {{ $assessment->id ?? 0 }}, $event)"
                                                                   :disabled="!ratings[{{ $goal->id }}] || ratings[{{ $goal->id }}] !== 'gray' || {{ $session->is_completed ? 'true' : 'false' }}"
                                                                   {{ (!$assessment || $currentRating !== 'gray' || $session->is_completed) ? 'disabled' : '' }}>
                                                            <label class="custom-control-label" for="current-{{ $goal->id }}"></label>
                                                        </div>
                                                    </td>

                                                    {{-- Kommentar Button --}}
                                                    <td class="align-middle text-center">
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-secondary"
                                                                @click="openCommentModal({{ $goal->id }}, '{{ $goal->code }}', '{{ addslashes($goal->description) }}')"
                                                                title="Kommentar">
                                                            <i class="fas fa-comment"></i>
                                                            @if($goalComments->count() > 0)
                                                                <span class="badge badge-primary badge-sm">{{ $goalComments->count() }}</span>
                                                            @endif
                                                        </button>
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

            {{-- Aktions-Buttons --}}
            <div class="card mt-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <a href="{{ route('diagnostic.areas', $session->schueler_id) }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                            </a>
                        </div>
                        <div>
                            @if($session->is_completed)
                                @can('manage diagnostics')
                                    <form action="{{ route('diagnostic.reopen', $session->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('Session wirklich wieder öffnen?')">
                                            <i class="fas fa-unlock"></i> Session wieder öffnen
                                        </button>
                                    </form>
                                @endcan
                            @else
                                <a href="{{ route('diagnostic.export-session-pdf', $session->id) }}" class="btn btn-info" target="_blank">
                                    <i class="fas fa-file-pdf"></i> PDF Export
                                </a>
                                        <i class="fas fa-check-circle"></i> Session abschließen
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Kommentar Modal --}}
    <div class="modal fade" id="commentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-comment"></i> Kommentare zu <span x-text="selectedGoalCode"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted" x-text="selectedGoalDescription"></p>

                    {{-- Bestehende Kommentare --}}
                    <div class="mb-3" x-show="goalComments[selectedGoalId] && goalComments[selectedGoalId].length > 0">
                        <h6>Bisherige Kommentare:</h6>
                        <template x-for="comment in goalComments[selectedGoalId]" :key="comment.id">
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <span x-text="comment.user_name"></span>
                                            - <span x-text="comment.created_at"></span>
                                        </small>
                                        <button type="button" class="btn btn-sm btn-danger" @click="deleteComment(comment.id)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <p class="mb-0 mt-2" x-text="comment.comment"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Neuer Kommentar --}}
                    <div>
                        <h6>Neuer Kommentar:</h6>
                        <textarea class="form-control" rows="4" x-model="newComment" placeholder="Kommentar eingeben..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                    <button type="button" class="btn btn-primary" @click="saveComment()">
                        <i class="fas fa-save"></i> Speichern
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .history-circle {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: inline-block;
        border: 1px solid #999;
    }

    .rating-btn {
        min-width: 90px;
    }

    .rating-btn.active {
        box-shadow: 0 0 0 3px rgba(0,123,255,.25);
        font-weight: bold;
    }

    .nav-tabs .nav-link {
        font-weight: 500;
    }

    .nav-tabs .nav-link.active {
        font-weight: bold;
        background-color: #f8f9fa;
    }

    /* Tablet-Optimierung */
    @media (max-width: 768px) {
        .rating-btn {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .table {
            font-size: 0.9rem;
        }
    }
</style>
@endpush

@push('js')
<script>
console.log('=== Diagnostic Session Script wird geladen ===');
console.log('Axios verfügbar:', typeof axios !== 'undefined');

document.addEventListener('alpine:init', () => {
    console.log('=== Alpine:init Event empfangen ===');
    console.log('Alpine.js wird initialisiert...');
    Alpine.data('diagnosticSession', () => ({
        saving: false,
        saved: false,
        error: false,
        ratings: @json($session->assessments ? $session->assessments->pluck('rating', 'diagnostic_goal_id') : []),
        stageNotes: @json($session->stageNotes ? $session->stageNotes->pluck('notes', 'diagnostic_stage_id') : []),
        currentGoals: @json($session->assessments ? $session->assessments->pluck('is_current_goal', 'diagnostic_goal_id')->map(fn($val) => (bool)$val) : []),
        assessmentIds: @json($session->assessments ? $session->assessments->pluck('id', 'diagnostic_goal_id') : []),
        goalComments: @json($formattedComments ?? []),
        selectedGoalId: null,
        selectedGoalCode: '',
        selectedGoalDescription: '',
        newComment: '',

        init() {
            console.log('Diagnostic Session initialisiert');
            console.log('Ratings:', this.ratings);
            console.log('Current Goals:', this.currentGoals);
            console.log('Assessment IDs:', this.assessmentIds);

            // Tooltips initialisieren
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        },

        async saveAssessment(goalId, rating) {
            console.log('saveAssessment aufgerufen:', { goalId, rating });

            this.saving = true;
            this.saved = false;
            this.error = false;

            try {
                const response = await axios.post('{{ route('diagnostic.assess', $session->id) }}', {
                    goal_id: goalId,
                    rating: rating
                });

                console.log('Assessment Response:', response.data);

                // Update local state
                this.ratings[goalId] = rating;
                if (response.data.assessment && response.data.assessment.id) {
                    this.assessmentIds[goalId] = response.data.assessment.id;
                    console.log('Assessment-ID gespeichert:', this.assessmentIds[goalId]);
                }

                // Wenn das Rating nicht mehr 'gray' ist, deaktiviere "Aktuelles Ziel"
                if (rating !== 'gray') {
                    this.currentGoals[goalId] = false;
                    console.log('Aktuelles Ziel deaktiviert, da Rating nicht gray:', rating);
                }

                console.log('Aktualisierte States:', {
                    ratings: this.ratings,
                    assessmentIds: this.assessmentIds,
                    currentGoals: this.currentGoals
                });

                this.saved = true;
                setTimeout(() => this.saved = false, 2000);
            } catch (error) {
                console.error('Fehler beim Speichern:', error);
                console.error('Error Details:', error.response?.data);
                this.error = true;
                setTimeout(() => this.error = false, 3000);
            } finally {
                this.saving = false;
            }
        },

        async saveStageNote(stageId) {
            this.saving = true;
            this.saved = false;
            this.error = false;

            try {
                const response = await axios.post(`/diagnostics/session/{{ $session->id }}/stage/${stageId}/note`, {
                    notes: this.stageNotes[stageId]
                });

                this.saved = true;
                setTimeout(() => this.saved = false, 2000);
            } catch (error) {
                console.error('Fehler beim Speichern:', error);
                this.error = true;
                setTimeout(() => this.error = false, 3000);
            } finally {
                this.saving = false;
            }
        },

        async toggleCurrentGoal(goalId, assessmentId, event) {
            // Verhindere Standard-Checkbox-Verhalten
            if (event) {
                event.preventDefault();
            }

            console.log('toggleCurrentGoal aufgerufen:', { goalId, assessmentId });
            console.log('Aktueller Status von currentGoals[' + goalId + ']:', this.currentGoals[goalId]);

            // Speichere den vorherigen Zustand für Rollback
            const previousState = this.currentGoals[goalId];
            const newState = !previousState;

            // Wenn kein Assessment existiert, beende
            if (!assessmentId || assessmentId === 0) {
                // Versuche die Assessment-ID aus dem assessmentIds Array zu holen
                assessmentId = this.assessmentIds[goalId];
                console.log('Assessment-ID aus Array geholt:', assessmentId);

                if (!assessmentId) {
                    console.error('Keine Assessment-ID gefunden für Ziel:', goalId);
                    this.error = true;
                    setTimeout(() => this.error = false, 3000);
                    return;
                }
            }

            this.saving = true;
            this.saved = false;
            this.error = false;

            try {
                console.log('Sende Request an:', `/diagnostics/assessment/${assessmentId}/toggle-current`);
                const response = await axios.post(`/diagnostics/assessment/${assessmentId}/toggle-current`);
                console.log('Response erhalten:', response.data);

                if (response.data.success) {
                    // Update lokalen Status mit dem Server-Wert (nicht togglen!)
                    this.currentGoals[goalId] = response.data.is_current_goal;
                    console.log('Neuer Status vom Server gesetzt:', this.currentGoals[goalId]);
                    console.log('Alle currentGoals nach Update:', {...this.currentGoals});

                    this.saved = true;
                    setTimeout(() => this.saved = false, 2000);
                } else {
                    console.error('Server gab Fehler zurück');
                    this.error = true;
                    setTimeout(() => this.error = false, 3000);
                }
            } catch (error) {
                console.error('Fehler beim Umschalten:', error);
                console.error('Error Details:', error.response?.data);
                this.error = true;
                setTimeout(() => this.error = false, 3000);
            } finally {
                this.saving = false;
            }
        },

        openCommentModal(goalId, goalCode, goalDescription) {
            this.selectedGoalId = goalId;
            this.selectedGoalCode = goalCode;
            this.selectedGoalDescription = goalDescription;
            this.newComment = '';

            const modalElement = document.getElementById('commentModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        },

        async saveComment() {
            if (!this.newComment.trim()) {
                return;
            }

            try {
                const response = await axios.post(`/diagnostics/goal/${this.selectedGoalId}/schueler/{{ $session->schueler_id }}/comment`, {
                    comment: this.newComment
                });

                // Kommentar zur Liste hinzufügen
                if (!this.goalComments[this.selectedGoalId]) {
                    this.goalComments[this.selectedGoalId] = [];
                }

                this.goalComments[this.selectedGoalId].push({
                    id: response.data.comment.id,
                    comment: response.data.comment.comment,
                    user_name: '{{ Auth::user()->name }}',
                    created_at: new Date().toLocaleDateString('de-DE')
                });

                this.newComment = '';
                alert('Kommentar gespeichert');
            } catch (error) {
                console.error('Fehler beim Speichern des Kommentars:', error);
                alert('Fehler beim Speichern');
            }
        },

        async deleteComment(commentId) {
            if (!confirm('Kommentar wirklich löschen?')) {
                return;
            }

            try {
                await axios.delete(`/diagnostics/comment/${commentId}`);

                // Kommentar aus Liste entfernen
                for (let goalId in this.goalComments) {
                    this.goalComments[goalId] = this.goalComments[goalId].filter(c => c.id !== commentId);
                }

                alert('Kommentar gelöscht');
            } catch (error) {
                console.error('Fehler beim Löschen:', error);
                alert('Fehler beim Löschen');
            }
        }
    }));
});
</script>
@endpush
@endsection

