<?php

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
                'module' => 'Raumplan',
                'setting' => 'Startzeit für die Raumreservierung',
                'setting_name' => 'roombooking_start_hour',
                'type' => 'integer',
                'value' => '8',
                'description' => 'Startstunde für die Raumreservierung (0-23)',
            ],
            [
                'module' => 'Raumplan',
                'setting' => 'Endzeit für die Raumreservierung',
                'setting_name' => 'roombooking_end_hour',
                'type' => 'integer',
                'value' => '20',
                'description' => 'Endstunde für die Raumreservierung (0-23)',
            ]

        ];

            \App\Models\Setting::insert($settings);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $setting_names = [
            'roombooking_start_hour',
            'roombooking_end_hour',
        ];

        \App\Models\Setting::whereIn('setting', $setting_names)->delete();
    }
};
