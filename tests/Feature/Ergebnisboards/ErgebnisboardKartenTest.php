<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\Group;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Karten anlegen/ändern/löschen/sortieren, Versionskonflikt, Zuständige, Duplizieren, Archiv
 * (ErgebnisboardController + ErgebnisboardService).
 */
class ErgebnisboardKartenTest extends TestCase
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

    private function karte(string $bereich = 'ideen', array $attribute = []): ErgebnisKarte
    {
        return ErgebnisKarte::factory()->create(array_merge(['board_id' => $this->board->id, 'bereich' => $bereich], $attribute));
    }

    public function test_karte_anlegen_liefert_board_und_erhoeht_version(): void
    {
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $this->board), [
            'bereich' => 'geplant',
            'titel' => 'Elternabend vorbereiten',
            'zusatz' => 'mit Präsentation',
            'etikett' => 'Eltern',
            'fokus' => true,
            'faellig_am' => '2026-12-10',
            'version' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonCount(1, 'data.karten')
            ->assertJsonPath('data.karten.0.titel', 'Elternabend vorbereiten')
            ->assertJsonPath('data.karten.0.bereich', 'geplant')
            ->assertJsonPath('data.karten.0.fokus', true)
            ->assertJsonPath('data.karten.0.faellig_am', '2026-12-10');

        $this->assertDatabaseHas('ergebnis_karten', ['board_id' => $this->board->id, 'titel' => 'Elternabend vorbereiten', 'sort' => 1]);
    }

    public function test_neue_karte_kommt_ans_ende_des_bereichs(): void
    {
        $this->karte('ideen', ['sort' => 1]);
        $this->karte('ideen', ['sort' => 5]);

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $this->board), ['bereich' => 'ideen', 'titel' => 'Dritte'])
            ->assertCreated();

        $this->assertSame(6, ErgebnisKarte::where('titel', 'Dritte')->value('sort'));
    }

    public function test_karte_aendern_und_verschieben(): void
    {
        $karte = $this->karte('ideen');

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), [
            'titel' => 'Geänderter Titel',
            'bereich' => 'in_arbeit',
        ])->assertOk()->assertJsonPath('data.karten.0.titel', 'Geänderter Titel');

        $karte->refresh();
        $this->assertSame('Geänderter Titel', $karte->titel);
        $this->assertSame('in_arbeit', $karte->bereich);
        $this->assertSame(2, $this->board->fresh()->version);
    }

    public function test_karte_loeschen(): void
    {
        $karte = $this->karte();

        $this->actingAs($this->autor)->deleteJson(route('ergebnisboards.karten.destroy', [$this->board, $karte]))
            ->assertOk()->assertJsonCount(0, 'data.karten');

        // soft-gelöscht: im Verlauf wiederherstellbar, endgültig nach 90 Tagen
        $this->assertSoftDeleted($karte);
    }

    public function test_karte_eines_anderen_boards_ergibt_404(): void
    {
        $anderes = $this->board($this->autor, 'kanban');
        $fremdeKarte = ErgebnisKarte::factory()->create(['board_id' => $anderes->id, 'bereich' => 'ideen']);

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $fremdeKarte]), ['titel' => 'X'])
            ->assertNotFound();
        $this->actingAs($this->autor)->deleteJson(route('ergebnisboards.karten.destroy', [$this->board, $fremdeKarte]))
            ->assertNotFound();
        $this->assertModelExists($fremdeKarte);
    }

    public function test_sortieren_zwischen_bereichen(): void
    {
        $a = $this->karte('ideen', ['sort' => 1]);
        $b = $this->karte('ideen', ['sort' => 2]);
        $c = $this->karte('geplant', ['sort' => 1]);

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.sortierung', $this->board), [
            'ordnung' => [
                'ideen' => [$b->id],
                'geplant' => [$a->id, $c->id],
            ],
            'version' => 1,
        ])->assertOk()->assertJsonPath('data.version', 2);

        $this->assertSame(['ideen', 1], [$b->fresh()->bereich, $b->fresh()->sort]);
        $this->assertSame(['geplant', 1], [$a->fresh()->bereich, $a->fresh()->sort]);
        $this->assertSame(['geplant', 2], [$c->fresh()->bereich, $c->fresh()->sort]);
    }

    public function test_sortieren_ignoriert_karten_fremder_boards(): void
    {
        $anderes = $this->board($this->autor, 'kanban');
        $fremd = ErgebnisKarte::factory()->create(['board_id' => $anderes->id, 'bereich' => 'ideen']);

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.sortierung', $this->board), [
            'ordnung' => ['erledigt' => [$fremd->id]],
        ])->assertOk();

        $this->assertSame('ideen', $fremd->fresh()->bereich);
    }

    public function test_unbekannter_bereich_ergibt_422(): void
    {
        $karte = $this->karte();

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $this->board), ['bereich' => 'gibtsnicht', 'titel' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('bereich');
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), ['bereich' => 'staerken'])
            ->assertStatus(422)->assertJsonValidationErrors('bereich');
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.sortierung', $this->board), ['ordnung' => ['gibtsnicht' => [$karte->id]]])
            ->assertStatus(422)->assertJsonValidationErrors('ordnung');

        $this->assertSame(1, ErgebnisKarte::count());
        $this->assertSame('ideen', $karte->fresh()->bereich);
        $this->assertSame(1, $this->board->fresh()->version);
    }

    public function test_pflichtfelder_beim_anlegen(): void
    {
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $this->board), [])
            ->assertStatus(422)->assertJsonValidationErrors(['bereich', 'titel']);
    }

    public function test_veraltete_version_ergibt_409_mit_aktuellem_stand(): void
    {
        $karte = $this->karte('ideen', ['titel' => 'Original']);
        $this->board->update(['version' => 5]);

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), [
            'titel' => 'Überschrieben',
            'version' => 4,
        ])->assertStatus(409)
            ->assertJsonPath('data.version', 5)
            ->assertJsonPath('data.karten.0.titel', 'Original');

        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $this->board), [
            'bereich' => 'ideen', 'titel' => 'Neu', 'version' => 3,
        ])->assertStatus(409)->assertJsonCount(1, 'data.karten');

        $this->actingAs($this->autor)->deleteJson(route('ergebnisboards.karten.destroy', [$this->board, $karte]), ['version' => 1])
            ->assertStatus(409);

        $this->assertSame('Original', $karte->fresh()->titel);
        $this->assertSame(5, $this->board->fresh()->version);

        // mit aktueller Version klappt es
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), [
            'titel' => 'Überschrieben', 'version' => 5,
        ])->assertOk()->assertJsonPath('data.version', 6);
    }

    public function test_zustaendige_nur_aus_personen_mit_zugriff(): void
    {
        $gruppe = Group::factory()->create();
        $mitglied = $this->nutzer();
        $this->inGruppe($mitglied, $gruppe);
        $this->zugriff($this->board, $gruppe, 'lesen');
        $fremd = $this->nutzer();
        $karte = $this->karte();

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), [
            'zustaendige' => [$mitglied->id, $fremd->id],
        ])->assertStatus(422)->assertJsonValidationErrors('zustaendige');
        $this->assertSame(0, $karte->zustaendige()->count());

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), [
            'zustaendige' => [$mitglied->id, $this->autor->id],
        ])->assertOk()->assertJsonCount(2, 'data.karten.0.zustaendige');

        $this->assertEqualsCanonicalizing([$mitglied->id, $this->autor->id], $karte->zustaendige()->pluck('users.id')->all());

        // neue Karte mit fremder Person wird gar nicht erst angelegt
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $this->board), [
            'bereich' => 'ideen', 'titel' => 'Neu', 'zustaendige' => [$fremd->id],
        ])->assertStatus(422)->assertJsonValidationErrors('zustaendige');
        $this->assertSame(0, ErgebnisKarte::where('titel', 'Neu')->count());

        // leere Liste entfernt alle
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), ['zustaendige' => []])
            ->assertOk();
        $this->assertSame(0, $karte->zustaendige()->count());
    }

    public function test_duplizieren_uebernimmt_karten_ohne_aufgabe_und_zugriffe(): void
    {
        $kollege = $this->nutzer();
        $this->zugriff($this->board, $kollege, 'schreiben');
        $task = Task::factory()->create(['taskable_type' => User::class, 'taskable_id' => $this->autor->id]);
        $mitAufgabe = $this->karte('geplant', ['titel' => 'Mit Aufgabe', 'task_id' => $task->id, 'erinnert_am' => '2026-11-20']);
        $mitAufgabe->zustaendige()->attach($this->autor->id);
        $this->karte('ideen', ['titel' => 'Ohne Aufgabe']);
        $this->board->update(['archiviert_at' => now(), 'version' => 7]);

        $kopierer = $this->nutzer(['view result boards', 'create result boards']);
        $this->zugriff($this->board, $kopierer, 'lesen');

        $this->actingAs($kopierer)->post(route('ergebnisboards.duplicate', $this->board))->assertRedirect();

        $kopie = Ergebnisboard::where('id', '!=', $this->board->id)->firstOrFail();
        $this->assertSame($this->board->titel.' (Kopie)', $kopie->titel);
        $this->assertSame($kopierer->id, (int) $kopie->created_by);
        $this->assertSame(1, $kopie->version);
        $this->assertNull($kopie->archiviert_at);
        $this->assertSame($this->board->bereiche, $kopie->bereiche);

        $karten = $kopie->karten()->get();
        $this->assertEqualsCanonicalizing(
            [['ideen', 'Ohne Aufgabe'], ['geplant', 'Mit Aufgabe']],
            $karten->map(fn (ErgebnisKarte $k) => [$k->bereich, $k->titel])->all()
        );
        $this->assertTrue($karten->every(fn (ErgebnisKarte $k) => $k->task_id === null && $k->erinnert_am === null));
        $this->assertSame(0, $karten->sum(fn (ErgebnisKarte $k) => $k->zustaendige()->count()));

        $this->assertEqualsCanonicalizing(
            // Zugriffe übernommen; Ersteller:in des Originals behält „verwalten“, Kopierer:in braucht keinen Eintrag
            [[User::class, $kollege->id, 'schreiben'], [User::class, $this->autor->id, 'verwalten']],
            $kopie->zugriffe()->get()->map(fn ($z) => [$z->berechtigter_type, (int) $z->berechtigter_id, $z->recht])->all()
        );
        // Original unverändert
        $this->assertSame($task->id, (int) $mitAufgabe->fresh()->task_id);
    }

    public function test_duplizieren_braucht_create_recht(): void
    {
        $leser = $this->nutzer();
        $this->zugriff($this->board, $leser, 'lesen');

        $this->actingAs($leser)->post(route('ergebnisboards.duplicate', $this->board))->assertForbidden();
        $this->assertSame(1, Ergebnisboard::count());
    }

    public function test_archiviertes_board_ist_schreibgeschuetzt(): void
    {
        $karte = $this->karte();
        $this->actingAs($this->autor)->post(route('ergebnisboards.archive', $this->board))->assertRedirect();
        $this->assertNotNull($this->board->fresh()->archiviert_at);

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), ['titel' => 'X'])
            ->assertForbidden();
        $this->actingAs($this->autor)->postJson(route('ergebnisboards.karten.store', $this->board), ['bereich' => 'ideen', 'titel' => 'X'])
            ->assertForbidden();
        $this->actingAs($this->autor)->getJson(route('ergebnisboards.daten', $this->board))
            ->assertOk()->assertJsonPath('data.archiviert', true)->assertJsonPath('data.darf_schreiben', false);

        // wiederherstellen
        $this->actingAs($this->autor)->post(route('ergebnisboards.archive', $this->board), ['archivieren' => 0])->assertRedirect();
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.karten.update', [$this->board, $karte]), ['titel' => 'X'])
            ->assertOk();
    }

    public function test_kopf_aendern_mit_versionspruefung(): void
    {
        $this->actingAs($this->autor)->putJson(route('ergebnisboards.update', $this->board), [
            'titel' => 'Neuer Titel', 'untertitel' => 'Klausur 2026', 'farbschema' => 'papier', 'version' => 1,
        ])->assertOk()->assertJsonPath('data.titel', 'Neuer Titel')->assertJsonPath('data.farbschema', 'papier');

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.update', $this->board), ['titel' => 'Alt', 'version' => 1])
            ->assertStatus(409)->assertJsonPath('data.titel', 'Neuer Titel');

        $this->actingAs($this->autor)->putJson(route('ergebnisboards.update', $this->board), ['vorlage' => 'swot'])
            ->assertStatus(422);
    }

    public function test_board_loeschen_nur_mit_verwaltungsrecht(): void
    {
        $karte = $this->karte();
        $schreiber = $this->nutzer();
        $this->zugriff($this->board, $schreiber, 'schreiben');

        $this->actingAs($schreiber)->delete(route('ergebnisboards.destroy', $this->board))->assertForbidden();
        $this->actingAs($this->autor)->delete(route('ergebnisboards.destroy', $this->board))->assertRedirect(route('ergebnisboards.index'));

        $this->assertSoftDeleted($this->board);
        $this->actingAs($this->autor)->getJson(route('ergebnisboards.daten', $this->board->id))->assertNotFound();
    }
}
