<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        /*
         * Add the akt_id field to the vertretungen table
         * Description: The akt_id field is used to store the akt_id from Indiware Import
         */
        Schema::table('vertretungen', function (Blueprint $table) {
            $table->string('akt_id', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vertretungen', function (Blueprint $table) {
            $table->dropColumn('akt_id');
        });
    }
};
