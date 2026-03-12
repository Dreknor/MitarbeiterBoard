<?php

namespace Tests\Unit\Observers;

use App\Models\Klasse;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vertretung;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Testet VertretungObserver.
 */
class VertretungObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->actingAs(User::factory()->create());
    }

    private function elterninfoboardAktivieren(string $url = 'http://localhost:1'): void
    {
        Setting::factory()->forKey('vertretungsplan_send_elterninfoboard', '1', 'vertretungsplan')->create();
        Setting::factory()->forKey('elterninfoboard_url', $url, 'vertretungsplan')->create();
        Cache::flush();
    }

    private function elterninfoboardDeaktivieren(): void
    {
        Setting::factory()->forKey('vertretungsplan_send_elterninfoboard', '0', 'vertretungsplan')->create();
        Cache::flush();
    }

    // ─── Setting deaktiviert ─────────────────────────────────────────────────

    /** Setting = 0 → kein API-Aufruf, Vertretung wird normal erstellt */
    public function test_created_sendet_nicht_wenn_setting_deaktiviert(): void
    {
        $this->elterninfoboardDeaktivieren();

        $vertretung = Vertretung::factory()->create();

        $this->assertDatabaseHas('vertretungen', ['id' => $vertretung->id]);
    }

    /** URL fehlt → kein API-Aufruf, Vertretung wird normal erstellt */
    public function test_created_sendet_nicht_wenn_url_fehlt(): void
    {
        Setting::factory()->forKey('vertretungsplan_send_elterninfoboard', '1', 'vertretungsplan')->create();
        // elterninfoboard_url bewusst NICHT setzen
        Cache::flush();

        $vertretung = Vertretung::factory()->create();

        $this->assertDatabaseHas('vertretungen', ['id' => $vertretung->id]);
    }

    // ─── Exception-Handling ──────────────────────────────────────────────────

    /** Wenn API nicht erreichbar → Exception wird geloggt, nicht weitergeleitet */
    public function test_created_loggt_fehler_bei_api_exception(): void
    {
        $this->elterninfoboardAktivieren();
        Log::spy();

        $vertretung = Vertretung::factory()->create();

        $this->assertDatabaseHas('vertretungen', ['id' => $vertretung->id]);
        Log::shouldHaveReceived('error')->once();
    }

    /** Setting deaktiviert → update sendet nicht */
    public function test_updated_sendet_nicht_wenn_setting_deaktiviert(): void
    {
        $this->elterninfoboardDeaktivieren();

        $vertretung = Vertretung::factory()->create();
        $vertretung->update(['comment' => 'geändert']);

        $this->assertEquals('geändert', $vertretung->fresh()->comment);
    }

    /** Setting deaktiviert → delete sendet nicht */
    public function test_deleted_sendet_nicht_wenn_setting_deaktiviert(): void
    {
        $this->elterninfoboardDeaktivieren();

        $vertretung = Vertretung::factory()->create();
        $vertretung->delete();

        $this->assertSoftDeleted('vertretungen', ['id' => $vertretung->id]);
    }

    /** API-Fehler beim delete wird geloggt und nicht weitergeleitet */
    public function test_deleted_loggt_fehler_bei_api_exception(): void
    {
        $this->elterninfoboardAktivieren();

        // created loggt bereits einen Fehler
        $vertretung = Vertretung::factory()->create();

        Log::spy();
        $vertretung->delete();

        $this->assertSoftDeleted('vertretungen', ['id' => $vertretung->id]);
        Log::shouldHaveReceived('error')->atLeast()->once();
    }
}

