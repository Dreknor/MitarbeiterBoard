<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTerminListesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('listen', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('listenname');
            $table->string('type');
            $table->text('comment')->nullable();
            $table->unsignedInteger('duration')->default('30');
            $table->unsignedBigInteger('user_id');
            $table->boolean('visible_for_all');
            $table->boolean('active');
            $table->date('ende');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('group_listen', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('liste_id');

            $table->foreign('group_id')->references('id')->on('groups');
            $table->foreign('liste_id')->references('id')->on('listen');
        });

        Schema::create('listen_termine', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('listen_id');
            $table->dateTime('termin');
            $table->unsignedBigInteger('reserviert_fuer')->nullable();
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->foreign('reserviert_fuer')->references('id')->on('users');
            $table->foreign('listen_id')->references('id')->on('listen');
        });

        \Spatie\Permission\Models\Permission::create([
           'name' => 'see terminlisten',
           'guard_name' => 'web'
        ]);
        \Spatie\Permission\Models\Permission::create([
           'name' => 'create Terminliste',
           'guard_name' => 'web'
        ]);
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('listen_termine');
        Schema::dropIfExists('groups_listen');
        Schema::dropIfExists('listen');
    }
}
