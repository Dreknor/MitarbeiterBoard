<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashboard_cards', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('title');
            $table->text('view');
            $table->integer('default_row');
            $table->integer('default_col');
            $table->string('permission')->nullable();
        });

        Schema::create('dashboard_card_user', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('dashboard_card_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('row');
            $table->integer('col');
            $table->boolean('active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_cards');
        Schema::dropIfExists('dashboard_card_user');
    }
};
