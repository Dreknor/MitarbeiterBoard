<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitwirtschaft P2: Dienstplan-Workflow.
 *
 * - Veröffentlichung mit Zeitpunkt/Person, Änderungsprotokoll nach Veröffentlichung
 *   (Grundlage für Benachrichtigungen der betroffenen Mitarbeitenden).
 * - Tagesfenster des Planungsrasters je Abteilung statt fest 08:00–14:30.
 * - Persönlicher ICS-Feed "Mein Dienstplan".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rosters', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('published');
            $table->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('roster_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained('rosters')->cascadeOnDelete();
            $table->foreignId('employe_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('description', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->index(['roster_id', 'notified_at']);
        });

        // Automatisch gesetzte Abwesenheits-Markierungen (Urlaub, krank …) von geplanten Terminen unterscheiden
        Schema::table('roster_events', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('event');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->time('roster_day_start')->nullable();
            $table->time('roster_day_end')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('roster_feed_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['roster_feed_token']);
            $table->dropColumn('roster_feed_token');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['roster_day_start', 'roster_day_end']);
        });

        Schema::table('roster_events', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::dropIfExists('roster_changes');

        Schema::table('rosters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn('published_at');
        });
    }
};
