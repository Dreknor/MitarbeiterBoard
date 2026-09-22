<?php

namespace Tests\Unit\Models;

use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;
use App\Models\User;
use Tests\TestCase;

class PaedDiaryCategoryTest extends TestCase
{
    /** @test */
    public function scopeGlobal_liefert_nur_kategorien_ohne_user_id(): void
    {
        PaedDiaryCategory::factory()->create(['name' => 'Global', 'user_id' => null]);
        $ownedUser = User::factory()->create();
        PaedDiaryCategory::factory()->ownedBy($ownedUser)->create(['name' => 'Eigene']);

        $global = PaedDiaryCategory::global()->get();

        $this->assertCount(1, $global);
        $this->assertEquals('Global', $global->first()->name);
    }

    /** @test */
    public function scopeForUser_liefert_globale_und_eigene_kategorien(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        PaedDiaryCategory::factory()->create(['name' => 'Global', 'user_id' => null]);
        PaedDiaryCategory::factory()->ownedBy($user)->create(['name' => 'Eigene']);
        PaedDiaryCategory::factory()->ownedBy($other)->create(['name' => 'Andere']);

        $cats = PaedDiaryCategory::forUser($user->id)->get();

        $this->assertCount(2, $cats);
        $this->assertEqualsCanonicalizing(['Eigene', 'Global'], $cats->pluck('name')->toArray());
    }

    /** @test */
    public function isGlobal_und_isOwnedBy_funktionieren_korrekt(): void
    {
        $cat = new PaedDiaryCategory(['name' => 'Test', 'user_id' => null]);
        $this->assertTrue($cat->isGlobal());
        $this->assertFalse($cat->isOwnedBy(1));

        $cat->user_id = 5;
        $this->assertFalse($cat->isGlobal());
        $this->assertTrue($cat->isOwnedBy(5));
        $this->assertFalse($cat->isOwnedBy(3));
    }

    /** @test */
    public function hiddenByUsers_relationship_funktioniert(): void
    {
        $user = User::factory()->create();
        $cat  = PaedDiaryCategory::factory()->create(['name' => 'Test', 'user_id' => null]);
        $cat->hiddenByUsers()->attach($user->id);

        $this->assertCount(1, $cat->hiddenByUsers);
        $this->assertEquals($user->id, $cat->hiddenByUsers->first()->id);
    }

    /** @test */
    public function entries_relationship_funktioniert(): void
    {
        $cat   = PaedDiaryCategory::factory()->create();
        $entry = PaedDiaryEntry::factory()->withCategory($cat)->create();

        $this->assertCount(1, $cat->entries);
        $this->assertEquals($entry->id, $cat->entries->first()->id);
    }
}

