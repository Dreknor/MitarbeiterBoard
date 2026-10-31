<?php

namespace Tests\Feature\API\v1;

use App\Models\PaedDiaryElterngespraech;
use App\Models\PaedDiaryTask;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * API v1 – Elterngespräche: gleiche Logik und Rechte wie im Web (PaedDiaryElterngespraechService).
 */
class ParentMeetingApiTest extends ApiTestCase
{
    private function anlegen(int $schuelerId, array $daten = [])
    {
        return $this->postJson(self::API . "/students/{$schuelerId}/parent-meetings", array_merge([
            'date' => now()->addDays(2)->toDateString(),
            'reason' => 'Halbjahresgespräch',
            'agreements' => [['key' => 'a', 'text' => 'Leseübungen täglich', 'responsible' => 'eltern']],
        ], $daten));
    }

    /** @test */
    public function lehrkraft_legt_an_liest_aendert_und_schliesst_ab(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $neu = $this->anlegen($schueler->id)->assertCreated()
            ->assertJsonPath('data.reason', 'Halbjahresgespräch')
            ->assertJsonPath('data.agreements.0.responsible', 'eltern')
            ->json('data');

        $this->getJson(self::API . "/students/{$schueler->id}/parent-meetings")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::API . "/parent-meetings/{$neu['id']}")->assertOk()->assertJsonPath('data.can.update', true);

        $this->putJson(self::API . "/parent-meetings/{$neu['id']}", ['notes' => 'Gutes Gespräch', 'version' => $neu['version']])
            ->assertOk()->assertJsonPath('data.notes', 'Gutes Gespräch')->assertJsonPath('data.reason', 'Halbjahresgespräch');
        $this->putJson(self::API . "/parent-meetings/{$neu['id']}", ['notes' => 'Veraltet', 'version' => $neu['version']])
            ->assertStatus(409)->assertJsonPath('data.notes', 'Gutes Gespräch');

        $this->postJson(self::API . "/parent-meetings/{$neu['id']}/complete")->assertOk()->assertJsonPath('data.status', 'abgeschlossen');
        $this->putJson(self::API . "/parent-meetings/{$neu['id']}", ['notes' => 'Nachtrag'])->assertForbidden();
        $this->postJson(self::API . "/parent-meetings/{$neu['id']}/reopen")->assertOk()->assertJsonPath('data.status', 'geplant');
    }

    /** @test */
    public function validierung_und_fremde_klasse(): void
    {
        [, $schueler] = $this->classWithStudent();
        [$eigeneKlasse, $eigener] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$eigeneKlasse]);

        $this->anlegen($schueler->id)->assertForbidden();
        $this->anlegen($eigener->id, ['date' => null])->assertStatus(422)->assertJsonValidationErrors('date');
        $this->anlegen($eigener->id, ['start_time' => '16:00', 'end_time' => '15:00'])->assertStatus(422)->assertJsonValidationErrors('end_time');
        $this->anlegen($eigener->id, ['form' => 'brief'])->assertStatus(422)->assertJsonValidationErrors('form');
    }

    /** @test */
    public function vertrauliches_gespraech_ist_fuer_kolleg_innen_unsichtbar(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $id = $this->anlegen($schueler->id, ['confidential' => true])->json('data.id');

        Sanctum::actingAs($this->createTeacher(['view paed diary'], [$klasse]), ['paed-app']);
        $this->getJson(self::API . "/students/{$schueler->id}/parent-meetings")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::API . "/parent-meetings/{$id}")->assertForbidden();
        $this->get(self::API . "/parent-meetings/{$id}/protocol.pdf")->assertForbidden();
    }

    /** @test */
    public function vereinbarung_uebernehmen_nur_ueber_das_eigene_gespraech(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $a = $this->anlegen($schueler->id)->json('data');
        $b = $this->anlegen($schueler->id)->json('data');
        $vereinbarung = $a['agreements'][0]['id'];

        $this->postJson(self::API . "/parent-meetings/{$b['id']}/agreements/{$vereinbarung}/transfer", ['type' => 'task'])->assertNotFound();
        $this->postJson(self::API . "/parent-meetings/{$a['id']}/agreements/{$vereinbarung}/transfer", ['type' => 'task'])
            ->assertOk()->assertJsonPath('data.id', $vereinbarung);
        $this->assertSame(1, PaedDiaryTask::where('schueler_id', $schueler->id)->count());
    }

    /** @test */
    public function idempotency_key_legt_nicht_doppelt_an(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $key = (string) Str::uuid();

        $this->withHeader('Idempotency-Key', $key);
        $this->anlegen($schueler->id)->assertCreated();
        $this->anlegen($schueler->id)->assertCreated()->assertHeader('Idempotent-Replayed');

        $this->assertSame(1, PaedDiaryElterngespraech::where('schueler_id', $schueler->id)->count());
    }

    /** @test */
    public function pdf_und_loeschen(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $id = $this->anlegen($schueler->id)->json('data.id');

        $this->get(self::API . "/parent-meetings/{$id}/protocol.pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="Elterngespraech_' . Str::ascii($schueler->nachname) . '_' . Str::ascii($schueler->vorname) . '_' . now()->addDays(2)->toDateString() . '.pdf"');

        $this->deleteJson(self::API . "/parent-meetings/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('paed_diary_elterngespraeche', ['id' => $id]);
    }
}
