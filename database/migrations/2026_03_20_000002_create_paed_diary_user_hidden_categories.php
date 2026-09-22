<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Hidden-Categories Pivot + Permission (TODO 2)
 *
 * Erstellt die Pivot-Tabelle paed_diary_user_hidden_categories, die speichert,
 * welche Notizkategorien ein User in der Wochenansicht ausgeblendet hat.
 *
 * Fügt zusätzlich die Permission 'manage global paed diary categories' ein.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Pivot-Tabelle ─────────────────────────────────────────────────
        if (!Schema::hasTable('paed_diary_user_hidden_categories')) {
            Schema::create('paed_diary_user_hidden_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('paed_diary_category_id');
                $table->timestamps();

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->foreign('paed_diary_category_id')
                      ->references('id')
                      ->on('paed_diary_categories')
                      ->onDelete('cascade');

                $table->unique(
                    ['user_id', 'paed_diary_category_id'],
                    'pduhc_user_cat_unique'
                );
            });
        }

        // ── Permission ────────────────────────────────────────────────────
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->insertOrIgnore([
                'name'       => 'manage global paed diary categories',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paed_diary_user_hidden_categories');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', 'manage global paed diary categories')
                ->where('guard_name', 'web')
                ->delete();
        }
    }
};

