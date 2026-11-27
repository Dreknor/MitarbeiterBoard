<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\ErgebnisVorlage;
use App\Models\User;
use App\Support\ErgebnisboardVorlagen;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Vorlagen-Editor (ErgebnisVorlageController/ErgebnisVorlagenService) und „Board als Vorlage speichern“.
 */
class ErgebnisVorlagenEditorTest extends TestCase
{
    use ErgebnisboardTestHelfer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
    }

    private function verwalter(): User
    {
        return $this->nutzer(['view result boards', 'create result boards', 'manage result boards']);
    }

    private function definition(array $ueberschreiben = []): array
    {
        return array_merge([
            'titel' => 'Zielscheibe',
            'beschreibung' => 'Ziele nach Nähe zum Kern ordnen',
            'icon' => 'fas fa-bullseye',
            'aktiv' => true,
            'spalten' => '1fr 2fr',
            'bereiche_frei' => false,
            'auto_status' => false,
            'achsen' => ['y' => ['titel' => 'Nähe', 'oben' => 'fern', 'unten' => 'nah'], 'x' => null],
            'legende' => ['titel' => 'Legende', 'eintraege' => [['farbe' => 'gruen', 'text' => 'Kern: sofort'], ['farbe' => 'rot', 'text' => '']]],
            'bereiche' => [
                ['schluessel' => 'kern', 'titel' => 'Kern', 'farbe' => 'gruen', 'spalte' => 1, 'spalten' => 2, 'zeile' => 1, 'zeilen' => 1, 'raster' => 2],
                ['schluessel' => 'rand', 'titel' => 'Rand', 'farbe' => 'grau', 'spalte' => 1, 'spalten' => 1, 'zeile' => 2, 'zeilen' => 1, 'raster' => 1, 'status' => 'erledigt'],
                ['schluessel' => 'rand_detail', 'titel' => 'Detail', 'farbe' => 'blau', 'eltern' => 'rand'],
            ],
        ], $ueberschreiben);
    }

    private function vorlageAnlegen(array $attribute = []): ErgebnisVorlage
    {
        return ErgebnisVorlage::create(array_merge([
            'schluessel' => 'eigen_test',
            'titel' => 'Test-Vorlage',
            'definition' => ErgebnisboardVorlagen::normalisieren(ErgebnisboardVorlagen::VORLAGEN['swot']),
            'aktiv' => true,
        ], $attribute));
    }

    public function test_ohne_verwaltungsrecht_alles_verboten(): void
    {
        $user = $this->nutzer(['view result boards', 'create result boards']);
        $vorlage = $this->vorlageAnlegen();
        $board = $this->board($user, 'swot');

        $this->actingAs($user);
        $this->get(route('ergebnisboards.vorlagen.index'))->assertForbidden();
        $this->get(route('ergebnisboards.vorlagen.create'))->assertForbidden();
        $this->get(route('ergebnisboards.vorlagen.edit', $vorlage))->assertForbidden();
        $this->postJson(route('ergebnisboards.vorlagen.store'), $this->definition())->assertForbidden();
        $this->putJson(route('ergebnisboards.vorlagen.update', $vorlage), $this->definition())->assertForbidden();
        $this->delete(route('ergebnisboards.vorlagen.destroy', $vorlage))->assertForbidden();
        $this->post(route('ergebnisboards.als-vorlage', $board), ['titel' => 'X'])->assertForbidden();

        $this->assertSame(1, ErgebnisVorlage::count());
        $this->assertSame('Test-Vorlage', $vorlage->fresh()->titel);
    }

    public function test_seiten_werden_angezeigt(): void
    {
        $vorlage = $this->vorlageAnlegen(['aktiv' => false]);
        $this->actingAs($this->verwalter());

        $this->get(route('ergebnisboards.vorlagen.index'))->assertOk()
            ->assertSee('Test-Vorlage')->assertSee('inaktiv')
            ->assertSee('SWOT-Analyse')
            ->assertSee(route('ergebnisboards.vorlagen.create', ['basis' => 'swot']), false);
        $this->get(route('ergebnisboards.vorlagen.create', ['basis' => 'aufwand_ertrag']))->assertOk()
            ->assertSee('ergVorlagenEditor', false)->assertSee('Low Hanging Fruits');
        $this->get(route('ergebnisboards.vorlagen.edit', $vorlage))->assertOk()
            ->assertSee('Test-Vorlage')->assertSee(route('ergebnisboards.vorlagen.update', $vorlage), false);
    }

    public function test_link_vorlagen_verwalten_nur_mit_recht(): void
    {
        $this->actingAs($this->verwalter())->get(route('ergebnisboards.index'))
            ->assertOk()->assertSee('Vorlagen verwalten');
        $this->actingAs($this->nutzer(['view result boards', 'create result boards']))->get(route('ergebnisboards.index'))
            ->assertOk()->assertDontSee('Vorlagen verwalten');
    }

    public function test_board_seite_zeigt_als_vorlage_formular_nur_mit_recht(): void
    {
        $verwalter = $this->verwalter();
        $board = $this->board($verwalter, 'swot');
        $this->actingAs($verwalter)->get(route('ergebnisboards.show', $board))
            ->assertOk()->assertSee(route('ergebnisboards.als-vorlage', $board), false);

        $ersteller = $this->nutzer(['view result boards', 'create result boards']);
        $eigenes = $this->board($ersteller, 'swot');
        $this->actingAs($ersteller)->get(route('ergebnisboards.show', $eigenes))
            ->assertOk()->assertDontSee(route('ergebnisboards.als-vorlage', $eigenes), false);
    }

    public function test_store_legt_vorlage_an_die_fuer_neue_boards_genutzt_wird(): void
    {
        $verwalter = $this->verwalter();

        $antwort = $this->actingAs($verwalter)->postJson(route('ergebnisboards.vorlagen.store'), $this->definition())
            ->assertCreated()
            ->assertJsonPath('redirect', route('ergebnisboards.vorlagen.index'));

        $vorlage = ErgebnisVorlage::findOrFail($antwort->json('id'));
        $this->assertSame('eigen_zielscheibe', $vorlage->schluessel);
        $this->assertSame($verwalter->id, (int) $vorlage->created_by);
        $this->assertTrue($vorlage->aktiv);
        $this->assertSame('1fr 2fr', $vorlage->definition['spalten']);
        $this->assertSame(['y' => ['titel' => 'Nähe', 'oben' => 'fern', 'unten' => 'nah']], $vorlage->definition['achsen']);
        $this->assertCount(1, $vorlage->definition['legende']['eintraege'], 'leere Legendeneinträge fallen weg');

        $alle = ErgebnisboardVorlagen::alle();
        $this->assertArrayHasKey('eigen_zielscheibe', $alle);
        $this->assertTrue($alle['eigen_zielscheibe']['eigene']);
        $this->assertSame('Zielscheibe', $alle['eigen_zielscheibe']['titel']);

        $this->actingAs($verwalter)->post(route('ergebnisboards.store'), ['titel' => 'Klausurtag', 'vorlage' => 'eigen_zielscheibe'])
            ->assertRedirect();
        $board = Ergebnisboard::where('titel', 'Klausurtag')->firstOrFail();
        $this->assertSame('eigen_zielscheibe', $board->vorlage);
        $this->assertSame('1fr 2fr', $board->spalten);
        $this->assertSame(['kern', 'rand', 'rand_detail'], $board->bereichsSchluessel());
        $this->assertSame('rand', collect($board->bereiche)->firstWhere('schluessel', 'rand_detail')['eltern']);
        $this->assertSame('erledigt', collect($board->bereiche)->firstWhere('schluessel', 'rand')['status']);
    }

    public function test_update_aendert_vorlage(): void
    {
        $vorlage = $this->vorlageAnlegen();

        $this->actingAs($this->verwalter())
            ->putJson(route('ergebnisboards.vorlagen.update', $vorlage), $this->definition(['titel' => 'Neu', 'aktiv' => false]))
            ->assertOk()->assertJsonPath('id', $vorlage->id);

        $vorlage->refresh();
        $this->assertSame('Neu', $vorlage->titel);
        $this->assertSame('eigen_test', $vorlage->schluessel, 'Schlüssel bleibt beim Umbenennen');
        $this->assertFalse($vorlage->aktiv);
        $this->assertSame('1fr 2fr', $vorlage->definition['spalten']);
    }

    public function test_ungueltige_spalten_werden_abgelehnt(): void
    {
        $this->actingAs($this->verwalter())
            ->postJson(route('ergebnisboards.vorlagen.store'), $this->definition(['spalten' => '1fr; color:red']))
            ->assertStatus(422)->assertJsonValidationErrors('spalten');
        $this->assertSame(0, ErgebnisVorlage::count());
    }

    public function test_feld_ausserhalb_der_spalten_wird_abgelehnt(): void
    {
        $definition = $this->definition();
        $definition['bereiche'][0]['spalte'] = 2;   // 2 + 2 - 1 = 3 > 2 Spalten

        $this->actingAs($this->verwalter())
            ->postJson(route('ergebnisboards.vorlagen.store'), $definition)
            ->assertStatus(422)->assertJsonValidationErrors('bereiche');
        $this->assertSame(0, ErgebnisVorlage::count());
    }

    public function test_unterbereich_ohne_gueltiges_hauptfeld_wird_abgelehnt(): void
    {
        $definition = $this->definition();
        $definition['bereiche'][2]['eltern'] = 'gibtsnicht';

        $this->actingAs($this->verwalter())
            ->postJson(route('ergebnisboards.vorlagen.store'), $definition)
            ->assertStatus(422)->assertJsonValidationErrors('bereiche');

        // Unterbereich eines Unterbereichs ist ebenfalls kein Hauptfeld
        $definition['bereiche'][] = ['schluessel' => 'tiefer', 'titel' => 'Tiefer', 'eltern' => 'rand_detail'];
        $definition['bereiche'][2]['eltern'] = 'rand';
        $this->actingAs($this->verwalter())
            ->postJson(route('ergebnisboards.vorlagen.store'), $definition)
            ->assertStatus(422)->assertJsonValidationErrors('bereiche');

        $this->assertSame(0, ErgebnisVorlage::count());
    }

    public function test_inaktive_vorlage_ist_nicht_waehlbar(): void
    {
        $verwalter = $this->verwalter();
        $this->actingAs($verwalter)
            ->postJson(route('ergebnisboards.vorlagen.store'), $this->definition(['aktiv' => false]))
            ->assertCreated();

        $this->assertArrayNotHasKey('eigen_zielscheibe', ErgebnisboardVorlagen::alle());
        $this->assertArrayHasKey('eigen_zielscheibe', ErgebnisboardVorlagen::alle(false));

        $this->actingAs($verwalter)
            ->postJson(route('ergebnisboards.store'), ['titel' => 'X', 'vorlage' => 'eigen_zielscheibe'])
            ->assertStatus(422)->assertJsonValidationErrors('vorlage');
        $this->assertSame(0, Ergebnisboard::count());
    }

    public function test_loeschen_laesst_bestehende_boards_unveraendert(): void
    {
        $verwalter = $this->verwalter();
        $this->actingAs($verwalter)->postJson(route('ergebnisboards.vorlagen.store'), $this->definition())->assertCreated();
        $this->actingAs($verwalter)->post(route('ergebnisboards.store'), ['titel' => 'Bestand', 'vorlage' => 'eigen_zielscheibe']);
        $board = Ergebnisboard::where('titel', 'Bestand')->firstOrFail();
        $vorher = [$board->spalten, $board->bereiche, $board->achsen, $board->legende];

        $vorlage = ErgebnisVorlage::firstOrFail();
        $this->actingAs($verwalter)->delete(route('ergebnisboards.vorlagen.destroy', $vorlage))
            ->assertRedirect(route('ergebnisboards.vorlagen.index'));

        $this->assertNull(ErgebnisVorlage::find($vorlage->id));
        $board->refresh();
        $this->assertSame($vorher, [$board->spalten, $board->bereiche, $board->achsen, $board->legende]);
        $this->actingAs($verwalter)->get(route('ergebnisboards.show', $board))->assertOk();
    }

    public function test_als_vorlage_uebernimmt_raster_ohne_karten(): void
    {
        $verwalter = $this->verwalter();
        $board = $this->board($verwalter, 'aufwand_ertrag');
        ErgebnisKarte::factory()->create(['board_id' => $board->id, 'bereich' => 'quick_wins', 'titel' => 'Karte bleibt im Board']);
        $kartenVorher = ErgebnisKarte::count();

        $antwort = $this->actingAs($verwalter)->post(route('ergebnisboards.als-vorlage', $board), [
            'titel' => 'Matrix-Kopie',
            'beschreibung' => 'aus dem Board',
        ]);

        $vorlage = ErgebnisVorlage::where('titel', 'Matrix-Kopie')->firstOrFail();
        $antwort->assertRedirect(route('ergebnisboards.vorlagen.edit', $vorlage));
        $this->assertSame('aus dem Board', $vorlage->beschreibung);
        $this->assertSame($board->spalten, $vorlage->definition['spalten']);
        $this->assertSame($board->bereichsSchluessel(), array_column($vorlage->definition['bereiche'], 'schluessel'));
        $this->assertSame($board->achsen, $vorlage->definition['achsen']);
        $this->assertSame($board->legende['eintraege'], $vorlage->definition['legende']['eintraege']);
        $this->assertSame('fas fa-th-large', $vorlage->definition['icon']);
        $this->assertStringNotContainsString('Karte bleibt im Board', json_encode($vorlage->definition));
        $this->assertSame($kartenVorher, ErgebnisKarte::count());

        // Titel ist Pflicht
        $this->actingAs($verwalter)->post(route('ergebnisboards.als-vorlage', $board), ['titel' => ''])
            ->assertSessionHasErrors('titel');
    }
}
