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
                'module' => 'Ticketsystem',
                'setting' => 'ticket_closed_automatic',
                'setting_name' => 'Automatisches Schließen von Tickets',
                'type' => 'boolean',
                'value' => '1',
                'description' => 'Sollen Tickets automatisch geschlossen werden, wenn innerhalb der Warnzeit keine Antwort erfolgt?',
            ],
            [
                'module' => 'Ticketsystem',
                'setting' => 'ticket_closed_automatic_days',
                'setting_name' => 'Tage bis zum automatischen Schließen',
                'type' => 'number',
                'value' => '7',
                'description' => 'Anzahl der Tage, die ein Ticket nach Ablauf der Wartezeit offen bleiben kann, bevor es automatisch geschlossen wird.',
            ]
        ];

        Setting::insert($setting);

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::whereIn('setting', ['ticket_closed_automatic', 'ticket_closed_automatic_days'])->delete();

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
        });
    }
};
