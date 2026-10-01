<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anwesenheit für Meetings (auch freie Meetings ohne Gruppe).
 */
return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        Schema::table('presences', function (Blueprint $table) use ($isSqlite) {
            if (! $isSqlite) {
                $table->dropForeign(['group_id']);
            }
        });

        Schema::table('presences', function (Blueprint $table) use ($isSqlite) {
            $table->unsignedBigInteger('group_id')->nullable()->change();
            $table->foreignId('meeting_id')->nullable()->after('group_id')->constrained('meetings')->cascadeOnDelete();

            if (! $isSqlite) {
                $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        DB::table('presences')->whereNotNull('meeting_id')->delete();

        Schema::table('presences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meeting_id');
        });
    }
};

