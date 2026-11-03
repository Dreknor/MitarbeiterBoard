<?php

namespace App\Services\Benachrichtigungen;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\Channels\BenachrichtigungDatabaseChannel;
use Illuminate\Support\Collection;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Löst die Benachrichtigungs-Einstellungen einer Person auf und bestimmt
 * daraus die Kanäle einer Notification.
 *
 * Grundregel: Die Datenbank (Glocke) bekommt immer einen Eintrag,
 * Push und Mail nur nach Einstellung.
 */
class BenachrichtigungsService
{
    public const MAIL_SOFORT = 'sofort';
    public const MAIL_ZUSAMMENFASSUNG = 'zusammenfassung';
    public const MAIL_AUS = 'aus';

    /** @var array<int, Collection<string, NotificationPreference>> Cache pro Request */
    private array $cache = [];

    /**
     * Alle Kategorien aus der Config.
     *
     * @return array<string, array>
     */
    public function kategorien(): array
    {
        return config('benachrichtigungen.kategorien', []);
    }

    public function kategorieExistiert(string $kategorie): bool
    {
        return array_key_exists($kategorie, $this->kategorien());
    }

    /**
     * Kategorien, die die Person in den Einstellungen sieht (nach Permission gefiltert).
     *
     * @return array<string, array>
     */
    public function sichtbareKategorien(User $user): array
    {
        return array_filter($this->kategorien(), function (array $kategorie) use ($user) {
            $permissions = $kategorie['permission'] ?? null;

            if (empty($permissions)) {
                return true;
            }

            foreach ((array) $permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Wirksame Einstellung (gespeichert oder Standard) einer Kategorie.
     *
     * @return array{push: bool, mail: string}
     */
    public function einstellungFuer(User $user, string $kategorie): array
    {
        $standard = $this->kategorien()[$kategorie] ?? ['push' => false, 'mail' => self::MAIL_SOFORT];

        $gespeichert = $this->gespeicherte($user)->get($kategorie);

        return [
            'push' => $gespeichert ? (bool) $gespeichert->push : (bool) ($standard['push'] ?? false),
            'mail' => $gespeichert ? $gespeichert->mail : ($standard['mail'] ?? self::MAIL_SOFORT),
        ];
    }

    /**
     * Wirksame Einstellungen aller sichtbaren Kategorien.
     *
     * @return array<string, array{push: bool, mail: string}>
     */
    public function alleEinstellungen(User $user): array
    {
        $ergebnis = [];

        foreach (array_keys($this->sichtbareKategorien($user)) as $kategorie) {
            $ergebnis[$kategorie] = $this->einstellungFuer($user, $kategorie);
        }

        return $ergebnis;
    }

    /**
     * Kanäle für eine Notification dieser Kategorie.
     *
     * @return array<int, string>
     */
    public function kanaeleFuer(User $user, string $kategorie): array
    {
        $einstellung = $this->einstellungFuer($user, $kategorie);
        $kanaele = [BenachrichtigungDatabaseChannel::class];

        if ($einstellung['push'] && $user->pushSubscriptions()->exists()) {
            $kanaele[] = WebPushChannel::class;
        }

        if ($einstellung['mail'] === self::MAIL_SOFORT && filled($user->email)) {
            $kanaele[] = 'mail';
        }

        return $kanaele;
    }

    /**
     * Hat die Person die Kategorie aktiv eingeschaltet (Push oder Mail)?
     * Für „Opt-in“-Kategorien wie Abwesenheiten, die nicht jeder in der Glocke haben soll.
     */
    public function istAktiviert(User $user, string $kategorie): bool
    {
        $einstellung = $this->einstellungFuer($user, $kategorie);

        return $einstellung['push'] || $einstellung['mail'] !== self::MAIL_AUS;
    }

    /**
     * Filtert die Personen, die die Kategorie aktiv eingeschaltet haben.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    public function aktiviertFuer(Collection $users, string $kategorie): Collection
    {
        return $users->filter(fn (User $user) => $this->istAktiviert($user, $kategorie))->values();
    }

    /**
     * Alle Personen mit einer Permission (direkt oder über Rollen) – leer, falls es sie nicht gibt.
     *
     * @return Collection<int, User>
     */
    public function nutzerMitPermission(string $permission): Collection
    {
        if (!\Spatie\Permission\Models\Permission::where('name', $permission)->exists()) {
            return collect();
        }

        return User::permission($permission)->with('notificationPreferences')->get();
    }

    /**
     * Darf für diese Kategorie überhaupt eine Mail verschickt werden?
     * Für Sammelmails, die (noch) nicht über Notifications laufen.
     */
    public function mailErlaubt(User $user, string $kategorie): bool
    {
        return $this->einstellungFuer($user, $kategorie)['mail'] !== self::MAIL_AUS;
    }

    /**
     * Speichert die Einstellungen aus dem Formular.
     *
     * @param  array<string, array{push?: mixed, mail?: string}>  $daten
     */
    public function speichern(User $user, array $daten): void
    {
        foreach ($this->sichtbareKategorien($user) as $kategorie => $definition) {
            $eingabe = $daten[$kategorie] ?? [];

            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'kategorie' => $kategorie],
                [
                    'push' => (bool) ($eingabe['push'] ?? false),
                    'mail' => in_array($eingabe['mail'] ?? null, [self::MAIL_SOFORT, self::MAIL_ZUSAMMENFASSUNG, self::MAIL_AUS], true)
                        ? $eingabe['mail']
                        : ($definition['mail'] ?? self::MAIL_SOFORT),
                ]
            );
        }

        unset($this->cache[$user->id]);
    }

    /**
     * @return Collection<string, NotificationPreference>
     */
    private function gespeicherte(User $user): Collection
    {
        return $this->cache[$user->id] ??= ($user->relationLoaded('notificationPreferences')
            ? $user->notificationPreferences
            : $user->notificationPreferences()->get()
        )->keyBy('kategorie');
    }
}
