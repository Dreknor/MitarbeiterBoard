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
                'setting' => 'vertretungsplan_send_elterninfoboard',
                'setting_name' => 'Vertretungen an ElternInfoBoard senden',
                'type' => 'boolean',
                'value' => 0,
                'description' => 'Wenn diese Einstellung aktiviert ist, werden Vertretungen an das ElternInfoBoard gesendet. Dazu muss die URL in den verknüpfungen angegeben sein.',

            ],
            [
                'module' => 'Verknüpfungen',
                'setting' => 'elterninfoboard_url',
                'setting_name' => 'Adresse des ElternInfoBoard',
                'type' => 'url',
                'value' => '',
                'description' => 'URL des ElternInfoBoard'
            ],
            [
                'module' => 'Verknüpfungen',
                'setting' => 'api_key_elterninfoboard',
                'setting_name' => 'API-Key des ElternInfoBoard',
                'type' => 'text',
                'value' => '',
                'description' => 'Authentifizierungsschlüssel für das ElternInfoBoard. Wird benötigt um Vertretungen dort ebenfalls anzuzeigen'
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
