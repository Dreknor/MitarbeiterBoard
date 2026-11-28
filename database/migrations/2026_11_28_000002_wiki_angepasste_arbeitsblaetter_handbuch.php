<?php

use App\Models\WikiSeite;
use App\Services\Wiki\WikiService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Handbuch „Angepasste Arbeitsblätter“ als Systemseite unter „MitarbeiterBoard“. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wiki_seiten')) {
            return;
        }

        $eltern = WikiSeite::where('slug', 'mitarbeiterboard')->first();

        app(WikiService::class)->systemseite(
            'angepasste-arbeitsblaetter',
            'Angepasste Arbeitsblätter',
            file_get_contents(database_path('wiki/handbuecher/angepasste-arbeitsblaetter.html')),
            $eltern?->slug,
            'Handbuch zum neuen Modul'
        );
    }

    public function down(): void
    {
        // Inhalte bleiben erhalten – frühere Fassungen stehen im Verlauf.
    }
};
