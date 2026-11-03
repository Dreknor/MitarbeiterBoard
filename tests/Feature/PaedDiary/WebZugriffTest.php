<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\User;
use App\Services\PaedDiaryAppointmentService;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 * Web-Endpunkte des Tagebuchs prüfen Zugriffe wie die API v1 (StudentPolicy / PaedDiaryPolicy):
 * Abwesenheit, Bearbeiten/Abschließen/Löschen von Einträgen, Aufgaben nach Klassenwechsel, Termine.
 */
class WebZugriffTest extends TestCase
{
    use MocksExternalApis;

    private function lehrkraftMitKlassen(int $anzahl = 1): array
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klassen = Klasse::factory()->count($anzahl)->create();
        foreach ($klassen as $klasse) {
            $user->paed_klassen()->attach($klasse->id);
        }

        return [$user, $klassen];
    }

    private function eintrag(Klasse $klasse, Schueler $schueler, array $attribute = []): PaedDiaryEntry
    {
        $entry = PaedDiaryEntry::factory()->create(array_merge([
            'klasse_id' => $klasse->id,
            'datum' => Carbon::today()->subDays(2)->toDateString(),
            'completed_at' => null,
        ], $attribute));
        $entry->schueler()->attach($schueler->id);

        return $entry;
    }

    /** @test */
    public function abwesenheit_fuer_fremde_klasse_ist_verboten(): void
    {
        $this->lehrkraftMitKlassen();
        $fremd = Schueler::factory()->create(['klasse_id' => Klasse::factory()->create()->id]);

        $this->postJson('paed-diary/absence', [
            'schueler_id' => $fremd->id,
            'klasse_id' => $fremd->klasse_id,
            'datum' => Carbon::today()->toDateString(),
        ])->assertForbidden();

        $this->assertDatabaseMissing('paed_diary_schueler_absences', ['schueler_id' => $fremd->id]);
    }

    /** @test */
    public function abwesenheit_nutzt_die_klasse_des_schuelers(): void
    {
        [, $klassen] = $this->lehrkraftMitKlassen();
        $schueler = Schueler::factory()->create(['klasse_id' => $klassen[0]->id]);
        $andere = Klasse::factory()->create();

        $this->postJson('paed-diary/absence', [
            'schueler_id' => $schueler->id,
            'klasse_id' => $andere->id,
            'datum' => Carbon::today()->toDateString(),
        ])->assertOk()->assertJson(['absent' => true]);

        $this->assertDatabaseHas('paed_diary_schueler_absences', ['schueler_id' => $schueler->id, 'klasse_id' => $klassen[0]->id]);
        $this->assertDatabaseMissing('paed_diary_schueler_absences', ['klasse_id' => $andere->id]);
    }

    /** @test */
    public function eintrag_der_zweiten_klasse_ist_im_kopplungsmodus_bearbeitbar(): void
    {
        [, $klassen] = $this->lehrkraftMitKlassen(2);
        $schueler = Schueler::factory()->create(['klasse_id' => $klassen[1]->id]);
        $entry = $this->eintrag($klassen[1], $schueler);

        // Das Frontend sendet im Kopplungsmodus immer die erste Klasse der Gruppe
        $this->postJson("paed-diary/entry/{$entry->id}", [
            'klasse_id' => $klassen[0]->id,
            'date' => $entry->datum->toDateString(),
            'content' => 'Geändert',
            'schueler_ids' => [$schueler->id],
        ])->assertOk();

        $this->assertSame('Geändert', $entry->fresh()->content);
    }

    /** @test */
    public function fremder_vertraulicher_eintrag_ist_nicht_bearbeitbar_oder_loeschbar(): void
    {
        [, $klassen] = $this->lehrkraftMitKlassen();
        $schueler = Schueler::factory()->create(['klasse_id' => $klassen[0]->id]);
        $entry = $this->eintrag($klassen[0], $schueler, ['dossier_only' => true, 'user_id' => User::factory()->create()->id]);

        $this->postJson("paed-diary/entry/{$entry->id}", [
            'klasse_id' => $klassen[0]->id,
            'date' => $entry->datum->toDateString(),
            'content' => 'Überschrieben',
            'schueler_ids' => [$schueler->id],
        ])->assertForbidden();
        $this->deleteJson("paed-diary/entry/{$entry->id}")->assertForbidden();

        $this->assertNotSame('Überschrieben', $entry->fresh()->content);
    }

    /** @test */
    public function eintraege_anderer_autoren_loescht_nur_der_autor(): void
    {
        [$user, $klassen] = $this->lehrkraftMitKlassen();
        $schueler = Schueler::factory()->create(['klasse_id' => $klassen[0]->id]);
        $fremd = $this->eintrag($klassen[0], $schueler, ['user_id' => User::factory()->create()->id]);
        $eigen = $this->eintrag($klassen[0], $schueler, ['user_id' => $user->id]);

        $this->deleteJson("paed-diary/entry/{$fremd->id}")->assertForbidden();
        $this->deleteJson("paed-diary/entry/{$eigen->id}")->assertOk();

        $this->assertDatabaseHas('paed_diary_entries', ['id' => $fremd->id]);
        $this->assertDatabaseMissing('paed_diary_entries', ['id' => $eigen->id]);
    }

    /** @test */
    public function vorjahresnotiz_laesst_sich_ueber_die_aktuelle_klasse_abschliessen(): void
    {
        [, $klassen] = $this->lehrkraftMitKlassen();
        $alteKlasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klassen[0]->id]);
        $entry = $this->eintrag($alteKlasse, $schueler);

        $this->postJson("paed-diary/entry/{$entry->id}/complete", [])->assertOk();
        $this->assertNotNull($entry->fresh()->completed_at);

        $task = PaedDiaryTask::factory()->create(['klasse_id' => $alteKlasse->id, 'schueler_id' => $schueler->id]);
        $this->postJson("paed-diary/task/{$task->id}/close")->assertOk();
    }

    /** @test */
    public function termin_bearbeiten_behaelt_klassen_ohne_zugriff(): void
    {
        [$user, $klassen] = $this->lehrkraftMitKlassen();
        $fremdeKlasse = Klasse::factory()->create();
        $fremderSchueler = Schueler::factory()->create(['klasse_id' => $fremdeKlasse->id]);

        $termin = PaedDiaryAppointment::factory()->create(['user_id' => User::factory()->create()->id]);
        $termin->klassen()->attach([$klassen[0]->id, $fremdeKlasse->id]);
        $termin->schueler()->attach($fremderSchueler->id);

        app(PaedDiaryAppointmentService::class)->syncRelations($termin, [
            'klasse_ids' => [$klassen[0]->id],
            'schueler_ids' => [],
        ], $user);

        $this->assertEqualsCanonicalizing([$klassen[0]->id, $fremdeKlasse->id], $termin->klassen()->pluck('klassen.id')->all());
        $this->assertSame([$fremderSchueler->id], $termin->schueler()->pluck('schueler.id')->all());

        // Eigene Klasse darf entfernt werden
        app(PaedDiaryAppointmentService::class)->syncRelations($termin, ['klasse_ids' => []], $user);
        $this->assertSame([$fremdeKlasse->id], $termin->klassen()->pluck('klassen.id')->all());
    }
}
