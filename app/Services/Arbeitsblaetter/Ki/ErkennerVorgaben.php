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
        return AbStruktur::normalisieren(self::nachbearbeiten(AbStruktur::normalisieren(self::umwandeln($roh))));
    }

    /**
     * Typische Fehler der Modelle korrigieren:
     * - Ankreuzen: Frage, die nur eine Option wiederholt („Kann ich beantworten:“) → leer.
     * - Aufgabe mit Nummer, aber ohne Text direkt nach einer Arbeitsanweisung → Anweisung wird Aufgabentext.
     * - Leere Bausteine (Text ohne Inhalt, Ankreuzen ohne Optionen) entfallen.
     */
    public static function nachbearbeiten(array $struktur): array
    {
        $liste = function (array $bausteine) use (&$liste): array {
            $ergebnis = [];
            foreach ($bausteine as $b) {
                if ($b['typ'] === 'ankreuzen') {
                    $frage = mb_strtolower(trim($b['frage'] ?? ''));
                    if ($frage !== '' && in_array($frage, array_map(fn ($o) => mb_strtolower(trim($o)), $b['optionen'] ?? []), true)) {
                        $b['frage'] = '';
                    }
                    if (empty($b['optionen']) && ($b['frage'] ?? '') === '') {
                        continue;
                    }
                }
                if (in_array($b['typ'], ['text', 'kasten'], true) && empty($b['inhalt']) && ($b['titel'] ?? '') === '') {
                    continue;
                }
                if ($b['typ'] === 'aufgabe') {
                    $vorher = $ergebnis ? $ergebnis[count($ergebnis) - 1] : null;
                    if (empty($b['inhalt']) && $vorher && $vorher['typ'] === 'anweisung' && ($b['nummer'] ?? '') !== '') {
                        array_pop($ergebnis);
                        $b['inhalt'] = [['typ' => 'text', 'text' => $vorher['text']]];
                    }
                    $b['kinder'] = $liste($b['kinder'] ?? []);
                }
                $ergebnis[] = $b;
            }

            return $ergebnis;
        };
        $struktur['bausteine'] = $liste($struktur['bausteine'] ?? []);

        return $struktur;
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
            'titel' => ['type' => 'string', 'description' => 'Überschrift eines kasten (z. B. „Eintritt“)'],
            'reihen' => ['type' => 'integer', 'description' => 'Anzahl Kästchenreihen eines rechenfeld'],
            'angekreuzt' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Indizes (ab 0) der Optionen, die im Original schon angekreuzt sind (Beispiel)'],
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
Du erfasst Arbeitsblätter aus Schulen (Fotos oder PDF), damit sie für Kinder mit Sehbehinderung in großer Schrift neu gesetzt werden können. Eine Lehrkraft prüft dein Ergebnis anschließend. Alles, was das Kind lesen muss, muss als Text in den Bausteinen stehen – Bilder werden nur verkleinert/vergrößert übernommen und sind für diese Kinder oft schwer lesbar.

Gib den Inhalt in Lesereihenfolge als Bausteine aus. Übernimm Texte wörtlich (Rechtschreibung wie im Original, nichts ergänzen, nichts lösen, keine Lösungen eintragen). Kein Layout: keine Schriftgrößen, Farben oder Positionen – außer der Box bei Abbildungen.

Bausteine:
- ueberschrift: Titel des Blatts oder eines Abschnitts (text). Seitenzahlen, Verlags- und Bestellnummern, Kopf- und Fußzeilen weglassen.
- anweisung: allgemeine Arbeitsanweisung, die zu keiner nummerierten Aufgabe gehört (text).
- aufgabe: nummerierte Aufgabe. nummer wie im Original (z. B. „1“ aus einem Nummernkästchen), inhalt = der Aufgabentext daneben (Anweisung und Einleitung gehören in inhalt, nicht in einen eigenen anweisung-Baustein). Teilaufgaben a), b), c) sind Bausteine vom Typ aufgabe in kinder; alles, was zu einer Teilaufgabe gehört (ankreuzen, rechenfeld, Antwortzeile …), folgt direkt danach ebenfalls in kinder.
- text: sonstiger Text, auch Antwortsätze wie „Antwort: Der Eintritt kostet ___.“ (inhalt mit Lücken).
- kasten: gerahmter Text, Schild, Info-Kasten, Merkkasten, Sprechblase, Preisliste (titel = fett gedruckte Überschrift im Kasten, inhalt = Text, Zeilen mit \n trennen). Text in solchen Kästen NIE als bild erfassen.
- ankreuzen: Kästchen zum Ankreuzen. optionen = die Beschriftungen der Kästchen (z. B. „Kann ich beantworten:“, „Kann ich nicht beantworten:“). frage nur, wenn über den Kästchen eine eigene Frage steht – sonst leer lassen, die Beschriftungen nie zusätzlich als frage. mehrfach = true bei eckigen Kästchen, wenn mehrere angekreuzt werden dürfen. angekreuzt = Indizes der Kästchen, die im Original schon angekreuzt sind (vorgegebenes Beispiel).
- tabelle: Tabelle (zeilen = Zellen als Text, leere Zellen als ""; kopfzeile = true, wenn die erste Zeile Überschriften enthält).
- rechenfeld: kariertes Feld/Rechenkästchen zum Rechnen (reihen = Anzahl der Kästchenreihen). Vorgedruckte Hilfen im Feld (z. B. „2 · ___ = ___ €“) als inhalt mit Lücken.
- schreiblinien: freie Linien zum Schreiben ohne Text davor (anzahl).
- bild: nur Grafiken, deren Inhalt man zum Lösen sehen muss: Diagramme, Zahlenstrahl, geometrische Formen, Bildfolgen, Fotos, nach denen gefragt wird (beschreibung kurz; seite; box = [x1, y1, x2, y2] relativ zur Seite, 0 = links/oben, 1 = rechts/unten, eng um die Grafik, ohne umgebenden Text). Schmückende Illustrationen (Tiere, Figuren, Gebäude neben den Aufgaben, Cliparts, Rahmen, Logos) weglassen.

Lücken: Unterstriche, Antwortlinien, leere Kästchen oder freie Stellen zum Eintragen werden zu {"typ": "luecke", "laenge": n} mit der ungefähren Zahl der Zeichen, die hineinpassen (lange Antwortlinien 30–40). Vorgedruckte Beispiellösungen und Hilfen (oft farbig) übernehmen. Mathematische Ausdrücke als lesbarer Text (z. B. „3/4“, „2 · 5 =“, „1,50 €“).

Persönliche Angaben (Namen von Kindern, Noten, handschriftliche Einträge) nicht übernehmen; ein leeres Namensfeld darf als text mit Lücke erfasst werden. seiten = Anzahl der Seiten des Originals.
TXT;
}
