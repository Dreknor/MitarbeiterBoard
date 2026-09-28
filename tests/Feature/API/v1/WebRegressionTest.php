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

    /** @test */
    public function web_aufgaben_anlegen_bearbeiten_und_schliessen_nach_service_umbau(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($klasse->id);
        [$a, $b] = Schueler::factory()->count(2)->create(['klasse_id' => $klasse->id])->all();

        $response = $this->postJson('/paed-diary/task', [
            'klasse_id' => $klasse->id,
            'schueler_ids' => [$a->id, $b->id],
            'title' => 'Lesepass',
        ])->assertOk()->assertJsonPath('success', true)->assertJsonCount(2, 'tasks');
        // Web-Standard unverändert: neue Aufgaben sind hervorgehoben
        $response->assertJsonPath('tasks.0.highlighted', true);

        $taskId = $response->json('tasks.0.id');
        $updated = $this->putJson("/paed-diary/task/{$taskId}", ['title' => 'Lesepass abgeben', 'due_date' => '2026-10-01'])
            ->assertOk()
            ->assertJsonPath('task.title', 'Lesepass abgeben')
            ->assertJsonPath('task.due_date', '2026-10-01');
        // ohne `highlighted` bleibt die Hervorhebung (Web liefert den Rohwert 1/true)
        $this->assertTrue((bool) $updated->json('task.highlighted'));

        $this->postJson("/paed-diary/task/{$taskId}/close")->assertOk();
        $this->assertDatabaseHas('paed_diary_tasks', ['id' => $taskId, 'status' => 'closed']);
    }

    /** @test */
    public function web_termin_mit_notiz_pause_nach_service_umbau(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($klasse->id);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $entry = PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'datum' => now()->subDay()->toDateString(), 'completed_at' => null]);
        $entry->schueler()->sync([$schueler->id]);
        $date = now()->addDays(2)->toDateString();

        $id = $this->postJson('/paed-diary/appointments', [
            'title' => 'Elterngespräch',
            'start_date' => $date,
            'start_time' => '14:00',
            'schueler_ids' => [$schueler->id],
            'pause_entries' => '1',
        ])->assertOk()->assertJsonPath('success', true)->json('appointment_id');

        $this->assertDatabaseHas('paed_diary_appointment_schueler', ['paed_diary_appointment_id' => $id, 'schueler_id' => $schueler->id]);
        $this->assertTrue(\App\Models\PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->whereDate('date', $date)->exists());

        $this->putJson("/paed-diary/appointments/{$id}", [
            'title' => 'Elterngespräch neu',
            'start_date' => $date,
            'schueler_ids' => [$schueler->id],
        ])->assertOk();
        $this->assertDatabaseHas('paed_diary_appointments', ['id' => $id, 'title' => 'Elterngespräch neu', 'start_time' => null]);
    }
}
