<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Löschkonzept: Fremdschlüssel auf users, die beim Löschen eines Users Inhalte der Einrichtung
 * (Schülerdokumentation, Beiträge, Wochenpläne) oder aufbewahrungspflichtige Personaldaten
 * per Cascade mitgelöscht hätten, werden auf RESTRICT umgestellt.
 *
 * Gelöscht wird nur noch über den UserDeletionService. Ein vergessener Bereich lässt
 * forceDelete() laut scheitern, statt still Daten zu vernichten.
 *
 * SQLite (Tests) unterstützt das Ändern von Fremdschlüsseln nicht, daher nur MySQL/MariaDB.
 */
return new class extends Migration
{
    private array $columns = [
        // Inhalte der Einrichtung
        ['posts', 'author_id'],
        ['meeting_tasks', 'user_id'],
        ['paed_diary_entries', 'user_id'],
        ['paed_diary_tasks', 'created_by'],
        ['paed_diary_goals', 'user_id'],
        ['paed_diary_schueler_absences', 'marked_by'],
        ['paed_diary_appointments', 'user_id'],
        ['diagnostic_sessions', 'user_id'],
        ['diagnostic_goal_comments', 'user_id'],
        ['grading_documentation_sessions', 'user_id'],
        ['wp_plaene', 'created_by'],
        ['wp_formatvorlagen', 'created_by'],
        // Personalakte (Aufbewahrungsfristen)
        ['contract_audits', 'employe_id'],
        ['pers_documents', 'employe_id'],
        ['pers_consents', 'employe_id'],
        ['pers_employee_qualifications', 'employe_id'],
        ['pers_training_participants', 'employe_id'],
        ['timesheet_anomalies', 'employe_id'],
        ['pers_access_logs', 'user_id'],
    ];

    public function up(): void
    {
        $this->switchTo('restrict');
    }

    public function down(): void
    {
        $this->switchTo('cascade');
    }

    private function switchTo(string $onDelete): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->columns as [$table, $column]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column, $onDelete) {
                $blueprint->foreign($column)->references('id')->on('users')->onDelete($onDelete);
            });
        }
    }
};
