<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('paed_diary_categories')) {
            Schema::create('paed_diary_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamps();
                $table->unique(['name', 'user_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('paed_diary_categories');
    }
};

