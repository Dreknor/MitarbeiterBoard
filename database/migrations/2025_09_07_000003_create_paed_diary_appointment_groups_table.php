<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('paed_diary_appointment_groups')){
            Schema::create('paed_diary_appointment_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('paed_diary_appointment_id')
                    ->constrained('paed_diary_appointments')
                    ->onDelete('cascade')
                    ->name('fk_pda_groups_appointment');
                $table->foreignId('paed_diary_class_group_id')
                    ->constrained('paed_diary_class_groups')
                    ->onDelete('cascade')
                    ->name('fk_pda_groups_class_group');
                $table->timestamps();
            });
        }

    }

    public function down()
    {
        Schema::dropIfExists('paed_diary_appointment_groups');
    }
};
