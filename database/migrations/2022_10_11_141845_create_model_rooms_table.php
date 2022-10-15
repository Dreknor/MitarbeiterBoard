<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('rooms')) {

            Schema::create('rooms', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60);
                $table->string('room_number', 10)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });


            Schema::create('room_bookings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('room_id');

                $table->integer('weekday')->nullable();
                $table->date('date')->nullable();
                $table->time('start');
                $table->time('end');
                $table->string('name');
                $table->unsignedBigInteger('users_id');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('users_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            });

            Spatie\Permission\Models\Permission::create([
                'name' => 'view roomBooking',
                'guard_name' => 'web'
            ]);

            Spatie\Permission\Models\Permission::create([
                'name' => 'manage rooms',
                'guard_name' => 'web'
            ]);

        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('room_bookings');
        Schema::dropIfExists('rooms');

        \Spatie\Permission\Models\Permission::where('name', 'manage rooms')->delete();
        \Spatie\Permission\Models\Permission::where('name', 'view roomBooking')->delete();
    }
};
