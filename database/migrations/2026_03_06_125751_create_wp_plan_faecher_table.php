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
        Schema::create('wp_plan_faecher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_plan_id')->constrained('wp_plaene')->cascadeOnDelete();
            $table->foreignId('wp_fach_id')->constrained('wp_faecher')->cascadeOnDelete();
            $table->string('custom_name', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['wp_plan_id', 'wp_fach_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wp_plan_faecher');
    }
};
