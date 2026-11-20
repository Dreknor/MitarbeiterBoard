<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\Schueler;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Vorhandene Spalten im Pädagogischen Tagebuch bearbeiten (PaedDiarySpaltenService):
 * Name, Gruppe und Art gelten für alle verknüpften Kopien (spalten_uid), die Klassenauswahl
 * legt Kopien an, übernimmt passende vorhandene Spalten, reaktiviert oder deaktiviert sie ab heute.
 */
class SpaltenBearbeitenTest extends TestCase
{
    private User $user;
    private Klasse $a;
    private Klasse $b;
    private Klasse $c;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 08:00:00');
        Http::fake(['*' => Http::response([])]);

        $this->user = $this->actingAsWithPermission('view paed diary');
        $this->a = Klasse::factory()->create(['name' => '5a']);
        $this->b = Klasse::factory()->create(['name' => '5b']);
        $this->c = Klasse::factory()->create(['name' => '6a']);
        $this->user->paed_klassen()->attach([$this->a->id, $this->b->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function spalte(Klasse $klasse, string $name = 'HA', string $type = 'boolean', ?string $category = 'Lernen', array $mehr = []): PaedDiaryColumn
    {
        return PaedDiaryColumn::create(array_merge([
            'klasse_id' => $klasse->id,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'type' => $type,
            'category' => $category,
        ], $mehr));
    }

    private function speichern(PaedDiaryColumn $spalte, array $daten)
    {
        return $this->postJson("/paed-diary/column/{$spalte->id}", array_merge([
            'name' => $spalte->name,
            'category' => $spalte->category,
            'type' => $spalte->type,
            'klasse_ids' => [],
        ], $daten));
    }

    private function globalesRecht(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('manage global paed diary categories', 'web'));
    }

    /** @test */
    public function gleiche_spalten_werden_verknuepft_gleicher_name_in_anderer_gruppe_nicht(): void
    {
        $eins = $this->spalte($this->a);
        $zwei = $this->spalte($this->b, ' ha ');
        $andereGruppe = $this->spalte($this->b, 'HA', 'boolean', 'Verhalten');
        $andererTyp = $this->spalte($this->c, 'HA', 'ampel');

        $this->assertSame($eins->spalten_uid, $zwei->spalten_uid);
        $this->assertNotSame($eins->spalten_uid, $andereGruppe->spalten_uid);
        $this->assertNotSame($eins->spalten_uid, $andererTyp->spalten_uid);
    }

    /** @test */
    public function dialog_zeigt_eigene_klassen_mit_status_und_zaehlt_fremde(): void
    {
        $spalte = $this->spalte($this->a);
        $this->spalte($this->b, 'HA', 'boolean', 'Lernen', ['deactivated_from' => '2026-11-02']);
        $this->spalte($this->c);

        $antwort = $this->getJson("/paed-diary/column/{$spalte->id}/bearbeiten")->assertOk();

        $this->assertSame(1, $antwort->json('spalte.weitere_klassen'));
        $this->assertTrue($antwort->json('spalte.eigenschaften_gesperrt'));
        $this->assertTrue($antwort->json('spalte.typ_aenderbar'));
        $klassen = collect($antwort->json('spalte.klassen'))->keyBy('name');
        $this->assertSame(['5a', '5b'], $klassen->keys()->all());
        $this->assertSame('aktiv', $klassen['5a']['status']);
        $this->assertSame('deaktiviert', $klassen['5b']['status']);
        $this->assertSame('2026-11-02', $klassen['5b']['deaktiviert_seit']);
    }

    /** @test */
    public function fremde_spalte_ist_nicht_bearbeitbar(): void
    {
        $spalte = $this->spalte($this->c);

        $this->getJson("/paed-diary/column/{$spalte->id}/bearbeiten")->assertNotFound();
        $this->speichern($spalte, ['name' => 'Neu'])->assertNotFound();
    }

    /** @test */
    public function name_und_gruppe_gelten_fuer_alle_kopien(): void
    {
        $eins = $this->spalte($this->a);
        $zwei = $this->spalte($this->b);

        $this->speichern($eins, ['name' => 'Hausaufgaben', 'category' => 'Arbeit', 'klasse_ids' => [$this->a->id, $this->b->id]])
            ->assertOk()->assertJsonPath('success', true);

        foreach ([$eins, $zwei] as $kopie) {
            $kopie->refresh();
            $this->assertSame('Hausaufgaben', $kopie->name);
            $this->assertSame('Arbeit', $kopie->category);
            $this->assertNull($kopie->deactivated_from);
        }
    }

    /** @test */
    public function name_aendern_mit_kopien_in_fremden_klassen_braucht_sonderrecht(): void
    {
        $eins = $this->spalte($this->a);
        $fremd = $this->spalte($this->c);

        $this->speichern($eins, ['name' => 'Hausaufgaben', 'klasse_ids' => [$this->a->id]])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->assertSame('HA', $fremd->fresh()->name);

        // Klassen ändern geht trotzdem, solange Name/Gruppe/Art gleich bleiben
        $this->speichern($eins, ['klasse_ids' => [$this->a->id, $this->b->id]])->assertOk();
        $this->assertTrue(PaedDiaryColumn::where('klasse_id', $this->b->id)->where('spalten_uid', $eins->spalten_uid)->exists());

        $this->globalesRecht();
        $this->speichern($eins, ['name' => 'Hausaufgaben', 'klasse_ids' => [$this->a->id, $this->b->id, $this->c->id]])->assertOk();
        $this->assertSame('Hausaufgaben', $fremd->fresh()->name);
    }

    /** @test */
    public function klasse_hinzufuegen_legt_kopie_am_ende_an(): void
    {
        $spalte = $this->spalte($this->a, 'HA', 'boolean', 'Lernen', ['bis_wochenende' => true]);
        $this->spalte($this->b, 'Lesen', 'boolean', null, ['sort_order' => 7]);

        $this->speichern($spalte, ['klasse_ids' => [$this->a->id, $this->b->id]])
            ->assertOk()->assertJsonPath('ergebnis.neu', 1);

        $kopie = PaedDiaryColumn::where('klasse_id', $this->b->id)->where('spalten_uid', $spalte->spalten_uid)->firstOrFail();
        $this->assertSame('HA', $kopie->name);
        $this->assertSame('Lernen', $kopie->category);
        $this->assertSame(8, (int) $kopie->sort_order);
        $this->assertTrue($kopie->bis_wochenende);
    }

    /** @test */
    public function klasse_abwaehlen_deaktiviert_ab_heute_und_behaelt_werte(): void
    {
        $eins = $this->spalte($this->a);
        $zwei = $this->spalte($this->b);
        $kind = Schueler::factory()->create(['klasse_id' => $this->b->id]);
        PaedDiaryColumnValue::create(['paed_diary_column_id' => $zwei->id, 'schueler_id' => $kind->id, 'datum' => '2026-11-10', 'value' => '1']);

        $this->speichern($eins, ['klasse_ids' => [$this->a->id]])->assertOk()->assertJsonPath('ergebnis.deaktiviert', 1);

        $this->assertSame('2026-11-19', $zwei->fresh()->deactivated_from->toDateString());
        $this->assertNull($eins->fresh()->deactivated_from);
        $this->assertSame(1, PaedDiaryColumnValue::where('paed_diary_column_id', $zwei->id)->count());
    }

    /** @test */
    public function erneut_auswaehlen_reaktiviert_die_alte_kopie(): void
    {
        $eins = $this->spalte($this->a);
        $alt = $this->spalte($this->b, 'HA', 'boolean', 'Lernen', ['deactivated_from' => '2026-10-05']);

        $this->speichern($eins, ['klasse_ids' => [$this->a->id, $this->b->id]])
            ->assertOk()->assertJsonPath('ergebnis.reaktiviert', 1)->assertJsonPath('ergebnis.neu', 0);

        $this->assertNull($alt->fresh()->deactivated_from);
        $this->assertSame(1, PaedDiaryColumn::where('klasse_id', $this->b->id)->count());
    }

    /** @test */
    public function vorhandene_gleiche_spalte_wird_uebernommen(): void
    {
        $eins = $this->spalte($this->a);
        $vorhanden = $this->spalte($this->b, 'HA', 'boolean', 'Lernen', ['spalten_uid' => 'eigene-uid']);
        $kind = Schueler::factory()->create(['klasse_id' => $this->b->id]);
        PaedDiaryColumnValue::create(['paed_diary_column_id' => $vorhanden->id, 'schueler_id' => $kind->id, 'datum' => '2026-11-10', 'value' => '1']);

        $this->speichern($eins, ['klasse_ids' => [$this->a->id, $this->b->id]])
            ->assertOk()->assertJsonPath('ergebnis.uebernommen', 1)->assertJsonPath('ergebnis.neu', 0);

        $this->assertSame($eins->spalten_uid, $vorhanden->fresh()->spalten_uid);
        $this->assertSame(1, PaedDiaryColumn::where('klasse_id', $this->b->id)->count());
        $this->assertSame(1, PaedDiaryColumnValue::where('paed_diary_column_id', $vorhanden->id)->count());
    }

    /** @test */
    public function gleiche_spalte_mit_anderem_typ_in_neuer_klasse_wird_abgelehnt(): void
    {
        $eins = $this->spalte($this->a);
        $this->spalte($this->b, 'HA', 'ampel');

        $this->speichern($eins, ['klasse_ids' => [$this->a->id, $this->b->id]])
            ->assertStatus(422)->assertJsonValidationErrors('klasse_ids');

        $this->assertSame(1, PaedDiaryColumn::where('klasse_id', $this->b->id)->count());
    }

    /** @test */
    public function gleicher_name_in_anderer_gruppe_bekommt_eine_eigene_kopie(): void
    {
        $eins = $this->spalte($this->a);
        $this->spalte($this->b, 'HA', 'boolean', 'Verhalten');

        $this->speichern($eins, ['klasse_ids' => [$this->a->id, $this->b->id]])
            ->assertOk()->assertJsonPath('ergebnis.neu', 1);

        $this->assertSame(2, PaedDiaryColumn::where('klasse_id', $this->b->id)->whereNull('deactivated_from')->count());
    }

    /** @test */
    public function umbenennen_auf_vorhandene_spalte_der_klasse_wird_abgelehnt(): void
    {
        $eins = $this->spalte($this->a);
        $this->spalte($this->a, 'Lesen');

        $this->speichern($eins, ['name' => 'Lesen', 'klasse_ids' => [$this->a->id]])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertSame('HA', $eins->fresh()->name);
    }

    /** @test */
    public function typ_aendern_nur_ohne_werte_in_allen_kopien(): void
    {
        $eins = $this->spalte($this->a);
        $zwei = $this->spalte($this->b);

        $this->speichern($eins, ['type' => 'ampel', 'klasse_ids' => [$this->a->id, $this->b->id]])->assertOk();
        $this->assertSame('ampel', $zwei->fresh()->type);

        $kind = Schueler::factory()->create(['klasse_id' => $this->b->id]);
        PaedDiaryColumnValue::create(['paed_diary_column_id' => $zwei->id, 'schueler_id' => $kind->id, 'datum' => '2026-11-10', 'value' => '1']);

        $this->speichern($eins->fresh(), ['type' => 'boolean', 'klasse_ids' => [$this->a->id, $this->b->id]])
            ->assertStatus(422)->assertJsonValidationErrors('type');
        $this->assertSame('ampel', $eins->fresh()->type);
        $this->getJson("/paed-diary/column/{$eins->id}/bearbeiten")->assertJsonPath('spalte.typ_aenderbar', false);
    }

    /** @test */
    public function mit_sonderrecht_sind_alle_klassen_waehlbar(): void
    {
        $this->globalesRecht();
        $spalte = $this->spalte($this->a);

        $klassen = collect($this->getJson("/paed-diary/column/{$spalte->id}/bearbeiten")->json('spalte.klassen'))->pluck('name')->all();
        $this->assertSame(['5a', '5b', '6a'], $klassen);

        $this->speichern($spalte, ['klasse_ids' => [$this->a->id, $this->c->id]])->assertOk()->assertJsonPath('ergebnis.neu', 1);
        $this->assertTrue(PaedDiaryColumn::where('klasse_id', $this->c->id)->where('spalten_uid', $spalte->spalten_uid)->exists());
    }

    /** @test */
    public function gruppe_aendern_ueber_alte_route_gilt_fuer_alle_kopien(): void
    {
        $eins = $this->spalte($this->a);
        $zwei = $this->spalte($this->b);

        $this->postJson("/paed-diary/column/{$eins->id}/category", ['category' => 'Arbeit'])->assertOk();

        $this->assertSame('Arbeit', $zwei->fresh()->category);
    }

    /** @test */
    public function kopieren_verknuepft_und_reaktiviert(): void
    {
        $eins = $this->spalte($this->a, 'HA', 'boolean', 'Lernen', ['spalten_uid' => 'quelle']);
        $alt = $this->spalte($this->b, 'HA', 'boolean', 'Lernen', ['spalten_uid' => 'quelle', 'deactivated_from' => '2026-10-05']);

        $this->postJson("/paed-diary/column/{$eins->id}/copy", ['klasse_ids' => [$this->b->id]])
            ->assertOk()->assertJsonCount(1, 'created');

        $this->assertNull($alt->fresh()->deactivated_from);
        $this->assertSame(1, PaedDiaryColumn::where('klasse_id', $this->b->id)->count());
    }
}
