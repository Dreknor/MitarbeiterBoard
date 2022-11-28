<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRosterNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roster_news', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('roster_id');
            $table->text('news');
            $table->timestamps();

            $table->foreign('roster_id')->references('id')->on('rosters')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roster_news');
    }
}
