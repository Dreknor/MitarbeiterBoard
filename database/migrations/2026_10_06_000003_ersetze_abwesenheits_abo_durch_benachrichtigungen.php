<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ersetzt das bisherige Abwesenheits-Abo (users.absence_abo_now / absence_abo_daily)
 * durch das Benachrichtigungssystem:
 *
 * - absence_abo_now   → Kategorie „abwesenheiten“ mit Mail „sofort“ (notification_preferences)
 * - absence_abo_daily → Bereich „abwesenheiten“ in der Tagesübersicht (tagesvorschau_einstellungen)
 */
return new class extends Migration
{
    /** PRAGMA foreign_keys wirkt in SQLite nicht innerhalb einer Transaktion */
    public $withinTransaction = false;

    /** Bereiche, die ohne eigene Auswahl aktiv sind (siehe Tagesvorschau-Quellen, standardAktiv()) */
    private const STANDARD_BEREICHE = ['vertretungen', 'dienstplan', 'meetings', 'kalender', 'aufgaben', 'prozesse', 'tickets', 'genehmigungen'];

    public function up(): void
    {
        if (!Schema::hasColumn('users', 'absence_abo_now') && !Schema::hasColumn('users', 'absence_abo_daily')) {
            return;
        }

        $jetzt = now();

        if (Schema::hasColumn('users', 'absence_abo_now')) {
            foreach (DB::table('users')->where('absence_abo_now', 1)->whereNull('deleted_at')->pluck('id') as $userId) {
                DB::table('notification_preferences')->updateOrInsert(
                    ['user_id' => $userId, 'kategorie' => 'abwesenheiten'],
                    ['push' => false, 'mail' => 'sofort', 'created_at' => $jetzt, 'updated_at' => $jetzt]
                );
            }
        }

        if (Schema::hasColumn('users', 'absence_abo_daily')) {
            foreach (DB::table('users')->where('absence_abo_daily', 1)->whereNull('deleted_at')->pluck('id') as $userId) {
                $vorhanden = DB::table('tagesvorschau_einstellungen')->where('user_id', $userId)->first();

                if ($vorhanden) {
                    $bereiche = $vorhanden->bereiche !== null ? (json_decode($vorhanden->bereiche, true) ?: []) : self::STANDARD_BEREICHE;
                    $bereiche = array_values(array_unique([...$bereiche, 'abwesenheiten']));

                    DB::table('tagesvorschau_einstellungen')->where('id', $vorhanden->id)->update([
                        'bereiche'   => json_encode($bereiche),
                        'per_mail'   => true,
                        'updated_at' => $jetzt,
                    ]);

                    continue;
                }

                DB::table('tagesvorschau_einstellungen')->insert([
                    'user_id'             => $userId,
                    'per_mail'            => true,
                    'per_push'            => false,
                    'zeitpunkt'           => 'morgens',
                    // bisheriger Versand um 07:30 Uhr
                    'uhrzeit'             => '07:30:00',
                    'bereiche'            => json_encode([...self::STANDARD_BEREICHE, 'abwesenheiten']),
                    'kalender_ids'        => json_encode([]),
                    'eingeladene_termine' => true,
                    'created_at'          => $jetzt,
                    'updated_at'          => $jetzt,
                ]);
            }
        }

        // Ein einziger dropColumn-Aufruf (SQLite erlaubt keine mehrfachen pro Änderung)
        $spalten = array_values(array_filter(
            ['absence_abo_daily', 'absence_abo_now'],
            fn (string $spalte) => Schema::hasColumn('users', $spalte)
        ));

        // SQLite (Tests) baut die Tabelle dafür neu auf → Fremdschlüssel kurz aussetzen
        Schema::withoutForeignKeyConstraints(
            fn () => Schema::table('users', fn (Blueprint $table) => $table->dropColumn($spalten))
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'absence_abo_daily')) {
                $table->boolean('absence_abo_daily')->nullable();
            }
            if (!Schema::hasColumn('users', 'absence_abo_now')) {
                $table->boolean('absence_abo_now')->nullable();
            }
        });

        $sofort = DB::table('notification_preferences')
            ->where('kategorie', 'abwesenheiten')
            ->where('mail', 'sofort')
            ->pluck('user_id');
        DB::table('users')->whereIn('id', $sofort)->update(['absence_abo_now' => 1]);

        $taeglich = DB::table('tagesvorschau_einstellungen')
            ->whereNotNull('bereiche')
            ->get(['user_id', 'bereiche'])
            ->filter(fn ($z) => in_array('abwesenheiten', json_decode($z->bereiche, true) ?: [], true))
            ->pluck('user_id');
        DB::table('users')->whereIn('id', $taeglich)->update(['absence_abo_daily' => 1]);
    }
};
