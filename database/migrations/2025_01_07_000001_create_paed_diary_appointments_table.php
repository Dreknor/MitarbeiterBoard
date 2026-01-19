<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('paed_diary_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurring_type', ['daily', 'weekly', 'monthly'])->nullable();
            $table->integer('recurring_interval')->default(1);
            $table->date('recurring_end_date')->nullable();
            $table->boolean('is_paused')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('paed_diary_appointments');
    }
};
