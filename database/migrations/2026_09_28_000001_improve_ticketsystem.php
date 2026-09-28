<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ticketsystem überarbeitet:
 * - tickets.closed_at / closed_by für Archiv und Nachvollziehbarkeit
 * - tickets_pinned eindeutig pro Ticket+User (vorher entstanden Duplikate beim Anpinnen)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('waiting_until');
            $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
            $table->index('status');

            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::table('tickets')
            ->where('status', 'closed')
            ->update(['closed_at' => DB::raw('updated_at')]);

        // Doppelte Pins entfernen (je Ticket+User nur den ältesten behalten)
        $keep = DB::table('tickets_pinned')
            ->selectRaw('MIN(id) as id')
            ->groupBy('ticket_id', 'user_id')
            ->pluck('id')
            ->all();

        if ($keep !== []) {
            DB::table('tickets_pinned')->whereNotIn('id', $keep)->delete();
        }

        Schema::table('tickets_pinned', function (Blueprint $table) {
            $table->unique(['ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets_pinned', function (Blueprint $table) {
            $table->dropUnique(['ticket_id', 'user_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropIndex(['status']);
            $table->dropColumn(['closed_at', 'closed_by']);
        });
    }
};
