<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Monitoring (Systemstatus): Dienste (Heartbeat von außen, z. B. Home Assistant mit
 * WireGuard-Tunnel, und Webserver, die die Anwendung selbst prüft) samt Statusverlauf.
 * Einstellungen liegen in `settings` (Modul „Monitoring“) und werden über eine eigene
 * Oberfläche im Reiter „Monitoring“ der Einstellungen gepflegt.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['monitoring_rollen_sichtbar', 'Rollen mit Statusleiste', '[]', 'Rollen, die die Statusleiste im Dashboard sehen (JSON, über die Monitoring-Oberfläche pflegen)'],
        ['monitoring_rollen_benachrichtigen', 'Rollen mit Benachrichtigung', '[]', 'Rollen, die bei Störungen benachrichtigt werden (JSON, über die Monitoring-Oberfläche pflegen)'],
        ['monitoring_heartbeat_minuten', 'Heartbeat-Zeitlimit (Minuten)', '15', 'Bleibt der Heartbeat länger aus, gilt die Überwachung als ausgefallen'],
        ['monitoring_token_hash', 'Heartbeat-Token (Hash)', '', 'SHA-256 des Heartbeat-Tokens'],
        ['monitoring_token_erstellt', 'Heartbeat-Token erstellt', '', 'Zeitpunkt der Token-Erzeugung'],
    ];

    public function up(): void
    {
        Schema::create('monitoring_dienste', function (Blueprint $table) {
            $table->id();
            $table->string('schluessel', 60)->unique();
            $table->string('name', 100);
            $table->string('typ', 20); // quelle | heartbeat | http
            $table->string('url', 500)->nullable();
            $table->foreignId('abhaengig_von_id')->nullable()->constrained('monitoring_dienste')->nullOnDelete();
            $table->boolean('aktiv')->default(true);
            $table->unsignedInteger('sortierung')->default(0);
            $table->string('status', 20)->default('unbekannt'); // ok | fehler | unbekannt
            $table->timestamp('status_seit')->nullable();
            $table->timestamp('letzte_pruefung')->nullable();
            $table->string('meldung', 255)->nullable();
            $table->unsignedInteger('latenz_ms')->nullable();
            $table->unsignedInteger('fehlversuche')->default(0);
            // Wurde der aktuelle Fehler bereits gemeldet? (Folgefehler/Ausfall der Quelle werden zurückgehalten)
            $table->boolean('gemeldet')->default(false);
            $table->timestamps();
        });

        Schema::create('monitoring_ereignisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dienst_id')->constrained('monitoring_dienste')->cascadeOnDelete();
            $table->string('status_alt', 20);
            $table->string('status_neu', 20);
            $table->string('meldung', 255)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        foreach (self::SETTINGS as [$schluessel, $name, $wert, $beschreibung]) {
            if (! DB::table('settings')->where('setting', $schluessel)->exists()) {
                DB::table('settings')->insert([
                    'module' => 'Monitoring',
                    'setting' => $schluessel,
                    'setting_name' => $name,
                    'type' => 'hidden',
                    'value' => $wert,
                    'description' => $beschreibung,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_ereignisse');
        Schema::dropIfExists('monitoring_dienste');
        DB::table('settings')->whereIn('setting', array_column(self::SETTINGS, 0))->delete();
    }
};
