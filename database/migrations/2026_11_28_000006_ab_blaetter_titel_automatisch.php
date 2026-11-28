<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Titel aus dem Dateinamen (z. B. „e110 pk2723929“) wird nach der Erkennung durch die Überschrift ersetzt. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ab_blaetter', function (Blueprint $table) {
            $table->boolean('titel_automatisch')->default(false)->after('titel');
        });
    }

    public function down(): void
    {
        Schema::table('ab_blaetter', function (Blueprint $table) {
            $table->dropColumn('titel_automatisch');
        });
    }
};
