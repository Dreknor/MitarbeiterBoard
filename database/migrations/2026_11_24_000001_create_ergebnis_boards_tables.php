<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ergebnisboards: bearbeitbare Ergebnissicherung (Matrix, SWOT, Fahrplan, Kanban …).
 *
 * - ergebnis_boards: Kopf, Raster (Spalten + Bereiche als JSON, beim Anlegen aus der Vorlage kopiert),
 *   optionale Verknüpfung mit Sitzung/Thema, optimistische Sperre über `version`.
 * - ergebnis_karten: Karten in einem Bereich, optional mit Zuständigen, Fälligkeit und Aufgabe.
 * - ergebnis_board_zugriffe: lesen/schreiben/verwalten für Personen, Gruppen und Rollen (wie meeting_participants).
 * - ergebnis_vorlagen: eigene Vorlagen aus dem Vorlagen-Editor (zusätzlich zu App\Support\ErgebnisboardVorlagen).
 */
return new class extends Migration
{
    private const PERMISSIONS = ['view result boards', 'create result boards', 'manage result boards'];

    public function up(): void
    {
        Schema::create('ergebnis_boards', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            $table->string('untertitel', 500)->nullable();
            $table->string('kopfzeile')->nullable();
            $table->string('fusszeile')->nullable();
            $table->string('vorlage', 64);
            $table->string('farbschema', 16)->default('klar');
            $table->string('spalten')->default('1fr 1fr 1fr');
            $table->json('bereiche');
            $table->json('achsen')->nullable();
            $table->json('legende')->nullable();
            $table->boolean('bereiche_frei')->default(false);
            $table->boolean('auto_status')->default(false);
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->boolean('meeting_lesen')->default(false);
            $table->unsignedBigInteger('theme_id')->nullable()->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archiviert_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ergebnis_karten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('ergebnis_boards')->cascadeOnDelete();
            $table->string('bereich', 64);
            $table->string('titel');
            $table->text('zusatz')->nullable();
            $table->string('etikett', 60)->nullable();
            $table->boolean('fokus')->default(false);
            $table->boolean('breit')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->date('faellig_am')->nullable();
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->date('erinnert_am')->nullable();
            $table->timestamps();

            $table->index(['board_id', 'bereich', 'sort']);
        });

        Schema::create('ergebnis_karte_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karte_id')->constrained('ergebnis_karten')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['karte_id', 'user_id']);
        });

        Schema::create('ergebnis_board_zugriffe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('ergebnis_boards')->cascadeOnDelete();
            $table->string('berechtigter_type');
            $table->unsignedBigInteger('berechtigter_id');
            $table->string('recht', 12)->default('lesen');
            $table->timestamps();

            $table->unique(['board_id', 'berechtigter_type', 'berechtigter_id'], 'ergebnis_zugriff_unique');
            $table->index(['berechtigter_type', 'berechtigter_id']);
        });

        Schema::create('ergebnis_vorlagen', function (Blueprint $table) {
            $table->id();
            $table->string('schluessel', 64)->unique();
            $table->string('titel');
            $table->string('beschreibung', 500)->nullable();
            $table->json('definition');
            $table->boolean('aktiv')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (self::PERMISSIONS as $name) {
            $vorhanden = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
            if (! $vorhanden) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('ergebnis_vorlagen');
        Schema::dropIfExists('ergebnis_board_zugriffe');
        Schema::dropIfExists('ergebnis_karte_user');
        Schema::dropIfExists('ergebnis_karten');
        Schema::dropIfExists('ergebnis_boards');

        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
