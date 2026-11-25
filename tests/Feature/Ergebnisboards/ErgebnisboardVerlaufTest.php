<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\ErgebnisKommentar;
use App\Models\ErgebnisVerlauf;
use App\Models\User;
use App\Services\Ergebnisboards\ErgebnisboardService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Änderungsverlauf, Soft-Delete und Wiederherstellen von Karten, endgültiges Löschen nach 90 Tagen
 * (ErgebnisboardVerlaufService + ErgebnisboardService).
 */
class ErgebnisboardVerlaufTest extends TestCase
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

    private function aktionen(): array
    {
        return ErgebnisVerlauf::where('board_id', $this->board->id)->orderBy('id')->pluck('aktion')->all();
    }

    public function test_karte_anlegen_aendern_verschieben_wird_protokolliert(): void
    {
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $this->board), [
            'bereich' => 'ideen', 'titel' => 'Lesezeit',
        ])->assertCreated();
        $karte = ErgebnisKarte::first();

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), [
            'titel' => 'Betreute Lesezeit', 'bereich' => 'geplant', 'faellig_am' => '2026-12-01',
        ])->assertOk();

        $this->assertSame(['karte_angelegt', 'karte_geaendert', 'karte_verschoben'], $this->aktionen());

        $geaendert = ErgebnisVerlauf::where('aktion', 'karte_geaendert')->first();
        $this->assertSame(['Lesezeit', 'Betreute Lesezeit'], $geaendert->details['aenderungen']['titel']);
        $this->assertSame([null, '2026-12-01'], $geaendert->details['aenderungen']['faellig_am']);
        $this->assertSame($this->autor->id, $geaendert->user_id);

        $this->actingAs($this->autor)->getJson(route('ergebnisboards.verlauf', [$this->board, 'karte' => $karte->id]))
            ->assertOk()
            ->assertJsonCount(3, 'eintraege')
            ->assertJsonPath('eintraege.0.text', 'hat die Karte „Betreute Lesezeit“ von „Ideen“ nach „Geplant“ verschoben')
            ->assertJsonPath('eintraege.1.zeilen', ['Titel: „Lesezeit“ → „Betreute Lesezeit“', 'Fälligkeit: (leer) → 01.12.2026']);
    }

    public function test_umsortieren_im_selben_feld_wird_nicht_protokolliert(): void
    {
        $a = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'ideen']);
        $b = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'ideen']);

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.sortierung', $this->board), [
            'ordnung' => ['ideen' => [$b->id, $a->id]],
        ])->assertOk();
        $this->assertSame([], $this->aktionen());

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.sortierung', $this->board), [
            'ordnung' => ['erledigt' => [$a->id], 'ideen' => [$b->id]],
        ])->assertOk();
        $this->assertSame(['karte_verschoben'], $this->aktionen());
    }

    public function test_zustaendige_kopf_felder_und_zugriffe_werden_protokolliert(): void
    {
        $person = $this->nutzer();
        $person->update(['name' => 'Paula Person']);
        $karte = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'ideen']);

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.zugriffe', $this->board), [
            'zugriffe' => [['typ' => 'user', 'id' => $person->id, 'recht' => 'schreiben']],
        ])->assertOk();
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), [
            'zustaendige' => [$person->id],
        ])->assertOk();
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.update', $this->board), ['titel' => 'Neuer Titel'])->assertOk();

        $bereiche = $this->board->fresh()->bereiche;
        $bereiche[0]['titel'] = 'Sammlung';
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.bereiche', $this->board), ['bereiche' => $bereiche])->assertOk();

        $this->assertSame(['zugriffe_geaendert', 'zustaendige_geaendert', 'board_geaendert', 'felder_geaendert'], $this->aktionen());
        $this->assertSame(['Person Paula Person: schreiben'], ErgebnisVerlauf::where('aktion', 'zugriffe_geaendert')->first()->details['hinzugefuegt']);
        $this->assertSame(['Paula Person'], ErgebnisVerlauf::where('aktion', 'zustaendige_geaendert')->first()->details['hinzugefuegt']);
        $this->assertSame([['Ideen', 'Sammlung']], ErgebnisVerlauf::where('aktion', 'felder_geaendert')->first()->details['umbenannt']);

        $this->actingAs($this->autor)->getJson(route('ergebnisboards.verlauf', [$this->board, 'filter' => 'board']))
            ->assertOk()->assertJsonCount(3, 'eintraege')
            ->assertJsonPath('eintraege.1.text', 'hat Titel geändert');
    }

    public function test_geloeschte_karte_bleibt_wiederherstellbar_mit_kommentaren(): void
    {
        $karte = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'geplant', 'titel' => 'Weg']);
        ErgebnisKommentar::create(['board_id' => $this->board->id, 'karte_id' => $karte->id, 'user_id' => $this->autor->id, 'text' => 'Notiz']);

        $this->actingAs($this->autor)->deleteJson(route('ergebnisboards.karten.destroy', [$this->board, $karte]))
            ->assertOk()->assertJsonCount(0, 'data.karten');
        $this->assertSoftDeleted($karte);

        $this->actingAs($this->autor)->getJson(route('ergebnisboards.verlauf', $this->board))
            ->assertJsonPath('eintraege.0.aktion', 'karte_geloescht')
            ->assertJsonPath('eintraege.0.wiederherstellbar', true)
            ->assertJsonPath('darf_wiederherstellen', true);

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.wiederherstellen', [$this->board, $karte->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data.karten')
            ->assertJsonPath('data.karten.0.bereich', 'geplant')
            ->assertJsonPath('data.karten.0.kommentare', 1);

        $this->assertSame(['karte_geloescht', 'karte_wiederhergestellt'], $this->aktionen());
    }

    public function test_wiederherstellen_ins_erste_feld_wenn_feld_fehlt_und_nur_mit_schreibrecht(): void
    {
        $leser = $this->nutzer();
        $this->zugriff($this->board, $leser, 'lesen');
        $karte = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'geplant']);
        $karte->delete();

        $this->actingAs($leser)->postJson(route('ergebnisboards.karten.wiederherstellen', [$this->board, $karte->id]))->assertForbidden();
        $this->actingAs($leser)->getJson(route('ergebnisboards.verlauf', $this->board))->assertOk()->assertJsonPath('darf_wiederherstellen', false);

        // Feld „Geplant“ entfernen (freies Board, Feld ist leer)
        $bereiche = array_values(array_filter($this->board->fresh()->bereiche, fn ($b) => $b['schluessel'] !== 'geplant'));
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.bereiche', $this->board), ['bereiche' => $bereiche])->assertOk();

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.wiederherstellen', [$this->board, $karte->id]))
            ->assertOk()->assertJsonPath('data.karten.0.bereich', 'ideen');
        $this->assertTrue(ErgebnisVerlauf::where('aktion', 'karte_wiederhergestellt')->first()->details['ersatzfeld']);

        // Nicht gelöschte Karte kann nicht „wiederhergestellt“ werden
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.wiederherstellen', [$this->board, $karte->id]))->assertNotFound();
    }

    public function test_bereinigen_loescht_nach_90_tagen_endgueltig_verlauf_bleibt(): void
    {
        $alt = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'titel' => 'Alt']);
        $neu = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'titel' => 'Neu']);
        ErgebnisKommentar::create(['board_id' => $this->board->id, 'karte_id' => $alt->id, 'user_id' => $this->autor->id, 'text' => 'x']);
        $this->actingAs($this->autor)->deleteJson(route('ergebnisboards.karten.destroy', [$this->board, $alt]))->assertOk();
        $this->actingAs($this->autor)->deleteJson(route('ergebnisboards.karten.destroy', [$this->board, $neu]))->assertOk();
        ErgebnisKarte::withTrashed()->whereKey($alt->id)->update(['deleted_at' => now()->subDays(91)]);

        $this->assertSame(1, app(ErgebnisboardService::class)->geloeschteKartenBereinigen());

        $this->assertNull(ErgebnisKarte::withTrashed()->find($alt->id));
        $this->assertSoftDeleted($neu);
        $this->assertSame(0, ErgebnisKommentar::withTrashed()->where('karte_id', $alt->id)->count());
        $this->assertTrue(ErgebnisVerlauf::where('karte_id', $alt->id)->where('aktion', 'karte_geloescht')->exists());

        $this->actingAs($this->autor)->getJson(route('ergebnisboards.verlauf', $this->board))
            ->assertJsonPath('eintraege.1.text', 'hat die Karte „Alt“ aus „Ideen“ gelöscht')
            ->assertJsonPath('eintraege.1.wiederherstellbar', false);
    }

    public function test_angelegt_dupliziert_archiviert_und_seitenweise(): void
    {
        $board = app(ErgebnisboardService::class)->anlegen(['titel' => 'Neu', 'vorlage' => 'swot'], $this->autor);
        $this->assertSame(['board_angelegt'], ErgebnisVerlauf::where('board_id', $board->id)->pluck('aktion')->all());

        $this->actingAs($this->autor)->post(route('ergebnisboards.duplicate', $board))->assertRedirect();
        $kopie = Ergebnisboard::where('titel', 'Neu (Kopie)')->first();
        $this->assertSame(['board_dupliziert'], ErgebnisVerlauf::where('board_id', $kopie->id)->pluck('aktion')->all());

        $this->actingAs($this->autor)->post(route('ergebnisboards.archive', $board))->assertRedirect();
        $this->assertTrue(ErgebnisVerlauf::where('board_id', $board->id)->where('aktion', 'board_archiviert')->exists());

        for ($i = 0; $i < 55; $i++) {
            ErgebnisVerlauf::create(['board_id' => $this->board->id, 'aktion' => 'board_geaendert', 'details' => ['aenderungen' => ['titel' => ['a', 'b']]]]);
        }
        $erste = $this->actingAs($this->autor)->getJson(route('ergebnisboards.verlauf', $this->board))
            ->assertJsonCount(50, 'eintraege')->assertJsonPath('mehr', true)->json('eintraege');
        $this->actingAs($this->autor)->getJson(route('ergebnisboards.verlauf', [$this->board, 'vor' => end($erste)['id']]))
            ->assertJsonCount(5, 'eintraege')->assertJsonPath('mehr', false);
    }

    public function test_ohne_leserecht_kein_verlauf(): void
    {
        $this->actingAs($this->nutzer())->getJson(route('ergebnisboards.verlauf', $this->board))->assertForbidden();
    }
}
