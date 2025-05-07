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
                'module' => 'Urlaubsplaner',
                'setting' => 'holiday_claim',
                'setting_name' => 'Anzahl der Urlaubstage',
                'type' => 'number',
                'value' => 28,
                'description' => 'Anzahl der Urlaubstage, die für die Berechnung herangezogen werden, wenn keine individuellen Urlaubstage eingetragen sind.',
            ],

        ];

        Setting::insert($setting);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
