<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDurationFieldToWPTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wp_tasks', function (Blueprint $table) {
            $table->string('duration')->nullable()->after('task');
        });


        Schema::table('wochenplaene', function (Blueprint $table) {
            $table->boolean('hasDuration')->nullable()->after('group_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wp_taks', function (Blueprint $table) {
            //
        });
    }
}
