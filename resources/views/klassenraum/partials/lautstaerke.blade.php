{{-- Gemeinsame Oberfläche der Lautstärke-Tools – Logik in resources/js/klassenraum/shared/lautstaerke.js --}}
<canvas id="krt-szene" class="krt-szene" role="img" aria-label="{{ $tool['titel'] }}: {{ $tool['beschreibung'] }}"></canvas>

<header class="krt-leiste">
    <div class="krt-leiste-links">
        <a href="{{ route('klassenraum.index') }}" class="krt-knopf krt-knopf-rund" title="Zurück zur Übersicht" aria-label="Zurück zur Übersicht">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="krt-titel"><b>{{ $tool['titel'] }}</b><span>Klassenraum-Tools</span></div>
    </div>
    <div class="krt-leiste-rechts">
        <button type="button" class="krt-knopf" id="krt-mikro" aria-pressed="false" title="Mikrofon einschalten (M)">
            <i class="fas fa-microphone-slash"></i><span>Zuhören</span>
        </button>
        <button type="button" class="krt-knopf krt-knopf-rund" id="krt-musik" title="Musik ausschalten (T)" aria-label="Musik ausschalten (T)" aria-pressed="true">
            <i class="fas fa-music"></i>
        </button>
        <button type="button" class="krt-knopf krt-knopf-rund" id="krt-vollbild" title="Vollbild (F)" aria-label="Vollbild (F)" aria-pressed="false">
            <i class="fas fa-expand"></i>
        </button>
        <button type="button" class="krt-knopf krt-knopf-akzent" id="krt-einstellungen-oeffnen" aria-controls="krt-panel" aria-expanded="false">
            <i class="fas fa-sliders-h"></i><span>Einstellungen</span>
        </button>
    </div>
</header>

<section class="krt-anzeige" id="krt-anzeige" data-status="aus" aria-label="Raumlautstärke">
    <div class="krt-anzeige-kopf">
        <span class="krt-punkt" aria-hidden="true"></span>
        <b>Raumlautstärke</b>
        <span class="krt-info" id="krt-info"></span>
    </div>
    <div class="krt-balken" aria-hidden="true">
        <div class="krt-balken-fuellung" id="krt-fuellung"></div>
        <div class="krt-balken-grenze" id="krt-grenze-marke"></div>
    </div>
    <div class="krt-anzeige-fuss">
        <span class="krt-status" id="krt-status" aria-live="polite">Mikrofon ist aus</span>
        <span class="krt-grenze">Grenze <b data-anzeige="grenze"></b></span>
    </div>
    <div class="krt-anzeige-aktionen">
        <span class="krt-ruhezeit" id="krt-ruhezeit"></span>
        <button type="button" class="krt-knopf krt-knopf-rund krt-knopf-klein" id="krt-pause" aria-pressed="false" title="Pause (Leertaste)" aria-label="Pause (Leertaste)">
            <i class="fas fa-pause"></i>
        </button>
    </div>
</section>

<div class="krt-uhr" id="krt-uhr"></div>

<div class="krt-start" id="krt-start">
    <div class="krt-start-karte" role="dialog" aria-labelledby="krt-start-titel" aria-describedby="krt-start-text">
        <div class="krt-start-symbol"><i class="fas fa-microphone"></i></div>
        <h2 id="krt-start-titel">{{ $tool['titel'] }}</h2>
        <p id="krt-start-text">{{ $tool['beschreibung'] }}</p>
        <p class="krt-start-datenschutz">
            <i class="fas fa-shield-alt"></i>
            Das Mikrofon wird nur live im Browser ausgewertet. Es wird nichts aufgenommen, gespeichert oder übertragen.
        </p>
        <p class="krt-fehler" id="krt-fehler" role="alert" hidden></p>
        <div class="krt-start-knoepfe">
            <button type="button" class="krt-knopf krt-knopf-akzent krt-knopf-gross" id="krt-start-mikro">
                <i class="fas fa-microphone"></i><span>Zuhören starten</span>
            </button>
            <button type="button" class="krt-knopf krt-knopf-gross" id="krt-start-ohne">
                <span>Ohne Mikrofon ansehen</span>
            </button>
        </div>
    </div>
</div>

<aside class="krt-panel" id="krt-panel" hidden aria-label="Einstellungen">
    <div class="krt-panel-kopf">
        <div>
            <h2>Einstellungen</h2>
            <p>Werden in diesem Browser gespeichert.</p>
        </div>
        <button type="button" class="krt-knopf krt-knopf-rund" data-panel-schliessen title="Schließen" aria-label="Schließen"><i class="fas fa-times"></i></button>
    </div>

    <section class="krt-panel-abschnitt">
        <h3>Mikrofon</h3>
        <label class="krt-regler">
            <span><b>Grenze für „zu laut“</b><em data-anzeige="grenze"></em></span>
            <input type="range" min="15" max="95" step="1" data-einstellung="grenze">
            <small>Die senkrechte Linie in der Lautstärkeanzeige.</small>
        </label>
        <label class="krt-regler">
            <span><b>Empfindlichkeit</b><em data-anzeige="empfindlichkeit" data-format="prozent"></em></span>
            <input type="range" min="0" max="100" step="5" data-einstellung="empfindlichkeit">
            <small>Höher, wenn das Mikrofon weit weg steht oder der Ausschlag zu gering ist.</small>
        </label>
        <label class="krt-regler">
            <span><b>Reaktionszeit</b><em data-anzeige="reaktionszeit" data-format="sekunden"></em></span>
            <input type="range" min="0.5" max="5" step="0.5" data-einstellung="reaktionszeit">
            <small>So lange muss es über der Grenze sein, bevor die Szene reagiert – ein einzelnes Klatschen zählt nicht.</small>
        </label>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="warnton">
            <span><b>Hinweiston bei „zu laut“</b><small>Ein sanfter Zweiklang, höchstens alle acht Sekunden.</small></span>
        </label>
    </section>

    <section class="krt-panel-abschnitt">
        <h3>Musik</h3>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="musik">
            <span><b>Entspannte Hintergrundmusik</b><small>Wird im Browser erzeugt und leiser, wenn es im Raum zu laut wird.</small></span>
        </label>
        <label class="krt-regler">
            <span><b>Lautstärke der Musik</b><em data-anzeige="musikLautstaerke" data-format="prozent"></em></span>
            <input type="range" min="5" max="100" step="5" data-einstellung="musikLautstaerke">
            <small>Die Musik wird beim Messen herausgerechnet, soweit der Browser das kann. Reagiert die Szene schon auf die Musik, die Musik leiser stellen.</small>
        </label>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="weihnachtsmusik">
            <span><b>Weihnachtsmusik im Advent</b><small>Vom 1. Advent bis zum 26. Dezember spielt ein ruhiges Klavier Weihnachtslieder.</small></span>
        </label>
    </section>

    <section class="krt-panel-abschnitt">
        <h3>Szene</h3>
        <label class="krt-regler">
            <span><b id="krt-menge-label">Menge</b><em data-anzeige="menge" data-format="prozent"></em></span>
            <input type="range" min="30" max="150" step="10" data-einstellung="menge">
        </label>
        <label class="krt-regler">
            <span><b>Tempo</b><em data-anzeige="tempo" data-format="prozent"></em></span>
            <input type="range" min="30" max="150" step="10" data-einstellung="tempo">
        </label>
        <label class="krt-regler">
            <span><b>Klassentier</b></span>
            <select class="krt-auswahl" data-einstellung="klassentier">
                @foreach(\App\Support\KlassenraumTools::KLASSENTIERE as $index => $tier)
                    <option value="{{ $index }}">{{ $tier }}</option>
                @endforeach
            </select>
            <small>Guckt ab und zu hervor, wenn es eine Weile ruhig ist – das Maskottchen eurer Klasse.</small>
        </label>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="weihnachtskostuem">
            <span><b>Weihnachtsmütze im Advent</b><small>Vom 1. Advent bis zum 26. Dezember trägt das Klassentier eine Weihnachtsmütze.</small></span>
        </label>
        @isset($tool['auswahl'])
            <label class="krt-regler">
                <span><b>{{ $tool['auswahl']['titel'] }}</b></span>
                <select class="krt-auswahl" data-einstellung="{{ $tool['auswahl']['einstellung'] }}">
                    @foreach($tool['auswahl']['optionen'] as $index => $option)
                        <option value="{{ $index }}">{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        @endisset
    </section>

    <section class="krt-panel-abschnitt">
        <h3>Anzeige</h3>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="anzeige">
            <span><b>Lautstärkeanzeige</b><small>Balken unten links mit Grenze und Status.</small></span>
        </label>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="uhr">
            <span><b>Uhrzeit anzeigen</b></span>
        </label>
        <label class="krt-schalter">
            <input type="checkbox" data-einstellung="ausblenden">
            <span><b>Bedienung ausblenden</b><small>Nach kurzer Zeit ohne Mausbewegung.</small></span>
        </label>
    </section>

    <div class="krt-panel-fuss">
        <button type="button" class="krt-knopf" id="krt-zuruecksetzen"><i class="fas fa-undo"></i><span>Standardwerte</span></button>
        <p>Tastatur: <kbd>M</kbd> Mikrofon · <kbd>T</kbd> Musik · <kbd>F</kbd> Vollbild · <kbd>Leertaste</kbd> Pause</p>
    </div>
</aside>
