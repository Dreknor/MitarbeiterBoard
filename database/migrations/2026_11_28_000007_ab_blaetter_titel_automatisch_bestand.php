<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blätter, die vor der Spalte `titel_automatisch` aus Fotos/Dateien angelegt wurden und noch nicht
 * freigegeben sind, tragen meist den Dateinamen als Titel – beim nächsten „Erkennen“ ersetzen.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ab_blaetter')
            ->whereIn('quelle', ['kamera', 'upload', 'wochenplan'])
            ->where('status', '!=', 'freigegeben')
            ->where('titel_automatisch', false)
            ->update(['titel_automatisch' => true]);
    }

    public function down(): void
    {
    }
};
