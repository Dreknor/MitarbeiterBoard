<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryColumn;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 * Tests für PaedDiaryColumnController:
 * - Anlegen einer Spalte für mehrere Klassen gleichzeitig (klasse_ids)
 * - Kopieren einer bestehenden Spalte in andere Klassen (copyColumn)
 */
class ColumnMultiClassAndCopyTest extends TestCase
{
    use MocksExternalApis;

    private function klasseFuerUser($user): Klasse
    {
        $klasse = Klasse::factory()->create();
        $user->paed_klassen()->attach($klasse->id);
        return $klasse;
    }

    /** @test */
    public function spalte_kann_fuer_mehrere_klassen_gleichzeitig_angelegt_werden(): void
    {
        $user   = $this->actingAsWithPermission('view paed diary');
        $klasse1 = $this->klasseFuerUser($user);
        $klasse2 = $this->klasseFuerUser($user);

        $response = $this->postJson('/paed-diary/column', [
            'name'       => 'Hausaufgaben',
            'type'       => 'boolean',
            'klasse_ids' => [$klasse1->id, $klasse2->id],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(2, count($response->json('columns')));
        $this->assertDatabaseHas('paed_diary_columns', ['klasse_id' => $klasse1->id, 'name' => 'Hausaufgaben']);
        $this->assertDatabaseHas('paed_diary_columns', ['klasse_id' => $klasse2->id, 'name' => 'Hausaufgaben']);
    }

    /** @test */
    public function spalte_anlegen_ohne_klasse_angabe_scheitert(): void
    {
        $this->actingAsWithPermission('view paed diary');

        $response = $this->postJson('/paed-diary/column', [
            'name' => 'Ohne Klasse',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function bestehende_spalte_kann_in_andere_klasse_kopiert_werden(): void
    {
        $user    = $this->actingAsWithPermission('view paed diary');
        $klasse1 = $this->klasseFuerUser($user);
        $klasse2 = $this->klasseFuerUser($user);

        $column = PaedDiaryColumn::create([
            'klasse_id' => $klasse1->id,
            'name'      => 'Verhalten',
            'slug'      => 'verhalten',
            'type'      => 'ampel',
            'category'  => 'Sozial',
        ]);

        $response = $this->postJson("/paed-diary/column/{$column->id}/copy", [
            'klasse_ids' => [$klasse2->id],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, count($response->json('created')));
        $this->assertSame(0, count($response->json('skipped')));
        $this->assertDatabaseHas('paed_diary_columns', [
            'klasse_id' => $klasse2->id,
            'name'      => 'Verhalten',
            'type'      => 'ampel',
            'category'  => 'Sozial',
        ]);
    }

    /** @test */
    public function kopieren_wird_uebersprungen_wenn_name_in_zielklasse_bereits_existiert(): void
    {
        $user    = $this->actingAsWithPermission('view paed diary');
        $klasse1 = $this->klasseFuerUser($user);
        $klasse2 = $this->klasseFuerUser($user);

        $column = PaedDiaryColumn::create([
            'klasse_id' => $klasse1->id,
            'name'      => 'Verhalten',
            'slug'      => 'verhalten',
            'type'      => 'ampel',
        ]);
        PaedDiaryColumn::create([
            'klasse_id' => $klasse2->id,
            'name'      => 'Verhalten',
            'slug'      => 'verhalten',
            'type'      => 'ampel',
        ]);

        $response = $this->postJson("/paed-diary/column/{$column->id}/copy", [
            'klasse_ids' => [$klasse2->id],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(0, count($response->json('created')));
        $this->assertSame(1, count($response->json('skipped')));
        $this->assertSame(1, PaedDiaryColumn::where('klasse_id', $klasse2->id)->where('name', 'Verhalten')->count());
        $this->assertDatabaseCount('paed_diary_columns', 2);
    }

    /** @test */
    public function kopieren_ohne_zugriff_auf_quellklasse_wird_verweigert(): void
    {
        $this->actingAsWithPermission('view paed diary');
        $foreignKlasse = Klasse::factory()->create();
        $column = PaedDiaryColumn::create([
            'klasse_id' => $foreignKlasse->id,
            'name'      => 'Fremd',
            'slug'      => 'fremd',
            'type'      => 'text',
        ]);

        $response = $this->postJson("/paed-diary/column/{$column->id}/copy", [
            'klasse_ids' => [$foreignKlasse->id],
        ]);

        $response->assertNotFound();
    }
}
