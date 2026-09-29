<?php

namespace Tests\Feature\Personal\Zeit;

use App\Models\Group;
use App\Models\personal\Holiday;
use App\Models\User;
use App\Notifications\Personal\ZeitwirtschaftNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VorgesetzteTest extends TestCase
{
    use ZeitTestHelpers;

    private User $personal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->neuesModell();
        Carbon::setTestNow('2026-09-15 10:00:00');
        Notification::fake();
        $this->personal = $this->rechte(User::factory()->create(), 'edit employe');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_vorgesetzte_fuer_eine_gruppe_auf_einmal_setzen(): void
    {
        $gruppe = Group::factory()->create();
        $leitung = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $a = $this->mitarbeiter();
        $b = $this->mitarbeiter();
        $andere = $this->mitarbeiter();
        $gruppe->users()->attach([$a->id, $b->id]);

        $this->actingAs($this->personal)
            ->get(route('personal.vorgesetzte.index', ['group_id' => $gruppe->id]))
            ->assertOk()
            ->assertSee('name="user_ids[]" value="'.$a->id.'"', false)
            ->assertDontSee('name="user_ids[]" value="'.$andere->id.'"', false);

        $this->actingAs($this->personal)->post(route('personal.vorgesetzte.update'), [
            'user_ids' => [$a->id, $b->id],
            'superior_id' => $leitung->id,
        ])->assertRedirect();

        $this->assertSame($leitung->id, $a->fresh()->superior_id);
        $this->assertSame($leitung->id, $b->fresh()->superior_id);
        $this->assertNull($andere->fresh()->superior_id);

        $this->actingAs($this->personal)->post(route('personal.vorgesetzte.update'), [
            'user_ids' => [$b->id], 'entfernen' => 1,
        ]);
        $this->assertNull($b->fresh()->superior_id);
    }

    public function test_kreis_in_der_hierarchie_wird_verhindert(): void
    {
        $chef = $this->mitarbeiter();
        $leitung = $this->mitarbeiter();
        $leitung->update(['superior_id' => $chef->id]);

        $this->actingAs($this->personal)->post(route('personal.vorgesetzte.update'), [
            'user_ids' => [$chef->id], 'superior_id' => $leitung->id,
        ])->assertSessionHas('type', 'warning');

        $this->assertNull($chef->fresh()->superior_id);
    }

    public function test_nur_personalverwaltung_darf_zuordnen(): void
    {
        $ma = $this->mitarbeiter();

        $this->actingAs($ma)->get(route('personal.vorgesetzte.index'))->assertForbidden();
        $this->actingAs($ma)->post(route('personal.vorgesetzte.update'), ['user_ids' => [$ma->id], 'superior_id' => $ma->id])->assertForbidden();
    }

    public function test_stellvertretung_darf_urlaub_genehmigen_und_wird_benachrichtigt(): void
    {
        $leitung = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $zweiteLeitung = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $leitung->id]);

        $this->actingAs($this->personal)
            ->post(route('personal.vorgesetzte.deputies.store', $leitung->id), ['deputy_id' => $zweiteLeitung->id])
            ->assertRedirect();

        $this->actingAs($ma->fresh())->post(route('holidays.store'), [
            'employe_id' => $ma->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-20',
        ]);
        $antrag = Holiday::where('employe_id', $ma->id)->firstOrFail();

        Notification::assertSentTo($leitung, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'holiday_requested');
        Notification::assertSentTo($zweiteLeitung, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'holiday_requested');

        $this->actingAs($zweiteLeitung->fresh())->get(route('holidays.index'))->assertOk()->assertSee('Zu entscheiden');
        $this->actingAs($zweiteLeitung->fresh())->post(route('holidays.approve', $antrag))->assertRedirect();
        $this->assertTrue($antrag->fresh()->approved);
    }

    public function test_stellvertretung_ohne_genehmigungsrecht_darf_nicht_genehmigen(): void
    {
        $leitung = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $vertretung = $this->mitarbeiter();
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $leitung->id]);
        $leitung->deputies()->attach($vertretung->id);
        $antrag = Holiday::factory()->for($ma, 'employe')->pending()->create(['start_date' => '2026-10-19', 'end_date' => '2026-10-19']);

        $this->actingAs($vertretung)->post(route('holidays.approve', $antrag))->assertForbidden();
    }

    public function test_stellvertretung_kann_nachweise_der_unterstellten_pruefen(): void
    {
        $leitung = $this->mitarbeiter(['has timesheet', 'lock timesheets']);
        $vertretung = $this->mitarbeiter(['has timesheet', 'lock timesheets']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $leitung->id]);
        $leitung->deputies()->attach($vertretung->id);

        $this->actingAs($vertretung)->get(route('timesheets.show', $ma->id))->assertOk();
        $this->actingAs($vertretung)->get(route('timesheets.index'))->assertOk()->assertSee($ma->name);
    }

    public function test_stellvertretung_entfernen(): void
    {
        $leitung = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $vertretung = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $leitung->deputies()->attach($vertretung->id);

        $this->actingAs($this->personal)
            ->delete(route('personal.vorgesetzte.deputies.destroy', [$leitung->id, $vertretung->id]))
            ->assertRedirect();

        $this->assertSame(0, $leitung->deputies()->count());
    }
}
