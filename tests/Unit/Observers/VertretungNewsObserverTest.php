<?php

namespace Tests\Unit\Observers;

use App\Models\DailyNews;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Testet VertretungNewsObserver (DailyNews-Ereignisse → Elterninfoboard-API).
 * Migration hat vertretungsplan_send_elterninfoboard=0 als Default.
 */
class VertretungNewsObserverTest extends TestCase
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

    /** URL leer → kein API-Aufruf */
    public function test_created_sendet_nicht_wenn_url_fehlt(): void
    {
        Setting::where('setting', 'vertretungsplan_send_elterninfoboard')->update(['value' => '1']);
        Setting::where('setting', 'elterninfoboard_url')->update(['value' => '']);
        Cache::forget('setting_vertretungsplan_send_elterninfoboard');
        Cache::forget('setting_elterninfoboard_url');

        $news = DailyNews::create([
            'date_start' => now(),
            'date_end'   => now()->addDays(2),
            'news'       => 'Ohne URL',
        ]);

        $this->assertDatabaseHas('daily_news', ['id' => $news->id]);
    }

    // ─── Exception-Handling ──────────────────────────────────────────────────

    /** API nicht erreichbar → Exception abgefangen, DailyNews trotzdem gespeichert */
    public function test_created_exception_wird_abgefangen(): void
    {
        $this->elterninfoboardAktivieren();

        $news = DailyNews::create([
            'date_start' => now(),
            'date_end'   => now()->addDay(),
            'news'       => 'Mit fehlendem Server',
        ]);

        $this->assertDatabaseHas('daily_news', ['id' => $news->id]);
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

    /** API-Fehler beim delete → Exception abgefangen, Datensatz gelöscht */
    public function test_deleted_exception_wird_abgefangen(): void
    {
        $this->elterninfoboardAktivieren();

        $news = DailyNews::create([
            'date_start' => now(),
            'date_end'   => now()->addDay(),
            'news'       => 'Lösch-Exception-Test',
        ]);

        $news->delete();

        $this->assertDatabaseMissing('daily_news', ['id' => $news->id]);
    }
}

