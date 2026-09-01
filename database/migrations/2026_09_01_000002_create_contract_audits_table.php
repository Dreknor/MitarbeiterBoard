<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbeitspaket 1.2: Vertragshistorie / Audit-Log.
 * Speichert Versionen von Arbeitsverträgen (Employment) mit expliziten
 * Gültigkeitsbereichen (valid_from/valid_to) und hält Änderungen an
 * Soll-Arbeitszeiten, Wochenstunden und Beschäftigungsgraden fest, damit die
 * Engine tagesgenau mit den zum Stichtag gültigen Vertragskonditionen rechnen
 * und rückwirkende Änderungen erkennen kann.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employment_id');
            $table->unsignedBigInteger('employe_id');
            $table->string('action', 16); // created | updated | deleted

            // Snapshot der zum Zeitpunkt der Änderung gültigen Vertragskonditionen
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('hours', 5, 2)->nullable();
            $table->string('employment_type', 32)->nullable();
            $table->string('contract_type', 32)->nullable();
            $table->string('status', 32)->nullable();

            $table->json('changed_fields')->nullable(); // ['field' => ['old' => ..., 'new' => ...]]
            $table->unsignedBigInteger('changed_by')->nullable();

            // Rückwirkungs-Erkennung: betrifft die Änderung einen bereits geprüften/abgeschlossenen Zeitraum?
            $table->boolean('is_retroactive')->default(false);
            $table->date('affected_period_start')->nullable();
            $table->date('affected_period_end')->nullable();

            $table->timestamps();

            $table->foreign('employment_id')->references('id')->on('employments')->cascadeOnDelete();
            $table->foreign('employe_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['employe_id', 'valid_from'], 'contract_audits_employe_valid_from_idx');
            $table->index('employment_id', 'contract_audits_employment_idx');
            $table->index('is_retroactive', 'contract_audits_retroactive_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_audits');
    }
};

