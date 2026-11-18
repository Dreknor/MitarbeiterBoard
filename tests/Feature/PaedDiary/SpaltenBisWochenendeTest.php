<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\Schueler;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Spaltengruppen-Einstellung „gilt bis Freitag“ im Pädagogischen Tagebuch.
 *
 * Eine Änderung (Bestätigen, Ampel-Wert, Zurücknehmen) gilt ab ihrem Tag bis Freitag derselben
 * Woche bzw. bis zur nächsten ausdrücklichen Änderung – nicht rückwirkend, nur für das jeweilige
 * Kind, nur für Ja/Nein- und Ampel-Spalten. Gespeichert wird nur der Wert am Tag der Änderung.
 *
 * Referenzwoche: Mo 21.09.2026 – Fr 25.09.2026.
 */
class SpaltenBisWochenendeTest extends TestCase
{
    private const MO = '2026-09-21';
    private const DI = '2026-09-22';
    private const MI = '2026-09-23';
    private const DO = '2026-09-24';
    private const FR = '2026-09-25';

    private User $user;
    private Klasse $klasse;
    private Schueler $kind;
    private Schueler $anderesKind;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 08:00:00');
        cache()->flush();
        Http::fake(['*' => Http::response([])]);

        $this->user = $this->actingAsWithPermission('view paed diary');
        $this->klasse = Klasse::factory()->create();
        $this->user->paed_klassen()->attach($this->klasse->id);
        $this->kind = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
        $this->anderesKind = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function spalte(string $name, string $type = 'boolean', ?string $category = 'Wochenplan', bool $bisWochenende = true, ?Klasse $klasse = null): PaedDiaryColumn
    {
        return PaedDiaryColumn::create([
            'klasse_id' => ($klasse ?? $this->klasse)->id,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'type' => $type,
            'category' => $category,
            'bis_wochenende' => $bisWochenende,
        ]);
    }

    private function setzen(PaedDiaryColumn $spalte, string $datum, ?string $wert, ?Schueler $kind = null)
    {
        return $this->postJson('/paed-diary/column/value', [
            'column_id' => $spalte->id,
            'schueler_id' => ($kind ?? $this->kind)->id,
            'date' => $datum,
            'value' => $wert ?? '',
        ])->assertOk();
    }

    /** Gültige Werte Mo–Fr der Referenzwoche aus der Wochenansicht: [datum => wert|null] */
    private function woche(PaedDiaryColumn $spalte, ?Schueler $kind = null, string $montag = self::MO): array
    {
        $json = $this->getJson('/paed-diary/week?' . http_build_query(['klasse_id' => $this->klasse->id, 'week_start' => $montag]))
            ->assertOk()->json();
        $werte = $json['column_values'][$spalte->id][($kind ?? $this->kind)->id] ?? [];

        $tage = [];
        for ($i = 0; $i < 5; $i++) {
            $tag = Carbon::parse($montag)->addDays($i)->toDateString();
            $tage[$tag] = ($werte[$tag] ?? null) ?: null;
        }

        return $tage;
    }

    private function quellen(PaedDiaryColumn $spalte, ?Schueler $kind = null): array
    {
        $json = $this->getJson('/paed-diary/week?' . http_build_query(['klasse_id' => $this->klasse->id, 'week_start' => self::MO]))->json();

        return $json['column_value_quellen'][$spalte->id][($kind ?? $this->kind)->id] ?? [];
    }

    // ── Kernregeln ───────────────────────────────────────────────────────

    /** @test */
    public function bestaetigung_am_montag_gilt_bis_freitag_und_wird_nur_einmal_gespeichert(): void
    {
        $deutsch = $this->spalte('Deutsch');

        $antwort = $this->setzen($deutsch, self::MO, '1');

        $this->assertSame(
            [self::MO => '1', self::DI => '1', self::MI => '1', self::DO => '1', self::FR => '1'],
            $antwort->json('werte')
        );
        $this->assertSame([self::DI => self::MO, self::MI => self::MO, self::DO => self::MO, self::FR => self::MO], $antwort->json('quellen'));
        $this->assertSame([self::MO => '1', self::DI => '1', self::MI => '1', self::DO => '1', self::FR => '1'], $this->woche($deutsch));
        $this->assertSame(self::MO, $this->quellen($deutsch)[self::FR]);
        $this->assertArrayNotHasKey(self::MO, $this->quellen($deutsch));

        $this->assertSame(1, PaedDiaryColumnValue::count());
        $this->assertTrue(PaedDiaryColumnValue::sole()->bis_wochenende);
    }

    /** @test */
    public function bestaetigung_am_mittwoch_gilt_nicht_rueckwirkend(): void
    {
        $mathe = $this->spalte('Mathe');

        $this->setzen($mathe, self::MI, '1');

        $this->assertSame([self::MO => null, self::DI => null, self::MI => '1', self::DO => '1', self::FR => '1'], $this->woche($mathe));
    }

    /** @test */
    public function jede_spalte_der_gruppe_gilt_unabhaengig(): void
    {
        $deutsch = $this->spalte('Deutsch');
        $mathe = $this->spalte('Mathe');
        $sachunterricht = $this->spalte('Sachunterricht');

        $this->setzen($deutsch, self::MO, '1');
        $this->setzen($mathe, self::MI, '1');

        $this->assertSame([self::MO => '1', self::DI => '1', self::MI => '1', self::DO => '1', self::FR => '1'], $this->woche($deutsch));
        $this->assertSame([self::MO => null, self::DI => null, self::MI => '1', self::DO => '1', self::FR => '1'], $this->woche($mathe));
        $this->assertSame(array_fill_keys([self::MO, self::DI, self::MI, self::DO, self::FR], null), $this->woche($sachunterricht));
    }

    /** @test */
    public function gilt_nur_fuer_das_jeweilige_kind(): void
    {
        $deutsch = $this->spalte('Deutsch');

        $this->setzen($deutsch, self::MO, '1');

        $this->assertSame(array_fill_keys([self::MO, self::DI, self::MI, self::DO, self::FR], null), $this->woche($deutsch, $this->anderesKind));
    }

    /** @test */
    public function zuruecknehmen_gilt_ab_dem_tag_der_aenderung(): void
    {
        $deutsch = $this->spalte('Deutsch');
        $this->setzen($deutsch, self::MO, '1');

        $antwort = $this->setzen($deutsch, self::DO, null);

        $this->assertSame([self::MO => '1', self::DI => '1', self::MI => '1', self::DO => null, self::FR => null], $antwort->json('werte'));
        $this->assertSame([self::MO => '1', self::DI => '1', self::MI => '1', self::DO => null, self::FR => null], $this->woche($deutsch));
    }

    /** @test */
    public function zuruecknehmen_am_tag_der_bestaetigung_hebt_die_woche_auf(): void
    {
        $deutsch = $this->spalte('Deutsch');
        $this->setzen($deutsch, self::MO, '1');

        $this->setzen($deutsch, self::MO, null);

        $this->assertSame(array_fill_keys([self::MO, self::DI, self::MI, self::DO, self::FR], null), $this->woche($deutsch));
    }

    /** @test */
    public function ampel_aenderung_gilt_ab_ihrem_tag_bis_zur_naechsten_aenderung(): void
    {
        $ampel = $this->spalte('Lesen', 'ampel');

        $this->setzen($ampel, self::MO, '2');   // In Bearbeitung ab Mo
        $this->setzen($ampel, self::MI, '1');   // Ja ab Mi

        $this->assertSame([self::MO => '2', self::DI => '2', self::MI => '1', self::DO => '1', self::FR => '1'], $this->woche($ampel));
    }

    /** @test */
    public function spaetere_ausdrueckliche_eintraege_werden_nicht_ueberschrieben(): void
    {
        $ampel = $this->spalte('Lesen', 'ampel');

        $this->setzen($ampel, self::MI, '1');   // Ja ab Mi
        $this->setzen($ampel, self::MO, '3');   // danach: Nein am Mo

        $this->assertSame([self::MO => '3', self::DI => '3', self::MI => '1', self::DO => '1', self::FR => '1'], $this->woche($ampel));
    }

    /** @test */
    public function naechste_woche_beginnt_neu(): void
    {
        $deutsch = $this->spalte('Deutsch');
        $this->setzen($deutsch, self::FR, '1');

        $this->assertSame(array_fill_keys(['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02'], null), $this->woche($deutsch, null, '2026-09-28'));
    }

    // ── Abgrenzung ───────────────────────────────────────────────────────

    /** @test */
    public function ohne_einstellung_gilt_ein_wert_nur_fuer_seinen_tag(): void
    {
        $spalte = $this->spalte('Deutsch', 'boolean', 'Anderes', false);

        $this->setzen($spalte, self::MO, '1');

        $this->assertSame([self::MO => '1', self::DI => null, self::MI => null, self::DO => null, self::FR => null], $this->woche($spalte));
    }

    /** @test */
    public function text_und_zahlspalten_gelten_auch_mit_einstellung_nur_fuer_ihren_tag(): void
    {
        $text = $this->spalte('Notiz', 'text');

        $this->setzen($text, self::MO, 'gut');

        $this->assertSame([self::MO => 'gut', self::DI => null, self::MI => null, self::DO => null, self::FR => null], $this->woche($text));
        $this->assertFalse(PaedDiaryColumnValue::sole()->bis_wochenende);
    }

    /** @test */
    public function bestehende_werte_werden_nach_dem_einschalten_nicht_uebernommen(): void
    {
        $deutsch = $this->spalte('Deutsch', 'boolean', 'Wochenplan', false);
        $this->setzen($deutsch, self::MO, '1');

        $this->user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('manage global paed diary categories', 'web'));
        $this->postJson('/paed-diary/column-groups/bis-wochenende', ['name' => 'Wochenplan', 'aktiv' => true])->assertOk();

        $this->assertSame([self::MO => '1', self::DI => null, self::MI => null, self::DO => null, self::FR => null], $this->woche($deutsch));
    }

    /** @test */
    public function gesetzte_werte_bleiben_beim_ausschalten_der_gruppe_erhalten(): void
    {
        $deutsch = $this->spalte('Deutsch');
        $this->setzen($deutsch, self::MO, '1');

        $this->user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('manage global paed diary categories', 'web'));
        $this->postJson('/paed-diary/column-groups/bis-wochenende', ['name' => 'Wochenplan', 'aktiv' => false])->assertOk();

        $this->assertSame([self::MO => '1', self::DI => '1', self::MI => '1', self::DO => '1', self::FR => '1'], $this->woche($deutsch));
    }

    // ── Einstellung der Spaltengruppe ────────────────────────────────────

    /** @test */
    public function gruppe_umschalten_setzt_alle_spalten_der_gruppe_in_allen_klassen(): void
    {
        $andereKlasse = Klasse::factory()->create();
        $a = $this->spalte('Deutsch', 'boolean', 'Wochenplan', false);
        $b = $this->spalte('Deutsch', 'boolean', 'Wochenplan', false, $andereKlasse);
        $fremd = $this->spalte('Sonstiges', 'boolean', 'Andere Gruppe', false);

        $this->user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('manage global paed diary categories', 'web'));
        $this->postJson('/paed-diary/column-groups/bis-wochenende', ['name' => 'Wochenplan', 'aktiv' => true])
            ->assertOk()->assertJsonPath('updated', 2);

        $this->assertTrue($a->fresh()->bis_wochenende);
        $this->assertTrue($b->fresh()->bis_wochenende);
        $this->assertFalse($fremd->fresh()->bis_wochenende);

        $gruppen = collect($this->getJson('/paed-diary/column-groups')->json('groups'));
        $this->assertTrue($gruppen->firstWhere('name', 'Wochenplan')['bis_wochenende']);
        $this->assertFalse($gruppen->firstWhere('name', 'Andere Gruppe')['bis_wochenende']);
    }

    /** @test */
    public function gruppe_umschalten_braucht_das_recht_zum_verwalten_der_spaltengruppen(): void
    {
        $this->spalte('Deutsch', 'boolean', 'Wochenplan', false);

        $this->postJson('/paed-diary/column-groups/bis-wochenende', ['name' => 'Wochenplan', 'aktiv' => true])->assertForbidden();
        $this->assertFalse(PaedDiaryColumn::sole()->bis_wochenende);
        $this->assertFalse($this->getJson('/paed-diary/columns/all?klasse_id=' . $this->klasse->id)->json('darf_bis_wochenende'));
    }

    /** @test */
    public function neue_und_verschobene_spalten_uebernehmen_die_einstellung_ihrer_gruppe(): void
    {
        $this->spalte('Deutsch');

        // neu angelegt in der Gruppe
        $this->postJson('/paed-diary/column', ['name' => 'Mathe', 'type' => 'boolean', 'klasse_id' => $this->klasse->id, 'category' => 'Wochenplan'])->assertOk();
        $this->assertTrue(PaedDiaryColumn::where('name', 'Mathe')->sole()->bis_wochenende);

        // in die Gruppe verschoben
        $lesen = $this->spalte('Lesen', 'boolean', null, false);
        $this->postJson("/paed-diary/column/{$lesen->id}/category", ['category' => 'Wochenplan'])->assertOk()->assertJsonPath('bis_wochenende', true);

        // aus der Gruppe herausgenommen
        $this->postJson("/paed-diary/column/{$lesen->id}/category", ['category' => 'Anderes'])->assertOk()->assertJsonPath('bis_wochenende', false);
        $this->postJson("/paed-diary/column/{$lesen->id}/category", ['category' => ''])->assertOk()->assertJsonPath('bis_wochenende', false);
    }

    /** @test */
    public function umbenennen_in_bestehende_gruppe_uebernimmt_deren_einstellung(): void
    {
        $this->spalte('Deutsch');                                         // Wochenplan, an
        $alt = $this->spalte('Mathe', 'boolean', 'Alt', false);

        $this->user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('manage global paed diary categories', 'web'));
        $this->postJson('/paed-diary/column-groups/rename', ['old_name' => 'Alt', 'new_name' => 'Wochenplan'])->assertOk();

        $this->assertTrue($alt->fresh()->bis_wochenende);
    }

    // ── Schülerakte ──────────────────────────────────────────────────────

    /** @test */
    public function schuelerakte_zeigt_uebernommene_werte_mit_herkunft(): void
    {
        $deutsch = $this->spalte('Deutsch');
        $this->setzen($deutsch, self::MO, '1');

        $daten = $this->getJson("/paed-diary/schueler/{$this->kind->id}/data?" . http_build_query(['date_from' => self::MI, 'date_to' => self::FR]))
            ->assertOk()->json();

        $this->assertSame([self::MI, self::DO, self::FR], array_keys($daten['column_values']));
        $this->assertSame('1', $daten['column_values'][self::DO][$deutsch->id]['value']);
        $this->assertSame(self::MO, $daten['column_values'][self::DO][$deutsch->id]['uebernommen_von']);
        $this->assertTrue(collect($daten['columns'])->firstWhere('id', $deutsch->id)['bis_wochenende']);
    }
}
