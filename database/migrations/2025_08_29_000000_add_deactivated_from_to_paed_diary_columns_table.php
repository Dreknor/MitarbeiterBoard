<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeactivatedFromToPaedDiaryColumnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('paed_diary_columns', function (Blueprint $table) {
            $table->date('deactivated_from')->nullable()->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('paed_diary_columns', function (Blueprint $table) {
            $table->dropColumn('deactivated_from');
        });
    }
}
