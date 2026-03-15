<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature-Tests für TODO 22: Wiki-Eintrag Kalender-Modul.
 */
class CalendarDocumentationTest extends TestCase
{
    private string $wikiTitle = 'Kalender';

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

