<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use App\Models\personal\HortFaktor;
use App\Models\personal\HortFaktorWert;
use App\Models\personal\HortMonatZusatz;
use App\Models\personal\HortPlanung;
use App\Models\personal\HortPlanungMonat;
use App\Models\personal\HortPlanungPerson;
use App\Models\personal\HortZusatzstundenTyp;
use App\Services\HortPlanungService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature-Tests für HortPlanungController (Konzept §12.2).
 */
class HortPlanungControllerTest extends TestCase
{
    // ── Hilfsmethoden ────────────────────────────────────────────────

    /**
     * Erstellt eine vollständige Planung mit Monaten und Standard-Faktoren.
     */
    private function erstellePlanung(User $user, bool $aktiv = false): HortPlanung
    {
        $dept    = Group::factory()->asDepartment()->create();
        $service = app(HortPlanungService::class);

        $planung = HortPlanung::create([
            'name'          => 'Test-Planung',
            'department_id' => $dept->id,
            'start_monat'   => '2024-01-01',
            'end_monat'     => '2024-06-01',
            'typ'           => 'planung',
            'aktiv'         => $aktiv,
            'created_by'    => $user->id,
        ]);

        $service->erstelleMonate($planung);
        $service->erstelleStandardFaktoren($planung);
        $service->erstelleStandardZusatztypen($planung);
        $planung->load(['faktoren.werte', 'zusatzstundenTypen', 'monate']);

        return $planung;
    }

    // ── Zugriffskontrolle ─────────────────────────────────────────────

    public function test_index_erfordert_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('hort-planung.index'));
        $response->assertStatus(403);
    }

    public function test_index_zeigt_planungen(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung');
        $planung = $this->erstellePlanung($user);

        $response = $this->get(route('hort-planung.index'));

        $response->assertStatus(200);
        $response->assertSee($planung->name);
    }

    public function test_create_erfordert_manage_permission(): void
    {
        $this->actingAsWithPermission('view hort planung');

        $response = $this->get(route('hort-planung.create'));
        $response->assertStatus(403);
    }

    // ── CRUD Planung ──────────────────────────────────────────────────

    public function test_store_erstellt_planung_mit_monaten(): void
    {
        $user = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $dept = Group::factory()->asDepartment()->create();

        $response = $this->post(route('hort-planung.store'), [
            'name'          => 'Neue Planung',
            'beschreibung'  => 'Test',
            'department_id' => $dept->id,
            'start_monat'   => '2024-01',
            'end_monat'     => '2024-06',
            'typ'           => 'planung',
            'kinderanzahl'  => 100,
        ]);

        $response->assertRedirect();

        $planung = HortPlanung::where('name', 'Neue Planung')->first();
        $this->assertNotNull($planung);

        // 6 Monate erwartet (Jan–Jun 2024)
        $this->assertEquals(6, $planung->monate()->count());

        // Standard-Faktoren angelegt
        $this->assertEquals(5, $planung->faktoren()->count());

        // Standard-Zusatztypen angelegt
        $this->assertEquals(2, $planung->zusatzstundenTypen()->count());
    }

    public function test_show_matrix_mit_berechnungen(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung');
        $planung = $this->erstellePlanung($user);

        $response = $this->get(route('hort-planung.show', $planung));

        $response->assertStatus(200);
        $response->assertViewHasAll(['planung', 'berechnungenNachMonat', 'allePersonen']);
    }

    public function test_destroy_soft_delete(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);

        $response = $this->delete(route('hort-planung.destroy', $planung));
        $response->assertRedirect(route('hort-planung.index'));

        $this->assertSoftDeleted('hort_planungen', ['id' => $planung->id]);
    }

    public function test_update_aendert_name_und_zeitraum(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user); // Jan–Jun 2024 (6 Monate)

        $response = $this->put(route('hort-planung.update', $planung), [
            'name'        => 'Geänderte Planung',
            'start_monat' => '2024-01',
            'end_monat'   => '2024-09', // 3 neue Monate (Jul–Sep)
        ]);

        $response->assertRedirect(route('hort-planung.edit', $planung));

        $planung->refresh();
        $this->assertEquals('Geänderte Planung', $planung->name);
        $this->assertEquals('2024-09-01', $planung->end_monat->format('Y-m-d'));
        // 9 Monate erwartet (Jan–Sep 2024)
        $this->assertEquals(9, $planung->monate()->count());
    }

    // ── AJAX: Monat & Person ───────────────────────────────────────────

    public function test_updateMonat_ajax(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);
        $monat   = $planung->monate->first();

        $response = $this->putJson(
            route('hort-planung.updateMonat', [$planung, $monat]),
            ['kinderanzahl' => 95, 'vollzeitstunden' => 40]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'berechnungen']);
        $this->assertDatabaseHas('hort_planung_monate', ['id' => $monat->id, 'kinderanzahl' => 95]);
    }

    public function test_updatePerson_ajax(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);
        $monat   = $planung->monate->first();

        $mitarbeiter = User::factory()->create();
        $person = HortPlanungPerson::create([
            'hort_planung_monat_id' => $monat->id,
            'user_id'               => $mitarbeiter->id,
            'stunden_gesamt'        => 25,
            'stunden_stadt'         => 20,
        ]);

        $response = $this->putJson(
            route('hort-planung.updatePerson', [$planung, $person]),
            ['stunden_gesamt' => 30, 'stunden_stadt' => 25]
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('hort_planung_personen', ['id' => $person->id, 'stunden_gesamt' => 30]);
    }

    public function test_bulkUpdatePerson(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);
        $person  = User::factory()->create();

        $response = $this->putJson(
            route('hort-planung.bulkUpdatePerson', [$planung, $person]),
            ['ab_monat' => '2024-03-01', 'stunden_gesamt' => 35, 'stunden_stadt' => 30]
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'personIds', 'berechnungen']);

        // Stunden ab März – 4 Monate (Mrz-Jun)
        $count = HortPlanungPerson::whereIn(
            'hort_planung_monat_id',
            $planung->monate->pluck('id')
        )->where('user_id', $person->id)->count();

        $this->assertEquals(4, $count);
    }

    // ── Szenarien ─────────────────────────────────────────────────────

    public function test_duplicate_erstellt_kopie(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);

        $response = $this->post(
            route('hort-planung.duplicate', $planung),
            ['name' => 'Kopie der Planung', 'beschreibung' => 'Test-Kopie']
        );

        $response->assertRedirect();

        $kopie = HortPlanung::where('name', 'Kopie der Planung')->first();
        $this->assertNotNull($kopie);
        $this->assertEquals($planung->id, $kopie->kopiert_von_id);
        $this->assertEquals($planung->faktoren->count(), $kopie->faktoren->count());
        $this->assertEquals($planung->monate->count(), $kopie->monate->count());
    }

    public function test_rueckblick_zeigt_ist_daten(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung');
        $planung = $this->erstellePlanung($user);

        // Einen vergangenen Monat hinzufügen
        $vergMonat = HortPlanungMonat::create([
            'hort_planung_id' => $planung->id,
            'monat'           => now()->subMonths(2)->format('Y-m-01'),
            'kinderanzahl'    => 100,
            'vollzeitstunden' => 40,
        ]);

        $response = $this->get(route('hort-planung.rueckblick', $planung));
        $response->assertStatus(200);
        $response->assertViewHasAll(['planung', 'monate', 'berechnungenNachMonat']);
    }

    public function test_vergleich_zweier_planungen(): void
    {
        $user     = $this->actingAsWithPermission('view hort planung');
        $planungA = $this->erstellePlanung($user);
        $planungB = $this->erstellePlanung($user);

        $response = $this->get(route('hort-planung.vergleich', [$planungA, $planungB]));

        $response->assertStatus(200);
        $response->assertViewHasAll(['planung', 'other', 'vergleich']);
    }

    // ── Export ────────────────────────────────────────────────────────

    public function test_export_download(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung');
        $planung = $this->erstellePlanung($user);

        $response = $this->get(route('hort-planung.export', $planung));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheet',
            strtolower($response->headers->get('Content-Type', ''))
        );
    }

    // ── Faktoren-CRUD ─────────────────────────────────────────────────

    public function test_storeFaktor_legt_neuen_faktor_an(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);

        $response = $this->post(
            route('hort-planung.storeFaktor', $planung),
            [
                'kuerzel'          => 'inklusion',
                'bezeichnung'      => 'Inklusion',
                'berechnungs_typ'  => 'faktor_auf_bs',
                'position'         => 10,
                'wert'             => 0.05,
                'gueltig_ab'       => '2024-01-01',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('hort_faktoren', [
            'hort_planung_id' => $planung->id,
            'kuerzel'         => 'inklusion',
        ]);
    }

    public function test_updateFaktor_aendert_bezeichnung(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);
        $faktor  = $planung->faktoren->firstWhere('kuerzel', 'leitung');

        $response = $this->putJson(
            route('hort-planung.updateFaktor', [$planung, $faktor]),
            ['bezeichnung' => 'Leitungszuschlag']
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('hort_faktoren', ['id' => $faktor->id, 'bezeichnung' => 'Leitungszuschlag']);
    }

    public function test_deleteFaktor_deaktiviert(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);
        $faktor  = $planung->faktoren->firstWhere('kuerzel', 'leitung');

        $response = $this->deleteJson(route('hort-planung.deleteFaktor', [$planung, $faktor]));

        $response->assertStatus(200);
        $this->assertDatabaseHas('hort_faktoren', ['id' => $faktor->id, 'aktiv' => false]);
    }

    public function test_storeFaktorWert_ab_monat(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);
        $faktor  = $planung->faktoren->firstWhere('kuerzel', 'kinderschluessel');

        $response = $this->post(
            route('hort-planung.storeFaktorWert', [$planung, $faktor]),
            ['wert' => 21.0, 'gueltig_ab' => '2024-04-01', 'notiz' => 'Neue Verordnung']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('hort_faktor_werte', [
            'hort_faktor_id' => $faktor->id,
            'gueltig_ab'     => '2024-04-01',
        ]);
    }

    public function test_deleteFaktorWert_entfernt_aenderung(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);
        $faktor  = $planung->faktoren->firstWhere('kuerzel', 'kinderschluessel');

        // Zweiten Wert hinzufügen
        $neuerWert = HortFaktorWert::create([
            'hort_faktor_id' => $faktor->id,
            'wert'           => 21.0,
            'gueltig_ab'     => '2024-06-01',
            'notiz'          => 'Test',
            'created_by'     => $planung->created_by,
        ]);

        $response = $this->delete(route('hort-planung.deleteFaktorWert', [$planung, $neuerWert]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('hort_faktor_werte', ['id' => $neuerWert->id]);
    }

    // ── Zusatzstunden-CRUD ────────────────────────────────────────────

    public function test_storeZusatzTyp(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);

        $response = $this->post(
            route('hort-planung.storeZusatzTyp', $planung),
            ['kuerzel' => 'fortbildung', 'bezeichnung' => 'Fortbildungen', 'position' => 3]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('hort_zusatzstunden_typen', [
            'hort_planung_id' => $planung->id,
            'kuerzel'         => 'fortbildung',
        ]);
    }

    public function test_updateMonatZusatz(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);
        $monat   = $planung->monate->first();
        $typ     = $planung->zusatzstundenTypen->first();

        $response = $this->putJson(
            route('hort-planung.updateMonatZusatz', [$planung, $monat, $typ]),
            ['stunden' => 8.5, 'notiz' => 'Testeintrag']
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('hort_monat_zusatzstunden', [
            'hort_planung_monat_id'     => $monat->id,
            'hort_zusatzstunden_typ_id' => $typ->id,
            'stunden'                   => 8.5,
        ]);
    }

    public function test_deleteZusatzTyp_deaktiviert(): void
    {
        $user    = $this->actingAsWithPermission('view hort planung', 'manage hort planung');
        $planung = $this->erstellePlanung($user);
        $typ     = $planung->zusatzstundenTypen->first();

        $response = $this->deleteJson(route('hort-planung.deleteZusatzTyp', [$planung, $typ]));

        $response->assertStatus(200);
        $this->assertDatabaseHas('hort_zusatzstunden_typen', ['id' => $typ->id, 'aktiv' => false]);
    }
}

