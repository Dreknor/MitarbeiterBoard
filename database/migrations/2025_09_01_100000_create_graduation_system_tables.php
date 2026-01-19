<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grading systems
        if (!Schema::hasTable('grading_systems')) {
            Schema::create('grading_systems', function (Blueprint $table){
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        // Stages for each system
        if (!Schema::hasTable('grading_stages')) {
            Schema::create('grading_stages', function (Blueprint $table){
                $table->id();
                $table->unsignedBigInteger('grading_system_id');
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('symbol')->nullable(); // z.B. Icon-Class oder Pfad
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->foreign('grading_system_id')->references('id')->on('grading_systems')->onDelete('cascade');
                $table->unique(['grading_system_id','slug'],'grading_sys_stage_slug_uq');
            });
        }

        // Link Klasse -> which grading system applies
        if (!Schema::hasColumn('klassen','grading_system_id')) {
            Schema::table('klassen', function (Blueprint $table){
                $table->unsignedBigInteger('grading_system_id')->nullable()->after('kuerzel');
                $table->foreign('grading_system_id')->references('id')->on('grading_systems')->onDelete('set null');
            });
        }

        // Current stage for Schueler
        if (!Schema::hasColumn('schueler','grading_stage_id')) {
            Schema::table('schueler', function (Blueprint $table){
                $table->unsignedBigInteger('grading_stage_id')->nullable()->after('klasse_id');
                $table->foreign('grading_stage_id')->references('id')->on('grading_stages')->onDelete('set null');
            });
        }

        // History of grading changes (linked to paed diary entry when available)
        if (!Schema::hasTable('schueler_grading_histories')) {
            Schema::create('schueler_grading_histories', function (Blueprint $table){
                $table->id();
                $table->unsignedBigInteger('schueler_id');
                $table->unsignedBigInteger('grading_system_id')->nullable();
                $table->unsignedBigInteger('grading_stage_id')->nullable();
                $table->unsignedBigInteger('previous_grading_stage_id')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->unsignedBigInteger('paed_diary_entry_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('schueler_id')->references('id')->on('schueler')->onDelete('cascade');
                $table->foreign('grading_system_id')->references('id')->on('grading_systems')->onDelete('set null');
                $table->foreign('grading_stage_id')->references('id')->on('grading_stages')->onDelete('set null');
                $table->foreign('previous_grading_stage_id')->references('id')->on('grading_stages')->onDelete('set null');
                $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('paed_diary_entry_id')->references('id')->on('paed_diary_entries')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('schueler_grading_histories')) Schema::dropIfExists('schueler_grading_histories');

        if (Schema::hasColumn('schueler','grading_stage_id')) {
            Schema::table('schueler', function (Blueprint $table){
                $table->dropForeign(['grading_stage_id']);
                $table->dropColumn('grading_stage_id');
            });
        }

        if (Schema::hasColumn('klassen','grading_system_id')) {
            Schema::table('klassen', function (Blueprint $table){
                $table->dropForeign(['grading_system_id']);
                $table->dropColumn('grading_system_id');
            });
        }

        if (Schema::hasTable('grading_stages')) Schema::dropIfExists('grading_stages');
        if (Schema::hasTable('grading_systems')) Schema::dropIfExists('grading_systems');
    }
};

