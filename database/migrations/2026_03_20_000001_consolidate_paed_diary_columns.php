<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konsolidierungsmigration für PaedDiary-Spalten (TODO 1)
 *
 * Stellt idempotent sicher, dass alle im Code erwarteten Spalten existieren:
 *   - paed_diary_columns.category
 *   - paed_diary_columns.deactivated_from
 *   - paed_diary_entries.category_id  (FK → paed_diary_categories.id, onDelete set null)
 *   - paed_diary_entries.dossier_only
 *   - users.show_column_categories
 *
 * Jede addColumn-Aktion ist durch Schema::hasColumn geschützt, damit die
 * Migration auch auf einer Datenbank fehlerfrei läuft, auf der einzelne
 * Spalten bereits durch frühere Migrationen angelegt wurden.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── paed_diary_columns ────────────────────────────────────────────
        if (Schema::hasTable('paed_diary_columns')) {
            Schema::table('paed_diary_columns', function (Blueprint $table) {
                if (!Schema::hasColumn('paed_diary_columns', 'category')) {
                    $table->string('category', 100)
                          ->nullable()
                          ->after('name')
                          ->comment('Optionale Kategorie zur Gruppierung von Spalten');
                }
                if (!Schema::hasColumn('paed_diary_columns', 'deactivated_from')) {
                    $table->date('deactivated_from')
                          ->nullable()
                          ->after('active')
                          ->index()
                          ->comment('Spalte ist ab diesem Datum nicht mehr aktiv');
                }
            });
        }

        // ── paed_diary_entries ────────────────────────────────────────────
        if (Schema::hasTable('paed_diary_entries')) {
            Schema::table('paed_diary_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('paed_diary_entries', 'category_id')) {
                    $table->unsignedBigInteger('category_id')
                          ->nullable()
                          ->after('content')
                          ->comment('Verknüpfte Notizkategorie');
                    $table->foreign('category_id')
                          ->references('id')
                          ->on('paed_diary_categories')
                          ->onDelete('set null');
                }
                if (!Schema::hasColumn('paed_diary_entries', 'dossier_only')) {
                    $table->boolean('dossier_only')
                          ->default(false)
                          ->after('category_id')
                          ->comment('Nur im Dossier sichtbar');
                }
            });
        }

        // ── users ─────────────────────────────────────────────────────────
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'show_column_categories')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('show_column_categories')
                      ->default(false)
                      ->after('email')
                      ->comment('Spalten-Kategorien in der PaedDiary-Wochenansicht anzeigen');
            });
        }
    }

    public function down(): void
    {
        // Konsolidierungsmigration – kein Rollback erforderlich.
        // Die einzelnen Spalten werden durch ihre eigenen ursprünglichen
        // Migrationen wieder entfernt, sofern gewünscht.
    }
};

