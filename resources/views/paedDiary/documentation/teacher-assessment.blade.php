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
                            <a href="{{ route('gradingDocumentation.groupSession', $session->id) }}" class="btn btn-outline-light btn-sm back-btn">
                                <i class="fas fa-arrow-left"></i> <span class="d-none d-md-inline">Zurück</span>
                            </a>
                        @elseif($session->type === 'individual')
                            <a href="{{ route('gradingDocumentation.individualSession', $session->id) }}" class="btn btn-outline-light btn-sm back-btn">
                                <i class="fas fa-arrow-left"></i> <span class="d-none d-md-inline">Zurück</span>
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
                        <button id="skipButton" class="btn btn-warning btn-action">
                            <i class="fas fa-forward"></i> Überspringen
                        </button>
                        <button id="completeButton" class="btn btn-success btn-action" disabled>
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
    // Daten vom Server
    const sessionId = {{ $session->id }};
    const schueler = @json($schueler);
    const questions = @json($questions);
    const studentAnswers = @json($session->studentAnswers->groupBy('schueler_id')->map(function($answers) {
        return $answers->keyBy('question_id')->map(function($answer) { return $answer->self_rating; });
    }));
    const teacherAssessmentsData = @json($session->teacherAssessments->groupBy('schueler_id')->map(function($assessments) {
        return $assessments->keyBy('question_id');
    }));
    const coachingNotesData = @json($session->coachingNotes->keyBy('schueler_id')->map(function($note) {
        return $note->note;
    }));

    // State
    let currentSchuelerIndex = 0;
    let teacherAssessments = teacherAssessmentsData || {};
    let coachingNotes = coachingNotesData || {};
    let loading = false;
    let noteLoading = false;
    let autoScrollEnabled = localStorage.getItem('teacherAssessment_autoScroll') !== 'false'; // Standard: An

    // Zeige Hinweis wenn Session fortgesetzt wird
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

    // DOM Elemente
    const elements = {
        studentTabs: document.getElementById('studentTabs'),
        studentSelect: document.getElementById('studentSelect'),
        tabContent: document.getElementById('tabContent'),
        completeButton: document.getElementById('completeButton'),
        skipButton: document.getElementById('skipButton'),
        progressBar: document.getElementById('progressBar'),
        progressText: document.getElementById('progressText')
    };

    // Hilfsfunktionen
    function getCurrentSchueler() {
        return schueler[currentSchuelerIndex] || null;
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
        return questions.every(q => {
            return teacherAssessments[schuelerId] &&
                   teacherAssessments[schuelerId][q.id] &&
                   teacherAssessments[schuelerId][q.id].teacher_rating;
        });
    }

    function isAllComplete() {
        // Erlaube Abschluss, wenn mindestens ein Schüler bewertet wurde
        return schueler.some(s => isSchuelerComplete(s.id));
    }

    function skipCurrentStudent() {
        if (loading) return;

        if (currentSchuelerIndex < schueler.length - 1) {
            currentSchuelerIndex++;
            render();
        } else {
            // Beim letzten Schüler zum ersten zurück
            currentSchuelerIndex = 0;
            render();
        }
    }

    function scrollToNextQuestion(currentQuestionId) {
        // Nur scrollen wenn aktiviert
        if (!autoScrollEnabled) return;

        // Finde den Index der aktuellen Frage
        const currentIndex = questions.findIndex(q => q.id === currentQuestionId);

        if (currentIndex === -1) return;

        // Nächste Frage ermitteln
        const nextIndex = currentIndex + 1;

        if (nextIndex < questions.length) {
            // Zur nächsten Frage scrollen
            setTimeout(() => {
                const allCards = document.querySelectorAll('.question-card');
                if (allCards[nextIndex]) {
                    allCards[nextIndex].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    // Optionale visuelle Hervorhebung
                    allCards[nextIndex].classList.add('highlight-question');
                    setTimeout(() => {
                        allCards[nextIndex].classList.remove('highlight-question');
                    }, 1500);
                }
            }, 300);
        } else {
            // Alle Fragen für diesen Schüler beantwortet
            const currentSchueler = getCurrentSchueler();
            if (isSchuelerComplete(currentSchueler.id)) {
                // Zum nächsten Schüler wechseln, wenn nicht der letzte
                if (currentSchuelerIndex < schueler.length - 1) {
                    setTimeout(() => {
                        if (confirm(`Alle Fragen für ${currentSchueler.vorname} ${currentSchueler.nachname} beantwortet! Zum nächsten Schüler wechseln?`)) {
                            currentSchuelerIndex++;
                            render();
                            // Zum Anfang scrollen
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    }, 500);
                } else {
                    // Letzter Schüler komplett - Hinweis auf Abschluss
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

    // API-Funktionen
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
                render();

                // Automatisch zur nächsten Frage scrollen
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

        // Prüfe ob mindestens ein Schüler bewertet wurde
        const hasAnyAssessments = schueler.some(s => isSchuelerComplete(s.id));

        if (!hasAnyAssessments) {
            alert('Bitte bewerten Sie mindestens einen Schüler, bevor Sie die Dokumentation abschließen.');
            return;
        }

        // Prüfe ob Schüler übersprungen wurden
        const skippedStudents = schueler.filter(s => !isSchuelerComplete(s.id));
        let confirmMessage = 'Möchten Sie die Dokumentation wirklich abschließen? Danach können keine Änderungen mehr vorgenommen werden.';

        if (skippedStudents.length > 0) {
            const skippedNames = skippedStudents.map(s => `${s.vorname} ${s.nachname}`).join(', ');
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

    // Render-Funktionen
    function updateProgress() {
        const completedCount = schueler.filter(s => isSchuelerComplete(s.id)).length;
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

        schueler.forEach((s, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = `${s.nachname}, ${s.vorname}`;

            if (isSchuelerComplete(s.id)) {
                option.textContent += ' ✓';
            }

            if (currentSchuelerIndex === index) {
                option.selected = true;
            }

            elements.studentSelect.appendChild(option);
        });

        elements.studentSelect.onchange = (e) => {
            currentSchuelerIndex = parseInt(e.target.value);
            render();
        };
    }

    function renderTabs() {
        if (!elements.studentTabs) return;
        elements.studentTabs.innerHTML = '';

        schueler.forEach((s, index) => {
            const li = document.createElement('li');
            li.className = 'nav-item';

            const a = document.createElement('a');
            a.className = 'nav-link';
            if (currentSchuelerIndex === index) {
                a.classList.add('active');
            }
            a.href = '#';

            const nameSpan = document.createElement('span');
            nameSpan.textContent = `${s.nachname}, ${s.vorname}`;
            a.appendChild(nameSpan);

            if (isSchuelerComplete(s.id)) {
                const icon = document.createElement('i');
                icon.className = 'fas fa-check-circle text-success ml-2';
                a.appendChild(icon);
            }

            a.onclick = (e) => {
                e.preventDefault();
                currentSchuelerIndex = index;
                render();
            };

            li.appendChild(a);
            elements.studentTabs.appendChild(li);
        });
    }

    function renderTabContent() {
        if (!elements.tabContent) return;
        elements.tabContent.innerHTML = '';

        const currentSchueler = getCurrentSchueler();
        if (!currentSchueler) return;

        // Schüler-Info Header
        const studentHeader = document.createElement('div');
        studentHeader.className = 'student-header mb-3';
        studentHeader.innerHTML = `
            <div class="student-info-card">
                <div class="student-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="student-details">
                    <h5 class="mb-0">${currentSchueler.vorname} ${currentSchueler.nachname}</h5>
                    <small class="text-muted">Frage ${1} von ${questions.length}</small>
                </div>
                ${isSchuelerComplete(currentSchueler.id) ? '<span class="badge badge-success"><i class="fas fa-check"></i> Vollständig</span>' : '<span class="badge badge-warning"><i class="fas fa-clock"></i> In Bearbeitung</span>'}
            </div>
        `;
        elements.tabContent.appendChild(studentHeader);

        // Coaching-Protokoll (kurze Notiz je Schüler, unabhängig von den Fragen)
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
            coachingNoteInput.onblur = (e) => saveCoachingNote(currentSchueler.id, e.target.value);
        }

        questions.forEach((question, qIndex) => {
            const card = document.createElement('div');
            card.className = 'question-card mb-3';

            // Question Header mit Nummer
            const questionHeader = document.createElement('div');
            questionHeader.className = 'question-header';
            questionHeader.innerHTML = `
                <span class="question-number">${qIndex + 1}</span>
                <h6 class="question-text mb-0">${question.question}</h6>
            `;
            card.appendChild(questionHeader);

            // Card Body
            const cardBody = document.createElement('div');
            cardBody.className = 'question-body';

            // Selbsteinschätzung (kompakt)
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

            // Lehrereinschätzung
            const teacherSection = document.createElement('div');
            teacherSection.className = 'teacher-section';

            const sectionLabel = document.createElement('div');
            sectionLabel.className = 'section-label';
            sectionLabel.textContent = 'Ihre Einschätzung:';
            teacherSection.appendChild(sectionLabel);

            // Rating Buttons (optimiert für Touch)
            const buttonDiv = document.createElement('div');
            buttonDiv.className = 'rating-buttons mb-3';

            for (let rating = 1; rating <= 5; rating++) {
                const btn = document.createElement('button');
                btn.className = 'rating-btn';

                const currentRating = getTeacherRating(currentSchueler.id, question.id);
                if (currentRating === rating) {
                    btn.classList.add('active');
                }

                const icon = document.createElement('i');
                icon.className = getSmileyIcon(rating);
                btn.appendChild(icon);

                const label = document.createElement('span');
                label.className = 'rating-label';
                label.textContent = rating;
                btn.appendChild(label);

                btn.onclick = () => saveAssessment(currentSchueler.id, question.id, rating);
                btn.setAttribute('aria-label', getSmileyLabel(rating));

                buttonDiv.appendChild(btn);
            }
            teacherSection.appendChild(buttonDiv);

            // Kommentar Textarea (kompakter)
            const formGroup = document.createElement('div');
            formGroup.className = 'comment-section';

            const textarea = document.createElement('textarea');
            textarea.className = 'form-control comment-input';
            textarea.rows = 2;
            textarea.value = getTeacherComment(currentSchueler.id, question.id);
            textarea.placeholder = 'Optional: Kommentar hinzufügen...';
            textarea.onblur = (e) => saveComment(currentSchueler.id, question.id, e.target.value);

            formGroup.appendChild(textarea);
            teacherSection.appendChild(formGroup);

            cardBody.appendChild(teacherSection);
            card.appendChild(cardBody);

            elements.tabContent.appendChild(card);
        });
    }

    function renderCompleteButton() {
        if (!elements.completeButton) return;

        if (isAllComplete()) {
            elements.completeButton.disabled = false;
        } else {
            elements.completeButton.disabled = true;
        }
    }

    function render() {
        console.log('Rendering...');
        updateProgress();
        renderStudentSelect();
        renderTabs();
        renderTabContent();
        renderCompleteButton();
    }

    // Event Listener
    if (elements.completeButton) {
        elements.completeButton.addEventListener('click', completeSession);
    }

    if (elements.skipButton) {
        elements.skipButton.addEventListener('click', skipCurrentStudent);
    }

    // Auto-Scroll Toggle
    const autoScrollToggle = document.getElementById('autoScrollToggle');
    if (autoScrollToggle) {
        autoScrollToggle.addEventListener('click', toggleAutoScroll);
        updateAutoScrollUI();
    }

    // Keyboard Navigation für Tablets
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight' && currentSchuelerIndex < schueler.length - 1) {
            currentSchuelerIndex++;
            render();
        } else if (e.key === 'ArrowLeft' && currentSchuelerIndex > 0) {
            currentSchuelerIndex--;
            render();
        }
    });

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

        // Zum ersten nicht vollständig bewerteten Schüler springen
        const firstIncompleteIndex = schueler.findIndex(s => !isSchuelerComplete(s.id));
        if (firstIncompleteIndex !== -1) {
            currentSchuelerIndex = firstIncompleteIndex;
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

.btn-action {
    flex: 1;
    max-width: 250px;
    padding: 0.85rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 10px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: var(--transition);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-action:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

.btn-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-action i {
    font-size: 1.1rem;
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
