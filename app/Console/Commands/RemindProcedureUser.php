<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProcedureController;
use App\Models\User;
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
    public function handle(): int
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

        // Make the controller and call the wrapper method we added
        $controller = app()->make(ProcedureController::class);

        try {
            $controller->sendReminderEmailForUser($user);
            $this->info('Erinnerung für Benutzer gesendet (oder übersprungen bei Abwesenheit): ' . $user->id);
            return 0;
        } catch (\Exception $e) {
            $this->error('Fehler beim Senden der Erinnerung: ' . $e->getMessage());
            Log::error('procedure:remind-user error', ['exception' => $e]);
            return 1;
        }
    }
}

