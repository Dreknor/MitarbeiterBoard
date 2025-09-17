<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a nullable 'category' column to paed_diary_columns so columns can be grouped.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('paed_diary_columns')) return;

        Schema::table('paed_diary_columns', function (Blueprint $table) {
            if (!Schema::hasColumn('paed_diary_columns', 'category')) {
                $table->string('category', 100)->nullable()->after('name')->comment('Optional category for grouping columns');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (!Schema::hasTable('paed_diary_columns')) return;

        Schema::table('paed_diary_columns', function (Blueprint $table) {
            if (Schema::hasColumn('paed_diary_columns', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};

