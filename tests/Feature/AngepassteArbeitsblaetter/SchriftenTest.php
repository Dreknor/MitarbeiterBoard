<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Services\Arbeitsblaetter\AbSatzService;
use App\Services\Arbeitsblaetter\AbSchriftenService;
use App\Support\CabArchiv;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/**
 * Arial/Verdana bereitstellen: CAB-Leser, Übernahme aus Ordnern, Download nur mit Freigabe und
 * passender Prüfsumme, Befehl (auch für den Online-Updater) und Knopf in den Einstellungen.
 *
 * Die Microsoft-Pakete selbst sind nicht im Repository. Liegen arial32.exe/verdan32.exe in dem Ordner
 * aus AB_TEST_COREFONTS, prüft {@see test_originalpakete_mit_lzx()} das Entpacken echter Pakete.
 */
class SchriftenTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    private string $ziel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
        $this->ziel = sys_get_temp_dir().'/ab-schriften-'.uniqid();
        config(['arbeitsblaetter.schriften_pfad' => $this->ziel]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->ziel.'/*') ?: [] as $datei) {
            unlink($datei);
        }
        @rmdir($this->ziel);
        parent::tearDown();
    }

    private function ttf(): string
    {
        return (string) file_get_contents(resource_path('fonts/arbeitsblaetter/AtkinsonHyperlegible-Regular.ttf'));
    }

    /** Baut ein CAB-Archiv (ein Ordner, unkomprimiert oder MSZIP). */
    private function cab(array $dateien, bool $mszip): string
    {
        $daten = implode('', $dateien);
        $bloecke = '';
        foreach (str_split($daten, 32768) as $stueck) {
            $nutz = $mszip ? 'CK'.gzdeflate($stueck, 9) : $stueck;
            $bloecke .= pack('Vvv', 0, strlen($nutz), strlen($stueck)).$nutz;
        }
        $eintraege = '';
        $offset = 0;
        foreach ($dateien as $name => $inhalt) {
            $eintraege .= pack('VVvvvv', strlen($inhalt), $offset, 0, 0, 0, 0x20).$name."\0";
            $offset += strlen($inhalt);
        }
        $kopfLaenge = 36;
        $ordnerOffset = $kopfLaenge;
        $dateienOffset = $ordnerOffset + 8;
        $datenOffset = $dateienOffset + strlen($eintraege);
        $anzahlBloecke = count(str_split($daten, 32768));
        $gesamt = $datenOffset + strlen($bloecke);

        return pack('a4VVVVVCCvvvvv', 'MSCF', 0, $gesamt, 0, $dateienOffset, 0, 3, 1, 1, count($dateien), 0, 0, 0)
            .pack('Vvv', $datenOffset, $anzahlBloecke, $mszip ? 1 : 0)
            .$eintraege.$bloecke;
    }

    public function test_cab_leser_unkomprimiert_und_mszip_auch_in_exe(): void
    {
        $dateien = ['Arial.TTF' => $this->ttf(), 'fontinst.inf' => str_repeat('[font]', 20)];
        foreach ([false, true] as $mszip) {
            $exe = 'MZ'.random_bytes(500).$this->cab($dateien, $mszip);
            $this->assertSame($dateien, CabArchiv::dateien($exe), $mszip ? 'MSZIP' : 'unkomprimiert');
        }
        $this->expectException(RuntimeException::class);
        CabArchiv::dateien('kein Archiv');
    }

    public function test_originalpakete_mit_lzx(): void
    {
        $ordner = (string) env('AB_TEST_COREFONTS', '');
        if ($ordner === '' || ! is_file($ordner.'/arial32.exe') || ! is_file($ordner.'/verdan32.exe')) {
            $this->markTestSkipped('Originalpakete nicht vorhanden (AB_TEST_COREFONTS).');
        }
        foreach (AbSchriftenService::PAKETE as $paket => $info) {
            $inhalt = file_get_contents($ordner.'/'.$paket);
            $this->assertSame($info['sha256'], hash('sha256', $inhalt));
            $dateien = array_change_key_case(CabArchiv::dateien($inhalt), CASE_LOWER);
            foreach (array_keys($info['dateien']) as $name) {
                $this->assertSame(0xB1B0AFBA, $this->ttfSumme($dateien[$name]), $name.': TTF-Prüfsumme');
            }
        }
    }

    private function ttfSumme(string $daten): int
    {
        $daten .= str_repeat("\0", (4 - strlen($daten) % 4) % 4);
        $summe = 0;
        foreach (unpack('N*', $daten) as $wert) {
            $summe = ($summe + $wert) & 0xFFFFFFFF;
        }

        return $summe;
    }

    public function test_uebernahme_aus_ordner_ohne_gross_kleinschreibung(): void
    {
        $quelle = $this->ziel.'-quelle';
        mkdir($quelle);
        file_put_contents($quelle.'/ARIAL.TTF', $this->ttf());
        file_put_contents($quelle.'/arialbd.ttf', $this->ttf());
        file_put_contents($quelle.'/verdana.ttf', 'kaputt');

        $kopiert = app(AbSchriftenService::class)->ausOrdner($quelle);

        $this->assertSame(['arial.ttf', 'arialbd.ttf'], $kopiert);
        $this->assertFalse(AbSatzService::nutztErsatz('arial'));
        $this->assertTrue(AbSatzService::nutztErsatz('verdana'), 'beschädigte Datei wird nicht übernommen');
        array_map('unlink', glob($quelle.'/*'));
        rmdir($quelle);
    }

    public function test_laden_nur_mit_freigabe_und_passender_pruefsumme(): void
    {
        $service = app(AbSchriftenService::class);

        // ohne Freigabe: kein Netzwerkzugriff (Http::preventStrayRequests würde werfen)
        $ergebnis = $service->automatisch(false);
        $this->assertSame([], $ergebnis['geladen']);
        $this->assertNotNull($ergebnis['hinweis']);
        $this->assertCount(4, $ergebnis['fehlt']);

        Http::fake(['downloads.sourceforge.net/*' => Http::response('MZ gefälschtes Paket')]);
        try {
            $service->automatisch(true);
            $this->fail('Prüfsummenfehler erwartet');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Prüfsumme', $e->getMessage());
        }
        $this->assertFileDoesNotExist($this->ziel.'/arial.ttf');
    }

    public function test_befehl_fuer_updater_scheitert_nie_und_laden_braucht_zustimmung(): void
    {
        $this->artisan('arbeitsblaetter:schriften --automatisch')->expectsOutputToContain('Original fehlt')->assertSuccessful();

        $this->abEinstellung('ab_ms_schriften_laden', '1');
        Http::fake(['downloads.sourceforge.net/*' => Http::response('', 503)]);
        $this->artisan('arbeitsblaetter:schriften --automatisch')->expectsOutputToContain('konnten nicht geladen werden')->assertSuccessful();

        $this->abEinstellung('ab_ms_schriften_laden', '0');
        $this->artisan('arbeitsblaetter:schriften --laden --no-interaction')->expectsOutputToContain('Abgebrochen')->assertFailed();

        $this->assertContains(['arbeitsblaetter:schriften', '--automatisch'], config('updater.artisan_commands'));
    }

    public function test_einstellungen_freigabe_und_jetzt_laden(): void
    {
        $this->actingAs($this->lehrkraft(['edit settings']));
        $this->get(route('arbeitsblaetter.einstellungen'))->assertOk()->assertSee('Lizenzbedingungen der Microsoft')->assertSee('Ersatzschrift aktiv');

        $this->post(route('arbeitsblaetter.einstellungen.schriften'))->assertRedirect()->assertSessionHas('type', 'warning');

        $this->put(route('arbeitsblaetter.einstellungen.update'), [
            'ab_ordner_vorlage' => '{titel}', 'ab_datei_vorlage' => '{titel}', 'ab_ki_limit_seiten' => 0,
            'ab_ki_limit_euro' => '0', 'ab_ki_warnung_prozent' => 80, 'ab_ms_schriften_laden' => '1',
        ])->assertRedirect();
        $this->assertSame('1', settings('ab_ms_schriften_laden'));

        Http::fake(['downloads.sourceforge.net/*' => Http::response('falsch')]);
        $this->post(route('arbeitsblaetter.einstellungen.schriften'))->assertSessionHas('Meldung', fn ($m) => str_contains($m, 'Prüfsumme'));

        $this->actingAs($this->lehrkraft());
        $this->post(route('arbeitsblaetter.einstellungen.schriften'))->assertForbidden();
    }
}
