<?php

namespace App\Support;

/**
 * Baustein-Format der Angepassten Arbeitsblätter – einzige Stelle, die Typen und Felder sichert.
 *
 * ```json
 * { "seiten": 2, "bausteine": [
 *   {"id":"b1","typ":"ueberschrift","text":"Brüche"},
 *   {"id":"b2","typ":"anweisung","text":"Ergänze die fehlenden Zahlen."},
 *   {"id":"b3","typ":"aufgabe","nummer":"1","inhalt":[
 *      {"typ":"text","text":"3/4 = "},{"typ":"luecke","laenge":4},{"typ":"text","text":"/8"}],
 *    "kinder":[ …Bausteine ohne eigene kinder… ]},
 *   {"id":"b4","typ":"ankreuzen","frage":"…","optionen":["ja","nein"]},
 *   {"id":"b5","typ":"tabelle","zeilen":[["a","b"],["",""]],"kopfzeile":true},
 *   {"id":"b6","typ":"schreiblinien","anzahl":3},
 *   {"id":"b9","typ":"kasten","titel":"Eintritt","inhalt":[{"typ":"text","text":"Kinder: 8 €\nErwachsene: 16 €"}]},
 *   {"id":"b10","typ":"rechenfeld","reihen":2,"inhalt":[…Vorgabe, z. B. „2 · ___ = ___ €“…]},
 *   {"id":"b7","typ":"bild","media_id":12,"beschreibung":"Kreis, ¾ gefärbt",
 *    "quelle":{"seite":1,"box":[0.1,0.4,0.5,0.7]}},
 *   {"id":"b8","typ":"text","inhalt":[…]}
 * ]}
 * ```
 *
 * - Die Struktur enthält **kein** Layout – das kommt ausschließlich aus dem Sehprofil ({@see AbProfil}).
 * - `aufgabe` kann Unterbausteine (`kinder`, eine Ebene) haben, damit „Aufgabe nie teilen“
 *   ganze Gruppen zusammenhält.
 * - Unbekannte Typen werden zu `text`; Texte werden gekürzt, Anzahl der Bausteine begrenzt.
 * - `bild.quelle.box` = relative Box [x1, y1, x2, y2] (0–1) auf der Seite des Originals.
 */
class AbStruktur
{
    public const TYPEN = [
        'ueberschrift' => 'Überschrift',
        'anweisung' => 'Arbeitsanweisung',
        'text' => 'Text',
        'aufgabe' => 'Aufgabe',
        'ankreuzen' => 'Ankreuzen',
        'tabelle' => 'Tabelle',
        'kasten' => 'Kasten',
        'rechenfeld' => 'Rechenfeld',
        'schreiblinien' => 'Schreiblinien',
        'bild' => 'Abbildung',
    ];

    public const MAX_BAUSTEINE = 300;

    public const MAX_TEXT = 2000;

    public const MAX_KURZTEXT = 300;

    public const MAX_OPTIONEN = 12;

    public const MAX_ZEILEN = 30;

    public const MAX_SPALTEN = 10;

    public const MAX_SEGMENTE = 100;

    public static function leer(): array
    {
        return ['seiten' => 1, 'bausteine' => []];
    }

    /** Bringt jede Eingabe (KI, Editor, Altbestand) in das gültige Format. */
    public static function normalisieren(mixed $struktur): array
    {
        if (is_string($struktur)) {
            $struktur = json_decode($struktur, true);
        }
        if (! is_array($struktur)) {
            return self::leer();
        }

        $zaehler = 0;
        $ids = [];
        $bausteine = [];
        foreach (array_values((array) ($struktur['bausteine'] ?? [])) as $roh) {
            if ($zaehler >= self::MAX_BAUSTEINE) {
                break;
            }
            $baustein = self::baustein($roh, $ids, $zaehler, true);
            if ($baustein !== null) {
                $bausteine[] = $baustein;
            }
        }

        $seiten = (int) ($struktur['seiten'] ?? 1);

        return [
            'seiten' => max(1, min(50, $seiten)),
            'bausteine' => $bausteine,
        ];
    }

    /**
     * Prüft eine Struktur (z. B. vor dem Speichern aus dem Editor) und liefert Fehlermeldungen.
     *
     * @return array<int, string>
     */
    public static function pruefen(mixed $struktur): array
    {
        if (! is_array($struktur) || ! isset($struktur['bausteine']) || ! is_array($struktur['bausteine'])) {
            return ['Die Struktur muss eine Liste „bausteine“ enthalten.'];
        }

        $fehler = [];
        if (self::anzahl($struktur['bausteine']) > self::MAX_BAUSTEINE) {
            $fehler[] = 'Ein Blatt darf höchstens '.self::MAX_BAUSTEINE.' Bausteine haben.';
        }
        foreach ($struktur['bausteine'] as $i => $b) {
            if (! is_array($b) || ! isset($b['typ'])) {
                $fehler[] = 'Baustein '.($i + 1).' hat keinen Typ.';
            } elseif (! isset(self::TYPEN[$b['typ']])) {
                $fehler[] = 'Baustein '.($i + 1).' hat einen unbekannten Typ („'.mb_substr((string) $b['typ'], 0, 30).'“).';
            }
        }

        return $fehler;
    }

    /** Anzahl der Bausteine inkl. Unterbausteine. */
    public static function anzahl(array $bausteine): int
    {
        $n = 0;
        foreach ($bausteine as $b) {
            $n++;
            if (is_array($b) && ! empty($b['kinder']) && is_array($b['kinder'])) {
                $n += count($b['kinder']);
            }
        }

        return $n;
    }

    /** Alle Media-IDs der Abbildungen (inkl. Unterbausteine). */
    public static function mediaIds(array $struktur): array
    {
        $ids = [];
        foreach (self::alle($struktur) as $b) {
            if ($b['typ'] === 'bild' && ! empty($b['media_id'])) {
                $ids[] = (int) $b['media_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Flache Liste aller Bausteine (Unterbausteine nach ihrer Aufgabe).
     *
     * @return array<int, array>
     */
    public static function alle(array $struktur): array
    {
        $liste = [];
        foreach ((array) ($struktur['bausteine'] ?? []) as $b) {
            $liste[] = $b;
            foreach ((array) ($b['kinder'] ?? []) as $k) {
                $liste[] = $k;
            }
        }

        return $liste;
    }

    /** Reiner Text (Suche, Vorschau, Fallback). */
    public static function klartext(array $struktur): string
    {
        $teile = [];
        foreach (self::alle($struktur) as $b) {
            $teile[] = match ($b['typ']) {
                'ueberschrift', 'anweisung' => $b['text'] ?? '',
                'text', 'aufgabe' => trim(($b['nummer'] ?? '').' '.self::segmentText($b['inhalt'] ?? [])),
                'ankreuzen' => trim(($b['frage'] ?? '').' '.implode(' / ', $b['optionen'] ?? [])),
                'tabelle' => implode(' ', array_map(fn ($z) => implode(' | ', $z), $b['zeilen'] ?? [])),
                'kasten' => trim(($b['titel'] ?? '').' '.self::segmentText($b['inhalt'] ?? [])),
                'rechenfeld' => self::segmentText($b['inhalt'] ?? []),
                'bild' => $b['beschreibung'] ?? '',
                default => '',
            };
        }

        return trim(implode("\n", array_filter($teile, fn ($t) => trim((string) $t) !== '')));
    }

    public static function segmentText(array $segmente): string
    {
        return implode('', array_map(fn ($s) => ($s['typ'] ?? '') === 'luecke' ? str_repeat('_', max(3, (int) ($s['laenge'] ?? 4))) : (string) ($s['text'] ?? ''), $segmente));
    }

    // ─── intern ───────────────────────────────────────────────────────────────

    private static function baustein(mixed $roh, array &$ids, int &$zaehler, bool $mitKindern): ?array
    {
        if (is_string($roh)) {
            $roh = ['typ' => 'text', 'text' => $roh];
        }
        if (! is_array($roh)) {
            return null;
        }

        $typ = is_string($roh['typ'] ?? null) && isset(self::TYPEN[$roh['typ']]) ? $roh['typ'] : 'text';
        $zaehler++;
        $id = self::id($roh['id'] ?? null, $ids);

        $b = match ($typ) {
            'ueberschrift', 'anweisung' => [
                'text' => self::text($roh['text'] ?? self::segmentText(self::segmente($roh['inhalt'] ?? [])), self::MAX_KURZTEXT * 2),
            ],
            'text' => [
                'inhalt' => self::inhalt($roh),
            ],
            'aufgabe' => [
                'nummer' => self::text($roh['nummer'] ?? '', 10),
                'inhalt' => self::inhalt($roh),
            ],
            'ankreuzen' => [
                'frage' => self::text($roh['frage'] ?? ($roh['text'] ?? ''), self::MAX_TEXT),
                'optionen' => array_values(array_slice(array_filter(array_map(
                    fn ($o) => self::text(is_array($o) ? ($o['text'] ?? '') : $o, self::MAX_KURZTEXT),
                    (array) ($roh['optionen'] ?? [])
                ), fn ($o) => $o !== ''), 0, self::MAX_OPTIONEN)),
                'mehrfach' => (bool) ($roh['mehrfach'] ?? false),
                // im Original bereits angekreuzt (Beispiel): Indizes der Optionen
                'angekreuzt' => array_values(array_unique(array_filter(array_map('intval', array_filter((array) ($roh['angekreuzt'] ?? []), 'is_numeric')), fn ($i) => $i >= 0 && $i < self::MAX_OPTIONEN))),
            ],
            'kasten' => [
                'titel' => self::text($roh['titel'] ?? '', self::MAX_KURZTEXT),
                'inhalt' => self::inhalt($roh),
            ],
            'rechenfeld' => [
                'reihen' => max(1, min(20, (int) ($roh['reihen'] ?? 2))),
                'inhalt' => self::inhalt($roh),
            ],
            'tabelle' => self::tabelle($roh),
            'schreiblinien' => [
                'anzahl' => max(1, min(30, (int) ($roh['anzahl'] ?? 3))),
            ],
            'bild' => self::bild($roh),
        };

        $baustein = array_merge(['id' => $id, 'typ' => $typ], $b);

        if ($typ === 'aufgabe') {
            $kinder = [];
            if ($mitKindern) {
                foreach (array_values((array) ($roh['kinder'] ?? [])) as $kind) {
                    if ($zaehler >= self::MAX_BAUSTEINE) {
                        break;
                    }
                    $k = self::baustein($kind, $ids, $zaehler, false);
                    if ($k !== null) {
                        $kinder[] = $k;
                    }
                }
            }
            $baustein['kinder'] = $kinder;
        }

        return $baustein;
    }

    private static function id(mixed $id, array &$ids): string
    {
        $id = is_scalar($id) ? (string) $id : '';
        if (! preg_match('/^[A-Za-z0-9_-]{1,24}$/', $id) || isset($ids[$id])) {
            $n = count($ids) + 1;
            do {
                $id = 'b'.$n++;
            } while (isset($ids[$id]));
        }
        $ids[$id] = true;

        return $id;
    }

    private static function text(mixed $wert, int $max): string
    {
        if (! is_scalar($wert)) {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", (string) $wert);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        return trim(mb_substr($text, 0, $max));
    }

    /** Inhalt aus `inhalt` (Segmente) oder – ersatzweise – aus `text`. */
    private static function inhalt(array $roh): array
    {
        if (isset($roh['inhalt']) && is_array($roh['inhalt'])) {
            return self::segmente($roh['inhalt']);
        }
        $text = self::text($roh['text'] ?? '', self::MAX_TEXT);

        return $text === '' ? [] : self::textMitLuecken($text);
    }

    private static function segmente(mixed $segmente): array
    {
        if (is_string($segmente)) {
            return self::textMitLuecken(self::text($segmente, self::MAX_TEXT));
        }
        $ergebnis = [];
        foreach (array_slice((array) $segmente, 0, self::MAX_SEGMENTE) as $s) {
            if (is_string($s)) {
                $s = ['typ' => 'text', 'text' => $s];
            }
            if (! is_array($s)) {
                continue;
            }
            if (($s['typ'] ?? 'text') === 'luecke') {
                $ergebnis[] = ['typ' => 'luecke', 'laenge' => max(1, min(40, (int) ($s['laenge'] ?? 4)))];
            } else {
                $text = self::text($s['text'] ?? '', self::MAX_TEXT);
                // Leerzeichen am Rand bleiben erhalten („3/4 = “)
                $roh = is_scalar($s['text'] ?? null) ? (string) $s['text'] : '';
                if ($text === '' && trim($roh) === '') {
                    if ($roh !== '' && $ergebnis) {
                        $ergebnis[] = ['typ' => 'text', 'text' => ' '];
                    }
                    continue;
                }
                $ergebnis[] = ['typ' => 'text', 'text' => self::randLeer($roh, $text)];
            }
        }

        return $ergebnis;
    }

    private static function randLeer(string $roh, string $text): string
    {
        $vorne = preg_match('/^\s/u', $roh) ? ' ' : '';
        $hinten = preg_match('/\s$/u', $roh) ? ' ' : '';

        return $vorne.$text.$hinten;
    }

    /** „3/4 = ___ /8“ → Text + Lücke + Text (Unterstriche ab 3 Zeichen werden Lücken). */
    private static function textMitLuecken(string $text): array
    {
        $teile = preg_split('/(_{3,})/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $segmente = [];
        foreach ($teile as $teil) {
            $segmente[] = preg_match('/^_{3,}$/u', $teil)
                ? ['typ' => 'luecke', 'laenge' => min(40, mb_strlen($teil))]
                : ['typ' => 'text', 'text' => $teil];
        }

        return $segmente;
    }

    private static function tabelle(array $roh): array
    {
        $zeilen = [];
        foreach (array_slice(array_values((array) ($roh['zeilen'] ?? [])), 0, self::MAX_ZEILEN) as $zeile) {
            $zellen = [];
            foreach (array_slice(array_values((array) $zeile), 0, self::MAX_SPALTEN) as $zelle) {
                $zellen[] = self::text(is_array($zelle) ? ($zelle['text'] ?? '') : $zelle, 500);
            }
            $zeilen[] = $zellen;
        }
        // gleichmäßige Spaltenzahl
        $spalten = max(1, ...array_map('count', $zeilen ?: [[1]]));
        $zeilen = array_map(fn ($z) => array_pad($z, $spalten, ''), $zeilen ?: [['']]);

        return ['zeilen' => $zeilen, 'kopfzeile' => (bool) ($roh['kopfzeile'] ?? false)];
    }

    private static function bild(array $roh): array
    {
        $mediaId = isset($roh['media_id']) && is_numeric($roh['media_id']) && (int) $roh['media_id'] > 0 ? (int) $roh['media_id'] : null;
        $quelle = null;
        if (isset($roh['quelle']) && is_array($roh['quelle'])) {
            $box = array_values((array) ($roh['quelle']['box'] ?? []));
            if (count($box) === 4 && count(array_filter($box, 'is_numeric')) === 4) {
                $box = array_map(fn ($v) => round(min(1, max(0, (float) $v)), 4), $box);
                [$x1, $y1, $x2, $y2] = $box;
                if ($x2 > $x1 && $y2 > $y1) {
                    $quelle = ['seite' => max(1, min(50, (int) ($roh['quelle']['seite'] ?? 1))), 'box' => [$x1, $y1, $x2, $y2]];
                }
            }
        }

        return [
            'media_id' => $mediaId,
            'beschreibung' => self::text($roh['beschreibung'] ?? '', self::MAX_KURZTEXT),
            'quelle' => $quelle,
        ];
    }
}
