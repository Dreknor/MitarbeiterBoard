<?php

namespace Tests\Feature\API\v1;

use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetDays;
use App\Models\User;
use App\Notifications\Personal\ZeitwirtschaftNotification;
use App\Services\Personal\Zeit\TimesheetService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;

/**
 * API v1 – Arbeitszeitnachweise (/api/v1/timesheets/*).
 * Schwerpunkt: Zugriff nur auf eigene bzw. unterstellte Nachweise.
 */
class TimesheetApiTest extends ApiTestCase
{
    use ZeitTestHelpers;

    private User $chef;
    private User $ma;
    private User $fremd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->neuesModell();
        Carbon::setTestNow('2026-10-15 12:00:00');
        Notification::fake();

        $this->chef = $this->mitarbeiter(['has timesheet', 'lock timesheets']);
        $this->ma = $this->mitarbeiter();
        $this->ma->update(['superior_id' => $this->chef->id]);
        $this->fremd = $this->mitarbeiter();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function als(User $user): void
    {
        Sanctum::actingAs($user->fresh(), ['paed-app']);
    }

    private function nachweis(User $user, string $monat = '2026-09-01'): Timesheet
    {
        return app(TimesheetService::class)->forMonth($user, Carbon::parse($monat));
    }

    // ── Zugriff ─────────────────────────────────────────────────────────

    public function test_ohne_token_401(): void
    {
        $this->getJson(self::API.'/timesheets/employees')->assertUnauthorized();
        $this->getJson(self::API.'/timesheets/employees/'.$this->ma->id.'/months/2026-09')->assertUnauthorized();
    }

    public function test_mitarbeiter_sieht_nur_sich_selbst(): void
    {
        $this->als($this->ma);

        $this->getJson(self::API.'/timesheets/employees')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->ma->id)
            ->assertJsonPath('data.0.is_self', true);

        $this->getJson(self::API.'/timesheets/employees/'.$this->ma->id.'/months/2026-09')->assertOk()
            ->assertJsonPath('data.employee.id', $this->ma->id);

        // Fremde Person und eigene Vorgesetzte: verboten
        foreach ([$this->fremd, $this->chef] as $andere) {
            $this->getJson(self::API.'/timesheets/employees/'.$andere->id)->assertForbidden();
            $this->getJson(self::API.'/timesheets/employees/'.$andere->id.'/months/2026-09')->assertForbidden();
        }
        $this->assertSame(0, Timesheet::where('employe_id', $this->fremd->id)->count());
    }

    public function test_fremder_nachweis_kann_weder_gelesen_noch_bebucht_werden(): void
    {
        $ts = $this->nachweis($this->fremd);
        $zeile = TimesheetDays::create(['timesheet_id' => $ts->id, 'date' => '2026-09-10', 'start' => '08:00', 'end' => '16:00']);
        $this->als($this->ma);

        $this->getJson(self::API.'/timesheets/'.$ts->id.'/pdf')->assertForbidden();
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/days', ['date' => '2026-09-11', 'start' => '08:00', 'end' => '12:00'])->assertForbidden();
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/credits', ['date' => '2026-09-11', 'absence' => 'Fortbildung'])->assertForbidden();
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/apply-roster')->assertForbidden();
        $this->putJson(self::API.'/timesheets/days/'.$zeile->id, ['start' => '07:00', 'end' => '16:00'])->assertForbidden();
        $this->deleteJson(self::API.'/timesheets/days/'.$zeile->id)->assertForbidden();
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/submit')->assertForbidden();
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/lock')->assertForbidden();

        $this->assertSame(1, $ts->timesheet_days()->count());
        $this->assertSame('16:00', $zeile->fresh()->end->format('H:i'));
        $this->assertNull($ts->fresh()->submitted_at);
    }

    public function test_vorgesetzte_sehen_unterstellte_aber_keine_fremden(): void
    {
        $this->als($this->chef);

        $ids = collect($this->getJson(self::API.'/timesheets/employees')->assertOk()->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing([$this->chef->id, $this->ma->id], $ids->all());

        $this->getJson(self::API.'/timesheets/employees/'.$this->ma->id)->assertOk();
        $this->getJson(self::API.'/timesheets/employees/'.$this->ma->id.'/months/2026-09')->assertOk()
            ->assertJsonPath('data.permissions.edit', true)
            ->assertJsonPath('data.permissions.submit', false);

        $this->getJson(self::API.'/timesheets/employees/'.$this->fremd->id.'/months/2026-09')->assertForbidden();
        $this->postJson(self::API.'/timesheets/'.$this->nachweis($this->fremd)->id.'/lock')->assertForbidden();
    }

    public function test_vorgesetzte_ohne_lock_timesheets_sehen_nur_sich_selbst(): void
    {
        $leitung = $this->mitarbeiter();
        $this->fremd->update(['superior_id' => $leitung->id]);
        $this->als($leitung);

        $this->getJson(self::API.'/timesheets/employees')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $leitung->id);
        $this->getJson(self::API.'/timesheets/employees/'.$this->fremd->id.'/months/2026-09')->assertForbidden();
    }

    public function test_stellvertretung_darf_wie_die_leitung(): void
    {
        $vertretung = $this->mitarbeiter(['has timesheet', 'lock timesheets']);
        $this->chef->deputies()->attach($vertretung->id);
        $this->als($vertretung);

        $ids = collect($this->getJson(self::API.'/timesheets/employees')->assertOk()->json('data'))->pluck('id');
        $this->assertContains($this->ma->id, $ids->all());
        $this->assertNotContains($this->fremd->id, $ids->all());
        $this->getJson(self::API.'/timesheets/employees/'.$this->ma->id.'/months/2026-09')->assertOk();
    }

    public function test_personal_sieht_alle(): void
    {
        $personal = $this->rechte(User::factory()->create(), 'edit employe');
        $this->als($personal);

        $ids = collect($this->getJson(self::API.'/timesheets/employees')->assertOk()->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing([$this->chef->id, $this->ma->id, $this->fremd->id], $ids->all());
        $this->getJson(self::API.'/timesheets/employees/'.$this->fremd->id.'/months/2026-09')->assertOk();
    }

    // ── Monat & Buchungen ───────────────────────────────────────────────

    public function test_monat_mit_tageszeilen_und_buchungen(): void
    {
        $this->als($this->ma);
        $ts = $this->nachweis($this->ma);

        $antwort = $this->postJson(self::API.'/timesheets/'.$ts->id.'/days', [
            'date' => '2026-09-10', 'start' => '08:00', 'end' => '16:30', 'pause' => 30, 'comment' => 'Büro',
        ])->assertCreated()
            ->assertJsonPath('entry.start', '08:00')
            ->assertJsonPath('entry.duration_minutes', 480)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.month', 9);

        $tag = collect($antwort->json('data.days'))->firstWhere('date', '2026-09-10');
        $this->assertSame(480, $tag['actual_minutes']);
        $this->assertCount(1, $tag['entries']);
        $this->assertCount(30, $antwort->json('data.days'));

        $id = $antwort->json('entry.id');
        $this->putJson(self::API.'/timesheets/days/'.$id, ['start' => '07:00', 'end' => '16:30', 'pause' => 30])
            ->assertOk()->assertJsonPath('entry.duration_minutes', 540);

        // Überschneidung und Datum außerhalb des Monats
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/days', ['date' => '2026-09-10', 'start' => '12:00', 'end' => '13:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('start');
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/days', ['date' => '2026-10-01', 'start' => '08:00', 'end' => '09:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('date');

        $this->postJson(self::API.'/timesheets/'.$ts->id.'/credits', ['date' => '2026-09-11', 'absence' => 'Fortbildung'])
            ->assertCreated()->assertJsonPath('entry.is_credit', true);

        $this->deleteJson(self::API.'/timesheets/days/'.$id)->assertOk();
        $this->assertSame(1, $ts->timesheet_days()->count());
    }

    public function test_zukuenftige_monate_und_ungueltige_monate(): void
    {
        $this->als($this->ma);

        $this->getJson(self::API.'/timesheets/employees/'.$this->ma->id.'/months/2027-01')->assertUnprocessable();
        $this->getJson(self::API.'/timesheets/employees/'.$this->ma->id.'/months/2026-13')->assertNotFound();
    }

    // ── Workflow ────────────────────────────────────────────────────────

    public function test_einreichen_zurueckgeben_und_abschliessen(): void
    {
        $ts = $this->nachweis($this->ma);

        $this->als($this->ma);
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/lock')->assertForbidden();
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/submit')->assertOk()->assertJsonPath('data.status', 'eingereicht');
        Notification::assertSentTo($this->chef, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'timesheet_submitted');

        // Nach dem Einreichen keine Änderungen mehr durch die Person selbst
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/days', ['date' => '2026-09-11', 'start' => '08:00', 'end' => '12:00'])->assertForbidden();

        $this->als($this->chef);
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/return', [])->assertUnprocessable();
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/return', ['reason' => 'Bitte ergänzen'])->assertOk()
            ->assertJsonPath('data.status', 'offen')->assertJsonPath('data.return_reason', 'Bitte ergänzen');

        $this->als($this->ma);
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/submit')->assertOk();

        $this->als($this->chef);
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/lock')->assertOk()
            ->assertJsonPath('data.status', 'abgeschlossen')
            ->assertJsonPath('data.locked_by.id', $this->chef->id)
            ->assertJsonPath('data.permissions.edit', false);

        // Entsperren nur mit "edit employe"
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/unlock')->assertForbidden();
        $personal = $this->rechte(User::factory()->create(), 'edit employe');
        $this->als($personal);
        $this->postJson(self::API.'/timesheets/'.$ts->id.'/unlock')->assertOk()->assertJsonPath('data.status', 'offen');
    }

    public function test_laufender_monat_kann_nicht_eingereicht_werden(): void
    {
        $this->als($this->ma);
        $this->postJson(self::API.'/timesheets/'.$this->nachweis($this->ma, '2026-10-01')->id.'/submit')->assertForbidden();
    }

    public function test_pdf_export(): void
    {
        SnappyPdf::fake();
        $ts = $this->nachweis($this->ma);

        $this->als($this->chef);
        $this->getJson(self::API.'/timesheets/'.$ts->id.'/pdf')->assertOk();
        SnappyPdf::assertViewIs('personal.timesheets.pdf');
    }
}
