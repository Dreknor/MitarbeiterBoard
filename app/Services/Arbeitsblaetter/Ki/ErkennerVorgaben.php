<?php

namespace App\Services\Arbeitsblaetter\Ki;

use App\Support\AbStruktur;

/**
 * Gemeinsame Vorgaben aller KI-Treiber: Prompt, JSON-Schema der Antwort, Umwandlung ins
 * Baustein-Format, robustes Lesen der JSON-Antwort und Kostenschätzung aus Token.
 * Gesendet werden immer nur Seitenbilder/PDF, ggf. die Textebene und dieser Prompt.
 */
final class ErkennerVorgaben
{
    public const AUFTRAG = 'Erfasse dieses Arbeitsblatt vollständig als Bausteine nach den Regeln. Antworte nur mit JSON nach dem Schema.';

    public const TEXT_HINWEIS = "Textebene des PDFs (kann Fehler und falsche Reihenfolge enthalten, nur als Hilfe):
";

    /** JSON aus einer Modellantwort lesen (auch mit Code-Zaun oder Vor-/Nachtext). */
    public static function jsonLesen(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $m)) {
            $text = $m[1];
        }
        $daten = json_decode($text, true);
        if (! is_array($daten)) {
            $anfang = strpos($text, '{');
            $ende = strrpos($text, '}');
            if ($anfang !== false && $ende > $anfang) {
                $daten = json_decode(substr($text, $anfang, $ende - $anfang + 1), true);
            }
        }

        return is_array($daten) ? $daten : null;
    }

    /** Rohantwort → normalisierte Struktur. */
    public static function struktur(array $roh): array
    {
        return AbStruktur::normalisieren(self::umwandeln($roh));
    }

    /** Kosten in Cent aus Token und Preisen (Cent je 1 Mio. Token). */
    public static function kostenCent(int $eingabe, int $ausgabe, int $preisEingabe, int $preisAusgabe): int
    {
        return (int) ceil($eingabe / 1_000_000 * $preisEingabe + $ausgabe / 1_000_000 * $preisAusgabe);
    }

    /** KI-Format (flach, `seite`/`box` am Bild) → Baustein-Format. */
    public static function umwandeln(array $roh): array
    {
        $baustein = function (array $b) use (&$baustein) {
            if (($b['typ'] ?? '') === 'bild' && isset($b['box'])) {
                $b['quelle'] = ['seite' => (int) ($b['seite'] ?? 1), 'box' => $b['box']];
            }
            unset($b['seite'], $b['box']);
            if (isset($b['kinder']) && is_array($b['kinder'])) {
                $b['kinder'] = array_map($baustein, array_filter($b['kinder'], 'is_array'));
            }

            return $b;
        };

        return [
            'seiten' => (int) ($roh['seiten'] ?? 1),
            'bausteine' => array_map($baustein, array_filter((array) ($roh['bausteine'] ?? []), 'is_array')),
        ];
    }

    public static function schema(): array
    {
        $segment = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['typ'],
            'properties' => [
                'typ' => ['type' => 'string', 'enum' => ['text', 'luecke']],
                'text' => ['type' => 'string'],
                'laenge' => ['type' => 'integer', 'description' => 'Ungefähre Anzahl Zeichen, die in die Lücke passen'],
            ],
        ];
        $felder = [
            'typ' => ['type' => 'string', 'enum' => array_keys(AbStruktur::TYPEN)],
            'text' => ['type' => 'string', 'description' => 'Text für ueberschrift und anweisung'],
            'nummer' => ['type' => 'string', 'description' => 'Aufgabennummer wie im Original, z. B. „1“ oder „b)“'],
            'inhalt' => ['type' => 'array', 'items' => $segment, 'description' => 'Für text und aufgabe: Text und Lücken in Lesereihenfolge'],
            'frage' => ['type' => 'string'],
            'optionen' => ['type' => 'array', 'items' => ['type' => 'string']],
            'mehrfach' => ['type' => 'boolean'],
            'zeilen' => ['type' => 'array', 'items' => ['type' => 'array', 'items' => ['type' => 'string']]],
            'kopfzeile' => ['type' => 'boolean'],
            'anzahl' => ['type' => 'integer', 'description' => 'Anzahl der Schreiblinien'],
            'beschreibung' => ['type' => 'string', 'description' => 'Kurze Beschreibung der Abbildung'],
            'seite' => ['type' => 'integer', 'description' => 'Seite der Abbildung (ab 1)'],
            'box' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => 'Relative Box [x1, y1, x2, y2] der Abbildung auf der Seite, Werte 0 bis 1'],
        ];
        $kind = ['type' => 'object', 'additionalProperties' => false, 'required' => ['typ'], 'properties' => $felder];
        $baustein = ['type' => 'object', 'additionalProperties' => false, 'required' => ['typ'], 'properties' => array_merge($felder, [
            'kinder' => ['type' => 'array', 'items' => $kind, 'description' => 'Teilaufgaben einer aufgabe (eine Ebene)'],
        ])];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['seiten', 'bausteine'],
            'properties' => [
                'seiten' => ['type' => 'integer'],
                'bausteine' => ['type' => 'array', 'items' => $baustein],
            ],
        ];
    }

    public const PROMPT = <<<'TXT'
Du erfasst Arbeitsblätter aus Schulen (Fotos oder PDF), damit sie für Kinder mit Sehbehinderung in großer Schrift neu gesetzt werden können. Eine Lehrkraft prüft dein Ergebnis anschließend.

Gib den Inhalt in Lesereihenfolge als Bausteine aus. Übernimm Texte wörtlich (Rechtschreibung wie im Original, nichts ergänzen, nichts lösen, keine Lösungen eintragen). Kein Layout: keine Schriftgrößen, Farben oder Positionen – außer der Box bei Abbildungen.

Bausteine:
- ueberschrift: Titel des Blatts oder eines Abschnitts (text).
- anweisung: Arbeitsanweisung an die Kinder, z. B. „Ergänze …“, „Kreuze an …“ (text).
- aufgabe: nummerierte Aufgabe (nummer wie im Original, inhalt = Text und Lücken). Teilaufgaben a), b) … als kinder der Aufgabe.
- text: sonstiger Fließtext (inhalt).
- ankreuzen: Auswahl mit Kästchen (frage, optionen; mehrfach = true, wenn mehrere angekreuzt werden dürfen).
- tabelle: Tabelle (zeilen = Zellen als Text, leere Zellen als ""; kopfzeile = true, wenn die erste Zeile Überschriften enthält).
- schreiblinien: freie Linien zum Schreiben (anzahl).
- bild: Abbildung, Grafik, Skizze oder Diagramm, das zum Lösen gebraucht wird (beschreibung kurz; seite; box = [x1, y1, x2, y2] relativ zur Seite, 0 = links/oben, 1 = rechts/unten, großzügig um die Abbildung). Reine Deko (Cliparts ohne Bedeutung, Rahmen, Logos) weglassen.

Lücken: Unterstriche, leere Kästchen oder freie Stellen zum Eintragen werden zu {"typ": "luecke", "laenge": n} mit der ungefähren Zahl der Zeichen, die hineinpassen. Mathematische Ausdrücke als lesbarer Text (z. B. „3/4“, „2 · 5 =“).

Persönliche Angaben (Namen von Kindern, Noten, handschriftliche Einträge) nicht übernehmen; ein leeres Namensfeld darf als text mit Lücke erfasst werden. seiten = Anzahl der Seiten des Originals.
TXT;
}
