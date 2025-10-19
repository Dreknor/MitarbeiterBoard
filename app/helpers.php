<?php

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

enum units
{
    case seconds;
    case minutes;
    case hours;
    case mixed;
}

function random_color_part()
{
    return str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
}

function random_color()
{
    return random_color_part() . random_color_part();
}

/**
 * @param string|null $type
 * @param string|null $meldung
 * @param string|null $anchor
 * @return RedirectResponse
 */
function redirectBack(string $type = null, string $meldung = null, string $anchor = null): RedirectResponse
{
    return redirect()->to(url()->previous().$anchor)->with([
        'type' => $type,
        'Meldung' => $meldung
    ]);
}

function money($money = null, $symbol = true)
{
    if ($money != null) {
        if ($symbol == true) {
            return number_format($money, 2) . " €";
        } else {
            return number_format($money, 2);
        }
    }
}


/**
 * @param $start_date
 * @parm $end_date
 * @return int
 *
 * @throws Exception
 *
 * Berechnet die Anzahl der Arbeitstage zwischen zwei Daten (inklusive Start- und Enddatum). Feiertage werden berücksichtigt.
 *
 */
function workdays($start_date, $end_date): int
{
    $start_date = Carbon::parse($start_date);
    $end_date = Carbon::parse($end_date);

    $workdays = 0;
    $current_date = $start_date->copy();

    while ($current_date->lte($end_date)) {
        if ($current_date->isWeekday() && !is_holiday($current_date)) {
            $workdays++;
        }

        $current_date->addDay();
    }

    return $workdays;
}

// In app/Helpers/YourHelper.php
function getHolidayCellData($holiday, $day)
{
    $isWeekendOrHoliday = is_holiday($day) || $day->isWeekend();
    $data = [
        'class' => '',
        'icon' => '',
    ];
    if ($isWeekendOrHoliday) {
        $data['class'] = 'bg-info';
    } elseif (is_ferien($day)) {
        $data['class'] = 'bg-gradient-x-light-blue';
    }
    if (!is_null($holiday)) {
        if ($holiday->approved && !$isWeekendOrHoliday) {
            $data['class'] = 'bg-gradient-directional-success';
            $data['icon'] = '<i class="fa fa-check"></i>';
        } elseif (!$holiday->approved && !$holiday->rejected && !$isWeekendOrHoliday) {
            $data['class'] = 'bg-gradient-directional-amber';
            $data['icon'] = '<i class="fa fa-question"></i>';
        } elseif ($holiday->rejected && !$isWeekendOrHoliday) {
            $data['class'] = 'bg-gradient-directional-danger';
            $data['icon'] = '<i class="fas fa-times"></i>';
        }
    }



    return $data;
}

/**
 * @param Carbon $date
 * @return string
 */
function is_holiday(Carbon $date)
{
    try {
        // Feiertage für das Jahr zwischenspeichern und abrufen
        $holidays = Cache::remember(
            'holidays_' . $date->year,
            now()->addDays(31), // Cache für 31 Tage speichern
            fn() => fetch_holidays_by_year($date->year) // Hilfsfunktion für API-Aufruf
        );

        // Datum auf Feiertag prüfen
        return $holidays->first(function ($item) use ($date) {
            return $item['date'] == $date->format('Y-m-d');
        });

    } catch (Throwable $e) { // Throwable deckt Fehler wie Exception & Error ab
        Log::error('Feiertags-Helfer: Fehler beim Überprüfen von Feiertagen: ', [
            'date' => $date->toDateString(),
            'year' => $date->year,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Ruft Feiertage für ein bestimmtes Jahr von der API ab.
 *
 * @param int $year
 * @return Collection
 */
function fetch_holidays_by_year(int $year): Collection
{
    $apiUrl = "https://ipty.de/feiertag/api.php?do=getFeiertage&jahr={$year}&outformat=Y-m-d&loc=SN";

    try {
        $response = Http::timeout(5)->get($apiUrl);

        // Verarbeiten der API-Antwort (zur Sicherheit immer JSON prüfen)
        if ($response->successful()) {
            return collect($response->json());
        }

        Log::warning("Feiertage konnten nicht von der API geladen werden für Jahr $year", [
            'url' => $apiUrl,
            'status' => $response->status()
        ]);
    } catch (Throwable $e) {
        Log::error('Feiertags-API: Fehler beim Abrufen der Feiertage von der API: ', [
            'url' => $apiUrl,
            'year' => $year,
            'error' => $e->getMessage(),
        ]);
    }

    // Bei Fehlern: Leere Sammlung zurückgeben
    return collect([]);
}

/**
 * Ruft Ferien für ein bestimmtes Jahr von der API ab.
 *
 * @param int $year
 * @param string|null $state
 * @return Collection
 */
function fetch_ferien_by_year(int $year, ?string $state = null): Collection
{
    if (is_null($state)) {
        $state = settings('ferien_state', 'holidays');
    }

    try {
        $ferien = Cache::remember('ferien_'.$state.'_'.$year, 60*60*24*30, function () use ($year, $state) {
            $response = Http::timeout(5)->get("https://ferien-api.de/api/v1/holidays/".$state."/".$year);
            if ($response->successful()) {
                return collect($response->json());
            }
            return collect([]);
        });

        return $ferien;
    } catch (Throwable $e) {
        Log::error('Ferien-API: Fehler beim Abrufen der Ferien von der API: ', [
            'year' => $year,
            'state' => $state,
            'error' => $e->getMessage(),
        ]);
    }

    return collect([]);
}

/**
 * Prüft ob ein Datum in den Ferien liegt und gibt Ferien-Details zurück.
 *
 * @param Carbon $date
 * @param string|null $state
 * @param int|null $year
 * @return object|null
 */
function is_ferien(Carbon $date, $state = null, $year = null)
{
    if (is_null($year)){
        $year = $date->format('Y');
    }

    if (is_null($state)){
        $state = settings('ferien_state', 'holidays');
    }

    try {
        $ferien = fetch_ferien_by_year($year, $state);

        return $ferien->first(function ($item) use ($date) {
            // Die API gibt Arrays zurück, nicht Objekte
            $start = is_array($item) ? Carbon::createFromFormat('Y-m-d', $item['start']) : Carbon::createFromFormat('Y-m-d', $item->start);
            $end = is_array($item) ? Carbon::createFromFormat('Y-m-d', $item['end']) : Carbon::createFromFormat('Y-m-d', $item->end);
            return $date->between($start->startOfDay(), $end->endOfDay());
        });
    } catch (Throwable $e) {
        Log::error('Ferien-Helfer: Fehler beim Überprüfen von Ferien: ', [
            'date' => $date->toDateString(),
            'year' => $year,
            'state' => $state,
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}

function calculateWorkingTime(Collection $working_times, Collection $roster_events = null)
{
    if (!is_null($roster_events)) {
        $break = $roster_events->filter(function ($event) {
            return strtolower($event->event) == strtolower('Pause') ? $event : false;
        })->sum('duration');
    }

    $time = $working_times->sum('duration');
    $interval = CarbonInterval::minutes($time - $break)->cascade();


    return CarbonInterval::hours(($interval->d*24)+$interval->h)->minutes($interval->minutes)->seconds(0);

}

/**
 * leading zero
 * @param $number
 * @return string
 */
function format_number($number){
    if ($number == null){
        return "00";
    }
    return (strlen($number) < 2) ? "0{$number}" : $number;
}

/**
 * Converting decimal to time formatted HH:MM
 * @param $dec
 * @return string
 */

function convertTime($dec)
{
    $sign = false;
    if ($dec < 0){
        $sign = true;
        $dec = abs($dec);
    }

    // start by converting to seconds
    $seconds = $dec;
    $minutes = floor($dec / 60);

    //rest Sekunden
    $seconds -= $minutes * 60;
    $hours = floor($minutes / 60);
    $minutes -= $hours *60;

    $seconds = round($seconds);

    // return the time formatted HH:MM:SS
    if ($sign) {
        return '-'.lz($hours).":".lz($minutes).":".lz($seconds);
    }
    return lz($hours).":".lz($minutes).":".lz($seconds);
}

// lz = leading zero
function lz($num)
{
    return (strlen($num) < 2) ? "0{$num}" : $num;
}
function percent_to_seconds($percent, $full_hours = 40){
    $hours = $percent * $full_hours/100;
    $minutes = $hours*60;
    $seconds = $minutes*60;

    return ($seconds);
}

/**
 * @param $key
 * @return Repository|Application|\Illuminate\Foundation\Application|mixed
 */
function settings($key, $config_file = null)
{

   $settings = Cache::remember('setting_'.$key, 60, function() use ($key) {
        return Setting::where('setting', $key)->first()?->value;
    });

    if (is_null($settings)){

        if (!is_null($config_file)){
            return config($config_file.'.'.$key);
        }

        return config('config.'.$key);
    }

    return $settings;
}
