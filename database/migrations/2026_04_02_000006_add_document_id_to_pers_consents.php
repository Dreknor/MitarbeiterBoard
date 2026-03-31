<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-06 Nachbesserung: document_id (FK → pers_documents) zur pers_consents-Tabelle hinzufügen.
 * Ermöglicht Verknüpfung einer Einwilligung mit dem zugehörigen Papierdokument.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pers_consents', function (Blueprint $table) {
            $table->foreignId('document_id')
                ->nullable()
                ->after('granted_via')
                ->constrained('pers_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pers_consents', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropColumn('document_id');
        });
    }
};

