<?php

namespace App\Services\UserDeletion;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Eine Löschregel für genau eine Spalte, die auf einen User verweist.
 *
 * Aktionen:
 *  - delete:    Datensätze löschen
 *  - anonymize: Verweis auf das Platzhalter-Konto „Ehemalige Person“ umhängen
 *  - nullify:   Verweis auf NULL setzen
 *  - retain:    bis zum Ablauf der Aufbewahrungskategorie behalten, dann löschen (Stufe 3)
 */
class Rule
{
    public const DELETE = 'delete';
    public const ANONYMIZE = 'anonymize';
    public const NULLIFY = 'nullify';
    public const RETAIN = 'retain';

    public function __construct(
        public readonly string $area,
        public readonly string $label,
        public readonly string $table,
        public readonly string $column,
        public readonly string $action,
        public readonly int $stage,
        public readonly ?string $category = null,
        public readonly ?Closure $scope = null,
        public readonly ?string $auditableType = null,
        public readonly ?array $via = null,
    ) {
    }

    public static function delete(string $area, string $label, string $table, string $column, int $stage = 2, ?Closure $scope = null): self
    {
        return new self($area, $label, $table, $column, self::DELETE, $stage, null, $scope);
    }

    public static function anonymize(string $area, string $label, string $table, string $column, ?Closure $scope = null): self
    {
        return new self($area, $label, $table, $column, self::ANONYMIZE, 2, null, $scope);
    }

    public static function nullify(string $area, string $label, string $table, string $column, int $stage = 2, ?Closure $scope = null): self
    {
        return new self($area, $label, $table, $column, self::NULLIFY, $stage, null, $scope);
    }

    public static function retain(string $area, string $label, string $table, string $column, string $category, ?string $auditableType = null, ?Closure $scope = null): self
    {
        return new self($area, $label, $table, $column, self::RETAIN, 3, $category, $scope, $auditableType);
    }

    /**
     * Aufbewahren von Kind-Datensätzen, die nur über einen Eltern-Datensatz auf den User verweisen
     * (z. B. timesheet_days über timesheets). $column ist dann der Verweis auf die Eltern-Tabelle.
     */
    public static function retainVia(string $area, string $label, string $table, string $column, string $parentTable, string $parentColumn, string $category, ?string $auditableType = null): self
    {
        return new self($area, $label, $table, $column, self::RETAIN, 3, $category, null, $auditableType, [$parentTable, $parentColumn]);
    }

    /**
     * Abfrage auf alle Datensätze dieser Regel für den User.
     */
    public function query(int $userId): Builder
    {
        if ($this->via) {
            [$parentTable, $parentColumn] = $this->via;
            $query = DB::table($this->table)->whereIn(
                $this->table.'.'.$this->column,
                DB::table($parentTable)->where($parentColumn, $userId)->select('id')
            );
        } else {
            $query = DB::table($this->table)->where($this->table.'.'.$this->column, $userId);
        }

        if ($this->scope) {
            ($this->scope)($query);
        }

        return $query;
    }

    public function count(int $userId): int
    {
        return $this->query($userId)->count();
    }

    /**
     * Führt die Aktion aus (für retain-Regeln: endgültiges Löschen) und gibt die Anzahl zurück.
     */
    public function apply(int $userId, int $placeholderId): int
    {
        return match ($this->action) {
            self::ANONYMIZE => $this->query($userId)->update([$this->column => $placeholderId]),
            self::NULLIFY => $this->query($userId)->update([$this->column => null]),
            default => $this->purge($userId),
        };
    }

    private function purge(int $userId): int
    {
        if ($this->auditableType) {
            $ids = $this->query($userId)->pluck($this->table.'.id');

            DB::table('audits')
                ->where('auditable_type', $this->auditableType)
                ->whereIn('auditable_id', $ids)
                ->delete();
        }

        return $this->query($userId)->delete();
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::DELETE => 'löschen',
            self::ANONYMIZE => 'anonymisieren',
            self::NULLIFY => 'Verweis entfernen',
            self::RETAIN => 'aufbewahren',
        };
    }
}
