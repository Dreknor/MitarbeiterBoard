<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\ErgebnisKommentar;
use App\Models\User;
use App\Notifications\ErgebnisKommentarNeu;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Kommentare auf Boards und Karten: Rechte, Bearbeiten/Löschen, @-Erwähnungen,
 * Benachrichtigungen, Zähler und Version (ErgebnisKommentarController + ErgebnisboardKommentarService).
 */
class ErgebnisboardKommentareTest extends TestCase
{
    use ErgebnisboardTestHelfer;

    private User $autor;

    private User $leser;

    private Ergebnisboard $board;

    private ErgebnisKarte $karte;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();

        $this->autor = $this->nutzer(['view result boards', 'create result boards']);
        $this->autor->update(['name' => 'Anna Autorin']);
        $this->leser = $this->nutzer();
        $this->leser->update(['name' => 'Lars Leser']);
        $this->board = $this->board($this->autor, 'kanban');
        $this->zugriff($this->board, $this->leser, 'lesen');
        $this->karte = ErgebnisKarte::factory()->create(['board_id' => $this->board->id, 'bereich' => 'ideen', 'titel' => 'Lesezeit']);
    }

    public function test_leser_darf_karte_kommentieren_ohne_die_version_zu_aendern(): void
    {
        $this->actingAs($this->leser)->postJson(route('ergebnisboards.kommentare.store', $this->board), [
            'karte' => $this->karte->id,
            'text' => "Gute Idee!\nSiehe https://example.org/lesen",
        ])->assertCreated()
            ->assertJsonPath('anzahl', 1)
            ->assertJsonPath('karte', $this->karte->id)
            ->assertJsonPath('kommentare.0.person.name', 'Lars Leser')
            ->assertJsonPath('kommentare.0.darf_bearbeiten', true);

        $html = ErgebnisKommentar::first();
        $this->assertSame($this->karte->id, $html->karte_id);
        $this->assertSame(1, $this->board->fresh()->version);

        $this->actingAs($this->leser)->getJson(route('ergebnisboards.daten', $this->board))
            ->assertJsonPath('data.karten.0.kommentare', 1)
            ->assertJsonPath('data.kommentare_board', 0)
            ->assertJsonPath('data.darf_kommentieren', true);
    }

    public function test_text_wird_escaped_und_urls_werden_verlinkt(): void
    {
        $this->actingAs($this->leser)->postJson(route('ergebnisboards.kommentare.store', $this->board), [
            'text' => '<script>alert(1)</script> https://example.org/a?b=1',
        ])->assertCreated()
            ->assertJsonPath('kommentare.0.html', '&lt;script&gt;alert(1)&lt;/script&gt; <a href="https://example.org/a?b=1" target="_blank" rel="noopener noreferrer">https://example.org/a?b=1</a>');
    }

    public function test_ohne_zugriff_oder_bei_archiv_kein_kommentar(): void
    {
        $fremd = $this->nutzer();
        $this->actingAs($fremd)->postJson(route('ergebnisboards.kommentare.store', $this->board), ['text' => 'Hallo'])->assertForbidden();
        $this->actingAs($fremd)->getJson(route('ergebnisboards.kommentare.index', $this->board))->assertForbidden();

        $this->board->update(['archiviert_at' => now()]);
        $this->actingAs($this->leser)->postJson(route('ergebnisboards.kommentare.store', $this->board), ['text' => 'Hallo'])->assertForbidden();
        $this->actingAs($this->leser)->getJson(route('ergebnisboards.kommentare.index', $this->board))->assertOk();
    }

    public function test_karte_eines_anderen_boards_wird_abgelehnt(): void
    {
        $andere = ErgebnisKarte::factory()->create(['board_id' => $this->board($this->autor)->id]);

        $this->actingAs($this->leser)->postJson(route('ergebnisboards.kommentare.store', $this->board), [
            'karte' => $andere->id, 'text' => 'falsch',
        ])->assertNotFound();
    }

    public function test_eigenen_kommentar_bearbeiten_fremden_nicht(): void
    {
        $k = ErgebnisKommentar::create(['board_id' => $this->board->id, 'user_id' => $this->leser->id, 'text' => 'Alt']);

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.kommentare.update', [$this->board, $k]), ['text' => 'Neu'])->assertForbidden();

        $this->actingAs($this->leser)->putJson(route('ergebnisboards.kommentare.update', [$this->board, $k]), ['text' => 'Neu'])
            ->assertOk()->assertJsonPath('kommentare.0.text', 'Neu');
        $this->assertNotNull($k->fresh()->bearbeitet_at);
    }

    public function test_loeschen_eigener_kommentare_und_durch_verwalten(): void
    {
        $vomLeser = ErgebnisKommentar::create(['board_id' => $this->board->id, 'user_id' => $this->leser->id, 'text' => 'A']);
        $vomAutor = ErgebnisKommentar::create(['board_id' => $this->board->id, 'user_id' => $this->autor->id, 'text' => 'B']);

        $this->actingAs($this->leser)->deleteJson(route('ergebnisboards.kommentare.destroy', [$this->board, $vomAutor]))->assertForbidden();
        $this->actingAs($this->leser)->deleteJson(route('ergebnisboards.kommentare.destroy', [$this->board, $vomLeser]))
            ->assertOk()->assertJsonPath('anzahl', 1)
            ->assertJsonPath('kommentare.0.geloescht', fn ($v) => $v !== null)
            ->assertJsonPath('kommentare.0.text', null);

        // Ersteller:in hat „verwalten“ und darf auch fremde löschen
        $neu = ErgebnisKommentar::create(['board_id' => $this->board->id, 'user_id' => $this->leser->id, 'text' => 'C']);
        $this->actingAs($this->autor)->deleteJson(route('ergebnisboards.kommentare.destroy', [$this->board, $neu]))->assertOk();
        $this->assertSoftDeleted($neu);
    }

    public function test_karten_kommentar_benachrichtigt_zustaendige_und_bisherige_aber_nicht_autor(): void
    {
        $zustaendig = $this->nutzer();
        $this->zugriff($this->board, $zustaendig, 'schreiben');
        $this->karte->zustaendige()->attach($zustaendig->id);
        ErgebnisKommentar::create(['board_id' => $this->board->id, 'karte_id' => $this->karte->id, 'user_id' => $this->autor->id, 'text' => 'Erster']);

        $this->actingAs($this->leser)->postJson(route('ergebnisboards.kommentare.store', $this->board), [
            'karte' => $this->karte->id, 'text' => 'Zweiter',
        ])->assertCreated();

        Notification::assertSentTo($zustaendig, ErgebnisKommentarNeu::class, fn ($n) => ! $n->erwaehnung && $n->kategorie() === 'boards');
        Notification::assertSentTo($this->autor, ErgebnisKommentarNeu::class);
        Notification::assertNotSentTo($this->leser, ErgebnisKommentarNeu::class);
    }

    public function test_board_kommentar_benachrichtigt_ersteller(): void
    {
        $this->actingAs($this->leser)->postJson(route('ergebnisboards.kommentare.store', $this->board), ['text' => 'Zum Board'])
            ->assertCreated();

        Notification::assertSentTo($this->autor, ErgebnisKommentarNeu::class, fn ($n) => str_contains($n->titel($this->autor), 'Board'));
    }

    public function test_erwaehnung_nur_mit_leserecht_und_beim_bearbeiten_nur_neue(): void
    {
        $kollegin = $this->nutzer();
        $kollegin->update(['name' => 'Kira Kollegin']);
        $this->zugriff($this->board, $kollegin, 'lesen');
        $ohneZugriff = $this->nutzer();
        $ohneZugriff->update(['name' => 'Olaf Ohne']);

        $this->actingAs($this->leser)->postJson(route('ergebnisboards.kommentare.store', $this->board), [
            'karte' => $this->karte->id,
            'text' => 'Bitte @Kira Kollegin und @Olaf Ohne ansehen',
        ])->assertCreated()
            ->assertJsonPath('kommentare.0.html', 'Bitte <span class="erg-erwaehnung">@Kira Kollegin</span> und @Olaf Ohne ansehen');

        $kommentar = ErgebnisKommentar::first();
        $this->assertSame([$kollegin->id], $kommentar->erwaehnungen);
        Notification::assertSentTo($kollegin, ErgebnisKommentarNeu::class, fn ($n) => $n->erwaehnung);
        Notification::assertNotSentTo($ohneZugriff, ErgebnisKommentarNeu::class);

        // Bearbeiten: nur neu Erwähnte werden benachrichtigt
        Notification::fake();
        $this->actingAs($this->leser)->putJson(route('ergebnisboards.kommentare.update', [$this->board, $kommentar]), [
            'text' => 'Bitte @Kira Kollegin und @Anna Autorin ansehen',
        ])->assertOk();

        Notification::assertSentTo($this->autor, ErgebnisKommentarNeu::class, fn ($n) => $n->erwaehnung);
        Notification::assertNotSentTo($kollegin, ErgebnisKommentarNeu::class);
    }

    public function test_board_kommentare_und_karten_kommentare_getrennt(): void
    {
        ErgebnisKommentar::create(['board_id' => $this->board->id, 'user_id' => $this->autor->id, 'text' => 'Board']);
        ErgebnisKommentar::create(['board_id' => $this->board->id, 'karte_id' => $this->karte->id, 'user_id' => $this->autor->id, 'text' => 'Karte']);

        $this->actingAs($this->leser)->getJson(route('ergebnisboards.kommentare.index', $this->board))
            ->assertOk()->assertJsonCount(1, 'kommentare')->assertJsonPath('kommentare.0.text', 'Board');
        $this->actingAs($this->leser)->getJson(route('ergebnisboards.kommentare.index', [$this->board, 'karte' => $this->karte->id]))
            ->assertOk()->assertJsonCount(1, 'kommentare')->assertJsonPath('kommentare.0.text', 'Karte');
    }

    public function test_zu_langer_kommentar_wird_abgelehnt(): void
    {
        $this->actingAs($this->leser)->postJson(route('ergebnisboards.kommentare.store', $this->board), [
            'text' => str_repeat('a', ErgebnisKommentar::MAX_ZEICHEN + 1),
        ])->assertUnprocessable();
    }
}
