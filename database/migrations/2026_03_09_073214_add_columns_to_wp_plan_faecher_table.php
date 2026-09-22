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
        Schema::table('wp_plan_faecher', function (Blueprint $table) {
            if (!Schema::hasColumn('wp_plan_faecher', 'wp_plan_id')) {
                $table->foreignId('wp_plan_id')->after('id')->constrained('wp_plaene')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('wp_plan_faecher', 'wp_fach_id')) {
                $table->foreignId('wp_fach_id')->after('wp_plan_id')->constrained('wp_faecher')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('wp_plan_faecher', 'custom_name')) {
                $table->string('custom_name', 100)->nullable()->after('wp_fach_id');
            }
            if (!Schema::hasColumn('wp_plan_faecher', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('custom_name');
            }
        });

        // Unique-Constraint hinzufügen, falls noch nicht vorhanden
        try {
            Schema::table('wp_plan_faecher', function (Blueprint $table) {
                $table->unique(['wp_plan_id', 'wp_fach_id'], 'wp_plan_faecher_wp_plan_id_wp_fach_id_unique');
            });
        } catch (\Exception $e) {
            // Constraint existiert bereits
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wp_plan_faecher', function (Blueprint $table) {
            // Unique-Constraint entfernen
            $table->dropUnique('wp_plan_faecher_wp_plan_id_wp_fach_id_unique');

            // Foreign Keys entfernen
            $table->dropForeign(['wp_plan_id']);
            $table->dropForeign(['wp_fach_id']);

            // Spalten entfernen
            $table->dropColumn(['wp_plan_id', 'wp_fach_id', 'custom_name', 'sort_order']);
        });
    }
};
