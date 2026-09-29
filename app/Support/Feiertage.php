<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Gesetzliche Feiertage – lokal berechnet (keine externe API nötig).
 *
 * Liefert dasselbe Format wie früher die ipty.de-API: [['date' => 'Y-m-d', 'title' => '…'], …]
 * Bundesland über Setting "ferien_state" (Standard: SN).
 */
class Feiertage
{
    public static function fuerJahr(int $jahr, ?string $land = null): Collection
    {
        $land = strtoupper($land ?: (string) (settings('ferien_state', 'holidays') ?: 'SN'));
        $ostern = self::ostersonntag($jahr);

        $tage = [
            [Carbon::create($jahr, 1, 1), 'Neujahr'],
            [$ostern->copy()->subDays(2), 'Karfreitag'],
            [$ostern->copy()->addDay(), 'Ostermontag'],
            [Carbon::create($jahr, 5, 1), 'Tag der Arbeit'],
            [$ostern->copy()->addDays(39), 'Christi Himmelfahrt'],
            [$ostern->copy()->addDays(50), 'Pfingstmontag'],
            [Carbon::create($jahr, 10, 3), 'Tag der Deutschen Einheit'],
            [Carbon::create($jahr, 12, 25), '1. Weihnachtstag'],
            [Carbon::create($jahr, 12, 26), '2. Weihnachtstag'],
        ];

        if (in_array($land, ['BW', 'BY', 'ST'], true)) {
            $tage[] = [Carbon::create($jahr, 1, 6), 'Heilige Drei Könige'];
        }
        if (($land === 'BE' && $jahr >= 2019) || ($land === 'MV' && $jahr >= 2023)) {
            $tage[] = [Carbon::create($jahr, 3, 8), 'Internationaler Frauentag'];
        }
        if (in_array($land, ['BW', 'BY', 'HE', 'NW', 'RP', 'SL'], true)) {
            $tage[] = [$ostern->copy()->addDays(60), 'Fronleichnam'];
        }
        if ($land === 'SL') {
            $tage[] = [Carbon::create($jahr, 8, 15), 'Mariä Himmelfahrt'];
        }
        if ($land === 'TH' && $jahr >= 2019) {
            $tage[] = [Carbon::create($jahr, 9, 20), 'Weltkindertag'];
        }
        $reformationstag = ['BB', 'MV', 'SN', 'ST', 'TH'];
        if ($jahr >= 2018) {
            $reformationstag = array_merge($reformationstag, ['HB', 'HH', 'NI', 'SH']);
        }
        if ($jahr === 2017 || in_array($land, $reformationstag, true)) {
            $tage[] = [Carbon::create($jahr, 10, 31), 'Reformationstag'];
        }
        if (in_array($land, ['BW', 'BY', 'NW', 'RP', 'SL'], true)) {
            $tage[] = [Carbon::create($jahr, 11, 1), 'Allerheiligen'];
        }
        if ($land === 'SN') {
            // Mittwoch vor dem 23. November
            $bussUndBettag = Carbon::create($jahr, 11, 22);
            while ($bussUndBettag->dayOfWeek !== Carbon::WEDNESDAY) {
                $bussUndBettag->subDay();
            }
            $tage[] = [$bussUndBettag, 'Buß- und Bettag'];
        }

        return collect($tage)
            ->map(fn (array $t) => ['date' => $t[0]->format('Y-m-d'), 'title' => $t[1]])
            ->sortBy('date')
            ->values();
    }

    /**
     * Ostersonntag nach der Gaußschen Osterformel (Anonymous Gregorian algorithm),
     * unabhängig von der PHP-Extension "calendar".
     */
    public static function ostersonntag(int $jahr): Carbon
    {
        $a = $jahr % 19;
        $b = intdiv($jahr, 100);
        $c = $jahr % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $monat = intdiv($h + $l - 7 * $m + 114, 31);
        $tag = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($jahr, $monat, $tag)->startOfDay();
    }
}
