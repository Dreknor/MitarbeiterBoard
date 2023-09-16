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
            $table->boolean('mail_timesheet')->default(fals);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employes_data', function (Blueprint $table) {
            $table->dropColumn('mail_timesheet');
        });
    }
};
