<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schnellmodus: Blatt hochladen → fertige Fassungen. `schnell_ziele` = für wen die Fassungen
 * entstehen ({"kinder": [ids], "vorlagen": [ids]}); null = erweiterter Modus (Prüf-Editor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ab_blaetter', function (Blueprint $table) {
            $table->json('schnell_ziele')->nullable()->after('ki_hinweise');
        });
    }

    public function down(): void
    {
        Schema::table('ab_blaetter', function (Blueprint $table) {
            $table->dropColumn('schnell_ziele');
        });
    }
};
