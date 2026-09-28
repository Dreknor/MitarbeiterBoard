<?php

namespace Tests\Feature\API\v1;

use App\Models\Klasse;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryEntryPause;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * API v1 – Suche, Klassen-Feed, Aufgaben, Termine, Wiedervorlage und Schüler eines Eintrags.
 * Referenzdatum: Mo 21.09.2026.
 */
class PaedDiaryPlanningApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 08:00:00');
        cache()->flush();
        Http::fake(['ferien-api.de/*' => Http::response([])]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function entry(Klasse $klasse, array $schueler, array $attributes = []): PaedDiaryEntry
    {
        $entry = PaedDiaryEntry::factory()->create(array_merge([
            'klasse_id' => $klasse->id,
            'datum' => '2026-09-21',
            'completed_at' => now(),
        ], $attributes));
        $entry->schueler()->sync(collect($schueler)->pluck('id')->all());

        return $entry;
    }

    // ── Volltextsuche ──────────────────────────────────────────────────

    /** @test */
    public function suche_findet_eintraege_trotz_frageform_umlauten_und_verschluesselung(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $hit = $this->entry($klasse, [$schueler], ['content' => 'Heftiger Streit am Klettergerüst mit Ben', 'datum' => '2026-05-04']);
        $this->entry($klasse, [$schueler], ['content' => 'Klettergerüst allein geübt']);
        $this->entry($klasse, [$schueler], ['content' => 'Streit beim Mittagessen']);

        // Text ist verschlüsselt gespeichert – eine SQL-Suche fände nichts
        $this->assertStringNotContainsString('Klettergerüst', PaedDiaryEntry::whereKey($hit->id)->toBase()->value('content'));

        $this->getJson(self::API . "/students/{$schueler->id}/paed-diary/entries?search=" . urlencode('Wann hatten wir das mit dem Streit am Klettergerust?'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hit->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.search_terms', ['streit', 'klettergerust'])
            ->assertJsonPath('meta.search_truncated', false);
    }

    /** @test */
    public function suche_blendet_fremde_vertrauliche_eintraege_aus(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $this->entry($klasse, [$schueler], ['content' => 'Vertraulich: Streit zuhause', 'dossier_only' => true]);

        $this->getJson(self::API . "/students/{$schueler->id}/paed-diary/entries?search=Streit")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ── Klassen-Feed ───────────────────────────────────────────────────

    /** @test */
    public function klassen_feed_zeigt_eintraege_von_kolleginnen_der_letzten_zwei_wochen(): void
    {
        [$klasse, $schueler] = $this->classWithStudent(['vorname' => 'Mia', 'nachname' => 'Muster']);
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $kollegin = User::factory()->create(['name' => 'Frau Kollegin']);

        $fremd = $this->entry($klasse, [$schueler], ['user_id' => $kollegin->id, 'content' => 'Heute prima gelesen']);
        $eigen = $this->entry($klasse, [$schueler], ['user_id' => $user->id, 'datum' => '2026-09-18']);
        $alt = $this->entry($klasse, [$schueler], ['user_id' => $kollegin->id, 'datum' => '2026-08-01']);

        $response = $this->getJson(self::API . "/classes/{$klasse->id}/paed-diary/entries")->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$fremd->id, $eigen->id], $ids->all());
        $response->assertJsonPath('data.0.created_by_name', 'Frau Kollegin')
            ->assertJsonPath('data.0.students.0.firstname', 'Mia')
            ->assertJsonPath('data.0.students.0.lastname_initial', 'M.');

        $this->getJson(self::API . "/classes/{$klasse->id}/paed-diary/entries?author=others&from_date=2026-01-01")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', $alt->id);
    }

    /** @test */
    public function klassen_feed_fremder_klasse_ist_verboten(): void
    {
        [$klasse] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [Klasse::factory()->create()]);

        $this->getJson(self::API . "/classes/{$klasse->id}/paed-diary/entries")->assertForbidden();
    }

    /** @test */
    public function klassen_feed_mit_suche(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $hit = $this->entry($klasse, [$schueler], ['content' => 'Streit am Klettergerüst', 'datum' => '2026-03-01']);
        $this->entry($klasse, [$schueler], ['content' => 'Etwas anderes']);

        $this->getJson(self::API . "/classes/{$klasse->id}/paed-diary/entries?search=klettergerüst")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hit->id);
    }

    // ── Aufgaben ───────────────────────────────────────────────────────

    /** @test */
    public function aufgabe_fuer_mehrere_schueler_anlegen_und_bearbeiten(): void
    {
        [$klasse, $a] = $this->classWithStudent();
        $b = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $response = $this->postJson(self::API . '/paed-diary/tasks', [
            'schueler_ids' => [$a->id, $b->id],
            'title' => 'Lesepass abgeben',
            'due_date' => '2026-09-25',
        ])->assertCreated()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Lesepass abgeben')
            ->assertJsonPath('data.0.due_date', '2026-09-25')
            ->assertJsonPath('data.0.highlighted', false);

        $taskId = $response->json('data.0.id');
        $this->putJson(self::API . "/paed-diary/tasks/{$taskId}", [
            'title' => 'Lesepass unterschrieben abgeben',
            'due_date' => '2026-09-28',
            'highlighted' => true,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Lesepass unterschrieben abgeben')
            ->assertJsonPath('data.highlighted', true);

        $this->getJson(self::API . "/paed-diary/week?class_id={$klasse->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.tasks');
    }

    /** @test */
    public function aufgabe_fuer_fremden_schueler_ist_verboten_und_titel_pflicht(): void
    {
        [, $fremd] = $this->classWithStudent();
        [$klasse, $eigen] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->postJson(self::API . '/paed-diary/tasks', ['schueler_ids' => [$eigen->id, $fremd->id], 'title' => 'X'])
            ->assertForbidden()
            ->assertJsonPath('forbidden_schueler_ids', [$fremd->id]);
        $this->assertSame(0, PaedDiaryTask::count());

        $this->postJson(self::API . '/paed-diary/tasks', ['schueler_ids' => [$eigen->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $fremdeAufgabe = PaedDiaryTask::factory()->create(['klasse_id' => $fremd->klasse_id, 'schueler_id' => $fremd->id]);
        $this->putJson(self::API . "/paed-diary/tasks/{$fremdeAufgabe->id}", ['title' => 'Y'])->assertForbidden();
    }

    // ── Termine ────────────────────────────────────────────────────────

    /** @test */
    public function elterngespraech_fuer_einen_schueler_anlegen_aendern_und_loeschen(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $open = $this->entry($klasse, [$schueler], ['completed_at' => null, 'datum' => '2026-09-14']);

        $response = $this->postJson(self::API . '/paed-diary/appointments', [
            'title' => 'Elterngespräch',
            'start_date' => '2026-09-23',
            'start_time' => '14:00',
            'end_time' => '14:30',
            'schueler_ids' => [$schueler->id],
            'pause_entries' => true,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Elterngespräch')
            ->assertJsonPath('data.start_time', '14:00')
            ->assertJsonPath('data.schueler_ids', [$schueler->id])
            ->assertJsonPath('data.is_own', true);
        $id = $response->json('data.id');

        // Offene Notiz ist am Termintag pausiert (wie im Web)
        $this->assertTrue(PaedDiaryEntryPause::where('paed_diary_entry_id', $open->id)->whereDate('date', '2026-09-23')->exists());

        $week = $this->getJson(self::API . "/paed-diary/week?class_id={$klasse->id}")->assertOk();
        $week->assertJsonPath('data.appointments.0.id', $id)
            ->assertJsonPath('data.appointments.0.schueler_ids', [$schueler->id])
            ->assertJsonPath('data.appointments.0.is_own', true);

        $this->putJson(self::API . "/paed-diary/appointments/{$id}", [
            'title' => 'Elterngespräch (verschoben)',
            'start_date' => '2026-09-24',
            'schueler_ids' => [$schueler->id],
        ])->assertOk()->assertJsonPath('data.start_date', '2026-09-24')->assertJsonPath('data.start_time', null);

        $this->deleteJson(self::API . "/paed-diary/appointments/{$id}")->assertNoContent();
        $this->assertNull(PaedDiaryAppointment::find($id));
    }

    /** @test */
    public function termin_fuer_fremde_schueler_ist_verboten_und_braucht_ziel(): void
    {
        [, $fremd] = $this->classWithStudent();
        [$klasse] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->postJson(self::API . '/paed-diary/appointments', [
            'title' => 'X', 'start_date' => '2026-09-23', 'schueler_ids' => [$fremd->id],
        ])->assertForbidden();

        $this->postJson(self::API . '/paed-diary/appointments', ['title' => 'X', 'start_date' => '2026-09-23'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schueler_ids');

        $fremderTermin = PaedDiaryAppointment::factory()->create();
        $fremderTermin->schueler()->attach($fremd->id);
        $this->deleteJson(self::API . "/paed-diary/appointments/{$fremderTermin->id}")->assertForbidden();
    }

    /** @test */
    public function einzelnes_vorkommen_eines_serientermins_loeschen(): void
    {
        [$klasse] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $id = $this->postJson(self::API . '/paed-diary/appointments', [
            'title' => 'Förderstunde', 'start_date' => '2026-09-21', 'is_recurring' => true,
            'recurring_type' => 'weekly', 'class_ids' => [$klasse->id],
        ])->assertCreated()->json('data.id');

        $this->deleteJson(self::API . "/paed-diary/appointments/{$id}?mode=only_this&date=2026-09-28")->assertNoContent();

        $this->assertNotNull(PaedDiaryAppointment::find($id));
        $this->getJson(self::API . "/paed-diary/week?class_id={$klasse->id}&week_start=2026-09-28")
            ->assertOk()->assertJsonCount(0, 'data.appointments');
        $this->getJson(self::API . "/paed-diary/week?class_id={$klasse->id}&week_start=2026-10-05")
            ->assertOk()->assertJsonCount(1, 'data.appointments');
    }

    // ── Wiedervorlage ──────────────────────────────────────────────────

    /** @test */
    public function wiedervorlage_blendet_notiz_bis_zum_datum_an_schultagen_aus_und_ist_aufhebbar(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->entry($klasse, [$schueler], ['completed_at' => null, 'datum' => '2026-09-14']);

        // Mi 23.09. → „ab Montag wieder zeigen“ (28.09.): Mi, Do, Fr pausiert
        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/resubmission", [
            'from' => '2026-09-23',
            'resume_on' => '2026-09-28',
        ])->assertOk()->assertJsonPath('data.paused_dates', ['2026-09-23', '2026-09-24', '2026-09-25']);

        $pauses = collect($this->getJson(self::API . "/paed-diary/week?class_id={$klasse->id}")->json('data.pauses'));
        $this->assertEquals(['2026-09-23', '2026-09-24', '2026-09-25'], $pauses->pluck('date')->sort()->values()->all());
        $this->assertEquals(['Wiedervorlage'], $pauses->pluck('reason')->unique()->values()->all());

        // Wiederholung mit kürzerem Zeitraum ersetzt die alte Wiedervorlage
        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/resubmission", [
            'from' => '2026-09-23', 'resume_on' => '2026-09-24',
        ])->assertOk();
        $this->assertSame(1, PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->count());

        // Aufheben
        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/resubmission", ['from' => '2026-09-21', 'resume_on' => null])
            ->assertOk();
        $this->assertSame(0, PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->count());
    }

    /** @test */
    public function wiedervorlage_laesst_andere_pausen_stehen_und_prueft_eingaben(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->entry($klasse, [$schueler], ['completed_at' => null, 'datum' => '2026-09-14']);
        PaedDiaryEntryPause::create(['paed_diary_entry_id' => $entry->id, 'schueler_id' => $schueler->id, 'date' => '2026-09-22', 'reason' => 'Termin']);

        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/resubmission", ['resume_on' => null])->assertOk();
        $this->assertSame(1, PaedDiaryEntryPause::count());

        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/resubmission", ['resume_on' => '2026-09-21'])
            ->assertStatus(422)->assertJsonValidationErrors('resume_on');
        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/resubmission", ['resume_on' => '2027-09-21'])
            ->assertStatus(422)->assertJsonValidationErrors('resume_on');

        $done = $this->entry($klasse, [$schueler]);
        $this->putJson(self::API . "/paed-diary/entries/{$done->id}/resubmission", ['resume_on' => '2026-09-28'])
            ->assertStatus(422);
    }

    // ── Schüler eines Eintrags ─────────────────────────────────────────

    /** @test */
    public function schueler_zum_eintrag_hinzufuegen_und_entfernen(): void
    {
        [$klasse, $a] = $this->classWithStudent();
        $b = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->entry($klasse, [$a], ['completed_at' => null]);

        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/students/{$b->id}")
            ->assertOk()->assertJsonPath('data.schueler_ids', [$a->id, $b->id]);
        // wiederholbar
        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/students/{$b->id}")
            ->assertOk()->assertJsonPath('data.schueler_ids', [$a->id, $b->id]);

        PaedDiaryEntryPause::create(['paed_diary_entry_id' => $entry->id, 'schueler_id' => $a->id, 'date' => '2026-09-22']);
        $this->deleteJson(self::API . "/paed-diary/entries/{$entry->id}/students/{$a->id}")
            ->assertOk()->assertJsonPath('data.schueler_ids', [$b->id]);
        $this->assertSame(0, PaedDiaryEntryPause::where('schueler_id', $a->id)->count());

        // letzter Schüler bleibt
        $this->deleteJson(self::API . "/paed-diary/entries/{$entry->id}/students/{$b->id}")->assertStatus(422);
    }

    /** @test */
    public function schueler_anderer_klasse_kann_nicht_hinzugefuegt_werden(): void
    {
        [$klasse, $a] = $this->classWithStudent();
        [$andere, $fremd] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse, $andere]);
        $entry = $this->entry($klasse, [$a]);

        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/students/{$fremd->id}")
            ->assertStatus(422)->assertJsonValidationErrors('schueler_id');

        [$gesperrt, $x] = $this->classWithStudent();
        $fremderEintrag = $this->entry($gesperrt, [$x]);
        $this->putJson(self::API . "/paed-diary/entries/{$fremderEintrag->id}/students/{$x->id}")->assertForbidden();
    }
}
