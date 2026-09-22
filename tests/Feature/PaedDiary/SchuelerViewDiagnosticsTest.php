<?php

namespace Tests\Feature\PaedDiary;

use App\Models\DiagnosticAssessment;
use App\Models\DiagnosticSession;
use App\Models\Klasse;
use App\Models\Schueler;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Testet die Einbindung der Diagnose-Sitzungen und der aktuellen Diagnose-Ziele
 * in die Schüler-Einzelansicht des pädagogischen Tagebuchs.
 */
class SchuelerViewDiagnosticsTest extends TestCase
{
    use CreatesTestData;

    private function setupKlasseMitSchueler(): array
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        return compact('klasse', 'schueler');
    }

    /** @test */
    public function diagnose_tab_wird_ohne_berechtigung_nicht_angezeigt(): void
    {
        ['klasse' => $klasse, 'schueler' => $schueler] = $this->setupKlasseMitSchueler();
        $user = $this->actingAsWithPermission('view paed diary');
        $user->paed_klassen()->attach($klasse->id);

        $response = $this->get(route('paedDiary.schueler.view', $schueler->id));

        $response->assertOk();
        $response->assertDontSee('Diagnose-Sitzungen');
        $response->assertDontSee('Aktuelle Ziele aus den Diagnosen');
    }

    /** @test */
    public function diagnose_tab_zeigt_sitzungen_und_nur_aktuelle_ziele(): void
    {
        ['klasse' => $klasse, 'schueler' => $schueler] = $this->setupKlasseMitSchueler();
        $user = $this->actingAsWithPermission('view paed diary', 'view diagnostics');
        $user->paed_klassen()->attach($klasse->id);

        ['goals' => $goals] = $this->createDiagnosticSetup();

        $session = DiagnosticSession::create([
            'schueler_id' => $schueler->id,
            'diagnostic_area_id' => $goals[0]->stage->diagnostic_area_id,
            'user_id' => $user->id,
            'is_completed' => true,
        ]);

        // Ein aktuelles Ziel ...
        DiagnosticAssessment::create([
            'diagnostic_session_id' => $session->id,
            'diagnostic_goal_id' => $goals[0]->id,
            'rating' => 'dark_gray',
            'is_current_goal' => true,
        ]);

        // ... und ein nicht-aktuelles Ziel, das NICHT in der Zielübersicht auftauchen darf.
        DiagnosticAssessment::create([
            'diagnostic_session_id' => $session->id,
            'diagnostic_goal_id' => $goals[1]->id,
            'rating' => 'white',
            'is_current_goal' => false,
        ]);

        $response = $this->get(route('paedDiary.schueler.view', $schueler->id));

        $response->assertOk();
        $response->assertSee('Diagnose-Sitzungen');
        $response->assertSee('Aktuelle Ziele aus den Diagnosen');
        $response->assertSee($goals[0]->code);
        $response->assertDontSee($goals[1]->code);
    }
}
