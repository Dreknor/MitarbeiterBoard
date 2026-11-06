{{-- Klassenraum-Tool „Timer“ – Logik in resources/js/klassenraum/timer.js --}}
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

<main class="tmr" id="tmr" data-status="bereit">
    <div class="tmr-scheibe-rahmen">
        <svg class="tmr-scheibe" viewBox="0 0 200 200" role="img" aria-labelledby="tmr-zeit">
            <circle class="tmr-ziffernblatt" cx="100" cy="100" r="96"></circle>
            <g class="tmr-striche" id="tmr-striche"></g>
            <path class="tmr-keil" id="tmr-keil" d=""></path>
            <circle class="tmr-nabe" cx="100" cy="100" r="5"></circle>
        </svg>
    </div>

    <div class="tmr-mitte">
        <label class="sr-only" for="tmr-aufgabe">Wofür läuft die Zeit?</label>
        <input type="text" id="tmr-aufgabe" class="tmr-aufgabe" maxlength="60" placeholder="Wofür läuft die Zeit?" autocomplete="off">

        <div class="tmr-zeit" id="tmr-zeit" aria-live="off">5:00</div>
        <div class="tmr-hinweis" id="tmr-hinweis" aria-live="polite">&nbsp;</div>

        <div class="tmr-bedienung">
            <div class="tmr-schnellwahl" role="group" aria-label="Schnellwahl">
                @foreach([1, 2, 3, 5, 10, 15, 20, 30, 45, 60] as $minuten)
                    <button type="button" class="tmr-chip" data-minuten="{{ $minuten }}">{{ $minuten }}&nbsp;min</button>
                @endforeach
            </div>

            <div class="tmr-eigene" role="group" aria-label="Eigene Dauer">
                <button type="button" class="krt-knopf krt-knopf-rund" data-plus="-60" title="1 Minute weniger" aria-label="1 Minute weniger"><i class="fas fa-minus"></i></button>
                <label class="tmr-feld">
                    <input type="number" id="tmr-minuten" min="0" max="180" inputmode="numeric" aria-label="Minuten">
                    <span>min</span>
                </label>
                <label class="tmr-feld">
                    <input type="number" id="tmr-sekunden" min="0" max="59" step="5" inputmode="numeric" aria-label="Sekunden">
                    <span>s</span>
                </label>
                <button type="button" class="krt-knopf krt-knopf-rund" data-plus="60" title="1 Minute mehr" aria-label="1 Minute mehr"><i class="fas fa-plus"></i></button>
            </div>

            <div class="tmr-aktionen">
                <button type="button" class="tmr-start" id="tmr-start"><i class="fas fa-play"></i><span>Start</span></button>
                <button type="button" class="krt-knopf" id="tmr-reset" title="Zurücksetzen (R)"><i class="fas fa-undo"></i><span>Zurücksetzen</span></button>
            </div>
            <p class="tmr-tasten">
                Nach dem Start ist nur noch die Zeitscheibe zu sehen. Antippen oder eine Taste zeigt kurz die Steuerung.<br>
                Leertaste: Start/Pause · R: Beenden · F: Vollbild
            </p>
        </div>
    </div>
</main>

{{-- Fokusmodus: kompakte Steuerung, erscheint nur kurz nach Antippen/Taste (bei Pause/Ende dauerhaft) --}}
<div class="tmr-fokusleiste" id="tmr-fokusleiste" role="toolbar" aria-label="Timer-Steuerung">
    <button type="button" class="tmr-start" id="tmr-fokus-start"><i class="fas fa-pause"></i><span>Pause</span></button>
    <button type="button" class="krt-knopf" data-plus="60" title="1 Minute mehr"><i class="fas fa-plus"></i><span>1 min</span></button>
    <button type="button" class="krt-knopf" id="tmr-fokus-ende" title="Beenden (R)"><i class="fas fa-stop"></i><span>Beenden</span></button>
    <button type="button" class="krt-knopf krt-knopf-rund" id="tmr-fokus-vollbild" title="Vollbild (F)" aria-label="Vollbild (F)" aria-pressed="false">
        <i class="fas fa-expand"></i>
    </button>
    <span class="tmr-fokus-info" id="tmr-fokus-info"></span>
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
        <h3>Farbe der Zeitscheibe</h3>
        <div class="tmr-farben" role="radiogroup" aria-label="Farbe der Zeitscheibe">
            @foreach([['rot', '#e11d48', 'Rot'], ['orange', '#ea580c', 'Orange'], ['gruen', '#16a34a', 'Grün'], ['blau', '#2563eb', 'Blau'], ['lila', '#7c3aed', 'Lila']] as [$name, $farbe, $bezeichnung])
                <button type="button" class="tmr-farbe" role="radio" data-farbe="{{ $name }}" style="--farbe: {{ $farbe }}" aria-label="{{ $bezeichnung }}" title="{{ $bezeichnung }}"></button>
            @endforeach
        </div>
    </section>

    <section class="krt-panel-abschnitt">
        <h3>Ende</h3>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="gong">
            <span><b>Gong am Ende</b><small>Sanfter Klangschalen-Ton, wenn die Zeit abgelaufen ist.</small></span>
        </label>
        <label class="krt-regler">
            <span><b>Lautstärke des Gongs</b><em data-anzeige="gongLautstaerke" data-format="prozent"></em></span>
            <input type="range" min="10" max="100" step="5" data-einstellung="gongLautstaerke">
        </label>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="ueberziehen">
            <span><b>Weiterzählen nach Ablauf</b><small>Zeigt, wie viel Zeit seit dem Ende vergangen ist.</small></span>
        </label>
    </section>

    <section class="krt-panel-abschnitt">
        <h3>Anzeige</h3>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="sekunden">
            <span><b>Sekunden anzeigen</b><small>Während der Timer läuft. Aus: ruhige Anzeige in Minuten („noch 7 min“) ohne ständig wechselnde Ziffern.</small></span>
        </label>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="uhr">
            <span><b>Uhrzeit anzeigen</b><small>Nur beim Einstellen – während der Timer läuft, ist sie ausgeblendet.</small></span>
        </label>
    </section>
</aside>
