<?php

namespace App\Console\Commands;

use App\Mail\BenachrichtigungsZusammenfassung;
use App\Models\User;
use App\Services\Benachrichtigungen\BenachrichtigungsService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Verschickt pro Person eine Mail mit allen ungelesenen Benachrichtigungen aus
 * Kategorien, für die „Mail: Zusammenfassung“ eingestellt ist.
 */
class BenachrichtigungenZusammenfassung extends Command
{
    protected $signature = 'benachrichtigungen:zusammenfassung';

    protected $description = 'Verschickt die Zusammenfassung ungelesener Benachrichtigungen (Mail-Modus „Zusammenfassung“)';

    public function handle(BenachrichtigungsService $service): int
    {
        $versendet = 0;

        $offen = DatabaseNotification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereNull('read_at')
            ->whereNull('zusammenfassung_versendet_at')
            ->whereNotNull('kategorie')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at')
            ->get()
            ->groupBy('notifiable_id');

        foreach ($offen as $userId => $benachrichtigungen) {
            $user = User::find($userId);

            if (!$user || blank($user->email)) {
                continue;
            }

            $auswahl = $benachrichtigungen->filter(
                fn (DatabaseNotification $n) => $service->einstellungFuer($user, $n->kategorie)['mail'] === BenachrichtigungsService::MAIL_ZUSAMMENFASSUNG
            );

            if ($auswahl->isEmpty()) {
                continue;
            }

            $kategorien = $service->kategorien();

            $gruppen = $auswahl
                ->groupBy('kategorie')
                ->map(fn ($liste, $kategorie) => [
                    'label'     => $kategorien[$kategorie]['label'] ?? $kategorie,
                    'eintraege' => $liste->map(fn (DatabaseNotification $n) => [
                        'titel' => (string) ($n->data['subject'] ?? 'Benachrichtigung'),
                        'text'  => (string) ($n->data['message'] ?? ''),
                        'zeit'  => $n->created_at->format('d.m. H:i'),
                        'url'   => route('benachrichtigungen.oeffnen', $n->id),
                    ])->values()->all(),
                ])
                ->values()
                ->all();

            try {
                Mail::to($user)->queue(new BenachrichtigungsZusammenfassung($user->vorname ?? $user->name, $gruppen, $auswahl->count()));

                DatabaseNotification::whereIn('id', $auswahl->pluck('id'))->update(['zusammenfassung_versendet_at' => now()]);
                $versendet++;
            } catch (\Throwable $e) {
                Log::error('Benachrichtigungen: Zusammenfassung fehlgeschlagen', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("$versendet Zusammenfassung(en) versendet.");

        return self::SUCCESS;
    }
}
