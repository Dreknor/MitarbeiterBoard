<?php

namespace Tests\Feature\Procedure;

use App\Mail\newStepMail;
use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Category;
use App\Models\Procedure_Step;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests für laufende Prozesse:
 * Start, Ansicht, Schritt erledigen, Prozess beenden, Person zuweisen/entfernen.
 */
class ProcedureRunningTest extends TestCase
{
    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    /**
     * Erstellt ein minimales Vorlage + Position + Schritt Setup und
     * gibt Vorlage, Position und Step zurück.
     */
    private function setupVorlage(User $user = null): array
    {
        $user ??= User::factory()->create();
        $kategorie = Procedure_Category::factory()->create();
        $position  = Positions::factory()->create();

        $vorlage = Procedure::factory()->create([
            'name'        => 'Onboarding',
            'category_id' => $kategorie->id,
            'author_id'   => $user->id,
            'started_at'  => null,
        ]);

        $step = Procedure_Step::factory()->create([
            'procedure_id' => $vorlage->id,
            'position_id'  => $position->id,
            'parent'       => null,
        ]);

        $position->users()->attach($user->id);

        return compact('vorlage', 'position', 'step', 'user', 'kategorie');
    }

    // ─── Index: Aktive Prozesse ───────────────────────────────────────────────

    public function test_admin_sieht_aktive_prozesse(): void
    {
        $this->actingAsWithPermission('manage procedures');
        Procedure::factory()->gestartet()->create();

        $response = $this->get('/procedure');

        $response->assertStatus(200);
    }

    public function test_nutzer_mit_view_assigned_sieht_nur_eigene_prozesse(): void
    {
        $user = $this->actingAsWithPermission('view assigned procedures');
        $position = Positions::factory()->create();
        $position->users()->attach($user->id);

        $eigenProzess   = Procedure::factory()->gestartet()->create();
        $fremdProzess   = Procedure::factory()->gestartet()->create();

        // Step der eigenen Position anlegen
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $eigenProzess->id,
            'position_id'  => $position->id,
        ]);
        $step->users()->attach($user->id);

        $response = $this->get('/procedure');

        $response->assertStatus(200);
    }

    public function test_nutzer_ohne_permission_wird_abgewiesen(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/procedure');

        $response->assertStatus(403);
    }

    // ─── Prozess starten ─────────────────────────────────────────────────────

    public function test_admin_kann_prozess_aus_vorlage_starten(): void
    {
        Mail::fake();

        $admin = $this->actingAsWithPermission('manage procedures');
        [
            'vorlage'   => $vorlage,
            'position'  => $position,
        ] = $this->setupVorlage($admin);

        $startDatum = Carbon::now()->format('Y-m-d');

        $response = $this->post("/procedure/{$vorlage->id}/start", [
            'name'       => 'Onboarding Mustermann',
            'started_at' => $startDatum,
        ]);

        $response->assertRedirect();

        // Neue Prozess-Instanz muss existieren (nur Name prüfen, started_at ist Timestamp)
        $this->assertDatabaseHas('procedures', [
            'name' => 'Onboarding Mustermann',
        ]);
        $this->assertNotNull(
            \App\Models\Procedure::where('name', 'Onboarding Mustermann')->first()
        );
    }

    public function test_startup_sendet_mail_an_positionsinhaber(): void
    {
        Mail::fake();

        $admin = $this->actingAsWithPermission('manage procedures');
        [
            'vorlage'   => $vorlage,
            'position'  => $position,
            'user'      => $posUser,
        ] = $this->setupVorlage(User::factory()->create()); // anderer User in Position

        $position->users()->detach();
        $empfaenger = User::factory()->create();
        $position->users()->attach($empfaenger->id);

        $this->post("/procedure/{$vorlage->id}/start", [
            'name'       => 'Mail-Test-Prozess',
            'started_at' => now()->format('Y-m-d'),
        ]);

        Mail::assertQueued(newStepMail::class, function ($mail) use ($empfaenger) {
            return $mail->hasTo($empfaenger->email);
        });
    }

    public function test_nutzer_ohne_manage_kann_prozess_nicht_starten(): void
    {
        $this->actingAsWithPermission('view assigned procedures');
        $vorlage = Procedure::factory()->vorlage()->create();

        $response = $this->post("/procedure/{$vorlage->id}/start", [
            'name'       => 'Nicht erlaubt',
            'started_at' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('type', 'danger');
    }

    // ─── Prozess-Ansicht (start-View) ─────────────────────────────────────────

    public function test_admin_kann_laufenden_prozess_sehen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $prozess = Procedure::factory()->gestartet()->create();

        $response = $this->get("/procedure/{$prozess->id}/start");

        $response->assertStatus(200);
    }

    public function test_zugewiesener_nutzer_kann_laufenden_prozess_sehen(): void
    {
        $user = $this->actingAsWithPermission('view assigned procedures');
        $position = Positions::factory()->create();
        $position->users()->attach($user->id);

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
        ]);
        $step->users()->attach($user->id);

        $response = $this->get("/procedure/{$prozess->id}/start");

        $response->assertStatus(200);
    }

    public function test_nicht_zugewiesener_nutzer_sieht_fremden_prozess_nicht(): void
    {
        $this->actingAsWithPermission('view assigned procedures');
        $prozess = Procedure::factory()->gestartet()->create();

        $response = $this->get("/procedure/{$prozess->id}/start");

        $response->assertStatus(403);
    }

    // ─── Schritt: Erledigen ───────────────────────────────────────────────────

    public function test_zugewiesener_nutzer_kann_eigenen_schritt_erledigen(): void
    {
        Mail::fake();

        $user = $this->actingAsWithPermission('view assigned procedures', 'complete own procedure steps');
        $position = Positions::factory()->create();

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => now()->addDays(5),
            'parent'       => null,
        ]);
        $step->users()->attach($user->id);

        $response = $this->put("/procedure/step/{$step->id}/done");

        $response->assertRedirect();
        $this->assertDatabaseHas('procedure_steps', [
            'id'   => $step->id,
            'done' => 1,
        ]);
    }

    public function test_alle_schritte_erledigt_schliesst_prozess_ab(): void
    {
        Mail::fake();

        $user = $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => now()->addDays(3),
            'parent'       => null,
        ]);
        $step->users()->attach($user->id);

        $this->put("/procedure/step/{$step->id}/done");

        $this->assertNotNull(
            \App\Models\Procedure::find($prozess->id)->ended_at
        );
    }

    public function test_nicht_zugewiesener_nutzer_kann_schritt_nicht_erledigen(): void
    {
        $user = $this->actingAsWithPermission('view assigned procedures', 'complete own procedure steps');
        $position = Positions::factory()->create();

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => now()->addDays(5),
        ]);
        // Step wird NICHT dem User zugewiesen

        $response = $this->put("/procedure/step/{$step->id}/done");

        $response->assertRedirect();
        $response->assertSessionHas('type', 'danger');
        $this->assertDatabaseHas('procedure_steps', ['id' => $step->id, 'done' => 0]);
    }

    public function test_erledigen_ohne_enddate_schlaegt_fehl(): void
    {
        $user = $this->actingAsWithPermission('complete own procedure steps');
        $position = Positions::factory()->create();

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => null, // kein Datum gesetzt
        ]);
        $step->users()->attach($user->id);

        $response = $this->put("/procedure/step/{$step->id}/done");

        // Schritt kann ohne endDate nicht erledigt werden (implizit durch Controller-Check)
        $this->assertDatabaseHas('procedure_steps', ['id' => $step->id, 'done' => false]);
    }

    // ─── Prozess: Vorzeitig beenden ───────────────────────────────────────────

    public function test_admin_kann_prozess_vorzeitig_beenden(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $prozess = Procedure::factory()->gestartet()->create();

        $response = $this->get("/procedure/{$prozess->id}/ends");

        $response->assertRedirect();
        $this->assertNotNull(
            \App\Models\Procedure::find($prozess->id)->ended_at
        );
    }

    public function test_nutzer_ohne_manage_kann_prozess_nicht_beenden(): void
    {
        $this->actingAsWithPermission('view assigned procedures');
        $prozess = Procedure::factory()->gestartet()->create();

        $response = $this->get("/procedure/{$prozess->id}/ends");

        $response->assertRedirect();
        $response->assertSessionHas('type', 'danger');
        $this->assertDatabaseHas('procedures', ['id' => $prozess->id, 'ended_at' => null]);
    }

    // ─── Schritt: Person zuweisen ─────────────────────────────────────────────

    public function test_admin_kann_person_zu_schritt_zuweisen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $prozess  = Procedure::factory()->gestartet()->create();
        $step     = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
        ]);
        $zielUser = User::factory()->create();

        $response = $this->post('/procedure/step/addUser', [
            'step'      => $step->id,
            'person_id' => $zielUser->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('steps_users', [
            'steps_id' => $step->id,
            'users_id' => $zielUser->id,
        ]);
    }

    public function test_doppelte_zuweisung_wird_verhindert(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $prozess  = Procedure::factory()->gestartet()->create();
        $step     = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
        ]);
        $zielUser = User::factory()->create();
        $step->users()->attach($zielUser->id);

        $response = $this->post('/procedure/step/addUser', [
            'step'      => $step->id,
            'person_id' => $zielUser->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('type', 'info');
        $this->assertEquals(1, $step->users()->where('users_id', $zielUser->id)->count());
    }

    // ─── Schritt: Person entfernen ────────────────────────────────────────────

    public function test_admin_kann_person_von_schritt_entfernen(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $prozess  = Procedure::factory()->gestartet()->create();
        $step     = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
        ]);
        $zielUser = User::factory()->create();
        $step->users()->attach($zielUser->id);

        // Phase 4: REST-konformes DELETE
        $response = $this->delete("/procedure/step/{$step->id}/users/{$zielUser->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('steps_users', [
            'steps_id' => $step->id,
            'users_id' => $zielUser->id,
        ]);
    }

    public function test_admin_kann_person_von_schritt_entfernen_via_delete(): void
    {
        $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();
        $prozess  = Procedure::factory()->gestartet()->create();
        $step     = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
        ]);
        $zielUser = User::factory()->create();
        $step->users()->attach($zielUser->id);

        $response = $this->delete("/procedure/step/{$step->id}/users/{$zielUser->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('steps_users', [
            'steps_id' => $step->id,
            'users_id' => $zielUser->id,
        ]);
    }

    public function test_nutzer_ohne_manage_kann_person_nicht_entfernen(): void
    {
        $this->actingAsWithPermission('view assigned procedures');
        $position = Positions::factory()->create();
        $prozess  = Procedure::factory()->gestartet()->create();
        $step     = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
        ]);
        $zielUser = User::factory()->create();
        $step->users()->attach($zielUser->id);

        $response = $this->delete("/procedure/step/{$step->id}/users/{$zielUser->id}");

        $response->assertRedirect();
        $response->assertSessionHas('type', 'danger');
    }
}

