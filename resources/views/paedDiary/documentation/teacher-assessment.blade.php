@extends('layouts.app')

@section('content')
<div class="container-fluid px-2 px-md-3">
    <div class="row">
        <div class="col-12">
            <div class="card main-card">
                <div class="card-header">
                    <div class="header-content">
                        <div class="header-info">
                            <h5 class="mb-0">Lehrereinschätzung</h5>
                            <div class="class-info">
                                <span class="badge badge-light">{{ $session->klasse->name }}</span>
                                <span class="badge badge-light">{{ $session->gradingSystem->name }}</span>
                            </div>
                        </div>
                        @if($session->type === 'group')
                            <a href="{{ route('gradingDocumentation.groupSession', $session->id) }}" class="back-btn inline-flex items-center gap-2">
                                <i class="fas fa-arrow-left"></i> <span class="hidden md:inline">Zurück</span>
                            </a>
                        @elseif($session->type === 'individual')
                            <a href="{{ route('gradingDocumentation.individualSession', $session->id) }}" class="back-btn inline-flex items-center gap-2">
                                <i class="fas fa-arrow-left"></i> <span class="hidden md:inline">Zurück</span>
                            </a>
                        @endif
                    </div>
                </div>
                <div class="card-body p-2 p-md-3" id="teacherApp">
                    <!-- Kompakte Info-Banner -->
                    <div class="info-banners mb-3">
                        <div class="info-banner info-banner-primary">
                            <i class="fas fa-save"></i>
                            <span>Auto-Speicherung aktiv</span>
                        </div>
                        <div class="info-banner info-banner-success" id="resumedAlert" style="display: none;">
                            <i class="fas fa-history"></i>
                            <span><span id="resumedCount"></span> Bewertungen geladen</span>
                        </div>
                        <div class="info-banner info-banner-info" id="autoScrollToggle" style="cursor: pointer;" title="Klicken zum Umschalten">
                            <i class="fas fa-arrows-alt-v"></i>
                            <span id="autoScrollText">Auto-Scroll: An</span>
                        </div>
                    </div>
                    @if($session->type == 'group')
                        <div class="answer-order-toolbar mb-3">
                            <div>
                                <div class="answer-order-title">
                                    <i class="fas fa-random text-primary"></i>
                                    <strong>Beantwortungsreihenfolge</strong>
                                </div>
                                <small class="text-muted" id="answerOrderDescription"></small>
                            </div>
                            <div class="flex flex-wrap gap-2 mt-2 md:mt-0" role="group" aria-label="Beantwortungsreihenfolge">
                                <button type="button" class="answer-order-button" id="orderByStudentButton">Schülerweise</button>
                                <button type="button" class="answer-order-button" id="orderByQuestionButton">Fragenweise</button>
                            </div>
                        </div>

                        <div class="question-cycle-banner mb-3" id="questionCycleBanner" style="display: none;"></div>
                    @endif

                    <div class="question-navigation mb-3" id="questionNavigation">
                        <div class="question-navigation-label">
                            <i class="fas fa-list-ol text-primary"></i>
                            <strong>Direkt zu Frage springen</strong>
                        </div>
                        <div class="question-navigation-buttons" id="questionNavigationButtons"></div>
                    </div>

                    <!-- Fortschrittsanzeige -->
                    <div class="progress-section mb-3">
                        <div class="progress-info">
                            <span class="progress-label">Fortschritt:</span>
                            <span class="progress-text" id="progressText">0 von 0 Schülern</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Schüler-Navigation (Dropdown für mobile, Pills für Tablet/Desktop) -->
                    <div class="student-navigation mb-3">
                        <div class="d-md-none">
                            <select class="form-control form-control-lg student-select" id="studentSelect">
                                <!-- Options werden dynamisch generiert -->
                            </select>
                        </div>
                        <div class="d-none d-md-block">
                            <ul class="nav nav-pills student-pills" role="tablist" id="studentTabs">
                                <!-- Tabs werden dynamisch generiert -->
                            </ul>
                        </div>
                    </div>

                    <!-- Tab-Inhalt -->
                    <div class="tab-content" id="tabContent">
                        <!-- Inhalt wird dynamisch generiert -->
                    </div>

                    <!-- Sticky Footer mit Aktionsbuttons -->
                    <div class="action-footer">
                        <button id="skipButton" class="flex-1 max-w-[250px] px-4 py-2.5 text-sm font-medium text-[#212529] bg-white border-2 border-[#e9ecef] rounded-[8px] inline-flex items-center justify-center gap-2 cursor-pointer transition-all duration-300 hover:border-[#0d6efd] hover:text-[#0d6efd] disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-forward"></i> Überspringen
                        </button>
                        <button id="completeButton" class="flex-1 max-w-[250px] px-4 py-2.5 text-sm font-medium text-[#0d6efd] bg-white border-2 border-[#0d6efd] rounded-[8px] inline-flex items-center justify-center gap-2 cursor-pointer transition-all duration-300 hover:bg-[#0d6efd] hover:text-white disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-check"></i> Abschließen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const sessionId = {{ $session->id }};
    const schueler = @json($schueler);
    const questions = @json($questions);
    const sessionType = @json($session->type);
    const initialAnswerOrderMode = @json($session->answer_order_mode);
    const studentAnswers = @json($session->studentAnswers->groupBy('schueler_id')->map(function($answers) {
        return $answers->keyBy('question_id')->map(function($answer) { return $answer->self_rating; });
    }));
    const teacherAssessmentsData = @json($session->teacherAssessments->groupBy('schueler_id')->map(function($assessments) {
        return $assessments->keyBy('question_id');
    }));
    const coachingNotesData = @json($session->coachingNotes->keyBy('schueler_id')->map(function($note) {
        return $note->note;
    }));

    const ANSWER_ORDER_MODES = {
        BY_STUDENT: 'by_student',
        BY_QUESTION: 'by_question'
    };

    let currentSchuelerIndex = 0;
    let currentQuestionIndex = 0;
    let teacherAssessments = teacherAssessmentsData || {};
    let coachingNotes = coachingNotesData || {};
    let loading = false;
    let noteLoading = false;
    let autoScrollEnabled = localStorage.getItem('teacherAssessment_autoScroll') !== 'false';
    let answerOrderMode = sessionType === 'group'
        ? (initialAnswerOrderMode || ANSWER_ORDER_MODES.BY_STUDENT)
        : ANSWER_ORDER_MODES.BY_STUDENT;

    const assessmentCount = Object.values(teacherAssessments).reduce((count, assessments) => {
        return count + Object.keys(assessments).length;
    }, 0);

    if (assessmentCount > 0) {
        const resumedAlert = document.getElementById('resumedAlert');
        const resumedCount = document.getElementById('resumedCount');
        if (resumedAlert && resumedCount) {
            resumedCount.textContent = assessmentCount;
            resumedAlert.style.display = 'flex';
        }
    }

    const elements = {
        studentTabs: document.getElementById('studentTabs'),
        studentSelect: document.getElementById('studentSelect'),
        tabContent: document.getElementById('tabContent'),
        completeButton: document.getElementById('completeButton'),
        skipButton: document.getElementById('skipButton'),
        progressBar: document.getElementById('progressBar'),
        progressText: document.getElementById('progressText'),
        answerOrderDescription: document.getElementById('answerOrderDescription'),
        orderByStudentButton: document.getElementById('orderByStudentButton'),
        orderByQuestionButton: document.getElementById('orderByQuestionButton'),
        questionCycleBanner: document.getElementById('questionCycleBanner'),
        questionNavigation: document.getElementById('questionNavigation'),
        questionNavigationButtons: document.getElementById('questionNavigationButtons')
    };

    function getCurrentSchueler() {
        return schueler[currentSchuelerIndex] || null;
    }

    function getCurrentQuestion() {
        return questions[currentQuestionIndex] || null;
    }

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

    function getStudentAnswer(schuelerId, questionId) {
        return studentAnswers[schuelerId] && studentAnswers[schuelerId][questionId];
    }

    function getTeacherRating(schuelerId, questionId) {
        return teacherAssessments[schuelerId] &&
               teacherAssessments[schuelerId][questionId] &&
               teacherAssessments[schuelerId][questionId].teacher_rating;
    }

    function getTeacherComment(schuelerId, questionId) {
        return teacherAssessments[schuelerId] &&
               teacherAssessments[schuelerId][questionId] &&
               teacherAssessments[schuelerId][questionId].comment || '';
    }

    function getCoachingNote(schuelerId) {
        return coachingNotes[schuelerId] || '';
    }

    function isSchuelerComplete(schuelerId) {
        return questions.every(question => {
            return teacherAssessments[schuelerId] &&
                   teacherAssessments[schuelerId][question.id] &&
                   teacherAssessments[schuelerId][question.id].teacher_rating;
        });
    }

    function isAllComplete() {
        return schueler.some(currentSchueler => isSchuelerComplete(currentSchueler.id));
    }

    function isQuestionOrderMode() {
        return sessionType === 'group' && answerOrderMode === ANSWER_ORDER_MODES.BY_QUESTION;
    }

    function getAnswerOrderDescription() {
        if (isQuestionOrderMode()) {
            return 'Alle Schüler werden nacheinander zur aktuellen Frage bewertet, bevor zur nächsten Frage gewechselt wird.';
        }

        return 'Ein Schüler wird vollständig bewertet, bevor zum nächsten Schüler gewechselt wird.';
    }

    function getFirstUnansweredQuestionIndexForStudent(index) {
        const currentSchueler = schueler[index];

        if (!currentSchueler) {
            return 0;
        }

        const firstUnansweredQuestion = questions.findIndex(question => !getTeacherRating(currentSchueler.id, question.id));

        return firstUnansweredQuestion === -1 ? 0 : firstUnansweredQuestion;
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

    function isStepAssessed(step) {
        return !!getTeacherRating(step.schuelerId, step.questionId);
    }

    function setCurrentStep(step) {
        if (!step) return;

        currentSchuelerIndex = step.schuelerIndex;
        currentQuestionIndex = step.questionIndex;
    }

    function findFirstPendingStep() {
        return getTraversalSteps().find(step => !isStepAssessed(step)) || null;
    }

    function findNextPendingStepAfterCurrent(wrap = false) {
        const steps = getTraversalSteps();
        const currentIndex = steps.findIndex(step =>
            step.schuelerIndex === currentSchuelerIndex && step.questionIndex === currentQuestionIndex
        );

        if (currentIndex === -1) {
            return findFirstPendingStep();
        }

        const nextStep = steps.slice(currentIndex + 1).find(step => !isStepAssessed(step));
        if (nextStep) {
            return nextStep;
        }

        if (!wrap) {
            return null;
        }

        return steps.slice(0, currentIndex).find(step => !isStepAssessed(step)) || null;
    }

    function getAnsweredQuestionCountForStudent(schuelerId) {
        return questions.filter(question => !!getTeacherRating(schuelerId, question.id)).length;
    }

    function getQuestionCompletionCount(questionId) {
        return schueler.filter(currentSchueler => !!getTeacherRating(currentSchueler.id, questionId)).length;
    }

    function goToQuestion(questionIndex) {
        if (loading || questionIndex < 0 || questionIndex >= questions.length) return;

        currentQuestionIndex = questionIndex;
        render();

        setTimeout(() => {
            const targetCard = document.querySelector(`.question-card[data-question-index="${questionIndex}"]`);
            if (!targetCard) return;

            targetCard.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            targetCard.classList.add('highlight-question');
            setTimeout(() => {
                targetCard.classList.remove('highlight-question');
            }, 1500);
        }, 50);
    }

    function skipCurrentStudent() {
        if (loading) return;

        if (isQuestionOrderMode()) {
            const nextStep = findNextPendingStepAfterCurrent(true);

            if (!nextStep) {
                alert('Es gibt keine weiteren offenen Bewertungen.');
                return;
            }

            setCurrentStep(nextStep);
            render();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        const incompleteIndices = schueler
            .map((currentSchueler, index) => ({ currentSchueler, index }))
            .filter(({ currentSchueler }) => !isSchuelerComplete(currentSchueler.id))
            .map(({ index }) => index);

        if (incompleteIndices.length === 0) {
            currentSchuelerIndex = 0;
            currentQuestionIndex = 0;
            render();
            return;
        }

        const nextIndex = incompleteIndices.find(index => index > currentSchuelerIndex) ?? incompleteIndices[0];
        currentSchuelerIndex = nextIndex;
        currentQuestionIndex = getFirstUnansweredQuestionIndexForStudent(nextIndex);
        render();
    }

    /**
     * Reine Vorwärtsnavigation ohne Suche nach offenen Bewertungen: springt zum
     * nächsten Kind (bzw. bei der letzten/dem letzten zur nächsten Frage/zum ersten Kind).
     * Wird verwendet, wenn die aktuelle Bewertung bereits erfasst ist ("Weiter"-Zustand),
     * damit der Button nicht ins Leere läuft, sobald keine offenen Bewertungen mehr existieren.
     */
    function goToNextStep() {
        if (loading) return;

        if (isQuestionOrderMode()) {
            let nextSchuelerIndex = currentSchuelerIndex + 1;
            let nextQuestionIndex = currentQuestionIndex;

            if (nextSchuelerIndex >= schueler.length) {
                nextSchuelerIndex = 0;
                nextQuestionIndex = (currentQuestionIndex + 1) % questions.length;
            }

            currentSchuelerIndex = nextSchuelerIndex;
            currentQuestionIndex = nextQuestionIndex;
        } else {
            let nextQuestionIndex = currentQuestionIndex + 1;
            let nextSchuelerIndex = currentSchuelerIndex;

            if (nextQuestionIndex >= questions.length) {
                nextQuestionIndex = 0;
                nextSchuelerIndex = (currentSchuelerIndex + 1) % schueler.length;
            }

            currentQuestionIndex = nextQuestionIndex;
            currentSchuelerIndex = nextSchuelerIndex;
        }

        render();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function handleSkipButtonClick() {
        if (loading) return;

        const currentStepAssessed = !!getTeacherRating(
            schueler[currentSchuelerIndex]?.id,
            questions[currentQuestionIndex]?.id
        );

        if (currentStepAssessed) {
            goToNextStep();
            return;
        }

        skipCurrentStudent();
    }

    function scrollToNextQuestion(currentQuestionId) {
        if (!autoScrollEnabled || isQuestionOrderMode()) return;

        const currentIndex = questions.findIndex(question => question.id === currentQuestionId);
        if (currentIndex === -1) return;

        const nextIndex = currentIndex + 1;

        if (nextIndex < questions.length) {
            setTimeout(() => {
                const allCards = document.querySelectorAll('.question-card');
                if (allCards[nextIndex]) {
                    allCards[nextIndex].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    allCards[nextIndex].classList.add('highlight-question');
                    setTimeout(() => {
                        allCards[nextIndex].classList.remove('highlight-question');
                    }, 1500);
                }
            }, 300);
        } else {
            const currentSchueler = getCurrentSchueler();

            if (currentSchueler && isSchuelerComplete(currentSchueler.id)) {
                if (currentSchuelerIndex < schueler.length - 1) {
                    setTimeout(() => {
                        if (confirm(`Alle Fragen für ${currentSchueler.vorname} ${currentSchueler.nachname} beantwortet! Zum nächsten Schüler wechseln?`)) {
                            currentSchuelerIndex++;
                            currentQuestionIndex = getFirstUnansweredQuestionIndexForStudent(currentSchuelerIndex);
                            render();
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    }, 500);
                } else {
                    setTimeout(() => {
                        if (confirm('Alle Fragen beantwortet! Möchten Sie die Dokumentation jetzt abschließen?')) {
                            completeSession();
                        }
                    }, 500);
                }
            }
        }
    }

    function toggleAutoScroll() {
        autoScrollEnabled = !autoScrollEnabled;
        localStorage.setItem('teacherAssessment_autoScroll', autoScrollEnabled);
        updateAutoScrollUI();
    }

    function updateAutoScrollUI() {
        const toggle = document.getElementById('autoScrollToggle');
        const text = document.getElementById('autoScrollText');

        if (toggle && text) {
            if (autoScrollEnabled) {
                text.textContent = 'Auto-Scroll: An';
                toggle.classList.remove('info-banner-secondary');
                toggle.classList.add('info-banner-info');
            } else {
                text.textContent = 'Auto-Scroll: Aus';
                toggle.classList.remove('info-banner-info');
                toggle.classList.add('info-banner-secondary');
            }
        }
    }

    function renderAnswerOrderControls() {
        if (!elements.answerOrderDescription) return;

        elements.answerOrderDescription.textContent = getAnswerOrderDescription();
        elements.orderByStudentButton.classList.toggle('active', !isQuestionOrderMode());
        elements.orderByQuestionButton.classList.toggle('active', isQuestionOrderMode());

        const currentStepAssessed = !!getTeacherRating(
            schueler[currentSchuelerIndex]?.id,
            questions[currentQuestionIndex]?.id
        );

        if (elements.skipButton) {
            elements.skipButton.classList.toggle('border-[#0d6efd]', currentStepAssessed);
            elements.skipButton.classList.toggle('text-[#0d6efd]', currentStepAssessed);
            elements.skipButton.innerHTML = isQuestionOrderMode()
                ? `<i class="fas fa-forward"></i> ${currentStepAssessed ? 'Weiter' : 'Ohne Bewertung weiter'}`
                : `<i class="fas fa-forward"></i> ${currentStepAssessed ? 'Weiter' : 'Überspringen'}`;
        }
    }

    function renderQuestionCycleBanner() {
        if (!elements.questionCycleBanner) return;

        if (!isQuestionOrderMode()) {
            elements.questionCycleBanner.style.display = 'none';
            elements.questionCycleBanner.innerHTML = '';
            return;
        }

        const currentQuestion = getCurrentQuestion();

        if (!currentQuestion) {
            elements.questionCycleBanner.style.display = 'none';
            return;
        }

        const completedForQuestion = getQuestionCompletionCount(currentQuestion.id);
        elements.questionCycleBanner.style.display = 'block';
        elements.questionCycleBanner.innerHTML = `
            <div class="question-cycle-meta">Frage ${currentQuestionIndex + 1} von ${questions.length}</div>
            <div class="question-cycle-text">${currentQuestion.question}</div>
            <div class="question-cycle-progress">${completedForQuestion} von ${schueler.length} Schülern bewertet</div>
        `;
    }

    function renderQuestionNavigation() {
        if (!elements.questionNavigation || !elements.questionNavigationButtons) return;

        if (sessionType !== 'group' || !isQuestionOrderMode() || questions.length <= 1) {
            elements.questionNavigation.style.display = 'none';
            elements.questionNavigationButtons.innerHTML = '';
            return;
        }

        elements.questionNavigation.style.display = 'block';
        elements.questionNavigationButtons.innerHTML = '';

        questions.forEach((question, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'question-nav-btn';
            button.textContent = index + 1;
            button.title = question.question;
            button.setAttribute('aria-label', `Zu Frage ${index + 1} springen`);

            if (index === currentQuestionIndex) {
                button.classList.add('active');
            }

            if (getQuestionCompletionCount(question.id) === schueler.length) {
                button.classList.add('completed');
            }

            button.addEventListener('click', () => goToQuestion(index));
            elements.questionNavigationButtons.appendChild(button);
        });
    }

    async function updateAnswerOrderMode(mode) {
        if (loading || sessionType !== 'group' || answerOrderMode === mode) return;

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
            } else {
                currentQuestionIndex = getFirstUnansweredQuestionIndexForStudent(currentSchuelerIndex);
            }

            render();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error) {
            console.error('Fehler:', error);
            alert(error.message || 'Fehler beim Ändern der Beantwortungsreihenfolge.');
        } finally {
            loading = false;
        }
    }

    async function saveAssessment(schuelerId, questionId, rating) {
        if (loading) return;

        loading = true;

        try {
            const response = await fetch('/paed-diary/documentation/teacher-assessment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    schueler_id: schuelerId,
                    question_id: questionId,
                    teacher_rating: rating,
                    comment: getTeacherComment(schuelerId, questionId)
                })
            });

            if (response.ok) {
                if (!teacherAssessments[schuelerId]) {
                    teacherAssessments[schuelerId] = {};
                }
                teacherAssessments[schuelerId][questionId] = {
                    teacher_rating: rating,
                    comment: getTeacherComment(schuelerId, questionId)
                };

                if (isQuestionOrderMode()) {
                    const nextStep = findNextPendingStepAfterCurrent(true);
                    if (nextStep) {
                        setCurrentStep(nextStep);
                    }
                    render();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }

                currentQuestionIndex = getFirstUnansweredQuestionIndexForStudent(currentSchuelerIndex);
                render();
                scrollToNextQuestion(questionId);
            } else {
                alert('Fehler beim Speichern der Einschätzung.');
            }
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Speichern der Einschätzung.');
        } finally {
            loading = false;
        }
    }

    async function saveComment(schuelerId, questionId, comment) {
        if (loading) return;

        loading = true;

        try {
            const response = await fetch('/paed-diary/documentation/teacher-assessment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    schueler_id: schuelerId,
                    question_id: questionId,
                    teacher_rating: getTeacherRating(schuelerId, questionId),
                    comment: comment
                })
            });

            if (response.ok) {
                if (!teacherAssessments[schuelerId]) {
                    teacherAssessments[schuelerId] = {};
                }
                if (!teacherAssessments[schuelerId][questionId]) {
                    teacherAssessments[schuelerId][questionId] = {};
                }
                teacherAssessments[schuelerId][questionId].comment = comment;
            }
        } catch (error) {
            console.error('Fehler:', error);
        } finally {
            loading = false;
        }
    }

    async function saveCoachingNote(schuelerId, note) {
        if (noteLoading) return;

        noteLoading = true;

        try {
            const response = await fetch('/paed-diary/documentation/coaching-note', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    schueler_id: schuelerId,
                    note: note
                })
            });

            if (response.ok) {
                coachingNotes[schuelerId] = note;
                const status = document.getElementById('coachingNoteStatus');
                if (status) {
                    status.textContent = 'Gespeichert';
                    status.classList.add('saved');
                    setTimeout(() => status.classList.remove('saved'), 1500);
                }
            } else {
                alert('Fehler beim Speichern des Coaching-Protokolls.');
            }
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Speichern des Coaching-Protokolls.');
        } finally {
            noteLoading = false;
        }
    }

    async function completeSession() {
        if (loading) return;

        const hasAnyAssessments = schueler.some(currentSchueler => isSchuelerComplete(currentSchueler.id));

        if (!hasAnyAssessments) {
            alert('Bitte bewerten Sie mindestens einen Schüler, bevor Sie die Dokumentation abschließen.');
            return;
        }

        const skippedStudents = schueler.filter(currentSchueler => !isSchuelerComplete(currentSchueler.id));
        let confirmMessage = 'Möchten Sie die Dokumentation wirklich abschließen? Danach können keine Änderungen mehr vorgenommen werden.';

        if (skippedStudents.length > 0) {
            const skippedNames = skippedStudents.map(currentSchueler => `${currentSchueler.vorname} ${currentSchueler.nachname}`).join(', ');
            confirmMessage = `Achtung: Folgende Schüler wurden nicht vollständig bewertet: ${skippedNames}\n\nMöchten Sie die Dokumentation trotzdem abschließen? Danach können keine Änderungen mehr vorgenommen werden.`;
        }

        if (!confirm(confirmMessage)) {
            return;
        }

        loading = true;

        try {
            const response = await fetch(`/paed-diary/documentation/session/${sessionId}/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (response.ok) {
                alert('Dokumentation erfolgreich abgeschlossen!');
                window.location.href = '/paed-diary/documentation';
            } else {
                alert('Fehler beim Abschließen der Dokumentation.');
            }
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Abschließen der Dokumentation.');
        } finally {
            loading = false;
        }
    }

    function updateProgress() {
        const completedCount = schueler.filter(currentSchueler => isSchuelerComplete(currentSchueler.id)).length;
        const totalCount = schueler.length;
        const percentage = totalCount > 0 ? Math.round((completedCount / totalCount) * 100) : 0;

        if (elements.progressBar) {
            elements.progressBar.style.width = `${percentage}%`;
            elements.progressBar.setAttribute('aria-valuenow', percentage);
            elements.progressBar.textContent = `${percentage}%`;
        }

        if (elements.progressText) {
            elements.progressText.textContent = `${completedCount} von ${totalCount} Schülern`;
        }
    }

    function renderStudentSelect() {
        if (!elements.studentSelect) return;
        elements.studentSelect.innerHTML = '';

        schueler.forEach((currentSchueler, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = `${currentSchueler.nachname}, ${currentSchueler.vorname}`;

            if (isSchuelerComplete(currentSchueler.id)) {
                option.textContent += ' ✓';
            }

            if (currentSchuelerIndex === index) {
                option.selected = true;
            }

            elements.studentSelect.appendChild(option);
        });

        elements.studentSelect.onchange = (event) => {
            currentSchuelerIndex = parseInt(event.target.value, 10);
            if (!isQuestionOrderMode()) {
                currentQuestionIndex = getFirstUnansweredQuestionIndexForStudent(currentSchuelerIndex);
            }
            render();
        };
    }

    function renderTabs() {
        if (!elements.studentTabs) return;
        elements.studentTabs.innerHTML = '';

        schueler.forEach((currentSchueler, index) => {
            const li = document.createElement('li');
            li.className = 'nav-item';

            const link = document.createElement('a');
            link.className = 'nav-link';
            if (currentSchuelerIndex === index) {
                link.classList.add('active');
            }
            link.href = '#';

            const nameSpan = document.createElement('span');
            nameSpan.textContent = `${currentSchueler.nachname}, ${currentSchueler.vorname}`;
            link.appendChild(nameSpan);

            if (isSchuelerComplete(currentSchueler.id)) {
                const icon = document.createElement('i');
                icon.className = 'fas fa-check-circle text-success ml-2';
                link.appendChild(icon);
            }

            link.onclick = (event) => {
                event.preventDefault();
                currentSchuelerIndex = index;
                if (!isQuestionOrderMode()) {
                    currentQuestionIndex = getFirstUnansweredQuestionIndexForStudent(index);
                }
                render();
            };

            li.appendChild(link);
            elements.studentTabs.appendChild(li);
        });
    }

    function renderTabContent() {
        if (!elements.tabContent) return;
        elements.tabContent.innerHTML = '';

        const currentSchueler = getCurrentSchueler();
        if (!currentSchueler) return;

        const answeredCount = getAnsweredQuestionCountForStudent(currentSchueler.id);
        const currentQuestion = getCurrentQuestion();
        const headerMeta = isQuestionOrderMode()
            ? `Aktuelle Frage ${currentQuestionIndex + 1} von ${questions.length}`
            : `${answeredCount} von ${questions.length} Fragen bewertet`;

        const studentHeader = document.createElement('div');
        studentHeader.className = 'student-header mb-3';
        studentHeader.innerHTML = `
            <div class="student-info-card">
                <div class="student-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="student-details">
                    <h5 class="mb-0">${currentSchueler.vorname} ${currentSchueler.nachname}</h5>
                    <small class="text-muted">${headerMeta}</small>
                </div>
                ${isSchuelerComplete(currentSchueler.id) ? '<span class="badge badge-success"><i class="fas fa-check"></i> Vollständig</span>' : '<span class="badge badge-warning"><i class="fas fa-clock"></i> In Bearbeitung</span>'}
            </div>
        `;
        elements.tabContent.appendChild(studentHeader);

        const coachingCard = document.createElement('div');
        coachingCard.className = 'coaching-note-card mb-3';
        coachingCard.innerHTML = `
            <div class="section-label">
                <i class="fas fa-clipboard"></i> Coaching-Protokoll
                <span class="coaching-note-status" id="coachingNoteStatus"></span>
            </div>
            <textarea class="form-control coaching-note-input" id="coachingNoteInput" rows="3"
                      placeholder="Kurze Notiz zum Coaching-Gespräch (optional)..."></textarea>
        `;
        elements.tabContent.appendChild(coachingCard);

        const coachingNoteInput = coachingCard.querySelector('#coachingNoteInput');
        if (coachingNoteInput) {
            coachingNoteInput.value = getCoachingNote(currentSchueler.id);
            coachingNoteInput.onblur = (event) => saveCoachingNote(currentSchueler.id, event.target.value);
        }

        const visibleQuestions = isQuestionOrderMode() && currentQuestion ? [currentQuestion] : questions;

        visibleQuestions.forEach((question) => {
            const qIndex = questions.findIndex(currentItem => currentItem.id === question.id);
            const card = document.createElement('div');
            card.className = 'question-card mb-3';
            card.dataset.questionIndex = String(qIndex);

            const questionHeader = document.createElement('div');
            questionHeader.className = 'question-header';
            questionHeader.innerHTML = `
                <span class="question-number">${qIndex + 1}</span>
                <h6 class="question-text mb-0">${question.question}</h6>
            `;
            card.appendChild(questionHeader);

            const cardBody = document.createElement('div');
            cardBody.className = 'question-body';

            const studentAnswer = getStudentAnswer(currentSchueler.id, question.id);
            const studentSection = document.createElement('div');
            studentSection.className = 'student-answer-section mb-3';

            if (studentAnswer) {
                studentSection.innerHTML = `
                    <div class="section-label">Schüler-Einschätzung:</div>
                    <div class="student-answer">
                        <i class="${getSmileyIcon(studentAnswer)}"></i>
                        <span>${getSmileyLabel(studentAnswer)}</span>
                    </div>
                `;
            } else {
                studentSection.innerHTML = `
                    <div class="section-label">Schüler-Einschätzung:</div>
                    <div class="no-answer">
                        <i class="fas fa-minus-circle"></i> Keine Antwort
                    </div>
                `;
            }
            cardBody.appendChild(studentSection);

            const teacherSection = document.createElement('div');
            teacherSection.className = 'teacher-section';

            const sectionLabel = document.createElement('div');
            sectionLabel.className = 'section-label';
            sectionLabel.textContent = 'Ihre Einschätzung:';
            teacherSection.appendChild(sectionLabel);

            const buttonDiv = document.createElement('div');
            buttonDiv.className = 'rating-buttons mb-3';

            for (let rating = 1; rating <= 5; rating++) {
                const button = document.createElement('button');
                button.className = 'rating-btn';

                const currentRating = getTeacherRating(currentSchueler.id, question.id);
                if (currentRating === rating) {
                    button.classList.add('active');
                }

                const icon = document.createElement('i');
                icon.className = getSmileyIcon(rating);
                button.appendChild(icon);

                const label = document.createElement('span');
                label.className = 'rating-label';
                label.textContent = rating;
                button.appendChild(label);

                button.onclick = () => saveAssessment(currentSchueler.id, question.id, rating);
                button.setAttribute('aria-label', getSmileyLabel(rating));

                buttonDiv.appendChild(button);
            }
            teacherSection.appendChild(buttonDiv);

            const formGroup = document.createElement('div');
            formGroup.className = 'comment-section';

            const textarea = document.createElement('textarea');
            textarea.className = 'form-control comment-input';
            textarea.rows = 2;
            textarea.value = getTeacherComment(currentSchueler.id, question.id);
            textarea.placeholder = 'Optional: Kommentar hinzufügen...';
            textarea.onblur = (event) => saveComment(currentSchueler.id, question.id, event.target.value);

            formGroup.appendChild(textarea);
            teacherSection.appendChild(formGroup);

            cardBody.appendChild(teacherSection);
            card.appendChild(cardBody);
            elements.tabContent.appendChild(card);
        });
    }

    function renderCompleteButton() {
        if (!elements.completeButton) return;

        elements.completeButton.disabled = !isAllComplete();
    }

    function render() {
        updateProgress();
        renderAnswerOrderControls();
        renderQuestionCycleBanner();
        renderQuestionNavigation();
        renderStudentSelect();
        renderTabs();
        renderTabContent();
        renderCompleteButton();
    }

    if (elements.completeButton) {
        elements.completeButton.addEventListener('click', completeSession);
    }

    if (elements.skipButton) {
        elements.skipButton.addEventListener('click', handleSkipButtonClick);
    }

    if (elements.orderByStudentButton) {
        elements.orderByStudentButton.addEventListener('click', () => updateAnswerOrderMode(ANSWER_ORDER_MODES.BY_STUDENT));
    }

    if (elements.orderByQuestionButton) {
        elements.orderByQuestionButton.addEventListener('click', () => updateAnswerOrderMode(ANSWER_ORDER_MODES.BY_QUESTION));
    }

    const autoScrollToggle = document.getElementById('autoScrollToggle');
    if (autoScrollToggle) {
        autoScrollToggle.addEventListener('click', toggleAutoScroll);
        updateAutoScrollUI();
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight' && currentSchuelerIndex < schueler.length - 1) {
            currentSchuelerIndex++;
            if (!isQuestionOrderMode()) {
                currentQuestionIndex = getFirstUnansweredQuestionIndexForStudent(currentSchuelerIndex);
            }
            render();
        } else if (event.key === 'ArrowLeft' && currentSchuelerIndex > 0) {
            currentSchuelerIndex--;
            if (!isQuestionOrderMode()) {
                currentQuestionIndex = getFirstUnansweredQuestionIndexForStudent(currentSchuelerIndex);
            }
            render();
        }
    });

    function init() {
        if (schueler.length === 0) {
            alert('Keine Schüler in dieser Klasse gefunden!');
            return;
        }

        if (questions.length === 0) {
            alert('Keine Fragen für dieses Bewertungssystem gefunden!');
            return;
        }

        const firstPendingStep = findFirstPendingStep();
        if (firstPendingStep) {
            setCurrentStep(firstPendingStep);
        } else {
            const firstIncompleteIndex = schueler.findIndex(currentSchueler => !isSchuelerComplete(currentSchueler.id));
            if (firstIncompleteIndex !== -1) {
                currentSchuelerIndex = firstIncompleteIndex;
                currentQuestionIndex = getFirstUnansweredQuestionIndexForStudent(firstIncompleteIndex);
            }
        }

        render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

<style>
:root {
    --primary: #0d6efd;
    --primary-dark: #0b5ed7;
    --success: #198754;
    --warning: #ffc107;
    --danger: #dc3545;
    --info: #0dcaf0;
    --light: #f8f9fa;
    --dark: #212529;
    --muted: #6c757d;
    --border-radius: 12px;
    --transition: all 0.3s ease;
}

/* Haupt-Card */
.main-card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.main-card .card-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border: none;
    padding: 1rem 1.5rem;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.answer-order-button {
    padding: 0.5rem 0.875rem;
    color: var(--primary);
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}

.answer-order-button:hover,
.answer-order-button.active {
    color: white;
    background: var(--primary);
    border-color: var(--primary);
}

.header-info {
    flex: 1;
}

.header-info h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.25rem;
}

.class-info {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.class-info .badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    font-weight: 500;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
}

.back-btn {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white !important;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: var(--transition);
    white-space: nowrap;
}

.back-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateX(-3px);
}

/* Info-Banner */
.info-banners {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.info-banner {
    flex: 1;
    min-width: 200px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
}

.info-banner-primary {
    background: linear-gradient(135deg, #e7f3ff 0%, #cfe7ff 100%);
    color: #0056b3;
}

.info-banner-success {
    background: linear-gradient(135deg, #d1f4e0 0%, #b8f0cf 100%);
    color: #0f5132;
}

.info-banner-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}

.info-banner-secondary {
    background: linear-gradient(135deg, #e2e3e5 0%, #d6d8db 100%);
    color: #383d41;
}

.info-banner i {
    font-size: 1.1rem;
}

.answer-order-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    flex-wrap: wrap;
}

.answer-order-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.answer-order-toolbar .btn.active {
    color: #fff;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-color: var(--primary);
}

.question-cycle-banner {
    padding: 1rem 1.25rem;
    border-radius: 12px;
    background: linear-gradient(135deg, #eef6ff 0%, #dbeafe 100%);
    border: 1px solid #c9defa;
    color: #0b3d6f;
    box-shadow: 0 2px 10px rgba(13, 110, 253, 0.08);
}

.question-cycle-meta {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin-bottom: 0.35rem;
}

.question-cycle-text {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.35rem;
}

.question-cycle-progress {
    font-size: 0.9rem;
}

.question-navigation {
    padding: 0.85rem 1rem;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.question-navigation-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.question-navigation-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.question-nav-btn {
    min-width: 42px;
    height: 42px;
    border: 2px solid #e9ecef;
    border-radius: 999px;
    background: #fff;
    color: var(--dark);
    font-weight: 700;
    transition: var(--transition);
}

.question-nav-btn:hover {
    border-color: var(--primary);
    background: #f0f7ff;
    transform: translateY(-1px);
}

.question-nav-btn.completed {
    border-color: var(--success);
    color: var(--success);
    background: #edf9f1;
}

.question-nav-btn.active {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-color: var(--primary);
    color: #fff;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
}

/* Fortschrittsanzeige */
.progress-section {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.progress-label {
    font-weight: 600;
    color: var(--dark);
}

.progress-text {
    color: var(--muted);
    font-size: 0.9rem;
}

.progress {
    height: 8px;
    border-radius: 10px;
    background: #e9ecef;
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(90deg, var(--primary) 0%, var(--info) 100%);
    transition: width 0.4s ease;
    font-size: 0.7rem;
    line-height: 8px;
    text-align: center;
    color: transparent;
}

/* Schüler-Navigation */
.student-navigation {
    margin-bottom: 1.5rem;
}

.student-select {
    border-radius: 10px;
    border: 2px solid #e9ecef;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    font-weight: 500;
    transition: var(--transition);
}

.student-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}

.student-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0;
    margin: 0;
    border: none;
}

.student-pills .nav-item {
    margin: 0;
}

.student-pills .nav-link {
    padding: 0.6rem 1rem;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    background: white;
    color: var(--dark);
    font-weight: 500;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.student-pills .nav-link:hover {
    border-color: var(--primary);
    background: #f0f7ff;
    transform: translateY(-2px);
}

.student-pills .nav-link.active {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
}

/* Schüler Header */
.student-header {
    margin-bottom: 1.5rem;
}

.student-info-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.student-details {
    flex: 1;
}

.student-details h5 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

.student-info-card .badge {
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
    border-radius: 8px;
}

/* Coaching-Protokoll */
.coaching-note-card {
    background: #fff8e1;
    border: 1px solid #ffe0a3;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.coaching-note-card .section-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: #8a6d00;
    margin-bottom: 0.5rem;
}

.coaching-note-status {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--success);
    opacity: 0;
    transition: opacity 0.3s ease;
    text-transform: none;
    letter-spacing: normal;
}

.coaching-note-status.saved {
    opacity: 1;
}

.coaching-note-input {
    border-radius: 8px;
    border: 2px solid #ffe0a3;
    padding: 0.75rem;
    font-size: 0.9rem;
    resize: vertical;
    background: white;
}

.coaching-note-input:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.15);
}

/* Fragen-Karten */
.question-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    transition: var(--transition);
    scroll-margin-top: 20px;
}

.question-card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.question-card.highlight-question {
    animation: highlightPulse 1.5s ease;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.3);
}

@keyframes highlightPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(13, 110, 253, 0.2);
        transform: scale(1.02);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
        transform: scale(1);
    }
}

.question-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 2px solid #dee2e6;
}

.question-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.question-text {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    flex: 1;
}

.question-body {
    padding: 1.25rem;
}

/* Schüler-Antwort Bereich */
.student-answer-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.75rem 1rem;
}

.section-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.student-answer {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1rem;
    font-weight: 500;
}

.student-answer i {
    font-size: 1.5rem;
}

.no-answer {
    color: var(--muted);
    font-style: italic;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Lehrer-Einschätzung */
.teacher-section {
    margin-top: 1rem;
}

.rating-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.rating-btn {
    flex: 1;
    min-width: 60px;
    max-width: 80px;
    padding: 0.75rem 0.5rem;
    border: 2px solid #e9ecef;
    background: white;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.rating-btn:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-color: var(--primary);
}

.rating-btn.active {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-color: var(--primary);
    color: white;
    box-shadow: 0 4px 16px rgba(13, 110, 253, 0.4);
}

.rating-btn i {
    font-size: 1.5rem;
}

.rating-btn.active i {
    color: white !important;
}

.rating-label {
    font-size: 0.75rem;
    font-weight: 600;
}

/* Kommentar-Bereich */
.comment-section {
    margin-top: 1rem;
}

.comment-input {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 0.75rem;
    font-size: 0.9rem;
    transition: var(--transition);
    resize: vertical;
}

.comment-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}

/* Sticky Footer */
.action-footer {
    position: sticky;
    bottom: 0;
    background: white;
    padding: 1rem;
    margin: 1.5rem -0.5rem -0.5rem;
    border-top: 2px solid #e9ecef;
    display: flex;
    gap: 1rem;
    justify-content: center;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.05);
    z-index: 10;
}

/* Responsive Anpassungen */
@media (max-width: 767px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .back-btn {
        width: 100%;
        justify-content: center;
    }

    .info-banners {
        flex-direction: column;
    }

    .answer-order-toolbar {
        align-items: flex-start;
    }

    .student-info-card {
        flex-wrap: wrap;
    }

    .rating-buttons {
        gap: 0.4rem;
    }

    .rating-btn {
        min-width: 50px;
        padding: 0.6rem 0.4rem;
    }

    .rating-btn i {
        font-size: 1.3rem;
    }

    .action-footer {
        margin-left: -0.5rem;
        margin-right: -0.5rem;
    }
}

@media (min-width: 768px) and (max-width: 1024px) {
    /* Tablet-spezifische Optimierungen */
    .student-pills {
        max-height: 120px;
        overflow-y: auto;
    }

    .rating-buttons {
        gap: 0.75rem;
    }

    .rating-btn {
        min-width: 70px;
    }

    .question-card {
        margin-bottom: 1rem;
    }
}

</style>
@endsection
