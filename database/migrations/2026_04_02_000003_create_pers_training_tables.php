<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pers_trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('provider')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('location')->nullable();
            $table->decimal('cost', 8, 2)->nullable();
            $table->integer('max_participants')->nullable();
            $table->foreignId('qualification_type_id')->nullable()->constrained('pers_qualification_types')->nullOnDelete();
            $table->string('status')->default('geplant');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pers_training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('pers_trainings')->cascadeOnDelete();
            $table->foreignId('employe_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('angemeldet');
            $table->foreignId('certificate_document_id')->nullable()->constrained('pers_documents')->nullOnDelete();
            $table->text('feedback')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['training_id', 'employe_id']);
        });

        // Permissions
        Permission::findOrCreate('view trainings', 'web');
        Permission::findOrCreate('manage trainings', 'web');
        Permission::findOrCreate('approve trainings', 'web');
    }

    public function down(): void
    {
        Schema::dropIfExists('pers_training_participants');
        Schema::dropIfExists('pers_trainings');
    }
};

