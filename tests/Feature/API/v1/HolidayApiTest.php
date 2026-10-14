<?php

namespace Tests\Feature\API\v1;

use App\Models\personal\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;

/**
 * API v1 – Urlaub (/api/v1/holidays/*).
 * Schwerpunkt: Antrag, Entscheidung und Zugriff nur auf eigene bzw. unterstellte Anträge.
 */
class HolidayApiTest extends ApiTestCase
{
    use ZeitTestHelpers;

    private User $chef;
    private User $ma;
    private User $fremd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->neuesModell();
        Carbon::setTestNow('2026-09-15 10:00:00');
        Notification::fake();

        $this->chef = $this->mitarbeiter(['has holidays', 'approve holidays']);
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

    private function antrag(User $user, array $daten = []): Holiday
    {
        return Holiday::create(array_merge([
            'employe_id' => $user->id,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-23',
            'days' => 5,
            'approved' => false,
            'rejected' => false,
        ], $daten));
    }

    public function test_ohne_urlaubsrecht_403(): void
    {
        $this->als($this->mitarbeiter(['has timesheet']));

        $this->getJson(self::API.'/holidays')->assertForbidden();
    }

    public function test_uebersicht_mit_konto_und_eigenen_antraegen(): void
    {
        $this->antrag($this->ma);
        $this->antrag($this->fremd);
        $this->als($this->ma);

        $res = $this->getJson(self::API.'/holidays?year=2026')->assertOk();

        $res->assertJsonPath('data.year', 2026)
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.status', 'beantragt')
            ->assertJsonPath('data.requests.0.permissions.delete', true)
            ->assertJsonPath('data.requests.0.permissions.approve', false)
            ->assertJsonPath('data.employees.0.id', $this->ma->id)
            ->assertJsonPath('data.pending_decisions', 0);
        $this->assertNotNull($res->json('data.account.rest'));
    }

    public function test_vorschau_zaehlt_arbeitstage(): void
    {
        $this->als($this->ma);

        $this->getJson(self::API.'/holidays/preview?employe_id='.$this->ma->id.'&start_date=2026-10-19&end_date=2026-10-23')
            ->assertOk()
            ->assertJsonPath('data.days', 5)
            ->assertJsonPath('data.own_overlap', false);
    }

    public function test_antrag_bleibt_offen_und_vorgesetzte_entscheidet(): void
    {
        $this->als($this->ma);
        $id = $this->postJson(self::API.'/holidays', [
            'employe_id' => $this->ma->id,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-23',
            'comment' => 'Herbst',
        ])->assertCreated()
            ->assertJsonPath('approved', false)
            ->assertJsonPath('data.0.days', 5)
            ->json('data.0.id');

        $this->als($this->chef);
        $this->getJson(self::API.'/holidays')->assertJsonPath('data.pending_decisions', 1);
        $this->getJson(self::API.'/holidays/decisions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.permissions.approve', true);

        $this->postJson(self::API."/holidays/$id/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'genehmigt')
            ->assertJsonPath('data.approved_by.id', $this->chef->id);
    }

    public function test_fuer_fremde_person_kein_antrag_und_keine_entscheidung(): void
    {
        $this->als($this->ma);
        $this->postJson(self::API.'/holidays', [
            'employe_id' => $this->fremd->id,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-23',
        ])->assertForbidden();

        $antrag = $this->antrag($this->fremd);
        $this->als($this->chef);
        $this->postJson(self::API."/holidays/{$antrag->id}/approve")->assertForbidden();
        $this->postJson(self::API."/holidays/{$antrag->id}/reject")->assertForbidden();
        $this->deleteJson(self::API."/holidays/{$antrag->id}")->assertForbidden();
        $this->assertFalse($antrag->fresh()->approved);
    }

    public function test_ueberschneidung_422(): void
    {
        $this->antrag($this->ma);
        $this->als($this->ma);

        $this->postJson(self::API.'/holidays', [
            'employe_id' => $this->ma->id,
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-22',
        ])->assertUnprocessable()->assertJsonValidationErrors('start_date');
    }

    public function test_ablehnen_mit_begruendung(): void
    {
        $antrag = $this->antrag($this->ma);
        $this->als($this->chef);

        $this->postJson(self::API."/holidays/{$antrag->id}/reject", ['reason' => 'Personalengpass'])
            ->assertOk()
            ->assertJsonPath('data.status', 'abgelehnt')
            ->assertJsonPath('data.rejection_reason', 'Personalengpass');
    }

    public function test_stornierung_beantragen_und_entscheiden(): void
    {
        $antrag = $this->antrag($this->ma, ['approved' => true, 'approved_by' => $this->chef->id, 'approved_at' => now()]);

        $this->als($this->ma);
        $this->getJson(self::API.'/holidays')->assertJsonPath('data.requests.0.permissions.request_cancellation', true)
            ->assertJsonPath('data.requests.0.permissions.delete', false);
        $this->postJson(self::API."/holidays/{$antrag->id}/cancel", ['reason' => 'Plan geändert'])
            ->assertOk()
            ->assertJsonPath('data.status', 'storno_beantragt');

        $this->als($this->chef);
        $this->postJson(self::API."/holidays/{$antrag->id}/cancel-decision", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', 'storniert');
        $this->assertSoftDeleted($antrag);
    }

    public function test_eigenen_offenen_antrag_loeschen(): void
    {
        $antrag = $this->antrag($this->ma);
        $this->als($this->ma);

        $this->deleteJson(self::API."/holidays/{$antrag->id}")->assertNoContent();
        $this->assertSoftDeleted($antrag);
    }

    public function test_me_meldet_urlaubsrechte_und_app_zugang(): void
    {
        $this->als($this->ma);

        $this->getJson(self::API.'/auth/me')
            ->assertOk()
            ->assertJsonPath('data.permissions.holidays', true)
            ->assertJsonPath('data.permissions.timesheets', true)
            ->assertJsonPath('data.permissions.approve_holidays', false)
            ->assertJsonPath('data.apps.dienstplan', true);
    }
}
