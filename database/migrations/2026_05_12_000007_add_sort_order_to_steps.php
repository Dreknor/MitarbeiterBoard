<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: Reihenfolge-Spalte für Prozess-Schritte und Vorlagen-Schritte.
 * Ermöglicht Drag-&-Drop-Sortierung (B-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('procedure_steps', 'sort_order')) {
            Schema::table('procedure_steps', function (Blueprint $table) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('parent');
            });
            \Illuminate\Support\Facades\DB::statement('UPDATE procedure_steps SET sort_order = id');
        }

        if (!Schema::hasColumn('procedure_template_steps', 'sort_order')) {
            Schema::table('procedure_template_steps', function (Blueprint $table) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('parent_id');
            });
            \Illuminate\Support\Facades\DB::statement('UPDATE procedure_template_steps SET sort_order = id');
        }
    }

    public function down(): void
    {
        Schema::table('procedure_steps', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('procedure_template_steps', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};


