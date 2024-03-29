<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employe_holiday_claims', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('employe_id');
            $table->integer('holiday_claim')->default(26);
            $table->date('date_start');
            $table->unsignedBigInteger('changedBy');

            $table->foreign('employe_id')->references('id')->on('users');
            $table->foreign('changedBy')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('holiday_claim');
    }
};
