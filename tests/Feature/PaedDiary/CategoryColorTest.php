<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 * Kategorien-Verwaltung: Farben anlegen/ändern, Rückgabe von Farbe, Sichtbarkeit und Anzahl Einträge,
 * neue Verwaltungsseite.
 */
class CategoryColorTest extends TestCase
{
    use MocksExternalApis;

    /** @test */
    public function eigene_kategorie_mit_farbe_anlegen_und_farbe_aendern(): void
    {
        $this->actingAsWithPermission('view paed diary');

        $id = $this->postJson('/paed-diary/categories', ['name' => 'Lernen', 'color' => '#1D4ED8'])
            ->assertOk()
            ->assertJsonPath('category.color', '#1d4ed8')
            ->json('category.id');

        $this->putJson("/paed-diary/categories/{$id}/rename", ['color' => '#047857'])
            ->assertOk()->assertJsonPath('color', '#047857')->assertJsonPath('name', 'Lernen');

        $this->putJson("/paed-diary/categories/{$id}/rename", ['color' => null])->assertOk();
        $this->assertNull(PaedDiaryCategory::find($id)->color);

        $this->putJson("/paed-diary/categories/{$id}/rename", ['color' => 'rot'])->assertStatus(422);
    }

    /** @test */
    public function globale_kategorie_farbe_nur_mit_recht(): void
    {
        $global = PaedDiaryCategory::create(['name' => 'Verhalten', 'user_id' => null]);

        $this->actingAsWithPermission('view paed diary');
        $this->putJson("/paed-diary/categories/global/{$global->id}", ['color' => '#be123c'])->assertForbidden();

        $this->actingAsWithPermission('view paed diary', 'manage global paed diary categories');
        $this->putJson("/paed-diary/categories/global/{$global->id}", ['color' => '#be123c'])->assertOk();
        $this->assertSame('#be123c', $global->fresh()->color);
    }

    /** @test */
    public function liste_liefert_farbe_sichtbarkeit_und_anzahl(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $kat = PaedDiaryCategory::create(['name' => 'Elternkontakt', 'color' => '#b45309', 'user_id' => $user->id]);
        PaedDiaryEntry::factory()->count(2)->create(['category_id' => $kat->id, 'klasse_id' => Klasse::factory()->create()->id]);
        $user->hiddenPaedDiaryCategories()->attach($kat->id);

        $eintrag = collect($this->getJson('/paed-diary/categories')->assertOk()->json('categories'))->firstWhere('id', $kat->id);

        $this->assertSame('#b45309', $eintrag['color']);
        $this->assertTrue($eintrag['hidden']);
        $this->assertSame(2, $eintrag['entries_count']);
    }

    /** @test */
    public function verwaltungsseite_zeigt_neue_oberflaeche(): void
    {
        $this->withoutVite();
        $this->actingAsWithPermission('view paed diary');

        $this->get('/paed-diary/categories/manage')
            ->assertOk()
            ->assertSee('Kategorien &amp; Spaltengruppen', false)
            ->assertSee('kategorienVerwaltung', false);
    }
}
