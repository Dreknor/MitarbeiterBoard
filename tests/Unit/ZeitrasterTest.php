<?php

namespace Tests\Unit;

use App\Models\Klasse;
use App\Models\LessonTime;
use App\Models\Zeitraster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ZeitrasterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── Zeitraster-Model ────────────────────────────────────────────────────

    /** @test */
    public function test_zeitraster_can_be_created(): void
    {
        $z = Zeitraster::create(['name' => 'Grundschule', 'ist_standard' => false]);
        $this->assertDatabaseHas('zeitraster', ['name' => 'Grundschule']);
    }

    /** @test */
    public function test_zeitraster_has_lesson_times_relation(): void
    {
        $z = Zeitraster::create(['name' => 'GS', 'ist_standard' => false]);
        LessonTime::create([
            'period'        => 1,
            'start'         => '07:30',
            'end'           => '08:15',
            'week'          => null,
            'zeitraster_id' => $z->id,
        ]);
        $this->assertCount(1, $z->lessonTimes);
    }

    /** @test */
    public function test_zeitraster_has_klassen_relation(): void
    {
        $z      = Zeitraster::create(['name' => 'OS', 'ist_standard' => false]);
        $klasse = Klasse::factory()->create(['zeitraster_id' => $z->id]);
        $this->assertCount(1, $z->klassen);
        $this->assertEquals($klasse->id, $z->klassen->first()->id);
    }

    // ─── getStandard() ───────────────────────────────────────────────────────

    /** @test */
    public function test_get_standard_returns_correct_zeitraster(): void
    {
        // Standard-Eintrag aus Migration zurücksetzen, um kontrollierten Zustand zu haben
        Zeitraster::query()->update(['ist_standard' => false]);
        Cache::forget('zeitraster_standard');

        Zeitraster::create(['name' => 'NichtStandard', 'ist_standard' => false]);
        $std = Zeitraster::create(['name' => 'Teststandard', 'ist_standard' => true]);

        $this->assertEquals($std->id, Zeitraster::getStandard()->id);
    }

    /** @test */
    public function test_get_standard_returns_null_when_none_set(): void
    {
        // Alle Zeitraster auf ist_standard=false setzen (inkl. Migration-Seed)
        Zeitraster::query()->update(['ist_standard' => false]);
        Cache::forget('zeitraster_standard');

        Zeitraster::create(['name' => 'OhneStandard', 'ist_standard' => false]);

        $this->assertNull(Zeitraster::getStandard());
    }

    /** @test */
    public function test_get_standard_uses_cache(): void
    {
        $std = Zeitraster::where('ist_standard', true)->first(); // aus Migration
        Cache::forget('zeitraster_standard');

        // Ersten Aufruf: DB-Query, cacht das Ergebnis
        $result1 = Zeitraster::getStandard();
        // Zweiten Aufruf: aus Cache
        $result2 = Zeitraster::getStandard();

        $this->assertNotNull($result1);
        $this->assertEquals($result1->id, $result2->id);
        $this->assertTrue(Cache::has('zeitraster_standard'));
    }

    // ─── markAlsStandard() ───────────────────────────────────────────────────

    /** @test */
    public function test_mark_als_standard_resets_others(): void
    {
        $a = Zeitraster::create(['name' => 'A', 'ist_standard' => true]);
        $b = Zeitraster::create(['name' => 'B', 'ist_standard' => false]);

        $b->markAlsStandard();

        $this->assertFalse($a->fresh()->ist_standard);
        $this->assertTrue($b->fresh()->ist_standard);
    }

    /** @test */
    public function test_mark_als_standard_invalidates_cache(): void
    {
        $a = Zeitraster::create(['name' => 'A', 'ist_standard' => true]);
        $b = Zeitraster::create(['name' => 'B', 'ist_standard' => false]);

        // Cache befüllen
        Zeitraster::getStandard();
        $this->assertTrue(Cache::has('zeitraster_standard'));

        $b->markAlsStandard();

        // Cache muss nach markAlsStandard() gelöscht sein
        $this->assertFalse(Cache::has('zeitraster_standard'));
    }

    /** @test */
    public function test_mark_als_standard_resets_migration_seed_as_well(): void
    {
        // Auch der Seed-Eintrag aus der Migration soll zurückgesetzt werden
        $seeded = Zeitraster::where('ist_standard', true)->first();
        $neu    = Zeitraster::create(['name' => 'Neu', 'ist_standard' => false]);

        $neu->markAlsStandard();

        $this->assertFalse($seeded->fresh()->ist_standard);
        $this->assertTrue($neu->fresh()->ist_standard);
    }

    // ─── LessonTime::resolveTime() mit zeitrasterId ──────────────────────────

    /** @test */
    public function test_lesson_time_resolves_zeitraster_specific_time(): void
    {
        $gs = Zeitraster::create(['name' => 'GS', 'ist_standard' => false]);
        LessonTime::create(['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null, 'zeitraster_id' => null]);
        LessonTime::create(['period' => 1, 'start' => '07:00', 'end' => '07:45', 'week' => null, 'zeitraster_id' => $gs->id]);

        $result = LessonTime::resolveTime(1, null, $gs->id);

        $this->assertEquals('07:00', $result['start']);
        $this->assertEquals('07:45', $result['end']);
    }

    /** @test */
    public function test_lesson_time_falls_back_to_global(): void
    {
        LessonTime::create(['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null, 'zeitraster_id' => null]);
        $gs = Zeitraster::create(['name' => 'GS', 'ist_standard' => false]);

        // Kein GS-spezifischer Eintrag → Fallback auf globalen
        $result = LessonTime::resolveTime(1, null, $gs->id);

        $this->assertEquals('07:30', $result['start']);
    }

    /** @test */
    public function test_lesson_time_returns_null_and_logs_warning_for_unknown_period(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(
            fn ($msg) => str_contains($msg, 'Stunde 99')
        );

        $result = LessonTime::resolveTime(99, null, null);

        $this->assertNull($result);
    }

    /** @test */
    public function test_lesson_time_backward_compat_no_zeitraster_id(): void
    {
        LessonTime::create(['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null, 'zeitraster_id' => null]);

        // Aufruf ohne zeitrasterId – altes Verhalten unverändert
        $result = LessonTime::resolveTime(1, null);

        $this->assertEquals('07:30', $result['start']);
    }

    /** @test */
    public function test_lesson_time_week_specificity_takes_priority(): void
    {
        LessonTime::create(['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null,  'zeitraster_id' => null]);
        LessonTime::create(['period' => 1, 'start' => '07:45', 'end' => '08:30', 'week' => 'A',   'zeitraster_id' => null]);

        // Wochenspezifischer Eintrag (A) hat Vorrang
        $result = LessonTime::resolveTime(1, 'A', null);

        $this->assertEquals('07:45', $result['start']);
    }

    // ─── Klasse::getEffectiveZeitrasterId() ──────────────────────────────────

    /** @test */
    public function test_klasse_get_effective_zeitraster_id_returns_own(): void
    {
        $gs     = Zeitraster::create(['name' => 'GS', 'ist_standard' => false]);
        $klasse = Klasse::factory()->create(['zeitraster_id' => $gs->id]);

        $this->assertEquals($gs->id, $klasse->getEffectiveZeitrasterId());
    }

    /** @test */
    public function test_klasse_get_effective_zeitraster_id_falls_back_to_standard(): void
    {
        // Standard-Zeitraster aus Migration verwenden
        Cache::forget('zeitraster_standard');
        $std    = Zeitraster::where('ist_standard', true)->first();
        $klasse = Klasse::factory()->create(['zeitraster_id' => null]);

        $this->assertEquals($std->id, $klasse->getEffectiveZeitrasterId());
    }

    /** @test */
    public function test_klasse_get_effective_zeitraster_id_returns_null_when_no_standard(): void
    {
        Zeitraster::query()->update(['ist_standard' => false]);
        Cache::forget('zeitraster_standard');
        $klasse = Klasse::factory()->create(['zeitraster_id' => null]);

        $this->assertNull($klasse->getEffectiveZeitrasterId());
    }

    /** @test */
    public function test_klasse_zeitraster_relation(): void
    {
        $z      = Zeitraster::create(['name' => 'OS', 'ist_standard' => false]);
        $klasse = Klasse::factory()->create(['zeitraster_id' => $z->id]);

        $this->assertNotNull($klasse->zeitraster);
        $this->assertEquals($z->id, $klasse->zeitraster->id);
    }

    /** @test */
    public function test_klasse_zeitraster_relation_null_when_not_assigned(): void
    {
        $klasse = Klasse::factory()->create(['zeitraster_id' => null]);

        $this->assertNull($klasse->zeitraster);
    }
}

