<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryEntryPause;
use App\Models\PaedDiarySchuelerAbsence;
use App\Models\Schueler;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 * Testet POST /paed-diary/absence (toggleAbsence):
 * - Abwesenheit wird gesetzt
 * - Offene Einträge des Schülers werden pausiert
 * - Antwort enthält die erstellten Pausen
 * - Beim Aufheben werden Pausen entfernt und removed_entry_ids zurückgegeben
 */
class ToggleAbsenceTest extends TestCase
{
    use MocksExternalApis;

    // ── Hilfsmethoden ─────────────────────────────────────────────────────────

    private function setupKlasseWithSchuelerAndUser(): array
    {
        $user    = $this->actingAsWithPermission('view paed diary');
        $klasse  = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        return [$user, $klasse, $schueler];
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /** @test */
    public function abwesenheit_setzen_erstellt_db_eintrag(): void
    {
        [, $klasse, $schueler] = $this->setupKlasseWithSchuelerAndUser();
        $datum = Carbon::today()->toDateString();

        $this->postJson('paed-diary/absence', [
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => $datum,
        ])->assertOk()->assertJson(['success' => true, 'absent' => true]);

        $this->assertDatabaseHas('paed_diary_schueler_absences', [
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
        ]);
    }

    /** @test */
    public function abwesenheit_setzen_pausiert_offene_eintraege(): void
    {
        [, $klasse, $schueler] = $this->setupKlasseWithSchuelerAndUser();
        $datum = Carbon::today()->toDateString();

        // Offenen Eintrag erstellen und Schüler anhängen
        $entry = PaedDiaryEntry::create([
            'klasse_id'    => $klasse->id,
            'user_id'      => 1,
            'datum'        => Carbon::yesterday()->toDateString(),
            'content'      => 'Test-Notiz',
            'completed_at' => null,
            'dossier_only' => false,
        ]);
        $entry->schueler()->attach($schueler->id);

        $this->postJson('paed-diary/absence', [
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => $datum,
        ])->assertOk();

        // Pause muss in der DB existieren (ohne datum-Vergleich wegen SQLite-Format)
        $this->assertDatabaseHas('paed_diary_entry_pauses', [
            'paed_diary_entry_id' => $entry->id,
            'schueler_id'         => $schueler->id,
        ]);
    }

    /** @test */
    public function antwort_enthaelt_erstellte_pausen(): void
    {
        [, $klasse, $schueler] = $this->setupKlasseWithSchuelerAndUser();
        $datum = Carbon::today()->toDateString();

        $entry = PaedDiaryEntry::create([
            'klasse_id'    => $klasse->id,
            'user_id'      => 1,
            'datum'        => Carbon::yesterday()->toDateString(),
            'content'      => 'Test-Notiz',
            'completed_at' => null,
            'dossier_only' => false,
        ]);
        $entry->schueler()->attach($schueler->id);

        $response = $this->postJson('paed-diary/absence', [
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => $datum,
        ]);

        $response->assertOk()
            ->assertJsonPath('absent', true)
            ->assertJsonCount(1, 'pauses');

        $pause = $response->json('pauses.0');
        $this->assertEquals($entry->id, $pause['entry_id']);
        $this->assertEquals($schueler->id, $pause['schueler_id']);
        $this->assertEquals($datum, $pause['date']);
    }

    /** @test */
    public function abgeschlossene_eintraege_werden_nicht_pausiert(): void
    {
        [, $klasse, $schueler] = $this->setupKlasseWithSchuelerAndUser();
        $datum = Carbon::today()->toDateString();

        // Bereits abgeschlossener Eintrag
        $entry = PaedDiaryEntry::create([
            'klasse_id'    => $klasse->id,
            'user_id'      => 1,
            'datum'        => Carbon::yesterday()->toDateString(),
            'content'      => 'Abgeschlossene Notiz',
            'completed_at' => Carbon::now(),
            'dossier_only' => false,
        ]);
        $entry->schueler()->attach($schueler->id);

        $response = $this->postJson('paed-diary/absence', [
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => $datum,
        ]);

        $response->assertOk()->assertJsonCount(0, 'pauses');

        $this->assertDatabaseMissing('paed_diary_entry_pauses', [
            'paed_diary_entry_id' => $entry->id,
        ]);
    }

    /** @test */
    public function abwesenheit_aufheben_entfernt_pausen_und_gibt_ids_zurueck(): void
    {
        [, $klasse, $schueler] = $this->setupKlasseWithSchuelerAndUser();
        $datum = Carbon::today()->toDateString();

        $entry = PaedDiaryEntry::create([
            'klasse_id'    => $klasse->id,
            'user_id'      => 1,
            'datum'        => Carbon::yesterday()->toDateString(),
            'content'      => 'Test-Notiz',
            'completed_at' => null,
            'dossier_only' => false,
        ]);
        $entry->schueler()->attach($schueler->id);

        // Abwesenheit + Pause direkt anlegen (simuliert ersten Toggle)
        PaedDiarySchuelerAbsence::create([
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => $datum,
            'marked_by'   => 1,
        ]);
        PaedDiaryEntryPause::create([
            'paed_diary_entry_id' => $entry->id,
            'schueler_id'         => $schueler->id,
            'date'                => $datum,
        ]);

        // Abwesenheit aufheben (zweiter Toggle = delete)
        $response = $this->postJson('paed-diary/absence', [
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
            'datum'       => $datum,
        ]);

        $response->assertOk()
            ->assertJsonPath('absent', false)
            ->assertJsonFragment(['removed_entry_ids' => [$entry->id]]);

        // Abwesenheit und Pause müssen weg sein
        $this->assertDatabaseMissing('paed_diary_schueler_absences', [
            'schueler_id' => $schueler->id,
            'klasse_id'   => $klasse->id,
        ]);
        $this->assertDatabaseMissing('paed_diary_entry_pauses', [
            'paed_diary_entry_id' => $entry->id,
            'schueler_id'         => $schueler->id,
        ]);
    }
}

