<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRostersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rosters', function (Blueprint $table) {
            $table->id();

            $table->date('start_date');
            $table->string('type', 15)->default('normal');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('department_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('department_id')->references('id')->on('groups');

            Spatie\Permission\Models\Permission::create([
                'name' => 'create roster',
                'guard_name' => 'web'
            ]);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rosters');
    }
}
