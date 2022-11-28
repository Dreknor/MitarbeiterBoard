<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmploymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employe_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('hour_type_id');
            $table->date('start');
            $table->date('end')->nullable();
            $table->decimal('hours', 5,2);
            $table->string('comment')->nullable();
            $table->unsignedBigInteger('replaced_employment_id')->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employe_id')->references('id')->on('users');
            $table->foreign('department_id')->references('id')->on('groups');
            $table->foreign('hour_type_id')->references('id')->on('hour_types');
            $table->foreign('replaced_employment_id')->references('id')->on('employments');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employments');
    }
}
