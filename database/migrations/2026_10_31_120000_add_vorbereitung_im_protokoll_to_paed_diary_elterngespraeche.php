<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachtrag: Die Spalte kam erst nachträglich in die Anlage-Migration der Elterngespräche.
 * Installationen, die diese schon ausgeführt hatten, bekommen sie hier (neue haben sie bereits).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paed_diary_elterngespraeche', 'vorbereitung_im_protokoll')) {
            Schema::table('paed_diary_elterngespraeche', function (Blueprint $table) {
                $table->boolean('vorbereitung_im_protokoll')->default(false)->after('vorbereitung_notizen');
            });
        }
    }

    public function down(): void
    {
        // Die Spalte gehört zur Anlage-Migration und wird mit deren Tabelle entfernt.
    }
};
