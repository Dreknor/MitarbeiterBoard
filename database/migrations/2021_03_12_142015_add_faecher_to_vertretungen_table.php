<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFaecherToVertretungenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vertretungen', function (Blueprint $table) {
            $table->string('altFach', 10)->nullable();
            $table->string('neuFach', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vertretungen', function (Blueprint $table) {
            $table->removeColumn('altFach');
            $table->removeColumn('neuFach');
        });
    }
}
