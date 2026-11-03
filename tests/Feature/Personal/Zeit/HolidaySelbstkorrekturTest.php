<?php

namespace Tests\Feature\Personal\Zeit;

use App\Models\Absence;
use App\Models\personal\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Personen ohne Vorgesetzte (z. B. Geschäftsführung) dürfen eigene Urlaube
 * jederzeit korrigieren und entfernen – auch rückwirkend und nach Genehmigung.
 */
class HolidaySelbstkorrekturTest extends TestCase
{
    use ZeitTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->neuesModell();
        Carbon::setTestNow('2026-09-15 10:00:00');
        Notification::fake();
        $this->settingSetzen('absence_auto_create', '1');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ohne_vorgesetzte_genehmigten_vergangenen_urlaub_korrigieren(): void
    {
        $gf = $this->mitarbeiter();
        $antrag = Holiday::factory()->for($gf, 'employe')->approved()->create(['start_date' => '2026-08-10', 'end_date' => '2026-08-14', 'days' => 5]);
        $this->assertSame(1, Absence::where('holiday_id', $antrag->id)->count());

        $this->actingAs($gf)->put(route('holidays.correct', $antrag), [
            'start_date' => '2026-08-11',
            'end_date' => '2026-08-12',
        ])->assertSessionHasNoErrors()->assertSessionHas('type', 'success');

        $antrag->refresh();
        $this->assertSame('2026-08-11', $antrag->start_date->toDateString());
        $this->assertSame('2026-08-12', $antrag->end_date->toDateString());
        $this->assertEquals(2, $antrag->days);
        $this->assertTrue($antrag->approved);

        $absence = Absence::where('holiday_id', $antrag->id)->firstOrFail();
        $this->assertSame('2026-08-11', $absence->start->toDateString());
        $this->assertSame('2026-08-12', $absence->end->toDateString());
    }

    public function test_ohne_vorgesetzte_genehmigten_vergangenen_urlaub_entfernen(): void
    {
        $gf = $this->mitarbeiter();
        $antrag = Holiday::factory()->for($gf, 'employe')->approved()->create(['start_date' => '2026-08-10', 'end_date' => '2026-08-10']);

        $this->actingAs($gf)->delete(route('holidays.destroy', $antrag))->assertRedirect();
        $this->assertSoftDeleted('holidays', ['id' => $antrag->id]);
    }

    public function test_mit_vorgesetzten_keine_selbstkorrektur(): void
    {
        $chef = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $chef->id]);
        $antrag = Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-08-10', 'end_date' => '2026-08-14']);

        $this->actingAs($ma->fresh())->put(route('holidays.correct', $antrag), [
            'start_date' => '2026-08-11', 'end_date' => '2026-08-12',
        ])->assertForbidden();
        $this->actingAs($ma->fresh())->delete(route('holidays.destroy', $antrag))->assertForbidden();

        $this->assertSame('2026-08-10', $antrag->fresh()->start_date->toDateString());
    }

    public function test_fremden_urlaub_nicht_korrigieren(): void
    {
        $gf = $this->mitarbeiter();
        $antrag = Holiday::factory()->for($this->mitarbeiter(), 'employe')->approved()->create(['start_date' => '2026-08-10', 'end_date' => '2026-08-14']);

        $this->actingAs($gf)->put(route('holidays.correct', $antrag), [
            'start_date' => '2026-08-11', 'end_date' => '2026-08-12',
        ])->assertForbidden();
    }

    public function test_korrektur_prueft_ueberschneidung_und_jahreswechsel(): void
    {
        $gf = $this->mitarbeiter();
        $antrag = Holiday::factory()->for($gf, 'employe')->approved()->create(['start_date' => '2026-08-10', 'end_date' => '2026-08-11']);
        Holiday::factory()->for($gf, 'employe')->approved()->create(['start_date' => '2026-08-17', 'end_date' => '2026-08-18']);

        $this->actingAs($gf)->put(route('holidays.correct', $antrag), [
            'start_date' => '2026-08-10', 'end_date' => '2026-08-17',
        ])->assertSessionHasErrors('start_date');

        $this->actingAs($gf)->put(route('holidays.correct', $antrag), [
            'start_date' => '2026-12-30', 'end_date' => '2027-01-04',
        ])->assertSessionHasErrors('end_date');

        $this->assertSame('2026-08-11', $antrag->fresh()->end_date->toDateString());
    }

    public function test_index_zeigt_korrigieren_nur_ohne_vorgesetzte(): void
    {
        $gf = $this->mitarbeiter();
        Holiday::factory()->for($gf, 'employe')->approved()->create(['start_date' => '2026-08-10', 'end_date' => '2026-08-11']);
        $this->actingAs($gf)->get(route('holidays.index'))->assertOk()->assertSee('Korrigieren');

        $chef = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $chef->id]);
        Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-08-10', 'end_date' => '2026-08-11']);
        $this->actingAs($ma->fresh())->get(route('holidays.index'))->assertOk()->assertDontSee('Korrigieren');
    }
}
