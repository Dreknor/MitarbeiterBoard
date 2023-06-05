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
        Schema::create('wiki_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('author_id');
            $table->string('title', 80);
            $table->text('text');
            $table->unsignedBigInteger('previous_version')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('previous_version')->references('id')->on('wiki_sites');
        });

        Spatie\Permission\Models\Permission::create([
            'name' => 'view wiki',
            'guard_name' => 'web'
        ]);

        Spatie\Permission\Models\Permission::create([
            'name' => 'edit wiki',
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
        Schema::dropIfExists('wiki_sites');
    }
};
