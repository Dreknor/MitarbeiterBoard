<?php

namespace App\Services\Arbeitsblaetter\Ki;

use App\Support\AbStruktur;

/**
 * Gemeinsame Vorgaben aller KI-Treiber: Prompt, JSON-Schema der Antwort, Umwandlung ins
 * Baustein-Format, robustes Lesen der JSON-Antwort, Reparatur typischer Modellfehler,
 * Plausibilitätsprüfung und Kostenschätzung aus Token.
 * Gesendet werden immer nur Seitenbilder/PDF, ggf. die Textebene und dieser Prompt.
 */
final class ErkennerVorgaben
{
    public const AUFTRAG = 'Erfasse dieses Arbeitsblatt vollständig – jede Aufgabe, jeden Kasten, jede Tabelle mit allen Zellen – als Bausteine nach den Regeln. Antworte nur mit JSON nach dem Schema.';

    public const TEXT_HINWEIS = "Textebene des PDFs (kann Fehler und falsche Reihenfolge enthalten, nur als Hilfe – Aufgabentexte daraus gehören in deine Bausteine, Lösungen, Werbung und Fußzeilen nicht):
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
     * - Aufgabennummer „1.“ → „1“.
     * - Zeilenumbrüche des Originals im Fließtext → Leerzeichen (nur Kästen behalten ihre Zeilen).
     * - Leere Bausteine (Text ohne Inhalt, Ankreuzen ohne Optionen) entfallen.
     */
    public static function nachbearbeiten(array $struktur): array
    {
        // Modelle schreiben Zeilenumbrüche manchmal als Zeichenfolge „\n“
        array_walk_recursive($struktur, function (&$wert) {
            if (is_string($wert)) {
                $wert = str_replace('\n', "\n", $wert);
            }
        });
        $liste = function (array $bausteine) use (&$liste): array {
            $ergebnis = [];
            foreach ($bausteine as $b) {
                // leere einspaltige Tabelle = Antwortlinien
                if ($b['typ'] === 'tabelle' && ($b['zeilen'] ?? []) && collect($b['zeilen'])->every(fn ($z) => count($z) <= 1)) {
                    $koerper = ! empty($b['kopfzeile']) ? array_slice($b['zeilen'], 1) : $b['zeilen'];
                    if ($koerper && collect($koerper)->every(fn ($z) => trim((string) ($z[0] ?? '')) === '')) {
                        if (! empty($b['kopfzeile']) && trim((string) ($b['zeilen'][0][0] ?? '')) !== '') {
                            $ergebnis[] = ['id' => $b['id'].'t', 'typ' => 'text', 'inhalt' => [['typ' => 'text', 'text' => $b['zeilen'][0][0]]]];
                        }
                        $b = ['id' => $b['id'], 'typ' => 'schreiblinien', 'anzahl' => max(2, count($koerper))];
                    }
                }
                if ($b['typ'] === 'ankreuzen') {
                    $frage = mb_strtolower(trim($b['frage'] ?? ''));
                    if ($frage !== '' && in_array($frage, array_map(fn ($o) => mb_strtolower(trim($o)), $b['optionen'] ?? []), true)) {
                        $b['frage'] = '';
                    }
                    if (empty($b['optionen']) && ($b['frage'] ?? '') === '') {
                        continue;
                    }
                }
                if (in_array($b['typ'], ['ueberschrift', 'anweisung'], true)) {
                    $b['text'] = self::fliesstext($b['text'] ?? '');
                }
                if (in_array($b['typ'], ['text', 'aufgabe', 'rechenfeld'], true)) {
                    $b['inhalt'] = array_map(fn ($s) => ($s['typ'] ?? '') === 'text' ? ['typ' => 'text', 'text' => self::fliesstext($s['text'] ?? '', true)] : $s, $b['inhalt'] ?? []);
                }
                if (in_array($b['typ'], ['text', 'kasten'], true) && empty($b['inhalt']) && ($b['titel'] ?? '') === '') {
                    continue;
                }
                if ($b['typ'] === 'aufgabe') {
                    $b['nummer'] = rtrim(trim($b['nummer'] ?? ''), '.');
                    $vorher = $ergebnis ? $ergebnis[count($ergebnis) - 1] : null;
                    if (empty($b['inhalt']) && $vorher && $vorher['typ'] === 'anweisung' && $b['nummer'] !== '') {
                        array_pop($ergebnis);
                        $b['inhalt'] = [['typ' => 'text', 'text' => $vorher['text']]];
                    }
                    $b['kinder'] = $liste($b['kinder'] ?? []);
                }
                $ergebnis[] = $b;
            }

            return $ergebnis;
        };
        $struktur['bausteine'] = self::bilderZusammenfuehren(self::loesungsteilEntfernen($liste($struktur['bausteine'] ?? [])));

        return $struktur;
    }

    /**
     * Lösungsteile, die die KI trotz Anweisung übernimmt: alles ab einer Überschrift „Lösung(en)“ fällt weg,
     * ebenso Aufgaben, deren Text (ab 25 Zeichen) wortgleich schon vorkam – meist das Lösungsblatt.
     */
    /**
     * Überlappende Abbildungen hintereinander (Hexe + halber Kürbis, Stativ + Klemme): Die KI hat ein Motiv
     * doppelt oder zerschnitten erfasst – sie werden zu einem Bild mit der gemeinsamen Box. Auch in Aufgaben.
     */
    public static function bilderZusammenfuehren(array $bausteine): array
    {
        $flaeche = fn (array $b) => max(0, $b[2] - $b[0]) * max(0, $b[3] - $b[1]);
        $ergebnis = [];
        foreach ($bausteine as $b) {
            if (! empty($b['kinder'])) {
                $b['kinder'] = self::bilderZusammenfuehren($b['kinder']);
            }
            $vorher = $ergebnis ? $ergebnis[count($ergebnis) - 1] : null;
            $boxA = $vorher['quelle']['box'] ?? null;
            $boxB = $b['quelle']['box'] ?? null;
            if ($b['typ'] === 'bild' && ($vorher['typ'] ?? '') === 'bild' && $boxA && $boxB && empty($b['media_id']) && empty($vorher['media_id'])
                && (int) ($vorher['quelle']['seite'] ?? 1) === (int) ($b['quelle']['seite'] ?? 1)) {
                $schnitt = [max($boxA[0], $boxB[0]), max($boxA[1], $boxB[1]), min($boxA[2], $boxB[2]), min($boxA[3], $boxB[3])];
                $kleiner = min($flaeche($boxA), $flaeche($boxB));
                if ($kleiner > 0 && $flaeche($schnitt) / $kleiner > 0.3) {
                    $vorher['quelle']['box'] = [min($boxA[0], $boxB[0]), min($boxA[1], $boxB[1]), max($boxA[2], $boxB[2]), max($boxA[3], $boxB[3])];
                    $vorher['beschreibung'] = trim(($vorher['beschreibung'] ?? '').' / '.($b['beschreibung'] ?? ''), ' /');
                    $ergebnis[count($ergebnis) - 1] = $vorher;

                    continue;
                }
            }
            $ergebnis[] = $b;
        }

        return $ergebnis;
    }

    public static function loesungsteilEntfernen(array $bausteine): array
    {
        $ergebnis = [];
        $gesehen = [];
        $schluesselVon = fn (array $b) => ($b['nummer'] ?? '').'|'.mb_substr(mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', AbStruktur::segmentText($b['inhalt'] ?? [])) ?? ''), 0, 40);
        foreach ($bausteine as $b) {
            if ($b['typ'] === 'ueberschrift' && preg_match('/^\s*(l(ö|oe)sung(en|sblatt)?|musterl(ö|oe)sung|answer key|solutions?)\b/iu', (string) ($b['text'] ?? '')) && count($ergebnis) >= 2) {
                break;
            }
            if ($b['typ'] === 'aufgabe') {
                // Anfang reicht: das Lösungsblatt wiederholt die Aufgabe, oft mit eingesetzten Antworten dahinter
                $schluessel = mb_substr(mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', AbStruktur::segmentText($b['inhalt'] ?? [])) ?? ''), 0, 40);
                if (mb_strlen($schluessel) >= 25 && isset($gesehen[($b['nummer'] ?? '').'|'.$schluessel])) {
                    continue;
                }
                $gesehen[($b['nummer'] ?? '').'|'.$schluessel] = true;
                // Teilaufgaben auch merken: das Lösungsblatt wiederholt „b) …“ oft als eigene Aufgabe
                foreach ($b['kinder'] ?? [] as $kind) {
                    if ($kind['typ'] === 'aufgabe') {
                        $gesehen[$schluesselVon($kind)] = true;
                    }
                }
            }
            $ergebnis[] = $b;
        }

        return $ergebnis;
    }

    /** Einzelne Zeilenumbrüche → Leerzeichen; Absätze (Leerzeile) bleiben. Ränder bleiben erhalten. */
    /**
     * Umbruch mitten im Satz (Zeilenfall des Originals) → Leerzeichen. Er bleibt bei Listen: Satzende
     * oder kurze Zeile davor, nächste Zeile beginnt mit Aufzählungszeichen/Nummer.
     */
    private static function fliesstext(string $text, bool $raender = false): string
    {
        $neu = preg_replace_callback('/([^\n]*?)[ \t]*\n(?!\n)[ \t]*(?=(\S?))/u', function ($m) {
            $zeile = $m[1];
            $weiter = $m[2];
            $satzende = (bool) preg_match('/[.!?:;)]$/u', $zeile); // „…(Gegenwart)“ + nächster Lückensatz bleiben getrennt
            $aufzaehlung = (bool) preg_match('/^[-–•\d]/u', $weiter);
            $klein = $weiter !== '' && mb_strtolower($weiter) === $weiter && mb_strtoupper($weiter) !== $weiter;
            $verbinden = ! $aufzaehlung && ! $satzende && ($klein || mb_strlen($zeile) >= 55);

            return $zeile.($verbinden ? ' ' : "\n");
        }, $text) ?? $text;
        $neu = preg_replace('/ {2,}/u', ' ', $neu) ?? $neu;

        return $raender ? $neu : trim($neu);
    }

    /** Kosten in Cent (mit Bruchteilen – günstige Modelle kosten oft weniger als 1 Cent je Blatt) aus Token und Preisen (Cent je 1 Mio. Token). */
    public static function kostenCent(int $eingabe, int $ausgabe, float $preisEingabe, float $preisAusgabe): float
    {
        return round($eingabe / 1_000_000 * $preisEingabe + $ausgabe / 1_000_000 * $preisAusgabe, 4);
    }

    /**
     * KI-Format (flach, `seite`/`box` am Bild, Kasten als `text`) → Baustein-Format.
     * Repariert dabei Inhalte, die Modelle gern in `kinder` statt in `zeilen`/`text` ablegen,
     * Boxen im Format 0–1000 bzw. 0–100 und Kommentare in Bildbeschreibungen.
     */
    public static function umwandeln(array $roh): array
    {
        $baustein = function (array $b, bool $oben) use (&$baustein) {
            $typ = (string) ($b['typ'] ?? '');
            $kinder = isset($b['kinder']) && is_array($b['kinder']) ? array_values(array_filter($b['kinder'], 'is_array')) : [];

            // Kasten ohne Text (die KI hat ihn nicht abgeschrieben): lieber als Abbildung übernehmen als leer lassen
            if ($typ === 'kasten' && self::leererText($b) && ! $kinder && isset($b['box']) && is_array($b['box'])) {
                $b = ['typ' => 'bild', 'beschreibung' => trim('Schild/Kasten '.($b['titel'] ?? '')).' – Text bitte prüfen', 'seite' => $b['seite'] ?? 1, 'box' => $b['box']];
                $typ = 'bild';
            }

            if ($typ === 'bild') {
                if (isset($b['box']) && is_array($b['box'])) {
                    $b['quelle'] = ['seite' => (int) ($b['seite'] ?? 1), 'box' => self::box($b['box'])];
                }
                if (isset($b['beschreibung']) && is_string($b['beschreibung'])) {
                    $b['beschreibung'] = trim(preg_replace('/\s*\((?:[^()]*\b(?:vermutlich|wahrscheinlich|möglicherweise|Fehler im Original|evtl\.)\b[^()]*)\)/iu', '', $b['beschreibung']) ?? $b['beschreibung']);
                }
            }
            unset($b['seite'], $b['box']);

            if ($typ === 'kasten' && self::leererText($b) && $kinder) {
                $b['text'] = implode("\n", array_filter(array_map([self::class, 'rohText'], $kinder), fn ($t) => $t !== ''));
                $kinder = [];
            }
            if ($typ === 'kasten' && isset($b['text']) && ! isset($b['inhalt'])) {
                $b['inhalt'] = (string) $b['text'];
            }
            if ($typ === 'tabelle' && empty($b['zeilen']) && $kinder) {
                $b['zeilen'] = array_values(array_filter(array_map(
                    fn ($k) => ! empty($k['zeilen']) && is_array($k['zeilen']) ? $k['zeilen'] : [self::rohText($k)],
                    $kinder
                ), fn ($z) => $z !== [''] && $z !== []));
                if (count($b['zeilen']) && is_array($b['zeilen'][0][0] ?? null)) {
                    $b['zeilen'] = array_merge(...$b['zeilen']);
                }
                $kinder = [];
            }

            if ($typ === 'aufgabe' && $oben) {
                $b['kinder'] = array_map(fn ($k) => $baustein($k, false), $kinder);
            } else {
                unset($b['kinder']);
            }

            return $b;
        };

        return [
            'seiten' => (int) ($roh['seiten'] ?? 1),
            'bausteine' => array_map(fn ($b) => $baustein($b, true), self::gruppieren(array_filter((array) ($roh['bausteine'] ?? []), 'is_array'))),
        ];
    }

    /**
     * Flache Liste der KI → Aufgaben mit Unterbausteinen: Bausteine mit `teil_von_aufgabe = true`
     * hängen an der vorangehenden Aufgabe (eine Ebene). Ältere Antworten mit `kinder` bleiben gültig.
     */
    public static function gruppieren(array $liste): array
    {
        $ergebnis = [];
        $aufgabe = null; // Index der letzten Aufgabe der obersten Ebene
        foreach (array_values($liste) as $b) {
            $teil = ! empty($b['teil_von_aufgabe']);
            unset($b['teil_von_aufgabe']);
            if ($teil && $aufgabe !== null) {
                $ergebnis[$aufgabe]['kinder'][] = $b;

                continue;
            }
            $ergebnis[] = $b;
            $aufgabe = ($b['typ'] ?? '') === 'aufgabe' ? count($ergebnis) - 1 : null;
        }

        return $ergebnis;
    }

    private static function leererText(array $b): bool
    {
        $text = $b['text'] ?? ($b['inhalt'] ?? '');

        return is_array($text) ? trim(AbStruktur::segmentText(array_filter($text, 'is_array'))) === '' : trim((string) $text) === '';
    }

    /** Text eines Roh-Bausteins (text, inhalt als Segmente oder Text, Zeilen). */
    private static function rohText(array $b): string
    {
        if (isset($b['text']) && is_scalar($b['text']) && trim((string) $b['text']) !== '') {
            return trim((string) $b['text']);
        }
        if (isset($b['inhalt'])) {
            return trim(is_array($b['inhalt']) ? AbStruktur::segmentText(array_filter($b['inhalt'], 'is_array')) : (string) $b['inhalt']);
        }
        if (! empty($b['zeilen']) && is_array($b['zeilen'])) {
            return implode("\n", array_map(fn ($z) => implode(' ', array_map('strval', (array) $z)), $b['zeilen']));
        }

        return '';
    }

    /** Box auf 0–1 bringen (manche Modelle liefern 0–1000 oder 0–100) und etwas Rand geben. */
    public static function box(array $box): array
    {
        $box = array_values(array_filter($box, 'is_numeric'));
        if (count($box) !== 4) {
            return $box;
        }
        $box = array_map('floatval', $box);
        $max = max($box);
        if ($max > 1.0001) {
            $teiler = $max > 100 ? 1000 : 100;
            $box = array_map(fn ($v) => $v / $teiler, $box);
        }
        [$x1, $y1, $x2, $y2] = $box;
        if ($x2 < $x1) {
            [$x1, $x2] = [$x2, $x1];
        }
        if ($y2 < $y1) {
            [$y1, $y2] = [$y2, $y1];
        }
        // Modelle schneiden gern knapp ab („988“ → „98“) – 1,5 % Rand
        $rand = 0.015;

        return array_map(fn ($v) => round(min(1, max(0, $v)), 4), [$x1 - $rand, $y1 - $rand, $x2 + $rand, $y2 + $rand]);
    }

    /**
     * Hinweise, dass eine Erkennung vermutlich unvollständig ist (zu wenige Bausteine,
     * deutlich weniger Text als die Textebene des PDFs). Leer = plausibel.
     *
     * @return array<int, string>
     */
    /**
     * Hinweis, wenn das Original vor allem Material für die Lehrkraft ist (Handreichung, Erwartungshorizont)
     * und kein Schülerblatt – solche Seiten soll die Erkennung weglassen, ganze Dokumente erkennt man am Anfang.
     *
     * @return array<int, string>
     */
    public static function lehrkraftMaterial(?string $textebene, array $struktur): array
    {
        $anfang = mb_strtolower(mb_substr(trim((string) $textebene), 0, 800).' '.mb_substr(AbStruktur::klartext($struktur), 0, 300));
        foreach (['hinweise für die lehrkraft', 'hinweise für lehrkräfte', 'lehrerhandreichung', 'handreichung für', 'erwartungshorizont', 'lösungsblatt', 'lehrerinformation'] as $merkmal) {
            if (str_contains($anfang, $merkmal)) {
                return ['Das Original scheint Material für die Lehrkraft zu sein („'.$merkmal.'“) und kein Schülerblatt. Bitte prüfen, ob es so an das Kind gehen soll.'];
            }
        }

        return [];
    }

    public static function unvollstaendig(array $struktur, int $seiten, ?string $textebene = null): array
    {
        $hinweise = [];
        $anzahl = AbStruktur::anzahl($struktur['bausteine'] ?? []);
        if ($anzahl < 3 * max(1, $seiten)) {
            $hinweise[] = 'Es wurden nur '.$anzahl.' Bausteine erkannt – die Erkennung ist vermutlich unvollständig.';
        }
        $vergleich = fn (string $t) => mb_strlen(preg_replace('/[^\p{L}\p{N}]+/u', '', $t) ?? '');
        // Lösungs- und Lehrkraftteile lässt die Erkennung absichtlich weg – sie zählen nicht mit
        $aufgaben = (string) $textebene;
        if (preg_match('/(erwartungshorizont|erwartungsbild|lehrplanbezug|hinweise zum unterrichtlichen einsatz|didaktische hinweise|l(ö|oe)sungen?\s*:|l(ö|oe)sungsblatt|hinweise f(ü|ue)r die lehrkraft|musterl(ö|oe)sung)/iu', $aufgaben, $treffer, PREG_OFFSET_CAPTURE) && $treffer[0][1] > strlen($aufgaben) * 0.2) {
            $aufgaben = substr($aufgaben, 0, $treffer[0][1]);
        }
        $soll = $textebene ? $vergleich($aufgaben) : 0;
        if ($soll >= 150 && $vergleich(AbStruktur::klartext($struktur)) < 0.5 * $soll) {
            $hinweise[] = 'Der erkannte Text ist deutlich kürzer als der Text im PDF – vermutlich fehlen Teile.';
        }

        return $hinweise;
    }

    /**
     * JSON-Schema der Antwort: je Bausteintyp ein eigenes Objekt mit Pflichtfeldern (anyOf),
     * damit Tabellen Zeilen und Kästen Text haben müssen.
     *
     * @param  bool  $grenzen  Zahlen-/Längengrenzen (minimum, minItems …) mitsenden. Ohne Grenzen (Anthropic) sind
     *                          zusätzlich alle Felder Pflicht – Anthropic erlaubt höchstens 24 optionale Felder je Schema;
     *                          nicht zutreffende Felder liefert das Modell dann leer ("" / 0 / []).
     */
    public static function schema(bool $grenzen = true): array
    {
        $g = fn (array $schema, array $grenze) => $grenzen ? array_merge($schema, $grenze) : $schema;
        $allePflicht = ! $grenzen;
        $segment = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => $allePflicht ? ['typ', 'text', 'laenge'] : ['typ'],
            'properties' => [
                'typ' => ['type' => 'string', 'enum' => ['text', 'luecke']],
                'text' => ['type' => 'string'],
                'laenge' => ['type' => 'integer', 'description' => 'Ungefähre Anzahl Zeichen, die in die Lücke passen'],
            ],
        ];
        $segmente = fn (string $beschreibung) => $g(['type' => 'array', 'items' => $segment, 'description' => $beschreibung], ['minItems' => 1]);
        $objekt = fn (string $typ, array $felder, array $pflicht, string $beschreibung) => [
            'type' => 'object',
            'description' => $beschreibung,
            'additionalProperties' => false,
            'required' => array_merge(['typ'], $allePflicht ? [...array_keys($felder), 'teil_von_aufgabe'] : $pflicht),
            'properties' => array_merge(['typ' => ['type' => 'string', 'enum' => [$typ]]], $felder, [
                'teil_von_aufgabe' => ['type' => 'boolean', 'description' => 'true = gehört zur vorangehenden nummerierten Aufgabe (Teilaufgabe a), b) oder deren Tabelle, Linien, Bild …)'],
            ]),
        ];

        $typen = [
            'ueberschrift' => $objekt('ueberschrift', ['text' => ['type' => 'string']], ['text'], 'Titel des Blatts oder eines Abschnitts'),
            'anweisung' => $objekt('anweisung', ['text' => ['type' => 'string']], ['text'], 'Arbeitsanweisung ohne Nummer'),
            'text' => $objekt('text', ['inhalt' => $segmente('Text und Lücken in Lesereihenfolge')], ['inhalt'], 'Sonstiger Text, Antwortsätze, Wortlisten'),
            'ankreuzen' => $objekt('ankreuzen', [
                'frage' => ['type' => 'string'],
                'optionen' => $g(['type' => 'array', 'items' => ['type' => 'string']], ['minItems' => 1]),
                'mehrfach' => ['type' => 'boolean'],
                'angekreuzt' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Indizes (ab 0) der Optionen, die im Original schon angekreuzt sind'],
            ], ['optionen'], 'Kästchen zum Ankreuzen'),
            'tabelle' => $objekt('tabelle', [
                'zeilen' => $g(['type' => 'array', 'items' => $g(['type' => 'array', 'items' => ['type' => 'string']], ['minItems' => 1]), 'description' => 'Alle Zeilen mit allen Zellen als Text, leere Zellen als ""'], ['minItems' => 1]),
                'kopfzeile' => ['type' => 'boolean'],
            ], ['zeilen'], 'Tabelle, Strichliste, Buchstabengitter (ein Buchstabe je Zelle)'),
            'kasten' => $objekt('kasten', [
                'titel' => ['type' => 'string', 'description' => 'Fette Überschrift im Kasten, z. B. „Merke!“'],
                'text' => ['type' => 'string', 'description' => 'Vollständiger Text im Kasten, Zeilen mit \n trennen'],
            ], ['text'], 'Gerahmter Text: Merkkasten, Wortspeicher, Schild, Sprechblase'),
            'rechenfeld' => $objekt('rechenfeld', [
                'reihen' => $g(['type' => 'integer', 'description' => 'Anzahl der Kästchenreihen wie im Original'], ['minimum' => 1, 'maximum' => 20]),
                'inhalt' => ['type' => 'array', 'items' => $segment, 'description' => 'Vorgedruckte Hilfe im Feld, z. B. „2 · ___ = ___ €“'],
            ], ['reihen'], 'Kariertes Feld zum Rechnen'),
            'schreiblinien' => $objekt('schreiblinien', [
                'anzahl' => $g(['type' => 'integer'], ['minimum' => 1, 'maximum' => 30]),
                'lineatur' => ['type' => 'string', 'enum' => AbStruktur::LINEATUREN, 'description' => 'grundschule = Vierliniensystem (Ober-, Mittel-, Grund-, Unterlinie, oft graues Mittelband), sonst einfach'],
            ], ['anzahl'], 'Freie Linien zum Schreiben'),
            'zeichenflaeche' => $objekt('zeichenflaeche', [
                'hoehe' => $g(['type' => 'integer', 'description' => 'Höhe in Schreibzeilen (ca. 1,2 cm je Zeile)'], ['minimum' => 2, 'maximum' => 20]),
            ], ['hoehe'], 'Leere Fläche zum Zeichnen, Malen oder Kleben'),
            'bild' => $objekt('bild', [
                'beschreibung' => ['type' => 'string', 'description' => 'Was zu sehen ist, sachlich, ohne Kommentar'],
                'seite' => ['type' => 'integer', 'description' => 'Seite der Abbildung (ab 1)'],
                'box' => $g(['type' => 'array', 'items' => $g(['type' => 'number'], ['minimum' => 0, 'maximum' => 1]), 'description' => '[x1, y1, x2, y2] relativ zur Seite, Werte 0 bis 1'], ['minItems' => 4, 'maxItems' => 4]),
            ], ['beschreibung', 'seite', 'box'], 'Grafik, die man zum Lösen sehen muss'),
        ];

        // flache Liste: Unterbausteine einer Aufgabe tragen teil_von_aufgabe = true ({@see gruppieren()}) –
        // verschachtelte Typlisten machen das Schema für Anthropic zu groß
        $typen['aufgabe'] = $objekt('aufgabe', [
            'nummer' => ['type' => 'string', 'description' => 'Aufgabennummer ohne Punkt, z. B. „1“ oder „b)“'],
            'inhalt' => ['type' => 'array', 'items' => $segment, 'description' => 'Aufgabentext und Lücken in Lesereihenfolge'],
        ], ['nummer', 'inhalt'], 'Nummerierte Aufgabe oder Teilaufgabe');

        if ($allePflicht) {
            // Anthropic: jeder anyOf-Zweig vergrößert die Grammatik (Grenze „compiled grammar too large“) –
            // gleich gebaute Typen teilen sich einen Zweig, die Antwort bleibt dieselbe
            $paar = function (array $typenListe, array $vorlage, string $beschreibung) {
                $vorlage['description'] = $beschreibung;
                $vorlage['properties']['typ']['enum'] = $typenListe;

                return $vorlage;
            };
            $typen['ueberschrift'] = $paar(['ueberschrift', 'anweisung'], $typen['ueberschrift'], 'ueberschrift = Titel des Blatts/Abschnitts, anweisung = Arbeitsanweisung ohne Nummer');
            unset($typen['anweisung']);
            $typen['aufgabe'] = $paar(['aufgabe', 'text'], $typen['aufgabe'], 'aufgabe = nummerierte Aufgabe/Teilaufgabe; text = sonstiger Text, Antwortsatz, Wortliste (nummer leer)');
            unset($typen['text']);
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['seiten', 'bausteine'],
            'properties' => [
                'seiten' => ['type' => 'integer'],
                'bausteine' => ['type' => 'array', 'items' => ['anyOf' => array_values($typen)]],
            ],
        ];
    }

    public const PROMPT = <<<'TXT'
Du erfasst Arbeitsblätter aus Schulen (Fotos oder PDF), damit sie für Kinder mit Sehbehinderung in großer Schrift neu gesetzt werden können. Eine Lehrkraft prüft dein Ergebnis anschließend. Alles, was das Kind lesen muss, muss als Text in den Bausteinen stehen – Bilder werden nur vergrößert übernommen und sind für diese Kinder oft schwer lesbar. Wörter, Zahlen und Sätze, die in oder neben einer Grafik stehen (Wortlisten, Zahlen am Rechenstrich, Beschriftungen zum Zuordnen), gehören deshalb immer zusätzlich als text, tabelle oder aufgabe in die Bausteine.

Erfasse das GANZE Blatt von oben nach unten in Lesereihenfolge. Lass keine Aufgabe, keinen Kasten und keine Tabelle aus. Übernimm Texte wörtlich (Rechtschreibung wie im Original, nichts ergänzen, nichts lösen, keine Lösungen eintragen, keine eigenen Kommentare oder Vermutungen). Kein Layout: keine Schriftgrößen, Farben oder Positionen – außer der Box bei Abbildungen. Zeilenumbrüche des Originals im Fließtext NICHT übernehmen – ein Satz bleibt ein Satz. Zeilen einer Liste (Wortreihen, untereinander stehende Sätze) dagegen mit \n trennen.

Bausteine (Feld typ):
- ueberschrift: Titel des Blatts oder eines Abschnitts (text). Seitenzahlen, Verlags-, Lizenz- und Bestellnummern, Kopf- und Fußzeilen, Internetadressen weglassen.
- anweisung: allgemeine Arbeitsanweisung, die zu keiner nummerierten Aufgabe gehört (text).
- aufgabe: nummerierte Aufgabe. nummer wie im Original, aber ohne Punkt („1“, nicht „1.“). inhalt = der Aufgabentext (Anweisung gehört in inhalt). Teilaufgaben a), b) sind eigene aufgabe-Bausteine. Alles, was zu einer Aufgabe gehört (Teilaufgaben, Tabelle, Schreiblinien, Ankreuzen, Rechenfeld, Zeichenfläche, Bild, Wortliste), folgt direkt danach mit teil_von_aufgabe = true. Bei allen anderen Bausteinen ist teil_von_aufgabe = false.
- text: sonstiger Text, auch Antwortsätze wie „Antwort: Der Eintritt kostet ___.“ und Wortlisten zum Zuordnen (inhalt mit Lücken).
- kasten: gerahmter Text – Merkkasten („Merke!“), Wortspeicher, Info-Kasten, Schild, Sprechblase, Preisliste. titel = fette Überschrift im Kasten (nur wenn sie im Original steht – nie selbst ausdenken, sonst ""), text = der VOLLSTÄNDIGE Text im Kasten (Zeilen mit \n trennen, Wörter eines Wortspeichers mit \n oder „ · “). Ein Kasten ohne text ist falsch. Text in Kästen NIE als bild erfassen.
- tabelle: jede Tabelle mit ALLEN Zeilen und Zellen (zeilen = Liste von Zeilen, jede Zeile eine Liste von Zelltexten, leere Zellen ""; kopfzeile = true, wenn die erste Zeile Überschriften enthält). Strichlisten als Tabelle mit den Strichen als Text („IIII“), Buchstabengitter/Suchsel als Tabelle mit genau einem Buchstaben je Zelle. Eine Tabelle ohne zeilen ist falsch.
- ankreuzen: Kästchen zum Ankreuzen. optionen = Beschriftungen der Kästchen. frage nur, wenn über den Kästchen eine eigene Frage steht. mehrfach = true bei eckigen Kästchen, wenn mehrere angekreuzt werden dürfen. angekreuzt = Indizes der im Original schon angekreuzten Kästchen (Beispiel).
- rechenfeld: kariertes Feld/Rechenkästchen (reihen = genaue Anzahl der Kästchenreihen im Original). Vorgedruckte Hilfen im Feld als inhalt mit Lücken.
- schreiblinien: freie Linien zum Schreiben ohne Text davor (anzahl = Zahl der Schreibzeilen wie im Original; lineatur = grundschule bei Grundschul-Lineatur – je Schreibzeile mehrere Hilfslinien bzw. ein graues Mittelband –, sonst einfach).
- zeichenflaeche: leere Fläche oder leerer Rahmen zum Zeichnen, Malen, Kleben (hoehe in Schreibzeilen, z. B. 4 für eine kleine, 10 für eine halbe Seite). Bei „Zeichne …“/„Male …“/„Draw …“ ohne vorgedrucktes Bild immer eine zeichenflaeche anlegen.
- bild: nur Grafiken, deren Inhalt man zum Lösen sehen muss: Diagramme, Zahlenstrahl/Rechenstrich, Münzen, geometrische Formen, Bildfolgen, Bilder zum Benennen, Ausmalbilder (beschreibung sachlich; seite; box = [x1, y1, x2, y2] relativ zur Seite mit Werten von 0 bis 1 – 0 = links/oben, 1 = rechts/unten –, die ganze Grafik vollständig umschließend, aber ohne Aufgabentext darüber oder darunter). Schmückende Illustrationen (Figuren neben den Aufgaben, Cliparts, Rahmen, Logos) weglassen.

Sehgerechte Form – so umbauen, dass ein Kind mit Sehbehinderung ohne Suchen arbeiten kann:
- Zuordnen/Verbinden (Wörter zu Bildern, Begriffe zu Erklärungen): als tabelle mit einer Zeile je Paar, links das Vorgegebene, rechts "" zum Eintragen; die Bilder als bild-Bausteine davor.
- Wortpaare mit Antwortlinie („monster – ____“, Übersetzen, Vokabeln): als tabelle mit zwei Spalten, eine Zeile je Wort, rechte Zelle "".
- Richtig/falsch-Aussagen: als tabelle mit Kopfzeile ["Aussage", "richtig", "falsch"] und je Aussage einer Zeile ["…", "", ""].
- Wortlisten und Wortspeicher in Rastern oder Kästen: als kasten mit einem Wort je Zeile (Zeilen mit \n trennen).
- Schreiblinien, Lücken und Kästchen stehen in der Reihenfolge immer NACH der Frage oder Beschriftung, zu der sie gehören – erst „Vokal am Ende:“, dann seine Linien, dann „Konsonant am Ende:“, dann dessen Linien.
- Arbeitsraum nie weglassen: eine Frage, die einen Satz als Antwort verlangt, bekommt mindestens 2 schreiblinien; Rechenaufgaben mit Platz für Rechenwege oder Nebenrechnungen bekommen ein rechenfeld (mindestens 3 reihen); zeichenflaeche so hoch wie im Original (halbe Seite ≈ 10).
- Zuordnen zu Bildern: die bild-Bausteine werden im Blatt der Reihe nach mit 1, 2, 3 … nummeriert – in der Tabelle links „Bild 1“, „Bild 2“ … in derselben Reihenfolge, rechts "".
- Strichlisten/Zählaufgaben: tabelle mit zwei Spalten (Gegenstand | Anzahl bzw. Striche), nie mehr Spalten als im Original.
- Kopfzeilen und Beschriftungen in der Sprache des Originals lassen (englisches Blatt: "true"/"false", nicht „richtig“/„falsch“).
- Ankreuzen: mehrfach = true, sobald mehr als eine Antwort richtig sein kann („Kreuze alle … an“, Mehrfachauswahl, Option „beide“); nur bei genau einer richtigen Antwort false.
- Periodische Dezimalzahlen als „0,3 (Periode 3)“, Brüche als „3/4“, Potenzen als „10^3“.
- Zähl- und Suchbilder (Wimmelbild, „Zähle die …“, „Finde …“): EIN bild-Baustein mit dem ganzen Bild – nie in Einzelteile zerlegen, sonst stimmt das Ergebnis nicht.
- Schreiblinien gehören direkt hinter ihre Frage: jede Frage mit eigenem Antwortraum bekommt ihre eigenen schreiblinien (so viele wie im Original), nie alle Linien gesammelt am Ende.
- Abbildungen: box eng um die Grafik selbst – ohne Überschrift, Anweisung, Aufgabentext, Tabellen oder Schmuckfiguren daneben; mehrere getrennte Bilder (z. B. sechs Bilder zum Benennen) als einzelne bild-Bausteine, nicht als ein großes Bild.

Lücken: Unterstriche, Antwortlinien, leere Kästchen oder freie Stellen zum Eintragen werden zu {"typ": "luecke", "laenge": n} mit der ungefähren Zahl der Zeichen, die hineinpassen (Wort 10–15, lange Antwortlinie 30). Leerzeichen vor und nach einer Lücke gehören in die Textsegmente („soll “, Lücke, „ für“). Vorgedruckte Beispiellösungen und Hilfen übernehmen. Mathematische Ausdrücke als lesbarer Text („3/4“, „2 · 5 =“, „1,50 €“, „+ 200“).

Lösungsseiten und Lösungen („Lösung“, „Lösungsblatt“, Musterlösungen, eingetragene Antworten, angekreuzte Lösungen) komplett weglassen – das Kind bekommt nur die Aufgaben; ebenso alles, was nur für die Lehrkraft bestimmt ist („Hinweise für die Lehrkraft“, Erwartungshorizont, Lösungserwartung, Bewertung, Kompetenzen, Lehrplanbezug, Materialhinweise), Werbung, Hinweise auf andere Webseiten, Copyright- und Kontaktzeilen. Wiederholt eine Seite nur die Aufgaben einer anderen (z. B. als Lösungsblatt), erfasse sie nicht doppelt. Die Textebene eines PDFs hilft nur beim richtigen Abschreiben von Text, der im Bild sichtbar ist. Text, der im Bild nicht zu sehen ist (verdeckt, unter Feldern, weiß auf weiß – oft die Lösungen), NIE übernehmen. Persönliche Angaben (Namen von Kindern, Noten, handschriftliche Einträge) nicht übernehmen; ein leeres Namensfeld darf als text mit Lücke erfasst werden. seiten = Anzahl der Seiten des Originals.
TXT;
}
