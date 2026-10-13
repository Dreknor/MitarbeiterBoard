<?php

namespace Tests\Feature\API\v1;

use App\Models\User;
use App\Services\Api\ApiTokenService;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;

/**
 * Mein Profil → Anwendungstokens (API): erstellen, nutzen, widerrufen.
 */
class ApiTokenProfileTest extends ApiTestCase
{
    private function tokenUser(array $permissions = ['view paed diary', 'create api tokens']): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function bearer(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /** @test */
    public function token_wird_erstellt_einmalig_angezeigt_und_funktioniert_fuer_die_api(): void
    {
        $user = $this->tokenUser();

        $response = $this->actingAs($user)
            ->from(route('self-service.index'))
            ->post(route('self-service.api-tokens.store'), ['name' => 'Export-Skript', 'laufzeit' => 30]);

        $response->assertRedirect(route('self-service.index') . '#api-tokens')
            ->assertSessionHas('apiTokenPlain')
            ->assertSessionHas('apiTokenName', 'Export-Skript');

        $plain = session('apiTokenPlain');
        $model = PersonalAccessToken::findToken($plain);

        $this->assertTrue($model->tokenable->is($user));
        $this->assertSame([ApiTokenService::API_TOKEN_ABILITY], $model->abilities);
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $model->expires_at->getTimestamp(), 5);

        $this->bearer($plain)->getJson(self::API . '/auth/me')->assertOk();
    }

    /** @test */
    public function anwendungstoken_wird_bei_nutzung_nicht_verlaengert_und_laeuft_ab(): void
    {
        $user = $this->tokenUser();
        $plain = app(ApiTokenService::class)->issueApiToken($user, 'Skript', 30)->plainTextToken;
        $model = PersonalAccessToken::findToken($plain);
        $expires = $model->expires_at->getTimestamp();

        $this->travel(10)->days();
        $this->bearer($plain)->getJson(self::API . '/auth/me')->assertOk();
        $this->assertSame($expires, $model->fresh()->expires_at->getTimestamp());

        $this->travel(21)->days();
        $this->bearer($plain)->getJson(self::API . '/auth/me')->assertUnauthorized();
    }

    /** @test */
    public function ohne_recht_kein_token_und_ungueltige_laufzeit_wird_abgelehnt(): void
    {
        $ohneRecht = $this->tokenUser(['view paed diary']);
        $this->actingAs($ohneRecht)
            ->post(route('self-service.api-tokens.store'), ['name' => 'X', 'laufzeit' => 30])
            ->assertForbidden();

        $user = $this->tokenUser();
        $this->actingAs($user)
            ->post(route('self-service.api-tokens.store'), ['name' => 'X', 'laufzeit' => 9999])
            ->assertSessionHasErrors('laufzeit', null, 'apiToken');

        $this->assertSame(0, PersonalAccessToken::count());
    }

    /** @test */
    public function profil_zeigt_tokens_getrennt_von_app_geraeten_und_widerruft_nur_eigene(): void
    {
        $user = $this->tokenUser();
        $user->createToken('iPad', [ApiTokenService::ABILITY]);
        $apiToken = app(ApiTokenService::class)->issueApiToken($user, 'Mein Skript', 90)->accessToken;

        $this->actingAs($user)->get(route('self-service.index'))
            ->assertOk()
            ->assertSee('Anwendungstokens (API)')
            ->assertSee('Mein Skript')
            ->assertSee('iPad');

        // API-Geräteliste enthält keine Anwendungstokens
        $appToken = $user->createToken('iPhone', [ApiTokenService::ABILITY])->plainTextToken;
        $names = $this->bearer($appToken)->getJson(self::API . '/auth/devices')->assertOk()->json('data.*.device_name');
        $this->assertEqualsCanonicalizing(['iPad', 'iPhone'], $names);

        // Fremde Tokens können nicht widerrufen werden
        $fremd = $this->tokenUser();
        $this->app['auth']->forgetGuards();
        $this->actingAs($fremd)->delete(route('self-service.app-devices.destroy', $apiToken->id))
            ->assertSessionHas('type', 'warning');
        $this->assertNotNull($apiToken->fresh());

        $this->actingAs($user)->delete(route('self-service.app-devices.destroy', $apiToken->id))
            ->assertSessionHas('Meldung', 'Das Anwendungstoken wurde widerrufen.');
        $this->assertNull($apiToken->fresh());
    }

    /** @test */
    public function eigene_daten_zeigen_die_token_karte(): void
    {
        $user = $this->tokenUser(['create api tokens']);

        $this->actingAs($user)->get(route('employes.self'))
            ->assertOk()
            ->assertSee('Anwendungstokens (API)')
            ->assertDontSee('Meine App-Geräte');

        // Ohne App-Recht heißt der Profil-Tab nur „API“
        $this->actingAs($user)->get(route('self-service.index'))
            ->assertOk()
            ->assertSee('Anwendungstokens (API)')
            ->assertDontSee('Pädagogen-App &amp; API', false);
    }
}
