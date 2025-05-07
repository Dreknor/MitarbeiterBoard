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

        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });


        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['open', 'closed', 'waiting'])->default('open');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('low');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamp('waiting_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('assigned_to')->references('id')->on('users');
            $table->foreign('category_id')->references('id')->on('ticket_categories');

        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id');
            $table->text('comment');
            $table->boolean('internal')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('ticket_id')->references('id')->on('tickets');
            $table->foreign('user_id')->references('id')->on('users');
        });

        \Spatie\Permission\Models\Permission::create(['name' => 'view tickets', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::create(['name' => 'edit tickets', 'guard_name' => 'web']);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_categories');

        \Spatie\Permission\Models\Permission::where('name', 'view tickets')->delete();
        \Spatie\Permission\Models\Permission::where('name', 'edit tickets')->delete();

    }
};
