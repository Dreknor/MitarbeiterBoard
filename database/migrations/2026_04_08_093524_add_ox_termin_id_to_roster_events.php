<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roster_events', function (Blueprint $table) {
            if (!Schema::hasColumn('roster_events', 'ox_termin_id')) {
                $table->unsignedBigInteger('ox_termin_id')->nullable()->after('event');
                $table->foreign('ox_termin_id')->references('id')->on('ox_termine')->nullOnDelete();
            }
        });
    }
    public function down(): void
    {
        Schema::table('roster_events', function (Blueprint $table) {
            $table->dropForeign(['ox_termin_id']);
            $table->dropColumn('ox_termin_id');
        });
    }
};
