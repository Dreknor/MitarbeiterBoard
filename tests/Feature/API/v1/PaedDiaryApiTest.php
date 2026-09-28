<?php

namespace Tests\Feature\API\v1;

use App\Models\Klasse;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use App\Models\User;

class PaedDiaryApiTest extends ApiTestCase
{
    /** @test */
    public function einzeleintrag_wird_gespeichert(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $category = PaedDiaryCategory::factory()->create();

        $response = $this->postJson(self::API . '/paed-diary/entries', [
            'schueler_id' => $schueler->id,
            'category_id' => $category->id,
            'entry_date' => '2026-09-23',
            'content' => 'Hat eigenständig Konflikt in der Pause geschlichtet.',
            'is_dossier_only' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.schueler_ids', [$schueler->id])
            ->assertJsonPath('data.class_id', $klasse->id)
            ->assertJsonPath('data.entry_date', '2026-09-23')
            ->assertJsonPath('data.is_dossier_only', false)
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('data.content', 'Hat eigenständig Konflikt in der Pause geschlichtet.');

        $entry = PaedDiaryEntry::findOrFail($response->json('data.id'));
        $this->assertSame($user->id, $entry->user_id);
        $this->assertFalse($entry->dossier_only);
        // Inhalt wird verschlüsselt gespeichert
        $this->assertNotSame('Hat eigenständig Konflikt in der Pause geschlichtet.', $entry->getRawOriginal('content'));
    }

    /** @test */
    public function bulk_eintrag_legt_einen_eintrag_pro_klasse_an(): void
    {
        $klasseA = Klasse::factory()->create();
        $klasseB = Klasse::factory()->create();
        $a1 = Schueler::factory()->create(['klasse_id' => $klasseA->id]);
        $a2 = Schueler::factory()->create(['klasse_id' => $klasseA->id]);
        $b1 = Schueler::factory()->create(['klasse_id' => $klasseB->id]);
        $this->actingAsTeacher(['view paed diary'], [$klasseA, $klasseB]);

        $response = $this->postJson(self::API . '/paed-diary/bulk-entries', [
            'schueler_ids' => [$a1->id, $a2->id, $b1->id],
            'entry_date' => '2026-09-23',
            'content' => 'Teilnahme an der Exkursion ins Museum.',
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.entries_created', 2)
            ->assertJsonPath('meta.students_count', 3);

        $this->assertDatabaseCount('paed_diary_entries', 2);
        $this->assertDatabaseCount('paed_diary_entry_schueler', 3);
    }

    /** @test */
    public function bulk_eintrag_mit_fremdem_schueler_wird_abgelehnt_und_nichts_gespeichert(): void
    {
        [$klasse, $eigen] = $this->classWithStudent();
        [, $fremd] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->postJson(self::API . '/paed-diary/bulk-entries', [
            'schueler_ids' => [$eigen->id, $fremd->id],
            'entry_date' => '2026-09-23',
            'content' => 'Test',
        ])->assertForbidden()->assertJsonPath('forbidden_schueler_ids', [$fremd->id]);

        $this->assertDatabaseCount('paed_diary_entries', 0);
    }

    /** @test */
    public function validierungsfehler_liefern_422(): void
    {
        [$klasse] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->postJson(self::API . '/paed-diary/entries', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schueler_id', 'entry_date', 'content']);

        $this->postJson(self::API . '/paed-diary/entries', [
            'schueler_id' => 999999,
            'entry_date' => '23.09.2026',
            'content' => 'x',
            'category_id' => 'abc',
            'is_dossier_only' => 'vielleicht',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['schueler_id', 'entry_date', 'category_id', 'is_dossier_only']);

        $this->postJson(self::API . '/paed-diary/bulk-entries', [
            'schueler_ids' => 'nicht-array',
            'entry_date' => '2026-09-23',
            'content' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors(['schueler_ids']);
    }

    /** @test */
    public function fremde_persoenliche_kategorie_wird_abgelehnt(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $fremdeKategorie = PaedDiaryCategory::factory()->ownedBy(User::factory()->create())->create();

        $this->postJson(self::API . '/paed-diary/entries', [
            'schueler_id' => $schueler->id,
            'category_id' => $fremdeKategorie->id,
            'entry_date' => '2026-09-23',
            'content' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors('category_id');
    }

    /** @test */
    public function eintrag_fuer_fremden_schueler_liefert_403(): void
    {
        [, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary'], [Klasse::factory()->create()]);

        $this->postJson(self::API . '/paed-diary/entries', [
            'schueler_id' => $schueler->id,
            'entry_date' => '2026-09-23',
            'content' => 'x',
        ])->assertForbidden();
    }

    /** @test */
    public function eintrag_anzeigen_aktualisieren_und_loeschen(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $entry = PaedDiaryEntry::factory()->completed()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id, 'content' => 'Alt']);
        $entry->schueler()->attach($schueler->id);

        $this->getJson(self::API . "/paed-diary/entries/{$entry->id}")
            ->assertOk()->assertJsonPath('data.content', 'Alt')->assertJsonPath('data.is_own', true);

        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}", ['content' => 'Neu', 'entry_date' => '2026-09-22'])
            ->assertOk()
            ->assertJsonPath('data.content', 'Neu')
            ->assertJsonPath('data.entry_date', '2026-09-22');

        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}", ['entry_date' => 'gestern'])
            ->assertStatus(422);

        $this->deleteJson(self::API . "/paed-diary/entries/{$entry->id}")->assertNoContent();
        $this->assertDatabaseMissing('paed_diary_entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('paed_diary_entry_schueler', ['paed_diary_entry_id' => $entry->id]);
    }

    /** @test */
    public function update_mit_schueler_aus_anderer_klasse_liefert_422(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        [$andere, $fremd] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse, $andere]);
        $entry = PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id]);
        $entry->schueler()->attach($schueler->id);

        $this->putJson(self::API . "/paed-diary/entries/{$entry->id}", ['schueler_ids' => [$fremd->id]])
            ->assertStatus(422)->assertJsonValidationErrors('schueler_ids');
    }

    /** @test */
    public function nur_autor_oder_sonderrecht_darf_loeschen(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $kollegin = User::factory()->create();
        $entry = PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'user_id' => $kollegin->id]);
        $entry->schueler()->attach($schueler->id);

        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        // Lesen und Bearbeiten erlaubt (Klassenzugriff), Löschen nicht
        $this->getJson(self::API . "/paed-diary/entries/{$entry->id}")->assertOk();
        $this->deleteJson(self::API . "/paed-diary/entries/{$entry->id}")->assertForbidden();
        $this->assertDatabaseHas('paed_diary_entries', ['id' => $entry->id]);
    }

    /** @test */
    public function vertraulicher_fremder_eintrag_liefert_403(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $entry = PaedDiaryEntry::factory()->dossierOnly()->create(['klasse_id' => $klasse->id, 'user_id' => User::factory()->create()->id]);
        $entry->schueler()->attach($schueler->id);

        $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $this->getJson(self::API . "/paed-diary/entries/{$entry->id}")->assertForbidden();

        $this->actingAsAdmin();
        $this->getJson(self::API . "/paed-diary/entries/{$entry->id}")->assertOk();
    }

    /** @test */
    public function schuelereintraege_sind_paginiert_und_filterbar(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);
        $category = PaedDiaryCategory::factory()->create();

        foreach (['2026-09-01', '2026-09-10', '2026-09-20'] as $i => $datum) {
            $e = PaedDiaryEntry::factory()->create([
                'klasse_id' => $klasse->id, 'user_id' => $user->id, 'datum' => $datum,
                'category_id' => $i === 2 ? $category->id : null,
            ]);
            $e->schueler()->attach($schueler->id);
        }

        $this->getJson(self::API . "/students/{$schueler->id}/paed-diary/entries?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.entry_date', '2026-09-20')
            ->assertJsonPath('meta.total', 3);

        $this->getJson(self::API . "/students/{$schueler->id}/paed-diary/entries?from_date=2026-09-05&to_date=2026-09-15")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.entry_date', '2026-09-10');

        // to_date ist inklusiv
        $this->getJson(self::API . "/students/{$schueler->id}/paed-diary/entries?from_date=2026-09-20&to_date=2026-09-20")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->getJson(self::API . "/students/{$schueler->id}/paed-diary/entries?category_id={$category->id}")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->getJson(self::API . "/students/{$schueler->id}/paed-diary/entries?from_date=2026-09-15&to_date=2026-09-01")
            ->assertStatus(422);
    }

    /** @test */
    public function kategorien_liefern_globale_und_eigene_mit_farbe(): void
    {
        $user = $this->actingAsTeacher();
        PaedDiaryCategory::factory()->create(['name' => 'Global', 'color' => '#FF0000']);
        PaedDiaryCategory::factory()->ownedBy($user)->create(['name' => 'Eigen']);
        PaedDiaryCategory::factory()->ownedBy(User::factory()->create())->create(['name' => 'Fremd']);

        $names = collect($this->getJson(self::API . '/paed-diary/categories')->assertOk()->json('data'))->pluck('name');

        $this->assertEqualsCanonicalizing(['Global', 'Eigen'], $names->all());
    }
}
