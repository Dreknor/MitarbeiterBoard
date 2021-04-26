<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWochenplaeneTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wochenplaene', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->dateTime('gueltig_ab');
            $table->dateTime('gueltig_bis');
            $table->unsignedBigInteger('group_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('group_id')->references('id')->on('groups');
        });

        Schema::create('wps_klassen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wochenplan_id');
            $table->unsignedBigInteger('klasse_id');
            $table->timestamps();

            $table->foreign('wochenplan_id')->references('id')->on('wochenplaene');
            $table->foreign('klasse_id')->references('id')->on('klassen');
        });

        \Illuminate\Support\Facades\DB::table('permissions')->insert([
            'name' => 'create Wochenplan',
            'guard_name'=>'web'
        ]);

        \Illuminate\Support\Facades\Artisan::call('cache:clear');

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wochenplaene');
    }
}
