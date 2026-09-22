<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pers_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');               // 'view', 'export', 'download', 'deletion'
            $table->string('resource_type');        // Morph-Typ (z.B. 'App\Models\User')
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('route');
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();   // Zusatzdaten (Export-Typ, Filter etc.)
            $table->timestamp('created_at')->useCurrent();
            // KEIN updated_at – Logs sind immutabel
            // KEIN deleted_at – Logs werden nie soft-deleted (nur Bereinigung über Service)

            $table->index(['user_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pers_access_logs');
    }
};

