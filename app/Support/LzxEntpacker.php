<?php

namespace App\Support;

use RuntimeException;

/**
 * LZX-Entpacker für CAB-Archive (Microsoft LZX, wie in libmspack „lzxd“): Rahmen zu 32 KB,
 * Blocktypen verbatim/aligned/unkomprimiert, Huffman-Bäume mit Delta-Längen über einen
 * Vorbaum, wiederholte Abstände R0–R2 und die Intel-E8-Rückübersetzung.
 *
 * Die ganze Ausgabe bleibt im Speicher (für die Schriftpakete < 1 MB), daher kein Ringpuffer.
 */
class LzxEntpacker
{
    private const RAHMEN = 32768;

    private const MIN_MATCH = 2;

    private const LAENGEN_SEKUNDAER = 249;

    private const POS_SLOTS = [15 => 30, 16 => 32, 17 => 34, 18 => 36, 19 => 38, 20 => 42, 21 => 50];

    private string $ein = '';

    private int $einPos = 0;

    private int $puffer = 0;

    private int $bits = 0;

    private array $extraBits = [];

    private array $positionBasis = [];

    private int $posSlots;

    /** Längen der Bäume (Deltas beziehen sich auf die vorherigen Längen). */
    private array $hauptLaengen = [];

    private array $laengenLaengen = [];

    private array $haupt = [];

    private array $laenge = [];

    private array $ausgerichtet = [];

    public function __construct(int $fensterBits)
    {
        if (! isset(self::POS_SLOTS[$fensterBits])) {
            throw new RuntimeException('LZX-Fenstergröße '.$fensterBits.' wird nicht unterstützt.');
        }
        $this->posSlots = self::POS_SLOTS[$fensterBits];

        $j = 0;
        for ($i = 0; $i < 51; $i += 2) {
            $this->extraBits[$i] = $j;
            $this->extraBits[$i + 1] = $j;
            if ($i !== 0 && $j < 17) {
                $j++;
            }
        }
        $j = 0;
        for ($i = 0; $i < 51; $i++) {
            $this->positionBasis[$i] = $j;
            $j += 1 << $this->extraBits[$i];
        }
        $this->hauptLaengen = array_fill(0, 256 + $this->posSlots * 8, 0);
        $this->laengenLaengen = array_fill(0, self::LAENGEN_SEKUNDAER, 0);
    }

    public function entpacken(string $eingabe, int $groesse): string
    {
        $this->ein = $eingabe;
        $this->einPos = 0;
        $this->puffer = 0;
        $this->bits = 0;

        $fenster = '';          // unübersetzte Ausgabe (Grundlage für Rückverweise)
        $ergebnis = '';         // Ausgabe nach E8-Rückübersetzung
        $r0 = $r1 = $r2 = 1;
        $blockTyp = 0;
        $blockLaenge = 0;
        $blockRest = 0;
        $intelGroesse = 0;
        $intelAktiv = false;

        // Kopf: Intel-E8-Übersetzung
        if ($this->lesen(1)) {
            $hoch = $this->lesen(16);
            $intelGroesse = ($hoch << 16) | $this->lesen(16);
        }

        $rahmen = 0;
        while (strlen($ergebnis) < $groesse) {
            $rahmenStart = $rahmen * self::RAHMEN;
            $rahmenEnde = min($groesse, $rahmenStart + self::RAHMEN);

            while (strlen($fenster) < $rahmenEnde) {
                if ($blockRest === 0) {
                    if ($blockTyp === 3 && ($blockLaenge & 1)) {
                        $this->einPos++; // Füllbyte nach ungeradem unkomprimiertem Block
                    }
                    $blockTyp = $this->lesen(3);
                    $hoch = $this->lesen(16);
                    $blockLaenge = $blockRest = ($hoch << 8) | $this->lesen(8);

                    if ($blockTyp === 2) {
                        $laengen = [];
                        for ($i = 0; $i < 8; $i++) {
                            $laengen[$i] = $this->lesen(3);
                        }
                        $this->ausgerichtet = $this->tabelle($laengen);
                    }
                    if ($blockTyp === 1 || $blockTyp === 2) {
                        $this->laengenLesen($this->hauptLaengen, 0, 256);
                        $this->laengenLesen($this->hauptLaengen, 256, 256 + $this->posSlots * 8);
                        $this->haupt = $this->tabelle($this->hauptLaengen);
                        if ($this->hauptLaengen[0xE8] !== 0) {
                            $intelAktiv = true;
                        }
                        $this->laengenLesen($this->laengenLaengen, 0, self::LAENGEN_SEKUNDAER);
                        $this->laenge = $this->tabelle($this->laengenLaengen);
                    } elseif ($blockTyp === 3) {
                        $intelAktiv = true;
                        $this->sicherstellen(16);
                        if ($this->bits > 16) {
                            $this->einPos -= 2;
                        }
                        $this->puffer = 0;
                        $this->bits = 0;
                        [$r0, $r1, $r2] = array_values(unpack('V3', substr($this->ein, $this->einPos, 12)));
                        $this->einPos += 12;
                    } else {
                        throw new RuntimeException('Ungültiger LZX-Blocktyp '.$blockTyp.'.');
                    }
                }

                $lauf = min($blockRest, $rahmenEnde - strlen($fenster));
                $vorher = strlen($fenster);

                if ($blockTyp === 3) {
                    $fenster .= substr($this->ein, $this->einPos, $lauf);
                    $this->einPos += $lauf;
                } else {
                    $ziel = $vorher + $lauf;
                    while (strlen($fenster) < $ziel) {
                        $element = $this->symbol($this->haupt);
                        if ($element < 256) {
                            $fenster .= chr($element);
                            continue;
                        }
                        $element -= 256;
                        $laenge = $element & 7;
                        if ($laenge === 7) {
                            $laenge += $this->symbol($this->laenge);
                        }
                        $laenge += self::MIN_MATCH;
                        $abstand = $element >> 3;

                        if ($abstand > 2) {
                            if ($abstand !== 3) {
                                $extra = $this->extraBits[$abstand];
                                $abstand = $this->positionBasis[$abstand] - 2;
                                if ($blockTyp === 2 && $extra >= 3) {
                                    if ($extra > 3) {
                                        $abstand += $this->lesen($extra - 3) << 3;
                                    }
                                    $abstand += $this->symbol($this->ausgerichtet);
                                } elseif ($extra > 0) {
                                    $abstand += $this->lesen($extra);
                                }
                            } else {
                                $abstand = 1;
                            }
                            $r2 = $r1;
                            $r1 = $r0;
                            $r0 = $abstand;
                        } elseif ($abstand === 0) {
                            $abstand = $r0;
                        } elseif ($abstand === 1) {
                            $abstand = $r1;
                            $r1 = $r0;
                            $r0 = $abstand;
                        } else {
                            $abstand = $r2;
                            $r2 = $r0;
                            $r0 = $abstand;
                        }

                        $l = strlen($fenster);
                        if ($abstand > $l || $abstand < 1) {
                            throw new RuntimeException('Ungültiger LZX-Rückverweis.');
                        }
                        $teil = substr($fenster, $l - $abstand, min($laenge, $abstand));
                        while (strlen($teil) < $laenge) {
                            $teil .= $teil;
                        }
                        $fenster .= substr($teil, 0, $laenge);
                    }
                }

                $blockRest -= strlen($fenster) - $vorher;
                if ($blockRest < 0) {
                    throw new RuntimeException('LZX-Block überschreitet seine Länge.');
                }
            }

            // Rahmen fertig: E8-Rückübersetzung auf eine Kopie, Bitstrom neu ausrichten
            $daten = substr($fenster, $rahmenStart, $rahmenEnde - $rahmenStart);
            if ($intelAktiv && $intelGroesse && $rahmen < 32768 && strlen($daten) > 10) {
                $daten = $this->e8($daten, $rahmenStart, $intelGroesse);
            }
            $ergebnis .= $daten;
            if ($this->bits > 0) {
                $this->sicherstellen(16);
            }
            if ($this->bits & 15) {
                $this->entfernen($this->bits & 15);
            }
            $rahmen++;
        }

        return $ergebnis;
    }

    // ─── Bitstrom (16-Bit-Wörter, Little Endian, höchstwertiges Bit zuerst) ───

    private function sicherstellen(int $n): void
    {
        while ($this->bits < $n) {
            $lo = $this->einPos < strlen($this->ein) ? ord($this->ein[$this->einPos]) : 0;
            $hi = $this->einPos + 1 < strlen($this->ein) ? ord($this->ein[$this->einPos + 1]) : 0;
            $this->einPos += 2;
            $this->puffer = ($this->puffer << 16) | ($hi << 8) | $lo;
            $this->bits += 16;
        }
    }

    private function vorschau(int $n): int
    {
        $this->sicherstellen($n);

        return ($this->puffer >> ($this->bits - $n)) & ((1 << $n) - 1);
    }

    private function entfernen(int $n): void
    {
        $this->bits -= $n;
        $this->puffer &= (1 << $this->bits) - 1;
    }

    private function lesen(int $n): int
    {
        if ($n === 0) {
            return 0;
        }
        $wert = $this->vorschau($n);
        $this->entfernen($n);

        return $wert;
    }

    // ─── Huffman ──────────────────────────────────────────────────────────────

    /** Kanonische Codes → Nachschlagetabelle [maxLaenge, eintraege (symbol << 5 | laenge)]. */
    private function tabelle(array $laengen): array
    {
        $max = max(1, max($laengen));
        $eintraege = array_fill(0, 1 << $max, -1);
        $code = 0;
        for ($l = 1; $l <= $max; $l++) {
            foreach ($laengen as $symbol => $laenge) {
                if ($laenge !== $l) {
                    continue;
                }
                $start = $code << ($max - $l);
                $anzahl = 1 << ($max - $l);
                $wert = ($symbol << 5) | $l;
                for ($i = 0; $i < $anzahl; $i++) {
                    $eintraege[$start + $i] = $wert;
                }
                $code++;
            }
            $code <<= 1;
        }

        return [$max, $eintraege];
    }

    private function symbol(array $tabelle): int
    {
        [$max, $eintraege] = $tabelle;
        $wert = $eintraege[$this->vorschau($max)] ?? -1;
        if ($wert < 0) {
            throw new RuntimeException('Ungültiger Huffman-Code im LZX-Strom.');
        }
        $this->entfernen($wert & 31);

        return $wert >> 5;
    }

    /** Längen über den Vorbaum lesen (Deltas modulo 17, Lauflängen für Nullen und Wiederholungen). */
    private function laengenLesen(array &$laengen, int $erster, int $letzter): void
    {
        $vor = [];
        for ($i = 0; $i < 20; $i++) {
            $vor[$i] = $this->lesen(4);
        }
        $vorbaum = $this->tabelle($vor);

        for ($x = $erster; $x < $letzter;) {
            $z = $this->symbol($vorbaum);
            if ($z === 17) {
                $y = $this->lesen(4) + 4;
                while ($y-- > 0 && $x < $letzter) {
                    $laengen[$x++] = 0;
                }
            } elseif ($z === 18) {
                $y = $this->lesen(5) + 20;
                while ($y-- > 0 && $x < $letzter) {
                    $laengen[$x++] = 0;
                }
            } elseif ($z === 19) {
                $y = $this->lesen(1) + 4;
                $z = $this->symbol($vorbaum);
                $z = $laengen[$x] - $z;
                if ($z < 0) {
                    $z += 17;
                }
                while ($y-- > 0 && $x < $letzter) {
                    $laengen[$x++] = $z;
                }
            } else {
                $z = $laengen[$x] - $z;
                if ($z < 0) {
                    $z += 17;
                }
                $laengen[$x++] = $z;
            }
        }
    }

    /** Intel-E8-Rückübersetzung (absolute → relative Sprungadressen) für einen Rahmen. */
    private function e8(string $daten, int $position, int $dateiGroesse): string
    {
        $ende = strlen($daten) - 10;
        for ($i = 0; $i < $ende; $i++) {
            if ($daten[$i] !== "\xE8") {
                continue;
            }
            $absolut = unpack('l', substr($daten, $i + 1, 4))[1];
            $aktuell = $position + $i;
            if ($absolut >= -$aktuell && $absolut < $dateiGroesse) {
                $relativ = $absolut >= 0 ? $absolut - $aktuell : $absolut + $dateiGroesse;
                $daten = substr_replace($daten, pack('V', $relativ & 0xFFFFFFFF), $i + 1, 4);
            }
            $i += 4;
        }

        return $daten;
    }
}
