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
        Schema::create('group_task_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('taskable_id');
            $table->unsignedBigInteger('users_id');
            $table->timestamps();

            $table->foreign('taskable_id')->references('id')->on('tasks');
            $table->foreign('users_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_task_users');
    }
};
