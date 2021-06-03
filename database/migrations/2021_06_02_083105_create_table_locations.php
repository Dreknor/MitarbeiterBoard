<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableLocations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inv_locationtypes', function (Blueprint $table){
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

        });

        Schema::create('inv_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('kennzeichnung')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('locationtype_id')->nullable();
            $table->unsignedBigInteger('verantwortlicher_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('locationtype_id')->references('id')->on('inv_locationtypes');
            $table->foreign('verantwortlicher_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('table_locations');
    }
}
