<?php

namespace App\Services\UserDeletion;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Eine offene Verantwortung, die vor der Deaktivierung an eine andere Person übergeben wird.
 *
 * Ohne Ziel wird ein nullable Verweis entfernt; nicht-nullable Verweise bleiben bestehen
 * und werden in Stufe 2 auf „Ehemalige Person“ umgehängt.
 */
class Handover
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $table,
        public readonly string $column,
        public readonly bool $nullable,
        public readonly ?Closure $scope = null,
        public readonly ?string $pivotColumn = null,
    ) {
    }

    public function query(int $userId): Builder
    {
        $query = DB::table($this->table)->where($this->column, $userId);

        if ($this->scope) {
            ($this->scope)($query);
        }

        return $query;
    }

    public function count(int $userId): int
    {
        return $this->query($userId)->count();
    }

    public function apply(int $userId, ?int $targetId): int
    {
        if ($targetId === null) {
            if ($this->pivotColumn) {
                return $this->query($userId)->delete();
            }

            return $this->nullable ? $this->query($userId)->update([$this->column => null]) : 0;
        }

        if ($this->pivotColumn) {
            // Pivot: Zeilen, in denen das Ziel schon eingetragen ist, entfernen statt doppeln
            $existing = DB::table($this->table)->where($this->column, $targetId)->pluck($this->pivotColumn);
            $this->query($userId)->whereIn($this->pivotColumn, $existing)->delete();
        }

        return $this->query($userId)->update([$this->column => $targetId]);
    }

    /**
     * @return Handover[]
     */
    public static function all(): array
    {
        $today = fn () => now()->toDateString();

        return [
            new self('subordinates', 'Vorgesetzte(r) von Mitarbeitenden', 'users', 'superior_id', true,
                fn (Builder $q) => $q->whereNull('deleted_at')),
            new self('deputies', 'Stellvertretung für Mitarbeitende', 'users', 'deputy_id', true,
                fn (Builder $q) => $q->whereNull('deleted_at')),
            new self('tickets', 'Offene Tickets (zugewiesen)', 'tickets', 'assigned_to', true,
                fn (Builder $q) => $q->where('status', '!=', 'closed')),
            new self('themes', 'Offene Themen (zugewiesen)', 'themes', 'assigned_to', true,
                fn (Builder $q) => $q->where('completed', false)),
            new self('procedure_steps', 'Offene Prozessschritte', 'steps_users', 'users_id', false,
                fn (Builder $q) => $q->whereIn('steps_id', DB::table('procedure_steps')->where('done', false)->select('id')),
                'steps_id'),
            new self('recurring_themes', 'Wiederkehrende Themen', 'recurring_themes', 'creator_id', false),
            new self('inventory', 'Verantwortung für Inventarstandorte', 'inv_locations', 'verantwortlicher_id', true),
            new self('meeting_tasks', 'Aufgaben in künftigen Sitzungen', 'meeting_tasks', 'user_id', false,
                fn (Builder $q) => $q->whereIn('meeting_id', DB::table('meetings')->where('date', '>=', $today())->select('id'))),
            new self('diagnostics', 'Laufende Diagnostik', 'diagnostic_sessions', 'user_id', false,
                fn (Builder $q) => $q->where('is_completed', false)),
            new self('wochenplaene', 'Aktuelle Wochenpläne', 'wp_plaene', 'created_by', false,
                fn (Builder $q) => $q->where('gueltig_bis', '>=', $today())),
            new self('formatvorlagen', 'Wochenplan-Formatvorlagen', 'wp_formatvorlagen', 'created_by', false),
        ];
    }
}
