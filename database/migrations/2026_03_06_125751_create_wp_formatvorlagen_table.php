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
        Schema::create('wp_formatvorlagen', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('beschreibung')->nullable();
            $table->string('schriftgroesse', 20)->default('normal');
            $table->string('schriftart', 100)->nullable();
            $table->json('layout_config')->nullable();
            $table->string('blade_template', 255)->default('wochenplan.pdf.standard');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wp_formatvorlagen');
    }
};
