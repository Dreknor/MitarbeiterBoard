<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Themen-Aufgaben: Nachvollziehbarkeit und Duplikat-Schutz.
 *
 * - tasks: Ersteller sowie Zeitpunkt/Person der Erledigung
 * - group_task_users: Erledigung wird markiert statt die Zuordnung zu löschen
 *   (damit sichtbar bleibt, wer erledigt hat), doppelte Zuordnungen werden
 *   bereinigt und per Unique-Index verhindert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('creator_id')->nullable()->after('taskable_id')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completed');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('group_task_users', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('users_id');
        });

        // Doppelte Zuordnungen (gleiche Aufgabe, gleiche Person) entfernen – die älteste bleibt.
        $keep = DB::table('group_task_users')
            ->selectRaw('MIN(id) as id')
            ->groupBy('taskable_id', 'users_id')
            ->pluck('id');

        DB::table('group_task_users')->whereNotIn('id', $keep)->delete();

        Schema::table('group_task_users', function (Blueprint $table) {
            $table->unique(['taskable_id', 'users_id'], 'group_task_users_task_user_unique');
        });

        // Bereits erledigte Aufgaben: Erledigungszeitpunkt näherungsweise übernehmen
        DB::table('tasks')->where('completed', 1)->whereNull('completed_at')->update([
            'completed_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('group_task_users', function (Blueprint $table) {
            $table->dropUnique('group_task_users_task_user_unique');
        });

        // Erledigte Zuordnungen entsprechen dem alten Verhalten "gelöscht"
        DB::table('group_task_users')->whereNotNull('completed_at')->delete();

        Schema::table('group_task_users', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropConstrainedForeignId('creator_id');
            $table->dropColumn('completed_at');
        });
    }
};
