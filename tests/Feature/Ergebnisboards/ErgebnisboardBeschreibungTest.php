<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\ErgebnisVerlauf;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Kartenbeschreibung (Rich-Text, bereinigt) und Checkliste.
 */
class ErgebnisboardBeschreibungTest extends TestCase
{
    use ErgebnisboardTestHelfer;

    private User $autor;

    private Ergebnisboard $board;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();

        $this->autor = $this->nutzer(['view result boards', 'create result boards']);
        $this->board = $this->board($this->autor, 'kanban');
    }

    public function test_beschreibung_wird_bereinigt_und_checkliste_normalisiert(): void
    {
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $this->board), [
            'bereich' => 'ideen',
            'titel' => 'Lesezeit',
            'beschreibung' => '<p>Beschluss: <strong>täglich</strong> 20 Min.</p><script>alert(1)</script><p onclick="x()">Leseraum</p>',
            'checkliste' => [
                ['text' => 'Raum klären', 'erledigt' => true],
                ['text' => '   '],
                ['id' => 'abc123', 'text' => 'Plan erstellen'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.karten.0.beschreibung', '<p>Beschluss: <strong>täglich</strong> 20 Min.</p><p>Leseraum</p>')
            ->assertJsonPath('data.karten.0.beschreibung_text', "Beschluss: täglich 20 Min.\nLeseraum")
            ->assertJsonCount(2, 'data.karten.0.checkliste')
            ->assertJsonPath('data.karten.0.checkliste.0.erledigt', true)
            ->assertJsonPath('data.karten.0.checkliste.1.id', 'abc123')
            ->assertJsonPath('data.karten.0.checkliste.1.erledigt', false);

        $this->assertNotEmpty(ErgebnisKarte::first()->checkliste[0]['id']);
    }

    public function test_leere_beschreibung_wird_null_und_haken_werden_protokolliert(): void
    {
        $karte = ErgebnisKarte::factory()->create([
            'board_id' => $this->board->id, 'bereich' => 'ideen',
            'checkliste' => [['id' => 'a1', 'text' => 'Eins', 'erledigt' => false]],
        ]);

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), [
            'beschreibung' => '<p>&nbsp;</p>',
            'checkliste' => [['id' => 'a1', 'text' => 'Eins', 'erledigt' => true]],
        ])->assertOk()
            ->assertJsonPath('data.karten.0.beschreibung', null)
            ->assertJsonPath('data.karten.0.checkliste.0.erledigt', true);

        $eintrag = ErgebnisVerlauf::where('aktion', 'karte_geaendert')->first();
        $this->assertSame(['0/1 erledigt (Eins)', '1/1 erledigt (Eins)'], $eintrag->details['aenderungen']['checkliste']);
    }

    public function test_nur_mit_schreibrecht(): void
    {
        $leser = $this->nutzer();
        $this->zugriff($this->board, $leser, 'lesen');
        $karte = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'ideen']);

        $this->actingAs($leser)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), [
            'checkliste' => [['text' => 'x', 'erledigt' => true]],
        ])->assertForbidden();
    }
}
