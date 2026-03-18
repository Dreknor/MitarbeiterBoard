<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryCategory;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 *
 * Testet den POST-Endpoint toggle-hidden sowie die hidden_category_ids im weekData-Response.
 */
class CategoryToggleTest extends TestCase
{
    use MocksExternalApis;

    /** @test */
    public function user_kann_globale_kategorie_ausblenden(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $cat  = PaedDiaryCategory::create(['name' => 'Verhalten', 'user_id' => null]);

        $response = $this->postJson("/paed-diary/categories/{$cat->id}/toggle-hidden");

        $response->assertOk()->assertJson(['success' => true, 'hidden' => true]);

        $this->assertDatabaseHas('paed_diary_user_hidden_categories', [
            'user_id'               => $user->id,
            'paed_diary_category_id' => $cat->id,
        ]);
    }

    /** @test */
    public function user_kann_ausgeblendete_kategorie_wieder_einblenden(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $cat  = PaedDiaryCategory::create(['name' => 'Verhalten', 'user_id' => null]);
        $user->hiddenPaedDiaryCategories()->attach($cat->id);

        $response = $this->postJson("/paed-diary/categories/{$cat->id}/toggle-hidden");

        $response->assertOk()->assertJson(['success' => true, 'hidden' => false]);

        $this->assertDatabaseMissing('paed_diary_user_hidden_categories', [
            'user_id'               => $user->id,
            'paed_diary_category_id' => $cat->id,
        ]);
    }

    /** @test */
    public function user_kann_fremde_kategorie_nicht_togglen(): void
    {
        $this->actingAsWithPermission('view paed diary');
        $other = User::factory()->create();
        $cat   = PaedDiaryCategory::create(['name' => 'Fremd', 'user_id' => $other->id]);

        $response = $this->postJson("/paed-diary/categories/{$cat->id}/toggle-hidden");

        $response->assertStatus(403);
    }

    /** @test */
    public function weekData_enthaelt_hidden_category_ids(): void
    {
        $this->fakeAllExternalApis();

        $user   = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);

        $cat = PaedDiaryCategory::create(['name' => 'Verhalten', 'user_id' => null]);
        $user->hiddenPaedDiaryCategories()->attach($cat->id);

        $response = $this->getJson(route('paedDiary.week', [
            'klasse_id'  => $klasse->id,
            'week_start' => now()->startOfWeek()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertArrayHasKey('hidden_category_ids', $response->json());
        $this->assertContains($cat->id, $response->json('hidden_category_ids'));
    }

    /** @test */
    public function toggle_ist_user_spezifisch(): void
    {
        $user1 = $this->actingAsWithPermission('view paed diary');
        $user2 = User::factory()->create();
        $cat   = PaedDiaryCategory::create(['name' => 'Verhalten', 'user_id' => null]);

        $this->postJson("/paed-diary/categories/{$cat->id}/toggle-hidden");

        // User 1 hat die Kategorie ausgeblendet
        $this->assertDatabaseHas('paed_diary_user_hidden_categories', [
            'user_id'               => $user1->id,
            'paed_diary_category_id' => $cat->id,
        ]);

        // User 2 nicht
        $this->assertDatabaseMissing('paed_diary_user_hidden_categories', [
            'user_id'               => $user2->id,
            'paed_diary_category_id' => $cat->id,
        ]);
    }

    /** @test */
    public function user_kann_eigene_kategorie_togglen(): void
    {
        $user = $this->actingAsWithPermission('view paed diary');
        $cat  = PaedDiaryCategory::create(['name' => 'Eigene', 'user_id' => $user->id]);

        $response = $this->postJson("/paed-diary/categories/{$cat->id}/toggle-hidden");

        $response->assertOk()->assertJson(['success' => true, 'hidden' => true]);
    }

    /** @test */
    public function weekData_hat_leeres_hidden_category_ids_wenn_keine_kategorie_ausgeblendet(): void
    {
        $this->fakeAllExternalApis();

        $user   = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);

        $response = $this->getJson(route('paedDiary.week', [
            'klasse_id'  => $klasse->id,
            'week_start' => now()->startOfWeek()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertArrayHasKey('hidden_category_ids', $response->json());
        $this->assertEmpty($response->json('hidden_category_ids'));
    }
}

