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
        \App\Models\Setting::insert([
            'module' => 'Vertretungsplan',
            'setting' => 'vertretungsplan_remove',
            'setting_name' => 'vertretungsplan_remove',
            'type'  => 'text',
            'value' => '',
            'description' => 'Wenn die Gruppen aus einer LDAP-Verzeichnisstruktur kommen, wird der hier angegebene Text aus den gruppen entfernt, welches vor den Klassennamen steht.',
        ]);

        \App\Models\Setting::insert([
            'module' => 'Vertretungsplan',
            'setting' => 'vertretungsplan_add_prefix',
            'setting_name' => 'vertretungsplan_add_prefix',
            'type'  => 'text',
            'value' => '',
            'description' => 'Wenn die Gruppen aus einer LDAP-Verzeichnisstruktur kommen, wird der hier angegebene Text vor den Klassennamen gestellt.',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Models\Setting::where('setting', 'vertretungsplan_remove')->delete();
        \App\Models\Setting::where('setting', 'vertretungsplan_add_prefix')->delete();
    }
};
