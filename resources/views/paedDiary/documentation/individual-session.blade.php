@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Einzeldokumentation - {{ $schueler->vorname }} {{ $schueler->nachname }}</h5>
                    <small class="text-muted">{{ $session->gradingSystem->name }} | Klasse: {{ $session->klasse->name }}</small>
                </div>
                <div class="card-body" id="individualApp">
                    <!-- Navigation zwischen Schüler- und Lehrereinschätzung -->
                    <div class="alert alert-info d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <i class="fas fa-info-circle"></i>
                            <strong>Flexibler Wechsel:</strong> Sie können jederzeit zwischen Schülereinschätzung und Lehrereinschätzung wechseln.
                        </div>
                        <a id="teacherAssessmentLinkTop" href="#" class="btn btn-success">
                            <i class="fas fa-user-tie"></i> Zur Lehrereinschätzung wechseln
                        </a>
                    </div>

                    <!-- Fortschrittsanzeige -->
                    <div class="progress mb-4" style="height: 30px;">
                        <div class="progress-bar bg-success" role="progressbar" id="progressBar"
                             aria-valuemin="0" aria-valuemax="100" style="width: 0%">
                            <span id="progressText">0 / 0</span>
                        </div>
                    </div>

                    <!-- Fragen-Liste -->
                    <div id="questionsList">
                        <!-- Fragen werden dynamisch generiert -->
                    </div>

                    <!-- Alle Fragen beantwortet -->
                    <div id="completedAlert" class="alert alert-success" style="display: none;">
                        <h4><i class="fas fa-check-circle"></i> Alle Fragen wurden beantwortet!</h4>
                        <p class="mb-3">Der Lehrer kann nun seine Einschätzung abgeben.</p>
                        <a id="teacherAssessmentLink" href="#" class="btn btn-success">
                            <i class="fas fa-user-tie"></i> Zur Lehrereinschätzung
                        </a>
                        <a href="{{ route('gradingDocumentation.index') }}" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // Daten vom Server
    const sessionId = {{ $session->id }};
    const schuelerId = {{ $schueler->id }};
    const questions = @json($questions);
    const answersData = @json($session->studentAnswers->keyBy('question_id')->map(function($answer) { return $answer->self_rating; }));

    console.log('Session ID:', sessionId);
    console.log('Schüler ID:', schuelerId);
    console.log('Fragen:', questions);
    console.log('Antworten:', answersData);

    // State
    let answers = answersData || {};
    let loading = false;

    // DOM Elemente
    const elements = {
        progressBar: document.getElementById('progressBar'),
        progressText: document.getElementById('progressText'),
        questionsList: document.getElementById('questionsList'),
        completedAlert: document.getElementById('completedAlert'),
        teacherAssessmentLink: document.getElementById('teacherAssessmentLink'),
        teacherAssessmentLinkTop: document.getElementById('teacherAssessmentLinkTop')
    };

    // Computed Properties als Funktionen
    function getCompletedCount() {
        return Object.keys(answers).length;
    }

    function getTotalCount() {
        return questions.length;
    }

    function getProgressPercent() {
        const total = getTotalCount();
        return total > 0 ? ((getCompletedCount() / total) * 100).toFixed(0) : 0;
    }

    function getProgressText() {
        return `${getCompletedCount()} / ${getTotalCount()}`;
    }

    function isAllCompleted() {
        return getCompletedCount() >= getTotalCount();
    }

    function getTeacherAssessmentUrl() {
        return `/paed-diary/documentation/session/${sessionId}/teacher-assessment`;
    }

    // Hilfsfunktionen
    function getSmileyIcon(rating) {
        const icons = {
            1: 'fas fa-frown text-danger',
            2: 'fas fa-frown-open text-warning',
            3: 'fas fa-meh text-secondary',
            4: 'fas fa-smile text-info',
            5: 'fas fa-grin-stars text-success'
        };
        return icons[rating] || 'fas fa-meh';
    }

    function getSmileyLabel(rating) {
        const labels = {
            1: 'Sehr schlecht',
            2: 'Schlecht',
            3: 'Mittel',
            4: 'Gut',
            5: 'Sehr gut'
        };
        return labels[rating] || '';
    }

    // Hauptfunktionen
    async function saveAnswer(questionId, rating) {
        if (loading) return;

        loading = true;

        try {
            const response = await fetch('/paed-diary/documentation/student-answer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    schueler_id: schuelerId,
                    question_id: questionId,
                    self_rating: rating
                })
            });

            if (response.ok) {
                answers[questionId] = rating;
                render();
            } else {
                alert('Fehler beim Speichern der Antwort.');
            }
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Speichern der Antwort.');
        } finally {
            loading = false;
        }
    }

    // Render-Funktionen
    function renderProgress() {
        if (!elements.progressBar || !elements.progressText) return;
        const percent = getProgressPercent();
        elements.progressBar.style.width = percent + '%';
        elements.progressBar.setAttribute('aria-valuenow', percent);
        elements.progressText.textContent = getProgressText();
    }

    function renderQuestions() {
        if (!elements.questionsList) return;
        elements.questionsList.innerHTML = '';

        if (isAllCompleted()) {
            elements.questionsList.style.display = 'none';
            return;
        } else {
            elements.questionsList.style.display = 'block';
        }

        questions.forEach((question, index) => {
            const card = document.createElement('div');
            card.className = 'card mb-3';

            // Card Header
            const cardHeader = document.createElement('div');
            cardHeader.className = 'card-header';
            if (answers[question.id]) {
                cardHeader.classList.add('bg-success', 'text-white');
            }

            const h6 = document.createElement('h6');
            h6.className = 'mb-0';
            h6.textContent = `Frage ${index + 1} von ${questions.length}`;

            if (answers[question.id]) {
                const checkIcon = document.createElement('i');
                checkIcon.className = 'fas fa-check-circle float-right';
                h6.appendChild(checkIcon);
            }

            cardHeader.appendChild(h6);
            card.appendChild(cardHeader);

            // Card Body
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body';

            const h5 = document.createElement('h5');
            h5.className = 'mb-4';
            h5.textContent = question.question;
            cardBody.appendChild(h5);

            // Smiley-Auswahl
            const buttonDiv = document.createElement('div');
            buttonDiv.className = 'd-flex justify-content-center';

            for (let rating = 1; rating <= 5; rating++) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-lg mx-2 smiley-btn';

                if (answers[question.id] === rating) {
                    btn.classList.add('btn-success');
                } else {
                    btn.classList.add('btn-outline-secondary');
                }

                const icon = document.createElement('i');
                icon.className = getSmileyIcon(rating);
                icon.style.fontSize = '2.5rem';
                btn.appendChild(icon);

                const label = document.createElement('div');
                label.className = 'small mt-2';
                label.textContent = getSmileyLabel(rating);
                btn.appendChild(label);

                btn.onclick = () => saveAnswer(question.id, rating);

                buttonDiv.appendChild(btn);
            }

            cardBody.appendChild(buttonDiv);
            card.appendChild(cardBody);
            elements.questionsList.appendChild(card);
        });
    }

    function renderCompletedState() {
        if (!elements.completedAlert) return;

        if (isAllCompleted()) {
            elements.completedAlert.style.display = 'block';
            if (elements.teacherAssessmentLink) {
                elements.teacherAssessmentLink.href = getTeacherAssessmentUrl();
            }
        } else {
            elements.completedAlert.style.display = 'none';
        }
    }

    function render() {
        console.log('Rendering...');
        renderProgress();
        renderQuestions();
        renderCompletedState();
    }

    // Initialisierung
    function init() {
        console.log('Initializing...');
        if (questions.length === 0) {
            console.warn('Keine Fragen gefunden!');
            alert('Keine Fragen für dieses Bewertungssystem gefunden!');
            return;
        }

        // Setze den Top-Link beim Start
        const url = getTeacherAssessmentUrl();
        if (elements.teacherAssessmentLinkTop) {
            elements.teacherAssessmentLinkTop.href = url;
        }

        render();
    }

    // Warte auf DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

<style>
.smiley-btn:hover {
    transform: scale(1.1);
    transition: transform 0.2s;
}
</style>
@endsection
