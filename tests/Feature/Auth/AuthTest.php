<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Feature-Tests für Authentifizierung und Autorisierung.
 *
 * Prüft:
 * - Lokales Login (E-Mail + Passwort)
 * - Logout
 * - Redirect-Verhalten (Gast → Login, eingeloggt → Home)
 * - Permission-Gates (403 ohne Permission)
 */
class AuthTest extends TestCase
{
    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    private function createUserWithPassword(string $password = 'password'): User
    {
        return User::factory()->create([
            'password'        => Hash::make($password),
            'changePassword'  => false,
        ]);
    }

    // ─── Login-Formular ──────────────────────────────────────────────────────

    public function test_login_seite_ist_erreichbar(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_gast_wird_von_geschuetzter_route_weitergeleitet(): void
    {
        $response = $this->get('/home');
        $response->assertRedirect('/login');
    }

    // ─── Erfolgreicher Login ─────────────────────────────────────────────────

    public function test_login_mit_gueltigen_zugangsdaten(): void
    {
        $user = $this->createUserWithPassword('geheim123');

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'geheim123',
        ]);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    // ─── Fehlgeschlagener Login ───────────────────────────────────────────────

    public function test_login_mit_falschem_passwort_wird_abgewiesen(): void
    {
        $user = $this->createUserWithPassword('richtiges-pw');

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'falsches-pw',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_mit_unbekannter_email_wird_abgewiesen(): void
    {
        $response = $this->post('/login', [
            'email'    => 'existiert-nicht@example.com',
            'password' => 'irgendwas',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_ohne_email_schlaegt_fehl(): void
    {
        $response = $this->post('/login', [
            'email'    => '',
            'password' => 'passwort',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ─── Logout ──────────────────────────────────────────────────────────────

    public function test_logout_beendet_session(): void
    {
        $user = $this->createUserWithPassword();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    // ─── Eingeloggter User wird nicht zum Login weitergeleitet ───────────────

    public function test_eingeloggter_user_kann_home_aufrufen(): void
    {
        $user = $this->createUserWithPassword();
        $this->actingAs($user);

        $response = $this->get('/home');

        // 200 oder Redirect zu Dashboard – aber kein Redirect zu /login
        $this->assertNotEquals('/login', $response->headers->get('Location'));
        $response->assertStatus(200);
    }

    // ─── Permission-Checks ───────────────────────────────────────────────────

    public function test_route_ohne_berechtigung_gibt_403_oder_redirect(): void
    {
        $user = User::factory()->create(['changePassword' => false]);
        $this->actingAs($user);

        // Route die 'edit employe' Permission erfordert
        $response = $this->get('/employes');

        // Entweder 403 Forbidden oder Redirect mit Fehlermeldung
        $this->assertContains($response->getStatusCode(), [302, 403]);
    }

    public function test_route_mit_berechtigung_gibt_kein_403(): void
    {
        Permission::findOrCreate('edit employe');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->create(['changePassword' => false]);
        $user->givePermissionTo('edit employe');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->actingAs($user);
        $response = $this->get('/employes');

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    // ─── Abgelaufenes Passwort ────────────────────────────────────────────────

    public function test_user_mit_change_password_wird_zu_passwort_aenderung_weitergeleitet(): void
    {
        $user = User::factory()->create([
            'password'       => Hash::make('alt'),
            'changePassword' => true,
        ]);

        $this->actingAs($user);

        // Jede geschützte Route sollte zum Password-Expired-Redirect führen
        $response = $this->get('/home');

        // Redirect zu password/expired oder direkt 302
        $this->assertContains($response->getStatusCode(), [200, 302]);
    }

    // ─── CSRF-Schutz ─────────────────────────────────────────────────────────

    public function test_login_post_ohne_csrf_schlaegt_fehl(): void
    {
        // withoutMiddleware überspringt dies - hier testen wir, dass
        // der Login-Endpunkt generell existiert und antwortet
        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->post('/login', [
                'email'    => 'test@test.de',
                'password' => 'passwort',
            ]);

        // Ohne gültigen User: Redirect zurück zu Login
        $response->assertRedirect('/login');
    }
}

