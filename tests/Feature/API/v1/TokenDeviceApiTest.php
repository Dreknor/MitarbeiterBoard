<?php

namespace Tests\Feature\API\v1;

use App\Models\DiagnosticDevelopmentGoal;
use App\Models\PaedDiaryEntry;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;

/**
 * B6 – Token-Laufzeit, Geräteliste, Konfliktschutz, Login-Rate-Limit.
 */
class TokenDeviceApiTest extends ApiTestCase
{
    private function appUser(array $permissions = ['view paed diary']): User
    {
        $user = User::factory()->create(['password' => Hash::make('geheim123')]);
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
    public function token_laeuft_nach_90_tagen_ab_und_wird_bei_nutzung_verlaengert(): void
    {
        $user = $this->appUser();

        $response = $this->postJson(self::API . '/auth/token', [
            'email' => $user->email, 'password' => 'geheim123', 'device_name' => 'iPad',
        ])->assertCreated();
        $token = $response->json('access_token');
        $model = PersonalAccessToken::findToken($token);

        $this->assertEqualsWithDelta(now()->addDays(90)->getTimestamp(), $model->expires_at->getTimestamp(), 5);
        $this->assertSame($model->expires_at->toIso8601String(), $response->json('expires_at'));

        // Nutzung am selben Tag schreibt nicht erneut
        $this->travel(2)->hours();
        $this->bearer($token)->getJson(self::API . '/auth/me')->assertOk();
        $this->assertEqualsWithDelta(now()->subHours(2)->addDays(90)->getTimestamp(), $model->fresh()->expires_at->getTimestamp(), 5);

        // Nach mehr als einem Tag wird auf 90 Tage ab jetzt verlängert
        $this->travel(30)->days();
        $this->bearer($token)->getJson(self::API . '/auth/me')->assertOk();
        $this->assertEqualsWithDelta(now()->addDays(90)->getTimestamp(), $model->fresh()->expires_at->getTimestamp(), 5);

        // Ohne Nutzung läuft es ab → 401
        $this->travel(91)->days();
        $this->bearer($token)->getJson(self::API . '/auth/me')->assertUnauthorized();
    }

    /** @test */
    public function laufzeit_ist_konfigurierbar(): void
    {
        config(['paed_app.token_days' => 7]);
        $user = $this->appUser();

        $this->postJson(self::API . '/auth/token', [
            'email' => $user->email, 'password' => 'geheim123', 'device_name' => 'iPad',
        ])->assertCreated();

        $this->assertEqualsWithDelta(now()->addDays(7)->getTimestamp(), $user->tokens()->first()->expires_at->getTimestamp(), 5);
    }

    /** @test */
    public function geraeteliste_und_abmelden(): void
    {
        $user = $this->appUser();
        $current = $user->createToken('iPad Klasse 4a', ['paed-app'], now()->addDays(90));
        $other = $user->createToken('Privathandy', ['paed-app'], now()->addDays(90));
        $foreign = $this->appUser()->createToken('Fremd', ['paed-app'], now()->addDays(90));

        $this->bearer($current->plainTextToken)->getJson(self::API . '/auth/devices')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'device_name', 'last_used_at', 'created_at', 'is_current']]])
            ->assertJsonFragment(['device_name' => 'iPad Klasse 4a', 'is_current' => true])
            ->assertJsonFragment(['device_name' => 'Privathandy', 'is_current' => false]);

        // Fremde Tokens: 404, eigenes Gerät abmelden: 204
        $this->bearer($current->plainTextToken)->deleteJson(self::API . "/auth/devices/{$foreign->accessToken->id}")->assertNotFound();
        $this->bearer($current->plainTextToken)->deleteJson(self::API . "/auth/devices/{$other->accessToken->id}")->assertNoContent();

        $this->bearer($other->plainTextToken)->getJson(self::API . '/auth/me')->assertUnauthorized();
        $this->assertNotNull(PersonalAccessToken::find($foreign->accessToken->id));
    }

    /** @test */
    public function geraete_ohne_tagebuchrecht_403(): void
    {
        $user = $this->appUser([]);
        $token = $user->createToken('iPad', ['paed-app'])->plainTextToken;

        $this->bearer($token)->getJson(self::API . '/auth/devices')->assertForbidden();
    }

    /** @test */
    public function geraet_im_web_profil_abmelden(): void
    {
        $user = $this->appUser();
        $token = $user->createToken('iPad Klasse 4a', ['paed-app'], now()->addDays(90));
        $foreign = $this->appUser()->createToken('Fremd', ['paed-app'], now()->addDays(90));

        $this->withoutVite();
        $this->actingAs($user);
        $this->get('/mein-profil')->assertOk()->assertSee('Meine App-Geräte')->assertSee('iPad Klasse 4a');

        $this->delete("/mein-profil/app-geraete/{$foreign->accessToken->id}")->assertRedirect();
        $this->assertNotNull(PersonalAccessToken::find($foreign->accessToken->id));

        $this->delete("/mein-profil/app-geraete/{$token->accessToken->id}")
            ->assertRedirect()
            ->assertSessionHas('type', 'success');
        $this->assertNull(PersonalAccessToken::find($token->accessToken->id));

        $this->bearer($token->plainTextToken)->getJson(self::API . '/auth/me')->assertUnauthorized();
    }

    /** @test */
    public function tagebuch_put_mit_veraltetem_stand_liefert_409(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = PaedDiaryEntry::factory()->completed()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id, 'content' => 'Original']);
        $entry->schueler()->attach($schueler->id);
        $gelesen = $entry->fresh()->updated_at->toIso8601String();

        // Parallele Änderung (z.B. im Web)
        $this->travel(5)->minutes();
        $entry->update(['content' => 'Im Web geändert']);

        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}", ['content' => 'Aus der App', 'expected_updated_at' => $gelesen])
            ->assertStatus(409)
            ->assertJsonPath('data.id', $entry->id)
            ->assertJsonPath('data.content', 'Im Web geändert');
        $this->assertSame('Im Web geändert', $entry->fresh()->content);

        // Aktueller Stand (auch in anderer Zeitzone) → 200
        $aktuell = $entry->fresh()->updated_at->copy()->setTimezone('UTC')->toIso8601String();
        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}", ['content' => 'Aus der App', 'expected_updated_at' => $aktuell])
            ->assertOk()->assertJsonPath('data.content', 'Aus der App');

        // Ohne Feld unverändert
        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}", ['content' => 'Ohne Prüfung'])->assertOk();
        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}", ['expected_updated_at' => 'gestern'])
            ->assertStatus(422)->assertJsonValidationErrors('expected_updated_at');
    }

    /** @test */
    public function ziel_put_mit_veraltetem_stand_liefert_409(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);
        $goal = DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $schueler->id, 'title' => 'Original']);
        $gelesen = $goal->updated_at->toIso8601String();

        $this->travel(1)->minutes();
        $goal->update(['title' => 'Parallel geändert']);

        $this->putJson(self::API . "/diagnostic/goals/{$goal->id}", ['title' => 'App', 'expected_updated_at' => $gelesen])
            ->assertStatus(409)
            ->assertJsonPath('data.title', 'Parallel geändert');

        $this->putJson(self::API . "/diagnostic/goals/{$goal->id}", ['title' => 'App'])->assertOk()->assertJsonPath('data.title', 'App');

        // Fremder Schüler bleibt 403
        [, $fremd] = $this->classWithStudent();
        $fremdesZiel = DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $fremd->id]);
        $this->putJson(self::API . "/diagnostic/goals/{$fremdesZiel->id}", ['title' => 'x', 'expected_updated_at' => $gelesen])->assertForbidden();
    }

    /** @test */
    public function login_limit_pro_email_und_ip(): void
    {
        $user = $this->appUser();
        $other = $this->appUser();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson(self::API . '/auth/token', ['email' => $user->email, 'password' => 'falsch', 'device_name' => 'x'])->assertStatus(422);
        }
        $this->postJson(self::API . '/auth/token', ['email' => $user->email, 'password' => 'geheim123', 'device_name' => 'x'])->assertStatus(429);

        // Andere Konten aus demselben Schulnetz sind nicht betroffen
        $this->postJson(self::API . '/auth/token', ['email' => $other->email, 'password' => 'geheim123', 'device_name' => 'x'])->assertCreated();
    }

    /** @test */
    public function login_limit_pro_ip_insgesamt(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson(self::API . '/auth/token', ['email' => "konto{$i}@schule.test", 'password' => 'x', 'device_name' => 'x'])->assertStatus(422);
        }

        $this->postJson(self::API . '/auth/token', ['email' => 'neu@schule.test', 'password' => 'x', 'device_name' => 'x'])->assertStatus(429);
    }
}
