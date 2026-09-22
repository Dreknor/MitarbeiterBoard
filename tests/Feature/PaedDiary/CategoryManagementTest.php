<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryEntry;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 * Tests für PaedDiaryCategoryController
 * Abdeckung: manageView, getCategories, storeCategory, renameCategory,
 * deleteCategory, storeGlobalCategory, updateGlobalCategory, deleteGlobalCategory,
 * getColumnGroups, renameColumnGroup.
 */
class CategoryManagementTest extends TestCase
{
    use MocksExternalApis;

    // ── manageView ────────────────────────────────────────────────────────────

    /** @test */
    public function manage_view_ist_erreichbar_mit_permission(): void
    {
        $this->actingAsWithPermission('view paed diary');
        $this->get('/paed-diary/categories/manage')->assertOk();
    }

    /** @test */
    public function manage_view_ist_nicht_erreichbar_ohne_permission(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/paed-diary/categories/manage')->assertForbidden();
    }

    // ── getCategories ─────────────────────────────────────────────────────────

    /** @test */
    public function get_categories_liefert_globale_und_eigene(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        PaedDiaryCategory::create(['name' => 'Global', 'user_id' => null]);
        PaedDiaryCategory::create(['name' => 'Eigene', 'user_id' => $user->id]);

        $response = $this->getJson('/paed-diary/categories');
        $response->assertOk();

        $names = collect($response->json('categories'))->pluck('name');
        $this->assertContains('Global', $names);
        $this->assertContains('Eigene', $names);
    }

    // ── storeCategory ─────────────────────────────────────────────────────────

    /** @test */
    public function user_kann_eigene_kategorie_erstellen(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $response = $this->postJson('/paed-diary/categories', ['name' => 'MeineKat']);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('paed_diary_categories', [
            'name'    => 'MeineKat',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function kategorie_erstellen_schlaegt_bei_duplikat_fehl(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        PaedDiaryCategory::create(['name' => 'Doppelt', 'user_id' => $user->id]);

        $this->postJson('/paed-diary/categories', ['name' => 'Doppelt'])
             ->assertStatus(422);
    }

    // ── renameCategory ────────────────────────────────────────────────────────

    /** @test */
    public function user_kann_eigene_kategorie_umbenennen(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $cat  = PaedDiaryCategory::create(['name' => 'Alt', 'user_id' => $user->id]);

        $this->putJson("/paed-diary/categories/{$cat->id}/rename", ['name' => 'Neu'])
             ->assertOk()->assertJsonPath('success', true);

        $this->assertSame('Neu', $cat->fresh()->name);
    }

    /** @test */
    public function user_kann_fremde_kategorie_nicht_umbenennen(): void
    {
        $this->actingAsWithPermission('view paed diary');
        $other = User::factory()->create();
        $cat   = PaedDiaryCategory::create(['name' => 'Fremd', 'user_id' => $other->id]);

        $this->putJson("/paed-diary/categories/{$cat->id}/rename", ['name' => 'Hack'])
             ->assertForbidden();
    }

    // ── deleteCategory ────────────────────────────────────────────────────────

    /** @test */
    public function user_kann_eigene_kategorie_loeschen(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $cat  = PaedDiaryCategory::create(['name' => 'Weg', 'user_id' => $user->id]);

        $this->deleteJson("/paed-diary/categories/{$cat->id}")->assertOk();
        $this->assertDatabaseMissing('paed_diary_categories', ['id' => $cat->id]);
    }

    /** @test */
    public function beim_loeschen_wird_category_id_in_entries_auf_null_gesetzt(): void
    {
        $user   = $this->actingAsWithPermission('view paed diary');
        $cat    = PaedDiaryCategory::create(['name' => 'Weg', 'user_id' => $user->id]);
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);
        $entry  = PaedDiaryEntry::factory()->create([
            'klasse_id'   => $klasse->id,
            'user_id'     => $user->id,
            'category_id' => $cat->id,
        ]);

        $this->deleteJson("/paed-diary/categories/{$cat->id}")->assertOk();
        $this->assertNull($entry->fresh()->category_id);
    }

    // ── Globale Kategorien ────────────────────────────────────────────────────

    /** @test */
    public function user_ohne_permission_kann_keine_globale_kategorie_erstellen(): void
    {
        $this->actingAsWithPermission('view paed diary');
        $this->postJson('/paed-diary/categories/global', ['name' => 'Nope'])
             ->assertForbidden();
    }

    /** @test */
    public function user_mit_permission_kann_globale_kategorie_erstellen(): void
    {
        $this->actingAsWithPermission('view paed diary', 'manage global paed diary categories');

        $this->postJson('/paed-diary/categories/global', ['name' => 'GlobalNeu'])
             ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('paed_diary_categories', [
            'name'    => 'GlobalNeu',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function user_mit_permission_kann_globale_kategorie_umbenennen(): void
    {
        $this->actingAsWithPermission('view paed diary', 'manage global paed diary categories');
        $cat = PaedDiaryCategory::create(['name' => 'GlobalAlt', 'user_id' => null]);

        $this->putJson("/paed-diary/categories/global/{$cat->id}", ['name' => 'GlobalNeu'])
             ->assertOk()->assertJsonPath('success', true);

        $this->assertSame('GlobalNeu', $cat->fresh()->name);
    }

    /** @test */
    public function user_mit_permission_kann_globale_kategorie_loeschen(): void
    {
        $this->actingAsWithPermission('view paed diary', 'manage global paed diary categories');
        $cat = PaedDiaryCategory::create(['name' => 'GlobalWeg', 'user_id' => null]);

        $this->deleteJson("/paed-diary/categories/global/{$cat->id}")->assertOk();
        $this->assertDatabaseMissing('paed_diary_categories', ['id' => $cat->id]);
    }

    // ── Spaltengruppen ────────────────────────────────────────────────────────

    /** @test */
    public function column_groups_endpoint_liefert_gruppen_mit_anzahl(): void
    {
        $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();

        PaedDiaryColumn::create(['klasse_id' => $klasse->id, 'name' => 'Sp1', 'slug' => 'sp1', 'type' => 'text', 'category' => 'Verhalten']);
        PaedDiaryColumn::create(['klasse_id' => $klasse->id, 'name' => 'Sp2', 'slug' => 'sp2', 'type' => 'text', 'category' => 'Verhalten']);
        PaedDiaryColumn::create(['klasse_id' => $klasse->id, 'name' => 'Sp3', 'slug' => 'sp3', 'type' => 'text', 'category' => 'Sozial']);

        $response = $this->getJson('/paed-diary/column-groups');
        $response->assertOk();

        $groups = collect($response->json('groups'));
        $this->assertSame(2, (int) $groups->firstWhere('name', 'Verhalten')['count']);
        $this->assertSame(1, (int) $groups->firstWhere('name', 'Sozial')['count']);
    }

    /** @test */
    public function spaltengruppe_umbenennen_aktualisiert_alle_spalten(): void
    {
        $this->actingAsWithPermission('view paed diary', 'manage global paed diary categories');
        $klasse = Klasse::factory()->create();

        PaedDiaryColumn::create(['klasse_id' => $klasse->id, 'name' => 'S1', 'slug' => 'sg-s1', 'type' => 'text', 'category' => 'Alt']);
        PaedDiaryColumn::create(['klasse_id' => $klasse->id, 'name' => 'S2', 'slug' => 'sg-s2', 'type' => 'text', 'category' => 'Alt']);

        $this->postJson('/paed-diary/column-groups/rename', [
            'old_name' => 'Alt',
            'new_name' => 'Neu',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('paed_diary_columns', ['category' => 'Alt']);
        $this->assertSame(2, PaedDiaryColumn::where('category', 'Neu')->count());
    }

    /** @test */
    public function spaltengruppe_umbenennen_ohne_permission_ist_verboten(): void
    {
        $this->actingAsWithPermission('view paed diary');

        $this->postJson('/paed-diary/column-groups/rename', [
            'old_name' => 'Alt',
            'new_name' => 'Neu',
        ])->assertForbidden();
    }
}

