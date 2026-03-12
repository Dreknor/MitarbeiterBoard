<?php

namespace Tests\Unit\Observers;

use App\Models\DailyNews;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Testet VertretungNewsObserver (DailyNews-Ereignisse → Elterninfoboard-API).
 */
class VertretungNewsObserverTest extends TestCase
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

    /** Setting = 0 → kein API-Aufruf, DailyNews wird normal erstellt */
    public function test_created_sendet_nicht_wenn_setting_deaktiviert(): void
    {
        $this->elterninfoboardDeaktivieren();

        $news = DailyNews::create([
            'date_start' => now(),
            'date_end'   => now()->addDays(3),
            'news'       => 'Testmeldung',
        ]);

        $this->assertDatabaseHas('daily_news', ['id' => $news->id]);
    }

    /** URL fehlt → kein API-Aufruf */
    public function test_created_sendet_nicht_wenn_url_fehlt(): void
    {
        Setting::factory()->forKey('vertretungsplan_send_elterninfoboard', '1', 'vertretungsplan')->create();
        Cache::flush();

        $news = DailyNews::create([
            'date_start' => now(),
            'date_end'   => now()->addDays(2),
            'news'       => 'Ohne URL',
        ]);

        $this->assertDatabaseHas('daily_news', ['id' => $news->id]);
    }

    // ─── Exception-Handling ──────────────────────────────────────────────────

    /** API nicht erreichbar → Exception geloggt, keine Propagation */
    public function test_created_loggt_fehler_bei_api_exception(): void
    {
        $this->elterninfoboardAktivieren();
        Log::spy();

        $news = DailyNews::create([
            'date_start' => now(),
            'date_end'   => now()->addDay(),
            'news'       => 'Mit fehlendem Server',
        ]);

        $this->assertDatabaseHas('daily_news', ['id' => $news->id]);
        Log::shouldHaveReceived('error')->once();
    }

    /** Setting deaktiviert → delete sendet nicht */
    public function test_deleted_sendet_nicht_wenn_setting_deaktiviert(): void
    {
        $this->elterninfoboardDeaktivieren();

        $news = DailyNews::create([
            'date_start' => now(),
            'date_end'   => now()->addDays(1),
            'news'       => 'Löschtest',
        ]);

        $news->delete();

        $this->assertDatabaseMissing('daily_news', ['id' => $news->id]);
    }

    /** API-Fehler beim delete wird geloggt */
    public function test_deleted_loggt_fehler_bei_api_exception(): void
    {
        $this->elterninfoboardAktivieren();

        // create loggt bereits Fehler
        $news = DailyNews::create([
            'date_start' => now(),
            'date_end'   => now()->addDay(),
            'news'       => 'Lösch-Exception-Test',
        ]);

        Log::spy();
        $news->delete();

        Log::shouldHaveReceived('error')->atLeast()->once();
    }
}

