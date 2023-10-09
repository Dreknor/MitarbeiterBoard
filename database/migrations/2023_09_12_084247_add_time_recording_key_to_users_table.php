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
        Schema::table('employes_data', function (Blueprint $table) {
            $table->string('secret_key', 15)->nullable();
            $table->string('time_recording_key')->nullable()->unique('time_recording_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employes_data', function (Blueprint $table) {
            $table->dropColumn('time_recording_key');
            $table->dropColumn('secret_key');
        });
    }
};
