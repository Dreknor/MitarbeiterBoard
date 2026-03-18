<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('klassen', function (Blueprint $table) {
            $table->unsignedBigInteger('zeitraster_id')
                ->nullable()
                ->after('show_vertretungen')
                ->comment('NULL = Standard-Zeitraster verwenden');

            $table->foreign('zeitraster_id')
                ->references('id')
                ->on('zeitraster')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('klassen', function (Blueprint $table) {
            $table->dropForeign(['zeitraster_id']);
            $table->dropColumn('zeitraster_id');
        });
    }
};

