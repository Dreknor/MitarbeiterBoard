<?php

namespace Tests\Feature;

use App\Models\GradingStage;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use Tests\TestCase;

class KlasseControllerTest extends TestCase
{
    private function buildUpdatePayload(Klasse $klasse, array $overrides = []): array
    {
        return array_merge([
            'name'    => $klasse->name,
            'kuerzel' => $klasse->kuerzel,
            'color'   => $klasse->color, // NOT NULL in DB
        ], $overrides);
    }

    // ─── Test: User ohne Permission kann show_vertretungen nicht ändern ───────

    public function test_user_without_permission_cannot_change_show_vertretungen(): void
    {
        $klasse = Klasse::factory()->create(['show_vertretungen' => true]);
        $this->actingAsWithPermission('edit klassen');

        $payload = $this->buildUpdatePayload($klasse, ['show_vertretungen' => '0']);
        $this->put(route('klassen.update', $klasse), $payload);

        $klasse->refresh();
        $this->assertTrue((bool) $klasse->show_vertretungen, 'User ohne Permission darf show_vertretungen nicht ändern');
    }

    // ─── Test: User mit Permission kann show_vertretungen ändern ─────────────

    public function test_user_with_permission_can_change_show_vertretungen(): void
    {
        $klasse = Klasse::factory()->create(['show_vertretungen' => true]);
        $this->actingAsWithPermission('edit klassen', 'edit klassen vertretungen');

        $payload = $this->buildUpdatePayload($klasse, ['show_vertretungen' => '0']);
        $response = $this->put(route('klassen.update', $klasse), $payload);

        $response->assertRedirect();
        $rawValue = \Illuminate\Support\Facades\DB::table('klassen')->where('id', $klasse->id)->value('show_vertretungen');
        $this->assertEquals(0, (int) $rawValue, 'User mit Permission: show_vertretungen muss auf 0 gesetzt worden sein');
    }

    // ─── Test: Graduierungssystem zuweisen legt je Schüler einen abgeschlossenen Eintrag an ──

    public function test_grading_system_assignment_creates_completed_entry_per_student(): void
    {
        $klasse = Klasse::factory()->create();
        $this->actingAsWithPermission('edit klassen');
        $system = GradingSystem::factory()->create();
        $stage = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Stufe I', 'slug' => 's1', 'sort_order' => 1]);
        $anna = Schueler::factory()->create(['klasse_id' => $klasse->id, 'vorname' => 'Anna', 'nachname' => 'Alt']);
        $ben = Schueler::factory()->create(['klasse_id' => $klasse->id, 'vorname' => 'Ben', 'nachname' => 'Berg']);

        $payload = $this->buildUpdatePayload($klasse, ['grading_system_id' => $system->id]);
        $this->put(route('klassen.update', $klasse), $payload)->assertRedirect();

        foreach ([[$anna, $ben], [$ben, $anna]] as [$schueler, $andere]) {
            $this->assertEquals($stage->id, $schueler->fresh()->grading_stage_id);

            $eintraege = PaedDiaryEntry::whereHas('schueler', fn ($q) => $q->whereKey($schueler->id))->get();
            $this->assertCount(1, $eintraege, 'Pro Schüler genau ein Eintrag');
            $eintrag = $eintraege->first();
            $this->assertNotNull($eintrag->completed_at, 'Der Eintrag darf nicht als offene Notiz liegen bleiben');
            $this->assertEquals([$schueler->id], $eintrag->schueler()->pluck('schueler.id')->all());
            $this->assertStringContainsString($schueler->vorname, $eintrag->content);
            $this->assertStringNotContainsString($andere->vorname, $eintrag->content);

            $this->assertDatabaseHas('schueler_grading_histories', [
                'schueler_id' => $schueler->id,
                'grading_stage_id' => $stage->id,
                'paed_diary_entry_id' => $eintrag->id,
            ]);
        }
    }
}


