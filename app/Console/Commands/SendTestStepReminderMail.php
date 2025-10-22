<?php

namespace App\Console\Commands;

use App\Mail\StepErinnerungMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestStepReminderMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test-step-reminder {email? : Die E-Mail-Adresse des Empfängers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Versendet eine Test-E-Mail für die Erinnerung an ausstehende Prozess-Schritte';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = $this->argument('email');

        if (!$email) {
            $email = $this->ask('Bitte geben Sie die E-Mail-Adresse des Empfängers ein');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Ungültige E-Mail-Adresse!');
            return Command::FAILURE;
        }

        $this->info('Bereite Test-E-Mail vor...');

        // Test-Daten erstellen
        $testSteps = [
            [
                'procedureName' => 'Urlaubsantrag Mustermann',
                'procedureId' => 1,
                'stepName' => 'Genehmigung durch Vorgesetzten',
                'stepId' => 101,
                'endDate' => now()->format('d.m.Y')
            ],
            [
                'procedureName' => 'Wartung Server-Raum',
                'procedureId' => 2,
                'stepName' => 'Dokumentation erstellen',
                'stepId' => 102,
                'endDate' => now()->addDays(2)->format('d.m.Y')
            ],
            [
                'procedureName' => 'Bestellung IT-Equipment',
                'procedureId' => 3,
                'stepName' => 'Budget-Freigabe einholen',
                'stepId' => 103,
                'endDate' => now()->subDays(1)->format('d.m.Y')
            ]
        ];

        // Versuche den Namen des Benutzers zu finden
        $user = User::where('email', $email)->first();
        $name = $user ? $user->name : 'Test-Benutzer';

        try {
            Mail::to($email)->send(new StepErinnerungMail($name, $testSteps));

            $this->info('✅ Test-E-Mail erfolgreich versendet!');
            $this->line('');
            $this->line("Empfänger: <fg=cyan>{$email}</>");
            $this->line("Name: <fg=cyan>{$name}</>");
            $this->line("Anzahl Test-Aufträge: <fg=cyan>" . count($testSteps) . "</>");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Fehler beim Versenden der E-Mail:');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}

