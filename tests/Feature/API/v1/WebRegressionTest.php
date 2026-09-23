<?php

namespace Tests\Feature\API\v1;

use App\Models\DiagnosticArea;
use App\Models\GradingStage;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use Tests\TestCase;

/**
 * Regressionstests für Korrekturen am Web-Frontend, die im Zuge der API v1 vorgenommen wurden.
 */
class WebRegressionTest extends TestCase
{
    /** @test */
    public function legacy_diagnose_adminrouten_erfordern_manage_diagnostics(): void
    {
        $this->actingAsWithPermission('view paed diary', 'view diagnostics');
        $area = DiagnosticArea::factory()->create();

        $this->deleteJson("/diagnostics/areas/{$area->id}")->assertForbidden();
        $this->assertDatabaseHas('diagnostic_areas', ['id' => $area->id]);
    }

    /** @test */
    public function dossier_only_false_wird_im_web_nicht_als_true_gespeichert(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($klasse->id);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $this->postJson('/paed-diary/entry', [
            'klasse_id' => $klasse->id,
            'date' => now()->toDateString(),
            'content' => 'Test',
            'schueler_ids' => [$schueler->id],
            'dossier_only' => false,
            'completed' => false,
        ])->assertOk();

        $entry = PaedDiaryEntry::latest('id')->first();
        $this->assertFalse($entry->dossier_only);
        $this->assertNull($entry->completed_at);
    }

    /** @test */
    public function stufenwechsel_im_web_nutzt_gemeinsamen_service(): void
    {
        $user = $this->actingAsWithPermission('view paed diary', 'manage grading systems');
        $system = GradingSystem::factory()->create();
        $stage = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Stufe A', 'slug' => 'a', 'sort_order' => 1]);
        $klasse = Klasse::factory()->create(['grading_system_id' => $system->id]);
        $user->paed_klassen()->attach($klasse->id);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $this->postJson('/paed-diary/change-stage', [
            'schueler_id' => $schueler->id,
            'grading_stage_id' => $stage->id,
        ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('new_stage.name', 'Stufe A');

        $this->assertSame($stage->id, $schueler->fresh()->grading_stage_id);
        $this->assertDatabaseHas('schueler_grading_histories', ['schueler_id' => $schueler->id, 'grading_stage_id' => $stage->id, 'changed_by' => $user->id]);
        $this->assertDatabaseCount('paed_diary_entries', 1);
    }
}
