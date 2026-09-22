@extends('layouts.app')

@section('js')
<script src="{{ asset('js/qrcode.min.js') }}"></script>
@endsection

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

                    <div class="alert alert-light border mb-4" id="answerOrderPanel">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                            <div class="mb-2 mb-md-0">
                                <i class="fas fa-random text-primary"></i>
                                <strong>Beantwortungsreihenfolge:</strong>
                                <div class="small text-muted mt-1" id="answerOrderDescription"></div>
                            </div>
                            <div class="btn-group btn-group-sm answer-order-buttons" role="group" aria-label="Beantwortungsreihenfolge">
                                <button type="button" class="btn btn-outline-primary" id="orderByStudentButton">Schülerweise</button>
                                <button type="button" class="btn btn-outline-primary" id="orderByQuestionButton">Fragenweise</button>
                            </div>
                        </div>
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
                                            <th>Aktionen</th>
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

<!-- QR-Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" role="dialog" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrCodeModalLabel">QR-Code für Schüler</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-3" id="qrCodeSchuelerName"></p>
                <div id="qrCodeContainer" class="mb-3">
                    <!-- QR-Code wird hier eingefügt -->
                </div>
                <p class="small text-muted">Der Schüler kann diesen QR-Code scannen, um die Fragen auf einem anderen Gerät zu beantworten, ohne sich anmelden zu müssen.</p>
                <div class="mt-3">
                    <input type="text" class="form-control" id="qrCodeUrl" readonly>
                    <button class="btn btn-sm btn-secondary mt-2" onclick="copyQRUrl()">
                        <i class="fas fa-copy"></i> Link kopieren
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
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
    const initialAnswerOrderMode = @json($session->answer_order_mode);

    const ANSWER_ORDER_MODES = {
        BY_STUDENT: 'by_student',
        BY_QUESTION: 'by_question'
    };

    // State
    let currentSchuelerIndex = 0;
    let currentQuestionIndex = 0;
    let answers = {};
    let loading = false;
    let answerOrderMode = initialAnswerOrderMode || ANSWER_ORDER_MODES.BY_STUDENT;

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
        skipStudentButton: document.getElementById('skipStudentButton'),
        answerOrderDescription: document.getElementById('answerOrderDescription'),
        orderByStudentButton: document.getElementById('orderByStudentButton'),
        orderByQuestionButton: document.getElementById('orderByQuestionButton')
    };

    function isQuestionOrderMode() {
        return answerOrderMode === ANSWER_ORDER_MODES.BY_QUESTION;
    }

    function getAnswerKey(schuelerId, questionId) {
        return `${schuelerId}_${questionId}`;
    }

    function getAnswerOrderDescription() {
        if (isQuestionOrderMode()) {
            return 'Alle Schüler beantworten zuerst dieselbe Frage, anschließend wird zur nächsten Frage gewechselt.';
        }

        return 'Ein Schüler beantwortet alle Fragen vollständig, bevor der nächste Schüler an der Reihe ist.';
    }

    function getTraversalSteps() {
        const steps = [];

        if (isQuestionOrderMode()) {
            questions.forEach((question, questionIndex) => {
                schueler.forEach((currentSchueler, schuelerIndex) => {
                    steps.push({ schuelerIndex, questionIndex, schuelerId: currentSchueler.id, questionId: question.id });
                });
            });

            return steps;
        }

        schueler.forEach((currentSchueler, schuelerIndex) => {
            questions.forEach((question, questionIndex) => {
                steps.push({ schuelerIndex, questionIndex, schuelerId: currentSchueler.id, questionId: question.id });
            });
        });

        return steps;
    }

    function isStepAnswered(step) {
        return answers[getAnswerKey(step.schuelerId, step.questionId)] !== undefined;
    }

    function setCurrentStep(step) {
        if (!step) return;

        currentSchuelerIndex = step.schuelerIndex;
        currentQuestionIndex = step.questionIndex;
    }

    function findFirstPendingStep() {
        return getTraversalSteps().find(step => !isStepAnswered(step)) || null;
    }

    function findNextPendingStepAfterCurrent(wrap = false) {
        const steps = getTraversalSteps();
        const currentIndex = steps.findIndex(step =>
            step.schuelerIndex === currentSchuelerIndex && step.questionIndex === currentQuestionIndex
        );

        if (currentIndex === -1) {
            return findFirstPendingStep();
        }

        const nextStep = steps.slice(currentIndex + 1).find(step => !isStepAnswered(step));
        if (nextStep) {
            return nextStep;
        }

        if (!wrap) {
            return null;
        }

        return steps.slice(0, currentIndex).find(step => !isStepAnswered(step)) || null;
    }

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
                const key = getAnswerKey(currentSchueler.id, currentQuestion.id);
                answers[key] = rating;

                const nextStep = findNextPendingStepAfterCurrent(true);
                if (nextStep) {
                    setCurrentStep(nextStep);
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

    async function updateAnswerOrderMode(mode) {
        if (loading || answerOrderMode === mode) return;

        loading = true;

        try {
            const response = await fetch(`/paed-diary/documentation/session/${sessionId}/answer-order-mode`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    answer_order_mode: mode
                })
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Die Reihenfolge konnte nicht geändert werden.');
            }

            const data = await response.json();
            answerOrderMode = data.answer_order_mode || ANSWER_ORDER_MODES.BY_STUDENT;

            const firstPendingStep = findFirstPendingStep();
            if (firstPendingStep) {
                setCurrentStep(firstPendingStep);
            }

            render();
        } catch (error) {
            console.error('Fehler:', error);
            alert(error.message || 'Fehler beim Ändern der Beantwortungsreihenfolge.');
        } finally {
            loading = false;
        }
    }

    function skipStudent() {
        if (loading) return;

        const nextStep = findNextPendingStepAfterCurrent(true);
        if (!nextStep) {
            alert('Es gibt keine weiteren offenen Antworten.');
            return;
        }

        setCurrentStep(nextStep);
        render();
    }

    function jumpToStudent(schuelerIndex) {
        if (loading) return;

        currentSchuelerIndex = schuelerIndex;

        if (isQuestionOrderMode()) {
            render();

            if (elements.currentStudentCard) {
                elements.currentStudentCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            return;
        }

        // Finde die erste unbeantwortete Frage für diesen Schüler
        const s = schueler[schuelerIndex];
        let foundUnanswered = false;
        for (let qIndex = 0; qIndex < questions.length; qIndex++) {
            const key = getAnswerKey(s.id, questions[qIndex].id);
            if (answers[key] === undefined) {
                currentQuestionIndex = qIndex;
                foundUnanswered = true;
                break;
            }
        }

        // Falls alle Fragen beantwortet sind, beginne bei der ersten Frage
        if (!foundUnanswered) {
            currentQuestionIndex = 0;
        }

        render();

        // Scrolle zur aktuellen Schülerkarte
        if (elements.currentStudentCard) {
            elements.currentStudentCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Event Listener
    if (elements.skipStudentButton) {
        elements.skipStudentButton.addEventListener('click', skipStudent);
    }

    if (elements.orderByStudentButton) {
        elements.orderByStudentButton.addEventListener('click', () => updateAnswerOrderMode(ANSWER_ORDER_MODES.BY_STUDENT));
    }

    if (elements.orderByQuestionButton) {
        elements.orderByQuestionButton.addEventListener('click', () => updateAnswerOrderMode(ANSWER_ORDER_MODES.BY_QUESTION));
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
            elements.currentQuestionText.textContent = `Frage ${currentQuestionIndex + 1} von ${questions.length}: ${currentQuestion.question}`;
        }
    }

    function renderSmileyButtons() {
        if (!elements.smileyButtons) return;
        elements.smileyButtons.innerHTML = '';

        const currentSchueler = getCurrentSchueler();
        const currentQuestion = getCurrentQuestion();

        for (let rating = 1; rating <= 5; rating++) {
            const button = document.createElement('button');
            button.className = 'btn btn-lg mx-2 smiley-btn';
            if (currentSchueler && currentQuestion && answers[getAnswerKey(currentSchueler.id, currentQuestion.id)] === rating) {
                button.classList.add('btn-success');
            } else {
                button.classList.add('btn-outline-secondary');
            }
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

        schueler.forEach((s, index) => {
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

            // Aktionsspalte
            const actionsCell = document.createElement('td');

            // Springen-Button
            const jumpBtn = document.createElement('button');
            jumpBtn.className = 'btn btn-sm btn-primary mr-1';
            jumpBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Springen';
            jumpBtn.onclick = () => jumpToStudent(index);
            jumpBtn.title = 'Zu diesem Schüler springen';
            actionsCell.appendChild(jumpBtn);

            // QR-Code Button
            const qrBtn = document.createElement('button');
            qrBtn.className = 'btn btn-sm btn-info';
            qrBtn.innerHTML = '<i class="fas fa-qrcode"></i>';
            qrBtn.onclick = () => showQRCode(s.id);
            qrBtn.title = 'QR-Code für Schüler anzeigen';
            actionsCell.appendChild(qrBtn);

            row.appendChild(nameCell);
            row.appendChild(progressCell);
            row.appendChild(actionsCell);
            elements.studentTableBody.appendChild(row);
        });
    }

    function renderAnswerOrderControls() {
        if (elements.answerOrderDescription) {
            elements.answerOrderDescription.textContent = getAnswerOrderDescription();
        }

        if (elements.orderByStudentButton) {
            elements.orderByStudentButton.classList.toggle('active', !isQuestionOrderMode());
        }

        if (elements.orderByQuestionButton) {
            elements.orderByQuestionButton.classList.toggle('active', isQuestionOrderMode());
        }

        if (elements.skipStudentButton) {
            elements.skipStudentButton.innerHTML = isQuestionOrderMode()
                ? '<i class="fas fa-forward"></i> Ohne Antwort weiter'
                : '<i class="fas fa-forward"></i> Schüler überspringen';
        }
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
        renderAnswerOrderControls();
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

        const firstPendingStep = findFirstPendingStep();
        if (firstPendingStep) {
            setCurrentStep(firstPendingStep);
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

// Globale Funktionen für QR-Code
async function showQRCode(schuelerId) {
    const schuelerList = @json($schueler);
    const schueler = schuelerList.find(s => s.id === schuelerId);

    if (!schueler) {
        alert('Schüler nicht gefunden');
        return;
    }

    try {
        const response = await fetch(`/paed-diary/documentation/session/{{ $session->id }}/student/${schuelerId}/qr-token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        if (!response.ok) {
            // Versuche die Fehlermeldung vom Server zu lesen
            let errorMessage = 'Fehler beim Generieren des QR-Codes';
            try {
                const errorData = await response.json();
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (e) {
                // Falls JSON-Parsing fehlschlägt, zeige HTTP-Status
                errorMessage = `HTTP ${response.status}: ${response.statusText}`;
            }
            throw new Error(errorMessage);
        }

        const data = await response.json();

        // Modal-Inhalt aktualisieren
        document.getElementById('qrCodeSchuelerName').textContent =
            `${schueler.vorname} ${schueler.nachname}`;
        document.getElementById('qrCodeUrl').value = data.url;

        // QR-Code generieren
        const qrContainer = document.getElementById('qrCodeContainer');
        qrContainer.innerHTML = '';

        // Verwende QRCode.js wenn verfügbar, sonst zeige nur den Link
        if (typeof QRCode !== 'undefined') {
            new QRCode(qrContainer, {
                text: data.url,
                width: 256,
                height: 256
            });
        } else {
            qrContainer.innerHTML = `<p class="text-warning">QR-Code Bibliothek nicht verfügbar. Bitte Link verwenden.</p>`;
        }

        // Modal anzeigen
        $('#qrCodeModal').modal('show');
    } catch (error) {
        console.error('Fehler:', error);
        alert('Fehler beim Generieren des QR-Codes: ' + error.message);
    }
}

function copyQRUrl() {
    const urlInput = document.getElementById('qrCodeUrl');
    urlInput.select();
    urlInput.setSelectionRange(0, 99999); // Für mobile Geräte

    try {
        document.execCommand('copy');
        alert('Link in die Zwischenablage kopiert!');
    } catch (err) {
        console.error('Fehler beim Kopieren:', err);
        alert('Fehler beim Kopieren des Links');
    }
}
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
.smiley-btn i {
    font-size: 3rem;
}
.smiley-btn .small {
    font-size: 0.8rem;
}
.smiley-btn:hover {
    transform: translateY(-6px) scale(1.03);
    box-shadow: 0 16px 40px rgba(10,15,40,0.12);
}

/* Current student card */
#currentStudentCard {
    border-radius: 12px;
    border: 1px solid rgba(13,110,253,0.12);
    box-shadow: 0 12px 34px rgba(10,15,40,0.06);
}
#currentStudentCard .card-header {
    background: linear-gradient(90deg,#0d6efd,#0b5ed7);
    color: #fff;
}

/* Table */
.table thead th {
    border-bottom: none;
}
.table tbody tr.table-primary {
    background: linear-gradient(90deg, rgba(13,110,253,0.06), rgba(13,110,253,0.02));
}

/* Completed alert */
#completedAlert {
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(10,15,40,0.06);
}

/* Buttons */
.btn-success, .btn-primary {
    border-radius: 10px;
    box-shadow: 0 6px 18px rgba(10,15,40,0.06);
}
.btn-warning {
    border-radius: 8px;
}

.answer-order-buttons .btn.active {
    color: #fff;
    background: linear-gradient(90deg,#0d6efd,#0b5ed7);
    border-color: #0d6efd;
}

/* Small helpers */
.text-center h3 {
    font-weight: 600;
}

@media (max-width: 768px) {
    .smiley-btn {
        width: 76px;
        height: 76px;
    }
    .smiley-btn i {
        font-size: 2rem;
    }
}
</style>
@endsection
