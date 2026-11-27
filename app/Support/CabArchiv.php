<?php

namespace App\Support;

use RuntimeException;

/**
 * Minimaler Leser für Microsoft-CAB-Archive (auch eingebettet in selbstentpackende .exe),
 * unkomprimiert, MSZIP oder LZX ({@see LzxEntpacker}). Genutzt, um die „Core Fonts for the Web“ (Arial, Verdana) ohne
 * `cabextract` zu entpacken ({@see \App\Console\Commands\Arbeitsblaetter\Schriften}).
 *
 * MSZIP: jeder Datenblock beginnt mit „CK“ und enthält einen Deflate-Strom; das Fenster
 * (letzte 32 KB) wird über die Blöcke eines Ordners hinweg als Wörterbuch weitergegeben.
 */
class CabArchiv
{
    private const SIGNATUR = 'MSCF';

    private const FENSTER = 32768;

    /**
     * @return array<string, string> Dateiname → Inhalt
     *
     * @throws RuntimeException
     */
    public static function dateien(string $inhalt): array
    {
        $start = self::cabStart($inhalt);
        $cab = substr($inhalt, $start);

        $kopf = unpack('a4sig/Vr1/Vgroesse/Vr2/VdateienOffset/Vr3/Cmin/Cmaj/vordner/vdateien/vflags/vsetId/vnummer', $cab);
        if ($kopf['sig'] !== self::SIGNATUR) {
            throw new RuntimeException('Kein CAB-Archiv.');
        }
        $pos = 36;
        $reserveOrdner = 0;
        $reserveDaten = 0;
        if ($kopf['flags'] & 0x0004) {
            $r = unpack('vkopf/Cordner/Cdaten', substr($cab, $pos, 4));
            $pos += 4 + $r['kopf'];
            $reserveOrdner = $r['ordner'];
            $reserveDaten = $r['daten'];
        }
        if ($kopf['flags'] & 0x0003) {
            throw new RuntimeException('Mehrteilige CAB-Archive werden nicht unterstützt.');
        }

        $ordner = [];
        for ($i = 0; $i < $kopf['ordner']; $i++) {
            $ordner[] = unpack('Voffset/vbloecke/vtyp', substr($cab, $pos, 8));
            $pos += 8 + $reserveOrdner;
        }

        $eintraege = [];
        $pos = $kopf['dateienOffset'];
        for ($i = 0; $i < $kopf['dateien']; $i++) {
            $f = unpack('Vgroesse/Voffset/vordner/vdatum/vzeit/vattribute', substr($cab, $pos, 16));
            $pos += 16;
            $ende = strpos($cab, "\0", $pos);
            $f['name'] = str_replace('\\', '/', substr($cab, $pos, $ende - $pos));
            $pos = $ende + 1;
            $eintraege[] = $f;
        }

        $entpackt = [];
        foreach ($ordner as $nr => $o) {
            $entpackt[$nr] = self::ordnerEntpacken($cab, $o, $reserveDaten);
        }

        $ergebnis = [];
        foreach ($eintraege as $f) {
            if (! isset($entpackt[$f['ordner']])) {
                continue;
            }
            $daten = substr($entpackt[$f['ordner']], $f['offset'], $f['groesse']);
            if (strlen($daten) !== $f['groesse']) {
                throw new RuntimeException('Datei '.$f['name'].' ist unvollständig.');
            }
            $ergebnis[basename($f['name'])] = $daten;
        }

        return $ergebnis;
    }

    private static function cabStart(string $inhalt): int
    {
        $pos = 0;
        while (($pos = strpos($inhalt, self::SIGNATUR, $pos)) !== false) {
            // Plausibilität: reserved1 = 0 und Versionsnummer 1.3
            if (substr($inhalt, $pos + 4, 4) === "\0\0\0\0" && ord($inhalt[$pos + 24]) === 3 && ord($inhalt[$pos + 25]) === 1) {
                return $pos;
            }
            $pos++;
        }

        throw new RuntimeException('Im Paket wurde kein CAB-Archiv gefunden.');
    }

    private static function ordnerEntpacken(string $cab, array $ordner, int $reserveDaten): string
    {
        $typ = $ordner['typ'] & 0x000F;
        if (! in_array($typ, [0, 1, 3], true)) {
            throw new RuntimeException('Komprimierungsart '.$typ.' wird nicht unterstützt (nur unkomprimiert, MSZIP und LZX).');
        }

        if ($typ === 3) {
            // LZX: Datenblöcke bilden einen durchgehenden Bitstrom
            $pos = $ordner['offset'];
            $eingabe = '';
            $groesse = 0;
            for ($b = 0; $b < $ordner['bloecke']; $b++) {
                $block = unpack('Vpruefsumme/vlaenge/vroh', substr($cab, $pos, 8));
                $pos += 8 + $reserveDaten;
                $eingabe .= substr($cab, $pos, $block['laenge']);
                $pos += $block['laenge'];
                $groesse += $block['roh'];
            }

            return (new LzxEntpacker(($ordner['typ'] >> 8) & 0x1F))->entpacken($eingabe, $groesse);
        }

        $pos = $ordner['offset'];
        $ausgabe = '';
        for ($b = 0; $b < $ordner['bloecke']; $b++) {
            $block = unpack('Vpruefsumme/vlaenge/vroh', substr($cab, $pos, 8));
            $pos += 8 + $reserveDaten;
            $daten = substr($cab, $pos, $block['laenge']);
            $pos += $block['laenge'];

            if ($typ === 0) {
                $ausgabe .= $daten;
                continue;
            }
            if (substr($daten, 0, 2) !== 'CK') {
                throw new RuntimeException('Ungültiger MSZIP-Block.');
            }
            $optionen = ['window' => 15];
            if ($ausgabe !== '') {
                $optionen['dictionary'] = substr($ausgabe, -self::FENSTER);
            }
            $kontext = inflate_init(ZLIB_ENCODING_RAW, $optionen);
            $teil = $kontext ? inflate_add($kontext, substr($daten, 2), ZLIB_FINISH) : false;
            if ($teil === false || strlen($teil) !== $block['roh']) {
                throw new RuntimeException('MSZIP-Block konnte nicht entpackt werden.');
            }
            $ausgabe .= $teil;
        }

        return $ausgabe;
    }
}
