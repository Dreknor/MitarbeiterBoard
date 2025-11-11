<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('wiki_sites')) {
            return;
        }
        $oldTitle = 'Raum-Planungstool — Anleitung';
        $newTitle = 'Raumplanungstool — Anleitung';
        if (DB::table('wiki_sites')->where('title', $oldTitle)->exists()) {
            DB::table('wiki_sites')->where('title', $oldTitle)->update(['title' => $newTitle]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('wiki_sites')) {
            return;
        }
        $oldTitle = 'Raum-Planungstool — Anleitung';
        $newTitle = 'Raumplanungstool — Anleitung';
        if (DB::table('wiki_sites')->where('title', $newTitle)->exists()) {
            DB::table('wiki_sites')->where('title', $newTitle)->update(['title' => $oldTitle]);
        }
    }
};
