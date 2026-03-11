<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'module'       => 'Raumplan',
                'setting'      => 'VP-Raumbuchungs-Integration aktiv',
                'setting_name' => 'vp_room_integration_enabled',
                'type'         => 'boolean',
                'value'        => '1',
                'description'  => 'Wenn aktiv, werden beim Vertretungsplan-Import automatisch Raumbuchungen erstellt/storniert.',
            ],
            [
                'module'       => 'Raumplan',
                'setting'      => 'VP-Buchungen aufräumen nach X Tagen',
                'setting_name' => 'vp_room_cleanup_days',
                'type'         => 'integer',
                'value'        => '28',
                'description'  => 'VP-Raumbuchungen (source=indiware_vp) die älter als X Tage sind, werden automatisch gelöscht.',
            ],
            [
                'module'       => 'Raumplan',
                'setting'      => 'Admin-Benachrichtigung bei Raum-Konflikten',
                'setting_name' => 'vp_room_notify_conflicts',
                'type'         => 'boolean',
                'value'        => '0',
                'description'  => 'Wenn aktiv, wird der Admin per Notification benachrichtigt, wenn der VP-Import einen Raumkonflikt erkennt.',
            ],
        ];

        \App\Models\Setting::insert($settings);
    }

    public function down(): void
    {
        \App\Models\Setting::whereIn('setting_name', [
            'vp_room_integration_enabled',
            'vp_room_cleanup_days',
            'vp_room_notify_conflicts',
        ])->delete();
    }
};

