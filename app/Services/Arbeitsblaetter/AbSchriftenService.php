<?php

namespace App\Services\Arbeitsblaetter;

use App\Support\CabArchiv;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Microsoft-Schriften (Arial, Verdana) für die Angepassten Arbeitsblätter bereitstellen.
 *
 * Die Schriften dürfen nicht im Repository liegen. Sie kommen
 * - aus einem Ordner mit lizenzierten Dateien (z. B. C:\Windows\Fonts, {@see ausOrdner()}),
 * - aus den Schriftordnern des Servers (z. B. Paket ttf-mscorefonts-installer, {@see ausSystem()}) oder
 * - aus Microsofts „Core Fonts for the Web“ ({@see laden()}): die unveränderten Originalpakete
 *   arial32.exe/verdan32.exe (Prüfsumme festgeschrieben) werden geladen und in PHP entpackt
 *   ({@see CabArchiv}). Dafür müssen die Lizenzbedingungen von Microsoft akzeptiert sein
 *   (Einstellung `ab_ms_schriften_laden` bzw. Option `--lizenz-akzeptiert`).
 *
 * Ziel ist `config('arbeitsblaetter.schriften_pfad')` mit arial.ttf, arialbd.ttf, verdana.ttf, verdanab.ttf.
 */
class AbSchriftenService
{
    public const LIZENZ_URL = 'https://corefonts.sourceforge.net/eula.htm';

    /** Originalpakete mit festgeschriebener SHA-256-Prüfsumme und den benötigten Dateien (Paketname → Zielname). */
    public const PAKETE = [
        'arial32.exe' => [
            'sha256' => '85297a4d146e9c87ac6f74822734bdee5f4b2a722d7eaa584b7f2cbf76f478f6',
            'dateien' => ['arial.ttf' => 'arial.ttf', 'arialbd.ttf' => 'arialbd.ttf'],
        ],
        'verdan32.exe' => [
            'sha256' => 'c1cb61255e363166794e47664e2f21af8e3a26cb6346eb8d2ae2fa85dd5aad96',
            'dateien' => ['verdana.ttf' => 'verdana.ttf', 'verdanab.ttf' => 'verdanab.ttf'],
        ],
    ];

    public const QUELLE_URL = 'https://downloads.sourceforge.net/corefonts/';

    /** Übliche Schriftordner auf Servern. */
    public const SYSTEM_ORDNER = [
        '/usr/share/fonts/truetype/msttcorefonts',
        '/usr/share/fonts/msttcore',
        '/usr/share/fonts/truetype/msttcore',
        '/usr/share/fonts/corefonts',
        '/usr/share/fonts/TTF',
        '/usr/local/share/fonts',
        'C:/Windows/Fonts',
    ];

    public function zielOrdner(): string
    {
        return rtrim((string) config('arbeitsblaetter.schriften_pfad'), '/');
    }

    /** Benötigte Zieldateien. */
    public function benoetigt(): array
    {
        return array_merge(...array_map(fn ($p) => array_values($p['dateien']), array_values(self::PAKETE)));
    }

    /** @return array<string, bool> Datei → vorhanden */
    public function stand(): array
    {
        $stand = [];
        foreach (AbSatzService::eigeneSchriften() as $schrift) {
            foreach ([0, 1] as $schnitt) {
                $stand[AbSatzService::SCHRIFT_DATEIEN[$schrift][$schnitt]] = AbSatzService::originalPfad($schrift, $schnitt) !== null;
            }
        }

        return $stand;
    }

    public function vollstaendig(): bool
    {
        return ! in_array(false, $this->stand(), true);
    }

    public static function ladenErlaubt(): bool
    {
        return (bool) settings('ab_ms_schriften_laden');
    }

    /**
     * Fehlende Dateien aus einem Ordner übernehmen (Groß-/Kleinschreibung egal).
     *
     * @return array<int, string> übernommene Dateien
     */
    public function ausOrdner(string $ordner, bool $ueberschreiben = false): array
    {
        if (! is_dir($ordner)) {
            throw new RuntimeException('Ordner nicht gefunden: '.$ordner);
        }
        $vorhanden = [];
        foreach (scandir($ordner) ?: [] as $name) {
            $vorhanden[strtolower($name)] = $ordner.'/'.$name;
        }

        $kopiert = [];
        foreach ($this->benoetigt() as $datei) {
            $ziel = $this->zielOrdner().'/'.$datei;
            if (! isset($vorhanden[$datei]) || (! $ueberschreiben && is_file($ziel))) {
                continue;
            }
            $inhalt = (string) file_get_contents($vorhanden[$datei]);
            if (! $this->istTrueType($inhalt)) {
                continue;
            }
            $this->schreiben($datei, $inhalt);
            $kopiert[] = $datei;
        }

        return $kopiert;
    }

    /** Fehlende Dateien aus den Schriftordnern des Servers übernehmen. */
    public function ausSystem(): array
    {
        $kopiert = [];
        foreach (self::SYSTEM_ORDNER as $ordner) {
            if (is_dir($ordner) && is_readable($ordner)) {
                $kopiert = array_merge($kopiert, $this->ausOrdner($ordner));
            }
        }

        return array_values(array_unique($kopiert));
    }

    /**
     * Originalpakete von Microsoft laden, Prüfsumme prüfen, in PHP entpacken und ablegen.
     *
     * @return array<int, string> abgelegte Dateien
     */
    public function laden(bool $ueberschreiben = false): array
    {
        $abgelegt = [];
        foreach (self::PAKETE as $paket => $info) {
            $fehlen = array_filter($info['dateien'], fn ($ziel) => $ueberschreiben || ! is_file($this->zielOrdner().'/'.$ziel));
            if (! $fehlen) {
                continue;
            }

            $antwort = Http::timeout(120)->withOptions(['allow_redirects' => ['max' => 10]])->get(self::QUELLE_URL.$paket);
            if (! $antwort->successful()) {
                throw new RuntimeException($paket.' konnte nicht geladen werden (HTTP '.$antwort->status().').');
            }
            $inhalt = $antwort->body();
            if (! hash_equals($info['sha256'], hash('sha256', $inhalt))) {
                throw new RuntimeException($paket.': Prüfsumme stimmt nicht – Download abgebrochen.');
            }

            $dateien = array_change_key_case(CabArchiv::dateien($inhalt), CASE_LOWER);
            foreach ($fehlen as $quelle => $ziel) {
                if (! isset($dateien[$quelle]) || ! $this->istTrueType($dateien[$quelle])) {
                    throw new RuntimeException($paket.': '.$quelle.' fehlt oder ist beschädigt.');
                }
                $this->schreiben($ziel, $dateien[$quelle]);
                $abgelegt[] = $ziel;
            }
        }

        if ($abgelegt) {
            $this->hinweisSchreiben();
            Log::info('Angepasste Arbeitsblätter: Microsoft-Schriften geladen', ['dateien' => $abgelegt]);
        }

        return $abgelegt;
    }

    /**
     * Alles, was ohne Rückfrage geht: System-Schriftordner, dann – nur wenn erlaubt – laden.
     *
     * @return array{kopiert: array, geladen: array, fehlt: array, hinweis: ?string}
     */
    public function automatisch(?bool $ladenErlaubt = null): array
    {
        $ergebnis = ['kopiert' => [], 'geladen' => [], 'fehlt' => [], 'hinweis' => null];
        if (! $this->vollstaendig()) {
            $ergebnis['kopiert'] = $this->ausSystem();
        }
        if (! $this->vollstaendig()) {
            if ($ladenErlaubt ?? self::ladenErlaubt()) {
                $ergebnis['geladen'] = $this->laden();
            } else {
                $ergebnis['hinweis'] = 'Laden ist nicht freigegeben (Einstellung „Microsoft-Schriften laden“ bzw. --lizenz-akzeptiert).';
            }
        }
        $ergebnis['fehlt'] = array_keys(array_filter($this->stand(), fn ($da) => ! $da));

        return $ergebnis;
    }

    /** TrueType/OpenType-Kennung prüfen. */
    public function istTrueType(string $inhalt): bool
    {
        return strlen($inhalt) > 1000 && in_array(substr($inhalt, 0, 4), ["\x00\x01\x00\x00", 'true', 'OTTO'], true);
    }

    private function schreiben(string $datei, string $inhalt): void
    {
        $ordner = $this->zielOrdner();
        if (! is_dir($ordner) && ! @mkdir($ordner, 0755, true) && ! is_dir($ordner)) {
            throw new RuntimeException('Ordner kann nicht angelegt werden: '.$ordner);
        }
        if (file_put_contents($ordner.'/'.$datei, $inhalt) === false) {
            throw new RuntimeException('Datei kann nicht geschrieben werden: '.$ordner.'/'.$datei);
        }
    }

    private function hinweisSchreiben(): void
    {
        @file_put_contents($this->zielOrdner().'/LIZENZ-HINWEIS.txt', implode("\n", [
            'Arial und Verdana stammen aus Microsofts „Core Fonts for the Web“ (arial32.exe, verdan32.exe,',
            'unverändert von '.self::QUELLE_URL.' geladen und mit SHA-256 geprüft).',
            'Es gelten die Lizenzbedingungen von Microsoft: '.self::LIZENZ_URL,
            'Die Dateien dürfen nicht weitergegeben oder ins Repository eingecheckt werden.',
            'Geladen am '.now()->format('d.m.Y H:i').'.',
            '',
        ]));
    }
}
