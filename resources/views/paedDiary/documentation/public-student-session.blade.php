<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Selbsteinschätzung - {{ $schueler->vorname }} {{ $schueler->nachname }}</title>

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
            height: 120px;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 8px 28px rgba(10, 15, 40, 0.06);
            transition: transform 180ms ease, box-shadow 180ms ease;
            border: 2px solid transparent;
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

        .smiley-btn i {
            font-size: 3.5rem;
        }

        .smiley-btn .label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
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

        .completion-message h2 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 15px;
        }

        .completion-message p {
            font-size: 1.2rem;
            color: #666;
        }

        .loading {
            text-align: center;
            padding: 40px;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        @media (max-width: 768px) {
            .smiley-btn {
                width: 90px;
                height: 90px;
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
            <p>{{ $schueler->vorname }} {{ $schueler->nachname }}</p>
        </div>

        <div class="content" id="app">
            <div id="loadingScreen" class="loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Lädt...</span>
                </div>
            </div>

            <div id="questionScreen" style="display: none;">
                <div class="question-container">
                    <div class="question-text" id="questionText"></div>
                    <div class="smiley-buttons" id="smileyButtons">
                        <!-- Buttons werden hier eingefügt -->
                    </div>
                </div>

                <div class="progress-container">
                    <div class="progress-text">
                        <strong>Fortschritt:</strong> <span id="progressText">0 / 0</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%">
                            0%
                        </div>
                    </div>
                </div>
            </div>

            <div id="completionScreen" style="display: none;">
                <div class="completion-message">
                    <i class="fas fa-check-circle"></i>
                    <h2>Fertig! 🎉</h2>
                    <p>Du hast alle Fragen beantwortet. Vielen Dank!</p>
                    <p class="mt-4 text-muted">Du kannst dieses Fenster jetzt schließen.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Daten vom Server
        const token = '{{ $token }}';
        const questions = @json($questions);
        const existingAnswers = @json($session->studentAnswers);

        // State
        let currentQuestionIndex = 0;
        let answers = {};
        let loading = false;

        // Bereits vorhandene Antworten laden
        if (existingAnswers && existingAnswers.length > 0) {
            existingAnswers.forEach(answer => {
                answers[answer.question_id] = answer.self_rating;
            });
        }

        // Smiley-Konfiguration
        const smileys = [
            { rating: 1, icon: 'fas fa-frown', color: '#dc3545', label: 'Sehr schlecht' },
            { rating: 2, icon: 'fas fa-frown-open', color: '#fd7e14', label: 'Schlecht' },
            { rating: 3, icon: 'fas fa-meh', color: '#6c757d', label: 'Mittel' },
            { rating: 4, icon: 'fas fa-smile', color: '#0dcaf0', label: 'Gut' },
            { rating: 5, icon: 'fas fa-grin-stars', color: '#198754', label: 'Sehr gut' }
        ];

        // Hilfsfunktionen
        function getProgress() {
            const answered = Object.keys(answers).length;
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
            return Object.keys(answers).length >= questions.length;
        }

        // Antwort speichern
        async function saveAnswer(rating) {
            const currentQuestion = getCurrentQuestion();
            if (loading || !currentQuestion) return;

            loading = true;

            try {
                const response = await fetch('/paed-diary/documentation/public/student-answer', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
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

                    // Zur nächsten Frage
                    if (currentQuestionIndex < questions.length - 1) {
                        currentQuestionIndex++;
                        render();
                    } else {
                        // Alle Fragen beantwortet
                        showCompletion();
                    }
                } else {
                    alert('Fehler beim Speichern der Antwort. Bitte versuche es erneut.');
                }
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Speichern der Antwort. Bitte versuche es erneut.');
            } finally {
                loading = false;
            }
        }

        // Render-Funktionen
        function renderQuestion() {
            const currentQuestion = getCurrentQuestion();
            if (!currentQuestion) return;

            document.getElementById('questionText').textContent = currentQuestion.question;
        }

        function renderSmileyButtons() {
            const container = document.getElementById('smileyButtons');
            container.innerHTML = '';

            smileys.forEach(smiley => {
                const button = document.createElement('button');
                button.className = 'smiley-btn';
                button.onclick = () => saveAnswer(smiley.rating);

                const icon = document.createElement('i');
                icon.className = smiley.icon;
                icon.style.color = smiley.color;

                const label = document.createElement('div');
                label.className = 'label';
                label.textContent = smiley.label;

                button.appendChild(icon);
                button.appendChild(label);
                container.appendChild(button);
            });
        }

        function renderProgress() {
            const progress = getProgress();
            const progressText = document.getElementById('progressText');
            const progressBar = document.getElementById('progressBar');

            progressText.textContent = `${progress.answered} / ${progress.total}`;
            progressBar.style.width = `${progress.percentage}%`;
            progressBar.textContent = `${progress.percentage}%`;
        }

        function render() {
            renderQuestion();
            renderSmileyButtons();
            renderProgress();
        }

        function showCompletion() {
            document.getElementById('questionScreen').style.display = 'none';
            document.getElementById('completionScreen').style.display = 'block';
        }

        // Initialisierung
        function init() {
            document.getElementById('loadingScreen').style.display = 'none';

            if (questions.length === 0) {
                alert('Keine Fragen gefunden!');
                return;
            }

            // Zur ersten unbeantworteten Frage springen
            for (let i = 0; i < questions.length; i++) {
                if (!answers[questions[i].id]) {
                    currentQuestionIndex = i;
                    break;
                }
            }

            // Prüfen ob bereits alles beantwortet
            if (isCompleted()) {
                showCompletion();
            } else {
                document.getElementById('questionScreen').style.display = 'block';
                render();
            }
        }

        // Warte auf DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    </script>
</body>
</html>
