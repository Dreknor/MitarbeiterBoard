<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KI-Kosten mit Bruchteilen eines Cents speichern: günstige Modelle (z. B. Claude Haiku, Gemini Lite)
 * kosten oft deutlich unter 1 Cent je Blatt – aufgerundet wäre das Monatsbudget um ein Vielfaches überschätzt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ab_ki_verbrauch', function (Blueprint $table) {
            $table->decimal('kosten_cent', 12, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ab_ki_verbrauch', function (Blueprint $table) {
            $table->unsignedInteger('kosten_cent')->default(0)->change();
        });
    }
};
