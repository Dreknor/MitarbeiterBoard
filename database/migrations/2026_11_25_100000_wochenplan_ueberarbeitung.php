<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Überarbeitung des Wochenplan-Moduls:
 * - Versionsprüfung und Nextcloud-Ablage je Plan
 * - eigene Datei-Tabelle (Arbeitsblätter, zentral in der Nextcloud)
 * - Status je Kind und Aufgabe (offen/teilweise/erledigt)
 * - Verknüpfung mit Tagebuch-Aufgaben, Niveau, Links, Aufgaben-Bibliothek
 * - Recht „manage all wochenplaene“ und Einstellungen (Modul „Wochenplan“)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wp_plaene', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('created_by');
            $table->unsignedTinyInteger('niveau')->nullable()->after('selbsteinschaetzung');
            $table->string('ablage_ordner', 1000)->nullable();
            $table->string('ablage_datei', 1000)->nullable();
            $table->string('ablage_status', 20)->nullable();
            $table->text('ablage_fehler')->nullable();
            $table->timestamp('ablage_angefordert_at')->nullable();
            $table->timestamp('abgelegt_at')->nullable();
        });

        Schema::table('wp_aufgaben', function (Blueprint $table) {
            $table->unsignedTinyInteger('niveau')->nullable()->after('dauer');
            $table->string('link', 500)->nullable()->after('niveau');
            // Kinderplan-Aufgabe wurde gegenüber dem Klassenplan angepasst (Herkunft bleibt in synced_from_id)
            $table->boolean('angepasst')->default(false)->after('synced_from_id');
            $table->unsignedBigInteger('paed_diary_task_id')->nullable()->after('synced_from_id');
            $table->foreign('paed_diary_task_id')->references('id')->on('paed_diary_tasks')->nullOnDelete();
        });

        Schema::create('wp_dateien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_plan_id')->constrained('wp_plaene')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('dateiname', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('groesse')->default(0);
            // Pfad relativ zum Basisordner in der Nextcloud
            $table->string('nextcloud_pfad', 1000)->nullable();
            // Zwischenspeicher (Disk „wochenplan“) bis zum erfolgreichen Upload bzw. dauerhaft ohne Nextcloud
            $table->string('lokaler_pfad', 1000)->nullable();
            $table->string('status', 20)->default('lokal');
            $table->text('fehler')->nullable();
            $table->foreignId('hochgeladen_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('geprueft_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('wp_aufgabe_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_aufgabe_id')->constrained('wp_aufgaben')->cascadeOnDelete();
            $table->unsignedBigInteger('schueler_id');
            $table->foreign('schueler_id')->references('id')->on('schueler')->cascadeOnDelete();
            $table->string('status', 12)->default('offen');
            $table->string('notiz', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['wp_aufgabe_id', 'schueler_id']);
            $table->index('schueler_id');
        });

        Schema::create('wp_bausteine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_fach_id')->nullable()->constrained('wp_faecher')->nullOnDelete();
            $table->unsignedTinyInteger('klassenstufe')->nullable();
            $table->text('aufgabe');
            $table->string('dauer', 50)->nullable();
            $table->unsignedTinyInteger('niveau')->nullable();
            $table->string('link', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['wp_fach_id', 'klassenstufe']);
        });

        // Recht für klassenübergreifenden Zugriff
        $now = now();
        if (! DB::table('permissions')->where('name', 'manage all wochenplaene')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'manage all wochenplaene',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $permissionId = DB::table('permissions')->where('name', 'manage all wochenplaene')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
        if ($permissionId && $adminRoleId
            && ! DB::table('role_has_permissions')->where(['permission_id' => $permissionId, 'role_id' => $adminRoleId])->exists()) {
            DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $adminRoleId]);
        }

        $settings = [
            ['wochenplan_tagebuch_anbindung', 'Anbindung an das Pädagogische Tagebuch', 'boolean', '0',
                'Übernahme von Tagebuch-Aufgaben, Abgleich „erledigt“, Wochenplan-Übersicht in der Wochenansicht und Wochenpläne in der Schülerakte.'],
            ['wochenplan_nextcloud_aktiv', 'Nextcloud-Ablage aktiv', 'boolean', '0',
                'Arbeitsblätter und fertige Pläne (PDF und Word) werden in der Nextcloud abgelegt. Zugangsdaten: NEXTCLOUD_WOCHENPLAN_* in der .env.'],
            ['wochenplan_nextcloud_basisordner', 'Nextcloud-Basisordner', 'string', '/Wochenpläne',
                'Ordner in der Nextcloud (Dienst-Konto), unter dem alle Wochenpläne abgelegt werden.'],
            ['wochenplan_ordner_vorlage', 'Ordner eines Plans', 'string', '{schuljahr}/{klasse}/{jahr}-KW{kw} {plan}',
                'Platzhalter: {schuljahr} {klasse} {jahr} {kw} {von} {bis} {plan}. Unterordner mit / trennen.'],
            ['wochenplan_datei_vorlage', 'Dateiname eines Plans', 'string', '{plan}',
                'Ohne Dateiendung. Platzhalter wie beim Ordner.'],
            ['wochenplan_kinderplan_vorlage', 'Dateiname eines Kinderplans', 'string', 'Kinderpläne/{nachname}, {vorname}',
                'Relativ zum Ordner des Klassenplans. Zusätzlich {vorname} und {nachname}.'],
        ];
        foreach ($settings as [$key, $name, $type, $value, $description]) {
            if (! Setting::where('setting', $key)->exists()) {
                Setting::insert([
                    'module' => 'Wochenplan',
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
        Setting::whereIn('setting', [
            'wochenplan_tagebuch_anbindung', 'wochenplan_nextcloud_aktiv', 'wochenplan_nextcloud_basisordner',
            'wochenplan_ordner_vorlage', 'wochenplan_datei_vorlage', 'wochenplan_kinderplan_vorlage',
        ])->delete();

        DB::table('permissions')->where('name', 'manage all wochenplaene')->delete();

        Schema::dropIfExists('wp_bausteine');
        Schema::dropIfExists('wp_aufgabe_status');
        Schema::dropIfExists('wp_dateien');

        Schema::table('wp_aufgaben', function (Blueprint $table) {
            $table->dropForeign(['paed_diary_task_id']);
            $table->dropColumn(['niveau', 'link', 'angepasst', 'paed_diary_task_id']);
        });

        Schema::table('wp_plaene', function (Blueprint $table) {
            $table->dropColumn([
                'version', 'niveau', 'ablage_ordner', 'ablage_datei', 'ablage_status',
                'ablage_fehler', 'ablage_angefordert_at', 'abgelegt_at',
            ]);
        });
    }
};
