<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 / §8.2 – Erweiterte Auslöser für wiederkehrende Prozesse.
 *
 * Fügt `active`, `weekday`, `weekday_interval`, `schuljahres_tag`,
 * `schuljahres_monat`, `last_triggered_at`, `next_trigger_at` hinzu und
 * erweitert das ENUM `faelligkeit_typ` um `wochentag` und `schuljahres_stichtag`.
 *
 * Hinweis: SQLite (Test-Umgebung) hat keine echten ENUMs – `faelligkeit_typ`
 * wird dort als TEXT gespeichert. Daher wird das ENUM-Statement nur für
 * MySQL/MariaDB ausgeführt.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('recurring_procedures', function (Blueprint $table) {
            if (!Schema::hasColumn('recurring_procedures', 'active')) {
                $table->boolean('active')->default(true)->after('procedure_id');
            }
            if (!Schema::hasColumn('recurring_procedures', 'weekday')) {
                $table->tinyInteger('weekday')->nullable()->after('month');
            }
            if (!Schema::hasColumn('recurring_procedures', 'weekday_interval')) {
                $table->tinyInteger('weekday_interval')->nullable()->after('weekday');
            }
            if (!Schema::hasColumn('recurring_procedures', 'schuljahres_tag')) {
                $table->tinyInteger('schuljahres_tag')->nullable()->after('weekday_interval');
            }
            if (!Schema::hasColumn('recurring_procedures', 'schuljahres_monat')) {
                $table->tinyInteger('schuljahres_monat')->nullable()->after('schuljahres_tag');
            }
            if (!Schema::hasColumn('recurring_procedures', 'last_triggered_at')) {
                $table->timestamp('last_triggered_at')->nullable()->after('ferien');
            }
            if (!Schema::hasColumn('recurring_procedures', 'next_trigger_at')) {
                $table->timestamp('next_trigger_at')->nullable()->after('last_triggered_at');
            }
        });

        // ENUM erweitern (nur MySQL/MariaDB)
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            try {
                DB::statement(
                    "ALTER TABLE recurring_procedures MODIFY COLUMN faelligkeit_typ "
                    . "ENUM('datum','vor_ferien','nach_ferien','wochentag','schuljahres_stichtag') NOT NULL"
                );
            } catch (\Throwable $e) {
                // Falls die Spalte bereits den erweiterten ENUM hat, ignorieren.
            }
        }
    }

    public function down(): void
    {
        Schema::table('recurring_procedures', function (Blueprint $table) {
            foreach ([
                'active', 'weekday', 'weekday_interval', 'schuljahres_tag',
                'schuljahres_monat', 'last_triggered_at', 'next_trigger_at',
            ] as $col) {
                if (Schema::hasColumn('recurring_procedures', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

