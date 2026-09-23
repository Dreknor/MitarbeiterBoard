<?php

namespace Tests\Feature\API\v1;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * B1 – Instanz-Info für die Serverwahl der App.
 */
class InstanceApiTest extends ApiTestCase
{
    /** @test */
    public function instanz_info_ohne_token_liefert_schema(): void
    {
        config([
            'app.logo' => 'logo.png',
            'services.keycloak.client_id' => 'mitarbeiterboard',
            'services.keycloak.base_url' => 'https://sso.schule.test',
            'services.keycloak.realms' => 'ucs',
        ]);
        Setting::create(['module' => 'paed_app', 'setting' => 'school_name', 'setting_name' => 'Schulname', 'type' => 'string', 'value' => 'Grundschule Musterstadt']);

        $response = $this->getJson(self::API . '/instance')
            ->assertOk()
            ->assertExactJson([
                'name' => 'Grundschule Musterstadt',
                'logo_url' => asset('img/logo.png'),
                'primary_color' => config('paed_app.primary_color'),
                'api_version' => '1.1.0',
                'min_app_version' => config('paed_app.min_app_version'),
                'auth' => ['password' => true, 'sso' => true, 'sso_label' => config('paed_app.sso_label')],
            ]);

        // Keine personenbezogenen Daten
        $this->assertStringNotContainsString('@', json_encode($response->json()));
    }

    /** @test */
    public function sso_und_passwort_login_sind_abschaltbar(): void
    {
        config([
            'services.keycloak.client_id' => null,
            'paed_app.password_login' => false,
        ]);

        $this->getJson(self::API . '/instance')
            ->assertOk()
            ->assertJsonPath('auth.sso', false)
            ->assertJsonPath('auth.password', false);

        // Abgeschalteter Passwort-Login greift auch beim Token-Endpunkt
        $user = User::factory()->create(['password' => Hash::make('geheim123')]);
        Permission::findOrCreate('view paed diary', 'web');
        $user->givePermissionTo('view paed diary');

        $this->postJson(self::API . '/auth/token', [
            'email' => $user->email, 'password' => 'geheim123', 'device_name' => 'iPad',
        ])->assertForbidden()->assertJsonStructure(['message']);
    }

    /** @test */
    public function instanz_info_hat_rate_limit(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson(self::API . '/instance')->assertOk();
        }

        $this->getJson(self::API . '/instance')->assertStatus(429);
    }

    /** @test */
    public function profil_zeigt_qr_code_zum_verbinden(): void
    {
        $this->withoutVite();
        $this->actingAsWithPermission('view paed diary');

        $this->get('/mein-profil')
            ->assertOk()
            ->assertSee('App verbinden')
            ->assertSee('Pädagogen-App')
            ->assertSee('<svg', false)
            ->assertSee(rtrim(url('/'), '/'));
    }

    /** @test */
    public function profil_ohne_tagebuchrecht_zeigt_keine_app_kachel(): void
    {
        $this->withoutVite();
        $this->actingAsWithPermission();

        $this->get('/mein-profil')->assertOk()->assertDontSee('App verbinden');
    }

    /** @test */
    public function connect_url_zeigt_auf_diesen_server(): void
    {
        $this->assertSame(
            'paeddiary://connect?server=' . rtrim(url('/'), '/'),
            app(\App\Services\Api\PaedAppService::class)->connectUrl()
        );
    }
}
