<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {


        Schema::table('timesheets', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable();
           $table->unsignedBigInteger('locked_by')->nullable();

            $table->foreign('locked_by')->references('id')->on('users');
        });

        Permission::create([
            'name' => 'lock timesheets',
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
        Schema::table('timesheets', function (Blueprint $table) {
            //
        });
    }
};
