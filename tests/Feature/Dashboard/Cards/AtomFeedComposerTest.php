<?php

namespace Tests\Feature\Dashboard\Cards;

use App\View\Composers\AtomFeedComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AtomFeedComposerTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://feed.example.test/atom.xml';

    private function laden(): array
    {
        $composer = new AtomFeedComposer();
        $methode = new \ReflectionMethod($composer, 'fetchFeed');
        $methode->setAccessible(true);

        return $methode->invoke($composer, self::URL);
    }

    private function feed(string $vorspann): string
    {
        return $vorspann . '
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Test</title>
    <entry>
        <title type="text">Austausch der Schuladmins</title>
        <published>2026-11-24T11:42:57+01:00</published>
        <link rel="alternate" type="text/html" href="https://feed.example.test/events/1" />
        <summary type="text">Kurzbeschreibung</summary>
    </entry>
</feed>';
    }

    public function test_textrest_nach_xml_deklaration_wird_toleriert(): void
    {
        Http::fake([self::URL => Http::response($this->feed("\xEF\xBB\xBF<?xml version=\"1.0\" encoding=\"utf-8\"?>'; ?>"))]);
        Log::spy();

        $eintraege = $this->laden();

        $this->assertCount(1, $eintraege);
        $this->assertSame('Austausch der Schuladmins', $eintraege[0]['title']);
        $this->assertSame('https://feed.example.test/events/1', $eintraege[0]['link']);
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_gueltiger_feed_bleibt_unveraendert(): void
    {
        Http::fake([self::URL => Http::response($this->feed('<?xml version="1.0" encoding="utf-8"?>'))]);

        $this->assertCount(1, $this->laden());
    }

    public function test_kaputter_feed_liefert_leere_liste_und_nur_warnung(): void
    {
        Http::fake([self::URL => Http::response('<html><body>Wartung</body>')]);
        Log::spy();

        $this->assertSame([], $this->laden());
        Log::shouldHaveReceived('warning')->once();
        Log::shouldNotHaveReceived('error');
    }
}
