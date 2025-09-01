<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grading_stages') && !Schema::hasColumn('grading_stages','image')) {
            Schema::table('grading_stages', function (Blueprint $table){
                $table->string('image')->nullable()->after('symbol');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('grading_stages') && Schema::hasColumn('grading_stages','image')) {
            Schema::table('grading_stages', function (Blueprint $table){
                $table->dropColumn('image');
            });
        }
    }
};

