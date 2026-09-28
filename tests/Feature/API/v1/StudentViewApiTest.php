<?php

namespace Tests\Feature\API\v1;

use App\Models\DiagnosticDevelopmentGoal;
use App\Models\GradingDocumentationSession;
use App\Models\GradingStage;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;
use App\Models\SchuelerGradingHistory;
use App\Models\User;

class StudentViewApiTest extends ApiTestCase
{
    /** @test */
    public function schueler_view_liefert_alle_module_in_einer_antwort(): void
    {
        $system = GradingSystem::factory()->create();
        $stage = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Graduierung II', 'slug' => 'g2', 'sort_order' => 2]);
        [$klasse, $schueler] = $this->classWithStudent(['vorname' => 'Max', 'nachname' => 'Mustermann', 'geburtsdatum' => '2016-04-12']);
        $klasse->update(['grading_system_id' => $system->id]);
        $schueler->update(['grading_stage_id' => $stage->id]);
        SchuelerGradingHistory::create([
            'schueler_id' => $schueler->id, 'grading_system_id' => $system->id, 'grading_stage_id' => $stage->id,
            'changed_by' => null, 'created_at' => '2026-05-10 10:00:00',
        ]);

        $user = $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);

        $session = GradingDocumentationSession::factory()->create([
            'klasse_id' => $klasse->id, 'grading_system_id' => $system->id, 'schueler_id' => $schueler->id, 'user_id' => $user->id,
        ]);
        DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $schueler->id, 'title' => 'Sachtexte lesen']);
        DiagnosticDevelopmentGoal::factory()->archived()->create(['schueler_id' => $schueler->id]);

        $category = PaedDiaryCategory::factory()->create(['name' => 'Sozialverhalten', 'color' => '#3B82F6']);
        $entry = PaedDiaryEntry::factory()->completed()->create([
            'klasse_id' => $klasse->id, 'user_id' => $user->id, 'category_id' => $category->id,
            'datum' => '2026-09-23', 'content' => 'Hat heute sehr hilfsbereit unterstützt.',
        ]);
        $entry->schueler()->attach($schueler->id);

        $response = $this->getJson(self::API . "/students/{$schueler->id}/view")->assertOk();

        $response->assertJsonPath('student.id', $schueler->id)
            ->assertJsonPath('student.firstname', 'Max')
            ->assertJsonPath('student.class_name', $klasse->name)
            ->assertJsonPath('student.date_of_birth', '2016-04-12')
            ->assertJsonPath('grading_overview.current_stage.title', 'Graduierung II')
            ->assertJsonPath('grading_overview.current_stage.level', 2)
            ->assertJsonPath('grading_overview.current_stage.achieved_at', '2026-05-10')
            ->assertJsonPath('grading_overview.has_open_session', true)
            ->assertJsonPath('grading_overview.open_session_id', $session->id)
            ->assertJsonPath('diagnostic_overview.active_goals_count', 1)
            ->assertJsonPath('diagnostic_overview.active_goals.0.title', 'Sachtexte lesen')
            ->assertJsonPath('recent_paed_diary_entries.0.id', $entry->id)
            ->assertJsonPath('recent_paed_diary_entries.0.category_name', 'Sozialverhalten')
            ->assertJsonPath('recent_paed_diary_entries.0.category_color', '#3B82F6')
            ->assertJsonPath('recent_paed_diary_entries.0.content', 'Hat heute sehr hilfsbereit unterstützt.')
            ->assertJsonPath('recent_paed_diary_entries.0.created_by_name', $user->name)
            ->assertJsonPath('permissions.can_view_diagnostics', true)
            ->assertJsonMissingPath('data');

        // Archivierte Ziele optional
        $this->getJson(self::API . "/students/{$schueler->id}/view?include_archived_goals=true")
            ->assertOk()
            ->assertJsonCount(2, 'diagnostic_overview.active_goals')
            ->assertJsonPath('diagnostic_overview.active_goals_count', 1);
    }

    /** @test */
    public function diary_limit_begrenzt_die_eintraege(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);

        PaedDiaryEntry::factory()->count(5)->create(['klasse_id' => $klasse->id, 'user_id' => $user->id])
            ->each(fn ($e) => $e->schueler()->attach($schueler->id));

        $this->getJson(self::API . "/students/{$schueler->id}/view?diary_limit=3")
            ->assertOk()
            ->assertJsonCount(3, 'recent_paed_diary_entries')
            ->assertJsonPath('diagnostic_overview', null);

        $this->getJson(self::API . "/students/{$schueler->id}/view?diary_limit=abc")->assertStatus(422);
    }

    /** @test */
    public function vertrauliche_eintraege_fremder_autoren_sind_nur_mit_sonderrecht_sichtbar(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $kollegin = User::factory()->create();

        $vertraulich = PaedDiaryEntry::factory()->dossierOnly()->completed()->create(['klasse_id' => $klasse->id, 'user_id' => $kollegin->id]);
        $vertraulich->schueler()->attach($schueler->id);
        $normal = PaedDiaryEntry::factory()->completed()->create(['klasse_id' => $klasse->id, 'user_id' => $kollegin->id]);
        $normal->schueler()->attach($schueler->id);

        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $ids = collect($this->getJson(self::API . "/students/{$schueler->id}/view")->assertOk()->json('recent_paed_diary_entries'))->pluck('id');
        $this->assertTrue($ids->contains($normal->id));
        $this->assertFalse($ids->contains($vertraulich->id));

        // Mit Sonderrecht sichtbar
        $this->actingAsTeacher(['view paed diary', 'view confidential diary entries'], [$klasse]);
        $ids = collect($this->getJson(self::API . "/students/{$schueler->id}/view")->assertOk()->json('recent_paed_diary_entries'))->pluck('id');
        $this->assertTrue($ids->contains($vertraulich->id));
    }

    /** @test */
    public function eigene_vertrauliche_eintraege_sind_fuer_den_autor_sichtbar(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $eigen = PaedDiaryEntry::factory()->dossierOnly()->completed()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id]);
        $eigen->schueler()->attach($schueler->id);

        $this->getJson(self::API . "/students/{$schueler->id}/view")
            ->assertOk()
            ->assertJsonPath('recent_paed_diary_entries.0.id', $eigen->id)
            ->assertJsonPath('recent_paed_diary_entries.0.is_dossier_only', true);
    }

    /** @test */
    public function fremder_schueler_liefert_403_und_unbekannter_404(): void
    {
        [, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [Klasse::factory()->create()]);

        $this->getJson(self::API . "/students/{$schueler->id}/view")->assertForbidden();
        $this->getJson(self::API . '/students/999999/view')->assertNotFound();
    }

    /** @test */
    public function admin_hat_klassenuebergreifenden_zugriff(): void
    {
        [, $schueler] = $this->classWithStudent();
        $this->actingAsAdmin();

        $this->getJson(self::API . "/students/{$schueler->id}/view")->assertOk();
    }

    /** @test */
    public function ohne_authentifizierung_401(): void
    {
        [, $schueler] = $this->classWithStudent();

        $this->getJson(self::API . "/students/{$schueler->id}/view")->assertUnauthorized();
    }
}
