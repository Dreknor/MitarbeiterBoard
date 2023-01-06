<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('hasAllocations')->default(false);
        });

        Schema::table('themes', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to')->nullable();
        });
    }

    public function down()
    {
        Schema::table('groups', function (Blueprint $table) {
            //
        });
    }
};
