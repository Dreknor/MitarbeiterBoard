<?php

namespace Tests\Feature\API\v1;

use App\Models\DiagnosticDevelopmentGoal;
use App\Models\DiagnosticSession;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;
use App\Models\User;
use Illuminate\Support\Facades\View;

/**
 * B7 – Dossier als PDF (gleiche Parameter, Rechte und Daten wie /dossier).
 */
class DossierPdfApiTest extends ApiTestCase
{
    /** Vom PDF-View empfangene Daten */
    private ?array $viewData = null;

    protected function setUp(): void
    {
        parent::setUp();

        View::composer('pdf.dossier', function ($view) {
            $this->viewData = $view->getData();
        });
    }

    /** @test */
    public function pdf_wird_mit_korrekten_headern_erzeugt(): void
    {
        [$klasse, $schueler] = $this->classWithStudent(['vorname' => 'Jürgen', 'nachname' => 'Müller']);
        $user = $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);
        $category = PaedDiaryCategory::factory()->create(['name' => 'Sozialverhalten', 'color' => '#22C55E']);
        PaedDiaryEntry::factory()->create([
            'klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => '2026-09-10', 'category_id' => $category->id,
        ])->schueler()->attach($schueler->id);
        DiagnosticSession::factory()->completed()->create(['schueler_id' => $schueler->id, 'session_date' => '2026-09-05']);
        DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $schueler->id]);

        $response = $this->get(self::API . "/students/{$schueler->id}/dossier.pdf?from_date=2026-09-01&to_date=2026-09-30")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="Dossier_Muller_Jurgen_2026-09-01_2026-09-30.pdf"');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        // Gleiche Datenbasis wie der JSON-Export
        $this->assertCount(1, $this->viewData['entries']);
        $this->assertNotNull($this->viewData['diagnostic']);
        $this->assertCount(1, $this->viewData['diagnostic']['sessions']);
        $this->assertSame($user->name, $this->viewData['meta']['generated_by']);
    }

    /** @test */
    public function include_confidential_false_blendet_vertrauliche_eintraege_aus(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);
        PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => now()->toDateString()])
            ->schueler()->attach($schueler->id);
        PaedDiaryEntry::factory()->dossierOnly()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => now()->toDateString()])
            ->schueler()->attach($schueler->id);

        $this->get(self::API . "/students/{$schueler->id}/dossier.pdf")->assertOk();
        $this->assertCount(2, $this->viewData['entries']);
        $this->assertTrue($this->viewData['entries']->contains('dossier_only', true));

        $this->get(self::API . "/students/{$schueler->id}/dossier.pdf?include_confidential=false")->assertOk();
        $this->assertCount(1, $this->viewData['entries']);
        $this->assertFalse($this->viewData['entries']->contains('dossier_only', true));

        // Vertrauliche Einträge fremder Autoren ohne Sonderrecht nie enthalten
        PaedDiaryEntry::factory()->dossierOnly()->create(['klasse_id' => $klasse->id, 'user_id' => User::factory()->create()->id, 'datum' => now()->toDateString()])
            ->schueler()->attach($schueler->id);
        $this->get(self::API . "/students/{$schueler->id}/dossier.pdf")->assertOk();
        $this->assertCount(2, $this->viewData['entries']);
    }

    /** @test */
    public function ohne_diagnoserecht_fehlt_der_diagnoseteil(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        DiagnosticSession::factory()->completed()->create(['schueler_id' => $schueler->id, 'session_date' => now()->toDateString()]);

        $this->get(self::API . "/students/{$schueler->id}/dossier.pdf")->assertOk();

        $this->assertNull($this->viewData['diagnostic']);
        $this->assertFalse($this->viewData['meta']['includes_diagnostics']);
    }

    /** @test */
    public function fremder_schueler_403_und_validierung_422(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        [, $fremd] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->get(self::API . "/students/{$fremd->id}/dossier.pdf")->assertForbidden()->assertJsonStructure(['message']);
        $this->get(self::API . "/students/{$schueler->id}/dossier.pdf?from_date=2026-09-30&to_date=2026-09-01")
            ->assertStatus(422)->assertJsonValidationErrors('to_date');

        $this->actingAsTeacher([], [$klasse]);
        $this->get(self::API . "/students/{$schueler->id}/dossier.pdf")->assertForbidden();
    }

    /** @test */
    public function sechzig_eintraege_in_unter_fuenf_sekunden(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);
        $categories = PaedDiaryCategory::factory()->count(4)->create();

        for ($i = 0; $i < 60; $i++) {
            PaedDiaryEntry::factory()->create([
                'klasse_id' => $klasse->id,
                'user_id' => $user->id,
                'datum' => now()->subDays($i % 30)->toDateString(),
                'category_id' => $categories[$i % 4]->id,
                'content' => str_repeat('Beobachtung im Unterricht mit ausführlicher Beschreibung. ', 4),
            ])->schueler()->attach($schueler->id);
        }

        $start = microtime(true);
        $this->get(self::API . "/students/{$schueler->id}/dossier.pdf?from_date=" . now()->subDays(40)->toDateString())->assertOk();
        $duration = microtime(true) - $start;

        $this->assertCount(60, $this->viewData['entries']);
        $this->assertLessThan(5.0, $duration, "PDF-Erzeugung dauerte {$duration}s");
    }

    /** @test */
    public function web_export_nutzt_dieselbe_view(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsWithPermission('view paed diary');
        $user->paed_klassen()->attach($klasse->id);

        $this->get("/paed-diary/schueler/{$schueler->id}/dossier.pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($schueler->id, $this->viewData['schueler']->id);

        [, $fremd] = $this->classWithStudent();
        $this->get("/paed-diary/schueler/{$fremd->id}/dossier.pdf")->assertForbidden();
    }

    /** @test */
    public function json_dossier_bleibt_unveraendert(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->getJson(self::API . "/students/{$schueler->id}/dossier")
            ->assertOk()
            ->assertJsonStructure([
                'student' => ['id', 'firstname', 'lastname', 'class_id', 'class_name', 'date_of_birth'],
                'period' => ['from_date', 'to_date'],
                'paed_diary' => ['entries_count', 'by_category', 'entries', 'goals'],
                'grading' => ['current_stage', 'stage_history', 'completed_sessions'],
                'diagnostic',
                'meta' => ['generated_at', 'generated_by', 'includes_confidential', 'includes_diagnostics'],
            ]);
    }
}
