<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature-Tests für die Dokumentation des Kalender-Moduls im Wiki.
 */
class CalendarDocumentationTest extends TestCase
{
    private string $wikiTitle = 'Kalender';

    protected function setUp(): void
    {
        parent::setUp();

        // Wiki-Migration benötigt einen User mit ID 1 (author_id)
        if (!User::find(1)) {
            User::factory()->create(['id' => 1]);
        }

        // Wiki-Eintrag manuell anlegen falls Migration fehlschlug (z.B. wegen fehlender User)
        if (Schema::hasTable('wiki_sites') && !DB::table('wiki_sites')->where('title', $this->wikiTitle)->exists()) {
            // Migration erneut ausführen
            $migration = include database_path('migrations/2026_03_14_120000_add_calendar_wiki_entry.php');
            $migration->up();
        }
    }

    public function test_Wiki_Eintrag_fuer_Kalender_Modul_existiert(): void
    {
        if (!Schema::hasTable('wiki_sites')) {
            $this->markTestSkipped('wiki_sites Tabelle existiert nicht');
        }

        $this->assertTrue(
            DB::table('wiki_sites')->where('title', $this->wikiTitle)->exists(),
            "Wiki-Eintrag \"{$this->wikiTitle}\" wurde nicht gefunden."
        );
    }

    public function test_Wiki_Eintrag_enthaelt_Abschnitte(): void
    {
        if (!Schema::hasTable('wiki_sites')) {
            $this->markTestSkipped('wiki_sites Tabelle existiert nicht');
        }

        $text = DB::table('wiki_sites')->where('title', $this->wikiTitle)->value('text');

        $this->assertNotNull($text);
        $this->assertStringContainsString('iCal', $text);
        $this->assertStringContainsString('Administratoren', $text);
        $this->assertStringContainsString('Sync-Logs', $text);
    }
}

