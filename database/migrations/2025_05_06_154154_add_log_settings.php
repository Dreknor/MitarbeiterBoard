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
                'module' => 'Logs',
                'setting' => 'log_level',
                'setting_name' => 'Log Level',
                'type' => 'select',
                'value' => 'info',
                'description' => 'Der Log Level, der für die Protokollierung verwendet wird. Mögliche Werte: debug, info, notice, warning, error, critical, alert, emergency.',
            ],
            [
                'module' => 'Logs',
                'setting' => 'log_channel',
                'setting_name' => 'Log Channel',
                'type' => 'select',
                'value' => 'stack',
                'description' => 'Der Log-Kanal, der für die Protokollierung verwendet wird. Mögliche Werte: stack, single, daily, syslog, errorlog, null.',
            ],
            [
                'module' => 'Logs',
                'setting' => 'mail_log',
                'setting_name' => 'Email protokollieren',
                'type' => 'boolean',
                'value' => false,
                'description' => 'Ob E-Mails protokolliert werden sollen oder nicht. Erfordert mindestens Log-Level "Info"',
            ],

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
