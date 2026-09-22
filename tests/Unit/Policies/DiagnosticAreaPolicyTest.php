<?php

namespace Tests\Unit\Policies;

use App\Models\DiagnosticArea;
use App\Models\GradingDocumentationSession;
use App\Models\Klasse;
use App\Models\User;
use App\Policies\DiagnosticAreaPolicy;
use App\Policies\GradingDocumentationSessionPolicy;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests für DiagnosticAreaPolicy und GradingDocumentationSessionPolicy.
 *
 * @see \App\Policies\DiagnosticAreaPolicy
 * @see \App\Policies\GradingDocumentationSessionPolicy
 */
class DiagnosticAreaPolicyTest extends TestCase
{
    private DiagnosticAreaPolicy $areaPolicy;
    private GradingDocumentationSessionPolicy $sessionPolicy;
    private DiagnosticArea $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->areaPolicy    = new DiagnosticAreaPolicy();
        $this->sessionPolicy = new GradingDocumentationSessionPolicy();

        Permission::findOrCreate('view diagnostics');
        Permission::findOrCreate('manage diagnostics');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->area = DiagnosticArea::factory()->create();
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    private function userWithViewPerm(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view diagnostics');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        return $user;
    }

    private function userWithManagePerm(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage diagnostics');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        return $user;
    }

    private function userWithNoPerm(): User
    {
        return User::factory()->create();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DiagnosticAreaPolicy
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_area_viewAny_erfordert_manage_permission(): void
    {
        $this->assertTrue($this->areaPolicy->viewAny($this->userWithManagePerm()));
        $this->assertFalse($this->areaPolicy->viewAny($this->userWithViewPerm()));
        $this->assertFalse($this->areaPolicy->viewAny($this->userWithNoPerm()));
    }

    public function test_area_view_erlaubt_mit_view_oder_manage_permission(): void
    {
        $this->assertTrue($this->areaPolicy->view($this->userWithViewPerm(),   $this->area));
        $this->assertTrue($this->areaPolicy->view($this->userWithManagePerm(), $this->area));
        $this->assertFalse($this->areaPolicy->view($this->userWithNoPerm(),    $this->area));
    }

    public function test_area_viewArea_erlaubt_mit_view_oder_manage_permission(): void
    {
        $this->assertTrue($this->areaPolicy->viewArea($this->userWithViewPerm(),   $this->area));
        $this->assertTrue($this->areaPolicy->viewArea($this->userWithManagePerm(), $this->area));
        $this->assertFalse($this->areaPolicy->viewArea($this->userWithNoPerm(),    $this->area));
    }

    public function test_area_create_erfordert_manage_permission(): void
    {
        $this->assertTrue($this->areaPolicy->create($this->userWithManagePerm()));
        $this->assertFalse($this->areaPolicy->create($this->userWithViewPerm()));
        $this->assertFalse($this->areaPolicy->create($this->userWithNoPerm()));
    }

    public function test_area_update_erfordert_manage_permission(): void
    {
        $this->assertTrue($this->areaPolicy->update($this->userWithManagePerm(), $this->area));
        $this->assertFalse($this->areaPolicy->update($this->userWithViewPerm(),  $this->area));
    }

    public function test_area_delete_erfordert_manage_permission(): void
    {
        $this->assertTrue($this->areaPolicy->delete($this->userWithManagePerm(), $this->area));
        $this->assertFalse($this->areaPolicy->delete($this->userWithViewPerm(),  $this->area));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GradingDocumentationSessionPolicy
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_grading_session_view_erlaubt_fuer_ersteller(): void
    {
        $owner   = User::factory()->create();
        $klasse  = Klasse::factory()->create();
        $session = GradingDocumentationSession::factory()->create([
            'user_id'   => $owner->id,
            'klasse_id' => $klasse->id,
        ]);

        $this->assertTrue($this->sessionPolicy->view($owner, $session));
    }

    public function test_grading_session_view_erlaubt_wenn_klasse_zugeordnet(): void
    {
        $owner   = User::factory()->create();
        $klasse  = Klasse::factory()->create();
        $session = GradingDocumentationSession::factory()->create([
            'user_id'   => $owner->id,
            'klasse_id' => $klasse->id,
        ]);

        $andererUser = User::factory()->create();
        $andererUser->paed_klassen()->attach($klasse->id);

        $this->assertTrue($this->sessionPolicy->view($andererUser, $session));
    }

    public function test_grading_session_view_verweigert_ohne_klasse_und_nicht_ersteller(): void
    {
        $owner   = User::factory()->create();
        $klasse  = Klasse::factory()->create();
        $session = GradingDocumentationSession::factory()->create([
            'user_id'   => $owner->id,
            'klasse_id' => $klasse->id,
        ]);

        $fremderUser = User::factory()->create(); // keine Klasse zugeordnet

        $this->assertFalse($this->sessionPolicy->view($fremderUser, $session));
    }

    public function test_grading_session_update_nur_fuer_ersteller(): void
    {
        $owner   = User::factory()->create();
        $klasse  = Klasse::factory()->create();
        $session = GradingDocumentationSession::factory()->create([
            'user_id'   => $owner->id,
            'klasse_id' => $klasse->id,
        ]);

        // Anderer User mit Klassen-Zugriff darf NICHT updaten
        $andererUser = User::factory()->create();
        $andererUser->paed_klassen()->attach($klasse->id);

        $this->assertTrue($this->sessionPolicy->update($owner, $session));
        $this->assertFalse($this->sessionPolicy->update($andererUser, $session));
    }
}

