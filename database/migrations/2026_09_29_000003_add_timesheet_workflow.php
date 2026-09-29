<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitwirtschaft P2: Monatsabschluss als Workflow und Herkunft von Tagesbuchungen.
 *
 * - Mitarbeiter reicht ein → Vorgesetzte/Personal bestätigt (= sperrt) oder gibt zurück.
 * - timesheet_days.source kennzeichnet automatisch erzeugte Zeilen (Urlaub/Abwesenheit),
 *   damit sie bei Änderungen neu abgeglichen werden können, ohne manuelle Einträge anzufassen.
 * - Urlaubsfelder werden vorzeichenbehaftet und halbtagsfähig.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->decimal('holidays_old', 5, 1)->nullable()->change();
            $table->decimal('holidays_new', 5, 1)->nullable()->change();
            $table->decimal('holidays_rest', 5, 1)->nullable()->change();
            $table->timestamp('submitted_at')->nullable()->after('locked_by');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->string('return_reason', 255)->nullable()->after('submitted_by');
            // Bis zu diesem Tag wurde der Dienstplan bereits automatisch übernommen (keine Wiederholung nach Löschen)
            $table->date('plan_uebernommen_bis')->nullable()->after('return_reason');
        });

        Schema::table('timesheet_days', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('comment');
            $table->foreignId('holiday_id')->nullable()->after('source')->constrained('holidays')->nullOnDelete();
            $table->foreignId('absence_id')->nullable()->after('holiday_id')->constrained('absences')->nullOnDelete();
            $table->index(['timesheet_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_days', function (Blueprint $table) {
            $table->dropIndex(['timesheet_id', 'date']);
            $table->dropConstrainedForeignId('absence_id');
            $table->dropConstrainedForeignId('holiday_id');
            $table->dropColumn('source');
        });

        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['submitted_at', 'return_reason', 'plan_uebernommen_bis']);
        });
    }
};
