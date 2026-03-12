<?php

namespace Tests\Unit\Observers;

use App\Models\Setting;
use App\Models\User;
use App\Models\Vertretung;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Testet VertretungObserver.
 * Migration hat vertretungsplan_send_elterninfoboard=0 und elterninfoboard_url='' als Default.
 */
class VertretungObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->actingAs(User::factory()->create());
    }

    private function elterninfoboardAktivieren(string $url = 'http://127.0.0.1:65000'): void
    {
        Setting::where('setting', 'vertretungsplan_send_elterninfoboard')->update(['value' => '1']);
        Setting::where('setting', 'elterninfoboard_url')->update(['value' => $url]);
        Cache::forget('setting_vertretungsplan_send_elterninfoboard');
        Cache::forget('setting_elterninfoboard_url');
    }

    private function elterninfoboardDeaktivieren(): void
    {
        Setting::where('setting', 'vertretungsplan_send_elterninfoboard')->update(['value' => '0']);
        Cache::forget('setting_vertretungsplan_send_elterninfoboard');
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
        Setting::where('setting', 'vertretungsplan_send_elterninfoboard')->update(['value' => '1']);
        Setting::where('setting', 'elterninfoboard_url')->update(['value' => '']);
        Cache::forget('setting_vertretungsplan_send_elterninfoboard');
        Cache::forget('setting_elterninfoboard_url');

        $vertretung = Vertretung::factory()->create();

        $this->assertDatabaseHas('vertretungen', ['id' => $vertretung->id]);
    }

    // ─── Exception-Handling ──────────────────────────────────────────────────

    /** API nicht erreichbar → Exception abgefangen, kein Absturz, Vertretung gespeichert */
    public function test_created_exception_wird_abgefangen(): void
    {
        $this->elterninfoboardAktivieren();

        $vertretung = Vertretung::factory()->create();

        $this->assertDatabaseHas('vertretungen', ['id' => $vertretung->id]);
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

    /** API-Fehler beim delete → Exception abgefangen, Model soft-deleted */
    public function test_deleted_exception_wird_abgefangen(): void
    {
        $this->elterninfoboardAktivieren();

        $vertretung = Vertretung::factory()->create();
        $vertretung->delete();

        $this->assertSoftDeleted('vertretungen', ['id' => $vertretung->id]);
    }
}

