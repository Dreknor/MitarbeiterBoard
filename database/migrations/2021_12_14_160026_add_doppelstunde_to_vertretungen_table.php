<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDoppelstundeToVertretungenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vertretungen', function (Blueprint $table) {
            $table->boolean('Doppelstunde')->default(0)->after('stunde');
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
            //
        });
    }
}
