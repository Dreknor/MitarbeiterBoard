<?php

namespace Tests\Feature\Absences;

use App\Models\Absence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;
use Tests\TestCase;

/**
 * Web: Vorgesetzte erfassen Abwesenheiten ihrer Unterstellten ohne "create absences".
 */
class AbwesenheitVorgesetzteTest extends TestCase
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

    private function melden(User $actor, User $fuer): void
    {
        $this->actingAs($actor->fresh())->post('/absences', [
            'users_id' => $fuer->id,
            'reason' => 'krank',
            'start' => '2026-10-14',
            'end' => '2026-10-15',
            'showVertretungsplan' => 1,
        ])->assertRedirect();
    }

    public function test_vorgesetzte_melden_fuer_unterstellte(): void
    {
        $leitung = $this->mitarbeiter(['view absences']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $leitung->id]);
        $unterMa = $this->mitarbeiter();
        $unterMa->update(['superior_id' => $ma->id]);

        $this->melden($leitung, $ma);
        $this->melden($leitung, $unterMa);

        $this->assertSame(1, Absence::where('users_id', $ma->id)->where('creator_id', $leitung->id)->count());
        $this->assertSame(1, Absence::where('users_id', $unterMa->id)->count());
    }

    public function test_nicht_unterstellte_werden_nicht_erfasst(): void
    {
        $ich = $this->mitarbeiter(['view absences']);
        $fremd = $this->mitarbeiter();

        $this->melden($ich, $fremd);

        $this->assertSame(0, Absence::where('users_id', $fremd->id)->count());
        $this->assertSame(1, Absence::where('users_id', $ich->id)->count());
    }

    public function test_formular_zeigt_nur_unterstellte(): void
    {
        $leitung = $this->mitarbeiter(['view absences']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $leitung->id]);
        $fremd = $this->mitarbeiter();

        $this->actingAs($leitung->fresh());
        foreach (['absences.dashboardCard', 'absences.dashboardCard-v2'] as $view) {
            $html = view($view)->render();
            $this->assertStringContainsString('name="users_id" class', $html);
            $this->assertStringContainsString('value="'.$ma->id.'"', $html);
            $this->assertStringNotContainsString('value="'.$fremd->id.'"', $html);
        }
    }

    public function test_ohne_unterstellte_kein_auswahlfeld(): void
    {
        $ich = $this->mitarbeiter(['view absences']);

        $this->actingAs($ich);
        $html = view('absences.dashboardCard-v2')->render();
        $this->assertStringContainsString('type="hidden" name="users_id"', $html);
    }

    /**
     * Ganzer Weg wie im Dashboard: Karte laden (v1 und v2) → Auswahl enthält Unterstellte → speichern.
     */
    public function test_dashboard_karte_vorgesetzte_tragen_fuer_unterstellte_ein(): void
    {
        $this->withoutVite();
        $leitung = $this->mitarbeiter(['view absences']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $leitung->id]);
        $fremd = $this->mitarbeiter();

        $karte = \App\Models\DashboardCard::where('view', 'absences.dashboardCard')->sole();
        $platz = \App\Models\DashBoardUser::create(['dashboard_card_id' => $karte->id, 'user_id' => $leitung->id, 'row' => 1, 'col' => 1, 'active' => true]);

        foreach ([false, true] as $v2) {
            if ($v2) {
                $this->rechte($leitung, 'use dashboard v2');
            }
            $this->actingAs($leitung->fresh())->get("/dashboard/card/{$platz->id}")
                ->assertOk()
                ->assertSee('name="users_id"', false)
                ->assertSee('value="'.$ma->id.'"', false)
                ->assertDontSee('value="'.$fremd->id.'"', false);
        }

        $this->melden($leitung, $ma);
        $this->assertSame(1, Absence::where('users_id', $ma->id)->where('creator_id', $leitung->id)->count());
    }

    public function test_stellvertretung_traegt_fuer_unterstellte_der_leitung_ein(): void
    {
        $leitung = $this->mitarbeiter(['view absences']);
        $vertretung = $this->mitarbeiter(['view absences']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $leitung->id]);
        $vertretung->deputyFor()->attach($leitung->id);

        $this->actingAs($vertretung->fresh());
        $this->assertStringContainsString('value="'.$ma->id.'"', view('absences.dashboardCard-v2')->render());

        $this->melden($vertretung, $ma);
        $this->assertSame(1, Absence::where('users_id', $ma->id)->where('creator_id', $vertretung->id)->count());
    }

    public function test_vorgesetzte_ohne_view_absences_koennen_nicht_eintragen(): void
    {
        $leitung = $this->mitarbeiter();
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $leitung->id]);

        $this->actingAs($leitung->fresh())->post('/absences', [
            'users_id' => $ma->id,
            'reason' => 'krank',
            'start' => '2026-10-14',
            'end' => '2026-10-15',
        ])->assertForbidden();

        $this->assertSame(0, Absence::count());
    }
}
