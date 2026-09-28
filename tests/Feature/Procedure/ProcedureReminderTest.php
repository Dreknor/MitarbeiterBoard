<?php

namespace Tests\Feature\Procedure;

use App\Mail\StepErinnerungMail;
use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Step;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests für die Erinnerungsmail-Funktionen.
 */
class ProcedureReminderTest extends TestCase
{
    // ─── Erinnerungsmail: manuelle Auslösung ─────────────────────────────────

    public function test_remind_step_mail_sendet_an_user_mit_ueberfaelligen_schritten(): void
    {
        Mail::fake();

        $admin    = $this->actingAsWithPermission('manage procedures');
        $position = Positions::factory()->create();

        $empfaenger = User::factory()->create();
        $position->users()->attach($empfaenger->id);

        $prozess = Procedure::factory()->gestartet()->create();

        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => now()->subDays(2), // überfällig
        ]);
        $step->users()->attach($empfaenger->id);

        $this->runReminderSchedule();

        Mail::assertQueued(StepErinnerungMail::class, function ($mail) use ($empfaenger) {
            return $mail->hasTo($empfaenger->email);
        });
    }

    public function test_remind_step_mail_sendet_nicht_an_user_ohne_ueberfaellige_schritte(): void
    {
        Mail::fake();

        $this->actingAsWithPermission('manage procedures');
        $position   = Positions::factory()->create();
        $empfaenger = User::factory()->create();
        $position->users()->attach($empfaenger->id);

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => now()->addDays(5), // noch nicht fällig
        ]);
        $step->users()->attach($empfaenger->id);

        $this->runReminderSchedule();

        Mail::assertNotQueued(StepErinnerungMail::class);
    }

    public function test_remind_step_mail_sendet_nicht_an_user_mit_erledigten_schritten(): void
    {
        Mail::fake();

        $this->actingAsWithPermission('manage procedures');
        $position   = Positions::factory()->create();
        $empfaenger = User::factory()->create();
        $position->users()->attach($empfaenger->id);

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => true,
            'endDate'      => now()->subDays(1),
        ]);
        $step->users()->attach($empfaenger->id);

        $this->runReminderSchedule();

        Mail::assertNotQueued(StepErinnerungMail::class);
    }

    // ─── Artisan-Command: procedure:remind-user ───────────────────────────────

    public function test_artisan_command_sendet_erinnerung_an_user_per_id(): void
    {
        Mail::fake();

        $position = Positions::factory()->create();
        $user     = User::factory()->create();
        $position->users()->attach($user->id);

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => now()->subDays(1),
        ]);
        $step->users()->attach($user->id);

        $this->artisan("procedure:remind-user {$user->id}")
             ->assertExitCode(0);

        Mail::assertQueued(StepErinnerungMail::class, fn ($m) => $m->hasTo($user->email));
    }

    public function test_artisan_command_sendet_erinnerung_an_user_per_email(): void
    {
        Mail::fake();

        $position = Positions::factory()->create();
        $user     = User::factory()->create(['email' => 'test-remind@example.com']);
        $position->users()->attach($user->id);

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => now()->subDays(1),
        ]);
        $step->users()->attach($user->id);

        $this->artisan('procedure:remind-user test-remind@example.com')
             ->assertExitCode(0);

        Mail::assertQueued(StepErinnerungMail::class, fn ($m) => $m->hasTo($user->email));
    }

    public function test_artisan_command_gibt_fehler_bei_unbekanntem_user(): void
    {
        $this->artisan('procedure:remind-user 99999')
             ->assertExitCode(1);
    }

    public function test_artisan_command_sendet_nicht_bei_abwesenheit(): void
    {
        Mail::fake();

        $position = Positions::factory()->create();
        $user     = User::factory()->create();
        $position->users()->attach($user->id);

        $prozess = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'position_id'  => $position->id,
            'done'         => false,
            'endDate'      => now()->subDays(1),
        ]);
        $step->users()->attach($user->id);

        // Abwesenheit direkt in DB einfügen (umgeht Absence::boot(), das auth()->id() nutzt)
        // end = endOfDay damit hasAbsence(now()) auch intraday korrekt matcht (SQLite-Datetime-Vergleich)
        \Illuminate\Support\Facades\DB::table('absences')->insert([
            'users_id'   => $user->id,
            'creator_id' => $user->id,
            'reason'     => 'Urlaub',
            'start'      => today()->startOfDay(),
            'end'        => today()->endOfDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan("procedure:remind-user {$user->id}")
             ->assertExitCode(0);

        Mail::assertNotQueued(StepErinnerungMail::class);
    }

    // ─── Regressionen ────────────────────────────────────────────────────────

    public function test_remind_step_mail_ignoriert_schritte_geloeschter_und_beendeter_prozesse(): void
    {
        Mail::fake();

        $empfaenger = User::factory()->create();

        $geloescht = Procedure::factory()->gestartet()->create();
        $beendet   = Procedure::factory()->abgeschlossen()->create();

        foreach ([$geloescht, $beendet] as $prozess) {
            $step = Procedure_Step::factory()->create([
                'procedure_id' => $prozess->id,
                'done'         => false,
                'endDate'      => now()->subDays(2),
            ]);
            $step->users()->attach($empfaenger->id);
        }
        $geloescht->delete();

        // Darf nicht an der fehlenden (soft-deleted) Prozess-Relation scheitern
        $this->runReminderSchedule();

        Mail::assertNotQueued(StepErinnerungMail::class);
    }

    public function test_remind_step_mail_erinnert_am_faelligkeitstag(): void
    {
        Mail::fake();

        $empfaenger = User::factory()->create();
        $prozess    = Procedure::factory()->gestartet()->create();
        $step = Procedure_Step::factory()->create([
            'procedure_id' => $prozess->id,
            'done'         => false,
            'endDate'      => today(),
        ]);
        $step->users()->attach($empfaenger->id);

        $this->runReminderSchedule();

        Mail::assertQueued(StepErinnerungMail::class, fn ($mail) => $mail->hasTo($empfaenger->email));
    }

    /** Ruft den Erinnerungslauf so auf, wie ihn der Scheduler (Console/Kernel) startet. */
    private function runReminderSchedule(): void
    {
        $this->app->call('App\\Http\\Controllers\\ProcedureController@remindStepMail');
    }
}
