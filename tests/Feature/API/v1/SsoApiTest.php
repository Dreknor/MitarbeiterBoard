<?php

namespace Tests\Feature\API\v1;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Spatie\Permission\Models\Permission;

/**
 * B2 – SSO-Login der App über den bestehenden Keycloak-Login (PKCE zwischen App und Backend).
 */
class SsoApiTest extends ApiTestCase
{
    private string $verifier;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.keycloak.client_id' => 'mitarbeiterboard',
            'services.keycloak.base_url' => 'https://sso.schule.test',
            'services.keycloak.realms' => 'ucs',
        ]);

        $this->verifier = Str::random(64);
        $this->user = User::factory()->create(['email' => 'lehrerin@schule.test', 'username' => 'l.lehrerin']);
        Permission::findOrCreate('view paed diary', 'web');
        $this->user->givePermissionTo('view paed diary');
    }

    private function challenge(?string $verifier = null): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier ?? $this->verifier, true)), '+/', '-_'), '=');
    }

    private function mockKeycloak(?User $user = null, bool $fail = false): void
    {
        $user ??= $this->user;
        $socialiteUser = (new SocialiteUser())
            ->setRaw(['memberof' => []])
            ->map(['nickname' => $user->username, 'email' => $user->email]);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://sso.schule.test/auth'));
        $fail
            ? $provider->shouldReceive('user')->andThrow(new \Exception('invalid_state'))
            : $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }

    private function start(array $overrides = [])
    {
        return $this->get(self::API . '/auth/sso/start?' . http_build_query(array_merge([
            'redirect_uri' => 'paeddiary://auth',
            'code_challenge' => $this->challenge(),
            'code_challenge_method' => 'S256',
            'state' => 'abc123',
        ], $overrides)));
    }

    /** @return array<string, string> Query-Parameter des App-Redirects */
    private function callbackToApp(): array
    {
        $response = $this->get('/auth/callback')->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('paeddiary://auth?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private function exchange(string $code, ?string $verifier = null)
    {
        return $this->postJson(self::API . '/auth/sso/exchange', [
            'code' => $code,
            'code_verifier' => $verifier ?? $this->verifier,
            'device_name' => 'iPad Klasse 4a',
        ]);
    }

    /** @test */
    public function kompletter_ablauf_liefert_token_wie_passwort_login(): void
    {
        $this->mockKeycloak();

        $this->start()->assertRedirect('https://sso.schule.test/auth');

        $query = $this->callbackToApp();
        $this->assertSame('abc123', $query['state']);
        $this->assertNotEmpty($query['code']);
        $this->assertArrayNotHasKey('error', $query);

        // Kein Web-Login für den App-Fall
        $this->assertGuest();

        $this->exchange($query['code'])
            ->assertCreated()
            ->assertJsonStructure(['token_type', 'access_token', 'expires_at', 'user' => ['id', 'name', 'email', 'permissions']])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $this->user->id);

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $this->user->id, 'name' => 'iPad Klasse 4a']);

        // Code ist nur einmal verwendbar
        $this->exchange($query['code'])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /** @test */
    public function falscher_verifier_liefert_422_und_verbraucht_den_code(): void
    {
        $this->mockKeycloak();
        $this->start();
        $query = $this->callbackToApp();

        $this->exchange($query['code'], Str::random(64))->assertStatus(422)->assertJsonValidationErrors('code_verifier');
        $this->exchange($query['code'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function abgelaufener_code_liefert_422(): void
    {
        $this->mockKeycloak();
        $this->start();
        $query = $this->callbackToApp();

        $this->travel(61)->seconds();

        $this->exchange($query['code'])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /** @test */
    public function nicht_erlaubte_redirect_uri_und_fehlende_pkce_liefern_422(): void
    {
        $this->mockKeycloak();

        $this->start(['redirect_uri' => 'https://evil.test/callback'])
            ->assertStatus(422)->assertJsonValidationErrors('redirect_uri');
        $this->start(['code_challenge_method' => 'plain'])
            ->assertStatus(422)->assertJsonValidationErrors('code_challenge_method');
        $this->start(['code_challenge' => 'zu-kurz'])
            ->assertStatus(422)->assertJsonValidationErrors('code_challenge');
    }

    /** @test */
    public function ohne_keycloak_konfiguration_404(): void
    {
        config(['services.keycloak.client_id' => null]);

        $this->start()->assertNotFound()->assertJsonStructure(['message']);
    }

    /** @test */
    public function benutzer_ohne_tagebuchrecht_erhaelt_403_beim_tausch(): void
    {
        $ohneRecht = User::factory()->create(['username' => 'ohne.recht']);
        $this->mockKeycloak($ohneRecht);
        $this->start();
        $query = $this->callbackToApp();

        $this->exchange($query['code'])->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function keycloak_fehler_leitet_mit_error_in_die_app(): void
    {
        $this->mockKeycloak(fail: true);
        $this->start();

        $query = $this->callbackToApp();
        $this->assertSame('sso_failed', $query['error']);
        $this->assertSame('abc123', $query['state']);
        $this->assertArrayNotHasKey('code', $query);
    }

    /** @test */
    public function normaler_web_login_bleibt_unveraendert(): void
    {
        $this->mockKeycloak();

        $this->get('/auth/redirect')->assertRedirect('https://sso.schule.test/auth');
        $this->get('/auth/callback')->assertRedirect(url('/'));

        $this->assertAuthenticatedAs($this->user);
    }

    /** @test */
    public function abgebrochener_app_login_beeinflusst_spaeteren_web_login_nicht(): void
    {
        $this->mockKeycloak();

        $this->start();                   // App-Login begonnen, aber nicht abgeschlossen
        $this->get('/auth/redirect');     // danach normaler Web-Login im selben Browser
        $this->get('/auth/callback')->assertRedirect(url('/'));

        $this->assertAuthenticatedAs($this->user);
    }

    /** @test */
    public function exchange_hat_rate_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->exchange('unbekannt')->assertStatus(422);
        }

        $this->exchange('unbekannt')->assertStatus(429);
    }
}
