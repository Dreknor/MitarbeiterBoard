{{-- Klassenraum-Tool „Atemübung“ – Logik in resources/js/klassenraum/atemuebung.js --}}
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
    </div>
</header>

<main class="atm" id="atm" data-status="bereit">
    <div class="atm-buehne" aria-hidden="true">
        <div class="atm-ring"></div>
        <div class="atm-kreis" id="atm-kreis"></div>
        <div class="atm-text"><b id="atm-phase">Bereit?</b><span id="atm-zahl"></span></div>
    </div>

    <div class="atm-rest" id="atm-rest" aria-live="polite"></div>

    <section class="atm-planung" aria-labelledby="atm-titel">
        <div>
            <h1 id="atm-titel">Gemeinsam ankommen</h1>
            <p class="atm-hinweis">Alle atmen mit dem Kreis: Wenn er wächst, langsam einatmen – wenn er kleiner wird, langsam ausatmen.</p>
        </div>

        <div class="atm-gruppe">
            <span class="atm-label">Rhythmus</span>
            <div class="atm-auswahl" role="radiogroup" aria-label="Rhythmus" data-gruppe="rhythmus">
                <button type="button" class="atm-chip" role="radio" data-wert="ruhig">Ruhig <small>4 ein · 6 aus</small></button>
                <button type="button" class="atm-chip" role="radio" data-wert="gleich">Gleichmäßig <small>4 ein · 4 aus</small></button>
                <button type="button" class="atm-chip" role="radio" data-wert="viereck">Viereck <small>4 · halten 4 · 4 · halten 4</small></button>
                <button type="button" class="atm-chip" role="radio" data-wert="kurz">Für die Kleinen <small>3 ein · 3 aus</small></button>
            </div>
        </div>

        <div class="atm-gruppe">
            <span class="atm-label">Dauer</span>
            <div class="atm-auswahl" role="radiogroup" aria-label="Dauer" data-gruppe="dauer">
                @foreach([1, 2, 3, 5] as $minuten)
                    <button type="button" class="atm-chip" role="radio" data-wert="{{ $minuten }}">{{ $minuten }}&nbsp;min</button>
                @endforeach
                <button type="button" class="atm-chip" role="radio" data-wert="0">ohne Ende</button>
            </div>
        </div>

        <div class="atm-schalter-reihe">
            <label class="krt-schalter">
                <input type="checkbox" data-einstellung="klang">
                <span><b>Sanfter Klang</b><small>Ein leiser Ton beim Ein- und Ausatmen.</small></span>
            </label>
            <label class="krt-schalter">
                <input type="checkbox" data-einstellung="musik">
                <span><b>Ruhige Musik</b><small>Leise Klangfläche im Hintergrund.</small></span>
            </label>
        </div>

        <div>
            <button type="button" class="atm-start" id="atm-start"><i class="fas fa-play"></i><span>Start</span></button>
        </div>
    </section>
</main>

<div class="tmr-fokusleiste atm-leiste" id="tmr-fokusleiste" role="toolbar" aria-label="Steuerung">
    <button type="button" class="atm-start" id="atm-weiter"><i class="fas fa-pause"></i><span>Pause</span></button>
    <button type="button" class="krt-knopf" id="atm-ende" title="Beenden (R)"><i class="fas fa-stop"></i><span>Beenden</span></button>
    <button type="button" class="krt-knopf krt-knopf-rund" id="tmr-fokus-vollbild" title="Vollbild (F)" aria-label="Vollbild (F)" aria-pressed="false">
        <i class="fas fa-expand"></i>
    </button>
</div>
