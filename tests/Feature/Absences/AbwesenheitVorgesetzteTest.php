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
}
