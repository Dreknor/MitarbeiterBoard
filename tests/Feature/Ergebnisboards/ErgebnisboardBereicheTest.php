<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Felder (Bereiche) eines Boards: umbenennen, einfärben, hinzufügen, entfernen, umsortieren
 * (PUT ergebnisboards/{board}/bereiche → ErgebnisboardService::bereicheSpeichern()).
 */
class ErgebnisboardBereicheTest extends TestCase
{
    use ErgebnisboardTestHelfer;

    private User $autor;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
        $this->autor = $this->nutzer(['view result boards', 'create result boards']);
    }

    private function speichern(Ergebnisboard $board, array $bereiche, ?int $version = null)
    {
        return $this->actingAs($this->autor)->putJson(route('ergebnisboards.bereiche', $board), array_filter([
            'bereiche' => $bereiche,
            'version' => $version,
        ], fn ($v) => $v !== null));
    }

    /** Bereiche des Boards in der Form, wie die Oberfläche sie schickt. */
    private function liste(Ergebnisboard $board): array
    {
        return array_map(fn ($b) => array_intersect_key($b, array_flip(['schluessel', 'titel', 'hinweis', 'badge', 'farbe', 'raster', 'eltern', 'status'])), $board->fresh()->bereiche);
    }

    public function test_bereiche_umbenennen_und_einfaerben(): void
    {
        $board = $this->board($this->autor, 'swot');
        $liste = $this->liste($board);
        $liste[0]['titel'] = 'Unsere Stärken';
        $liste[0]['farbe'] = 'tuerkis';
        $liste[1]['hinweis'] = 'Was fehlt uns?';

        $this->speichern($board, $liste, 1)
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.bereiche.0.titel', 'Unsere Stärken')
            ->assertJsonPath('data.bereiche.0.farbe', 'tuerkis');

        $board->refresh();
        $this->assertSame('Unsere Stärken', $board->bereich('staerken')['titel']);
        $this->assertSame('Was fehlt uns?', $board->bereich('schwaechen')['hinweis']);
        // Grid-Position der festen Vorlage bleibt
        $this->assertSame(2, $board->bereich('risiken')['spalte']);
        $this->assertSame(2, $board->bereich('risiken')['zeile']);
        $this->assertSame('1fr 1fr', $board->spalten);
    }

    public function test_ungueltige_farbe_wird_abgelehnt(): void
    {
        $board = $this->board($this->autor, 'swot');
        $liste = $this->liste($board);
        $liste[0]['farbe'] = 'pink';

        $this->speichern($board, $liste)->assertStatus(422)->assertJsonValidationErrors('bereiche.0.farbe');
    }

    public function test_freie_vorlage_bereich_hinzufuegen_passt_spalten_an(): void
    {
        $board = $this->board($this->autor, 'kanban');
        $liste = $this->liste($board);
        $liste[] = ['titel' => 'Review', 'farbe' => 'orange', 'status' => 'in_arbeit'];

        $this->speichern($board, $liste)->assertOk();

        $board->refresh();
        $this->assertSame(['ideen', 'geplant', 'in_arbeit', 'erledigt', 'review'], $board->bereichsSchluessel());
        $this->assertSame('1fr 1fr 1fr 1fr 1fr', $board->spalten);
        $this->assertSame(5, $board->bereich('review')['spalte']);
        $this->assertSame('orange', $board->bereich('review')['farbe']);
        $this->assertSame('in_arbeit', $board->bereich('review')['status']);
    }

    public function test_neuer_schluessel_kollidiert_nicht_mit_vorhandenem(): void
    {
        $board = $this->board($this->autor, 'kanban');
        $liste = $this->liste($board);
        $liste[] = ['titel' => 'Ideen'];

        $this->speichern($board, $liste)->assertOk();

        $this->assertSame(['ideen', 'geplant', 'in_arbeit', 'erledigt', 'ideen_2'], $board->fresh()->bereichsSchluessel());
    }

    public function test_freie_vorlage_bereiche_umsortieren(): void
    {
        $board = $this->board($this->autor, 'frei');
        $karte = ErgebnisKarte::factory()->create(['board_id' => $board->id, 'bereich' => 'spalte_1']);

        $this->speichern($board, array_reverse($this->liste($board)))->assertOk();

        $board->refresh();
        $this->assertSame(['spalte_3', 'spalte_2', 'spalte_1'], $board->bereichsSchluessel());
        $this->assertSame([1, 2, 3], array_column($board->bereiche, 'spalte'));
        $this->assertSame('1fr 1fr 1fr', $board->spalten);
        $this->assertSame('spalte_1', $karte->fresh()->bereich);
    }

    public function test_leeren_bereich_bei_freier_vorlage_entfernen(): void
    {
        $board = $this->board($this->autor, 'frei');
        $liste = array_values(array_filter($this->liste($board), fn ($b) => $b['schluessel'] !== 'spalte_2'));

        $this->speichern($board, $liste)->assertOk();

        $board->refresh();
        $this->assertSame(['spalte_1', 'spalte_3'], $board->bereichsSchluessel());
        $this->assertSame('1fr 1fr', $board->spalten);
    }

    public function test_bereich_mit_karten_kann_nicht_entfernt_werden(): void
    {
        $board = $this->board($this->autor, 'kanban');
        ErgebnisKarte::factory()->create(['board_id' => $board->id, 'bereich' => 'geplant']);
        $liste = array_values(array_filter($this->liste($board), fn ($b) => $b['schluessel'] !== 'geplant'));

        $this->speichern($board, $liste)->assertStatus(422)->assertJsonValidationErrors('bereiche');

        $this->assertContains('geplant', $board->fresh()->bereichsSchluessel());
        $this->assertSame(1, $board->fresh()->version);
    }

    public function test_feste_vorlage_kein_neues_hauptfeld(): void
    {
        $board = $this->board($this->autor, 'swot');
        $liste = $this->liste($board);
        $liste[] = ['titel' => 'Fünftes Feld'];

        $this->speichern($board, $liste)->assertStatus(422)->assertJsonValidationErrors('bereiche');
        $this->assertCount(4, $board->fresh()->bereiche);
    }

    public function test_feste_vorlage_hauptfeld_nicht_entfernbar(): void
    {
        $board = $this->board($this->autor, 'swot');
        $liste = array_values(array_filter($this->liste($board), fn ($b) => $b['schluessel'] !== 'risiken'));

        $this->speichern($board, $liste)->assertStatus(422)->assertJsonValidationErrors('bereiche');
        $this->assertContains('risiken', $board->fresh()->bereichsSchluessel());
    }

    public function test_feste_vorlage_unterbereich_anlegen_und_wieder_entfernen(): void
    {
        $board = $this->board($this->autor, 'swot');
        $liste = $this->liste($board);
        $liste[] = ['titel' => 'Personal', 'eltern' => 'staerken'];

        $this->speichern($board, $liste)->assertOk();

        $board->refresh();
        $unter = $board->bereich('personal');
        $this->assertNotNull($unter);
        $this->assertSame('staerken', $unter['eltern']);
        $this->assertCount(5, $board->bereiche);

        // Karte im Unterbereich anlegen geht
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $board), ['bereich' => 'personal', 'titel' => 'Engagiertes Team'])
            ->assertCreated();

        // Unterbereich mit Karte kann nicht entfernt werden, leer schon
        $ohneUnter = array_values(array_filter($this->liste($board), fn ($b) => $b['schluessel'] !== 'personal'));
        $this->speichern($board, $ohneUnter)->assertStatus(422);

        ErgebnisKarte::query()->delete();
        $this->speichern($board, $ohneUnter)->assertOk();
        $this->assertNull($board->fresh()->bereich('personal'));
    }

    public function test_unterbereich_braucht_gueltiges_hauptfeld(): void
    {
        $board = $this->board($this->autor, 'swot');

        $liste = $this->liste($board);
        $liste[] = ['titel' => 'Ohne Eltern', 'eltern' => 'gibtsnicht'];
        $this->speichern($board, $liste)->assertStatus(422);

        // Unterbereich eines Unterbereichs ist nicht erlaubt
        $liste = $this->liste($board);
        $liste[] = ['titel' => 'Ebene 1', 'eltern' => 'staerken'];
        $this->speichern($board, $liste)->assertOk();
        $liste = $this->liste($board);
        $liste[] = ['titel' => 'Ebene 2', 'eltern' => 'ebene_1'];
        $this->speichern($board, $liste)->assertStatus(422);
    }

    public function test_feste_vorlage_status_bleibt_unveraendert(): void
    {
        $board = $this->board($this->autor, 'umsetzungsstand');
        $liste = $this->liste($board);
        $liste[0]['status'] = 'erledigt';

        $this->speichern($board, $liste)->assertOk();
        $this->assertSame('offen', $board->fresh()->bereich('offen')['status']);
    }

    public function test_bereiche_versionskonflikt(): void
    {
        $board = $this->board($this->autor, 'kanban', ['version' => 3]);
        $liste = $this->liste($board);
        $liste[0]['titel'] = 'Neu';

        $this->speichern($board, $liste, 2)->assertStatus(409)->assertJsonPath('data.bereiche.0.titel', 'Ideen');
    }
}
