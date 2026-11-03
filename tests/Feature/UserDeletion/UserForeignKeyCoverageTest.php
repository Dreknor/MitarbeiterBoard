<?php

namespace Tests\Feature\UserDeletion;

use App\Services\UserDeletion\RuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Stellt sicher, dass jede Spalte mit Fremdschlüssel auf users eine Löschregel hat.
 * Schlägt dieser Test fehl, fehlt in RuleRegistry ein Eintrag für eine neue Tabelle.
 */
class UserForeignKeyCoverageTest extends TestCase
{
    private function userForeignKeys(): array
    {
        $columns = [];

        foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'") as $table) {
            foreach (DB::select('PRAGMA foreign_key_list("'.$table->name.'")') as $fk) {
                if ($fk->table === 'users') {
                    $columns[] = $table->name.'.'.$fk->from;
                }
            }
        }

        sort($columns);

        return array_values(array_unique($columns));
    }

    public function test_every_foreign_key_on_users_has_a_deletion_rule(): void
    {
        $covered = app(RuleRegistry::class)->coveredColumns();

        $missing = array_diff($this->userForeignKeys(), $covered);

        $this->assertSame([], array_values($missing), 'Spalten ohne Löschregel in RuleRegistry: '.implode(', ', $missing));
    }

    public function test_every_rule_points_to_an_existing_column(): void
    {
        foreach (app(RuleRegistry::class)->coveredColumns() as $column) {
            [$table, $name] = explode('.', $column);

            $this->assertTrue(Schema::hasColumn($table, $name), "Regel verweist auf fehlende Spalte {$column}");
        }
    }

    public function test_extra_columns_without_foreign_key_are_covered(): void
    {
        $covered = app(RuleRegistry::class)->coveredColumns();

        foreach (RuleRegistry::EXTRA_COLUMNS as $column) {
            $this->assertContains($column, $covered);
        }
    }
}
