<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Testet, dass Einträge mit dossier_only=true nicht in der
 * Klassen-/Gruppenansicht (weekData) erscheinen.
 */
class DossierOnlyFilterTest extends TestCase
{
    private function setupKlasseWithEntries(): array
    {
        $user   = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($klasse->id);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        return compact('user', 'klasse', 'schueler');
    }

    /** @test */
    public function week_data_enthaelt_keine_dossier_only_eintraege_der_aktuellen_woche(): void
    {
        ['klasse' => $klasse, 'schueler' => $schueler] = $this->setupKlasseWithEntries();

        $monday = Carbon::now()->startOfWeek();

        // Normaler Eintrag
        $normal = PaedDiaryEntry::factory()->create([
            'klasse_id'    => $klasse->id,
            'datum'        => $monday,
            'content'      => 'Normaler Eintrag',
            'dossier_only' => false,
        ]);
        $normal->schueler()->attach($schueler->id);

        // Dossier-only Eintrag
        $dossier = PaedDiaryEntry::factory()->dossierOnly()->create([
            'klasse_id' => $klasse->id,
            'datum'     => $monday,
            'content'   => 'Nur Schüleransicht',
        ]);
        $dossier->schueler()->attach($schueler->id);

        $response = $this->getJson('/paed-diary/week?klasse_id=' . $klasse->id . '&week_start=' . $monday->toDateString());
        $response->assertOk();

        $entries = collect($response->json('entries'));
        $this->assertTrue($entries->contains('id', $normal->id), 'Normaler Eintrag muss enthalten sein');
        $this->assertFalse($entries->contains('id', $dossier->id), 'Dossier-only Eintrag darf nicht enthalten sein');
    }

    /** @test */
    public function week_data_enthaelt_keine_offenen_dossier_only_eintraege_aus_vorwochen(): void
    {
        ['klasse' => $klasse, 'schueler' => $schueler] = $this->setupKlasseWithEntries();

        $lastWeekMonday = Carbon::now()->startOfWeek()->subWeek();
        $thisWeekMonday = Carbon::now()->startOfWeek();

        // Offener dossier_only Eintrag aus letzter Woche
        $dossier = PaedDiaryEntry::factory()->dossierOnly()->create([
            'klasse_id'    => $klasse->id,
            'datum'        => $lastWeekMonday,
            'content'      => 'Dossier aus Vorwoche',
            'completed_at' => null,
        ]);
        $dossier->schueler()->attach($schueler->id);

        // Offener normaler Eintrag aus letzter Woche
        $normal = PaedDiaryEntry::factory()->create([
            'klasse_id'    => $klasse->id,
            'datum'        => $lastWeekMonday,
            'content'      => 'Normal aus Vorwoche',
            'completed_at' => null,
            'dossier_only' => false,
        ]);
        $normal->schueler()->attach($schueler->id);

        $response = $this->getJson('/paed-diary/week?klasse_id=' . $klasse->id . '&week_start=' . $thisWeekMonday->toDateString());
        $response->assertOk();

        $entries = collect($response->json('entries'));
        $this->assertTrue($entries->contains('id', $normal->id), 'Normaler offener Eintrag aus Vorwoche muss enthalten sein');
        $this->assertFalse($entries->contains('id', $dossier->id), 'Dossier-only Eintrag aus Vorwoche darf nicht enthalten sein');
    }

    /** @test */
    public function open_entries_enthaelt_keine_dossier_only_eintraege(): void
    {
        ['klasse' => $klasse, 'schueler' => $schueler] = $this->setupKlasseWithEntries();

        $monday = Carbon::now()->startOfWeek();

        $dossier = PaedDiaryEntry::factory()->dossierOnly()->create([
            'klasse_id'    => $klasse->id,
            'datum'        => $monday,
            'content'      => 'Dossier-Aufgabe',
            'completed_at' => null,
        ]);
        $dossier->schueler()->attach($schueler->id);

        $normal = PaedDiaryEntry::factory()->create([
            'klasse_id'    => $klasse->id,
            'datum'        => $monday,
            'content'      => 'Normale Aufgabe',
            'completed_at' => null,
            'dossier_only' => false,
        ]);
        $normal->schueler()->attach($schueler->id);

        $response = $this->getJson('/paed-diary/week?klasse_id=' . $klasse->id . '&week_start=' . $monday->toDateString());
        $response->assertOk();

        $openEntries = collect($response->json('open_entries'));
        $this->assertTrue($openEntries->contains('id', $normal->id), 'Normale offene Notiz muss in open_entries enthalten sein');
        $this->assertFalse($openEntries->contains('id', $dossier->id), 'Dossier-only Notiz darf nicht in open_entries enthalten sein');
    }

    /** @test */
    public function store_dossier_only_eintrag_wird_automatisch_abgeschlossen(): void
    {
        ['klasse' => $klasse, 'schueler' => $schueler] = $this->setupKlasseWithEntries();

        $monday = Carbon::now()->startOfWeek();

        $response = $this->postJson('/paed-diary/entry', [
            'klasse_id'    => $klasse->id,
            'date'         => $monday->toDateString(),
            'content'      => 'Dossier-Eintrag per API',
            'schueler_ids' => [$schueler->id],
            'dossier_only' => true,
        ]);
        $response->assertOk();
        $response->assertJsonStructure(['entry_ids']);

        $entryId = $response->json('entry_ids.0');
        $entry = PaedDiaryEntry::find($entryId);
        $this->assertTrue((bool) $entry->dossier_only, 'dossier_only muss true sein');
        $this->assertNotNull($entry->completed_at, 'Dossier-only Eintrag muss automatisch abgeschlossen sein');
    }

    /** @test */
    public function store_normaler_eintrag_wird_nicht_automatisch_abgeschlossen(): void
    {
        ['klasse' => $klasse, 'schueler' => $schueler] = $this->setupKlasseWithEntries();

        $monday = Carbon::now()->startOfWeek();

        $response = $this->postJson('/paed-diary/entry', [
            'klasse_id'    => $klasse->id,
            'date'         => $monday->toDateString(),
            'content'      => 'Normaler Eintrag per API',
            'schueler_ids' => [$schueler->id],
        ]);
        $response->assertOk();

        $entryId = $response->json('entry_ids.0');
        $entry = PaedDiaryEntry::find($entryId);
        $this->assertFalse((bool) $entry->dossier_only, 'dossier_only muss false sein');
        $this->assertNull($entry->completed_at, 'Normaler Eintrag darf nicht automatisch abgeschlossen sein');
    }

    /** @test */
    public function update_zu_dossier_only_schliesst_eintrag_automatisch_ab(): void
    {
        ['klasse' => $klasse, 'schueler' => $schueler] = $this->setupKlasseWithEntries();

        $monday = Carbon::now()->startOfWeek();

        // Normalen offenen Eintrag erstellen
        $entry = PaedDiaryEntry::factory()->create([
            'klasse_id'    => $klasse->id,
            'datum'        => $monday,
            'content'      => 'Noch offen',
            'completed_at' => null,
            'dossier_only' => false,
        ]);
        $entry->schueler()->attach($schueler->id);

        // Per Update auf dossier_only umschalten
        $response = $this->postJson('/paed-diary/entry/' . $entry->id, [
            'klasse_id'    => $klasse->id,
            'date'         => $monday->toDateString(),
            'content'      => 'Jetzt Dossier',
            'schueler_ids' => [$schueler->id],
            'dossier_only' => true,
        ]);
        $response->assertOk();

        $entry->refresh();
        $this->assertTrue((bool) $entry->dossier_only, 'dossier_only muss true sein');
        $this->assertNotNull($entry->completed_at, 'Eintrag muss nach Umschalten auf dossier_only abgeschlossen sein');
    }
}

