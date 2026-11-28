<?php

use App\Models\WikiSeite;
use App\Services\Wiki\WikiService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Handbuch „Angepasste Arbeitsblätter“: Grundschul-Lineatur und Sidebar-Name. Neue Fassung, alte bleibt im Verlauf. */
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
            'Handbuch: Grundschul-Lineatur, „Blätter anpassen“'
        );
    }

    public function down(): void
    {
        // Inhalte bleiben erhalten – frühere Fassungen stehen im Verlauf.
    }
};
