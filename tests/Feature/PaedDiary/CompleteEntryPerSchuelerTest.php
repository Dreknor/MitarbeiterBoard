<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 * Testet POST /paed-diary/entry/{entry}/complete (completeEntry):
 *
 * Ein Eintrag kann mehreren Schülern zugeordnet sein. Wird er für einen
 * einzelnen Schüler abgeschlossen, darf dies nicht automatisch auch für
 * die übrigen Schüler gelten - der ursprüngliche Eintrag muss für sie
 * offen bleiben.
 */
class CompleteEntryPerSchuelerTest extends TestCase
{
    use MocksExternalApis;

    private function setupKlasseWithSchuelerAndUser(int $anzahlSchueler = 2): array
    {
        $user   = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);
        $schueler = Schueler::factory()->count($anzahlSchueler)->create(['klasse_id' => $klasse->id]);
        return [$user, $klasse, $schueler];
    }

    /** @test */
    public function abschluss_fuer_einen_schueler_laesst_eintrag_fuer_andere_schueler_offen(): void
    {
        [$user, $klasse, $schuelerListe] = $this->setupKlasseWithSchuelerAndUser(2);
        $schuelerA = $schuelerListe[0];
        $schuelerB = $schuelerListe[1];

        $entry = PaedDiaryEntry::create([
            'klasse_id'    => $klasse->id,
            'user_id'      => $user->id,
            'datum'        => Carbon::yesterday()->toDateString(),
            'content'      => 'Gemeinsame Notiz',
            'completed_at' => null,
            'dossier_only' => false,
        ]);
        $entry->schueler()->attach([$schuelerA->id, $schuelerB->id]);

        $response = $this->postJson("paed-diary/entry/{$entry->id}/complete", [
            'schueler_id'   => $schuelerA->id,
            'completed_at'  => Carbon::today()->toDateString(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Ursprünglicher Eintrag bleibt offen und ist nur noch mit Schüler B verknüpft.
        $entry->refresh();
        $this->assertNull($entry->completed_at);
        $this->assertEquals([$schuelerB->id], $entry->schueler()->pluck('schueler.id')->all());

        // Es existiert ein neuer, abgeschlossener Eintrag nur für Schüler A.
        $completedEntry = PaedDiaryEntry::where('id', '!=', $entry->id)
            ->whereNotNull('completed_at')
            ->first();
        $this->assertNotNull($completedEntry);
        $this->assertEquals([$schuelerA->id], $completedEntry->schueler()->pluck('schueler.id')->all());
    }

    /** @test */
    public function abschluss_bei_nur_einem_schueler_beendet_den_originaleintrag(): void
    {
        [$user, $klasse, $schuelerListe] = $this->setupKlasseWithSchuelerAndUser(1);
        $schueler = $schuelerListe[0];

        $entry = PaedDiaryEntry::create([
            'klasse_id'    => $klasse->id,
            'user_id'      => $user->id,
            'datum'        => Carbon::yesterday()->toDateString(),
            'content'      => 'Einzel-Notiz',
            'completed_at' => null,
            'dossier_only' => false,
        ]);
        $entry->schueler()->attach($schueler->id);

        $response = $this->postJson("paed-diary/entry/{$entry->id}/complete", [
            'schueler_id'  => $schueler->id,
            'completed_at' => Carbon::today()->toDateString(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $entry->refresh();
        $this->assertNotNull($entry->completed_at);
    }

    /** @test */
    public function abschluss_ohne_schueler_id_beendet_weiterhin_den_gesamten_eintrag(): void
    {
        [$user, $klasse, $schuelerListe] = $this->setupKlasseWithSchuelerAndUser(2);

        $entry = PaedDiaryEntry::create([
            'klasse_id'    => $klasse->id,
            'user_id'      => $user->id,
            'datum'        => Carbon::yesterday()->toDateString(),
            'content'      => 'Gemeinsame Notiz',
            'completed_at' => null,
            'dossier_only' => false,
        ]);
        $entry->schueler()->attach($schuelerListe->pluck('id')->all());

        $response = $this->postJson("paed-diary/entry/{$entry->id}/complete", [
            'completed_at' => Carbon::today()->toDateString(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $entry->refresh();
        $this->assertNotNull($entry->completed_at);
        $this->assertCount(2, $entry->schueler()->pluck('schueler.id')->all());
    }
}

