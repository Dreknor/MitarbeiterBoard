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
        Schema::create('wp_aufgaben', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_plan_fach_id')->constrained('wp_plan_faecher')->cascadeOnDelete();
            $table->text('aufgabe');
            $table->string('dauer', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('synced_from_id')->nullable();
            $table->foreign('synced_from_id')->references('id')->on('wp_aufgaben')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('wp_plan_fach_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wp_aufgaben');
    }
};
