<?php

namespace Tests\Feature\Procedure;

use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Category;
use App\Models\Procedure_Step;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase-4-Tests:
 *  - Legacy-URLs leiten auf neue Single-Page-Index weiter
 *  - GET-Mutation /ends → 404 (entfernt, jetzt POST /end)
 *  - POST /end beendet Prozess korrekt
 *  - GET /stepMail → 404 (entfernt, Scheduler-only)
 *  - GET /positions/remove → 405 Method Not Allowed (jetzt DELETE)
 */
class ProcedurePhase4Test extends TestCase
{
    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('manage procedures');
        $user->givePermissionTo('delete procedures');
        $user->givePermissionTo('complete own procedure steps');
        return $user;
    }

    private function erstelleGestarteten(User $user): array
    {
        $kategorie = Procedure_Category::factory()->create();
        $position  = Positions::factory()->create();

        $laufend = Procedure::factory()->gestartet()->create([
            'name'        => 'Laufender Prozess',
            'category_id' => $kategorie->id,
            'author_id'   => $user->id,
        ]);

        $step = Procedure_Step::factory()->create([
            'procedure_id' => $laufend->id,
            'position_id'  => $position->id,
            'parent'       => null,
            'sort_order'   => 0,
            'durationDays' => 5,
            'endDate'      => now()->addDays(5),
            'done'         => false,
        ]);
        $step->users()->attach($user->id);

        return compact('laufend', 'position', 'step', 'kategorie');
    }

    // ─── Legacy-URL Redirects ─────────────────────────────────────────────────

    public function test_get_template_leitet_weiter(): void
    {
        $this->adminUser();

        $response = $this->get('/procedure/template');

        $response->assertRedirect('/procedure#templates');
    }

    public function test_get_recurring_leitet_weiter(): void
    {
        $this->adminUser();

        $response = $this->get('/procedure/recurring');

        $response->assertRedirect('/procedure#automation');
    }

    public function test_get_positions_leitet_weiter(): void
    {
        $this->adminUser();

        $response = $this->get('/procedure/positions');

        $response->assertRedirect('/procedure#automation');
    }

    // ─── GET-Mutation /ends → nicht mehr verfügbar ───────────────────────────

    public function test_get_ends_gibts_nicht_mehr(): void
    {
        $user = $this->adminUser();
        ['laufend' => $laufend] = $this->erstelleGestarteten($user);

        $response = $this->get("/procedure/{$laufend->id}/ends");

        // Route wurde entfernt → 404
        $response->assertStatus(404);
    }

    // ─── POST /end beendet den Prozess ───────────────────────────────────────

    public function test_post_end_beendet_prozess(): void
    {
        Mail::fake();
        $user = $this->adminUser();
        ['laufend' => $laufend, 'step' => $step] = $this->erstelleGestarteten($user);

        $response = $this->post("/procedure/{$laufend->id}/end");

        $response->assertRedirect();
        $response->assertSessionHas('Meldung');

        $this->assertDatabaseHas('procedures', [
            'id'      => $laufend->id,
        ]);

        // ended_at wurde gesetzt
        $laufend->refresh();
        $this->assertNotNull($laufend->ended_at);
    }

    public function test_post_end_ohne_permission_abgelehnt(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->givePermissionTo('view assigned procedures');

        $admin = User::factory()->create();
        $admin->givePermissionTo('manage procedures');

        $kategorie = Procedure_Category::factory()->create();
        $position  = Positions::factory()->create();
        $laufend = Procedure::factory()->gestartet()->create([
            'category_id' => $kategorie->id,
            'author_id'   => $admin->id,
        ]);

        $response = $this->post("/procedure/{$laufend->id}/end");

        $response->assertRedirect();
        $response->assertSessionHas('type', 'danger');
    }

    // ─── GET /stepMail Route entfernt ────────────────────────────────────────

    public function test_get_stepmail_route_entfernt(): void
    {
        $this->adminUser();

        $response = $this->get('/procedure/stepMail');

        // Kein GET-Route mehr – Laravel gibt 405 (DELETE-Wildcard matcht den URL-Segment)
        // oder 404 je nach Route-Binding-Ergebnis. Beides ist akzeptabel.
        $this->assertContains($response->getStatusCode(), [404, 405],
            'GET /procedure/stepMail darf nicht mehr aufrufbar sein (Route entfernt)');
    }

    // ─── GET /positions/remove → jetzt DELETE ────────────────────────────────

    public function test_get_positions_remove_methode_nicht_erlaubt(): void
    {
        $user = $this->adminUser();
        $position = Positions::factory()->create();

        // GET-Mutation ist weg – GET auf diese URL gibt 405 oder 404
        $response = $this->get("/procedure/positions/{$position->id}/remove/{$user->id}");

        $this->assertContains($response->getStatusCode(), [404, 405]);
    }

    // ─── ViewServiceProvider: dashboardCard-v2 Composer noch aktiv ───────────

    public function test_dashboard_card_v2_composer_ist_registriert(): void
    {
        $user = $this->adminUser();
        $position = Positions::factory()->create();

        $position2 = Positions::factory()->create();
        $prozess = Procedure::factory()->gestartet()->create(['author_id' => $user->id]);
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position2->id,
            'done'         => false,
            'endDate'      => now()->addDays(2),
        ]);
        $step->users()->attach($user->id);

        // Composer direkt aufrufen (wie ProcedureDashboardCardTest)
        $composer = new \App\View\Composers\ProcedureComposer();
        $view     = view('procedure.dashboardCard-v2');
        $composer->compose($view);

        $this->assertArrayHasKey('steps', $view->getData(),
            'ProcedureComposer muss $steps in procedure.dashboardCard-v2 injizieren');
    }
}

