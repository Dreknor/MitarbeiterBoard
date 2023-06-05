<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employes_data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('familienname')->nullable();
            $table->string('geburtsname')->nullable();
            $table->string('vorname')->nullable();
            $table->date('geburtstag')->nullable();
            $table->set('geschlecht', ['männlich', 'weiblich','anderes']);
            $table->string('sozialversicherungsnummer')->nullable();
            $table->string('geburtsort')->nullable();
            $table->string('staatsangehoerigkeit')->default('deutsch');
            $table->boolean('schwerbehindert')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employes_data');
    }
}
