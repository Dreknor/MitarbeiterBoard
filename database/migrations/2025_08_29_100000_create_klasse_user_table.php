<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('klasse_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('klasse_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->unique(['klasse_id','user_id']);
            $table->foreign('klasse_id')->references('id')->on('klassen')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('klasse_user');
    }
};

