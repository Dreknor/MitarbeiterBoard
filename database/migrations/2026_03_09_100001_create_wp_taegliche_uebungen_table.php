<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optionaler Toggle am Plan selbst
        Schema::table('wp_plaene', function (Blueprint $table) {
            $table->boolean('taegliche_uebungen_aktiv')->default(false)->after('selbsteinschaetzung');
        });

        // Tägliche-Übungen-Aufgaben
        Schema::create('wp_taegliche_uebungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_plan_id')->constrained('wp_plaene')->cascadeOnDelete();
            $table->text('aufgabe');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('wp_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_taegliche_uebungen');

        Schema::table('wp_plaene', function (Blueprint $table) {
            $table->dropColumn('taegliche_uebungen_aktiv');
        });
    }
};

