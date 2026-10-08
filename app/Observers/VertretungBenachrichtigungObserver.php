<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Vertretung;
use App\Notifications\VertretungGeaendert;
use Illuminate\Support\Facades\Log;

/**
 * Meldet Vertretungen an die betroffene Lehrkraft (manuell und per Import).
 * Nur Vertretungen ab heute; der Import löst bei unveränderten Daten kein Update aus.
 * (Die Weitergabe an das ElternInfoBoard übernimmt weiterhin VertretungObserver.)
 */
class VertretungBenachrichtigungObserver
{
    /** Felder, deren Änderung für die Lehrkraft relevant ist */
    private const RELEVANT = ['date', 'stunde', 'klassen_id', 'users_id', 'altFach', 'neuFach', 'comment', 'type', 'Doppelstunde'];

    /** Doppelte Meldungen innerhalb eines Requests vermeiden (z. B. eine Stunde in mehreren Klassen) */
    private static array $gemeldet = [];

    public function created(Vertretung $vertretung): void
    {
        $this->melden($vertretung->users_id, VertretungGeaendert::NEU, $vertretung);
    }

    public function updated(Vertretung $vertretung): void
    {
        if (!$vertretung->wasChanged(self::RELEVANT)) {
            return;
        }

        $vorher = $vertretung->getOriginal('users_id');

        if ($vertretung->wasChanged('users_id')) {
            // Bisherige Lehrkraft: Vertretung entfällt für sie; neue Lehrkraft: neue Vertretung
            $this->melden($vorher, VertretungGeaendert::ENTFAELLT, $vertretung, original: true);
            $this->melden($vertretung->users_id, VertretungGeaendert::NEU, $vertretung);

            return;
        }

        $this->melden($vertretung->users_id, VertretungGeaendert::GEAENDERT, $vertretung);
    }

    public function deleted(Vertretung $vertretung): void
    {
        $this->melden($vertretung->users_id, VertretungGeaendert::ENTFAELLT, $vertretung);
    }

    private function melden(?int $userId, string $art, Vertretung $vertretung, bool $original = false): void
    {
        if (!$userId) {
            return;
        }

        $datum = $original ? $vertretung->getOriginal('date') : $vertretung->date;
        $datum = $datum ? \Carbon\Carbon::parse($datum)->startOfDay() : null;

        if ($datum === null || $datum->lt(today())) {
            return;
        }

        // Wer selbst bearbeitet, braucht keine Meldung
        if (auth()->id() !== null && (int) auth()->id() === (int) $userId) {
            return;
        }

        $schluessel = implode('|', [$userId, $art, $datum->toDateString(), (string) $vertretung->getRawOriginal('stunde')]);
        if (isset(self::$gemeldet[$schluessel])) {
            return;
        }
        self::$gemeldet[$schluessel] = true;

        $user = User::find($userId);
        if (!$user) {
            return;
        }

        try {
            $user->notify((new VertretungGeaendert(
                art: $art,
                datum: $datum,
                stunde: (string) $vertretung->stunde,
                klasse: (string) ($vertretung->klasse?->name ?? ''),
                fach: $vertretung->neuFach ?: $vertretung->altFach,
                kommentar: $vertretung->comment,
            ))->afterCommit());
        } catch (\Throwable $e) {
            Log::warning('Vertretung: Benachrichtigung fehlgeschlagen', [
                'vertretung' => $vertretung->id,
                'user'       => $userId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
