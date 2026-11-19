<?php

namespace App\Support;

/**
 * Verzeichnis der Klassenraum-Tools (/klassenraum).
 *
 * Jedes Tool ist eine eigenständige Vollbildseite für Beamer/Tafel. Ein neues Tool
 * braucht einen Eintrag hier, einen Vite-Entrypoint unter resources/js/klassenraum/
 * (in vite.config.js eintragen) und – falls nötig – eigenes Markup in
 * resources/views/klassenraum/tools/{schluessel}.blade.php.
 *
 * Zugriff für alle Tools über die Permission "use classroom tools".
 */
class KlassenraumTools
{
    public const PERMISSION = 'use classroom tools';

    /**
     * Klassentiere der Lautstärke-Szenen (Einstellung „klassentier“, gespeichert wird der Index;
     * Reihenfolge wie KLASSENTIERE in resources/js/klassenraum/shared/maus.js). Standard: Maus.
     */
    public const KLASSENTIERE = ['Kein Tier', 'Maus', 'Fuchs', 'Faultier', 'Lemur', 'Eule'];

    /**
     * Weihnachtsmusik der Lautstärke-Szenen in der Adventszeit: echte Aufnahmen unter
     * public/audio/klassenraum/weihnachten/ (Lizenz: LIZENZ.txt dort). Alle Stücke von Kevin MacLeod
     * (incompetech.com), CC BY 4.0 – die Nennung muss in den Einstellungen sichtbar bleiben.
     * Neues Stück: Datei (Lautheit −20 LUFS, Mono MP3) ablegen und hier eintragen.
     *
     * @var array<int, array{datei: string, titel: string}>
     */
    public const WEIHNACHTSMUSIK = [
        ['datei' => 'stille-nacht.mp3', 'titel' => 'Silent Night'],
        ['datei' => 'oh-holy-night.mp3', 'titel' => 'Oh Holy Night'],
        ['datei' => 'little-town-of-bethlehem.mp3', 'titel' => 'Oh, Little Town of Bethlehem'],
        ['datei' => 'deck-the-halls.mp3', 'titel' => 'Deck the Halls A'],
        ['datei' => 'angels-we-have-heard-on-high.mp3', 'titel' => 'Angels We Have Heard on High'],
        ['datei' => 'what-child-is-this.mp3', 'titel' => 'What Child is This'],
        ['datei' => 'it-came-upon-a-midnight-clear.mp3', 'titel' => 'It Came Upon a Midnight Clear'],
        ['datei' => 'jingle-bells.mp3', 'titel' => 'Jingle Bells'],
        ['datei' => 'we-wish-you-a-merry-christmas.mp3', 'titel' => 'We Wish you a Merry Christmas'],
        ['datei' => 'adeste-fideles.mp3', 'titel' => 'Adeste Fideles Waltz'],
        ['datei' => 'jesu-joy-of-mans-desiring.mp3', 'titel' => "Jesu, Joy of Man's Desiring"],
        ['datei' => 'dance-of-the-sugar-plum-fairies.mp3', 'titel' => 'Dance of the Sugar Plum Fairies'],
    ];

    public const WEIHNACHTSMUSIK_ORDNER = 'audio/klassenraum/weihnachten';

    /**
     * Weihnachtsmusik für das Skript: [{url, titel}].
     *
     * @return array<int, array{url: string, titel: string}>
     */
    public static function weihnachtsmusik(): array
    {
        return array_map(fn (array $stueck) => [
            'url' => asset(self::WEIHNACHTSMUSIK_ORDNER.'/'.$stueck['datei']),
            'titel' => $stueck['titel'],
        ], self::WEIHNACHTSMUSIK);
    }

    /** Kategorien in Anzeigereihenfolge. */
    public const KATEGORIEN = [
        'lautstaerke' => [
            'titel' => 'Lautstärke',
            'beschreibung' => 'Das Mikrofon misst die Raumlautstärke live im Browser – nichts wird aufgenommen, gespeichert oder übertragen. Wird es zu laut, reagiert die Szene.',
        ],
        'zeit' => [
            'titel' => 'Zeit',
            'beschreibung' => 'Zeit sichtbar machen – für Arbeitsphasen, Aufräumen und Pausen.',
        ],
        'ruhe' => [
            'titel' => 'Ruhe & Ankommen',
            'beschreibung' => 'Kurze Übungen, um nach der Pause oder vor einer Arbeitsphase zur Ruhe zu kommen.',
        ],
    ];

    /**
     * Felder: titel, kategorie, beschreibung, icon (Font Awesome), farbe (Karte), entry (Vite),
     * mikrofon (nutzt die gemeinsame Lautstärke-Oberfläche), thema ('dunkel'|'hell' für die Vollbildseite),
     * vorschau (Bild in public/img/klassenraum/ für die Übersicht), optional auswahl (eigene Auswahlliste
     * der Szene im Einstellungsfeld: einstellung, titel, optionen – gespeichert wird der Index).
     *
     * @var array<string, array{titel: string, kategorie: string, beschreibung: string, icon: string, farbe: string, entry: string, mikrofon: bool, thema: string, vorschau: string}>
     */
    public const TOOLS = [
        'aquarium' => [
            'titel' => 'Aquarium',
            'kategorie' => 'lautstaerke',
            'beschreibung' => 'Fische ziehen ruhig ihre Bahnen. Wird es zu laut, verstecken sie sich und das Wasser trübt sich ein.',
            'icon' => 'fas fa-fish',
            'farbe' => '#0e7490',
            'entry' => 'resources/js/klassenraum/aquarium.js',
            'mikrofon' => true,
            'thema' => 'dunkel',
            'vorschau' => 'img/klassenraum/aquarium.jpg',
        ],
        'sternenhimmel' => [
            'titel' => 'Sternenhimmel',
            'kategorie' => 'lautstaerke',
            'beschreibung' => 'In der Stille gehen nach und nach Sterne auf, manchmal fällt eine Sternschnuppe. Bei Lärm ziehen Wolken auf.',
            'icon' => 'fas fa-moon',
            'farbe' => '#3730a3',
            'entry' => 'resources/js/klassenraum/sternenhimmel.js',
            'mikrofon' => true,
            'thema' => 'dunkel',
            'vorschau' => 'img/klassenraum/sternenhimmel.jpg',
        ],
        'blumenwiese' => [
            'titel' => 'Blumenwiese',
            'kategorie' => 'lautstaerke',
            'beschreibung' => 'Blumen blühen, Schmetterlinge tanzen. Wird es zu laut, schließen sich die Blüten und es beginnt zu regnen.',
            'icon' => 'fas fa-seedling',
            'farbe' => '#4d7c0f',
            'entry' => 'resources/js/klassenraum/blumenwiese.js',
            'mikrofon' => true,
            'thema' => 'dunkel',
            'vorschau' => 'img/klassenraum/blumenwiese.jpg',
        ],
        'zoo' => [
            'titel' => 'Zoo',
            'kategorie' => 'lautstaerke',
            'beschreibung' => 'Elefanten, Giraffen, Zebras, Affen und Pinguine. Je länger es leise ist, desto mehr Tiere kommen nach und nach heraus, zuletzt die Tierbabys – und manchmal kommt die Tierpflegerin zum Füttern. Bei Lärm verstecken sich alle im Stall.',
            'icon' => 'fas fa-paw',
            'farbe' => '#c2410c',
            'entry' => 'resources/js/klassenraum/zoo.js',
            'mikrofon' => true,
            'thema' => 'dunkel',
            'vorschau' => 'img/klassenraum/zoo.jpg',
        ],
        'vulkan' => [
            'titel' => 'Vulkan',
            'kategorie' => 'lautstaerke',
            'beschreibung' => 'Ein uralter Vulkan schlummert friedlich inmitten von Dinosauriern und Urwaldpflanzen. Bleibt es leise, grasen die Dinos entspannt. Wird es zu laut, fängt der Vulkan an zu grollen, Rauch steigt auf und er droht auszubrechen.',
            'icon' => 'fas fa-volcano',
            'farbe' => '#b91c1c',
            'entry' => 'resources/js/klassenraum/vulkan.js',
            'mikrofon' => true,
            'thema' => 'dunkel',
            'vorschau' => 'img/klassenraum/vulkan.jpg',
        ],
        'polarlicht' => [
            'titel' => 'Polarlicht',
            'kategorie' => 'lautstaerke',
            'beschreibung' => 'Nachthimmel über einem stillen See. Je länger es leise ist, desto mehr Polarlichter tanzen am Himmel und spiegeln sich im Wasser. Wird es zu laut, flackern sie und ziehen sich zurück.',
            'icon' => 'fas fa-mountain',
            'farbe' => '#047857',
            'entry' => 'resources/js/klassenraum/polarlicht.js',
            'mikrofon' => true,
            'thema' => 'dunkel',
            'vorschau' => 'img/klassenraum/polarlicht.jpg',
            // eigene Auswahl im Einstellungsfeld (Wert = Index, siehe FARBWELTEN in szenen/polarlicht.js)
            'auswahl' => [
                'einstellung' => 'farben',
                'titel' => 'Farben der Polarlichter',
                'optionen' => ['Bunt', 'Grün', 'Violett', 'Eisblau'],
            ],
        ],
        'timer' => [
            'titel' => 'Timer',
            'kategorie' => 'zeit',
            'beschreibung' => 'Großer, ruhiger Countdown als Zeitscheibe, Sanduhr, Kerze oder Raupe – mit Schnellwahl und sanftem Gong am Ende.',
            'icon' => 'fas fa-hourglass-half',
            'farbe' => '#b45309',
            'entry' => 'resources/js/klassenraum/timer.js',
            'mikrofon' => false,
            'thema' => 'hell',
            'vorschau' => 'img/klassenraum/timer.jpg',
        ],
        'arbeitsphasen' => [
            'titel' => 'Arbeitsphasen',
            'kategorie' => 'zeit',
            'beschreibung' => 'Mehrere Schritte nacheinander, z. B. 8 min Lesen → 2 min Aufräumen → Sitzkreis – mit Gong und Vorschau auf den nächsten Schritt.',
            'icon' => 'fas fa-list-ol',
            'farbe' => '#1d4ed8',
            'entry' => 'resources/js/klassenraum/arbeitsphasen.js',
            'mikrofon' => false,
            'thema' => 'hell',
            'vorschau' => 'img/klassenraum/arbeitsphasen.jpg',
        ],
        'atemuebung' => [
            'titel' => 'Atemübung',
            'kategorie' => 'ruhe',
            'beschreibung' => 'Ein Kreis wächst beim Einatmen und wird beim Ausatmen kleiner – zum Ankommen nach der Pause.',
            'icon' => 'fas fa-wind',
            'farbe' => '#0f766e',
            'entry' => 'resources/js/klassenraum/atemuebung.js',
            'mikrofon' => false,
            'thema' => 'dunkel',
            'vorschau' => 'img/klassenraum/atemuebung.jpg',
        ],
    ];

    /**
     * Tools gruppiert nach Kategorie (nur Kategorien mit mindestens einem Tool).
     *
     * @return array<string, array{titel: string, beschreibung: string, tools: array<string, array>}>
     */
    public static function nachKategorie(): array
    {
        $gruppen = [];

        foreach (self::KATEGORIEN as $schluessel => $kategorie) {
            $tools = array_filter(self::TOOLS, fn (array $tool) => $tool['kategorie'] === $schluessel);

            if ($tools !== []) {
                $gruppen[$schluessel] = $kategorie + ['tools' => $tools];
            }
        }

        return $gruppen;
    }

    public static function finde(string $schluessel): ?array
    {
        return self::TOOLS[$schluessel] ?? null;
    }
}
