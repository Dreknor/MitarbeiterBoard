<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('paed_diary_entries') && !Schema::hasColumn('paed_diary_entries', 'category_id')) {
            Schema::table('paed_diary_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('content');
                $table->foreign('category_id')->references('id')->on('paed_diary_categories')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('paed_diary_entries') && Schema::hasColumn('paed_diary_entries', 'category_id')) {
            Schema::table('paed_diary_entries', function (Blueprint $table) {
                $table->dropForeign([ 'category_id' ]);
                $table->dropColumn('category_id');
            });
        }
    }
};

