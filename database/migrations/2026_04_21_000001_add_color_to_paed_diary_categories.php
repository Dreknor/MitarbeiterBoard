<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('paed_diary_categories') && !Schema::hasColumn('paed_diary_categories', 'color')) {
            Schema::table('paed_diary_categories', function (Blueprint $table) {
                $table->string('color', 7)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paed_diary_categories', 'color')) {
            Schema::table('paed_diary_categories', function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};

