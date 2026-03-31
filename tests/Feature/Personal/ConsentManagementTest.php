<?php

namespace Tests\Feature\Personal;

use App\Models\personal\Consent;
use App\Models\personal\ConsentType;
use App\Models\User;
use Tests\TestCase;

class ConsentManagementTest extends TestCase
{
    /** @test */
    public function user_can_grant_consent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        // 'foto_organigramm' wird durch die Migration geseedet – kein factory()->create()!
        $type = ConsentType::where('key', 'foto_organigramm')->first();

        $this->post(route('self-service.consents.grant', $type));

        $this->assertTrue($user->fresh()->hasConsent('foto_organigramm'));
    }

    /** @test */
    public function user_can_revoke_consent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $type = ConsentType::factory()->create(['key' => 'test_revoke']);
        Consent::factory()->create([
            'employe_id'      => $user->id,
            'consent_type_id' => $type->id,
            'revoked_at'      => null,
        ]);

        $this->post(route('self-service.consents.revoke', $type));

        $this->assertFalse($user->fresh()->hasConsent('test_revoke'));
        $this->assertNotNull(
            Consent::where('employe_id', $user->id)->first()->revoked_at
        );
    }

    /** @test */
    public function has_consent_returns_false_after_revoke(): void
    {
        $user = User::factory()->create();
        $type = ConsentType::factory()->create(['key' => 'test_key']);
        Consent::factory()->create([
            'employe_id'      => $user->id,
            'consent_type_id' => $type->id,
            'revoked_at'      => now(),
        ]);

        $this->assertFalse($user->hasConsent('test_key'));
    }

    /** @test */
    public function admin_can_view_all_consents(): void
    {
        $this->actingAsWithPermission('manage personal_consents');

        $this->get(route('personal.consents.admin'))->assertStatus(200);
    }

    /** @test */
    public function user_cannot_revoke_others_consent(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $type = ConsentType::factory()->create();
        Consent::factory()->create([
            'employe_id'      => $other->id,
            'consent_type_id' => $type->id,
            'revoked_at'      => null,
        ]);

        // Widerruf via Route nutzt immer auth()->user() – gibt 404 weil kein eigener Consent
        $this->post(route('self-service.consents.revoke', $type))
            ->assertStatus(404);
    }

    /** @test */
    public function self_service_index_requires_auth(): void
    {
        $this->get(route('self-service.index'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('self-service.index'))->assertStatus(200);
    }
}

