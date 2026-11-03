<?php

namespace Tests\Feature\Grading;

use App\Models\GradingCoachingNote;
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
 * Graduierung/Reflexion im Parallelbetrieb: Personen mit der bisherigen Ansicht bekommen die
 * bisherigen Views, Personen mit der neuen Ansicht die Views unter paedDiary.neu.graduierung.
 */
class GradingNeueAnsichtTest extends TestCase
{
    private User $user;
    private Klasse $klasse;
    private Schueler $schueler;
    private GradingSystem $system;
    private GradingQuestion $frage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->system = GradingSystem::factory()->create(['name' => 'Reflexion Grundschule']);
        $this->frage = GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => 'Ich höre zu, wenn andere sprechen', 'sort_order' => 1, 'active' => true]);
        GradingQuestion::create(['grading_system_id' => $this->system->id, 'question' => 'Ich hole mir Hilfe', 'sort_order' => 2, 'active' => true]);

        $this->user = $this->actingAsWithPermission('view paed diary');
        $this->klasse = Klasse::factory()->create(['grading_system_id' => $this->system->id]);
        $this->user->paed_klassen()->attach($this->klasse->id);
        $this->schueler = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Mila', 'nachname' => 'Berger']);
    }

    private function ansicht(string $ansicht, string $bewertung = 'schuelerweise'): void
    {
        $this->user->forceFill(['paed_diary_darstellung' => ['ansicht' => $ansicht, 'bewertung' => $bewertung]])->save();
    }

    private function gruppenSession(array $attribute = []): GradingDocumentationSession
    {
        return GradingDocumentationSession::factory()->asGroup()->create(array_merge([
            'user_id' => $this->user->id,
            'klasse_id' => $this->klasse->id,
            'grading_system_id' => $this->system->id,
        ], $attribute));
    }

    private function mitDaten(GradingDocumentationSession $session): GradingDocumentationSession
    {
        GradingStudentAnswer::create(['session_id' => $session->id, 'schueler_id' => $this->schueler->id, 'question_id' => $this->frage->id, 'self_rating' => 5, 'answered_at' => now()]);
        GradingTeacherAssessment::create(['session_id' => $session->id, 'schueler_id' => $this->schueler->id, 'question_id' => $this->frage->id, 'teacher_rating' => 2, 'comment' => 'Hört gut zu', 'assessed_at' => now()]);
        GradingCoachingNote::create(['session_id' => $session->id, 'schueler_id' => $this->schueler->id, 'user_id' => $this->user->id, 'note' => 'Ziel: mehr nachfragen', 'noted_at' => now()]);

        return $session;
    }

    /** @test */
    public function uebersicht_waehlt_die_view_nach_der_ansicht(): void
    {
        $offen = $this->gruppenSession();
        $this->gruppenSession(['completed_at' => now()->subDay()]);

        $this->ansicht('bisher');
        $this->get(route('gradingDocumentation.index'))->assertOk()->assertViewIs('paedDiary.documentation.index');

        $this->ansicht('neu');
        $this->get(route('gradingDocumentation.index'))
            ->assertOk()
            ->assertViewIs('paedDiary.neu.graduierung.index')
            ->assertSee('graduierungStart(', false)
            ->assertSee($this->klasse->name)
            ->assertSee(route('gradingDocumentation.groupSession', $offen->id), false)
            ->assertSee('Wieder öffnen');
    }

    /** @test */
    public function gruppensession_waehlt_die_view_nach_der_ansicht(): void
    {
        $session = $this->mitDaten($this->gruppenSession());

        $this->ansicht('bisher');
        $this->get(route('gradingDocumentation.groupSession', $session))->assertOk()->assertViewIs('paedDiary.documentation.group-session');

        $this->ansicht('neu');
        $this->get(route('gradingDocumentation.groupSession', $session))
            ->assertOk()
            ->assertViewIs('paedDiary.neu.graduierung.gruppe')
            ->assertViewHas('finalisiert')
            ->assertSee('graduierungGruppe(', false)
            ->assertSee('Berger')
            ->assertSee('Ich hole mir Hilfe')
            ->assertSee(route('gradingDocumentation.teacherAssessment', $session->id), false);
    }

    /** @test */
    public function einzelsession_waehlt_die_view_nach_der_ansicht(): void
    {
        $session = $this->mitDaten(GradingDocumentationSession::factory()->create([
            'user_id' => $this->user->id,
            'klasse_id' => $this->klasse->id,
            'grading_system_id' => $this->system->id,
            'schueler_id' => $this->schueler->id,
        ]));

        $this->ansicht('bisher');
        $this->get(route('gradingDocumentation.individualSession', $session))->assertOk()->assertViewIs('paedDiary.documentation.individual-session');

        $this->ansicht('neu');
        $this->get(route('gradingDocumentation.individualSession', $session))
            ->assertOk()
            ->assertViewIs('paedDiary.neu.graduierung.einzel')
            ->assertSee('graduierungEinzel(', false)
            ->assertSee('Mila Berger')
            ->assertSee('Ich hole mir Hilfe');
    }

    /** @test */
    public function einschaetzung_der_lehrkraft_waehlt_view_nach_ansicht_und_modus(): void
    {
        $session = $this->mitDaten($this->gruppenSession());
        $url = route('gradingDocumentation.teacherAssessment', $session);

        $this->ansicht('bisher', 'matrix');
        $this->get($url)->assertOk()->assertViewIs('paedDiary.documentation.teacher-assessment');

        $this->ansicht('neu', 'matrix');
        $this->get($url)->assertOk()->assertViewIs('paedDiary.neu.bewertung-matrix');

        $this->ansicht('neu', 'schuelerweise');
        $antwort = $this->get($url)
            ->assertOk()
            ->assertViewIs('paedDiary.neu.graduierung.bewertung')
            ->assertSee('graduierungBewertung(', false)
            ->assertSee('Mila Berger')
            ->assertSee(route('paedDiary.settings.darstellung'), false)
            ->assertSee('value="matrix"', false);

        $zeile = $antwort->viewData('daten')->firstWhere('id', $this->schueler->id);
        $this->assertSame(5, (int) $zeile['selbst'][$this->frage->id]);
        $this->assertSame(2, (int) $zeile['lehrkraft'][$this->frage->id]);
        $this->assertSame('Hört gut zu', $zeile['kommentare'][$this->frage->id]);
        $this->assertSame('Ziel: mehr nachfragen', $zeile['notiz']);
        $this->assertFalse($zeile['abgeschlossen']);
    }

    /** @test */
    public function schueleruebersicht_waehlt_die_view_nach_der_ansicht(): void
    {
        $this->mitDaten($this->gruppenSession(['completed_at' => now()]));
        $url = route('gradingDocumentation.schuelerDocumentations', $this->schueler);

        $this->ansicht('bisher');
        $this->get($url)->assertOk()->assertViewIs('paedDiary.documentation.schueler-overview');

        $this->ansicht('neu');
        $this->get($url)
            ->assertOk()
            ->assertViewIs('paedDiary.neu.graduierung.schueler')
            ->assertSee('Reflexion Grundschule')
            ->assertSee('Ich hole mir Hilfe')
            ->assertSee('Hört gut zu')
            ->assertSee('Ziel: mehr nachfragen')
            ->assertSee('Δ 3');
    }
}
