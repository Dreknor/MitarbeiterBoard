<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRosterChecksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roster_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->string('type');
            $table->string('operator', 3)->default('=');
            $table->string('check_name');
            $table->string('field_name');
            $table->string('value');
            $table->unsignedInteger('weekday', 0);
            $table->unsignedInteger('needs', 0)->default(1);
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('groups');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roster_checks');
    }
}
