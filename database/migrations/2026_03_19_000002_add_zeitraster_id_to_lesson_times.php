<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_times', function (Blueprint $table) {
            // Neue nullable FK-Spalte
            $table->unsignedBigInteger('zeitraster_id')
                ->nullable()
                ->after('week')
                ->comment('NULL = globale Fallback-Zeiten ohne Zeitraster-Zuordnung');

            // FK-Constraint mit SET NULL beim Löschen
            $table->foreign('zeitraster_id')
                ->references('id')
                ->on('zeitraster')
                ->onDelete('set null');

            // Alten Unique-Index entfernen
            $table->dropUnique('unique_period_week');

            // Neuen Unique-Index anlegen (erlaubt mehrere NULLs in zeitraster_id)
            $table->unique(['period', 'week', 'zeitraster_id'], 'unique_period_week_zeitraster');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_times', function (Blueprint $table) {
            // Neuen Index entfernen
            $table->dropUnique('unique_period_week_zeitraster');

            // FK-Constraint entfernen
            $table->dropForeign(['zeitraster_id']);

            // Alten Unique-Index wiederherstellen
            $table->unique(['period', 'week'], 'unique_period_week');

            // Spalte entfernen
            $table->dropColumn('zeitraster_id');
        });
    }
};

