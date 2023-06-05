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

        Spatie\Permission\Models\Permission::create([
            'name' => 'has timesheet',
            'guard_name' => 'web'
        ]);

        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employe_id');
            $table->unsignedSmallInteger('month');
            $table->unsignedInteger('year');
            $table->unsignedSmallInteger('holidays_old')->nullable();
            $table->unsignedSmallInteger('holidays_new' )->nullable();
            $table->unsignedSmallInteger('holidays_rest' )->nullable();
            $table->integer('working_time_account');
            $table->string('comment', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employe_id')->references('id')->on('users');
        });

        Schema::create('timesheet_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('timesheet_id');
            $table->date('date');
            $table->time('start')->nullable();
            $table->time('end')->nullable();
            $table->unsignedSmallInteger('pause' )->nullable();
            $table->integer('percent_of_workingtime')->nullable();
            $table->string('comment', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('timesheet_id')->references('id')->on('timesheets');

        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('timesheets');
    }
};
