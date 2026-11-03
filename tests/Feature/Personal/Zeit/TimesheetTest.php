<?php

namespace Tests\Feature\Personal\Zeit;

use App\Models\Absence;
use App\Models\personal\Roster;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\personal\WorkingTime;
use App\Notifications\Personal\ZeitwirtschaftNotification;
use App\Services\Personal\Zeit\TimesheetService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TimesheetTest extends TestCase
{
    use ZeitTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->neuesModell();
        Carbon::setTestNow('2026-10-15 12:00:00');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- Sicherheit ----

    public function test_fremder_nachweis_kann_nicht_ueber_eigene_id_bebucht_werden(): void
    {
        $angreifer = $this->mitarbeiter();
        $opfer = $this->mitarbeiter();
        $fremd = app(TimesheetService::class)->forMonth($opfer, Carbon::parse('2026-09-01'));

        $this->actingAs($angreifer)
            ->post(route('timesheets.day.store', [$angreifer->id, $fremd->id, '2026-09-10']), ['start' => '08:00', 'end' => '16:00'])
            ->assertNotFound();

        $this->actingAs($angreifer)
            ->post(route('timesheets.lock', [$angreifer->id, $fremd->id]))
            ->assertNotFound();

        $this->assertSame(0, $fremd->timesheet_days()->count());
        $this->assertNull($fremd->fresh()->locked_at);
    }

    public function test_fremden_nachweis_ansehen_ist_verboten(): void
    {
        $a = $this->mitarbeiter();
        $b = $this->mitarbeiter();

        $this->actingAs($a)->get(route('timesheets.show', $b->id))->assertForbidden();
    }

    public function test_gesperrter_nachweis_kann_nicht_geaendert_werden(): void
    {
        $ma = $this->mitarbeiter();
        $ts = app(TimesheetService::class)->forMonth($ma, Carbon::parse('2026-09-01'));
        $zeile = TimesheetDays::create(['timesheet_id' => $ts->id, 'date' => '2026-09-10', 'start' => '08:00', 'end' => '16:00']);
        $ts->update(['locked_at' => now(), 'locked_by' => $ma->id]);

        $this->actingAs($ma)->post(route('timesheets.day.store', [$ma->id, $ts->id, '2026-09-11']), ['start' => '08:00', 'end' => '12:00'])->assertForbidden();
        $this->actingAs($ma)->put(route('timesheets.day.update', $zeile), ['start' => '07:00', 'end' => '16:00'])->assertForbidden();
        $this->actingAs($ma)->delete(route('timesheets.day.destroy', $zeile))->assertForbidden();

        $this->assertSame(1, $ts->timesheet_days()->count());
    }

    public function test_zustandsaenderungen_per_get_existieren_nicht_mehr(): void
    {
        $ma = $this->mitarbeiter();
        $ts = app(TimesheetService::class)->forMonth($ma, Carbon::parse('2026-09-01'));

        $this->actingAs($ma)->get('timesheets/'.$ma->id.'/'.$ts->id.'/lock')->assertStatus(405);
        $this->actingAs($ma)->get('timesheets/'.$ma->id.'/login')->assertNotFound();
        $this->assertNull($ts->fresh()->locked_at);
    }

    // ---- Workflow ----

    public function test_einreichen_bestaetigen_und_zurueckgeben(): void
    {
        $chef = $this->mitarbeiter(['has timesheet', 'lock timesheets']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $chef->id]);
        $ts = app(TimesheetService::class)->forMonth($ma, Carbon::parse('2026-09-01'));

        // Mitarbeitende können nicht selbst abschließen
        $this->actingAs($ma)->post(route('timesheets.lock', [$ma->id, $ts->id]))->assertForbidden();

        $this->actingAs($ma)->post(route('timesheets.submit', [$ma->id, $ts->id]))->assertRedirect();
        $this->assertNotNull($ts->fresh()->submitted_at);
        Notification::assertSentTo($chef, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'timesheet_submitted');

        // Nach dem Einreichen keine Änderungen mehr durch Mitarbeitende
        $this->actingAs($ma)->post(route('timesheets.day.store', [$ma->id, $ts->id, '2026-09-11']), ['start' => '08:00', 'end' => '12:00'])->assertForbidden();

        $this->actingAs($chef)->post(route('timesheets.return', [$ma->id, $ts->id]), ['reason' => 'Bitte 11.09. ergänzen'])->assertRedirect();
        $this->assertNull($ts->fresh()->submitted_at);
        Notification::assertSentTo($ma, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'timesheet_returned');

        $this->actingAs($ma)->post(route('timesheets.submit', [$ma->id, $ts->id]));
        $this->actingAs($chef)->post(route('timesheets.lock', [$ma->id, $ts->id]))->assertRedirect();
        $this->assertNotNull($ts->fresh()->locked_at);
    }

    public function test_laufender_monat_kann_noch_nicht_eingereicht_werden(): void
    {
        $ma = $this->mitarbeiter();
        $ts = app(TimesheetService::class)->forMonth($ma, Carbon::parse('2026-10-01'));

        $this->actingAs($ma)->post(route('timesheets.submit', [$ma->id, $ts->id]))->assertForbidden();
    }

    // ---- Berechnung ----

    public function test_abwesenheit_ueber_wochenende_wird_nur_an_arbeitstagen_gutgeschrieben(): void
    {
        $ma = $this->mitarbeiter();
        // Do 24.09. bis Di 29.09. krank → 4 Arbeitstage (Do, Fr, Mo, Di)
        Absence::create(['users_id' => $ma->id, 'creator_id' => $ma->id, 'reason' => 'krank', 'start' => '2026-09-24', 'end' => '2026-09-29']);

        $ts = Timesheet::where('employe_id', $ma->id)->where('year', 2026)->where('month', 9)->firstOrFail();
        $tage = $ts->timesheet_days()->where('source', 'abwesenheit')->pluck('date')->map->toDateString()->sort()->values()->all();

        $this->assertSame(['2026-09-24', '2026-09-25', '2026-09-28', '2026-09-29'], $tage);
    }

    public function test_monatsuebergreifende_abwesenheit_landet_in_beiden_monaten(): void
    {
        $ma = $this->mitarbeiter();
        Absence::create(['users_id' => $ma->id, 'creator_id' => $ma->id, 'reason' => 'krank', 'start' => '2026-09-30', 'end' => '2026-10-02']);

        $this->assertSame(1, TimesheetDays::whereHas('timesheet', fn ($q) => $q->where('employe_id', $ma->id)->where('month', 9))->where('source', 'abwesenheit')->count());
        $this->assertSame(2, TimesheetDays::whereHas('timesheet', fn ($q) => $q->where('employe_id', $ma->id)->where('month', 10))->where('source', 'abwesenheit')->count());
    }

    public function test_saldo_wird_in_folgemonate_uebertragen(): void
    {
        $ma = $this->mitarbeiter();
        $service = app(TimesheetService::class);
        $august = $service->forMonth($ma, Carbon::parse('2026-08-01'));
        $september = $service->forMonth($ma, Carbon::parse('2026-09-01'));
        $service->recalculate($august); // berechnet August und zieht September nach
        $vorher = $september->fresh()->working_time_account;

        // Nachträglich 2 Stunden Mehrarbeit am Samstag im August
        $service->buchen($august, Carbon::parse('2026-08-01'), ['start' => '08:00', 'end' => '10:00']);

        $this->assertEquals($vorher + 7200, $september->fresh()->working_time_account);
    }

    public function test_ohne_automatik_wird_dienstplan_nur_auf_klick_gebucht(): void
    {
        $this->settingSetzen('timesheet_dienstplan_automatisch', '0');
        $ma = $this->mitarbeiter();
        $roster = Roster::factory()->create(['start_date' => '2026-09-07', 'type' => 'normal']);
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $ma->id, 'date' => '2026-09-08', 'start' => '07:00', 'end' => '15:30']);

        $this->actingAs($ma)->get(route('timesheets.show', [$ma->id, '2026-09']))->assertOk()->assertSee('Plan 07:00–15:30');

        $ts = Timesheet::where('employe_id', $ma->id)->where('month', 9)->firstOrFail();
        $this->assertSame(0, $ts->timesheet_days()->whereNotNull('start')->count());

        $this->actingAs($ma)->post(route('timesheets.plan-month', [$ma->id, $ts->id]))->assertRedirect();
        $zeile = $ts->timesheet_days()->whereNotNull('start')->firstOrFail();
        $this->assertSame('07:00', $zeile->start->format('H:i'));
        $this->assertSame(30, (int) $zeile->pause, 'Gesetzliche Pause bei über 6 Stunden');
    }

    public function test_ueberschneidende_buchung_wird_abgelehnt(): void
    {
        $ma = $this->mitarbeiter();
        $ts = app(TimesheetService::class)->forMonth($ma, Carbon::parse('2026-09-01'));
        TimesheetDays::create(['timesheet_id' => $ts->id, 'date' => '2026-09-10', 'start' => '08:00', 'end' => '12:00']);

        $this->actingAs($ma)->post(route('timesheets.day.store', [$ma->id, $ts->id, '2026-09-10']), ['start' => '11:00', 'end' => '14:00'])
            ->assertSessionHasErrors('start');
    }

    public function test_uebersicht_und_pdf_seiten(): void
    {
        $chef = $this->mitarbeiter(['has timesheet', 'lock timesheets', 'edit employe']);
        $ma = $this->mitarbeiter();

        $this->actingAs($chef)->get(route('timesheets.index'))->assertOk()->assertSee($ma->name);
        $this->actingAs($chef)->get(route('timesheets.show', [$ma->id, '2026-09']))->assertOk();
        $this->actingAs($ma)->get(route('timesheets.overview', $ma->id))->assertOk();
    }

    // ---- Keine Einträge für die Zukunft ----

    public function test_arbeitszeit_fuer_zukuenftige_tage_ist_nicht_moeglich(): void
    {
        $ma = $this->mitarbeiter();
        $ts = app(TimesheetService::class)->forMonth($ma, Carbon::parse('2026-10-01'));

        // morgen (Test-Zeit: 15.10.2026, 12:00 Uhr)
        $this->actingAs($ma)->post(route('timesheets.day.store', [$ma->id, $ts->id, '2026-10-16']), ['start' => '08:00', 'end' => '12:00'])
            ->assertSessionHasErrors('date');
        $this->actingAs($ma)->post(route('timesheets.day.absence', [$ma->id, $ts->id, '2026-10-16']), ['absence' => 'krank'])
            ->assertSessionHasErrors('date');
        $this->actingAs($ma)->get(route('timesheets.day.create', [$ma->id, $ts->id, '2026-10-16']))->assertRedirect();

        $this->assertSame(0, $ts->timesheet_days()->count());
    }

    public function test_heute_nur_bis_zur_aktuellen_uhrzeit(): void
    {
        $ma = $this->mitarbeiter();
        $ts = app(TimesheetService::class)->forMonth($ma, Carbon::parse('2026-10-01'));

        $this->actingAs($ma)->post(route('timesheets.day.store', [$ma->id, $ts->id, '2026-10-15']), ['start' => '08:00', 'end' => '16:00'])
            ->assertSessionHasErrors('end');
        $this->actingAs($ma)->post(route('timesheets.day.store', [$ma->id, $ts->id, '2026-10-15']), ['start' => '08:00', 'end' => '11:30'])
            ->assertSessionHasNoErrors();

        $zeile = $ts->timesheet_days()->firstOrFail();
        $this->actingAs($ma)->put(route('timesheets.day.update', $zeile), ['start' => '08:00', 'end' => '13:00'])
            ->assertSessionHasErrors('end');
        $this->assertSame('11:30', $zeile->fresh()->end->format('H:i'));
    }

    public function test_dienstplan_kann_nicht_fuer_die_zukunft_uebernommen_werden(): void
    {
        $ma = $this->mitarbeiter();
        $roster = Roster::factory()->create(['start_date' => '2026-10-12', 'type' => 'normal']);
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $ma->id, 'date' => '2026-10-16', 'start' => '08:00', 'end' => '12:00']);
        WorkingTime::create(['roster_id' => $roster->id, 'employe_id' => $ma->id, 'date' => '2026-10-15', 'start' => '08:00', 'end' => '15:00']);
        $ts = app(TimesheetService::class)->forMonth($ma, Carbon::parse('2026-10-01'));

        $this->actingAs($ma)->post(route('timesheets.day.plan', [$ma->id, $ts->id, '2026-10-16']))->assertSessionHasErrors('date');
        $this->actingAs($ma)->post(route('timesheets.plan-month', [$ma->id, $ts->id]))->assertRedirect();

        $this->assertSame(0, $ts->timesheet_days()->whereDate('date', '>=', '2026-10-15')->count(), 'Heute (Ende 15:00 noch nicht erreicht) und morgen bleiben leer');
    }
}
