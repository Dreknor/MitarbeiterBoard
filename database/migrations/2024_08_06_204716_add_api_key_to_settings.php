<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
                'setting' => 'indiware_import_key',
                'setting_name' => 'API Key für Indiware Export universell',
                'type' => 'string',
                'value' => Str::random(25),
                'description' => 'API Key für Indiware Export universell. Im Indiware muss in den Einstellungen die URL auf '.config('app.url').'/api/vertretungen/KEY/vp/ gesetzt werden.',
            ],

        ];

        Setting::insert($setting);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::where('setting', 'indiware_import_key')->delete();

    }
};
