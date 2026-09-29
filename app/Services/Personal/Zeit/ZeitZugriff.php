<?php

namespace App\Services\Personal\Zeit;

use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Support\Collection;

/**
 * Gemeinsame Zugriffslogik der Zeitwirtschaft (Urlaub, Arbeitszeitnachweis).
 *
 * Zuständig sind alle Personen oberhalb in der superior_id-Kette (max. 5 Ebenen) sowie deren
 * Stellvertretungen (Tabelle user_deputies): Eine Stellvertretung darf alles, was die vertretene
 * Leitung darf – sofern sie selbst das jeweilige Recht besitzt.
 */
class ZeitZugriff
{
    /** @var array<int, int[]> */
    private array $unterstellte = [];

    public function __construct(private readonly PersonalScopeService $scope)
    {
    }

    /**
     * IDs aller Personen, für die der Benutzer als Leitung oder Stellvertretung zuständig ist.
     *
     * @return int[]
     */
    public function unterstellteIds(User $actor): array
    {
        if (!isset($this->unterstellte[$actor->id])) {
            $ids = $this->scope->getSubordinateIds($actor);

            foreach ($actor->deputyFor()->get() as $leitung) {
                $ids = array_merge($ids, $this->scope->getSubordinateIds($leitung));
            }

            $this->unterstellte[$actor->id] = array_values(array_diff(array_unique(array_map('intval', $ids)), [$actor->id]));
        }

        return $this->unterstellte[$actor->id];
    }

    public function istVorgesetzt(User $actor, User $employe): bool
    {
        if ($actor->id === $employe->id) {
            return false;
        }

        return in_array($employe->id, $this->unterstellteIds($actor), true);
    }

    // ---- Urlaub ----

    public function darfUrlaubGenehmigen(User $actor, User $employe): bool
    {
        if ($actor->id === $employe->id || !$actor->can('approve holidays')) {
            return false;
        }

        return $actor->can('approve all holidays') || $this->istVorgesetzt($actor, $employe);
    }

    public function darfUrlaubErfassenFuer(User $actor, User $employe): bool
    {
        if ($actor->id === $employe->id) {
            return $actor->can('has holidays');
        }

        return $this->darfUrlaubGenehmigen($actor, $employe) || $this->istVorgesetzt($actor, $employe);
    }

    /**
     * Wer über Anträge von $employe entscheidet: die erste Leitung der Vorgesetztenkette, bei der
     * sie selbst oder eine Stellvertretung "approve holidays" hat (alle diese Personen werden
     * benachrichtigt); ohne Treffer alle mit "approve all holidays".
     *
     * @return Collection<int, User>
     */
    public function urlaubsGenehmigende(User $employe): Collection
    {
        $zustaendig = $this->ersteZustaendige($employe, 'approve holidays');

        return $zustaendig->isNotEmpty()
            ? $zustaendig
            : User::permission('approve all holidays')->where('id', '!=', $employe->id)->get();
    }

    // ---- Arbeitszeitnachweis ----

    /**
     * Personal bzw. Vorgesetzte/Stellvertretungen mit "lock timesheets" – dürfen Nachweise anderer bearbeiten und abschließen.
     */
    public function verwaltetNachweiseVon(User $actor, User $employe): bool
    {
        if ($actor->id === $employe->id) {
            return false;
        }

        if ($actor->can('edit employe')) {
            return true;
        }

        return $actor->can('lock timesheets') && $this->istVorgesetzt($actor, $employe);
    }

    public function darfNachweiseSehen(User $actor, User $employe): bool
    {
        return $actor->id === $employe->id || $this->verwaltetNachweiseVon($actor, $employe);
    }

    /**
     * @return Collection<int, User>
     */
    public function nachweisPruefende(User $employe): Collection
    {
        $zustaendig = $this->ersteZustaendige($employe, 'lock timesheets', 1);

        return $zustaendig->isNotEmpty()
            ? $zustaendig
            : User::permission('edit employe')->where('id', '!=', $employe->id)->get();
    }

    // =========================================================================

    /**
     * Geht die Vorgesetztenkette hoch und liefert bei der ersten Ebene, auf der die Leitung oder eine
     * ihrer Stellvertretungen das Recht hat, genau diese Personen.
     *
     * @return Collection<int, User>
     */
    private function ersteZustaendige(User $employe, string $recht, int $maxEbenen = 5): Collection
    {
        $geprueft = [];
        $leitung = $employe->superior;

        while ($leitung !== null && !in_array($leitung->id, $geprueft, true) && count($geprueft) < $maxEbenen) {
            $geprueft[] = $leitung->id;

            $kandidaten = collect([$leitung])
                ->merge($leitung->deputies)
                ->unique('id')
                ->filter(fn (User $u) => $u->id !== $employe->id && $u->can($recht))
                ->values();

            if ($kandidaten->isNotEmpty()) {
                return $kandidaten;
            }

            $leitung = $leitung->superior;
        }

        return collect();
    }
}
