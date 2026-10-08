<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\TagesvorschauEinstellung;
use App\Models\User;

abstract class BasisQuelle implements TagesvorschauQuelle
{
    public function sichtbarFuer(User $user): bool
    {
        return true;
    }

    /**
     * Ist der Bereich aktiv, solange die Person noch keine eigene Auswahl getroffen hat?
     * false = Opt-in (muss ausdrücklich ausgewählt werden).
     */
    public function standardAktiv(): bool
    {
        return true;
    }

    /**
     * Ohne eigene Auswahl (bereiche = null) gilt standardAktiv(), sonst die Auswahl.
     */
    public function aktivFuer(User $user, TagesvorschauEinstellung $einstellung): bool
    {
        $bereiche = $einstellung->bereiche;

        return $bereiche === null
            ? $this->standardAktiv()
            : in_array($this->bereich(), $bereiche, true);
    }
}
