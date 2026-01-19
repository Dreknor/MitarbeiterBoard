<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paed_diary_class_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::create('paed_diary_class_group_klasse', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('klasse_id');
            $table->timestamps();
            $table->foreign('group_id')->references('id')->on('paed_diary_class_groups')->onDelete('cascade');
            $table->foreign('klasse_id')->references('id')->on('klassen')->onDelete('cascade');
            $table->unique(['group_id','klasse_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('paed_diary_class_group_klasse');
        Schema::dropIfExists('paed_diary_class_groups');
    }
};

