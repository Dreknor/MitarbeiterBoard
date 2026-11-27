<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Ergebnisboards\ErgebnisboardService;
use App\Services\Meetings\MeetingService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Zugriff auf Ergebnisboards: lesen/schreiben/verwalten über Person, Gruppe, Rolle, Ersteller, Sitzung
 * (ErgebnisboardService::rechtFuer(), ErgebnisboardPolicy, Ergebnisboard::scopeSichtbarFuer()).
 */
class ErgebnisboardZugriffTest extends TestCase
{
    use ErgebnisboardTestHelfer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
    }

    private function recht(Ergebnisboard $board, User $user): ?string
    {
        return $this->actingAs($user)->getJson(route('ergebnisboards.daten', $board))->assertOk()->json('data.recht');
    }

    private function karteAnlegen(Ergebnisboard $board, User $user)
    {
        return $this->actingAs($user)->postJson(route('ergebnisboards.karten.store', $board), [
            'bereich' => 'ideen',
            'titel' => 'Neue Idee',
        ]);
    }

    private function zugriffeSpeichern(Ergebnisboard $board, User $user, array $zugriffe = [])
    {
        return $this->actingAs($user)->postJson(route('ergebnisboards.zugriffe', $board), ['zugriffe' => $zugriffe]);
    }

    // ------------------------------------------------------------------
    // Person
    // ------------------------------------------------------------------

    public function test_person_mit_leserecht_darf_lesen_aber_nicht_schreiben(): void
    {
        $board = $this->board();
        $leser = $this->nutzer();
        $this->zugriff($board, $leser, 'lesen');

        $this->assertSame('lesen', $this->recht($board, $leser));
        $this->actingAs($leser)->getJson(route('ergebnisboards.daten', $board))
            ->assertJsonPath('data.darf_schreiben', false)
            ->assertJsonPath('data.darf_verwalten', false);
        $this->karteAnlegen($board, $leser)->assertForbidden();
        $this->zugriffeSpeichern($board, $leser)->assertForbidden();
    }

    public function test_person_mit_schreibrecht_darf_karten_anlegen_aber_nicht_verwalten(): void
    {
        $board = $this->board();
        $autor = $this->nutzer();
        $this->zugriff($board, $autor, 'schreiben');

        $this->assertSame('schreiben', $this->recht($board, $autor));
        $this->karteAnlegen($board, $autor)->assertCreated();
        $this->zugriffeSpeichern($board, $autor)->assertForbidden();
        $this->actingAs($autor)->post(route('ergebnisboards.archive', $board))->assertForbidden();
        $this->actingAs($autor)->delete(route('ergebnisboards.destroy', $board))->assertForbidden();
    }

    public function test_person_mit_verwaltungsrecht_darf_zugriffe_aendern(): void
    {
        $board = $this->board();
        $verwalter = $this->nutzer();
        $neu = $this->nutzer();
        $this->zugriff($board, $verwalter, 'verwalten');

        $this->assertSame('verwalten', $this->recht($board, $verwalter));
        $this->zugriffeSpeichern($board, $verwalter, [
            ['typ' => 'user', 'id' => $verwalter->id, 'recht' => 'verwalten'],
            ['typ' => 'user', 'id' => $neu->id, 'recht' => 'lesen'],
        ])->assertOk();

        $this->assertDatabaseHas('ergebnis_board_zugriffe', [
            'board_id' => $board->id, 'berechtigter_type' => User::class, 'berechtigter_id' => $neu->id, 'recht' => 'lesen',
        ]);
        $this->assertSame('lesen', $this->recht($board, $neu));
    }

    // ------------------------------------------------------------------
    // Gruppe
    // ------------------------------------------------------------------

    public function test_gruppenmitglied_erhaelt_lese_und_schreibrecht_der_gruppe(): void
    {
        $leseGruppe = Group::factory()->create();
        $schreibGruppe = Group::factory()->create();
        $board = $this->board();
        $this->zugriff($board, $leseGruppe, 'lesen');
        $this->zugriff($board, $schreibGruppe, 'schreiben');

        $leser = $this->nutzer();
        $this->inGruppe($leser, $leseGruppe);
        $autor = $this->nutzer();
        $this->inGruppe($autor, $schreibGruppe);
        $fremd = $this->nutzer();

        $this->assertSame('lesen', $this->recht($board, $leser));
        $this->karteAnlegen($board, $leser)->assertForbidden();

        $this->assertSame('schreiben', $this->recht($board, $autor));
        $this->karteAnlegen($board, $autor)->assertCreated();

        $this->actingAs($fremd)->getJson(route('ergebnisboards.daten', $board))->assertForbidden();
    }

    public function test_gruppe_bekommt_nie_verwaltungsrecht(): void
    {
        $gruppe = Group::factory()->create();
        $board = $this->board();
        // auch ein direkt gespeichertes „verwalten“ wird für Gruppen auf „schreiben“ gekappt
        $this->zugriff($board, $gruppe, 'verwalten');
        $mitglied = $this->nutzer();
        $this->inGruppe($mitglied, $gruppe);

        $this->assertSame('schreiben', $this->recht($board, $mitglied));
        $this->zugriffeSpeichern($board, $mitglied)->assertForbidden();
    }

    public function test_zugriffe_speichern_kappt_verwalten_fuer_gruppen_und_rollen(): void
    {
        $ersteller = $this->nutzer(['view result boards', 'create result boards']);
        $board = $this->board($ersteller);
        $gruppe = Group::factory()->create();
        $rolle = Role::create(['name' => 'EB-Testrolle Steuergruppe', 'guard_name' => 'web']);

        $this->zugriffeSpeichern($board, $ersteller, [
            ['typ' => 'group', 'id' => $gruppe->id, 'recht' => 'verwalten'],
            ['typ' => 'role', 'id' => $rolle->id, 'recht' => 'verwalten'],
        ])->assertOk();

        $this->assertDatabaseHas('ergebnis_board_zugriffe', ['board_id' => $board->id, 'berechtigter_type' => Group::class, 'recht' => 'schreiben']);
        $this->assertDatabaseHas('ergebnis_board_zugriffe', ['board_id' => $board->id, 'berechtigter_type' => Role::class, 'recht' => 'schreiben']);
        $this->assertDatabaseMissing('ergebnis_board_zugriffe', ['board_id' => $board->id, 'recht' => 'verwalten']);
    }

    // ------------------------------------------------------------------
    // Rolle
    // ------------------------------------------------------------------

    public function test_rolle_gibt_lese_und_schreibrecht_aber_nie_verwalten(): void
    {
        $leseRolle = Role::create(['name' => 'EB-Testrolle Lesen', 'guard_name' => 'web']);
        $schreibRolle = Role::create(['name' => 'EB-Testrolle Schreiben', 'guard_name' => 'web']);
        $verwaltRolle = Role::create(['name' => 'EB-Testrolle Verwalten', 'guard_name' => 'web']);
        $board = $this->board();
        $this->zugriff($board, $leseRolle, 'lesen');
        $this->zugriff($board, $schreibRolle, 'schreiben');
        $this->zugriff($board, $verwaltRolle, 'verwalten');

        $leser = $this->nutzer();
        $leser->assignRole($leseRolle);
        $autor = $this->nutzer();
        $autor->assignRole($schreibRolle);
        $verwaltung = $this->nutzer();
        $verwaltung->assignRole($verwaltRolle);

        $this->assertSame('lesen', $this->recht($board, $leser));
        $this->karteAnlegen($board, $leser)->assertForbidden();
        $this->assertSame('schreiben', $this->recht($board, $autor));
        $this->karteAnlegen($board, $autor)->assertCreated();
        $this->assertSame('schreiben', $this->recht($board, $verwaltung));
        $this->zugriffeSpeichern($board, $verwaltung)->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Kombinationen, Ersteller, Sonderrecht
    // ------------------------------------------------------------------

    public function test_hoechstes_recht_gewinnt(): void
    {
        $gruppe = Group::factory()->create();
        $rolle = Role::create(['name' => 'EB-Testrolle Kollegium', 'guard_name' => 'web']);
        $board = $this->board();

        // Person lesen + Gruppe schreiben → schreiben
        $a = $this->nutzer();
        $this->inGruppe($a, $gruppe);
        $this->zugriff($board, $a, 'lesen');
        $this->zugriff($board, $gruppe, 'schreiben');

        // Person verwalten + Rolle lesen → verwalten
        $b = $this->nutzer();
        $b->assignRole($rolle);
        $this->zugriff($board, $b, 'verwalten');
        $this->zugriff($board, $rolle, 'lesen');

        $this->assertSame('schreiben', $this->recht($board->fresh(), $a));
        $this->assertSame('verwalten', $this->recht($board->fresh(), $b));
    }

    public function test_ersteller_darf_immer_verwalten(): void
    {
        $ersteller = $this->nutzer();
        $board = $this->board($ersteller);
        // eigener Eintrag mit geringerem Recht ändert nichts
        $this->zugriff($board, $ersteller, 'lesen');

        $this->assertSame('verwalten', $this->recht($board, $ersteller));
        $this->zugriffeSpeichern($board, $ersteller)->assertOk();
    }

    public function test_manage_result_boards_sieht_und_verwaltet_alle_boards(): void
    {
        $board = $this->board();
        $admin = $this->nutzer(['view result boards', 'manage result boards']);

        $this->assertSame('verwalten', $this->recht($board, $admin));
        $this->assertTrue(Ergebnisboard::query()->sichtbarFuer($admin)->whereKey($board->id)->exists());
        $this->zugriffeSpeichern($board, $admin)->assertOk();
    }

    public function test_fremde_person_bekommt_403_auf_board_und_daten(): void
    {
        $board = $this->board();
        $fremd = $this->nutzer();

        $this->actingAs($fremd)->get(route('ergebnisboards.show', $board))->assertForbidden();
        $this->actingAs($fremd)->getJson(route('ergebnisboards.daten', $board))->assertForbidden();
        $this->actingAs($fremd)->get(route('ergebnisboards.pdf', $board))->assertForbidden();
        $this->karteAnlegen($board, $fremd)->assertForbidden();
    }

    public function test_nur_lesend_alle_karten_endpunkte_403(): void
    {
        $board = $this->board();
        $karte = ErgebnisKarte::factory()->create(['board_id' => $board->id, 'bereich' => 'ideen']);
        $leser = $this->nutzer();
        $this->zugriff($board, $leser, 'lesen');
        $this->actingAs($leser);

        $this->postJson(route('ergebnisboards.karten.store', $board), ['bereich' => 'ideen', 'titel' => 'X'])->assertForbidden();
        $this->putJson(route('ergebnisboards.karten.update', [$board, $karte]), ['titel' => 'Y'])->assertForbidden();
        $this->deleteJson(route('ergebnisboards.karten.destroy', [$board, $karte]))->assertForbidden();
        $this->postJson(route('ergebnisboards.sortierung', $board), ['ordnung' => ['geplant' => [$karte->id]]])->assertForbidden();
        $this->putJson(route('ergebnisboards.bereiche', $board), ['bereiche' => $board->bereiche])->assertForbidden();
        $this->putJson(route('ergebnisboards.update', $board), ['titel' => 'Neu'])->assertForbidden();
        $this->postJson(route('ergebnisboards.karten.dateien.store', [$board, $karte]), ['dateien' => []])->assertForbidden();
        $this->postJson(route('ergebnisboards.karten.aufgabe', [$board, $karte]))->assertForbidden();

        $this->assertSame('ideen', $karte->fresh()->bereich);
        $this->assertSame(1, $board->fresh()->version);
    }

    public function test_ohne_view_result_boards_kein_zugriff_auf_liste(): void
    {
        $user = $this->nutzer([]);
        $board = $this->board($user);

        $this->actingAs($user)->get(route('ergebnisboards.index'))->assertForbidden();
        // auch der Ersteller kommt ohne Grundrecht nicht an seine Boards (Routen-Middleware)
        $this->actingAs($user)->getJson(route('ergebnisboards.daten', $board))->assertForbidden();
    }

    public function test_sichtbar_fuer_liefert_nur_boards_mit_zugriff(): void
    {
        $user = $this->nutzer();
        $gruppe = Group::factory()->create();
        $rolle = Role::create(['name' => 'EB-Testrolle Fachschaft', 'guard_name' => 'web']);
        $user->assignRole($rolle);
        $this->inGruppe($user, $gruppe);

        $eigen = $this->board($user);
        $person = $this->board();
        $this->zugriff($person, $user, 'lesen');
        $ueberGruppe = $this->board();
        $this->zugriff($ueberGruppe, $gruppe, 'lesen');
        $ueberRolle = $this->board();
        $this->zugriff($ueberRolle, $rolle, 'schreiben');
        $fremd = $this->board();
        $this->zugriff($fremd, Group::factory()->create(), 'schreiben');

        $ids = Ergebnisboard::query()->sichtbarFuer($user)->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$eigen->id, $person->id, $ueberGruppe->id, $ueberRolle->id])->sort()->values()->all(), $ids);
    }

    public function test_liste_zeigt_nur_sichtbare_boards(): void
    {
        $user = $this->nutzer();
        $sichtbar = $this->board(null, 'kanban', ['titel' => 'Sichtbares Board']);
        $this->zugriff($sichtbar, $user, 'lesen');
        $this->board(null, 'kanban', ['titel' => 'Geheimes Board']);
        $archiviert = $this->board(null, 'kanban', ['titel' => 'Altes Board', 'archiviert_at' => now()]);
        $this->zugriff($archiviert, $user, 'lesen');

        $this->actingAs($user)->get(route('ergebnisboards.index'))
            ->assertOk()
            ->assertViewHas('boards', fn ($boards) => collect($boards->items())->pluck('id')->all() === [$sichtbar->id]);

        $this->actingAs($user)->get(route('ergebnisboards.index', ['archiv' => 1]))
            ->assertOk()
            ->assertViewHas('boards', fn ($boards) => collect($boards->items())->pluck('id')->all() === [$archiviert->id]);
    }

    public function test_teilnehmende_einer_sitzung_lesen_bei_meeting_lesen(): void
    {
        $orga = User::factory()->create();
        $teilnehmer = $this->nutzer();
        $meeting = Meeting::factory()->free()->create(['creator_id' => $orga->id]);
        app(MeetingService::class)->syncParticipants($meeting, ['users' => [$teilnehmer->id]]);

        $mitLesen = $this->board(null, 'kanban', ['meeting_id' => $meeting->id, 'meeting_lesen' => true]);
        $ohneLesen = $this->board(null, 'kanban', ['meeting_id' => $meeting->id, 'meeting_lesen' => false]);

        $this->assertSame('lesen', $this->recht($mitLesen, $teilnehmer));
        $this->karteAnlegen($mitLesen, $teilnehmer)->assertForbidden();
        $this->actingAs($teilnehmer)->getJson(route('ergebnisboards.daten', $ohneLesen))->assertForbidden();

        $sichtbar = Ergebnisboard::query()->sichtbarFuer($teilnehmer)->pluck('id')->all();
        $this->assertSame([$mitLesen->id], $sichtbar);

        // Teilnehmende sind als Zuständige wählbar
        $this->assertTrue(app(ErgebnisboardService::class)->personenMitZugriff($mitLesen)->pluck('id')->contains($teilnehmer->id));
    }

    // ------------------------------------------------------------------
    // Seiten (HTML) – nur Status/Zugriff
    // ------------------------------------------------------------------

    public function test_seiten_liste_anlegen_und_board_fuer_berechtigte(): void
    {
        $user = $this->nutzer(['view result boards', 'create result boards']);
        $board = $this->board($user);

        $this->actingAs($user)->get(route('ergebnisboards.index'))->assertOk();
        $this->actingAs($user)->get(route('ergebnisboards.create'))->assertOk();
        $this->actingAs($user)->get(route('ergebnisboards.show', $board))->assertOk();
    }

    public function test_anlegen_nur_mit_create_recht(): void
    {
        $user = $this->nutzer();

        $this->actingAs($user)->get(route('ergebnisboards.create'))->assertForbidden();
        $this->actingAs($user)->post(route('ergebnisboards.store'), ['titel' => 'X', 'vorlage' => 'swot'])->assertForbidden();
        $this->assertSame(0, Ergebnisboard::count());
    }

    public function test_board_anlegen_kopiert_vorlage_und_zugriffe(): void
    {
        $user = $this->nutzer(['view result boards', 'create result boards']);
        $kollege = $this->nutzer();
        $gruppe = Group::factory()->create();

        $antwort = $this->actingAs($user)->post(route('ergebnisboards.store'), [
            'titel' => 'Pädagogischer Tag',
            'vorlage' => 'swot',
            'zugriffe' => [
                ['typ' => 'user', 'id' => $kollege->id, 'recht' => 'schreiben'],
                ['typ' => 'group', 'id' => $gruppe->id, 'recht' => 'lesen'],
                ['typ' => 'user', 'id' => $user->id, 'recht' => 'lesen'], // Ersteller wird übersprungen
            ],
        ]);

        $board = Ergebnisboard::firstOrFail();
        $antwort->assertRedirect(route('ergebnisboards.show', $board));
        $this->assertSame('swot', $board->vorlage);
        $this->assertSame(['staerken', 'schwaechen', 'chancen', 'risiken'], $board->bereichsSchluessel());
        $this->assertSame($user->id, (int) $board->created_by);
        $this->assertSame(2, $board->zugriffe()->count());
        $this->assertSame('schreiben', app(ErgebnisboardService::class)->rechtFuer($board, $kollege));
    }

    public function test_unbekannte_vorlage_wird_abgelehnt(): void
    {
        $user = $this->nutzer(['view result boards', 'create result boards']);

        $this->actingAs($user)->postJson(route('ergebnisboards.store'), ['titel' => 'X', 'vorlage' => 'gibtsnicht'])
            ->assertStatus(422)->assertJsonValidationErrors('vorlage');
    }
}
