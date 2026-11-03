<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ein Bereich der Tagesübersicht („Dein Tag“), z. B. Vertretungen oder Termine.
 * Registrierung in config/benachrichtigungen.php unter tagesvorschau.quellen.
 */
interface TagesvorschauQuelle
{
    /** Eindeutiger Schlüssel, wird in tagesvorschau_einstellungen.bereiche gespeichert */
    public function bereich(): string;

    public function label(): string;

    /** FontAwesome-Klasse, z. B. "fa-exchange-alt" */
    public function icon(): string;

    /** Darf die Person diesen Bereich überhaupt sehen (Permission)? */
    public function sichtbarFuer(User $user): bool;

    /** Hat die Person den Bereich in ihren Einstellungen aktiviert? */
    public function aktivFuer(User $user, TagesvorschauEinstellung $einstellung): bool;

    /**
     * Einträge für den Zieltag.
     *
     * @return Collection<int, TagesvorschauEintrag>
     */
    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection;
}
