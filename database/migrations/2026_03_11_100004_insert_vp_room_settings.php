<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'module'       => 'Raumplan',
                'setting'      => 'vp_room_integration_enabled',
                'setting_name' => 'VP-Raumbuchungs-Integration aktiv',
                'type'         => 'boolean',
                'value'        => '1',
                'description'  => 'Wenn aktiv, werden beim Vertretungsplan-Import automatisch Raumbuchungen erstellt/storniert.',
            ],
            [
                'module'       => 'Raumplan',
                'setting'      => 'vp_room_cleanup_days',
                'setting_name' => 'VP-Buchungen aufräumen nach X Tagen',
                'type'         => 'integer',
                'value'        => '28',
                'description'  => 'VP-Raumbuchungen (source=indiware_vp) die älter als X Tage sind, werden automatisch gelöscht.',
            ],
            [
                'module'       => 'Raumplan',
                'setting'      => 'vp_room_notify_conflicts',
                'setting_name' => 'Admin-Benachrichtigung bei Raum-Konflikten',
                'type'         => 'boolean',
                'value'        => '0',
                'description'  => 'Wenn aktiv, wird der Admin per Notification benachrichtigt, wenn der VP-Import einen Raumkonflikt erkennt.',
            ],
        ];

        \App\Models\Setting::insert($settings);
    }

    public function down(): void
    {
        \App\Models\Setting::whereIn('setting', [
            'vp_room_integration_enabled',
            'vp_room_cleanup_days',
            'vp_room_notify_conflicts',
        ])->delete();
    }
};

