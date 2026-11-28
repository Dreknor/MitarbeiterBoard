<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Arbeitsblaetter\AbKiVerbrauch;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\Klasse;
use App\Notifications\AbKiBudgetWarnung;
use App\Notifications\AngepasstesBlattFehler;
use App\Notifications\AngepasstesBlattZurPruefung;
use App\Services\Arbeitsblaetter\AbKiBudget;
use App\Services\Arbeitsblaetter\AbQuellenService;
use App\Services\Arbeitsblaetter\Ki\AbErkennungFehler;
use App\Services\Arbeitsblaetter\Ki\AnthropicErkenner;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/** KI-Erkennung: Ablauf, Budget (Warnung, Sperre, manuell weiter möglich), keine Personendaten, EXIF entfernt. */
class ErkennungTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
    }

    public function test_kamera_upload_wird_erkannt_und_zur_pruefung_gemeldet(): void
    {
        Notification::fake();
        $user = $this->abBenachrichtigungenAn($this->lehrkraft());
        $this->actingAs($user);

        $antwort = $this->post(route('arbeitsblaetter.store'), [
            'art' => 'kamera',
            'titel' => 'Brüche',
            'dateien' => [UploadedFile::fake()->createWithContent('seite-1.jpg', $this->bildInhalt())],
        ]);
        $blatt = AngepasstesBlatt::firstOrFail();
        $antwort->assertRedirect(route('arbeitsblaetter.edit', $blatt));

        $this->assertSame(AngepasstesBlatt::ZU_PRUEFEN, $blatt->status);
        $this->assertSame('fake', $blatt->ki_anbieter);
        $this->assertSame('Brüche', $blatt->strukturGueltig()['bausteine'][0]['text']);
        $bild = collect($blatt->strukturGueltig()['bausteine'])->firstWhere('typ', 'bild');
        $this->assertNotNull($bild['media_id'], 'Abbildung wurde aus dem Foto ausgeschnitten');
        $this->assertSame(1, AbKiVerbrauch::sum('seiten'));
        Notification::assertSentTo($user, AngepasstesBlattZurPruefung::class);

        $this->get(route('arbeitsblaetter.abbildungen.show', [$blatt, $bild['media_id']]))->assertOk();
        $this->get(route('arbeitsblaetter.edit', $blatt))->assertOk()->assertSee('Brüche');
    }

    public function test_exif_wird_entfernt_und_bild_verkleinert(): void
    {
        $bild = imagecreatetruecolor(3000, 1000);
        ob_start();
        imagejpeg($bild);
        $jpeg = (string) ob_get_clean();
        // APP1-Segment mit EXIF-Kennung einfügen
        $exif = "Exif\0\0".str_repeat('GPS-Daten ', 20);
        $mitExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        $this->assertStringContainsString('GPS-Daten', $mitExif);

        $ergebnis = app(AbQuellenService::class)->bildNormalisieren($mitExif);
        $this->assertStringNotContainsString('GPS-Daten', $ergebnis);
        $this->assertStringNotContainsString('Exif', $ergebnis);
        [$b, $h] = getimagesizefromstring($ergebnis);
        $this->assertSame(2400, max($b, $h));
    }

    public function test_budget_warnung_und_sperre_manuell_bleibt_moeglich(): void
    {
        Notification::fake();
        $admin = $this->lehrkraft(['edit settings']);
        $this->abEinstellung('ab_ki_limit_seiten', '10');
        $this->abEinstellung('ab_ki_limit_euro', '0');
        $budget = app(AbKiBudget::class);

        $budget->verbuchen(null, null, 7, 5, 'fake');
        Notification::assertNothingSent();
        $budget->verbuchen(null, null, 1, 5, 'fake');
        Notification::assertSentTo($admin, AbKiBudgetWarnung::class, fn ($n) => ! $n->gesperrt && $n->prozent === 80);

        $this->assertTrue($budget->darfErkennen(2));
        $this->assertFalse($budget->darfErkennen(3));
        $budget->verbuchen(null, null, 2, 5, 'fake');
        Notification::assertSentTo($admin, AbKiBudgetWarnung::class, fn ($n) => $n->gesperrt);

        $this->actingAs($this->lehrkraft());
        $this->post(route('arbeitsblaetter.store'), [
            'art' => 'upload',
            'dateien' => [UploadedFile::fake()->createWithContent('blatt.jpg', $this->bildInhalt())],
        ])->assertRedirect();
        $blatt = AngepasstesBlatt::firstOrFail();
        $this->assertSame(AngepasstesBlatt::ENTWURF, $blatt->status, 'gesperrt → manuell erfassen');
        $this->assertCount(0, $this->erkenner->aufrufe);
        $this->assertCount(1, $blatt->getMedia(AngepasstesBlatt::ORIGINALE));
        $this->get(route('arbeitsblaetter.edit', $blatt))->assertOk()->assertSee('aufgebraucht');
    }

    public function test_fehler_setzt_status_und_benachrichtigt(): void
    {
        Notification::fake();
        $this->erkenner->fehler = new AbErkennungFehler('Kaputt.');
        $user = $this->abBenachrichtigungenAn($this->lehrkraft());
        $this->actingAs($user);

        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [UploadedFile::fake()->createWithContent('a.jpg', $this->bildInhalt())]]);

        $blatt = AngepasstesBlatt::firstOrFail();
        $this->assertSame(AngepasstesBlatt::FEHLER, $blatt->status);
        $this->assertSame('Kaputt.', $blatt->fehler);
        Notification::assertSentTo($user, AngepasstesBlattFehler::class);
    }

    public function test_anthropic_sendet_keine_personendaten_und_wandelt_antwort(): void
    {
        config(['arbeitsblaetter.ki.schluessel' => 'test-schluessel', 'arbeitsblaetter.ki.modell' => 'claude-opus-5-5']);
        $klasse = Klasse::factory()->create(['name' => '3a']);
        $kind = $this->kindMitProfil($klasse);
        $kind->update(['vorname' => 'Annalena', 'nachname' => 'Geheimnis']);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-opus-5-5',
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode(['seiten' => 1, 'bausteine' => [
                ['typ' => 'ueberschrift', 'text' => 'Brüche'],
                ['typ' => 'bild', 'beschreibung' => 'Kreis', 'seite' => 1, 'box' => [0.1, 0.2, 0.5, 0.6]],
            ]])]],
            'usage' => ['input_tokens' => 10000, 'output_tokens' => 1000],
        ])]);

        $ergebnis = app(AnthropicErkenner::class)->erkennen([['inhalt' => $this->bildInhalt(), 'mime' => 'image/jpeg']], 'Textebene');

        $this->assertSame('Brüche', $ergebnis->struktur['bausteine'][0]['text']);
        $this->assertSame(['seite' => 1, 'box' => [0.085, 0.185, 0.515, 0.615]], $ergebnis->struktur['bausteine'][1]['quelle'], 'Box mit 1,5 % Rand');
        $this->assertSame(6.0, $ergebnis->kostenCent, '10k × 400 ct/1M + 1k × 2000 ct/1M');

        Http::assertSent(function (Request $anfrage) {
            $body = $anfrage->body();
            return $anfrage->hasHeader('x-api-key', 'test-schluessel')
                && ! str_contains($body, 'Annalena') && ! str_contains($body, 'Geheimnis') && ! str_contains($body, '3a')
                && ! str_contains($body, 'farbschema')
                && data_get($anfrage->data(), 'output_config.format.type') === 'json_schema';
        });
    }

    public function test_anthropic_fehler_sind_wiederholbar_bzw_endgueltig(): void
    {
        config(['arbeitsblaetter.ki.schluessel' => 'x']);
        Http::fake(['api.anthropic.com/*' => Http::sequence()->push(['error' => ['message' => 'überlastet']], 529)->push(['error' => ['message' => 'bad']], 400)]);
        $erkenner = app(AnthropicErkenner::class);

        try {
            $erkenner->erkennen([['inhalt' => 'x', 'mime' => 'image/jpeg']]);
            $this->fail('Fehler erwartet');
        } catch (AbErkennungFehler $e) {
            $this->assertTrue($e->wiederholbar);
        }
        try {
            $erkenner->erkennen([['inhalt' => 'x', 'mime' => 'image/jpeg']]);
            $this->fail('Fehler erwartet');
        } catch (AbErkennungFehler $e) {
            $this->assertFalse($e->wiederholbar);
        }
    }

    public function test_ohne_ki_bleibt_der_manuelle_weg(): void
    {
        $this->arbeitsblaetterEinrichten(false);
        $this->actingAs($this->lehrkraft());
        $this->get(route('arbeitsblaetter.create'))->assertOk()->assertSee('keine KI-Erkennung eingerichtet');
        $this->post(route('arbeitsblaetter.store'), ['art' => 'manuell', 'titel' => 'Abgetippt'])->assertRedirect();
        $this->assertSame(AngepasstesBlatt::ENTWURF, AngepasstesBlatt::where('titel', 'Abgetippt')->value('status'));
    }
}
