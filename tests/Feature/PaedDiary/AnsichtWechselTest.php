<?php

namespace Tests\Feature\PaedDiary;

use App\Models\GradingDocumentationSession;
use App\Models\GradingQuestion;
use App\Models\GradingStudentAnswer;
use App\Models\GradingSystem;
use App\Models\GradingTeacherAssessment;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use Tests\TestCase;

/**
 * Bisherige und neue Ansicht laufen parallel: Ohne Wahl erscheint die bisherige Ansicht mit
 * Hinweis auf die neue; die Wahl wird pro Person gespeichert und gilt für Woche und Schülerakte.
 */
class AnsichtWechselTest extends TestCase
{
    private function lehrkraft(): array
    {
        $this->withoutVite();
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($klasse->id);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);

        return [$user, $klasse, $schueler];
    }

    /** @test */
    public function erster_aufruf_zeigt_bisherige_ansicht_mit_hinweis(): void
    {
        $this->lehrkraft();

        $this->get('/paed-diary')
            ->assertOk()
            ->assertViewIs('paedDiary.v2.index')
            ->assertSee('Neue Ansicht ausprobieren');
    }

    /** @test */
    public function hinweis_laesst_sich_ausblenden_und_der_umschalter_bleibt(): void
    {
        [$user] = $this->lehrkraft();

        $this->from('/paed-diary')->post(route('paedDiary.settings.darstellung'), ['hinweis_ausgeblendet' => '1'])
            ->assertRedirect('/paed-diary');

        $this->get('/paed-diary')
            ->assertViewIs('paedDiary.v2.index')
            ->assertDontSee('Neue Ansicht ausprobieren')
            ->assertSee('Neue Ansicht');
        $this->assertTrue($user->fresh()->paedDiaryDarstellung()['hinweis_ausgeblendet']);
    }

    /** @test */
    public function wechsel_zur_neuen_ansicht_und_zurueck(): void
    {
        [$user, , $schueler] = $this->lehrkraft();

        $this->from('/paed-diary')->post(route('paedDiary.settings.darstellung'), ['ansicht' => 'neu'])->assertRedirect('/paed-diary');
        $this->get('/paed-diary')->assertOk()->assertViewIs('paedDiary.neu.index')->assertSee('Zur bisherigen Ansicht wechseln');
        $this->get('/paed-diary/schueler/' . $schueler->id)->assertOk()->assertViewIs('paedDiary.neu.schueler');

        $this->post(route('paedDiary.settings.darstellung'), ['ansicht' => 'bisher']);
        $this->get('/paed-diary')->assertViewIs('paedDiary.v2.index');
        $this->get('/paed-diary/schueler/' . $schueler->id)->assertViewIs('paedDiary.schueler');
        // Wer die neue Ansicht einmal gewählt hat, bekommt keinen Hinweis mehr
        $this->assertTrue($user->fresh()->paedDiaryDarstellung()['hinweis_ausgeblendet']);
    }

    /** @test */
    public function darstellung_wird_per_json_gespeichert_und_validiert(): void
    {
        [$user] = $this->lehrkraft();

        $this->postJson(route('paedDiary.settings.darstellung'), ['layout' => 'kompakt', 'schrift' => 'gross', 'kategorie_farben' => false])
            ->assertOk()
            ->assertJsonPath('darstellung.layout', 'kompakt')
            ->assertJsonPath('darstellung.schrift', 'gross')
            ->assertJsonPath('darstellung.kategorie_farben', false)
            ->assertJsonPath('darstellung.ansicht', 'bisher');

        $this->postJson(route('paedDiary.settings.darstellung'), ['layout' => 'riesig'])->assertStatus(422);
        $this->assertSame('kompakt', $user->fresh()->paedDiaryDarstellung()['layout']);
    }

    /** @test */
    public function bewertung_als_matrix_in_der_neuen_ansicht(): void
    {
        [$user, $klasse, $schueler] = $this->lehrkraft();
        $system = GradingSystem::factory()->create();
        $klasse->update(['grading_system_id' => $system->id]);
        $frage = GradingQuestion::create(['grading_system_id' => $system->id, 'question' => 'Ich hole mir Hilfe', 'sort_order' => 1, 'active' => true]);
        $session = GradingDocumentationSession::factory()->asGroup()->create([
            'klasse_id' => $klasse->id, 'grading_system_id' => $system->id, 'user_id' => $user->id,
        ]);
        GradingStudentAnswer::create(['session_id' => $session->id, 'schueler_id' => $schueler->id, 'question_id' => $frage->id, 'self_rating' => 5, 'answered_at' => now()]);

        // Bisherige Ansicht: immer „Schüler für Schüler“
        $this->get(route('gradingDocumentation.teacherAssessment', $session))->assertViewIs('paedDiary.documentation.teacher-assessment');

        $user->forceFill(['paed_diary_darstellung' => ['ansicht' => 'neu', 'bewertung' => 'matrix']])->save();
        $this->get(route('gradingDocumentation.teacherAssessment', $session))
            ->assertOk()
            ->assertViewIs('paedDiary.neu.bewertung-matrix')
            ->assertViewHas('matrix', fn ($m) => $m->first()['selbst'][$frage->id] === 5);
    }

    /** @test */
    public function neue_schuelerakte_liefert_reflexion_fuer_das_spinnennetz(): void
    {
        [$user, $klasse, $schueler] = $this->lehrkraft();
        $user->forceFill(['paed_diary_darstellung' => ['ansicht' => 'neu']])->save();

        $system = GradingSystem::factory()->create();
        $klasse->update(['grading_system_id' => $system->id]);
        $frage = GradingQuestion::create(['grading_system_id' => $system->id, 'question' => 'Ich höre zu', 'sort_order' => 1, 'active' => true]);
        $session = GradingDocumentationSession::factory()->create([
            'type' => 'individual', 'schueler_id' => $schueler->id, 'klasse_id' => $klasse->id,
            'grading_system_id' => $system->id, 'user_id' => User::factory()->create()->id, 'completed_at' => now(),
        ]);
        GradingStudentAnswer::create(['session_id' => $session->id, 'schueler_id' => $schueler->id, 'question_id' => $frage->id, 'self_rating' => 4, 'answered_at' => now()]);
        GradingTeacherAssessment::create(['session_id' => $session->id, 'schueler_id' => $schueler->id, 'question_id' => $frage->id, 'teacher_rating' => 2, 'assessed_at' => now()]);

        $this->get('/paed-diary/schueler/' . $schueler->id)
            ->assertOk()
            ->assertViewIs('paedDiary.neu.schueler')
            ->assertSee('Reflexion als Spinnennetz')
            ->assertViewHas('reflexionen', fn ($r) => $r->count() === 1
                && $r->first()['questions'][0]['self'] === 4
                && $r->first()['questions'][0]['teacher'] === 2);
    }
}
