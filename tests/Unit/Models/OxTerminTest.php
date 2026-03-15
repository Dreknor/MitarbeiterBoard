<?php

namespace Tests\Unit\Models;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\OxTerminTeilnehmer;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class OxTerminTest extends TestCase
{
    /** @test */
    public function ox_termin_kann_erstellt_werden(): void
    {
        $termin = OxTermin::factory()->create();

        $this->assertInstanceOf(OxTermin::class, $termin);
        $this->assertFalse($termin->ganztaegig);
    }

    /** @test */
    public function ox_termin_hat_kalender_relation(): void
    {
        $termin = OxTermin::factory()->create();

        $this->assertInstanceOf(OxCalendar::class, $termin->kalender);
    }

    /** @test */
    public function ox_termin_hat_ersteller_relation(): void
    {
        $user   = User::factory()->create();
        $termin = OxTermin::factory()->create(['erstellt_von' => $user->id]);

        $this->assertInstanceOf(User::class, $termin->ersteller);
        $this->assertSame($user->id, $termin->ersteller->id);
    }

    /** @test */
    public function ox_termin_hat_teilnehmer_relation(): void
    {
        $termin = OxTermin::factory()->create();
        OxTerminTeilnehmer::create([
            'ox_termin_id' => $termin->id,
            'email'        => 'test@schule.de',
            'name'         => 'Max Mustermann',
            'status'       => 'ACCEPTED',
        ]);

        $this->assertCount(1, $termin->teilnehmer);
    }

    /** @test */
    public function ox_termin_castet_exdates_als_array(): void
    {
        $termin = OxTermin::factory()->create([
            'exdates' => ['2026-03-15T00:00:00Z', '2026-03-22T00:00:00Z'],
        ]);

        $this->assertIsArray($termin->exdates);
        $this->assertCount(2, $termin->exdates);
    }

    /** @test */
    public function ox_termin_castet_beginn_und_ende_als_carbon(): void
    {
        $termin = OxTermin::factory()->create();

        $this->assertInstanceOf(Carbon::class, $termin->beginn);
        $this->assertInstanceOf(Carbon::class, $termin->ende);
    }

    /** @test */
    public function ox_termin_wird_kaskadiert_geloescht_wenn_kalender_geloescht_wird(): void
    {
        $calendar = OxCalendar::factory()->create();
        $termin   = OxTermin::factory()->create(['ox_calendar_id' => $calendar->id]);
        $calendar->forceDelete();

        $this->assertNull(OxTermin::withTrashed()->find($termin->id));
    }
}

