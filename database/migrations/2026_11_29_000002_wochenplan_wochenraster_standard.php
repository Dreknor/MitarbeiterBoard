<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tägliche Übungen: „horizontal“ (eine Spalte je Datum) war nur der bisherige Vorgabewert und
 * wird bei mehrwöchigen Plänen unlesbar. Formatvorlagen mit diesem Wert erhalten das Wochenraster
 * (Woche × Wochentag × Übung) wie in der Papiervorlage. Andere Layouts bleiben unverändert.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('wp_formatvorlagen')->whereNotNull('layout_config')->orderBy('id')->each(function ($zeile) {
            $config = json_decode((string) $zeile->layout_config, true);
            if (! is_array($config) || ($config['taegliche_uebungen']['layout'] ?? null) !== 'horizontal') {
                return;
            }
            $config['taegliche_uebungen']['layout'] = 'wochenweise';
            DB::table('wp_formatvorlagen')->where('id', $zeile->id)
                ->update(['layout_config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
        });
    }

    public function down(): void
    {
        // keine Rücknahme: welche Vorlagen vorher „horizontal“ hatten, ist nicht mehr bekannt
    }
};
