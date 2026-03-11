<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('klassen', function (Blueprint $table) {
            $table->boolean('show_vertretungen')
                ->default(true)
                ->after('color')
                ->comment('Vertretungen auf dem öffentlichen VP und ElternInfoBoard anzeigen; false = nur Raumbuchungen');
        });
    }

    public function down(): void
    {
        Schema::table('klassen', function (Blueprint $table) {
            $table->dropColumn('show_vertretungen');
        });
    }
};

