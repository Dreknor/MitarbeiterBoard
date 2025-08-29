<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schueler', function (Blueprint $table) {
            $table->string('import_key')->nullable()->unique();
            $table->index('import_key');
        });
    }

    public function down(): void
    {
        Schema::table('schueler', function (Blueprint $table) {
            $table->dropIndex(['import_key']);
            $table->dropColumn('import_key');
        });
    }
};

