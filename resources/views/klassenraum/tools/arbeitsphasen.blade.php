{{-- Klassenraum-Tool „Arbeitsphasen“ – Logik in resources/js/klassenraum/arbeitsphasen.js --}}
<header class="krt-leiste">
    <div class="krt-leiste-links">
        <a href="{{ route('klassenraum.index') }}" class="krt-knopf krt-knopf-rund" title="Zurück zur Übersicht" aria-label="Zurück zur Übersicht">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="krt-titel"><b>{{ $tool['titel'] }}</b><span>Klassenraum-Tools</span></div>
    </div>
    <div class="krt-leiste-rechts">
        <button type="button" class="krt-knopf krt-knopf-rund" id="krt-vollbild" title="Vollbild (F)" aria-label="Vollbild (F)" aria-pressed="false">
            <i class="fas fa-expand"></i>
        </button>
        <button type="button" class="krt-knopf krt-knopf-akzent" id="krt-einstellungen-oeffnen" aria-controls="krt-panel" aria-expanded="false">
            <i class="fas fa-sliders-h"></i><span>Einstellungen</span>
        </button>
    </div>
</header>

<main class="abl" id="abl" data-status="bereit">
    {{-- Planung --}}
    <section class="abl-planung" aria-labelledby="abl-titel">
        <div>
            <h1 id="abl-titel">Arbeitsphasen planen</h1>
            <p class="abl-hinweis">Die Schritte laufen nacheinander ab. Am Ende jedes Schritts erklingt ein Gong, und es ist immer zu sehen, was als Nächstes kommt.</p>
        </div>

        <div class="abl-vorlagen" role="group" aria-label="Vorlagen">
            <span class="abl-hinweis">Vorlagen:</span>
            <button type="button" class="tmr-chip" data-vorlage="lesen">Lesen → Aufräumen → Sitzkreis</button>
            <button type="button" class="tmr-chip" data-vorlage="arbeit">Stillarbeit → Partnerarbeit → Vorstellen</button>
            <button type="button" class="tmr-chip" data-vorlage="stationen">Stationenlernen</button>
            <button type="button" class="tmr-chip" data-vorlage="ankommen">Ankommen → Arbeiten → Bewegung</button>
        </div>

        <ol class="abl-schritte" id="abl-schritte" aria-label="Schritte"></ol>

        <div class="abl-fuss">
            <button type="button" class="krt-knopf" id="abl-hinzufuegen"><i class="fas fa-plus"></i><span>Schritt hinzufügen</span></button>
            <span class="abl-hinweis">0 min = ohne Zeitbegrenzung (z. B. Sitzkreis zum Schluss) · Gesamt: <b id="abl-gesamt"></b></span>
        </div>

        @include('klassenraum.partials.darstellungen')

        <div class="tmr-aktionen">
            <button type="button" class="tmr-start" id="abl-start"><i class="fas fa-play"></i><span>Start</span></button>
        </div>
        <p class="tmr-tasten">
            Nach dem Start sind nur noch der aktuelle Schritt und die Zeit zu sehen. Antippen oder eine Taste zeigt kurz die Steuerung.<br>
            Leertaste: Pause/Weiter · N: nächster Schritt · R: Beenden · F: Vollbild
        </p>
    </section>

    {{-- Ablauf (Fokusmodus) --}}
    <section class="abl-lauf" aria-live="polite">
        <ol class="abl-fortschritt" id="abl-fortschritt" aria-label="Fortschritt"></ol>
        <div class="tmr-scheibe-rahmen" id="tmr-rahmen">
            <div class="tmr-anzeige" id="tmr-anzeige"></div>
        </div>
        <div class="abl-symbol" id="abl-symbol" aria-hidden="true"><i class="fas fa-users"></i></div>
        <div class="abl-name" id="abl-name"></div>
        <div class="tmr-zeit abl-zeit" id="abl-zeit"></div>
        <div class="abl-danach" id="abl-danach"></div>
    </section>
</main>

<div class="tmr-fokusleiste" id="tmr-fokusleiste" role="toolbar" aria-label="Steuerung">
    <button type="button" class="tmr-start" id="abl-weiter"><i class="fas fa-pause"></i><span>Pause</span></button>
    <button type="button" class="krt-knopf" id="abl-naechster" title="Nächster Schritt (N)"><i class="fas fa-step-forward"></i><span>Nächster Schritt</span></button>
    <button type="button" class="krt-knopf" id="abl-plus" title="1 Minute mehr"><i class="fas fa-plus"></i><span>1 min</span></button>
    <button type="button" class="krt-knopf" id="abl-ende" title="Beenden (R)"><i class="fas fa-stop"></i><span>Beenden</span></button>
    <button type="button" class="krt-knopf krt-knopf-rund" id="tmr-fokus-vollbild" title="Vollbild (F)" aria-label="Vollbild (F)" aria-pressed="false">
        <i class="fas fa-expand"></i>
    </button>
    <span class="tmr-fokus-info" id="abl-info"></span>
</div>

<div class="krt-uhr" id="krt-uhr"></div>

<aside class="krt-panel" id="krt-panel" hidden aria-label="Einstellungen">
    <div class="krt-panel-kopf">
        <div>
            <h2>Einstellungen</h2>
            <p>Werden in diesem Browser gespeichert.</p>
        </div>
        <button type="button" class="krt-knopf krt-knopf-rund" data-panel-schliessen title="Schließen" aria-label="Schließen"><i class="fas fa-times"></i></button>
    </div>

    <section class="krt-panel-abschnitt">
        <h3>Ablauf</h3>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="automatisch">
            <span><b>Automatisch weiter</b><small>Der nächste Schritt startet direkt nach dem Gong. Aus: Es geht erst nach „Weiter“ weiter.</small></span>
        </label>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="sekunden">
            <span><b>Sekunden anzeigen</b><small>Aus: ruhige Anzeige in Minuten („noch 7 min“).</small></span>
        </label>
    </section>

    <section class="krt-panel-abschnitt">
        <h3>Gong</h3>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="gong">
            <span><b>Gong beim Schrittwechsel</b></span>
        </label>
        <label class="krt-regler">
            <span><b>Lautstärke des Gongs</b><em data-anzeige="gongLautstaerke" data-format="prozent"></em></span>
            <input type="range" min="10" max="100" step="5" data-einstellung="gongLautstaerke">
        </label>
    </section>

    <section class="krt-panel-abschnitt">
        <h3>Anzeige</h3>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="uhr">
            <span><b>Uhrzeit anzeigen</b><small>Nur beim Planen – während des Ablaufs ausgeblendet.</small></span>
        </label>
    </section>
</aside>
