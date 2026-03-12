<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Http;

trait MocksExternalApis
{
    /**
     * Faked Feiertags-API (ipty.de)
     */
    protected function mockHolidayApi(): void
    {
        Http::fake([
            'ipty.de/feiertag/*' => Http::response([
                ['date' => '2026-01-01', 'title' => 'Neujahr'],
                ['date' => '2026-04-03', 'title' => 'Karfreitag'],
                ['date' => '2026-04-06', 'title' => 'Ostermontag'],
                ['date' => '2026-05-01', 'title' => 'Tag der Arbeit'],
                ['date' => '2026-05-14', 'title' => 'Christi Himmelfahrt'],
                ['date' => '2026-05-25', 'title' => 'Pfingstmontag'],
                ['date' => '2026-10-03', 'title' => 'Tag der deutschen Einheit'],
                ['date' => '2026-10-31', 'title' => 'Reformationstag'],
                ['date' => '2026-11-18', 'title' => 'Buß- und Bettag'],
                ['date' => '2026-12-25', 'title' => '1. Weihnachtsfeiertag'],
                ['date' => '2026-12-26', 'title' => '2. Weihnachtsfeiertag'],
            ], 200),
        ]);
    }

    /**
     * Faked Ferien-API (ferien-api.de)
     * Datumsformat: Y-m-d (da der Helper createFromFormat('Y-m-d', ...) nutzt)
     */
    protected function mockFerienApi(): void
    {
        Http::fake([
            'ferien-api.de/*' => Http::response([
                [
                    'name'  => 'Winterferien',
                    'start' => '2026-02-09',
                    'end'   => '2026-02-21',
                ],
                [
                    'name'  => 'Osterferien',
                    'start' => '2026-04-02',
                    'end'   => '2026-04-18',
                ],
                [
                    'name'  => 'Sommerferien',
                    'start' => '2026-06-27',
                    'end'   => '2026-08-08',
                ],
                [
                    'name'  => 'Herbstferien',
                    'start' => '2026-10-12',
                    'end'   => '2026-10-24',
                ],
                [
                    'name'  => 'Weihnachtsferien',
                    'start' => '2026-12-21',
                    'end'   => '2027-01-03',
                ],
            ], 200),
        ]);
    }

    /**
     * Alle externen APIs auf einmal mocken.
     */
    protected function fakeAllExternalApis(): void
    {
        $this->mockHolidayApi();
        $this->mockFerienApi();
    }
}
