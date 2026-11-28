<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/** Angepasste Arbeitsblätter: Freigabe, Arial/Verdana von Microsoft zu laden (Lizenz der Core Fonts). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Setting::where('setting', 'ab_ms_schriften_laden')->exists()) {
            Setting::insert([
                'module' => 'Angepasste Arbeitsblätter',
                'setting' => 'ab_ms_schriften_laden',
                'setting_name' => 'Microsoft-Schriften (Arial, Verdana) laden',
                'type' => 'boolean',
                'value' => '0',
                'description' => 'Lizenzbedingungen der Microsoft „Core Fonts for the Web“ akzeptiert – der Online-Updater bzw. „Jetzt laden“ holt Arial und Verdana.',
            ]);
        }
    }

    public function down(): void
    {
        Setting::where('setting', 'ab_ms_schriften_laden')->delete();
    }
};
