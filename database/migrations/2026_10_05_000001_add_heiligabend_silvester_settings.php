<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Urlaubsplaner: Wertung von Heiligabend und Silvester (Standard: wie Feiertage).
 */
return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'module' => 'Urlaubsplaner',
                'setting' => 'heiligabend_feiertag',
                'setting_name' => 'Heiligabend (24.12.) als Feiertag werten',
                'type' => 'boolean',
                'value' => '1',
                'description' => 'An = Heiligabend zählt wie ein Feiertag: keine Soll-Arbeitszeit, kein Urlaubstag. Aus = normaler Arbeitstag. Gilt ab dem Stichtag des neuen Arbeitszeitmodells.',
            ],
            [
                'module' => 'Urlaubsplaner',
                'setting' => 'silvester_feiertag',
                'setting_name' => 'Silvester (31.12.) als Feiertag werten',
                'type' => 'boolean',
                'value' => '1',
                'description' => 'An = Silvester zählt wie ein Feiertag: keine Soll-Arbeitszeit, kein Urlaubstag. Aus = normaler Arbeitstag. Gilt ab dem Stichtag des neuen Arbeitszeitmodells.',
            ],
        ];

        foreach ($settings as $setting) {
            if (!Setting::where('setting', $setting['setting'])->exists()) {
                Setting::create($setting);
            }
        }
    }

    public function down(): void
    {
        Setting::whereIn('setting', ['heiligabend_feiertag', 'silvester_feiertag'])->delete();
    }
};
