<?php

namespace Tests\Unit\Migrations;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests für Hidden-Categories Pivot + Permission
 */
class CreateHiddenCategoriesPivotTest extends TestCase
{
    /** @test */
    public function hidden_categories_pivot_tabelle_existiert_mit_korrekten_spalten(): void
    {
        $this->assertTrue(
            Schema::hasTable('paed_diary_user_hidden_categories'),
            'Tabelle paed_diary_user_hidden_categories fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_user_hidden_categories', 'user_id'),
            'Spalte user_id fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_user_hidden_categories', 'paed_diary_category_id'),
            'Spalte paed_diary_category_id fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_user_hidden_categories', 'created_at'),
            'Spalte created_at fehlt'
        );
        $this->assertTrue(
            Schema::hasColumn('paed_diary_user_hidden_categories', 'updated_at'),
            'Spalte updated_at fehlt'
        );
    }

    /** @test */
    public function permission_manage_global_paed_diary_categories_existiert(): void
    {
        $this->assertDatabaseHas('permissions', [
            'name'       => 'manage global paed diary categories',
            'guard_name' => 'web',
        ]);
    }

    /** @test */
    public function unique_constraint_verhindert_doppelte_eintraege(): void
    {
        $user = \App\Models\User::factory()->create();

        $category = \DB::table('paed_diary_categories')->insertGetId([
            'name'       => 'Testkategorie-' . uniqid(),
            'user_id'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('paed_diary_user_hidden_categories')->insert([
            'user_id'                 => $user->id,
            'paed_diary_category_id'  => $category,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('paed_diary_user_hidden_categories')->insert([
            'user_id'                 => $user->id,
            'paed_diary_category_id'  => $category,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
    }

    /** @test */
    public function cascade_delete_entfernt_eintraege_beim_loeschen_des_users(): void
    {
        $user = \App\Models\User::factory()->create();

        $category = \DB::table('paed_diary_categories')->insertGetId([
            'name'       => 'CascadeTest-' . uniqid(),
            'user_id'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('paed_diary_user_hidden_categories')->insert([
            'user_id'                => $user->id,
            'paed_diary_category_id' => $category,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // User model verwendet SoftDeletes – forceDelete() löst den FK-Cascade aus
        $user->forceDelete();

        $this->assertDatabaseMissing('paed_diary_user_hidden_categories', [
            'user_id'                => $user->id,
            'paed_diary_category_id' => $category,
        ]);
    }

    /** @test */
    public function cascade_delete_entfernt_eintraege_beim_loeschen_der_kategorie(): void
    {
        $user = \App\Models\User::factory()->create();

        $categoryId = \DB::table('paed_diary_categories')->insertGetId([
            'name'       => 'CascadeKatTest-' . uniqid(),
            'user_id'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('paed_diary_user_hidden_categories')->insert([
            'user_id'                => $user->id,
            'paed_diary_category_id' => $categoryId,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        \DB::table('paed_diary_categories')->where('id', $categoryId)->delete();

        $this->assertDatabaseMissing('paed_diary_user_hidden_categories', [
            'user_id'                => $user->id,
            'paed_diary_category_id' => $categoryId,
        ]);
    }
}

