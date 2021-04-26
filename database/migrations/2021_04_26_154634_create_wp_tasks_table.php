<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWpTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wp_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wprow_id');
            $table->text('task');
            $table->timestamps();

            $table->foreign('wprow_id')->references('id')->on('wprows');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wp_tasks');
    }
}
