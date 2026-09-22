<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Korrigiert den vertauschten `setting`/`setting_name`-Spalten-Fehler
 * aus Migration 2026_03_11_100004_insert_vp_room_settings.
 *
 * Konvention (alle anderen Settings):
 *   setting      = Machine-Key  (wird vom settings()-Helper gesucht)
 *   setting_name = Anzeigename  (wird in der UI angezeigt)
 *
 * Die ursprüngliche Migration hatte beide Spalten vertauscht, wodurch
 * settings('vp_room_integration_enabled') immer null zurückgab und
 * der VP-Raum-Import nie Raumbuchungen erstellte.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fixes = [
            [
                'old_setting'      => 'VP-Raumbuchungs-Integration aktiv',
                'old_setting_name' => 'vp_room_integration_enabled',
                'new_setting'      => 'vp_room_integration_enabled',
                'new_setting_name' => 'VP-Raumbuchungs-Integration aktiv',
            ],
            [
                'old_setting'      => 'VP-Buchungen aufräumen nach X Tagen',
                'old_setting_name' => 'vp_room_cleanup_days',
                'new_setting'      => 'vp_room_cleanup_days',
                'new_setting_name' => 'VP-Buchungen aufräumen nach X Tagen',
            ],
            [
                'old_setting'      => 'Admin-Benachrichtigung bei Raum-Konflikten',
                'old_setting_name' => 'vp_room_notify_conflicts',
                'new_setting'      => 'vp_room_notify_conflicts',
                'new_setting_name' => 'Admin-Benachrichtigung bei Raum-Konflikten',
            ],
        ];

        foreach ($fixes as $fix) {
            \App\Models\Setting::where('setting', $fix['old_setting'])
                ->where('setting_name', $fix['old_setting_name'])
                ->update([
                    'setting'      => $fix['new_setting'],
                    'setting_name' => $fix['new_setting_name'],
                ]);
        }

        // Cache leeren, damit die korrigierten Werte sofort greifen
        \Illuminate\Support\Facades\Cache::forget('setting_vp_room_integration_enabled');
        \Illuminate\Support\Facades\Cache::forget('setting_vp_room_cleanup_days');
        \Illuminate\Support\Facades\Cache::forget('setting_vp_room_notify_conflicts');
    }

    public function down(): void
    {
        // Rücktauschen (nur für rollback-Zwecke)
        $fixes = [
            [
                'old_setting'      => 'vp_room_integration_enabled',
                'old_setting_name' => 'VP-Raumbuchungs-Integration aktiv',
                'new_setting'      => 'VP-Raumbuchungs-Integration aktiv',
                'new_setting_name' => 'vp_room_integration_enabled',
            ],
            [
                'old_setting'      => 'vp_room_cleanup_days',
                'old_setting_name' => 'VP-Buchungen aufräumen nach X Tagen',
                'new_setting'      => 'VP-Buchungen aufräumen nach X Tagen',
                'new_setting_name' => 'vp_room_cleanup_days',
            ],
            [
                'old_setting'      => 'vp_room_notify_conflicts',
                'old_setting_name' => 'Admin-Benachrichtigung bei Raum-Konflikten',
                'new_setting'      => 'Admin-Benachrichtigung bei Raum-Konflikten',
                'new_setting_name' => 'vp_room_notify_conflicts',
            ],
        ];

        foreach ($fixes as $fix) {
            \App\Models\Setting::where('setting', $fix['old_setting'])
                ->where('setting_name', $fix['old_setting_name'])
                ->update([
                    'setting'      => $fix['new_setting'],
                    'setting_name' => $fix['new_setting_name'],
                ]);
        }
    }
};

