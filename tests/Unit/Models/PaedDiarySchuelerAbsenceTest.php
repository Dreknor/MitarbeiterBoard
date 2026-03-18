<?php

namespace Tests\Unit\Models;

use App\Models\Klasse;
use App\Models\PaedDiarySchuelerAbsence;
use App\Models\Schueler;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class PaedDiarySchuelerAbsenceTest extends TestCase
{
    /** @test */
    public function model_hat_korrekte_beziehungen(): void
    {
        $user     = User::factory()->create();
        $klasse   = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $absence = PaedDiarySchuelerAbsence::create([
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => '2026-03-17',
            'marked_by'   => $user->id,
        ]);

        $this->assertEquals($schueler->id, $absence->schueler->id);
        $this->assertEquals($klasse->id, $absence->klasse->id);
        $this->assertEquals($user->id, $absence->markedByUser->id);
        $this->assertInstanceOf(Carbon::class, $absence->datum);
    }

    /** @test */
    public function datum_wird_als_carbon_instanz_gecastet(): void
    {
        $absence = PaedDiarySchuelerAbsence::factory()->create(['datum' => '2026-03-17']);
        $this->assertInstanceOf(Carbon::class, $absence->datum);
        $this->assertEquals('2026-03-17', $absence->datum->toDateString());
    }

    /** @test */
    public function scopeForKlasseInRange_filtert_korrekt(): void
    {
        $user   = User::factory()->create();
        $klasse = Klasse::factory()->create();
        $s1     = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $s2     = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        // Abwesenheit innerhalb des Zeitraums
        PaedDiarySchuelerAbsence::create([
            'schueler_id' => $s1->id, 'klasse_id' => $klasse->id,
            'datum' => '2026-03-17', 'marked_by' => $user->id,
        ]);
        // Abwesenheit außerhalb des Zeitraums
        PaedDiarySchuelerAbsence::create([
            'schueler_id' => $s2->id, 'klasse_id' => $klasse->id,
            'datum' => '2026-04-01', 'marked_by' => $user->id,
        ]);

        $results = PaedDiarySchuelerAbsence::forKlasseInRange(
            $klasse->id, '2026-03-16', '2026-03-20'
        )->get();

        $this->assertCount(1, $results);
        $this->assertEquals($s1->id, $results->first()->schueler_id);
    }

    /** @test */
    public function schueler_paedDiaryAbsences_relationship_funktioniert(): void
    {
        $schueler = Schueler::factory()->create();
        PaedDiarySchuelerAbsence::factory()->create(['schueler_id' => $schueler->id]);
        PaedDiarySchuelerAbsence::factory()->create(['schueler_id' => $schueler->id]);

        $this->assertCount(2, $schueler->paedDiaryAbsences);
    }

    /** @test */
    public function unique_constraint_verhindert_doppelte_abwesenheit(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $user   = User::factory()->create();
        $klasse = Klasse::factory()->create();
        $s      = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $data = [
            'schueler_id' => $s->id,
            'klasse_id'   => $klasse->id,
            'datum'       => '2026-03-17',
            'marked_by'   => $user->id,
        ];
        PaedDiarySchuelerAbsence::create($data);
        PaedDiarySchuelerAbsence::create($data); // Duplikat → Exception
    }
}

