<?php

namespace Tests\Feature\Personal\Zeit;

use App\Models\Absence;
use App\Models\personal\Holiday;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\personal\WorkingTime;
use App\Models\Setting;
use App\Services\Personal\Zeit\TimesheetService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Bestandsschutz: Die Umstellung darf seit Jahren genutzte Daten nicht verändern.
 */
class BestandsschutzTest extends TestCase
{
    use ZeitTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Carbon::setTestNow('2026-10-15 12:00:00');
        $this->settingSetzen('zeitwirtschaft_stichtag', '2026-10-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_neue_einstellungen_sind_standardmaessig_neutral(): void
    {
        $this->assertSame('', (string) Setting::where('setting', 'urlaub_verfall_datum')->value('value'), 'Kein automatischer Verfall von Resturlaub');
        $this->assertSame('0', (string) Setting::where('setting', 'urlaub_anteilig')->value('value'), 'Standardanspruch bleibt ungekürzt');
        $this->assertSame('1', (string) Setting::where('setting', 'timesheet_dienstplan_automatisch')->value('value'), 'Dienstplan wird weiter automatisch übernommen');
        $this->assertNotEmpty(Setting::where('setting', 'zeitwirtschaft_stichtag')->value('value'));
    }

    public function test_abgelaufener_altmonat_behaelt_gespeicherte_werte_beim_oeffnen(): void
    {
        $ma = $this->mitarbeiter();
        $august = Timesheet::create(['employe_id' => $ma->id, 'year' => 2026, 'month' => 8, 'working_time_account' => 12345, 'holidays_rest' => 17]);

        $this->actingAs($ma)->get(route('timesheets.show', [$ma->id, '2026-08']))->assertOk()->assertSee('vor der Umstellung');

        $august->refresh();
        $this->assertSame(12345, $august->working_time_account);
        $this->assertEquals(17, $august->holidays_rest);
    }

    public function test_urlaub_im_altmonat_veraendert_den_nachweis_nicht(): void
    {
        $ma = $this->mitarbeiter();
        $august = Timesheet::create(['employe_id' => $ma->id, 'year' => 2026, 'month' => 8, 'working_time_account' => 5000]);

        Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-08-10', 'end_date' => '2026-08-11', 'days' => 2]);

        $this->assertSame(0, $august->timesheet_days()->count(), 'Keine automatischen Gutschriften in Altmonaten');
        $this->assertSame(5000, $august->fresh()->working_time_account);
    }

    public function test_ausdrueckliche_neuberechnung_nutzt_die_bisherige_methode(): void
    {
        $ma = $this->mitarbeiter();
        $august = Timesheet::create(['employe_id' => $ma->id, 'year' => 2026, 'month' => 8, 'working_time_account' => 0]);
        // Mo 03.08.: 8 h gearbeitet
        TimesheetDays::create(['timesheet_id' => $august->id, 'date' => '2026-08-03', 'start' => '08:00', 'end' => '16:30', 'pause' => 30]);
        // Sa 08.08.: "krank" – bisher auch am Wochenende gutgeschrieben (8 h)
        TimesheetDays::create(['timesheet_id' => $august->id, 'date' => '2026-08-08', 'percent_of_workingtime' => 100, 'comment' => 'krank']);

        $this->actingAs($ma)->post(route('timesheets.recalculate', [$ma->id, $august->id]))->assertRedirect();

        // 21 Werktage à 8 h Soll, 1 Tag erfüllt, + 8 h Wochenend-Gutschrift → −152 h (exakt wie bisher)
        $this->assertSame(-152 * 3600, $august->fresh()->working_time_account);

        $vergleich = app(TimesheetService::class)->vergleiche($august->fresh());
        $this->assertSame(-160 * 3600, $vergleich['neu']['working_time_account'], 'Neues Modell würde die Wochenend-Gutschrift nicht zählen');
    }

    public function test_aenderung_im_altmonat_zieht_andere_altmonate_nicht_mit(): void
    {
        $ma = $this->mitarbeiter();
        $service = app(TimesheetService::class);
        $juli = Timesheet::create(['employe_id' => $ma->id, 'year' => 2026, 'month' => 7, 'working_time_account' => 0]);
        $august = Timesheet::create(['employe_id' => $ma->id, 'year' => 2026, 'month' => 8, 'working_time_account' => 777]);

        $service->buchen($juli, Carbon::parse('2026-07-04'), ['start' => '08:00', 'end' => '10:00']);

        $this->assertSame(777, $august->fresh()->working_time_account, 'Eingefrorener Folgemonat bleibt unverändert');
    }

    public function test_alte_unverknuepfte_urlaubs_abwesenheit_wird_bei_stornierung_entfernt(): void
    {
        $ma = $this->mitarbeiter();
        $urlaub = Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-11-02', 'end_date' => '2026-11-03', 'days' => 2]);
        Absence::where('holiday_id', $urlaub->id)->forceDelete();
        // Zustand wie vor dem Update: Absence ohne Verknüpfung
        Absence::create(['users_id' => $ma->id, 'creator_id' => $ma->id, 'reason' => 'Urlaub', 'start' => '2026-11-02', 'end' => '2026-11-03']);

        $urlaub->delete();

        $this->assertSame(0, Absence::where('users_id', $ma->id)->where('reason', 'Urlaub')->count());
    }

    public function test_alte_urlaubstermine_im_dienstplan_werden_nicht_gedoppelt(): void
    {
        $abteilung = $this->abteilung();
        $ma = $this->mitarbeiter(vertrag: ['department_id' => $abteilung->id]);
        $roster = Roster::factory()->create(['department_id' => $abteilung->id, 'start_date' => '2026-11-02', 'type' => 'normal']);
        // Früher angelegter Eintrag ohne Kennzeichnung
        RosterEvents::create(['roster_id' => $roster->id, 'employe_id' => $ma->id, 'date' => '2026-11-03', 'start' => '08:00', 'end' => '14:30', 'event' => 'Urlaub']);

        Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'days' => 1]);

        $this->assertSame(1, $roster->events()->where('employe_id', $ma->id)->whereDate('date', '2026-11-03')->count());
    }

    public function test_automatische_dienstplan_uebernahme_nur_einmal_je_tag(): void
    {
        $this->settingSetzen('zeitwirtschaft_stichtag', '2026-10-01');
        $ma = $this->mitarbeiter();
        $roster = Roster::factory()->create(['start_date' => '2026-10-05', 'type' => 'normal']);
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $ma->id, 'date' => '2026-10-06', 'start' => '08:00', 'end' => '12:00']);

        $this->actingAs($ma)->get(route('timesheets.show', [$ma->id, '2026-10']))->assertOk();
        $ts = Timesheet::where('employe_id', $ma->id)->where('month', 10)->firstOrFail();
        $zeile = $ts->timesheet_days()->whereDate('date', '2026-10-06')->firstOrFail();

        // Mitarbeitende löscht den Eintrag bewusst – er darf nicht wiederkommen
        $this->actingAs($ma)->delete(route('timesheets.day.destroy', $zeile))->assertRedirect();
        $this->actingAs($ma)->get(route('timesheets.show', [$ma->id, '2026-10']))->assertOk();

        $this->assertSame(0, $ts->timesheet_days()->whereDate('date', '2026-10-06')->count());
    }

    public function test_uebergangsmonat_wird_wie_bisher_aus_dem_dienstplan_befuellt(): void
    {
        $this->settingSetzen('zeitwirtschaft_stichtag', '2026-11-01');
        $ma = $this->mitarbeiter();
        $roster = Roster::factory()->create(['start_date' => '2026-10-05', 'type' => 'normal']);
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $ma->id, 'date' => '2026-10-06', 'start' => '08:00', 'end' => '12:00']);
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $ma->id, 'date' => '2026-10-08', 'start' => '09:00', 'end' => '13:00']);

        $this->actingAs($ma)->get(route('timesheets.show', [$ma->id, '2026-10']))->assertOk()->assertSee('bisherigen Methode');

        $ts = Timesheet::where('employe_id', $ma->id)->where('month', 10)->firstOrFail();
        $this->assertSame(2, $ts->timesheet_days()->where('comment', 'aus Dienstplan erstellt')->count());
    }

    public function test_resturlaub_springt_am_stichtag_nicht(): void
    {
        $ma = $this->mitarbeiter();
        $this->settingSetzen('holiday_claim', '30');
        // Gespeicherter Stand September (bisherige Zählung über „Urlaub“-Zeilen): 20 Tage Rest,
        // obwohl die Urlaubsanträge im System etwas anderes ergeben würden.
        Timesheet::create(['employe_id' => $ma->id, 'year' => 2026, 'month' => 9, 'working_time_account' => 3600, 'holidays_rest' => 20]);
        Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-03-02', 'end_date' => '2026-03-06', 'days' => 5]);

        // Neuer Antrag nach dem Stichtag
        Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'days' => 2]);

        $oktober = Timesheet::where('employe_id', $ma->id)->where('year', 2026)->where('month', 10)->firstOrFail();
        app(TimesheetService::class)->recalculate($oktober->fresh());

        $this->assertEquals(18, $oktober->fresh()->holidays_rest, 'Rest = gespeicherter Septemberwert − neue Anträge');
        $this->assertEquals(2, $oktober->fresh()->holidays_new);

        $konto = app(\App\Services\Personal\Zeit\UrlaubskontoService::class);
        $konto->vergessen();
        $this->assertEquals(18, $konto->rest($ma, 2026));
    }
    public function test_stornierter_urlaub_wird_im_uebergangsmonat_aus_dem_nachweis_entfernt(): void
    {
        // Oktober liegt vor dem Stichtag: Urlaub wurde wie bisher als unverknüpfte „Urlaub“-Zeile übernommen
        $this->settingSetzen('zeitwirtschaft_stichtag', '2026-11-01');
        $ma = $this->mitarbeiter();
        $urlaub = Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'days' => 2]);

        $oktober = Timesheet::create(['employe_id' => $ma->id, 'year' => 2026, 'month' => 10, 'working_time_account' => 0]);
        foreach (['2026-10-05', '2026-10-06'] as $tag) {
            $oktober->timesheet_days()->create(['date' => $tag, 'percent_of_workingtime' => 100, 'comment' => 'Urlaub']);
        }
        $oktober->timesheet_days()->create(['date' => '2026-10-07', 'start' => '08:00:00', 'end' => '16:00:00', 'pause' => 30, 'comment' => 'aus Dienstplan erstellt']);

        app(\App\Services\Personal\Zeit\HolidayService::class)->stornieren($urlaub, $ma);

        $this->assertSame(0, $oktober->timesheet_days()->where('comment', 'Urlaub')->count());
        $this->assertSame(1, $oktober->timesheet_days()->count(), 'Arbeitszeitbuchungen bleiben erhalten');
    }

    public function test_stornierter_urlaub_entfernt_auch_unverknuepfte_zeilen_im_neuen_modell(): void
    {
        $this->neuesModell();
        $ma = $this->mitarbeiter();
        $urlaub = Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'days' => 2]);
        $anderer = Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-10-08', 'end_date' => '2026-10-08', 'days' => 1]);

        $oktober = Timesheet::where('employe_id', $ma->id)->where('year', 2026)->where('month', 10)->firstOrFail();
        // Altbestand: unverknüpfte Zeile aus der bisherigen Übernahme
        $oktober->timesheet_days()->create(['date' => '2026-10-05', 'percent_of_workingtime' => 100, 'comment' => 'Urlaub']);

        app(\App\Services\Personal\Zeit\HolidayService::class)->stornieren($urlaub, $ma);

        $daten = $oktober->timesheet_days()->where('comment', 'Urlaub')->pluck('date')->map->toDateString()->all();
        $this->assertSame(['2026-10-08'], $daten, 'Nur der weiterhin genehmigte Urlaub bleibt gutgeschrieben');
    }
    public function test_bereinigungsbefehl_entfernt_stehen_gebliebenen_urlaub(): void
    {
        $ma = $this->mitarbeiter();
        $august = Timesheet::create(['employe_id' => $ma->id, 'year' => 2026, 'month' => 8, 'working_time_account' => 0]);
        // Stornierter Urlaub (vor der Korrektur: Zeilen blieben stehen)
        $urlaub = Holiday::factory()->for($ma, 'employe')->approved()->createQuietly(['start_date' => '2026-08-10', 'end_date' => '2026-08-11', 'days' => 2]);
        $urlaub->deleteQuietly();
        foreach (['2026-08-10', '2026-08-11'] as $tag) {
            $august->timesheet_days()->create(['date' => $tag, 'percent_of_workingtime' => 100, 'comment' => 'Urlaub']);
        }
        // Von Hand eingetragener Urlaub ohne Antrag im System bleibt
        $august->timesheet_days()->create(['date' => '2026-08-20', 'percent_of_workingtime' => 100, 'comment' => 'Urlaub']);

        $this->artisan('personal:urlaub-bereinigen')->assertSuccessful();
        $this->assertSame(3, $august->timesheet_days()->count(), 'Vorschau ändert nichts');

        $this->artisan('personal:urlaub-bereinigen', ['--ausfuehren' => true])->assertSuccessful();
        $daten = $august->timesheet_days()->pluck('date')->map->toDateString()->all();
        $this->assertSame(['2026-08-20'], $daten);
    }
}
