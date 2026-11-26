<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persönliche Kalenderdaten lagen zwischenzeitlich als Zeilen in der globalen settings-Tabelle
 * (Modul „Kalender“) und erschienen dadurch in den Admin-Einstellungen:
 *  - „user_color_{user}_{kalender}“ → user_calendar_colors
 *  - „feed_token_{user}“            → users.calendar_token (Abo-Links bleiben gültig)
 * Gültige Einträge werden übernommen, die settings-Zeilen entfernt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $zeilen = DB::table('settings')
            ->where('module', 'Kalender')
            ->where(fn ($q) => $q->where('setting', 'like', 'user_color_%')->orWhere('setting', 'like', 'feed_token_%'))
            ->get(['id', 'setting', 'value'])
            // LIKE-„_“ ist ein Platzhalter (Escape unterscheidet sich je Datenbank) – exakt per Regex filtern
            ->filter(fn ($z) => preg_match('/^(user_color_\d+_\d+|feed_token_\d+)$/', $z->setting))
            ->values();

        foreach ($zeilen as $zeile) {
            $wert = (string) $zeile->value;

            if (preg_match('/^user_color_(\d+)_(\d+)$/', $zeile->setting, $m)
                && Schema::hasTable('user_calendar_colors')
                && preg_match('/^#[0-9A-Fa-f]{6}$/', $wert)
                && DB::table('users')->where('id', $m[1])->exists()
                && DB::table('ox_calendars')->where('id', $m[2])->exists()) {
                DB::table('user_calendar_colors')->updateOrInsert(
                    ['user_id' => (int) $m[1], 'ox_calendar_id' => (int) $m[2]],
                    ['farbe' => strtolower($wert), 'created_at' => now(), 'updated_at' => now()]
                );
            }

            if (preg_match('/^feed_token_(\d+)$/', $zeile->setting, $m)
                && Schema::hasColumn('users', 'calendar_token')
                && strlen($wert) === 64
                && ! DB::table('users')->where('calendar_token', $wert)->exists()) {
                DB::table('users')->where('id', $m[1])->whereNull('calendar_token')->update(['calendar_token' => $wert]);
            }
        }

        DB::table('settings')->whereIn('id', $zeilen->pluck('id'))->delete();
    }

    public function down(): void
    {
        // Kein Rückweg nötig – Farben und Tokens bleiben in ihren Tabellen.
    }
};
