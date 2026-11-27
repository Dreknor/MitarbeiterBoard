<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schreibrecht des OX-Dienstkontos je Kalender (aus CalDAV current-user-privilege-set).
 * null = noch nicht geprüft/unbekannt (blockiert nichts), false = OX lässt das Konto nur lesen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ox_calendars', function (Blueprint $table) {
            $table->boolean('ox_schreibrecht')->nullable()->after('schreibbar');
            $table->timestamp('ox_rechte_geprueft_at')->nullable()->after('ox_schreibrecht');
        });
    }

    public function down(): void
    {
        Schema::table('ox_calendars', function (Blueprint $table) {
            $table->dropColumn(['ox_schreibrecht', 'ox_rechte_geprueft_at']);
        });
    }
};
