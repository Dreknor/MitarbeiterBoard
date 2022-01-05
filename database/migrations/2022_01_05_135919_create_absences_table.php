<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbsencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('users_id');
            $table->unsignedBigInteger('creator_id');
            $table->unsignedBigInteger('before')->nullable();
            $table->string('reason', 64);
            $table->date('start');
            $table->date('end');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('users_id')->references('id')->on('users');
            $table->foreign('creator_id')->references('id')->on('users');
            $table->foreign('before')->references('id')->on('absences');
        });

        \Illuminate\Support\Facades\DB::table('permissions')->insert([
            'name' => 'view absences',
            'guard_name'=>'web'
        ]);
        \Illuminate\Support\Facades\DB::table('permissions')->insert([
            'name' => 'create absences',
            'guard_name'=>'web'
        ]);
        \Illuminate\Support\Facades\DB::table('permissions')->insert([
            'name' => 'delete absences',
            'guard_name'=>'web'
        ]);


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('absences');
    }
}
