<?php

use App\Models\Setting;
use App\Support\AbProfil;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Angepasste Arbeitsblätter (Sehbehinderung).
 *
 * - ab_profil_vorlagen: Profil-Vorlagen („Groß“, „Sehr groß“ …) mit vollständigen Werten.
 * - ab_sehprofile: ein Profil je Kind (Vorlage + Abweichungen) – keine Diagnose, kein Visus.
 * - ab_blaetter: erfasste Arbeitsblätter als Bausteine (JSON), Status, optimistische Sperre.
 * - ab_fassungen: gesetzte Fassungen je Kind/Profil (PDF, optional DOCX, Nextcloud, Wochenplan).
 * - ab_ki_verbrauch: Grundlage für das Monatslimit der KI-Erkennung.
 * - wp_dateien: Verweis auf die Fassung und das ersetzte Klassenplan-Original.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['create adapted worksheets', 'manage visual profiles', 'manage adapted worksheets'];

    public function up(): void
    {
        Schema::create('ab_profil_vorlagen', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->json('werte');
            $table->unsignedInteger('sortierung')->default(0);
            $table->timestamps();
        });

        Schema::create('ab_sehprofile', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schueler_id')->unique();
            $table->foreignId('vorlage_id')->nullable()->constrained('ab_profil_vorlagen')->nullOnDelete();
            $table->json('werte')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->foreignId('geaendert_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ab_blaetter', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            $table->unsignedBigInteger('wp_fach_id')->nullable()->index();
            $table->unsignedTinyInteger('klassenstufe')->nullable();
            $table->string('quelle', 16)->default('upload');
            $table->unsignedBigInteger('quelle_wp_datei_id')->nullable()->index();
            $table->string('status', 16)->default('entwurf');
            $table->json('struktur')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('geprueft_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('freigegeben_at')->nullable();
            $table->string('ki_anbieter', 32)->nullable();
            $table->string('ki_modell', 64)->nullable();
            $table->unsignedSmallInteger('ki_seiten')->nullable();
            $table->text('textebene')->nullable();
            $table->string('fehler', 1000)->nullable();
            $table->timestamp('erinnert_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'erstellt_von']);
        });

        Schema::create('ab_fassungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blatt_id')->constrained('ab_blaetter')->cascadeOnDelete();
            $table->unsignedBigInteger('schueler_id')->nullable()->index();
            $table->foreignId('vorlage_id')->nullable()->constrained('ab_profil_vorlagen')->nullOnDelete();
            $table->json('profil_werte');
            $table->string('profil_hash', 64);
            $table->unsignedInteger('struktur_version');
            $table->string('pdf_pfad')->nullable();
            $table->string('docx_pfad')->nullable();
            $table->string('nextcloud_pfad', 1000)->nullable();
            $table->unsignedBigInteger('wp_datei_id')->nullable()->index();
            $table->string('status', 16)->default('aktuell');
            $table->foreignId('erzeugt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['blatt_id', 'schueler_id']);
        });

        Schema::create('ab_ki_verbrauch', function (Blueprint $table) {
            $table->id();
            $table->string('monat', 7)->index();
            $table->unsignedBigInteger('blatt_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedSmallInteger('seiten')->default(0);
            $table->unsignedInteger('kosten_cent')->default(0);
            $table->string('anbieter', 32)->nullable();
            $table->timestamps();
        });

        Schema::table('wp_dateien', function (Blueprint $table) {
            $table->unsignedBigInteger('ab_fassung_id')->nullable()->index();
            $table->unsignedBigInteger('ersetzt_wp_datei_id')->nullable()->index();
        });

        // Profil-Vorlagen
        $jetzt = now();
        foreach (AbProfil::STANDARD_VORLAGEN as $i => [$name, $werte]) {
            DB::table('ab_profil_vorlagen')->insert([
                'name' => $name,
                'werte' => json_encode(AbProfil::normalisieren($werte)),
                'sortierung' => $i,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);
        }

        // Rechte
        foreach (self::PERMISSIONS as $name) {
            if (! DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web', 'created_at' => $jetzt, 'updated_at' => $jetzt]);
            }
        }
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
        if ($adminRoleId) {
            foreach (DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id') as $permissionId) {
                if (! DB::table('role_has_permissions')->where(['permission_id' => $permissionId, 'role_id' => $adminRoleId])->exists()) {
                    DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $adminRoleId]);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Einstellungen
        $settings = [
            ['ab_ordner_vorlage', 'Nextcloud-Ordner einer Fassung', 'string', 'Angepasste Arbeitsblätter/{schuljahr}/{nachname} {vorname}',
                'Platzhalter: {schuljahr} {klasse} {vorname} {nachname} {titel} {datum}. Unterordner mit / trennen.'],
            ['ab_datei_vorlage', 'Dateiname einer Fassung', 'string', '{titel}',
                'Ohne Dateiendung. Platzhalter wie beim Ordner.'],
            ['ab_ki_limit_seiten', 'KI-Limit: Seiten pro Monat', 'string', '200',
                '0 = ohne Limit. Bei Erreichen bleibt nur die manuelle Erfassung.'],
            ['ab_ki_limit_euro', 'KI-Limit: Euro pro Monat', 'string', '20',
                '0 = ohne Limit.'],
            ['ab_ki_warnung_prozent', 'KI-Limit: Warnung ab (%)', 'string', '80',
                'Benachrichtigung an alle mit „edit settings“.'],
            ['ab_wochenplan_automatisch', 'Wochenplan: automatisch anpassen', 'boolean', '1',
                'Neue Arbeitsblätter an Klassenplänen werden für Kinder mit Sehprofil automatisch erkannt.'],
            ['ab_docx_ausgabe', 'Zusätzlich Word-Datei (DOCX) erzeugen', 'boolean', '0',
                'Neben dem PDF auch eine bearbeitbare Word-Datei.'],
        ];
        foreach ($settings as [$key, $name, $type, $value, $description]) {
            if (! Setting::where('setting', $key)->exists()) {
                Setting::insert([
                    'module' => 'Angepasste Arbeitsblätter',
                    'setting' => $key,
                    'setting_name' => $name,
                    'type' => $type,
                    'value' => $value,
                    'description' => $description,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('wp_dateien', function (Blueprint $table) {
            $table->dropIndex(['ab_fassung_id']);
            $table->dropIndex(['ersetzt_wp_datei_id']);
            $table->dropColumn(['ab_fassung_id', 'ersetzt_wp_datei_id']);
        });
        Schema::dropIfExists('ab_ki_verbrauch');
        Schema::dropIfExists('ab_fassungen');
        Schema::dropIfExists('ab_blaetter');
        Schema::dropIfExists('ab_sehprofile');
        Schema::dropIfExists('ab_profil_vorlagen');

        Setting::where('module', 'Angepasste Arbeitsblätter')->delete();
        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
