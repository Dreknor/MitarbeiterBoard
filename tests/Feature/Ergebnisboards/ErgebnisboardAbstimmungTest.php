<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\ErgebnisStimme;
use App\Models\ErgebnisVerlauf;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Abstimmung („Punkte kleben“): Einstellungen nur mit Schreibrecht, Punkte mit Leserecht,
 * Punkte-Limit, verdecktes Ergebnis, Zurücksetzen, Version (ErgebnisboardAbstimmungService).
 */
class ErgebnisboardAbstimmungTest extends TestCase
{
    use ErgebnisboardTestHelfer;

    private User $autor;

    private User $leser;

    private Ergebnisboard $board;

    private ErgebnisKarte $a;

    private ErgebnisKarte $b;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();

        $this->autor = $this->nutzer(['view result boards', 'create result boards']);
        $this->leser = $this->nutzer();
        $this->board = $this->board($this->autor, 'kanban');
        $this->zugriff($this->board, $this->leser, 'lesen');
        $this->a = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'ideen']);
        $this->b = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'ideen']);
    }

    private function starten(array $daten = []): void
    {
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.abstimmung', $this->board), ['status' => 'laeuft', 'punkte' => 2] + $daten)
            ->assertOk()->assertJsonPath('data.abstimmung.status', 'laeuft');
    }

    private function stimme(User $user, ErgebnisKarte $karte, int $richtung = 1)
    {
        return $this->actingAs($user)->postJson(route('ergebnisboards.karten.stimme', [$this->board, $karte]), ['richtung' => $richtung]);
    }

    public function test_nur_schreibrecht_darf_abstimmung_starten(): void
    {
        $this->actingAs($this->leser)->putJson(route('ergebnisboards.abstimmung', $this->board), ['status' => 'laeuft'])->assertForbidden();
        $this->starten();
        $this->assertTrue(ErgebnisVerlauf::where('aktion', 'abstimmung_gestartet')->exists());
    }

    public function test_punkte_mit_leserecht_bis_zum_limit_und_ohne_versionswechsel(): void
    {
        $this->stimme($this->leser, $this->a)->assertUnprocessable(); // läuft noch nicht
        $this->starten();
        $version = $this->board->fresh()->version;

        $this->stimme($this->leser, $this->a)->assertOk()->assertJsonPath('abstimmung.rest', 1);
        $this->stimme($this->leser, $this->a)->assertOk()
            ->assertJsonPath('abstimmung.rest', 0)
            ->assertJsonPath('abstimmung.meine.'.$this->a->id, 2)
            ->assertJsonPath('abstimmung.karten.'.$this->a->id, 2);
        $this->stimme($this->leser, $this->b)->assertUnprocessable();

        $this->stimme($this->leser, $this->a, -1)->assertOk()->assertJsonPath('abstimmung.rest', 1);
        $this->assertSame(1, (int) ErgebnisStimme::where('user_id', $this->leser->id)->value('anzahl'));
        $this->assertSame($version, $this->board->fresh()->version);

        $fremd = $this->nutzer();
        $this->stimme($fremd, $this->a)->assertForbidden();
    }

    public function test_verdeckt_zeigt_nur_eigene_punkte_bis_zum_ende(): void
    {
        $this->starten(['verdeckt' => true]);
        $this->stimme($this->leser, $this->a)->assertOk()
            ->assertJsonPath('abstimmung.sichtbar', false)
            ->assertJsonPath('abstimmung.karten', [])
            ->assertJsonPath('abstimmung.meine.'.$this->a->id, 1);
        $this->stimme($this->autor, $this->b)->assertOk();

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.abstimmung', $this->board), ['status' => 'beendet'])
            ->assertOk()
            ->assertJsonPath('data.abstimmung.sichtbar', true)
            ->assertJsonPath('data.abstimmung.teilnehmende', 2)
            ->assertJsonPath('data.abstimmung.karten.'.$this->a->id, 1);

        $this->stimme($this->leser, $this->b)->assertUnprocessable();
    }

    public function test_zuruecksetzen_und_geloeschte_karten_zaehlen_nicht(): void
    {
        $this->starten();
        $this->stimme($this->leser, $this->a)->assertOk();
        $this->stimme($this->leser, $this->b)->assertOk();

        $this->b->delete();
        $this->actingAs($this->leser)->getJson(route('ergebnisboards.daten', $this->board))
            ->assertJsonPath('data.abstimmung.rest', 1)
            ->assertJsonPath('data.abstimmung.karten', [$this->a->id => 1]);

        $this->actingAs($this->leser)->postJson(route('ergebnisboards.abstimmung.zuruecksetzen', $this->board))->assertForbidden();
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.abstimmung.zuruecksetzen', $this->board))->assertOk();
        $this->assertSame(0, ErgebnisStimme::where('board_id', $this->board->id)->count());
        $this->assertTrue(ErgebnisVerlauf::where('aktion', 'abstimmung_zurueckgesetzt')->exists());
    }

    public function test_archiviertes_board_nimmt_keine_punkte(): void
    {
        $this->starten();
        $this->board->update(['archiviert_at' => now()]);
        $this->stimme($this->leser, $this->a)->assertUnprocessable();
    }

    public function test_kopie_startet_ohne_abstimmung(): void
    {
        $this->starten();
        $this->actingAs($this->autor)->post(route('ergebnisboards.duplicate', $this->board))->assertRedirect();
        $kopie = Ergebnisboard::where('titel', $this->board->titel.' (Kopie)')->first();
        $this->assertSame('aus', $kopie->abstimmungsStand()['status']);
    }
}
