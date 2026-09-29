<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stellvertretungen von Leitungen: Eine Stellvertretung darf alles genehmigen bzw. prüfen,
 * was die vertretene Leitung darf (Urlaub, Arbeitszeitnachweise ihrer Unterstellten).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_deputies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();    // Leitung
            $table->foreignId('deputy_id')->constrained('users')->cascadeOnDelete();  // Stellvertretung
            $table->timestamps();
            $table->unique(['user_id', 'deputy_id']);
            $table->index('deputy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_deputies');
    }
};
