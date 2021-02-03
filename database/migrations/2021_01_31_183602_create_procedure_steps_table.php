<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcedureStepsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procedure_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procedure_id');
            $table->unsignedBigInteger('parent')->nullable();
            $table->unsignedBigInteger('position_id');

            $table->string('name', 60);
            $table->mediumText('description')->nullable();
            $table->unsignedInteger('durationDays')->nullable();
            $table->timestamp('endDate')->nullable();
            $table->boolean('done')->default('0');

            $table->timestamps();
            $table->foreign('procedure_id')->references('id')->on('procedures');
            $table->foreign('parent')->references('id')->on('procedure_steps');
            $table->foreign('position_id')->references('id')->on('positions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procedure_steps');
    }
}
