<?php

namespace Tests\Feature\API\v1;

use App\Models\Absence;
use App\Models\User;
use App\Notifications\AbwesenheitGemeldet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;

/**
 * API v1 – Abwesenheiten (/api/v1/absences/*).
 */
class AbsenceApiTest extends ApiTestCase
{
    use ZeitTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00');
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

    private function abwesenheit(User $user, array $daten = []): Absence
    {
        return Absence::factory()->create(array_merge([
            'users_id' => $user->id,
            'creator_id' => $user->id,
            'reason' => 'krank',
            'start' => '2026-10-14',
            'end' => '2026-10-16',
        ], $daten));
    }

    public function test_ohne_recht_403(): void
    {
        $this->als($this->mitarbeiter(['has holidays']));

        $this->getJson(self::API.'/absences')->assertForbidden();
    }

    public function test_liste_zeigt_nur_aktuelle_und_kommende(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $andere = $this->mitarbeiter();
        $this->abwesenheit($andere);
        $this->abwesenheit($andere, ['start' => '2026-10-01', 'end' => '2026-10-02']);
        $this->als($ich);

        $this->getJson(self::API.'/absences')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee.id', $andere->id)
            ->assertJsonPath('data.0.today', true)
            ->assertJsonPath('data.0.can_delete', false)
            ->assertJsonPath('options.create_for_others', false)
            ->assertJsonPath('options.employees', []);
    }

    public function test_melden_ohne_create_recht_immer_fuer_sich_selbst(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $andere = $this->mitarbeiter();
        $this->als($ich);

        $this->postJson(self::API.'/absences', [
            'employe_id' => $andere->id,
            'reason' => 'krank',
            'start' => '2026-10-14',
            'end' => '2026-10-15',
            'show_substitution_plan' => false,
        ])->assertCreated()
            ->assertJsonPath('data.employee.id', $ich->id)
            ->assertJsonPath('data.show_substitution_plan', false)
            ->assertJsonPath('data.can_delete', true);

        $this->assertSame(0, Absence::where('users_id', $andere->id)->count());
    }

    public function test_melden_fuer_andere_mit_create_recht_und_verlaengern(): void
    {
        $leitung = $this->mitarbeiter(['view absences', 'create absences']);
        $ma = $this->mitarbeiter();
        $this->als($leitung);

        $this->postJson(self::API.'/absences', ['employe_id' => $ma->id, 'reason' => 'krank', 'start' => '2026-10-14', 'end' => '2026-10-14'])
            ->assertCreated()->assertJsonPath('data.employee.id', $ma->id);
        // direkt anschließend erneut gemeldet → bestehende wird verlängert
        $this->postJson(self::API.'/absences', ['employe_id' => $ma->id, 'reason' => 'krank', 'start' => '2026-10-15', 'end' => '2026-10-16'])
            ->assertCreated()->assertJsonPath('data.end', '2026-10-16');

        $this->assertSame(1, Absence::where('users_id', $ma->id)->count());
    }

    public function test_ungueltiger_zeitraum_422(): void
    {
        $this->als($this->mitarbeiter(['view absences']));

        $this->postJson(self::API.'/absences', ['reason' => 'krank', 'start' => '2026-10-16', 'end' => '2026-10-14'])
            ->assertUnprocessable();
    }

    public function test_loeschen_nur_eigene_eintraege(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $andere = $this->mitarbeiter(['view absences']);
        $fremde = $this->abwesenheit($andere);
        $eigene = $this->abwesenheit($ich);
        $this->als($ich);

        $this->deleteJson(self::API."/absences/{$fremde->id}")->assertForbidden();
        $this->deleteJson(self::API."/absences/{$eigene->id}")->assertNoContent();
        $this->assertSoftDeleted($eigene);
        $this->assertNotSoftDeleted($fremde);
    }

    public function test_abgelaufene_nicht_loeschbar(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $heuteEnde = $this->abwesenheit($ich, ['start' => '2026-10-13', 'end' => '2026-10-14']);
        $this->als($ich);

        $this->deleteJson(self::API."/absences/{$heuteEnde->id}")->assertUnprocessable();
    }
}
