<?php

namespace Tests\Unit\Policies;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticSession;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use App\Policies\DiagnosticPolicy;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests für DiagnosticPolicy.
 *
 * Kritisch: stellt sicher, dass Nutzende nur auf Diagnosedaten von Schülern
 * zugreifen können, für deren Klasse sie berechtigt sind.
 *
 * @see \App\Policies\DiagnosticPolicy
 */
class DiagnosticPolicyTest extends TestCase
{
    private DiagnosticPolicy $policy;
    private Klasse $klasse;
    private Schueler $schueler;
    private DiagnosticArea $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DiagnosticPolicy();

        Permission::findOrCreate('view diagnostics');
        Permission::findOrCreate('manage diagnostics');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->klasse   = Klasse::factory()->create();
        $this->schueler = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
        $this->area     = DiagnosticArea::factory()->create();
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    private function makeUserWithDiagView(bool $withKlassenZugriff = true): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view diagnostics');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        if ($withKlassenZugriff) {
            $user->paed_klassen()->attach($this->klasse->id);
        }

        return $user;
    }

    private function makeUserWithDiagManage(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage diagnostics');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $user->paed_klassen()->attach($this->klasse->id);
        return $user;
    }

    private function makeSession(bool $completed = false): DiagnosticSession
    {
        return DiagnosticSession::factory()->create([
            'schueler_id'        => $this->schueler->id,
            'diagnostic_area_id' => $this->area->id,
            'is_completed'       => $completed,
        ]);
    }

    // ─── viewAny ─────────────────────────────────────────────────────────────

    public function test_viewAny_erlaubt_mit_view_permission(): void
    {
        $user = $this->makeUserWithDiagView();
        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_viewAny_verweigert_ohne_permission(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($this->policy->viewAny($user));
    }

    // ─── view ─────────────────────────────────────────────────────────────────

    public function test_view_erlaubt_wenn_klasse_zugeordnet(): void
    {
        $user    = $this->makeUserWithDiagView();
        $session = $this->makeSession();

        $this->assertTrue($this->policy->view($user, $session));
    }

    public function test_view_verweigert_wenn_andere_klasse(): void
    {
        $user       = $this->makeUserWithDiagView(withKlassenZugriff: false);
        $andereKlasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($andereKlasse->id);

        $session = $this->makeSession();

        $this->assertFalse($this->policy->view($user, $session));
    }

    public function test_view_verweigert_ohne_permission(): void
    {
        $user = User::factory()->create();
        $user->paed_klassen()->attach($this->klasse->id);

        $session = $this->makeSession();

        $this->assertFalse($this->policy->view($user, $session));
    }

    public function test_view_verweigert_wenn_schueler_keine_klasse(): void
    {
        $user = $this->makeUserWithDiagView();

        $schuelerOhneKlasse = Schueler::factory()->create(['klasse_id' => null]);
        $session = DiagnosticSession::factory()->create([
            'schueler_id'        => $schuelerOhneKlasse->id,
            'diagnostic_area_id' => $this->area->id,
        ]);

        $this->assertFalse($this->policy->view($user, $session));
    }

    // ─── create ───────────────────────────────────────────────────────────────

    public function test_create_erlaubt_wenn_klasse_zugeordnet(): void
    {
        $user = $this->makeUserWithDiagView();
        $this->assertTrue($this->policy->create($user, $this->schueler));
    }

    public function test_create_verweigert_ohne_klassen_zuordnung(): void
    {
        $user = $this->makeUserWithDiagView(withKlassenZugriff: false);
        $this->assertFalse($this->policy->create($user, $this->schueler));
    }

    public function test_create_verweigert_wenn_schueler_keine_klasse(): void
    {
        $user = $this->makeUserWithDiagView();
        $schuelerOhneKlasse = Schueler::factory()->create(['klasse_id' => null]);
        $this->assertFalse($this->policy->create($user, $schuelerOhneKlasse));
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_erlaubt_wenn_klasse_zugeordnet_und_nicht_abgeschlossen(): void
    {
        $user    = $this->makeUserWithDiagView();
        $session = $this->makeSession(completed: false);

        $this->assertTrue($this->policy->update($user, $session));
    }

    public function test_update_verweigert_bei_abgeschlossener_session(): void
    {
        $user    = $this->makeUserWithDiagView();
        $session = $this->makeSession(completed: true);

        $this->assertFalse($this->policy->update($user, $session));
    }

    public function test_update_verweigert_ohne_klassen_zuordnung(): void
    {
        $user    = $this->makeUserWithDiagView(withKlassenZugriff: false);
        $session = $this->makeSession();

        $this->assertFalse($this->policy->update($user, $session));
    }

    // ─── complete ─────────────────────────────────────────────────────────────

    public function test_complete_erlaubt_wenn_klasse_zugeordnet(): void
    {
        $user    = $this->makeUserWithDiagView();
        $session = $this->makeSession(completed: false);

        $this->assertTrue($this->policy->complete($user, $session));
    }

    public function test_complete_verweigert_wenn_bereits_abgeschlossen(): void
    {
        $user    = $this->makeUserWithDiagView();
        $session = $this->makeSession(completed: true);

        $this->assertFalse($this->policy->complete($user, $session));
    }

    // ─── reopen ───────────────────────────────────────────────────────────────

    public function test_reopen_erlaubt_mit_manage_permission(): void
    {
        $user    = $this->makeUserWithDiagManage();
        $session = $this->makeSession(completed: true);

        $this->assertTrue($this->policy->reopen($user, $session));
    }

    public function test_reopen_verweigert_ohne_manage_permission(): void
    {
        $user    = $this->makeUserWithDiagView(); // nur view, nicht manage
        $session = $this->makeSession(completed: true);

        $this->assertFalse($this->policy->reopen($user, $session));
    }

    // ─── delete ───────────────────────────────────────────────────────────────

    public function test_delete_erlaubt_mit_manage_permission(): void
    {
        $user    = $this->makeUserWithDiagManage();
        $session = $this->makeSession();

        $this->assertTrue($this->policy->delete($user, $session));
    }

    public function test_delete_verweigert_mit_nur_view_permission(): void
    {
        $user    = $this->makeUserWithDiagView();
        $session = $this->makeSession();

        $this->assertFalse($this->policy->delete($user, $session));
    }
}

