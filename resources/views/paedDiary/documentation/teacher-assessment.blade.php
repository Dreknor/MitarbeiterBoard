@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Lehrereinschätzung - {{ $session->klasse->name }}</h5>
                        <small class="text-muted">{{ $session->gradingSystem->name }}</small>
                    </div>
                    @if($session->type === 'group')
                        <a href="{{ route('gradingDocumentation.groupSession', $session->id) }}" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Zurück zur Schülereinschätzung
                        </a>
                    @elseif($session->type === 'individual')
                        <a href="{{ route('gradingDocumentation.individualSession', $session->id) }}" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Zurück zur Schülereinschätzung
                        </a>
                    @endif
                </div>
                <div class="card-body" id="teacherApp">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle"></i>
                        <strong>Flexibler Wechsel:</strong> Sie können jederzeit zwischen Lehrereinschätzung und Schülereinschätzung wechseln.
                        Geben Sie für jeden Schüler und jede Frage Ihre Einschätzung ab und fügen Sie bei Bedarf einen Kommentar hinzu.
                    </div>

                    <div class="alert alert-success mb-4">
                        <i class="fas fa-save"></i>
                        <strong>Automatisches Speichern:</strong> Ihre Eingaben werden automatisch gespeichert. Sie können die Session jederzeit unterbrechen und später fortsetzen, solange sie noch nicht abgeschlossen ist.
                    </div>

                    <div class="alert alert-info mb-4" id="resumedAlert" style="display: none;">
                        <i class="fas fa-history"></i>
                        <strong>Session fortgesetzt:</strong> <span id="resumedCount"></span> bereits gespeicherte Bewertungen wurden geladen.
                    </div>

                    <!-- Schüler-Tabs -->
                    <ul class="nav nav-tabs" role="tablist" id="studentTabs">
                        <!-- Tabs werden dynamisch generiert -->
                    </ul>

                    <!-- Tab-Inhalt -->
                    <div class="tab-content mt-3" id="tabContent">
                        <!-- Inhalt wird dynamisch generiert -->
                    </div>

                    <!-- Abschluss-Button -->
                    <div class="text-center mt-4">
                        <button id="completeButton" class="btn btn-success btn-lg" disabled>
                            <i class="fas fa-check"></i> Dokumentation abschließen
                        </button>
                        <p id="completeHint" class="text-muted mt-2">
                            <small>Bitte bewerten Sie mindestens einen Schüler.</small>
                        </p>
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

    // State
    let currentSchuelerIndex = 0;
    let teacherAssessments = teacherAssessmentsData || {};
    let loading = false;

    // Zeige Hinweis wenn Session fortgesetzt wird
    const assessmentCount = Object.values(teacherAssessments).reduce((count, assessments) => {
        return count + Object.keys(assessments).length;
    }, 0);
    if (assessmentCount > 0) {
        const resumedAlert = document.getElementById('resumedAlert');
        const resumedCount = document.getElementById('resumedCount');
        if (resumedAlert && resumedCount) {
            resumedCount.textContent = assessmentCount;
            resumedAlert.style.display = 'block';
        }
    }

    // DOM Elemente
    const elements = {
        studentTabs: document.getElementById('studentTabs'),
        tabContent: document.getElementById('tabContent'),
        completeButton: document.getElementById('completeButton'),
        completeHint: document.getElementById('completeHint')
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
            a.textContent = `${s.nachname}, ${s.vorname}`;

            if (isSchuelerComplete(s.id)) {
                const icon = document.createElement('i');
                icon.className = 'fas fa-check-circle text-success ml-1';
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

        // Überspringen-Button oben
        const skipDiv = document.createElement('div');
        skipDiv.className = 'alert alert-info d-flex justify-content-between align-items-center mb-3';

        const skipInfo = document.createElement('span');
        skipInfo.innerHTML = '<i class="fas fa-info-circle"></i> Sie können diesen Schüler überspringen, wenn aktuell keine Bewertung möglich ist.';

        const skipBtn = document.createElement('button');
        skipBtn.className = 'btn btn-warning btn-sm';
        skipBtn.innerHTML = '<i class="fas fa-forward"></i> Schüler überspringen';
        skipBtn.onclick = skipCurrentStudent;

        skipDiv.appendChild(skipInfo);
        skipDiv.appendChild(skipBtn);
        elements.tabContent.appendChild(skipDiv);

        questions.forEach(question => {
            const card = document.createElement('div');
            card.className = 'card mb-3';

            // Card Header
            const cardHeader = document.createElement('div');
            cardHeader.className = 'card-header';
            const h6 = document.createElement('h6');
            h6.className = 'mb-0';
            h6.textContent = question.question;
            cardHeader.appendChild(h6);
            card.appendChild(cardHeader);

            // Card Body
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body';

            const row = document.createElement('div');
            row.className = 'row';

            // Linke Spalte - Selbsteinschätzung
            const colLeft = document.createElement('div');
            colLeft.className = 'col-md-6';

            const h6Left = document.createElement('h6');
            h6Left.className = 'text-muted';
            h6Left.textContent = 'Selbsteinschätzung des Schülers:';
            colLeft.appendChild(h6Left);

            const studentAnswer = getStudentAnswer(currentSchueler.id, question.id);
            if (studentAnswer) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-light';

                const icon = document.createElement('i');
                icon.className = getSmileyIcon(studentAnswer);
                icon.style.fontSize = '2rem';

                const span = document.createElement('span');
                span.className = 'ml-2';
                span.textContent = getSmileyLabel(studentAnswer);

                alertDiv.appendChild(icon);
                alertDiv.appendChild(span);
                colLeft.appendChild(alertDiv);
            } else {
                const noAnswer = document.createElement('div');
                noAnswer.className = 'text-muted';
                noAnswer.innerHTML = '<i class="fas fa-times-circle"></i> Keine Antwort';
                colLeft.appendChild(noAnswer);
            }

            // Rechte Spalte - Lehrereinschätzung
            const colRight = document.createElement('div');
            colRight.className = 'col-md-6';

            const h6Right = document.createElement('h6');
            h6Right.className = 'text-primary';
            h6Right.textContent = 'Ihre Einschätzung:';
            colRight.appendChild(h6Right);

            // Rating Buttons
            const buttonDiv = document.createElement('div');
            buttonDiv.className = 'd-flex justify-content-center mb-3';

            for (let rating = 1; rating <= 5; rating++) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-md mx-1 teacher-smiley-btn';

                const currentRating = getTeacherRating(currentSchueler.id, question.id);
                if (currentRating === rating) {
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.add('btn-outline-secondary');
                }

                const icon = document.createElement('i');
                icon.className = getSmileyIcon(rating);
                icon.style.fontSize = '2rem';
                btn.appendChild(icon);

                const label = document.createElement('div');
                label.className = 'small mt-1';
                label.textContent = getSmileyLabel(rating);
                btn.appendChild(label);

                btn.onclick = () => saveAssessment(currentSchueler.id, question.id, rating);

                buttonDiv.appendChild(btn);
            }
            colRight.appendChild(buttonDiv);

            // Kommentar Textarea
            const formGroup = document.createElement('div');
            formGroup.className = 'form-group';

            const label = document.createElement('label');
            label.textContent = 'Kommentar:';

            const textarea = document.createElement('textarea');
            textarea.className = 'form-control';
            textarea.rows = 3;
            textarea.value = getTeacherComment(currentSchueler.id, question.id);
            textarea.placeholder = 'Optional: Fügen Sie hier einen Kommentar hinzu...';
            textarea.onblur = (e) => saveComment(currentSchueler.id, question.id, e.target.value);

            formGroup.appendChild(label);
            formGroup.appendChild(textarea);
            colRight.appendChild(formGroup);

            row.appendChild(colLeft);
            row.appendChild(colRight);
            cardBody.appendChild(row);
            card.appendChild(cardBody);

            elements.tabContent.appendChild(card);
        });
    }

    function renderCompleteButton() {
        if (!elements.completeButton || !elements.completeHint) return;

        if (isAllComplete()) {
            elements.completeButton.disabled = false;
            elements.completeHint.style.display = 'none';
        } else {
            elements.completeButton.disabled = true;
            elements.completeHint.style.display = 'block';
        }
    }

    function render() {
        console.log('Rendering...');
        renderTabs();
        renderTabContent();
        renderCompleteButton();
    }

    // Event Listener
    elements.completeButton.addEventListener('click', completeSession);

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
:root{
    --primary: #0d6efd; /* Bootstrap primary */
    --primary-600: #0b5ed7;
    --muted: #6c757d;
    --card-bg: #ffffff;
    --glass: rgba(255,255,255,0.6);
    --radius: 12px;
}

/* Grundlegendes */
.teacher-smiley-btn, .smiley-btn, .btn-smiley {
    min-width: 88px;
    padding: 0.6rem 0.75rem;
    border-radius: 10px;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    box-shadow: 0 6px 18px rgba(20,20,50,0.06);
    transition: transform 200ms ease, box-shadow 200ms ease, background-color 200ms ease;
    cursor: pointer;
    border: none;
    background: linear-gradient(180deg, #fff 0%, #f8f9fa 100%);
}

.teacher-smiley-btn:hover, .smiley-btn:hover, .btn-smiley:hover {
    transform: translateY(-4px) scale(1.03);
    box-shadow: 0 10px 30px rgba(20,20,50,0.12);
}

.teacher-smiley-btn.btn-primary, .btn-smiley.active {
    background: linear-gradient(90deg, var(--primary) 0%, #0b5ed7 100%);
    color: #fff;
}

.teacher-smiley-btn i, .smiley-btn i {
    font-size: 1.8rem;
}

.teacher-smiley-btn .small, .smiley-btn .small {
    font-size: 0.75rem;
}

/* Karten & Allgemeines */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}
.card-header {
    background: linear-gradient(90deg, rgba(13,110,253,0.98), rgba(11,94,215,0.9));
    color: #fff;
    border-bottom: none;
    padding: 1rem 1.25rem;
}
.card-header .mb-0 { font-weight: 600; }
.card-body { padding: 1.25rem; background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(250,251,252,0.98)); }

/* Tabs moderner Look */
.nav-tabs {
    border-bottom: none;
    gap: 0.5rem;
}
.nav-tabs .nav-item .nav-link {
    background: transparent;
    border: none;
    color: #6c757d;
    padding: 0.55rem 0.85rem;
    border-radius: 8px;
    transition: background-color 180ms ease, color 180ms ease, transform 180ms ease;
}
.nav-tabs .nav-item .nav-link:hover { background: rgba(13,110,253,0.06); transform: translateY(-2px); }
.nav-tabs .nav-item .nav-link.active {
    background: linear-gradient(90deg, var(--primary) 0%, #0b5ed7 100%);
    color: #fff;
    box-shadow: 0 8px 24px rgba(13,110,253,0.14);
}

/* Alerts */
.alert { border-radius: 10px; }
.alert-info { background: linear-gradient(90deg, #e9f2ff, #f7fbff); color: #08325a; }
.alert-warning { background: linear-gradient(90deg,#fff4e5,#fffaf0);}

/* Kommentar-Textarea */
.form-control { border-radius: 8px; box-shadow: none; border: 1px solid #e6e9ef; }
.form-control:focus { border-color: var(--primary); box-shadow: 0 6px 18px rgba(13,110,253,0.08); }

/* Abschluss-Button */
#completeButton { padding: 0.8rem 1.4rem; border-radius: 10px; font-size: 1.05rem; }
#completeButton[disabled] { opacity: 0.6; transform: none; }
#completeHint { margin-top: 0.6rem; }

/* Responsive Anpassungen */
@media (max-width: 768px) {
    .teacher-smiley-btn { min-width: 64px; padding: 0.45rem; }
    .card-header { padding: 0.75rem; }
}

</style>
@endsection
