<?php

namespace Tests\Feature\Grading;

use App\Models\GradingDocumentationSession;
use App\Models\GradingJoinCode;
use App\Models\GradingQuestion;
use App\Models\GradingSessionStudent;
use App\Models\GradingStage;
use App\Models\GradingStudentDevice;
use App\Models\GradingSystem;
use App\Models\GradingTeacherAssessment;
use App\Models\Klasse;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use App\Models\SchuelerGradingHistory;
use App\Models\User;
use App\Services\GradingSessionService;
use App\Services\GradingStageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Graduierung: Befunde aus dem Review (QR-Token, öffentliche Seite, Stufenwechsel,
 * Stufenbild-Upload, Lerngruppen, Teilnehmer, Aufräumen, Fortschritt).
 */
class GradingReviewBefundeTest extends TestCase
{
    private function lehrkraftMitKlasse(string ...$extraRechte): array
    {
        $system = GradingSystem::factory()->create();
        $frage1 = GradingQuestion::create(['grading_system_id' => $system->id, 'question' => 'Ich höre zu', 'sort_order' => 1, 'active' => true]);
        $frage2 = GradingQuestion::create(['grading_system_id' => $system->id, 'question' => 'Ich helfe anderen', 'sort_order' => 2, 'active' => true]);
        $user = $this->actingAsWithPermission('view paed diary', ...$extraRechte);
        $klasse = Klasse::factory()->create(['grading_system_id' => $system->id]);
        $user->paed_klassen()->attach($klasse->id);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id, 'vorname' => 'Anna', 'nachname' => 'Arndtmeier']);

        return [$user, $klasse, $schueler, $system, $frage1, $frage2];
    }

    private function gruppenSession(User $user, Klasse $klasse, GradingSystem $system, array $attribute = []): GradingDocumentationSession
    {
        return GradingDocumentationSession::factory()->asGroup()->create(array_merge([
            'user_id' => $user->id,
            'klasse_id' => $klasse->id,
            'grading_system_id' => $system->id,
        ], $attribute));
    }

    private function token(GradingDocumentationSession $session, Schueler $schueler): string
    {
        $token = Str::random(64);
        DB::table('grading_student_tokens')->insert([
            'token' => $token, 'session_id' => $session->id, 'schueler_id' => $schueler->id,
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    // ── G9: QR-Token ────────────────────────────────────────────────────

    /** @test */
    public function qr_token_wird_je_session_und_schueler_wiederverwendet(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        $session = $this->gruppenSession($user, $klasse, $system);

        $erster = $this->postJson(route('gradingDocumentation.generateQRToken', [$session, $schueler]))->assertOk()->json('token');
        $zweiter = $this->postJson(route('gradingDocumentation.generateQRToken', [$session, $schueler]))->assertOk()->json('token');

        $this->assertSame($erster, $zweiter);
        $this->assertSame(1, DB::table('grading_student_tokens')->count());
    }

    /** @test */
    public function qr_token_fremde_session_und_fremder_schueler_werden_abgewiesen(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        $session = $this->gruppenSession($user, $klasse, $system);
        $fremderSchueler = Schueler::factory()->create();

        $this->postJson(route('gradingDocumentation.generateQRToken', [$session, $fremderSchueler]))
            ->assertForbidden();

        // Fremde Lehrkraft: 403 (keine verschluckte AuthorizationException / kein 500)
        $this->actingAsWithPermission('view paed diary');
        $this->postJson(route('gradingDocumentation.generateQRToken', [$session, $schueler]))
            ->assertForbidden()
            ->assertJsonMissing(['message' => 'Fehler beim Generieren des Tokens']);

        $this->assertSame(0, DB::table('grading_student_tokens')->count());
    }

    // ── G11 / G17 / G24: öffentliche QR-Seite ───────────────────────────

    /** @test */
    public function oeffentliche_seite_zeigt_nur_vorname_und_neutralen_titel(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        $session = $this->gruppenSession($user, $klasse, $system);
        $token = $this->token($session, $schueler);

        $this->get(route('gradingDocumentation.publicStudentSession', $token))
            ->assertOk()
            ->assertSee('<title>Selbsteinschätzung</title>', false)
            ->assertSee('Anna')
            ->assertDontSee('Arndtmeier')
            ->assertSee(json_encode('Ich höre zu'), false)
            ->assertSee('Klappt fast immer');
    }

    /** @test */
    public function oeffentliche_seite_zeigt_bei_abgeschlossener_session_einen_hinweis(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        $session = $this->gruppenSession($user, $klasse, $system);
        $token = $this->token($session, $schueler);

        // Einzelner Schüler abgeschlossen (z. B. per App)
        GradingSessionStudent::create(['session_id' => $session->id, 'schueler_id' => $schueler->id, 'finalized_at' => now(), 'finalized_by' => $user->id]);

        $this->get(route('gradingDocumentation.publicStudentSession', $token))
            ->assertOk()
            ->assertSee('Diese Selbsteinschätzung ist abgeschlossen')
            ->assertDontSee(json_encode('Ich höre zu'), false);
    }

    /** @test */
    public function oeffentliche_seite_zeigt_im_modus_by_question_nur_freigegebene_fragen(): void
    {
        [$user, $klasse, $schueler, $system, $frage1] = $this->lehrkraftMitKlasse();
        $session = $this->gruppenSession($user, $klasse, $system, [
            'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_QUESTION,
        ]);
        $token = $this->token($session, $schueler);

        $this->get(route('gradingDocumentation.publicStudentSession', $token))
            ->assertOk()
            ->assertSee('noch keine Frage freigegeben')
            ->assertDontSee(json_encode('Ich höre zu'), false);

        $session->update(['current_question_id' => $frage1->id]);

        $this->get(route('gradingDocumentation.publicStudentSession', $token))
            ->assertOk()
            ->assertSee(json_encode('Ich höre zu'), false)
            ->assertDontSee('Ich helfe anderen');
    }

    // ── G12: Stufenwechsel ──────────────────────────────────────────────

    /** @test */
    public function gleiche_stufe_erzeugt_weder_historie_noch_tagebucheintrag(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        $stufe = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Forscher', 'sort_order' => 1]);
        $schueler->update(['grading_stage_id' => $stufe->id]);

        $ergebnis = app(GradingStageService::class)->changeStage($schueler->fresh(), $stufe, $user, $klasse);

        $this->assertNull($ergebnis);
        $this->assertSame(0, SchuelerGradingHistory::count());
        $this->assertSame(0, PaedDiaryEntry::count());
    }

    /** @test */
    public function fremder_tagebucheintrag_wird_nicht_verknuepft(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        $stufe = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Forscher', 'sort_order' => 1]);
        $anderer = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $fremderEintrag = PaedDiaryEntry::create(['klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => now()->toDateString(), 'content' => 'Anderer']);
        $fremderEintrag->schueler()->sync([$anderer->id]);

        $history = app(GradingStageService::class)->changeStage($schueler, $stufe, $user, $klasse, $fremderEintrag->id);

        $this->assertNotNull($history);
        $this->assertNotSame($fremderEintrag->id, (int) $history->paed_diary_entry_id);
        $this->assertTrue(PaedDiaryEntry::find($history->paed_diary_entry_id)->schueler->contains($schueler));

        // Eigener Eintrag des Schülers wird verknüpft, kein zusätzlicher Auto-Eintrag
        $stufe2 = GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Entdecker', 'sort_order' => 2]);
        $eigenerEintrag = PaedDiaryEntry::create(['klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => now()->toDateString(), 'content' => 'Eigener']);
        $eigenerEintrag->schueler()->sync([$schueler->id]);
        $anzahl = PaedDiaryEntry::count();

        $history2 = app(GradingStageService::class)->changeStage($schueler->fresh(), $stufe2, $user, $klasse, $eigenerEintrag->id);

        $this->assertSame($eigenerEintrag->id, (int) $history2->paed_diary_entry_id);
        $this->assertSame($anzahl, PaedDiaryEntry::count());
    }

    // ── G15: Stufenbild-Upload ──────────────────────────────────────────

    /** @test */
    public function stufenbild_endung_wird_aus_dem_inhalt_bestimmt(): void
    {
        $this->actingAsWithPermission('view paed diary', 'manage grading systems');
        $system = GradingSystem::factory()->create();

        // Echte PNG-Datei mit falscher Endung: gespeichert wird die Endung des Inhalts (Fake-Dateien leiten den MIME-Typ aus dem Namen ab)
        $tmp = tempnam(sys_get_temp_dir(), 'stufe');
        $bild = imagecreatetruecolor(4, 4);
        imagepng($bild, $tmp);
        imagedestroy($bild);

        $this->post(route('admin.grading.stage.store', $system), [
            'name' => 'Forscher',
            'image' => new UploadedFile($tmp, 'bild.gif', 'image/gif', null, true),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $pfad = GradingStage::where('name', 'Forscher')->value('image');
        try {
            $this->assertMatchesRegularExpression('#^img/stages/[^/]+\.png$#', $pfad);
        } finally {
            @unlink(public_path($pfad));
        }

        $this->post(route('admin.grading.stage.store', $system), [
            'name' => 'Svg',
            'image' => UploadedFile::fake()->createWithContent('bild.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ])->assertSessionHasErrors('image');
        $this->assertDatabaseMissing('grading_stages', ['name' => 'Svg']);
    }

    // ── G20: Lerngruppe ─────────────────────────────────────────────────

    /** @test */
    public function gruppensession_nur_mit_eigener_lerngruppe_die_die_klasse_enthaelt(): void
    {
        [$user, $klasse] = $this->lehrkraftMitKlasse();

        $fremdeGruppe = PaedDiaryClassGroup::create(['user_id' => User::factory()->create()->id, 'name' => 'Fremd']);
        $fremdeGruppe->klassen()->attach($klasse->id);
        $this->postJson(route('gradingDocumentation.startGroup'), ['klasse_id' => $klasse->id, 'group_id' => $fremdeGruppe->id])
            ->assertStatus(422);

        $eigeneOhneKlasse = PaedDiaryClassGroup::create(['user_id' => $user->id, 'name' => 'Leer']);
        $this->postJson(route('gradingDocumentation.startGroup'), ['klasse_id' => $klasse->id, 'group_id' => $eigeneOhneKlasse->id])
            ->assertStatus(422);

        $eigene = PaedDiaryClassGroup::create(['user_id' => $user->id, 'name' => 'Eigene']);
        $eigene->klassen()->attach($klasse->id);
        $this->postJson(route('gradingDocumentation.startGroup'), ['klasse_id' => $klasse->id, 'group_id' => $eigene->id])
            ->assertOk();

        $this->assertSame(1, GradingDocumentationSession::count());
        $this->assertSame($eigene->id, (int) GradingDocumentationSession::value('group_id'));
    }

    // ── G10: Teilnehmer & Abschluss ─────────────────────────────────────

    /** @test */
    public function lehrereinschaetzung_zeigt_nur_teilnehmer_und_abschluss_setzt_finalized_at(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        Schueler::factory()->create(['klasse_id' => $klasse->id, 'vorname' => 'Nichtdabei', 'nachname' => 'Xaver']);
        $session = $this->gruppenSession($user, $klasse, $system);
        GradingSessionStudent::create(['session_id' => $session->id, 'schueler_id' => $schueler->id]);

        $this->get(route('gradingDocumentation.teacherAssessment', $session))
            ->assertOk()
            ->assertSee('Anna')
            ->assertDontSee('Nichtdabei')
            ->assertDontSee(':root {', false);

        $this->get(route('gradingDocumentation.groupSession', $session))
            ->assertOk()
            ->assertSee('Anna')
            ->assertDontSee('Nichtdabei');

        $this->postJson(route('gradingDocumentation.completeSession', $session))->assertOk();

        $this->assertNotNull($session->fresh()->completed_at);
        $this->assertNotNull(GradingSessionStudent::where('session_id', $session->id)->value('finalized_at'));
        $this->assertSame($user->id, (int) GradingSessionStudent::where('session_id', $session->id)->value('finalized_by'));
    }

    // ── G21: Aufräumen ──────────────────────────────────────────────────

    /** @test */
    public function cleanup_entfernt_abgelaufene_codes_und_verwaiste_geraete_samt_tokens(): void
    {
        [$user, $klasse, $schueler, $system] = $this->lehrkraftMitKlasse();
        $offen = $this->gruppenSession($user, $klasse, $system);
        $abgeschlossen = $this->gruppenSession($user, $klasse, $system, ['completed_at' => now()]);

        $abgelaufen = GradingJoinCode::create(['session_id' => $offen->id, 'schueler_id' => $schueler->id, 'code' => 'AAAAAA', 'expires_at' => now()->subMinute()]);
        $gueltig = GradingJoinCode::create(['session_id' => $offen->id, 'schueler_id' => $schueler->id, 'code' => 'BBBBBB', 'expires_at' => now()->addHour()]);
        $codeAbgeschlossen = GradingJoinCode::create(['session_id' => $abgeschlossen->id, 'schueler_id' => $schueler->id, 'code' => 'CCCCCC', 'expires_at' => now()->addHour()]);

        $geraet = fn (GradingJoinCode $code) => tap(GradingStudentDevice::create([
            'join_code_id' => $code->id, 'session_id' => $code->session_id, 'schueler_id' => $code->schueler_id,
            'device_name' => 'iPad', 'expires_at' => $code->expires_at,
        ]), fn ($d) => $d->createToken('iPad', [$d->ability()], $code->expires_at));

        $altGeraet = $geraet($abgelaufen);
        $aktivGeraet = $geraet($gueltig);
        $verwaistGeraet = $geraet($codeAbgeschlossen);

        $this->artisan('grading:cleanup-tokens')->assertSuccessful();

        $this->assertModelMissing($abgelaufen);
        $this->assertModelExists($gueltig);
        $this->assertModelMissing($altGeraet);
        $this->assertModelMissing($verwaistGeraet);
        $this->assertModelExists($aktivGeraet);
        $this->assertSame([$aktivGeraet->id], PersonalAccessToken::where('tokenable_type', GradingStudentDevice::class)->pluck('tokenable_id')->map(fn ($id) => (int) $id)->all());
    }

    // ── G25: Fortschritt gesammelt ──────────────────────────────────────

    /** @test */
    public function gesammelter_fortschritt_entspricht_dem_einzelnen(): void
    {
        [$user, $klasse, $schueler, $system, $frage1, $frage2] = $this->lehrkraftMitKlasse();
        $ben = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        $ganzeKlasse = $this->gruppenSession($user, $klasse, $system);
        $mitListe = $this->gruppenSession($user, $klasse, $system);
        GradingSessionStudent::create(['session_id' => $mitListe->id, 'schueler_id' => $ben->id, 'finalized_at' => now()]);
        $abgeschlossen = $this->gruppenSession($user, $klasse, $system, ['completed_at' => now()]);
        $einzel = GradingDocumentationSession::factory()->create([
            'user_id' => $user->id, 'klasse_id' => $klasse->id, 'grading_system_id' => $system->id, 'schueler_id' => $schueler->id,
        ]);

        foreach ([$ganzeKlasse, $mitListe, $abgeschlossen, $einzel] as $session) {
            GradingTeacherAssessment::create(['session_id' => $session->id, 'schueler_id' => $schueler->id, 'question_id' => $frage1->id, 'teacher_rating' => 3]);
            GradingTeacherAssessment::create(['session_id' => $session->id, 'schueler_id' => $ben->id, 'question_id' => $frage2->id, 'teacher_rating' => 4]);
        }

        $service = app(GradingSessionService::class);
        $sessions = GradingDocumentationSession::all();
        $gesammelt = $service->progressFuer($sessions);

        foreach ($sessions as $session) {
            $this->assertSame($service->progress($session), $gesammelt[$session->id], "Session {$session->id}");
        }
    }
}
