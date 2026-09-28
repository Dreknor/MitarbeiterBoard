<?php

namespace Tests\Feature\API\v1;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class AuthApiTest extends ApiTestCase
{
    private function userWithPassword(bool $withPermission = true): User
    {
        $user = User::factory()->create(['password' => Hash::make('geheim123')]);
        if ($withPermission) {
            Permission::findOrCreate('view paed diary', 'web');
            $user->givePermissionTo('view paed diary');
        }

        return $user;
    }

    /** @test */
    public function token_wird_mit_gueltigen_zugangsdaten_ausgestellt(): void
    {
        $user = $this->userWithPassword();

        $response = $this->postJson(self::API . '/auth/token', [
            'email' => $user->email,
            'password' => 'geheim123',
            'device_name' => 'iPad Klasse 4a',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token_type', 'access_token', 'expires_at', 'user' => ['id', 'name', 'permissions']])
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'iPad Klasse 4a']);
    }

    /** @test */
    public function falsches_passwort_liefert_422(): void
    {
        $user = $this->userWithPassword();

        $this->postJson(self::API . '/auth/token', [
            'email' => $user->email,
            'password' => 'falsch',
            'device_name' => 'Test',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    /** @test */
    public function benutzer_ohne_tagebuch_recht_erhaelt_kein_token(): void
    {
        $user = $this->userWithPassword(false);

        $this->postJson(self::API . '/auth/token', [
            'email' => $user->email,
            'password' => 'geheim123',
            'device_name' => 'Test',
        ])->assertForbidden();
    }

    /** @test */
    public function ohne_token_liefert_die_api_401_als_json(): void
    {
        // Bewusst ohne Accept-Header: Die API muss trotzdem JSON liefern
        $this->get(self::API . '/classes')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Nicht authentifiziert.');
    }

    /** @test */
    public function bearer_token_authentifiziert_und_kann_widerrufen_werden(): void
    {
        $user = $this->userWithPassword();
        $token = $user->createToken('Test', ['paed-app'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(self::API . '/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.permissions.view_all_students', false);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson(self::API . '/auth/token')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Auth-Guard-Cache zurücksetzen, damit der nächste Request neu authentifiziert
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(self::API . '/auth/me')
            ->assertUnauthorized();
    }

    /** @test */
    public function authentifizierter_benutzer_ohne_modulrecht_erhaelt_403(): void
    {
        $this->actingAsTeacher([]);

        $this->getJson(self::API . '/classes')->assertForbidden();
    }
}
