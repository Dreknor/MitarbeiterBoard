<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            [
                'module' => 'Abwesenheiten',
                'setting' => 'absence_reason_default',
                'setting_name' => 'Standard Abwesenheitsgrund',
                'type' => 'string',
                'value' => 'krank',
                'description' => 'Standard-Abwesenheitsgrund, der beim Erstellen einer neuen Abwesenheit vorgeschlagen wird.',
            ],
            [
                'module' => 'Abwesenheiten',
                'setting' => 'absence_sick_note_days',
                'setting_name' => 'Tage bis Krankenschein benötigt',
                'type' => 'number',
                'value' => 3,
                'description' => 'Anzahl der Tage, ab denen ein Krankenschein benötigt wird.',
            ],
            [
                'module' => 'Abwesenheiten',
                'setting' => 'absence_sick_note_reasons',
                'setting_name' => 'Gründe für Krankenschein (mit | trennen)',
                'type' => 'string',
                'value' => 'krank|Kind krank',
                'description' => 'Abwesenheitsgründe, für die ein Krankenschein benötigt wird. Mehrere Gründe mit | trennen.',
            ],
        ];

        foreach ($settings as $setting) {
            // Prüfe ob Setting bereits existiert
            $exists = Setting::where('setting', $setting['setting'])->exists();
            if (!$exists) {
                Setting::create($setting);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $setting_names = [
            'absence_reason_default',
            'absence_sick_note_days',
            'absence_sick_note_reasons',
        ];

        Setting::whereIn('setting', $setting_names)->delete();
    }
};
