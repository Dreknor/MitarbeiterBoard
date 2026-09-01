<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbeitspaket 1.1: Auffälligkeiten-Protokoll der Prüfengine.
 * Speichert alle durch den TimeValidationService erzeugten Abweichungen
 * (Überschneidungen, Dienstplan-Abweichungen, Urlaubs-Konflikte, Fehlzeiten,
 * Vertragsänderungen) inkl. Freigabe-Attributen für HR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_anomalies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employe_id');
            $table->date('date')->nullable(); // konkreter betroffener Tag (falls zutreffend)
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('rule_type', 64); // App\Enums\AnomalyRuleType
            $table->string('severity', 16);  // App\Enums\AnomalySeverity
            $table->text('description');
            $table->json('context')->nullable(); // maschinenlesbare Detaildaten (Soll/Ist, betroffene IDs, ...)
            $table->unsignedBigInteger('related_employment_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->string('resolution_comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employe_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('related_employment_id')->references('id')->on('employments')->nullOnDelete();

            // Schnelle Abfragen nach Mitarbeiter + Monat sowie nach offenen Auffälligkeiten
            $table->index(['employe_id', 'year', 'month'], 'ts_anomalies_employe_period_idx');
            $table->index(['rule_type', 'severity'], 'ts_anomalies_rule_severity_idx');
            $table->index('resolved_at', 'ts_anomalies_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_anomalies');
    }
};

