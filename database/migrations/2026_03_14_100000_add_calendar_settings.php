<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return; // Settings-Tabelle muss existieren
        }

        $settings = [
            [
                'module'       => 'Kalender',
                'setting'      => 'calendar_sync_interval',
                'setting_name' => 'Sync-Intervall (Minuten)',
                'type'         => 'number',
                'value'        => '15',
                'description'  => 'Wie oft (in Minuten) der CalDAV-Sync ausgeführt wird.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'module'       => 'Kalender',
                'setting'      => 'calendar_sync_enabled',
                'setting_name' => 'Synchronisation aktiv',
                'type'         => 'boolean',
                'value'        => '1',
                'description'  => 'Kalender-Synchronisation mit OX CalDAV aktivieren/deaktivieren.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'module'       => 'Kalender',
                'setting'      => 'calendar_default_ansicht',
                'setting_name' => 'Standard-Ansicht',
                'type'         => 'select',
                'value'        => 'timeGridWeek',
                'description'  => 'Standard-Kalenderansicht beim Öffnen (timeGridWeek, dayGridMonth, timeGridDay, listWeek).',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'module'       => 'Kalender',
                'setting'      => 'calendar_max_monate_voraus',
                'setting_name' => 'Sync-Horizont (Monate)',
                'type'         => 'number',
                'value'        => '6',
                'description'  => 'Wie viele Monate in die Zukunft Termine synchronisiert werden.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'module'       => 'Kalender',
                'setting'      => 'calendar_log_aufbewahrung_tage',
                'setting_name' => 'Log-Aufbewahrung (Tage)',
                'type'         => 'number',
                'value'        => '90',
                'description'  => 'Sync-Logs älter als diese Anzahl Tage werden automatisch hart gelöscht.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['module' => $setting['module'], 'setting' => $setting['setting']],
                $setting
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('module', 'Kalender')
            ->whereIn('setting', [
                'calendar_sync_interval',
                'calendar_sync_enabled',
                'calendar_default_ansicht',
                'calendar_max_monate_voraus',
                'calendar_log_aufbewahrung_tage',
            ])
            ->delete();
    }
};

