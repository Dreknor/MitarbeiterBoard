<?php

use App\Models\WikiSeite;
use App\Services\Wiki\WikiService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Handbuch zum Raumplan überarbeitet (u. a. „Freien Raum finden“).
 * Bestehende Seite bekommt eine neue Fassung, die bisherige bleibt im Verlauf; liegt sie noch
 * auf oberster Ebene, kommt sie unter „MitarbeiterBoard“.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wiki_seiten')) {
            return;
        }

        $eltern = WikiSeite::where('slug', 'mitarbeiterboard')->first();

        $seite = app(WikiService::class)->systemseite(
            'raumplanungstool-anleitung',
            'Raumplan',
            file_get_contents(database_path('wiki/handbuecher/raumplanungstool-anleitung.html')),
            $eltern?->slug,
            'Handbuch aktualisiert: Freien Raum finden'
        );

        // Aus der Wiki-Übernahme ggf. noch auf oberster Ebene → zu den übrigen Handbüchern;
        // eine bewusst anders einsortierte Seite bleibt, wo sie ist.
        if ($eltern && $seite->parent_id === null) {
            $seite->parent_id = $eltern->id;
            $seite->save();
        }
    }

    public function down(): void
    {
        // Inhalte bleiben erhalten – frühere Fassungen stehen im Verlauf.
    }
};
