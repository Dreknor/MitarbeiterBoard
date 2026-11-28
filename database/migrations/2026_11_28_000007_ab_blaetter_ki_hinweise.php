<?php

use App\Support\AbProfil;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hinweise der KI-Erkennung (Ersatzmodell, vermutlich unvollständig) am Blatt; Vorlage „Groß“
 * war identisch mit dem Standard (20 pt) und wird – sofern unverändert – auf 24 pt gesetzt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ab_blaetter', function (Blueprint $table) {
            $table->json('ki_hinweise')->nullable()->after('ki_seiten');
        });

        $alt = AbProfil::normalisieren(['groesse' => 20, 'zeilenabstand' => 1.5]);
        $neu = collect(AbProfil::STANDARD_VORLAGEN)->firstWhere(0, 'Groß')[1];
        foreach (DB::table('ab_profil_vorlagen')->where('name', 'Groß')->get() as $vorlage) {
            if (AbProfil::normalisieren(json_decode((string) $vorlage->werte, true)) == $alt) {
                DB::table('ab_profil_vorlagen')->where('id', $vorlage->id)->update([
                    'werte' => json_encode(AbProfil::normalisieren($neu)),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ab_blaetter', function (Blueprint $table) {
            $table->dropColumn('ki_hinweise');
        });
    }
};
