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
        Schema::create('recurring_themes', function (Blueprint $table) {
            $table->id();
            $table->text('theme');
            $table->text('information')->nullable();
            $table->text('goal');
            $table->unsignedBigInteger( 'type_id');
            $table->unsignedBigInteger( 'creator_id');
            $table->unsignedBigInteger( 'group_id');
            $table->integer( 'month');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('type_id')->references('id')->on('types');
            $table->foreign('creator_id')->references('id')->on('users');
            $table->foreign('group_id')->references('id')->on('groups');
        });

        Spatie\Permission\Models\Permission::create([
            'name' => 'manage recurring themes',
            'guard_name' => 'web'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('recurring_themes');
        \Spatie\Permission\Models\Permission::query()->where('name', 'manage recurring themes')->delete();
    }
};
