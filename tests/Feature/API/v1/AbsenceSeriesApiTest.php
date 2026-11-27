<?php

namespace Tests\Feature\API\v1;

use App\Models\Absence;
use App\Models\AbsenceSerie;
use App\Models\User;
use App\Notifications\AbwesenheitGemeldet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;

/**
 * API v1 – wiederkehrende Abwesenheiten (/api/v1/absences/series), Regeln wie im Web (AbsenceSerieService).
 */
class AbsenceSeriesApiTest extends ApiTestCase
{
    use ZeitTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00'); // Mittwoch
        Notification::fake();
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

    private function anlegen(array $daten = [])
    {
        return $this->postJson(self::API.'/absences/series', array_merge([
            'reason' => 'Fortbildung',
            'start' => '2026-10-14',
            'end' => '2026-10-31',
            'frequency' => 'woechentlich',
            'weekdays' => [2, 4],
        ], $daten));
    }

    public function test_woechentliche_serie_anlegen(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $this->als($ich);

        $this->anlegen()
            ->assertCreated()
            ->assertJsonPath('created', 5)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('data.frequency', 'woechentlich')
            ->assertJsonPath('data.weekdays', [2, 4])
            ->assertJsonPath('data.interval', 1)
            ->assertJsonPath('data.description', 'jeden Dienstag und Donnerstag vom 14.10.2026 bis 31.10.2026')
            ->assertJsonPath('data.can_delete_future', true)
            ->assertJsonCount(5, 'absences')
            ->assertJsonPath('absences.0.start', '2026-10-15')
            ->assertJsonPath('absences.0.end', '2026-10-15')
            ->assertJsonPath('absences.0.series.id', AbsenceSerie::sole()->id);

        Notification::assertNothingSentTo(User::all(), AbwesenheitGemeldet::class);
    }

    public function test_monatlich_und_liste_zeigt_serie(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $this->als($ich);

        $this->anlegen([
            'end' => '2027-01-31',
            'frequency' => 'monatlich',
            'month_position' => -1,
            'month_weekday' => 5,
        ])->assertCreated()->assertJsonPath('created', 3)->assertJsonPath('data.weekdays', null);

        $this->getJson(self::API.'/absences')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.series.description', 'jeden letzten Freitag im Monat vom 14.10.2026 bis 31.01.2027')
            ->assertJsonPath('options.series.frequencies', ['woechentlich', 'monatlich'])
            ->assertJsonPath('options.series.month_positions', [1, 2, 3, 4, -1])
            ->assertJsonPath('options.series.earliest_start', '2026-10-14');
    }

    public function test_regeln_und_validierung(): void
    {
        $this->als($this->mitarbeiter(['view absences']));

        $this->anlegen(['reason' => 'krank'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->anlegen(['start' => '2026-10-13'])->assertUnprocessable()->assertJsonValidationErrors('start');
        $this->anlegen(['end' => '2027-10-15'])->assertUnprocessable()->assertJsonValidationErrors('end');
        $this->anlegen(['weekdays' => []])->assertUnprocessable()->assertJsonValidationErrors('weekdays');
        $this->anlegen(['weekdays' => [7]])->assertUnprocessable()->assertJsonValidationErrors('weekdays.0');
        $this->anlegen(['frequency' => 'taeglich'])->assertUnprocessable()->assertJsonValidationErrors('frequency');
        $this->anlegen(['frequency' => 'monatlich'])->assertUnprocessable()->assertJsonValidationErrors(['month_position', 'month_weekday']);
        $this->anlegen(['start' => '2026-10-17', 'end' => '2026-10-18'])->assertUnprocessable()->assertJsonValidationErrors('rhythmus');

        $this->assertSame(0, Absence::count());
    }

    public function test_fuer_andere_nur_mit_recht(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $andere = $this->mitarbeiter();
        $this->als($ich);

        $this->anlegen(['employe_id' => $andere->id])->assertCreated();
        $this->assertSame(0, Absence::where('users_id', $andere->id)->count());

        $leitung = $this->mitarbeiter(['view absences', 'create absences']);
        $this->als($leitung);
        $this->anlegen(['employe_id' => $andere->id, 'weekdays' => [1]])->assertCreated()->assertJsonPath('absences.0.employee.id', $andere->id);
    }

    public function test_zukuenftige_termine_loeschen(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $this->als($ich);
        $id = $this->anlegen(['weekdays' => [4]])->json('data.id'); // 15., 22., 29.10.

        Carbon::setTestNow('2026-10-23 08:00:00');

        $this->als($this->mitarbeiter(['view absences']));
        $this->deleteJson(self::API.'/absences/series/'.$id)->assertForbidden();

        $this->als($ich);
        $this->deleteJson(self::API.'/absences/series/'.$id)->assertOk()->assertJsonPath('deleted', 1);
        $this->assertSame(2, Absence::count());

        $this->getJson(self::API.'/absences')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson(self::API.'/absences/series/999')->assertNotFound();
    }
}
