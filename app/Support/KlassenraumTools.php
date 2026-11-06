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
     * vorschau (Bild in public/img/klassenraum/ für die Übersicht).
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
            'beschreibung' => 'Elefanten, Giraffen, Affen und Pinguine. Je leiser es ist, desto mehr Tierbabys trauen sich heraus – und manchmal kommt die Tierpflegerin zum Füttern. Bei Lärm verstecken sich alle im Stall.',
            'icon' => 'fas fa-paw',
            'farbe' => '#c2410c',
            'entry' => 'resources/js/klassenraum/zoo.js',
            'mikrofon' => true,
            'thema' => 'dunkel',
            'vorschau' => 'img/klassenraum/zoo.jpg',
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
