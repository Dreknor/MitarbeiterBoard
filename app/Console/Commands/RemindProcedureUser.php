<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Procedure\ProcedureNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RemindProcedureUser extends Command
{
    /**
     * The name and signature of the console command.
     * Accepts either a numeric user id or an email address.
     *
     * @var string
     */
    protected $signature = 'procedure:remind-user {user : User ID or email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a procedure reminder email for a single user (by id or email).';

    /**
     * Execute the console command.
     */
    public function handle(ProcedureNotificationService $notifications): int
    {
        $input = $this->argument('user');

        if (is_numeric($input)) {
            $user = User::find($input);
        } else {
            $user = User::where('email', $input)->first();
        }

        if (!$user) {
            $this->error('Benutzer nicht gefunden: ' . $input);
            return 1;
        }

        if ($user->hasAbsence(now())) {
            $this->info('Benutzer ist abwesend – keine Erinnerung gesendet: ' . $user->id);
            return 0;
        }

        try {
            $pending = $notifications->pendingStepsFor($user);
            if ($pending === []) {
                $this->info('Keine fälligen Schritte für Benutzer: ' . $user->id);
                return 0;
            }

            $notifications->sendReminder($user, $pending);
            $this->info('Erinnerung für Benutzer gesendet: ' . $user->id);
            return 0;
        } catch (\Exception $e) {
            $this->error('Fehler beim Senden der Erinnerung: ' . $e->getMessage());
            Log::error('procedure:remind-user error', ['exception' => $e]);
            return 1;
        }
    }
}

