<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use OwenIt\Auditing\Resolvers\UserResolver;
use Tests\TestCase;

/**
 * CVE-2026-114509 (GHSA-f9x3-7vq9-cph6): Illuminate\Auth\TokenGuard akzeptiert
 * nicht-String-Credentials. Laravel 10 erhält dafür keinen Fix mehr, deshalb
 * darf kein Guard mit dem Treiber "token" konfiguriert sein.
 */
class TokenGuardCveTest extends TestCase
{
    public function test_kein_guard_nutzt_den_token_treiber(): void
    {
        foreach (config('auth.guards') as $name => $guard) {
            $this->assertNotSame('token', $guard['driver'] ?? null, "Guard '{$name}' nutzt den Treiber 'token'.");
        }
    }

    public function test_audit_resolver_nutzt_nur_vorhandene_guards(): void
    {
        $guards = array_keys(config('auth.guards'));

        foreach (config('audit.user.guards') as $guard) {
            $this->assertTrue(
                in_array($guard, $guards, true) || $guard === 'sanctum',
                "Audit-Guard '{$guard}' ist nicht konfiguriert."
            );
        }
    }

    public function test_audit_resolver_ordnet_sanctum_token_dem_benutzer_zu(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->app['request']->headers->set('Authorization', 'Bearer '.$token);

        $this->assertTrue($user->is(UserResolver::resolve()));
    }

    public function test_audit_resolver_ignoriert_manipulierte_api_token(): void
    {
        User::factory()->create();

        $this->app['request']->merge(['api_token' => true]);
        $this->app['request']->headers->set('Authorization', 'Bearer 0');

        $this->assertNull(UserResolver::resolve());
    }
}
