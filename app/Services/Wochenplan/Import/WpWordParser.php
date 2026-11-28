<?php

namespace App\Services\Wochenplan\Import;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Liest Wochenpläne aus Word-Dateien (.docx) nach der Papiervorlage der Schule.
 *
 * Eine Datei kann mehrere Pläne enthalten (Lerngruppen-Plan + individuelle Pläne je Kind); jeder
 * beginnt mit einer Überschrift „N. Wochenplan vom …“ bzw. „N. Arbeitsplan …“. Erkannt werden:
 * Zeitraum, Lerngruppe/Klasse, Name des Kindes, tägliche Übungen (als Tabellenzelle oder als
 * Absätze nach „Tägliche Übung“, „5+5“ = Mathe-Übung), Fächer mit Aufgaben (Nummern „1.“, „*“ =
 * freiwillig), Selbsteinschätzung und „Empfehlungen für die Freiarbeit“ je Fach.
 *
 * Ergebnis ist reine Struktur (keine Datenbankzugriffe), damit Vorschau und Tests unabhängig sind.
 */
class WpWordParser
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Zeilen, die nur aus Punkten/Strichen/Unterstrichen bestehen (Schreiblinien, Abhakfelder). */
    private const PLATZHALTER = '/^[\s._…\-–—_\/|□]*$/u';

    private DOMXPath $xp;

    /**
     * @return array{plaene: array<int, array>, hinweise: array<int, string>}
     */
    public function parse(string $pfad): array
    {
        $zip = new ZipArchive();
        if ($pfad === '' || ! is_file($pfad) || $zip->open($pfad) !== true) {
            throw new RuntimeException('Die Datei ist kein lesbares Word-Dokument (.docx).');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('Im Dokument fehlt word/document.xml.');
        }

        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        $this->xp = new DOMXPath($dom);
        $this->xp->registerNamespace('w', self::W);

        $body = $this->xp->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException('Das Dokument hat keinen Inhalt.');
        }

        $plaene = [];
        $plan = null;
        $modus = null;          // null | 'kopf' | 'tue' | 'freiarbeit'
        $faFach = null;
        $kopfText = '';

        foreach ($body->childNodes as $knoten) {
            if (! $knoten instanceof DOMElement) {
                continue;
            }

            if ($knoten->localName === 'p') {
                $text = trim($this->absatzText($knoten));
                if ($text === '') {
                    continue;
                }

                if (preg_match('/^[^\p{L}\d]*(\d{1,2})\s*\.\s*(Wochen|Arbeits)plan\b/iu', $text)) {
                    if ($plan) {
                        $plaene[] = $this->abschliessen($plan, $kopfText);
                    }
                    $plan = $this->neuerPlan();
                    $kopfText = $text;
                    $modus = 'kopf';
                    $faFach = null;

                    continue;
                }
                if (! $plan) {
                    continue;
                }

                if (preg_match('/^Name\s*:?(.*)$/isu', $text, $m) || ($modus === 'kopf' && preg_match('/^[_.…\s]*([\p{L}][\p{L} .\-]*?)[_.…\s]*$/u', $text, $m) && preg_match('/[_.…]{3,}/u', $text))) {
                    $plan['name'] = $this->nameAus($m[1]);
                    $modus = null;

                    continue;
                }
                if (preg_match('/^[^\p{L}]*Tägliche\s+Übung/iu', $text)) {
                    $modus = 'tue';
                    $rest = trim(preg_replace('/^[^\p{L}]*Tägliche\s+Übung\s*(\(TÜ\))?/iu', '', $text));
                    if ($rest !== '') {
                        $plan['tue_zeilen'][] = $rest;
                    }

                    continue;
                }
                if (preg_match('/So\s+habe\s+ich\s+gearbeitet/iu', $text)) {
                    $plan['selbsteinschaetzung'] = true;
                    $modus = null;

                    continue;
                }
                if (preg_match('/Empfehlungen\s+für\s+die\s+Freiarbeit(.*)$/isu', $text, $m)) {
                    $modus = 'freiarbeit';
                    $faFach = trim($m[1], " :\t\n") ?: null;

                    continue;
                }

                if ($modus === 'kopf') {
                    $kopfText .= ' '.$text;
                } elseif ($modus === 'tue') {
                    foreach (explode("\n", $text) as $zeile) {
                        $plan['tue_zeilen'][] = $zeile;
                    }
                } elseif ($modus === 'freiarbeit' && preg_match('/^(.+?)\s*:\s*$/u', $text, $m)) {
                    $faFach = trim($m[1]);
                }

                continue;
            }

            if ($knoten->localName === 'tbl' && $plan) {
                if ($modus === 'kopf') {
                    $modus = null;
                }
                $this->tabelle($knoten, $plan, $modus, $faFach);
            }
        }

        if ($plan) {
            $plaene[] = $this->abschliessen($plan, $kopfText);
        }

        $hinweise = [];
        if (! $plaene) {
            $hinweise[] = 'Keine Überschrift „N. Wochenplan …“ gefunden – die Datei folgt nicht der Vorlage.';
        }

        return ['plaene' => $plaene, 'hinweise' => $hinweise];
    }

    // ─── Tabellen ───────────────────────────────────────────────────────────

    private function tabelle(DOMElement $tbl, array &$plan, ?string &$modus, ?string $faFach): void
    {
        foreach ($this->xp->query('w:tr', $tbl) as $tr) {
            $zellen = iterator_to_array($this->xp->query('w:tc', $tr));
            if (! $zellen) {
                continue;
            }
            $texte = array_map(fn ($tc) => $this->zellText($tc), $zellen);

            if ($modus === 'freiarbeit') {
                foreach (explode("\n", $texte[0]) as $eintrag) {
                    $eintrag = $this->bereinigen($eintrag);
                    if (! $this->istPlatzhalter($eintrag)) {
                        $plan['freiarbeit'][] = ['fach' => $faFach, 'text' => $eintrag];
                    }
                }

                continue;
            }

            // Tägliche Übung als Tabellenzelle (mit eingebettetem Wochenraster)
            foreach ($texte as $t) {
                if (preg_match('/^[^\p{L}]*Tägliche\s+Übung/iu', $t)) {
                    $zeilen = explode("\n", preg_replace('/^[^\p{L}]*Tägliche\s+Übung\s*(\(TÜ\))?/iu', '', $t));
                    $plan['tue_zeilen'] = array_merge($plan['tue_zeilen'], $zeilen);
                    $modus = null;

                    continue 2;
                }
            }

            // Wochenraster (Montag … Freitag / Woche 1) und Kopfzeile „Aufgaben“ überspringen
            $alle = implode(' ', $texte);
            if (preg_match('/\bMontag\b|\bWoche\s*\d?\s*$|^\s*(Woche\s*\d*)?\s*$/iu', $texte[0] ?? '') && preg_match('/Montag|Dienstag|Woche/iu', $alle)) {
                continue;
            }
            if (preg_match('/^\s*Aufgaben\s*$/iu', $texte[1] ?? '')) {
                $modus = null;

                continue;
            }
            if (count($texte) < 2) {
                continue;
            }

            $aufgaben = $this->aufgabenAus($this->zellAbsaetze($zellen[1]), $this->abhakFelder($texte));
            if (! $aufgaben || ! preg_match('/\p{L}/u', $texte[0].implode('', array_column($aufgaben, 'text')))) {
                continue;
            }
            $plan['faecher'][] = ['label' => $texte[0], 'aufgaben' => $aufgaben];
        }

        if ($modus === 'tue') {
            $modus = null;   // Tabelle nach „Tägliche Übung (TÜ)“ ist das Wochenraster
        }
    }

    // ─── Aufgaben zerlegen ──────────────────────────────────────────────────

    private const MONATE = 'Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember';

    /**
     * Zerlegt den Inhalt einer Aufgaben-Zelle. Nummern („1.“) und Sternchen („*“ = freiwillig)
     * beginnen eine Aufgabe; Text davor ist Einleitung. Ohne Nummern hilft die Zahl der
     * Abhak-Punkte („……“) in der Spalte „fertig“: so viele Absätze werden eigene Aufgaben.
     *
     * @param  string|array<int,string>  $absaetze  Absätze der Zelle (Zeilenumbrüche darin als \n)
     * @return array<int, array{text:string, freiwillig:bool}>
     */
    public function aufgabenAus(string|array $absaetze, int $erwartet = 0): array
    {
        $absaetze = is_array($absaetze) ? $absaetze : [$absaetze];

        $aufgaben = [];
        $einleitung = [];    // Absätze vor der ersten Nummer: [[zeile, …], …]
        foreach ($absaetze as $absatz) {
            $neuerAbsatz = true;
            foreach ($this->zeilenTrennen($absatz) as $zeile) {
                $zeile = trim(str_replace('✍', '', $this->bereinigen($zeile)));
                if ($zeile === '') {
                    continue;
                }
                $monat = '(?!(?:'.self::MONATE.')\b)';
                if (preg_match('/^(\*)?\s*(\d{1,2})\s*\.(?!\d)\s*+'.$monat.'(.*)$/u', $zeile, $m)) {
                    [$freiwillig, $inhalt] = [$m[1] === '*', $m[3]];
                } elseif (preg_match('/^\*\s*(.*)$/u', $zeile, $m)) {
                    [$freiwillig, $inhalt] = [true, $m[1]];
                } else {
                    $zeile = $this->istPlatzhalter($zeile) ? '______________________' : $zeile;
                    if ($aufgaben) {
                        $aufgaben[array_key_last($aufgaben)]['text'] .= "\n".$zeile;
                    } elseif ($neuerAbsatz && $zeile !== '______________________') {
                        $einleitung[] = [$zeile];
                    } elseif ($einleitung) {
                        $einleitung[array_key_last($einleitung)][] = $zeile;
                    }
                    $neuerAbsatz = false;

                    continue;
                }
                $neuerAbsatz = false;
                if (trim($inhalt) === '') {
                    continue;   // leere Nummer („2.“ ohne Text)
                }
                if ($this->istPlatzhalter($inhalt)) {
                    // „1.____ 2.____“ = Schreiblinien zur letzten Aufgabe bzw. Einleitung
                    if ($aufgaben) {
                        $aufgaben[array_key_last($aufgaben)]['text'] .= "\n".$zeile;
                    } elseif ($einleitung) {
                        $einleitung[array_key_last($einleitung)][] = $zeile;
                    }

                    continue;
                }
                $aufgaben[] = ['text' => $inhalt, 'freiwillig' => $freiwillig];
            }
        }

        if ($einleitung) {
            $texte = array_map(fn ($zeilen) => implode("\n", $zeilen), $einleitung);
            $nummeriert = array_filter($aufgaben, fn ($a) => ! $a['freiwillig']);
            if ($nummeriert && count($texte) === 1 && str_ends_with(rtrim($texte[0]), ':')) {
                // „Arbeite an deiner Nacherzählung:“ gehört zur ersten Aufgabe
                $aufgaben[0]['text'] = $texte[0].' '.$aufgaben[0]['text'];
            } else {
                $frei = max(1, $erwartet - count($nummeriert));
                $vorne = count($texte) <= $frei
                    ? array_map(fn ($t) => ['text' => $t, 'freiwillig' => false], $texte)
                    : [['text' => implode("\n", $texte), 'freiwillig' => false]];
                $aufgaben = array_merge($vorne, $aufgaben);
            }
        }

        foreach ($aufgaben as &$a) {
            $a['text'] = trim(preg_replace("/[ \t]+/u", ' ', $a['text']));
        }
        unset($a);

        return array_values(array_filter($aufgaben, fn ($a) => preg_match('/\p{L}/u', $a['text'])));
    }

    /** Zeilen eines Absatzes; eingebettete Nummern („…:1. Beende“) und Sternchen („…!* AB“) beginnen eine neue Zeile. */
    private function zeilenTrennen(string $absatz): array
    {
        $zeilen = [];
        foreach (explode("\n", $absatz) as $zeile) {
            // nur nach Satzzeichen oder direkt am Wort („…Text2. Lies“), nicht bei Datumsangaben („ab 10. Dezember“)
            $zeile = preg_replace('/(?:(?<=[:!?)»“"])\s*|(?<=\p{L})|(?<=[a-zäöüß]\.)\s+)(?=\d{1,2}\.\s*(?!(?:'.self::MONATE.')\b)[A-ZÄÖÜ„«»"“])/u', "\n", $zeile);
            $zeile = preg_replace('/(?<=\S)\s*(?=\*\s*[A-ZÄÖÜ„«»"“])/u', "\n", $zeile);
            array_push($zeilen, ...explode("\n", $zeile));
        }

        return $zeilen;
    }

    /** Anzahl der Abhak-Felder („……“) in den Spalten fertig/kontrolliert/Unterschrift (größter Wert). */
    private function abhakFelder(array $texte): int
    {
        $max = 0;
        foreach (array_slice($texte, 2) as $t) {
            $max = max($max, preg_match_all('/[.…]{2,}/u', $t));
        }

        return $max;
    }

    // ─── Abschluss eines Plans ──────────────────────────────────────────────

    private function neuerPlan(): array
    {
        return ['name' => null, 'tue_zeilen' => [], 'faecher' => [], 'freiarbeit' => [], 'selbsteinschaetzung' => false];
    }

    private function abschliessen(array $plan, string $kopf): array
    {
        $kopf = preg_replace('/^[^\p{L}\d]+/u', '', $this->bereinigen($kopf));
        preg_match('/^(\d{1,2})\s*\.\s*(Wochen|Arbeits)plan/iu', $kopf, $m);
        [$von, $bis, $rest] = $this->zeitraum($kopf);

        $rest = trim(preg_replace('/^\s*:?\s*/u', '', $rest));
        preg_match('/Klasse\s*(\d)/iu', $kopf, $k) || preg_match('/\b(\d)\s*$/u', $rest, $k);
        $gruppe = trim(preg_replace('/Klasse\s*\d\s*,?/iu', '', $rest), " ,:\t");

        return [
            'nummer' => isset($m[1]) ? (int) $m[1] : null,
            'art' => isset($m[2]) && mb_strtolower($m[2]) === 'arbeits' ? 'Arbeitsplan' : 'Wochenplan',
            'kopf' => $kopf,
            'von' => $von,
            'bis' => $bis,
            'stufe' => isset($k[1]) ? (int) $k[1] : null,
            'gruppe' => $gruppe !== '' ? $gruppe : null,
            'kind' => $plan['name'],
            'uebungen' => $this->uebungen($plan['tue_zeilen']),
            'faecher' => $plan['faecher'],
            'freiarbeit' => $plan['freiarbeit'],
            'selbsteinschaetzung' => $plan['selbsteinschaetzung'],
        ];
    }

    /** @return array{0:?string, 1:?string, 2:string} von, bis (Y-m-d) und der Text nach dem Zeitraum */
    public function zeitraum(string $kopf): array
    {
        $muster = '/(\d{1,2})\s*\.\s*(?:(\d{1,2})\s*\.?)?\s*(\d{4})?\s*(?:-|–|bis)\s*(\d{1,2})\s*\.\s*(\d{1,2})\s*\.?\s*(\d{4})?/u';
        if (! preg_match($muster, $kopf, $m, PREG_OFFSET_CAPTURE)) {
            return [null, null, preg_replace('/^.*?plan\b\s*(vom|für)?/iu', '', $kopf)];
        }
        $d1 = (int) $m[1][0];
        $m2 = (int) $m[5][0];
        $m1 = $m[2][0] !== '' ? (int) $m[2][0] : $m2;
        $d2 = (int) $m[4][0];
        $j2 = $m[6][0] ?? '';
        $j1 = $m[3][0] ?? '';
        $jahrBis = $j2 !== '' ? (int) $j2 : ($j1 !== '' ? (int) $j1 : (int) now()->year);
        $jahrVon = $j1 !== '' ? (int) $j1 : ($m1 > $m2 ? $jahrBis - 1 : $jahrBis);

        $von = checkdate($m1, $d1, $jahrVon) ? sprintf('%04d-%02d-%02d', $jahrVon, $m1, $d1) : null;
        $bis = checkdate($m2, $d2, $jahrBis) ? sprintf('%04d-%02d-%02d', $jahrBis, $m2, $d2) : null;
        $rest = substr($kopf, $m[0][1] + strlen($m[0][0]));

        return [$von, $bis, $rest];
    }

    /** @return array<int, array{kuerzel:?string, text:string}> */
    private function uebungen(array $zeilen): array
    {
        $uebungen = [];
        $aktuell = null;
        foreach ($zeilen as $zeile) {
            $zeile = $this->bereinigen($zeile);
            if ($zeile === '' || $this->istPlatzhalter($zeile) || preg_match('/^(Montag|Dienstag|Mittwoch|Donnerstag|Freitag|Woche\s*\d*|5\+5)$/iu', $zeile)) {
                continue;
            }
            $kuerzel = null;
            if (preg_match('/^5\s*\+\s*5\s*(.*)$/u', $zeile, $m)) {
                $kuerzel = '5+5';
                $zeile = trim($m[1]);
            } elseif (preg_match('/^✍\s*(.*)$/u', $zeile, $m)) {
                $kuerzel = '✍';
                $zeile = trim($m[1]);
            } elseif (preg_match('/^TÜ\s+(Deutsch|Sprache|Mathe(?:matik)?|\p{L}+)\s*:?\s*(.*)$/u', $zeile, $m)) {
                // „TÜ Deutsch …“ / „TÜ Mathe …“
                $kuerzel = match (true) {
                    (bool) preg_match('/^(Deutsch|Sprache)$/u', $m[1]) => '✍',
                    str_starts_with($m[1], 'Mathe') => '5+5',
                    default => mb_substr($m[1], 0, 12),
                };
                $zeile = trim($m[2]);
            }
            // eingebettetes „5+5“ (Textrahmen ohne Umbruch): „… Seite 32, 335+5 Matherad …“ → nur bei Leerraum danach
            if ($kuerzel === null && preg_match('/^(.*?)(5\+5)\s{2,}(.+)$/u', $zeile, $m)) {
                $this->uebungAnhaengen($uebungen, $aktuell, null, trim($m[1]));
                $kuerzel = '5+5';
                $zeile = trim($m[3]);
            }
            $this->uebungAnhaengen($uebungen, $aktuell, $kuerzel, $zeile);
        }
        if ($aktuell) {
            $uebungen[] = $aktuell;
        }

        foreach ($uebungen as &$u) {
            if ($u['kuerzel'] === null && count($uebungen) > 1) {
                $u['kuerzel'] = '✍';   // in der Vorlage steht vor der Deutsch-Übung immer der Stift
            }
        }

        return array_values(array_filter($uebungen, fn ($u) => $u['text'] !== ''));
    }

    private function uebungAnhaengen(array &$uebungen, ?array &$aktuell, ?string $kuerzel, string $text): void
    {
        if ($aktuell === null || $kuerzel !== null) {
            if ($aktuell) {
                $uebungen[] = $aktuell;
            }
            $aktuell = ['kuerzel' => $kuerzel, 'text' => $text];

            return;
        }
        $aktuell['text'] = trim($aktuell['text']."\n".$text);
    }

    // ─── Text ───────────────────────────────────────────────────────────────

    /** Text eines Absatzes: Tabulator → Leerzeichen, Zeilenumbruch → \n, Wingdings-Stift → ✍. */
    private function absatzText(DOMElement $p): string
    {
        $text = '';
        foreach ($this->xp->query('.//w:t | .//w:tab | .//w:br | .//w:cr | .//w:sym', $p) as $el) {
            // Text in Textrahmen/Grafiken gehört nicht zum Fließtext
            if ($this->xp->query('ancestor::w:txbxContent', $el)->length) {
                continue;
            }
            $text .= match ($el->localName) {
                't' => $el->textContent,
                'tab' => ' ',
                'br', 'cr' => "\n",
                'sym' => $this->symbol($el),
                default => '',
            };
        }

        return $text;
    }

    /** Wingdings-Symbole: nur der Schreibstift (Tägliche Übung) zählt, Ordner/Punkte/Smileys entfallen. */
    private function symbol(DOMElement $sym): string
    {
        $zeichen = strtoupper((string) $sym->getAttributeNS(self::W, 'char'));
        $code = hexdec(substr($zeichen, -2) ?: '0');

        return in_array($code, [0x21, 0x3F, 0x40], true) ? '✍ ' : '';
    }

    /** Direkte Absätze einer Zelle (eingebettete Tabellen wie das Wochenraster bleiben außen vor). */
    private function zellText(DOMElement $tc): string
    {
        return trim(implode("\n", $this->zellAbsaetze($tc)));
    }

    /** @return array<int, string> */
    private function zellAbsaetze(DOMElement $tc): array
    {
        $absaetze = [];
        foreach ($this->xp->query('w:p', $tc) as $p) {
            $absaetze[] = $this->absatzText($p);
        }

        return $absaetze;
    }

    private function bereinigen(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{2009}", "\u{202F}"], ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);

        return trim($text);
    }

    private function istPlatzhalter(string $text): bool
    {
        return (bool) preg_match(self::PLATZHALTER, $text);
    }

    private function nameAus(string $roh): ?string
    {
        $name = trim(preg_replace('/[_.…:]+/u', ' ', $roh));
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === '' || str_starts_with(mb_strtolower($name), 'vorlage')) {
            return null;
        }

        // „Merle W“ → Initiale wieder mit Punkt
        return preg_replace('/\b(\p{Lu})$/u', '$1.', $name);
    }
}
