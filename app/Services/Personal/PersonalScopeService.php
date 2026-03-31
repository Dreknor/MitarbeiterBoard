<?php

namespace App\Services\Personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Prüft Sichtbarkeits- und Zugriffsrechte auf Personalakten.
 * Stellt sicher, dass ein Benutzer nur die Mitarbeiter sieht, die er sehen darf.
 *
 * Implementierung folgt in Phase 1 (P1-01).
 */
class PersonalScopeService
{
    private const CACHE_TTL = 300; // 5 Minuten

    /**
     * Gibt Query-Builder zurück, der nur für $user sichtbare Mitarbeiter liefert.
     * IDOR-Schutz: Immer diesen Builder nutzen, nie User::query() direkt.
     */
    public function visibleEmployees(?User $user = null): Builder
    {
        $user ??= auth()->user();

        if ($user->can('view personal_data:all')) {
            return User::query();
        }

        if ($user->can('view personal_data:department')) {
            return $this->getDepartmentScope($user);
        }

        // Nur eigene Daten (self)
        return User::where('id', $user->id);
    }

    /**
     * Prüft ob $actor auf $target-Mitarbeiter zugreifen darf.
     * Differenziert zwischen 'view' und 'edit' Scopes.
     */
    public function canAccess(User $actor, User $target, string $action = 'view'): bool
    {
        if ($actor->id === $target->id) return true;

        $scope = $this->getScope($actor, $action);

        if ($scope === 'all') return true;
        if ($scope === 'department') {
            return $this->isInDepartment($actor, $target) || $this->isSubordinate($actor, $target);
        }

        return false;
    }

    /**
     * Gibt den höchsten Scope des Users zurück: 'all', 'department', oder 'self'.
     * Unterscheidet zwischen view- und edit-Permissions.
     */
    public function getScope(User $user, string $action = 'view'): string
    {
        $suffix = $action === 'edit' ? 'edit' : 'view';
        return Cache::remember(
            "personal_scope_{$user->id}_{$suffix}",
            self::CACHE_TTL,
            function () use ($user, $suffix) {
                if ($user->can("{$suffix} personal_data:all")) return 'all';
                if ($user->can("{$suffix} personal_data:department")) return 'department';
                return 'self';
            }
        );
    }

    /**
     * Cache invalidieren (aufrufen wenn superior_id oder Gruppenzugehörigkeit sich ändert).
     */
    public function invalidateCache(User $user): void
    {
        Cache::forget("personal_scope_{$user->id}_view");
        Cache::forget("personal_scope_{$user->id}_edit");
        // Auch alle User invalidieren, die $user als Vorgesetzten haben
        User::where('superior_id', $user->id)->each(function ($u) {
            Cache::forget("personal_scope_{$u->id}_view");
            Cache::forget("personal_scope_{$u->id}_edit");
        });
    }

    private function getDepartmentScope(User $user): Builder
    {
        $groupIds = $user->groups_rel()->pluck('groups.id');
        $subordinateIds = $this->getSubordinateIds($user);

        return User::where(function (Builder $q) use ($user, $groupIds, $subordinateIds) {
            $q->where('id', $user->id)
              ->orWhereIn('id', $subordinateIds)
              ->orWhereHas('employments', function (Builder $eq) use ($groupIds) {
                  $eq->whereIn('department_id', $groupIds)
                     ->where('status', 'aktiv');
              });
        });
    }

    /**
     * Gibt alle unterstellten User-IDs zurück (rekursiv über superior_id-Kette).
     * Maximale Tiefe: 5 Ebenen (verhindert Endlosschleifen bei zirkulären Hierarchien).
     */
    public function getSubordinateIds(User $user, int $depth = 0): array
    {
        if ($depth >= 5) return [];

        $directs = User::where('superior_id', $user->id)->pluck('id')->toArray();
        $all = $directs;
        foreach ($directs as $id) {
            $subordinate = User::find($id);
            if ($subordinate) {
                $all = array_merge($all, $this->getSubordinateIds($subordinate, $depth + 1));
            }
        }
        return array_unique($all);
    }

    private function isInDepartment(User $actor, User $target): bool
    {
        $actorGroupIds = $actor->groups_rel()->pluck('groups.id');
        return $target->employments()
            ->whereIn('department_id', $actorGroupIds)
            ->where('status', 'aktiv')
            ->exists();
    }

    private function isSubordinate(User $actor, User $target): bool
    {
        return in_array($target->id, $this->getSubordinateIds($actor));
    }
}
