<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('roster_task_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->string('event_name');
            $table->time('required_start')->nullable();
            $table->time('required_end')->nullable();
            $table->boolean('adjust_working_time')->default(true);
            $table->timestamps();
            $table->foreign('department_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->index(['department_id','event_name']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('roster_task_requirements');
    }
};
