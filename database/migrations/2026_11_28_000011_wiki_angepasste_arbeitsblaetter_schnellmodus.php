<?php

use App\Models\WikiSeite;
use App\Services\Wiki\WikiService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Handbuch „Angepasste Arbeitsblätter“: Schnellmodus und Benachrichtigungen als Opt-in. Neue Fassung, alte bleibt im Verlauf. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wiki_seiten')) {
            return;
        }

        app(WikiService::class)->systemseite(
            'angepasste-arbeitsblaetter',
            'Angepasste Arbeitsblätter',
            file_get_contents(database_path('wiki/handbuecher/angepasste-arbeitsblaetter.html')),
            WikiSeite::where('slug', 'mitarbeiterboard')->value('slug'),
            'Handbuch: Schnellmodus, Benachrichtigungen'
        );
    }

    public function down(): void
    {
        // Inhalte bleiben erhalten – frühere Fassungen stehen im Verlauf.
    }
};
