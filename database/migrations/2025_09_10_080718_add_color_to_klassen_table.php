<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('klassen', function (Blueprint $table) {
            $table->string('color', 7)->default('#ffffff')->after('kuerzel')->comment('Hex color code for the class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('klassen', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
