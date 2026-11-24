<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\Group;
use App\Models\GroupTaskUser;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use App\Notifications\AufgabeZugewiesen;
use App\Services\Tasks\ThemeTaskService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Karte → Aufgabe im verknüpften Thema und Rückkopplung des Aufgabenstatus auf die Spalte
 * (ErgebnisboardAufgabenService, ErgebnisKarteAufgabeObserver).
 */
class ErgebnisboardAufgabenTest extends TestCase
{
    use ErgebnisboardTestHelfer;

    private User $autor;

    private User $mitglied;

    private Group $gruppe;

    private Theme $thema;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();

        $this->autor = $this->nutzer(['view result boards', 'create result boards']);
        $this->mitglied = $this->nutzer();
        $this->gruppe = Group::factory()->create();
        $this->inGruppe($this->autor, $this->gruppe);
        $this->inGruppe($this->mitglied, $this->gruppe);
        $this->thema = Theme::factory()->create(['group_id' => $this->gruppe->id, 'creator_id' => $this->autor->id]);
    }

    private function boardMitThema(string $vorlage = 'kanban', array $attribute = []): Ergebnisboard
    {
        $board = $this->board($this->autor, $vorlage, array_merge(['theme_id' => $this->thema->id], $attribute));
        $this->zugriff($board, $this->gruppe, 'schreiben');

        return $board;
    }

    private function karte(Ergebnisboard $board, string $bereich, array $zustaendige = [], array $attribute = []): ErgebnisKarte
    {
        $karte = ErgebnisKarte::factory()->create(array_merge(['board_id' => $board->id, 'bereich' => $bereich], $attribute));
        $karte->zustaendige()->attach(collect($zustaendige)->pluck('id')->all());

        return $karte;
    }

    private function alsAufgabe(Ergebnisboard $board, ErgebnisKarte $karte, array $daten = [])
    {
        return $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.aufgabe', [$board, $karte]), $daten);
    }

    public function test_ohne_thema_keine_aufgabe(): void
    {
        $board = $this->board($this->autor, 'kanban');
        $karte = $this->karte($board, 'geplant', [$this->autor]);

        $this->alsAufgabe($board, $karte)->assertStatus(422)->assertJsonValidationErrors('aufgabe');
        $this->assertSame(0, Task::withCompleted()->count());
    }

    public function test_ohne_zustaendige_keine_aufgabe(): void
    {
        $board = $this->boardMitThema();
        $karte = $this->karte($board, 'geplant');

        $this->alsAufgabe($board, $karte)->assertStatus(422)->assertJsonValidationErrors('aufgabe');
        $this->assertSame(0, Task::withCompleted()->count());
    }

    public function test_eine_zustaendige_person_ergibt_persoenliche_aufgabe(): void
    {
        $board = $this->boardMitThema();
        $karte = $this->karte($board, 'geplant', [$this->mitglied], [
            'titel' => 'Elternbrief schreiben',
            'zusatz' => 'bis zum Elternabend',
            'faellig_am' => '2026-12-05',
        ]);

        $this->alsAufgabe($board, $karte)->assertCreated()
            ->assertJsonPath('data.karten.0.aufgabe.erledigt', false)
            ->assertJsonPath('data.karten.0.aufgabe.datum', '2026-12-05');

        $task = Task::withCompleted()->firstOrFail();
        $this->assertSame(User::class, $task->taskable_type);
        $this->assertSame($this->mitglied->id, (int) $task->taskable_id);
        $this->assertSame($this->thema->id, (int) $task->theme_id);
        $this->assertSame($this->autor->id, (int) $task->creator_id);
        $this->assertSame('Elternbrief schreiben – bis zum Elternabend', $task->task);
        $this->assertSame($task->id, (int) $karte->fresh()->task_id);
        $this->assertSame(2, $board->fresh()->version);

        Notification::assertSentTo($this->mitglied, AufgabeZugewiesen::class);
        Notification::assertNotSentTo($this->autor, AufgabeZugewiesen::class);
    }

    public function test_ohne_faelligkeit_wird_datum_uebernommen(): void
    {
        $board = $this->boardMitThema();
        $karte = $this->karte($board, 'geplant', [$this->autor]);
        $datum = now()->addDays(3)->toDateString();

        $this->alsAufgabe($board, $karte, ['datum' => $datum])->assertCreated();

        $this->assertSame($datum, $karte->fresh()->faellig_am->toDateString());
        $this->assertSame($datum, Task::withCompleted()->firstOrFail()->date->toDateString());
    }

    public function test_zweite_aufgabe_zur_selben_karte_wird_abgelehnt(): void
    {
        $board = $this->boardMitThema();
        $karte = $this->karte($board, 'geplant', [$this->autor]);

        $this->alsAufgabe($board, $karte)->assertCreated();
        $this->alsAufgabe($board, $karte)->assertStatus(422)->assertJsonValidationErrors('aufgabe');
        $this->assertSame(1, Task::withCompleted()->count());
    }

    public function test_nur_mit_schreibrecht(): void
    {
        $board = $this->boardMitThema();
        $karte = $this->karte($board, 'geplant', [$this->autor]);
        $leser = $this->nutzer();
        $this->zugriff($board, $leser, 'lesen');

        $this->actingAs($leser)->postJson(route('ergebnisboards.karten.aufgabe', [$board, $karte]))->assertForbidden();
        $this->assertSame(0, Task::withCompleted()->count());
    }

    public function test_mehrere_zustaendige_ergeben_gemeinsame_aufgabe_der_gruppe(): void
    {
        $board = $this->boardMitThema();
        $karte = $this->karte($board, 'geplant', [$this->autor, $this->mitglied]);

        $this->alsAufgabe($board, $karte)->assertCreated();

        $task = Task::withCompleted()->firstOrFail();
        $this->assertSame(Group::class, $task->taskable_type);
        $this->assertSame($this->gruppe->id, (int) $task->taskable_id);
        $this->assertEqualsCanonicalizing([$this->autor->id, $this->mitglied->id], $task->taskUsers()->pluck('users_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_thema_auf_tagesordnung_der_sitzung_ergibt_aufgabe_der_sitzung(): void
    {
        $meeting = Meeting::factory()->create(['group_id' => $this->gruppe->id]);
        $meeting->themes()->attach($this->thema->id);
        $board = $this->boardMitThema('kanban', ['meeting_id' => $meeting->id]);
        $karte = $this->karte($board, 'geplant', [$this->autor, $this->mitglied]);

        $this->alsAufgabe($board, $karte)->assertCreated();

        $task = Task::withCompleted()->firstOrFail();
        $this->assertSame(Meeting::class, $task->taskable_type);
        $this->assertSame($meeting->id, (int) $task->taskable_id);
    }

    public function test_erledigte_aufgabe_verschiebt_karte_auf_kanban_nach_erledigt(): void
    {
        $board = $this->boardMitThema('kanban');
        $karte = $this->karte($board, 'geplant', [$this->mitglied]);
        $this->alsAufgabe($board, $karte)->assertCreated();
        $task = Task::withCompleted()->firstOrFail();
        $version = $board->fresh()->version;

        $this->assertTrue(app(ThemeTaskService::class)->complete($task, $this->mitglied));

        $karte->refresh();
        $this->assertSame('erledigt', $karte->bereich);
        $this->assertGreaterThan($version, $board->fresh()->version);
    }

    public function test_kanban_ohne_auto_status_bleibt_bei_teilweiser_erledigung(): void
    {
        $board = $this->boardMitThema('kanban');
        $karte = $this->karte($board, 'ideen', [$this->autor, $this->mitglied]);
        $this->alsAufgabe($board, $karte)->assertCreated();
        $task = Task::withCompleted()->firstOrFail();

        app(ThemeTaskService::class)->complete($task, $this->mitglied);
        $this->assertSame('ideen', $karte->fresh()->bereich);

        app(ThemeTaskService::class)->complete($task->fresh(), $this->autor);
        $this->assertSame('erledigt', $karte->fresh()->bereich);
    }

    public function test_umsetzungsstand_folgt_dem_status_der_gemeinsamen_aufgabe(): void
    {
        $board = $this->boardMitThema('umsetzungsstand');
        $this->assertTrue($board->auto_status);
        $karte = $this->karte($board, 'offen', [$this->autor, $this->mitglied]);
        $this->alsAufgabe($board, $karte)->assertCreated();
        $task = Task::withCompleted()->firstOrFail();
        $this->assertSame('offen', $karte->fresh()->bereich);

        app(ThemeTaskService::class)->complete($task, $this->mitglied);
        $this->assertSame('in_arbeit', $karte->fresh()->bereich);
        $this->assertNotNull(GroupTaskUser::where('taskable_id', $task->id)->where('users_id', $this->mitglied->id)->value('completed_at'));

        app(ThemeTaskService::class)->complete($task->fresh(), $this->autor);
        $this->assertSame('erledigt', $karte->fresh()->bereich);
        $this->assertTrue((bool) Task::withCompleted()->find($task->id)->completed);
    }

    public function test_umsetzungsstand_karte_in_falscher_spalte_wird_beim_anlegen_eingeordnet(): void
    {
        $board = $this->boardMitThema('umsetzungsstand');
        $karte = $this->karte($board, 'in_arbeit', [$this->autor]);

        $this->alsAufgabe($board, $karte)->assertCreated();

        $this->assertSame('offen', $karte->fresh()->bereich);
    }

    public function test_aufgabe_ueber_board_erledigen(): void
    {
        $board = $this->boardMitThema('kanban');
        $karte = $this->karte($board, 'in_arbeit', [$this->mitglied]);
        $this->alsAufgabe($board, $karte)->assertCreated();

        $this->actingAs($this->mitglied)->postJson(route('ergebnisboards.karten.aufgabe.erledigen', [$board, $karte]))
            ->assertOk()
            ->assertJsonPath('message', 'Als erledigt markiert.')
            ->assertJsonPath('data.karten.0.bereich', 'erledigt')
            ->assertJsonPath('data.karten.0.aufgabe.erledigt', true);

        // nochmal: nichts mehr offen
        $this->actingAs($this->mitglied)->postJson(route('ergebnisboards.karten.aufgabe.erledigen', [$board, $karte]))
            ->assertOk()->assertJsonPath('message', 'Für Sie war hier nichts mehr offen.');
    }

    public function test_andere_person_kann_persoenliche_aufgabe_nicht_erledigen(): void
    {
        $board = $this->boardMitThema('kanban');
        $karte = $this->karte($board, 'in_arbeit', [$this->mitglied]);
        $this->alsAufgabe($board, $karte)->assertCreated();

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.aufgabe.erledigen', [$board, $karte]))
            ->assertOk()->assertJsonPath('message', 'Für Sie war hier nichts mehr offen.');
        $this->assertSame('in_arbeit', $karte->fresh()->bereich);
    }

    public function test_archiviertes_board_wird_nicht_mehr_verschoben(): void
    {
        $board = $this->boardMitThema('kanban');
        $karte = $this->karte($board, 'geplant', [$this->mitglied]);
        $this->alsAufgabe($board, $karte)->assertCreated();
        $board->update(['archiviert_at' => now()]);

        app(ThemeTaskService::class)->complete(Task::withCompleted()->firstOrFail(), $this->mitglied);

        $this->assertSame('geplant', $karte->fresh()->bereich);
    }

    public function test_thema_und_sitzung_nur_verknuepfbar_wenn_eigene(): void
    {
        $board = $this->board($this->autor, 'kanban');
        $fremdesThema = Theme::factory()->create();
        $fremdeSitzung = Meeting::factory()->create();
        $eigeneSitzung = Meeting::factory()->create(['group_id' => $this->gruppe->id]);

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.update', $board), ['theme_id' => $fremdesThema->id])
            ->assertStatus(422)->assertJsonValidationErrors('theme_id');
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.update', $board), ['meeting_id' => $fremdeSitzung->id])
            ->assertStatus(422)->assertJsonValidationErrors('meeting_id');

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.update', $board), [
            'theme_id' => $this->thema->id,
            'meeting_id' => $eigeneSitzung->id,
        ])->assertOk()
            ->assertJsonPath('data.theme.id', $this->thema->id)
            ->assertJsonPath('data.meeting.id', $eigeneSitzung->id);
    }
}
