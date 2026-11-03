<?php

namespace Tests\Feature\Tasks;

use App\Models\Group;
use App\Models\GroupTaskUser;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use App\Notifications\AufgabeZugewiesen;
use App\Services\Meetings\MeetingService;
use App\View\Composers\TasksComposer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Tests\TestCase;

class ThemeTaskTest extends TestCase
{
    private Group $group;
    private Theme $theme;
    private User $me;
    private User $anna;
    private User $ben;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        $this->me   = $this->actingAsWithPermission();
        $this->anna = User::factory()->create(['name' => 'Anna Schmidt']);
        $this->ben  = User::factory()->create(['name' => 'Ben Müller']);

        $this->group = Group::factory()->create(['name' => 'Steuergruppe']);
        $this->group->users()->attach([$this->me->id, $this->anna->id, $this->ben->id]);

        $this->theme = Theme::factory()->create(['group_id' => $this->group->id, 'creator_id' => $this->me->id, 'theme' => 'Schulfest']);
    }

    private function storeGroupTask(array $data)
    {
        return $this->post(route('themes.tasks.store', ['groupname' => $this->group->name, 'theme' => $this->theme->id]), array_merge([
            'task' => 'Getränke organisieren',
            'date' => now()->addWeek()->format('Y-m-d'),
        ], $data));
    }

    /** @test */
    public function gruppenaufgabe_behaelt_erledigte_zustaendigkeiten_und_zeigt_wer_erledigt_hat(): void
    {
        $this->storeGroupTask(['assign' => 'all'])->assertSessionHas('type', 'success');

        $task = Task::where('theme_id', $this->theme->id)->firstOrFail();
        $this->assertSame(Group::class, $task->taskable_type);
        $this->assertSame($this->me->id, $task->creator_id);
        $this->assertSame(3, $task->taskUsers()->count());
        Notification::assertSentTimes(AufgabeZugewiesen::class, 2); // nicht an den Ersteller
        Notification::assertNotSentTo($this->me, AufgabeZugewiesen::class);

        $this->actingAs($this->anna)->get(route('tasks.complete', $task))->assertSessionHas('type', 'success');

        $row = GroupTaskUser::where('taskable_id', $task->id)->where('users_id', $this->anna->id)->first();
        $this->assertNotNull($row, 'Zuständigkeit darf beim Erledigen nicht gelöscht werden');
        $this->assertNotNull($row->completed_at);
        $this->assertFalse($task->fresh()->completed);

        // Ansicht zeigt Fortschritt und Namen
        $this->actingAs($this->me)->get(url($this->group->name . '/themes/' . $this->theme->id))
            ->assertOk()
            ->assertSee('1/3 erledigt')
            ->assertSee('Anna Schmidt');

        $this->actingAs($this->ben)->get(route('tasks.complete', $task));
        $this->actingAs($this->me)->get(route('tasks.complete', $task));

        $task = Task::withCompleted()->find($task->id);
        $this->assertTrue($task->completed);
        $this->assertSame($this->me->id, $task->completed_by);
        $this->assertNotNull($task->completed_at);
        $this->assertSame(3, GroupTaskUser::where('taskable_id', $task->id)->whereNotNull('completed_at')->count());

        // Erledigte Aufgabe bleibt am Thema sichtbar
        $this->get(url($this->group->name . '/themes/' . $this->theme->id))
            ->assertOk()
            ->assertSee('Erledigte Aufgaben (1)');
    }

    /** @test */
    public function doppeltes_absenden_legt_keine_zweite_aufgabe_an(): void
    {
        $this->storeGroupTask(['assign' => 'all']);
        $this->storeGroupTask(['assign' => 'all', 'task' => '  getränke   ORGANISIEREN '])
            ->assertSessionHas('type', 'warning');

        $this->assertSame(1, Task::where('theme_id', $this->theme->id)->count());
        $this->assertSame(3, GroupTaskUser::count());
    }

    /** @test */
    public function bereits_zustaendige_personen_werden_uebersprungen(): void
    {
        // Persönliche Aufgabe für Anna existiert schon
        $this->storeGroupTask(['assign' => 'users', 'users' => [$this->anna->id]]);
        $personal = Task::where('theme_id', $this->theme->id)->firstOrFail();
        $this->assertSame(User::class, $personal->taskable_type);
        $this->assertSame($this->anna->id, $personal->taskable_id);

        $this->storeGroupTask(['assign' => 'all'])->assertSessionHas('type', 'success');

        $collective = Task::where('theme_id', $this->theme->id)->where('taskable_type', Group::class)->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$this->me->id, $this->ben->id],
            $collective->taskUsers()->pluck('users_id')->all()
        );
    }

    /** @test */
    public function gemeinsame_aufgabe_wird_um_neue_personen_ergaenzt(): void
    {
        $this->storeGroupTask(['assign' => 'users', 'users' => [$this->me->id, $this->anna->id]]);
        $this->storeGroupTask(['assign' => 'users', 'users' => [$this->anna->id, $this->ben->id]]);

        $this->assertSame(1, Task::where('theme_id', $this->theme->id)->count());
        $task = Task::where('theme_id', $this->theme->id)->first();
        $this->assertEqualsCanonicalizing(
            [$this->me->id, $this->anna->id, $this->ben->id],
            $task->taskUsers()->pluck('users_id')->all()
        );
    }

    /** @test */
    public function personen_ausserhalb_der_gruppe_koennen_nicht_zugewiesen_werden(): void
    {
        $fremd = User::factory()->create();

        $this->storeGroupTask(['assign' => 'users', 'users' => [$this->anna->id, $fremd->id]])
            ->assertSessionHas('type', 'warning');

        $this->assertSame(0, Task::count());
    }

    /** @test */
    public function fremde_koennen_persoenliche_aufgaben_nicht_erledigen_und_nur_ersteller_loeschen(): void
    {
        $this->storeGroupTask(['assign' => 'users', 'users' => [$this->anna->id]]);
        $task = Task::firstOrFail();

        $this->actingAs($this->ben)->get(route('tasks.complete', $task))->assertSessionHas('type', 'warning');
        $this->assertFalse($task->fresh()->completed);

        $this->actingAs($this->ben)->delete(route('tasks.destroy', $task))->assertForbidden();
        $this->actingAs($this->me)->delete(route('tasks.destroy', $task))->assertSessionHas('type', 'success');
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    /** @test */
    public function dashboard_listet_jede_offene_aufgabe_genau_einmal(): void
    {
        $this->storeGroupTask(['assign' => 'all']);
        $this->storeGroupTask(['assign' => 'users', 'users' => [$this->anna->id], 'task' => 'Plakate drucken']);
        $group = Task::where('taskable_type', Group::class)->firstOrFail();

        $this->actingAs($this->anna);
        $view = \Mockery::mock(View::class);
        $view->shouldReceive('with')->once()->withArgs(function ($data) {
            $this->assertCount(2, $data['tasks']);
            $this->assertCount(2, $data['tasks']->pluck('id')->unique());

            return true;
        });
        (new TasksComposer())->compose($view);

        // Nach Erledigung verschwindet die gemeinsame Aufgabe für Anna
        $this->get(route('tasks.complete', $group));
        $view = \Mockery::mock(View::class);
        $view->shouldReceive('with')->once()->withArgs(function ($data) {
            $this->assertSame(['Plakate drucken'], $data['tasks']->pluck('task')->all());

            return true;
        });
        (new TasksComposer())->compose($view);
    }

    /** @test */
    public function freie_meeting_themen_haben_aufgaben_fuer_alle_teilnehmenden(): void
    {
        $meeting = Meeting::factory()->free()->create(['creator_id' => $this->me->id]);
        app(MeetingService::class)->syncParticipants($meeting, ['users' => [$this->anna->id]]);
        $theme = Theme::factory()->create(['group_id' => null, 'creator_id' => $this->me->id, 'theme' => 'Budget']);
        $meeting->themes()->attach($theme->id);

        $this->post(route('meetings.themes.tasks.store', [$meeting, $theme]), [
            'task'   => 'Angebote einholen',
            'date'   => now()->addWeek()->format('Y-m-d'),
            'assign' => 'all',
        ])->assertRedirect(route('meetings.themes.show', [$meeting, $theme]));

        $task = Task::where('theme_id', $theme->id)->firstOrFail();
        $this->assertSame(Meeting::class, $task->taskable_type);
        $this->assertEqualsCanonicalizing([$this->me->id, $this->anna->id], $task->taskUsers()->pluck('users_id')->all());
        $this->assertSame(route('meetings.themes.show', [$meeting, $theme]), $task->themeUrl());

        $this->actingAs($this->anna)->get(route('tasks.complete', $task))->assertSessionHas('type', 'success');
        $this->actingAs($this->anna)->get(route('meetings.themes.show', [$meeting, $theme]))
            ->assertOk()
            ->assertSee('Angebote einholen')
            ->assertSee('1/2 erledigt');

        // Nicht-Teilnehmende dürfen weder anlegen noch erledigen
        $this->actingAs($this->ben)->post(route('meetings.themes.tasks.store', [$meeting, $theme]), [
            'task' => 'Einschleusen', 'date' => now()->addWeek()->format('Y-m-d'), 'assign' => 'all',
        ])->assertForbidden();
        $this->actingAs($this->ben)->get(route('tasks.complete', $task))->assertSessionHas('type', 'warning');
    }

    /** @test */
    public function meeting_aufgaben_nur_an_teilnehmende(): void
    {
        $meeting = Meeting::factory()->free()->create(['creator_id' => $this->me->id]);
        $theme   = Theme::factory()->create(['group_id' => null, 'creator_id' => $this->me->id]);
        $meeting->themes()->attach($theme->id);

        $this->post(route('meetings.themes.tasks.store', [$meeting, $theme]), [
            'task' => 'Test', 'date' => now()->addWeek()->format('Y-m-d'), 'assign' => 'users', 'users' => [$this->ben->id],
        ])->assertSessionHas('type', 'warning');

        $this->assertSame(0, Task::count());
    }
}
