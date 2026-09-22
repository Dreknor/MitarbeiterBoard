<?php

namespace Tests\Unit\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Testet UserObserver (registriert in AppServiceProvider).
 *
 * Der Observer reagiert auf saved / deleted / restored / retrieved
 * und hält einen User-Cache unter `user.{id}` aktuell.
 *
 * Hinweis: UserObserver referenziert intern `Illuminate\Foundation\Auth\User`,
 * greift aber über App\Models\User da dieser die Basisklasse erweitert und
 * der Observer per `User::observe(...)` an App\Models\User gebunden ist.
 */
class UserObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── saved ───────────────────────────────────────────────────────────────

    /** Nach dem Erstellen eines Users liegt dieser im Cache */
    public function test_saved_legt_user_im_cache_ab(): void
    {
        $user = User::factory()->create();

        $cached = Cache::get("user.{$user->id}");

        $this->assertNotNull($cached, 'User wurde nach saved nicht gecacht.');
        $this->assertEquals($user->id, $cached->id);
    }

    /** Nach einem Update wird der Cache-Eintrag aktualisiert */
    public function test_saved_aktualisiert_cache_nach_update(): void
    {
        $user = User::factory()->create(['name' => 'Alter Name']);

        $user->update(['name' => 'Neuer Name']);

        $cached = Cache::get("user.{$user->id}");

        $this->assertNotNull($cached);
        $this->assertEquals('Neuer Name', $cached->name);
    }

    // ─── deleted ─────────────────────────────────────────────────────────────

    /** Nach dem Löschen wird der Cache-Eintrag entfernt */
    public function test_deleted_entfernt_user_aus_cache(): void
    {
        $user = User::factory()->create();
        // Sicherstellen dass gecacht
        $this->assertNotNull(Cache::get("user.{$user->id}"));

        $user->delete();

        $this->assertNull(
            Cache::get("user.{$user->id}"),
            'User-Cache wurde nach deleted nicht geleert.'
        );
    }

    // ─── restored ────────────────────────────────────────────────────────────

    /** Nach dem Wiederherstellen liegt der User erneut im Cache */
    public function test_restored_legt_user_wieder_in_cache(): void
    {
        $user = User::factory()->create();
        $user->delete();

        // Cache nach Delete leer
        $this->assertNull(Cache::get("user.{$user->id}"));

        $user->restore();

        $cached = Cache::get("user.{$user->id}");
        $this->assertNotNull($cached, 'User wurde nach restored nicht gecacht.');
        $this->assertEquals($user->id, $cached->id);
    }

    // ─── retrieved ───────────────────────────────────────────────────────────

    /** Nach dem Abrufen aus der DB wird der User in den Cache aufgenommen */
    public function test_retrieved_befuellt_cache_beim_lesen(): void
    {
        $user = User::factory()->create();

        // Cache leeren, um retrieved-Pfad isoliert zu testen
        Cache::forget("user.{$user->id}");

        // Frisch aus DB laden → retrieved wird ausgelöst
        $freshUser = User::find($user->id);

        $cached = Cache::get("user.{$freshUser->id}");
        $this->assertNotNull($cached, 'User wurde nach retrieved nicht in den Cache aufgenommen.');
    }
}

