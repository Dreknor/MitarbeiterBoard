<?php

namespace Tests\Feature\API\v1;

use App\Models\Klasse;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryClassDayPause;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryEntryPause;
use App\Models\PaedDiarySchuelerAbsence;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * API v1 – Wochenansicht (Kalender) des Pädagogischen Tagebuchs.
 * Referenzwoche: Mo 21.09.2026 – Fr 25.09.2026.
 */
class PaedDiaryWeekApiTest extends ApiTestCase
{
    private const WEEK = '2026-09-21';

    /** Antwort der (gefakten) Ferien-API */
    private array $holidays = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 08:00:00');
        cache()->flush();
        Http::fake(['ferien-api.de/*' => fn () => Http::response($this->holidays)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fakeHolidays(array $holidays): void
    {
        cache()->flush();
        $this->holidays = $holidays;
    }

    private function openEntry(Klasse $klasse, array $schueler, string $date, array $attributes = []): PaedDiaryEntry
    {
        $entry = PaedDiaryEntry::factory()->create(array_merge([
            'klasse_id' => $klasse->id,
            'datum' => $date,
            'completed_at' => null,
        ], $attributes));
        $entry->schueler()->sync(collect($schueler)->pluck('id')->all());

        return $entry;
    }

    // ── Lesen ──────────────────────────────────────────────────────────

    /** @test */
    public function woche_liefert_tage_schueler_notizen_pausen_abwesenheiten_spalten_und_termine(): void
    {
        [$klasse, $schueler] = $this->classWithStudent(['vorname' => 'Mia', 'nachname' => 'Muster']);
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $open = $this->openEntry($klasse, [$schueler], '2026-09-14', ['content' => 'Übt Schleife binden']);
        $done = PaedDiaryEntry::factory()->completed()->create(['klasse_id' => $klasse->id, 'datum' => '2026-09-22']);
        $done->schueler()->sync([$schueler->id]);
        $confidential = PaedDiaryEntry::factory()->dossierOnly()->completed()->create(['klasse_id' => $klasse->id, 'datum' => '2026-09-22']);
        $confidential->schueler()->sync([$schueler->id]);

        PaedDiaryEntryPause::create(['paed_diary_entry_id' => $open->id, 'schueler_id' => $schueler->id, 'date' => '2026-09-23']);
        PaedDiarySchuelerAbsence::create([
            'schueler_id' => $schueler->id, 'klasse_id' => $klasse->id, 'datum' => '2026-09-24', 'marked_by' => $user->id,
        ]);
        $column = PaedDiaryColumn::factory()->create(['klasse_id' => $klasse->id, 'type' => 'ampel', 'name' => 'HA']);
        PaedDiaryColumnValue::create(['paed_diary_column_id' => $column->id, 'schueler_id' => $schueler->id, 'datum' => '2026-09-21', 'value' => '2']);
        $task = PaedDiaryTask::factory()->create(['klasse_id' => $klasse->id, 'schueler_id' => $schueler->id, 'status' => 'open']);
        $appointment = PaedDiaryAppointment::factory()->create(['start_date' => '2026-09-22', 'start_time' => '08:30', 'end_time' => '09:15']);
        $appointment->klassen()->attach($klasse->id);

        $response = $this->getJson(self::API . '/paed-diary/week?class_id=' . $klasse->id . '&week_start=2026-09-23');

        $response->assertOk()
            ->assertJsonPath('data.week_start', self::WEEK)
            ->assertJsonPath('data.week_end', '2026-09-25')
            ->assertJsonCount(5, 'data.days')
            ->assertJsonPath('data.days.0.is_holiday', false)
            ->assertJsonPath('data.students.0.firstname', 'Mia')
            ->assertJsonPath('data.classes.0.id', $klasse->id)
            ->assertJsonPath('data.pauses.0.entry_id', $open->id)
            ->assertJsonPath('data.pauses.0.date', '2026-09-23')
            ->assertJsonPath('data.absences.0.date', '2026-09-24')
            ->assertJsonPath('data.columns.0.type', 'ampel')
            ->assertJsonPath('data.column_values.0.value', '2')
            ->assertJsonPath('data.tasks.0.id', $task->id)
            ->assertJsonPath('data.appointments.0.id', $appointment->id)
            ->assertJsonPath('data.appointments.0.date', '2026-09-22')
            ->assertJsonPath('data.appointments.0.start_time', '08:30')
            ->assertJsonPath('data.appointments.0.class_ids', [$klasse->id]);

        $entries = collect($response->json('data.entries'))->keyBy('id');
        $this->assertTrue($entries->has($open->id), 'Offene Notiz aus der Vorwoche fehlt');
        $this->assertFalse($entries[$open->id]['is_completed']);
        $this->assertSame('Übt Schleife binden', $entries[$open->id]['content']);
        $this->assertTrue($entries->has($done->id));
        $this->assertFalse($entries->has($confidential->id), 'Vertrauliche Einträge gehören nicht in die Wochenansicht');
    }

    /** @test */
    public function offene_notizen_werden_in_den_ferien_automatisch_pausiert(): void
    {
        $this->fakeHolidays([['name' => 'herbstferien', 'start' => '2026-09-24', 'end' => '2026-09-30']]);
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->openEntry($klasse, [$schueler], '2026-09-21');

        $this->getJson(self::API . '/paed-diary/week?class_id=' . $klasse->id . '&week_start=' . self::WEEK)
            ->assertOk()
            ->assertJsonPath('data.days.3.is_holiday', true)
            ->assertJsonPath('data.days.3.holiday_name', 'herbstferien');

        $this->assertDatabaseHas('paed_diary_entry_pauses', ['paed_diary_entry_id' => $entry->id, 'date' => '2026-09-24 00:00:00']);
        $this->assertSame(2, PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->count());
    }

    /** @test */
    public function woche_einer_lerngruppe_vereint_die_klassen(): void
    {
        $a = Klasse::factory()->create();
        $b = Klasse::factory()->create();
        Schueler::factory()->create(['klasse_id' => $a->id]);
        Schueler::factory()->create(['klasse_id' => $b->id]);
        $user = $this->actingAsTeacher(['view paed diary'], [$a, $b]);
        $group = PaedDiaryClassGroup::create(['user_id' => $user->id, 'name' => 'Lernhaus']);
        $group->klassen()->attach([$a->id, $b->id]);

        $this->getJson(self::API . '/paed-diary/week?group_id=' . $group->id)
            ->assertOk()
            ->assertJsonPath('data.group.name', 'Lernhaus')
            ->assertJsonCount(2, 'data.classes')
            ->assertJsonCount(2, 'data.students');
    }

    /** @test */
    public function woche_ohne_rechte_oder_fremde_gruppe_wird_abgelehnt(): void
    {
        [$eigen] = $this->classWithStudent();
        [$fremd] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$eigen]);
        $otherGroup = PaedDiaryClassGroup::create(['user_id' => $this->createTeacher()->id, 'name' => 'Fremd']);

        $this->getJson(self::API . '/paed-diary/week?class_id=' . $fremd->id)->assertForbidden();
        $this->getJson(self::API . '/paed-diary/week?group_id=' . $otherGroup->id)->assertNotFound();
        $this->getJson(self::API . '/paed-diary/week')->assertStatus(422)->assertJsonValidationErrors('class_id');
        $this->getJson(self::API . '/paed-diary/week?class_id=' . $eigen->id . '&week_start=kein-datum')
            ->assertStatus(422)->assertJsonValidationErrors('week_start');
    }

    /** @test */
    public function woche_ohne_tagebuchrecht_ist_verboten(): void
    {
        [$klasse] = $this->classWithStudent();
        $this->actingAsTeacher([], [$klasse]);

        $this->getJson(self::API . '/paed-diary/week?class_id=' . $klasse->id)->assertForbidden();
    }

    // ── Abschließen & Pausieren ────────────────────────────────────────

    /** @test */
    public function notiz_wird_nur_fuer_einen_schueler_abgeschlossen_und_wiederholung_ist_folgenlos(): void
    {
        $klasse = Klasse::factory()->create();
        $a = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $b = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->openEntry($klasse, [$a, $b], '2026-09-21');

        $payload = ['schueler_id' => $a->id, 'date' => '2026-09-21'];
        $this->postJson(self::API . "/paed-diary/entries/{$entry->id}/complete", $payload)
            ->assertOk()->assertJsonPath('data.completed', true);
        // Wiederholung (z. B. aus der Offline-Warteschlange) darf die Notiz von B nicht abschließen
        $this->postJson(self::API . "/paed-diary/entries/{$entry->id}/complete", $payload)->assertOk();

        $entry->refresh();
        $this->assertNull($entry->completed_at);
        $this->assertSame([$b->id], $entry->schueler()->pluck('schueler.id')->all());
        $completed = PaedDiaryEntry::whereNotNull('completed_at')->whereHas('schueler', fn ($q) => $q->where('schueler.id', $a->id))->get();
        $this->assertCount(1, $completed);
    }

    /** @test */
    public function notiz_wird_fuer_alle_abgeschlossen_und_auf_die_tage_verteilt(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->openEntry($klasse, [$schueler], '2026-09-21');

        $this->postJson(self::API . "/paed-diary/entries/{$entry->id}/complete", ['date' => '2026-09-23'])->assertOk();

        $this->assertNotNull($entry->fresh()->completed_at);
        // 21., 22., 23. → drei abgeschlossene Einträge
        $this->assertSame(3, PaedDiaryEntry::whereNotNull('completed_at')->count());
    }

    /** @test */
    public function notiz_wird_an_einem_tag_pausiert_und_wieder_angezeigt(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->openEntry($klasse, [$schueler], '2026-09-21');
        $url = self::API . "/paed-diary/entries/{$entry->id}/pause";

        $this->putJson($url, ['schueler_id' => $schueler->id, 'date' => '2026-09-22', 'paused' => true])
            ->assertOk()->assertJsonPath('data.paused', true);
        $this->putJson($url, ['schueler_id' => $schueler->id, 'date' => '2026-09-22', 'paused' => true])->assertOk();
        $this->assertSame(1, PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->count());

        $this->putJson($url, ['schueler_id' => $schueler->id, 'date' => '2026-09-22', 'paused' => false])
            ->assertOk()->assertJsonPath('data.paused', false);
        $this->assertSame(0, PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->count());
    }

    /** @test */
    public function pause_wird_validiert(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        [, $anderer] = $this->classWithStudent(['klasse_id' => $klasse->id]);
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->openEntry($klasse, [$schueler], '2026-09-22');
        $url = self::API . "/paed-diary/entries/{$entry->id}/pause";

        $this->putJson($url, ['schueler_id' => $schueler->id, 'date' => '2026-09-21', 'paused' => true])
            ->assertStatus(422)->assertJsonValidationErrors('date');
        $this->putJson($url, ['schueler_id' => $anderer->id, 'date' => '2026-09-22', 'paused' => true])
            ->assertStatus(422)->assertJsonValidationErrors('schueler_id');
        $this->putJson($url, ['schueler_id' => $schueler->id, 'date' => '2026-09-22'])
            ->assertStatus(422)->assertJsonValidationErrors('paused');
    }

    /** @test */
    public function fremde_notiz_kann_nicht_pausiert_oder_abgeschlossen_werden(): void
    {
        [$eigen] = $this->classWithStudent();
        [$fremd, $fremdSchueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$eigen]);
        $entry = $this->openEntry($fremd, [$fremdSchueler], '2026-09-21');

        $this->postJson(self::API . "/paed-diary/entries/{$entry->id}/complete")->assertForbidden();
        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}/pause", [
            'schueler_id' => $fremdSchueler->id, 'date' => '2026-09-21', 'paused' => true,
        ])->assertForbidden();
    }

    // ── Abwesenheiten & Tagespausen ────────────────────────────────────

    /** @test */
    public function abwesenheit_pausiert_offene_notizen_und_ist_wiederholbar(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->openEntry($klasse, [$schueler], '2026-09-21');
        $url = self::API . '/paed-diary/absences';

        $this->putJson($url, ['schueler_id' => $schueler->id, 'date' => '2026-09-22', 'absent' => true])
            ->assertOk()->assertJsonPath('data.absent', true)->assertJsonPath('data.paused_entry_ids', [$entry->id]);
        $this->putJson($url, ['schueler_id' => $schueler->id, 'date' => '2026-09-22', 'absent' => true])->assertOk();

        $this->assertSame(1, PaedDiarySchuelerAbsence::where('schueler_id', $schueler->id)->count());
        $this->assertSame(1, PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->count());

        $this->putJson($url, ['schueler_id' => $schueler->id, 'date' => '2026-09-22', 'absent' => false])
            ->assertOk()->assertJsonPath('data.absent', false)->assertJsonPath('data.resumed_entry_ids', [$entry->id]);
        $this->assertSame(0, PaedDiarySchuelerAbsence::count());
        $this->assertSame(0, PaedDiaryEntryPause::count());
    }

    /** @test */
    public function abwesenheit_fuer_fremden_schueler_ist_verboten(): void
    {
        [$eigen] = $this->classWithStudent();
        [, $fremd] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$eigen]);

        $this->putJson(self::API . '/paed-diary/absences', ['schueler_id' => $fremd->id, 'date' => '2026-09-22', 'absent' => true])
            ->assertForbidden();
        $this->putJson(self::API . '/paed-diary/absences', ['schueler_id' => $fremd->id])
            ->assertStatus(422)->assertJsonValidationErrors(['date', 'absent']);
    }

    /** @test */
    public function tagespause_pausiert_alle_offenen_notizen_der_klasse_und_wird_aufgehoben(): void
    {
        $klasse = Klasse::factory()->create();
        $a = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $b = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->openEntry($klasse, [$a, $b], '2026-09-21');
        $url = self::API . '/paed-diary/day-pauses';

        $this->putJson($url, ['class_id' => $klasse->id, 'date' => '2026-09-23', 'paused' => true])
            ->assertOk()->assertJsonPath('data.reason', 'Veranstaltung');
        $this->assertSame(1, PaedDiaryClassDayPause::count());
        $this->assertSame(2, PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->count());

        $this->getJson(self::API . '/paed-diary/week?class_id=' . $klasse->id . '&week_start=' . self::WEEK)
            ->assertJsonPath('data.day_pauses.0.date', '2026-09-23');

        $this->putJson($url, ['class_id' => $klasse->id, 'date' => '2026-09-23', 'paused' => false])->assertOk();
        $this->assertSame(0, PaedDiaryClassDayPause::count());
        $this->assertSame(0, PaedDiaryEntryPause::count());
    }

    /** @test */
    public function tagespause_mit_eigenem_grund_wird_vollstaendig_aufgehoben(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = $this->openEntry($klasse, [$schueler], '2026-09-21');
        $url = self::API . '/paed-diary/day-pauses';

        $this->putJson($url, ['class_id' => $klasse->id, 'date' => '2026-09-23', 'paused' => true, 'reason' => 'Wandertag'])
            ->assertOk()->assertJsonPath('data.reason', 'Wandertag');
        $this->assertSame(1, PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)->count());

        $this->putJson($url, ['class_id' => $klasse->id, 'date' => '2026-09-23', 'paused' => false])->assertOk();
        $this->assertSame(0, PaedDiaryEntryPause::count());
    }

    /** @test */
    public function tagespause_fuer_fremde_klasse_ist_verboten(): void
    {
        [$eigen] = $this->classWithStudent();
        [$fremd] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$eigen]);

        $this->putJson(self::API . '/paed-diary/day-pauses', ['class_id' => $fremd->id, 'date' => '2026-09-23', 'paused' => true])
            ->assertForbidden();
    }

    // ── Spalten & Aufgaben ─────────────────────────────────────────────

    /** @test */
    public function spaltenwert_wird_gesetzt_und_nach_typ_validiert(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $ampel = PaedDiaryColumn::factory()->create(['klasse_id' => $klasse->id, 'type' => 'ampel']);
        $url = self::API . '/paed-diary/column-values';

        $this->putJson($url, ['column_id' => $ampel->id, 'schueler_id' => $schueler->id, 'date' => '2026-09-22', 'value' => '3'])
            ->assertOk()->assertJsonPath('data.value', '3');
        $this->putJson($url, ['column_id' => $ampel->id, 'schueler_id' => $schueler->id, 'date' => '2026-09-22', 'value' => '1'])
            ->assertOk();
        $this->assertSame('1', PaedDiaryColumnValue::sole()->value);

        $this->putJson($url, ['column_id' => $ampel->id, 'schueler_id' => $schueler->id, 'date' => '2026-09-22', 'value' => '7'])
            ->assertStatus(422)->assertJsonValidationErrors('value');
    }

    /** @test */
    public function spaltenwert_fremder_klasse_ist_verboten(): void
    {
        [$eigen] = $this->classWithStudent();
        [$fremd, $fremdSchueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$eigen]);
        $column = PaedDiaryColumn::factory()->create(['klasse_id' => $fremd->id, 'type' => 'boolean']);

        $this->putJson(self::API . '/paed-diary/column-values', [
            'column_id' => $column->id, 'schueler_id' => $fremdSchueler->id, 'date' => '2026-09-22', 'value' => '1',
        ])->assertForbidden();
    }

    /** @test */
    public function aufgabe_wird_geschlossen(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        [$fremd, $fremdSchueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $task = PaedDiaryTask::factory()->create(['klasse_id' => $klasse->id, 'schueler_id' => $schueler->id, 'status' => 'open']);
        $fremdTask = PaedDiaryTask::factory()->create(['klasse_id' => $fremd->id, 'schueler_id' => $fremdSchueler->id, 'status' => 'open']);

        $this->postJson(self::API . "/paed-diary/tasks/{$task->id}/close")->assertOk();
        $this->assertSame('closed', $task->fresh()->status);
        $this->postJson(self::API . "/paed-diary/tasks/{$fremdTask->id}/close")->assertForbidden();
    }
}
