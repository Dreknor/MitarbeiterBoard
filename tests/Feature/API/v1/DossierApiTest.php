<?php

namespace Tests\Feature\API\v1;

use App\Models\DiagnosticDevelopmentGoal;
use App\Models\DiagnosticSession;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;
use App\Models\User;

class DossierApiTest extends ApiTestCase
{
    /** @test */
    public function dossier_buendelt_alle_module_im_zeitraum(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);
        $category = PaedDiaryCategory::factory()->create(['name' => 'Sozialverhalten']);

        foreach (['2026-08-15', '2026-09-10', '2026-09-12'] as $datum) {
            PaedDiaryEntry::factory()->create([
                'klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => $datum, 'category_id' => $category->id,
            ])->schueler()->attach($schueler->id);
        }
        DiagnosticSession::factory()->completed()->create(['schueler_id' => $schueler->id, 'session_date' => '2026-09-05']);
        DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $schueler->id]);

        $this->getJson(self::API . "/students/{$schueler->id}/dossier?from_date=2026-09-01&to_date=2026-09-30")
            ->assertOk()
            ->assertJsonPath('student.id', $schueler->id)
            ->assertJsonPath('period.from_date', '2026-09-01')
            ->assertJsonPath('period.to_date', '2026-09-30')
            ->assertJsonPath('paed_diary.entries_count', 2)
            ->assertJsonPath('paed_diary.by_category.0.category_name', 'Sozialverhalten')
            ->assertJsonPath('paed_diary.by_category.0.count', 2)
            ->assertJsonPath('paed_diary.entries.0.entry_date', '2026-09-10')
            ->assertJsonCount(1, 'diagnostic.sessions')
            ->assertJsonCount(1, 'diagnostic.development_goals')
            ->assertJsonStructure(['grading' => ['current_stage', 'stage_history', 'completed_sessions'], 'meta' => ['generated_at']]);
    }

    /** @test */
    public function vertrauliche_eintraege_nur_mit_sonderrecht_und_abschaltbar(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $vertraulich = PaedDiaryEntry::factory()->dossierOnly()->create([
            'klasse_id' => $klasse->id, 'user_id' => User::factory()->create()->id, 'datum' => now()->toDateString(),
        ]);
        $vertraulich->schueler()->attach($schueler->id);

        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $this->getJson(self::API . "/students/{$schueler->id}/dossier")
            ->assertOk()
            ->assertJsonPath('paed_diary.entries_count', 0)
            ->assertJsonPath('meta.includes_confidential', false)
            ->assertJsonPath('diagnostic', null);

        $this->actingAsAdmin();
        $this->getJson(self::API . "/students/{$schueler->id}/dossier")
            ->assertOk()->assertJsonPath('paed_diary.entries_count', 1)->assertJsonPath('meta.includes_confidential', true);

        $this->getJson(self::API . "/students/{$schueler->id}/dossier?include_confidential=false")
            ->assertOk()->assertJsonPath('paed_diary.entries_count', 0);
    }

    /** @test */
    public function ungueltiger_zeitraum_liefert_422_und_fremder_schueler_403(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        [, $fremd] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->getJson(self::API . "/students/{$schueler->id}/dossier?from_date=2026-09-30&to_date=2026-09-01")
            ->assertStatus(422)->assertJsonValidationErrors('to_date');
        $this->getJson(self::API . "/students/{$schueler->id}/dossier?include_confidential=vielleicht")
            ->assertStatus(422);
        $this->getJson(self::API . "/students/{$fremd->id}/dossier")->assertForbidden();
    }
}
