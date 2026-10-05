<?php

namespace Tests\Feature\Personal\Zeit;

use App\Models\Absence;
use App\Models\personal\Holiday;
use App\Models\personal\TimesheetDays;
use App\Notifications\Personal\ZeitwirtschaftNotification;
use App\Services\Personal\Zeit\UrlaubskontoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HolidayWorkflowTest extends TestCase
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

    public function test_antrag_bleibt_offen_und_benachrichtigt_vorgesetzte(): void
    {
        $chef = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $chef->id]);

        $this->actingAs($ma)->post(route('holidays.store'), [
            'employe_id' => $ma->id,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-23',
        ])->assertRedirect();

        $antrag = Holiday::where('employe_id', $ma->id)->firstOrFail();
        $this->assertFalse($antrag->approved);
        $this->assertEquals(5, $antrag->days);
        Notification::assertSentTo($chef, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'holiday_requested');
    }

    public function test_genehmigende_koennen_eigenen_urlaub_nicht_selbst_genehmigen(): void
    {
        $chef = $this->mitarbeiter(['has holidays', 'approve holidays', 'approve all holidays']);

        $this->actingAs($chef)->post(route('holidays.store'), [
            'employe_id' => $chef->id,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-20',
        ]);

        $antrag = Holiday::where('employe_id', $chef->id)->firstOrFail();
        $this->assertFalse($antrag->approved, 'Eigener Antrag darf nicht automatisch genehmigt sein');

        $this->actingAs($chef)->post(route('holidays.approve', $antrag))->assertForbidden();
    }

    public function test_genehmigung_nur_im_eigenen_zustaendigkeitsbereich(): void
    {
        $fremderChef = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $ma = $this->mitarbeiter();
        $antrag = Holiday::factory()->for($ma, 'employe')->pending()->create(['start_date' => '2026-10-19', 'end_date' => '2026-10-19']);

        $this->actingAs($fremderChef)->post(route('holidays.approve', $antrag))->assertForbidden();

        $fremderChef->update(['superior_id' => null]);
        $ma->update(['superior_id' => $fremderChef->id]);
        $this->actingAs($fremderChef->fresh())->post(route('holidays.approve', $antrag))->assertRedirect();
        $this->assertTrue($antrag->fresh()->approved);
    }

    public function test_umschliessender_urlaub_wird_als_ueberschneidung_erkannt(): void
    {
        $ma = $this->mitarbeiter();
        Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-30']);

        $this->actingAs($ma)->post(route('holidays.store'), [
            'employe_id' => $ma->id,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-20',
        ])->assertSessionHasErrors('start_date');

        $this->assertSame(1, Holiday::where('employe_id', $ma->id)->count());
    }

    public function test_urlaubstage_zaehlen_nur_arbeitstage_laut_vertrag(): void
    {
        $ma = $this->mitarbeiter(vertrag: ['hours' => 24, 'workdays' => [1, 2, 3]]);

        // Do 29.10. bis Mi 04.11.: Arbeitstage Mo, Di, Mi = 3 (Sa 31.10. Reformationstag ohnehin frei)
        $this->actingAs($ma)->post(route('holidays.store'), [
            'employe_id' => $ma->id,
            'start_date' => '2026-10-29',
            'end_date' => '2026-11-04',
        ]);

        $this->assertEquals(3, Holiday::where('employe_id', $ma->id)->value('days'));
    }

    public function test_halber_tag_und_jahreswechsel_werden_geteilt(): void
    {
        $ma = $this->mitarbeiter();

        $this->actingAs($ma)->post(route('holidays.store'), [
            'employe_id' => $ma->id, 'start_date' => '2026-10-16', 'end_date' => '2026-10-16', 'half_day' => 1,
        ]);
        $this->assertEquals(0.5, Holiday::where('employe_id', $ma->id)->value('days'));

        $this->actingAs($ma)->post(route('holidays.store'), [
            'employe_id' => $ma->id, 'start_date' => '2026-12-28', 'end_date' => '2027-01-05',
        ]);
        $teile = Holiday::where('employe_id', $ma->id)->where('start_date', '>=', '2026-12-01')->orderBy('start_date')->get();
        $this->assertCount(2, $teile);
        $this->assertSame('2026-12-31', $teile[0]->end_date->toDateString());
        $this->assertSame('2027-01-01', $teile[1]->start_date->toDateString());
    }

    public function test_aenderung_der_wertung_von_heiligabend_und_silvester_berechnet_urlaubstage_neu(): void
    {
        $ma = $this->mitarbeiter();
        // Mo 21.12. – Do 31.12.2026: alt 8 Arbeitstage (25.12. ist Feiertag), mit Heiligabend/Silvester als Feiertag 6
        $antrag = Holiday::factory()->for($ma, 'employe')->create(['start_date' => '2026-12-21', 'end_date' => '2026-12-31', 'days' => 8, 'approved' => true, 'rejected' => false]);

        $this->assertSame(1, app(\App\Services\Personal\Zeit\HolidayService::class)->tageNeuBerechnen(2026));
        $this->assertEquals(6, $antrag->fresh()->days);

        $admin = $this->mitarbeiter(['edit settings']);
        $this->actingAs($admin)->post(url('settings'), ['setting' => ['heiligabend_feiertag' => '0', 'silvester_feiertag' => '0']])->assertRedirect();
        $this->assertEquals(8, $antrag->fresh()->days);
    }

    public function test_genehmigung_erzeugt_verknuepfte_abwesenheit_und_gutschriften_nur_an_arbeitstagen(): void
    {
        $chef = $this->mitarbeiter(['has holidays', 'approve holidays', 'approve all holidays']);
        $ma = $this->mitarbeiter();

        // Fr 04.09. bis Mo 07.09. (vergangener Monat → Nachweis wird abgeglichen)
        $antrag = Holiday::factory()->for($ma, 'employe')->pending()->create(['start_date' => '2026-09-04', 'end_date' => '2026-09-07', 'days' => 2]);

        $this->actingAs($chef)->post(route('holidays.approve', $antrag))->assertRedirect();

        $this->assertSame(1, Absence::where('holiday_id', $antrag->id)->count());

        $gutschriften = TimesheetDays::where('holiday_id', $antrag->id)->pluck('date')->map->toDateString()->sort()->values()->all();
        $this->assertSame(['2026-09-04', '2026-09-07'], $gutschriften, 'Wochenende darf nicht gutgeschrieben werden');

        Notification::assertSentTo($ma, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'holiday_approved');
    }

    public function test_ablehnen_mit_begruendung_entfernt_gutschriften(): void
    {
        $chef = $this->mitarbeiter(['has holidays', 'approve holidays', 'approve all holidays']);
        $ma = $this->mitarbeiter();
        $antrag = Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-09-08', 'end_date' => '2026-09-08', 'days' => 1]);
        $this->assertSame(1, TimesheetDays::where('holiday_id', $antrag->id)->count());

        $this->actingAs($chef)->post(route('holidays.reject', $antrag), ['reason' => 'Personalengpass'])->assertRedirect();

        $antrag->refresh();
        $this->assertTrue($antrag->rejected);
        $this->assertSame('Personalengpass', $antrag->rejection_reason);
        $this->assertSame(0, TimesheetDays::where('holiday_id', $antrag->id)->count());
        $this->assertSame(0, Absence::where('holiday_id', $antrag->id)->count());
    }

    public function test_stornierung_genehmigten_urlaubs_laeuft_ueber_antrag(): void
    {
        $chef = $this->mitarbeiter(['has holidays', 'approve holidays']);
        $ma = $this->mitarbeiter();
        $ma->update(['superior_id' => $chef->id]);
        $antrag = Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-11-02', 'end_date' => '2026-11-06', 'days' => 5]);

        // Mitarbeitende dürfen genehmigten Urlaub nicht einfach löschen
        $this->actingAs($ma)->delete(route('holidays.destroy', $antrag))->assertForbidden();

        $this->actingAs($ma)->post(route('holidays.cancel', $antrag), ['reason' => 'Termin verschoben'])->assertRedirect();
        $this->assertNotNull($antrag->fresh()->cancellation_requested_at);
        Notification::assertSentTo($chef, ZeitwirtschaftNotification::class, fn ($n) => $n->type === 'holiday_cancellation_requested');

        $this->actingAs($chef->fresh())->post(route('holidays.cancel-decision', $antrag), ['decision' => 'approve'])->assertRedirect();
        $this->assertSoftDeleted('holidays', ['id' => $antrag->id]);
    }

    public function test_loeschen_ist_nur_per_delete_moeglich(): void
    {
        $ma = $this->mitarbeiter();
        $antrag = Holiday::factory()->for($ma, 'employe')->pending()->create(['start_date' => '2026-10-19', 'end_date' => '2026-10-19']);

        $this->actingAs($ma)->get('holidays/'.$antrag->id.'/delete')->assertNotFound();
        $this->assertNotSoftDeleted('holidays', ['id' => $antrag->id]);

        $this->actingAs($ma)->delete(route('holidays.destroy', $antrag))->assertRedirect();
        $this->assertSoftDeleted('holidays', ['id' => $antrag->id]);
    }

    public function test_urlaubskonto_mit_uebertrag_und_verfall(): void
    {
        $ma = $this->mitarbeiter();
        $this->settingSetzen('holiday_claim', '30');
        $this->settingSetzen('urlaubskonto_startjahr', '2025');
        $this->settingSetzen('urlaub_verfall_datum', '03-31');

        Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2025-07-07', 'end_date' => '2025-07-18', 'days' => 10]);
        Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-02-02', 'end_date' => '2026-02-06', 'days' => 5]);

        $konto = app(UrlaubskontoService::class);
        $konto->vergessen();

        $this->assertEquals(30, $konto->anspruch($ma, 2026));
        $this->assertEquals(20, $konto->uebertrag($ma, 2026), 'Rest 2025 = 30 − 10');
        // Bis 31.03. nur 5 Tage genommen → 15 Tage Übertrag verfallen
        $this->assertEquals(15, $konto->verfallen($ma, 2026, Carbon::parse('2026-09-15')));
        $this->assertEquals(30 + 20 - 5 - 15, $konto->rest($ma, 2026));
    }

    public function test_vorschau_liefert_tage_und_rest(): void
    {
        $ma = $this->mitarbeiter();
        $this->settingSetzen('holiday_claim', '30');

        $this->actingAs($ma)->getJson(route('holidays.preview', [
            'employe_id' => $ma->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-23',
        ]))->assertOk()->assertJsonPath('tage', 5);
    }

    public function test_index_und_verwaltung_sind_erreichbar(): void
    {
        $chef = $this->mitarbeiter(['has holidays', 'approve holidays', 'approve all holidays']);
        $ma = $this->mitarbeiter();
        Holiday::factory()->for($ma, 'employe')->pending()->create(['start_date' => '2026-09-21', 'end_date' => '2026-09-22']);

        $this->actingAs($chef)->get(route('holidays.index'))->assertOk()->assertSee('Zu entscheiden');
        $this->actingAs($chef)->get(route('holidays.manage'))->assertOk();
        $this->actingAs($chef)->get(route('holidays.manage', ['tab' => 'konten']))->assertOk();
        $this->actingAs($ma)->get(route('holidays.account', $ma->id))->assertOk();
        $this->actingAs($ma)->get(route('holidays.account', $chef->id))->assertForbidden();
    }

    public function test_index_fuer_mitarbeitende_ohne_unterstellte(): void
    {
        $ma = $this->mitarbeiter();

        $this->actingAs($ma)->get(route('holidays.index'))->assertOk();
    }

    public function test_mein_profil_zeigt_urlaubskonto_und_abwesenheiten_zusammen(): void
    {
        $ma = $this->mitarbeiter();
        $urlaub = Holiday::factory()->for($ma, 'employe')->approved()->create(['start_date' => '2026-08-03', 'end_date' => '2026-08-07', 'days' => 5]);
        // Aus dem Urlaub entstandene Abwesenheit darf nicht doppelt erscheinen
        Absence::factory()->create(['users_id' => $ma->id, 'holiday_id' => $urlaub->id, 'reason' => 'Urlaub', 'start' => '2026-08-03', 'end' => '2026-08-07']);
        Absence::factory()->create(['users_id' => $ma->id, 'reason' => 'Fortbildung', 'start' => '2026-09-10', 'end' => '2026-09-10']);
        Absence::factory()->create(['users_id' => $ma->id, 'reason' => 'Fortbildung alt', 'start' => '2025-03-10', 'end' => '2025-03-10']);

        $this->actingAs($ma)->get(route('self-service.index'))
            ->assertOk()
            ->assertViewHas('konto', fn ($konto) => $konto['genommen'] == 5)
            ->assertViewHas('abwesenheiten', fn ($liste) => $liste->count() === 2
                && $liste->pluck('art')->all() === ['abwesenheit', 'urlaub'])
            ->assertSee('Fortbildung')
            ->assertDontSee('Fortbildung alt')
            ->assertDontSee('Qualifikationen');
    }
}
