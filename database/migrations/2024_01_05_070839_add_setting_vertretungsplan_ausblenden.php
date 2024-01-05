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
        $setting = [
            [
            'module' => 'Vertretungsplan',
            'setting' => 'vertretungsplan_ausblenden',
            'setting_name' => 'Vertretungsplan ausblenden',
            'type' => 'boolean',
            'value' => 1,
            'description' => 'Wenn diese Einstellung aktiviert ist, wird der aktuelle Vertretungsplan aber einer gewissen Uhrzeit ausgeblendet.',

        ],
            [
            'module' => 'Vertretungsplan',
            'setting' => 'vertretungsplan_ausblenden_zeit',
            'setting_name' => 'Vertretungsplan ausblenden Uhrzeit',
            'type' => 'time',
            'value' => '13:00',
            'description' => 'Wenn die Einstellung "Vertretungsplan ausblenden" aktiviert ist, wird der aktuelle Vertretungsplan aber einer gewissen Uhrzeit ausgeblendet.',
            ]
            ];

        Setting::insert($setting);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
