<?php

namespace Tests\Unit\Models;

use App\Models\PaedDiaryCategory;
use App\Models\User;
use Tests\TestCase;

class UserHiddenCategoriesTest extends TestCase
{
    /** @test */
    public function user_hiddenPaedDiaryCategories_relationship_funktioniert(): void
    {
        $user = User::factory()->create();
        $cat1 = PaedDiaryCategory::factory()->create(['name' => 'Kat1', 'user_id' => null]);
        $cat2 = PaedDiaryCategory::factory()->create(['name' => 'Kat2', 'user_id' => null]);
        $user->hiddenPaedDiaryCategories()->attach([$cat1->id, $cat2->id]);

        $this->assertCount(2, $user->hiddenPaedDiaryCategories);
    }

    /** @test */
    public function ausgeblendete_kategorien_sind_user_spezifisch(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $cat   = PaedDiaryCategory::factory()->create(['name' => 'Global', 'user_id' => null]);

        $user1->hiddenPaedDiaryCategories()->attach($cat->id);

        $this->assertCount(1, $user1->hiddenPaedDiaryCategories);
        $this->assertCount(0, $user2->hiddenPaedDiaryCategories);
    }

    /** @test */
    public function cascade_delete_entfernt_hidden_eintraege_beim_loeschen_der_kategorie(): void
    {
        $user = User::factory()->create();
        $cat  = PaedDiaryCategory::factory()->create(['name' => 'Test', 'user_id' => null]);
        $user->hiddenPaedDiaryCategories()->attach($cat->id);

        $this->assertDatabaseHas('paed_diary_user_hidden_categories', [
            'user_id'                => $user->id,
            'paed_diary_category_id' => $cat->id,
        ]);

        $cat->delete();

        $this->assertDatabaseMissing('paed_diary_user_hidden_categories', [
            'user_id'                => $user->id,
            'paed_diary_category_id' => $cat->id,
        ]);
    }
}

