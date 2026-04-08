<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pers_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->boolean('requires_expiry')->default(false);
            $table->integer('default_reminder_days')->nullable();
            $table->string('nextcloud_subfolder');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pers_document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('document_type_id')->constrained('pers_document_types')->cascadeOnDelete();
            $table->string('template_path');
            $table->json('placeholders')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pers_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('pers_document_types');
            $table->string('title');
            $table->string('nextcloud_path');
            $table->string('nextcloud_file_id')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('reminder_days')->nullable();
            $table->datetime('reminder_sent_at')->nullable();
            $table->string('status')->default('aktuell');
            $table->string('sync_status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // Permissions
        Permission::findOrCreate('view personal_documents', 'web');
        Permission::findOrCreate('create personal_documents', 'web');
        Permission::findOrCreate('manage personal_documents', 'web');
        Permission::findOrCreate('manage document_templates', 'web');
    }

    public function down(): void
    {
        Schema::dropIfExists('pers_documents');
        Schema::dropIfExists('pers_document_templates');
        Schema::dropIfExists('pers_document_types');
    }
};

