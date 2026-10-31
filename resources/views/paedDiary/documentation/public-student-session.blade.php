<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Selbsteinschätzung</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        :root {
            --primary: #0d6efd;
            --primary-700: #0b5ed7;
            --success: #198754;
            --radius: 12px;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .main-card {
            max-width: 800px;
            margin: 0 auto;
            border-radius: var(--radius);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .header {
            background: linear-gradient(90deg, var(--primary), var(--primary-700));
            color: white;
            padding: 30px;
            border-radius: var(--radius) var(--radius) 0 0;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .content {
            background: white;
            padding: 40px;
            border-radius: 0 0 var(--radius) var(--radius);
        }

        .question-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .question-counter {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 8px;
        }

        .question-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 30px;
        }

        .smiley-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .smiley-btn {
            width: 120px;
            min-height: 120px;
            padding: 10px 6px;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 28px rgba(10, 15, 40, 0.06);
            transition: transform 180ms ease, box-shadow 180ms ease;
            border: 2px solid #e9ecef;
            background: linear-gradient(180deg, #fff, #f8fafc);
            cursor: pointer;
        }

        .smiley-btn:hover {
            transform: translateY(-6px) scale(1.05);
            box-shadow: 0 16px 40px rgba(10, 15, 40, 0.12);
        }

        .smiley-btn:active {
            transform: translateY(-2px) scale(1.02);
        }

        /* Sichtbarer Tastaturfokus */
        .smiley-btn:focus { outline: none; }
        .smiley-btn:focus-visible,
        .nav-btn:focus-visible {
            outline: 3px solid #1d4ed8;
            outline-offset: 3px;
        }

        .smiley-btn[aria-pressed="true"] {
            border-color: var(--primary);
            background: #e7f0ff;
        }

        .smiley-btn:disabled {
            opacity: 0.6;
            cursor: wait;
        }

        .smiley-btn i {
            font-size: 3.5rem;
        }

        .smiley-btn .label {
            font-size: 0.85rem;
            color: #444;
            font-weight: 500;
            line-height: 1.2;
        }

        .question-nav {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
        }

        .error-message {
            margin-top: 20px;
        }

        .progress-container {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e9ecef;
        }

        .progress-text {
            text-align: center;
            margin-bottom: 15px;
            font-size: 1.1rem;
            color: #666;
        }

        .progress {
            height: 30px;
            border-radius: 15px;
            background: #e9ecef;
        }

        .progress-bar {
            border-radius: 15px;
            background: linear-gradient(90deg, var(--primary), var(--primary-700));
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .completion-message {
            text-align: center;
            padding: 40px;
        }

        .completion-message i {
            font-size: 5rem;
            color: var(--success);
            margin-bottom: 20px;
        }

        .completion-message i.info {
            color: var(--primary);
        }

        .completion-message h2 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 15px;
        }

        .completion-message p {
            font-size: 1.2rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .smiley-btn {
                width: 90px;
                min-height: 100px;
            }

            .smiley-btn i {
                font-size: 2.5rem;
            }

            .question-text {
                font-size: 1.2rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="main-card">
        <div class="header">
            <h1>🎓 Selbsteinschätzung</h1>
            <p>Hallo {{ $vorname }}!</p>
        </div>

        <div class="content" id="app">
            @if($abgeschlossen)
                {{-- Session oder Bewertung des Schülers abgeschlossen: keine Fragen mehr anzeigen --}}
                <div class="completion-message">
                    <i class="fas fa-lock info" aria-hidden="true"></i>
                    <h2>Diese Selbsteinschätzung ist abgeschlossen</h2>
                    <p>Es können keine Antworten mehr abgegeben werden. Vielen Dank!</p>
                    <p class="mt-4 text-muted">Du kannst dieses Fenster jetzt schließen.</p>
                </div>
            @elseif($wartetAufFreigabe)
                <div class="completion-message">
                    <i class="fas fa-hourglass-half info" aria-hidden="true"></i>
                    <h2>Gleich geht's los</h2>
                    <p>Deine Lehrkraft hat noch keine Frage freigegeben.</p>
                    <button type="button" class="btn btn-primary btn-lg mt-3 nav-btn" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt" aria-hidden="true"></i> Neu laden
                    </button>
                </div>
            @elseif(count($questions) === 0)
                <div class="completion-message">
                    <i class="fas fa-info-circle info" aria-hidden="true"></i>
                    <h2>Keine Fragen vorhanden</h2>
                    <p>Für diese Selbsteinschätzung gibt es noch keine Fragen. Bitte sag deiner Lehrkraft Bescheid.</p>
                </div>
            @else
                <div id="questionScreen" style="display: none;">
                    <div class="question-container">
                        <div class="question-counter" id="questionCounter" aria-live="polite"></div>
                        <h2 class="question-text" id="questionText"></h2>
                        <div class="smiley-buttons" id="smileyButtons" role="group" aria-labelledby="questionText">
                            <!-- Buttons werden hier eingefügt -->
                        </div>
                        <div class="alert alert-danger error-message" id="errorMessage" role="alert" style="display: none;"></div>
                        <div class="question-nav">
                            <button type="button" class="btn btn-outline-secondary nav-btn" id="backButton">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i> Zurück
                            </button>
                            <button type="button" class="btn btn-outline-primary nav-btn" id="nextButton">
                                Weiter <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="progress-container">
                        <div class="progress-text">
                            <strong>Fortschritt:</strong> <span id="progressText">0 / 0</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                0%
                            </div>
                        </div>
                    </div>
                </div>

                <div id="completionScreen" style="display: none;">
                    <div class="completion-message">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        @if($nachFrage)
                            <h2>Danke! 🎉</h2>
                            <p>Du hast alle freigegebenen Fragen beantwortet. Warte, bis deine Lehrkraft die nächste Frage freigibt.</p>
                            <button type="button" class="btn btn-primary btn-lg mt-3 nav-btn" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt" aria-hidden="true"></i> Neu laden
                            </button>
                        @else
                            <h2>Fertig! 🎉</h2>
                            <p>Du hast alle Fragen beantwortet. Vielen Dank!</p>
                            <p class="mt-4 text-muted">Du kannst dieses Fenster jetzt schließen.</p>
                        @endif
                        <div class="mt-3">
                            <button type="button" class="btn btn-link nav-btn" id="reviewButton">Antworten noch einmal ansehen</button>
                        </div>
                    </div>
                </div>

                <script>
                    // Daten vom Server
                    const token = @json($token);
                    const saveUrl = @json(route('gradingDocumentation.savePublicStudentAnswer'));
                    const questions = @json($questions);

                    // State
                    let currentQuestionIndex = 0;
                    const answers = Object.assign({}, @json($answers));
                    let loading = false;

                    // Skala positiv formuliert (1 = noch am Anfang, 5 = sicher)
                    const smileys = [
                        { rating: 1, icon: 'fas fa-seedling', color: '#6f42c1', label: 'Klappt noch nicht' },
                        { rating: 2, icon: 'fas fa-meh', color: '#fd7e14', label: 'Klappt manchmal' },
                        { rating: 3, icon: 'fas fa-smile', color: '#0d6efd', label: 'Klappt oft' },
                        { rating: 4, icon: 'fas fa-grin', color: '#20c997', label: 'Klappt fast immer' },
                        { rating: 5, icon: 'fas fa-grin-stars', color: '#198754', label: 'Kann ich sicher' }
                    ];

                    function getProgress() {
                        const answered = questions.filter(q => answers[q.id]).length;
                        return {
                            answered,
                            total: questions.length,
                            percentage: questions.length > 0 ? Math.round((answered / questions.length) * 100) : 0
                        };
                    }

                    function getCurrentQuestion() {
                        return questions[currentQuestionIndex] || null;
                    }

                    function isCompleted() {
                        return questions.every(q => answers[q.id]);
                    }

                    function showError(message) {
                        const el = document.getElementById('errorMessage');
                        el.textContent = message;
                        el.style.display = message ? 'block' : 'none';
                    }

                    async function saveAnswer(rating) {
                        const currentQuestion = getCurrentQuestion();
                        if (loading || !currentQuestion) return;

                        loading = true;
                        showError('');
                        renderSmileyButtons();

                        try {
                            const response = await fetch(saveUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({
                                    token: token,
                                    question_id: currentQuestion.id,
                                    self_rating: rating
                                })
                            });

                            if (response.ok) {
                                answers[currentQuestion.id] = rating;
                                goToNext();
                            } else if (response.status === 409) {
                                // Session wurde inzwischen abgeschlossen → Seite neu laden zeigt den Hinweis
                                window.location.reload();
                            } else {
                                let message = 'Deine Antwort konnte nicht gespeichert werden. Bitte versuche es erneut.';
                                try {
                                    const data = await response.json();
                                    if (data && data.message && response.status !== 500) message = data.message;
                                } catch (e) { /* keine JSON-Antwort */ }
                                showError(message);
                            }
                        } catch (error) {
                            showError('Keine Verbindung. Bitte prüfe das WLAN und versuche es erneut.');
                        } finally {
                            loading = false;
                            if (document.getElementById('questionScreen').style.display !== 'none') {
                                renderSmileyButtons();
                            }
                        }
                    }

                    function goToNext() {
                        if (currentQuestionIndex < questions.length - 1) {
                            currentQuestionIndex++;
                            render();
                        } else if (isCompleted()) {
                            showCompletion();
                        } else {
                            // Zur ersten noch offenen Frage springen
                            currentQuestionIndex = questions.findIndex(q => !answers[q.id]);
                            render();
                        }
                    }

                    function renderQuestion() {
                        const currentQuestion = getCurrentQuestion();
                        if (!currentQuestion) return;

                        document.getElementById('questionCounter').textContent = `Frage ${currentQuestionIndex + 1} von ${questions.length}`;
                        document.getElementById('questionText').textContent = currentQuestion.question;
                    }

                    function renderSmileyButtons() {
                        const container = document.getElementById('smileyButtons');
                        const currentQuestion = getCurrentQuestion();
                        const selected = currentQuestion ? answers[currentQuestion.id] : null;
                        container.innerHTML = '';

                        smileys.forEach(smiley => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'smiley-btn';
                            button.disabled = loading;
                            button.setAttribute('aria-pressed', String(Number(selected) === smiley.rating));
                            button.addEventListener('click', () => saveAnswer(smiley.rating));

                            const icon = document.createElement('i');
                            icon.className = smiley.icon;
                            icon.style.color = smiley.color;
                            icon.setAttribute('aria-hidden', 'true');

                            const label = document.createElement('div');
                            label.className = 'label';
                            label.textContent = smiley.label;

                            button.appendChild(icon);
                            button.appendChild(label);
                            container.appendChild(button);
                        });
                    }

                    function renderNav() {
                        document.getElementById('backButton').disabled = currentQuestionIndex === 0;
                        const current = getCurrentQuestion();
                        // "Weiter" nur, wenn die aktuelle Frage schon beantwortet ist
                        document.getElementById('nextButton').disabled = !(current && answers[current.id]);
                    }

                    function renderProgress() {
                        const progress = getProgress();
                        const progressBar = document.getElementById('progressBar');

                        document.getElementById('progressText').textContent = `${progress.answered} / ${progress.total}`;
                        progressBar.style.width = `${progress.percentage}%`;
                        progressBar.setAttribute('aria-valuenow', String(progress.percentage));
                        progressBar.textContent = `${progress.percentage}%`;
                    }

                    function render() {
                        document.getElementById('completionScreen').style.display = 'none';
                        document.getElementById('questionScreen').style.display = 'block';
                        showError('');
                        renderQuestion();
                        renderSmileyButtons();
                        renderNav();
                        renderProgress();
                    }

                    function showCompletion() {
                        document.getElementById('questionScreen').style.display = 'none';
                        document.getElementById('completionScreen').style.display = 'block';
                    }

                    function init() {
                        document.getElementById('backButton').addEventListener('click', () => {
                            if (currentQuestionIndex > 0) {
                                currentQuestionIndex--;
                                render();
                            }
                        });
                        document.getElementById('nextButton').addEventListener('click', goToNext);
                        document.getElementById('reviewButton').addEventListener('click', () => {
                            currentQuestionIndex = 0;
                            render();
                        });

                        // Zur ersten unbeantworteten Frage springen
                        const offen = questions.findIndex(q => !answers[q.id]);
                        currentQuestionIndex = offen === -1 ? 0 : offen;

                        if (isCompleted()) {
                            showCompletion();
                        } else {
                            render();
                        }
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', init);
                    } else {
                        init();
                    }
                </script>
            @endif
        </div>
    </div>
</body>
</html>
