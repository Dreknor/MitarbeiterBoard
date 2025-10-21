<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Mail\remindTaskMail;
use App\Models\User;
use App\Models\Task;
use App\Models\Theme;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendTestTaskReminderMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test-task-reminder {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sendet eine Test-E-Mail für die Aufgaben-Erinnerung';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = $this->argument('email');

        if (!$email) {
            $email = $this->ask('Bitte gib die E-Mail-Adresse ein, an die die Test-E-Mail gesendet werden soll');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Ungültige E-Mail-Adresse!');
            return 1;
        }

        // Erstelle Test-Aufgaben mit verschiedenen Status
        $testTasks = collect([
            (object) [
                'id' => 1,
                'task' => 'Dringende Aufgabe: Quartalsbericht fertigstellen',
                'date' => Carbon::now()->subDays(2),
                'theme' => (object) ['name' => 'Berichtswesen'],
                'completed' => false,
            ],
            (object) [
                'id' => 2,
                'task' => 'Heute: Team-Meeting vorbereiten',
                'date' => Carbon::now(),
                'theme' => (object) ['name' => 'Meetings'],
                'completed' => false,
            ],
            (object) [
                'id' => 3,
                'task' => 'Projekt-Dokumentation aktualisieren',
                'date' => Carbon::now()->addDays(2),
                'theme' => (object) ['name' => 'Dokumentation'],
                'completed' => false,
            ],
            (object) [
                'id' => 4,
                'task' => 'Monatsabschluss durchführen',
                'date' => Carbon::now()->addDays(5),
                'theme' => null,
                'completed' => false,
            ],
        ]);

        $this->info('Sende Test-E-Mail an: ' . $email);

        try {
            Mail::to($email)->send(new remindTaskMail('Test-Benutzer', $testTasks));
            $this->info('✓ Test-E-Mail erfolgreich versendet!');
            $this->line('');
            $this->line('Die E-Mail enthält ' . $testTasks->count() . ' Test-Aufgaben:');
            $this->line('- 1 überfällige Aufgabe (vor 2 Tagen)');
            $this->line('- 1 heute fällige Aufgabe');
            $this->line('- 2 zukünftige Aufgaben (in 2 und 5 Tagen)');

            return 0;
        } catch (\Exception $e) {
            $this->error('Fehler beim Versenden der E-Mail: ' . $e->getMessage());
            return 1;
        }
    }
}

