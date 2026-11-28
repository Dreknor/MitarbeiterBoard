<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Klasse;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Services\Arbeitsblaetter\Ki\AbErkennerFabrik;
use App\Services\Arbeitsblaetter\Ki\AbErkennungFehler;
use App\Services\Arbeitsblaetter\Ki\ErkennerVorgaben;
use App\Services\Arbeitsblaetter\Ki\GeminiErkenner;
use App\Services\Arbeitsblaetter\Ki\OpenRouterErkenner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/** KI-Treiber Gemini, OpenRouter (auto) und OpenRouter (free): Anfrageformat, keine Personendaten, Kosten, Fehler. */
class KiTreiberTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    private const ANTWORT = ['seiten' => 1, 'bausteine' => [
        ['typ' => 'ueberschrift', 'text' => 'Brüche'],
        ['typ' => 'aufgabe', 'nummer' => '1', 'inhalt' => [['typ' => 'text', 'text' => '3/4 = '], ['typ' => 'luecke', 'laenge' => 3]]],
        ['typ' => 'bild', 'beschreibung' => 'Kreis', 'seite' => 1, 'box' => [0.1, 0.2, 0.5, 0.6]],
    ]];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'arbeitsblaetter.ki.gemini.schluessel' => 'g-schluessel',
            'arbeitsblaetter.ki.gemini.modell' => 'gemini-flash-latest',
            'arbeitsblaetter.ki.openrouter.schluessel' => 'or-schluessel',
        ]);
        $kind = $this->kindMitProfil(Klasse::factory()->create(['name' => '3a']));
        $kind->update(['vorname' => 'Annalena', 'nachname' => 'Geheimnis']);
    }

    private function seiten(): array
    {
        return [['inhalt' => $this->bildInhalt(), 'mime' => 'image/jpeg'], ['inhalt' => '%PDF-1.4 test', 'mime' => 'application/pdf']];
    }

    private function ohnePersonendaten(string $body): bool
    {
        return ! str_contains($body, 'Annalena') && ! str_contains($body, 'Geheimnis') && ! str_contains($body, 'farbschema');
    }

    public function test_fabrik_kennt_alle_treiber(): void
    {
        foreach (['gemini' => GeminiErkenner::class, 'openrouter_auto' => OpenRouterErkenner::class, 'openrouter_free' => OpenRouterErkenner::class] as $treiber => $klasse) {
            config(['arbeitsblaetter.ki.treiber' => $treiber]);
            $erkenner = AbErkennerFabrik::erstellen();
            $this->assertInstanceOf($klasse, $erkenner);
            $this->assertTrue($erkenner->konfiguriert());
            $this->assertArrayHasKey($treiber, AbErkennerFabrik::TREIBER);
        }
        config(['arbeitsblaetter.ki.treiber' => 'openrouter_free']);
        $this->assertSame('openrouter/free', AbErkennerFabrik::erstellen()->modell());
        config(['arbeitsblaetter.ki.treiber' => 'openrouter_auto']);
        $this->assertSame('openrouter/auto', AbErkennerFabrik::erstellen()->modell());
    }

    public function test_gemini_anfrage_und_antwort(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode(self::ANTWORT)]]]]],
            'usageMetadata' => ['promptTokenCount' => 100000, 'candidatesTokenCount' => 10000, 'thoughtsTokenCount' => 10000],
            'modelVersion' => 'gemini-x-flash',
        ])]);

        $ergebnis = app(GeminiErkenner::class)->erkennen($this->seiten(), 'Textebene');

        $this->assertSame('Brüche', $ergebnis->struktur['bausteine'][0]['text']);
        $this->assertSame(['seite' => 1, 'box' => [0.085, 0.185, 0.515, 0.615]], $ergebnis->struktur['bausteine'][2]['quelle'], 'Box mit 1,5 % Rand');
        $this->assertSame(11.0, $ergebnis->kostenCent, '0,1 Mio × 50 ct + 0,02 Mio × 300 ct');
        $this->assertSame('gemini', $ergebnis->anbieter);
        $this->assertSame('gemini-x-flash', $ergebnis->modell);

        Http::assertSent(function (Request $r) {
            $teile = data_get($r->data(), 'contents.0.parts');
            return str_contains($r->url(), '/models/gemini-flash-latest:generateContent')
                && $r->hasHeader('x-goog-api-key', 'g-schluessel')
                && data_get($r->data(), 'generationConfig.responseMimeType') === 'application/json'
                && data_get($r->data(), 'generationConfig.responseJsonSchema.type') === 'object'
                && collect($teile)->contains(fn ($t) => data_get($t, 'inlineData.mimeType') === 'application/pdf')
                && collect($teile)->contains(fn ($t) => data_get($t, 'inlineData.mimeType') === 'image/jpeg')
                && $this->ohnePersonendaten($r->body());
        });
    }

    public function test_gemini_fehler(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'You exceeded your current quota']], 429) // Kontingent: keine sinnlose Wiederholung
            ->push(['promptFeedback' => ['blockReason' => 'SAFETY']])
            ->push(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{"seiten":1,"baus']]]]]])]);
        $erkenner = app(GeminiErkenner::class);

        foreach ([[true, 'Kontingent'], [false, 'abgelehnt'], [false, 'zu umfangreich']] as [$wiederholbar, $text]) {
            try {
                $erkenner->erkennen($this->seiten());
                $this->fail('Fehler erwartet');
            } catch (AbErkennungFehler $e) {
                $this->assertSame($wiederholbar, $e->wiederholbar);
                $this->assertStringContainsString($text, $e->getMessage());
            }
        }
    }

    public function test_openrouter_auto_anfrage_kosten_und_codezaun(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([
            'model' => 'anthropic/claude-x',
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => "```json\n".json_encode(self::ANTWORT)."\n```"]]],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100, 'cost' => 0.0234],
        ])]);

        $ergebnis = (new OpenRouterErkenner('auto'))->erkennen($this->seiten(), 'Textebene');

        $this->assertSame('Brüche', $ergebnis->struktur['bausteine'][0]['text']);
        $this->assertSame(2.34, $ergebnis->kostenCent, 'gemeldete Kosten 0,0234 USD = 2,34 Cent (Bruchteile bleiben erhalten)');
        $this->assertSame('openrouter_auto', $ergebnis->anbieter);
        $this->assertSame('anthropic/claude-x', $ergebnis->modell);

        Http::assertSent(function (Request $r) {
            $inhalt = data_get($r->data(), 'messages.1.content');
            return $r->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $r->hasHeader('Authorization', 'Bearer or-schluessel')
                && data_get($r->data(), 'model') === 'openrouter/auto'
                && data_get($r->data(), 'response_format.type') === 'json_schema'
                && data_get($r->data(), 'provider.data_collection') === 'deny'
                && data_get($r->data(), 'provider.require_parameters') === true
                && ! isset($r->data()['plugins'])
                && collect($inhalt)->contains(fn ($t) => ($t['type'] ?? '') === 'file' && str_starts_with($t['file']['file_data'], 'data:application/pdf;base64,'))
                && collect($inhalt)->contains(fn ($t) => ($t['type'] ?? '') === 'image_url' && str_starts_with($t['image_url']['url'], 'data:image/jpeg;base64,'))
                && $this->ohnePersonendaten($r->body());
        });
    }

    public function test_openrouter_free_kostet_nichts_und_nutzt_kostenlose_pdf_umwandlung(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([
            'model' => 'meta/irgendwas:free',
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'Hier ist das Ergebnis: '.json_encode(self::ANTWORT)]]],
            'usage' => ['prompt_tokens' => 50000, 'completion_tokens' => 5000],
        ])]);

        $ergebnis = (new OpenRouterErkenner('free'))->erkennen($this->seiten());

        $this->assertSame(0.0, $ergebnis->kostenCent);
        $this->assertSame('openrouter_free', $ergebnis->anbieter);
        $this->assertCount(3, $ergebnis->struktur['bausteine']);
        Http::assertSent(fn (Request $r) => data_get($r->data(), 'model') === 'openrouter/free'
            && data_get($r->data(), 'plugins.0.pdf.engine') === 'cloudflare-ai');
    }

    public function test_openrouter_fehler(): void
    {
        Http::fake(['openrouter.ai/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limited']], 429)
            ->push(['error' => ['message' => 'no credits']], 402)
            ->push(['error' => ['message' => 'Provider returned error', 'code' => 502]], 200)
            ->push(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'Ich kann das nicht.']]]])]);
        $erkenner = new OpenRouterErkenner('free');

        foreach ([[true, 'ausgelastet'], [false, 'Guthaben'], [true, 'Provider returned error'], [true, 'nicht gelesen']] as [$wiederholbar, $text]) {
            try {
                $erkenner->erkennen($this->seiten());
                $this->fail('Fehler erwartet');
            } catch (AbErkennungFehler $e) {
                $this->assertSame($wiederholbar, $e->wiederholbar, $text);
                $this->assertStringContainsString($text, $e->getMessage());
            }
        }
    }

    public function test_ganzer_ablauf_mit_gemini_ueber_die_fabrik(): void
    {
        $this->arbeitsblaetterEinrichten();
        config(['arbeitsblaetter.ki.treiber' => 'gemini']);
        $this->app->forgetInstance(AbErkenner::class);
        $this->app->bind(AbErkenner::class, fn () => AbErkennerFabrik::erstellen());
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode(self::ANTWORT)]]]]],
            'usageMetadata' => ['promptTokenCount' => 1000, 'candidatesTokenCount' => 100],
        ])]);
        $this->actingAs($this->lehrkraft());

        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [\Illuminate\Http\UploadedFile::fake()->createWithContent('a.jpg', $this->bildInhalt())]])->assertRedirect();

        $blatt = \App\Models\Arbeitsblaetter\AngepasstesBlatt::firstOrFail();
        $this->assertSame('zu_pruefen', $blatt->status);
        $this->assertSame('gemini', $blatt->ki_anbieter);
        $this->assertNotNull(collect($blatt->strukturGueltig()['bausteine'])->firstWhere('typ', 'bild')['media_id']);
    }

    public function test_verbindungspruefung(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['name' => 'models/gemini-flash-latest']),
            'openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'x']]),
        ]);
        $this->assertTrue(app(GeminiErkenner::class)->pruefen()[0]);
        $this->assertTrue((new OpenRouterErkenner('free'))->pruefen()[0]);
        $this->assertSame(['a' => 1], ErkennerVorgaben::jsonLesen("```\n{\"a\":1}\n```"));
        $this->assertNull(ErkennerVorgaben::jsonLesen('kein json'));
    }
}
