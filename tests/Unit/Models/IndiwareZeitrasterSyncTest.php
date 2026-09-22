<?php

namespace Tests\Unit\Models;

use App\Models\LessonTime;
use App\Models\Zeitraster;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Testet die Logik des Stundenoffsets und der Zeitraster-Synchronisierung,
 * die beim Indiware-XML-Import verwendet wird.
 */
class IndiwareZeitrasterSyncTest extends TestCase
{
    /**
     * @test
     * GS-Plan: Zeitraster 1-6, Plan 2-7 → Offset 1
     * Period in LessonTime muss Plan-Nummerierung verwenden (2-7).
     */
    public function offset_berechnung_grundschule()
    {
        // GS-Zeitraster simulieren (wie aus XML)
        $zeiten = [
            ['stunde' => 1, 'woche' => 1, 'zeit' => '08:00'],
            ['stunde' => 2, 'woche' => 1, 'zeit' => '08:45'],
            ['stunde' => 3, 'woche' => 1, 'zeit' => '10:30'],
            ['stunde' => 4, 'woche' => 1, 'zeit' => '11:15'],
            ['stunde' => 5, 'woche' => 1, 'zeit' => '12:30'],
            ['stunde' => 6, 'woche' => 1, 'zeit' => '13:15'],
        ];

        $planStunden = [2, 3, 4, 5, 6, 7];

        $zeitrasterStunden = array_unique(array_column($zeiten, 'stunde'));
        $minZeitraster = (int) min($zeitrasterStunden);
        $minPlan = (int) min($planStunden);
        $stundenOffset = $minPlan - $minZeitraster;

        $this->assertEquals(1, $stundenOffset);

        // Prüfe Mapping: jede Plan-Stunde findet das korrekte Zeitraster
        foreach ($planStunden as $plStunde) {
            $mappedStunde = $plStunde - $stundenOffset;
            $found = collect($zeiten)->firstWhere('stunde', $mappedStunde);
            $this->assertNotNull($found, "Plan-Stunde {$plStunde} → Zeitraster-Stunde {$mappedStunde} nicht gefunden");
        }

        // Plan-Stunde 2 → Zeitraster 1 → 08:00
        $this->assertEquals('08:00', collect($zeiten)->firstWhere('stunde', 2 - $stundenOffset)['zeit']);
        // Plan-Stunde 7 → Zeitraster 6 → 13:15
        $this->assertEquals('13:15', collect($zeiten)->firstWhere('stunde', 7 - $stundenOffset)['zeit']);
    }

    /**
     * @test
     * OS-Plan: Zeitraster 0-9, Plan 1-9 → Offset 1
     */
    public function offset_berechnung_oberschule()
    {
        $zeiten = [
            ['stunde' => 0, 'woche' => 1, 'zeit' => '06:55'],
            ['stunde' => 1, 'woche' => 1, 'zeit' => '07:45'],
            ['stunde' => 2, 'woche' => 1, 'zeit' => '08:30'],
            ['stunde' => 3, 'woche' => 1, 'zeit' => '09:40'],
            ['stunde' => 4, 'woche' => 1, 'zeit' => '10:25'],
            ['stunde' => 5, 'woche' => 1, 'zeit' => '11:20'],
            ['stunde' => 6, 'woche' => 1, 'zeit' => '13:00'],
            ['stunde' => 7, 'woche' => 1, 'zeit' => '13:45'],
            ['stunde' => 8, 'woche' => 1, 'zeit' => '14:40'],
            ['stunde' => 9, 'woche' => 1, 'zeit' => '15:30'],
        ];

        $planStunden = [1, 2, 3, 4, 5, 6, 7, 8, 9];

        $zeitrasterStunden = array_unique(array_column($zeiten, 'stunde'));
        $minZeitraster = (int) min($zeitrasterStunden);
        $minPlan = (int) min($planStunden);
        $stundenOffset = $minPlan - $minZeitraster;

        $this->assertEquals(1, $stundenOffset);

        // Plan-Stunde 1 → Zeitraster 0 → 06:55
        $this->assertEquals('06:55', collect($zeiten)->firstWhere('stunde', 1 - $stundenOffset)['zeit']);
        // Plan-Stunde 9 → Zeitraster 8 → 14:40
        $this->assertEquals('14:40', collect($zeiten)->firstWhere('stunde', 9 - $stundenOffset)['zeit']);
    }

    /**
     * @test
     * Kein Offset wenn Plan und Zeitraster gleich starten.
     */
    public function kein_offset_wenn_nummerierung_identisch()
    {
        $zeitrasterStunden = [1, 2, 3, 4, 5];
        $planStunden = [1, 2, 3, 4, 5];

        $minZeitraster = min($zeitrasterStunden);
        $minPlan = min($planStunden);
        $stundenOffset = $minPlan - $minZeitraster;

        $this->assertEquals(0, $stundenOffset);
    }

    /**
     * @test
     * Zeitraster-Sync erzeugt LessonTime-Einträge mit Plan-Nummerierung.
     */
    public function sync_erzeugt_lesson_times_mit_plan_nummerierung()
    {
        $zeitraster = Zeitraster::create([
            'name'         => 'GS Test',
            'ist_standard' => false,
        ]);

        // GS-Daten simulieren: Offset = 1, Dauerstunde = 45
        $zeiten = [
            ['stunde' => 1, 'woche' => '1', 'zeit' => '08:00'],
            ['stunde' => 2, 'woche' => '1', 'zeit' => '08:45'],
            ['stunde' => 3, 'woche' => '1', 'zeit' => '10:30'],
            ['stunde' => 1, 'woche' => '2', 'zeit' => '08:00'],
            ['stunde' => 2, 'woche' => '2', 'zeit' => '08:45'],
            ['stunde' => 3, 'woche' => '2', 'zeit' => '10:30'],
        ];
        $stundenOffset = 1;
        $dauerstunde = 45;

        // Sync-Logik (wie im Controller)
        $importedPeriods = [];
        foreach ($zeiten as $zeit) {
            $zrStunde   = (int) $zeit['stunde'];
            $planPeriod = $zrStunde + $stundenOffset;
            $wocheLetter = count(array_unique(array_column($zeiten, 'woche'))) > 1
                ? ($zeit['woche'] == 1 ? 'A' : 'B')
                : null;

            $dedupKey = $planPeriod . '_' . ($wocheLetter ?? '');
            if (isset($importedPeriods[$dedupKey])) {
                continue;
            }

            $startTime = Carbon::parse($zeit['zeit']);
            $endTime   = $startTime->copy()->addMinutes($dauerstunde);

            LessonTime::create([
                'zeitraster_id' => $zeitraster->id,
                'period'        => $planPeriod,
                'start'         => $startTime->format('H:i'),
                'end'           => $endTime->format('H:i'),
                'week'          => $wocheLetter,
            ]);

            $importedPeriods[$dedupKey] = true;
        }

        // Identische A/B-Zeiten zusammenfassen
        $lessonTimes = $zeitraster->lessonTimes()->get();
        $byPeriod = $lessonTimes->groupBy('period');
        foreach ($byPeriod as $period => $entries) {
            if ($entries->count() === 2) {
                $a = $entries->firstWhere('week', 'A');
                $b = $entries->firstWhere('week', 'B');
                if ($a && $b && $a->start === $b->start && $a->end === $b->end) {
                    $b->delete();
                    $a->update(['week' => null]);
                }
            }
        }

        // Prüfungen: 3 Einträge (A+B waren identisch → zusammengefasst)
        $lessonTimes = $zeitraster->lessonTimes()->orderBy('period')->get();
        $this->assertCount(3, $lessonTimes);

        // Period nutzt Plan-Nummerierung (offset = 1)
        $this->assertEquals(2, $lessonTimes[0]->period);
        $this->assertEquals(3, $lessonTimes[1]->period);
        $this->assertEquals(4, $lessonTimes[2]->period);

        // Zeiten korrekt
        $this->assertEquals('08:00', substr($lessonTimes[0]->start, 0, 5));
        $this->assertEquals('08:45', substr($lessonTimes[0]->end, 0, 5));
        $this->assertEquals('08:45', substr($lessonTimes[1]->start, 0, 5));

        // Alle wochenunabhängig (weil A=B zusammengeführt)
        $this->assertNull($lessonTimes[0]->week);
        $this->assertNull($lessonTimes[1]->week);
    }

    /**
     * @test
     * VP-API Ak_StundeVon kann nach Sync korrekt aufgelöst werden.
     */
    public function vp_api_stundennummer_matcht_synced_lesson_time()
    {
        $zeitraster = Zeitraster::create([
            'name'         => 'GS VP-Test',
            'ist_standard' => true,
        ]);

        // GS: Plan-Stunde 2 = 08:00 (VP sendet Ak_StundeVon = 2)
        LessonTime::create([
            'zeitraster_id' => $zeitraster->id,
            'period'        => 2,
            'start'         => '08:00',
            'end'           => '08:45',
            'week'          => null,
        ]);
        LessonTime::create([
            'zeitraster_id' => $zeitraster->id,
            'period'        => 3,
            'start'         => '08:45',
            'end'           => '09:30',
            'week'          => null,
        ]);

        // Simuliere VP-Lookup: Ak_StundeVon = 2
        $resolved = LessonTime::resolveTime(2, null, $zeitraster->id);
        $this->assertNotNull($resolved, 'LessonTime für period=2 muss gefunden werden');
        $this->assertEquals('08:00', substr($resolved['start'], 0, 5));
        $this->assertEquals('08:45', substr($resolved['end'], 0, 5));

        // Ak_StundeVon = 3 → 08:45
        $resolved3 = LessonTime::resolveTime(3, null, $zeitraster->id);
        $this->assertNotNull($resolved3);
        $this->assertEquals('08:45', substr($resolved3['start'], 0, 5));
    }

    /**
     * @test
     * OS: Ak_StundeVon = 1 → period 1 → 06:55 (nach Sync mit Offset)
     */
    public function vp_api_oberschule_stunde_1_korrekt()
    {
        $zeitraster = Zeitraster::create([
            'name'         => 'OS VP-Test',
            'ist_standard' => false,
        ]);

        // OS: ZR stunde 0 = 06:55, Plan stunde 1, Offset = 1
        // → LessonTime period 1 = 06:55
        LessonTime::create([
            'zeitraster_id' => $zeitraster->id,
            'period'        => 1,
            'start'         => '06:55',
            'end'           => '07:40',
            'week'          => null,
        ]);

        $resolved = LessonTime::resolveTime(1, null, $zeitraster->id);
        $this->assertNotNull($resolved);
        $this->assertEquals('06:55', substr($resolved['start'], 0, 5));
    }
}

