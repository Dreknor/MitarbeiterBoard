<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Protokolliert An- und Abmeldungen, damit sie in den Systemlogs unter "Login" gefiltert werden können.
 */
class LogAuthentication
{
    public function subscribe($events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Logout::class => 'handleLogout',
        ];
    }

    public function handleLogin(Login $event): void
    {
        Log::info('Login: Anmeldung erfolgreich', [
            'user_id' => $event->user?->getAuthIdentifier(),
            'name' => $event->user->name ?? null,
            'guard' => $event->guard,
            'ip' => request()->ip(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        Log::warning('Login: Anmeldung fehlgeschlagen', [
            'user_id' => $event->user?->getAuthIdentifier(),
            'login' => Arr::except($event->credentials, ['password']),
            'guard' => $event->guard,
            'ip' => request()->ip(),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        Log::info('Login: Abmeldung', [
            'user_id' => $event->user?->getAuthIdentifier(),
            'guard' => $event->guard,
            'ip' => request()->ip(),
        ]);
    }
}
