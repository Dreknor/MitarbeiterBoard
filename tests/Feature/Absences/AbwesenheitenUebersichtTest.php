<?php

namespace Tests\Feature\Absences;

use App\Models\Absence;
use App\Models\User;
use App\Models\VertretungsplanAbsence;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Personal\Zeit\ZeitTestHelpers;
use Tests\TestCase;

/**
 * Übersicht /absences (Tailwind-Neugestaltung): Filter, Löschen per DELETE, Zusammenführen,
 * Kopplung an den Vertretungsplan und automatische Krankenschein-Pflicht.
 */
class AbwesenheitenUebersichtTest extends TestCase
{
    use ZeitTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00');
        Notification::fake();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function abwesenheit(User $user, array $daten = []): Absence
    {
        return Absence::factory()->create(array_merge([
            'users_id' => $user->id,
            'creator_id' => $user->id,
            'reason' => 'Fortbildung',
            'start' => '2026-10-14',
            'end' => '2026-10-16',
            'showVertretungsplan' => false,
        ], $daten));
    }

    private function leser(string ...$weitere): User
    {
        return $this->mitarbeiter(['view absences', 'view old absences', ...$weitere]);
    }

    public function test_uebersicht_zeigt_standardmaessig_aktuelle_und_geplante(): void
    {
        $ich = $this->leser();
        $anna = $this->mitarbeiter();
        $anna->update(['name' => 'Anna Aktuell']);
        $bert = $this->mitarbeiter();
        $bert->update(['name' => 'Bert Vergangen']);
        $this->abwesenheit($anna);
        $this->abwesenheit($bert, ['start' => '2026-09-01', 'end' => '2026-09-02']);

        $this->actingAs($ich)->get('/absences')
            ->assertOk()
            ->assertSee('14.10.2026 – 16.10.2026')
            ->assertDontSee('01.09.2026 – 02.09.2026')
            ->assertSee('data-tour="abw-neu"', false)
            ->assertSee('data-tour-config', false);

        $this->get('/absences?zeitraum=vergangen')
            ->assertOk()
            ->assertSee('01.09.2026 – 02.09.2026')
            ->assertDontSee('14.10.2026 – 16.10.2026');
    }

    public function test_filter_nach_suche_grund_und_zeitraum(): void
    {
        $ich = $this->leser();
        $anna = $this->mitarbeiter();
        $anna->update(['name' => 'Anna Krank']);
        $bert = $this->mitarbeiter();
        $bert->update(['name' => 'Bert Fortbildung']);
        $this->abwesenheit($anna, ['reason' => 'krank']);
        $this->abwesenheit($bert, ['start' => '2026-10-20', 'end' => '2026-10-21']);

        $this->actingAs($ich)->get('/absences?grund=krank')
            ->assertOk()->assertSee('14.10.2026 – 16.10.2026')->assertDontSee('20.10.2026 – 21.10.2026');

        $this->get('/absences?q=Bert')
            ->assertOk()->assertSee('20.10.2026 – 21.10.2026')->assertDontSee('14.10.2026 – 16.10.2026');

        $this->get('/absences?zeitraum=heute')
            ->assertOk()->assertSee('14.10.2026 – 16.10.2026')->assertDontSee('20.10.2026 – 21.10.2026');

        $this->get('/absences?zeitraum=geplant')
            ->assertOk()->assertSee('20.10.2026 – 21.10.2026')->assertDontSee('14.10.2026 – 16.10.2026');

        $this->get('/absences?zeitraum=quatsch')->assertSessionHasErrors('zeitraum');
    }

    public function test_ohne_recht_auf_uebersicht_kein_zugriff(): void
    {
        $this->actingAs($this->mitarbeiter(['view absences']))->get('/absences')->assertForbidden();
    }

    public function test_loeschen_nur_per_delete(): void
    {
        $ich = $this->leser();
        $eigene = $this->abwesenheit($ich);
        $this->actingAs($ich);

        $this->get("/absences/{$eigene->id}/delete")->assertNotFound();
        $this->assertNotSoftDeleted($eigene);

        $this->delete("/absences/{$eigene->id}")->assertRedirect();
        $this->assertSoftDeleted($eigene);
    }

    public function test_fremde_und_urlaubs_abwesenheiten_nicht_loeschbar(): void
    {
        $ich = $this->leser();
        $andere = $this->mitarbeiter();
        $fremde = $this->abwesenheit($andere);
        $fremde->forceFill(['creator_id' => $andere->id])->save();
        $urlaub = $this->abwesenheit($ich);
        $urlaub->forceFill(['holiday_id' => 999])->saveQuietly();
        $this->actingAs($ich);

        $this->delete("/absences/{$fremde->id}")->assertRedirect();
        $this->delete("/absences/{$urlaub->id}")->assertRedirect();

        $this->assertNotSoftDeleted($fremde);
        $this->assertNotSoftDeleted($urlaub);
    }

    public function test_spaetere_abwesenheit_wird_nicht_verkuerzt(): void
    {
        $ich = $this->leser();
        $spaeter = $this->abwesenheit($ich, ['start' => '2026-10-20', 'end' => '2026-10-25']);
        $this->actingAs($ich);

        $this->post('/absences', [
            'users_id' => $ich->id,
            'reason' => 'Fortbildung',
            'start' => '2026-10-14',
            'end' => '2026-10-15',
        ])->assertRedirect();

        $this->assertSame('2026-10-25', $spaeter->fresh()->end->toDateString());
        $this->assertSame(2, Absence::where('users_id', $ich->id)->count());
    }

    public function test_angrenzende_abwesenheit_wird_verlaengert(): void
    {
        $ich = $this->leser();
        $vorher = $this->abwesenheit($ich, ['start' => '2026-10-12', 'end' => '2026-10-13']);
        $this->actingAs($ich);

        $this->post('/absences', [
            'users_id' => $ich->id,
            'reason' => 'Fortbildung',
            'start' => '2026-10-14',
            'end' => '2026-10-15',
        ])->assertRedirect();

        $this->assertSame(1, Absence::where('users_id', $ich->id)->count());
        $vorher->refresh();
        $this->assertSame('2026-10-12', $vorher->start->toDateString());
        $this->assertSame('2026-10-15', $vorher->end->toDateString());
    }

    public function test_vertretungsplan_eintrag_wird_verknuepft_und_mitgeloescht(): void
    {
        $ich = $this->leser();
        $this->actingAs($ich);

        $this->post('/absences', [
            'users_id' => $ich->id,
            'reason' => 'Fortbildung',
            'start' => '2026-10-15',
            'end' => '2026-10-16',
            'showVertretungsplan' => 1,
        ])->assertRedirect();

        $absence = Absence::where('users_id', $ich->id)->sole();
        $eintrag = VertretungsplanAbsence::where('absence_id', $absence->id)->sole();
        $this->assertSame('2026-10-16', $eintrag->end_date->toDateString());

        // Verlängern aktualisiert den Eintrag statt einen zweiten anzulegen
        $this->post('/absences', [
            'users_id' => $ich->id,
            'reason' => 'Fortbildung',
            'start' => '2026-10-17',
            'end' => '2026-10-19',
            'showVertretungsplan' => 1,
        ])->assertRedirect();
        $this->assertSame(1, VertretungsplanAbsence::count());
        $this->assertSame('2026-10-19', $eintrag->fresh()->end_date->toDateString());

        $this->delete("/absences/{$absence->id}")->assertRedirect();
        $this->assertSame(0, VertretungsplanAbsence::count());
    }

    public function test_ohne_vertretungsplan_kein_eintrag(): void
    {
        $ich = $this->leser();
        $this->actingAs($ich)->post('/absences', [
            'users_id' => $ich->id,
            'reason' => 'Fortbildung',
            'start' => '2026-10-15',
            'end' => '2026-10-16',
            'showVertretungsplan' => 0,
        ])->assertRedirect();

        $this->assertSame(0, VertretungsplanAbsence::count());
    }

    public function test_lange_krankheit_verlangt_krankenschein(): void
    {
        $ich = $this->leser();
        $this->actingAs($ich)->post('/absences', [
            'users_id' => $ich->id,
            'reason' => 'krank',
            'start' => '2026-10-14',
            'end' => '2026-10-23',
        ])->assertRedirect();

        $this->assertTrue(Absence::where('users_id', $ich->id)->sole()->sick_note_required);
    }

    public function test_export_uebernimmt_filter(): void
    {
        \Maatwebsite\Excel\Facades\Excel::fake();
        $ich = $this->leser('export absence');
        $this->abwesenheit($this->mitarbeiter(), ['reason' => 'krank']);
        $this->abwesenheit($this->mitarbeiter());

        $this->actingAs($ich)->get('/absences/export?grund=krank')->assertOk();

        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Abwesenheiten_2026-10-14.xlsx', function (\App\Exports\AbsenceExport $export) {
            return $export->collection()->count() === 1 && $export->collection()->first()->reason === 'krank';
        });
    }
}
