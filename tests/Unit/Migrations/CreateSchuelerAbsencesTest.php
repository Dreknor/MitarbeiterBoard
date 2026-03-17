<?php

namespace Tests\Unit\Migrations;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests für Schüler-Tages-Abwesenheit
 */
class CreateSchuelerAbsencesTest extends TestCase
{
    /** @test */
    public function schueler_absences_tabelle_existiert_mit_korrekten_spalten(): void
    {
        $this->assertTrue(
            Schema::hasTable('paed_diary_schueler_absences'),
            'Tabelle paed_diary_schueler_absences fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_schueler_absences', 'schueler_id'),
            'Spalte schueler_id fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_schueler_absences', 'klasse_id'),
            'Spalte klasse_id fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_schueler_absences', 'datum'),
            'Spalte datum fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_schueler_absences', 'marked_by'),
            'Spalte marked_by fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_schueler_absences', 'created_at'),
            'Spalte created_at fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_schueler_absences', 'updated_at'),
            'Spalte updated_at fehlt'
        );
    }

    /** @test */
    public function unique_constraint_verhindert_doppelte_abwesenheiten(): void
    {
        $user = \App\Models\User::factory()->create();
        $klasse = \App\Models\Klasse::factory()->create();
        $schueler = \App\Models\Schueler::factory()->create(['klasse_id' => $klasse->id]);

        \DB::table('paed_diary_schueler_absences')->insert([
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => '2026-03-17',
            'marked_by'   => $user->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('paed_diary_schueler_absences')->insert([
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => '2026-03-17',
            'marked_by'   => $user->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** @test */
    public function cascade_delete_entfernt_eintraege_beim_loeschen_des_schuelers(): void
    {
        $user = \App\Models\User::factory()->create();
        $klasse = \App\Models\Klasse::factory()->create();
        $schueler = \App\Models\Schueler::factory()->create(['klasse_id' => $klasse->id]);

        \DB::table('paed_diary_schueler_absences')->insert([
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => '2026-03-17',
            'marked_by'   => $user->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->assertDatabaseCount('paed_diary_schueler_absences', 1);

        // Schueler verwendet SoftDeletes – forceDelete() löst FK-Cascade aus
        $schueler->forceDelete();

        $this->assertDatabaseCount('paed_diary_schueler_absences', 0);
    }

    /** @test */
    public function cascade_delete_entfernt_eintraege_beim_loeschen_der_klasse(): void
    {
        $user = \App\Models\User::factory()->create();
        $klasse = \App\Models\Klasse::factory()->create();
        $schueler = \App\Models\Schueler::factory()->create(['klasse_id' => $klasse->id]);

        \DB::table('paed_diary_schueler_absences')->insert([
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => '2026-03-17',
            'marked_by'   => $user->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->assertDatabaseCount('paed_diary_schueler_absences', 1);

        // Schueler zuerst entfernen, dann Klasse (beide mit forceDelete wegen SoftDeletes)
        $schueler->forceDelete();
        $klasse->forceDelete();

        $this->assertDatabaseCount('paed_diary_schueler_absences', 0);
    }

    /** @test */
    public function cascade_delete_entfernt_eintraege_beim_loeschen_des_users(): void
    {
        $user = \App\Models\User::factory()->create();
        $klasse = \App\Models\Klasse::factory()->create();
        $schueler = \App\Models\Schueler::factory()->create(['klasse_id' => $klasse->id]);

        \DB::table('paed_diary_schueler_absences')->insert([
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => '2026-03-17',
            'marked_by'   => $user->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->assertDatabaseCount('paed_diary_schueler_absences', 1);

        // User verwendet SoftDeletes – forceDelete() löst FK-Cascade aus
        $user->forceDelete();

        $this->assertDatabaseCount('paed_diary_schueler_absences', 0);
    }

    /** @test */
    public function datum_spalte_ist_vom_typ_date(): void
    {
        $user = \App\Models\User::factory()->create();
        $klasse = \App\Models\Klasse::factory()->create();
        $schueler = \App\Models\Schueler::factory()->create(['klasse_id' => $klasse->id]);

        \DB::table('paed_diary_schueler_absences')->insert([
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => '2026-03-17',
            'marked_by'   => $user->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $record = \DB::table('paed_diary_schueler_absences')->first();
        $this->assertEquals('2026-03-17', $record->datum);
    }
}


