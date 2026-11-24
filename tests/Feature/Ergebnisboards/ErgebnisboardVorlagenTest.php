<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\ErgebnisKarte;
use App\Models\ErgebnisVorlage;
use App\Support\ErgebnisboardVorlagen;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Registry der Vorlagen (App\Support\ErgebnisboardVorlagen), Vite-Entries und PDF-Ausgabe.
 */
class ErgebnisboardVorlagenTest extends TestCase
{
    use ErgebnisboardTestHelfer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
    }

    public function test_jede_vorlage_hat_ein_gueltiges_raster(): void
    {
        $vorlagen = ErgebnisboardVorlagen::alle();
        $this->assertNotEmpty($vorlagen);

        foreach ($vorlagen as $schluessel => $vorlage) {
            $this->assertNotSame('', trim($vorlage['titel']), "{$schluessel}: Titel fehlt");
            $this->assertTrue(ErgebnisboardVorlagen::spaltenGueltig($vorlage['spalten']), "{$schluessel}: ungültiges Grid-Template „{$vorlage['spalten']}“");
            $this->assertNotEmpty($vorlage['bereiche'], "{$schluessel}: keine Bereiche");

            $spalten = ErgebnisboardVorlagen::spaltenAnzahl($vorlage['spalten']);
            $schluesselListe = array_column($vorlage['bereiche'], 'schluessel');
            $this->assertSame(count($schluesselListe), count(array_unique($schluesselListe)), "{$schluessel}: Bereichsschlüssel doppelt");

            $hauptfelder = [];
            foreach ($vorlage['bereiche'] as $b) {
                $name = "{$schluessel}.{$b['schluessel']}";
                $this->assertMatchesRegularExpression('/^[a-z0-9_]{1,64}$/', $b['schluessel'], "{$name}: ungültiger Schlüssel");
                $this->assertArrayHasKey($b['farbe'], ErgebnisboardVorlagen::FARBEN, "{$name}: unbekannte Farbe");
                $this->assertContains($b['raster'], [1, 2], "{$name}: raster");
                if ($b['status'] !== null) {
                    $this->assertContains($b['status'], ErgebnisboardVorlagen::STATUS, "{$name}: status");
                }
                if ($b['eltern'] === null) {
                    $hauptfelder[] = $b['schluessel'];
                    $this->assertGreaterThanOrEqual(1, $b['spalte'], "{$name}: spalte");
                    $this->assertLessThanOrEqual($spalten, $b['spalte'] + $b['spalten'] - 1, "{$name}: ragt über {$spalten} Spalten hinaus");
                    $this->assertGreaterThanOrEqual(1, $b['zeile'], "{$name}: zeile");
                }
            }
            $this->assertNotEmpty($hauptfelder, "{$schluessel}: kein Hauptfeld");

            foreach ($vorlage['bereiche'] as $b) {
                if ($b['eltern'] !== null) {
                    $this->assertContains($b['eltern'], $hauptfelder, "{$schluessel}.{$b['schluessel']}: Unterbereich ohne Hauptfeld");
                }
            }
        }
    }

    public function test_rohe_vorlagen_nutzen_nur_bekannte_farben_und_status(): void
    {
        // normalisieren() würde Fehler stillschweigend auf „grau“/null setzen – daher die Rohdaten prüfen
        foreach (ErgebnisboardVorlagen::VORLAGEN as $schluessel => $vorlage) {
            foreach ($vorlage['bereiche'] as $b) {
                $this->assertArrayHasKey($b['farbe'], ErgebnisboardVorlagen::FARBEN, "{$schluessel}.{$b['schluessel']}");
                if (isset($b['status'])) {
                    $this->assertContains($b['status'], ErgebnisboardVorlagen::STATUS, "{$schluessel}.{$b['schluessel']}");
                }
            }
            foreach ($vorlage['legende']['eintraege'] ?? [] as $e) {
                $this->assertArrayHasKey($e['farbe'], ErgebnisboardVorlagen::FARBEN, "{$schluessel}: Legende");
            }
        }
    }

    public function test_auto_status_vorlagen_haben_alle_status_spalten(): void
    {
        foreach (ErgebnisboardVorlagen::alle() as $schluessel => $vorlage) {
            $status = array_filter(array_column($vorlage['bereiche'], 'status'));
            if ($vorlage['auto_status']) {
                $this->assertEqualsCanonicalizing(ErgebnisboardVorlagen::STATUS, array_values(array_unique($status)), "{$schluessel}: auto_status braucht offen/in_arbeit/erledigt");
            }
        }
    }

    public function test_spalten_gueltig_und_anzahl(): void
    {
        $this->assertTrue(ErgebnisboardVorlagen::spaltenGueltig('1fr 1fr'));
        $this->assertTrue(ErgebnisboardVorlagen::spaltenGueltig('5fr 7fr 3fr'));
        $this->assertTrue(ErgebnisboardVorlagen::spaltenGueltig('200px 50% 1.5fr'));
        $this->assertFalse(ErgebnisboardVorlagen::spaltenGueltig('1fr; background:red'));
        $this->assertFalse(ErgebnisboardVorlagen::spaltenGueltig('repeat(3, 1fr)'));
        $this->assertFalse(ErgebnisboardVorlagen::spaltenGueltig(''));
        $this->assertFalse(ErgebnisboardVorlagen::spaltenGueltig(trim(str_repeat('1fr ', 13))));

        $this->assertSame(3, ErgebnisboardVorlagen::spaltenAnzahl('5fr 7fr 3fr'));
        $this->assertSame(2, ErgebnisboardVorlagen::spaltenAnzahl('  1fr   1fr '));
    }

    public function test_eigene_vorlage_ergaenzt_aber_ueberschreibt_keine_eingebaute(): void
    {
        ErgebnisVorlage::create([
            'schluessel' => 'swot',
            'titel' => 'Gekapert',
            'definition' => ['spalten' => '1fr', 'bereiche' => [['schluessel' => 'x', 'titel' => 'X']]],
        ]);
        ErgebnisVorlage::create([
            'schluessel' => 'fuenf_finger',
            'titel' => 'Fünf-Finger-Feedback',
            'definition' => ['spalten' => '1fr 1fr', 'bereiche' => [['schluessel' => 'daumen', 'titel' => 'Daumen', 'farbe' => 'unbekannt']]],
        ]);
        ErgebnisVorlage::create([
            'schluessel' => 'inaktiv',
            'titel' => 'Inaktiv',
            'aktiv' => false,
            'definition' => ['bereiche' => [['schluessel' => 'a', 'titel' => 'A']]],
        ]);

        $alle = ErgebnisboardVorlagen::alle();
        $this->assertSame('SWOT-Analyse', $alle['swot']['titel']);
        $this->assertTrue($alle['fuenf_finger']['eigene']);
        $this->assertSame('grau', $alle['fuenf_finger']['bereiche'][0]['farbe']);
        $this->assertArrayNotHasKey('inaktiv', $alle);
        $this->assertNotNull(ErgebnisboardVorlagen::finden('inaktiv'));
    }

    public function test_vite_entries_sind_eingetragen(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString('resources/css/ergebnisboards.css', $vite);
        $this->assertStringContainsString('resources/js/ergebnisboards.js', $vite);
    }

    public function test_pdf_wird_ausgeliefert(): void
    {
        $user = $this->nutzer();
        $board = $this->board($user, 'aufwand_ertrag', ['titel' => 'Pädagogischer Tag 2026']);
        ErgebnisKarte::factory()->create(['board_id' => $board->id, 'bereich' => 'quick_wins', 'titel' => 'Hausaufgabenheft vereinheitlichen']);

        $antwort = $this->actingAs($user)->get(route('ergebnisboards.pdf', $board))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $antwort->getContent());
        $this->assertStringContainsString('paedagogischer-tag-2026.pdf', $antwort->headers->get('Content-Disposition'));
    }

    public function test_pdf_fuer_jede_vorlage(): void
    {
        $user = $this->nutzer();

        foreach (array_keys(ErgebnisboardVorlagen::VORLAGEN) as $vorlage) {
            $board = $this->board($user, $vorlage);
            ErgebnisKarte::factory()->create(['board_id' => $board->id]);

            $this->actingAs($user)->get(route('ergebnisboards.pdf', $board))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }
}
