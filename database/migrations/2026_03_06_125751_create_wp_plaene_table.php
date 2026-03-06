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
        Schema::create('wp_plaene', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->date('gueltig_von');
            $table->date('gueltig_bis');
            $table->foreignId('klasse_id')->nullable()->constrained('klassen')->nullOnDelete();
            $table->unsignedBigInteger('schueler_id')->nullable();
            $table->foreign('schueler_id')->references('id')->on('schueler')->nullOnDelete();
            $table->unsignedBigInteger('parent_plan_id')->nullable();
            $table->foreign('parent_plan_id')->references('id')->on('wp_plaene')->nullOnDelete();
            $table->unsignedBigInteger('vorlage_id')->nullable();
            $table->foreign('vorlage_id')->references('id')->on('wp_plaene')->nullOnDelete();
            $table->foreignId('formatvorlage_id')->nullable()->constrained('wp_formatvorlagen')->nullOnDelete();
            $table->smallInteger('selbsteinschaetzung')->default(0);
            $table->boolean('is_vorlage')->default(false);
            $table->string('vorlage_name', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('klasse_id');
            $table->index('schueler_id');
            $table->index('parent_plan_id');
            $table->index('is_vorlage');
            $table->index(['gueltig_von', 'gueltig_bis']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wp_plaene');
    }
};
