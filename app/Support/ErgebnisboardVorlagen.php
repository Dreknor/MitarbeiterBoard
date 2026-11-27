<?php

namespace App\Support;

use App\Models\ErgebnisVorlage;
use Illuminate\Support\Facades\Schema;

/**
 * Vorlagen der Ergebnisboards (/ergebnisboards).
 *
 * Eine Vorlage beschreibt nur das Raster: `spalten` (CSS-Grid-Template) und die Bereiche mit
 * Grid-Position. Ein einziges Partial (ergebnisboards/partials/raster) rendert alle Vorlagen –
 * eine neue Vorlage braucht also nur einen Eintrag hier (oder im Vorlagen-Editor, Tabelle
 * `ergebnis_vorlagen`). Beim Anlegen eines Boards wird die Definition ins Board kopiert;
 * spätere Änderungen der Vorlage betreffen bestehende Boards nicht.
 *
 * Bereich: schluessel, titel, hinweis?, badge?, farbe (FARBEN), icon? (Font Awesome),
 *          spalte, spalten, zeile, zeilen (Grid-Position, 1-basiert), raster (1|2 Kartenspalten),
 *          eltern? (Unterbereich innerhalb eines anderen Bereichs),
 *          status? (offen|in_arbeit|erledigt – Kopplung an Aufgaben).
 */
class ErgebnisboardVorlagen
{
    public const PERMISSION_VIEW = 'view result boards';

    public const PERMISSION_CREATE = 'create result boards';

    public const PERMISSION_MANAGE = 'manage result boards';

    public const FARBEN = [
        'gruen' => 'Grün',
        'gelb' => 'Gelb',
        'rot' => 'Rot',
        'blau' => 'Blau',
        'lila' => 'Lila',
        'grau' => 'Grau',
        'orange' => 'Orange',
        'tuerkis' => 'Türkis',
    ];

    public const STATUS = ['offen', 'in_arbeit', 'erledigt'];

    public const FARBSCHEMATA = ['klar' => 'Klar & Weiß', 'papier' => 'Warmes Papier'];

    /**
     * @var array<string, array<string, mixed>>
     */
    public const VORLAGEN = [
        'aufwand_ertrag' => [
            'titel' => 'Aufwand-Ertrag-Matrix',
            'beschreibung' => 'Maßnahmen nach Aufwand und pädagogischem Ertrag priorisieren – mit Übergangszone und Spalte für Leitungsaufgaben.',
            'icon' => 'fas fa-th-large',
            'spalten' => '5fr 7fr 3fr',
            'bereiche_frei' => false,
            'achsen' => [
                'y' => ['titel' => 'Aufwand', 'zusatz' => 'Rahmung', 'oben' => 'Langsam', 'unten' => 'Schnell'],
                'x' => ['titel' => 'Ertrag', 'ziel' => 'Erfolg', 'links' => 'Geringer Effekt', 'rechts' => 'Hoher Effekt'],
            ],
            'legende' => [
                'titel' => 'Arbeits-Fahrplan',
                'eintraege' => [
                    ['farbe' => 'gruen', 'text' => '1. Sofort: Quick Wins starten'],
                    ['farbe' => 'blau', 'text' => '2. Zeitnah: Übergangszone planen'],
                    ['farbe' => 'gelb', 'text' => '3. Langfristig: strategische Projekte'],
                ],
            ],
            'bereiche' => [
                ['schluessel' => 'rotten', 'titel' => 'Rotten Tomatoes', 'badge' => 'Zurückstellen', 'hinweis' => 'Maßnahmen, die langsam zu erledigen sind und wenig Effekt haben', 'farbe' => 'rot', 'icon' => 'fas fa-ban', 'spalte' => 1, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 3, 'raster' => 1],
                ['schluessel' => 'cream', 'titel' => 'Cream of the Crop', 'badge' => 'Strategische Langzeitprojekte', 'hinweis' => 'Maßnahmen, die langsam zu erledigen sind, aber einen großen Effekt haben', 'farbe' => 'gelb', 'icon' => 'fas fa-crown', 'spalte' => 2, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 2, 'raster' => 2],
                ['schluessel' => 'seite', 'titel' => 'Klärung & Schulleitung', 'badge' => 'Zielskizze & Rahmen', 'hinweis' => 'Strukturelle Voraussetzungen & Leitungsaufgaben', 'farbe' => 'lila', 'icon' => 'fas fa-compass', 'spalte' => 3, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 6, 'raster' => 1],
                ['schluessel' => 'uebergang', 'titel' => 'Übergangszone', 'badge' => 'Zeitnah anstoßen', 'hinweis' => 'Mittlerer Aufwand & hoher Erfolg', 'farbe' => 'blau', 'icon' => 'fas fa-exchange-alt', 'spalte' => 2, 'spalten' => 1, 'zeile' => 3, 'zeilen' => 2, 'raster' => 2],
                ['schluessel' => 'weeds', 'titel' => 'Harmless Weeds', 'badge' => 'Bei Gelegenheit', 'hinweis' => 'Maßnahmen, die schnell zu erledigen sind, aber nur geringen Effekt haben', 'farbe' => 'grau', 'icon' => 'fas fa-seedling', 'spalte' => 1, 'spalten' => 1, 'zeile' => 4, 'zeilen' => 3, 'raster' => 1],
                ['schluessel' => 'quick_wins', 'titel' => 'Low Hanging Fruits', 'badge' => 'Sofort umsetzen (Quick Wins)', 'hinweis' => 'Maßnahmen, die schnell zu erledigen sind und einen hohen Effekt haben', 'farbe' => 'gruen', 'icon' => 'fas fa-apple-alt', 'spalte' => 2, 'spalten' => 1, 'zeile' => 5, 'zeilen' => 2, 'raster' => 2],
            ],
        ],
        'swot' => [
            'titel' => 'SWOT-Analyse',
            'beschreibung' => 'Stärken, Schwächen, Chancen und Risiken – z. B. für Schulprogramm oder Standortbestimmung.',
            'icon' => 'fas fa-border-all',
            'spalten' => '1fr 1fr',
            'bereiche_frei' => false,
            'bereiche' => [
                ['schluessel' => 'staerken', 'titel' => 'Stärken', 'badge' => 'intern', 'hinweis' => 'Was gelingt uns gut?', 'farbe' => 'gruen', 'icon' => 'fas fa-thumbs-up', 'spalte' => 1, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'schwaechen', 'titel' => 'Schwächen', 'badge' => 'intern', 'hinweis' => 'Wo hakt es?', 'farbe' => 'rot', 'icon' => 'fas fa-thumbs-down', 'spalte' => 2, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'chancen', 'titel' => 'Chancen', 'badge' => 'extern', 'hinweis' => 'Welche Möglichkeiten bieten sich?', 'farbe' => 'blau', 'icon' => 'fas fa-lightbulb', 'spalte' => 1, 'spalten' => 1, 'zeile' => 2, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'risiken', 'titel' => 'Risiken', 'badge' => 'extern', 'hinweis' => 'Was könnte uns gefährden?', 'farbe' => 'orange', 'icon' => 'fas fa-exclamation-triangle', 'spalte' => 2, 'spalten' => 1, 'zeile' => 2, 'zeilen' => 1, 'raster' => 1],
            ],
        ],
        'fahrplan' => [
            'titel' => 'Fahrplan',
            'beschreibung' => 'Maßnahmen nach Zeithorizont ordnen: sofort, zeitnah, langfristig.',
            'icon' => 'fas fa-route',
            'spalten' => '1fr 1fr 1fr',
            'bereiche_frei' => true,
            'bereiche' => [
                ['schluessel' => 'sofort', 'titel' => 'Sofort', 'hinweis' => 'bis zu den nächsten Ferien', 'farbe' => 'gruen', 'icon' => 'fas fa-bolt', 'spalte' => 1, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'zeitnah', 'titel' => 'Zeitnah', 'hinweis' => 'in diesem Schulhalbjahr', 'farbe' => 'blau', 'icon' => 'fas fa-calendar-alt', 'spalte' => 2, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'langfristig', 'titel' => 'Langfristig', 'hinweis' => 'nächstes Schuljahr und später', 'farbe' => 'gelb', 'icon' => 'fas fa-mountain', 'spalte' => 3, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
            ],
        ],
        'start_stop_continue' => [
            'titel' => 'Start · Stop · Continue',
            'beschreibung' => 'Teamreflexion: Was beginnen, was lassen, was beibehalten wir?',
            'icon' => 'fas fa-traffic-light',
            'spalten' => '1fr 1fr 1fr',
            'bereiche_frei' => false,
            'bereiche' => [
                ['schluessel' => 'start', 'titel' => 'Start', 'hinweis' => 'Was beginnen wir?', 'farbe' => 'gruen', 'icon' => 'fas fa-play', 'spalte' => 1, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'stop', 'titel' => 'Stop', 'hinweis' => 'Was lassen wir?', 'farbe' => 'rot', 'icon' => 'fas fa-stop', 'spalte' => 2, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'continue', 'titel' => 'Continue', 'hinweis' => 'Was behalten wir bei?', 'farbe' => 'blau', 'icon' => 'fas fa-redo', 'spalte' => 3, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
            ],
        ],
        'ist_soll' => [
            'titel' => 'Ist → Soll',
            'beschreibung' => 'Ausgangslage und Zielbild gegenüberstellen.',
            'icon' => 'fas fa-arrows-alt-h',
            'spalten' => '1fr 1fr',
            'bereiche_frei' => false,
            'bereiche' => [
                ['schluessel' => 'ist', 'titel' => 'Ist', 'hinweis' => 'So ist es heute', 'farbe' => 'grau', 'icon' => 'fas fa-map-marker-alt', 'spalte' => 1, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'soll', 'titel' => 'Soll', 'hinweis' => 'So soll es werden', 'farbe' => 'gruen', 'icon' => 'fas fa-flag-checkered', 'spalte' => 2, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
            ],
        ],
        'kanban' => [
            'titel' => 'Kanban',
            'beschreibung' => 'Maßnahmen von der Idee bis „Erledigt“ verfolgen – mit Zuständigen und Fälligkeit, Spalten frei änderbar.',
            'icon' => 'fas fa-columns',
            'spalten' => '1fr 1fr 1fr 1fr',
            'bereiche_frei' => true,
            'bereiche' => [
                ['schluessel' => 'ideen', 'titel' => 'Ideen', 'farbe' => 'grau', 'icon' => 'far fa-lightbulb', 'spalte' => 1, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'geplant', 'titel' => 'Geplant', 'farbe' => 'lila', 'icon' => 'far fa-calendar', 'spalte' => 2, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1, 'status' => 'offen'],
                ['schluessel' => 'in_arbeit', 'titel' => 'In Arbeit', 'farbe' => 'blau', 'icon' => 'fas fa-spinner', 'spalte' => 3, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1, 'status' => 'in_arbeit'],
                ['schluessel' => 'erledigt', 'titel' => 'Erledigt', 'farbe' => 'gruen', 'icon' => 'fas fa-check', 'spalte' => 4, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1, 'status' => 'erledigt'],
            ],
        ],
        'umsetzungsstand' => [
            'titel' => 'Umsetzungsstand',
            'beschreibung' => 'Karten mit verknüpfter Aufgabe wandern automatisch: offen → in Arbeit → erledigt.',
            'icon' => 'fas fa-tasks',
            'spalten' => '1fr 1fr 1fr',
            'bereiche_frei' => false,
            'auto_status' => true,
            'bereiche' => [
                ['schluessel' => 'offen', 'titel' => 'Offen', 'farbe' => 'grau', 'icon' => 'far fa-circle', 'spalte' => 1, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1, 'status' => 'offen'],
                ['schluessel' => 'in_arbeit', 'titel' => 'In Arbeit', 'farbe' => 'blau', 'icon' => 'fas fa-spinner', 'spalte' => 2, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1, 'status' => 'in_arbeit'],
                ['schluessel' => 'erledigt', 'titel' => 'Erledigt', 'farbe' => 'gruen', 'icon' => 'fas fa-check', 'spalte' => 3, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1, 'status' => 'erledigt'],
            ],
        ],
        'frei' => [
            'titel' => 'Freie Spalten',
            'beschreibung' => 'Leeres Board mit frei benennbaren Spalten – für alles, wofür es keine Vorlage gibt.',
            'icon' => 'far fa-clone',
            'spalten' => '1fr 1fr 1fr',
            'bereiche_frei' => true,
            'bereiche' => [
                ['schluessel' => 'spalte_1', 'titel' => 'Spalte 1', 'farbe' => 'blau', 'spalte' => 1, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'spalte_2', 'titel' => 'Spalte 2', 'farbe' => 'tuerkis', 'spalte' => 2, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
                ['schluessel' => 'spalte_3', 'titel' => 'Spalte 3', 'farbe' => 'lila', 'spalte' => 3, 'spalten' => 1, 'zeile' => 1, 'zeilen' => 1, 'raster' => 1],
            ],
        ],
    ];

    /**
     * Alle Vorlagen: eingebaute + aktive aus dem Vorlagen-Editor (eigene überschreiben keine eingebauten).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function alle(bool $nurAktive = true): array
    {
        $vorlagen = [];
        foreach (self::VORLAGEN as $schluessel => $vorlage) {
            $vorlagen[$schluessel] = self::normalisieren($vorlage + ['eigene' => false]);
        }

        if (Schema::hasTable('ergebnis_vorlagen')) {
            ErgebnisVorlage::query()
                ->when($nurAktive, fn ($q) => $q->where('aktiv', true))
                ->orderBy('titel')
                ->get()
                ->each(function (ErgebnisVorlage $v) use (&$vorlagen) {
                    if (isset(self::VORLAGEN[$v->schluessel])) {
                        return;
                    }
                    $vorlagen[$v->schluessel] = self::normalisieren(array_merge($v->definition ?? [], [
                        'titel' => $v->titel,
                        'beschreibung' => $v->beschreibung,
                        'eigene' => true,
                        'id' => $v->id,
                    ]));
                });
        }

        return $vorlagen;
    }

    public static function finden(string $schluessel): ?array
    {
        return self::alle(false)[$schluessel] ?? null;
    }

    /**
     * Füllt fehlende Felder einer Vorlage/eines Bereichs mit Standardwerten.
     */
    public static function normalisieren(array $vorlage): array
    {
        $vorlage += [
            'titel' => 'Vorlage',
            'beschreibung' => null,
            'icon' => 'fas fa-th',
            'spalten' => '1fr 1fr 1fr',
            'bereiche_frei' => false,
            'auto_status' => false,
            'achsen' => null,
            'legende' => null,
            'bereiche' => [],
        ];
        $vorlage['bereiche'] = array_values(array_map([self::class, 'bereichNormalisieren'], $vorlage['bereiche']));

        return $vorlage;
    }

    public static function bereichNormalisieren(array $b): array
    {
        $b += [
            'titel' => '',
            'hinweis' => null,
            'badge' => null,
            'farbe' => 'grau',
            'icon' => null,
            'spalte' => 1,
            'spalten' => 1,
            'zeile' => 1,
            'zeilen' => 1,
            'raster' => 1,
            'eltern' => null,
            'status' => null,
        ];
        if (! array_key_exists($b['farbe'], self::FARBEN)) {
            $b['farbe'] = 'grau';
        }
        if (! in_array($b['status'], self::STATUS, true)) {
            $b['status'] = null;
        }
        foreach (['spalte', 'spalten', 'zeile', 'zeilen'] as $feld) {
            $b[$feld] = max(1, min(12, (int) $b[$feld]));
        }
        $b['raster'] = (int) $b['raster'] === 2 ? 2 : 1;

        return $b;
    }

    /** Anzahl der Spalten eines Grid-Templates (für PDF-Tabellen und Validierung). */
    public static function spaltenAnzahl(string $spalten): int
    {
        return max(1, count(preg_split('/\s+/', trim($spalten)) ?: []));
    }

    /** Grid-Template nur aus fr/px/%-Werten zulassen (wird inline als CSS ausgegeben). */
    public static function spaltenGueltig(string $spalten): bool
    {
        return (bool) preg_match('/^(\d{1,4}(\.\d+)?(fr|px|%)\s*){1,12}$/', trim($spalten).' ');
    }
}
