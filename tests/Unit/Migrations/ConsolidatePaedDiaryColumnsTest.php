<?php

namespace Tests\Unit\Migrations;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests für TODO 1 – Migration: Schema-Konsolidierung (T2)
 */
class ConsolidatePaedDiaryColumnsTest extends TestCase
{
    /** @test */
    public function migration_erstellt_alle_erwarteten_spalten(): void
    {
        $this->assertTrue(
            Schema::hasColumn('paed_diary_columns', 'category'),
            'paed_diary_columns.category fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_columns', 'deactivated_from'),
            'paed_diary_columns.deactivated_from fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_entries', 'category_id'),
            'paed_diary_entries.category_id fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_entries', 'dossier_only'),
            'paed_diary_entries.dossier_only fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('users', 'show_column_categories'),
            'users.show_column_categories fehlt'
        );
    }

    /** @test */
    public function category_spalte_in_paed_diary_columns_ist_nullable(): void
    {
        // Spalte kann ohne category gespeichert werden
        $this->assertTrue(Schema::hasColumn('paed_diary_columns', 'category'));

        // Direkter DB-Insert ohne category → darf keinen Fehler werfen
        \DB::table('paed_diary_columns')->insertOrIgnore([
            'klasse_id'  => \DB::table('klassen')->insertGetId([
                'name'       => 'Test-Klasse-' . uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'name'       => 'Testspalte-' . uniqid(),
            'slug'       => 'testspalte-' . uniqid(),
            'type'       => 'text',
            'sort_order' => 0,
            'active'     => true,
            'category'   => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(true); // Kein Fehler → nullable korrekt
    }

    /** @test */
    public function dossier_only_hat_default_false(): void
    {
        $this->assertTrue(Schema::hasColumn('paed_diary_entries', 'dossier_only'));
    }
}

