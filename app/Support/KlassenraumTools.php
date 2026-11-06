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
    ];

    /**
     * @var array<string, array{titel: string, kategorie: string, beschreibung: string, icon: string, farbe: string, entry: string, mikrofon: bool}>
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
        ],
        'sternenhimmel' => [
            'titel' => 'Sternenhimmel',
            'kategorie' => 'lautstaerke',
            'beschreibung' => 'In der Stille gehen nach und nach Sterne auf, manchmal fällt eine Sternschnuppe. Bei Lärm ziehen Wolken auf.',
            'icon' => 'fas fa-moon',
            'farbe' => '#3730a3',
            'entry' => 'resources/js/klassenraum/sternenhimmel.js',
            'mikrofon' => true,
        ],
        'blumenwiese' => [
            'titel' => 'Blumenwiese',
            'kategorie' => 'lautstaerke',
            'beschreibung' => 'Blumen blühen, Schmetterlinge tanzen. Wird es zu laut, schließen sich die Blüten und es beginnt zu regnen.',
            'icon' => 'fas fa-seedling',
            'farbe' => '#4d7c0f',
            'entry' => 'resources/js/klassenraum/blumenwiese.js',
            'mikrofon' => true,
        ],
        'timer' => [
            'titel' => 'Timer',
            'kategorie' => 'zeit',
            'beschreibung' => 'Großer, gut sichtbarer Countdown mit schwindender Zeitscheibe, Schnellwahl und sanftem Gong am Ende.',
            'icon' => 'fas fa-hourglass-half',
            'farbe' => '#b45309',
            'entry' => 'resources/js/klassenraum/timer.js',
            'mikrofon' => false,
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
