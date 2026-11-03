<?php

namespace App\Services\Personal\Zeit;

use App\Models\personal\OrgPosition;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Leitet Vorgesetzte und Stellvertretungen aus dem Organigramm ab (nur Vorschläge – übernommen
 * wird erst auf Knopfdruck, damit gepflegte Zuordnungen nicht überschrieben werden).
 *
 *  - Leitung: aktuelle Inhaber einer Stelle mit is_leadership (nicht als Stellvertretung).
 *  - Stellvertretungen einer Leitung: weitere Inhaber derselben Leitungsstelle und die dort als
 *    Stellvertretung (is_deputy) eingetragenen Personen.
 *  - Vorgesetzte einer Person: erste Leitung der nächsthöheren Leitungsstelle oberhalb ihrer Stelle.
 *    Ohne eigene Stelle: die oberste Leitungsstelle der Abteilung aus dem aktuellen Vertrag.
 */
class OrganigrammZuordnung
{
    /** @var Collection<int, OrgPosition>|null */
    private ?Collection $stellen = null;

    /**
     * @return Collection<int, OrgPosition> alle Stellen mit aktuell gültigen Zuordnungen, nach ID
     */
    public function stellen(): Collection
    {
        return $this->stellen ??= OrgPosition::query()
            ->with(['users' => fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('pers_org_position_user.valid_from')->orWhere('pers_org_position_user.valid_from', '<=', now()->toDateString()))
                ->where(fn ($w) => $w->whereNull('pers_org_position_user.valid_until')->orWhere('pers_org_position_user.valid_until', '>=', now()->toDateString()))
                ->orderBy('pers_org_position_user.valid_from')])
            ->get()
            ->keyBy('id');
    }

    public function istLeer(): bool
    {
        return $this->stellen()->isEmpty();
    }

    /**
     * @return array<int, string> user_id => Stellenbezeichnung aller aktuellen Leitungen
     */
    public function leitungen(): array
    {
        $ergebnis = [];
        foreach ($this->stellen()->where('is_leadership', true) as $stelle) {
            foreach ($this->inhaber($stelle) as $user) {
                $ergebnis[$user->id] ??= $stelle->name;
            }
        }

        return $ergebnis;
    }

    /**
     * Vorgeschlagene vorgesetzte Person je Mitarbeiter.
     *
     * @param Collection<int, User> $personen
     * @return array<int, array{superior: User, stelle: string, quelle: string}> user_id => Vorschlag
     */
    public function vorgesetzte(Collection $personen): array
    {
        if ($this->istLeer()) {
            return [];
        }

        $eigeneStellen = [];
        foreach ($this->stellen() as $stelle) {
            foreach ($stelle->users as $user) {
                if (!$user->pivot->is_deputy) {
                    $eigeneStellen[$user->id][] = $stelle;
                }
            }
        }

        $vorschlaege = [];
        foreach ($personen as $person) {
            $treffer = null;

            foreach ($eigeneStellen[$person->id] ?? [] as $stelle) {
                $treffer = $this->naechsteLeitungOberhalb($stelle, $person);
                if ($treffer !== null) {
                    $treffer['quelle'] = 'Stelle „'.$stelle->name.'“';
                    break;
                }
            }

            if ($treffer === null && !isset($eigeneStellen[$person->id])) {
                $treffer = $this->leitungDerAbteilung($person);
            }

            if ($treffer !== null) {
                $vorschlaege[$person->id] = $treffer;
            }
        }

        return $vorschlaege;
    }

    /**
     * Vorgeschlagene Stellvertretungen: [[leitung, stellvertretung, stelle], …]
     *
     * @return array<int, array{leitung: User, vertretung: User, stelle: string}>
     */
    public function stellvertretungen(): array
    {
        $paare = [];
        foreach ($this->stellen()->where('is_leadership', true) as $stelle) {
            $inhaber = $this->inhaber($stelle);
            $leitung = $inhaber->first();
            if ($leitung === null) {
                continue;
            }

            $vertretungen = $inhaber->slice(1)
                ->merge($stelle->users->filter(fn ($u) => $u->pivot->is_deputy))
                ->unique('id')
                ->reject(fn ($u) => $u->id === $leitung->id);

            foreach ($vertretungen as $vertretung) {
                $paare[$leitung->id.'-'.$vertretung->id] = ['leitung' => $leitung, 'vertretung' => $vertretung, 'stelle' => $stelle->name];
            }
        }

        return array_values($paare);
    }

    // =========================================================================

    /**
     * @return Collection<int, User>
     */
    private function inhaber(OrgPosition $stelle): Collection
    {
        return $stelle->users->reject(fn ($u) => (bool) $u->pivot->is_deputy)->values();
    }

    /**
     * Nächste Leitungsstelle oberhalb (bei einer Leitungsstelle: erst die darüber), deren Inhaber nicht die Person selbst ist.
     */
    private function naechsteLeitungOberhalb(OrgPosition $stelle, User $person): ?array
    {
        $besucht = [];
        $aktuell = $stelle->parent_position_id ? $this->stellen()->get($stelle->parent_position_id) : null;

        while ($aktuell !== null && !in_array($aktuell->id, $besucht, true) && count($besucht) < 20) {
            $besucht[] = $aktuell->id;
            if ($aktuell->is_leadership) {
                $leitung = $this->inhaber($aktuell)->first(fn ($u) => $u->id !== $person->id);
                if ($leitung !== null) {
                    return ['superior' => $leitung, 'stelle' => $aktuell->name, 'quelle' => ''];
                }
            }
            $aktuell = $aktuell->parent_position_id ? $this->stellen()->get($aktuell->parent_position_id) : null;
        }

        return null;
    }

    private function leitungDerAbteilung(User $person): ?array
    {
        $abteilungen = $person->employments
            ->filter(fn ($e) => $e->start && $e->start->lte(now()) && ($e->end === null || $e->end->gte(now())))
            ->pluck('department_id')
            ->filter()
            ->unique();

        foreach ($abteilungen as $abteilungId) {
            $stelle = $this->stellen()
                ->where('is_leadership', true)
                ->where('department_id', $abteilungId)
                ->sortBy([['level', 'asc'], ['sort_order', 'asc']])
                ->first(fn (OrgPosition $s) => $this->inhaber($s)->contains(fn ($u) => $u->id !== $person->id));

            if ($stelle !== null) {
                return [
                    'superior' => $this->inhaber($stelle)->first(fn ($u) => $u->id !== $person->id),
                    'stelle' => $stelle->name,
                    'quelle' => 'Abteilung laut Vertrag',
                ];
            }
        }

        return null;
    }
}
