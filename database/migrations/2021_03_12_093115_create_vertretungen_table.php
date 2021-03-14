<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVertretungenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vertretungen', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('klassen_id');
            $table->unsignedBigInteger('users_id')->nullable();
            $table->integer('stunde')->unsigned();
            $table->string('comment', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('klassen_id')->references('id')->on('klassen');
            $table->foreign('users_id')->references('id')->on('users');
        });


        \Illuminate\Support\Facades\DB::table('permissions')->insert([
            'name' => 'edit vertretungen',
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
        Schema::dropIfExists('vertretungen');
    }
}
