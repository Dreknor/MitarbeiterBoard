<?php

use App\Models\DailyNews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagesinfos (daily_news):
 * - Spalte `quelle` ('vp_import' = aus dem Vertretungsplan-Import, null = manuell angelegt),
 *   damit der Import nur seine eigenen Infos wieder entfernt.
 * - Einmalige Bereinigung: Duplikate (gleicher Zeitraum + gleicher Text, Leerzeichen normalisiert)
 *   löschen, der älteste Eintrag bleibt. Gelöscht wird über das Model, damit der
 *   VertretungNewsObserver die Duplikate auch im ElternInfoBoard entfernt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_news', function (Blueprint $table) {
            $table->string('quelle', 32)->nullable()->after('news');
            $table->index(['date_start', 'quelle']);
        });

        DailyNews::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (DailyNews $n) => DailyNews::schluessel($n->date_start, $n->date_end, $n->news))
            ->each(fn ($gruppe) => $gruppe->slice(1)->each->delete());
    }

    public function down(): void
    {
        Schema::table('daily_news', function (Blueprint $table) {
            $table->dropIndex(['date_start', 'quelle']);
            $table->dropColumn('quelle');
        });
    }
};
