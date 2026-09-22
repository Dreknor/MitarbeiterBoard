<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hort_planungen', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('beschreibung')->nullable();
            $table->foreignId('department_id')->constrained('groups');
            $table->date('start_monat');
            $table->date('end_monat');
            $table->enum('typ', ['planung', 'rueckblick'])->default('planung');
            $table->boolean('aktiv')->default(false);
            $table->foreignId('kopiert_von_id')->nullable()->constrained('hort_planungen')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Dynamische Faktoren ────────────────────────────────────────
        Schema::create('hort_faktoren', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hort_planung_id')->constrained('hort_planungen')->cascadeOnDelete();
            $table->string('kuerzel', 50);
            $table->string('bezeichnung');
            $table->enum('berechnungs_typ', ['divisor', 'faktor_auf_bs', 'faktor_auf_summe']);
            $table->unsignedSmallInteger('position');
            $table->boolean('aktiv')->default(true);
            $table->string('gesetzliche_grundlage')->nullable();
            $table->timestamps();
            $table->unique(['hort_planung_id', 'kuerzel']);
        });

        Schema::create('hort_faktor_werte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hort_faktor_id')->constrained('hort_faktoren')->cascadeOnDelete();
            $table->decimal('wert', 10, 6);
            $table->date('gueltig_ab');
            $table->string('notiz')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['hort_faktor_id', 'gueltig_ab']);
        });

        // ── Dynamische Zusatzstunden-Typen ─────────────────────────────
        Schema::create('hort_zusatzstunden_typen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hort_planung_id')->constrained('hort_planungen')->cascadeOnDelete();
            $table->string('kuerzel', 50);
            $table->string('bezeichnung');
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->unique(['hort_planung_id', 'kuerzel']);
        });

        // ── Monate ─────────────────────────────────────────────────────
        Schema::create('hort_planung_monate', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hort_planung_id')->constrained('hort_planungen')->cascadeOnDelete();
            $table->date('monat');
            $table->unsignedInteger('kinderanzahl');
            $table->decimal('vollzeitstunden', 5, 2)->default(40.00);
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->unique(['hort_planung_id', 'monat']);
        });

        // ── Zusatzstunden pro Monat & Typ ──────────────────────────────
        Schema::create('hort_monat_zusatzstunden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hort_planung_monat_id')->constrained('hort_planung_monate')->cascadeOnDelete();
            $table->foreignId('hort_zusatzstunden_typ_id')->constrained('hort_zusatzstunden_typen')->cascadeOnDelete();
            $table->decimal('stunden', 5, 2)->default(0.00);
            $table->string('notiz')->nullable();
            $table->timestamps();
            $table->unique(['hort_planung_monat_id', 'hort_zusatzstunden_typ_id'], 'monat_zusatz_unique');
        });

        // ── Personen ───────────────────────────────────────────────────
        Schema::create('hort_planung_personen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hort_planung_monat_id')->constrained('hort_planung_monate')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('stunden_gesamt', 5, 2)->nullable();
            $table->decimal('stunden_stadt', 5, 2)->nullable();
            $table->decimal('stunden_vertrag', 5, 2)->nullable();
            $table->decimal('stunden_ist', 7, 2)->nullable();
            $table->string('kommentar', 255)->nullable();
            $table->timestamps();
            $table->unique(['hort_planung_monat_id', 'user_id']);
        });

        // ── Snapshots ──────────────────────────────────────────────────
        Schema::create('hort_planung_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hort_planung_id')->constrained('hort_planungen')->cascadeOnDelete();
            $table->string('name');
            $table->json('daten');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // Permissions
        Permission::create(['name' => 'view hort planung', 'guard_name' => 'web']);
        Permission::create(['name' => 'manage hort planung', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Schema::dropIfExists('hort_planung_snapshots');
        Schema::dropIfExists('hort_planung_personen');
        Schema::dropIfExists('hort_monat_zusatzstunden');
        Schema::dropIfExists('hort_planung_monate');
        Schema::dropIfExists('hort_zusatzstunden_typen');
        Schema::dropIfExists('hort_faktor_werte');
        Schema::dropIfExists('hort_faktoren');
        Schema::dropIfExists('hort_planungen');

        Permission::where('name', 'LIKE', '%hort planung%')->delete();
    }
};

