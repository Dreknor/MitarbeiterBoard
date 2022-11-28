<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRosterEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roster_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('roster_id');
            $table->unsignedBigInteger('employe_id')->nullable();
            $table->date('date');
            $table->time('start');
            $table->time('end');
            $table->string('event');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('roster_id')->references('id')->on('rosters');
            $table->foreign('employe_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roster_events');
    }
}
