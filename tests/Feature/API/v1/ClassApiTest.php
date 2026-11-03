<?php

namespace Tests\Feature\API\v1;

use App\Models\DiagnosticDevelopmentGoal;
use App\Models\GradingStage;
use App\Models\GradingSystem;
use App\Models\Klasse;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;

class ClassApiTest extends ApiTestCase
{
    /** @test */
    public function liefert_nur_zugewiesene_klassen_mit_schuelerzahl(): void
    {
        [$klasse] = $this->classWithStudent();
        Schueler::factory()->count(2)->create(['klasse_id' => $klasse->id]);
        $fremd = Klasse::factory()->create();

        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $response = $this->getJson(self::API . '/classes')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $klasse->id)
            ->assertJsonPath('data.0.students_count', 3)
            ->assertJsonStructure(['data' => [['id', 'name', 'school_year', 'students_count']], 'learning_groups']);

        $this->assertNotContains($fremd->id, collect($response->json('data'))->pluck('id'));
    }

    /** @test */
    public function admin_kann_mit_all_alle_klassen_abrufen(): void
    {
        Klasse::factory()->count(3)->create();
        $this->actingAsAdmin();

        $this->getJson(self::API . '/classes?all=true')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson(self::API . '/classes')->assertOk()->assertJsonCount(0, 'data');
    }

    /** @test */
    public function klassenliste_liefert_kompakt_status(): void
    {
        $system = GradingSystem::factory()->create();
        $stage = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Graduierung II', 'slug' => 'g2', 'sort_order' => 2]);
        $klasse = Klasse::factory()->create(['grading_system_id' => $system->id]);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id, 'grading_stage_id' => $stage->id, 'nachname' => 'Aaa']);

        $user = $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);

        $entry = PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => now()->toDateString()]);
        $entry->schueler()->attach($schueler->id);
        $alt = PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => now()->subDays(30)->toDateString()]);
        $alt->schueler()->attach($schueler->id);

        DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $schueler->id]);
        DiagnosticDevelopmentGoal::factory()->achieved()->create(['schueler_id' => $schueler->id]);

        $this->getJson(self::API . "/classes/{$klasse->id}/students")
            ->assertOk()
            ->assertJsonPath('data.0.id', $schueler->id)
            ->assertJsonPath('data.0.current_grading.stage_title', 'Graduierung II')
            ->assertJsonPath('data.0.active_diagnostic_goals_count', 1)
            ->assertJsonPath('data.0.recent_diary_entries_count', 1)
            ->assertJsonPath('meta.recent_days', 14);
    }

    /** @test */
    public function klassenliste_und_schueler_view_ohne_n_plus_1_abfragen(): void
    {
        $klasse = Klasse::factory()->create();
        $user = $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);

        $countQueries = function () use ($klasse): int {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            $this->getJson(self::API . "/classes/{$klasse->id}/students")->assertOk();
            $count = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $count;
        };

        Schueler::factory()->count(2)->create(['klasse_id' => $klasse->id]);
        $countQueries(); // Permission-Cache aufwärmen
        $wenige = $countQueries();

        $students = Schueler::factory()->count(10)->create(['klasse_id' => $klasse->id]);
        foreach ($students as $s) {
            PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => now()->toDateString()])
                ->schueler()->attach($s->id);
        }
        $viele = $countQueries();

        $this->assertSame($wenige, $viele, 'Die Anzahl der Abfragen darf nicht mit der Schülerzahl wachsen.');
    }

    /** @test */
    public function fremde_klasse_liefert_403(): void
    {
        [$klasse] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [Klasse::factory()->create()]);

        $this->getJson(self::API . "/classes/{$klasse->id}/students")->assertForbidden();
    }

    /** @test */
    public function unbekannte_klasse_liefert_404(): void
    {
        $this->actingAsTeacher();

        $this->getJson(self::API . '/classes/999999/students')
            ->assertNotFound()
            ->assertJsonPath('message', 'Die angeforderte Ressource wurde nicht gefunden.');
    }
}
