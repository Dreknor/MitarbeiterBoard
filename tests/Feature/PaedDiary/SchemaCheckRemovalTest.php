<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryColumn;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 *
 * Stellt sicher, dass alle PaedDiary-Funktionen nach Entfernung der
 * Schema::hasColumn-Guards weiterhin korrekt arbeiten.
 */
class SchemaCheckRemovalTest extends TestCase
{
    use MocksExternalApis;

    /** @test */
    public function weekData_endpoint_liefert_spalten_mit_category_und_deactivated_from(): void
    {
        $this->fakeAllExternalApis();

        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);

        $col = PaedDiaryColumn::create([
            'klasse_id'  => $klasse->id,
            'name'       => 'Test',
            'slug'       => 'test',
            'type'       => 'text',
            'sort_order' => 1,
            'category'   => 'Verhalten',
        ]);

        $response = $this->getJson(route('paedDiary.week', [
            'klasse_id'  => $klasse->id,
            'week_start' => now()->startOfWeek()->toDateString(),
        ]));

        $response->assertOk();

        $columns = collect($response->json('columns'));
        $testCol = $columns->firstWhere('id', $col->id);

        $this->assertNotNull($testCol, 'Spalte nicht in Antwort vorhanden');
        $this->assertSame('Verhalten', $testCol['category']);
        $this->assertArrayHasKey('deactivated_from', $testCol);
    }

    /** @test */
    public function weekData_liefert_show_column_categories_direkt(): void
    {
        $this->fakeAllExternalApis();

        $user = $this->actingAsWithPermission('view paed diary');
        $user->show_column_categories = true;
        $user->save();

        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);

        $response = $this->getJson(route('paedDiary.week', [
            'klasse_id'  => $klasse->id,
            'week_start' => now()->startOfWeek()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertTrue($response->json('show_column_categories'));
    }

    /** @test */
    public function storeColumn_speichert_category_feld_direkt(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);

        $response = $this->postJson(route('paedDiary.column.store'), [
            'klasse_id' => $klasse->id,
            'name'      => 'Neue Spalte',
            'type'      => 'text',
            'category'  => 'Sozial',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('paed_diary_columns', [
            'klasse_id' => $klasse->id,
            'name'      => 'Neue Spalte',
            'category'  => 'Sozial',
        ]);
        $this->assertSame('Sozial', $response->json('column.category'));
    }

    /** @test */
    public function storeColumn_ohne_category_speichert_null(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);

        $response = $this->postJson(route('paedDiary.column.store'), [
            'klasse_id' => $klasse->id,
            'name'      => 'Spalte ohne Kategorie',
            'type'      => 'text',
        ]);

        $response->assertOk();
        $this->assertNull($response->json('column.category'));
    }

    /** @test */
    public function columnsAll_liefert_category_direkt(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);

        PaedDiaryColumn::create([
            'klasse_id'  => $klasse->id,
            'name'       => 'KatSpalte',
            'slug'       => 'katspalte',
            'type'       => 'text',
            'sort_order' => 1,
            'category'   => 'Soziales',
        ]);

        $response = $this->getJson(route('paedDiary.columns.all', [
            'klasse_id' => $klasse->id,
        ]));

        $response->assertOk();
        $col = collect($response->json('columns'))->first();
        $this->assertSame('Soziales', $col['category']);
    }

    /** @test */
    public function updateColumnCategory_aktualisiert_ohne_schema_check(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);

        $column = PaedDiaryColumn::create([
            'klasse_id'  => $klasse->id,
            'name'       => 'KatUpdate',
            'slug'       => 'kat-update',
            'type'       => 'text',
            'sort_order' => 1,
        ]);

        $response = $this->postJson(route('paedDiary.column.updateCategory', $column), [
            'category' => 'Motorik',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('paed_diary_columns', [
            'id'       => $column->id,
            'category' => 'Motorik',
        ]);
    }

    /** @test */
    public function destroyColumn_setzt_deactivated_from_ohne_schema_check(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);

        $column = PaedDiaryColumn::create([
            'klasse_id'  => $klasse->id,
            'name'       => 'ZuDeaktivieren',
            'slug'       => 'zu-deaktivieren',
            'type'       => 'text',
            'sort_order' => 1,
        ]);

        $weekStart = now()->startOfWeek()->toDateString();

        $response = $this->deleteJson(route('paedDiary.column.destroy', $column), [
            'klasse_id'  => $klasse->id,
            'week_start' => $weekStart,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $column->refresh();
        $this->assertNotNull($column->deactivated_from);
        $this->assertSame($weekStart, $column->deactivated_from->toDateString());
    }

    /** @test */
    public function updateShowCategoriesSetting_speichert_einstellung_ohne_schema_check(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');

        $response = $this->postJson(route('paedDiary.settings.showCategories'), [
            'show_column_categories' => true,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'show_column_categories' => true]);

        $this->assertDatabaseHas('users', [
            'id'                    => $user->id,
            'show_column_categories' => true,
        ]);
    }
}

