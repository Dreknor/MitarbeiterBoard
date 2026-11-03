<?php

namespace App\Support;


/**
 * Ordnet Systemlog-Einträge anhand ihres Nachrichtentextes thematischen Kategorien zu.
 *
 * Die Zuordnung erfolgt über Suchbegriffe (Teilstrings, ohne Beachtung der Groß-/Kleinschreibung).
 * Neue Log-Meldungen sollten mit einem passenden Präfix beginnen (z. B. "Login: …", "Kalender: …"),
 * damit sie automatisch in der richtigen Kategorie landen.
 */
class LogCategories
{
    public const OTHER = 'sonstige';

    /**
     * Reihenfolge = Priorität bei der Anzeige (erste passende Kategorie gewinnt).
     */
    public static function all(): array
    {
        return [
            'login' => [
                'label' => 'Login',
                'icon' => 'fas fa-sign-in-alt',
                'patterns' => ['Login:', 'OIDC:', 'SAML:', 'Keycloak', 'logged in', 'Anmeldung'],
            ],
            'email' => [
                'label' => 'E-Mail',
                'icon' => 'fas fa-envelope',
                'patterns' => ['Mail', 'SMTP', 'Meeting-Einladung', 'Arbeitszeitnachweis'],
            ],
            'benutzer' => [
                'label' => 'Benutzer & Gruppen',
                'icon' => 'fas fa-users',
                'patterns' => ['Benutzer:', 'Gruppen:', 'Löschkonzept', 'Berechtigung'],
            ],
            'kalender' => [
                'label' => 'Kalender & Termine',
                'icon' => 'fas fa-calendar-alt',
                'patterns' => ['Kalender', 'Termin', 'iCal', 'VEVENT', 'RRULE', 'CalDAV', 'ctag', 'sync-token', 'Feiertag', 'Ferien'],
            ],
            'vertretungsplan' => [
                'label' => 'Vertretungsplan & Räume',
                'icon' => 'fas fa-exchange-alt',
                'patterns' => ['Vertretungsplan', 'VP:', 'VP-Raum', 'Indiware', 'LessonTime', 'Raumbuchung', 'Raum für Meeting'],
            ],
            'personal' => [
                'label' => 'Personal & Zeiterfassung',
                'icon' => 'fas fa-user-clock',
                'patterns' => ['Personal', 'Timesheet', 'Prüfengine', 'TimeValidation', 'Abwesenheit', 'Krankenschein'],
            ],
            'prozesse' => [
                'label' => 'Prozesse & Tickets',
                'icon' => 'fas fa-tasks',
                'patterns' => ['Prozesse', 'procedure', 'Ticketsystem'],
            ],
            'nextcloud' => [
                'label' => 'Nextcloud',
                'icon' => 'fas fa-cloud',
                'patterns' => ['Nextcloud', 'NC-', 'directory', 'File uploaded', 'File shared', 'roster'],
            ],
            'system' => [
                'label' => 'System & Jobs',
                'icon' => 'fas fa-cogs',
                'patterns' => ['Job failed', 'Loglevel', 'Setting updated', 'Sync-Logs', 'Zugriffs-Logs', 'Log-Einträge', 'Schriftart'],
            ],
        ];
    }

    /**
     * Alle Kategorien inklusive "Sonstige" für die Filteranzeige.
     */
    public static function withOther(): array
    {
        return self::all() + [
            self::OTHER => [
                'label' => 'Sonstige',
                'icon' => 'fas fa-question-circle',
                'patterns' => [],
            ],
        ];
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::withOther());
    }

    /**
     * Ermittelt den Kategorie-Schlüssel für eine Log-Nachricht.
     */
    public static function categorize(?string $message): string
    {
        $message = mb_strtolower((string) $message);

        foreach (self::all() as $key => $category) {
            foreach ($category['patterns'] as $pattern) {
                if (str_contains($message, mb_strtolower($pattern))) {
                    return $key;
                }
            }
        }

        return self::OTHER;
    }

    public static function label(string $key): string
    {
        return self::withOther()[$key]['label'] ?? $key;
    }

    /**
     * Schränkt eine Log-Abfrage auf eine Kategorie ein.
     */
    public static function applyFilter($query, ?string $key)
    {
        if (! self::exists($key)) {
            return $query;
        }

        if ($key === self::OTHER) {
            foreach (self::allPatterns() as $pattern) {
                $query->whereRaw('LOWER(COALESCE(message, \'\')) NOT LIKE ?', [self::likeValue($pattern)]);
            }

            return $query;
        }

        $patterns = self::all()[$key]['patterns'];

        return $query->where(function ($q) use ($patterns) {
            foreach ($patterns as $pattern) {
                $q->orWhereRaw('LOWER(message) LIKE ?', [self::likeValue($pattern)]);
            }
        });
    }

    protected static function allPatterns(): array
    {
        return collect(self::all())->pluck('patterns')->flatten()->unique()->values()->all();
    }

    protected static function likeValue(string $pattern): string
    {
        // Die Suchbegriffe sind statisch und enthalten keine LIKE-Platzhalter (% oder _).
        return '%'.mb_strtolower($pattern).'%';
    }
}
