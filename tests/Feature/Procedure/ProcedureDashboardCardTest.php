<?php

namespace Tests\Feature\Procedure;

use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Step;
use App\Models\User;
use Tests\TestCase;

/**
 * Querschnittstests für Dashboard-Card und View Composer.
 */
class ProcedureDashboardCardTest extends TestCase
{
    public function test_dashboard_card_composer_liefert_steps_fuer_eingeloggten_user(): void
    {
        $user = $this->actingAsWithPermission('view assigned procedures');
        $position = Positions::factory()->create();

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => now()->addDays(2),
        ]);
        $step->users()->attach($user->id);

        // View Composer wird auf der Dashboard-Card-View aufgerufen.
        // Wir testen via Composer direkt:
        $composer = new \App\View\Composers\ProcedureComposer();
        $view     = view('procedure.dashboardCard-v2');

        $this->actingAs($user);
        $composer->compose($view);

        $this->assertTrue(count($view->getData()['steps']) >= 1);
    }

    public function test_dashboard_card_liefert_keine_erledigten_steps(): void
    {
        $user = $this->actingAsWithPermission('view assigned procedures');
        $position = Positions::factory()->create();

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => true,
            'endDate'      => now()->subDay(),
        ]);
        $step->users()->attach($user->id);

        $composer = new \App\View\Composers\ProcedureComposer();
        $view     = view('procedure.dashboardCard-v2');

        $this->actingAs($user);
        $composer->compose($view);

        $this->assertCount(0, $view->getData()['steps']);
    }

    public function test_dashboard_card_liefert_keine_steps_ohne_enddate(): void
    {
        $user = $this->actingAsWithPermission('view assigned procedures');
        $position = Positions::factory()->create();

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => null,
        ]);
        $step->users()->attach($user->id);

        $composer = new \App\View\Composers\ProcedureComposer();
        $view     = view('procedure.dashboardCard-v2');

        $this->actingAs($user);
        $composer->compose($view);

        $this->assertCount(0, $view->getData()['steps']);
    }
}

