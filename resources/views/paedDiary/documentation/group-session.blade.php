@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Gruppendokumentation - {{ $session->klasse->name }}</h5>
                    <small class="text-muted">{{ $session->gradingSystem->name }}</small>
                </div>
                <div class="card-body" id="documentationApp">
                    <div class="alert alert-success mb-4">
                        <i class="fas fa-save"></i>
                        <strong>Automatisches Speichern:</strong> Alle Antworten werden automatisch gespeichert. Sie können die Session jederzeit unterbrechen und später fortsetzen.
                    </div>

                    <div class="alert alert-info mb-4" id="resumedAlert" style="display: none;">
                        <i class="fas fa-history"></i>
                        <strong>Session fortgesetzt:</strong> <span id="resumedCount"></span> bereits gespeicherte Antworten wurden geladen.
                    </div>

                    <!-- Aktueller Schüler -->
                    <div id="currentStudentCard" class="card mb-4 border-primary">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h4 class="mb-0" id="currentStudentName">Lädt...</h4>
                            <button id="skipStudentButton" class="btn btn-warning btn-sm">
                                <i class="fas fa-forward"></i> Schüler überspringen
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Aktuelle Frage -->
                            <div id="questionContent" class="text-center">
                                <h3 class="mb-4" id="currentQuestionText"></h3>

                                <!-- Smiley-Auswahl -->
                                <div class="d-flex justify-content-center mb-4" id="smileyButtons">
                                    <!-- Buttons werden dynamisch generiert -->
                                </div>
                            </div>

                            <div id="loadingSpinner" class="text-center" style="display: none;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Lädt...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alle Schüler haben geantwortet -->
                    <div id="completedAlert" class="alert alert-success" style="display: none;">
                        <h4><i class="fas fa-check-circle"></i> Alle Schüler haben alle Fragen beantwortet!</h4>
                        <p class="mb-3">Sie können nun Ihre Lehrereinschätzung abgeben.</p>
                        <a id="teacherAssessmentLink" href="#" class="btn btn-success">
                            <i class="fas fa-user-tie"></i> Zur Lehrereinschätzung
                        </a>
                    </div>

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

                    <!-- Schüler-Liste -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Schüler-Übersicht</h6>

                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Schüler</th>
                                            <th>Fortschritt</th>
                                        </tr>
                                    </thead>
                                    <tbody id="studentTableBody">
                                        <!-- Zeilen werden dynamisch generiert -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
    const schueler = @json($schueler);
    const questions = @json($questions);
    const existingAnswers = @json($session->studentAnswers);

    // State
    let currentSchuelerIndex = 0;
    let currentQuestionIndex = 0;
    let answers = {};
    let loading = false;

    // Bereits vorhandene Antworten in das answers Objekt laden
    if (existingAnswers && existingAnswers.length > 0) {
        existingAnswers.forEach(answer => {
            const key = `${answer.schueler_id}_${answer.question_id}`;
            answers[key] = answer.self_rating;
        });
        console.log(`${existingAnswers.length} vorhandene Antworten geladen`);

        // Zeige Hinweis dass Session fortgesetzt wird
        const resumedAlert = document.getElementById('resumedAlert');
        const resumedCount = document.getElementById('resumedCount');
        if (resumedAlert && resumedCount) {
            resumedCount.textContent = existingAnswers.length;
            resumedAlert.style.display = 'block';
        }
    }

    // DOM Elemente
    const elements = {
        currentStudentCard: document.getElementById('currentStudentCard'),
        currentStudentName: document.getElementById('currentStudentName'),
        currentQuestionText: document.getElementById('currentQuestionText'),
        smileyButtons: document.getElementById('smileyButtons'),
        completedAlert: document.getElementById('completedAlert'),
        teacherAssessmentLink: document.getElementById('teacherAssessmentLink'),
        teacherAssessmentLinkTop: document.getElementById('teacherAssessmentLinkTop'),
        teacherAssessmentLinkBottom: document.getElementById('teacherAssessmentLinkBottom'),
        studentTableBody: document.getElementById('studentTableBody'),
        questionContent: document.getElementById('questionContent'),
        loadingSpinner: document.getElementById('loadingSpinner'),
        skipStudentButton: document.getElementById('skipStudentButton')
    };

    // Computed Properties als Funktionen
    function getCurrentSchueler() {
        return schueler[currentSchuelerIndex] || null;
    }

    function getCurrentQuestion() {
        return questions[currentQuestionIndex] || null;
    }

    function getTotalSteps() {
        return schueler.length * questions.length;
    }

    function getCompletedSteps() {
        return Object.keys(answers).length;
    }

    function isAllCompleted() {
        return getCompletedSteps() >= getTotalSteps();
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

    function getSchuelerProgress(schuelerId) {
        const answered = questions.filter(q => {
            const key = `${schuelerId}_${q.id}`;
            return answers[key] !== undefined;
        }).length;
        return `${answered} / ${questions.length}`;
    }

    function isSchuelerComplete(schuelerId) {
        return questions.every(q => {
            const key = `${schuelerId}_${q.id}`;
            return answers[key] !== undefined;
        });
    }

    // Hauptfunktionen
    async function saveAnswer(rating) {
        const currentSchueler = getCurrentSchueler();
        const currentQuestion = getCurrentQuestion();

        if (loading || !currentSchueler || !currentQuestion) return;

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
                    schueler_id: currentSchueler.id,
                    question_id: currentQuestion.id,
                    self_rating: rating
                })
            });

            if (response.ok) {
                const key = `${currentSchueler.id}_${currentQuestion.id}`;
                answers[key] = rating;

                // Nächste Frage oder nächster Schüler
                if (currentQuestionIndex < questions.length - 1) {
                    currentQuestionIndex++;
                } else if (currentSchuelerIndex < schueler.length - 1) {
                    currentQuestionIndex = 0;
                    currentSchuelerIndex++;
                }

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

    async function skipStudent() {
        const currentSchueler = getCurrentSchueler();

        if (loading || !currentSchueler) return;

        loading = true;

        try {
            // Einfach zum nächsten Schüler wechseln
            if (currentSchuelerIndex < schueler.length - 1) {
                currentSchuelerIndex++;
                currentQuestionIndex = 0; // Zurück zur ersten Frage
            } else {
                // Falls letzter Schüler, vielleicht zur Übersicht oder so
                alert('Alle Schüler wurden bereits bearbeitet.');
            }

            render();
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Überspringen des Schülers.');
        } finally {
            loading = false;
        }
    }

    // Event Listener
    if (elements.skipStudentButton) {
        elements.skipStudentButton.addEventListener('click', skipStudent);
    }

    // Render-Funktionen
    function renderCurrentStudent() {
        if (!elements.currentStudentName) return;
        const currentSchueler = getCurrentSchueler();
        if (currentSchueler) {
            elements.currentStudentName.textContent = `${currentSchueler.vorname} ${currentSchueler.nachname}`;
        } else {
            elements.currentStudentName.textContent = 'Lädt...';
        }
    }

    function renderCurrentQuestion() {
        if (!elements.currentQuestionText) return;
        const currentQuestion = getCurrentQuestion();
        if (currentQuestion) {
            elements.currentQuestionText.textContent = currentQuestion.question;
        }
    }

    function renderSmileyButtons() {
        if (!elements.smileyButtons) return;
        elements.smileyButtons.innerHTML = '';

        for (let rating = 1; rating <= 5; rating++) {
            const button = document.createElement('button');
            button.className = 'btn btn-lg mx-2 smiley-btn btn-outline-secondary';
            button.onclick = () => saveAnswer(rating);

            const icon = document.createElement('i');
            icon.className = getSmileyIcon(rating);
            icon.style.fontSize = '3rem';

            const label = document.createElement('div');
            label.className = 'small mt-2';
            label.textContent = getSmileyLabel(rating);

            button.appendChild(icon);
            button.appendChild(label);
            elements.smileyButtons.appendChild(button);
        }
    }

    function renderStudentTable() {
        if (!elements.studentTableBody) return;
        elements.studentTableBody.innerHTML = '';

        schueler.forEach(s => {
            const row = document.createElement('tr');

            const currentSchueler = getCurrentSchueler();
            if (currentSchueler && s.id === currentSchueler.id) {
                row.className = 'table-primary';
            }

            const nameCell = document.createElement('td');
            nameCell.textContent = `${s.nachname}, ${s.vorname}`;

            const progressCell = document.createElement('td');
            const progressSpan = document.createElement('span');
            progressSpan.textContent = getSchuelerProgress(s.id);
            progressCell.appendChild(progressSpan);

            if (isSchuelerComplete(s.id)) {
                const checkIcon = document.createElement('i');
                checkIcon.className = 'fas fa-check-circle text-success ml-2';
                progressCell.appendChild(checkIcon);
            }

            row.appendChild(nameCell);
            row.appendChild(progressCell);
            elements.studentTableBody.appendChild(row);
        });
    }

    function renderCompletionState() {
        if (!elements.currentStudentCard || !elements.completedAlert) return;

        // Setze immer die Links für beide Buttons
        const url = getTeacherAssessmentUrl();
        if (elements.teacherAssessmentLinkTop) {
            elements.teacherAssessmentLinkTop.href = url;
        }
        if (elements.teacherAssessmentLinkBottom) {
            elements.teacherAssessmentLinkBottom.href = url;
        }

        if (isAllCompleted()) {
            elements.currentStudentCard.style.display = 'none';
            elements.completedAlert.style.display = 'block';
            if (elements.teacherAssessmentLink) {
                elements.teacherAssessmentLink.href = url;
            }
        } else {
            elements.currentStudentCard.style.display = 'block';
            elements.completedAlert.style.display = 'none';
        }
    }

    function render() {
        console.log('Rendering...');
        renderCurrentStudent();
        renderCurrentQuestion();
        renderSmileyButtons();
        renderStudentTable();
        renderCompletionState();
    }

    // Initialisierung
    function init() {
        console.log('Initializing...');
        if (schueler.length === 0) {
            console.warn('Keine Schüler gefunden!');
            alert('Keine Schüler in dieser Klasse gefunden!');
            return;
        }
        if (questions.length === 0) {
            console.warn('Keine Fragen gefunden!');
            alert('Keine Fragen für dieses Bewertungssystem gefunden!');
            return;
        }

        // Zum ersten unbeantworteten Schüler/Frage springen
        let found = false;
        for (let sIndex = 0; sIndex < schueler.length; sIndex++) {
            for (let qIndex = 0; qIndex < questions.length; qIndex++) {
                const key = `${schueler[sIndex].id}_${questions[qIndex].id}`;
                if (answers[key] === undefined) {
                    currentSchuelerIndex = sIndex;
                    currentQuestionIndex = qIndex;
                    found = true;
                    break;
                }
            }
            if (found) break;
        }

        // Setze die Links beim Start
        const url = getTeacherAssessmentUrl();
        if (elements.teacherAssessmentLinkTop) {
            elements.teacherAssessmentLinkTop.href = url;
        }
        if (elements.teacherAssessmentLinkBottom) {
            elements.teacherAssessmentLinkBottom.href = url;
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
:root{
    --primary: #0d6efd;
    --primary-700: #0b5ed7;
    --muted: #6c757d;
    --radius: 12px;
}

.smiley-btn {
    width: 110px;
    height: 110px;
    border-radius: 14px;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    box-shadow: 0 8px 28px rgba(10,15,40,0.06);
    transition: transform 180ms ease, box-shadow 180ms ease, background 180ms ease;
    border: none;
    background: linear-gradient(180deg,#fff,#f8fafc);
}
.smiley-btn i { font-size: 3rem; }
.smiley-btn .small { font-size: 0.8rem; }
.smiley-btn:hover { transform: translateY(-6px) scale(1.03); box-shadow: 0 16px 40px rgba(10,15,40,0.12); }

/* Current student card */
#currentStudentCard { border-radius: 12px; border: 1px solid rgba(13,110,253,0.12); box-shadow: 0 12px 34px rgba(10,15,40,0.06); }
#currentStudentCard .card-header { background: linear-gradient(90deg,#0d6efd,#0b5ed7); color: #fff; }

/* Table */
.table thead th { border-bottom: none; }
.table tbody tr.table-primary { background: linear-gradient(90deg, rgba(13,110,253,0.06), rgba(13,110,253,0.02)); }

/* Completed alert */
#completedAlert { border-radius: 10px; box-shadow: 0 8px 24px rgba(10,15,40,0.06); }

/* Buttons */
.btn-success, .btn-primary { border-radius: 10px; box-shadow: 0 6px 18px rgba(10,15,40,0.06); }
.btn-warning { border-radius: 8px; }

/* Small helpers */
.text-center h3 { font-weight: 600; }

@media (max-width: 768px) {
    .smiley-btn { width: 76px; height: 76px; }
}
</style>
@endsection
