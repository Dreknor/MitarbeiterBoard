<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryElterngespraech;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryEntryPause;
use App\Models\PaedDiaryGoal;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaedDiaryElterngespraechProtokoll;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 * Elterngespräche in der Schülerakte: Planung mit Kalendertermin, Vertraulichkeit, Autospeichern
 * (Versionskonflikt), Abschluss, Übernahme von Vereinbarungen und Protokoll-PDF.
 */
class ElterngespraechTest extends TestCase
{
    use MocksExternalApis;

    private function lehrkraft(Klasse $klasse, string ...$rechte): User
    {
        $user = $this->createUserWithPermission('view paed diary', ...$rechte);
        $user->paed_klassen()->attach($klasse->id);

        return $user;
    }

    private function anlegen(User $user, Schueler $schueler, array $daten = [])
    {
        return $this->actingAs($user)->postJson("paed-diary/schueler/{$schueler->id}/gespraeche", array_merge([
            'date' => Carbon::today()->addDays(3)->toDateString(),
            'start_time' => '15:00',
            'end_time' => '15:45',
            'reason' => 'Lernentwicklung im Halbjahr',
            'parents' => 'Frau Muster',
            'topics' => [['title' => 'Lesen', 'preparation' => 'Lesetagebuch fehlt oft', 'result' => '']],
        ], $daten));
    }

    /** @test */
    public function gespraech_mit_kalendertermin_anlegen_und_pausen_setzen(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $user = $this->lehrkraft($klasse);
        $entry = PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'datum' => Carbon::today()->toDateString(), 'completed_at' => null]);
        $entry->schueler()->attach($schueler->id);

        $antwort = $this->anlegen($user, $schueler, ['calendar' => true, 'pause_entries' => true])
            ->assertCreated()
            ->assertJsonPath('data.status', 'geplant')
            ->assertJsonPath('data.calendar', true)
            ->assertJsonPath('data.topics.0.title', 'Lesen');

        $termin = PaedDiaryAppointment::findOrFail($antwort->json('data.appointment_id'));
        $this->assertSame('Elterngespräch ' . $schueler->vorname . ' ' . $schueler->nachname, $termin->title);
        $this->assertNull($termin->description);
        $this->assertTrue($termin->schueler()->where('schueler.id', $schueler->id)->exists());
        $this->assertTrue(PaedDiaryEntryPause::where('schueler_id', $schueler->id)
            ->whereDate('date', Carbon::today()->addDays(3))->exists());
    }

    /** @test */
    public function termin_verknuepft_schueler_auch_ohne_eigene_klassenzuordnung(): void
    {
        $schueler = Schueler::factory()->create();
        $user = $this->createUserWithPermission('view paed diary', 'view all students');

        $antwort = $this->anlegen($user, $schueler, ['calendar' => true])->assertCreated();

        $termin = PaedDiaryAppointment::findOrFail($antwort->json('data.appointment_id'));
        $this->assertTrue($termin->schueler()->where('schueler.id', $schueler->id)->exists());
    }

    /** @test */
    public function inhalte_werden_verschluesselt_gespeichert(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $id = $this->anlegen($this->lehrkraft($klasse), $schueler)->assertCreated()->json('data.id');

        $roh = DB::table('paed_diary_elterngespraeche')->where('id', $id)->first();
        $this->assertStringNotContainsString('Lernentwicklung', $roh->anlass);
        $this->assertStringNotContainsString('Lesetagebuch', $roh->gespraechspunkte);
        $this->assertSame('Lernentwicklung im Halbjahr', PaedDiaryElterngespraech::find($id)->anlass);
    }

    /** @test */
    public function vertrauliche_gespraeche_sehen_nur_beteiligte_und_sonderrecht(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $ersteller = $this->lehrkraft($klasse);
        $teilnehmerin = $this->lehrkraft($klasse);
        $kollege = $this->lehrkraft($klasse);
        $sonderrecht = $this->lehrkraft($klasse, 'view confidential diary entries');

        $id = $this->anlegen($ersteller, $schueler, ['confidential' => true, 'participant_ids' => [$teilnehmerin->id]])
            ->assertCreated()->json('data.id');
        $this->anlegen($ersteller, $schueler, ['confidential' => false])->assertCreated();

        foreach ([$ersteller, $teilnehmerin, $sonderrecht] as $user) {
            $this->actingAs($user)->getJson("paed-diary/schueler/{$schueler->id}/gespraeche")->assertOk()->assertJsonCount(2, 'data');
        }
        $this->actingAs($kollege)->getJson("paed-diary/schueler/{$schueler->id}/gespraeche")->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($kollege)->putJson("paed-diary/gespraeche/{$id}", ['notes' => 'x'])->assertForbidden();
        $this->actingAs($kollege)->get("paed-diary/gespraeche/{$id}/protokoll.pdf")->assertForbidden();
    }

    /** @test */
    public function fremde_klasse_hat_keinen_zugriff(): void
    {
        $schueler = Schueler::factory()->create();
        $fremd = $this->lehrkraft(Klasse::factory()->create());

        $this->anlegen($fremd, $schueler)->assertForbidden();
        $this->actingAs($fremd)->getJson("paed-diary/schueler/{$schueler->id}/gespraeche")->assertForbidden();
    }

    /** @test */
    public function ohne_tagebuchrecht_sind_die_routen_gesperrt(): void
    {
        $schueler = Schueler::factory()->create();
        $user = $this->createUserWithPermission('view wiki');

        $this->actingAs($user)->getJson("paed-diary/schueler/{$schueler->id}/gespraeche")->assertForbidden();
    }

    /** @test */
    public function veralteter_stand_liefert_konflikt_mit_aktuellem_stand(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $a = $this->lehrkraft($klasse);
        $b = $this->lehrkraft($klasse);
        $daten = $this->anlegen($a, $schueler)->json('data');

        $this->actingAs($b)->putJson("paed-diary/gespraeche/{$daten['id']}", ['notes' => 'Fassung B', 'version' => $daten['version']])
            ->assertOk()->assertJsonPath('data.version', $daten['version'] + 1);

        $konflikt = $this->actingAs($a)->putJson("paed-diary/gespraeche/{$daten['id']}", ['notes' => 'Fassung A', 'version' => $daten['version']])
            ->assertStatus(409)
            ->assertJsonPath('data.notes', 'Fassung B');
        $this->assertStringContainsString($b->name, $konflikt->json('message'));

        // „Meine Fassung behalten“: erneut mit dem neuen Stand speichern
        $this->actingAs($a)->putJson("paed-diary/gespraeche/{$daten['id']}", ['notes' => 'Fassung A', 'version' => $konflikt->json('data.version')])
            ->assertOk()->assertJsonPath('data.notes', 'Fassung A');
    }

    /** @test */
    public function autospeichern_ohne_terminaenderung_laesst_den_termin_unveraendert(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $user = $this->lehrkraft($klasse);
        $daten = $this->anlegen($user, $schueler, ['calendar' => true])->json('data');
        $termin = PaedDiaryAppointment::findOrFail($daten['appointment_id']);
        DB::table('paed_diary_appointments')->where('id', $termin->id)->update(['updated_at' => '2020-01-01 00:00:00']);

        $this->actingAs($user)->putJson("paed-diary/gespraeche/{$daten['id']}", [
            'date' => $daten['date'], 'start_time' => '15:00', 'end_time' => '15:45', 'calendar' => true, 'notes' => 'Neu',
        ])->assertOk();
        $this->assertSame('2020-01-01 00:00:00', (string) DB::table('paed_diary_appointments')->where('id', $termin->id)->value('updated_at'));

        $this->actingAs($user)->putJson("paed-diary/gespraeche/{$daten['id']}", ['start_time' => '16:00', 'end_time' => '16:30'])->assertOk();
        $this->assertSame('16:00', $termin->fresh()->start_time->format('H:i'));
    }

    /** @test */
    public function vereinbarungen_bekommen_ids_fuer_client_schluessel(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $user = $this->lehrkraft($klasse);

        $antwort = $this->anlegen($user, $schueler, ['agreements' => [
            ['key' => 'k1', 'text' => 'Lesetagebuch täglich abzeichnen', 'responsible' => 'eltern', 'due_date' => '2026-12-01'],
            ['key' => 'k2', 'text' => '   '],
        ]])->assertCreated();

        $this->assertCount(1, $antwort->json('data.agreements'));
        $this->assertSame($antwort->json('data.agreements.0.id'), $antwort->json('agreement_keys.k1'));
    }

    /** @test */
    public function abgeschlossenes_gespraech_ist_gesperrt_und_nur_ersteller_oeffnet_wieder(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $ersteller = $this->lehrkraft($klasse);
        $kollege = $this->lehrkraft($klasse);
        $id = $this->anlegen($ersteller, $schueler)->json('data.id');

        $this->actingAs($kollege)->postJson("paed-diary/gespraeche/{$id}/abschliessen")->assertOk()->assertJsonPath('data.status', 'abgeschlossen');
        $this->actingAs($ersteller)->putJson("paed-diary/gespraeche/{$id}", ['notes' => 'später'])->assertForbidden();
        $this->actingAs($kollege)->postJson("paed-diary/gespraeche/{$id}/wieder-oeffnen")->assertForbidden();
        $this->actingAs($ersteller)->postJson("paed-diary/gespraeche/{$id}/wieder-oeffnen")->assertOk()->assertJsonPath('data.status', 'geplant');
    }

    /** @test */
    public function absagen_und_loeschen_entfernen_den_termin(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $user = $this->lehrkraft($klasse);

        $a = $this->anlegen($user, $schueler, ['calendar' => true])->json('data');
        $this->actingAs($user)->postJson("paed-diary/gespraeche/{$a['id']}/absagen")->assertOk()
            ->assertJsonPath('data.status', 'abgesagt')->assertJsonPath('data.calendar', false);
        $this->assertDatabaseMissing('paed_diary_appointments', ['id' => $a['appointment_id']]);

        $b = $this->anlegen($user, $schueler, ['calendar' => true])->json('data');
        $this->actingAs($user)->deleteJson("paed-diary/gespraeche/{$b['id']}")->assertOk();
        $this->assertDatabaseMissing('paed_diary_appointments', ['id' => $b['appointment_id']]);
        $this->assertDatabaseMissing('paed_diary_elterngespraeche', ['id' => $b['id']]);
    }

    /** @test */
    public function vereinbarung_wird_einmalig_als_aufgabe_und_ziel_uebernommen(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $user = $this->lehrkraft($klasse);
        $id = $this->anlegen($user, $schueler, ['agreements' => [['text' => 'Hausaufgabenheft mitbringen', 'due_date' => '2026-11-20']]])
            ->json('data.agreements.0.id');

        $this->actingAs($user)->postJson("paed-diary/gespraeche/vereinbarungen/{$id}/uebernehmen", ['type' => 'aufgabe'])->assertOk();
        $this->actingAs($user)->postJson("paed-diary/gespraeche/vereinbarungen/{$id}/uebernehmen", ['type' => 'aufgabe'])->assertOk();
        $this->actingAs($user)->postJson("paed-diary/gespraeche/vereinbarungen/{$id}/uebernehmen", ['type' => 'ziel'])->assertOk();

        $this->assertSame(1, PaedDiaryTask::where('schueler_id', $schueler->id)->count());
        $aufgabe = PaedDiaryTask::where('schueler_id', $schueler->id)->first();
        $this->assertSame('Hausaufgabenheft mitbringen', $aufgabe->title);
        $this->assertSame('2026-11-20', $aufgabe->due_date->toDateString());
        $this->assertSame(1, PaedDiaryGoal::where('schueler_id', $schueler->id)->count());
    }

    /** @test */
    public function protokoll_pdf_zeigt_briefkopf_aber_keine_internen_notizen(): void
    {
        Setting::updateOrCreate(['setting' => 'school_address'], ['module' => 'paed_diary', 'setting_name' => 'Anschrift', 'type' => 'text', 'value' => 'Musterstraße 1 | 01445 Radebeul']);
        Cache::flush();
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $user = $this->lehrkraft($klasse);
        $id = $this->anlegen($user, $schueler, [
            'preparation_notes' => 'GEHEIME VORBEREITUNG',
            'topics' => [['title' => 'Lesen', 'preparation' => 'INTERNER HINWEIS', 'result' => 'Täglich 15 Minuten lesen']],
        ])->json('data.id');

        $this->actingAs($user)->get("paed-diary/gespraeche/{$id}/protokoll.pdf")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $html = view('pdf.elterngespraech', app(PaedDiaryElterngespraechProtokoll::class)->daten(PaedDiaryElterngespraech::find($id)))->render();
        $this->assertStringContainsString('Täglich 15 Minuten lesen', $html);
        $this->assertStringContainsString('Musterstraße 1', $html);
        $this->assertStringContainsString('01445 Radebeul', $html);
        $this->assertStringContainsString('Entwurf', $html);
        $this->assertStringNotContainsString('GEHEIME VORBEREITUNG', $html);
        $this->assertStringNotContainsString('INTERNER HINWEIS', $html);
    }

    /** @test */
    public function vorbereitung_erscheint_auf_wunsch_im_protokoll(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $user = $this->lehrkraft($klasse);
        $daten = $this->anlegen($user, $schueler, [
            'preparation_notes' => 'ALLGEMEINE VORBEREITUNG',
            'topics' => [['title' => 'Lesen', 'preparation' => 'HINWEIS ZUM PUNKT', 'result' => 'Ergebnis']],
        ])->json('data');
        $this->assertFalse($daten['preparation_in_protocol']);

        $this->actingAs($user)->putJson("paed-diary/gespraeche/{$daten['id']}", ['preparation_in_protocol' => true])
            ->assertOk()->assertJsonPath('data.preparation_in_protocol', true);

        $html = view('pdf.elterngespraech', app(PaedDiaryElterngespraechProtokoll::class)->daten(PaedDiaryElterngespraech::find($daten['id'])))->render();
        $this->assertStringContainsString('ALLGEMEINE VORBEREITUNG', $html);
        $this->assertStringContainsString('HINWEIS ZUM PUNKT', $html);
    }

    /** @test */
    public function dossier_listet_gespraeche_und_respektiert_vertraulichkeit(): void
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $user = $this->lehrkraft($klasse);
        $heute = Carbon::today()->toDateString();
        $this->anlegen($user, $schueler, ['date' => $heute, 'reason' => 'Offen besprochen']);
        $this->anlegen($user, $schueler, ['date' => $heute, 'reason' => 'Sehr vertraulich', 'confidential' => true]);

        $service = app(\App\Services\Api\DossierService::class);
        $mit = $service->build($schueler, $user, \Illuminate\Http\Request::create('/', 'GET', ['include_confidential' => '1']));
        $ohne = $service->build($schueler, $user, \Illuminate\Http\Request::create('/', 'GET', ['include_confidential' => '0']));

        $this->assertCount(2, $mit['parent_meetings']);
        $this->assertSame(['Offen besprochen'], $ohne['parent_meetings']->pluck('reason')->all());
    }
}
