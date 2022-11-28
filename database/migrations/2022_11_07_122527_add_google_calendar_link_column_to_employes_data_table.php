<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleCalendarLinkColumnToEmployesDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('employes_data', function (Blueprint $table) {
            $table->text('google_calendar_link')->nullable();
            $table->uuid('caldav_uuid')->nullable()->unique();
            $table->boolean('caldav_working_time')->nullable();
            $table->boolean('caldav_events')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employes', function (Blueprint $table) {
            //
        });
    }
}
